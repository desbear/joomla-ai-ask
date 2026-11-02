<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Gss\Component\Mcpask\Administrator\Agent\Agents;
use Gss\Component\Mcpask\Administrator\Agent\Handoffs;
use Gss\Component\Mcpask\Administrator\Agent\Report;
use Gss\Component\Mcpask\Administrator\Agent\Sessions;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * What AI Ask keeps of its visitors, forgotten as the options say (0.21.0): run every day by the
 * scheduled task "AI Ask - Forget old records" (and by the index's task), not only when someone
 * asks. When it last ran and what it removed is kept for the dashboard.
 *
 * @since  0.21.0
 */
final class Retention
{
    private const STATE = 'retention:last';

    public function __construct(private DatabaseInterface $db, private Settings $settings, private string $secret)
    {
    }

    /**
     * @return  array<string, int>  What was removed or made anonymous, by kind
     */
    public function run(): array
    {
        // The addresses due to be forgotten, sent first to whom the options name (0.22.6)
        $mailed   = (new AddressMail($this->db, $this->settings))->send();
        $log      = (new UsageLog($this->db, $this->settings, $this->secret))->prune();
        $handoffs = (new Handoffs($this->db))->forget($this->settings->handoffDays(), $this->settings->handoffContentDays());

        $counts = [
            'questions_deleted'  => $log['deleted'],
            'questions_cleared'  => $log['cleared'] + $mailed['sent'],
            'evidence_deleted'   => $log['evidence'],
            'handoffs_deleted'   => $handoffs['deleted'],
            'handoffs_cleared'   => $handoffs['cleared'],
            'agent_events'       => (new Agents($this->db))->forgetEvents($this->settings->agentEventDays()),
            'agent_sessions'     => (new Sessions($this->db, $this->secret))->forgetExpired(),
            'reports'            => (new Report($this->db, $this->settings, $this->secret))->forgetExpired(),
        ];

        (new State($this->db))->set(self::STATE, ['at' => Factory::getDate()->toSql(), 'counts' => $counts, 'mail' => $mailed]);

        return $counts;
    }

    /**
     * When it last ran (UTC, SQL format) and what it removed; null when it never did.
     *
     * @return  array{at: string, counts: array<string, int>, mail: array{sent: int, error: string}}|null
     */
    public static function last(DatabaseInterface $db): ?array
    {
        $last = (new State($db))->get(self::STATE);
        $mail = (array) ($last['mail'] ?? []);

        return !empty($last['at']) ? [
            'at'     => (string) $last['at'],
            'counts' => array_map('intval', (array) ($last['counts'] ?? [])),
            'mail'   => ['sent' => (int) ($mail['sent'] ?? 0), 'error' => (string) ($mail['error'] ?? '')],
        ] : null;
    }
}
