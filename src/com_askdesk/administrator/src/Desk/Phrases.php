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
 * Phrases the people answering often write (0.1.3): shared ones, kept in the back end, and each
 * person's own, kept from the console. Pinned ones are offered above the box to put in with a click;
 * the rest are in a list beside them. A phrase goes into the box, to be changed before it is sent.
 *
 * @since  0.1.3
 */
final class Phrases
{
    /** The most a person keeps of their own. */
    public const MINE = 100;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * The phrases one person may use: the shared ones offered, then their own.
     *
     * @return  array<int, array{id: int, title: string, text: string, pinned: bool, mine: bool}>
     */
    public function forStaff(int $userId): array
    {
        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_phrases'))
                ->where('(user_id = 0 AND published = 1) OR user_id = :user')->bind(':user', $userId, ParameterType::INTEGER)
                ->order('CASE WHEN user_id = 0 THEN 0 ELSE 1 END, ordering, id')
        )->loadObjectList();

        return array_map(static fn ($row) => [
            'id'     => (int) $row->id,
            'title'  => (string) $row->title,
            'text'   => (string) $row->text,
            'pinned' => (bool) $row->pinned,
            'mine'   => (int) $row->user_id > 0,
        ], $rows);
    }

    /**
     * The shared phrases, all, for the back end.
     *
     * @return  object[]
     */
    public function shared(): array
    {
        $db = $this->db;

        return $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_phrases'))->where('user_id = 0')->order('ordering, id'))->loadObjectList();
    }

    /**
     * A phrase added: shared (user 0), or one's own. Its title is the first words when none is given.
     *
     * @throws  \RuntimeException  When it has no words, or one has too many of one's own
     */
    public function add(int $userId, string $title, string $text, bool $pinned): int
    {
        [$title, $text] = self::clean($title, $text);

        if ($userId > 0 && $this->count($userId) >= self::MINE) {
            throw new \RuntimeException('too many');
        }

        $db   = $this->db;
        $last = (int) $db->setQuery(
            $db->getQuery(true)->select('COALESCE(MAX(ordering), 0)')->from($db->quoteName('#__askdesk_phrases'))->where('user_id = :user')->bind(':user', $userId, ParameterType::INTEGER)
        )->loadResult();
        $row  = (object) [
            'user_id'   => $userId,
            'title'     => $title,
            'text'      => $text,
            'pinned'    => (int) $pinned,
            'published' => 1,
            'ordering'  => $last + 1,
            'created'   => Factory::getDate()->toSql(),
        ];

        $db->insertObject('#__askdesk_phrases', $row, 'id');

        return (int) $row->id;
    }

    /**
     * A phrase changed: only by whom it belongs to (0 for the shared ones, from the back end).
     */
    public function update(int $userId, int $id, string $title, string $text, bool $pinned, bool $published = true): void
    {
        [$title, $text] = self::clean($title, $text);

        $db = $this->db;
        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName('#__askdesk_phrases'))
                ->set(['title = :title', 'text = :text', 'pinned = :pinned', 'published = :published'])
                ->where('id = :id')->where('user_id = :user')
                ->bind(':title', $title)->bind(':text', $text)
                ->bind(':pinned', $pinned, ParameterType::INTEGER)->bind(':published', $published, ParameterType::INTEGER)
                ->bind(':id', $id, ParameterType::INTEGER)->bind(':user', $userId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * A phrase deleted: only by whom it belongs to.
     */
    public function delete(int $userId, int $id): void
    {
        $db = $this->db;
        $db->setQuery(
            $db->getQuery(true)->delete($db->quoteName('#__askdesk_phrases'))->where('id = :id')->where('user_id = :user')
                ->bind(':id', $id, ParameterType::INTEGER)->bind(':user', $userId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * A phrase moved before or after its neighbour, among those of the same owner.
     */
    public function move(int $userId, int $id, int $by): void
    {
        $db    = $this->db;
        $rows  = $db->setQuery(
            $db->getQuery(true)->select('id, ordering')->from($db->quoteName('#__askdesk_phrases'))->where('user_id = :user')->bind(':user', $userId, ParameterType::INTEGER)->order('ordering, id')
        )->loadObjectList();
        $index = array_search($id, array_map(static fn ($row) => (int) $row->id, $rows), true);
        $other = $index === false ? null : ($rows[$index + ($by < 0 ? -1 : 1)] ?? null);

        if ($index === false || !$other) {
            return;
        }

        $mine   = (object) ['id' => $id, 'ordering' => (int) $other->ordering];
        $theirs = (object) ['id' => (int) $other->id, 'ordering' => (int) $rows[$index]->ordering];

        $db->updateObject('#__askdesk_phrases', $mine, 'id');
        $db->updateObject('#__askdesk_phrases', $theirs, 'id');
    }

    private function count(int $userId): int
    {
        $db = $this->db;

        return (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__askdesk_phrases'))->where('user_id = :user')->bind(':user', $userId, ParameterType::INTEGER))->loadResult();
    }

    /**
     * @return  array{0: string, 1: string}  The title and the words, plain
     *
     * @throws  \RuntimeException  When there are no words
     */
    private static function clean(string $title, string $text): array
    {
        $text  = mb_substr(trim(str_replace("\r\n", "\n", strip_tags($text))), 0, 2000);
        $title = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?? ''), 0, 60);

        if ($text === '') {
            throw new \RuntimeException('empty');
        }

        if ($title === '') {
            $title = mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 16);
        }

        return [$title, $text];
    }
}
