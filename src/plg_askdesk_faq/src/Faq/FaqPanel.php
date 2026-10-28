<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  plg_askdesk_faq
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Askdesk\Faq\Faq;

use Gss\Component\Askdesk\Administrator\Desk\PanelInterface;
use Gss\Component\Askdesk\Administrator\Desk\Summary;
use Gss\Component\Askdesk\Administrator\Desk\WorkInterface;
use Gss\Component\Askdesk\Administrator\Desk\Works;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * A chat turned into a question and its answer for the knowledge base: a card in the chat, drafted by AI
 * (what the visitor asked, in general words, and what they were told, nothing of who they are), its tags
 * picked from those set, kept as an article of com_content once confirmed (its alias by AI Alias then,
 * when that is on; a picture by AI Image in the article's form). Beside the chat it shows nothing of its
 * own: what was made is said above the suggested replies, opened whole in the side card.
 *
 * @since  0.6.4
 */
final class FaqPanel implements PanelInterface, WorkInterface
{
    private const FORM = 'faq';

    /** @var array<string, array<string, int|string>>|null  The choices of a card: label => value, by field */
    private ?array $choices = null;

    public function __construct(private Registry $params)
    {
    }

    public function name(): string
    {
        return 'faq';
    }

    /** Nothing beside the chat: what was made is said above the suggested replies */
    public function view(object $room, object $staff): array
    {
        return [];
    }

    public function act(object $room, object $staff, string $action, array $input): array
    {
        if ($action !== 'show' || !preg_match('/^a(\d{1,10})$/', (string) ($input['ref'] ?? ''), $m)) {
            return [];
        }

        $id = (int) $m[1];

        // Only an article made from this chat
        foreach (Works::of($room) as $work) {
            if ($work['panel'] === $this->name() && ($work['card']['made']['ref'] ?? '') === 'a' . $id) {
                return ['record' => $this->record($id)];
            }
        }

        return [];
    }

    public function forms(object $room, object $staff): array
    {
        if (!$this->user($staff)->authorise('core.create', 'com_content') && !$this->categories($staff)) {
            return [];
        }

        // Kept from this chat already: said, so it is not kept twice by mistake (a chat may still make two)
        $made = array_values(array_filter(Works::of($room), fn ($work) => $work['panel'] === $this->name() && $work['state'] === 'sent' && !empty($work['card']['made'])));

        return [[
            'id'          => self::FORM,
            'title'       => Text::_('PLG_ASKDESK_FAQ_FORM'),
            'description' => $made ? Text::sprintf('PLG_ASKDESK_FAQ_FORM_MADE', \count($made), (string) end($made)['card']['made']['title']) : Text::_('PLG_ASKDESK_FAQ_FORM_DESC'),
            'group'       => trim((string) $this->params->get('group', '')) ?: (string) Factory::getApplication()->get('sitename'),
            'icon'        => 'book',
        ]];
    }

    public function work(object $room, object $staff, string $form, string $action, array $input): array
    {
        $values = $this->values($staff, (array) ($input['values'] ?? []), $action === 'start');

        if ($action === 'start' || $action === 'fill') {
            $values = $this->draft($room, $staff, $values, $action === 'fill' ? (string) ($input['note'] ?? '') : '');
        }

        if ($action !== 'submit') {
            return $this->card($staff, $values);
        }

        $problem = $this->problem($staff, $values);

        if ($problem !== '') {
            return ['blocked' => $problem] + $this->card($staff, $values);
        }

        try {
            $id = $this->save($staff, $values);
        } catch (\Throwable $e) {
            return ['blocked' => Text::sprintf('PLG_ASKDESK_FAQ_FAILED', mb_substr($e->getMessage(), 0, 200))] + $this->card($staff, $values);
        }

        $state = (int) $this->choice($staff, 'state', $values['state']);

        return [
            'sent' => Text::_($state === 1 ? 'PLG_ASKDESK_FAQ_SENT_PUBLISHED' : 'PLG_ASKDESK_FAQ_SENT'),
            'made' => [
                'title' => $values['title'],
                'meta'  => implode(' · ', array_filter([$values['category'], $values['state']])),
                'tags'  => array_values(array_filter(explode("\n", $values['tags']))),
                'ref'   => 'a' . $id,
                'url'   => $this->editUrl($id),
                'mark'  => Text::_('PLG_ASKDESK_FAQ_MARK'),
            ],
        ] + $this->card($staff, $values);
    }

    /**
     * The card: the question, the answer, its tags, where it goes and how.
     *
     * @param   array<string, string>  $values
     */
    private function card(object $staff, array $values): array
    {
        $field = fn (string $key, string $kind, bool $required = false, bool $readonly = false) => [
            'key'      => $key,
            'label'    => Text::_('PLG_ASKDESK_FAQ_FIELD_' . strtoupper($key)),
            'kind'     => $kind,
            'options'  => \in_array($kind, ['select', 'tags'], true) ? array_keys($this->options($staff)[$key]) : [],
            'value'    => $values[$key] ?? '',
            'required' => $required,
            'readonly' => $readonly,
        ];

        return [
            'title'  => Text::_('PLG_ASKDESK_FAQ_FORM'),
            'hint'   => Text::_('PLG_ASKDESK_FAQ_HINT'),
            'submit' => Text::_('PLG_ASKDESK_FAQ_SUBMIT'),
            'fill'   => Text::_('PLG_ASKDESK_FAQ_FILL'),
            'fields' => array_values(array_filter([
                $field('title', 'text', true),
                $field('answer', 'textarea', true),
                $this->options($staff)['tags'] ? $field('tags', 'tags') : null,
                $field('category', 'select', true),
                \count($this->options($staff)['language']) > 1 ? $field('language', 'select') : null,
                // Published only by those who may
                $field('state', 'select', true, \count($this->options($staff)['state']) < 2),
            ])),
        ];
    }

    /**
     * What is in the card: as written, else as set (the category, the language, the tags always on, how kept).
     *
     * @param   array<string, mixed>  $given
     *
     * @return  array<string, string>
     */
    private function values(object $staff, array $given, bool $fresh): array
    {
        $options = $this->options($staff);
        $label   = static fn (array $choices, $value) => (string) (array_search((string) $value, array_map('strval', $choices), true) ?: '');
        $set     = [
            'title'    => '',
            'answer'   => '',
            'tags'     => implode("\n", array_keys(array_intersect($options['tags'], array_map('intval', (array) $this->params->get('tags_always', []))))),
            'category' => $label($options['category'], (int) $this->params->get('catid', 0)) ?: (string) (array_key_first($options['category']) ?? ''),
            'language' => $label($options['language'], (string) $this->params->get('language', '*')) ?: (string) (array_key_first($options['language']) ?? ''),
            'state'    => $label($options['state'], (int) $this->params->get('state', 0)) ?: (string) (array_key_first($options['state']) ?? ''),
        ];

        if ($fresh) {
            return $set;
        }

        $out = [];

        foreach ($set as $key => $default) {
            $value = \is_scalar($given[$key] ?? null) ? trim((string) $given[$key]) : $default;

            // A choice that is not one of the card's: as set
            if ($key === 'tags') {
                $value = implode("\n", array_values(array_intersect(array_keys($options['tags']), explode("\n", $value))));
            } elseif (isset($options[$key]) && !isset($options[$key][$value])) {
                $value = $default;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * The question, its answer and its tags drafted by AI from the chat (again, as the person answering
     * says, on asking); the tags always on kept.
     *
     * @param   array<string, string>  $values
     *
     * @return  array<string, string>
     */
    private function draft(object $room, object $staff, array $values, string $note): array
    {
        $tags   = array_keys($this->options($staff)['tags']);
        $fields = [
            ['key' => 'title', 'label' => 'The question, as anyone would ask it: one line, in general words', 'kind' => 'text'],
            ['key' => 'answer', 'label' => 'The answer the people answering gave, complete and clear, in short paragraphs (a list "- " where it is steps); only what was answered, never a word on what the chat leaves out', 'kind' => 'textarea'],
        ];

        if ($tags) {
            $fields[] = ['key' => 'tags', 'label' => 'The products or topics it is about', 'kind' => 'tags', 'options' => $tags];
        }

        try {
            $drafted = Summary::fill(
                $room,
                $this->writing($staff, $values['language']),
                $fields,
                'a question and its answer for the site\'s knowledge base (FAQ), for anyone with the same question. Never anything of this customer: no name, e-mail, phone, address, company, order, account or ticket number; their case told as anyone\'s',
                $note,
                $note !== '' ? array_intersect_key($values, ['title' => 1, 'answer' => 1, 'tags' => 1]) : []
            );
        } catch (\Throwable) {
            $drafted = [];
        }

        if (!$drafted) {
            return $values;
        }

        $always = array_keys(array_intersect($this->options($staff)['tags'], array_map('intval', (array) $this->params->get('tags_always', []))));
        $picked = [...$always, ...array_filter(explode("\n", (string) ($drafted['tags'] ?? '')))];

        return [
            'title'  => (string) ($drafted['title'] ?? '') ?: $values['title'],
            'answer' => (string) ($drafted['answer'] ?? '') ?: $values['answer'],
            'tags'   => implode("\n", array_values(array_intersect($tags, $picked))),
        ] + $values;
    }

    /** What is in the way of keeping it, said; "" when nothing is */
    private function problem(object $staff, array $values): string
    {
        if ($values['title'] === '' || $values['answer'] === '' || $values['category'] === '') {
            return Text::_('PLG_ASKDESK_FAQ_MISSING');
        }

        $catid = (int) $this->choice($staff, 'category', $values['category']);

        return $catid && $this->user($staff)->authorise('core.create', 'com_content.category.' . $catid) ? '' : Text::_('PLG_ASKDESK_FAQ_NOT_ALLOWED');
    }

    /**
     * The article kept, as com_content keeps one of its own form (its events: AI Alias, the search index…).
     *
     * @param   array<string, string>  $values
     */
    private function save(object $staff, array $values): int
    {
        $app   = Factory::getApplication();
        $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);

        if (!$model) {
            throw new \RuntimeException('com_content');
        }

        $tags = [];

        foreach (array_filter(explode("\n", $values['tags'])) as $tag) {
            $tags[] = (string) $this->options($staff)['tags'][$tag];
        }

        $data = [
            'id'        => 0,
            'title'     => mb_substr($values['title'], 0, 250),
            'alias'     => '',
            'catid'     => (int) $this->choice($staff, 'category', $values['category']),
            'language'  => (string) ($this->choice($staff, 'language', $values['language']) ?? '*'),
            'state'     => (int) $this->choice($staff, 'state', $values['state']),
            'introtext' => self::html($values['answer']),
            'fulltext'  => '',
            'tags'      => $tags,
            'featured'  => 0,
            'access'    => (int) $app->get('access', 1),
            'metadata'  => [],
            'attribs'   => [],
            'images'    => [],
            'urls'      => [],
        ];

        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $before = (int) $db->setQuery($db->getQuery(true)->select('MAX(id)')->from($db->quoteName('#__content')))->loadResult();

        // The article's form, which saving reads for its workflow stage: from the site, com_content's admin one is
        // not where it looks
        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_content/forms');
        Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_content/src/Field');

        // No alias given, for another extension to make (AI Alias, say) or com_content from the title
        if ($model->save($data)) {
            return $this->staged((int) $model->getState('article.id'), $data['catid']);
        }

        // Refused for an alias the title makes that the category has already (a question asked before): again,
        // with one of its own, numbered
        $alias = ApplicationHelper::stringURLSafe($data['title'], $data['language']);

        if ($alias !== '' && $this->aliasTaken($alias, $data['catid']) && !$this->keptSince($before, $data['title'], (int) $staff->user_id)) {
            for ($n = 2; $this->aliasTaken($alias . '-' . $n, $data['catid']); $n++);

            $data['alias'] = $alias . '-' . $n;
            $model         = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);

            if ($model->save($data)) {
                return $this->staged((int) $model->getState('article.id'), $data['catid']);
            }
        }

        // Kept, though another extension failed after (a search index out of reach, say): kept it is, not
        // kept twice by sending it again
        $kept = $this->keptSince($before, $data['title'], (int) $staff->user_id);

        if ($kept) {
            return $this->staged($kept, $data['catid']);
        }

        throw new \RuntimeException(strip_tags((string) $model->getError()));
    }

    /**
     * The article given its workflow stage when saving did not (the articles' list shows only those that have
     * one): its category's workflow's first, as com_content's form would.
     */
    private function staged(int $id, int $catid): int
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $there = (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__workflow_associations'))
                ->where('item_id = :id')->where('extension = ' . $db->quote('com_content.article'))
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadResult();

        if (!$there && $id) {
            try {
                $workflow = new Workflow('com_content.article', Factory::getApplication(), $db);
                $stage    = (int) $workflow->getDefaultStageByCategory($catid);

                if ($stage) {
                    $workflow->createAssociation($id, $stage);
                }
            } catch (\Throwable) {
                // Kept all the same; its stage set in the article's form
            }
        }

        return $id;
    }

    /** The article of this title one kept after the one given, if any */
    private function keptSince(int $before, string $title, int $userId): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (int) $db->setQuery(
            $db->getQuery(true)->select('MAX(id)')->from($db->quoteName('#__content'))
                ->where('id > :before')->where('title = :title')->where('created_by = :user')
                ->bind(':before', $before, ParameterType::INTEGER)->bind(':title', $title)->bind(':user', $userId, ParameterType::INTEGER)
        )->loadResult();
    }

    private function aliasTaken(string $alias, int $catid): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (bool) $db->setQuery(
            $db->getQuery(true)->select('1')->from($db->quoteName('#__content'))
                ->where('alias = :alias')->where('catid = :catid')
                ->bind(':alias', $alias)->bind(':catid', $catid, ParameterType::INTEGER),
            0,
            1
        )->loadResult();
    }

    /** The answer as HTML: its paragraphs, its lists ("- " or "1. "), its lines; nothing of it read as HTML */
    private static function html(string $text): string
    {
        $out = [];

        foreach (preg_split('/\R{2,}/u', trim($text)) as $block) {
            $lines = preg_split('/\R/u', trim($block));
            $list  = '';
            $items = [];
            $flush = static function () use (&$out, &$list, &$items): void {
                if ($items) {
                    $out[] = '<' . $list . '>' . implode('', array_map(static fn ($item) => '<li>' . $item . '</li>', $items)) . '</' . $list . '>';
                }

                $items = [];
            };
            $para = [];

            foreach ($lines as $line) {
                $safe = htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8');

                if (preg_match('/^(?:[-*•]|(\d+)[.)])\s+(.*)$/u', trim($line), $m)) {
                    if ($para) {
                        $out[] = '<p>' . implode('<br>', $para) . '</p>';
                        $para  = [];
                    }

                    $kind = $m[1] !== '' ? 'ol' : 'ul';

                    if ($list !== $kind) {
                        $flush();
                        $list = $kind;
                    }

                    $items[] = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');

                    continue;
                }

                $flush();

                if ($safe !== '') {
                    $para[] = $safe;
                }
            }

            $flush();

            if ($para) {
                $out[] = '<p>' . implode('<br>', $para) . '</p>';
            }
        }

        return implode("\n", $out);
    }

    /** An article made from the chat, whole, for the side card */
    private function record(int $id): array
    {
        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $row = $db->setQuery(
            $db->getQuery(true)
                ->select('a.id, a.title, a.introtext, a.state, a.language, c.title AS category')
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c'), 'c.id = a.catid')
                ->where('a.id = :id')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject();

        if (!$row) {
            return ['title' => Text::_('PLG_ASKDESK_FAQ_GONE')];
        }

        $tags = $db->setQuery(
            $db->getQuery(true)
                ->select('t.title')->from($db->quoteName('#__contentitem_tag_map', 'm'))
                ->join('INNER', $db->quoteName('#__tags', 't'), 't.id = m.tag_id')
                ->where('m.type_alias = ' . $db->quote('com_content.article'))->where('m.content_item_id = :id')
                ->bind(':id', $id, ParameterType::INTEGER)->order('t.lft')
        )->loadColumn();
        $state = match ((int) $row->state) {
            1       => Text::_('JPUBLISHED'),
            2       => Text::_('JARCHIVED'),
            -2      => Text::_('JTRASHED'),
            default => Text::_('JUNPUBLISHED'),
        };

        return [
            'title'  => (string) $row->title,
            'meta'   => implode(' · ', array_filter([(string) $row->category, $state])),
            'url'    => $this->editUrl($id),
            'open'   => Text::_('PLG_ASKDESK_FAQ_OPEN'),
            'fields' => [
                ['label' => Text::_('PLG_ASKDESK_FAQ_FIELD_TAGS'), 'value' => implode('、', $tags)],
                ['label' => Text::_('PLG_ASKDESK_FAQ_FIELD_LANGUAGE'), 'value' => $row->language === '*' ? Text::_('JALL') : (string) $row->language],
                ['label' => Text::_('PLG_ASKDESK_FAQ_FIELD_ANSWER'), 'value' => trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</(p|li)>#i', "\n", (string) $row->introtext)), ENT_QUOTES, 'UTF-8'))],
            ],
        ];
    }

    /** Where the article is edited (an AI picture made there, say) */
    private function editUrl(int $id): string
    {
        return Uri::root() . 'administrator/index.php?option=com_content&task=article.edit&id=' . $id;
    }

    /**
     * The choices of the card, label => value: the categories one may add to (by their path), the site's
     * languages, the tags to pick from (those set to suggest and to be always on; else all), how it is kept.
     *
     * @return  array<string, array<string, int|string>>
     */
    private function options(object $staff): array
    {
        if ($this->choices !== null) {
            return $this->choices;
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $user = $this->user($staff);

        $languages = [Text::_('JALL') => '*'];

        foreach (LanguageHelper::getContentLanguages([0, 1]) as $language) {
            $languages[(string) $language->title] = (string) $language->lang_code;
        }

        $ids   = array_values(array_unique(array_filter(array_map('intval', [...(array) $this->params->get('tags_suggest', []), ...(array) $this->params->get('tags_always', [])]))));
        $query = $db->getQuery(true)->select('id, title')->from($db->quoteName('#__tags'))->where('published = 1')->where('level > 0')->order('lft');

        if ($ids) {
            $query->whereIn('id', $ids);
        }

        $tags = [];

        foreach ($db->setQuery($query, 0, 200)->loadObjectList() as $tag) {
            $tags[(string) $tag->title] ??= (int) $tag->id;
        }

        $state = [Text::_('JUNPUBLISHED') => 0];

        if ($user->authorise('core.edit.state', 'com_content')) {
            $state[Text::_('JPUBLISHED')] = 1;
        }

        return $this->choices = [
            'category' => $this->categories($staff),
            'language' => $languages,
            'tags'     => $tags,
            'state'    => $state,
        ];
    }

    /**
     * The categories of articles one may add to, by their path ("Products / CRM").
     *
     * @return  array<string, int>
     */
    private function categories(object $staff): array
    {
        static $cache = [];

        $userId = (int) $staff->user_id;

        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $user = $this->user($staff);
        $rows = $db->setQuery(
            $db->getQuery(true)->select('id, title, level, parent_id')->from($db->quoteName('#__categories'))
                ->where('extension = ' . $db->quote('com_content'))->where('published = 1')->where('level > 0')->order('lft')
        )->loadObjectList('id');
        $out = [];

        foreach ($rows as $row) {
            if (!$user->authorise('core.create', 'com_content.category.' . (int) $row->id)) {
                continue;
            }

            $path = [(string) $row->title];

            for ($up = $rows[$row->parent_id] ?? null; $up; $up = $rows[$up->parent_id] ?? null) {
                array_unshift($path, (string) $up->title);
            }

            $label = mb_substr(implode(' / ', $path), 0, 90);

            if (isset($out[$label])) {
                $label .= ' (#' . (int) $row->id . ')';
            }

            $out[$label] = (int) $row->id;
        }

        return $cache[$userId] = $out;
    }

    /** The value of a choice by its label */
    private function choice(object $staff, string $key, string $label): int|string|null
    {
        return $this->options($staff)[$key][$label] ?? null;
    }

    /** The language it is written in: the article's, else the one of the person answering */
    private function writing(object $staff, string $label): string
    {
        $code = (string) ($this->choice($staff, 'language', $label) ?? '*');

        return $code !== '*' ? $code : ((string) ($staff->language ?? '') ?: 'en-GB');
    }

    private function user(object $staff): User
    {
        return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $staff->user_id);
    }
}
