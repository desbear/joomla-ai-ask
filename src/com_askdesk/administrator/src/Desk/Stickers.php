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
 * its own; it is not translated. The packs (0.6.8) are blocks of their own, in an order, each shown by one of its
 * stickers; the stickers keep naming their pack.
 *
 * @since  0.1.0
 */
final class Stickers
{
    /** The largest sticker: small pictures, moving ones too. */
    public const MAX_BYTES = 512 * 1024;

    private const TYPES = ['png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    /** The largest picture given to be made a WebP (0.6.8): what is kept must still be MAX_BYTES at most. */
    public const MAX_TO_WEBP = 4 * 1024 * 1024;

    private const FOLDER = 'images/askdesk/stickers';

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * The stickers one side may send, by pack, the packs in their order (0.6.8), each with the picture shown for it.
     *
     * @return  array<int, array{pack: string, cover: string, stickers: array<int, array{id: int, title: string, url: string}>}>
     */
    public function packs(bool $staff): array
    {
        $order  = [];
        $covers = [];

        foreach ($this->packList() as $pack) {
            $order[(string) $pack->name]  = \count($order);
            $covers[(string) $pack->name] = (int) $pack->cover;
        }

        $packs = [];

        foreach ($this->rows(true, $staff) as $row) {
            $name = (string) $row->pack;

            $packs[$name] ??= ['pack' => $name, 'cover' => '', 'stickers' => []];
            $packs[$name]['stickers'][] = $shown = self::shown($row);

            if ($packs[$name]['cover'] === '' || (int) $row->id === ($covers[$name] ?? 0)) {
                $packs[$name]['cover'] = $shown['url'];
            }
        }

        uksort($packs, static fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return array_values($packs);
    }

    /**
     * The packs, in their order (0.6.8), each with how many stickers it has and the one shown for it (the one
     * picked, else its first). A pack the stickers name that is not yet a block of its own is added at the end.
     *
     * @return  object[]  {id, name, cover, ordering, count, offered, picture, who}
     */
    public function packList(): array
    {
        $db     = $this->db;
        $rows   = $this->rows(false, true);
        $packs  = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_sticker_packs'))->order('ordering, id'))->loadObjectList('name');
        $last   = $packs ? max(array_map(static fn ($pack) => (int) $pack->ordering, $packs)) : 0;
        $byName = [];

        foreach ($rows as $row) {
            $byName[(string) $row->pack][] = $row;
        }

        foreach (array_keys($byName) as $name) {
            if (!isset($packs[$name])) {
                $pack = (object) ['name' => (string) $name, 'cover' => 0, 'ordering' => ++$last];
                $db->insertObject('#__askdesk_sticker_packs', $pack, 'id');
                $packs[$name] = $pack;
            }
        }

        $list = [];

        foreach ($packs as $name => $pack) {
            $stickers = $byName[$name] ?? [];
            $cover    = null;

            // A pack left with no sticker (all moved to another) goes
            if (!$stickers) {
                $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_sticker_packs'))->where('id = ' . (int) $pack->id))->execute();

                continue;
            }

            foreach ($stickers as $row) {
                if ((int) $row->id === (int) $pack->cover) {
                    $cover = $row;
                }
            }

            $cover ??= $stickers[0] ?? null;
            $whos    = array_unique(array_map(static fn ($row) => (string) $row->who, $stickers));

            $list[] = (object) [
                'id'       => (int) $pack->id,
                'name'     => (string) $name,
                'cover'    => $cover ? (int) $cover->id : 0,
                'ordering' => (int) $pack->ordering,
                'count'    => \count($stickers),
                'offered'  => \count(array_filter($stickers, static fn ($row) => (int) $row->published === 1)),
                'picture'  => $cover ? self::shown($cover)['url'] : '',
                'who'      => \count($whos) === 1 ? reset($whos) : ($whos ? 'mixed' : 'all'),
            ];
        }

        usort($list, static fn ($a, $b) => [$a->ordering, $a->id] <=> [$b->ordering, $b->id]);

        return $list;
    }

    /**
     * One pack, or null.
     */
    public function pack(int $id): ?object
    {
        foreach ($this->packList() as $pack) {
            if ($pack->id === $id) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * The packs put in the order given (0.6.8: dragged on the page); those not given keep after them.
     *
     * @param  int[]  $ids
     */
    public function orderPacks(array $ids): void
    {
        $ids  = array_values(array_unique(array_map('intval', $ids)));
        $rest = array_filter(array_map(static fn ($pack) => $pack->id, $this->packList()), static fn ($id) => !\in_array($id, $ids, true));

        foreach (array_values(array_merge($ids, $rest)) as $at => $id) {
            $row = (object) ['id' => $id, 'ordering' => $at + 1];

            $this->db->updateObject('#__askdesk_sticker_packs', $row, 'id');
        }
    }

    /**
     * Stickers put in the order given (0.6.8), taking the places they held among themselves, so others keep theirs.
     *
     * @param  int[]  $ids
     */
    public function orderStickers(array $ids): void
    {
        $ids    = array_values(array_unique(array_map('intval', $ids)));
        $places = [];
        $known  = [];

        foreach ($this->all() as $row) {
            if (\in_array((int) $row->id, $ids, true)) {
                $places[] = (int) $row->ordering;
                $known[]  = (int) $row->id;
            }
        }

        sort($places);

        // Places held twice (stickers added at once once could be) made apart
        for ($at = 1; $at < \count($places); $at++) {
            $places[$at] = max($places[$at], $places[$at - 1] + 1);
        }

        foreach (array_values(array_intersect($ids, $known)) as $at => $id) {
            $row = (object) ['id' => $id, 'ordering' => $places[$at]];

            $this->db->updateObject('#__askdesk_stickers', $row, 'id');
        }
    }

    /**
     * Stickers offered or not, by ID (0.6.8).
     *
     * @param  int[]  $ids
     */
    public function offer(array $ids, bool $published): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids) {
            $db = $this->db;
            $db->setQuery($db->getQuery(true)->update($db->quoteName('#__askdesk_stickers'))->set('published = ' . (int) $published)->whereIn('id', $ids))->execute();
        }
    }

    /**
     * A sticker already added made a WebP (0.6.8), as one added would be: true when it was. A moving GIF, one
     * already WebP, or one whose WebP is not smaller is kept.
     */
    public function toWebp(int $id): bool
    {
        $db    = $this->db;
        $row   = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_stickers'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadObject();
        $image = $row ? (string) $row->image : '';
        $file  = JPATH_ROOT . '/' . $image;
        $ext   = strtolower(pathinfo($image, PATHINFO_EXTENSION));

        if (!$row || !str_starts_with($image, self::FOLDER . '/') || !is_file($file) || !\in_array($ext, ['png', 'gif', 'jpg', 'jpeg'], true)) {
            return false;
        }

        $made = self::webp($file, $ext);

        if ($made === null || \strlen($made) >= (int) filesize($file)) {
            return false;
        }

        $name = bin2hex(random_bytes(8)) . '.webp';

        if (file_put_contents(JPATH_ROOT . '/' . self::FOLDER . '/' . $name, $made) === false) {
            return false;
        }

        $new = (object) ['id' => $id, 'image' => self::FOLDER . '/' . $name];

        $db->updateObject('#__askdesk_stickers', $new, 'id');
        @unlink($file);

        return true;
    }

    /**
     * A pack changed as a whole (0.6.8): its name (given one another pack has, it joins that one), and, when given,
     * whom all its stickers are for and whether they are all offered; the sticker shown for it.
     */
    public function updatePack(int $id, string $name, string $who, ?bool $published, int $cover): void
    {
        $pack = $this->pack($id);

        if (!$pack) {
            return;
        }

        $db   = $this->db;
        $name = mb_substr(trim(strip_tags($name)), 0, 100) ?: $pack->name;
        $set  = [];

        if ($name !== $pack->name) {
            $set[] = $db->quoteName('pack') . ' = ' . $db->quote($name);
        }

        if ($who === 'all' || $who === 'staff') {
            $set[] = $db->quoteName('who') . ' = ' . $db->quote($who);
        }

        if ($published !== null) {
            $set[] = 'published = ' . (int) $published;
        }

        if ($set) {
            $db->setQuery($db->getQuery(true)->update($db->quoteName('#__askdesk_stickers'))->set($set)->where($db->quoteName('pack') . ' = ' . $db->quote($pack->name)))->execute();
        }

        $other = $name !== $pack->name ? $db->setQuery($db->getQuery(true)->select('id')->from($db->quoteName('#__askdesk_sticker_packs'))->where($db->quoteName('name') . ' = ' . $db->quote($name)))->loadResult() : null;

        if ($other) {
            // Joined another pack: this one's block goes, the other's order and picture kept
            $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_sticker_packs'))->where('id = ' . $id))->execute();

            return;
        }

        $row = (object) ['id' => $id, 'name' => $name, 'cover' => max(0, $cover)];

        $db->updateObject('#__askdesk_sticker_packs', $row, 'id');
    }

    /**
     * A pack deleted, its stickers and their pictures too (0.6.8).
     */
    public function deletePack(int $id): void
    {
        $pack = $this->pack($id);

        if (!$pack) {
            return;
        }

        foreach ($this->all() as $row) {
            if ((string) $row->pack === $pack->name) {
                $this->delete((int) $row->id);
            }
        }

        $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__askdesk_sticker_packs'))->where('id = ' . $id))->execute();
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
     * A sticker added: a picture (PNG, GIF, WebP or JPEG, 512 KB at most), in a pack. Asked to (0.6.8), a PNG, a
     * JPEG or a still GIF is kept as a WebP (given at most MAX_TO_WEBP, what is kept 512 KB at most); a moving GIF is
     * kept as it is, as making it a WebP here would stop it.
     *
     * @param  array{name: string, tmp_name: string, size: int, error: int}  $upload
     *
     * @throws  \RuntimeException
     */
    public function add(array $upload, string $pack, string $title, string $who, bool $webp = false): int
    {
        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));

        if (($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name']) || !isset(self::TYPES[$extension])) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_STICKERS_TYPE'));
        }

        $type = \function_exists('finfo_open') ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']) : self::TYPES[$extension];

        $made = $webp && $extension !== 'webp' && (int) $upload['size'] <= self::MAX_TO_WEBP && $type === self::TYPES[$extension] ? self::webp((string) $upload['tmp_name'], $extension) : null;

        // Not smaller than what was given (a small picture of flat colours, say): what was given is kept
        if ($made !== null && \strlen($made) >= (int) $upload['size'] && (int) $upload['size'] <= self::MAX_BYTES) {
            $made = null;
        }

        if ($made === null && ((int) $upload['size'] > self::MAX_BYTES || $type !== self::TYPES[$extension] || @getimagesize((string) $upload['tmp_name']) === false)) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_STICKERS_TOO_LARGE'));
        }

