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
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Pages the administrator added one by one, by their address. Each is read on its own (its links
 * are not followed) and read again every day; they are read as the pages of listed websites are,
 * one "site" per host, so they are found, kept and removed the same way.
 *
 * @since  0.16.0
 */
final class ManualPages
{
    /** Sites of added pages are filed under the prefix of websites, this letter and a hash of the host. */
    public const SITE = 'm';

    /** Pages that may be added in all. */
    public const MAX = 2000;

    /** @var array<string, array>|null  The sites, worked out once per request */
    private static ?array $sites = null;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * @return  object[]  id, url, selector, created, created_by; newest first
     */
    public function all(): array
    {
        try {
            return $this->db->setQuery(
                $this->db->getQuery(true)->select('id, url, selector, created, created_by')->from($this->db->quoteName('#__mcpask_urls'))->order('id DESC')
            )->loadObjectList();
        } catch (\Throwable) {
            // Not installed yet: an update that has not run its SQL
            return [];
        }
    }

    /**
     * The added pages as websites to read: one per host, each listing its pages.
     *
     * @return  array<string, array{id: string, name: string, url: string, selector: string, limit: int, depth: int, internal: bool, manual: array<int, array{url: string, selector: string}>}>
     */
    public static function sites(DatabaseInterface $db): array
    {
        if (self::$sites !== null) {
            return self::$sites;
        }

        $sites = [];

        // Pages of this site are shown under its name, other pages under their host
        try {
            $ownHost = strtolower((string) Uri::getInstance(Uri::root())->getHost());
            $ownName = (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable) {
            [$ownHost, $ownName] = ['', ''];
        }

        foreach (array_reverse((new self($db))->all()) as $row) {
            $parts = parse_url((string) $row->url);
            $host  = strtolower((string) ($parts['host'] ?? ''));

            if ($host === '') {
                continue;
            }

            $id = self::SITE . substr(sha1($host), 0, 9);

            $sites[$id] ??= [
                'id'       => $id,
                'name'     => $host === $ownHost && $ownName !== '' ? $ownName : $host,
                'url'      => strtolower((string) ($parts['scheme'] ?? 'https')) . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/',
                'selector' => '',
                'limit'    => self::MAX,
                'depth'    => 1,
                'internal' => false,
                'manual'   => [],
            ];

            $sites[$id]['manual'][] = ['url' => (string) $row->url, 'selector' => (string) $row->selector];
        }

        return self::$sites = $sites;
    }

    public static function isManual(string $source): bool
    {
        return str_starts_with($source, WebSync::PREFIX . self::SITE) && \strlen($source) === \strlen(WebSync::PREFIX) + 10;
    }

    /**
     * Add addresses, one per line. Addresses that are not web addresses, that were added before,
     * or that are pages this site indexes already are not added.
     *
     * @param   string[]                  $lines
     * @param   callable(string): string  $indexedAs  What an address is indexed as already ("" when it is not)
     *
     * @return  array{added: string[], known: array<string, string>, invalid: string[], full: string[]}
     */
    public function add(array $lines, string $selector, int $userId, callable $indexedAs): array
    {
        $result = ['added' => [], 'known' => [], 'invalid' => [], 'full' => []];
        $count  = \count($this->all());
        $now    = Factory::getDate()->toSql();

        foreach (array_unique(array_filter(array_map('trim', $lines), 'strlen')) as $line) {
            $url = self::clean($line);

            if ($url === '') {
                $result['invalid'][] = $line;
                continue;
            }

            $as = $indexedAs($url);

            if ($as !== '') {
                $result['known'][$url] = $as;
                continue;
            }

            if ($count >= self::MAX) {
                $result['full'][] = $url;
                continue;
            }

            try {
                $page = (object) [
                    'url'        => $url,
                    'url_hash'   => sha1($url),
                    'selector'   => mb_substr(trim($selector), 0, 200),
                    'created'    => $now,
                    'created_by' => $userId,
                ];

                $this->db->insertObject('#__mcpask_urls', $page);
                $result['added'][] = $url;
                $count++;
            } catch (\Throwable) {
                // Added before
                $result['known'][$url] = '';
            }
        }

        self::$sites = null;

        return $result;
    }

    /**
     * @param  int[]  $ids
     */
    public function remove(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids) {
            $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mcpask_urls'))->whereIn('id', $ids))->execute();
        }

        self::$sites = null;
    }

    /**
     * An address as it is kept: http or https, a host, no fragment, no user or password.
     */
    public static function clean(string $url): string
    {
        $url   = preg_replace('/#.*$/', '', trim($url)) ?? '';
        $parts = parse_url($url);

        if (!$parts || !\in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || \strlen($url) > 1000) {
            return '';
        }

        return $url;
    }
}
