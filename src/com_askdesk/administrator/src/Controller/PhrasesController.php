<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Phrases;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The shared phrases: added, changed (title, words, pinned, offered), moved, deleted.
 *
 * @since  0.1.3
 */
class PhrasesController extends BaseController
{
    public function add(): void
    {
        if (!$this->allowed()) {
            return;
        }

        try {
            $this->phrases()->add(0, $this->input->getString('title', ''), (string) $this->input->get('text', '', 'raw'), $this->input->getBool('pinned'));
            $this->back(Text::_('COM_ASKDESK_PHRASES_ADDED'));
        } catch (\RuntimeException) {
            $this->back(Text::_('COM_ASKDESK_PHRASES_EMPTY'), 'warning');
        }
    }

    public function save(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $phrases = $this->phrases();

        foreach ((array) $this->input->get('phrases', [], 'array') as $id => $values) {
            $values = (array) $values;

            try {
                $phrases->update(0, (int) $id, (string) ($values['title'] ?? ''), (string) ($values['text'] ?? ''), !empty($values['pinned']), !empty($values['published']));
            } catch (\RuntimeException) {
                // No words: left as it was
            }
        }

        $this->back(Text::_('COM_ASKDESK_PHRASES_SAVED'));
    }

    public function move(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $this->phrases()->move(0, $this->input->getInt('id'), $this->input->getInt('by'));
        $this->back();
    }

    public function delete(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $this->phrases()->delete(0, $this->input->getInt('id'));
        $this->back(Text::_('COM_ASKDESK_PHRASES_DELETED'));
    }

    private function allowed(): bool
    {
        $user = $this->app->getIdentity();

        if (Session::checkToken() && ($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk'))) {
            return true;
        }

        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=phrases', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

        return false;
    }

    private function back(string $message = '', string $type = 'message'): void
    {
        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=phrases', false), $message ?: null, $type);
    }

    private function phrases(): Phrases
    {
        return new Phrases(Factory::getContainer()->get(DatabaseInterface::class));
    }
}
