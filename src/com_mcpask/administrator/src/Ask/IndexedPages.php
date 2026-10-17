<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Gss\Component\Mcpask\Administrator\Ask\Source\SourceInterface;
use Gss\Component\Mcpask\Administrator\Ask\Source\Sources;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * What the index holds, as the administrator looks through it: each page with its address, where
 * it comes from, its passages and how it stands.
 *
 * @since  0.16.0
 */
final class IndexedPages
{
    /** Groups of sources to filter by. */
    public const KINDS = ['own', 'peer', 'web', 'manual'];

    /** @var array<string, SourceInterface>|null */
    private ?array $own = null;

    /** @var array<string, string>|null  Addresses of indexed pages (compared form) => what they are */
    private ?array $ownAddresses = null;

    /** @var array<string, string>  Aliases of this site's indexed items ("alias", "id-alias") => what they are */
    private array $ownAliases = [];

    /** @var array<string, array<string, string>>  Paths of menu items that lead to indexed items => by language, what they are */
    private array $ownPaths = [];

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * A page of the index, a page at a time.
     *
     * @param   array{search: string, kind: string, status: string, thin: bool}  $filter
     *
     * @return  array{0: object[], 1: int}  Rows (id, source, item_id, title, link, language, status, message, chunks, checked, url, kind, site) and how many in all
     */
    public function list(array $filter, int $limit, int $start = 0): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)->from($db->quoteName('#__mcpask_documents'));

        if ($filter['search'] !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filter['search']) . '%';
            // An ID ("2071" or "#2071") finds the item too
            $id   = preg_match('/^#?(\d+)$/', $filter['search'], $match) ? ' OR item_id = ' . (int) $match[1] : '';

            $query->where('(title LIKE :search1 OR link LIKE :search2' . $id . ')')
                ->bind(':search1', $like)
                ->bind(':search2', $like);
        }

        $web    = $db->quote(WebSync::PREFIX . '%');
        $peer   = $db->quote(PeerSync::PREFIX . '%');
        $manual = $db->quote(WebSync::PREFIX . ManualPages::SITE . '%');

        match ($filter['kind']) {
            'own'    => $query->where('source NOT LIKE ' . $web)->where('source NOT LIKE ' . $peer),
            'peer'   => $query->where('source LIKE ' . $peer),
            // Listed websites only: added pages have their own filter (their sources are 10 characters after the prefix)
            'web'    => $query->where('source LIKE ' . $web)->where('NOT (source LIKE ' . $manual . ' AND CHAR_LENGTH(source) = ' . (\strlen(WebSync::PREFIX) + 10) . ')'),
            'manual' => $query->where('source LIKE ' . $manual)->where('CHAR_LENGTH(source) = ' . (\strlen(WebSync::PREFIX) + 10)),
            default  => null,
        };

        if ($filter['status'] === 'ok') {
            $query->where('status = ' . $db->quote('ok'));
        } elseif ($filter['status'] === 'pending') {
            $query->where('status <> ' . $db->quote('ok'));
        }

        // Pages with little text: worth a look, as a page whose text is somewhere the index does not read
        if ($filter['thin']) {
            $query->where('chunks <= 1');
        }

        $total = (int) $db->setQuery((clone $query)->select('COUNT(*)'))->loadResult();
        $rows  = $db->setQuery(
            $query->select('id, source, item_id, title, link, language, status, message, chunks, checked')->order($filter['thin'] ? 'chunks, title' : 'checked DESC, id DESC'),
            $start,
            $limit
        )->loadObjectList();

        foreach ($rows as $row) {
            $this->describe($row);
        }

        return [$rows, $total];
    }

    /**
     * One indexed page, described.
     */
    public function get(int $id): ?object
    {
        $row = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('id, source, item_id, title, link, language, status, message, chunks, checked')
                ->from($this->db->quoteName('#__mcpask_documents'))
                ->where('id = ' . $id)
        )->loadObject();

        return $row ? $this->describe($row) : null;
    }

    /**
     * The passages of a page, in order: what the AI service is given of it.
     *
     * @return  array<int, array{heading: string, text: string}>
     */
    public function passages(int $id): array
    {
        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('heading, text')
                ->from($this->db->quoteName('#__mcpask_chunks'))
                ->where('document_id = ' . $id)
                ->order('position')
        )->loadAssocList();

        return array_map(static fn ($row) => ['heading' => (string) $row['heading'], 'text' => (string) $row['text']], $rows);
    }

    /**
     * How many pages of each kind, for the filters.
     *
     * @return  array<string, int>
     */
    public function counts(): array
    {
        $counts = array_fill_keys(self::KINDS, 0);
        $rows   = $this->db->setQuery($this->db->getQuery(true)->select('source, COUNT(*) AS n')->from($this->db->quoteName('#__mcpask_documents'))->group('source'))->loadObjectList();

        foreach ($rows as $row) {
            $counts[self::kind((string) $row->source)] += (int) $row->n;
        }

        return $counts;
    }

    public static function kind(string $source): string
    {
        return match (true) {
            ManualPages::isManual($source) => 'manual',
            WebSync::isWeb($source)        => 'web',
            PeerSync::isPeer($source)      => 'peer',
            default                        => 'own',
        };
    }

    /**
     * The address of an indexed page as visitors reach it: a page of this site is routed, a page
     * of another site keeps the address it had there.
     */
    public function address(object $row): string
    {
        if (Sources::isElsewhere((string) $row->source)) {
            return (string) $row->link;
        }

        $own = $this->own()[(string) $row->source] ?? null;

        try {
            return $own ? $own->url((string) $row->link) : (string) $row->link;
        } catch (\Throwable) {
            return (string) $row->link;
        }
    }

    /**
     * What an address is indexed as already, for an address about to be added: a page of this
     * site ("FLEXIcontent #2071 Title") or a page already read from elsewhere. Empty when it is not.
     */
    public function indexedAs(string $url): string
    {
        if ($this->ownAddresses === null) {
            $this->ownAddresses = [];
            $this->ownAliases   = [];
            $items              = [];

            $rows = $this->db->setQuery(
                $this->db->getQuery(true)->select('id, source, item_id, title, link')->from($this->db->quoteName('#__mcpask_documents'))
            )->loadObjectList();

            foreach ($rows as $row) {
                $label = $row->title . ' (' . self::kind((string) $row->source) . ' #' . (int) $row->item_id . ')';

                $this->ownAddresses[self::compare($this->address($row))] ??= $label;

                // An address of this site may be written in more than one way (a menu item's, the
                // component's own): its item is known by its alias, or its ID and alias
                if (!Sources::isElsewhere((string) $row->source)) {
                    parse_str((string) parse_url((string) $row->link, PHP_URL_QUERY), $query);
                    [$id, $alias] = array_map('strval', explode(':', (string) ($query['id'] ?? ''), 2)) + [1 => ''];

                    if ($alias !== '') {
                        $this->ownAliases[$alias] ??= $label;
                        $this->ownAliases[$id . '-' . $alias] ??= $label;
                    }

                    $items[(int) $id] ??= $label;
                }
            }

            // Menu items that lead to an indexed item: their path is an address of it too
            try {
                $menus = $this->db->setQuery(
                    $this->db->getQuery(true)
                        ->select('path, link, language')
                        ->from($this->db->quoteName('#__menu'))
                        ->where('client_id = 0')
                        ->where('published = 1')
                        ->where('(link LIKE ' . $this->db->quote('%view=item%') . ' OR link LIKE ' . $this->db->quote('%view=article%') . ')')
                )->loadObjectList();
            } catch (\Throwable) {
                $menus = [];
            }

            foreach ($menus as $menu) {
                if (preg_match('/[?&]id=(\d+)/', (string) $menu->link, $match) && isset($items[(int) $match[1]])) {
                    $this->ownPaths[trim((string) $menu->path, '/')][(string) $menu->language] ??= $items[(int) $match[1]];
                }
            }
        }

        if (isset($this->ownAddresses[self::compare($url)])) {
            return $this->ownAddresses[self::compare($url)];
        }

        if (!self::isOwnHost($url)) {
            return '';
        }

        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH)), 'strlen'));
        $last     = preg_replace('/\.(html?|php)$/i', '', (string) end($segments));

        // A menu item's path, after the language code of the address if there is one; a path that
        // is the same in several languages is taken in the address's language
        $codes = $this->languageCodes();

        for ($skip = 0; $skip <= 1 && $skip < \count($segments); $skip++) {
            $path = implode('/', \array_slice($segments, $skip));

            if (isset($this->ownPaths[$path])) {
                $language = $skip ? ($codes[strtolower($segments[0])] ?? '') : '';

                return $this->ownPaths[$path][$language] ?? $this->ownPaths[$path]['*'] ?? reset($this->ownPaths[$path]);
            }
        }

        return $last !== '' ? ($this->ownAliases[$last] ?? '') : '';
    }

    /**
     * Language codes of addresses ("tw") and the languages they stand for ("zh-TW").
     *
     * @return  array<string, string>
     */
    private function languageCodes(): array
    {
        try {
            return array_change_key_case($this->db->setQuery(
                $this->db->getQuery(true)->select('sef, lang_code')->from($this->db->quoteName('#__languages'))
            )->loadAssocList('sef', 'lang_code'));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Whether an address is on this site.
     */
    private static function isOwnHost(string $url): bool
    {
        try {
            return strcasecmp((string) parse_url($url, PHP_URL_HOST), (string) Uri::getInstance(Uri::root())->getHost()) === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * An address as addresses are compared: without scheme, fragment and closing slash, the host in lower case.
     */
    public static function compare(string $url): string
    {
        $url   = preg_replace('/#.*$/', '', trim($url)) ?? '';
        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            return rtrim($url, '/');
        }

        return strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . rtrim((string) ($parts['path'] ?? ''), '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * @return  array<string, SourceInterface>
     */
    private function own(): array
    {
        return $this->own ??= Sources::own($this->db, $this->settings);
    }

    private function describe(object $row): object
    {
        $row->kind = self::kind((string) $row->source);
        $row->url  = $this->address($row);
        $row->site = Sources::siteName($this->settings, (string) $row->source);

        return $row;
    }
}
