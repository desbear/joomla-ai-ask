<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * One row per question in #__mcpask_log. The rows are what the limits count, so they are written
 * even when questions are not recorded. For the limits the address is kept as a keyed hash, for a
 * day. Who asked (the account, and the address in full) is recorded only when the administrator
 * wants it, and goes when the row does.
 *
 * @since  0.1.0
 */
final class UsageLog
{
    /** Questions one address may have in progress at the same time. */
    private const AT_ONCE = 2;

    /** Seconds after which a question that never finished no longer counts as in progress. */
    private const STALE = 300;

    /** Marks that make a question an attack, kept as evidence (0.21.0) */
    public const EVIDENCE = ['injection_suspect', 'html_payload', 'probe', 'identity_refused', 'suspicious', 'linked'];

    /** @var array<int, array{ip: string, question: string, hash: string[], linked: bool}>  What start() was given, until finish() */
    private array $asked = [];

    public function __construct(private DatabaseInterface $db, private Settings $settings, private string $secret)
    {
    }

    /**
     * Record a question and check the limits. The row is written first and counted with the
     * others, so questions sent at the same moment cannot all slip under a limit together.
     *
     * @param   array{minute: int, day: int}|null  $limits  This visitor's limits (0 is no limit), null for none at all
     *
     * @return  array{id: int, limit: ?string}  The ID for finish(), or the limit that was reached
     *                                          ("blocked", "minute", "day" or "site") and no row
     */
    public function start(string $ip, string $question, int $userId = 0, ?array $limits = ['minute' => -1, 'day' => -1]): array
    {
        // The general limits unless the visitor's own were given
        $minute = $limits === null ? 0 : ($limits['minute'] < 0 ? $this->settings->perMinute() : $limits['minute']);
        $day    = $limits === null ? 0 : ($limits['day'] < 0 ? $this->settings->perDay() : $limits['day']);

        // This address today and yesterday (the key changes daily): the limits look back a day
        $hash   = $this->hashes($ip);
        $who    = $this->settings->logWho();
        // Kept as written, with the address, only if it turns out to be an attack (finish())
        $record = (object) [
            'created'  => Factory::getDate()->toSql(),
            'ip_hash'  => $hash[0],
            'user_id'  => $who ? $userId : 0,
            'ip'       => '',
            'question' => $this->settings->logQuestions() ? Masker::mask($question) : null,
            'status'   => 'started',
        ];

        $this->db->insertObject('#__mcpask_log', $record, 'id');
        // A visitor already caught at something today: what else they ask is kept with it
        $this->asked[(int) $record->id] = ['ip' => $ip, 'question' => $question, 'hash' => $hash, 'linked' => $this->caught($hash)];

        // A member is counted by account, so colleagues behind one address do not share a limit
        // (when who asked is recorded; otherwise by address, as for guests)
        $byUser = $who && $userId > 0 ? $userId : 0;

        $limit = match (true) {
            // Too much has been turned away from this address lately: it waits out the hour
            $this->settings->strikes() > 0 && $this->turnedAway($hash) >= $this->settings->strikes() => 'blocked',
            $minute > 0 && $this->count(60, $hash, $byUser) > $minute,
            $this->inProgress($hash) > self::AT_ONCE                                  => 'minute',
            $day > 0 && $this->count(86400, $hash, $byUser) > $day                    => 'day',
            // What was turned away cost nothing, so it does not count towards the site's day either
            $limits !== null && $this->count(86400, null, 0, true) > $this->settings->sitePerDay() => 'site',
            default                                                                   => null,
        };

        if ($limit !== null) {
            // A refused question leaves nothing behind: a flood must not fill the table either
            $this->db->setQuery(
                $this->db->getQuery(true)->delete($this->db->quoteName('#__mcpask_log'))->where('id = ' . (int) $record->id)
            )->execute();

            return ['id' => 0, 'limit' => $limit];
        }

        // Now and then, forget what is older than the administrator wants kept
        if (random_int(1, 20) === 1) {
            $this->prune();
        }

        return ['id' => (int) $record->id, 'limit' => null];
    }

