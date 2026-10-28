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

        // Who answers now, with the name and face they show (stacked in the console beside whether
        // visitors can chat)
        $present  = $staff->present();
        $profiles = $staff->profiles(array_column($present, 'id'));
        $present  = array_map(static fn ($person) => array_merge($person, [
            'name' => $profiles[$person['id']]['name'] ?? $person['name'],
            'face' => $profiles[$person['id']]['face']['src'] ?? '',
        ]), $present);

        // The list of the tab looked at, as far as it was scrolled; with what goes on now, whatever the tab
        $inbox  = $rooms->inbox((int) $me->user_id, (string) ($body['list'] ?? 'all'), (int) ($body['limit'] ?? 30), (string) ($body['sort'] ?? ''));
        $result = [
            'me'     => ['id' => (int) $me->user_id, 'status' => $me->status, 'language' => $me->language],
            'staff'  => $present,
            'rooms'  => $inbox['rooms'],
            'counts' => $inbox['counts'],
            'more'   => $inbox['more'],
            'live'   => $inbox['live'],
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
            // Its work cards changed (0.6.1): asked for again, so others' come in too
            $result['room']['works'] = \Gss\Component\Askdesk\Administrator\Desk\Works::stamp((int) $room->id);
        }

        $this->send($result);
    }

    public function take(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        // No one's yet; or someone's who is not here, taken over (0.6.2)
        if (!$rooms->take($room, (int) $me->user_id, $me->language) && !$rooms->takeOver($room, (int) $me->user_id, $me->language)) {
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
     * A reply to a message left ({room, text}): kept in the ticket and the visitor told, or (tickets off)
     * translated for one's own mail program.
     */
    public function reply(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $text = \is_string($body['text'] ?? null) ? $body['text'] : '';

        try {
            // Kept as a ticket (0.6.2): the visitor told by the site that there is an answer, nothing for one's mail program
            $reply = Settings::load()->tickets()
                ? ['ticket' => true] + $rooms->replyTicket($this->room($rooms, $body), (int) $me->user_id, $text)
                : $rooms->replyByEmail($this->room($rooms, $body), (int) $me->user_id, $text);
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
     * Something done in one of those panels ({room, name, action, text?, ref?, note?, choices?}): what it shows now.
     */
    public function panel(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room  = $this->room($rooms, $body);
        $input = [
            'text' => \is_string($body['text'] ?? null) ? mb_substr(trim($body['text']), 0, 200) : '',
            'ref'  => \is_string($body['ref'] ?? null) ? mb_substr(trim($body['ref']), 0, 100) : '',
            'note'    => \is_string($body['note'] ?? null) ? mb_substr(trim($body['note']), 0, 4000) : '',
            'choices' => \is_array($body['choices'] ?? null) ? \array_slice(array_values(array_filter(array_map(static fn ($choice) => \is_string($choice) ? mb_substr(trim($choice), 0, 100) : '', $body['choices']))), 0, 60) : [],
            // A panel's form as filled (0.6.1): its fields' values by key
            'values'  => self::formValues($body['values'] ?? null),
        ];
        $view  = \Gss\Component\Askdesk\Administrator\Desk\Panels::act((string) ($body['name'] ?? ''), $room, $me, (string) ($body['action'] ?? ''), $input);

        $this->send($view === null ? ['error' => Text::_('JERROR_ALERTNOAUTHOR')] : ['panel' => $view]);
    }

    /**
     * Work in a chat (0.6.1, {room}): the forms to pick from, its cards.
     */
    public function works(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);

        $this->send([
            'forms' => \Gss\Component\Askdesk\Administrator\Desk\Works::forms($room, $me),
            'works' => \Gss\Component\Askdesk\Administrator\Desk\Works::of($room),
            'stamp' => \Gss\Component\Askdesk\Administrator\Desk\Works::stamp((int) $room->id),
        ]);
    }

    /**
     * A work card started ({room, panel, form}) or something done in one ({room, id, action: fill,
     * submit, drop, values?, note?: how the person answering wants it filled}): the card as it is now,
     * and whether the chat's cards changed.
     */
    public function work(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $room = $this->room($rooms, $body);
        $id   = (int) ($body['id'] ?? 0);
        $card = $id
            ? \Gss\Component\Askdesk\Administrator\Desk\Works::act($room, $me, $id, (string) ($body['action'] ?? ''), ['values' => self::formValues($body['values'] ?? null), 'note' => \is_string($body['note'] ?? null) ? mb_substr(trim($body['note']), 0, 2000) : ''])
            : \Gss\Component\Askdesk\Administrator\Desk\Works::start($room, $me, (string) ($body['panel'] ?? ''), (string) ($body['form'] ?? ''));

        if (!$card && !($id && ($body['action'] ?? '') === 'drop')) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')]);
        }

        $this->send([
            'work'  => $card,
            'stamp' => \Gss\Component\Askdesk\Administrator\Desk\Works::stamp((int) $room->id),
        ]);
    }

    /**
     * This browser to be notified, even with the console closed ({endpoint, p256dh, auth}): Web Push.
     */
    public function pushSubscribe(): void
    {
        [, , $me, $body] = $this->check();

        try {
            \Gss\Component\Askdesk\Administrator\Desk\Push::make()->subscribe(
                (int) $me->user_id,
                \is_string($body['endpoint'] ?? null) ? mb_substr($body['endpoint'], 0, 2000) : '',
                \is_string($body['p256dh'] ?? null) ? $body['p256dh'] : '',
                \is_string($body['auth'] ?? null) ? $body['auth'] : ''
            );
        } catch (\InvalidArgumentException) {
            $this->send(['error' => Text::_('COM_ASKDESK_CONSOLE_ERROR')]);
        }

        $this->send(['ok' => true]);
    }

    /**
     * This browser no longer notified ({endpoint}).
     */
    public function pushForget(): void
    {
        [, , $me, $body] = $this->check();

        \Gss\Component\Askdesk\Administrator\Desk\Push::make()->forget(\is_string($body['endpoint'] ?? null) ? $body['endpoint'] : '', (int) $me->user_id);
        $this->send(['ok' => true]);
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

    /**
     * A guest's identity confirmed by the person answering, having asked them ({room, confirmed}, 0.6.2): what is
     * known of someone with their e-mail is shown as theirs from then on (a CRM's customer, their records).
     */
    public function identity(): void
    {
        [$rooms, , $me, $body] = $this->check();

        $rooms->confirmIdentity($this->room($rooms, $body), (int) $me->user_id, !empty($body['confirmed']));

        $this->send(['ok' => true]);
    }

    /**
     * Closes (結案) a chat that ended unanswered, or a message left, once dealt with.
     */
    public function settle(): void
    {
        [$rooms, , $me, $body] = $this->check();

        try {
            $rooms->settle($this->room($rooms, $body), (int) $me->user_id);
        } catch (\RuntimeException $e) {
            $this->send(['error' => $e->getMessage()]);
        }

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
    /**
     * One's dashboard (0.5.0): its widgets, and the sources they can draw from with whether one can use
     * them ({}).
     */
    public function board(): void
    {
        [, $staff, $me] = $this->check();

        // Whom the dashboard can be narrowed to: oneself, and (when others' cases may be seen) each of
        // the people answering
        $own    = Settings::load()->keptToOwn($this->app->getIdentity());
        $names  = $own ? [] : $staff->names(array_map(static fn ($p) => (int) $p->id, $staff->people()));
        $people = [];

        foreach ($names as $id => $name) {
            if ((int) $id !== (int) $me->user_id) {
                $people[] = ['id' => (int) $id, 'name' => (string) $name];
            }
        }

        $this->send([
            'widgets' => \Gss\Component\Askdesk\Administrator\Desk\Boards::make()->forPerson((int) $me->user_id, $this->templater()),
            'sources' => \Gss\Component\Askdesk\Administrator\Desk\Sources::list($me),
            'people'  => $people,
            'others'  => !$own,
        ]);
    }

    /**
     * One's dashboard kept ({widgets}): what was kept.
     */
    public function boardSave(): void
    {
        [, , $me, $body] = $this->check();

        // The template's widgets stay (a Super User may take them off their own board)
        $this->send(['widgets' => \Gss\Component\Askdesk\Administrator\Desk\Boards::make()->savePerson((int) $me->user_id, $body['widgets'] ?? [], $this->templater())]);
    }

    /**
     * A Super User's board saved as the dashboard template for everyone.
     */
    public function boardTemplate(): void
    {
        [, , $me, $body] = $this->check();

        if (!$this->templater()) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
        }

        $boards = \Gss\Component\Askdesk\Administrator\Desk\Boards::make();
        $boards->saveTemplate($body['widgets'] ?? []);

        $this->send(['widgets' => $boards->savePerson((int) $me->user_id, $body['widgets'] ?? [], true)]);
    }

    /**
     * A panel form's values: text by key (keys of letters, digits, _ and -), 40 at most.
     *
     * @return  array<string, string>
     */
    private static function formValues(mixed $values): array
    {
        $out = [];

        foreach (\is_array($values) ? \array_slice($values, 0, 40, true) : [] as $key => $value) {
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $key) && \is_scalar($value)) {
                $out[(string) $key] = mb_substr(trim((string) $value), 0, 4000);
            }
        }

        return $out;
    }

    /** Whether this person sets the dashboard template (given it in the desk's permissions; Super Users) */
    private function templater(): bool
    {
        return Settings::may($this->app->getIdentity(), 'askdesk.template');
    }

    /**
     * A source's templates one can see ({source, query?, page?}).
     */
    public function sourceTemplates(): void
    {
        [, , $me, $body] = $this->check();

        $this->fromSource($body, fn ($source) => \Gss\Component\Askdesk\Administrator\Desk\Sources::templates($source, $me, \is_string($body['query'] ?? null) ? $body['query'] : '', (int) ($body['page'] ?? 1)));
    }

    /**
     * A template's fields ({source, template}).
     */
    public function sourceFields(): void
    {
        [, , $me, $body] = $this->check();

        $this->fromSource($body, fn ($source) => \Gss\Component\Askdesk\Administrator\Desk\Sources::fields($source, $me, $this->templateOf($body)));
    }

    /**
     * A page of a template's records ({source, template, page?, size?, query?, mine?}).
     */
    public function sourceRows(): void
    {
        [, , $me, $body] = $this->check();

        $this->fromSource($body, fn ($source) => \Gss\Component\Askdesk\Administrator\Desk\Sources::rows($source, $me, $this->templateOf($body), [
            'page'  => (int) ($body['page'] ?? 1),
            'size'  => (int) ($body['size'] ?? 100),
            'query' => \is_string($body['query'] ?? null) ? $body['query'] : '',
            'mine'  => !empty($body['mine']),
            // Another person's (not when others' cases may not be seen)
            'person' => Settings::load()->keptToOwn($this->app->getIdentity()) ? 0 : max(0, (int) ($body['person'] ?? 0)),
            'from'  => \is_string($body['from'] ?? null) ? $body['from'] : '',
            'to'    => \is_string($body['to'] ?? null) ? $body['to'] : '',
            // Only those changed since (a table checking for others' changes, 0.6.2)
            'since' => \is_string($body['since'] ?? null) ? $body['since'] : '',
        ]));
    }

    /**
     * One value of a record changed ({source, template, row, field, value}): the record after.
     */
    public function sourceUpdate(): void
    {
        [, , $me, $body] = $this->check();

        $value = $body['value'] ?? '';
        $value = \is_array($value) ? \array_slice(array_values(array_filter($value, 'is_scalar')), 0, 50) : (\is_scalar($value) ? $value : '');

        $this->fromSource($body, function ($source) use ($me, $body, $value) {
            $row = \is_scalar($body['row'] ?? null) ? mb_substr((string) $body['row'], 0, 100) : '';
            $got = \Gss\Component\Askdesk\Administrator\Desk\Sources::row($source->update($me, $this->templateOf($body), $row, \is_scalar($body['field'] ?? null) ? mb_substr((string) $body['field'], 0, 100) : '', $value));

            $this->told($source, $body, $row, (int) $me->user_id);

            return ['row' => $got];
        });
    }

    /**
     * What others changed from the dashboard after a point ({after, watch: [{source, template}]}, 0.6.2): which
     * records, so a table showing one reads it again (as this person); and the point to ask from next.
     */
    public function sourceChanges(): void
    {
        [, , $me, $body] = $this->check();

        $this->send((new \Gss\Component\Askdesk\Administrator\Desk\Changes(Factory::getContainer()->get(DatabaseInterface::class)))->after(
            (int) ($body['after'] ?? -1),
            \is_array($body['watch'] ?? null) ? $body['watch'] : [],
            (int) $me->user_id
        ));
    }

    /** A record changed from the dashboard told to the others looking at it (0.6.2) */
    private function told(object $source, array $body, string $row, int $userId, bool $deleted = false): void
    {
        if ($row !== '') {
            (new \Gss\Component\Askdesk\Administrator\Desk\Changes(Factory::getContainer()->get(DatabaseInterface::class)))->note($source->name(), $this->templateOf($body), $row, $userId, $deleted);
        }
    }

    /**
     * Something done to a record from the dashboard ({source, template, row, action}, 0.6.2): by a source that
     * offers it (act(), its fields' actions); the desk's cases closed (結案) together, say.
     */
    public function sourceAct(): void
    {
        [, , $me, $body] = $this->check();

        $this->fromSource($body, function ($source) use ($me, $body) {
            $action = \is_string($body['action'] ?? null) ? $body['action'] : '';

            if (!method_exists($source, 'act') || !preg_match('/^[a-z][a-z0-9_]{0,29}$/', $action)) {
                throw new \RuntimeException('Not done here', 403);
            }

            $row = \is_scalar($body['row'] ?? null) ? mb_substr((string) $body['row'], 0, 100) : '';
            $got = \Gss\Component\Askdesk\Administrator\Desk\Sources::row($source->act($me, $this->templateOf($body), $row, $action));

            $this->told($source, $body, $row, (int) $me->user_id);

            return ['row' => $got];
        });
    }

    /**
     * A record deleted from the dashboard ({source, template, row}, 0.6.1): by a source that can (remove()).
     */
    public function sourceDelete(): void
    {
        [, , $me, $body] = $this->check();

        $this->fromSource($body, function ($source) use ($me, $body) {
            if (!method_exists($source, 'remove')) {
                throw new \RuntimeException('Not deleted here', 403);
            }

            $row = \is_scalar($body['row'] ?? null) ? mb_substr((string) $body['row'], 0, 100) : '';

            $source->remove($me, $this->templateOf($body), $row);
            $this->told($source, $body, $row, (int) $me->user_id, true);

            return ['ok' => true];
        });
    }

    /**
     * A record's page of the source's own, for a frame ({source, template, row}; row '' for a new one, 0.6.5).
     * What it sends the page to start may carry the person's sign-in there: answered not to be kept (no-store).
     */
    public function sourceEmbed(): void
    {
        [, , $me, $body] = $this->check();

        $this->fromSource($body, fn ($source) => \Gss\Component\Askdesk\Administrator\Desk\Sources::embed(
            $source,
            $me,
            $this->templateOf($body),
            \is_scalar($body['row'] ?? null) ? mb_substr((string) $body['row'], 0, 100) : ''
        ));
    }

    /**
     * What a source answers, or why it can't: sign in first ({signin: where}), or what went wrong.
     */
    private function fromSource(array $body, callable $work): never
    {
        $source = \Gss\Component\Askdesk\Administrator\Desk\Sources::get(\is_string($body['source'] ?? null) ? $body['source'] : '');

        if (!$source) {
            $this->send(['error' => Text::_('JERROR_ALERTNOAUTHOR')], 404);
        }

        try {
            $this->send($work($source));
        } catch (\Gss\Component\Askdesk\Administrator\Desk\SignInNeeded $e) {
            $this->send(['signin' => $e->connect, 'error' => Text::sprintf('COM_ASKDESK_CONSOLE_SOURCE_SIGNIN', $source->title())]);
        } catch (\Throwable $e) {
            // Refused as not this person's to change (403), or under review in its system (423): said so, so
            // the dashboard greys the record (0.6.1)
            $locked = match ((int) $e->getCode()) {
                403     => 'denied',
                423     => 'review',
                default => '',
            };

            $this->send(['error' => Text::sprintf('COM_ASKDESK_CONSOLE_SOURCE_ERROR', $source->title()), 'detail' => mb_substr($e->getMessage(), 0, 200)] + ($locked !== '' ? ['locked' => $locked] : []));
        }
    }

    private function templateOf(array $body): string
    {
        return \is_scalar($body['template'] ?? null) ? mb_substr(trim((string) $body['template']), 0, 100) : '';
    }

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

        $rooms = new Rooms($db, $settings);

        // Others' chats not to be seen (as set): this person's and no one's only
        if ($settings->keptToOwn($user)) {
            $rooms->restrictTo((int) $user->id);
        }

        return [$rooms, $staff, $me, $body];
    }

    private function room(Rooms $rooms, array $body, bool $required = true): ?object
    {
        $id   = (int) ($body['room'] ?? 0);
        $room = $id > 0 ? $rooms->get($id) : null;

        // Another's chat this person may not see: as if there were none
        if ($room && !$rooms->visible($room)) {
            $room = null;
        }

        if (!$room && $required) {
            $this->send(['error' => Text::_('COM_ASKDESK_STAFF_NO_ROOM')]);
        }

        return $room;
    }
}
