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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * The panels plugins add beside a chat in the console (0.2.0): gathered once a request, each
 * one's view made safe to draw, and one's action passed to it.
 *
 * @since  0.2.0
 */
final class Panels
{
    /** @var PanelInterface[]|null */
    private static ?array $all = null;

    /**
     * @return  PanelInterface[]  By name
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        self::$all = [];

        try {
            PluginHelper::importPlugin('askdesk');
            $event = new Event('onAskdeskPanels', ['panels' => []]);
            Factory::getApplication()->getDispatcher()->dispatch('onAskdeskPanels', $event);

            foreach ((array) $event->getArgument('panels', []) as $panel) {
                if ($panel instanceof PanelInterface && preg_match('/^[a-z0-9]{1,30}$/', $panel->name())) {
                    self::$all[$panel->name()] = $panel;
                }
            }
        } catch (\Throwable) {
            // A plugin that fails adds nothing
        }

        return self::$all;
    }

    /**
     * What each panel shows beside a chat.
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function views(object $room, object $staff): array
    {
        $views = [];

        foreach (self::all() as $name => $panel) {
            try {
                $view = $panel->view($room, $staff);

                // Nothing to show for this chat: not shown at all
                if ($view === []) {
                    continue;
                }

                $views[] = self::clean($name, $view);
            } catch (\Throwable) {
                $views[] = self::clean($name, ['state' => 'error']);
            }
        }

        return $views;
    }

    /**
     * One panel's action; what it shows after.
     *
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>|null  Null when there is no such panel
     */
    public static function act(string $name, object $room, object $staff, string $action, array $input): ?array
    {
        $panel = self::all()[$name] ?? null;

        if (!$panel || !\in_array($action, ['search', 'choose', 'refresh', 'draft', 'send'], true)) {
            return null;
        }

        try {
            return self::clean($name, $panel->act($room, $staff, $action, $input));
        } catch (\Throwable) {
            return self::clean($name, ['state' => 'error']);
        }
    }

