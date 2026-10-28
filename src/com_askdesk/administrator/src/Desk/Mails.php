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
use Joomla\CMS\Mail\Mail;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * The e-mails the desk sends, as designed in the administrator (0.6.3): one for each kind and language, made with
 * the drag-and-drop editor (its design kept beside the e-mail it makes, the styles put inline for mail programs).
 * Where a language has none, or it is switched off, the desk's own plain words are sent as before. What a mail
 * says of a visitor or a chat goes into its {placeholders}, written safe for HTML; a plain-text copy goes with it.
 *
 * @since  0.6.3
 */
final class Mails
{
    private const TABLE = '#__askdesk_mails';

    /** The mails: who they go to, and the placeholders each may have */
    public const KINDS = [
        'code'       => ['visitor', ['site', 'code', 'minutes']],
        'notice'     => ['visitor', ['site', 'name', 'link']],
        'transcript' => ['visitor', ['site', 'name']],
        'left'       => ['staff', ['site', 'who', 'said', 'when', 'link']],
        'more'       => ['staff', ['site', 'who', 'said', 'when', 'link']],
        'unanswered' => ['staff', ['site', 'who', 'said', 'when', 'link']],
        'remind'     => ['staff', ['site', 'count', 'hours', 'list']],
    ];

    /** Placeholders whose value is HTML the desk makes itself, not words to be written safe */
    private const MARKUP = ['list'];

    /** The most an e-mail may be, its design apart */
    public const MAX = 2 * 1024 * 1024;

    public function __construct(private DatabaseInterface $db)
    {
    }

    public static function make(): self
    {
        return new self(Factory::getContainer()->get(DatabaseInterface::class));
    }

    // ---------- Sending ----------

    /**
     * The mail of a kind in the first of the languages that has one switched on, its placeholders filled; null
     * when none has (the desk's own words are sent then).
     *
     * @param   string[]               $tags  Languages, the one wanted first (a short tag such as "en" too)
     * @param   array<string, string>  $vars
     *
     * @return  array{subject: string, html: string, text: string}|null
     */
    public function compose(string $kind, array $tags, array $vars): ?array
    {
        $row = $this->pick($kind, $tags);

        if (!$row) {
            return null;
        }

        // (Written by the people here as they want it: not turned into another writing, nor what the visitor wrote)
        $html    = $this->fill((string) $row->html, $vars);
        $subject = trim((string) preg_replace('/\s+/u', ' ', strtr((string) $row->subject, self::pairs($vars, false))));

        return ['subject' => $subject, 'html' => self::document($html, $subject, (string) $row->language), 'text' => self::text($html)];
    }

    /**
     * The mail put in the mailer: HTML with its plain-text copy.
     *
     * @param  array{subject: string, html: string, text: string}  $mail
     */
    public static function apply(Mail $mailer, array $mail): void
    {
        $mailer->setSubject($mail['subject']);
        $mailer->isHtml(true);
        $mailer->setBody($mail['html']);
        $mailer->AltBody = $mail['text'];
    }

    /**
     * The one for the first language that has one, switched on: the language as it is, then (but for Chinese,
     * whose two writings are not one another's) another of its country's; a short tag finds its language's.
     *
     * @param  string[]  $tags
     */
    public function pick(string $kind, array $tags): ?object
    {
        if (!isset(self::KINDS[$kind])) {
            return null;
        }

        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
                ->where('kind = :kind')->where('published = 1')->bind(':kind', $kind)->order('language')
        )->loadObjectList();

        if (!$rows) {
            return null;
        }

        $tags = array_values(array_filter(array_map(static fn ($tag) => trim((string) $tag), $tags)));

        foreach ($tags as $tag) {
            foreach ($rows as $row) {
                if (strcasecmp($row->language, $tag) === 0) {
                    return $row;
                }
            }
        }

