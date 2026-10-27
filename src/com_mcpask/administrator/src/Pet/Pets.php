<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Pet;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Pets for the floating button (docs/pet-spec.md): animated pictures (APNG, animated AVIF, WebP or
 * GIF) of the site's own, and a still one for visitors who ask for less motion. A pet is chosen by
 * the module, or by an embed key, once one is made.
 *
 * Since 0.22.0 a pet's pictures are a library of its own, each named as the administrator likes,
 * put into the button's moments ("blocks") by dragging: a moment may have several, one of them
 * picked each time it plays, and one picture may serve several moments. Pets made before keep
 * working: each of their pictures is in the library, in the moment it was uploaded for.
 *
 * Pictures are only files of the site, in images/.
 *
 * @since  0.12.0
 */
final class Pets
{
    /** The moments, and how long each plays when it plays once (0: over and over) */
    public const ACTIONS = [
        'idle'     => 0,
        'hover'    => 1.5,
        'drag'     => 0,
        'thinking' => 0,
        'talking'  => 0,
        'happy'    => 2,
        'confused' => 2,
        'sleep'    => 0,
        'wake'     => 1,
        // Bowing to say thank you, when a visitor rates a chat with a person (AI Ask Desk, 0.20.0)
        'thanks'   => 2.5,
        // By itself (0.22.0): now and then while waiting at its place, and walking across the window
        'moments'  => 4,
        'walk'     => 0,
        // Turning from the front to set off, and back to the front when it stops (0.22.0)
        'turn'     => 0.5,
        'stop'     => 0.5,
        // Getting sleepy, before it sleeps (0.22.0)
        'sleepy'   => 2,
        // What the visitor does (0.22.0): the pointer near it, words selected, the end of the page
        // reached, back to the page after a while
        'near'     => 1.5,
        'selected' => 3,
        'bottom'   => 2,
        'back'     => 1.5,
    ];

    /** The moments in the editor, in groups (0.22.0) */
    public const GROUPS = [
        'rest'   => ['idle', 'moments', 'turn', 'walk', 'stop', 'sleepy', 'sleep', 'wake'],
        'sense'  => ['hover', 'near', 'drag', 'selected', 'bottom', 'back'],
        'answer' => ['thinking', 'talking', 'happy', 'confused', 'thanks'],
    ];

    /**
     * The moments of a pet that clears the conversation for a new one (0.18.0): while it takes the
     * old one away and asks what is next, and once it waits beside the box to type in. Without a
     * picture of its own a moment shows the first one.
     */
    public const CLEANER_ACTIONS = ['clean' => 0, 'point' => 0];

    /** Kinds of pets: those of the button and the agents, and those that clear the conversation */
    public const KINDS = ['agent', 'cleaner'];

    public const EXTENSIONS = ['png', 'apng', 'avif', 'webp', 'gif'];

    /** Still pictures (the still one and the square thumbnail) may be JPEG too */
    private const STILL_EXTENSIONS = ['png', 'webp', 'gif', 'avif', 'jpg', 'jpeg'];

    /** Pictures larger than this slow pages down: the back end says so */
    public const ADVISED_BYTES = 1572864;

    /** Nothing larger is used */
    private const MAX_BYTES = 5242880;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Every picture a pet may have, by the name it is uploaded as, with the setting it is kept in:
     * for each moment the whole pet ("idle") and its square thumbnail ("idle_thumb"); the still
     * picture of the whole pet ("static") and its thumbnail ("thumb").
     *
     * @return  array<string, string>
     */
    public static function pictures(): array
    {
        $fields = ['static' => 'static', 'thumb' => 'thumb'];

        foreach (array_keys(self::ACTIONS) as $name) {
            $fields[$name]            = $name . '_image';
            $fields[$name . '_thumb'] = $name . '_thumb';
        }

        foreach (array_keys(self::CLEANER_ACTIONS) as $name) {
            $fields[$name] = $name . '_image';
        }

        return $fields;
    }

