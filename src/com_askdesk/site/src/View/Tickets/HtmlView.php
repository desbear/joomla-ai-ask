<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\View\Tickets;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * The visitor's tickets (0.6.2): the page the notice's link opens. A guest gives the address they left their
 * message with and the code sent to it; a member signed in sees their own at once.
 *
 * @since  0.6.2
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    public array $config = [];

    public bool $enabled = false;

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $this->enabled = Settings::load()->tickets();

        $document = $this->getDocument();
        $document->setTitle(Text::_('COM_ASKDESK_TICKETS_TITLE'));
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->setMetaData('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover');

        if ($this->enabled) {
            $task = static fn (string $name) => Route::_('index.php?option=com_askdesk&task=tickets.' . $name . '&format=raw', false);

            // Visitors sign in at the account centre AI Ask uses, with no account here, when it has one (0.22.1)
            $class = 'Gss\\Component\\Mcpask\\Administrator\\Embed\\Cas';
            $cas   = class_exists($class) ? new $class() : null;
            $cas   = $cas && $cas->enabled() ? $cas : null;
            $login = $cas ? $cas->start(Uri::getInstance()->toString())
                : Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Uri::getInstance()->toString()));

            $this->config = [
                'token'   => Session::getFormToken(),
                'member'  => $user && !$user->guest ? (string) $user->name : '',
                'login'   => $user && !$user->guest ? '' : $login,
                'icon'    => Uri::root(true) . '/media/com_askdesk/images/icon-192.png',
                'home'    => Uri::root(),
                'urls'    => ['code' => $task('code'), 'verify' => $task('verify'), 'list' => $task('list'), 'room' => $task('room'), 'write' => $task('write'), 'out' => $task('out')],
                'strings' => ($cas && $cas->label() !== '' ? ['signIn' => $cas->label()] : []) + $this->strings(),
            ];

            $assets = $document->getWebAssetManager();
            $media  = JPATH_ROOT . '/media/com_askdesk/';
            $stamp  = static fn (string $file) => is_file($media . $file) ? (string) filemtime($media . $file) : '';

            $assets->registerAndUseStyle('com_askdesk.tickets', 'com_askdesk/tickets.css', ['version' => $stamp('css/tickets.css')]);
            $assets->registerAndUseScript('com_askdesk.tickets', 'com_askdesk/tickets.js', ['version' => $stamp('js/tickets.js')], ['defer' => true]);
        }

        parent::display($tpl);
    }

    /**
     * @return  array<string, string>
     */
    private function strings(): array
    {
        $strings = [];

        foreach ([
            'title', 'lead', 'email', 'sendCode', 'code', 'verify', 'resend', 'change', 'empty', 'open', 'answered', 'closed',
            'closedNote', 'write', 'send', 'back', 'out', 'member', 'you', 'staff', 'failed', 'signIn', 'or', 'started', 'expired',
        ] as $key) {
            $strings[$key] = Text::_('COM_ASKDESK_TICKETS_' . strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key)));
        }

        return $strings;
    }
}
