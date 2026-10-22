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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The desk's own chats as a source of the dashboard (0.6.0): its lists as templates (all not closed,
 * awaiting an answer, unhandled, closed), a chat as a record of a few fields, to be shown as a table,
 * a number, a chart. Read only: a chat is opened in the console from a row.
 *
 * @since  0.6.0
 */
final class Cases implements SourceInterface
{
    public function name(): string
    {
        return 'askdesk';
    }

    public function title(): string
    {
        return Text::_('COM_ASKDESK_CASES');
    }

    public function status(object $staff): array
    {
        return ['ready' => true, 'connected' => true, 'account' => Text::_('COM_ASKDESK_CASES_ACCOUNT'), 'connect' => '', 'manage' => ''];
    }

    public function templates(object $staff, string $query, int $page): array
    {
        $items = [];

        foreach (Rooms::LISTS as $list) {
            $name = Text::_('COM_ASKDESK_CASES_' . strtoupper($list));

            if ($query === '' || mb_stripos($name, $query) !== false) {
                $items[] = ['id' => $list, 'name' => $name, 'description' => Text::_('COM_ASKDESK_CASES_' . strtoupper($list) . '_DESC')];
            }
        }

        return ['items' => $page > 1 ? [] : $items, 'total' => \count($items)];
    }

    public function fields(object $staff, string $template): array
    {
        if (!\in_array($template, Rooms::LISTS, true)) {
            throw new \RuntimeException('No such list');
        }

        $f = static fn (string $key, string $kind, array $options = [], string $role = 'others') => ['key' => $key, 'label' => Text::_('COM_ASKDESK_CASES_F_' . strtoupper($key)), 'kind' => $kind, 'options' => $options, 'role' => $role];

        return [
            'id'     => $template,
            'name'   => Text::_('COM_ASKDESK_CASES_' . strtoupper($template)),
            'fields' => [
                $f('name', 'text', [], 'title'),
                $f('company', 'text'),
                $f('state', 'select', array_map(static fn ($s) => Text::_('COM_ASKDESK_CASES_S_' . strtoupper($s)), ['waiting', 'active', 'message', 'unhandled', 'settled'])),
                $f('preview', 'text'),
                $f('staff', 'select'),
                $f('who', 'select', [Text::_('COM_ASKDESK_CASES_W_MEMBER'), Text::_('COM_ASKDESK_CASES_W_GUEST')]),
                $f('language', 'select'),
                $f('since', 'date'),
                $f('created', 'date'),
                $f('settled', 'date'),
                $f('settledBy', 'select'),
            ],
        ];
    }

    public function rows(object $staff, string $template, array $query): array
    {
        if (!\in_array($template, Rooms::LISTS, true)) {
            throw new \RuntimeException('No such list');
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $size  = max(1, min(100, (int) $query['size']));
        $settings = Settings::load();
        $rooms    = new Rooms($db, $settings);

        // Others' chats not to be seen (as set): this person's and no one's only
        if ($settings->keptToOwn(Factory::getApplication()->getIdentity())) {
            $rooms->restrictTo((int) $staff->user_id);
        }

        $page  = $rooms->listPage((int) $staff->user_id, $template, (max(1, (int) $query['page']) - 1) * $size, $size, (string) $query['query'], (bool) $query['mine'], (string) ($query['from'] ?? ''), (string) ($query['to'] ?? ''), (int) ($query['person'] ?? 0));
        $zone  = new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC') ?: 'UTC');
        $day   = static fn (?string $iso) => $iso ? (new \DateTime($iso))->setTimezone($zone)->format('Y-m-d') : '';
        $state = static fn (array $room) => match (true) {
            $room['settled'] !== null     => 'settled',
            $room['status'] === 'message' => 'message',
            $room['status'] === 'closed'  => $room['unhandled'] ? 'unhandled' : 'ended',
            default                       => $room['status'],
        };

        return [
            'rows' => array_map(static fn (array $room) => [
                'id'     => (string) $room['id'],
                'no'     => '#' . $room['id'],
                'title'  => $room['name'],
                // Opened in the console, not as a form
                'room'   => $room['id'],
                'values' => [
                    'name'      => $room['name'] !== '' ? $room['name'] : Text::_('COM_ASKDESK_CONSOLE_VISITOR') . ' #' . $room['id'],
                    'company'   => $room['company'],
                    'state'     => Text::_('COM_ASKDESK_CASES_S_' . strtoupper($state($room))),
                    'preview'   => $room['preview'],
                    'staff'     => $room['staffName'],
                    'who'       => Text::_($room['member'] ? 'COM_ASKDESK_CASES_W_MEMBER' : 'COM_ASKDESK_CASES_W_GUEST'),
                    'language'  => $room['language'],
                    'since'     => $day($room['since']),
                    'created'   => $day($room['created']),
                    'settled'   => $day($room['settled']),
                    'settledBy' => $room['settledBy'],
                ],
            ], $page['rows']),
            'total' => $page['total'],
        ];
    }

    public function update(object $staff, string $template, string $row, string $field, mixed $value): array
    {
        throw new \RuntimeException(Text::_('COM_ASKDESK_CASES_READ_ONLY'));
    }
}
