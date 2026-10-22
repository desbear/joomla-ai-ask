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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Each person's dashboard (0.5.0): the widgets on it, where and how big on its 12 columns, and what each
 * shows (a source's template, which fields, as what). Kept as given once made safe; nothing in it is
 * read as HTML.
 *
 * @since  0.5.0
 */
final class Boards
{
    public const KINDS = ['number', 'battery', 'pie', 'doughnut', 'bar', 'hbar', 'stacked', 'line', 'area', 'table', 'editgrid', 'gantt'];

    private const COLUMNS = 12;
    private const MAX     = 60;

    public function __construct(private DatabaseInterface $db)
    {
    }

    public static function make(): self
    {
        return new self(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * This person's widgets; an empty list before they have placed any.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function get(int $userId): array
    {
        $json = $this->db->setQuery(
            $this->db->getQuery(true)->select($this->db->quoteName('layout'))->from($this->db->quoteName('#__askdesk_boards'))
                ->where($this->db->quoteName('user_id') . ' = :id')->bind(':id', $userId, ParameterType::INTEGER)
        )->loadResult();
        $list = json_decode((string) $json, true);

        return \is_array($list) ? self::clean($list) : [];
    }

    /**
     * Keeps this person's widgets, as made safe; what was kept.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function save(int $userId, mixed $widgets): array
    {
        $clean = self::clean(\is_array($widgets) ? $widgets : []);
        $row   = (object) ['user_id' => $userId, 'layout' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated' => Factory::getDate()->toSql()];

        $this->db->transactionStart();

        try {
            $this->db->setQuery(
                $this->db->getQuery(true)->delete($this->db->quoteName('#__askdesk_boards'))->where($this->db->quoteName('user_id') . ' = :id')->bind(':id', $userId, ParameterType::INTEGER)
            )->execute();
            $this->db->insertObject('#__askdesk_boards', $row);
            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw $e;
        }

        return $clean;
    }

    /**
     * Only what a widget is: its id, kind, source and template, fields, title, place and size within the
     * columns, frozen columns and column widths for a table.
     *
     * @param   array<int, mixed>  $widgets
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function clean(array $widgets): array
    {
        $text  = static fn ($v, int $max) => \is_scalar($v) ? mb_substr(trim(strip_tags((string) $v)), 0, $max) : '';
        $clean = [];
        $seen  = [];

        foreach (\array_slice(array_values($widgets), 0, self::MAX) as $widget) {
            if (!\is_array($widget)) {
                continue;
            }

            $id = preg_replace('/[^a-z0-9_-]/i', '', (string) ($widget['id'] ?? ''));

            if ($id === '' || isset($seen[$id]) || !\in_array($widget['kind'] ?? '', self::KINDS, true) || !preg_match('/^[a-z0-9]{1,30}$/', (string) ($widget['source'] ?? ''))) {
                continue;
            }

            $seen[$id] = true;
            $w         = min(self::COLUMNS, max(1, (int) ($widget['w'] ?? 4)));
            $cols      = [];

            foreach (\is_array($widget['cols'] ?? null) ? \array_slice($widget['cols'], 0, 100, true) : [] as $key => $width) {
                if (($key = $text($key, 100)) !== '') {
                    $cols[$key] = min(480, max(64, (int) $width));
                }
            }

            $clean[] = [
                'id'       => mb_substr($id, 0, 40),
                'kind'     => $widget['kind'],
                'source'   => (string) $widget['source'],
                'template' => $text($widget['template'] ?? '', 100),
                'fields'   => \array_slice(array_values(array_filter(array_map(static fn ($f) => $text($f, 100), (array) ($widget['fields'] ?? [])), static fn ($f) => $f !== '')), 0, 100),
                'title'    => $text($widget['title'] ?? '', 120),
                'x'        => min(self::COLUMNS - $w, max(0, (int) ($widget['x'] ?? 0))),
                'y'        => min(1000, max(0, (int) ($widget['y'] ?? 0))),
                'w'        => $w,
                'h'        => min(40, max(1, (int) ($widget['h'] ?? 4))),
                'freeze'   => min(5, max(0, (int) ($widget['freeze'] ?? 1))),
                'cols'     => $cols,
            ];
        }

        return $clean;
    }
}
