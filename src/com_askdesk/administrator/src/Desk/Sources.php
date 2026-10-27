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
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * The sources plugins give the dashboard (0.5.0): gathered once a request, what each says made safe to
 * draw (text only, sizes kept small, addresses of this site only).
 *
 * @since  0.5.0
 */
final class Sources
{
    public const KINDS = ['text', 'number', 'date', 'select', 'multi'];

    /** @var SourceInterface[]|null */
    private static ?array $all = null;

    /**
     * @return  SourceInterface[]  By name
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        // The desk's own chats first (its cases widget), then what plugins give
        self::$all = ['askdesk' => new Cases()];

        try {
            PluginHelper::importPlugin('askdesk');
            $event = new Event('onAskdeskSources', ['sources' => []]);
            Factory::getApplication()->getDispatcher()->dispatch('onAskdeskSources', $event);

            foreach ((array) $event->getArgument('sources', []) as $source) {
                if ($source instanceof SourceInterface && preg_match('/^[a-z0-9]{1,30}$/', $source->name())) {
                    self::$all[$source->name()] = $source;
                }
            }
        } catch (\Throwable) {
            // A plugin that fails adds nothing
        }

        return self::$all;
    }

    public static function get(string $name): ?SourceInterface
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Each source and whether this person can use it.
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function list(object $staff): array
    {
        $list = [];

        foreach (self::all() as $name => $source) {
            try {
                $status = $source->status($staff);
            } catch (\Throwable) {
                $status = ['ready' => false];
            }

            $list[] = [
                'name'      => $name,
                'title'     => self::text($source->title(), 60),
                'ready'     => !empty($status['ready']),
                'connected' => !empty($status['connected']),
                // Its records can be deleted from the dashboard (0.6.1: a source with remove())
                'removable' => method_exists($source, 'remove'),
                'account'   => self::text($status['account'] ?? '', 120),
                'connect'   => self::local($status['connect'] ?? ''),
                'manage'    => self::local($status['manage'] ?? ''),
            ];
        }

        return $list;
    }

    /**
     * @return  array{items: array<int, array{id: string, name: string, description: string}>, total: int}
     */
    public static function templates(SourceInterface $source, object $staff, string $query, int $page): array
    {
        $got   = $source->templates($staff, mb_substr(trim($query), 0, 100), max(1, $page));
        $items = [];

        foreach (\array_slice((array) ($got['items'] ?? []), 0, 100) as $item) {
            if (\is_array($item) && ($id = self::text($item['id'] ?? '', 100)) !== '') {
                $items[] = ['id' => $id, 'name' => self::text($item['name'] ?? '', 200), 'description' => self::text($item['description'] ?? '', 300)];
            }
        }

        return ['items' => $items, 'total' => max(\count($items), (int) ($got['total'] ?? 0))];
    }

    /**
     * @return  array{id: string, name: string, fields: array<int, array<string, mixed>>}
     */
    public static function fields(SourceInterface $source, object $staff, string $template): array
    {
        $got    = $source->fields($staff, $template);
        $fields = [];

        foreach (\array_slice((array) ($got['fields'] ?? []), 0, 200) as $field) {
            $key = \is_array($field) ? self::text($field['key'] ?? '', 100) : '';

            if ($key === '' || !\in_array($field['kind'] ?? '', self::KINDS, true)) {
                continue;
            }

            $fields[] = [
                'key'     => $key,
                'label'   => self::text($field['label'] ?? $key, 120),
                'kind'    => $field['kind'],
                'options' => \array_slice(array_values(array_filter(array_map(static fn ($o) => self::text($o, 120), (array) ($field['options'] ?? [])), static fn ($o) => $o !== '')), 0, 200),
                'role'    => self::text($field['role'] ?? '', 20),
            ];
        }

        // What may be done to records picked together, besides changing a field (0.6.2: the desk's cases closed):
        // {name, label, confirm}, done by the source's act()
        $actions = [];

        foreach (\array_slice((array) ($got['actions'] ?? []), 0, 10) as $action) {
            $name = \is_array($action) ? (string) ($action['name'] ?? '') : '';

            if (preg_match('/^[a-z][a-z0-9_]{0,29}$/', $name) && method_exists($source, 'act')) {
                $actions[] = ['name' => $name, 'label' => self::text($action['label'] ?? $name, 60), 'confirm' => self::text($action['confirm'] ?? '', 200)];
            }
        }

        return ['id' => self::text($got['id'] ?? $template, 100), 'name' => self::text($got['name'] ?? '', 200), 'fields' => $fields, 'actions' => $actions];
    }

