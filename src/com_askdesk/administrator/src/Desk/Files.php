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
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Files sent in a chat, by either side: pictures (1 MB at most), videos and documents (10 MB at
 * most), given out for 30 days; kept until their chat is archived with them and deleted (0.6.2). Each is stored under a random name in a folder not
 * meant to be read directly, and given out only through the desk, by a random token of its own.
 * Kinds that could run in a page (SVG, HTML) are refused, whatever their name says.
 *
 * @since  0.1.0
 */
final class Files
{
    public const KEEP_DAYS = 30;

    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_IMAGE_BYTES = 1024 * 1024;

    /** What may be sent, by extension: its kind, and the types its content may have. */
    private const TYPES = [
        'jpg'  => ['image', ['image/jpeg']],
        'jpeg' => ['image', ['image/jpeg']],
        'png'  => ['image', ['image/png']],
        'gif'  => ['image', ['image/gif']],
        'webp' => ['image', ['image/webp']],
        'mp4'  => ['video', ['video/mp4', 'application/mp4']],
        'm4v'  => ['video', ['video/mp4', 'video/x-m4v']],
        'mov'  => ['video', ['video/quicktime']],
        'webm' => ['video', ['video/webm']],
        'pdf'  => ['document', ['application/pdf']],
        'doc'  => ['document', ['application/msword', 'application/vnd.ms-office', 'application/CDFV2']],
        'docx' => ['document', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']],
        'xls'  => ['document', ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2']],
        'xlsx' => ['document', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']],
        'ppt'  => ['document', ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2']],
        'pptx' => ['document', ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip']],
        'txt'  => ['document', ['text/plain']],
        'csv'  => ['document', ['text/plain', 'text/csv', 'application/csv']],
        'zip'  => ['document', ['application/zip']],
    ];

    /** The type each kind of file is given out as. */
    private const SERVED = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
    ];

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Where the files are kept: a folder of the site's media with nothing in it to read directly
     * (no listing, no running, names no one can guess).
     */
    public static function folder(): string
    {
        $folder = JPATH_ROOT . '/media/com_askdesk/files';

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        if (!is_file($folder . '/.htaccess')) {
            file_put_contents($folder . '/.htaccess', "Require all denied\nDeny from all\n");
            file_put_contents($folder . '/index.html', '<!DOCTYPE html><title></title>');
        }

        return $folder;
    }

    /**
     * A file sent in a room: checked, kept, and a message of its own.
     *
     * @param  array{name: string, tmp_name: string, size: int, error: int}  $upload  As PHP has it
     *
     * @return  array<string, mixed>  The file, as the pages show it
     *
     * @throws  \RuntimeException  With words for whoever sent it
     */
    public function receive(object $room, string $author, int $staffId, array $upload, Rooms $rooms): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
            throw new \RuntimeException(Text::_(($upload['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($upload['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 'COM_ASKDESK_FILE_TOO_LARGE' : 'COM_ASKDESK_FILE_FAILED'));
        }

        return $this->keep($room, $author, $staffId, (string) $upload['tmp_name'], (string) $upload['name'], $rooms, true);
    }

    /**
     * A file put into a room: one uploaded (moved), or a copy of one in the library.
     *
     * @return  array<string, mixed>  The file, as the pages show it
     *
     * @throws  \RuntimeException  With words for whoever sent it
     */
    public function keep(object $room, string $author, int $staffId, string $path, string $name, Rooms $rooms, bool $uploaded = false): array
    {
        $rooms->mayAddFile($room, $author);

        [$name, $extension, $kind, $size] = self::check($path, $name);

        $stored = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!($uploaded ? move_uploaded_file($path, self::folder() . '/' . $stored) : copy($path, self::folder() . '/' . $stored))) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_FAILED'));
        }

        $now = Factory::getDate();
        $row = (object) [
            'room_id'  => (int) $room->id,
            'token'    => bin2hex(random_bytes(16)),
            'name'     => $name,
            'kind'     => $kind,
            'size'     => $size,
            'saved_as' => $stored,
            'author'   => $author,
            'staff_id' => $staffId,
            'created'  => $now->toSql(),
            'expires'  => Factory::getDate('+' . self::KEEP_DAYS . ' days')->toSql(),
        ];

        $this->db->insertObject('#__askdesk_files', $row, 'id');

        try {
            $rooms->addFile($room, $author, $staffId, (int) $row->id, $name);
        } catch (\Throwable $e) {
            // Turned away after all (another message came first): nothing of it is kept
            $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__askdesk_files'))->where('id = ' . (int) $row->id))->execute();
            @unlink(self::folder() . '/' . $stored);

            throw $e;
        }

        return self::shown($row);
    }

    /**
     * Whether a file may be sent: a kind allowed, what it is and not only what it is called, and
     * not too large.
     *
     * @return  array{0: string, 1: string, 2: string, 3: int}  Its clean name, extension, kind and size
     *
     * @throws  \RuntimeException
     */
    public static function check(string $path, string $name): array
    {
        $name      = self::cleanName($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size      = is_file($path) ? (int) filesize($path) : 0;

        if (!isset(self::TYPES[$extension])) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_TYPE'));
        }

        [$kind, $types] = self::TYPES[$extension];

        if ($size < 1 || $size > ($kind === 'image' ? self::MAX_IMAGE_BYTES : self::MAX_BYTES)) {
            throw new \RuntimeException(Text::_($kind === 'image' ? 'COM_ASKDESK_FILE_IMAGE_TOO_LARGE' : 'COM_ASKDESK_FILE_TOO_LARGE'));
        }

        // What it is, not what it says it is
        $type = \function_exists('finfo_open') ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path) : '';

        if ($type !== '' && !\in_array($type, $types, true) && !($kind === 'document' && $type === 'application/octet-stream' && \in_array($extension, ['doc', 'xls', 'ppt'], true))) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_TYPE'));
        }

        if ($kind === 'image' && @getimagesize($path) === false) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_FILE_TYPE'));
        }

