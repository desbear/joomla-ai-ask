<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Rooms;
use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Cases left open closed (結案) together from the back end (0.6.2), by whoever does it.
 *
 * @since  0.6.2
 */
class CasesController extends BaseController
{
    public function settle(): void
    {
        $back = Route::_('index.php?option=com_askdesk&view=cases&age=' . $this->input->getInt('age', 30), false);
        $user = $this->app->getIdentity();

        if (!Session::checkToken() || !$user->authorise('core.manage', 'com_askdesk')) {
            $this->setRedirect($back, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $ids   = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $this->input->get('cid', [], 'array'))))), 0, 500);
        $rooms = new Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $done  = 0;
        $kept  = 0;

        foreach ($ids as $id) {
            $room = $rooms->get($id);

            // Only what is left to be dealt with: a chat going on is not closed from here
            if (!$room || $room->settled !== null || !\in_array($room->status, ['message', 'closed'], true)) {
                $kept++;

                continue;
            }

            try {
                $rooms->settle($room, (int) $user->id);
                $done++;
            } catch (\Throwable) {
                $kept++;
            }
        }

        $this->setRedirect($back, Text::sprintf('COM_ASKDESK_CASES_SETTLED_N', $done) . ($kept ? ' ' . Text::sprintf('COM_ASKDESK_CASES_KEPT_N', $kept) : ''), $kept ? 'warning' : 'message');
    }
}
