<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Files;
use Gss\Component\Askdesk\Administrator\Desk\Rooms;
use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The visitor's side of a chat, for AI Ask's window: what is new ({key, after}), a message
 * ({key, text}), leaving ({key}). The room is the one of the key the agent gave the page.
 *
 * @since  0.1.0
 */
class ChatController extends BaseController
{
    use Json;

    /** The embed the request came through, when it did. */
    private ?object $embed = null;

    /**
     * An address the page gives as where it is: only of this site, or, embedded, of a website the
     * embed allows; anything else is not taken.
     */
    private function pageOf(mixed $value): string
    {
        $url   = \is_string($value) ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? ''), 0, 500) : '';
        $parts = parse_url($url);

        if (!preg_match('#^https?://#i', $url) || !\is_string($parts['host'] ?? null)) {
            return '';
        }

        // This site, by any name it answers to: the request's own and the one configured (its Live Site),
        // with or without "www."
        $bare  = static fn ($host) => preg_replace('/^www\./', '', strtolower(trim((string) $host)));
        $hosts = array_filter([
            \Joomla\CMS\Uri\Uri::getInstance()->getHost(),
            parse_url(\Joomla\CMS\Uri\Uri::root(), PHP_URL_HOST),
        ]);

        if (\in_array($bare($parts['host']), array_map($bare, $hosts), true)) {
            return $url;
        }

        $origin = strtolower($parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        $embeds = 'Gss\\Component\\Mcpask\\Administrator\\Embed\\Embeds';

        return $this->embed && class_exists($embeds) && $embeds::allows($this->embed, $origin) ? $url : '';
    }

    public function poll(): void
    {
        [$rooms, $room, $body] = $this->room();

        // Now and then: chats whose visitor is gone are closed, old ones forgotten
        if (random_int(1, 20) === 1) {
            $rooms->tidy();
        }

        // The page the visitor is on now: of this site, or of a website the chat is embedded in
        $rooms->visiting($room, $this->pageOf($body['page'] ?? null), \is_string($body['title'] ?? null) ? strip_tags($body['title']) : '');

        // The visitor is typing: the people answering see dots
        if (!empty($body['typing']) && \in_array($room->status, ['waiting', 'active'], true)) {
            $rooms->typing($room, 'visitor');
        }

        // Messages seen still being translated, translated since; and whether the page may translate
        // for the moment by itself
        $this->send($rooms->forVisitor($room, max(0, (int) ($body['after'] ?? 0))) + [
            'updates' => $rooms->updates($room, (array) ($body['pending'] ?? []), 'visitor'),
            'browser' => Settings::load()->browserTranslate(),
        ]);
    }

    /**
     * The stickers visitors may send ({key}).
     */
    public function stickers(): void
    {
        $this->room();

        $this->send(['packs' => (new \Gss\Component\Askdesk\Administrator\Desk\Stickers(Factory::getContainer()->get(DatabaseInterface::class)))->packs(false)]);
    }

    public function message(): void
    {
        [$rooms, $room, $body] = $this->room();

        try {
            // A sticker for visitors, or words
            if ((int) ($body['sticker'] ?? 0) > 0) {
                $sticker = (new \Gss\Component\Askdesk\Administrator\Desk\Stickers(Factory::getContainer()->get(DatabaseInterface::class)))->get((int) $body['sticker'], false);

                if (!$sticker) {
                    throw new \RuntimeException(Text::_('COM_ASKDESK_STICKER_GONE'));
                }

                $this->send(['id' => $rooms->addSticker($room, 'visitor', 0, $sticker)]);
            }

            $id = $rooms->fromVisitor($room, \is_string($body['text'] ?? null) ? $body['text'] : '');
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage(), 'gone' => !\in_array($room->status, ['waiting', 'active'], true)]);
        }

        $this->send(['id' => $id]);
    }

    public function leave(): void
    {
        [$rooms, $room] = $this->room();

        $rooms->visitorLeft($room);

        // What the visitor is asked now that it ended
        $this->send(['ok' => true, 'rating' => $rooms->ratingFor($rooms->get((int) $room->id) ?? $room)]);
    }

    /**
     * The visitor's rating of the chat that ended ({key, values, comment}).
     */
    public function rate(): void
    {
        [$rooms, $room, $body] = $this->room();

        try {
            $rooms->rate($room, \is_array($body['values'] ?? null) ? $body['values'] : [], \is_string($body['comment'] ?? null) ? $body['comment'] : '');
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        $this->send(['ok' => true]);
    }

    /**
     * A file the visitor sends (a form: key, file).
     */
    public function upload(): void
    {
        $file = $this->uploaded();
        $this->visitorIdentity();

        $rooms = new Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $room  = $rooms->byKey($this->app->getInput()->post->getString('key', ''));

        if (!$room) {
            $this->send(['error' => Text::_('COM_ASKDESK_CHAT_GONE'), 'gone' => true]);
        }

        if (!$file) {
            $this->send(['error' => Text::_('COM_ASKDESK_FILE_FAILED')]);
        }

        try {
            $shown = (new Files(Factory::getContainer()->get(DatabaseInterface::class)))->receive($room, 'visitor', 0, $file, $rooms);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        $this->send(['file' => $shown]);
    }

    /**
     * A file of a chat, by its token (f): a picture or video to show, a document to download.
     */
    public function file(): void
    {
        $file = (new Files(Factory::getContainer()->get(DatabaseInterface::class)))->byToken($this->app->getInput()->getString('f', ''));

        if (!$file) {
            http_response_code(404);
            $this->app->close();
        }

        Files::send($file);
    }

    private function visitorIdentity(): void
    {
        $embed = 'Gss\\Component\\Mcpask\\Administrator\\Embed\\Request';

        if (class_exists($embed)) {
            $this->embed = $embed::apply($this->app);
        }
    }

    /**
     * @return  array{0: Rooms, 1: object, 2: array}
     */
    private function room(): array
    {
        $body = $this->body();

        // Embedded on another website: the visitor its token stands for (AI Ask)
        $embed = 'Gss\\Component\\Mcpask\\Administrator\\Embed\\Request';

        if (class_exists($embed)) {
            $this->embed = $embed::apply($this->app);
        }

        $rooms = new Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $room  = $rooms->byKey(\is_string($body['key'] ?? null) ? $body['key'] : '');

        if (!$room) {
            $this->send(['error' => Text::_('COM_ASKDESK_CHAT_GONE'), 'gone' => true]);
        }

        return [$rooms, $room, $body];
    }
}
