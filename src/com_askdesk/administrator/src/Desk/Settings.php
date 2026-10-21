<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * The desk's options.
 *
 * @since  0.1.0
 */
final class Settings
{
    public function __construct(private Registry $params)
    {
    }

    public static function load(): self
    {
        return new self(ComponentHelper::getParams('com_askdesk'));
    }

    /**
     * The language the people answering read and write in, unless they choose their own.
     */
    public function staffLanguage(): string
    {
        $tag = trim((string) $this->params->get('staff_language', ''));

        return $tag !== '' ? $tag : (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
    }

    /**
     * The console's own logo at the top of its side, and its mark (where there is little room), as
     * addresses; empty for the desk's own (0.3.0).
     *
     * @return  array{logo: string, mark: string}
     */
    public function logo(): array
    {
        $clean = static function (string $value): string {
            $url = \Joomla\CMS\HTML\HTMLHelper::_('cleanImageURL', $value)->url ?? '';

            return $url === '' ? '' : (preg_match('#^(https?:)?//#i', $url) ? $url : \Joomla\CMS\Uri\Uri::root(true) . '/' . ltrim($url, '/'));
        };

        return ['logo' => $clean((string) $this->params->get('console_logo', '')), 'mark' => $clean((string) $this->params->get('console_mark', ''))];
    }

    /**
     * Whether messages are translated (with AI Ask's AI service).
     */
    public function translates(): bool
    {
        return (bool) $this->params->get('translate', 1) && class_exists('Gss\\Component\\Mcpask\\Administrator\\Ask\\Translator');
    }

    /**
     * @return  string[]  Lines "term" or "term = translation"
     */
    public function glossary(): array
    {
        return \array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->params->get('glossary', '')) ?: []), 'strlen')), 0, 100);
    }

    /** The model messages are translated with; empty for AI Ask's chat model. */
    public function translateModel(): string
    {
        return trim((string) $this->params->get('translate_model', ''));
    }

    /**
     * How much the model reasons while translating: the least by default (a message needs no
     * thinking, and reasoning is what makes it slow: 80 seconds against 2 with one model); empty to
     * leave it to the model. Models that do not reason are asked again without it.
     */
    public function translateEffort(): string
    {
        $effort = (string) $this->params->get('translate_effort', 'minimal');

        return \in_array($effort, ['', 'none', 'minimal', 'low'], true) ? $effort : 'minimal';
    }

    /** Whether the browser's own translation (where it has one) is shown while the AI's is on its way. */
    public function browserTranslate(): bool
    {
        return (bool) $this->params->get('browser_translate', 1);
    }

    /**
     * The quick machine translation shown when the AI takes longer than quickSeconds(): 'google'
     * (Google Cloud Translation, with its API key), or '' for none.
     */
    public function quickTranslate(): string
    {
        return $this->params->get('quick_translate', '') === 'google' && $this->googleKey() !== '' ? 'google' : '';
    }

    public function googleKey(): string
    {
        return trim((string) $this->params->get('google_key', ''));
    }

    /** How long the AI's translation is waited for before the quick one is shown, in seconds. */
    public function quickSeconds(): int
    {
        return max(1, min(20, (int) $this->params->get('quick_seconds', 3)));
    }

    /** Minutes without a sign of the visitor after which an open chat is closed. */
    public function idleMinutes(): int
    {
        return min(240, max(3, (int) $this->params->get('idle_minutes', 15)));
    }

    /** Days the chats of signed-in visitors are kept. Guests' chats are not kept. */
    public function keepDays(): int
    {
        return min(3650, max(1, (int) $this->params->get('keep_days', 365)));
    }

    /** Whether visitors are sent the chat as a PDF when it ends (to the address they gave). */
    public function transcript(): bool
    {
        return (bool) $this->params->get('transcript', 1);
    }

    /** The Dropfiles category the library is kept in; 0 for one made for it. */
    public function libraryRoot(): int
    {
        return max(0, (int) $this->params->get('library_root', 0));
    }

    public function sound(): bool
    {
        return (bool) $this->params->get('sound', 1);
    }

    public function noticeMember(): string
    {
        return trim((string) $this->params->get('notice_member', ''));
    }

    public function noticeGuest(): string
    {
        return trim((string) $this->params->get('notice_guest', ''));
    }

    public function privacyUrl(): string
    {
        $url = trim((string) $this->params->get('privacy_url', ''));

        return preg_match('#^(https?://|/)#i', $url) ? $url : '';
    }

    /** Kinds of answers a rating may ask for. */
    public const RATING_TYPES = ['stars', 'thumbs', 'scale', 'choice'];

    /**
     * What visitors are asked when a chat ends (0.1.0), as set under Options: a title, what is rated
     * (each a key, a label, a kind and, for a choice, its options; whether it must be answered) and
     * whether a comment may be written. One overall rating with stars when nothing is set; null when
     * visitors are not asked.
     *
     * @return  array{title: string, dimensions: array<int, array{key: string, label: string, type: string, options: string[], required: bool}>, comment: bool}|null
     */
    public function ratingForm(): ?array
    {
        if (!$this->params->get('rating', 1)) {
            return null;
        }

        $dimensions = [];

        foreach ((array) $this->params->get('rating_dimensions', []) as $row) {
            $row   = (array) $row;
            $label = trim(strip_tags((string) ($row['label'] ?? '')));
            $type  = \in_array($row['type'] ?? '', self::RATING_TYPES, true) ? $row['type'] : 'stars';

            if ($label === '') {
                continue;
            }

            $options = $type === 'choice'
                ? \array_slice(array_values(array_filter(array_map(static fn ($line) => mb_substr(trim($line), 0, 60), preg_split('/\R/u', (string) ($row['options'] ?? '')) ?: []), 'strlen')), 0, 8)
                : [];

            if ($type === 'choice' && !$options) {
                continue;
            }

            $dimensions[] = [
                'key'      => 'd' . substr(md5($label), 0, 8),
                'label'    => mb_substr($label, 0, 100),
                'type'     => $type,
                'options'  => $options,
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        if (!$dimensions) {
            $dimensions[] = ['key' => 'overall', 'label' => \Joomla\CMS\Language\Text::_('COM_ASKDESK_RATING_OVERALL'), 'type' => 'stars', 'options' => [], 'required' => true];
        }

        return [
            'title'      => trim((string) $this->params->get('rating_title', '')) ?: \Joomla\CMS\Language\Text::_('COM_ASKDESK_RATING_TITLE'),
            'dimensions' => \array_slice($dimensions, 0, 8),
            'comment'    => (bool) $this->params->get('rating_comment', 1),
        ];
    }

    /**
     * The console speaks the language a person answering chose (0.1.0): the request's language is
     * set to it, with the desk's words (English where the desk has no words of that language).
     * Nothing changes for a language the site does not have.
     */
    public static function useLanguage(string $tag): void
    {
        $app = Factory::getApplication();

        if ($tag === '' || $tag === $app->getLanguage()->getTag() || !\Joomla\CMS\Language\LanguageHelper::exists($tag, JPATH_SITE)) {
            return;
        }

        $language = \Joomla\CMS\Language\Language::getInstance($tag, (bool) $app->get('debug_lang'));
        $language->load('joomla', JPATH_SITE);
        $language->load('com_askdesk', JPATH_SITE);

        $app->loadLanguage($language);
        Factory::$language = $language;
    }

    /**
     * Whether a user answers visitors here: a member a super user made one of the people answering.
     */
    public static function isStaff(?\Joomla\CMS\User\User $user = null): bool
    {
        static $known = [];

        $user ??= Factory::getApplication()->getIdentity();

        if (!$user || $user->guest || $user->block) {
            return false;
        }

        if (!isset($known[(int) $user->id])) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $id = (int) $user->id;

            $known[$id] = (int) $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__askdesk_staff'))->where('user_id = :id')->where('active = 1')->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER)
            )->loadResult() > 0;
        }

        return $known[(int) $user->id];
    }
}
