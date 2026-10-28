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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Messages left as tickets (0.6.2): answered here in the console, the visitor told by e-mail only that there is
 * an answer, with a link; never the answer itself. On the page of the link a guest asks for a one-time code
 * sent to the address they left and sees, with it, that address's open tickets; a member signed in sees their
 * own. Neither the address nor the code is kept as it is: both hashed with the site's secret, the codes for
 * minutes.
 *
 * @since  0.6.2
 */
final class Tickets
{
    private const CODES = '#__askdesk_codes';

    /** How long a ticket is still shown to its visitor after it is closed */
    private const CLOSED_DAYS = 30;

    /** How long a code holds, and how often it may be tried */
    private const CODE_MINUTES = 10;
    private const MAX_TRIES    = 5;

    /** Codes asked for one address in a quarter of an hour, and from one address of the network in an hour */
    private const PER_ADDRESS = 3;
    private const PER_IP      = 10;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /** The page where tickets are seen: its menu item's address when there is one, on its own */
    public static function url(): string
    {
        $link = 'index.php?option=com_askdesk&view=tickets';

        try {
            $item = Factory::getApplication()->getMenu('site')->getItems('link', $link, true);
            $url  = Route::link('site', $item ? 'index.php?Itemid=' . (int) $item->id : $link, false, Route::TLS_IGNORE, true);
        } catch (\Throwable) {
            $url = Uri::root() . $link;
        }

        return $url . (str_contains($url, 'tmpl=') ? '' : (str_contains($url, '?') ? '&' : '?') . 'tmpl=component');
    }

    public static function address(string $email): string
    {
        $email = mb_strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    // ---------- The visitor's tickets ----------

    /**
     * The tickets of a guest's address, or of a member: open ones, and ones closed in the last days (their answer
     * may have come with the closing); newest first.
     *
     * @return  object[]
     */
    public function of(string $email, int $userId = 0): array
    {
        $db     = $this->db;
        $closed = Factory::getDate('-' . self::CLOSED_DAYS . ' days')->toSql();
        $query  = $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))
            ->where('ticket = 1')->where('archived_at IS NULL')
            ->where('(settled IS NULL OR settled >= :closed)')->bind(':closed', $closed)
            ->order('modified DESC');

        if ($userId > 0) {
            $query->where('user_id = :user')->bind(':user', $userId, ParameterType::INTEGER);
        } else {
            $email = self::address($email);

            if ($email === '') {
                return [];
            }

            // MySQL compares without case already (and keeps to its index that way)
            $column = $db->getServerType() === 'postgresql' ? 'LOWER(' . $db->quoteName('email') . ')' : $db->quoteName('email');
            $query->where('user_id = 0')->where($column . ' = :email')->bind(':email', $email);
        }

