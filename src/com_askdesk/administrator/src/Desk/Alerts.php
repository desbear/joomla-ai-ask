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
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * E-mails to the people answering (0.6.0) about what no one may have seen: a message left, a chat that
 * ended with the visitor's words unanswered; and, once, what is still unhandled after the hours set,
 * together in one. Each with a link that opens the chat in the console. In the language of the people
 * answering; to everyone answering, or to the addresses set.
 *
 * @since  0.6.0
 */
final class Alerts
{
    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * Told once the request has its answer, so the visitor waits for nothing (the message may be
     * translated for the people answering first).
     *
     * @param  string  $kind  'left' (a message left), 'more' (a visitor added to their ticket, 0.6.2) or 'unanswered'
     *                        (a chat ended unanswered)
     */
    public function later(int $roomId, string $kind): void
    {
        if (!$this->settings->alertMail() || ($kind === 'unanswered' && !$this->settings->alertUnanswered())) {
            return;
        }

        register_shutdown_function(function () use ($roomId, $kind): void {
            if (\function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $this->send($roomId, $kind);
        });
    }

    /**
     * One e-mail about one chat; noted on the chat (so the reminder counts from it).
     */
    public function send(int $roomId, string $kind): bool
    {
        $room = $this->room($roomId);
        $to   = $this->recipients();

        // Others' chats not seen (as set): one taken by someone is theirs to be told of
        if ($room && (int) $room->staff_id && !$this->settings->seeOthers() && !$this->settings->alertAddresses()) {
            $db    = $this->db;
            $staff = (int) $room->staff_id;
            $mail  = (string) $db->setQuery($db->getQuery(true)->select('email')->from($db->quoteName('#__users'))->where('id = :id')->where('block = 0')->bind(':id', $staff, ParameterType::INTEGER))->loadResult();
            $to    = filter_var($mail, FILTER_VALIDATE_EMAIL) ? [$mail] : [];
        }

        // Added to a ticket (0.6.2): the one who has it is told, as they answered it (everyone when no one has it,
        // or they can no longer be written to); once in ten minutes at most, however much more is written
        if ($room && $kind === 'more') {
            if ($room->alerted !== null && strtotime((string) $room->alerted . ' UTC') > time() - 600) {
                return false;
            }

            $mine = (int) $room->staff_id ? $this->addressOf((int) $room->staff_id) : '';
            $to   = $mine !== '' ? [$mine] : $to;
        }

        if (!$room || !$to || $room->settled !== null) {
            return false;
        }

        $words = $this->language();
        $site  = (string) Factory::getApplication()->get('sitename');
        $who   = $this->who($room, $words);
        $head  = match ($kind) {
            'left'  => 'COM_ASKDESK_ALERT_LEFT',
            'more'  => 'COM_ASKDESK_ALERT_MORE',
            default => 'COM_ASKDESK_ALERT_UNANSWERED',
        };
        $body  = [
            sprintf($words->_($head . '_LEAD'), $who),
            '',
            $words->_('COM_ASKDESK_ALERT_SAID') . $this->said($room),
            $words->_('COM_ASKDESK_ALERT_WHEN') . $this->when((string) $room->modified),
            '',
            $words->_('COM_ASKDESK_ALERT_OPEN') . self::link((int) $room->id),
            '',
            $words->_('COM_ASKDESK_ALERT_FOOT'),
        ];

        $designed = Mails::make()->compose($kind === 'more' || $kind === 'left' ? $kind : 'unanswered', [$words->getTag()], [
            'site' => $site,
            'who'  => $who,
            'said' => $this->said($room),
            'when' => $this->when((string) $room->modified),
            'link' => self::link((int) $room->id),
        ]);

        if (!$this->mail($to, sprintf($words->_($head . '_SUBJECT'), $site, $who), implode("\n", $body), $designed)) {
            return false;
        }

        $this->mark([(int) $room->id], 'alerted');

        return true;
    }

    /**
     * What is still unhandled after the hours set and was not reminded of: told once, together. Run
     * now and then, with the requests.
     */
    public function remind(): void
    {
        $hours = $this->settings->remindHours();

        if (!$this->settings->alertMail() || $hours < 1) {
            return;
        }

        $db     = $this->db;
        $before = Factory::getDate('-' . $hours . ' hours')->toSql();
        $rows   = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))
                ->where('settled IS NULL')->where('reminded IS NULL')
                ->where('(status = ' . $db->quote('message') . ' OR status = ' . $db->quote('closed') . ')')
                ->where('COALESCE(closed, created) < :before')->bind(':before', $before)
                ->order('created'),
            0,
            30
        )->loadObjectList();
        $to = $this->recipients();

        if (!$rows || !$to) {
            return;
        }

        // Noted first: two requests at once do not both tell
        $this->mark(array_map(static fn ($row) => (int) $row->id, $rows), 'reminded');

        $words = $this->language();
        $site  = (string) Factory::getApplication()->get('sitename');
        $body  = [sprintf($words->_('COM_ASKDESK_ALERT_REMIND_LEAD'), \count($rows), $hours), ''];
        $items = [];

        foreach ($rows as $room) {
            $item    = ['who' => $this->who($room, $words), 'when' => $this->when((string) ($room->closed ?? $room->created)), 'said' => $this->said($room), 'link' => self::link((int) $room->id)];
            $items[] = $item;
            $body[]  = '• ' . $item['who'] . '（' . $item['when'] . '）';
            $body[]  = '  ' . $item['said'];
            $body[]  = '  ' . $item['link'];
            $body[]  = '';
        }

        $body[]   = $words->_('COM_ASKDESK_ALERT_FOOT');
        $designed = Mails::make()->compose('remind', [$words->getTag()], ['site' => $site, 'count' => (string) \count($rows), 'hours' => (string) $hours, 'list' => Mails::listHtml($items)]);

        $this->mail($to, sprintf($words->_('COM_ASKDESK_ALERT_REMIND_SUBJECT'), $site, \count($rows)), implode("\n", $body), $designed);
    }

    /**
     * Who is told: the addresses set, or everyone answering (active, with an address, not blocked).
     *
     * @return  string[]
     */
    private function recipients(): array
    {
        $set = $this->settings->alertAddresses();

        if ($set) {
            return $set;
        }

        $db = $this->db;

        return array_values(array_unique(array_filter($db->setQuery(
            $db->getQuery(true)->select('u.email')->from($db->quoteName('#__askdesk_staff', 's'))
                ->join('INNER', $db->quoteName('#__users', 'u'), 'u.id = s.user_id')
                ->where('s.active = 1')->where('u.block = 0')
        )->loadColumn(), static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false)));
    }

    /** A person answering's address, when they may still be written to */
    private function addressOf(int $userId): string
    {
        $db   = $this->db;
        $mail = (string) $db->setQuery(
            $db->getQuery(true)->select('u.email')->from($db->quoteName('#__users', 'u'))
                ->join('INNER', $db->quoteName('#__askdesk_staff', 's'), 's.user_id = u.id')
                ->where('u.id = :id')->where('u.block = 0')->where('s.active = 1')->bind(':id', $userId, ParameterType::INTEGER)
        )->loadResult();

        return filter_var($mail, FILTER_VALIDATE_EMAIL) ? $mail : '';
    }

    /** The visitor, as the people answering know them: name, company, address */
    private function who(object $room, Language $words): string
    {
        $name = trim((string) $room->name) !== '' ? trim((string) $room->name) : $words->_('COM_ASKDESK_CONSOLE_VISITOR') . ' #' . (int) $room->id;
        $more = array_filter([trim((string) $room->company), trim((string) $room->email)]);

        return $name . ($more ? '（' . implode('，', $more) . '）' : '');
    }

    /** What the visitor said last, in the words of the people answering when translated */
    private function said(object $room): string
    {
        $db  = $this->db;
        $id  = (int) $room->id;
        $row = $db->setQuery(
            $db->getQuery(true)->select('text, translation')->from($db->quoteName('#__askdesk_messages'))
                ->where('room_id = :room')->where('author = ' . $db->quote('visitor'))->bind(':room', $id, ParameterType::INTEGER)->order('id DESC'),
            0,
            1
        )->loadObject();
        $text = $row ? ((string) $row->translation !== '' ? (string) $row->translation : (string) $row->text) : '';

        return mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, 300, '…');
    }

    private function when(string $sql): string
    {
        try {
            return Factory::getDate($sql, 'UTC')->setTimezone(new \DateTimeZone((string) Factory::getApplication()->get('offset', 'UTC') ?: 'UTC'))->format('Y-m-d H:i', true);
        } catch (\Throwable) {
            return $sql;
        }
    }

    /** The console, at the chat */
    private static function link(int $roomId): string
    {
        $url = Push::consoleUrl();

        return $url . (str_contains($url, 'tmpl=') ? '' : (str_contains($url, '?') ? '&' : '?') . 'tmpl=component') . '#room=' . $roomId;
    }

    /**
     * @param  string[]                                                $to
     * @param  array{subject: string, html: string, text: string}|null  $designed  The mail as designed (0.6.3), else the words
     */
    private function mail(array $to, string $subject, string $body, ?array $designed = null): bool
    {
        try {
            $mailer = Factory::getMailer();

            foreach ($to as $address) {
                $mailer->addBcc($address);
            }

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

    /**
     * @param  int[]  $ids
     */
    private function mark(array $ids, string $column): void
    {
        $db  = $this->db;
        $now = Factory::getDate()->toSql();

        $db->setQuery($db->getQuery(true)->update($db->quoteName('#__askdesk_rooms'))->set($db->quoteName($column) . ' = :now')->whereIn('id', $ids)->bind(':now', $now))->execute();
    }

    private function room(int $id): ?object
    {
        $db = $this->db;

        return $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadObject() ?: null;
    }

    /** The words of the people answering (their language as set, else the site's) */
    private function language(): Language
    {
        $tag = $this->settings->staffLanguage();

        if (!LanguageHelper::exists($tag, JPATH_SITE)) {
            $tag = (string) \Joomla\CMS\Component\ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        }

        $language = Language::getInstance($tag);
        $language->load('com_askdesk', JPATH_SITE, $tag, true);

        return $language;
    }
}
