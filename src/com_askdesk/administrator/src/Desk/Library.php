<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Category;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Files the people answering keep to send again (0.1.0), in Dropfiles: a category of the desk's,
 * with a folder for each of them and one they share. The categories are for registered users'
 * eyes only (Special), so they are not shown on the site or read by AI Ask. A file sent from here
 * is copied into the chat, as a file of the chat (kept 30 days).
 *
 * @since  0.1.0
 */
final class Library
{
    /** The access level of the desk's categories: Special. */
    private const ACCESS = 3;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * Whether Dropfiles is there to keep files in.
     */
    public static function available(): bool
    {
        return ComponentHelper::isEnabled('com_dropfiles') && is_dir(JPATH_ROOT . '/media/com_dropfiles');
    }

    /**
     * The desk's category: the one chosen under Options, or one made for it.
     */
    public function root(): int
    {
        $chosen = $this->settings->libraryRoot();

        if ($chosen && $this->category($chosen)) {
            return $chosen;
        }

        return $this->ensure('askdesk-library', Text::_('COM_ASKDESK_LIBRARY_ROOT'), 1);
    }

    /**
     * The folder of one person answering (by user ID), or the one they share (0).
     */
    public function folder(int $userId): int
    {
        $root = $this->root();

        if (!$userId) {
            return $this->ensure('askdesk-shared', Text::_('COM_ASKDESK_LIBRARY_SHARED'), $root);
        }

        $names = (new Staff($this->db, $this->settings))->names([$userId]);

        return $this->ensure('askdesk-staff-' . $userId, $names[$userId] ?? ('#' . $userId), $root);
    }

    /**
     * The files of a folder, newest first, with who added each.
     *
     * @return  array<int, array{id: int, name: string, kind: string, size: int, added: string, by: string}>
     */
    public function files(int $folder): array
    {
        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('f.id, f.title, f.ext, f.size, f.created_time, f.author')
                ->from($db->quoteName('#__dropfiles_files', 'f'))
                ->where('f.catid = :folder')->where('f.state = 1')->where($db->quoteName('f.remoteurl') . ' = ' . $db->quote(''))
                ->bind(':folder', $folder, ParameterType::INTEGER)
                ->order('f.id DESC'),
            0,
            200
        )->loadObjectList();

        $names = (new Staff($this->db, $this->settings))->names(array_map(static fn ($row) => (int) $row->author, $rows));

