<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\View\Pages;

use Gss\Component\Mcpask\Administrator\Ask\Ignores;
use Gss\Component\Mcpask\Administrator\Ask\IndexedPages;
use Gss\Component\Mcpask\Administrator\Ask\ManualPages;
use Gss\Component\Mcpask\Administrator\Ask\Settings;
use Gss\Component\Mcpask\Administrator\Ask\WebSync;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Every page the answers come from, as the index holds it: where it comes from, its passages and
 * how it stands; pages added by their address; and pages not to answer from.
 *
 * @since  0.16.0
 */
class HtmlView extends BaseHtmlView
{
    public const PER_PAGE = 50;

    /** "pages", "manual" or "ignored" */
    public string $tab = 'pages';

    /** @var object[] */
    public array $rows = [];

    public int $total = 0;

    /** @var array{search: string, kind: string, status: string, thin: bool} */
    public array $filter = [];

    /** @var array<string, int>  Pages by kind */
    public array $counts = [];

    public ?Pagination $pagination = null;

    /** @var object[]  Added pages, each with whether it is indexed (doc: id, title, chunks, status) */
    public array $manual = [];

    /** @var object[]  Single pages ignored */
    public array $ignored = [];

    public string $patterns = '';

    public bool $canEdit = false;

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db            = Factory::getContainer()->get(DatabaseInterface::class);
        $settings      = Settings::load();
        $input         = $app->getInput();
        $pages         = new IndexedPages($db, $settings);
        $this->canEdit = $user->authorise('core.admin', 'com_mcpask') || $user->authorise('core.options', 'com_mcpask');
        $this->tab     = \in_array($input->getCmd('tab'), ['manual', 'ignored'], true) ? $input->getCmd('tab') : 'pages';
        $this->counts  = $pages->counts();
        $this->filter  = [
            'search' => trim($input->getString('search', '')),
            'kind'   => \in_array($input->getCmd('kind'), IndexedPages::KINDS, true) ? $input->getCmd('kind') : '',
            'status' => \in_array($input->getCmd('status'), ['ok', 'pending'], true) ? $input->getCmd('status') : '',
            'thin'   => (bool) $input->getInt('thin', 0),
        ];

        if ($this->tab === 'pages') {
            $start                      = max(0, $input->getInt('limitstart', 0));
            [$this->rows, $this->total] = $pages->list($this->filter, self::PER_PAGE, $start);
            $this->pagination           = new Pagination($this->total, $start, self::PER_PAGE);

            foreach (['search', 'kind', 'status'] as $name) {
                if ($this->filter[$name] !== '') {
                    $this->pagination->setAdditionalUrlParam($name, $this->filter[$name]);
                }
            }

            if ($this->filter['thin']) {
                $this->pagination->setAdditionalUrlParam('thin', '1');
            }
        } elseif ($this->tab === 'manual') {
            $this->manual = $this->manual($db);
        } else {
            $this->ignored  = array_values((new Ignores($db, $settings))->items());
            $this->patterns = implode("\n", preg_split('/\R/u', (string) $settings->raw('ignore_urls')) ?: []);
        }

        ToolbarHelper::title(Text::_('COM_MCPASK_PAGES'), 'list');
        ToolbarHelper::link('index.php?option=com_mcpask&view=dashboard', Text::_('COM_MCPASK_SUBMENU_DASHBOARD'), 'dashboard');

        parent::display($tpl);
    }

    /**
     * The added pages, newest first, each with its page in the index when it was read.
     *
     * @return  object[]
     */
    private function manual(DatabaseInterface $db): array
    {
        $rows = (new ManualPages($db))->all();
        $keys = [];

        foreach ($rows as $row) {
            $host       = strtolower((string) parse_url((string) $row->url, PHP_URL_HOST));
            $row->source = WebSync::PREFIX . ManualPages::SITE . substr(sha1($host), 0, 9);
            $row->itemId = WebSync::itemId((string) $row->url);
            $row->doc    = null;
            $keys[]      = $row->itemId;
        }

        if ($keys) {
            $docs = $db->setQuery(
                $db->getQuery(true)
                    ->select('id, source, item_id, title, chunks, status, checked')
                    ->from($db->quoteName('#__mcpask_documents'))
                    ->where('source LIKE ' . $db->quote(WebSync::PREFIX . ManualPages::SITE . '%'))
                    ->whereIn('item_id', array_values(array_unique($keys)))
            )->loadObjectList();

            foreach ($rows as $row) {
                foreach ($docs as $doc) {
                    if ($doc->source === $row->source && (int) $doc->item_id === $row->itemId) {
                        $row->doc = $doc;
                    }
                }
            }
        }

        return $rows;
    }
}
