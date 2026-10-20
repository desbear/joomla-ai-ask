<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Staff;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The people who answer visitors (those allowed to): how each shows themselves to visitors, a name
 * and a face (a pet of AI Ask, or a picture), and whether they are there now.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $people = [];

    /** @var array<int, array{name: string, face: array{src: string, still: string}|null}> */
    public array $profiles = [];

    /** @var array<int, array<string, mixed>> */
    public array $stats = [];

    public int $days = 30;

    /** What was searched for to add someone, and who was found */
    public string $search = '';

    /** @var object[] */
    public array $found = [];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $staff          = new Staff(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $this->people   = $staff->people();
        $this->profiles = $staff->profiles(array_map(static fn ($person) => (int) $person->id, $this->people));
        $this->search   = trim(Factory::getApplication()->getInput()->getString('q', ''));
        $this->found    = $this->search !== '' ? $staff->candidates($this->search) : [];
        $this->days     = \in_array(Factory::getApplication()->getInput()->getInt('days', 30), [7, 30, 90, 365], true) ? Factory::getApplication()->getInput()->getInt('days', 30) : 30;
        $this->stats    = (new \Gss\Component\Askdesk\Administrator\Desk\Rooms(Factory::getContainer()->get(DatabaseInterface::class), Settings::load()))->staffStats($this->days);

        // The busiest first; then by name
        usort($this->people, fn ($a, $b) => [($this->stats[(int) $b->id]['chats'] ?? 0) + ($this->stats[(int) $b->id]['messages'] ?? 0), $a->name] <=> [($this->stats[(int) $a->id]['chats'] ?? 0) + ($this->stats[(int) $a->id]['messages'] ?? 0), $b->name]);

        ToolbarHelper::title(Text::_('COM_ASKDESK_STAFF'), 'users');
        ToolbarHelper::preferences('com_askdesk');

        parent::display($tpl);
    }
}