        return [$name, $extension, $kind, $size];
    }

    /**
     * A file by its token, while it is kept.
     */
    public function byToken(string $token): ?object
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            return null;
        }

        $db   = $this->db;
        $now  = Factory::getDate()->toSql();
        $file = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_files'))
                ->where('token = :token')->where('expires > :now')->bind(':token', $token)->bind(':now', $now)
        )->loadObject();

        return $file && is_file(self::folder() . '/' . basename((string) $file->saved_as)) ? $file : null;
    }

    /**
     * @param   int[]  $ids
     *
     * @return  array<int, object>  The files of messages, by ID (gone ones too, to say so)
     */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return [];
        }

        $db = $this->db;

        return $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_files'))->whereIn('id', $ids))->loadObjectList('id');
    }

    /**
     * The files sent in chats, newest first, for the back end (0.1.0): who sent each (the visitor,
     * or the person answering by name), in which chat, and until when it is kept.
     *
     * @return  object[]  With room_name, room_status, room_email and staff_name besides the file's columns
     */
    public function listing(string $kind = '', int $limit = 200): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('f.*, r.name AS room_name, r.status AS room_status, r.email AS room_email, r.user_id AS room_user')
            ->from($db->quoteName('#__askdesk_files', 'f'))
            ->join('LEFT', $db->quoteName('#__askdesk_rooms', 'r'), 'r.id = f.room_id')
            ->order('f.id DESC');

        if (isset(['image' => 1, 'video' => 1, 'document' => 1][$kind])) {
            $query->where('f.kind = :kind')->bind(':kind', $kind);
        }

        return $db->setQuery($query, 0, $limit)->loadObjectList();
    }

    /**
     * A file deleted before its days are over (from the back end): its message says it is gone.
     */
    public function delete(int $id): bool
    {
        $file = $this->byIds([$id])[$id] ?? null;

        if (!$file) {
            return false;
        }

        @unlink(self::folder() . '/' . basename((string) $file->saved_as));
        $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__askdesk_files'))->where('id = :id')->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER))->execute();

        return true;
    }

    /**
     * Give a file out: a picture or a video to show (videos a part at a time, as players ask), a
     * document only to download.
     */
    public static function send(object $file): never
    {
        $path      = self::folder() . '/' . basename((string) $file->saved_as);
        $extension = strtolower(pathinfo((string) $file->saved_as, PATHINFO_EXTENSION));
        $inline    = $file->kind !== 'document';
        $type      = $inline ? (self::SERVED[$extension] ?? 'application/octet-stream') : 'application/octet-stream';
        $size      = (int) filesize($path);
        $start     = 0;
        $end       = $size - 1;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');

        if ($inline && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $match) && ($match[1] !== '' || $match[2] !== '')) {
            $start = $match[1] === '' ? max(0, $size - (int) $match[2]) : (int) $match[1];
            $end   = $match[1] !== '' && $match[2] !== '' ? min((int) $match[2], $size - 1) : $size - 1;

            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }

            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }

        $name = str_replace(['"', "\r", "\n"], '', (string) $file->name);

        header('Content-Type: ' . $type);
        header('Content-Length: ' . ($end - $start + 1));
        header('Accept-Ranges: bytes');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; media-src \'self\'; style-src \'unsafe-inline\'');
        header('Cache-Control: private, max-age=3600');
        header('X-Robots-Tag: noindex');

        $handle = fopen($path, 'rb');
        fseek($handle, $start);
        $left = $end - $start + 1;

        while ($left > 0 && !feof($handle)) {
            $chunk = fread($handle, min(65536, $left));
            echo $chunk;
            $left -= \strlen($chunk);
            flush();
        }

        fclose($handle);
        exit;
    }

    /**
     * The files of rooms being deleted, and those past their days whose chat is gone, are deleted. A file
     * past its days whose chat is still here is kept (no longer given out) until the chat is archived with
     * it and deleted (0.6.2: before, it was deleted after its days, and the archive would lack it).
     *
     * @param  int[]  $rooms  Rooms being deleted
     */
    public function forget(array $rooms = []): void
    {
        $db    = $this->db;
        $now   = Factory::getDate()->toSql();
        $query = $db->getQuery(true)->select('f.id, f.saved_as')->from($db->quoteName('#__askdesk_files', 'f'))
            ->join('LEFT', $db->quoteName('#__askdesk_rooms', 'r'), 'r.id = f.room_id')
            ->where('(f.expires <= :now AND r.id IS NULL)')->bind(':now', $now);

        if ($rooms) {
            $query->orWhere('f.room_id IN (' . implode(',', array_map('intval', $rooms)) . ')');
        }

        $rows = $db->setQuery($query, 0, 2000)->loadObjectList();

        foreach ($rows as $row) {
            @unlink(self::folder() . '/' . basename((string) $row->saved_as));
        }

        if ($rows) {
            $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_files'))->whereIn('id', array_map(static fn ($row) => (int) $row->id, $rows)))->execute();
        }
    }

    /**
     * A file as the pages show it: its name, kind, size, the days it is kept, and where to get it
     * (none once it is gone).
     *
     * @return  array{name: string, kind: string, size: int, expires: string, url: string, gone: bool}
     */
    public static function shown(object $file, ?object $room = null): array
    {
        $gone = strtotime((string) $file->expires . ' UTC') <= time();

        return [
            'name'    => (string) $file->name,
            'kind'    => (string) $file->kind,
            'size'    => (int) $file->size,
            'expires' => gmdate('c', strtotime((string) $file->expires . ' UTC')),
            'url'     => $gone ? '' : \Joomla\CMS\Router\Route::_('index.php?option=com_askdesk&task=chat.file&format=raw&f=' . $file->token, false),
            'gone'    => $gone,
        ];
    }

    /** The kind of a file by its extension: image, video or document. */
    public static function kindOf(string $extension): string
    {
        return self::TYPES[strtolower($extension)][0] ?? 'document';
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', basename(str_replace('\\', '/', $name))) ?? '');

        return mb_substr($name !== '' ? $name : 'file', -150);
    }
}
