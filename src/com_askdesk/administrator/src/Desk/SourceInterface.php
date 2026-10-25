<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

\defined('_JEXEC') or die;

/**
 * Where the dashboard's widgets get their records (0.5.0): another system's templates (kinds of record,
 * a form system's form templates, say), each one's fields, and its records, read as the person looking.
 * Offered by plugins of the group "askdesk" on the event onAskdeskSources (append to the argument
 * "sources"). The console draws them all the same way; a widget is one template of one source.
 *
 * Fields have one of a few kinds: text, number, date (Y-m-d), select (one of options), multi (some of
 * options). A record is {id, no, title, values: {field key: value}} (a multi's value a list of strings).
 *
 * When the person must sign in to the other system first, a method throws SignInNeeded with the address
 * that starts it.
 *
 * Text only: nothing in it is read as HTML.
 *
 * A source may also have remove(object $staff, string $template, string $row): void (0.6.1), a record
 * deleted from the dashboard (asked first there, always: it is for good).
 *
 * And act(object $staff, string $template, string $row, string $action): array (0.6.2), something done to
 * records picked together (asked first there), offered as fields()'s "actions": [{name, label, confirm}]
 * (confirm with %s for how many); it answers the record's values that changed.
 *
 * @since  0.5.0
 */
interface SourceInterface
{
    /** A short name of its own, letters and digits: "bizform". */
    public function name(): string;

    /** Its name as people know it: "BizForm". */
    public function title(): string;

    /**
     * Whether it can be used, and by this person: {ready (set up), connected (signed in), account (who
     * they are there), connect (where to sign in: this site's address), manage (where to see or stop it)}.
     *
     * @return  array<string, mixed>
     */
    public function status(object $staff): array;

    /**
     * Its templates this person can see, a page of them, by name when asked.
     *
     * @return  array{items: array<int, array{id: string, name: string, description?: string}>, total: int}
     */
    public function templates(object $staff, string $query, int $page): array;

    /**
     * A template and its fields.
     *
     * @return  array{id: string, name: string, fields: array<int, array{key: string, label: string, kind: string, options?: string[], role?: string}>}
     */
    public function fields(object $staff, string $template): array;

    /**
     * A page of a template's records: {page, size (100 at most), query (words), mine (only this
     * person's), from, to (days Y-m-d, in the site's time, either empty: the records made in that time,
     * 0.6.0)}. A row may say it is not this person's to change (locked, 0.6.1: 'review' under review,
     * 'denied' not theirs), shown greyed and left out of a batch change.
     *
     * @param   array{page: int, size: int, query: string, mine: bool, from?: string, to?: string}  $query
     *
     * @return  array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function rows(object $staff, string $template, array $query): array;

    /**
     * One value of one record changed by this person; the record as it is after.
     *
     * @return  array<string, mixed>
     */
    public function update(object $staff, string $template, string $row, string $field, mixed $value): array;
}