    /**
     * @param  array{status: string, sources?: int, input?: int, output?: int, message?: string}  $result
     */
    public function finish(int $id, array $result, float $startedAt): void
    {
        $asked = $this->asked[$id] ?? ['ip' => '', 'question' => '', 'hash' => [], 'linked' => false];
        $flags = array_unique((array) ($result['flags'] ?? []));

        // What reads as reconnaissance is evidence even when it was answered (0.21.0)
        if (Suspicion::of((string) $asked['question']) !== null) {
            $flags[] = 'suspicious';
        }

        if ($asked['linked']) {
            $flags[] = 'linked';
        }

        $flags    = array_values(array_unique($flags));
        $evidence = $result['status'] === 'blocked' || array_intersect($flags, self::EVIDENCE);

        $record = (object) [
            'id'          => $id,
            'status'      => $result['status'],
            'message'     => mb_substr((string) ($result['message'] ?? ''), 0, 250),
            'sources'     => min(255, (int) ($result['sources'] ?? 0)),
            'tokens_in'   => (int) ($result['input'] ?? 0),
            'tokens_out'  => (int) ($result['output'] ?? 0),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'hint_id'     => (int) (($result['hints'] ?? [])[0] ?? 0),
            'flags'       => mb_substr(implode(',', $flags), 0, 255),
        ];

        // An attack: kept longer, with the address and the words as they came (0.21.0)
        if ($evidence) {
            $record->evidence = 1;
            $record->ip       = mb_substr((string) $asked['ip'], 0, 45);

            if ($this->settings->logQuestions()) {
                $record->question_raw = mb_substr((string) $asked['question'], 0, 60000);
            }
        }

        // The answer as shown, and as the model wrote it when the filter changed it; kept like the question
        if ($this->settings->logQuestions() && isset($result['answer'])) {
            $record->answer     = mb_substr((string) $result['answer'], 0, 60000);
            $record->answer_raw = !empty($result['removed']) ? mb_substr((string) ($result['answer_raw'] ?? ''), 0, 60000) : null;
        }

        $this->db->updateObject('#__mcpask_log', $record, 'id');

        // ...and what the same visitor asked in the day before, so the reconnaissance is kept whole
        if ($evidence && $asked['hash']) {
            $this->link($asked['hash'], (string) $asked['ip']);
        }
    }

    /**
     * Whether this visitor (today's or yesterday's hash) was caught at something in the last day.
     *
     * @param  string[]  $hash
     */
    private function caught(array $hash): bool
    {
        $since = Factory::getDate('-1 day')->toSql();

        return (int) $this->db->setQuery(
            $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mcpask_log'))
                ->where('evidence = 1')->whereIn('ip_hash', $hash, ParameterType::STRING)
                ->where('created >= :since')->bind(':since', $since)
        )->loadResult() > 0;
    }

    /**
     * The same visitor's other questions of the last day, kept as evidence too, with the address.
     *
     * @param  string[]  $hash
     */
    private function link(array $hash, string $ip): void
    {
        $since = Factory::getDate('-1 day')->toSql();
        $ip    = mb_substr($ip, 0, 45);

        $this->db->setQuery(
            $this->db->getQuery(true)->update($this->db->quoteName('#__mcpask_log'))
                ->set('evidence = 1')
                ->set('ip = :ip')
                ->set($this->db->quoteName('flags') . ' = ' . $this->addFlag('linked'))
                ->where('evidence = 0')->whereIn('ip_hash', $hash, ParameterType::STRING)
                ->where('created >= :since')
                ->bind(':ip', $ip)->bind(':since', $since)
        )->execute();
    }

    /**
     * What the visitor's browser is given to rate an answer with: the log ID, signed, so no one
     * can rate answers that were given to others.
     */
    public function reference(int $id): string
    {
        return $id . '.' . substr(hash_hmac('sha256', 'rating:' . $id, $this->secret), 0, 24);
    }

