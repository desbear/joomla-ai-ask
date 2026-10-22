<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  plg_mcpask_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Askdesk\Agent;

use Gss\Component\Askdesk\Administrator\Desk\Rooms;
use Gss\Component\Askdesk\Administrator\Desk\Settings as DeskSettings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Gss\Component\Mcpask\Administrator\Agent\AgentInterface;
use Gss\Component\Mcpask\Administrator\Agent\Card;
use Gss\Component\Mcpask\Administrator\Agent\Context;
use Gss\Component\Mcpask\Administrator\Agent\Handoffs;
use Gss\Component\Mcpask\Administrator\Agent\OfficeHours;
use Gss\Component\Mcpask\Administrator\Agent\Reply;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Passes the visitor on to a person of AI Ask Desk, in AI Ask's own window: the AI conversation so
 * far and the page go with them, and the chat is translated both ways. Within office hours with
 * someone online, the chat starts at once (the page talks with the desk from then on); otherwise
 * the visitor leaves a message for an answer by e-mail, or goes on with other agents.
 *
 * @since  0.1.0
 */
final class AskdeskAgent implements AgentInterface
{
    /** Of the desk's scripts, for browsers to fetch them again when they change. */
    private const VERSION = '0.5.1';

    public function type(): string
    {
        return 'askdesk';
    }

    public function title(): string
    {
        return Text::_('PLG_MCPASK_ASKDESK_TYPE');
    }

    public function description(): string
    {
        return Text::_('PLG_MCPASK_ASKDESK_TYPE_DESC');
    }

    public function available(): bool
    {
        return class_exists(Rooms::class) && ComponentHelper::isEnabled('com_askdesk');
    }

    public function form(): string
    {
        return \dirname(__DIR__, 2) . '/forms/agent.xml';
    }

    /**
     * Whether people answer now (for the light beside "Talk to a person"): within office hours,
     * with someone online in the console.
     */
    public function status(object $agent): string
    {
        return $this->open($agent) ? 'open' : 'closed';
    }

    public function start(Context $context): Reply
    {
        $own    = $context->own();
        $entry  = (array) ($context->state['entry'] ?? []);
        $hours  = OfficeHours::within($own, Factory::getDate('now', 'UTC'));
        $open   = $hours && $this->staff($context)->anyOnline();
        $key    = (new Handoffs($context->db))->create((int) $context->agent->id, $context->visitor, $entry, $hours, $context->language);
        $user   = Factory::getApplication()->getIdentity();
        $member = $user && !$user->guest;

        // A guest says who they are first: a name to be called by, an e-mail, perhaps a company
        if ($open && !$member && $own->get('guest_details', 1)) {
            $context->state = ['step' => 'details', 'handoff' => $key, 'entry' => $entry];
            $known          = $this->known();

            return new Reply([
                Card::text(trim((string) $own->get('details_text', '')) ?: Text::_('PLG_MCPASK_ASKDESK_DETAILS')),
                $this->detailsForm($known['name'], $known['email'], $known['company']),
            ]);
        }

        if ($open) {
            return $this->chat($context, $key, $entry, $member ? (string) $user->name : '', $member ? (string) $user->email : '', '');
        }

        return $this->away($context, $key, $entry);
    }

