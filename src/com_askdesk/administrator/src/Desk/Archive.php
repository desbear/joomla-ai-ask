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
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Closed chats archived, then forgotten (0.6.2). A chat whose case was closed (結案) more than the days set
 * (90 by default) is written out whole, as it is kept here (the chat, its messages, its work cards, its
 * rating, what was done with it, and its files themselves), into a folder outside the website, then marked
 * as archived, and only then deleted from here. One that cannot be written out stays, and is tried again
 * next time.
 *
 * One way only: the desk writes the folder and never reads it, nor anything that came from it. What
 * becomes of it (packed, encrypted and sent away by the site's backups) is outside the desk; bringing a
 * chat back is done by hand, never by the desk.
 *
 * In the folder: {year-month}/room-{id}/chat.json and {year-month}/room-{id}/files/…; and archive.log, a
 * line for each chat archived.
 *
 * @since  0.6.2
 */
final class Archive
{
    /** What an archived chat says it is, for whoever reads it by hand later */
    public const FORMAT = 'askdesk-archive/1';

    /** Where the last run is noted (in the desk's own table of values) */
    private const NOTE = 'archive_last';

    /** Chats at most in one run; the rest wait for the next */
    private const BATCH = 200;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * The folder, if it may be written: outside the website, created if missing.
     *
     * @return  string  '' with why in $why when it may not
     */
    public function folder(?string &$why = null): string
    {
        $folder = rtrim($this->settings->archiveFolder(), '/\\');
        $why    = '';

        if ($folder === '' || !preg_match('#^(/|[A-Za-z]:[\\\\/])#', $folder)) {
            $why = 'The archive folder must be a full path.';

            return '';
        }

        $root   = rtrim(realpath(JPATH_ROOT) ?: JPATH_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $inside = static function (string $path) use ($root): bool {
            // The nearest folder that is there, resolved (links followed), then what is still to be made
            $rest = '';

            while (!is_dir($path) && \dirname($path) !== $path) {
                $rest = '/' . basename($path) . $rest;
                $path = \dirname($path);
            }

            $full = rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR) . $rest . DIRECTORY_SEPARATOR;

            return str_starts_with($full, $root);
        };

        // Never where the website is served from, whatever is there could be fetched by anyone; checked before
        // anything is made there
        if ($inside($folder)) {
            $why = 'The archive folder is inside the website; it must be outside it: ' . $folder;

            return '';
        }

        if (!is_dir($folder) && !@mkdir($folder, 0750, true) && !is_dir($folder)) {
            $why = 'The archive folder cannot be made: ' . $folder;

            return '';
        }

        $real = realpath($folder) ?: $folder;

        if ($inside($real)) {
            $why = 'The archive folder is inside the website; it must be outside it: ' . $real;

            return '';
        }

        if (!is_writable($real)) {
            $why = 'The archive folder cannot be written: ' . $real;

            return '';
        }

        return $real;
    }

    /**
     * Archives what is due and deletes what was archived.
     *
     * @return  array{exported: int, deleted: int, error: string}
     */
    public function run(int $seconds = 40): array
    {
        $until  = microtime(true) + max(5, $seconds);
        $result = ['exported' => 0, 'deleted' => 0, 'error' => ''];
        $folder = $this->folder($why);

        // Written out first; marked archived only once written; deleted only once marked
        if ($folder !== '') {
            foreach ($this->due() as $room) {
                if (microtime(true) > $until) {
                    break;
                }

                try {
                    $this->export($room, $folder);
                    $this->mark((int) $room->id);
                    $result['exported']++;
                } catch (\Throwable $e) {
                    // Not written out (or not marked): kept, and tried again next time
                    $result['error'] = 'Chat ' . (int) $room->id . ': ' . $e->getMessage();

                    break;
                }
            }
        } else {
            $result['error'] = $why;
        }

        // Those marked are deleted, whether marked now or before (a run that stopped between the two)
        foreach ($this->archived() as $id) {
            if (microtime(true) > $until) {
                break;
            }

            $this->forget($id);
            $result['deleted']++;
        }

        $this->note($result);

        return $result;
    }

    /**
     * When it last ran, how many it archived and deleted, and what went wrong if anything did.
     *
     * @return  array{time: string, exported: int, deleted: int, error: string}|null
     */
    public function last(): ?array
    {
        $db = $this->db;

        try {
            $value = $db->setQuery(
                $db->getQuery(true)->select($db->quoteName('value'))->from($db->quoteName('#__askdesk_secrets'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote(self::NOTE))
            )->loadResult();
        } catch (\Throwable) {
            return null;
        }

        $last = \is_string($value) ? json_decode($value, true) : null;

        return \is_array($last) && isset($last['time']) ? [
            'time'     => (string) $last['time'],
            'exported' => (int) ($last['exported'] ?? 0),
            'deleted'  => (int) ($last['deleted'] ?? 0),
            'error'    => (string) ($last['error'] ?? ''),
        ] : null;
    }

    /** Chats closed (結案) before the days set, not yet archived */
    private function due(): array
    {
        $db     = $this->db;
        $before = Factory::getDate('-' . $this->settings->archiveDays() . ' days')->toSql();

        return $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))
                ->where($db->quoteName('settled') . ' IS NOT NULL')->where($db->quoteName('settled') . ' < :before')
                ->where($db->quoteName('archived_at') . ' IS NULL')
                ->bind(':before', $before)->order($db->quoteName('id')),
            0,
            self::BATCH
        )->loadObjectList();
    }

    /** @return  int[]  Chats archived and still here */
    private function archived(): array
    {
        $db = $this->db;

        return array_map('intval', $db->setQuery(
            $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__askdesk_rooms'))
                ->where($db->quoteName('archived_at') . ' IS NOT NULL')->order($db->quoteName('id')),
            0,
            self::BATCH
        )->loadColumn());
    }

    /**
     * A chat written out whole: put together in a folder of its own beside the place it goes, then moved
     * there in one step, so a chat in the archive is always complete. One already there (written out by a
     * run that stopped before marking it) is left as it is.
     */
    private function export(object $room, string $folder): void
    {
        $db    = $this->db;
        $id    = (int) $room->id;
        $month = gmdate('Y-m', strtotime((string) $room->settled . ' UTC') ?: time());
        $place = $folder . '/' . $month . '/room-' . $id;

        if (is_dir($place) && is_file($place . '/chat.json')) {
            return;
        }

        if (!is_dir($folder . '/' . $month) && !@mkdir($folder . '/' . $month, 0750, true) && !is_dir($folder . '/' . $month)) {
            throw new \RuntimeException('cannot make ' . $folder . '/' . $month);
        }

        $rows = static fn (string $table) => $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName($table))
                ->where($db->quoteName('room_id') . ' = :room')->bind(':room', $id, ParameterType::INTEGER)->order($db->quoteName('id'))
        )->loadAssocList();

        $files = $rows('#__askdesk_files');
        $work  = $folder . '/' . $month . '/.room-' . $id . '-' . bin2hex(random_bytes(4));

        if (!@mkdir($work . '/files', 0750, true)) {
            throw new \RuntimeException('cannot write in ' . $folder . '/' . $month);
        }

        try {
            // Its files themselves, under their own names (made safe), each once
            $taken = [];

            foreach ($files as &$file) {
                $from = Files::folder() . '/' . basename((string) $file['saved_as']);
                $name = self::safeName((string) $file['name'], (int) $file['id'], $taken);

                $file['archived_as'] = is_file($from) ? 'files/' . $name : '';

                if ($file['archived_as'] !== '' && !@copy($from, $work . '/files/' . $name)) {
                    throw new \RuntimeException('cannot copy file ' . (int) $file['id']);
                }
            }

            unset($file);

            $chat = [
                'format'   => self::FORMAT,
                'exported' => gmdate('c'),
                'site'     => Uri::root(),
                'room'     => (array) $room,
                'messages' => $rows('#__askdesk_messages'),
                'work'     => $this->optional('#__askdesk_work', $id),
                'files'    => $files,
                'rating'   => $this->optional('#__askdesk_ratings', $id),
                'handled'  => $this->optional('#__askdesk_handled', $id),
            ];
            $json = json_encode($chat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

            if ($json === false || @file_put_contents($work . '/chat.json', $json, LOCK_EX) !== \strlen($json)) {
                throw new \RuntimeException('cannot write chat.json');
            }

            if (!@rename($work, $place)) {
                throw new \RuntimeException('cannot move it into ' . $place);
            }
        } catch (\Throwable $e) {
            self::clear($work);

            throw $e;
        }

        // A line for each chat archived (written, never read here)
        @file_put_contents($folder . '/archive.log', json_encode([
            'room'     => $id,
            'settled'  => (string) $room->settled,
            'archived' => gmdate('c'),
            'path'     => $month . '/room-' . $id,
            'messages' => \count($chat['messages']),
            'files'    => \count($files),
            'sha256'   => hash('sha256', $json),
        ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** Rows of a table of the chat, an empty list when the table is not there (an older install) */
    private function optional(string $table, int $id): array
    {
        $db = $this->db;

        try {
            return $db->setQuery(
                $db->getQuery(true)->select('*')->from($db->quoteName($table))
                    ->where($db->quoteName('room_id') . ' = :room')->bind(':room', $id, ParameterType::INTEGER)
            )->loadAssocList();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Marked archived: once only, the time it was */
    private function mark(int $id): void
    {
        $db  = $this->db;
        $now = Factory::getDate()->toSql();

        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName('#__askdesk_rooms'))->set($db->quoteName('archived_at') . ' = :now')
                ->where($db->quoteName('id') . ' = :id')->where($db->quoteName('archived_at') . ' IS NULL')
                ->bind(':now', $now)->bind(':id', $id, ParameterType::INTEGER)
        )->execute();
    }

    /** An archived chat deleted from here: its messages, work cards, files (and the files themselves), and it */
    private function forget(int $id): void
    {
        $db = $this->db;

        (new Files($db))->forget([$id]);

        foreach (['#__askdesk_messages', '#__askdesk_work'] as $table) {
            try {
                $db->setQuery($db->getQuery(true)->delete($db->quoteName($table))->where($db->quoteName('room_id') . ' = :room')->bind(':room', $id, ParameterType::INTEGER))->execute();
            } catch (\Throwable) {
                // (A table an older install does not have)
            }
        }

        $db->setQuery(
            $db->getQuery(true)->delete($db->quoteName('#__askdesk_rooms'))
                ->where($db->quoteName('id') . ' = :id')->where($db->quoteName('archived_at') . ' IS NOT NULL')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->execute();
    }

    private function note(array $result): void
    {
        $db    = $this->db;
        $value = json_encode(['time' => gmdate('c')] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_secrets'))->where($db->quoteName('name') . ' = ' . $db->quote(self::NOTE)))->execute();

            $row = (object) ['name' => self::NOTE, 'value' => $value];

            $db->insertObject('#__askdesk_secrets', $row);
        } catch (\Throwable) {
            // Not noted: the back end says it has not run
        }
    }

    /** A file's name in the archive: its own, without what a file system would not take, and not twice */
    private static function safeName(string $name, int $id, array &$taken): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1f\/\\\\:*?"<>|]+/u', '_', $name), ' .');
        $name = $name === '' ? 'file' : mb_substr($name, 0, 120);
        $name = $id . '-' . $name;

        while (isset($taken[strtolower($name)])) {
            $name = $id . '-' . bin2hex(random_bytes(2)) . '-' . $name;
        }

        $taken[strtolower($name)] = true;

        return $name;
    }

    /** A half-written chat taken away again (only what this run made) */
    private static function clear(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