    /**
     * Record how the visitor rated an answer: 1 good, -1 bad, with an optional word of why. Only
     * for an answer given in the last day; rating again replaces the rating.
     */
    public function rate(string $reference, int $rating, string $feedback): bool
    {
        if (!preg_match('/^(\d{1,10})\.[0-9a-f]{24}$/', $reference, $match) || !hash_equals($this->reference((int) $match[1]), $reference) || !\in_array($rating, [1, -1], true)) {
            return false;
        }

        $since  = Factory::getDate('-1 day')->toSql();

        // A reason that is a probe: the rating is a scanner's. It is not counted, only marked, and
        // the one who sent it is told it was taken, as anyone else would be
        if ((new Guard($this->settings))->refusesFeedback($feedback) !== null || Suspicion::of($feedback) !== null) {
            // Evidence (0.21.0): with the address the probe came from
            $ip = mb_substr($this->settings->visitorIp(), 0, 45);

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__mcpask_log'))
                    ->set($this->db->quoteName('flags') . ' = ' . $this->addFlag('probe'))
                    ->set('evidence = 1')
                    ->set('ip = :ip')
                    ->where('id = ' . (int) $match[1])
                    ->where('created >= :since')
                    ->bind(':ip', $ip)
                    ->bind(':since', $since)
            )->execute();

            return true;
        }