        foreach ($tags as $tag) {
            $primary = strtolower(strtok($tag, '-_'));
            $short   = !preg_match('/[-_]/', $tag);

            if ($primary === 'zh' && !$short) {
                continue;
            }

            // A short "zh" is Taiwan's writing (as the desk writes Chinese), then the other
            $want = $primary === 'zh' ? ['zh-tw', 'zh-hk', 'zh-cn'] : [];

            foreach ($want as $one) {
                foreach ($rows as $row) {
                    if (strtolower($row->language) === $one) {
                        return $row;
                    }
                }
            }

            foreach ($rows as $row) {
                if ($primary !== 'zh' && str_starts_with(strtolower($row->language), $primary . '-')) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * The placeholders filled: words written safe for HTML (their lines kept), the desk's own markup as it is.
     *
     * @param  array<string, string>  $vars
     */
    public function fill(string $html, array $vars): string
    {
        return strtr($html, self::pairs($vars, true));
    }

    /**
     * @param   array<string, string>  $vars
     *
     * @return  array<string, string>
     */
    private static function pairs(array $vars, bool $html): array
    {
        $pairs = [];

        foreach ($vars as $key => $value) {
            $value = (string) $value;

            if ($html) {
                $value = \in_array($key, self::MARKUP, true) ? $value : nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), false);
            } elseif (\in_array($key, self::MARKUP, true)) {
                $value = '';
            }

            $pairs['{' . $key . '}'] = $value;
        }

        return $pairs;
    }

    /** What the editor gives, without the body it wraps it in (the e-mail has its own) */
    public static function body(string $html): string
    {
        $html = trim($html);

        if (preg_match('~^<body\b[^>]*>(.*)</body>$~is', $html, $match)) {
            return trim($match[1]);
        }

        return $html;
    }