        $folder = JPATH_ROOT . '/' . self::FOLDER;

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
            file_put_contents($folder . '/index.html', '<!DOCTYPE html><title></title>');
        }

        $name = bin2hex(random_bytes(8)) . '.' . ($made !== null ? 'webp' : ($extension === 'jpeg' ? 'jpg' : $extension));

        if ($made !== null ? file_put_contents($folder . '/' . $name, $made) === false : !move_uploaded_file((string) $upload['tmp_name'], $folder . '/' . $name)) {
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
     * A picture as a WebP (0.6.8), or null: this PHP not making them, a moving GIF, or what it makes over MAX_BYTES.
     */
    private static function webp(string $file, string $extension): ?string
    {
        if (!\function_exists('imagewebp') || ($extension === 'gif' && self::moving($file))) {
            return null;
        }

        $image = match ($extension) {
            'png'         => @imagecreatefrompng($file),
            'gif'         => @imagecreatefromgif($file),
            'jpg', 'jpeg' => @imagecreatefromjpeg($file),
            default       => false,
        };

        if (!$image) {
            return null;
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $made = imagewebp($image, null, 85);
        $data = (string) ob_get_clean();

        imagedestroy($image);

        return $made && $data !== '' && \strlen($data) <= self::MAX_BYTES ? $data : null;
    }

    /**
     * Whether a GIF has more than one frame.
     */
    private static function moving(string $file): bool
    {
        $data = (string) @file_get_contents($file);

        return preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $data) > 1;
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
