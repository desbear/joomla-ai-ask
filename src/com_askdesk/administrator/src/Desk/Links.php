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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Links to the site's articles in what the people answering write: made short (the article's
 * number, nothing more), and shown below the message as a card to open, with the article's title,
 * its first words and its first picture. Only articles of this site a guest may read; nothing is
 * fetched from elsewhere.
 *
 * @since  0.1.0
 */
final class Links
{
    /** Cards shown with one message at most. */
    private const MAX = 3;

    private const URL = '#\bhttps?://[^\s<>"\']+#iu';

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * The text with links to the site's articles made short.
     */
    public function shorten(string $text): string
    {
        return (string) preg_replace_callback(self::URL, function (array $match): string {
            [$url, $tail] = self::split($match[0]);
            $id = $this->articleId($url);

            return ($id ? self::articleUrl($id) : $url) . $tail;
        }, $text);
    }

    /**
     * The address of an article of this site, made short.
     */
    public function short(string $url): string
    {
        $id = $this->articleId($url);

        return $id ? self::articleUrl($id) : $url;
    }

    /**
     * Cards for the site's articles linked in a text, in the order linked.
     *
     * @return  array<int, array{url: string, title: string, excerpt: string, image: string}>
     */
    public function cards(string $text): array
    {
        preg_match_all(self::URL, $text, $matches);

        $ids = [];

        foreach ($matches[0] as $url) {
            $id = $this->articleId(self::split($url)[0]);

            if ($id && !\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        $ids = \array_slice($ids, 0, self::MAX);

        if (!$ids) {
            return [];
        }

        $db    = $this->db;
        $now   = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->select('id, title, introtext, ' . $db->quoteName('fulltext') . ', images')
            ->from($db->quoteName('#__content'))
            ->whereIn('id', $ids)
            ->where('state = 1')
            ->where('access = 1')
            ->where('(publish_up IS NULL OR publish_up <= :now1)')
            ->where('(publish_down IS NULL OR publish_down > :now2)')
            ->bind(':now1', $now)
            ->bind(':now2', $now);
        $rows  = $db->setQuery($query)->loadObjectList('id');
        $cards = [];

        foreach ($ids as $id) {
            $row = $rows[$id] ?? null;

            if (!$row) {
                continue;
            }

            $html = (string) $row->introtext . ' ' . (string) $row->fulltext;

            $cards[] = [
                'url'     => self::articleUrl($id),
                'title'   => mb_substr(trim((string) $row->title), 0, 200),
                'excerpt' => self::excerpt($html),
                'image'   => self::image((string) $row->images, $html),
            ];
        }

        return $cards;
    }

    /**
     * The number of the article an address of this site opens, or 0.
     */
    private function articleId(string $url): int
    {
        $parts = parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $here  = Uri::getInstance(Uri::root());

        if (!\is_array($parts) || strcasecmp((string) ($parts['host'] ?? ''), (string) $here->getHost()) !== 0) {
            return 0;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $option = (string) ($query['option'] ?? '');

        // index.php?option=com_content&view=article&id=12:alias, or the same with the option left
        // out (a menu item of the site's articles supplies it: /tw/?view=article&id=12)
        if (\in_array($option, ['', 'com_content'], true) && ($query['view'] ?? '') === 'article' && preg_match('/^(\d+)/', (string) ($query['id'] ?? ''), $id)) {
            return (int) $id[1];
        }

        if ($option !== '' || isset($query['view'])) {
            return 0;
        }

        // A search engine friendly address, as the address bar shows it: its last part the
        // article's number and alias (/faq/12-alias), or its alias alone; found in the articles
        return $this->articleOfPath((string) ($parts['path'] ?? ''));
    }

    /**
     * The article whose alias (with its number, or alone) ends an address's path, when only one
     * article has it; 0 for none.
     */
    private function articleOfPath(string $path): int
    {
        $last = basename((string) preg_replace('#(?:\.html?|/)$#i', '', rawurldecode($path)));

        if ($last === '' || $last === 'index.php' || !preg_match('/^[\p{L}\p{N}_-]+$/u', $last)) {
            return 0;
        }

        $db = $this->db;

        if (preg_match('/^(\d+)-(.+)$/u', $last, $match)) {
            $id    = (int) $match[1];
            $alias = $match[2];
            $found = (int) $db->setQuery(
                $db->getQuery(true)->select('id')->from($db->quoteName('#__content'))->where('id = :id')->where('alias = :alias')
                    ->bind(':id', $id, ParameterType::INTEGER)->bind(':alias', $alias)
            )->loadResult();

            if ($found) {
                return $found;
            }
        }

        // An alias alone: not when it is a menu item's (a page of its own, not an article)
        $menu = (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__menu'))->where('alias = :menu')->where('client_id = 0')->where('published = 1')->bind(':menu', $last)
        )->loadResult();

        if ($menu) {
            return 0;
        }

        $ids = $db->setQuery(
            $db->getQuery(true)->select('id')->from($db->quoteName('#__content'))->where('alias = :alias')->where('state = 1')->bind(':alias', $last),
            0,
            2
        )->loadColumn();

        return \count($ids) === 1 ? (int) $ids[0] : 0;
    }

    private static function articleUrl(int $id): string
    {
        return self::root() . '/index.php?option=com_content&view=article&id=' . $id;
    }

    /**
     * The site's address, without a script name a request may carry along.
     */
    private static function root(bool $path = false): string
    {
        return (string) preg_replace('#/index\.php$#', '', rtrim(Uri::root($path), '/'));
    }

    /**
     * An address, and what follows it that is not part of it (a full stop, a bracket).
     *
     * @return  array{0: string, 1: string}
     */
    private static function split(string $url): array
    {
        return preg_match('/^(.*?)([.,;:!?)\]}」』。，、！？]+)$/u', $url, $match) ? [$match[1], $match[2]] : [$url, ''];
    }

    private static function excerpt(string $html): string
    {
        $text = html_entity_decode(strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', '', str_replace(['<br', '</p>', '</div>'], [' <br', ' </p>', ' </div>'], $html))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Without addresses (a source quoted at the end)
        $text = (string) preg_replace('#\S*https?://\S+#u', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text), " \t:：");

        return mb_strlen($text) > 120 ? mb_substr($text, 0, 119) . '…' : $text;
    }

    /**
     * The article's picture: its intro or full picture, else the first picture in its text; only
     * pictures of this site.
     */
    private static function image(string $images, string $html): string
    {
        $set        = json_decode($images, true);
        $candidates = [(string) ($set['image_intro'] ?? ''), (string) ($set['image_fulltext'] ?? '')];

        if (preg_match('#<img\b[^>]*\bsrc=["\']([^"\']+)["\']#i', $html, $match)) {
            $candidates[] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        foreach ($candidates as $candidate) {
            $path = trim((string) (HTMLHelper::_('cleanImageURL', trim($candidate))->url ?? ''));
            $path = (string) preg_replace('#^' . preg_quote(self::root(), '#') . '#i', '', $path);
            $path = '/' . ltrim((string) strtok($path, '?#'), '/');

            // A picture of this site that is there (not one only the live site has)
            if ($path !== '/' && !str_contains($path, '..') && preg_match('#^/images/[^\s"\'<>]+\.(?:jpe?g|png|gif|webp|avif)$#i', $path) && is_file(JPATH_ROOT . rawurldecode($path))) {
                return self::root(true) . $path;
            }
        }

        return '';
    }
}