        $text   = mb_substr(Masker::mask(trim((string) preg_replace('/\s+/u', ' ', strip_tags($feedback)))), 0, 255);
        $query  = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__mcpask_log'))
            ->set('rating = ' . $rating)
            ->set('feedback = :feedback')
            ->where('id = ' . (int) $match[1])
            ->where('created >= :since')
            ->bind(':feedback', $text)
            ->bind(':since', $since);

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows() > 0;
    }

    /**
     * Mark questions of the log as dismissed: tests, or nothing to do about. They leave the list of
     * questions to look at and the share of helpful answers.
     *
     * @param   int[]  $ids
     */
    public function dismiss(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            return 0;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__mcpask_log'))
                ->set($this->db->quoteName('flags') . ' = ' . $this->addFlag('dismissed'))
                ->whereIn('id', $ids)
        )->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * SQL for the flags with one more, once.
     */
    private function addFlag(string $flag): string
    {
        $flags = $this->db->quoteName('flags');

        return 'CASE WHEN ' . $flags . ' LIKE ' . $this->db->quote('%' . $flag . '%') . ' THEN ' . $flags
            . ' WHEN ' . $flags . ' = ' . $this->db->quote('') . ' OR ' . $flags . ' IS NULL THEN ' . $this->db->quote($flag)
            . ' ELSE ' . $this->db->getQuery(true)->concatenate([$flags, $this->db->quote(',' . $flag)]) . ' END';
    }

    /**
     * Forget what is older than the administrator wants kept (0.21.0): a question's words, answer,
     * reason and account after questionDays(), its figures after logDays(), attacks (with their
     * address and words as sent) after evidenceDays(); the address hash after a day.
     *
     * @return  array{deleted: int, evidence: int, cleared: int}  Rows deleted, attacks deleted, rows made anonymous
     */
    public function prune(): array
    {
        $db     = $this->db;
        $table  = $db->quoteName('#__mcpask_log');
        $counts = ['deleted' => 0, 'evidence' => 0, 'cleared' => 0];
        $run    = static function ($query) use ($db): int {
            $db->setQuery($query)->execute();

            return $db->getAffectedRows();
        };

        $before = Factory::getDate('-' . $this->settings->logDays() . ' days')->toSql();
        $counts['deleted'] = $run($db->getQuery(true)->delete($table)->where('evidence = 0')->where('created < :before')->bind(':before', $before));

        $before = Factory::getDate('-' . $this->settings->evidenceDays() . ' days')->toSql();
        $counts['evidence'] = $run($db->getQuery(true)->delete($table)->where('evidence = 1')->where('created < :before')->bind(':before', $before));

        // Only the figures of an ordinary question after a while: no words, no account
        $before = Factory::getDate('-' . $this->settings->questionDays() . ' days')->toSql();
        $counts['cleared'] = $run(
            $db->getQuery(true)->update($table)
                ->set('question = NULL')->set('question_raw = NULL')->set('answer = NULL')->set('answer_raw = NULL')
                ->set('feedback = ' . $db->quote(''))->set('user_id = 0')->set('ip = ' . $db->quote(''))
                ->where('evidence = 0')
                ->where('created < :before')
                ->where('(question IS NOT NULL OR answer IS NOT NULL OR feedback <> ' . $db->quote('') . ' OR user_id <> 0 OR ip <> ' . $db->quote('') . ')')
                ->bind(':before', $before)
        );

        // The limits look back one day. After that the address hash only serves to link a
        // visitor's questions to each other, which nobody needs.
        $before = Factory::getDate('-1 day')->toSql();
        $run($db->getQuery(true)->update($table)->set('ip_hash = ' . $db->quote(''))->where('created < :before')->where('ip_hash <> ' . $db->quote(''))->bind(':before', $before));

        return $counts;
    }

    /**
     * Questions of one address that were turned away as probes within the last hour.
     */
    private function turnedAway(array $hash): int
    {
        $since = Factory::getDate('-1 hour')->toSql();
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__mcpask_log'))
            ->where('status = ' . $this->db->quote('blocked'))
            ->whereIn('ip_hash', $hash, ParameterType::STRING)
            ->where('created >= :since')
            ->bind(':since', $since);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Questions of one address that are being answered right now.
     */
    private function inProgress(array $hash): int
    {
        $since = Factory::getDate('-' . self::STALE . ' seconds')->toSql();
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__mcpask_log'))
            ->where('status = ' . $this->db->quote('started'))
            ->whereIn('ip_hash', $hash, ParameterType::STRING)
            ->where('created >= :since')
            ->bind(':since', $since);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Questions asked in the last so many seconds, by one account, one address or everyone.
     */
    public function count(int $seconds, string|array|null $hash = null, int $userId = 0, bool $paidOnly = false): int
    {
        $since = Factory::getDate('-' . $seconds . ' seconds')->toSql();
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__mcpask_log'))
            ->where('created >= :since')
            ->bind(':since', $since);

        if ($paidOnly) {
            $query->where('status <> ' . $this->db->quote('blocked'));
        }

        if ($userId > 0) {
            $query->where('user_id = ' . $userId);
        } elseif ($hash !== null) {
            $query->whereIn('ip_hash', (array) $hash, ParameterType::STRING);
        }

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * @return  object[]  The latest questions, newest first, with the name of who asked when that is known
     */
    /** Marks a question can carry in the log. */
    public const FLAGS = ['injection_suspect', 'html_payload', 'output_filtered', 'probe', 'dismissed', 'identity_refused', 'suspicious', 'linked'];

    /** Marks of questions that are tests, not visitors': left out of what is looked at and counted. */
    public const NOT_VISITORS = ['injection_suspect', 'html_payload', 'probe', 'dismissed', 'identity_refused'];

    public function recent(int $limit, string $flag = ''): array
    {
        return $this->search(['flag' => $flag], $limit)[0];
    }

    /** Statuses a question may end in, for filtering the log. */
    public const STATUSES = ['ok', 'empty', 'error', 'blocked', 'aborted', 'started'];

    /**
     * Questions of the log, newest first, a page at a time (0.20.3): with the account that asked and
     * the embed key a question came through (another website).
     *
     * @param   array{from?: string, to?: string, source?: string, status?: string, rating?: string, flag?: string, who?: string, search?: string}  $filter
     *          from, to: days (Y-m-d) in the site's time zone; source: "site" (asked here) or "embed:ID";
     *          status: one of STATUSES or "unanswered"; rating: good, bad or none; who: member or guest
     *
     * @return  array{0: object[], 1: int}  Rows and how many in all
     */
    public function search(array $filter, int $limit, int $start = 0): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__mcpask_log', 'l'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = l.user_id');
        $embeds = isset($db->getTableColumns('#__mcpask_log')['embed_id']);

        if ($embeds) {
            $query->join('LEFT', $db->quoteName('#__mcpask_embeds', 'e'), 'e.id = l.embed_id');
        }

        $zone = Factory::getApplication()->get('offset', 'UTC');

        foreach (['from' => '>=', 'to' => '<'] as $name => $operator) {
            $day = (string) ($filter[$name] ?? '');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                // The day as the site counts it; "to" includes its whole day
                $when = Factory::getDate($day . ($name === 'to' ? ' +1 day' : ''), $zone)->toSql();
                $query->where('l.created ' . $operator . ' ' . $db->quote($when));
            }
        }

        $source = (string) ($filter['source'] ?? '');

        if ($embeds && $source === 'site') {
            $query->where('l.embed_id = 0');
        } elseif ($embeds && preg_match('/^embed:(\d+)$/', $source, $match)) {
            $query->where('l.embed_id = ' . (int) $match[1]);
        }

        $status = (string) ($filter['status'] ?? '');

        if ($status === 'unanswered') {
            $query->where('l.status <> ' . $db->quote('ok'));
        } elseif (\in_array($status, self::STATUSES, true)) {
            $query->where('l.status = ' . $db->quote($status));
        }

        match ((string) ($filter['rating'] ?? '')) {
            'good'  => $query->where('l.rating > 0'),
            'bad'   => $query->where('l.rating < 0'),
            'none'  => $query->where('l.rating = 0'),
            default => null,
        };

        // Only questions with this mark (a known one: the name goes into the query)
        if (\in_array($filter['flag'] ?? '', self::FLAGS, true)) {
            $query->where($db->quoteName('l.flags') . ' LIKE ' . $db->quote('%' . $filter['flag'] . '%'));
        }

        match ((string) ($filter['who'] ?? '')) {
            'member' => $query->where('l.user_id > 0'),
            'guest'  => $query->where('l.user_id = 0'),
            default  => null,
        };

        $search = trim((string) ($filter['search'] ?? ''));

        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where('(l.question LIKE :search1 OR u.name LIKE :search2 OR u.email LIKE :search3)')
                ->bind(':search1', $like)
                ->bind(':search2', $like)
                ->bind(':search3', $like);
        }

        $total = (int) $db->setQuery((clone $query)->select('COUNT(*)'))->loadResult();

        $query->select('l.id, l.created, l.question, l.status, l.message, l.sources, l.tokens_in, l.tokens_out, l.duration_ms, l.user_id, l.ip, l.rating, l.feedback, l.hint_id, l.flags, l.answer, l.answer_raw, u.name, u.username')
            ->select($embeds ? 'l.embed_id, e.title AS embed_title' : '0 AS embed_id, ' . $db->quote('') . ' AS embed_title')
            ->order('l.id DESC');

        return [$db->setQuery($query, $start, $limit)->loadObjectList(), $total];
    }

    /**
     * The address, hashed with today's key and with yesterday's (0.21.0): the same address links up
     * for a day, and once a key is thrown away no hash made with it can be traced back to the
     * address, not even by someone who has the site's configuration.
     *
     * @return  array{0: string, 1: string}  Today's hash, yesterday's
     */
    private function hashes(string $ip): array
    {
        // One IPv6 customer has a whole /64 of addresses to choose from, so that is what is counted
        $packed = @inet_pton($ip);

        // An IPv4 address written as IPv6 (::ffff:a.b.c.d) is that IPv4 address, not the /64 of all of them
        if ($packed !== false && \strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            $packed = substr($packed, 12);
            $ip     = (string) inet_ntop($packed);
        }

        if ($packed !== false && \strlen($packed) === 16) {
            $ip = (string) inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
        }

        $keys = $this->keys();

        return [hash_hmac('sha256', $ip, $keys['today']), hash_hmac('sha256', $ip, $keys['yesterday'])];
    }

    /**
     * Today's key and yesterday's, random, made anew each day; the day before yesterday's is gone.
     *
     * @return  array{today: string, yesterday: string}
     */
    private function keys(): array
    {
        static $keys = null;

        $day = gmdate('Y-m-d');

        if ($keys !== null && $keys['day'] === $day) {
            return $keys;
        }

        $state = new State($this->db);
        $kept  = $state->get('log:keys');

        if (($kept['day'] ?? '') !== $day || empty($kept['today'])) {
            $kept = [
                'day'       => $day,
                'today'     => bin2hex(random_bytes(32)),
                // Only yesterday's: one older than that cannot be used for a day's limits anyway
                'yesterday' => ($kept['day'] ?? '') === gmdate('Y-m-d', time() - 86400) && !empty($kept['today']) ? $kept['today'] : bin2hex(random_bytes(32)),
            ];
            $state->set('log:keys', $kept);
        }

        return $keys = $kept;
    }
}
