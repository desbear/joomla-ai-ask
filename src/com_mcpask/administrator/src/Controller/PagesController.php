<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Controller;

use Gss\Component\Mcpask\Administrator\Ask\Ignores;
use Gss\Component\Mcpask\Administrator\Ask\IndexedPages;
use Gss\Component\Mcpask\Administrator\Ask\Indexer;
use Gss\Component\Mcpask\Administrator\Ask\ManualPages;
use Gss\Component\Mcpask\Administrator\Ask\ServiceException;
use Gss\Component\Mcpask\Administrator\Ask\Settings;
use Gss\Component\Mcpask\Administrator\Ask\SuppliedTexts;
use Gss\Component\Mcpask\Administrator\Ask\Source\Sources;
use Gss\Component\Mcpask\Administrator\Ask\WebSync;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * What the administrator does on the page of indexed pages: add pages by their address, read a
 * page again, see its passages, ignore pages and answer from them again, and edit the patterns of
 * addresses not to answer from.
 *
 * @since  0.16.0
 */
class PagesController extends BaseController
{
    /** Seconds the added pages are read for right away; the rest are read at the next synchronisation. */
    private const READ_SECONDS = 20;

    /**
     * Add addresses, one per line, and read them right away as far as time allows.
     */
    public function add(): void
    {
        $this->check();

        $db       = $this->db();
        $settings = Settings::load();
        $pages    = new IndexedPages($db, $settings);
        $lines    = preg_split('/\R/u', (string) $this->input->post->get('urls', '', 'raw')) ?: [];
        $result   = (new ManualPages($db))->add($lines, (string) $this->input->post->getString('selector', ''), (int) $this->app->getIdentity()->id, [$pages, 'indexedAs']);

        if ($result['added']) {
            $this->app->enqueueMessage(Text::plural('COM_MCPASK_PAGES_ADDED', \count($result['added'])), 'success');

            try {
                (new WebSync($db, Settings::load()))->run(self::READ_SECONDS);
            } catch (\Throwable $e) {
                $this->app->enqueueMessage($e->getMessage(), 'warning');
            }
        }

        foreach ($result['known'] as $url => $as) {
            $this->app->enqueueMessage(Text::sprintf($as !== '' ? 'COM_MCPASK_PAGES_KNOWN_AS' : 'COM_MCPASK_PAGES_KNOWN', $url, $as), 'info');
        }

        if ($result['invalid']) {
            $this->app->enqueueMessage(Text::sprintf('COM_MCPASK_PAGES_INVALID', implode(', ', \array_slice($result['invalid'], 0, 10))), 'warning');
        }

        if ($result['full']) {
            $this->app->enqueueMessage(Text::sprintf('COM_MCPASK_PAGES_FULL', ManualPages::MAX), 'warning');
        }

        $this->back('manual');
    }

    /**
     * Remove added pages: they are taken out of the index at the next reading.
     */
    public function remove(): void
    {
        $this->check();

        $ids = array_map('intval', (array) $this->input->post->get('cid', [], 'array'));

        if ($ids) {
            (new ManualPages($this->db()))->remove($ids);

            // The sites that are left are read, and those with no pages left are forgotten
            try {
                (new WebSync($this->db(), Settings::load()))->run(5);
            } catch (\Throwable) {
                // Done at the next synchronisation
            }

            $this->app->enqueueMessage(Text::plural('COM_MCPASK_PAGES_REMOVED', \count($ids)), 'success');
        }

        $this->back('manual');
    }

