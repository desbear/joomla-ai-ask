<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask\Source;

use Gss\Component\Mcpask\Administrator\Ask\PeerSync;
use Gss\Component\Mcpask\Administrator\Ask\Settings;
use Gss\Component\Mcpask\Administrator\Ask\WebSync;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * Chooses the content source of this site.
 *
 * @since  0.1.0
 */
final class Sources
{
    /**
     * FLEXIcontent keeps its items in the articles table, so when it is installed its items are
     * the site's content and are read as such, with their fields and addresses.
     */
    public static function current(DatabaseInterface $db, Settings $settings): SourceInterface
    {
        return FlexicontentSource::isAvailable() ? new FlexicontentSource($db, $settings) : new ContentSource($db, $settings);
    }

    /**
     * Every kind of this site's own content that is answered from, by name: the articles (or
     * FLEXIcontent items) first, then EasyBlog posts when EasyBlog is installed and included, then
     * what the enabled AI Ask plugins add (files of a download manager): all of it one index,
     * searched together for every answer.
     *
     * @return  array<string, SourceInterface>
     */
    public static function own(DatabaseInterface $db, Settings $settings): array
    {
        $current = self::current($db, $settings);
        $sources = [$current->name() => $current];

        if ($settings->includeEasyblog() && EasyblogSource::isAvailable()) {
            $sources['easyblog'] = new EasyblogSource($db);
        }

        foreach (self::added($db, $settings) as $source) {
            $sources[$source->name()] ??= $source;
        }

        return $sources;
    }

    /**
     * Sources the enabled AI Ask plugins add, by the event onMcpaskSources: each plugin puts its
     * SourceInterface objects in the "sources" argument. A name is a short word of its own (not
     * one of this site's kinds, not one of other sites), as the index keeps it with each page.
     *
     * @return  SourceInterface[]
     */
    private static function added(DatabaseInterface $db, Settings $settings): array
    {
        try {
            PluginHelper::importPlugin('mcpask');

            $event = new Event('onMcpaskSources', ['db' => $db, 'settings' => $settings, 'sources' => []]);
            Factory::getApplication()->getDispatcher()->dispatch('onMcpaskSources', $event);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter(
            (array) $event->getArgument('sources', []),
            static fn ($source) => $source instanceof SourceInterface
                && preg_match('/^[a-z][a-z0-9_]{1,29}$/', $source->name())
                && !\in_array($source->name(), ['content', 'flexicontent', 'easyblog'], true)
        ));
    }

    /**
     * The file behind a stored page of this site, when its source is one of files.
     *
     * @param   array<string, SourceInterface>  $own
     *
     * @return  array{type: string, size: int, open: string, download: string, page: int}|null
     */
    public static function file(array $own, object $page): ?array
    {
        $source = $own[$page->source] ?? null;

        if (!$source instanceof FileSourceInterface) {
            return null;
        }

        try {
            $file = $source->file((string) $page->link);
        } catch (\Throwable) {
            return null;
        }

        if ($file === null) {
            return null;
        }

        // The page of a PDF the passage came from ("p. 3", the heading the document reader gives it)
        $number = preg_match('/(?:^|›\s*)p\.\s*(\d+)\b/u', (string) ($page->heading ?? ''), $match) ? (int) $match[1] : 0;

        if ($number > 1 && $file['open'] !== '' && $file['type'] === 'pdf') {
            $file['open'] .= '#page=' . $number;
        }

        return $file + ['page' => $number];
    }

    /**
     * Whether stored pages came from elsewhere: another site with this package, or a website the
     * administrator listed. Those were public where they came from and keep their own addresses.
     */
    public static function isElsewhere(string $source): bool
    {
        return PeerSync::isPeer($source) || WebSync::isWeb($source);
    }

    /**
     * The sources searched besides this site's own.
     *
     * @return  string[]
     */
    public static function elsewhere(Settings $settings): array
    {
        return array_merge(
            array_map(static fn ($id) => PeerSync::PREFIX . $id, array_keys($settings->peers())),
            array_map(static fn ($id) => WebSync::PREFIX . $id, array_keys($settings->websites()))
        );
    }

    /**
     * The name of the site a stored page came from; empty for this site's own.
     */
    public static function siteName(Settings $settings, string $source): string
    {
        if (PeerSync::isPeer($source)) {
            return $settings->peers()[substr($source, \strlen(PeerSync::PREFIX))]['name'] ?? '';
        }

        if (WebSync::isWeb($source)) {
            return $settings->websites()[substr($source, \strlen(WebSync::PREFIX))]['name'] ?? '';
        }

        return '';
    }
}
