<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Dashboard;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Where the desk stands: whether what it needs is there (AI Ask, its agent, an agent set up, people
 * allowed to answer), who is there now, the chats open, and the way to the console.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, bool> */
    public array $checks = [];

    /** @var array<int, array{id: int, name: string, status: string}> */
    public array $present = [];

    /** @var array<string, int> */
    public array $counts = [];

    public string $console = '';

    /**
     * Closed chats archived (0.6.2): when it last ran, how many, what went wrong, whether it is late, where
     *
     * @var array{last: ?array, late: bool, folder: string, problem: string, due: int}
     */
    public array $archive = ['last' => null, 'late' => true, 'folder' => '', 'problem' => '', 'due' => 0];

    /** @var array{count: int, dimensions: array<string, array<string, mixed>>, comments: array<int, array<string, string>>} */
    public array $ratings = ['count' => 0, 'dimensions' => [], 'comments' => []];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.manage', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $settings = Settings::load();

        $agents = 0;

        try {
            $agents = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mcpask_agents'))->where('type = ' . $db->quote('askdesk'))->where('published = 1'))->loadResult();
        } catch (\Throwable) {
            // AI Ask is not there
        }

        $this->checks = [
            'mcpask'    => ComponentHelper::isEnabled('com_mcpask') && class_exists('Gss\\Component\\Mcpask\\Administrator\\Ask\\Translator'),
            'plugin'    => PluginHelper::isEnabled('mcpask', 'askdesk'),
            'agent'     => $agents > 0,
            'translate' => $settings->translates(),
        ];

        $this->present = (new Staff($db, $settings))->present();

        $archive = new \Gss\Component\Askdesk\Administrator\Desk\Archive($db, $settings);
        $last    = $archive->last();
        $folder  = $archive->folder($problem);

        $this->archive = [
            'last'    => $last,
            // Not run for two days: said so
            'late'    => !$last || strtotime($last['time']) < time() - 2 * 86400,
            'folder'  => $folder !== '' ? $folder : $settings->archiveFolder(),
            'problem' => (string) $problem,
            'due'     => (int) $db->setQuery(
                $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__askdesk_rooms'))
                    ->where('settled IS NOT NULL')->where('archived_at IS NULL')
                    ->where('settled < ' . $db->quote(Factory::getDate('-' . $settings->archiveDays() . ' days')->toSql()))
            )->loadResult(),
        ];

        foreach ($db->setQuery($db->getQuery(true)->select('status, COUNT(*) AS n')->from($db->quoteName('#__askdesk_rooms'))->group('status'))->loadObjectList() as $row) {
            $this->counts[(string) $row->status] = (int) $row->n;
        }

        $this->ratings = (new \Gss\Component\Askdesk\Administrator\Desk\Rooms($db, $settings))->ratingStats(30);

        // An address of the site's front end, from the back end
        $this->console = preg_replace('#/administrator/?$#', '/', Uri::root()) . 'index.php?option=com_askdesk&view=console&tmpl=component';

        ToolbarHelper::title(Text::_('COM_ASKDESK'), 'headset');

        if ($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk')) {
            ToolbarHelper::preferences('com_askdesk');
        }

        parent::display($tpl);
    }
}
