<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\View\Agents;

use Gss\Component\Mcpask\Administrator\Access\Areas;
use Gss\Component\Mcpask\Administrator\Agent\AgentInterface;
use Gss\Component\Mcpask\Administrator\Agent\Agents;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Agents: the helpers that take over a conversation for one task, with how often they were
 * offered, started and completed.
 *
 * @since  0.8.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $agents = [];

    /** @var array<string, AgentInterface>  Kinds that can be set up here */
    public array $types = [];

    /** @var array<string, AgentInterface>  Every kind a plugin offers, also those that cannot work here */
    public array $known = [];

    /** @var array<int, array<string, int>> */
    public array $stats = [];

    /** @var array<int, string>  User group names, by ID */
    public array $groupNames = [];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!Areas::allows($user, 'agents')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $agents       = new Agents(Factory::getContainer()->get(DatabaseInterface::class));
        $this->agents = $agents->all();
        $this->stats  = $agents->stats(30);
        $this->types  = Agents::types();
        $this->known  = Agents::types(false);
        $db           = Factory::getContainer()->get(DatabaseInterface::class);
        $this->groupNames = array_map('strval', $db->setQuery(
            $db->getQuery(true)->select('id, title')->from($db->quoteName('#__usergroups'))
        )->loadAssocList('id', 'title'));

        ToolbarHelper::title(Text::_('COM_MCPASK_AGENTS'), 'users');
        ToolbarHelper::link('index.php?option=com_mcpask', Text::_('COM_MCPASK_SUBMENU_DASHBOARD'), 'home');

        if (Areas::allows($user, 'handoffs') && array_filter($this->agents, static fn ($agent) => $agent->type === 'livechat')) {
            ToolbarHelper::link('index.php?option=com_mcpask&view=handoffs', Text::_('COM_MCPASK_HANDOFFS'), 'comments-2');
        }

        parent::display($tpl);
    }
}
