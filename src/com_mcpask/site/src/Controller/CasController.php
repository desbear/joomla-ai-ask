<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Site\Controller;

use Gss\Component\Mcpask\Administrator\Embed\Cas;
use Gss\Component\Mcpask\Administrator\Embed\Visitor;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Signing in at an account centre over CAS without an account here (0.22.1): to the account centre's login
 * ({return}), back with a ticket the server checks there, and out again. Only ever back to a page of this site.
 *
 * @since  0.22.1
 */
class CasController extends BaseController
{
    public function login(): void
    {
        $cas    = $this->cas();
        $return = $this->returned();

        // Signed in already (here, or there): straight back
        if (Visitor::current() || !$this->app->getIdentity()->guest) {
            $this->app->redirect($return);
        }

        $this->app->redirect($cas->loginUrl($cas->service($return)));
    }

    public function back(): void
    {
        $cas    = $this->cas();
        $return = $this->returned();
        $ticket = $this->app->getInput()->getString('ticket', '');
        $who    = $ticket !== '' ? $cas->validate($ticket, $cas->service($return)) : null;

        if ($who && $this->app->getIdentity()->guest) {
            Visitor::keep($who['email'], $who['name'], 'cas');
        } elseif (!$who) {
            $this->app->enqueueMessage(Text::_('COM_MCPASK_CAS_FAILED'), 'warning');
        }

        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        $this->app->redirect($return);
    }

    public function logout(): void
    {
        $return = $this->returned();

        if (Session::checkToken('get')) {
            Visitor::forget();
        }

        $this->app->redirect($return);
    }

    private function cas(): Cas
    {
        $cas = new Cas();

        if (!$cas->enabled()) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        return $cas;
    }

    /**
     * The page to go back to: one of this site's, else its home page.
     */
    private function returned(): string
    {
        $return = (string) base64_decode($this->app->getInput()->getBase64('return', ''), true);
        $root   = rtrim(Uri::root(), '/') . '/';

        return $return !== '' && str_starts_with($return, $root) && Uri::isInternal($return) ? strtok($return, '#') : $root;
    }
}
