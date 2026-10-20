<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\View\Pets;

use Gss\Component\Mcpask\Administrator\Pet\Pets;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The pet library: pets the floating button can be.
 *
 * @since  0.12.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $pets = [];

    /** @var array<string, array>  What the button would show of each, by choice */
    public array $shown = [];

    /** "agent" or "cleaner": which pets the page lists (0.18.0) */
    public string $kind = 'agent';

    /** @var array<string, int>  How many of each kind */
    public array $counts = [];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.admin', 'com_mcpask') && !$user->authorise('core.options', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $pets       = new Pets(Factory::getContainer()->get(DatabaseInterface::class));
        $this->kind = Factory::getApplication()->getInput()->getCmd('kind') === 'cleaner' ? 'cleaner' : 'agent';
        $all        = $pets->all();
        $this->pets = array_values(array_filter($all, fn ($pet) => Pets::kindOf($pet) === $this->kind));

        foreach (Pets::KINDS as $kind) {
            $this->counts[$kind] = \count(array_filter($all, static fn ($pet) => Pets::kindOf($pet) === $kind));
        }

        foreach ($this->pets as $pet) {
            $choice               = (string) $pet->id;
            $this->shown[$choice] = $this->kind === 'cleaner'
                ? (($cleaner = $pets->cleaner($choice)) ? ['thumb' => $cleaner['clean'], 'actions' => array_filter(['clean' => $pet->params->get('clean_image'), 'point' => $pet->params->get('point_image')])] : [])
                : ($pets->display($choice) ?? []);
        }

        ToolbarHelper::title(Text::_('COM_MCPASK_PETS'), 'image');
        ToolbarHelper::link('index.php?option=com_mcpask&view=pet&kind=' . $this->kind, Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_CLEANER_NEW' : 'COM_MCPASK_PET_NEW'), 'new');

        // Deleting the chosen ones, as in Joomla's own lists
        if ($this->pets) {
            ToolbarHelper::deleteList(Text::_('COM_MCPASK_PET_DELETE_CONFIRM'), 'pets.delete', 'JTOOLBAR_DELETE');
        }

        ToolbarHelper::link('index.php?option=com_mcpask', Text::_('COM_MCPASK_SUBMENU_DASHBOARD'), 'home');

        parent::display($tpl);
    }
}
