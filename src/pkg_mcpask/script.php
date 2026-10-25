<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  pkg_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {
    private string $minimumJoomla = '4.4.0';

    private string $minimumPhp = '8.1.0';

    /** @var array<int, array{0: string, 1: string}>  Plugins (group, element) that were not installed before this run */
    private array $newPlugins = [];

    /** Plugins enabled when they are first installed. */
    private const PLUGINS = [['content', 'mcpask'], ['task', 'mcpask'], ['easyblog', 'mcpask'], ['mcpask', 'rsform'], ['mcpask', 'livechat'], ['mcp', 'mcpask']];

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, $this->minimumPhp, '<') || version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_MCPASK_REQUIRES', $this->minimumJoomla, $this->minimumPhp), 'error');

            return false;
        }

        // Which plugins this run adds, so they can be switched on without touching the others
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach (self::PLUGINS as [$folder, $element]) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));

            if (!(int) $db->setQuery($query)->loadResult()) {
                $this->newPlugins[] = [$folder, $element];
            }
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type !== 'uninstall') {
            $this->allowAsking();
            $this->completeSchema((string) $adapter->getManifest()->version);
            $this->sealSecrets();
            $this->markEvidence();
            $this->maskKept();
        }

        // Never re-enable what an administrator disabled: only plugins this run added are switched on
        if (!$this->newPlugins) {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($this->newPlugins as [$folder, $element]) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = :folder')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':folder', $folder)
                ->bind(':element', $element);

            $db->setQuery($query)->execute();
        }

        // Joomla caches the list of plugins; without clearing it the new ones stay unseen
        foreach (['com_plugins', '_system'] as $group) {
            try {
                Factory::getCache($group, '')->clean();
            } catch (\Throwable) {
                // A failing cache clean must not fail the installation
            }
        }

        if ($type === 'install' || $type === 'discover_install') {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_MCPASK_INSTALLED', 'index.php?option=com_mcpask'), 'message');
        }

        return true;
    }

    /**
     * The database statements of this version, run again one by one: a site may have had an
     * earlier build of the same version, after which Joomla takes them all as done, also those added
     * since. What is there already (a table, a column) fails on its own and is passed over.
     */
    private function completeSchema(string $version): void
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $type = str_contains(strtolower($db->getServerType()), 'postgres') ? 'postgresql' : 'mysql';
            $file = JPATH_ADMINISTRATOR . '/components/com_mcpask/sql/updates/' . $type . '/' . basename($version) . '.sql';
        } catch (\Throwable) {
            return;
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !is_file($file)) {
            return;
        }

        foreach (preg_split('/;\s*$/m', (string) file_get_contents($file)) ?: [] as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            try {
                $db->setQuery($statement)->execute();
            } catch (\Throwable) {
                // Done already
            }
        }
    }

    /**
     * Secrets kept in plain text before 0.20.8, encrypted once (Crypto): the component's AI service
     * keys, export key and peers' keys, the secret of modules showing another site's AI Ask, the
     * image plugin's key, embed keys' secrets. What is sealed already is left as it is.
     */
    private function sealSecrets(): void
    {
        $crypto = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Crypto';
        $file   = JPATH_ADMINISTRATOR . '/components/com_mcpask/src/Ask/Crypto.php';

        try {
            if (!class_exists($crypto) && is_file($file)) {
                require_once $file;
            }

            if (!class_exists($crypto)) {
                return;
            }

            $db     = Factory::getContainer()->get(DatabaseInterface::class);
            $sealed = 0;
            $seal   = static function (mixed $value) use ($crypto, &$sealed): mixed {
                if (!\is_string($value) || trim($value) === '' || $crypto::isSealed(trim($value))) {
                    return $value;
                }

                $sealed++;

                return $crypto::seal($value);
            };

            // Params of the component, the image plugin and the AI Ask modules
            $rows = [
                ['#__extensions', 'extension_id', $db->quoteName('type') . ' = ' . $db->quote('component') . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('com_mcpask'),
                    static function (array $params) use ($seal): array {
                        foreach (['api_key', 'fallback_api_key', 'export_key'] as $name) {
                            if (isset($params[$name])) {
                                $params[$name] = $seal($params[$name]);
                            }
                        }

                        foreach ((array) ($params['peers'] ?? []) as $row => $peer) {
                            if (\is_array($peer) && isset($peer['key'])) {
                                $params['peers'][$row]['key'] = $seal($peer['key']);
                            }
                        }

                        return $params;
                    }],
                ['#__extensions', 'extension_id', $db->quoteName('type') . ' = ' . $db->quote('plugin') . ' AND ' . $db->quoteName('folder') . ' = ' . $db->quote('mcpask') . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('imagegen'),
                    static fn (array $params): array => isset($params['key']) ? ['key' => $seal($params['key'])] + $params : $params],
                ['#__modules', 'id', $db->quoteName('module') . ' = ' . $db->quote('mod_mcp_ask'),
                    static fn (array $params): array => isset($params['remote_secret']) ? ['remote_secret' => $seal($params['remote_secret'])] + $params : $params],
            ];

            foreach ($rows as [$table, $key, $where, $change]) {
                foreach ($db->setQuery($db->getQuery(true)->select([$db->quoteName($key, 'id'), $db->quoteName('params')])->from($db->quoteName($table))->where($where))->loadObjectList() as $row) {
                    $params = json_decode((string) $row->params, true);

                    if (!\is_array($params)) {
                        continue;
                    }

                    $before  = $sealed;
                    $changed = $change($params);

                    if ($sealed > $before) {
                        $db->setQuery($db->getQuery(true)->update($db->quoteName($table))
                            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($changed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))
                            ->where($db->quoteName($key) . ' = ' . (int) $row->id))->execute();
                    }
                }
            }

            // Embed keys' secrets (the column is wide enough since 0.20.8)
            foreach ($db->setQuery($db->getQuery(true)->select(['id', 'secret'])->from($db->quoteName('#__mcpask_embeds')))->loadObjectList() as $row) {
                $value = $seal((string) $row->secret);

                if ($value !== $row->secret) {
                    $db->setQuery($db->getQuery(true)->update($db->quoteName('#__mcpask_embeds'))
                        ->set($db->quoteName('secret') . ' = ' . $db->quote($value))->where('id = ' . (int) $row->id))->execute();
                }
            }

            if ($sealed > 0) {
                Factory::getApplication()->enqueueMessage(Text::plural('PKG_MCPASK_SECRETS_SEALED', $sealed), 'message');
            }
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_MCPASK_SECRETS_NOT_SEALED', $e->getMessage()), 'warning');
        }
    }

    /**
     * Before 0.21.0 attacks were kept like any question: those that read as one (Suspicion), and the
     * other questions from the same address on the same day, become evidence; then the address is
     * taken from every other question (from 0.21.0 an ordinary question never keeps it).
     */
    private function markEvidence(): void
    {
        $class = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Suspicion';
        $file  = JPATH_ADMINISTRATOR . '/components/com_mcpask/src/Ask/Suspicion.php';

        try {
            if (!class_exists($class) && is_file($file)) {
                require_once $file;
            }

            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $table = $db->quoteName('#__mcpask_log');
            $flag  = static fn (string $flags, string $flag): string => implode(',', array_unique(array_filter([...explode(',', $flags), $flag])));

            if (class_exists($class)) {
                $last = 0;

                do {
                    $rows = $db->setQuery($db->getQuery(true)->select('id, question, feedback, flags')->from($table)
                        ->where('evidence = 0')->where('id > ' . $last)->order('id')->setLimit(500))->loadObjectList();

                    foreach ($rows as $row) {
                        $last = (int) $row->id;

                        if ($class::of((string) $row->question) !== null || $class::of((string) $row->feedback) !== null) {
                            $update = (object) ['id' => $last, 'evidence' => 1, 'flags' => mb_substr($flag((string) $row->flags, 'suspicious'), 0, 255)];
                            $db->updateObject('#__mcpask_log', $update, 'id');
                        }
                    }
                } while (\count($rows) === 500);
            }

            // The same address's other questions of that day
            $days = $db->setQuery($db->getQuery(true)->select('ip, created')->from($table)
                ->where('evidence = 1')->where('ip <> ' . $db->quote('')))->loadObjectList();
            $done = [];

            foreach ($days as $day) {
                $key = $day->ip . '|' . substr((string) $day->created, 0, 10);

                if (isset($done[$key])) {
                    continue;
                }

                $done[$key] = true;
                $from       = substr((string) $day->created, 0, 10) . ' 00:00:00';
                $to         = substr((string) $day->created, 0, 10) . ' 23:59:59';

                foreach ($db->setQuery($db->getQuery(true)->select('id, flags')->from($table)->where('evidence = 0')
                    ->where('ip = :ip')->where('created BETWEEN :from AND :to')
                    ->bind(':ip', $day->ip)->bind(':from', $from)->bind(':to', $to))->loadObjectList() as $row) {
                    $update = (object) ['id' => (int) $row->id, 'evidence' => 1, 'flags' => mb_substr($flag((string) $row->flags, 'linked'), 0, 255)];
                    $db->updateObject('#__mcpask_log', $update, 'id');
                }
            }

            $db->setQuery('UPDATE ' . $table . ' SET ' . $db->quoteName('ip') . ' = ' . $db->quote('') . ' WHERE ' . $db->quoteName('evidence') . ' = 0')->execute();
        } catch (\Throwable) {
            // Marked from now on; what was kept before stays as it was
        }
    }

    /**
     * What visitors wrote and was kept before 0.21.0, with e-mail addresses, telephone, ID and card
     * numbers and passwords hidden (Masker), as is done since when it is kept: questions, reasons
     * given with ratings, handoffs' questions. An attack's question is kept as it was as evidence.
     */
    private function maskKept(): void
    {
        $masker = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Masker';
        $file   = JPATH_ADMINISTRATOR . '/components/com_mcpask/src/Ask/Masker.php';

        try {
            if (!class_exists($masker) && is_file($file)) {
                require_once $file;
            }

            if (!class_exists($masker)) {
                return;
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // An attack's words as they came, before they are masked
            $db->setQuery('UPDATE ' . $db->quoteName('#__mcpask_log') . ' SET ' . $db->quoteName('question_raw') . ' = ' . $db->quoteName('question')
                . ' WHERE ' . $db->quoteName('evidence') . ' = 1 AND ' . $db->quoteName('question_raw') . ' IS NULL AND ' . $db->quoteName('question') . ' IS NOT NULL')->execute();

            $mask  = static fn (string $text): string => $masker::mask($text);
            // A page without its query or fragment: they can carry keys
            $plain = static fn (string $url): string => (string) strtok((string) strtok($url, '#'), '?');

            foreach ([['#__mcpask_log', ['question' => $mask, 'feedback' => $mask]], ['#__mcpask_handoffs', ['question' => $mask, 'page' => $plain]]] as [$table, $changes]) {
                $columns = array_keys($changes);
                $last = 0;

                do {
                    $rows = $db->setQuery(
                        $db->getQuery(true)->select(array_merge(['id'], $columns))->from($db->quoteName($table))->where('id > ' . $last)->order('id')->setLimit(500)
                    )->loadObjectList();

                    foreach ($rows as $row) {
                        $last   = (int) $row->id;
                        $update = (object) ['id' => $last];

                        foreach ($changes as $column => $change) {
                            if ($row->$column !== null && $row->$column !== '' && ($changed = $change((string) $row->$column)) !== $row->$column) {
                                $update->$column = $changed;
                            }
                        }

                        if (\count((array) $update) > 1) {
                            $db->updateObject($table, $update, 'id');
                        }
                    }
                } while (\count($rows) === 500);
            }
        } catch (\Throwable) {
            // Masked when next forgotten anyway: questions' words go after a fortnight
        }
    }

    /**
     * Everyone may ask, as before there was a permission for it, until an administrator says
     * otherwise: Public is allowed "Ask questions" when no group has a rule for it yet.
     */
    private function allowAsking(): void
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('id, rules')
                ->from($db->quoteName('#__assets'))
                ->where($db->quoteName('name') . ' = ' . $db->quote('com_mcpask'));
            $asset = $db->setQuery($query)->loadObject();

            if (!$asset) {
                return;
            }

            $rules = json_decode((string) $asset->rules, true) ?: [];

            if (!empty($rules['mcpask.ask'])) {
                return;
            }

            $rules['mcpask.ask'] = ['1' => 1];
            $update              = (object) ['id' => (int) $asset->id, 'rules' => json_encode($rules)];

            $db->updateObject('#__assets', $update, 'id');
        } catch (\Throwable) {
            // Without the rule the component still lets everyone ask
        }
    }
};
