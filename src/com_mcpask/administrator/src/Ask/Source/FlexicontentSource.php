<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask\Source;

use Gss\Component\Mcpask\Administrator\Ask\Document;
use Gss\Component\Mcpask\Administrator\Ask\State;
use Gss\Component\Mcpask\Administrator\Ask\WebClient;
use Gss\Component\Mcpask\Administrator\Ask\WebPage;
use Joomla\CMS\Component\ComponentHelper;
use Gss\Component\Mcpask\Administrator\Ask\WebSync;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * FLEXIcontent items: rows of #__content extended by #__flexicontent_items_ext, with their own
 * custom fields and their own URLs.
 *
 * @since  0.1.0
 */
final class FlexicontentSource extends ContentSource
{
    /** FLEXIcontent field types whose value is text worth reading (textarea covers the editor). */
    protected const TEXT_FIELDS = ['text', 'textarea'];

    public static function isAvailable(): bool
    {
        return ComponentHelper::isInstalled('com_flexicontent') && ComponentHelper::isEnabled('com_flexicontent');
    }

    public function name(): string
    {
        return 'flexicontent';
    }

    public function url(string $link): string
    {
        $helper = JPATH_SITE . '/components/com_flexicontent/helpers/route.php';

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        try {
            if (!class_exists('FlexicontentHelperRoute', false) && is_file($helper)) {
                require_once $helper;
            }

            // FLEXIcontent finds the menu item an item belongs under, which decides its address
            if (class_exists('FlexicontentHelperRoute', false) && isset($query['id'], $query['cid'])) {
                $link = \FlexicontentHelperRoute::getItemRoute((string) $query['id'], (int) $query['cid']);
            }

            return Route::link('site', $link, false, Route::TLS_IGNORE, true);
        } catch (\Throwable) {
            return parent::url($link);
        }
    }

    /**
     * An item; of the types read from their page, as its page shows it.
     */
    public function load(int $id): ?Document
    {
        $document = parent::load($id);
        $pages    = $this->settings->flexiPages();

        if (!$document || !$pages || !isset($pages[$type = $this->typeOf($id)])) {
            return $document;
        }

        $page = $this->page($document, $pages[$type]);

        // The page could not be read just now: null keeps what was indexed, to be tried again later
        if ($page === null) {
            return null;
        }

        // A page that is not this item's to read (it leads to another website, or holds no text
        // where it should): the item as it is stored, as for other types
        if ($page === '') {
            return $document;
        }

        return new Document($document->source, $document->itemId, $document->title, $document->link, $document->language, $document->access, $page);
    }

