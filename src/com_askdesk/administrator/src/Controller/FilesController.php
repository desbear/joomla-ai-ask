<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Files;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The files sent in chats: one opened (id), or deleted before its days are over (id).
 *
 * @since  0.1.0
 */
class FilesController extends BaseController
{
    public function download(): void
    {
        $files = new Files(Factory::getContainer()->get(DatabaseInterface::class));
        $id    = $this->input->getInt('id');
        $file  = $files->byIds([$id])[$id] ?? null;

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_askdesk') || !$file || !is_file(Files::folder() . '/' . basename((string) $file->saved_as))) {
            $this->setRedirect(Route::_('index.php?option=com_askdesk&view=files', false), Text::_('COM_ASKDESK_FILES_GONE'), 'error');

            return;
        }

        Files::send($file);
    }

    public function delete(): void
    {
        $id = $this->input->getInt('id');

        if (!Session::checkToken() || !$this->app->getIdentity()->authorise('core.delete', 'com_askdesk')) {
            $this->setRedirect(Route::_('index.php?option=com_askdesk&view=files', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $deleted = (new Files(Factory::getContainer()->get(DatabaseInterface::class)))->delete($id);

        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=files', false), Text::_($deleted ? 'COM_ASKDESK_FILES_DELETED' : 'COM_ASKDESK_FILES_GONE'), $deleted ? 'message' : 'warning');
    }
}
