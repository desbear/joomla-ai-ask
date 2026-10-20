<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  plg_mcpask_livechat
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Livechat\Agent;

use Gss\Component\Mcpask\Administrator\Agent\AgentInterface;
use Gss\Component\Mcpask\Administrator\Agent\Card;
use Gss\Component\Mcpask\Administrator\Agent\Context;
use Gss\Component\Mcpask\Administrator\Agent\Handoffs;
use Gss\Component\Mcpask\Administrator\Agent\OfficeHours;
use Gss\Component\Mcpask\Administrator\Agent\Reply;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Passes the visitor on to a person in LiveChat: the page opens LiveChat's own window (loaded only
 * now, not before), with what the visitor asked and the page they were on for the people answering.
 * When someone is there the visitor chats; when not (outside office hours, or no one online), they
 * leave a message in the same window, which LiveChat keeps as a ticket. Each handoff is logged.
 *
 * Its turn is the only one: the conversation goes back to questions straight away.
 *
 * @since  0.10.0
 */
final class LivechatAgent implements AgentInterface
{
    public function type(): string
    {
        return 'livechat';
    }

    public function title(): string
    {
        return Text::_('PLG_MCPASK_LIVECHAT_TYPE');
    }

    public function description(): string
    {
        return Text::_('PLG_MCPASK_LIVECHAT_TYPE_DESC');
    }

    public function available(): bool
    {
        return true;
    }

    public function form(): string
    {
        return \dirname(__DIR__, 2) . '/forms/agent.xml';
    }

    public function start(Context $context): Reply
    {
        $own     = $context->own();
        $license = (int) $own->get('license', 0);

        if ($license < 1) {
            return new Reply([Card::error(Text::_('PLG_MCPASK_LIVECHAT_NOT_SET'))], true);
        }

        $entry = (array) ($context->state['entry'] ?? []);
        $hours = self::withinHours($own, Factory::getDate('now', 'UTC'));
        $key   = (new Handoffs($context->db))->create((int) $context->agent->id, $context->visitor, $entry, $hours, $context->language);

        // What the people answering see beside the chat
        $carry     = self::carry($own);
        $variables = [Text::_('PLG_MCPASK_LIVECHAT_VAR_FROM') => (string) $context->agent->title];

        // The whole conversation before the handoff, in parts the chat window shows whole
        if (\in_array('conversation', $carry, true) && !empty($entry['conversation'])) {
            $lines = array_map(
                static fn ($turn) => Text::_($turn['role'] === 'user' ? 'PLG_MCPASK_LIVECHAT_VAR_VISITOR' : 'PLG_MCPASK_LIVECHAT_VAR_AI') . '：' . self::clip((string) $turn['text'], 1500),
                (array) $entry['conversation']
            );
            $parts = self::parts(implode("\n\n", $lines), 950, 8);

            foreach ($parts as $index => $part) {
                $variables[Text::_('PLG_MCPASK_LIVECHAT_VAR_CONVERSATION') . (\count($parts) > 1 ? ' ' . ($index + 1) . '/' . \count($parts) : '')] = $part;
            }
        }

        foreach (['question' => 'QUESTION', 'answer' => 'ANSWER', 'page' => 'PAGE', 'report' => 'REPORT'] as $name => $label) {
            if (\in_array($name, $carry, true) && trim((string) ($entry[$name] ?? '')) !== '') {
                $variables[Text::_('PLG_MCPASK_LIVECHAT_VAR_' . $label)] = self::clip((string) $entry[$name], $name === 'answer' ? 600 : 500);
            }
        }

        // Signed-in visitors need not give their name again
        $user     = Factory::getApplication()->getIdentity();
        $customer = $user && !$user->guest ? ['name' => (string) $user->name, 'email' => (string) $user->email] : [];

        $opening = trim((string) $context->params()->get('opening', ''));
        $texts   = [
            'waiting' => $opening !== '' ? $opening : Text::_('PLG_MCPASK_LIVECHAT_WAITING'),
            'online'  => trim((string) $own->get('online_text', '')) ?: Text::_('PLG_MCPASK_LIVECHAT_ONLINE'),
            'offline' => trim((string) $own->get('offline_text', '')) ?: Text::_('PLG_MCPASK_LIVECHAT_OFFLINE'),
            'failed'  => Text::_('PLG_MCPASK_LIVECHAT_FAILED'),
            'button'  => Text::_('PLG_MCPASK_LIVECHAT_REOPEN'),
        ];

        $context->state = [];

        // No one there to chat: other agents instead of a message to leave, or beside it (0.17.0).
        // Outside office hours that is known here; within them, only when LiveChat says so.
        $mode      = \in_array($own->get('away', 'message'), ['refer', 'both'], true) ? (string) $own->get('away') : 'message';
        $referrals = $mode !== 'message' ? $context->referrals() : [];
        $awayText  = trim((string) $own->get('away_text', '')) ?: Text::_('PLG_MCPASK_LIVECHAT_AWAY');
        $texts['away'] = $awayText;

        if ($referrals && !$hours) {
            $agents = Card::agents($awayText, $referrals, $context->chainOnward(), $key);

            return $mode === 'refer'
                ? new Reply([$agents], true)
                : new Reply([Card::handoff($key, $license, (int) $own->get('group', 0), $hours, $texts, $variables, $customer), $agents], true);
        }

        $away = $referrals ? ['mode' => $mode, 'agents' => $referrals, 'chain' => $context->chainOnward()] : null;

        return new Reply([Card::handoff($key, $license, (int) $own->get('group', 0), $hours, $texts, $variables, $customer, $away)], true);
    }

    /**
     * Whether people answer now, as far as the office hours tell (0.17.0): "open" or "closed".
     * Whether someone is online only LiveChat's own window can tell.
     */
    public function status(object $agent): string
    {
        return self::withinHours($agent->own, Factory::getDate('now', 'UTC')) ? 'open' : 'closed';
    }

    public function handle(Context $context, array $input): Reply
    {
        // Nothing comes after the window is opened
        return new Reply([], true);
    }

    /**
     * Whether the moment is within the office hours set (see OfficeHours, where this moved in 0.20.0).
     */
    public static function withinHours(Registry $own, \DateTimeInterface $now): bool
    {
        return OfficeHours::within($own, $now);
    }

    /**
     * @return  string[]  What is passed on: question, answer, page, report
     */
    private static function carry(Registry $own): array
    {
        $carry = $own->get('carry', ['conversation', 'page', 'report']);

        return \is_array($carry) ? array_map('strval', $carry) : array_map('trim', explode(',', (string) $carry));
    }

    /**
     * A long text in parts of at most $length characters (cut between lines where possible), the
     * newest kept when there are more than $max.
     *
     * @return  string[]
     */
    private static function parts(string $text, int $length, int $max): array
    {
        // Lines longer than a part are cut first
        $pieces = [];

        foreach (preg_split('/\n/u', $text) ?: [] as $line) {
            do {
                $pieces[] = mb_substr($line, 0, $length);
                $line     = mb_substr($line, $length);
            } while ($line !== '');
        }

        // Then put together as long as they fit
        $parts   = [];
        $current = '';

        foreach ($pieces as $piece) {
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($piece) > $length) {
                $parts[] = $current;
                $current = '';
            }

            $current = $current === '' ? $piece : $current . "\n" . $piece;
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return \array_slice(array_values(array_filter(array_map('trim', $parts), static fn ($part) => $part !== '')), -$max);
    }

    private static function clip(string $text, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
