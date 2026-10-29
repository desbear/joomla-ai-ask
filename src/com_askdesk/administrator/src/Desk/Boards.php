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
    /** The dashboard template (0.6.0) is kept as the board of no one */
    private const TEMPLATE = 0;

    /**
     * The dashboard template set by a Super User: its widgets, each marked as the template's.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function template(): array
    {
        return array_map(static fn ($w) => ['tpl' => true] + $w, $this->get(self::TEMPLATE));
    }

    /**
     * A board saved as the template for everyone (a Super User's): its widgets all the template's from
     * now; the people whose boards had template widgets the template no longer has lose them.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function saveTemplate(mixed $widgets): array
    {
        return $this->save(self::TEMPLATE, array_map(static fn ($w) => \is_array($w) ? ['tpl' => true] + $w : $w, \is_array($widgets) ? $widgets : []));
    }

    /**
     * A person's board with the template in it: each template widget there, with the template's content
     * (what it shows, its title) where this person put it and as large as they made it; theirs after;
     * one the template no longer has, gone. No board of their own yet: the template.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function forPerson(int $userId, bool $mayRemove = false): array
    {
        $own = $this->get($userId);

        // A Super User's own board is what the template is made from: theirs as they left it
        return $own ? self::merged($this->template(), $own, !$mayRemove) : $this->template();
    }

    /**
     * A person's board saved, the template kept in it: a template widget taken away comes back (unless
     * they may take it away: a Super User), its content the template's; only where it is and its size,
     * its columns, are theirs.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function savePerson(int $userId, mixed $widgets, bool $mayRemove = false): array
    {
        $saved = $this->save($userId, self::merged($this->template(), self::clean(\is_array($widgets) ? $widgets : []), !$mayRemove));

        return self::merged($this->template(), $saved, !$mayRemove);
    }

    /**
     * @param   array<int, array<string, mixed>>  $template
     * @param   array<int, array<string, mixed>>  $own
     *
     * @return  array<int, array<string, mixed>>
     */
    private static function merged(array $template, array $own, bool $keepTemplate): array
    {
        $mine = array_column($own, null, 'id');
        $tpl  = array_column($template, null, 'id');
        $out  = [];

        foreach ($template as $t) {
            $o = $mine[$t['id']] ?? null;

            if ($o) {
                // Where they put it, how large, its columns' widths and order, the name they gave it, its look: theirs; the rest
                // the template's (which columns are shown too: only their order is one's own, 0.6.2)
                $placed = ['x' => $o['x'], 'y' => $o['y'], 'w' => $o['w'], 'h' => $o['h'], 'cols' => $o['cols'], 'freeze' => $o['freeze'], 'fields' => self::reordered((array) ($t['fields'] ?? []), (array) ($o['fields'] ?? []))];

                // A number's look (0.6.7): theirs when they gave it one, else the template's
                $look = (array) ($o['look'] ?? []);

                if (($look['bg'] ?? '') !== '' || ($look['size'] ?? 'm') !== 'm') {
                    $placed['look'] = $look;
                }
                $out[]  = ($o['named'] ? ['title' => $o['title'], 'named' => true] : ['named' => false]) + $placed + ['base' => $t['title'], 'baseFields' => array_values((array) ($t['fields'] ?? []))] + $t;
            } elseif ($keepTemplate) {
                // Its name as the template has it (what a name of one's own gives way to)
                $out[] = ['base' => $t['title'], 'baseFields' => array_values((array) ($t['fields'] ?? []))] + $t;
            }
        }

        foreach ($own as $o) {
            // Theirs: not the template's, nor one it had once (gone with it)
            if (!isset($tpl[$o['id']]) && empty($o['tpl'])) {
                $out[] = ['tpl' => false] + $o;
            }
        }

        return $out;
    }

    /**
     * The template's columns in the order this person put them (0.6.2): theirs first as they ordered them, the
     * template's they have not placed after, in the template's order; none the template does not show.
     *
     * @param   string[]  $template
     * @param   string[]  $own
     *
     * @return  string[]
     */
    private static function reordered(array $template, array $own): array
    {
        $shown = array_values($template);
        $mine  = array_values(array_intersect(array_unique($own), $shown));

        return array_values(array_merge($mine, array_diff($shown, $mine)));
    }

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
                // A number's look (0.6.5; 0.6.7 one's own on a template's widget too): its card's colour (#rrggbb, or none) and its figure's size
                'look'     => [
                    'bg'   => \is_string($widget['look']['bg'] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $widget['look']['bg']) ? strtolower($widget['look']['bg']) : '',
                    'size' => \in_array($widget['look']['size'] ?? '', ['s', 'm', 'l', 'xl'], true) ? $widget['look']['size'] : 'm',
                ],
                // Of the dashboard template (0.6.0): kept by everyone, its content the template's
                'tpl'      => !empty($widget['tpl']),
                // Named by this person (0.6.1): a template widget keeps the name they gave it
                'named'    => !empty($widget['named']) && $text($widget['title'] ?? '', 120) !== '',
                // A change to many records at once made at once (0.6.1); else said first, to confirm
                'batchDirect' => !empty($widget['batchDirect']),
            ];
        }

        return $clean;
    }
}
