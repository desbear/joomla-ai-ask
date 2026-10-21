<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\View\Questions;

use Gss\Component\Mcpask\Administrator\Ask\Settings;
use Gss\Component\Mcpask\Administrator\Ask\UsageLog;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Every question of the log, as long as it is kept, a page at a time and filtered: by days, where
 * it was asked (this site, or another website through an embed key), how it ended, its rating, its
 * marks, who asked, and words.
 *
 * @since  0.20.3
 */
class HtmlView extends BaseHtmlView
{
    public const PER_PAGE = 50;

    /** @var object[] */
    public array $rows = [];

    public int $total = 0;

    /** @var array<string, string> */
    public array $filter = [];

    /** @var array<int, string>  Embed keys by ID: their names */
    public array $embeds = [];

    public ?Pagination $pagination = null;

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $input = $app->getInput();
        $day   = static fn (string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
        $pick  = static fn (string $value, array $allowed) => \in_array($value, $allowed, true) ? $value : '';

        try {
            $this->embeds = array_map('strval', $db->setQuery(
                $db->getQuery(true)->select('id, title')->from($db->quoteName('#__mcpask_embeds'))->order('title')
            )->loadAssocList('id', 'title'));
        } catch (\Throwable) {
            $this->embeds = [];
        }

        $source = $input->getString('source', '');

        $this->filter = [
            'from'   => $day($input->getString('from', '')),
            'to'     => $day($input->getString('to', '')),
            'source' => $source === 'site' || (preg_match('/^embed:(\d+)$/', $source, $match) && isset($this->embeds[(int) $match[1]])) ? $source : '',
            'status' => $pick($input->getCmd('status', ''), [...UsageLog::STATUSES, 'unanswered']),
            'rating' => $pick($input->getCmd('rating', ''), ['good', 'bad', 'none']),
            'flag'   => $pick($input->getCmd('flag', ''), UsageLog::FLAGS),
            'who'    => $pick($input->getCmd('who', ''), ['member', 'guest']),
            'search' => mb_substr(trim($input->getString('search', '')), 0, 100),
        ];

        $start                      = max(0, $input->getInt('limitstart', 0));
        [$this->rows, $this->total] = (new UsageLog($db, Settings::load(), (string) $app->get('secret')))->search($this->filter, self::PER_PAGE, $start);
        $this->pagination           = new Pagination($this->total, $start, self::PER_PAGE);

        foreach ($this->filter as $name => $value) {
            if ($value !== '') {
                $this->pagination->setAdditionalUrlParam($name, $value);
            }
        }

        ToolbarHelper::title(Text::_('COM_MCPASK_QUESTIONS_ALL'), 'comments');
        ToolbarHelper::link('index.php?option=com_mcpask&view=dashboard', Text::_('COM_MCPASK_SUBMENU_DASHBOARD'), 'dashboard');

        parent::display($tpl);
    }
}
