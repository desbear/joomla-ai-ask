<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Pages that are not to be answered from although they would be indexed: single pages the
 * administrator chose (kept by what they are, so a new address does not bring them back), and
 * addresses matching patterns, one per line, with * for anything. Such pages are taken out of the
 * index and are not read again.
 *
 * @since  0.16.0
 */
final class Ignores
{
    /** @var array<string, object>|null  Single pages, by "source:item" */
    private ?array $items = null;

    /** @var array<int, array{0: string, 1: string}>|null  Patterns as written, and as regular expressions */
    private ?array $patterns = null;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * @return  array<string, object>  id, source, item_id, title, url, note, created, created_by; by "source:item"
     */
    public function items(): array
    {
        if ($this->items === null) {
            $this->items = [];

            try {
                $rows = $this->db->setQuery(
                    $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__mcpask_ignores'))->order('id DESC')
                )->loadObjectList();
            } catch (\Throwable) {
                // Not installed yet: an update that has not run its SQL
                $rows = [];
            }

            foreach ($rows as $row) {
                $this->items[$row->source . ':' . (int) $row->item_id] = $row;
            }
        }

        return $this->items;
    }

    public function hasItem(string $source, int $itemId): bool
    {
        return isset($this->items()[$source . ':' . $itemId]);
    }

    /**
     * The pattern an address matches, if any.
     */
    public function pattern(string $url): string
    {
        if ($url === '') {
            return '';
        }

        if ($this->patterns === null) {
            $this->patterns = [];

            foreach ($this->settings->ignorePatterns() as $pattern) {
                $this->patterns[] = [$pattern, self::regex($pattern)];
            }
        }

        // Compared with the whole address, and with the path and query alone for patterns that start with /
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/') . (($query = parse_url($url, PHP_URL_QUERY)) ? '?' . $query : '');

        foreach ($this->patterns as [$pattern, $regex]) {
            if (preg_match($regex, $url) || (str_starts_with($pattern, '/') && preg_match($regex, $path))) {
                return $pattern;
            }
        }

        return '';
    }

    public function isIgnored(string $source, int $itemId, string $url): bool
    {
        return $this->hasItem($source, $itemId) || $this->pattern($url) !== '';
    }

    /**
     * Ignore a page from now on, and take it out of the index.
     */
    public function add(string $source, int $itemId, string $title, string $url, string $note, int $userId): void
    {
        if (!$this->hasItem($source, $itemId)) {
            $rule = (object) [
                'source'     => mb_substr($source, 0, 32),
                'item_id'    => $itemId,
                'title'      => mb_substr($title, 0, 255),
                'url'        => mb_substr($url, 0, 1024),
                'note'       => mb_substr(trim($note), 0, 255),
                'created'    => Factory::getDate()->toSql(),
                'created_by' => $userId,
            ];

            $this->db->insertObject('#__mcpask_ignores', $rule);
        }

        $this->items = null;
        (new Indexer($this->db, $this->settings))->remove($source, $itemId);
    }

    /**
     * Answer from a page again: it is read at the next synchronisation.
     *
     * @return  object|null  The rule that was removed
     */
    public function restore(int $id): ?object
    {
        $row = $this->db->setQuery($this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__mcpask_ignores'))->where('id = ' . $id))->loadObject();

        if ($row) {
            $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mcpask_ignores'))->where('id = ' . $id))->execute();
            $this->items = null;
        }

        return $row ?: null;
    }

    /**
     * Take out of the index what is ignored now: after the rules changed, and at each synchronisation.
     *
     * @param   callable(object): string  $address  The address of an indexed page (id, source, item_id, link)
     *
     * @return  int  Pages taken out
     */
    public function purge(callable $address): int
    {
        $patterns = $this->settings->ignorePatterns();

        if (!$this->items() && !$patterns) {
            return 0;
        }

        $db      = $this->db;
        $indexer = new Indexer($db, $this->settings);
        $removed = 0;
        $query   = $db->getQuery(true)->select('id, source, item_id, link')->from($db->quoteName('#__mcpask_documents'));

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            if ($this->hasItem($row->source, (int) $row->item_id) || ($patterns && $this->pattern($address($row)) !== '')) {
                $indexer->remove($row->source, (int) $row->item_id);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * A pattern as a regular expression: * for anything, the rest as written, the whole address,
     * letters of either case.
     */
    public static function regex(string $pattern): string
    {
        return '#^' . str_replace('\*', '.*', preg_quote(trim($pattern), '#')) . '$#iu';
    }
}
