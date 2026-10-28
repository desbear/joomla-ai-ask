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
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Notifications to the people answering on their phones and computers, the console closed or not
 * (0.3.0): Web Push, each browser subscribed with the console's bell. Someone new waiting, a message
 * in one's own chat, a message left. Sent after the visitor's request is answered (and after the
 * message is translated), encrypted for each browser (RFC 8291) and signed for this site (VAPID,
 * RFC 8292), with PHP's own OpenSSL: no library.
 *
 * @since  0.3.0
 */
final class Push
{
    private const SUBSCRIPTIONS = '#__askdesk_push';

    private const SECRETS = '#__askdesk_secrets';

    /** Above this many failures in a row a subscription is let go */
    private const FAILURES = 5;

    /** @var array<int, array{kind: string, room: int, message: int}> */
    private static array $queue = [];

    public function __construct(private DatabaseInterface $db)
    {
    }

    public static function make(): self
    {
        return new self(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Whether pushing can work here at all (OpenSSL with elliptic curves).
     */
    public static function possible(): bool
    {
        return \function_exists('openssl_pkey_derive') && \defined('OPENSSL_KEYTYPE_EC') && \function_exists('hash_hkdf');
    }

    // ---------- Subscriptions ----------

    /**
     * A browser of someone answering, to notify (its push service's address and keys).
     */
    public function subscribe(int $userId, string $endpoint, string $p256dh, string $auth): void
    {
        if (!preg_match('#^https://[^\s"<>]+$#', $endpoint) || \strlen(self::unb64($p256dh)) !== 65 || \strlen(self::unb64($auth)) !== 16) {
            throw new \InvalidArgumentException('Not a push subscription');
        }

        $db   = $this->db;
        $hash = hash('sha256', $endpoint);

        $row = (object) [
            'user_id'       => $userId,
            'endpoint'      => $endpoint,
            'endpoint_hash' => $hash,
            'p256dh'        => $p256dh,
            'auth'          => $auth,
            'created'       => Factory::getDate()->toSql(),
            'failures'      => 0,
        ];

        // The same browser again (renewed, or another person on it now): one row, the latest
        $this->forget($endpoint);
        $db->insertObject(self::SUBSCRIPTIONS, $row);
    }

    public function forget(string $endpoint, int $userId = 0): void
    {
        $db    = $this->db;
        $hash  = hash('sha256', $endpoint);
        $query = $db->getQuery(true)->delete($db->quoteName(self::SUBSCRIPTIONS))
            ->where($db->quoteName('endpoint_hash') . ' = :hash')->bind(':hash', $hash);

        if ($userId > 0) {
            $query->where($db->quoteName('user_id') . ' = :user')->bind(':user', $userId, ParameterType::INTEGER);
        }

        $db->setQuery($query)->execute();
    }

    // ---------- What happened, to tell ----------

    /**
     * Someone new waits for a person.
     */
    public static function waiting(int $roomId): void
    {
        self::queue('waiting', $roomId, 0);
    }

    /**
     * A message from a visitor (in a chat, or left).
     */
    public static function message(int $roomId, int $messageId): void
    {
        self::queue('message', $roomId, $messageId);
    }

    private static function queue(string $kind, int $room, int $message): void
    {
        if (!self::possible()) {
            return;
        }

        if (self::$queue === []) {
            // After the visitor is answered, and after what the request translates afterwards (its own
            // work registered later): a shutdown function that registers the sending last
            register_shutdown_function(static function (): void {
                register_shutdown_function(static function (): void {
                    if (\function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request();
                    }

                    ignore_user_abort(true);

                    try {
                        self::make()->sendQueued();
                    } catch (\Throwable) {
                        // Notifications are a help: the console shows everything anyway
                    }
                });
            });
        }

        self::$queue[] = ['kind' => $kind, 'room' => $room, 'message' => $message];
    }

    private function sendQueued(): void
    {
        $queue       = self::$queue;
        self::$queue = [];

        foreach ($queue as $item) {
            $room = $this->room($item['room']);

            if (!$room) {
                continue;
            }

            foreach ($this->recipients($room, $item['kind']) as $staff) {
                $words = $this->words($room, $item, (string) $staff->language);

                foreach ($this->subscriptionsOf((int) $staff->user_id) as $subscription) {
                    $this->deliver($subscription, $words);
                }
            }
        }
    }

    /**
     * Whom to tell: the one answering a chat about its messages; otherwise all who answer and are not
     * away (a message left: everyone, it waits for one of them).
     *
     * @return  object[]
     */
    private function recipients(object $room, string $kind): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)->select('s.user_id, s.language, s.status')->from($db->quoteName('#__askdesk_staff', 's'))
            ->where('s.active = 1')
            ->where('EXISTS (SELECT 1 FROM ' . $db->quoteName(self::SUBSCRIPTIONS) . ' p WHERE p.user_id = s.user_id)');

        if ($kind === 'message' && (int) $room->staff_id > 0) {
            $staffId = (int) $room->staff_id;
            $query->where('s.user_id = :staff')->bind(':staff', $staffId, ParameterType::INTEGER);
        } elseif ($room->status !== 'message') {
            $query->where('s.status <> ' . $db->quote('away'));
        }

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * What the notification says, in the language of the one told.
     *
     * @return  array{title: string, body: string, tag: string, url: string}
     */
    private function words(object $room, array $item, string $tag): array
    {
        $language = $this->language($tag);
        $name     = trim((string) $room->name) !== '' ? trim((string) $room->name) : $language->_('COM_ASKDESK_PUSH_VISITOR');
        $text     = '';

        if ($item['message'] > 0) {
            $db  = $this->db;
            $id  = $item['message'];
            $row = $db->setQuery(
                $db->getQuery(true)->select('text, translation, file_id, sticker_id')->from($db->quoteName('#__askdesk_messages'))
                    ->where('id = :id')->bind(':id', $id, ParameterType::INTEGER)
            )->loadObject();

            if ($row) {
                $text = (int) $row->file_id ? $language->_('COM_ASKDESK_PUSH_FILE') : ((int) $row->sticker_id ? $language->_('COM_ASKDESK_PUSH_STICKER') : (string) (trim((string) $row->translation) !== '' ? $row->translation : $row->text));
            }
        } else {
            // What they asked last, before asking for a person
            $entry = json_decode((string) $room->entry, true) ?: [];
            $asked = array_values(array_filter((array) ($entry['conversation'] ?? []), static fn ($turn) => \is_array($turn) && ($turn['role'] ?? '') === 'user'));
            $text  = (string) ($entry['question'] ?? '') ?: (string) (end($asked)['text'] ?? '');
        }

        $text  = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))), 0, 140);
        $title = match (true) {
            $item['kind'] === 'waiting' => \sprintf($language->_('COM_ASKDESK_PUSH_WAITING'), $name),
            $room->status === 'message' => \sprintf($language->_('COM_ASKDESK_PUSH_LEFT'), $name),
            default                     => $name,
        };

        return [
            'title' => $title,
            'body'  => $text !== '' ? $text : $language->_('COM_ASKDESK_PUSH_OPEN'),
            'tag'   => 'askdesk-room-' . (int) $room->id,
            'url'   => self::consoleUrl() . '#room=' . (int) $room->id,
        ];
    }

    private function language(string $tag): Language
    {
        static $languages = [];

        $tag = $tag !== '' && LanguageHelper::exists($tag, JPATH_SITE) ? $tag : Settings::load()->staffLanguage();

        if (!isset($languages[$tag])) {
            $languages[$tag] = Language::getInstance($tag);
            $languages[$tag]->load('com_askdesk', JPATH_SITE, $tag, true);
        }

        return $languages[$tag];
    }

    /**
     * The console's address, whole: its menu item's, or the component's view.
     */
    public static function consoleUrl(): string
    {
        $link = 'index.php?option=com_askdesk&view=console';

        try {
            $item = Factory::getApplication()->getMenu('site')->getItems('link', $link, true);
            $url  = Route::link('site', $item ? 'index.php?Itemid=' . (int) $item->id : $link, false, Route::TLS_IGNORE, true);
        } catch (\Throwable) {
            $url = Uri::root() . $link;
        }

        return $url;
    }

    /** The console on its own, without the site's template, at a chat when one is given */
    public static function consoleAt(int $roomId = 0): string
    {
        $url = self::consoleUrl();

        return $url . (str_contains($url, 'tmpl=') ? '' : (str_contains($url, '?') ? '&' : '?') . 'tmpl=component') . ($roomId ? '#room=' . $roomId : '');
    }

    private function room(int $id): ?object
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)->select('id, name, status, staff_id, entry')->from($db->quoteName('#__askdesk_rooms'))
                ->where('id = :id')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * @return  object[]
     */
    private function subscriptionsOf(int $userId): array
    {
        $db = $this->db;

        return $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName(self::SUBSCRIPTIONS))
                ->where('user_id = :user')->bind(':user', $userId, ParameterType::INTEGER)
        )->loadObjectList();
    }

    // ---------- Sending ----------

    /**
     * One notification to one browser. A subscription the push service no longer knows is let go;
     * one failing again and again too.
     *
     * @param  array<string, string>  $words
     */
    public function deliver(object $subscription, array $words): bool
    {
        $endpoint = (string) $subscription->endpoint;
        $body     = self::encrypt(json_encode($words, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) $subscription->p256dh, (string) $subscription->auth);
        $keys     = $this->keys();
        $parts    = parse_url($endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        try {
            $response = HttpFactory::getHttp()->post($endpoint, $body, [
                'Content-Type'     => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => '86400',
                'Urgency'          => 'high',
                'Topic'            => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $words['tag']), 0, 32),
                'Authorization'    => 'vapid t=' . $this->jwt($audience, $keys) . ', k=' . $keys['public'],
            ], 10);
        } catch (\Throwable) {
            $this->failed($subscription, false);

            return false;
        }

        if ($response->code >= 200 && $response->code < 300) {
            $this->db->setQuery(
                $this->db->getQuery(true)->update($this->db->quoteName(self::SUBSCRIPTIONS))
                    ->set(['failures = 0', 'last_sent = ' . $this->db->quote(Factory::getDate()->toSql())])
                    ->where('id = ' . (int) $subscription->id)
            )->execute();

            return true;
        }

        // Gone (404, 410), or no longer accepted with these keys (403): let go
        $this->failed($subscription, \in_array($response->code, [403, 404, 410], true));

        return false;
    }

    private function failed(object $subscription, bool $gone): void
    {
        $db = $this->db;

        if ($gone || (int) $subscription->failures + 1 >= self::FAILURES) {
            $db->setQuery($db->getQuery(true)->delete($db->quoteName(self::SUBSCRIPTIONS))->where('id = ' . (int) $subscription->id))->execute();

            return;
        }

        $db->setQuery($db->getQuery(true)->update($db->quoteName(self::SUBSCRIPTIONS))->set('failures = failures + 1')->where('id = ' . (int) $subscription->id))->execute();
    }

    // ---------- This site's keys (VAPID) ----------

    /**
     * The site's public key, as browsers take it (base64url of the uncompressed point).
     */
    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /**
     * Made once, kept in the desk's own table (saving the options doesn't lose them).
     *
     * @return  array{public: string, private: string}
     */
    private function keys(): array
    {
        static $keys = null;

        if ($keys !== null) {
            return $keys;
        }

        $db    = $this->db;
        $saved = $db->setQuery(
            $db->getQuery(true)->select('value')->from($db->quoteName(self::SECRETS))->where($db->quoteName('name') . ' = ' . $db->quote('vapid'))
        )->loadResult();
        $saved = $saved ? json_decode((string) $saved, true) : null;

        if (\is_array($saved) && !empty($saved['public']) && !empty($saved['private'])) {
            return $keys = $saved;
        }

        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        openssl_pkey_export($key, $pem);

        $keys = ['public' => self::b64(self::point($key)), 'private' => $pem];

        $row = (object) ['name' => 'vapid', 'value' => json_encode($keys)];

        $db->insertObject(self::SECRETS, $row);

        return $keys;
    }

    /**
     * The signed token saying this site sends it (ES256), for the push service at the audience.
     *
     * @param  array{public: string, private: string}  $keys
     */
    private function jwt(string $audience, array $keys): string
    {
        $subject = (string) Factory::getApplication()->get('mailfrom', '');
        $head    = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims  = self::b64(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => filter_var($subject, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $subject : rtrim(Uri::root(), '/'),
        ], JSON_UNESCAPED_SLASHES));

        openssl_sign($head . '.' . $claims, $der, $keys['private'], OPENSSL_ALGO_SHA256);

        return $head . '.' . $claims . '.' . self::b64(self::raw($der));
    }

    // ---------- Encryption (RFC 8291, aes128gcm) ----------

    /**
     * The payload sealed for one browser: only it can read it.
     */
    public static function encrypt(string $payload, string $p256dh, string $auth, $local = null, ?string $salt = null): string
    {
        // A key and a salt of its own each time (given only to check it against RFC 8291's example)
        $client = self::unb64($p256dh);
        $secret = self::unb64($auth);
        $local ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ours   = self::point($local);
        $shared = openssl_pkey_derive(self::publicKeyOf($client), $local, 32);

        if ($shared === false) {
            throw new \RuntimeException('No shared secret');
        }

        $ikm   = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $client . $ours, $secret);
        $salt ??= random_bytes(16);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $text  = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt . pack('N', 4096) . \chr(65) . $ours . $text . $tag;
    }

    /**
     * A key's public point, uncompressed (0x04, x, y).
     *
     * @param  \OpenSSLAsymmetricKey  $key
     */
    private static function point($key): string
    {
        $details = openssl_pkey_get_details($key);

        return "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    /**
     * A public key from its uncompressed point (wrapped as SubjectPublicKeyInfo for OpenSSL).
     *
     * @return  \OpenSSLAsymmetricKey
     */
    private static function publicKeyOf(string $point)
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        $key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");

        if ($key === false) {
            throw new \InvalidArgumentException('Not a P-256 key');
        }

        return $key;
    }

    /**
     * An ECDSA signature as OpenSSL gives it (DER) made the 64 bytes JWT wants (r, s).
     */
    private static function raw(string $der): string
    {
        $offset = 2;
        $parts  = [];

        for ($i = 0; $i < 2; $i++) {
            $length  = \ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return implode('', $parts);
    }

    public static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - \strlen($data) % 4) % 4), true);
    }
}