        return array_map(static fn ($row) => [
            'id'    => (int) $row->id,
            'name'  => $row->title . '.' . $row->ext,
            'kind'  => Files::kindOf((string) $row->ext),
            'size'  => (int) $row->size,
            'added' => gmdate('c', strtotime($row->created_time . ' UTC')),
            'by'    => $names[(int) $row->author] ?? '',
        ], $rows);
    }

    /**
     * A file added to a folder, by the same rules as files sent in a chat.
     *
     * @param  array{name: string, tmp_name: string, size: int, error: int}  $upload
     *
     * @throws  \RuntimeException
     */
    public function add(int $folder, array $upload, int $userId): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_FAILED'));
        }

        return $this->put($folder, (string) $upload['tmp_name'], (string) $upload['name'], $userId, true);
    }

    /**
     * A file sent in a chat kept in the library (a copy: the chat's goes after its days).
     *
     * @throws  \RuntimeException
     */
    public function keep(int $fileId, int $folder, int $userId): array
    {
        $file = (new Files($this->db))->byIds([$fileId])[$fileId] ?? null;
        $path = $file ? Files::folder() . '/' . basename((string) $file->saved_as) : '';

        if (!$file || !is_file($path) || strtotime((string) $file->expires . ' UTC') <= time()) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILES_GONE'));
        }

        return $this->put($folder, $path, (string) $file->name, $userId, false);
    }

    /**
     * @throws  \RuntimeException
     */
    private function put(int $folder, string $path, string $name, int $userId, bool $uploaded): array
    {
        [$name, $extension, , $size] = Files::check($path, $name);

        $dir = JPATH_ROOT . '/media/com_dropfiles/' . $folder;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_FAILED'));
        }

        $stored = uniqid() . '.' . $extension;

        if (!($uploaded ? move_uploaded_file($path, $dir . '/' . $stored) : copy($path, $dir . '/' . $stored))) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_FAILED'));
        }

        $db       = $this->db;
        $now      = Factory::getDate()->toSql();
        $ordering = (int) $db->setQuery(
            $db->getQuery(true)->select('COALESCE(MAX(ordering), -1) + 1')->from($db->quoteName('#__dropfiles_files'))->where('catid = :folder')->bind(':folder', $folder, ParameterType::INTEGER)
        )->loadResult();
        $row = (object) [
            'catid'         => $folder,
            'file'          => $stored,
            'state'         => 1,
            'ordering'      => $ordering,
            'title'         => mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 255),
            'description'   => '',
            'ext'           => $extension,
            'size'          => $size,
            'created_time'  => $now,
            'modified_time' => $now,
            'publish'       => $now,
            'author'        => (string) $userId,
            'file_tags'     => '',
        ];

        $db->insertObject('#__dropfiles_files', $row, 'id');
        $this->count($folder);

        return ['id' => (int) $row->id, 'name' => $name];
    }

    /**
     * A file of the library, when it is in one of the folders a person answering may use: their
     * own and the shared one.
     */
    public function file(int $id, int $userId): ?object
    {
        $db   = $this->db;
        $file = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__dropfiles_files'))->where('id = :id')->where('state = 1')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject();

        if (!$file || !\in_array((int) $file->catid, [$this->folder($userId), $this->folder(0)], true)) {
            return null;
        }

        $file->path = JPATH_ROOT . '/media/com_dropfiles/' . (int) $file->catid . '/' . basename((string) $file->file);

        return is_file($file->path) ? $file : null;
    }

    /**
     * A file of the library sent in a chat: copied, as a file of the chat.
     *
     * @throws  \RuntimeException
     */
    public function send(int $id, object $room, int $staffId, Rooms $rooms): array
    {
        $file = $this->file($id, $staffId);

        if (!$file) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_LIBRARY_GONE'));
        }

        return (new Files($this->db))->keep($room, 'staff', $staffId, $file->path, $file->title . '.' . $file->ext, $rooms);
    }

    /**
     * The folders made so far: the shared one first, then each person's, with how many files.
     *
     * @return  array<int, array{id: int, title: string, files: int, user: int}>
     */
    public function folders(): array
    {
        $db   = $this->db;
        $root = $this->root();
        $rows = $db->setQuery(
            $db->getQuery(true)->select('c.id, c.title, c.alias, COUNT(f.id) AS files')
                ->from($db->quoteName('#__categories', 'c'))
                ->join('LEFT', $db->quoteName('#__dropfiles_files', 'f'), 'f.catid = c.id AND f.state = 1')
                ->where('c.parent_id = :root')->where('c.extension = ' . $db->quote('com_dropfiles'))
                ->bind(':root', $root, ParameterType::INTEGER)
                ->group('c.id, c.title, c.alias')->order('c.lft')
        )->loadObjectList();

        $folders = array_map(static fn ($row) => [
            'id'    => (int) $row->id,
            'title' => (string) $row->title,
            'files' => (int) $row->files,
            'user'  => preg_match('/^askdesk-staff-(\d+)$/', (string) $row->alias, $match) ? (int) $match[1] : 0,
        ], $rows);

        usort($folders, static fn ($a, $b) => [$a['user'] > 0, $a['title']] <=> [$b['user'] > 0, $b['title']]);

        return $folders;
    }

    private function category(int $id): ?object
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)->select('id, alias, parent_id')->from($db->quoteName('#__categories'))
                ->where('id = :id')->where('extension = ' . $db->quote('com_dropfiles'))->where('published = 1')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * A category of Dropfiles by its alias under a parent, made when it is not there.
     */
    private function ensure(string $alias, string $title, int $parent): int
    {
        static $known = [];

        $key = $parent . '/' . $alias;

        if (isset($known[$key])) {
            return $known[$key];
        }

        $db = $this->db;
        $id = (int) $db->setQuery(
            $db->getQuery(true)->select('id')->from($db->quoteName('#__categories'))
                ->where('alias = :alias')->where('parent_id = :parent')->where('extension = ' . $db->quote('com_dropfiles'))->where('published >= 0')
                ->bind(':alias', $alias)->bind(':parent', $parent, ParameterType::INTEGER)
        )->loadResult();

        if (!$id) {
            $table = new Category($db);
            $table->setLocation($parent, 'last-child');
            $table->bind([
                'title'           => $title,
                'alias'           => $alias,
                'extension'       => 'com_dropfiles',
                'published'       => 1,
                'access'          => self::ACCESS,
                'language'        => '*',
                'params'          => '{}',
                'metadata'        => '{}',
                'description'     => '',
                'created_user_id' => (int) Factory::getApplication()->getIdentity()?->id,
            ]);

            if (!$table->check() || !$table->store()) {
                throw new \RuntimeException(Text::_('COM_ASKDESK_LIBRARY_FAILED'));
            }

            $table->rebuildPath($table->id);
            $id = (int) $table->id;

            // Dropfiles keeps its own row for each category
            $row = (object) [
                'id'     => $id,
                'type'   => 'default',
                'path'   => '',
                'params' => json_encode(['usergroup' => ['-1'], 'ordering' => 'created_time', 'orderingdir' => 'desc', 'showsize' => '1', 'showtitle' => '1', 'showdownload' => '1', 'access' => -1, 'refToFile' => []]),
                'theme'  => '',
                'count'  => 0,
            ];
            $db->insertObject('#__dropfiles', $row);

            if (!is_dir(JPATH_ROOT . '/media/com_dropfiles/' . $id)) {
                mkdir(JPATH_ROOT . '/media/com_dropfiles/' . $id, 0755, true);
                file_put_contents(JPATH_ROOT . '/media/com_dropfiles/' . $id . '/index.html', '<!DOCTYPE html><title></title>');
            }
        }

        return $known[$key] = $id;
    }

    private function count(int $folder): void
    {
        $db = $this->db;
        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName('#__dropfiles'))
                ->set($db->quoteName('count') . ' = (SELECT COUNT(*) FROM ' . $db->quoteName('#__dropfiles_files') . ' WHERE catid = :folder AND state = 1)')
                ->where('id = :id')->bind(':folder', $folder, ParameterType::INTEGER)->bind(':id', $folder, ParameterType::INTEGER)
        )->execute();
    }
}
