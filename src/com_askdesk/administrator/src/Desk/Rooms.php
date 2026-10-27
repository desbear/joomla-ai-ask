<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Gss\Component\Mcpask\Administrator\Ask\Settings as AskSettings;
use Gss\Component\Mcpask\Administrator\Ask\Translator;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Chats between visitors and the people answering them. A visitor's page holds the room's random
 * key; the room is found by its hash. A room waits for someone to take it, is answered (active),
 * or was a message left when no one was there; then it is closed.
 *
 * Every message is kept as it was written and, translated, in the other side's language: what the
 * visitor writes is translated for the people answering when it is sent, what they write for the
 * visitor. Each side reads its own language and can see the words first written.
 *
 * Kept: chats of signed-in visitors for the days set; chats of guests only while they go on (an
 * hour after they close, they are gone).
 *
 * @since  0.1.0
 */
final class Rooms
{
    public const STATUSES = ['waiting', 'active', 'message', 'closed'];

    /** Characters of one message at most. */
    public const MAX_TEXT = 2000;

    /** Messages one room may have. */
    public const MAX_MESSAGES = 400;

    /** Messages a visitor may send in a minute. */
    private const PER_MINUTE = 20;

    /** Minutes a guest's closed chat stays, for the person answering to finish with it. */
    private const GUEST_GRACE_MINUTES = 60;

    /** Seconds between notes that the visitor's page is still there. */
    private const SEEN_SECONDS = 20;

    /** Seconds a message may take to be translated, after which it is shown as written. */
    private const TRANSLATE_SECONDS = 120;

