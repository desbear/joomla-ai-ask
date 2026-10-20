<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Gss\Component\Mcpask\Administrator\Agent\OfficeHours;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Whether a visitor asking for a person now would get a chat, and why not (0.1.0): for each
 * published AI Ask Desk agent, whether it is within its hours (a day closed, outside the hours, its
 * own time now and time zone), and whether anyone answering is online. Shown to the people
 * answering, so a form to leave a message never comes as a surprise.
 *
 * @since  0.1.0
 */
final class Availability
{
    public function __construct(private DatabaseInterface $db, private Staff $staff)
    {
    }

    /**
     * @return  array{open: bool, online: bool, agents: array<int, array{title: string, hours: bool, closedToday: bool, now: string, zone: string}>}
     */
    public function now(): array
    {
        $online = $this->staff->anyOnline();
        $agents = [];

        if (class_exists(OfficeHours::class)) {
            $db   = $this->db;
            $rows = $db->setQuery(
                $db->getQuery(true)->select('id, title, params')->from($db->quoteName('#__mcpask_agents'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('askdesk'))->where('published = 1')->order('ordering, id')
            )->loadObjectList();

            foreach ($rows as $row) {
                $params = new Registry((string) $row->params);
                $own    = new Registry($params->get('type', []));
                $zone   = trim((string) $own->get('timezone', '')) ?: (string) Factory::getApplication()->get('offset', 'UTC');

                try {
                    $local = (new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone($zone));
                } catch (\Exception) {
                    $local = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    $zone  = 'UTC';
                }

                $closedToday = (bool) preg_match('/^\s*' . preg_quote($local->format('Y-m-d'), '/') . '\b/m', (string) $own->get('closed', ''));

                $agents[] = [
                    'title'       => (string) $row->title,
                    'hours'       => OfficeHours::within($own, Factory::getDate('now', 'UTC')),
                    'closedToday' => $closedToday,
                    'now'         => $local->format('D H:i'),
                    'zone'        => $zone,
                ];
            }
        }

        return [
            'open'   => $online && (bool) array_filter($agents, static fn ($agent) => $agent['hours']),
            'online' => $online,
            'agents' => $agents,
        ];
    }
}