    /**
     * @param   array{page: int, size: int, query: string, mine: bool}  $query
     *
     * @return  array{rows: array<int, array<string, mixed>>, total: int, page: int, size: int}
     */
    public static function rows(SourceInterface $source, object $staff, string $template, array $query): array
    {
        // A time range when asked (days, in the site's time): from, to (Y-m-d), either may be empty
        $day   = static fn ($d) => \is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)) ? $d : '';
        // Only those changed since a moment (UTC, ISO 8601), when a table checks for others' changes (0.6.2): a
        // source that can says so ({since: true}); one that cannot gives its first page as asked
        $since = \is_string($query['since'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $query['since']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($query['since'])) : '';
        $query = ['page' => max(1, (int) $query['page']), 'size' => min(100, max(1, (int) $query['size'])), 'query' => mb_substr(trim((string) $query['query']), 0, 100), 'mine' => (bool) $query['mine'], 'person' => max(0, (int) ($query['person'] ?? 0)), 'from' => $day($query['from'] ?? ''), 'to' => $day($query['to'] ?? ''), 'since' => $since];
        $now   = gmdate('Y-m-d\TH:i:s\Z');
        $got   = $source->rows($staff, $template, $query);
        $rows  = array_values(array_filter(array_map([self::class, 'row'], \array_slice((array) ($got['rows'] ?? []), 0, 100))));

        return ['rows' => $rows, 'total' => max(\count($rows), (int) ($got['total'] ?? 0)), 'page' => $query['page'], 'size' => $query['size'], 'now' => $now, 'since' => $since !== '' && !empty($got['since'])];
    }

    /**
     * A record as the console draws it; null when it has no id.
     *
     * @return  array{id: string, no: string, title: string, values: array<string, mixed>}|null
     */
    public static function row(mixed $row): ?array
    {
        if (!\is_array($row) || ($id = self::text($row['id'] ?? '', 100)) === '') {
            return null;
        }

        $values = [];

        foreach (\array_slice((array) ($row['values'] ?? []), 0, 200, true) as $key => $value) {
            $key = self::text($key, 100);

            if ($key === '') {
                continue;
            }

            $values[$key] = match (true) {
                \is_int($value), \is_float($value) => $value,
                \is_array($value)                  => \array_slice(array_values(array_filter(array_map(static fn ($v) => self::text($v, 200), $value), static fn ($v) => $v !== '')), 0, 50),
                default                            => self::text($value, 2000),
            };
        }

        $clean = ['id' => $id, 'no' => self::text($row['no'] ?? '', 100), 'title' => self::text($row['title'] ?? '', 300), 'values' => $values];

        // The record's own page in its system (to edit it there): an address on the web only
        if (\is_string($row['link'] ?? null) && preg_match('#^https://[^\s"<>]+$#i', $row['link'])) {
            $clean['link'] = mb_substr($row['link'], 0, 500);
        }

        // Not to be changed by this person (0.6.1): why, in a word the dashboard knows ('review': under review
        // in its system; 'denied': not theirs to change), shown greyed and left out of a batch change
        if (\in_array($row['locked'] ?? '', ['review', 'denied'], true)) {
            $clean['locked'] = $row['locked'];
        }

        // A chat of the desk's own (its cases): opened in the console from the row
        if (isset($row['room']) && (int) $row['room'] > 0) {
            $clean['room'] = (int) $row['room'];
        }

        return $clean;
    }

    private static function text(mixed $value, int $max): string
    {
        return \is_scalar($value) ? mb_substr(trim(strip_tags((string) $value)), 0, $max) : '';
    }

    /** An address of this site only (where to sign in, say); empty otherwise */
    private static function local(mixed $url): string
    {
        $root = rtrim(Uri::root(), '/') . '/';

        return \is_string($url) && str_starts_with($url, $root) && !preg_match('#[\s<>"\\\\]#', $url) ? mb_substr($url, 0, 500) : '';
    }
}
