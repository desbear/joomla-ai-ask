<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Library;
use Gss\Component\Askdesk\Administrator\Desk\Rooms;
use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The console of the people answering: what is new ({room, after}), taking a chat ({room}),
 * writing in it ({room, text}), closing it ({room}), one's status and language ({status, language}),
 * and a visitor's message in one's own language ({room, message}). Only for people allowed to
 * answer, with the form token of their session.
 *
 * @since  0.1.0
 */
class StaffController extends BaseController
{
    use Json;

    public function poll(): void
    {
        [$rooms, $staff, $me, $body] = $this->check();

        if (random_int(1, 20) === 1) {
            $rooms->tidy();
        }

        $result = [
            'me'     => ['id' => (int) $me->user_id, 'status' => $me->status, 'language' => $me->language],
            'staff'  => $staff->present(),
            'rooms'  => $rooms->inbox((int) $me->user_id),
        ];

        // Whether a visitor asking for a person now gets a chat, and why not
        try {
            $result['visitors'] = (new \Gss\Component\Askdesk\Administrator\Desk\Availability(Factory::getContainer()->get(DatabaseInterface::class), $staff))->now();
        } catch (\Throwable) {
            // Not told
        }

        $room = $this->room($rooms, $body, false);

        if ($room) {
            // Typing in one's own chat: the visitor sees dots
            if (!empty($body['typing']) && $room->status === 'active' && (int) $room->staff_id === (int) $me->user_id) {
                $rooms->typing($room, 'staff');
            }

            $result['room'] = $rooms->forStaff($room, max(0, (int) ($body['after'] ?? 0)), (int) $me->user_id, $me->language);
            $result['room']['updates'] = $rooms->updates($room, (array) ($body['pending'] ?? []), 'staff', (int) $me->user_id, $me->language);
        }

        $this->send($result);
    }

    public function take(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        if (!$rooms->take($room, (int) $me->user_id, $me->language)) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_TAKEN')]);
        }

