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
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Stickers to send in a chat (0.1.0): small pictures (moving ones too), in packs, managed in the
 * back end. Each is for both sides, or for the people answering only. A sticker is a message of
 * its own; it is not translated.
 *
 * @since  0.1.0
 */
final class Stickers
{
    /** The largest sticker: small pictures, moving ones too. */
    public const MAX_BYTES = 512 * 1024;

    private const TYPES = ['png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    private const FOLDER = 'images/askdesk/stickers';

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * The stickers one side may send, by pack.
     *
     * @return  array<int, array{pack: string, stickers: array<int, array{id: int, title: string, url: string}>}>
     */
    public function packs(bool $staff): array
    {
        $packs = [];

        foreach ($this->rows(true, $staff) as $row) {
            $name = (string) $row->pack;

            $packs[$name] ??= ['pack' => $name, 'stickers' => []];
            $packs[$name]['stickers'][] = self::shown($row);
        }

        return array_values($packs);
    }

    /**
     * A sticker one side may send, or null.
     */
    public function get(int $id, bool $staff): ?object
    {
        foreach ($this->rows(true, $staff, $id) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @param   int[]  $ids
     *
     * @return  array<int, array{id: int, title: string, url: string}>  As shown, by ID (those taken away too, without a picture)
     */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return [];
        }

        $db   = $this->db;
        $rows = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_stickers'))->whereIn('id', $ids))->loadObjectList('id');

        return array_map(static fn ($row) => self::shown($row), $rows);
    }

    /**
     * All of them, for the back end.
     *
     * @return  object[]
     */
    public function all(): array
    {
        return $this->rows(false, true);
    }

    /**
     * A sticker added: a picture (PNG, GIF, WebP or JPEG, 512 KB at most), in a pack.
     *
     * @param  array{name: string, tmp_name: string, size: int, error: int}  $upload
     *
     * @throws  \RuntimeException
     */
    public function add(array $upload, string $pack, string $title, string $who): int
    {
        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));

        if (($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name']) || !isset(self::TYPES[$extension])) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_STICKERS_TYPE'));
        }

        $type = \function_exists('finfo_open') ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']) : self::TYPES[$extension];

        if ((int) $upload['size'] > self::MAX_BYTES || $type !== self::TYPES[$extension] || @getimagesize((string) $upload['tmp_name']) === false) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_STICKERS_TOO_LARGE'));
        }

        $folder = JPATH_ROOT . '/' . self::FOLDER;

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
            file_put_contents($folder . '/index.html', '<!DOCTYPE html><title></title>');
        }

        $name = bin2hex(random_bytes(8)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);

        if (!move_uploaded_file((string) $upload['tmp_name'], $folder . '/' . $name)) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_FAILED'));
        }

        $db    = $this->db;
        $pack  = mb_substr(trim(strip_tags($pack)), 0, 100) ?: Text::_('COM_ASKDESK_STICKERS_PACK_DEFAULT');
        $title = mb_substr(trim(strip_tags($title)), 0, 100) ?: pathinfo((string) $upload['name'], PATHINFO_FILENAME);
        $last  = (int) $db->setQuery($db->getQuery(true)->select('COALESCE(MAX(ordering), 0)')->from($db->quoteName('#__askdesk_stickers')))->loadResult();
        $row   = (object) [
            'pack'      => $pack,
            'title'     => mb_substr($title, 0, 100),
            'image'     => self::FOLDER . '/' . $name,
            'who'       => $who === 'staff' ? 'staff' : 'all',
            'published' => 1,
            'ordering'  => $last + 1,
            'created'   => Factory::getDate()->toSql(),
        ];

        $db->insertObject('#__askdesk_stickers', $row, 'id');

        return (int) $row->id;
    }

    /**
     * A sticker's words, pack, who it is for, and whether it is offered.
     */
    public function update(int $id, string $pack, string $title, string $who, bool $published): void
    {
        $row = (object) [
            'id'        => $id,
            'pack'      => mb_substr(trim(strip_tags($pack)), 0, 100) ?: Text::_('COM_ASKDESK_STICKERS_PACK_DEFAULT'),
            'title'     => mb_substr(trim(strip_tags($title)), 0, 100),
            'who'       => $who === 'staff' ? 'staff' : 'all',
            'published' => (int) $published,
        ];

        $this->db->updateObject('#__askdesk_stickers', $row, 'id');
    }

    /**
     * A sticker moved before or after its neighbour.
     */
    public function move(int $id, int $by): void
    {
        $rows  = $this->all();
        $index = array_search($id, array_map(static fn ($row) => (int) $row->id, $rows), true);
        $other = $index === false ? null : ($rows[$index + ($by < 0 ? -1 : 1)] ?? null);

        if ($index === false || !$other) {
            return;
        }

        $mine   = (object) ['id' => $id, 'ordering' => (int) $other->ordering];
        $theirs = (object) ['id' => (int) $other->id, 'ordering' => (int) $rows[$index]->ordering];

        $this->db->updateObject('#__askdesk_stickers', $mine, 'id');
        $this->db->updateObject('#__askdesk_stickers', $theirs, 'id');
    }

    /**
     * A sticker deleted, its picture too. Messages that sent it say it is gone.
     */
    public function delete(int $id): void
    {
        $db  = $this->db;
        $row = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_stickers'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadObject();

        if (!$row) {
            return;
        }

        if (str_starts_with((string) $row->image, self::FOLDER . '/')) {
            @unlink(JPATH_ROOT . '/' . self::FOLDER . '/' . basename((string) $row->image));
        }

        $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_stickers'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->execute();
    }

    /**
     * @return  array{id: int, title: string, url: string}
     */
    public static function shown(object $row): array
    {
        $image = (string) $row->image;

        return [
            'id'    => (int) $row->id,
            'title' => (string) $row->title,
            'url'   => $image !== '' && is_file(JPATH_ROOT . '/' . $image) ? Uri::root(true) . '/' . $image : '',
        ];
    }

    /**
     * @return  object[]
     */
    private function rows(bool $published, bool $staff, int $id = 0): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_stickers'))->order('ordering, id');

        if ($published) {
            $query->where('published = 1');
        }

        if (!$staff) {
            $query->where($db->quoteName('who') . ' = ' . $db->quote('all'));
        }

        if ($id) {
            $query->where('id = :id')->bind(':id', $id, ParameterType::INTEGER);
        }

        return $db->setQuery($query)->loadObjectList();
    }
}