    /**
     * What a guest said of themselves, or the message they left.
     */
    public function handle(Context $context, array $input): Reply
    {
        $fields  = (array) ($input['fields'] ?? []);
        $name    = trim(mb_substr(strip_tags((string) ($fields['name'] ?? '')), 0, 100));
        $email   = trim((string) ($fields['email'] ?? ''));
        $company = trim(mb_substr(strip_tags((string) ($fields['company'] ?? '')), 0, 150));
        $entry   = (array) ($context->state['entry'] ?? []);
        $key     = (string) ($context->state['handoff'] ?? '');
        $user    = Factory::getApplication()->getIdentity();
        $member  = $user && !$user->guest;
        $errors  = [];

        if (!$member && $name === '') {
            $errors['name'] = Text::_('PLG_MCPASK_ASKDESK_ERROR_NAME');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = Text::_('PLG_MCPASK_ASKDESK_ERROR_EMAIL');
        }

        // Who the guest is, before the chat
        if (($context->state['step'] ?? '') === 'details') {
            if (!isset($fields['email']) || $errors) {
                return new Reply([$this->detailsForm($name, $email, $company, $errors)]);
            }

            $this->remember($name, $email, $company);

            // Someone may have gone meanwhile: then a message, as when no one is there
            if (OfficeHours::within($context->own(), Factory::getDate('now', 'UTC')) && $this->staff($context)->anyOnline()) {
                return $this->chat($context, $key, $entry, $name, $email, $company);
            }

            return $this->away($context, $key, $entry, $name, $email, $company);
        }

        // The message left: kept for the people answering, who reply by e-mail
        $message = trim((string) ($fields['message'] ?? ''));

        if (mb_strlen($message) < 2) {
            $errors['message'] = Text::_('PLG_MCPASK_ASKDESK_ERROR_MESSAGE');
        }

        if (!isset($fields['email'], $fields['message']) || $errors) {
            return new Reply([$this->messageForm($name, $email, $company, $message, $errors)]);
        }

        if (!$member) {
            $this->remember($name, $email, $company);
        }

        $this->rooms($context)->leave([
            'agent_id' => (int) $context->agent->id,
            'user_id'  => $member ? (int) $user->id : 0,
            'name'     => $name,
            'email'    => $email,
            'company'  => $company,
            'language' => $context->language,
            'page'     => (string) ($entry['page'] ?? ''),
            'entry'    => $entry + ['agent' => (string) $context->agent->title],
        ], $message);

        (new Handoffs($context->db))->event($key, $context->visitor, 'message', 'offline');
        $context->state = [];

        return new Reply([Card::done(trim((string) $context->own()->get('thanks_text', '')) ?: Text::_('PLG_MCPASK_ASKDESK_THANKS'))], true);
    }

    /**
     * The chat begins: a room, and the card the page talks with it through.
     */
    private function chat(Context $context, string $key, array $entry, string $name, string $email, string $company): Reply
    {
        $own    = $context->own();
        $desk   = DeskSettings::load();
        $user   = Factory::getApplication()->getIdentity();
        $member = $user && !$user->guest;
        $room   = $this->rooms($context)->open([
            'agent_id' => (int) $context->agent->id,
            'user_id'  => $member ? (int) $user->id : 0,
            'name'     => $name,
            'email'    => $email,
            'company'  => $company,
            'language' => $context->language,
            'page'     => (string) ($entry['page'] ?? ''),
            'entry'    => $entry + ['agent' => (string) $context->agent->title],
        ]);

        $context->state = [];
        $task = static fn (string $name) => Route::_('index.php?option=com_askdesk&task=chat.' . $name . '&format=raw', false);

        return new Reply([Card::chat($room['key'], $key, ['poll' => $task('poll'), 'send' => $task('message'), 'leave' => $task('leave'), 'rate' => $task('rate'), 'upload' => $task('upload'), 'stickers' => $task('stickers'), 'capture' => \Joomla\CMS\Uri\Uri::root(true) . '/media/com_askdesk/js/capture.js?v=' . self::VERSION], [
            'waiting' => trim((string) $own->get('waiting_text', '')) ?: (trim((string) $context->params()->get('opening', '')) ?: Text::_('PLG_MCPASK_ASKDESK_WAITING')),
            'joined'  => Text::_('PLG_MCPASK_ASKDESK_JOINED'),
            'closed'  => Text::_('PLG_MCPASK_ASKDESK_CLOSED'),
            'failed'  => Text::_('PLG_MCPASK_ASKDESK_FAILED'),
            'leave'   => Text::_('PLG_MCPASK_ASKDESK_LEAVE'),
            'notice'  => $this->notice($desk, $member),
        ])], true);
    }

    /**
     * No one there: a message to leave, other agents, or both.
     */
    private function away(Context $context, string $key, array $entry, string $name = '', string $email = '', string $company = ''): Reply
    {
        $own       = $context->own();
        $user      = Factory::getApplication()->getIdentity();
        $member    = $user && !$user->guest;
        $known     = $member ? ['name' => (string) $user->name, 'email' => (string) $user->email, 'company' => ''] : $this->known();
        $mode      = \in_array($own->get('away', 'message'), ['refer', 'both'], true) ? (string) $own->get('away') : 'message';
        $referrals = $mode !== 'message' ? $context->referrals() : [];
        $awayText  = trim((string) $own->get('away_text', '')) ?: Text::_('PLG_MCPASK_ASKDESK_AWAY');
        $agents    = $referrals ? Card::agents($awayText, $referrals, $context->chainOnward(), $key) : null;

        if ($mode === 'refer' && $agents) {
            $context->state = [];

            return new Reply([$agents], true);
        }

        $context->state = ['step' => 'message', 'handoff' => $key, 'entry' => $entry];

        return new Reply([
            Card::text(trim((string) $own->get('offline_text', '')) ?: Text::_('PLG_MCPASK_ASKDESK_OFFLINE')),
            $this->messageForm($name ?: $known['name'], $email ?: $known['email'], $company ?: $known['company'], ''),
            $agents,
        ]);
    }

