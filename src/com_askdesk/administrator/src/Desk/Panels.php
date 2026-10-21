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
                $views[] = self::clean($name, $panel->view($room, $staff));
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

        if (!$panel || !\in_array($action, ['search', 'choose', 'refresh'], true)) {
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
        $link  = \is_array($view['link'] ?? null) && $https($view['link']['url'] ?? null) !== '' ? ['title' => $text($view['link']['title'] ?? '', 80), 'url' => $https($view['link']['url'])] : null;

        return [
            'name'     => $name,
            'title'    => $text($view['title'] ?? '', 80),
            'subtitle' => $text($view['subtitle'] ?? '', 160),
            'state'    => \in_array($view['state'] ?? '', ['found', 'none', 'error', 'setup'], true) ? $view['state'] : 'none',
            'message'  => $text($view['message'] ?? '', 300),
            'fields'   => array_slice(array_values(array_filter(array_map(static fn ($field) => \is_array($field) ? ['label' => $text($field['label'] ?? '', 60), 'value' => $text($field['value'] ?? '', 400)] : null, $list($view['fields'] ?? [])), static fn ($field) => $field && $field['value'] !== '')), 0, 30),
            'tags'     => array_slice(array_values(array_filter(array_map(static fn ($tag) => $text($tag, 60), $list($view['tags'] ?? [])))), 0, 30),
            'link'     => $link,
            'search'   => \is_array($view['search'] ?? null) ? ['placeholder' => $text($view['search']['placeholder'] ?? '', 80)] : null,
            'results'  => array_slice(array_values(array_filter(array_map(static fn ($item) => \is_array($item) && $text($item['ref'] ?? '', 100) !== '' ? ['ref' => $text($item['ref'], 100), 'title' => $text($item['title'] ?? '', 120), 'meta' => $text($item['meta'] ?? '', 200)] : null, $list($view['results'] ?? [])))), 0, 20),
        ];
    }
}