    /**
     * Only what the console draws, as text, sizes kept small; links https only.
     *
     * @param   array<string, mixed>  $view
     *
     * @return  array<string, mixed>
     */
    private static function clean(string $name, array $view): array
    {
        $text  = static fn ($value, int $length = 300) => \is_scalar($value) ? mb_substr(trim((string) $value), 0, $length) : '';
        $https = static fn ($value) => \is_string($value) && preg_match('#^https://[^\s"<>]+$#i', $value) ? $value : '';
        $list  = static fn ($value) => \is_array($value) ? array_values($value) : [];
        $note  = \is_array($view['note'] ?? null) ? $view['note'] : null;
        $brief = \is_array($view['brief'] ?? null) ? $view['brief'] : null;
        $items = \is_array($view['list'] ?? null) ? $view['list'] : null;
        $link  = \is_array($view['link'] ?? null) && $https($view['link']['url'] ?? null) !== '' ? ['title' => $text($view['link']['title'] ?? '', 80), 'url' => $https($view['link']['url'])] : null;

        return [
            'name'     => $name,
            'title'    => $text($view['title'] ?? '', 80),
            'subtitle' => $text($view['subtitle'] ?? '', 160),
            'state'    => \in_array($view['state'] ?? '', ['found', 'none', 'error', 'setup'], true) ? $view['state'] : 'none',
            'message'  => $text($view['message'] ?? '', 300),
            'fields'   => array_slice(array_values(array_filter(array_map(static fn ($field) => \is_array($field) ? ['label' => $text($field['label'] ?? '', 60), 'value' => $text($field['value'] ?? '', 400)] : null, $list($view['fields'] ?? [])), static fn ($field) => $field && $field['value'] !== '')), 0, 30),
            'tags'     => array_slice(array_values(array_filter(array_map(static fn ($tag) => $text($tag, 80), $list($view['tags'] ?? [])))), 0, 200),
            'link'     => $link,
            'search'   => \is_array($view['search'] ?? null) ? ['placeholder' => $text($view['search']['placeholder'] ?? '', 80)] : null,
            'note'     => $note ? [
                'title'   => $text($note['title'] ?? '', 80),
                'text'    => $text($note['text'] ?? '', 4000),
                'hint'    => $text($note['hint'] ?? '', 200),
                'sent'    => $text($note['sent'] ?? '', 200),
                'author'  => $text($note['author'] ?? '', 200),
                'blocked' => $text($note['blocked'] ?? '', 300),
                'choices' => array_slice(array_values(array_filter(array_map(static fn ($choice) => \is_array($choice) && $text($choice['value'] ?? '', 100) !== '' ? [
                    'value'   => $text($choice['value'], 100),
                    'label'   => $text(($choice['label'] ?? '') ?: $choice['value'], 60),
                    'group'   => $text($choice['group'] ?? '', 60),
                    'checked' => !empty($choice['checked']),
                ] : null, $list($note['choices'] ?? [])))), 0, 60),
            ] : null,
            'list'     => $items ? [
                'title' => $text($items['title'] ?? '', 80),
                'empty' => $text($items['empty'] ?? '', 160),
                'total' => max(0, (int) ($items['total'] ?? 0)),
                'more'  => \is_array($items['more'] ?? null) && $https($items['more']['url'] ?? null) !== '' ? ['title' => $text($items['more']['title'] ?? '', 80), 'url' => $https($items['more']['url'])] : null,
                'items' => array_slice(array_values(array_filter(array_map(static fn ($item) => \is_array($item) ? [
                    'title' => $text($item['title'] ?? '', 120),
                    'meta'  => $text($item['meta'] ?? '', 160),
                    'text'  => $text($item['text'] ?? '', 4000),
                    'tags'  => array_slice(array_values(array_filter(array_map(static fn ($tag) => $text($tag, 60), $list($item['tags'] ?? [])))), 0, 10),
                ] : null, $list($items['items'] ?? [])), static fn ($item) => $item && ($item['title'] !== '' || $item['text'] !== ''))), 0, 50),
            ] : null,
            'icon'     => preg_match('/^[a-z]{1,20}$/', (string) ($view['icon'] ?? '')) ? (string) $view['icon'] : '',
            'look'     => ($view['look'] ?? '') === 'ai' ? 'ai' : '',
            'follow'   => \in_array($view['follow'] ?? '', ['draft', 'refresh'], true) ? $view['follow'] : '',
            'brief'    => $brief ? [
                'generated' => $text($brief['generated'] ?? '', 120),
                'stale'     => !empty($brief['stale']),
                'notice'    => $text($brief['notice'] ?? '', 200),
                'sections'  => array_slice(array_values(array_filter(array_map(static fn ($section) => \is_array($section) ? [
                    'title'  => $text($section['title'] ?? '', 60),
                    'tone'   => \in_array($section['tone'] ?? '', ['info', 'warn', 'ok'], true) ? $section['tone'] : 'info',
                    'points' => array_slice(array_values(array_filter(array_map(static fn ($point) => \is_array($point) && $text($point['text'] ?? '', 400) !== '' ? [
                        'text'    => $text($point['text'], 400),
                        'sources' => array_slice(array_values(array_filter(array_map(static fn ($source) => $text($source, 40), $list($point['sources'] ?? [])))), 0, 6),
                    ] : null, $list($section['points'] ?? [])))), 0, 8),
                ] : null, $list($brief['sections'] ?? [])), static fn ($section) => $section && $section['points'])), 0, 6),
            ] : null,
            'results'  => array_slice(array_values(array_filter(array_map(static fn ($item) => \is_array($item) && $text($item['ref'] ?? '', 100) !== '' ? ['ref' => $text($item['ref'], 100), 'title' => $text($item['title'] ?? '', 120), 'meta' => $text($item['meta'] ?? '', 200)] : null, $list($view['results'] ?? [])))), 0, 20),
        ];
    }
}
