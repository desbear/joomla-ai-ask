<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Stickers;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The stickers: added (pictures, in a pack), changed (words, pack, for whom, offered), moved, deleted.
 *
 * @since  0.1.0
 */
class StickersController extends BaseController
{
    public function upload(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $stickers = $this->stickers();
        $files    = $this->input->files->get('files', [], 'raw');
        $files    = isset($files['name']) ? [$files] : (array) $files;
        $added    = 0;
        $errors   = [];

        foreach (\array_slice($files, 0, 50) as $file) {
            if (!\is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $stickers->add($file, $this->input->getString('pack', ''), '', $this->input->getCmd('who', 'all'));
                $added++;
            } catch (\RuntimeException $e) {
                $errors[] = (string) ($file['name'] ?? '') . ': ' . $e->getMessage();
            }
        }

        foreach ($errors as $error) {
            $this->app->enqueueMessage($error, 'warning');
        }

        $this->back(Text::plural('COM_ASKDESK_STICKERS_N_ADDED', $added));
    }

    public function save(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $stickers = $this->stickers();

        foreach ((array) $this->input->get('stickers', [], 'array') as $id => $values) {
            $values = (array) $values;
            $stickers->update((int) $id, (string) ($values['pack'] ?? ''), (string) ($values['title'] ?? ''), (string) ($values['who'] ?? 'all'), !empty($values['published']));
        }

        $this->back(Text::_('COM_ASKDESK_STICKERS_SAVED'));
    }

    public function move(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $this->stickers()->move($this->input->getInt('id'), $this->input->getInt('by'));
        $this->back();
    }

    public function delete(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $this->stickers()->delete($this->input->getInt('id'));
        $this->back(Text::_('COM_ASKDESK_STICKERS_DELETED'));
    }

    private function allowed(): bool
    {
        $user = $this->app->getIdentity();

        if (Session::checkToken() && ($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk'))) {
            return true;
        }

        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=stickers', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

        return false;
    }

    private function back(string $message = ''): void
    {
        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=stickers', false), $message ?: null);
    }

    private function stickers(): Stickers
    {
        return new Stickers(Factory::getContainer()->get(DatabaseInterface::class));
    }
}