    /**
     * Read one indexed page again, now.
     */
    public function reread(): void
    {
        $this->check();

        $db       = $this->db();
        $settings = Settings::load();
        $row      = (new IndexedPages($db, $settings))->get($this->input->post->getInt('id', 0));

        if (!$row) {
            $this->back();

            return;
        }

        try {
            if ($row->kind === 'own') {
                $source   = Sources::own($db, $settings)[$row->source] ?? null;
                $document = $source?->load((int) $row->item_id);
                $outcome  = $document ? (new Indexer($db, $settings))->index($document) : 'failed';
                $why      = '';
            } elseif ($row->kind === 'peer') {
                [$outcome, $why] = ['peer', ''];
            } else {
                ['outcome' => $outcome, 'why' => $why] = (new WebSync($db, $settings))->rereadPage($row->source, (int) $row->item_id, (string) $row->link);
            }

            $this->app->enqueueMessage(
                Text::sprintf('COM_MCPASK_PAGES_REREAD_' . strtoupper($outcome), $row->title, $why),
                \in_array($outcome, ['indexed', 'unchanged'], true) ? 'success' : 'warning'
            );
        } catch (ServiceException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->back();
    }

    /**
     * Read a file of a source of files now: to see at once whether it is answered from, and why not.
     */
    public function readfile(): void
    {
        $this->check();

        $db       = $this->db();
        $settings = Settings::load();
        $name     = $this->input->post->getCmd('source', '');
        $id       = $this->input->post->getInt('item', 0);
        $source   = Sources::own($db, $settings)[$name] ?? null;

        try {
            $document = $source?->load($id);
            $outcome  = $document ? (new Indexer($db, $settings))->index($document) : 'unreadable';

            // Not read at all, or read without text: gone from the index
            if (!$document) {
                (new Indexer($db, $settings))->remove($name, $id);
            }

            $this->app->enqueueMessage(
                Text::sprintf('COM_MCPASK_FILES_READ_' . strtoupper($outcome), $document ? $document->title : '#' . $id),
                \in_array($outcome, ['indexed', 'unchanged'], true) ? 'success' : 'warning'
            );
        } catch (ServiceException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->app->enqueueMessage(Text::sprintf('COM_MCPASK_FILES_READ_FAILED', $e->getMessage()), 'error');
        }

        $this->back('files');
    }

    /**
     * Keep the text given for a file (or any page of this site), and index it again with it.
     */
    public function text(): void
    {
        $this->check();

        $db       = $this->db();
        $settings = Settings::load();
        $name     = $this->input->post->getCmd('source', '');
        $id       = $this->input->post->getInt('item', 0);
        $source   = Sources::own($db, $settings)[$name] ?? null;

        if ($source === null || $id <= 0) {
            $this->back('files');

            return;
        }

        $text = (string) $this->input->post->get('text', '', 'raw');

        (new SuppliedTexts($db))->save($name, $id, $text, $this->input->post->getCmd('mode', 'add'), (int) $this->app->getIdentity()->id);
        $this->app->enqueueMessage(Text::_(trim($text) === '' ? 'COM_MCPASK_FILES_TEXT_REMOVED' : 'COM_MCPASK_FILES_TEXT_SAVED'), 'success');

        // Index it again now, with its text
        $this->input->post->set('item', $id);
        $this->readfile();
    }

    /**
     * Ignore indexed pages from now on.
     */
    public function ignore(): void
    {
        $this->check();

        $db       = $this->db();
        $settings = Settings::load();
        $pages    = new IndexedPages($db, $settings);
        $ignores  = new Ignores($db, $settings);
        $note     = (string) $this->input->post->getString('note', '');
        $count    = 0;

        foreach (array_map('intval', (array) $this->input->post->get('cid', [], 'array')) as $id) {
            if ($row = $pages->get($id)) {
                $ignores->add((string) $row->source, (int) $row->item_id, (string) $row->title, (string) $row->url, $note, (int) $this->app->getIdentity()->id);
                $count++;
            }
        }

        if ($count) {
            $this->app->enqueueMessage(Text::plural('COM_MCPASK_PAGES_IGNORED', $count), 'success');
        }

        $this->back();
    }

    /**
     * Answer from ignored pages again: they are read at the next synchronisation.
     */
    public function restore(): void
    {
        $this->check();

        $ignores = new Ignores($this->db(), Settings::load());
        $count   = 0;

        foreach (array_map('intval', (array) $this->input->post->get('cid', [], 'array')) as $id) {
            $count += $ignores->restore($id) ? 1 : 0;
        }

        if ($count) {
            $this->app->enqueueMessage(Text::plural('COM_MCPASK_PAGES_RESTORED', $count), 'success');
        }

        $this->back('ignored');
    }

    /**
     * Keep the patterns of addresses not to answer from, and take what they match out of the index.
     */
    public function patterns(): void
    {
        $this->check();

        $lines = preg_split('/\R/u', (string) $this->input->post->get('patterns', '', 'raw')) ?: [];
        $text  = implode("\n", \array_slice(array_values(array_filter(array_map(static fn ($line) => mb_substr(trim($line), 0, 500), $lines), 'strlen')), 0, 200));
        $db     = $this->db();
        $params = ComponentHelper::getParams('com_mcpask');

        $params->set('ignore_urls', $text);
        $paramsText = $params->toString();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_mcpask'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->bind(':params', $paramsText)
        );
        $db->execute();

        $pages   = new IndexedPages($db, Settings::load());
        $removed = (new Ignores($db, Settings::load()))->purge(static fn ($row) => $pages->address($row));

        $this->app->enqueueMessage(Text::plural('COM_MCPASK_PAGES_PATTERNS_SAVED', $removed), 'success');

        $this->back('ignored');
    }

    /**
     * The passages of a page, as JSON: what the AI service is given of it.
     */
    public function passages(): void
    {
        if (!Session::checkToken('get') || !$this->app->getIdentity()->authorise('core.manage', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $passages = (new IndexedPages($this->db(), Settings::load()))->passages($this->input->getInt('id', 0));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['passages' => $passages], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->app->close();
    }

    private function check(): void
    {
        $user = $this->app->getIdentity();

        if (!Session::checkToken() || (!$user->authorise('core.admin', 'com_mcpask') && !$user->authorise('core.options', 'com_mcpask'))) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function back(string $tab = ''): void
    {
        // The page as it was: its tab, filters and place in the list
        $keep = array_filter([
            'tab'        => $tab ?: $this->input->post->getCmd('tab', ''),
            'search'     => $this->input->post->getString('search', ''),
            'kind'       => $this->input->post->getString('kind', ''),
            'status'     => $this->input->post->getCmd('status', ''),
            'thin'       => $this->input->post->getInt('thin', 0) ?: null,
            'limitstart' => $this->input->post->getInt('limitstart', 0) ?: null,
        ], static fn ($value) => $value !== null && $value !== '');

        $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pages' . ($keep ? '&' . http_build_query($keep) : ''), false));
    }

    private function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
