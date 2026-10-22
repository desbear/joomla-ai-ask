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

        self::$all = [];

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

        return ['id' => self::text($got['id'] ?? $template, 100), 'name' => self::text($got['name'] ?? '', 200), 'fields' => $fields];
    }

    /**
     * @param   array{page: int, size: int, query: string, mine: bool}  $query
     *
     * @return  array{rows: array<int, array<string, mixed>>, total: int, page: int, size: int}
     */
    public static function rows(SourceInterface $source, object $staff, string $template, array $query): array
    {
        $query = ['page' => max(1, (int) $query['page']), 'size' => min(100, max(1, (int) $query['size'])), 'query' => mb_substr(trim((string) $query['query']), 0, 100), 'mine' => (bool) $query['mine']];
        $got   = $source->rows($staff, $template, $query);
        $rows  = array_values(array_filter(array_map([self::class, 'row'], \array_slice((array) ($got['rows'] ?? []), 0, 100))));

        return ['rows' => $rows, 'total' => max(\count($rows), (int) ($got['total'] ?? 0)), 'page' => $query['page'], 'size' => $query['size']];
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

        return ['id' => $id, 'no' => self::text($row['no'] ?? '', 100), 'title' => self::text($row['title'] ?? '', 300), 'values' => $values];
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
