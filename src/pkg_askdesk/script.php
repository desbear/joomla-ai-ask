<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  pkg_askdesk
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

    /** The AI Ask the desk works with: the window visitors chat in, its agents, its AI service. */
    private string $minimumAsk = '0.20.0';

    private bool $newPlugin = false;

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
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_ASKDESK_REQUIRES', $this->minimumJoomla, $this->minimumPhp), 'error');

            return false;
        }

        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $ask = $db->setQuery(
            $db->getQuery(true)->select('manifest_cache')->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))->where($db->quoteName('element') . ' = ' . $db->quote('com_mcpask'))
        )->loadResult();
        $version = (string) (json_decode((string) $ask, true)['version'] ?? '');

        if ($version === '' || version_compare($version, $this->minimumAsk, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_ASKDESK_REQUIRES_ASK', $this->minimumAsk), 'error');

            return false;
        }

        $this->newPlugin = !(int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))->where($db->quoteName('folder') . ' = ' . $db->quote('mcpask'))->where($db->quoteName('element') . ' = ' . $db->quote('askdesk'))
        )->loadResult();

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type !== 'uninstall') {
            $this->repairTables();
        }

        // Never re-enable what an administrator disabled: only a plugin this run added is switched on
        if ($type === 'uninstall' || !$this->newPlugin) {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))->where($db->quoteName('folder') . ' = ' . $db->quote('mcpask'))->where($db->quoteName('element') . ' = ' . $db->quote('askdesk'))
        )->execute();

        foreach (['com_plugins', '_system'] as $group) {
            try {
                Factory::getCache($group, '')->clean();
            } catch (\Throwable) {
                // A failing cache clean must not fail the installation
            }
        }

        Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_ASKDESK_INSTALLED', 'index.php?option=com_askdesk'), 'message');

        return true;
    }

    /**
     * The desk's tables as its install script makes them, on every install and update: a table not
     * there is made, a column not there is added. A site that had an earlier build of the same
     * version (whose update scripts Joomla does not run again) gets what it lacks; nothing there is
     * changed or removed.
     */
    private function repairTables(): void
    {
        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $driver = str_contains(strtolower($db->getServerType()), 'postgres') ? 'postgresql' : 'mysql';
        $file   = JPATH_ADMINISTRATOR . '/components/com_askdesk/sql/install.' . $driver . '.utf8.sql';

        if (!is_file($file)) {
            return;
        }

        $prefix = $db->getPrefix();
        $tables = array_map('strtolower', $db->getTableList());
        $sql    = (string) file_get_contents($file);
        $quote  = $driver === 'mysql' ? '`' : '"';

        preg_match_all('/CREATE TABLE IF NOT EXISTS [`"]#__(\w+)[`"] \((.*?)\n\)[^;]*;/s', $sql, $creates, PREG_SET_ORDER);

        foreach ($creates as [$statement, $name, $body]) {
            $table = $prefix . $name;

            try {
                if (!\in_array(strtolower($table), $tables, true)) {
                    $db->setQuery(str_replace('#__', $prefix, $statement))->execute();

                    // Its indexes, written after it (PostgreSQL)
                    preg_match_all('/CREATE INDEX [^;]* ON [`"]#__' . preg_quote($name, '/') . '[`"][^;]*;/', $sql, $indexes);

                    foreach ($indexes[0] as $index) {
                        $db->setQuery(str_replace('#__', $prefix, $index))->execute();
                    }

                    continue;
                }

                $have = array_map('strtolower', array_keys($db->getTableColumns($table, false)));

                foreach (preg_split('/\R/', $body) ?: [] as $line) {
                    if (preg_match('/^\s*' . preg_quote($quote, '/') . '(\w+)' . preg_quote($quote, '/') . '\s+(.+?),?\s*$/', $line, $column)
                        && !\in_array(strtolower($column[1]), $have, true)
                        && !preg_match('/\b(serial|AUTO_INCREMENT)\b/i', $column[2])) {
                        $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' ADD COLUMN ' . $quote . $column[1] . $quote . ' ' . $column[2])->execute();
                    }
                }
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            }
        }
    }
};