    /** Seconds after a sign of typing that one side is still taken to be typing. */
    private const TYPING_SECONDS = 6;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * A new room for a visitor passed on by an agent.
     *
     * @param  array{agent_id: int, user_id: int, name: string, email: string, company?: string, language: string, page: string, entry: array, status?: string}  $data
     *
     * @return  array{key: string, id: int}
     */
    public function open(array $data): array
    {
        $key  = bin2hex(random_bytes(24));
        $now  = Factory::getDate()->toSql();
        $page = Translator::tag((string) $data['language']) ?: (string) $data['language'];

        $row = (object) [
            'token'            => hash('sha256', $key),
            'agent_id'         => (int) $data['agent_id'],
            'status'           => \in_array($data['status'] ?? '', ['waiting', 'message'], true) ? $data['status'] : 'waiting',
            'staff_id'         => 0,
            'user_id'          => (int) $data['user_id'],
            'name'             => mb_substr(trim((string) $data['name']), 0, 100),
            'email'            => mb_substr(filter_var(trim((string) $data['email']), FILTER_VALIDATE_EMAIL) ?: '', 0, 255),
            'company'          => mb_substr(trim(strip_tags((string) ($data['company'] ?? ''))), 0, 150),
            'page_language'    => mb_substr($page, 0, 10),
            'visitor_language' => mb_substr($page, 0, 10),
            'staff_language'   => mb_substr($this->settings->staffLanguage(), 0, 10),
            'page'             => mb_substr((string) $data['page'], 0, 500),
            'entry'            => json_encode(self::cleanEntry((array) $data['entry']), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'last_message'     => 0,
            'staff_seen'       => 0,
            'created'          => $now,
            'modified'         => $now,
            'visitor_seen'     => $now,
        ];

        $this->db->insertObject('#__askdesk_rooms', $row, 'id');
        $this->forgetOld();

        // Someone new waits: the people answering are told (a message left, by its message)
        if ($row->status === 'waiting') {
            Push::waiting((int) $row->id);
        }

        return ['key' => $key, 'id' => (int) $row->id];
    }

    /**
     * A message left when no one was there to chat: a room of its own, with the message
     * translated for the people answering, who reply by e-mail.
     *
     * @param  array{agent_id: int, user_id: int, name: string, email: string, language: string, page: string, entry: array}  $data
     */
    public function leave(array $data, string $text): int
    {
        $text = self::clean($text);
        $id   = $this->open(['status' => 'message'] + $data)['id'];
        $room = $this->get($id);

        // Kept at once (the visitor waits for nothing); translated afterwards, as a chat's messages,
        // unless it surely is in the language of the people answering already
        $found = LanguageGuess::detect($text);

        if ($found !== '' && $found !== 'zh' && !Translator::same($found, (string) $room->visitor_language)) {
            $room->visitor_language = $found;
            $this->update($id, ['visitor_language' => mb_substr($found, 0, 10)]);
        }

        $language = $found !== '' && $found !== 'zh' ? $found : (string) $room->visitor_language;

        // The people answering are told by e-mail too, after the message is kept (and translated)
        $alerts = new Alerts($this->db, $this->settings);

        if (!$this->settings->translates() || LanguageGuess::already($text, (string) $room->staff_language)) {
            $this->add($room, 'visitor', 0, $text, $language);
            $alerts->later($id, 'left');

            return $id;
        }

        $this->later($this->add($room, 'visitor', 0, $text, $found !== 'zh' ? $found : '', '', '', 0, 0, 1));
        $alerts->later($id, 'left');

        return $id;
    }

    /**
     * The room of a key, when the visitor may use it: a signed-in visitor's room only for them.
     */
    public function byKey(string $key): ?object
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $key)) {
            return null;
        }

        $db    = $this->db;
        $token = hash('sha256', $key);
        $room  = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))->where('token = :token')->bind(':token', $token)
        )->loadObject();

        if (!$room) {
            return null;
        }

        $user = Factory::getApplication()->getIdentity();

        if ((int) $room->user_id > 0 && (!$user || (int) $user->id !== (int) $room->user_id)) {
            return null;
        }

        return $room;
    }

    public function get(int $id): ?object
    {
        $db = $this->db;

        return $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadObject() ?: null;
    }

    // ---------- The visitor's side ----------

    /**
     * What is new for the visitor after a message: messages in their language, how the chat
     * stands and who answers. A chat whose visitor is gone too long is closed first.
     *
     * @return  array{status: string, staff: string, messages: array<int, array<string, mixed>>}
     */
    public function forVisitor(object $room, int $after): array
    {
        $this->seen($room);

        [$messages, $staff, $profiles] = $this->visitorItems($room, $this->rows((int) $room->id, $after));

        return [
            'rating'   => $room->status === 'closed' ? $this->ratingFor($room) : null,
            'typing'   => $room->status === 'active' && self::lately($room->staff_typing ?? null),
            'status'   => $room->status === 'message' ? 'closed' : (string) $room->status,
            'staff'    => (int) $room->staff_id ? ($staff[(int) $room->staff_id] ?? '') : '',
            'face'     => (int) $room->staff_id ? ($profiles[(int) $room->staff_id]['face'] ?? null) : null,
            'messages' => $messages,
        ];
    }

    /**
     * Messages as the visitor's page shows them.
     *
     * @param   object[]  $rows
     *
     * @return  array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, array<string, mixed>>}  The messages, the names and the profiles of the people answering
     */
    private function visitorItems(object $room, array $rows): array
    {
        $profiles = (new Staff($this->db, $this->settings))->profiles([(int) $room->staff_id, ...array_column($rows, 'staff_id')]);
        $staff    = array_map(static fn ($profile) => $profile['name'], $profiles);
        $messages = [];
        $files    = $this->filesOf($rows);
        $stickers = $this->stickersOf($rows);

        foreach ($rows as $row) {
            $item = ['id' => (int) $row->id, 'author' => (string) $row->author, 'time' => self::time((string) $row->created)];

            if (isset($stickers[(int) $row->id])) {
                $item['sticker'] = $stickers[(int) $row->id];
                $item['name']    = $row->author === 'staff' ? ($staff[(int) $row->staff_id] ?? '') : '';
                $item['face']    = $row->author === 'staff' ? ($profiles[(int) $row->staff_id]['face'] ?? null) : null;
                $item['text']    = '';
                $messages[]      = $item;

                continue;
            }

            if (isset($files[(int) $row->id])) {
                $item['file'] = $files[(int) $row->id];
                $item['name'] = $row->author === 'staff' ? ($staff[(int) $row->staff_id] ?? '') : '';
                $item['face'] = $row->author === 'staff' ? ($profiles[(int) $row->staff_id]['face'] ?? null) : null;
                $item['text'] = '';
                $messages[]   = $item;

                continue;
            }

            if ($row->author === 'system') {
                $item['text'] = $this->systemForVisitor($room, $row, $staff);
            } elseif ($row->author === 'staff') {
                $item['name']     = $staff[(int) $row->staff_id] ?? '';
                $item['face']     = $profiles[(int) $row->staff_id]['face'] ?? null;
                $item['text']     = $row->translation !== '' ? (string) $row->translation : (string) $row->text;
                $item['original'] = $row->translation !== '' ? (string) $row->text : '';
                $item['links']    = self::cardsFor($row, true);

                // Still being translated: the words as written, for the page to hold back (or to
                // translate itself for the moment) until the translation comes with an update
                if ($this->translating($row)) {
                    $item += ['translating' => true, 'from' => (string) $room->staff_language, 'to' => (string) $room->visitor_language];

                    // The AI slow: a quick machine translation for the moment
                    if (trim((string) ($row->draft ?? '')) !== '') {
                        $item['draft'] = (string) $row->draft;
                    }
                }
            } else {
                $item['text'] = (string) $row->text;
            }

            $messages[] = $item;
        }

        return [$messages, $staff, $profiles];
    }

    /**
     * The page the visitor is on now (their window goes with them from page to page): kept as the
     * chat's page, and added to the pages they went through (the last 12).
     */
    public function visiting(object $room, string $url, string $title): void
    {
        if ($url === '' || $url === (string) $room->page || !\in_array($room->status, ['waiting', 'active'], true)) {
            return;
        }

        $pages = self::pagesOf($room);

        if (!$pages && (string) $room->page !== '') {
            $pages[] = ['url' => (string) $room->page, 'title' => '', 'at' => self::time((string) $room->created)];
        }

        $pages[] = ['url' => mb_substr($url, 0, 500), 'title' => mb_substr(trim($title), 0, 150), 'at' => self::time(Factory::getDate()->toSql())];
        $pages   = \array_slice($pages, -12);

        $room->page  = mb_substr($url, 0, 500);
        $room->pages = json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->update((int) $room->id, ['page' => $room->page, 'pages' => $room->pages], false);
    }

    /**
     * @return  array<int, array{url: string, title: string, at: string}>
     */
    private static function pagesOf(object $room): array
    {
        $pages = json_decode((string) ($room->pages ?? ''), true);

        return \is_array($pages) ? array_values(array_filter($pages, static fn ($page) => \is_array($page) && \is_string($page['url'] ?? null) && preg_match('#^https?://#i', $page['url']))) : [];
    }

    /**
     * A message from the visitor, translated for the people answering.
     *
     * @return  int  Its ID
     *
     * @throws  \RuntimeException  With words for the visitor, when it cannot be sent
     */
    public function fromVisitor(object $room, string $text): int
    {
        $text = self::clean($text);

        if ($text === '' || !\in_array($room->status, ['waiting', 'active'], true)) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_CHAT_CLOSED'));
        }

        $this->allowed($room);
        $this->update((int) $room->id, ['visitor_typing' => null], false);

        // Kept at once, in the order written; translated afterwards unless it surely needs no translating
        // (the language the person answering reads, told by its own letters)
        $found = LanguageGuess::detect($text);

        if ($found !== '' && $found !== 'zh' && !Translator::same($found, (string) $room->visitor_language)) {
            $room->visitor_language = $found;
            $this->update((int) $room->id, ['visitor_language' => mb_substr($found, 0, 10)]);
        }

        if (!$this->settings->translates() || LanguageGuess::already($text, (string) $room->staff_language)) {
            return $this->add($room, 'visitor', 0, $text, $found !== '' && $found !== 'zh' ? $found : (string) $room->visitor_language);
        }

        $id = $this->add($room, 'visitor', 0, $text, $found !== 'zh' ? $found : '', '', '', 0, 0, 1);
        $this->later($id);

        return $id;
    }

    /**
     * What the visitor is asked when the chat ends: the form, and the face of who answered them (to
     * bow and thank them). Null when visitors are not asked, the chat was rated already, or no one
     * answered it.
     *
     * @return  array<string, mixed>|null
     */
    public function ratingFor(object $room): ?array
    {
        $form = $this->settings->ratingForm();

        if (!$form || !(int) $room->staff_id || $this->rated((int) $room->id)) {
            return null;
        }

        $profile = (new Staff($this->db, $this->settings))->profiles([(int) $room->staff_id])[(int) $room->staff_id] ?? null;

        $texts = [];

        foreach (['submit', 'skip', 'thanks', 'comment', 'good', 'bad', 'by', 'empty'] as $word) {
            $texts[$word] = Text::_('COM_ASKDESK_RATING_' . strtoupper($word));
        }

        $texts['by'] = Text::sprintf('COM_ASKDESK_RATING_BY', $profile['name'] ?? '');

        return $form + ['staff' => $profile['name'] ?? '', 'face' => $profile['face'] ?? null, 'texts' => $texts];
    }

    /**
     * The visitor's rating of the chat, once, after it ended (or as they end it).
     *
     * @param  array<string, mixed>  $values  By key of the form
     *
     * @throws  \RuntimeException
     */
    public function rate(object $room, array $values, string $comment): void
    {
        $form = $this->settings->ratingForm();

        if (!$form || !(int) $room->staff_id || $this->rated((int) $room->id)) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_RATING_CLOSED'));
        }

        $kept = [];

        foreach ($form['dimensions'] as $dimension) {
            $value = $values[$dimension['key']] ?? null;
            $value = match ($dimension['type']) {
                'stars'  => is_numeric($value) && (int) $value >= 1 && (int) $value <= 5 ? (int) $value : null,
                'thumbs' => \in_array((string) $value, ['1', '-1'], true) ? (int) $value : null,
                'scale'  => is_numeric($value) && (int) $value >= 0 && (int) $value <= 10 ? (int) $value : null,
                'choice' => \in_array((string) $value, $dimension['options'], true) ? (string) $value : null,
                default  => null,
            };

            if ($value === null && $dimension['required']) {
                throw new \RuntimeException(Text::sprintf('COM_ASKDESK_RATING_MISSING', $dimension['label']));
            }

            if ($value !== null) {
                $kept[$dimension['key']] = ['label' => $dimension['label'], 'type' => $dimension['type'], 'value' => $value];
            }
        }

        if (!$kept) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_RATING_EMPTY'));
        }

        $row = (object) [
            'room_id'  => (int) $room->id,
            'staff_id' => (int) $room->staff_id,
            'agent_id' => (int) $room->agent_id,
            'member'   => (int) ((int) $room->user_id > 0),
            'language' => mb_substr((string) $room->visitor_language, 0, 10),
            'ratings'  => json_encode($kept, JSON_UNESCAPED_UNICODE),
            'comment'  => $form['comment'] ? mb_substr(trim(self::clean($comment)), 0, 1000) : '',
            'created'  => Factory::getDate()->toSql(),
        ];

        $this->db->insertObject('#__askdesk_ratings', $row);
    }

    /**
     * How a chat was rated, for the people answering.
     *
     * @return  array{ratings: array<string, array{label: string, type: string, value: int|string}>, comment: string, time: string}|null
     */
    public function rating(int $roomId): ?array
    {
        $db  = $this->db;
        $row = $db->setQuery($db->getQuery(true)->select('ratings, comment, created')->from($db->quoteName('#__askdesk_ratings'))->where('room_id = :room')->bind(':room', $roomId, ParameterType::INTEGER))->loadObject();

        return $row ? ['ratings' => (array) json_decode((string) $row->ratings, true), 'comment' => (string) $row->comment, 'time' => self::time((string) $row->created)] : null;
    }

    /**
     * The ratings of the last days, for the dashboard: per thing rated, the average (stars and
     * scales), the share of thumbs up, or the count of each choice; how many ratings; the last comments.
     *
     * @return  array{count: int, dimensions: array<string, array<string, mixed>>, comments: array<int, array{comment: string, time: string, staff: string}>}
     */
    public function ratingStats(int $days = 30): array
    {
        $db    = $this->db;
        $since = Factory::getDate('-' . max(1, $days) . ' days')->toSql();
        $rows  = $db->setQuery(
            $db->getQuery(true)->select('staff_id, ratings, comment, created')->from($db->quoteName('#__askdesk_ratings'))
                ->where('created >= :since')->bind(':since', $since)->order('id DESC'),
            0,
            2000
        )->loadObjectList();

        $dimensions = [];
        $comments   = [];

        foreach ($rows as $row) {
            foreach ((array) json_decode((string) $row->ratings, true) as $key => $rated) {
                $entry = $dimensions[$key] ?? ['label' => (string) ($rated['label'] ?? $key), 'type' => (string) ($rated['type'] ?? ''), 'n' => 0, 'sum' => 0, 'choices' => []];
                $entry['n']++;

                if ($entry['type'] === 'choice') {
                    $entry['choices'][(string) $rated['value']] = ($entry['choices'][(string) $rated['value']] ?? 0) + 1;
                } else {
                    $entry['sum'] += $entry['type'] === 'thumbs' ? ((int) $rated['value'] > 0 ? 1 : 0) : (int) $rated['value'];
                }

                $dimensions[$key] = $entry;
            }

            if (trim((string) $row->comment) !== '' && \count($comments) < 8) {
                $comments[] = ['comment' => (string) $row->comment, 'time' => self::time((string) $row->created), 'staff' => (int) $row->staff_id];
            }
        }

        $names = (new Staff($db, $this->settings))->names(array_column($comments, 'staff'));

        foreach ($comments as &$comment) {
            $comment['staff'] = $names[$comment['staff']] ?? '';
        }

        unset($comment);

        return ['count' => \count($rows), 'dimensions' => $dimensions, 'comments' => $comments];
    }

    private function rated(int $roomId): bool
    {
        $db = $this->db;

        return (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__askdesk_ratings'))->where('room_id = :room')->bind(':room', $roomId, ParameterType::INTEGER))->loadResult() > 0;
    }

    /**
     * The visitor ended the chat.
     */
    public function visitorLeft(object $room): void
    {
        if (\in_array($room->status, ['waiting', 'active'], true)) {
            $this->add($room, 'system', 0, 'visitor_left');
            $this->close($room);
        }
    }

    // ---------- The side of the people answering ----------

    /** The lists of the people answering, by tab */
    public const LISTS = ['all', 'awaiting', 'unhandled', 'settled'];

    /** Kept to the chats of this person and those no one has taken (0: all), as the settings say */
    private int $only = 0;

    /** Lists and chats kept to this person's and those no one has taken (they may not see others') */
    public function restrictTo(int $staffId): self
    {
        $this->only = max(0, $staffId);

        return $this;
    }

    /** Whether the one these rooms are for may see this chat */
    public function visible(object $room): bool
    {
        return !$this->only || \in_array((int) $room->staff_id, [0, $this->only], true);
    }

    /** How a list may be ordered: latest moved first, longest untouched first, latest started first */
    public const SORTS = ['recent', 'oldest', 'created'];

    /**
     * The chats to show the people answering, a list (a tab) at a time and its first so many:
     *  - all: what is not closed (結案) yet: waiting, answered, messages left, chats that ended with the
     *    visitor's words unanswered; and this person's own chats closed in the last hours
     *  - awaiting: those whose visitor waits for an answer (or for someone to take the chat)
     *  - unhandled: messages left and chats that ended unanswered, until someone closes them
     *  - settled: those closed, latest first
     *  - everything: all with those closed too (all's switch)
     * Ordered as asked (SORTS), else as each list is. With how many each list has, whether this one has more, and what goes on now (waiting, answered,
     * messages left: what rings, whatever the tab).
     *
     * @return  array{rooms: array<int, array<string, mixed>>, counts: array<string, int>, more: bool, live: array<int, array<string, mixed>>}
     */
    public function inbox(int $staffId, string $list = 'all', int $limit = 30, string $sort = ''): array
    {
        $list   = \in_array($list, [...self::LISTS, 'everything'], true) ? $list : 'all';
        $limit  = max(10, min(500, $limit));
        $counts = $this->counts($staffId);
        $rows = $this->db->setQuery($this->listQuery($list, $staffId, $sort), 0, $limit)->loadObjectList();
        $live = $list === 'all' ? array_values(array_filter($rows, static fn ($row) => $row->status !== 'closed'))
            : $this->db->setQuery($this->listQuery('live', $staffId), 0, 200)->loadObjectList();

        return [
            'rooms'  => $this->listed($rows),
            'counts' => $counts,
            'more'   => $counts[$list] > \count($rows),
            'live'   => $this->listed($live),
        ];
    }

    /**
     * How many each list has, asked with each poll: three queries each on an index (0.6.1), not one a list
     * each scanning the rooms. The lists overlap: awaiting and unhandled are within all, which is what is not
     * closed yet (open, or closed with no one having closed the case) and this person's own closed lately
     *
     * @return  array<string, int>
     */
    private function counts(int $staffId): array
    {
        $db    = $this->db;
        $q     = static fn (string $s) => $db->quote($s);
        $open  = 'r.status IN (' . $q('waiting') . ', ' . $q('active') . ', ' . $q('message') . ')';
        $left  = '(r.status = ' . $q('closed') . ' AND r.settled IS NULL)';
        $only  = $this->only;
        $mine  = static fn ($query) => $only ? $query->where('r.staff_id IN (0, ' . (int) $only . ')') : $query;
        $rooms = $db->quoteName('#__askdesk_rooms', 'r');

        // Not closed yet (on idx_open), with how many of them wait for an answer or were left unanswered
        $row = $db->setQuery($mine($db->getQuery(true)
            ->select('COUNT(*) AS n')
            ->select('SUM(CASE WHEN ' . $open . ' AND (r.status = ' . $q('waiting') . ' OR r.last_author = ' . $q('visitor') . ') THEN 1 ELSE 0 END) AS awaiting')
            ->select('SUM(CASE WHEN r.status = ' . $q('message') . ' OR ' . $left . ' THEN 1 ELSE 0 END) AS unhandled')
            ->from($rooms)->where('(' . $open . ' OR ' . $left . ')')))->loadObject();

        // This person's own, closed lately and the case closed too (those not closed are counted above)
        $since = Factory::getDate('-6 hours')->toSql();
        $own   = (int) $db->setQuery($mine($db->getQuery(true)->select('COUNT(*)')->from($rooms)
            ->where('r.staff_id = :me')->where('r.status = ' . $q('closed'))->where('r.settled IS NOT NULL')->where('r.closed >= :since')
            ->bind(':me', $staffId, ParameterType::INTEGER)->bind(':since', $since)))->loadResult();

        // Closed cases (on idx_settled)
        $settled = (int) $db->setQuery($mine($db->getQuery(true)->select('COUNT(*)')->from($rooms)->where('r.settled IS NOT NULL')))->loadResult();

        $counts = [
            'all'       => (int) ($row->n ?? 0) + $own,
            'awaiting'  => (int) ($row->awaiting ?? 0),
            'unhandled' => (int) ($row->unhandled ?? 0),
            'settled'   => $settled,
        ];
        $counts['everything'] = $counts['all'] + $counts['settled'];

        return $counts;
    }

    /**
     * A page of a list for the dashboard (its cases widget): by words (the visitor's name, company,
     * address), only this person's when asked; with how many there are.
     *
     * @return  array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function listPage(int $staffId, string $list, int $offset, int $size, string $words = '', bool $mine = false, string $from = '', string $to = '', int $person = 0): array
    {
        $list  = \in_array($list, self::LISTS, true) ? $list : 'all';
        $query = $this->listQuery($list, $staffId);
        $words = trim($words);

        if ($words !== '') {
            $like = '%' . $this->db->escape($words, true) . '%';
            $query->where('(r.name LIKE :w1 OR r.company LIKE :w2 OR r.email LIKE :w3)')->bind(':w1', $like)->bind(':w2', $like)->bind(':w3', $like);
        }

        // Only this person's, or another's (theirs: taken by them)
        $who = $mine ? $staffId : $person;

        if ($who > 0) {
            $query->where('r.staff_id = :who')->bind(':who', $who, ParameterType::INTEGER);
        }

        // Started in the days asked (the site's days, kept in UTC)
        $zone = new \DateTimeZone((string) Factory::getApplication()->get('offset', 'UTC') ?: 'UTC');

        if ($from !== '') {
            $start = (new \DateTime($from . ' 00:00:00', $zone))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $query->where('r.created >= :from')->bind(':from', $start);
        }

        if ($to !== '') {
            $end = (new \DateTime($to . ' 23:59:59', $zone))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $query->where('r.created <= :to')->bind(':to', $end);
        }

        $count = (clone $query)->clear('select')->clear('order')->select('COUNT(*)');

        return [
            'rows'  => $this->listed($this->db->setQuery($query, max(0, $offset), max(1, min(100, $size)))->loadObjectList()),
            'total' => (int) $this->db->setQuery($count)->loadResult(),
        ];
    }

    /** The query of a list (as inbox() says), its rows newest first ('awaiting': longest waiting first) */
    private function listQuery(string $list, int $staffId, string $sort = ''): \Joomla\Database\DatabaseQuery
    {
        $db    = $this->db;
        $q     = static fn (string $s) => $db->quote($s);
        $open  = 'r.status IN (' . $q('waiting') . ', ' . $q('active') . ', ' . $q('message') . ')';
        $left  = '(r.status = ' . $q('closed') . ' AND r.settled IS NULL)';
        $query = $db->getQuery(true)
            ->select('r.id, r.status, r.staff_id, r.user_id, r.name, r.email, r.company, r.page, r.page_language, r.visitor_language, r.last_message, r.last_author, r.staff_seen, r.created, r.modified, r.closed, r.settled, r.settled_by')
            ->from($db->quoteName('#__askdesk_rooms', 'r'));

        switch ($list) {
            case 'live':
                $query->where($open)->order('r.modified DESC');
                break;
            case 'awaiting':
                $query->where($open)->where('(r.status = ' . $q('waiting') . ' OR r.last_author = ' . $q('visitor') . ')')->order('r.modified ASC');
                break;
            case 'unhandled':
                $query->where('(r.status = ' . $q('message') . ' OR ' . $left . ')')->order('r.modified DESC');
                break;
            case 'settled':
                $query->where('r.settled IS NOT NULL')->order('r.settled DESC');
                break;
            case 'everything':
                // Not closed (as all), or closed: every chat kept, those of this person's lately aside
                $query->where('(' . $open . ' OR ' . $left . ' OR r.settled IS NOT NULL)')->order('r.modified DESC');
                break;
            default:
                $since = Factory::getDate('-6 hours')->toSql();
                $query->where('(' . $open . ' OR ' . $left . ' OR (r.status = ' . $q('closed') . ' AND r.staff_id = :me AND r.closed >= :since))')
                    ->bind(':me', $staffId, ParameterType::INTEGER)->bind(':since', $since)->order('r.modified DESC');
        }

        // Only one's own and no one's, when others' may not be seen
        if ($this->only) {
            $only = $this->only;
            $query->where('r.staff_id IN (0, :only)')->bind(':only', $only, ParameterType::INTEGER);
        }

        // Ordered as asked instead (the id last, so pages do not overlap)
        $by = ['recent' => 'r.modified DESC', 'oldest' => 'r.modified ASC', 'created' => 'r.created DESC'][$sort] ?? null;

        if ($by !== null && $list !== 'live') {
            $query->clear('order')->order($by . ', r.id DESC');
        }

        return $query;
    }

    /**
     * Chats as the list shows them: who, what was said last, how many unread, whether the visitor waits
     * for an answer, whether it is closed (結案) or waits to be.
     *
     * @param   object[]  $rows
     *
     * @return  array<int, array<string, mixed>>
     */
    private function listed(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $db    = $this->db;
        $ids   = array_map(static fn ($row) => (int) $row->id, $rows);
        $names = (new Staff($db, $this->settings))->names(array_merge(array_column($rows, 'staff_id'), array_column($rows, 'settled_by')));

        // When the visitor or the one answering last wrote (the desk's own notes aside)
        $said = [];

        foreach ($db->setQuery(
            $db->getQuery(true)->select('m.room_id, m.author, m.created')->from($db->quoteName('#__askdesk_messages', 'm'))
                ->whereIn('m.id', $this->ends($ids, 'MAX', 'author <> ' . $db->quote('system')) ?: [0])
        )->loadObjectList() as $row) {
            $said[(int) $row->room_id] = $row;
        }

        // The last message of each, and how many from the visitor are not read
        $last = [];

        foreach ($db->setQuery(
            $db->getQuery(true)->select('m.room_id, m.author, m.text, m.translation')->from($db->quoteName('#__askdesk_messages', 'm'))
                ->whereIn('m.id', array_values(array_filter(array_map(static fn ($row) => (int) $row->last_message, $rows))) ?: [0])
        )->loadObjectList() as $row) {
            $last[(int) $row->room_id] = $row;
        }

        $unread = [];

        foreach ($db->setQuery(
            $db->getQuery(true)->select('m.room_id, COUNT(*) AS n')->from($db->quoteName('#__askdesk_messages', 'm'))
                ->join('INNER', $db->quoteName('#__askdesk_rooms', 'r'), 'r.id = m.room_id')
                ->whereIn('m.room_id', $ids)->where('m.author = ' . $db->quote('visitor'))->where('m.id > r.staff_seen')->group('m.room_id')
        )->loadObjectList() as $row) {
            $unread[(int) $row->room_id] = (int) $row->n;
        }

        return array_map(function ($row) use ($last, $unread, $names, $said) {
            $message = $last[(int) $row->id] ?? null;
            $preview = !$message ? '' : ($message->author === 'system' ? $this->systemText((string) $message->text, '') : ($message->author === 'visitor' && $message->translation !== '' ? $message->translation : $message->text));
            $closed  = $row->status === 'closed';
            $visitor = (string) $row->last_author === 'visitor';

            return [
                'id'        => (int) $row->id,
                'status'    => (string) $row->status,
                'staff'     => (int) $row->staff_id,
                'staffName' => $names[(int) $row->staff_id] ?? '',
                'member'    => (int) $row->user_id > 0,
                'name'      => (string) $row->name,
                'email'     => (string) $row->email,
                'company'   => (string) $row->company,
                'page'      => (string) $row->page,
                'language'  => (string) $row->visitor_language,
                'preview'   => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $preview)), 0, 120),
                'unread'    => $unread[(int) $row->id] ?? 0,
                // The visitor wrote last and no one answered them yet; or waits for someone to take the chat
                'awaiting'  => !$closed && ($row->status === 'waiting' || $visitor),
                // Ended with the visitor's words unanswered, or a message left: someone is to deal with it and close it
                'unhandled' => $row->status === 'message' || ($closed && $row->settled === null),
                'settled'   => $row->settled !== null ? self::time((string) $row->settled) : null,
                'settledBy' => $names[(int) $row->settled_by] ?? '',
                'since'     => isset($said[(int) $row->id]) ? self::time((string) $said[(int) $row->id]->created) : self::time((string) $row->created),
                'created'   => self::time((string) $row->created),
                'modified'  => self::time((string) $row->modified),
                'closed'    => $row->closed !== null ? self::time((string) $row->closed) : null,
            ];
        }, $rows);
    }

    /**
     * One chat for the people answering: what the visitor did before (the AI conversation, the page)
     * and the messages after one, each in the language of the person reading.
     *
     * @return  array<string, mixed>
     */
    public function forStaff(object $room, int $after, int $staffId, string $language): array
    {
        $rows = $this->rows((int) $room->id, $after);

        [$items, $names] = $this->staffItems($room, $rows, $staffId, $language);

        // Read up to here, when it is this person's chat or no one's yet
        $top = $rows ? (int) end($rows)->id : 0;

        if ($top > (int) $room->staff_seen && \in_array((int) $room->staff_id, [0, $staffId], true)) {
            $this->update((int) $room->id, ['staff_seen' => $top], false);
        }

        $result = [
            'id'       => (int) $room->id,
            'typing'   => \in_array($room->status, ['waiting', 'active'], true) && self::lately($room->visitor_typing ?? null),
            'rating'   => $room->status === 'closed' ? $this->rating((int) $room->id) : null,
            'status'   => (string) $room->status,
            'staff'    => (int) $room->staff_id,
            'staffName' => $names[(int) $room->staff_id] ?? '',
            'messages' => $items,
            // Closed (結案), or still to be dealt with and closed
            'settled'   => ($room->settled ?? null) !== null ? self::time((string) $room->settled) : null,
            'unhandled' => $room->status === 'message' || ($room->status === 'closed' && ($room->settled ?? null) === null),
        ];

        // Where the visitor is now, and the pages they went through during the chat (they move on)
        $result += ['page' => (string) $room->page, 'pages' => self::pagesOf($room)];

        // Whether the guest is who they say (confirmed by the person answering, 0.6.2): with each look, it may change
        $result['confirmed'] = self::trusted($room);

        // What the visitor did before, once, with the first messages
        if ($after === 0) {
            $entry = json_decode((string) $room->entry, true);
            $result += [
                'name'     => (string) $room->name,
                'email'    => (string) $room->email,
                'company'  => (string) $room->company,
                'member'   => (int) $room->user_id > 0,
                'page'     => (string) $room->page,
                'language' => (string) $room->visitor_language,
                'created'  => self::time((string) $room->created),
                'entry'    => \is_array($entry) ? $entry : [],
            ];
        }

        return $result;
    }

    /**
     * Whether a chat's visitor is who their e-mail says (0.6.2): a member signed in, or a guest the person
     * answering asked and confirmed. Until then, what is known elsewhere of someone with that e-mail (a CRM's
     * customer, their records, their other chats) is not shown as theirs: anyone can type an address.
     */
    public static function trusted(object $room): bool
    {
        return (int) ($room->user_id ?? 0) > 0 || !empty($room->identity_confirmed);
    }

    /** A guest's identity confirmed (asked first) by the person answering, or the confirming taken back */
    public function confirmIdentity(object $room, int $staffId, bool $confirmed): void
    {
        if ((int) $room->user_id > 0) {
            return;
        }

        $this->update((int) $room->id, $confirmed ? ['identity_confirmed' => Factory::getDate()->toSql(), 'identity_by' => $staffId] : ['identity_confirmed' => null, 'identity_by' => 0], false);
    }

    /**
     * Someone takes a waiting chat (or one left as a message): only one of several people trying at once gets it.
     */
    public function take(object $room, int $staffId, string $language): bool
    {
        $db    = $this->db;
        $now   = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__askdesk_rooms'))
            ->set('staff_id = :staff')->set('staff_language = :language')->set('modified = :now')
            ->set('status = CASE WHEN status = ' . $db->quote('message') . ' THEN status ELSE ' . $db->quote('active') . ' END')
            ->where('id = :id')->where('staff_id = 0')
            ->where($db->quoteName('status') . ' IN (' . $db->quote('waiting') . ', ' . $db->quote('message') . ')')
            ->bind(':staff', $staffId, ParameterType::INTEGER)
            ->bind(':language', $language)
            ->bind(':now', $now)
            ->bind(':id', $room->id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() < 1) {
            return false;
        }

        $room = $this->get((int) $room->id);

        if ($room->status === 'active') {
            $this->add($room, 'system', $staffId, 'joined');
        }

        $this->handled($room, $staffId);

        return true;
    }

    /**
     * Who took a chat or dealt with a message, and how long the visitor waited for it: kept apart
     * from the chat (guests' chats go), with nothing of the visitor, for each person's numbers.
     */
    private function handled(object $room, int $staffId): void
    {
        $db   = $this->db;
        $id   = (int) $room->id;
        $done = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__askdesk_handled'))->where('room_id = :room')->bind(':room', $id, ParameterType::INTEGER))->loadResult();

        if ($done || $staffId < 1) {
            return;
        }

        $row = (object) [
            'room_id'  => $id,
            'staff_id' => $staffId,
            'kind'     => $room->status === 'message' ? 'message' : 'chat',
            'member'   => (int) ((int) $room->user_id > 0),
            'waited'   => max(0, time() - (int) strtotime($room->created . ' UTC')),
            'created'  => Factory::getDate()->toSql(),
        ];

        try {
            $db->insertObject('#__askdesk_handled', $row);
        } catch (\Throwable) {
            // Taken twice at once: once is enough
        }
    }

    /**
     * Each person's numbers over the last days: chats taken, messages dealt with, how long visitors
     * waited for them on average, and how they were rated (the average of stars, of 0-10, the share
     * of thumbs up).
     *
     * @return  array<int, array{chats: int, messages: int, waited: int, ratings: int, stars: ?float, scale: ?float, thumbs: ?int}>
     */
    public function staffStats(int $days = 30): array
    {
        $db    = $this->db;
        $since = Factory::getDate('-' . max(1, $days) . ' days')->toSql();
        $stats = [];
        $blank = ['chats' => 0, 'messages' => 0, 'waited' => 0, 'ratings' => 0, 'stars' => null, 'scale' => null, 'thumbs' => null];

        foreach ($db->setQuery(
            $db->getQuery(true)->select('staff_id, kind, COUNT(*) AS n, AVG(waited) AS waited')->from($db->quoteName('#__askdesk_handled'))
                ->where('created >= :since')->bind(':since', $since)->group('staff_id, kind')
        )->loadObjectList() as $row) {
            $entry = $stats[(int) $row->staff_id] ?? $blank;
            $entry[$row->kind === 'message' ? 'messages' : 'chats'] = (int) $row->n;

            if ($row->kind !== 'message') {
                $entry['waited'] = (int) round((float) $row->waited);
            }

            $stats[(int) $row->staff_id] = $entry;
        }

        $sums = [];

        foreach ($db->setQuery(
            $db->getQuery(true)->select('staff_id, ratings')->from($db->quoteName('#__askdesk_ratings'))->where('created >= :since')->bind(':since', $since)
        )->loadObjectList() as $row) {
            $staff = (int) $row->staff_id;
            $sum   = $sums[$staff] ?? ['n' => 0, 'stars' => [0, 0], 'scale' => [0, 0], 'thumbs' => [0, 0]];
            $sum['n']++;

            foreach ((array) json_decode((string) $row->ratings, true) as $rated) {
                $type = (string) ($rated['type'] ?? '');

                if (isset($sum[$type])) {
                    $sum[$type][0] += $type === 'thumbs' ? ((int) $rated['value'] > 0 ? 1 : 0) : (int) $rated['value'];
                    $sum[$type][1]++;
                }
            }

            $sums[$staff] = $sum;
        }

        foreach ($sums as $staff => $sum) {
            $entry            = $stats[$staff] ?? $blank;
            $entry['ratings'] = $sum['n'];
            $entry['stars']   = $sum['stars'][1] ? round($sum['stars'][0] / $sum['stars'][1], 1) : null;
            $entry['scale']   = $sum['scale'][1] ? round($sum['scale'][0] / $sum['scale'][1], 1) : null;
            $entry['thumbs']  = $sum['thumbs'][1] ? (int) round($sum['thumbs'][0] / $sum['thumbs'][1] * 100) : null;
            $stats[$staff]    = $entry;
        }

        return $stats;
    }

    /**
     * A message from the person answering, translated for the visitor.
     *
     * @return  array{id: int, translated: bool, failed: bool}
     *
     * @throws  \RuntimeException
     */
    public function fromStaff(object $room, int $staffId, string $text): array
    {
        $links = new Links($this->db);
        $text  = $links->shorten(self::clean($text));

        if ($text === '' || $room->status !== 'active' || (int) $room->staff_id !== $staffId) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_STAFF_NOT_YOURS'));
        }

        $this->update((int) $room->id, ['staff_typing' => null], false);

        // Kept at once, in the order written; translated for the visitor afterwards, unless the visitor
        // surely reads it as it is
        $pending = $this->settings->translates() && !LanguageGuess::already($text, (string) $room->visitor_language);
        $id      = $this->add($room, 'staff', $staffId, $text, (string) $room->staff_language, '', '', 0, 0, $pending ? 1 : 0);

        $this->keepCards($room, $id, $links->cards($text), false);

        if ($pending) {
            $this->later($id);
        }

        return ['id' => $id, 'translated' => false, 'failed' => false, 'translating' => $pending];
    }

    /**
     * Messages translated once the request that brought them has its answer (the page waits for
     * nothing); where the server cannot answer first, while it does.
     */
    private function later(int $id): void
    {
        static $queue = null;

        if ($queue === null) {
            $queue = [];

            register_shutdown_function(function () use (&$queue): void {
                if (\function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                ignore_user_abort(true);
                @set_time_limit(300);

                foreach ($queue as $each) {
                    try {
                        $this->translateNow($each);
                    } catch (\Throwable) {
                        // Shown as written once its time is up
                    }
                }
            });
        }

        $queue[] = $id;
    }

    /**
     * A message kept untranslated, translated now: the visitor's into the language of the person
     * answering (its own language noted), theirs into the visitor's, with its article cards.
     */
    public function translateNow(int $id): void
    {
        $db  = $this->db;
        $row = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_messages'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER))->loadObject();

        if (!$row || (int) $row->translating !== 1 || !($room = $this->get((int) $row->room_id))) {
            return;
        }

        $visitor = $row->author === 'visitor';
        $target  = $visitor ? (string) $room->staff_language : (string) $room->visitor_language;
        $done    = (object) ['id' => $id, 'translating' => 0];

        try {
            $translation = $this->translate((string) $row->text, $target, $room, true);

            if ($translation['translated']) {
                $done->translation          = $translation['text'];
                $done->translation_language = mb_substr($target, 0, 10);
            }

            if ($visitor && $translation['language'] !== '') {
                $done->language = mb_substr($translation['language'], 0, 10);

                if (!Translator::same($translation['language'], (string) $room->visitor_language)) {
                    $this->update((int) $room->id, ['visitor_language' => mb_substr($translation['language'], 0, 10)], false);
                }
            }
        } catch (\Throwable) {
            // Shown as written: the person answering is told it was not translated
            $done->translating = 2;
        }

        // The articles it links to, their words for the visitor too, before it is shown translated
        if (!$visitor && $done->translating === 0 && isset($done->translation) && $row->links) {
            $this->keepCards($room, $id, (array) json_decode((string) $row->links, true), true);
        }

        $db->updateObject('#__askdesk_messages', $done, 'id');
    }

    /**
     * A visitor's message in another language, for a person who reads that one (not kept).
     *
     * @return  string
     */
    public function translateFor(object $room, int $messageId, string $language): string
    {
        $db  = $this->db;
        $row = $db->setQuery(
            $db->getQuery(true)->select('text')->from($db->quoteName('#__askdesk_messages'))
                ->where('id = :id')->where('room_id = :room')
                ->bind(':id', $messageId, ParameterType::INTEGER)->bind(':room', $room->id, ParameterType::INTEGER)
        )->loadResult();

        if (!\is_string($row)) {
            return '';
        }

        $translation = $this->translate($row, $language, $room);

        return $translation['text'];
    }

    /**
     * A reply to a message left, for the person answering to send by e-mail from their own mail
     * program: kept with the message, and translated into the visitor's language with a subject.
     * Nothing is sent from here.
     *
     * @return  array{id: int, email: string, subject: string, body: string, translated: bool}
     *
     * @throws  \RuntimeException
     */
    public function replyByEmail(object $room, int $staffId, string $text): array
    {
        $text = (new Links($this->db))->shorten(self::clean($text));

        if ($text === '' || $room->status !== 'message' || !\in_array((int) $room->staff_id, [0, $staffId], true) || $room->email === '') {
            throw new \RuntimeException(Text::_('COM_ASKDESK_STAFF_NOT_YOURS'));
        }

        if (!(int) $room->staff_id) {
            $this->update((int) $room->id, ['staff_id' => $staffId, 'staff_language' => (string) $room->staff_language]);
            $room->staff_id = $staffId;
            $this->handled($room, $staffId);
        }

        $subject     = Text::sprintf('COM_ASKDESK_REPLY_SUBJECT', (string) Factory::getApplication()->get('sitename'));
        $translation = ['language' => '', 'text' => $text, 'translated' => false];
        $heading     = ['text' => $subject, 'translated' => false];

        try {
            $translation = $this->translate($text, (string) $room->visitor_language, $room, true);
            $heading     = $this->translate($subject, (string) $room->visitor_language, $room, true);
        } catch (\Throwable) {
            // Written as it was: the person answering sees it is not translated
        }

        $id = $this->add($room, 'staff', $staffId, $text, $translation['language'] ?: (string) $room->staff_language, $translation['translated'] ? $translation['text'] : '', $translation['translated'] ? (string) $room->visitor_language : '');

        return [
            'id'         => $id,
            'email'      => (string) $room->email,
            'subject'    => $heading['translated'] ? $heading['text'] : $subject,
            'body'       => $translation['translated'] ? $translation['text'] : $text,
            'translated' => $translation['translated'],
        ];
    }

    /**
     * Replies to suggest for the visitor's last message (AI Ask's index, judged by its AI): worked
     * out once per message and kept with it.
     *
     * @return  array{for: int, items: array<int, array<string, mixed>>}
     */
    public function suggestions(object $room, string $language): array
    {
        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('id, text, language, translation, translation_language, suggestions')
                ->from($db->quoteName('#__askdesk_messages'))
                // What the visitor wrote: not files or stickers
                ->where('room_id = :room')->where('author = ' . $db->quote('visitor'))->where('file_id = 0')->where('sticker_id = 0')
                ->bind(':room', $room->id, ParameterType::INTEGER)->order('id DESC'),
            0,
            2
        )->loadObjectList();

        if (!$rows || !class_exists('Gss\\Component\\Mcpask\\Administrator\\Ask\\Suggester')) {
            return ['for' => 0, 'items' => []];
        }

        $last  = $rows[0];
        $known = $last->suggestions !== null ? json_decode((string) $last->suggestions, true) : null;

        if (\is_array($known)) {
            return ['for' => (int) $last->id, 'items' => (array) ($known['items'] ?? [])];
        }

        // Searched in the language of the site's pages: the visitor's words, or their translation
        $page  = (string) $room->page_language;
        $words = array_map(static fn ($row) => Translator::same((string) $row->language, $page) || $row->translation === '' || !Translator::same((string) $row->translation_language, $page)
            ? (string) $row->text
            : (string) $row->translation, array_reverse($rows));

        $latest = (string) array_pop($words);
        $items  = (new \Gss\Component\Mcpask\Administrator\Ask\Suggester($db, AskSettings::load()))->suggest($latest, $page, $language, implode("\n", $words));
        $links  = new Links($db);

        // Articles linked short, with their picture
        foreach ($items as &$item) {
            $item['url']   = $links->short((string) $item['url']);
            $item['image'] = $links->cards($item['url'])[0]['image'] ?? '';
        }

        unset($item);
        $keep  = (object) ['id' => (int) $last->id, 'suggestions' => json_encode(['items' => $items, 'language' => $language], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];

        $db->updateObject('#__askdesk_messages', $keep, 'id');

        return ['for' => (int) $last->id, 'items' => $items];
    }

    /**
     * The people answering search the site's pages themselves: pages that answer the question (less
     * strictly than for suggestions), each with a short answer drafted from it, in their language.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function search(string $question, string $language): array
    {
        $question = self::clean($question);

        if (mb_strlen($question) < 2 || !class_exists('Gss\\Component\\Mcpask\\Administrator\\Ask\\Suggester')) {
            return [];
        }

        $items = (new \Gss\Component\Mcpask\Administrator\Ask\Suggester($this->db, AskSettings::load()))->suggest($question, $language, $language, '', 55, 5);
        $links = new Links($this->db);

        foreach ($items as &$item) {
            $item['url']   = $links->short((string) $item['url']);
            $item['image'] = $links->cards($item['url'])[0]['image'] ?? '';
        }

        unset($item);

        return $items;
    }

    /**
     * What a signed-in visitor did before: their other chats here, and the questions they asked
     * the AI (AI Ask's log, as long as it keeps them). Guests have no history.
     *
     * @return  array{member: bool, chats: array<int, array<string, mixed>>, questions: array<int, array<string, mixed>>}
     */
    public function history(object $room): array
    {
        $user = (int) $room->user_id;

        if ($user < 1) {
            return ['member' => false, 'chats' => [], 'questions' => []];
        }

        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('r.id, r.status, r.staff_id, r.created, r.visitor_language, (SELECT COUNT(*) FROM ' . $db->quoteName('#__askdesk_messages') . ' m WHERE m.room_id = r.id AND m.author <> ' . $db->quote('system') . ') AS messages')
                ->from($db->quoteName('#__askdesk_rooms', 'r'))
                ->where('r.user_id = :user')->where('r.id <> :room')
                ->bind(':user', $user, ParameterType::INTEGER)->bind(':room', $room->id, ParameterType::INTEGER)
                ->order('r.id DESC'),
            0,
            20
        )->loadObjectList();

        $first = [];

        if ($rows) {
            foreach ($db->setQuery(
                $db->getQuery(true)->select('m.room_id, m.text, m.translation')->from($db->quoteName('#__askdesk_messages', 'm'))
                    ->whereIn('m.id', $this->ends(array_map(static fn ($row) => (int) $row->id, $rows), 'MIN', 'author = ' . $db->quote('visitor')) ?: [0])
            )->loadObjectList() as $row) {
                $first[(int) $row->room_id] = (string) ($row->translation !== '' ? $row->translation : $row->text);
            }
        }

        $names = (new Staff($db, $this->settings))->names(array_column($rows, 'staff_id'));
        $chats = array_map(static fn ($row) => [
            'id'        => (int) $row->id,
            'status'    => (string) $row->status,
            'staffName' => $names[(int) $row->staff_id] ?? '',
            'time'      => self::time((string) $row->created),
            'messages'  => (int) $row->messages,
            'language'  => (string) $row->visitor_language,
            'preview'   => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $first[(int) $row->id] ?? '')), 0, 120),
        ], $rows);

        $questions = class_exists('Gss\\Component\\Mcpask\\Administrator\\Ask\\VisitorQuestions')
            ? (new \Gss\Component\Mcpask\Administrator\Ask\VisitorQuestions($db, AskSettings::load()))->of($user)
            : [];

        return ['member' => true, 'chats' => $chats, 'questions' => $questions];
    }

    /**
     * Messages as the console shows them.
     *
     * @param   object[]  $rows
     *
     * @return  array{0: array<int, array<string, mixed>>, 1: array<int, string>}  The messages, and the names of the people answering
     */
    private function staffItems(object $room, array $rows, int $staffId, string $language): array
    {
        $profiles = (new Staff($this->db, $this->settings))->profiles([(int) $room->staff_id, ...array_column($rows, 'staff_id')]);
        $names    = array_map(static fn ($profile) => $profile['name'], $profiles);
        $items = [];
        $files = $this->filesOf($rows, true);
        $stickers = $this->stickersOf($rows);

        foreach ($rows as $row) {
            $item = ['id' => (int) $row->id, 'author' => (string) $row->author, 'time' => self::time((string) $row->created), 'language' => (string) $row->language];

            if (isset($stickers[(int) $row->id])) {
                $item['sticker'] = $stickers[(int) $row->id];
                $item['name']    = $row->author === 'staff' ? ($names[(int) $row->staff_id] ?? '') : '';
                $item['face']    = $row->author === 'staff' ? ($profiles[(int) $row->staff_id]['face'] ?? null) : null;
                $item['mine']    = $row->author === 'staff' && (int) $row->staff_id === $staffId;
                $item['text']    = '';
                $items[]         = $item;

                continue;
            }

            if (isset($files[(int) $row->id])) {
                $item['file'] = $files[(int) $row->id];
                $item['name'] = $row->author === 'staff' ? ($names[(int) $row->staff_id] ?? '') : '';
                $item['face'] = $row->author === 'staff' ? ($profiles[(int) $row->staff_id]['face'] ?? null) : null;
                $item['mine'] = $row->author === 'staff' && (int) $row->staff_id === $staffId;
                $item['text'] = '';
                $items[]      = $item;

                continue;
            }

            if ($row->author === 'system') {
                $item['text'] = $this->systemText((string) $row->text, $names[(int) $row->staff_id] ?? '');
            } elseif ($row->author === 'visitor') {
                $item['text']     = $row->translation !== '' ? (string) $row->translation : (string) $row->text;
                $item['original'] = $row->translation !== '' ? (string) $row->text : '';
                // Translated into a language this person does not read: they may ask for theirs
                $item['foreign']  = !Translator::same($row->translation !== '' ? (string) $row->translation_language : (string) $row->language, $language);

                // Still being translated: the words as written meanwhile (the console may translate
                // them itself for the moment), the translation with an update
                if ($this->translating($row)) {
                    $item += ['translating' => true, 'from' => (string) $row->language, 'to' => $language];
                    $item['foreign'] = true;

                    if (trim((string) ($row->draft ?? '')) !== '' && Translator::same((string) $room->staff_language, $language)) {
                        $item['draft'] = (string) $row->draft;
                    }
                }
            } else {
                $item['name']        = $names[(int) $row->staff_id] ?? '';
                $item['face']        = $profiles[(int) $row->staff_id]['face'] ?? null;
                $item['mine']        = (int) $row->staff_id === $staffId;
                $item['text']        = (string) $row->text;
                $item['translation'] = (string) $row->translation;
                $item['translatedTo'] = (string) $row->translation_language;
                $item['links']        = self::cardsFor($row, false);
                $item['translating']  = $this->translating($row);
                $item['failed']       = (int) ($row->translating ?? 0) === 2;
            }

            $items[] = $item;
        }

        return [$items, $names];
    }

    /**
     * Messages translated since a page saw them still being translated: shown again, translated.
     *
     * @param   int[]  $ids
     *
     * @return  array<int, array<string, mixed>>
     */
    public function updates(object $room, array $ids, string $side, int $staffId = 0, string $language = ''): array
    {
        $ids = \array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, 50);

        if (!$ids) {
            return [];
        }

        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_messages'))->where('room_id = :room')->whereIn('id', $ids)
                ->bind(':room', $room->id, ParameterType::INTEGER)->order('id')
        )->loadObjectList();

        $this->quickDrafts($room, $rows);

        // Translated since, or given a quick translation for the moment
        $rows = array_values(array_filter($rows, fn ($row) => !$this->translating($row) || trim((string) ($row->draft ?? '')) !== ''));

        if (!$rows) {
            return [];
        }

        return $side === 'staff' ? $this->staffItems($room, $rows, $staffId, $language)[0] : $this->visitorItems($room, $rows)[0];
    }

    /**
     * Messages the AI has been translating for longer than the settings allow: a quick machine
     * translation (Google's) for the moment, asked for once, by whichever page asks first.
     *
     * @param   object[]  $rows
     */
    private function quickDrafts(object $room, array $rows): void
    {
        if ($this->settings->quickTranslate() === '') {
            return;
        }

        $quick = null;
        $since = time() - $this->settings->quickSeconds();

        foreach ($rows as $row) {
            if ((int) ($row->translating ?? 0) !== 1 || $row->draft !== null || strtotime((string) $row->created . ' UTC') > $since) {
                continue;
            }

            // Claimed first (an empty draft), so two pages asking at once ask Google once
            $db      = $this->db;
            $id      = (int) $row->id;
            $claimed = $db->setQuery(
                $db->getQuery(true)->update($db->quoteName('#__askdesk_messages'))->set($db->quoteName('draft') . ' = ' . $db->quote(''))
                    ->where('id = :id')->where($db->quoteName('draft') . ' IS NULL')->where('translating = 1')->bind(':id', $id, ParameterType::INTEGER)
            )->execute() && $db->getAffectedRows() === 1;

            $row->draft = '';

            if (!$claimed) {
                continue;
            }

            $visitor = $row->author === 'visitor';
            $quick ??= new QuickTranslate($this->settings);
            $draft   = $quick->translate((string) $row->text, $visitor ? (string) $row->language : (string) $room->staff_language, $visitor ? (string) $room->staff_language : (string) $room->visitor_language);

            if ($draft === '') {
                continue;
            }

            // Only while the AI's has not come meanwhile
            $db->setQuery(
                $db->getQuery(true)->update($db->quoteName('#__askdesk_messages'))->set($db->quoteName('draft') . ' = :draft')
                    ->where('id = :id')->where('translating = 1')->bind(':draft', $draft)->bind(':id', $id, ParameterType::INTEGER)
            )->execute();

            if ($db->getAffectedRows() === 1) {
                $row->draft = $draft;
            }
        }
    }

    /**
     * Whether a message is still being translated. One that has waited too long is given up on (the
     * request translating it ended without a word): shown as written.
     */
    private function translating(object $row): bool
    {
        if ((int) ($row->translating ?? 0) !== 1) {
            return false;
        }

        if (strtotime((string) $row->created . ' UTC') < time() - self::TRANSLATE_SECONDS) {
            $row->translating = 2;
            $update = (object) ['id' => (int) $row->id, 'translating' => 2];
            $this->db->updateObject('#__askdesk_messages', $update, 'id');

            return false;
        }

        return true;
    }

    /**
     * The person answering closes the chat, or a message left that was dealt with.
     */
    public function closeByStaff(object $room, int $staffId): void
    {
        if ($room->status === 'closed') {
            return;
        }

        if ($room->status === 'active') {
            $this->add($room, 'system', $staffId, 'closed');
        }

        if (!(int) $room->staff_id) {
            $this->update((int) $room->id, ['staff_id' => $staffId]);
        }

        if ($room->status === 'message') {
            $this->handled($room, $staffId);
        }

        // Ended (or dealt with) by the one answering: closed (結案) by them too
        $this->close($room, $staffId);
    }

    /**
     * Closes (結案) a chat that ended with the visitor's words unanswered, or a message left, once
     * someone has dealt with it: off the lists of what is to be done, into those closed.
     */
    public function settle(object $room, int $staffId): void
    {
        if ($room->settled !== null) {
            return;
        }

        if ($room->status !== 'closed') {
            if ($room->status !== 'message') {
                throw new \RuntimeException(Text::_('COM_ASKDESK_STAFF_SETTLE_OPEN'));
            }

            $this->closeByStaff($room, $staffId);

            return;
        }

        if (!(int) $room->staff_id) {
            $this->update((int) $room->id, ['staff_id' => $staffId], false);
        }

        $this->handled($room, $staffId);
        $this->update((int) $room->id, ['settled' => Factory::getDate()->toSql(), 'settled_by' => $staffId]);
    }

    // ---------- Keeping ----------

    /**
     * Chats whose visitor is gone are closed; guests' closed chats and members' old ones are
     * forgotten. Run now and then, with the requests.
     */
    public function tidy(): void
    {
        // At most once a minute, whoever asks (each poll of anyone, visitors' too, may): taken by one request
        if (!$this->claimTidy()) {
            return;
        }

        $db   = $this->db;
        $idle = Factory::getDate('-' . $this->settings->idleMinutes() . ' minutes')->toSql();

        foreach ($db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_rooms'))
                ->where($db->quoteName('status') . ' IN (' . $db->quote('waiting') . ', ' . $db->quote('active') . ')')
                ->where('visitor_seen < :idle')->bind(':idle', $idle),
            0,
            50
        )->loadObjectList() as $room) {
            $this->add($room, 'system', 0, 'visitor_gone');
            $this->close($room);
        }

        $this->forgetOld();

        // Closed chats archived and then deleted: by the daily scheduled task, or here once a day when it
        // has not run (0.6.2)
        $archive = new Archive($db, $this->settings);
        $last    = $archive->last();

        if ((!$last || strtotime($last['time']) < time() - 86400) && $this->claim('archive_claimed', 86400)) {
            $archive->run(20);
        }

        // What is still unhandled after the hours set: told once more
        (new Alerts($this->db, $this->settings))->remind();
    }

    /** Whether it is this request's turn to tidy: a minute since the last, claimed by one request only (0.6.1) */
    private function claimTidy(): bool
    {
        return $this->claim('tidied', 60);
    }

    /** Whether it is this request's turn for what is done every so many seconds: claimed by one request only */
    private function claim(string $name, int $every): bool
    {
        $db    = $this->db;
        $table = $db->quoteName('#__askdesk_secrets');
        $now   = (string) time();
        $due   = (string) (time() - $every);

        try {
            $db->setQuery(
                $db->getQuery(true)->update($table)->set($db->quoteName('value') . ' = :now')
                    ->where($db->quoteName('name') . ' = ' . $db->quote($name))->where($db->quoteName('value') . ' < :due')
                    ->bind(':now', $now)->bind(':due', $due)
            )->execute();

            if ($db->getAffectedRows() > 0) {
                return true;
            }

            // Never tidied before: the first to put its time down does it
            $seen = $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($table)->where($db->quoteName('name') . ' = ' . $db->quote($name)))->loadResult();

            if ((int) $seen === 0) {
                $mark = (object) ['name' => $name, 'value' => $now];

                $db->insertObject('#__askdesk_secrets', $mark);

                return true;
            }
        } catch (\Throwable) {
            // Another request put it down first
        }

        return false;
    }

    private function forgetOld(): void
    {
        $db     = $this->db;
        // A guest's chat left alone without closing goes after a day at most
        $stale  = Factory::getDate('-1 day')->toSql();
        $old    = Factory::getDate('-' . $this->settings->keepDays() . ' days')->toSql();

        // A guest's chat never taken up nor finished goes after a day, not archived (0.6.2). Every closed chat
        // (結案), a guest's too, is archived after the days set and only then deleted (Archive); none is
        // deleted here for its age any more
        $query = $db->getQuery(true)->select('id')->from($db->quoteName('#__askdesk_rooms'))
            ->where('(user_id = 0 AND status IN (' . $db->quote('waiting') . ', ' . $db->quote('active') . ') AND modified < :stale AND settled IS NULL)')
            ->bind(':stale', $stale);
        $ids = array_map('intval', $db->setQuery($query, 0, 500)->loadColumn());

        // Ratings hold nothing of the visitor but what they wrote in the comment, and who handled
        // what nothing of them at all: kept for the days set
        $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_ratings'))->where('created < :old')->bind(':old', $old))->execute();
        $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_handled'))->where('created < :old2')->bind(':old2', $old))->execute();

        (new Files($db))->forget($ids);

        if ($ids) {
            $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_messages'))->whereIn('room_id', $ids))->execute();
            // Its work cards with it (0.6.1; what they made is kept where it was made)
            $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_work'))->whereIn('room_id', $ids))->execute();
            $db->setQuery($db->getQuery(true)->delete($db->quoteName('#__askdesk_rooms'))->whereIn('id', $ids))->execute();
        }
    }

    // ---------- Underneath ----------

    /**
     * The first or last message of each room (MIN or MAX) of those that are as said, by their ids: asked
     * apart, then the messages by their keys (0.6.1). As one query (id IN (SELECT … GROUP BY)) MySQL read the
     * whole table of messages for it; this way it reads the index on (room_id, author, id)
     *
     * @param   int[]  $rooms
     *
     * @return  int[]
     */
    private function ends(array $rooms, string $end, string $which): array
    {
        $rooms = array_values(array_filter(array_map('intval', $rooms)));

        if (!$rooms || !\in_array($end, ['MIN', 'MAX'], true)) {
            return [];
        }

        $db = $this->db;

        return array_map('intval', $db->setQuery(
            $db->getQuery(true)->select($end . '(id)')->from($db->quoteName('#__askdesk_messages'))
                ->whereIn('room_id', $rooms)->where($which)->group('room_id')
        )->loadColumn());
    }

    /**
     * @return  object[]
     */
    private function rows(int $roomId, int $after): array
    {
        static $cache = [];

        $key = $roomId . ':' . $after;

        if (!isset($cache[$key])) {
            $db = $this->db;
            $cache[$key] = $db->setQuery(
                $db->getQuery(true)->select('*')->from($db->quoteName('#__askdesk_messages'))
                    ->where('room_id = :room')->where('id > :after')
                    ->bind(':room', $roomId, ParameterType::INTEGER)->bind(':after', $after, ParameterType::INTEGER)
                    ->order('id'),
                0,
                200
            )->loadObjectList();
        }

        return $cache[$key];
    }

    /**
     * The cards of the articles a message links to, kept with it; their title and first words
     * translated for the visitor too when the message was.
     *
     * @param  array<int, array{url: string, title: string, excerpt: string, image: string}>  $cards
     */
    private function keepCards(object $room, int $id, array $cards, bool $translate): void
    {
        if (!$cards) {
            return;
        }

        foreach ($cards as &$card) {
            if (!$translate) {
                continue;
            }

            try {
                $title = $this->translate($card['title'], (string) $room->visitor_language, $room, true);
                $card['visitorTitle'] = $title['translated'] ? $title['text'] : '';

                if ($card['excerpt'] !== '') {
                    $excerpt = $this->translate($card['excerpt'], (string) $room->visitor_language, $room, true);
                    $card['visitorExcerpt'] = $excerpt['translated'] ? $excerpt['text'] : '';
                }
            } catch (\Throwable) {
                // Shown as written
            }
        }

        unset($card);

        $row = (object) ['id' => $id, 'links' => json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        $this->db->updateObject('#__askdesk_messages', $row, 'id');
    }

    /**
     * A message's cards, for the visitor (in their language when translated) or the people answering.
     *
     * @return  array<int, array{url: string, title: string, excerpt: string, image: string}>
     */
    private static function cardsFor(object $row, bool $visitor): array
    {
        $cards = json_decode((string) ($row->links ?? ''), true);

        if (!\is_array($cards)) {
            return [];
        }

        return array_values(array_map(static fn ($card) => [
            'url'     => (string) ($card['url'] ?? ''),
            'title'   => (string) ($visitor && !empty($card['visitorTitle']) ? $card['visitorTitle'] : ($card['title'] ?? '')),
            'excerpt' => (string) ($visitor && !empty($card['visitorExcerpt']) ? $card['visitorExcerpt'] : ($card['excerpt'] ?? '')),
            'image'   => (string) ($card['image'] ?? ''),
        ], array_filter($cards, 'is_array')));
    }

    /**
     * A file sent in a room, as a message of its own (its name for its words, not translated).
     */
    public function addFile(object $room, string $author, int $staffId, int $fileId, string $name): int
    {
        if (!\in_array($room->status, ['waiting', 'active'], true)) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_CHAT_CLOSED'));
        }

        return $this->add($room, $author, $staffId, $name, '', '', '', $fileId);
    }

    /**
     * A sticker sent in a room, as a message of its own (its name for its words, not translated):
     * by the visitor while the chat goes on, by the person answering in their own chat.
     *
     * @throws  \RuntimeException
     */
    public function addSticker(object $room, string $author, int $staffId, object $sticker): int
    {
        $open = $author === 'staff'
            ? $room->status === 'active' && (int) $room->staff_id === $staffId
            : \in_array($room->status, ['waiting', 'active'], true);

        if (!$open) {
            throw new \RuntimeException(Text::_($author === 'staff' ? 'COM_ASKDESK_STAFF_NOT_YOURS' : 'COM_ASKDESK_CHAT_CLOSED'));
        }

        if ($author === 'visitor') {
            $this->allowed($room);
        }

        $this->update((int) $room->id, [$author === 'staff' ? 'staff_typing' : 'visitor_typing' => null], false);

        return $this->add($room, $author, $staffId, mb_substr((string) $sticker->title, 0, 100), '', '', '', 0, (int) $sticker->id);
    }

    /**
     * The stickers of messages, as the pages show them (one taken away since: its name, no picture).
     *
     * @param   object[]  $rows
     *
     * @return  array<int, array{id: int, title: string, url: string}>  By message
     */
    private function stickersOf(array $rows): array
    {
        $ids = array_filter(array_map(static fn ($row) => (int) ($row->sticker_id ?? 0), $rows));

        if (!$ids) {
            return [];
        }

        $stickers = (new Stickers($this->db))->byIds($ids);
        $shown    = [];

        foreach ($rows as $row) {
            if ((int) ($row->sticker_id ?? 0)) {
                $shown[(int) $row->id] = $stickers[(int) $row->sticker_id] ?? ['id' => 0, 'title' => (string) $row->text, 'url' => ''];
            }
        }

        return $shown;
    }

    /**
     * The files of messages, as the pages show them.
     *
     * @param   object[]  $rows  Messages
     *
     * @return  array<int, array<string, mixed>>  By message
     */
    private function filesOf(array $rows, bool $staff = false): array
    {
        $ids = array_filter(array_map(static fn ($row) => (int) ($row->file_id ?? 0), $rows));

        if (!$ids) {
            return [];
        }

        $files = (new Files($this->db))->byIds($ids);
        $shown = [];

        foreach ($rows as $row) {
            $file = $files[(int) ($row->file_id ?? 0)] ?? null;

            if ($file) {
                // For the people answering, which file it is: to keep it in the library
                $shown[(int) $row->id] = Files::shown($file) + ($staff ? ['id' => (int) $file->id] : []);
            } elseif ((int) ($row->file_id ?? 0)) {
                // Its days are over: it is said so
                $shown[(int) $row->id] = ['name' => (string) $row->text, 'kind' => 'document', 'size' => 0, 'expires' => '', 'url' => '', 'gone' => true];
            }
        }

        return $shown;
    }

    private function add(object $room, string $author, int $staffId, string $text, string $language = '', string $translation = '', string $translationLanguage = '', int $fileId = 0, int $stickerId = 0, int $translating = 0): int
    {
        $now = Factory::getDate()->toSql();
        $row = (object) [
            'file_id'              => $fileId,
            'sticker_id'           => $stickerId,
            'translating'          => $translating,
            'room_id'              => (int) $room->id,
            'author'               => $author,
            'staff_id'             => $staffId,
            'text'                 => $text,
            'language'             => mb_substr($language, 0, 10),
            'translation'          => $translation,
            'translation_language' => mb_substr($translationLanguage, 0, 10),
            'created'              => $now,
        ];

        $this->db->insertObject('#__askdesk_messages', $row, 'id');
        $this->update((int) $room->id, ['last_message' => (int) $row->id] + ($author !== 'system' ? ['last_author' => $author] : []));

        // What a visitor writes (or sends) reaches the people answering, on their phones too
        if ($author === 'visitor') {
            Push::message((int) $room->id, (int) $row->id);
        }

        return (int) $row->id;
    }

    private function close(object $room, int $by = 0): void
    {
        $now    = Factory::getDate()->toSql();
        $fresh  = $this->get((int) $room->id) ?? $room;
        $values = ['status' => 'closed', 'closed' => $now];

        // Closed (結案) at once unless it ended by itself with the visitor's words unanswered: that waits
        // in the list until someone deals with it
        $left = !($by > 0 || (string) ($fresh->last_author ?? '') !== 'visitor');

        if (!$left) {
            $values += ['settled' => $now, 'settled_by' => $by];
        }

        $this->update((int) $room->id, $values);

        // Left to be dealt with: the people answering are told by e-mail
        if ($left && \in_array($room->status, ['waiting', 'active'], true)) {
            (new Alerts($this->db, $this->settings))->later((int) $room->id, 'unanswered');
        }

        // A chat someone answered is written out for the visitor (a message left was answered by e-mail)
        if (\in_array($room->status, ['waiting', 'active'], true) && ($closed = $this->get((int) $room->id)) && (int) $closed->staff_id) {
            (new Transcript($this->db, $this->settings))->sendLater($closed);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function update(int $id, array $values, bool $modified = true): void
    {
        $row = (object) (['id' => $id] + $values + ($modified ? ['modified' => Factory::getDate()->toSql()] : []));

        // A value set to nothing (no one typing) is written as such
        $this->db->updateObject('#__askdesk_rooms', $row, 'id', \in_array(null, $values, true));
    }

    /**
     * One side is typing (the visitor, or the person answering in their own chat): noted, for the
     * other side to see dots.
     */
    public function typing(object $room, string $side): void
    {
        $column = $side === 'staff' ? 'staff_typing' : 'visitor_typing';

        if (!self::lately($room->{$column} ?? null, 2)) {
            $this->update((int) $room->id, [$column => Factory::getDate()->toSql()], false);
            $room->{$column} = Factory::getDate()->toSql();
        }
    }

    private static function lately(?string $time, int $seconds = self::TYPING_SECONDS): bool
    {
        return $time !== null && $time !== '' && strtotime($time . ' UTC') >= time() - $seconds;
    }

    /**
     * The visitor's page is still there (noted now and then).
     */
    private function seen(object $room): void
    {
        if (!$room->visitor_seen || strtotime($room->visitor_seen . ' UTC') < time() - self::SEEN_SECONDS) {
            $this->update((int) $room->id, ['visitor_seen' => Factory::getDate()->toSql()], false);
        }
    }

    /**
     * @throws  \RuntimeException
     */
    private function allowed(object $room): void
    {
        $db    = $this->db;
        $since = Factory::getDate('-1 minute')->toSql();
        $query = $db->getQuery(true)->select('COUNT(*) AS total, SUM(CASE WHEN author = ' . $db->quote('visitor') . ' AND created >= :since THEN 1 ELSE 0 END) AS recent')
            ->from($db->quoteName('#__askdesk_messages'))->where('room_id = :room')
            ->bind(':since', $since)->bind(':room', $room->id, ParameterType::INTEGER);
        $count = $db->setQuery($query)->loadObject();

        if ((int) ($count->total ?? 0) >= self::MAX_MESSAGES || (int) ($count->recent ?? 0) >= self::PER_MINUTE) {
            throw new \RuntimeException(Text::_('COM_ASKDESK_CHAT_TOO_MANY'));
        }
    }

    /**
     * A message in another language, with the last messages of the chat for sense. Not translated
     * (the language unknown) when translating is off or fails for the visitor's messages: the
     * person answering reads it as it is.
     *
     * @return  array{language: string, text: string, translated: bool}
     */
    private function translate(string $text, string $target, object $room, bool $strict = false): array
    {
        $none = ['language' => '', 'text' => $text, 'translated' => false];

        if (!$this->settings->translates() || $target === '') {
            return $none;
        }

        $before = [];

        foreach (\array_slice(array_reverse($this->recent((int) $room->id)), 0, 4) as $row) {
            if ($row->author !== 'system') {
                $before[] = ($row->author === 'visitor' ? 'Visitor: ' : 'Staff: ') . mb_substr((string) $row->text, 0, 300);
            }
        }

        try {
            return (new Translator(AskSettings::load()))->translate($text, $target, $this->settings->glossary(), implode("\n", array_reverse($before)), $this->settings->translateModel(), $this->settings->translateEffort());
        } catch (\Throwable $e) {
            if ($strict) {
                throw $e;
            }

            return $none;
        }
    }

    /**
     * @return  object[]  The last messages of a room, newest first
     */
    private function recent(int $roomId): array
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)->select('author, text')->from($db->quoteName('#__askdesk_messages'))->where('room_id = :room')
                ->bind(':room', $roomId, ParameterType::INTEGER)->order('id DESC'),
            0,
            6
        )->loadObjectList();
    }

    /**
     * What a system message says, in the language of the request.
     */
    private function systemText(string $code, string $name): string
    {
        return match ($code) {
            'joined'       => Text::sprintf('COM_ASKDESK_SYSTEM_JOINED', $name),
            'closed'       => Text::_('COM_ASKDESK_SYSTEM_CLOSED'),
            'visitor_left' => Text::_('COM_ASKDESK_SYSTEM_VISITOR_LEFT'),
            'visitor_gone' => Text::_('COM_ASKDESK_SYSTEM_VISITOR_GONE'),
            default        => '',
        };
    }

    /**
     * A system message for the visitor: in the page's language, or translated into theirs once
     * (kept with the message) when they write in another.
     *
     * @param  array<int, string>  $names
     */
    private function systemForVisitor(object $room, object $row, array $names): string
    {
        if ($row->translation !== '' && Translator::same((string) $row->translation_language, (string) $room->visitor_language)) {
            return (string) $row->translation;
        }

        $text = $this->systemText((string) $row->text, $names[(int) $row->staff_id] ?? '');

        if ($text === '' || Translator::same((string) $room->visitor_language, (string) $room->page_language) || !$this->settings->translates()) {
            return $text;
        }

        $translation = $this->translate($text, (string) $room->visitor_language, $room);

        if ($translation['translated']) {
            $update = (object) ['id' => (int) $row->id, 'translation' => $translation['text'], 'translation_language' => mb_substr((string) $room->visitor_language, 0, 10)];
            $this->db->updateObject('#__askdesk_messages', $update, 'id');

            return $translation['text'];
        }

        return $text;
    }

    /**
     * What the agent handed over: the AI conversation, the question, the answer, the report.
     *
     * @return  array<string, mixed>
     */
    private static function cleanEntry(array $entry): array
    {
        $conversation = [];

        foreach (\array_slice((array) ($entry['conversation'] ?? []), -20) as $turn) {
            if (\is_array($turn) && \in_array($turn['role'] ?? '', ['user', 'assistant'], true)) {
                $conversation[] = ['role' => $turn['role'], 'text' => mb_substr((string) ($turn['text'] ?? ''), 0, 1500)];
            }
        }

        return array_filter([
            'conversation' => $conversation,
            'question'     => mb_substr((string) ($entry['question'] ?? ''), 0, 500),
            'report'       => mb_substr((string) ($entry['report'] ?? ''), 0, 500),
            'source'       => mb_substr((string) ($entry['source'] ?? ''), 0, 20),
            'agent'        => mb_substr((string) ($entry['agent'] ?? ''), 0, 255),
        ], static fn ($value) => $value !== '' && $value !== []);
    }

    private static function clean(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return mb_substr(trim($text), 0, self::MAX_TEXT);
    }

    /**
     * A time of the database (UTC) as ISO 8601, for the page to show in its own time zone.
     */
    private static function time(string $sql): string
    {
        return $sql !== '' ? gmdate('c', strtotime($sql . ' UTC')) : '';
    }
}
