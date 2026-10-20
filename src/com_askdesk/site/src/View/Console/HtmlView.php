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
                'translate' => $settings->translates(),
                'browser'   => $settings->translates() && $settings->browserTranslate(),
                'library'   => \Gss\Component\Askdesk\Administrator\Desk\Library::available(),
                'languages' => $languages,
                'urls'      => ['poll' => $task('poll'), 'take' => $task('take'), 'send' => $task('message'), 'close' => $task('close'), 'status' => $task('status'), 'translate' => $task('translate'), 'reply' => $task('reply'), 'suggest' => $task('suggest'), 'history' => $task('history'), 'past' => $task('past'), 'search' => $task('search'), 'upload' => $task('upload'), 'profile' => $task('profile'), 'saveProfile' => $task('saveProfile'), 'library' => $task('library'), 'libraryAdd' => $task('libraryAdd'), 'librarySend' => $task('librarySend'), 'libraryKeep' => $task('libraryKeep'), 'endCheck' => $task('endCheck'), 'stickers' => $task('stickers')],
                'strings'   => $this->strings(),
            ];

            $assets = $document->getWebAssetManager();
            $assets->getRegistry()->addExtensionRegistryFile('com_askdesk');
            $assets->useStyle('com_askdesk.console')->useScript('com_askdesk.console');
        }

        parent::display($tpl);
    }

    /**
     * @return  array<string, string>
     */
    private function strings(): array
    {
        $keys = [
            'waiting', 'active', 'message', 'closed', 'mine', 'none', 'take', 'close', 'send', 'placeholder', 'original', 'translation',
            'translatedTo', 'untranslated', 'translateMine', 'before', 'page', 'pagesVisited', 'pagesAll', 'member', 'guest', 'email', 'online', 'busy', 'away',
            'language', 'notify', 'notifyOn', 'unread', 'back', 'taken', 'error', 'signedOut', 'expired', 'visitor', 'ai', 'pick',
            'staffNow', 'answeredBy', 'reply', 'question', 'report', 'replyPlaceholder', 'replySend', 'replyNote', 'replyOpened', 'done', 'showInbox', 'resize', 'suggestions', 'suggesting', 'noSuggestion', 'useReply', 'openPage', 'relevance', 'history', 'pastChats', 'pastQuestions', 'noHistory', 'guestHistory', 'answered', 'notAnswered', 'helpful', 'notHelpful', 'reference', 'messages', 'search', 'searchPlaceholder', 'searching', 'noResults', 'insert', 'confirmEnd', 'confirmEndNote', 'cancel', 'all', 'awaiting', 'awaitingNote', 'endedUnanswered', 'justNow', 'minutes', 'hours', 'rated', 'notRated', 'attach', 'uploading', 'expiresIn', 'expired', 'download', 'tooLarge', 'imageTooLarge', 'profile', 'displayName', 'pet', 'noPet', 'petNoThanks', 'picture', 'thanksPicture', 'save', 'saved', 'clearFace', 'library', 'libraryMine', 'libraryShared', 'libraryAdd', 'libraryEmpty', 'librarySend', 'libraryNone', 'keepMine', 'keepShared', 'kept', 'endChecking', 'endOk', 'endWait', 'endCheckFailed', 'endAnyway', 'dropHere', 'stickers', 'noStickers', 'newChat', 'uploadOwn', 'faceTile', 'faceHint', 'thanksTile', 'thanksHint', 'choosePicture', 'change', 'reachOpen', 'reachAway', 'reachOutside', 'reachClosedDay', 'reachNoOne', 'reachNoAgent', 'translating', 'draft', 'quick', 'untranslatedSent',
        ];
        $strings = [];

        foreach ($keys as $key) {
            $strings[$key] = Text::_('COM_ASKDESK_CONSOLE_' . strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key)));
        }

        return $strings;
    }
}