        return $db->setQuery($query, 0, 50)->loadObjectList();
    }

    /**
     * Whether a ticket is one the visitor may see: their address's (a guest's) or theirs (a member's), open or
     * closed lately. Only an open one may be written to (Tickets::writable).
     */
    public static function belongs(object $room, string $email, int $userId): bool
    {
        $closed = $room->settled !== null && strtotime((string) $room->settled . ' UTC') < time() - self::CLOSED_DAYS * 86400;

        if (!(int) ($room->ticket ?? 0) || $closed || ($room->archived_at ?? null) !== null) {
            return false;
        }

        if ((int) $room->user_id > 0) {
            return $userId > 0 && (int) $room->user_id === $userId;
        }

        $email = self::address($email);

        return $email !== '' && hash_equals($email, mb_strtolower(trim((string) $room->email)));
    }

    public static function writable(object $room): bool
    {
        return $room->status === 'message' && $room->settled === null;
    }

    // ---------- One-time codes ----------

    /**
     * A code sent to the address, when it has open tickets. Said the same either way, so the page tells no one
     * whether an address has any; only too many asks are told apart.
     *
     * @return  string  'sent', 'none' (nothing to see: no mail) or 'busy' (asked too often)
     */
    public function ask(string $email, string $ip): string
    {
        $email = self::address($email);
        $db    = $this->db;
        $now   = Factory::getDate();
        $hash  = $this->hash($email);
        $from  = $this->hash('ip|' . $ip);

        $old   = Factory::getDate('-1 day')->toSql();

        $db->setQuery($db->getQuery(true)->delete($db->quoteName(self::CODES))->where('created < :old')->bind(':old', $old))->execute();

        $count = static fn (string $column, string $value, string $since) => (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName(self::CODES))
                ->where($db->quoteName($column) . ' = :value')->where('created >= :since')->bind(':value', $value)->bind(':since', $since)
        )->loadResult();

        if ($email === '' || $count('email', $hash, Factory::getDate('-15 minutes')->toSql()) >= self::PER_ADDRESS || $count('ip', $from, Factory::getDate('-1 hour')->toSql()) >= self::PER_IP) {
            return 'busy';
        }

        $code = sprintf('%06d', random_int(0, 999999));
        $none = !$this->of($email);
        $row  = (object) [
            'email'   => $hash,
            // Nothing to see: a code no one is sent and none can match, kept only to count the asks
            'code'    => $this->hash($email . '|' . ($none ? bin2hex(random_bytes(16)) : $code)),
            'ip'      => $from,
            'tries'   => 0,
            'created' => $now->toSql(),
            'expires' => Factory::getDate('+' . self::CODE_MINUTES . ' minutes')->toSql(),
        ];

        $db->insertObject(self::CODES, $row, 'id');

        if ($none) {
            return 'none';
        }

        $designed = Mails::make()->compose('code', [Factory::getApplication()->getLanguage()->getTag()], ['site' => self::site(), 'code' => $code, 'minutes' => (string) self::CODE_MINUTES]);

        $this->later(fn () => $this->mail($email, Text::sprintf('COM_ASKDESK_TICKET_CODE_SUBJECT', self::site()), implode("\n", [
            Text::_('COM_ASKDESK_TICKET_CODE_LEAD'),
            '',
            $code,
            '',
            Text::sprintf('COM_ASKDESK_TICKET_CODE_MINUTES', self::CODE_MINUTES),
            Text::_('COM_ASKDESK_TICKET_CODE_NOT_YOU'),
        ]), $designed));

        return 'sent';
    }

    /** Whether the code is the one last sent to the address, in time and within the tries; used up when it is */
    public function verify(string $email, string $code): bool
    {
        $email = self::address($email);
        $code  = preg_replace('/\D/', '', $code) ?? '';

        if ($email === '' || \strlen($code) !== 6) {
            return false;
        }

        $db   = $this->db;
        $hash = $this->hash($email);
        $now  = Factory::getDate()->toSql();
        $row  = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName(self::CODES))
                ->where('email = :email')->where('expires >= :now')->bind(':email', $hash)->bind(':now', $now)->order('id DESC'),
            0,
            1
        )->loadObject();

        if (!$row || (int) $row->tries >= self::MAX_TRIES) {
            return false;
        }

        $db->setQuery($db->getQuery(true)->update($db->quoteName(self::CODES))->set('tries = tries + 1')->where('id = :id')->bind(':id', $row->id, ParameterType::INTEGER))->execute();

        if (!hash_equals((string) $row->code, $this->hash($email . '|' . $code))) {
            return false;
        }

        $db->setQuery($db->getQuery(true)->delete($db->quoteName(self::CODES))->where('email = :email')->bind(':email', $hash))->execute();

        return true;
    }

    // ---------- Telling the visitor ----------

    /**
     * The visitor told there is an answer to their ticket: a link to the page, and nothing of the answer (an
     * e-mail goes where it goes; the answer is read only by one who has the address's code). Sent after the
     * request has its answer, in the language of the page they left it on.
     */
    public function notifyLater(object $room): void
    {
        $email = self::address((string) $room->email);

        if ($email === '' || (int) $room->user_id > 0 && !$this->memberMail($room)) {
            return;
        }

        $words = (new Transcript($this->db, $this->settings))->language($room);
        $name  = trim((string) $room->name);
        $body  = [
            $name !== '' ? sprintf($words->_('COM_ASKDESK_TICKET_NOTICE_HELLO'), $name) : $words->_('COM_ASKDESK_TICKET_NOTICE_HELLO_ANY'),
            '',
            sprintf($words->_('COM_ASKDESK_TICKET_NOTICE_LEAD'), self::site()),
            $words->_('COM_ASKDESK_TICKET_NOTICE_WHY'),
            '',
            self::url(),
        ];
        $subject  = sprintf($words->_('COM_ASKDESK_TICKET_NOTICE_SUBJECT'), self::site());
        $designed = Mails::make()->compose('notice', [$words->getTag()], ['site' => self::site(), 'name' => $name, 'link' => self::url()]);

        $this->later(fn () => $this->mail($email, $subject, implode("\n", $body), $designed));
    }

    /** A member's ticket goes to the address they left it with: still theirs (their account's) */
    private function memberMail(object $room): bool
    {
        $db   = $this->db;
        $user = (int) $room->user_id;
        $mail = (string) $db->setQuery($db->getQuery(true)->select('email')->from($db->quoteName('#__users'))->where('id = :id')->where('block = 0')->bind(':id', $user, ParameterType::INTEGER))->loadResult();

        return $mail !== '' && strcasecmp($mail, (string) $room->email) === 0;
    }

    /**
     * @param  array{subject: string, html: string, text: string}|null  $designed  The mail as designed (0.6.3), else the words
     */
    private function mail(string $to, string $subject, string $body, ?array $designed = null): bool
    {
        try {
            $mailer = Factory::getMailer();
            $mailer->addRecipient($to);

            if ($designed) {
                Mails::apply($mailer, $designed);
            } else {
                $mailer->setSubject($subject);
                $mailer->setBody($body);
            }

            return (bool) $mailer->Send();
        } catch (\Throwable) {
            return false;
        }
    }

    /** After the request has its answer, so the page waits for no mail server */
    private function later(callable $send): void
    {
        register_shutdown_function(static function () use ($send): void {
            if (\function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $send();
        });
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, 'askdesk-tickets|' . (string) Factory::getApplication()->get('secret'));
    }

    private static function site(): string
    {
        return (string) Factory::getApplication()->get('sitename');
    }
}