    /**
     * Items of the types read from their page are read again every day: what a page shows can
     * change with its template, without the item changing.
     */
    public function modified(array $ids): array
    {
        $modified = parent::modified($ids);
        $pages    = $this->settings->flexiPages();

        if (!$pages || !$modified) {
            return $modified;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('item_id')
            ->from($db->quoteName('#__flexicontent_items_ext'))
            ->whereIn('item_id', array_map('intval', array_keys($modified)))
            ->whereIn('type_id', array_keys($pages));
        $today = Factory::getDate('today')->toSql();

        foreach ($db->setQuery($query)->loadColumn() as $id) {
            $modified[(int) $id] = max($modified[(int) $id], $today);
        }

        return $modified;
    }

    private function typeOf(int $id): int
    {
        $db = $this->db;

        return (int) $db->setQuery($db->getQuery(true)->select('type_id')->from($db->quoteName('#__flexicontent_items_ext'))->where('item_id = ' . $id))->loadResult();
    }

    /**
     * The part of an item's page that holds it, as visitors who are not signed in see it, in the
     * item's language. Null when the page could not be read just now; empty when it will not be
     * (it leads to another website, is not HTML, or has no text where the selector points).
     */
    private function page(Document $document, string $selector): ?string
    {
        $root = $this->root();

        if ($root === '') {
            return null;
        }

        parse_str((string) parse_url($document->link, PHP_URL_QUERY), $query);

        $language = $document->language !== '*' ? $document->language : (string) ComponentHelper::getParams('com_languages')->get('site', '');
        // Joomla turns this address into the item's own, in its language, wherever it is asked from
        $url      = $root . 'index.php?' . http_build_query(array_filter([
            'option' => 'com_flexicontent',
            'view'   => 'item',
            'id'     => (int) $document->itemId,
            'cid'    => (int) ($query['cid'] ?? 0) ?: null,
            'lang'   => $language ?: null,
        ]));

        try {
            $response = (new WebClient($root, true))->get($url);
        } catch (\Throwable $e) {
            $response = ['status' => 0, 'type' => '', 'body' => '', 'charset' => '', 'why' => 'connect:' . $e->getMessage()];
        }

        if ($response['status'] === 200 && !\in_array($response['type'], ['text/html', 'application/xhtml+xml', ''], true)) {
            $this->unread($document, Text::sprintf('COM_MCPASK_PAGES_NOT_HTML', $response['type']), true);

            return '';
        }

        if ($response['status'] !== 200) {
            $lasting = \in_array($response['status'], [404, 410], true) || str_starts_with((string) ($response['why'] ?? ''), 'elsewhere');

            $this->unread($document, WebSync::reason($response), $lasting);

            return $lasting ? '' : null;
        }

        $page = WebPage::read($response['body'], $selector, $response['charset']);

        if (trim(strip_tags($page['html'])) === '') {
            $this->unread($document, Text::sprintf('COM_MCPASK_PAGES_NO_TEXT', $selector !== '' ? $selector : '—'), true);

            return '';
        }

        $this->unread($document, '');

        return $page['html'];
    }

    /** Items kept in the list of those whose page could not be read. */
    public const UNREAD = 'pages:unread';

    private const UNREAD_KEPT = 20;

    /**
     * Note that an item's page could not be read, and why, for the dashboard; or, with no reason,
     * that it was read after all. Stored: the item was indexed from its text and fields instead.
     */
    private function unread(Document $document, string $why, bool $stored = false): void
    {
        $state  = new State($this->db);
        $unread = $state->get(self::UNREAD);
        $had    = isset($unread[$document->itemId]);

        unset($unread[$document->itemId]);

        if ($why !== '') {
            $unread = [$document->itemId => ['title' => $document->title, 'why' => $why, 'stored' => $stored, 'at' => Factory::getDate()->toSql()]] + $unread;
        } elseif (!$had) {
            return;
        }

        $state->set(self::UNREAD, \array_slice($unread, 0, self::UNREAD_KEPT, true));
    }

    /**
     * The site's address. A request knows it; a scheduled task run from the command line uses the
     * one the last request remembered.
     */
    private function root(): string
    {
        if (!str_starts_with(PHP_SAPI, 'cli')) {
            try {
                return rtrim(Uri::root(), '/') . '/';
            } catch (\Throwable) {
                // Fall back on what was remembered
            }
        }

        $url = (string) ((new State($this->db))->get('site:url')['url'] ?? '');

        return $url !== '' ? rtrim($url, '/') . '/' : '';
    }

    public function textFields(): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('f.name, f.label')
            ->from($db->quoteName('#__flexicontent_fields', 'f'))
            ->where('f.iscore = 0')
            ->where('f.published = 1')
            ->whereIn('f.field_type', self::TEXT_FIELDS, ParameterType::STRING)
            ->order('f.ordering, f.label');
        $fields = [];

        foreach ($db->setQuery($query)->loadObjectList() as $field) {
            $fields[(string) $field->name] = (string) $field->label;
        }

        return $fields;
    }

    protected function eligible(): QueryInterface
    {
        // FLEXIcontent gives each content type an access level of its own. (A type that is
        // unpublished only takes no new items: its items stay on the site.)
        return parent::eligible()
            ->join('INNER', $this->db->quoteName('#__flexicontent_items_ext', 'ie'), 'ie.item_id = a.id')
            ->join('INNER', $this->db->quoteName('#__flexicontent_types', 'ty'), 'ty.id = ie.type_id')
            ->where('ty.access IN (' . implode(',', $this->levels()) . ')');
    }

    protected function link(object $row): string
    {
        return 'index.php?option=com_flexicontent&view=item&cid=' . (int) $row->catid . '&id=' . (int) $row->id . ':' . $row->alias;
    }

    /**
     * Text fields of the item that are published, public, and still part of the item's content type
     * (a value can outlive the field's place in the type).
     *
     * FLEXIcontent decides in the templates of each type where a field is shown, which cannot be
     * read from here: a public field that no template shows is still read. The option "Only these
     * fields" is there for sites where that matters.
     */
    protected function fields(object $row): array
    {
        $db    = $this->db;
        $id    = (int) $row->id;
        $query = $db->getQuery(true)
            ->select('f.name, r.value')
            ->from($db->quoteName('#__flexicontent_fields_item_relations', 'r'))
            ->join('INNER', $db->quoteName('#__flexicontent_fields', 'f'), 'f.id = r.field_id')
            ->where('f.iscore = 0')
            ->where('f.published = 1')
            ->where('f.access IN (' . implode(',', $this->levels()) . ')')
            ->where(
                'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__flexicontent_fields_type_relations', 'ft')
                . ' INNER JOIN ' . $db->quoteName('#__flexicontent_items_ext', 'ie') . ' ON ie.type_id = ft.type_id'
                . ' WHERE ft.field_id = f.id AND ie.item_id = r.item_id)'
            )
            ->whereIn('f.field_type', self::TEXT_FIELDS, ParameterType::STRING)
            ->where('r.item_id = :item')
            ->where('r.value <> ' . $db->quote(''))
            ->order('f.ordering, r.valueorder, r.suborder')
            ->bind(':item', $id, ParameterType::INTEGER);

        // Serialized values belong to other field types that reuse these names; they are not prose
        return array_values(array_filter(
            $db->setQuery($query)->loadObjectList(),
            fn ($field) => $this->wanted((string) $field->name) && !preg_match('/^(a|s|i|d|b|O):\d*[:;]/', (string) $field->value)
        ));
    }
}
