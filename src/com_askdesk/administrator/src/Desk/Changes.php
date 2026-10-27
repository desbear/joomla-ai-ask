<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Records changed from the dashboard, told to the others looking at them (0.6.2): which record of which
 * template of which source was changed (or deleted), by whom, when. Not what it became: each one shown it
 * reads it again as themselves (the source's own rights), so nothing reaches anyone who may not see it.
 * Kept an hour.
 *
 * @since  0.6.2
 */
final class Changes
{
    private const TABLE = '#__askdesk_source_changes';

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function note(string $source, string $template, string $row, int $userId, bool $deleted = false): void
    {
        $db  = $this->db;
        $now = Factory::getDate()->toSql();
        $obj = (object) [
            'source'   => mb_substr($source, 0, 30),
            'template' => mb_substr($template, 0, 100),
            'record'   => mb_substr($row, 0, 100),
            'user_id'  => $userId,
            'deleted'  => $deleted ? 1 : 0,
            'created'  => $now,
        ];

        try {
            $db->insertObject(self::TABLE, $obj, 'id');

            // Now and then, what is over an hour old goes
            if (random_int(1, 50) === 1) {
                $old = Factory::getDate('-1 hour')->toSql();
                $db->setQuery($db->getQuery(true)->delete($db->quoteName(self::TABLE))->where($db->quoteName('created') . ' < :old')->bind(':old', $old))->execute();
            }
        } catch (\Throwable) {
            // Not told: the others see it at their next look
        }
    }

    /**
     * What others changed after a point, in the templates watched; and the point to ask from next.
     *
     * @param   array<int, array{source: string, template: string}>  $watch
     *
     * @return  array{changes: array<int, array{source: string, template: string, row: string, deleted: bool}>, last: int}
     */
    public function after(int $after, array $watch, int $userId): array
    {
        $db = $this->db;

        try {
            $last = (int) $db->setQuery($db->getQuery(true)->select('MAX(' . $db->quoteName('id') . ')')->from($db->quoteName(self::TABLE)))->loadResult();
        } catch (\Throwable) {
            return ['changes' => [], 'last' => $after];
        }

        // The first ask (-1): from now on; nothing watched: only where now is
        if ($after < 0 || !$watch) {
            return ['changes' => [], 'last' => $after < 0 ? $last : max($after, $last)];
        }

        $keys = [];

        foreach (\array_slice($watch, 0, 50) as $item) {
            if (\is_array($item) && \is_string($item['source'] ?? null) && \is_scalar($item['template'] ?? null)) {
                $keys[$item['source'] . "\n" . $item['template']] = true;
            }
        }

        $rows = $db->setQuery(
            $db->getQuery(true)->select('id, source, template, record, deleted')->from($db->quoteName(self::TABLE))
                ->where($db->quoteName('id') . ' > :after')->where($db->quoteName('user_id') . ' <> :me')
                ->bind(':after', $after, ParameterType::INTEGER)->bind(':me', $userId, ParameterType::INTEGER)
                ->order($db->quoteName('id')),
            0,
            200
        )->loadObjectList();

        $changes = [];

        foreach ($rows as $row) {
            if (isset($keys[$row->source . "\n" . $row->template])) {
                $changes[] = ['source' => (string) $row->source, 'template' => (string) $row->template, 'row' => (string) $row->record, 'deleted' => (bool) $row->deleted];
            }
        }

        return ['changes' => $changes, 'last' => max($after, $rows ? (int) end($rows)->id : $last)];
    }
}
