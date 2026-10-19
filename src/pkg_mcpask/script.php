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
