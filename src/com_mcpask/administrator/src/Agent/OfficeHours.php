<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Agent;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Office hours of agents that pass visitors on to people (LiveChat, AI Ask Desk): the hours of each
 * day of the week, days closed and weekend days worked, in the agent's time zone or the site's.
 * The fields are "timezone", "hours", "closed" and "open_days" of the agent's own settings.
 *
 * @since  0.20.0
 */
final class OfficeHours
{
    /**
     * Whether the moment is within the office hours set (in the agent's time zone, or the site's),
     * and not a day set as closed. No hours set means always.
     */
    public static function within(Registry $own, \DateTimeInterface $now): bool
    {
        $zone = trim((string) $own->get('timezone', '')) ?: (string) Factory::getApplication()->get('offset', 'UTC');

        try {
            $local = (new \DateTimeImmutable('@' . $now->getTimestamp()))->setTimezone(new \DateTimeZone($zone));
        } catch (\Exception) {
            $local = new \DateTimeImmutable('@' . $now->getTimestamp());
        }

        $today = $local->format('Y-m-d');

        foreach (preg_split('/\R/', (string) $own->get('closed', '')) ?: [] as $line) {
            if (preg_match('/^\s*(\d{4}-\d{2}-\d{2})/', $line, $match) && $match[1] === $today) {
                return false;
            }
        }

        $rows = array_values(array_filter(array_map(static fn ($row) => (array) $row, (array) $own->get('hours', []))));
        $day  = (string) $local->format('N');
        $time = $local->format('H:i');

        // A day worked although it is not one of the week's (a weekend made up for a holiday): its
        // own hours when given ("09:00-12:00"), otherwise those of a working day of the week
        foreach (preg_split('/\R/', (string) $own->get('open_days', '')) ?: [] as $line) {
            if (!preg_match('/^\s*(\d{4}-\d{2}-\d{2})(.*)$/', $line, $match) || $match[1] !== $today) {
                continue;
            }

            preg_match_all('/\b(\d{1,2}:\d{2})\s*[-–~]\s*(\d{1,2}:\d{2})\b/u', $match[2], $ranges, PREG_SET_ORDER);

            if ($ranges) {
                foreach ($ranges as [, $from, $to]) {
                    if (self::time($from) !== '' && self::time($to) !== '' && $time >= self::time($from) && $time < self::time($to)) {
                        return true;
                    }
                }

                return false;
            }

            if (!$rows) {
                return true;
            }

            // The hours of the first working day of the week that has any (Monday, usually)
            foreach (['1', '2', '3', '4', '5'] as $weekday) {
                foreach ($rows as $row) {
                    if (\in_array($weekday, array_map('strval', (array) ($row['days'] ?? [])), true)) {
                        $day = $weekday;

                        break 2;
                    }
                }
            }

            break;
        }

        if (!$rows) {
            return true;
        }

        foreach ($rows as $row) {
            $from = self::time($row['from'] ?? '');
            $to   = self::time($row['to'] ?? '');

            if (\in_array($day, array_map('strval', (array) ($row['days'] ?? [])), true) && $from !== '' && $to !== '' && $time >= $from && $time < $to) {
                return true;
            }
        }

        return false;
    }

    private static function time(string $value): string
    {
        return preg_match('/^([01]?\d|2[0-4]):([0-5]\d)$/', trim($value), $match) ? sprintf('%02d:%s', $match[1], $match[2]) : '';
    }
}
