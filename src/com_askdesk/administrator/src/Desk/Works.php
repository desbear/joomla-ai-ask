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
 * Work cards in a chat (0.6.1): a form picked from the box's form button, filled in the chat where
 * only the people answering see it, sent on confirming; kept with the chat (who, when, the card as it
 * is now), apart from its messages (the visitor never gets them). The forms and what they make are the
 * panels' (WorkInterface); what they made, the panels' own cards beside the chat.
 *
 * @since  0.6.1
 */
final class Works
{
    private const TABLE = '#__askdesk_work';

    /**
     * The panels that offer work, by name.
     *
     * @return  WorkInterface[]
     */
    public static function panels(): array
    {
        return array_filter(Panels::all(), static fn ($panel) => $panel instanceof WorkInterface);
    }

    /**
     * The forms to pick from for this chat, all panels' (the first the usual one).
     *
     * @return  array<int, array{panel: string, id: string, title: string, description: string, first: bool, suggest: string, before: string}>
     */
    public static function forms(object $room, object $staff): array
    {
        $forms = [];

        foreach (self::panels() as $name => $panel) {
            try {
                foreach ($panel->forms($room, $staff) as $form) {
                    $id = \is_array($form) ? (string) ($form['id'] ?? '') : '';

                    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
                        continue;
                    }

                    $forms[] = [
                        'panel'       => $name,
                        'id'          => $id,
                        'title'       => self::text($form['title'] ?? '', 80),
                        'description' => self::text($form['description'] ?? '', 200),
                        'first'       => !empty($form['first']),
                        'suggest'     => self::text($form['suggest'] ?? '', 200),
                        // The form the suggestion is to fill first (a customer record before a to-do, say)
                        'before'      => preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) ($form['before'] ?? '')) ? (string) $form['before'] : '',
                    ];
                }
            } catch (\Throwable) {
                // A panel that fails offers nothing
            }
        }

        usort($forms, static fn ($a, $b) => $b['first'] <=> $a['first']);

        return $forms;
    }

    /**
     * A new card of a form in the chat, as the panel starts it.
     *
     * @return  array<string, mixed>|null  The card as listed; null when there is no such form
     */
    public static function start(object $room, object $staff, string $name, string $form): ?array
    {
        $panel = self::panels()[$name] ?? null;

        if (!$panel || !\in_array($form, array_column(self::forms($room, $staff), 'id'), true)) {
            return null;
        }

        $card = Panels::card($panel->work($room, $staff, $form, 'start', []));

        if (!$card) {
            return null;
        }

        $db  = self::db();
        $now = Factory::getDate()->toSql();
        $row = (object) [
            'room_id'  => (int) $room->id,
            'staff_id' => (int) $staff->user_id,
            'panel'    => $name,
            'form'     => $form,
            'card'     => json_encode($card, JSON_UNESCAPED_UNICODE),
            'state'    => 'open',
            'created'  => $now,
            'modified' => $now,
        ];

        $db->insertObject(self::TABLE, $row, 'id');

        return self::one($room, (int) $row->id);
    }

    /**
     * Something done in a card: "fill", "submit" ({values}) or "drop" (not wanted after all: gone).
     *
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>|null  The card as listed now; null when there is no such card (or it is gone)
     */
    public static function act(object $room, object $staff, int $id, string $action, array $input): ?array
    {
        $row = self::row($room, $id);

        if (!$row || $row->state !== 'open' || !\in_array($action, ['fill', 'submit', 'drop'], true)) {
            return $row && $row->state !== 'dropped' ? self::listed($row) : null;
        }

        $db  = self::db();
        $now = Factory::getDate()->toSql();

        if ($action === 'drop') {
            $db->setQuery($db->getQuery(true)->update($db->quoteName(self::TABLE))->set($db->quoteName('state') . ' = ' . $db->quote('dropped'))->set($db->quoteName('modified') . ' = :now')->where($db->quoteName('id') . ' = :id')->bind(':now', $now)->bind(':id', $id, ParameterType::INTEGER))->execute();

            return null;
        }

        $panel = self::panels()[$row->panel] ?? null;

        if (!$panel) {
            return self::listed($row);
        }

        $card = Panels::card($panel->work($room, $staff, (string) $row->form, $action, $input));

        if (!$card) {
            return self::listed($row);
        }

        $row->card     = json_encode($card, JSON_UNESCAPED_UNICODE);
        $row->state    = $card['sent'] !== '' && $card['made'] ? 'sent' : 'open';
        $row->modified = $now;
        $db->updateObject(self::TABLE, $row, 'id');

        return self::listed($row);
    }

    /**
     * The cards of a chat, the first first (those dropped not).
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function of(object $room): array
    {
        $db = self::db();
        $id = (int) $room->id;

        try {
            $rows = $db->setQuery(
                $db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
                    ->where($db->quoteName('room_id') . ' = :room')->where($db->quoteName('state') . ' <> ' . $db->quote('dropped'))
                    ->bind(':room', $id, ParameterType::INTEGER)->order($db->quoteName('id')),
                0,
                100
            )->loadObjectList();
        } catch (\Throwable) {
            return [];
        }

        return array_map([self::class, 'listed'], $rows);
    }

    /**
     * Whether the cards of a chat changed since: told with each poll, so others' cards come in too.
     */
    public static function stamp(int $roomId): string
    {
        $db = self::db();

        try {
            $row = $db->setQuery(
                $db->getQuery(true)->select('COUNT(*) AS n, MAX(' . $db->quoteName('modified') . ') AS m')->from($db->quoteName(self::TABLE))
                    ->where($db->quoteName('room_id') . ' = :room')->bind(':room', $roomId, ParameterType::INTEGER)
            )->loadObject();
        } catch (\Throwable) {
            return '';
        }

        return $row && (int) $row->n ? (int) $row->n . '|' . $row->m : '';
    }

    private static function one(object $room, int $id): ?array
    {
        $row = self::row($room, $id);

        return $row ? self::listed($row) : null;
    }

    private static function row(object $room, int $id): ?object
    {
        $db   = self::db();
        $room = (int) $room->id;

        return $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName(self::TABLE))
                ->where($db->quoteName('id') . ' = :id')->where($db->quoteName('room_id') . ' = :room')
                ->bind(':id', $id, ParameterType::INTEGER)->bind(':room', $room, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /** A card as the console draws it: who started it and when, its state, the card */
    private static function listed(object $row): array
    {
        static $names = [];

        $staff = (int) $row->staff_id;

        if (!isset($names[$staff])) {
            $names[$staff] = (string) (Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($staff)->name ?? '');
        }

        return [
            'id'        => (int) $row->id,
            'panel'     => (string) $row->panel,
            'form'      => (string) $row->form,
            'staff'     => $staff,
            'staffName' => $names[$staff],
            'state'     => (string) $row->state,
            'time'      => gmdate('c', strtotime((string) $row->created . ' UTC')),
            'modified'  => (string) $row->modified,
            'card'      => json_decode((string) $row->card, true) ?: null,
        ];
    }

    private static function text(mixed $value, int $length): string
    {
        return \is_scalar($value) ? mb_substr(trim((string) $value), 0, $length) : '';
    }

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
