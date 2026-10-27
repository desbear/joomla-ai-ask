<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Gss\Component\Mcpask\Administrator\Ask\Translator;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * The chat written out for the visitor when it ends: a PDF in the words they read (what they wrote,
 * and what the people answering wrote as it was translated for them), sent to their address.
 * Files are listed by name; they are not sent along.
 *
 * @since  0.1.0
 */
final class Transcript
{
    /** @var array<string, string>  The words of the last PDF made, in the visitor's language when they could be */
    private array $words = [];

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * Sent once the request that ended the chat has its answer, so ending it waits for nothing
     * (the words may be translated first).
     */
    public function sendLater(object $room): void
    {
        register_shutdown_function(function () use ($room): void {
            if (\function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $this->send($room);
        });
    }

    /**
     * Sends the chat to the visitor, when it was a chat (not a message left), someone answered
     * and their address is known. Nothing is said when it cannot be sent.
     */
    public function send(object $room): bool
    {
        if (!$this->settings->transcript() || $room->email === '' || !(int) $room->staff_id || !filter_var($room->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $pdf = $this->pdf($room);

            if ($pdf === '') {
                return false;
            }

            $words  = $this->words;
            $mailer = Factory::getMailer();
            $mailer->addRecipient((string) $room->email, (string) $room->name);
            $mailer->setSubject($words['subject']);
            $mailer->setBody(str_replace('⏎', "\n", $words['body']));
            $mailer->addStringAttachment($pdf, 'chat-' . gmdate('Ymd', strtotime((string) $room->created . ' UTC')) . '-' . (int) $room->id . '.pdf', 'base64', 'application/pdf');

            return (bool) $mailer->Send();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The PDF, in the room's language (the page's, the words of the desk; the chat as the visitor
     * read it). Empty when nothing was said.
     */
    public function pdf(object $room, ?Language $language = null): string
    {
        $language ??= $this->language($room);
        $rows       = $this->messages((int) $room->id);

        if (!array_filter($rows, static fn ($row) => $row->author !== 'system')) {
            return '';
        }

        $names = (new Staff($this->db, $this->settings))->names(array_merge([(int) $room->staff_id], array_map(static fn ($row) => (int) $row->staff_id, $rows)));
        $files = (new Files($this->db))->byIds(array_filter(array_map(static fn ($row) => (int) $row->file_id, $rows)));
        $zone  = new \DateTimeZone((string) Factory::getApplication()->get('offset', 'UTC') ?: 'UTC');
        $at    = static fn (string $sql, string $format) => Factory::getDate($sql, 'UTC')->setTimezone($zone)->format($format, true);
        $words = $this->words = $this->words($room, $language, $rows, $names, $at, $zone);
        $title = $words['title'];
        $pdf   = new Pdf((string) ($room->visitor_language ?: $room->page_language), $title);

        $pdf->text($title, ['size' => 17, 'bold' => true, 'after' => 4]);
        $pdf->text($words['when'], ['size' => 9, 'grey' => true]);
        $pdf->text($words['who'], ['size' => 9, 'grey' => true]);
        $pdf->rule(8);

        foreach ($rows as $row) {
            $time = $at((string) $row->created, 'H:i');

            if ($row->author === 'system') {
                $text = $words['system' . (int) $row->id] ?? '';

                if ($text !== '') {
                    $pdf->text($time . '  ' . $text, ['size' => 8.5, 'grey' => true, 'center' => true, 'after' => 6]);
                }

                continue;
            }

            $who  = $row->author === 'staff' ? ($names[(int) $row->staff_id] ?? $words['staff']) : ((string) $room->name ?: $words['you']);
            $file = (int) $row->file_id ? ($files[(int) $row->file_id] ?? null) : null;

            if ((int) ($row->sticker_id ?? 0)) {
                $text = sprintf($words['sticker'], (string) $row->text);
            } elseif ((int) $row->file_id) {
                $text = sprintf($words['file'], (string) ($file->name ?? $row->text), $file ? self::size((int) $file->size) : '-');
            } elseif ($row->author === 'staff' && $row->translation !== '') {
                $text = (string) $row->translation;
            } else {
                $text = (string) $row->text;
            }

            $pdf->text($who . '  ·  ' . $time, ['size' => 8.5, 'grey' => true, 'bold' => $row->author === 'staff']);
            $pdf->text($text, ['size' => 10.5, 'indent' => 10, 'after' => 8]);
        }

        $pdf->rule(4);
        $pdf->text($words['foot'], ['size' => 8, 'grey' => true]);

        return $pdf->output();
    }

    /**
     * The words of the PDF and its e-mail: the desk's, in the language of the room's page, and
     * translated into the visitor's when they wrote in another (one request for all; as they are
     * when it fails). System messages as the visitor read them.
     *
     * @param   object[]            $rows
     * @param   array<int, string>  $names
     *
     * @return  array<string, string>
     */
    private function words(object $room, Language $language, array $rows, array $names, callable $at, \DateTimeZone $zone): array
    {
        $site  = (string) Factory::getApplication()->get('sitename');
        $words = [
            'title'   => sprintf($language->_('COM_ASKDESK_TRANSCRIPT_TITLE'), $site),
            'when'    => sprintf($language->_('COM_ASKDESK_TRANSCRIPT_WHEN'), $at((string) $room->created, 'Y-m-d H:i'), $at((string) ($room->closed ?: $room->modified), 'H:i'), $zone->getName()),
            'who'     => sprintf($language->_('COM_ASKDESK_TRANSCRIPT_WHO'), (string) $room->name, $names[(int) $room->staff_id] ?? ''),
            'staff'   => $language->_('COM_ASKDESK_TRANSCRIPT_STAFF'),
            'you'     => $language->_('COM_ASKDESK_TRANSCRIPT_YOU'),
            'file'    => $language->_('COM_ASKDESK_TRANSCRIPT_FILE'),
            'sticker' => $language->_('COM_ASKDESK_TRANSCRIPT_STICKER'),
            'foot'    => sprintf($language->_('COM_ASKDESK_TRANSCRIPT_FOOT'), $site),
            'subject' => sprintf($language->_('COM_ASKDESK_TRANSCRIPT_SUBJECT'), $site),
            'body'    => str_replace("\n", '⏎', sprintf($language->_('COM_ASKDESK_TRANSCRIPT_BODY'), (string) $room->name, $site)),
        ];
        $kept = [];

        foreach ($rows as $row) {
            if ($row->author !== 'system') {
                continue;
            }

            if ($row->translation !== '' && Translator::same((string) $row->translation_language, (string) $room->visitor_language)) {
                $kept['system' . (int) $row->id] = (string) $row->translation;
            } else {
                $words['system' . (int) $row->id] = $this->systemText($language, (string) $row->text, $names[(int) $row->staff_id] ?? '');
            }
        }

        $visitor = (string) $room->visitor_language;

        if ($visitor === '' || Translator::same($visitor, $language->getTag()) || !$this->settings->translates()) {
            return $words + $kept;
        }

        // One line each, its key first: translated together, read back by key
        $lines = [];

        foreach ($words as $key => $text) {
            $lines[] = $key . ' | ' . str_replace(["\r", "\n"], ' ', $text);
        }

        try {
            $result = (new Translator(\Gss\Component\Mcpask\Administrator\Ask\Settings::load()))->translate(
                "Keep each line's key and \" | \" as they are, %s, %1\$s and %2\$s as they are, and the ⏎ marks.\n" . implode("\n", $lines),
                $visitor,
                $this->settings->glossary(),
                '',
                $this->settings->translateModel(),
                $this->settings->translateEffort()
            );
        } catch (\Throwable) {
            return $words + $kept;
        }

        foreach (preg_split('/\R/u', (string) $result['text']) ?: [] as $line) {
            if (preg_match('/^\s*([a-z]+\d*)\s*\|\s*(.+)$/u', $line, $match) && isset($words[$match[1]]) && trim($match[2]) !== '') {
                // The file's words keep the places of its name and size
                if (($match[1] === 'file' && (!str_contains($match[2], '%1$s') || !str_contains($match[2], '%2$s'))) || ($match[1] === 'sticker' && !str_contains($match[2], '%s'))) {
                    continue;
                }

                $words[$match[1]] = trim($match[2]);
            }
        }

        return $words + $kept;
    }

    /**
     * The desk's words in the language of the room's page (the site's, when it has none of it).
     */
    /** The desk's words in the room's language: the page's, when the site has it (the ticket's notice too, 0.6.2) */
    public function language(object $room): Language
    {
        $tag = (string) $room->page_language;

        // The room keeps a short tag (zh-TW, en): the site's language of it
        foreach (array_keys(LanguageHelper::getKnownLanguages(JPATH_SITE)) as $known) {
            if (strcasecmp($known, $tag) === 0 || stripos($known, $tag . '-') === 0) {
                $tag = $known;
                break;
            }
        }

        if (!LanguageHelper::exists($tag, JPATH_SITE)) {
            $tag = (string) \Joomla\CMS\Component\ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        }

        $language = Language::getInstance($tag);
        $language->load('com_askdesk', JPATH_SITE, $tag, true);

        return $language;
    }

    private function systemText(Language $language, string $code, string $name): string
    {
        return match ($code) {
            'joined'       => sprintf($language->_('COM_ASKDESK_SYSTEM_JOINED'), $name),
            'closed'       => $language->_('COM_ASKDESK_SYSTEM_CLOSED'),
            'visitor_left' => $language->_('COM_ASKDESK_SYSTEM_VISITOR_LEFT'),
            'visitor_gone' => $language->_('COM_ASKDESK_SYSTEM_VISITOR_GONE'),
            default        => '',
        };
    }

    /**
     * @return  object[]
     */
    private function messages(int $roomId): array
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_messages'))->where('room_id = :room')
                ->bind(':room', $roomId, ParameterType::INTEGER)->order('id'),
            0,
            Rooms::MAX_MESSAGES
        )->loadObjectList();
    }

    private static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
