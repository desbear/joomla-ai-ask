<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Keeps how a person answering shows themselves.
 *
 * @since  0.1.0
 */
class PersonController extends BaseController
{
    public function apply(): void
    {
        $this->keep(true);
    }

    public function save(): void
    {
        $this->keep(false);
    }

    /**
     * Make a member one of the people answering (id), from the list of those found.
     */
    public function add(): void
    {
        $this->designate(true);
    }

    /**
     * A person no longer answers visitors (id): what they did stays, under their name.
     */
    public function remove(): void
    {
        $this->designate(false);
    }

    private function designate(bool $active): void
    {
        $user = $this->app->getIdentity();
        $id   = $this->input->getInt('id');

        if (!Session::checkToken() || !($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk')) || !User::getInstance($id)->id) {
            $this->setRedirect(Route::_('index.php?option=com_askdesk&view=staff', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        (new Staff(Factory::getContainer()->get(DatabaseInterface::class), Settings::load()))->designate($id, $active);

        $this->setRedirect(
            Route::_('index.php?option=com_askdesk&view=staff', false),
            Text::sprintf($active ? 'COM_ASKDESK_STAFF_ADDED' : 'COM_ASKDESK_STAFF_REMOVED', User::getInstance($id)->name)
        );
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=staff', false));
    }

    private function keep(bool $stay): void
    {
        $user = $this->app->getIdentity();
        $id   = $this->input->getInt('id');

        if (!Session::checkToken() || !($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk')) || !User::getInstance($id)->id) {
            $this->setRedirect(Route::_('index.php?option=com_askdesk&view=staff', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $data = (array) $this->input->post->get('jform', [], 'array');
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $pet  = (string) ($data['avatar'] ?? '');

        // Only a pet that can bow to say thank you
        if ($pet !== '' && !(Staff::pets($db)[(int) $pet]['thanks'] ?? false)) {
            $this->setRedirect(Route::_('index.php?option=com_askdesk&view=person&id=' . $id, false), Text::_('COM_ASKDESK_PERSON_PET_NEEDS_THANKS'), 'error');

            return;
        }

        (new Staff(Factory::getContainer()->get(DatabaseInterface::class), Settings::load()))->setProfile(
            $id,
            (string) ($data['display_name'] ?? ''),
            (string) ($data['avatar'] ?? ''),
            (string) ($data['avatar_image'] ?? ''),
            (string) ($data['thanks_image'] ?? '')
        );

        $this->setRedirect(
            Route::_($stay ? 'index.php?option=com_askdesk&view=person&id=' . $id : 'index.php?option=com_askdesk&view=staff', false),
            Text::_('COM_ASKDESK_PERSON_SAVED')
        );
    }
}
