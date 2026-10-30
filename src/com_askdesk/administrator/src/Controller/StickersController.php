<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Stickers;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The stickers: added (pictures, in a pack, made WebPs if asked), changed (words, pack, for whom, offered), put in
 * order, made WebPs, deleted; the packs (0.6.8): put in order, changed, offered or deleted as a whole, one of their
 * stickers shown for them. A pack's page is saved as Joomla's edit pages are (apply, save, cancel); what is ticked
 * there is acted on by the toolbar, what was changed on the page kept first.
 *
 * @since  0.1.0
 */
class StickersController extends BaseController
{
    public function upload(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $stickers = $this->stickers();
        $files    = $this->input->files->get('files', [], 'raw');
        $files    = isset($files['name']) ? [$files] : (array) $files;
        $added    = 0;
        $errors   = [];

        foreach (\array_slice($files, 0, 50) as $file) {
            if (!\is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $stickers->add($file, $this->input->getString('pack', ''), '', $this->input->getCmd('who', 'all'), $this->input->getBool('webp'));
                $added++;
            } catch (\RuntimeException $e) {
                $errors[] = (string) ($file['name'] ?? '') . ': ' . $e->getMessage();
            }
        }

        foreach ($errors as $error) {
            $this->app->enqueueMessage($error, 'warning');
        }

        $this->back(Text::plural('COM_ASKDESK_STICKERS_N_ADDED', $added));
    }

    /**
     * A pack's page saved, staying on it (0.6.8): the pack as a whole, its stickers, their order.
     */
    public function apply(): void
    {
        if ($this->allowed()) {
            $this->keep();
            $this->back(Text::_('COM_ASKDESK_STICKERS_SAVED'));
        }
    }

    /**
     * A pack's page saved, back to the packs.
     */
    public function save(): void
    {
        if ($this->allowed()) {
            $this->keep();
            $this->setRedirect($this->packs(), Text::_('COM_ASKDESK_STICKERS_SAVED'));
        }
    }

    public function cancel(): void
    {
        $this->setRedirect($this->packs());
    }

    /**
     * The ones picked offered: on a pack's page its stickers (what was changed there kept first), else whole packs.
     */
    public function publish(): void
    {
        $this->offer(true);
    }

    public function unpublish(): void
    {
        $this->offer(false);
    }

    /**
     * The stickers picked on a pack's page made WebPs (what was changed there kept first).
     */
    public function webp(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $this->keep();

        $stickers = $this->stickers();
        $made     = 0;

        foreach ($this->picked() as $id) {
            $made += (int) $stickers->toWebp($id);
        }

        $this->back(Text::plural('COM_ASKDESK_STICKERS_N_WEBP', $made));
    }

    /**
     * The ones picked deleted: on a pack's page its stickers, else whole packs with their stickers.
     */
    public function delete(): void
    {
        if (!$this->allowed()) {
            return;
        }

        $stickers = $this->stickers();

        if ($this->input->getInt('pack_id')) {
            $this->keep();

            foreach ($this->picked() as $id) {
                $stickers->delete($id);
            }

            $this->back(Text::_('COM_ASKDESK_STICKERS_DELETED'));

            return;
        }

        foreach ($this->picked() as $id) {
            $stickers->deletePack($id);
        }

        $this->setRedirect($this->packs(), Text::_('COM_ASKDESK_STICKERS_PACK_DELETED'));
    }

    /**
     * The packs in the order dragged to (0.6.8), from the page itself: answered in JSON.
     */
    public function orderPacks(): void
    {
        $user = $this->app->getIdentity();
        $ok   = Session::checkToken() && ($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk'));

        if ($ok) {
            $this->stickers()->orderPacks((array) $this->input->get('order', [], 'array'));
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->sendHeaders();
        echo json_encode(['ok' => $ok]);
        $this->app->close();
    }

    private function offer(bool $published): void
    {
        if (!$this->allowed()) {
            return;
        }

        $stickers = $this->stickers();

        if ($this->input->getInt('pack_id')) {
            $this->keep();
            $stickers->offer($this->picked(), $published);
            $this->back(Text::_('COM_ASKDESK_STICKERS_SAVED'));

            return;
        }

        foreach ($this->picked() as $id) {
            $pack = $stickers->pack($id);

            if ($pack) {
                $stickers->updatePack($id, $pack->name, '', $published, $pack->cover);
            }
        }

        $this->setRedirect($this->packs(), Text::_('COM_ASKDESK_STICKERS_SAVED'));
    }

    /**
     * What a pack's page holds, kept: its stickers as changed, their order, then the pack as a whole.
     */
    private function keep(): void
    {
        $stickers = $this->stickers();
        $id       = $this->input->getInt('pack_id');

        if (!$id) {
            return;
        }

        foreach ((array) $this->input->get('stickers', [], 'array') as $sticker => $values) {
            $values = (array) $values;
            $stickers->update((int) $sticker, (string) ($values['pack'] ?? ''), (string) ($values['title'] ?? ''), (string) ($values['who'] ?? 'all'), !empty($values['published']));
        }

        $stickers->orderStickers((array) $this->input->get('order', [], 'array'));

        $who       = $this->input->getCmd('pack_who', '');
        $published = $this->input->getCmd('pack_published', '');
        $name      = $this->input->getString('pack_name', '');

        $stickers->updatePack($id, $name, $who, $published === '' ? null : $published === '1', $this->input->getInt('cover'));

        // Back to it under its new name
        if (trim(strip_tags($name)) !== '') {
            $this->input->set('back_pack', mb_substr(trim(strip_tags($name)), 0, 100));
        }
    }

    /**
     * @return  int[]  The IDs ticked in the list
     */
    private function picked(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->input->get('cid', [], 'array'))));
    }

    private function packs(): string
    {
        return Route::_('index.php?option=com_askdesk&view=stickers', false);
    }

    private function allowed(): bool
    {
        $user = $this->app->getIdentity();

        if (Session::checkToken() && ($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk'))) {
            return true;
        }

        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=stickers', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

        return false;
    }

    /**
     * Back to the pack it was done in (0.6.8), found again by its name (it may have been renamed, or joined another),
     * else to the packs.
     */
    private function back(string $message = ''): void
    {
        $name = trim($this->input->getString('back_pack', ''));
        $id   = 0;

        if ($name !== '') {
            foreach ($this->stickers()->packList() as $pack) {
                if ($pack->name === $name) {
                    $id = $pack->id;
                }
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=stickers' . ($id ? '&pack=' . $id : ''), false), $message ?: null);
    }

    private function stickers(): Stickers
    {
        return new Stickers(Factory::getContainer()->get(DatabaseInterface::class));
    }
}
