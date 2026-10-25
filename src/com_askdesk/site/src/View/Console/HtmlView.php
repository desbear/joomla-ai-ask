<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\View\Console;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * The console of the people answering: the chats waiting and answered, one chat open, and one's
 * status. A page of the site's front end, so that it works on a phone; only for people allowed to
 * answer. Its script talks with the desk (StaffController).
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    public array $config = [];

    public bool $allowed = false;

    public bool $signedIn = false;

    public string $login = '';

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $this->signedIn = $user && !$user->guest;
        $this->allowed  = Settings::isStaff($user);

        // In the language this person chose to work in
        if ($this->allowed) {
            Settings::useLanguage((new Staff(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), Settings::load()))->get((int) $user->id)->language);
        }
        $this->login    = Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Uri::getInstance()->toString()));

        $document = $this->getDocument();
        $document->setLanguage(strtolower($app->getLanguage()->getTag()));
        $document->setTitle(Text::_('COM_ASKDESK_CONSOLE'));
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->setMetaData('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover');

        if ($this->allowed) {
            $settings = Settings::load();
            $profile  = (new Staff(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), $settings))->profiles([(int) $user->id])[(int) $user->id] ?? ['name' => (string) $user->name, 'face' => null];
            $task     = static fn (string $name) => Route::_('index.php?option=com_askdesk&task=staff.' . $name . '&format=raw', false);
            // Routed as the console is (a bare index.php may be sent on to a language's address, and a
            // worker's script may not be); its reach widened to the whole site by the header it comes with
            $pwa      = static fn (string $name) => Route::_('index.php?option=com_askdesk&task=pwa.' . $name . '&format=raw', false);
            $languages = [];

            foreach (LanguageHelper::getKnownLanguages(JPATH_SITE) as $tag => $language) {
                $languages[] = ['tag' => $tag, 'name' => (string) ($language['nativeName'] ?? $language['name'] ?? $tag)];
            }

            $this->config = [
                'token'     => Session::getFormToken(),
                'me'        => (int) $user->id,
                'meName'    => $profile['name'],
                'meFace'    => $profile['face']['src'] ?? '',
                'root'      => rtrim(Uri::root(), '/'),
                'sound'     => $settings->sound(),
                // The desk's own cards beside a chat not shown, as set (0.6.1)
                'sideHidden' => $settings->sideHidden(),
                'translate' => $settings->translates(),
                'browser'   => $settings->translates() && $settings->browserTranslate(),
                'library'   => \Gss\Component\Askdesk\Administrator\Desk\Library::available(),
                'languages' => $languages,
                'urls'      => ['poll' => $task('poll'), 'take' => $task('take'), 'send' => $task('message'), 'close' => $task('close'), 'settle' => $task('settle'), 'status' => $task('status'), 'translate' => $task('translate'), 'reply' => $task('reply'), 'suggest' => $task('suggest'), 'history' => $task('history'), 'past' => $task('past'), 'search' => $task('search'), 'upload' => $task('upload'), 'profile' => $task('profile'), 'saveProfile' => $task('saveProfile'), 'library' => $task('library'), 'libraryAdd' => $task('libraryAdd'), 'librarySend' => $task('librarySend'), 'libraryKeep' => $task('libraryKeep'), 'endCheck' => $task('endCheck'), 'stickers' => $task('stickers'), 'phrases' => $task('phrases'), 'phraseSave' => $task('phraseSave'), 'phraseDelete' => $task('phraseDelete'), 'panels' => $task('panels'), 'panel' => $task('panel'), 'works' => $task('works'), 'work' => $task('work'), 'pushSubscribe' => $task('pushSubscribe'), 'pushForget' => $task('pushForget'), 'board' => $task('board'), 'boardSave' => $task('boardSave'), 'boardTemplate' => $task('boardTemplate'), 'sourceTemplates' => $task('sourceTemplates'), 'sourceFields' => $task('sourceFields'), 'sourceRows' => $task('sourceRows'), 'sourceUpdate' => $task('sourceUpdate'), 'sourceDelete' => $task('sourceDelete'), 'worker' => $pwa('worker')],
                'push'      => \Gss\Component\Askdesk\Administrator\Desk\Push::possible() ? \Gss\Component\Askdesk\Administrator\Desk\Push::make()->publicKey() : '',
                'scope'     => Uri::root(true) . '/',
                'logo'      => $settings->logo(),
                'strings'   => $this->strings(),
                'board'     => $this->board(),
                'preview'   => $this->preview(),
            ];

            // An app on a phone's home screen, or a computer's: its manifest, icon, colour, name
            $images = Uri::root(true) . '/media/com_askdesk/images/';

            $here = Uri::getInstance();

            $document->addHeadLink($pwa('manifest') . '&start=' . rawurlencode($here->getPath() . ($here->getQuery() ? '?' . $here->getQuery() : '')), 'manifest');
            $document->addHeadLink($images . 'apple-touch-icon.png', 'apple-touch-icon');
            $document->setMetaData('theme-color', '#3b1fc9');
            $document->setMetaData('mobile-web-app-capable', 'yes');
            $document->setMetaData('apple-mobile-web-app-capable', 'yes');
            // Opened from the home screen, the status bar is see-through: the console's colour runs up under it
            $document->setMetaData('apple-mobile-web-app-status-bar-style', 'black-translucent');
            $document->setMetaData('apple-mobile-web-app-title', Text::_('COM_ASKDESK_PWA_SHORT'));

            $assets = $document->getWebAssetManager();
            $assets->getRegistry()->addExtensionRegistryFile('com_askdesk');

            // Asked for anew whenever the files change (an update installed), not kept from before by the browser
            $media = JPATH_ROOT . '/media/com_askdesk/';
            $stamp = static fn (string $file) => is_file($media . $file) ? (string) filemtime($media . $file) : '';

            $assets->registerAndUseStyle('com_askdesk.console', 'com_askdesk/console.css', ['version' => $stamp('css/console.css')]);
            $assets->registerAndUseScript('com_askdesk.console', 'com_askdesk/console.js', ['version' => $stamp('js/console.js')], ['defer' => true]);
        }

        parent::display($tpl);
    }

    /**
     * @return  array<string, string>
     */
    /**
     * The dashboard (0.5.1): its script and styles, loaded by the console only when no chat is open, and
     * the language it speaks. Their versions are when they were built, so a new build is fetched again.
     *
     * @return  array<string, string>|null
     */
    private function preview(): ?array
    {
        // The viewer of documents in a chat (0.6.1): a small script fetched when one is first opened, each kind's
        // library after it from the same place; versioned by when it was built
        $media = JPATH_ROOT . '/media/com_askdesk/';

        if (!is_file($media . 'js/preview.js')) {
            return null;
        }

        $base = Uri::root(true) . '/media/com_askdesk/';

        return ['script' => $base . 'js/preview.js?' . filemtime($media . 'js/preview.js'), 'base' => $base, 'version' => (string) filemtime($media . 'js/preview.js')];
    }

    private function board(): ?array
    {
        $media = JPATH_ROOT . '/media/com_askdesk/';

        if (!is_file($media . 'js/board.js') || !is_file($media . 'css/board.css')) {
            return null;
        }

        $base = Uri::root(true) . '/media/com_askdesk/';

        return [
            'script'   => $base . 'js/board.js?' . filemtime($media . 'js/board.js'),
            'style'    => $base . 'css/board.css?' . filemtime($media . 'css/board.css'),
            'language' => Factory::getApplication()->getLanguage()->getTag(),
            // Setting the dashboard template for everyone: for those given it (Super Users have it)
            'templater' => \Gss\Component\Askdesk\Administrator\Desk\Settings::may(Factory::getApplication()->getIdentity(), 'askdesk.template'),
        ];
    }

    private function strings(): array
    {
        $keys = [
            'waiting', 'active', 'message', 'closed', 'mine', 'none', 'take', 'close', 'send', 'placeholder', 'original', 'translation',
            'translatedTo', 'untranslated', 'translateMine', 'before', 'page', 'pagesVisited', 'pagesAll', 'remove', 'phrases', 'phrasesMore', 'phrasesAll', 'phrasesShared', 'phrasesMine', 'phrasesSearch', 'phrasesNone', 'phraseSaveMine', 'phraseSaved', 'phrasePin', 'phraseDelete', 'phraseFailed', 'panelSearch', 'panelChoose', 'panelRefresh', 'panelError', 'panelSetup', 'panelNoteDraft', 'panelNoteRedraft', 'panelNoteSend', 'panelNotePlaceholder', 'panelNoteWriting', 'panelNoteChoose', 'panelListAll', 'panelListClose', 'workButton', 'workPick', 'workPickHint', 'workOnly', 'workDrop', 'workNone', 'workBy', 'workExpand', 'workClose', 'workNote', 'workNoteSend', 'workHere', 'workGoChat', 'edgeOpen', 'edgeNews', 'listOrder', 'panelTagsMore', 'panelTagsLess', 'homeTitle', 'homeEmpty', 'homeSources', 'homeConnect', 'homeConnected', 'homeManage', 'homeNotReady', 'homeNone', 'homeWidgets', 'pushOn', 'pushOff', 'pushInstall', 'pushDenied', 'pushFailed', 'notifications', 'doneOn', 'member', 'guest', 'email', 'online', 'busy', 'away',
            'language', 'notify', 'notifyOn', 'unread', 'back', 'taken', 'error', 'signedOut', 'expired', 'visitor', 'ai', 'pick',
            'staffNow', 'answeredBy', 'reply', 'question', 'report', 'replyPlaceholder', 'replySend', 'replyNote', 'replyOpened', 'done', 'showInbox', 'resize', 'suggestions', 'suggesting', 'noSuggestion', 'useReply', 'openPage', 'relevance', 'history', 'pastChats', 'pastQuestions', 'noHistory', 'guestHistory', 'answered', 'notAnswered', 'helpful', 'notHelpful', 'reference', 'messages', 'search', 'searchPlaceholder', 'searching', 'noResults', 'insert', 'confirmEnd', 'confirmEndNote', 'cancel', 'all', 'awaiting', 'awaitingNote', 'endedUnanswered', 'unhandled', 'unhandledNote', 'settled', 'settledNote', 'settle', 'settleOn', 'settledAt', 'settledBy', 'loadingMore', 'emptyList', 'showSettled', 'sortRecent', 'sortOldest', 'sortCreated', 'justNow', 'minutes', 'hours', 'rated', 'notRated', 'attach', 'uploading', 'expiresIn', 'expired', 'download', 'tooLarge', 'imageTooLarge', 'profile', 'displayName', 'pet', 'noPet', 'petNoThanks', 'picture', 'thanksPicture', 'save', 'saved', 'clearFace', 'library', 'libraryMine', 'libraryShared', 'libraryAdd', 'libraryEmpty', 'librarySend', 'libraryNone', 'keepMine', 'keepShared', 'kept', 'endChecking', 'endOk', 'endWait', 'endCheckFailed', 'endAnyway', 'dropHere', 'stickers', 'noStickers', 'newChat', 'uploadOwn', 'faceTile', 'faceHint', 'thanksTile', 'thanksHint', 'choosePicture', 'change', 'reachOpen', 'reachAway', 'reachOutside', 'reachClosedDay', 'reachNoOne', 'reachNoAgent', 'translating', 'draft', 'quick', 'untranslatedSent', 'preview', 'previewLoading', 'previewFailed', 'previewTooLarge', 'previewPage', 'previewTruncated', 'previewClose', 'attachPick', 'attachLimits',
        ];
        $strings = [];

        foreach ($keys as $key) {
            $strings[$key] = Text::_('COM_ASKDESK_CONSOLE_' . strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key)));
        }

        return $strings;
    }
}