    private function detailsForm(string $name, string $email, string $company, array $errors = []): array
    {
        return Card::fields('details', [
            ['name' => 'name', 'label' => Text::_('PLG_MCPASK_ASKDESK_FIELD_NICKNAME'), 'kind' => 'text', 'required' => true, 'value' => $name, 'error' => $errors['name'] ?? '', 'max' => 100],
            ['name' => 'email', 'label' => Text::_('PLG_MCPASK_ASKDESK_FIELD_EMAIL'), 'kind' => 'email', 'required' => true, 'value' => $email, 'error' => $errors['email'] ?? ''],
            ['name' => 'company', 'label' => Text::_('PLG_MCPASK_ASKDESK_FIELD_COMPANY'), 'kind' => 'text', 'value' => $company, 'max' => 150],
        ], Text::_('PLG_MCPASK_ASKDESK_START'));
    }

    private function messageForm(string $name, string $email, string $company, string $message, array $errors = []): array
    {
        $user   = Factory::getApplication()->getIdentity();
        $member = $user && !$user->guest;

        return Card::fields('message', [
            ['name' => 'name', 'label' => Text::_($member ? 'PLG_MCPASK_ASKDESK_FIELD_NAME' : 'PLG_MCPASK_ASKDESK_FIELD_NICKNAME'), 'kind' => 'text', 'required' => !$member, 'value' => $name, 'error' => $errors['name'] ?? '', 'max' => 100],
            ['name' => 'email', 'label' => Text::_('PLG_MCPASK_ASKDESK_FIELD_EMAIL'), 'kind' => 'email', 'required' => true, 'value' => $email, 'error' => $errors['email'] ?? ''],
            ['name' => 'company', 'label' => Text::_('PLG_MCPASK_ASKDESK_FIELD_COMPANY'), 'kind' => 'text', 'value' => $company, 'max' => 150],
            ['name' => 'message', 'label' => Text::_('PLG_MCPASK_ASKDESK_FIELD_MESSAGE'), 'kind' => 'textarea', 'required' => true, 'value' => $message, 'error' => $errors['message'] ?? '', 'max' => Rooms::MAX_TEXT],
        ], Text::_('PLG_MCPASK_ASKDESK_SEND'));
    }

    /**
     * What a guest said of themselves, kept in their session (not stored) to fill in the next time.
     *
     * @return  array{name: string, email: string, company: string}
     */
    private function known(): array
    {
        $kept = (array) Factory::getApplication()->getSession()->get('askdesk.guest', []);

        return ['name' => (string) ($kept['name'] ?? ''), 'email' => (string) ($kept['email'] ?? ''), 'company' => (string) ($kept['company'] ?? '')];
    }

    private function remember(string $name, string $email, string $company): void
    {
        Factory::getApplication()->getSession()->set('askdesk.guest', ['name' => $name, 'email' => $email, 'company' => $company]);
    }

    /**
     * What the visitor is told about keeping the chat: signed in, kept for the days set; a guest,
     * not kept.
     */
    private function notice(DeskSettings $desk, bool $member): string
    {
        $text = $member
            ? ($desk->noticeMember() ?: Text::sprintf('PLG_MCPASK_ASKDESK_NOTICE_MEMBER', $desk->keepDays()))
            : ($desk->noticeGuest() ?: Text::_('PLG_MCPASK_ASKDESK_NOTICE_GUEST'));

        $text = str_replace('{days}', (string) $desk->keepDays(), $text);

        if ($desk->privacyUrl() !== '') {
            $text .= ' [' . Text::_('PLG_MCPASK_ASKDESK_PRIVACY') . '](' . $desk->privacyUrl() . ')';
        }

        return $text;
    }

    private function open(object $agent): bool
    {
        if (!$this->available() || !OfficeHours::within($agent->own, Factory::getDate('now', 'UTC'))) {
            return false;
        }

        return (new Staff(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), DeskSettings::load()))->anyOnline();
    }

    private function staff(Context $context): Staff
    {
        return new Staff($context->db, DeskSettings::load());
    }

    private function rooms(Context $context): Rooms
    {
        return new Rooms($context->db, DeskSettings::load());
    }
}