    /**
     * Whether a picture is a still one (which may be JPEG too).
     */
    private static function isStill(string $field): bool
    {
        return \in_array($field, ['static', 'thumb'], true);
    }

    /**
     * @return  object[]  With params as Registry
     */
    public function all(string $kind = ''): array
    {
        $pets = array_map([self::class, 'prepare'], $this->db->setQuery(
            $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__mcpask_pets'))->order($this->hasOrdering() ? 'ordering, title' : 'title')
        )->loadObjectList());

        return $kind === '' ? $pets : array_values(array_filter($pets, static fn ($pet) => self::kindOf($pet) === $kind));
    }

    /**
     * What a pet is for: "agent" (the button and the agents) or "cleaner" (0.18.0).
     */
    public static function kindOf(object $pet): string
    {
        return $pet->params->get('kind', 'agent') === 'cleaner' ? 'cleaner' : 'agent';
    }

    /**
     * A pet that clears the conversation, as the page needs it: its pictures, and its size. Null
     * when there is no such pet or it has no picture.
     *
     * @return  array{clean: string, point: string, size: int}|null
     */
    public function cleaner(string $choice): ?array
    {
        $pet = ctype_digit($choice) ? $this->get((int) $choice) : null;

        if (!$pet || self::kindOf($pet) !== 'cleaner') {
            return null;
        }

        $clean = self::clean((string) $pet->params->get('clean_image', ''));
        $point = self::clean((string) $pet->params->get('point_image', ''));

        return $clean === '' ? null : [
            'clean' => self::url($clean),
            'point' => self::url($point !== '' ? $point : $clean),
            'size'  => max(80, min(320, (int) $pet->params->get('size', 160))),
        ];
    }

