<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Alerts;
use Gss\Component\Askdesk\Administrator\Desk\Rooms;
use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Tickets;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The visitor's tickets (0.6.2), from the tickets page: a code asked for ({email}) and given ({email, code}),
 * the tickets ({}), one of them ({id}), more written to it ({id, text}), and leaving ({}). A guest sees those
 * of the address whose code they gave, for as long as this browser's session lasts; a member signed in sees
 * their own.
 *
 * @since  0.6.2
 */
class TicketsController extends BaseController
{
    use Json;

    private const SESSION = 'askdesk.tickets';

    /** AI Ask's visitor signed in at an account centre with no account here (0.22.1) */
    private const VISITOR = 'Gss\Component\Mcpask\Administrator\Embed\Visitor';

    /** How long a code given holds, unused */
    private const HOLD_SECONDS = 7200;

    public function code(): void
    {
        $body  = $this->check();
        $email = Tickets::address(\is_string($body['email'] ?? null) ? $body['email'] : '');

        if ($email === '') {
            $this->send(['error' => Text::_('COM_ASKDESK_TICKETS_BAD_EMAIL')]);
        }

        $asked = $this->tickets()->ask($email, (string) $this->app->getInput()->server->getString('REMOTE_ADDR', ''));

        // Whether the address has tickets is not told: the same words when it has none
        $this->send($asked === 'busy' ? ['error' => Text::_('COM_ASKDESK_TICKETS_BUSY')] : ['sent' => true, 'text' => Text::sprintf('COM_ASKDESK_TICKETS_CODE_SENT', $email)]);
    }

    public function verify(): void
    {
        $body  = $this->check();
        $email = Tickets::address(\is_string($body['email'] ?? null) ? $body['email'] : '');

        if (!$this->tickets()->verify($email, \is_string($body['code'] ?? null) ? $body['code'] : '')) {
            $this->send(['error' => Text::_('COM_ASKDESK_TICKETS_BAD_CODE')]);
        }

        // A new session for what the code opened, so no one who knew the old one shares it
        $session = $this->app->getSession();
        $session->fork();
        $session->set(self::SESSION, ['email' => $email, 'at' => time()]);

        $this->send(['ok' => true, 'token' => Session::getFormToken()] + $this->listed());
    }

    public function list(): void
    {
        $this->check();

        $this->send($this->listed());
    }

    public function room(): void
    {
        $body = $this->check();
        $room = $this->ticket($body);

        $this->send($this->shown($room));
    }

    public function write(): void
    {
        $body  = $this->check();
        $room  = $this->ticket($body);
        $rooms = new Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());

        if (!Tickets::writable($room)) {
            $this->send(['error' => Text::_('COM_ASKDESK_TICKETS_CLOSED')]);
        }

        try {
            $rooms->fromVisitor($room, \is_string($body['text'] ?? null) ? $body['text'] : '', true);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        // The one who has it told by e-mail too (their phone is told at once)
        (new Alerts(Factory::getContainer()->get(DatabaseInterface::class), Settings::load()))->later((int) $room->id, 'more');

        $this->send($this->shown($rooms->get((int) $room->id) ?? $room));
    }

    public function out(): void
    {
        $this->check();
        $this->app->getSession()->remove(self::SESSION);

        // Signed in at an account centre through AI Ask: out of that too, or the address would stay
        $visitor = self::VISITOR;

        if (class_exists($visitor)) {
            $visitor::forget();
        }

        $this->send(['ok' => true]);
    }

    // ---------- Underneath ----------

    /**
     * @return  array{who: array{email: string, member: string}, tickets: array<int, array<string, mixed>>}
     */
    private function listed(): array
    {
        [$email, $user] = $this->who();
        $tickets        = $this->tickets();
        $seen           = [];
        $items          = [];

        foreach (array_merge($user ? $tickets->of('', (int) $user->id) : [], $email !== '' ? $tickets->of($email) : []) as $room) {
            if (isset($seen[(int) $room->id])) {
                continue;
            }

            $seen[(int) $room->id] = true;
            $items[]               = [
                'id'       => (int) $room->id,
                'title'    => $this->firstWords($room),
                'created'  => self::time((string) $room->created),
                'modified' => self::time((string) $room->modified),
                'open'     => Tickets::writable($room),
                'answered' => (string) $room->last_author === 'staff',
            ];
        }

        return ['who' => ['email' => $email, 'member' => $user ? (string) $user->name : ''], 'tickets' => $items];
    }

    /**
     * @return  array<string, mixed>
     */
    private function shown(object $room): array
    {
        $rooms = new Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());

        return ['id' => (int) $room->id, 'open' => Tickets::writable($room), 'created' => self::time((string) $room->created)] + $rooms->forTicket($room);
    }

    private function ticket(array $body): object
    {
        [$email, $user] = $this->who();
        $rooms          = new Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $room           = $rooms->get((int) ($body['id'] ?? 0));

        if (!$room || !Tickets::belongs($room, $email, $user ? (int) $user->id : 0)) {
            $this->send(['error' => Text::_('COM_ASKDESK_TICKETS_GONE'), 'gone' => true]);
        }

        return $room;
    }

    /**
     * The address whose code was given here (while it holds), and the member signed in.
     *
     * @return  array{0: string, 1: ?object}
     */
    private function who(): array
    {
        $kept  = (array) $this->app->getSession()->get(self::SESSION, []);
        $email = isset($kept['email'], $kept['at']) && (int) $kept['at'] > time() - self::HOLD_SECONDS ? (string) $kept['email'] : '';
        $user  = $this->app->getIdentity();

        if ($email !== '') {
            // Kept while used
            $this->app->getSession()->set(self::SESSION, ['email' => $email, 'at' => time()]);
        } elseif (class_exists($visitor = self::VISITOR) && ($signed = $visitor::current())) {
            // Signed in at an account centre through AI Ask (no account here): the address it vouches for, with no code
            $email = $signed['email'];
        }

        return [$email, $user && !$user->guest ? $user : null];
    }

    /**
     * @return  array<string, mixed>
     */
    private function check(): array
    {
        $body = $this->body();

        if (!Settings::load()->tickets()) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
        }

        if (!hash_equals(Session::getFormToken(), $this->app->getInput()->server->getString('HTTP_X_CSRF_TOKEN', ''))) {
            $this->send(['error' => Text::_('JINVALID_TOKEN_NOTICE'), 'expired' => true], 403);
        }

        return $body;
    }

    private function tickets(): Tickets
    {
        return new Tickets(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
    }

    /** The ticket's first words, the visitor's, as its title */
    private function firstWords(object $room): string
    {
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $text = (string) $db->setQuery(
            $db->getQuery(true)->select('text')->from($db->quoteName('#__askdesk_messages'))
                ->where('room_id = :room')->where('author = ' . $db->quote('visitor'))->where('file_id = 0')
                ->bind(':room', $room->id, \Joomla\Database\ParameterType::INTEGER)->order('id'),
            0,
            1
        )->loadResult();

        return mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, 80, '…');
    }

    private static function time(string $sql): string
    {
        return $sql !== '' ? gmdate('c', strtotime($sql . ' UTC')) : '';
    }
}