        $this->send(['ok' => true]);
    }

    public function message(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        try {
            if ((int) ($body['sticker'] ?? 0) > 0) {
                $sticker = (new \Gss\Component\Askdesk\Administrator\Desk\Stickers(Factory::getContainer()->get(DatabaseInterface::class)))->get((int) $body['sticker'], true);

                if (!$sticker) {
                    throw new \RuntimeException(Text::_('COM_ASKDESK_STICKER_GONE'));
                }

                $this->send(['id' => $rooms->addSticker($room, 'staff', (int) $me->user_id, $sticker), 'failed' => false, 'warning' => '']);
            }

            $sent = $rooms->fromStaff($room, (int) $me->user_id, \is_string($body['text'] ?? null) ? $body['text'] : '');
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        $this->send($sent + ['warning' => $sent['failed'] ? Text::_('COM_ASKDESK_STAFF_UNTRANSLATED') : '']);
    }

    /**
     * A reply to a message left, translated, for one's own mail program ({room, text}).
     */
    public function reply(): void
    {
        [$rooms, , $me, $body] = $this->check();

        try {
            $reply = $rooms->replyByEmail($this->room($rooms, $body), (int) $me->user_id, \is_string($body['text'] ?? null) ? $body['text'] : '');
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        $this->send($reply + ['warning' => $reply['translated'] ? '' : Text::_('COM_ASKDESK_STAFF_UNTRANSLATED')]);
    }

    /**
     * Replies to suggest for the visitor's last message ({room}), in one's own language.
     */
    public function suggest(): void
    {
        [$rooms, , $me, $body] = $this->check();

        try {
            $this->send($rooms->suggestions($this->room($rooms, $body), $me->language));
        } catch (\Throwable $e) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_SUGGEST_FAILED') . (JDEBUG ? ' ' . $e->getMessage() : ''), 'items' => []]);
        }
    }

    /**
     * Searching the site's pages, for the person answering ({text}).
     */
    public function search(): void
    {
        [$rooms, , $me, $body] = $this->check();

        try {
            $this->send(['items' => $rooms->search(\is_string($body['text'] ?? null) ? $body['text'] : '', $me->language)]);
        } catch (\Throwable $e) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_SUGGEST_FAILED') . (JDEBUG ? ' ' . $e->getMessage() : ''), 'items' => []]);
        }
    }

    /**
     * What a signed-in visitor did before ({room}): their chats here, their questions to the AI.
     */
    public function history(): void
    {
        [$rooms, , , $body] = $this->check();

        $this->send($rooms->history($this->room($rooms, $body)));
    }

    /**
     * One of those chats, to read ({room}).
     */
    public function past(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $this->send($rooms->forStaff($this->room($rooms, $body), 0, (int) $me->user_id, $me->language));
    }

    /**
     * A file the person answering sends in their chat (a form: room, file).
     */
    public function upload(): void
    {
        $file = $this->uploaded();
        $user = $this->app->getIdentity();

        if (!Settings::isStaff($user) || !hash_equals(Session::getFormToken(), $this->app->getInput()->server->getString('HTTP_X_CSRF_TOKEN', ''))) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $rooms = new Rooms($db, Settings::load());
        $room  = $rooms->get($this->app->getInput()->post->getInt('room', 0));

        if (!$room || !$file || $room->status !== 'active' || (int) $room->staff_id !== (int) $user->id) {
            $this->send(['error' => Text::_($file ? 'COM_ASKDESK_STAFF_NOT_YOURS' : 'COM_ASKDESK_FILE_FAILED')]);
        }

        try {
            $shown = (new \Gss\Component\Askdesk\Administrator\Desk\Files($db))->receive($room, 'staff', (int) $user->id, $file, $rooms);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        $this->send(['file' => $shown]);
    }

    /**
     * The stickers the people answering may send ({}).
     */
    public function stickers(): void
    {
        $this->check();

        $this->send(['packs' => (new \Gss\Component\Askdesk\Administrator\Desk\Stickers(Factory::getContainer()->get(DatabaseInterface::class)))->packs(true)]);
    }

    /**
     * What other extensions show beside a chat (0.2.0), a panel each ({room}).
     */
    public function panels(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        $this->send(['panels' => \Gss\Component\Askdesk\Administrator\Desk\Panels::views($room, $me)]);
    }

    /**
     * Something done in one of those panels ({room, name, action, text?, ref?}): what it shows now.
     */
    public function panel(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room  = $this->room($rooms, $body);
        $input = [
            'text' => \is_string($body['text'] ?? null) ? mb_substr(trim($body['text']), 0, 200) : '',
            'ref'  => \is_string($body['ref'] ?? null) ? mb_substr(trim($body['ref']), 0, 100) : '',
        ];
        $view  = \Gss\Component\Askdesk\Administrator\Desk\Panels::act((string) ($body['name'] ?? ''), $room, $me, (string) ($body['action'] ?? ''), $input);

        $this->send($view === null ? ['error' => Text::_('JERROR_ALERTNOAUTHOR')] : ['panel' => $view]);
    }

    /**
     * The phrases one may put in the box: the shared ones, and one's own ({}).
     */
    public function phrases(): void
    {
        [, , $me] = $this->check();

        $this->send(['phrases' => $this->phraseBook()->forStaff((int) $me->user_id)]);
    }

    /**
     * One's own phrase kept, or changed ({id?, title, text, pinned}); all of one's phrases back.
     */
    public function phraseSave(): void
    {
        [, , $me, $body] = $this->check();

        $book  = $this->phraseBook();
        $title = \is_string($body['title'] ?? null) ? $body['title'] : '';
        $text  = \is_string($body['text'] ?? null) ? $body['text'] : '';

        try {
            if ((int) ($body['id'] ?? 0) > 0) {
                $book->update((int) $me->user_id, (int) $body['id'], $title, $text, !empty($body['pinned']));
            } else {
                $book->add((int) $me->user_id, $title, $text, !empty($body['pinned']));
            }
        } catch (\RuntimeException) {
            $this->send(['error' => Text::_('COM_ASKDESK_CONSOLE_PHRASE_FAILED')]);
        }

        $this->send(['phrases' => $book->forStaff((int) $me->user_id)]);
    }

    /**
     * One's own phrase deleted ({id}).
     */
    public function phraseDelete(): void
    {
        [, , $me, $body] = $this->check();

        $book = $this->phraseBook();
        $book->delete((int) $me->user_id, (int) ($body['id'] ?? 0));

        $this->send(['phrases' => $book->forStaff((int) $me->user_id)]);
    }

    private function phraseBook(): \Gss\Component\Askdesk\Administrator\Desk\Phrases
    {
        return new \Gss\Component\Askdesk\Administrator\Desk\Phrases(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * The library in Dropfiles: one's own files and those shared ({}).
     */
    public function library(): void
    {
        [, , $me] = $this->check();

        if (!Library::available()) {
            $this->send(['available' => false]);
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $library = new Library($db, Settings::load());

        try {
            $this->send([
                'available' => true,
                'mine'      => $library->files($library->folder((int) $me->user_id)),
                'shared'    => $library->files($library->folder(0)),
            ]);
        } catch (\Throwable) {
            $this->send(['available' => false, 'error' => Text::_('COM_ASKDESK_LIBRARY_FAILED')]);
        }
    }

    /**
     * A file put in the library (a form: folder mine or shared, and the file).
     */
    public function libraryAdd(): void
    {
        $file = $this->uploaded();
        $user = $this->app->getIdentity();

        if (!Settings::isStaff($user) || !hash_equals(Session::getFormToken(), $this->app->getInput()->server->getString('HTTP_X_CSRF_TOKEN', ''))) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
        }

        if (!$file || !Library::available()) {
            $this->send(['error' => Text::_('COM_ASKDESK_FILE_FAILED')]);
        }

        Settings::useLanguage((string) (new Staff(Factory::getContainer()->get(DatabaseInterface::class), Settings::load()))->get((int) $user->id)?->language);

        $library = new Library(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $shared  = $this->app->getInput()->post->getCmd('folder', 'mine') === 'shared';

        try {
            $this->send(['file' => $library->add($library->folder($shared ? 0 : (int) $user->id), $file, (int) $user->id)]);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }
    }

    /**
     * A file sent in a chat kept in one's library, or the shared one ({file, folder: mine|shared}).
     */
    public function libraryKeep(): void
    {
        [, , $me, $body] = $this->check();

        if (!Library::available()) {
            $this->send(['error' => Text::_('COM_ASKDESK_CONSOLE_LIBRARY_NONE')]);
        }

        try {
            $library = new Library(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
            $folder  = $library->folder(($body['folder'] ?? '') === 'shared' ? 0 : (int) $me->user_id);

            $this->send(['file' => $library->keep((int) ($body['file'] ?? 0), $folder, (int) $me->user_id), 'kept' => Text::_('COM_ASKDESK_CONSOLE_KEPT')]);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }
    }

    /**
     * A file of the library sent in one's chat ({room, file}).
     */
    public function librarySend(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        if ($room->status !== 'active' || (int) $room->staff_id !== (int) $me->user_id) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_NOT_YOURS')]);
        }

        try {
            $library = new Library(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
            $this->send(['file' => $library->send((int) ($body['file'] ?? 0), $room, (int) $me->user_id, $rooms)]);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }
    }

    /**
     * How one shows oneself: one's name, face, and the pets one may choose ({}).
     */
    public function profile(): void
    {
        [, $staff, $me] = $this->check();

        $this->send($this->profileOf($staff, (int) $me->user_id));
    }

    /**
     * Keep how one shows oneself (a form: display_name, avatar, perhaps a picture or a thank-you picture).
     */
    public function saveProfile(): void
    {
        $this->uploaded();
        $user = $this->app->getIdentity();

        if (!Settings::isStaff($user) || !hash_equals(Session::getFormToken(), $this->app->getInput()->server->getString('HTTP_X_CSRF_TOKEN', ''))) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $staff   = new Staff($db, Settings::load());
        $post    = $this->app->getInput()->post;
        $current = $staff->profile((int) $user->id);
        $pet     = $post->getString('avatar', '');
        $image   = $pet === '' ? $current['avatar_image'] : '';
        $thanks  = $pet === '' ? $current['thanks_image'] : '';

        // Only a pet that can bow to say thank you
        if ($pet !== '' && !(Staff::pets($db)[(int) $pet]['thanks'] ?? false)) {
            $this->send(['error' => Text::_('COM_ASKDESK_CONSOLE_PET_NEEDS_THANKS')]);
        }

        try {
            foreach (['picture' => 'image', 'thanks' => 'thanks'] as $field => $which) {
                $upload = $_FILES[$field] ?? null;

                if (\is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $path = Staff::keepPicture((int) $user->id, ['name' => (string) $upload['name'], 'tmp_name' => (string) $upload['tmp_name'], 'size' => (int) $upload['size'], 'error' => (int) $upload['error']]);
                    $pet  = '';

                    if ($which === 'image') {
                        $image = $path;
                    } else {
                        $thanks = $path;
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

        if ($post->getInt('clear', 0)) {
            $image = $thanks = $pet = '';
        }

        $staff->setProfile((int) $user->id, $post->getString('display_name', ''), $pet, $image, $thanks);
        $this->send($this->profileOf($staff, (int) $user->id));
    }

    private function profileOf(Staff $staff, int $userId): array
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $kept    = $staff->profile($userId);
        $shown   = $staff->profiles([$userId])[$userId] ?? ['name' => '', 'face' => null];
        $pets    = [];

        foreach (Staff::pets($db) as $id => $pet) {
            $pets[] = ['id' => (string) $id, 'title' => $pet['title'], 'thanks' => $pet['thanks'], 'thumb' => $pet['thumb']];
        }

        return [
            'name'    => $shown['name'],
            'account' => (string) \Joomla\CMS\User\User::getInstance($userId)->name,
            'display' => $kept['display_name'],
            'avatar'  => $kept['avatar'],
            'picture' => $kept['avatar'] === '' && $kept['avatar_image'] !== '' ? ($shown['face']['src'] ?? '') : '',
            'thanksPicture' => $kept['avatar'] === '' && $kept['thanks_image'] !== '' ? ($shown['face']['thanks'] ?? '') : '',
            'face'    => $shown['face'],
            'pets'    => $pets,
        ];
    }

    /**
     * Before one's chat is ended: whether it looks done, by AI ({room}). Advice only.
     */
    public function endCheck(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        if ($room->status !== 'active') {
            $this->send(['ok' => true, 'reason' => '']);
        }

        try {
            $result = (new \Gss\Component\Askdesk\Administrator\Desk\EndCheck(Factory::getContainer()->get(DatabaseInterface::class)))->check($room, (string) $me->language);
        } catch (\Throwable) {
            $this->send(['error' => Text::_('COM_ASKDESK_CONSOLE_END_CHECK_FAILED')]);
        }

        $this->send(['ok' => $result['ok'], 'reason' => $result['reason'] !== '' ? $result['reason'] : Text::_($result['ok'] ? 'COM_ASKDESK_CONSOLE_END_OK' : 'COM_ASKDESK_CONSOLE_END_NO_REPLY')]);
    }

    public function close(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $rooms->closeByStaff($this->room($rooms, $body), (int) $me->user_id);
        $this->send(['ok' => true]);
    }

    public function status(): void
    {
        [, $staff, $me, $body] = $this->check();

        $status   = \is_string($body['status'] ?? null) ? $body['status'] : null;
        $language = \is_string($body['language'] ?? null) ? $body['language'] : null;
        $me       = $staff->set((int) $me->user_id, $status, $language);

        $this->send(['me' => ['id' => (int) $me->user_id, 'status' => $me->status, 'language' => $me->language]]);
    }

    public function translate(): void
    {
        [$rooms, , $me, $body] = $this->check();

        try {
            $text = $rooms->translateFor($this->room($rooms, $body), (int) ($body['message'] ?? 0), $me->language);
        } catch (\Throwable) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_TRANSLATE_FAILED')]);
        }

        $this->send(['text' => $text]);
    }

    /**
     * @return  array{0: Rooms, 1: Staff, 2: object, 3: array}
     */
    private function check(): array
    {
        $body = $this->body();
        $user = $this->app->getIdentity();

        if (!Settings::isStaff($user)) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR'), 'signedOut' => !$user || $user->guest], 403);
        }

        $token = $this->app->getInput()->server->getString('HTTP_X_CSRF_TOKEN', '');

        if (!hash_equals(Session::getFormToken(), $token)) {
            $this->send(['error' => Text::_('JINVALID_TOKEN_NOTICE'), 'expired' => true], 403);
        }

        $settings = Settings::load();
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $staff    = new Staff($db, $settings);

        // The console is open: its person is there
        $me = $staff->touch((int) $user->id);

        // What the desk says (who joined, errors) in the language they work in
        Settings::useLanguage($me->language);

        // A visitor's page waits only while a request runs: the console's are short
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return [new Rooms($db, $settings), $staff, $me, $body];
    }

    private function room(Rooms $rooms, array $body, bool $required = true): ?object
    {
        $id   = (int) ($body['room'] ?? 0);
        $room = $id > 0 ? $rooms->get($id) : null;

        if (!$room && $required) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_NO_ROOM')]);
        }

        return $room;
    }
}