    public function get(int $id): ?object
    {
        $row = $this->db->setQuery(
            $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__mcpask_pets'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject();

        return $row ? self::prepare($row) : null;
    }

    public function save(array $data): int
    {
        $now    = Factory::getDate()->toSql();
        $id     = (int) ($data['id'] ?? 0);
        $params = (array) ($data['params'] ?? []);

        // Only paths of the site's own pictures are kept
        foreach (self::pictures() as $field) {
            $params[$field] = self::clean((string) ($params[$field] ?? ''));
        }

        // And those of its library (0.22.0)
        if (isset($params['library']) && \is_array($params['library'])) {
            foreach ($params['library'] as &$item) {
                $item['image'] = self::clean((string) ($item['image'] ?? ''));
                $item['thumb'] = self::clean((string) ($item['thumb'] ?? ''));
            }

            unset($item);
        }

        $row = (object) [
            'title'    => mb_substr(trim((string) ($data['title'] ?? '')), 0, 255),
            'params'   => json_encode($params, JSON_UNESCAPED_UNICODE),
            'modified' => $now,
        ];

        if ($id > 0 && $this->get($id)) {
            $row->id = $id;
            $this->db->updateObject('#__mcpask_pets', $row, 'id');

            return $id;
        }

        $row->created = $now;

        // A new pet comes last in the lists
        if ($this->hasOrdering()) {
            $row->ordering = 1 + (int) $this->db->setQuery($this->db->getQuery(true)->select('MAX(ordering)')->from($this->db->quoteName('#__mcpask_pets')))->loadResult();
        }

        $this->db->insertObject('#__mcpask_pets', $row, 'id');

        return (int) $row->id;
    }

    /**
     * Put pets in this order (0.19.0): the order of the lists and of the choices.
     *
     * @param   int[]  $ids  The pets, first to last
     *
     * @throws  \RuntimeException  When the database has no place for an order yet
     */
    public function reorder(array $ids): void
    {
        if (!$this->hasOrdering()) {
            throw new \RuntimeException('The order of pets cannot be kept: the database of AI Ask is not up to date. Install the package again.');
        }

        foreach (array_values(array_unique(array_map('intval', $ids))) as $position => $id) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__mcpask_pets'))
                    ->set('ordering = ' . ($position + 1))
                    ->where('id = ' . $id)
            )->execute();
        }
    }

    /**
     * Move a pet one place earlier (-1) or later (1) among the pets of its kind.
     */
    public function move(int $id, int $step): void
    {
        $pet = $this->get($id);

        if (!$pet) {
            return;
        }

        $ids  = array_map(static fn ($item) => (int) $item->id, $this->all(self::kindOf($pet)));
        $from = array_search($id, $ids, true);
        $to   = $from + ($step < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= \count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        $this->reorder($ids);
    }

    /**
     * A picture that stands for a pet in lists: its still thumbnail, else the thumbnail of its
     * idle moment, else the still or idle picture (a cleaning pet's picture while cleaning).
     */
    public static function face(object $pet): string
    {
        $params = $pet->params;
        $fields = self::kindOf($pet) === 'cleaner' ? ['clean_image', 'point_image'] : ['thumb', 'idle_thumb', 'static', 'idle_image'];

        foreach ($fields as $field) {
            $path = self::clean((string) $params->get($field, ''));

            if ($path !== '' && is_file(JPATH_ROOT . '/' . $path)) {
                return self::url($path);
            }
        }

        return '';
    }

    /**
     * The pets that can be chosen, with their pictures, for the picker of the settings.
     *
     * @return  array<string, array{title: string, face: string}>  By ID
     */
    public function faces(string $kind = 'agent'): array
    {
        $faces = [];

        foreach ($this->all($kind) as $pet) {
            $faces[(string) $pet->id] = ['title' => (string) $pet->title, 'face' => self::face($pet)];
        }

        return $faces;
    }

    private ?bool $ordered = null;

    /**
     * Whether the table has the order column (an update that has not run its SQL has not).
     */
    private function hasOrdering(): bool
    {
        try {
            return $this->ordered ??= isset($this->db->getTableColumns('#__mcpask_pets')['ordering']);
        } catch (\Throwable) {
            return $this->ordered = false;
        }
    }

    public function delete(int $id): void
    {
        $this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mcpask_pets'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->execute();

        // Its pictures go with it
        $folder = JPATH_ROOT . '/' . self::folder($id);

        foreach (glob($folder . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($folder)) {
            rmdir($folder);
        }
    }

    /**
     * Where a pet's uploaded pictures are kept.
     */
    public static function folder(int $id): string
    {
        return 'images/mcpask/pets/' . $id;
    }

    /**
     * Keep an uploaded picture for one of a pet's moments (or its still picture): checked to be the
     * picture it says it is, and named anew, so browsers do not keep showing the old one.
     *
     * @param   string  $field  "static" or a moment's name
     *
     * @return  string  Its path from the site's root
     *
     * @throws  \RuntimeException  What is wrong with it, for the administrator
     */
    public static function store(int $id, string $field, string $temp, string $name): string
    {
        if (!isset(self::pictures()[$field])) {
            throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_TYPE', $name));
        }

        return self::keep($id, $field, self::isStill($field), $temp, $name);
    }

    /**
     * Keep an uploaded picture of a pet's library (0.22.0): the picture itself, or its thumbnail.
     *
     * @param   string  $key  The picture's key in the library
     *
     * @return  string  Its path from the site's root
     *
     * @throws  \RuntimeException  What is wrong with it, for the administrator
     */
    public static function storeItem(int $id, string $key, bool $thumb, string $temp, string $name): string
    {
        if (!preg_match('/^[a-z0-9_]{1,32}$/', $key)) {
            throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_TYPE', $name));
        }

        return self::keep($id, ($thumb ? 'itemthumb-' : 'item-') . $key, $thumb, $temp, $name);
    }

    /**
     * Take away a picture of a pet's own folder that nothing uses any more (0.22.0).
     *
     * @param   string[]  $used  The paths still used
     */
    public static function discard(int $id, string $path, array $used): void
    {
        if ($path !== '' && str_starts_with($path, self::folder($id) . '/') && !\in_array($path, $used, true) && is_file(JPATH_ROOT . '/' . $path)) {
            unlink(JPATH_ROOT . '/' . $path);
        }
    }

    private static function keep(int $id, string $field, bool $still, string $temp, string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!\in_array($extension, $still ? self::STILL_EXTENSIONS : self::EXTENSIONS, true)) {
            throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_TYPE', $name));
        }

        if (!is_uploaded_file($temp) && !is_file($temp) || filesize($temp) > self::MAX_BYTES) {
            throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_SIZE', $name));
        }

        if (!self::isPicture($temp)) {
            throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_TYPE', $name));
        }

        $folder = JPATH_ROOT . '/' . self::folder($id);

        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
            file_put_contents($folder . '/index.html', '<!DOCTYPE html><title></title>');
        }

        // The picture this one takes the place of
        foreach (glob($folder . '/' . $field . '-*') ?: [] as $old) {
            unlink($old);
        }

        $path = self::folder($id) . '/' . $field . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

        if (!(is_uploaded_file($temp) ? move_uploaded_file($temp, JPATH_ROOT . '/' . $path) : copy($temp, JPATH_ROOT . '/' . $path))) {
            throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_SAVE', $name));
        }

        return $path;
    }

    /**
     * How long an animated picture plays once, in seconds, as the file says: APNG and GIF by the
     * delays of their frames, animated WebP by the durations of its frames. Null when it does not
     * say (a still picture, AVIF).
     *
     * @since  0.18.0
     */
    public static function duration(string $file): ?float
    {
        $data = @file_get_contents($file, false, null, 0, self::MAX_BYTES);

        if (!\is_string($data) || \strlen($data) < 16) {
            return null;
        }

        $total  = 0.0;
        $frames = 0;

        if (str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            // fcTL chunks: delay_num / delay_den seconds each (a den of 0 means 1/100)
            for ($at = 8; $at + 8 <= \strlen($data);) {
                $length = unpack('N', substr($data, $at, 4))[1];
                $type   = substr($data, $at + 4, 4);

                if ($type === 'fcTL' && $length >= 26) {
                    ['num' => $num, 'den' => $den] = unpack('nnum/nden', substr($data, $at + 8 + 20, 4));
                    $total += $num / ($den ?: 100);
                    $frames++;
                }

                if ($type === 'IEND') {
                    break;
                }

                $at += 12 + $length;
            }
        } elseif (str_starts_with($data, 'GIF8')) {
            // Graphic control extensions: delays in hundredths of a second
            preg_match_all('/\x21\xF9\x04.(..)/s', $data, $matches);

            foreach ($matches[1] as $delay) {
                $total += unpack('v', $delay)[1] / 100;
                $frames++;
            }
        } elseif (str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP') {
            // ANMF chunks: a 24-bit duration in milliseconds after the frame's place and size
            for ($at = 12; $at + 8 <= \strlen($data);) {
                $type   = substr($data, $at, 4);
                $length = unpack('V', substr($data, $at + 4, 4))[1];

                if ($type === 'ANMF' && $length >= 16) {
                    $bytes  = substr($data, $at + 8 + 12, 3);
                    $total += (\ord($bytes[0]) | (\ord($bytes[1]) << 8) | (\ord($bytes[2]) << 16)) / 1000;
                    $frames++;
                }

                $at += 8 + $length + ($length % 2);
            }
        }

        return $frames > 1 && $total > 0 ? round($total, 2) : null;
    }

    /**
     * The pets that can be chosen, for lists: those of the library.
     *
     * @return  array<string, string>  By ID
     */
    public function choices(string $kind = 'agent'): array
    {
        $choices = [];

        foreach ($this->all($kind) as $pet) {
            $choices[(string) $pet->id] = $pet->title;
        }

        return $choices;
    }

    /**
     * What the button needs of a pet: its sizes, words, how soon it sleeps, and the address and
     * length of each moment it has. Null when there is no such pet, or it has no idle picture.
     *
     * @return  array{thumb: string, size: int, mobile: int, bubble: string, sleep: int, still: string, actions: array<string, array{src: string, thumb: string, seconds: float}>}|null
     */
    public function display(string $choice): ?array
    {
        $params = ctype_digit($choice) ? $this->get((int) $choice)?->params : null;

        if (!$params) {
            return null;
        }

        $actions          = [];
        [$items, $blocks] = self::library($params);

        // Each moment its pictures, one picked each time it plays: the first, and the others with it
        foreach (self::ACTIONS as $name => $length) {
            $plays = [];

            foreach ($blocks[$name] ?? [] as $key) {
                $path = self::clean((string) ($items[$key]['image'] ?? ''));

                if ($path === '') {
                    continue;
                }

                $seconds = $length > 0 ? max(0.3, min(10.0, (float) ($items[$key]['seconds'] ?? 0) ?: $length)) : 0;
                $thumb   = self::clean((string) ($items[$key]['thumb'] ?? ''));
                $plays[] = ['src' => self::url($path), 'thumb' => $thumb !== '' ? self::url($thumb) : '', 'seconds' => round($seconds, 2)];
            }

            if ($plays) {
                $actions[$name] = $plays[0] + (\count($plays) > 1 ? ['more' => \array_slice($plays, 1, 11)] : []);
            }
        }

        if (!isset($actions['idle'])) {
            return null;
        }

        $still = self::clean((string) $params->get('static', ''));
        $thumb = self::clean((string) $params->get('thumb', ''));

        return [
            'thumb'   => $thumb !== '' ? self::url($thumb) : ($still !== '' ? self::url($still) : ''),
            'size'    => max(48, min(200, (int) $params->get('size', 96))),
            'mobile'  => max(40, min(160, (int) $params->get('mobile_size', 72))),
            'bubble'  => mb_substr(trim((string) $params->get('bubble', '')), 0, 60),
            // Greetings at the start of each visit: by name to signed-in visitors, a few lines in turn to others
            'member'  => mb_substr(trim((string) $params->get('greet_member', '')), 0, 80),
            'guest'   => \array_slice(array_values(array_filter(array_map(static fn ($line) => mb_substr(trim($line), 0, 80), preg_split('/\R/u', (string) $params->get('greet_guest', '')) ?: []), 'strlen')), 0, 5),
            'sleep'   => max(0, min(3600, (int) $params->get('sleep_after', 60))),
            'still'   => $still !== '' ? self::url($still) : '',
            'actions' => $actions,
        ];
    }

    /**
     * A pet's library (0.22.0): its pictures by key, each with its name, picture, thumbnail and how
     * long it plays once (0: as long as the moment it plays in); and the keys of the pictures of
     * each moment. A pet made before has a picture for each moment it had one for, in that moment.
     *
     * @return  array{0: array<string, array{name: string, image: string, thumb: string, seconds: float}>, 1: array<string, string[]>}
     */
    public static function library(Registry $params): array
    {
        $items  = [];
        $blocks = [];

        if ($params->exists('library')) {
            foreach ((array) json_decode((string) json_encode($params->get('library', [])), true) as $item) {
                $key = (string) ($item['key'] ?? '');

                if (\is_array($item) && preg_match('/^[a-z0-9_]{1,32}$/', $key)) {
                    $items[$key] = [
                        'name'    => mb_substr(trim((string) ($item['name'] ?? '')), 0, 60),
                        'image'   => (string) ($item['image'] ?? ''),
                        'thumb'   => (string) ($item['thumb'] ?? ''),
                        'seconds' => (float) ($item['seconds'] ?? 0),
                    ];
                }
            }

            foreach ((array) json_decode((string) json_encode($params->get('blocks', [])), true) as $name => $keys) {
                if (isset(self::ACTIONS[$name])) {
                    $blocks[$name] = array_values(array_unique(array_filter(array_map('strval', (array) $keys), static fn ($key) => isset($items[$key]))));
                }
            }

            return [$items, $blocks];
        }

        foreach (self::ACTIONS as $name => $length) {
            $image = (string) $params->get($name . '_image', '');

            if ($image !== '') {
                $items[$name]  = [
                    'name'    => Text::_('COM_MCPASK_PET_ACTION_' . strtoupper($name)),
                    'image'   => $image,
                    'thumb'   => (string) $params->get($name . '_thumb', ''),
                    'seconds' => $length > 0 ? (float) $params->get($name . '_seconds', 0) : 0,
                ];
                $blocks[$name] = [$name];
            }
        }

        return [$items, $blocks];
    }

    /**
     * How an agent shows itself (0.12.3): a pet of the library (its moments), or one picture of the
     * site's (an APNG moves too). Null for neither.
     *
     * @return  array{actions: array<string, array{src: string, seconds: float}>, still: string}|null
     */
    public function avatar(string $choice, string $image): ?array
    {
        $path = self::clean($image);

        if ($path !== '') {
            return ['actions' => ['idle' => ['src' => self::url($path), 'thumb' => self::url($path), 'seconds' => 0]], 'still' => '', 'thumb' => ''];
        }

        $pet = $choice !== '' ? $this->display($choice) : null;

        return $pet ? ['actions' => $pet['actions'], 'still' => $pet['still'], 'thumb' => $pet['thumb']] : null;
    }

    /**
     * The size of each picture of a pet, for the back end.
     *
     * @return  array<string, int>  Bytes, by field
     */
    public static function sizes(Registry $params): array
    {
        $sizes = [];

        foreach (self::pictures() as $field) {
            $path = self::clean((string) $params->get($field, ''));

            if ($path !== '') {
                $sizes[$field] = (int) filesize(JPATH_ROOT . '/' . $path);
            }
        }

        // The library's (0.22.0), without counting twice what is also kept for a moment
        if ($params->exists('library')) {
            $sizes = array_intersect_key($sizes, ['static' => 1, 'thumb' => 1]);

            foreach (self::library($params)[0] as $key => $item) {
                foreach (['image' => 'item:', 'thumb' => 'itemthumb:'] as $part => $prefix) {
                    $path = self::clean($item[$part]);

                    if ($path !== '') {
                        $sizes[$prefix . $key] = (int) filesize(JPATH_ROOT . '/' . $path);
                    }
                }
            }
        }

        return $sizes;
    }

    /**
     * A picture's path from the site's root, if it may be used: in images/, of a picture type,
     * there, and not too large. Otherwise empty.
     */
    public static function clean(string $value): string
    {
        $path = ltrim(str_replace('\\', '/', HTMLHelper::_('cleanImageURL', trim($value))->url ?? ''), '/');
        $path = preg_replace('#^' . preg_quote(rtrim(Uri::root(true), '/') . '/', '#') . '#', '', '/' . $path) ?? '';
        $path = ltrim(rawurldecode((string) strtok($path, '?#')), '/');

        if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'images/')) {
            return '';
        }

        if (!\in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::STILL_EXTENSIONS, true) && !\in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            return '';
        }

        $file = JPATH_ROOT . '/' . $path;

        return is_file($file) && filesize($file) <= self::MAX_BYTES && self::isPicture($file) ? $path : '';
    }

    /**
     * Whether the file is the picture its name says (by its first bytes).
     */
    private static function isPicture(string $file): bool
    {
        $head = (string) file_get_contents($file, false, null, 0, 32);

        return str_starts_with($head, "\x89PNG\r\n\x1a\n")
            || str_starts_with($head, "\xFF\xD8\xFF")
            || str_starts_with($head, 'GIF8')
            || (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP')
            || (substr($head, 4, 4) === 'ftyp' && \in_array(substr($head, 8, 4), ['avif', 'avis', 'mif1', 'msf1'], true));
    }

    /**
     * The address of a picture, with when it last changed, so a new one is not mistaken for the old.
     */
    private static function url(string $path): string
    {
        return rtrim(Uri::root(true), '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path))) . '?' . filemtime(JPATH_ROOT . '/' . $path);
    }

    private static function prepare(object $row): object
    {
        $row->params = new Registry($row->params ?: '{}');

        return $row;
    }
}
