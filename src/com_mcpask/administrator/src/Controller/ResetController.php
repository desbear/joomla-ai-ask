<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Controller;

use Gss\Component\Mcpask\Administrator\Ask\Reset;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Clears the records chosen on the Clear data page. Super Users only, and only once they
 * confirmed it.
 *
 * @since  0.20.4
 */
class ResetController extends BaseController
{
    public function clear(): void
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $back  = Route::_('index.php?option=com_mcpask&view=reset', false);
        $kinds = array_values(array_filter((array) $this->input->post->get('kinds', [], 'array'), 'is_string'));

        if (!$kinds || !$this->input->post->getBool('confirm')) {
            $this->setRedirect($back, Text::_('COM_MCPASK_RESET_NOTHING'), 'warning');

            return;
        }

        $cleared = (new Reset(Factory::getContainer()->get(DatabaseInterface::class)))->clear($kinds);
        $done    = [];

        foreach ($cleared as $kind => $count) {
            $done[] = Text::sprintf('COM_MCPASK_RESET_DONE_ITEM', Text::_('COM_MCPASK_RESET_' . strtoupper($kind)), $count);
        }

        Log::add('AI Ask: ' . $user->username . ' cleared ' . json_encode($cleared), Log::INFO, 'com_mcpask');
        $this->setRedirect($back, Text::sprintf('COM_MCPASK_RESET_DONE', implode('、', $done)));
    }
}
