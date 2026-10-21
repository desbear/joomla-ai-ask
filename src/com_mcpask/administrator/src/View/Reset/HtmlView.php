<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\View\Reset;

use Gss\Component\Mcpask\Administrator\Ask\Reset;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Clear data: the records kept of visitors, how many of each, to clear (Super Users only).
 *
 * @since  0.20.4
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, int> */
    public array $counts = [];

    public function display($tpl = null): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->counts = (new Reset(Factory::getContainer()->get(DatabaseInterface::class)))->counts();

        ToolbarHelper::title(Text::_('COM_MCPASK_RESET'), 'trash');
        ToolbarHelper::link('index.php?option=com_mcpask', Text::_('JTOOLBAR_BACK'), 'arrow-left');

        parent::display($tpl);
    }
}
