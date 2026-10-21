<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * What the site has kept of its visitors, cleared in one go: to start over, or when another site
 * takes over answering. Settings, agents, embed keys, pets, source rules and the index stay.
 *
 * @since  0.20.4
 */
final class Reset
{
    /** The kinds of records, and their tables */
    public const KINDS = [
        'questions' => ['#__mcpask_log'],
        'handoffs'  => ['#__mcpask_handoffs'],
        'agents'    => ['#__mcpask_agent_events', '#__mcpask_agent_sessions'],
        'reports'   => ['#__mcpask_reports'],
    ];

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * @return  array<string, int>  How many records of each kind there are
     */
    public function counts(): array
    {
        $counts = [];

        foreach (self::KINDS as $kind => $tables) {
            $counts[$kind] = 0;

            foreach ($tables as $table) {
                try {
                    $counts[$kind] += (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName($table)))->loadResult();
                } catch (\Throwable) {
                    // A table not there yet
                }
            }
        }

        return $counts;
    }

    /**
     * @param   string[]  $kinds
     *
     * @return  array<string, int>  How many records of each kind were removed
     */
    public function clear(array $kinds): array
    {
        $before  = $this->counts();
        $cleared = [];

        foreach (array_intersect(array_keys(self::KINDS), $kinds) as $kind) {
            foreach (self::KINDS[$kind] as $table) {
                $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName($table)))->execute();
            }

            $cleared[$kind] = $before[$kind];
        }

        return $cleared;
    }
}
