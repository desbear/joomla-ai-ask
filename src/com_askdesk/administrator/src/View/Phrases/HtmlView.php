<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Phrases;

use Gss\Component\Askdesk\Administrator\Desk\Phrases;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The phrases the people answering share: those pinned offered above the box in the console.
 *
 * @since  0.1.3
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $phrases = [];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->phrases = (new Phrases(Factory::getContainer()->get(DatabaseInterface::class)))->shared();

        ToolbarHelper::title(Text::_('COM_ASKDESK_PHRASES'), 'comments');
        ToolbarHelper::preferences('com_askdesk');

        parent::display($tpl);
    }
}
