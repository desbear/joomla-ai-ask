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
 * The people answering: whether each is there (online, busy, away), the language they work in,
 * and when their console was last heard from. Someone whose console has not been heard from for
 * a while is away, whatever they set.
 *
 * @since  0.1.0
 */
final class Staff
{
    public const STATUSES = ['online', 'busy', 'away'];

    /** Seconds after the console's last request that its person still counts as there. */
    public const PRESENT_SECONDS = 90;

    /** Seconds between notes that a console is still there. */
    private const TOUCH_SECONDS = 20;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * @return  object{user_id: int, status: string, language: string, seen: ?string}
     */
    public function get(int $userId): object
    {
        $db  = $this->db;
        $row = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_staff'))->where('user_id = :id')->bind(':id', $userId, ParameterType::INTEGER)
        )->loadObject();

        return (object) [
            'user_id'  => $userId,
            'status'   => $row && \in_array($row->status, self::STATUSES, true) ? (string) $row->status : 'away',
            'language' => $row && $row->language !== '' ? (string) $row->language : $this->settings->staffLanguage(),
            'seen'     => $row->seen ?? null,
            'known'    => (bool) $row,
        ];
    }

    /**
     * Set someone's status or language; the console is heard from as well.
     */
    public function set(int $userId, ?string $status = null, ?string $language = null): object
    {
        $current = $this->get($userId);
        $row     = (object) [
            'user_id'  => $userId,
            'status'   => $status !== null && \in_array($status, self::STATUSES, true) ? $status : $current->status,
            'language' => $language !== null && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $language) ? $language : ($current->known ? $current->language : ''),
            'seen'     => Factory::getDate()->toSql(),
        ];

        if ($current->known) {
            $this->db->updateObject('#__askdesk_staff', $row, 'user_id');
        } else {
            $this->db->insertObject('#__askdesk_staff', $row);
        }

        return $this->get($userId);
    }

    /**
     * The console is still there (noted now and then, not on every request).
     */
    public function touch(int $userId): object
    {
        $current = $this->get($userId);

        if (!$current->known || !$current->seen || strtotime($current->seen . ' UTC') < time() - self::TOUCH_SECONDS) {
            return $this->set($userId);
        }

        return $current;
    }

    /**
     * Whether someone is there to chat now: online, with their console open.
     */
    public function anyOnline(): bool
    {
        $db    = $this->db;
        $since = Factory::getDate('-' . self::PRESENT_SECONDS . ' seconds')->toSql();

        return (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__askdesk_staff'))
                ->where('status = ' . $db->quote('online'))->where('active = 1')->where('seen >= :since')->bind(':since', $since)
        )->loadResult() > 0;
    }

    /**
     * Everyone with a console open now, with their status.
     *
     * @return  array<int, array{id: int, name: string, status: string}>
     */
    public function present(): array
    {
        $db    = $this->db;
        $since = Factory::getDate('-' . self::PRESENT_SECONDS . ' seconds')->toSql();
        $rows  = $db->setQuery(
            $db->getQuery(true)->select('s.user_id, s.status, u.name')->from($db->quoteName('#__askdesk_staff', 's'))
                ->join('INNER', $db->quoteName('#__users', 'u'), 'u.id = s.user_id')
                ->where('s.seen >= :since')->where('s.active = 1')->where('s.status <> ' . $db->quote('away'))->bind(':since', $since)->order('u.name')
        )->loadObjectList();

        return array_map(static fn ($row) => ['id' => (int) $row->user_id, 'name' => (string) $row->name, 'status' => (string) $row->status], $rows);
    }

    /**
     * @param   int[]  $ids
     *
     * @return  array<int, string>  Names by user: the name they show visitors, or their account's
     */
    public function names(array $ids): array
    {
        return array_map(static fn ($profile) => $profile['name'], $this->profiles($ids));
    }

    /**
     * How people answering show themselves (0.1.0): the name shown to visitors (their account's
     * unless set), and their face, a pet of AI Ask's library or one picture, when chosen.
     *
     * @param   int[]  $ids
     *
     * @return  array<int, array{name: string, face: array{src: string, still: string}|null}>
     */
    public function profiles(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return [];
        }

        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('u.id, u.name, s.display_name, s.avatar, s.avatar_image, s.thanks_image')
                ->from($db->quoteName('#__users', 'u'))
                ->join('LEFT', $db->quoteName('#__askdesk_staff', 's'), 's.user_id = u.id')
                ->whereIn('u.id', $ids)
        )->loadObjectList('id');

        $profiles = [];

        foreach ($rows as $id => $row) {
            $profiles[(int) $id] = [
                'name' => trim((string) $row->display_name) !== '' ? trim((string) $row->display_name) : (string) $row->name,
                'face' => self::face((string) $row->avatar, (string) $row->avatar_image, $db, (string) $row->thanks_image),
            ];
        }

        return $profiles;
    }

    /**
     * A face as the pages show it: the picture to show (a pet's resting moment, small), a still one
     * for visitors who ask for less motion, and the person bowing to say thank you (a pet's moment of
     * thanks, else its happy one; with a picture, the one given for it) when a visitor rates a chat.
     *
     * @return  array{src: string, still: string, thanks: string}|null
     */
    public static function face(string $pet, string $image, DatabaseInterface $db, string $thanks = ''): ?array
    {
        $pets = 'Gss\\Component\\Mcpask\\Administrator\\Pet\\Pets';

        if (($pet === '' && $image === '') || !class_exists($pets)) {
            return null;
        }

        static $cache = [];

        $key = $pet . '|' . $image . '|' . $thanks;

        if (!\array_key_exists($key, $cache)) {
            try {
                $look = (new $pets($db))->avatar($pet, $image);
            } catch (\Throwable) {
                $look = null;
            }

            $idle  = $look['actions']['idle'] ?? null;
            $bow   = $pet !== '' && $image === '' ? ($look['actions']['thanks']['src'] ?? $look['actions']['happy']['src'] ?? '') : '';
            $given = $thanks !== '' ? ((new $pets($db))->avatar('', $thanks)['actions']['idle']['src'] ?? '') : '';

            $cache[$key] = $idle ? [
                'src'    => (string) ($idle['thumb'] ?? '') ?: (string) ($idle['src'] ?? ''),
                'still'  => (string) ($look['thumb'] ?? '') ?: (string) ($look['still'] ?? ''),
                'thanks' => (string) ($given ?: $bow ?: ($idle['src'] ?? '')),
            ] : null;
        }

        return $cache[$key];
    }

    /**
     * The pets of AI Ask's library a person answering may show as, and whether each has its moment
     * of bowing thanks (only those may be chosen).
     *
     * @return  array<int, array{title: string, thanks: bool, thumb: string}>
     */
    public static function pets(DatabaseInterface $db): array
    {
        $class = 'Gss\\Component\\Mcpask\\Administrator\\Pet\\Pets';

        if (!class_exists($class)) {
            return [];
        }

        try {
            $library = new $class($db);
            $pets    = [];

            foreach ($library->choices('agent') as $id => $title) {
                $shown           = $library->display((string) $id);
                $pets[(int) $id] = [
                    'title'  => (string) $title,
                    'thanks' => isset($shown['actions']['thanks']),
                    'thumb'  => (string) ($shown['thumb'] ?? '') ?: (string) ($shown['actions']['idle']['thumb'] ?? '') ?: (string) ($shown['actions']['idle']['src'] ?? ''),
                ];
            }

            return $pets;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * A picture a person answering gives for their face (or their bowing thanks): checked as a
     * picture of 1 MB at most, kept among the site's images, to be shown to visitors.
     *
     * @param  array{name: string, tmp_name: string, size: int, error: int}  $upload
     *
     * @return  string  Its path from the site's root
     *
     * @throws  \RuntimeException
     */
    public static function keepPicture(int $userId, array $upload): string
    {
        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));

        if (($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name']) || !\in_array($extension, ['png', 'apng', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_ASKDESK_FILE_TYPE'));
        }

        if ((int) $upload['size'] > Files::MAX_IMAGE_BYTES || @getimagesize((string) $upload['tmp_name']) === false) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_ASKDESK_FILE_IMAGE_TOO_LARGE'));
        }

        $folder = JPATH_ROOT . '/images/askdesk/staff';

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
            file_put_contents($folder . '/index.html', '<!DOCTYPE html><title></title>');
        }

        $name = $userId . '-' . bin2hex(random_bytes(6)) . '.' . ($extension === 'apng' ? 'png' : $extension);

        if (!move_uploaded_file((string) $upload['tmp_name'], $folder . '/' . $name)) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_ASKDESK_FILE_FAILED'));
        }

        return 'images/askdesk/staff/' . $name;
    }

    /**
     * What a person keeps of how they show themselves.
     *
     * @return  array{display_name: string, avatar: string, avatar_image: string, thanks_image: string}
     */
    public function profile(int $userId): array
    {
        $db  = $this->db;
        $row = $db->setQuery(
            $db->getQuery(true)->select('display_name, avatar, avatar_image, thanks_image')->from($db->quoteName('#__askdesk_staff'))->where('user_id = :id')->bind(':id', $userId, ParameterType::INTEGER)
        )->loadAssoc() ?: [];

        return ['display_name' => (string) ($row['display_name'] ?? ''), 'avatar' => (string) ($row['avatar'] ?? ''), 'avatar_image' => (string) ($row['avatar_image'] ?? ''), 'thanks_image' => (string) ($row['thanks_image'] ?? '')];
    }

    /**
     * Keep how someone shows themselves.
     */
    public function setProfile(int $userId, string $name, string $pet, string $image, string $thanks = ''): void
    {
        $current = $this->get($userId);
        $row     = (object) [
            'user_id'      => $userId,
            'display_name' => mb_substr(trim(strip_tags($name)), 0, 100),
            'avatar'       => mb_substr(preg_replace('/[^A-Za-z0-9_:\-]/', '', $pet) ?? '', 0, 100),
            'avatar_image' => mb_substr(trim($image), 0, 500),
            'thanks_image' => mb_substr(trim($thanks), 0, 500),
        ];

        if ($current->known) {
            $this->db->updateObject('#__askdesk_staff', $row, 'user_id');
        } else {
            $row->status   = 'away';
            $row->language = '';
            $this->db->insertObject('#__askdesk_staff', $row);
        }
    }

    /**
     * The people answering: the members made so by a super user, not blocked.
     *
     * @return  array<int, object{id: int, name: string, email: string, username: string, display_name: string, avatar: string, avatar_image: string, status: string, seen: ?string}>
     */
    public function people(): array
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)
                ->select('u.id, u.name, u.username, u.email, s.display_name, s.avatar, s.avatar_image, s.status, s.seen')
                ->from($db->quoteName('#__askdesk_staff', 's'))
                ->join('INNER', $db->quoteName('#__users', 'u'), 'u.id = s.user_id')
                ->where('s.active = 1')
                ->where('u.block = 0')
                ->order('u.name'),
            0,
            300
        )->loadObjectList();
    }

    /**
     * Members who are not among the people answering yet, by name, username or e-mail.
     *
     * @return  object[]
     */
    public function candidates(string $words, int $limit = 20): array
    {
        $words = trim($words);

        if (mb_strlen($words) < 1) {
            return [];
        }

        $db   = $this->db;
        $like = '%' . $db->escape($words, true) . '%';

        return $db->setQuery(
            $db->getQuery(true)
                ->select('u.id, u.name, u.username, u.email')
                ->from($db->quoteName('#__users', 'u'))
                ->join('LEFT', $db->quoteName('#__askdesk_staff', 's'), 's.user_id = u.id')
                ->where('u.block = 0')
                ->where('(s.active IS NULL OR s.active = 0)')
                ->where('(u.name LIKE :name OR u.username LIKE :username OR u.email LIKE :email)')
                ->bind(':name', $like)->bind(':username', $like)->bind(':email', $like)
                ->order('u.name'),
            0,
            max(1, min(50, $limit))
        )->loadObjectList();
    }

    /**
     * Make a member one of the people answering, or no longer.
     */
    public function designate(int $userId, bool $active): void
    {
        $current = $this->get($userId);
        $row     = (object) ['user_id' => $userId, 'active' => (int) $active];

        if ($current->known) {
            $this->db->updateObject('#__askdesk_staff', $row, 'user_id');
        } else {
            $row->status   = 'away';
            $row->language = '';
            $this->db->insertObject('#__askdesk_staff', $row);
        }
    }
}