    /** The e-mail whole, as mail programs read it best */
    public static function document(string $body, string $subject, string $language): string
    {
        $e = static fn ($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="' . $e($language) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="x-apple-disable-message-reformatting">'
            . '<title>' . $e($subject) . '</title></head><body style="margin:0;padding:0;">' . $body . '</body></html>';
    }

    /**
     * The words of an e-mail without its markup: its lines, and its links written out after their words.
     */
    public static function text(string $html): string
    {
        $html = (string) preg_replace('~<(head|style|script|title)\b[^>]*>.*?</\1>~is', '', $html);
        $html = (string) preg_replace_callback('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~is', static function (array $m): string {
            $href  = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $words = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($href === '' || str_starts_with($href, '#') || $href === $words) {
                return $m[3];
            }

            return $m[3] . ($words !== '' ? ' ' : '') . '( ' . $href . ' )';
        }, $html);
        $html = (string) preg_replace('~<br\s*/?>~i', "\n", $html);
        $html = (string) preg_replace('~</(p|h[1-6]|blockquote)>~i', "$0\n\n", $html);
        $html = (string) preg_replace('~</(div|li|tr|table)>~i', "$0\n", $html);
        $html = (string) preg_replace('~<li\b[^>]*>~i', '• ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    // ---------- In the administrator ----------

    /**
     * The languages a mail may be made in: the site's.
     *
     * @return  array<string, string>  tag => name
     */
    public static function languages(): array
    {
        $languages = [];

        foreach (LanguageHelper::getKnownLanguages(JPATH_SITE) as $tag => $language) {
            $languages[$tag] = (string) ($language['nativeName'] ?? $language['name'] ?? $tag);
        }

        ksort($languages);

        return $languages;
    }

    public static function valid(string $kind, string $language): bool
    {
        return isset(self::KINDS[$kind]) && isset(self::languages()[$language]);
    }

    /**
     * All made, by kind and language (without what they are).
     *
     * @return  array<string, array<string, object>>
     */
    public function made(): array
    {
        $db   = $this->db;
        $made = [];
        $rows = $db->setQuery(
            $db->getQuery(true)->select('id, kind, language, subject, published, modified, modified_by')->from($db->quoteName(self::TABLE))
        )->loadObjectList();

        foreach ($rows as $row) {
            $made[$row->kind][$row->language] = $row;
        }

        return $made;
    }

    public function get(string $kind, string $language): ?object
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
                ->where('kind = :kind')->where('language = :language')->bind(':kind', $kind)->bind(':language', $language)
        )->loadObject() ?: null;
    }

    public function save(string $kind, string $language, string $subject, string $html, string $design, bool $published, int $userId): void
    {
        if (!self::valid($kind, $language)) {
            throw new \InvalidArgumentException('kind');
        }

        $html = self::body($html);

        if (trim($subject) === '' || trim(strip_tags($html, '<img>')) === '' || \strlen($html) > self::MAX || \strlen($design) > self::MAX * 2) {
            throw new \RuntimeException('empty');
        }

        $row = (object) [
            'kind'        => $kind,
            'language'    => $language,
            'subject'     => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $subject)), 0, 255),
            'html'        => $html,
            'design'      => $design,
            'published'   => $published ? 1 : 0,
            'modified'    => Factory::getDate()->toSql(),
            'modified_by' => $userId,
        ];
        $old = $this->get($kind, $language);

        if ($old) {
            $row->id = (int) $old->id;
            $this->db->updateObject(self::TABLE, $row, 'id');
        } else {
            $this->db->insertObject(self::TABLE, $row, 'id');
        }
    }

    public function publish(string $kind, string $language, bool $published): void
    {
        $db    = $this->db;
        $value = $published ? 1 : 0;

        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName(self::TABLE))->set('published = :published')
                ->where('kind = :kind')->where('language = :language')
                ->bind(':published', $value, ParameterType::INTEGER)->bind(':kind', $kind)->bind(':language', $language)
        )->execute();
    }

    /** The desk's own words again, for that kind and language */
    public function delete(string $kind, string $language): void
    {
        $db = $this->db;

        $db->setQuery(
            $db->getQuery(true)->delete($db->quoteName(self::TABLE))
                ->where('kind = :kind')->where('language = :language')->bind(':kind', $kind)->bind(':language', $language)
        )->execute();
    }

    /**
     * The desk's words of a site language (its own when it has them, else English).
     */
    public static function words(string $tag): Language
    {
        $language = Language::getInstance($tag);
        $language->load('com_askdesk', JPATH_SITE, 'en-GB', true);
        $language->load('com_askdesk', JPATH_SITE, $tag, true);

        return $language;
    }

    /**
     * Where a new one starts: the desk's own words for it, laid out as an e-mail (a card on a grey ground, a
     * button for its link), with the placeholders where they go.
     *
     * @return  array{subject: string, html: string}
     */
    public static function starter(string $kind, Language $words): array
    {
        $t   = static fn (string $key, string ...$tokens) => self::tokens($words->_($key), $tokens);
        $e   = static fn (string $text) => nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), false);
        $p   = static fn (string $text, string $style = '') => '<p style="margin:0 0 14px 0;' . $style . '">' . $e($text) . '</p>';
        $btn = static fn (string $label) => '<table cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 18px 0;border-collapse:separate;"><tr>'
            . '<td align="center" bgcolor="#0e86fe" style="border-radius:10px;background-color:#0e86fe;">'
            . '<a href="{link}" target="_blank" style="display:inline-block;padding:12px 26px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;border-radius:10px;">'
            . $e($label) . '</a></td></tr></table>';
        $muted = 'color:#6b7280;font-size:13px;';

        switch ($kind) {
            case 'code':
                $subject = $t('COM_ASKDESK_TICKET_CODE_SUBJECT', '{site}');
                $body    = $p($t('COM_ASKDESK_TICKET_CODE_LEAD'))
                    . '<div style="margin:6px 0 18px 0;padding:16px;border-radius:12px;background-color:#ebf5ff;color:#0e3c78;font-size:30px;font-weight:bold;letter-spacing:8px;text-align:center;">{code}</div>'
                    . $p($t('COM_ASKDESK_TICKET_CODE_MINUTES', '{minutes}'))
                    . $p($t('COM_ASKDESK_TICKET_CODE_NOT_YOU'), $muted);
                break;

            case 'notice':
                $subject = $t('COM_ASKDESK_TICKET_NOTICE_SUBJECT', '{site}');
                $body    = $p($t('COM_ASKDESK_TICKET_NOTICE_HELLO', '{name}'))
                    . $p($t('COM_ASKDESK_TICKET_NOTICE_LEAD', '{site}'))
                    . $p($t('COM_ASKDESK_TICKET_NOTICE_WHY'), $muted)
                    . $btn($t('COM_ASKDESK_MAIL_OPEN_TICKETS'));
                break;

            case 'transcript':
                $subject = $t('COM_ASKDESK_TRANSCRIPT_SUBJECT', '{site}');
                $body    = '';

                foreach (preg_split('/\n\s*\n/u', $t('COM_ASKDESK_TRANSCRIPT_BODY', '{name}', '{site}')) ?: [] as $para) {
                    $body .= $p(trim($para));
                }
                break;

            case 'remind':
                $subject = $t('COM_ASKDESK_ALERT_REMIND_SUBJECT', '{site}', '{count}');
                $body    = $p($t('COM_ASKDESK_ALERT_REMIND_LEAD', '{count}', '{hours}'))
                    . '<div>{list}</div>'
                    . $p($t('COM_ASKDESK_ALERT_FOOT'), $muted);
                break;

            default:
                $head    = 'COM_ASKDESK_ALERT_' . strtoupper($kind);
                $subject = $t($head . '_SUBJECT', '{site}', '{who}');
                $body    = $p($t($head . '_LEAD', '{who}'))
                    . '<div style="margin:4px 0 16px 0;padding:14px 16px;border-left:4px solid #0e86fe;border-radius:8px;background-color:#f5f7fa;">'
                    . '<p style="margin:0 0 6px 0;' . $muted . '">' . $e($t('COM_ASKDESK_ALERT_SAID')) . '</p>'
                    . '<p style="margin:0 0 6px 0;">{said}</p>'
                    . '<p style="margin:0;' . $muted . '">' . $e($t('COM_ASKDESK_ALERT_WHEN')) . '{when}</p></div>'
                    . $btn(rtrim($t('COM_ASKDESK_ALERT_OPEN'), " ：:"))
                    . $p($t('COM_ASKDESK_ALERT_FOOT'), $muted);
        }

        $font = "font-family:-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,'PingFang TC','Microsoft JhengHei',sans-serif;";
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f7fa" style="width:100%;background-color:#f5f7fa;' . $font . '"><tr>'
            . '<td align="center" style="padding:32px 12px;">'
            . '<table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e3e7ec;border-radius:16px;">'
            . '<tr><td style="padding:28px 32px 6px 32px;color:#232332;font-size:20px;font-weight:bold;line-height:1.4;">{site}</td></tr>'
            . '<tr><td style="padding:12px 32px 18px 32px;color:#232332;font-size:15px;line-height:1.7;">' . $body . '</td></tr>'
            . '</table>'
            . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">'
            . '<tr><td align="center" style="padding:16px 32px;color:#8a8f98;font-size:12px;line-height:1.6;">{site}</td></tr>'
            . '</table></td></tr></table>';

        return ['subject' => $subject, 'html' => $html];
    }

    /** The desk's words with the placeholders where their %s and %d are, in their order (or as numbered) */
    private static function tokens(string $text, array $tokens): string
    {
        $text = str_replace('\n', "\n", $text);
        $at   = 0;

        return (string) preg_replace_callback('/%(?:(\d+)\$)?[sd]/', static function (array $m) use ($tokens, &$at): string {
            $index = isset($m[1]) && $m[1] !== '' ? (int) $m[1] - 1 : $at++;

            return $tokens[$index] ?? '';
        }, $text);
    }

    /**
     * The items of the reminder, as its {list} shows them.
     *
     * @param  array<int, array{who: string, when: string, said: string, link: string}>  $items
     */
    public static function listHtml(array $items): string
    {
        $e    = static fn (string $text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $html = '';

        foreach ($items as $item) {
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 10px 0;border:1px solid #e3e7ec;border-radius:10px;"><tr>'
                . '<td style="padding:12px 14px;font-size:14px;line-height:1.6;color:#232332;">'
                . '<strong>' . $e($item['who']) . '</strong> <span style="color:#6b7280;">' . $e($item['when']) . '</span><br>'
                . $e($item['said']) . '<br>'
                . '<a href="' . $e($item['link']) . '" target="_blank" style="color:#0e86fe;">' . $e($item['link']) . '</a>'
                . '</td></tr></table>';
        }

        return $html;
    }

    /**
     * Words to try a mail with, in its language.
     *
     * @return  array<string, string>
     */
    public static function sample(string $kind, Language $words): array
    {
        $site = (string) Factory::getApplication()->get('sitename');
        $when = Factory::getDate('now', 'UTC')->setTimezone(new \DateTimeZone((string) Factory::getApplication()->get('offset', 'UTC') ?: 'UTC'))->format('Y-m-d H:i', true);
        $name = $words->_('COM_ASKDESK_MAIL_SAMPLE_NAME');
        $link = self::KINDS[$kind][0] === 'visitor' ? Tickets::url() : Push::consoleAt();
        $vars = [
            'site'    => $site,
            'name'    => $name,
            'code'    => '123456',
            'minutes' => '10',
            'link'    => $link,
            'who'     => $name . '（sample@example.com）',
            'said'    => $words->_('COM_ASKDESK_MAIL_SAMPLE_SAID'),
            'when'    => $when,
            'count'   => '2',
            'hours'   => '24',
        ];
        $vars['list'] = self::listHtml([
            ['who' => $vars['who'], 'when' => $when, 'said' => $vars['said'], 'link' => $link],
            ['who' => $words->_('COM_ASKDESK_CONSOLE_VISITOR') . ' #2', 'when' => $when, 'said' => $vars['said'], 'link' => $link],
        ]);

        return array_intersect_key($vars, array_flip(self::KINDS[$kind][1]));
    }
}
