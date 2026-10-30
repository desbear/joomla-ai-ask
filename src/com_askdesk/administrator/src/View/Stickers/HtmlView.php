<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Stickers;

use Gss\Component\Askdesk\Administrator\Desk\Stickers;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * The stickers to send in chats: added by the picture, in packs, for both sides or the people answering only. The
 * packs first (0.6.8), each a block shown by one of its stickers, in their order; one pack opened, its stickers.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $stickers = [];

    /** @var string[] The packs' names, to pick from */
    public array $packs = [];

    /** @var object[] The packs, in their order (0.6.8) */
    public array $packList = [];

    /** The pack opened, or null for all of them */
    public ?object $pack = null;

    /** The component's options: what a sticker added is by default (0.6.8) */
    public ?Registry $defaults = null;

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $stickers       = new Stickers(Factory::getContainer()->get(DatabaseInterface::class));
        $this->packList = $stickers->packList();
        $this->packs    = array_map(static fn ($pack) => $pack->name, $this->packList);
        $this->pack     = ($id = Factory::getApplication()->getInput()->getInt('pack')) ? $stickers->pack($id) : null;
        $this->stickers = $this->pack ? array_values(array_filter($stickers->all(), fn ($row) => (string) $row->pack === $this->pack->name)) : [];

        $this->defaults = ComponentHelper::getParams('com_askdesk');

        $this->toolbar();

        parent::display($tpl);
    }

    /**
     * Joomla's own toolbar (0.6.8): adding opens a window to drop pictures in; a pack's page is saved, saved and
     * closed or closed as an edit page is; what is ticked is offered, stopped, made WebPs or deleted.
     */
    private function toolbar(): void
    {
        $toolbar = Toolbar::getInstance();

        ToolbarHelper::title(Text::_('COM_ASKDESK_STICKERS') . ($this->pack ? ' · ' . $this->pack->name : ''), 'smile');

        if ($this->pack) {
            ToolbarHelper::apply('stickers.apply');
            ToolbarHelper::save('stickers.save');
        }

        $toolbar->customButton('sticker-add')->html('<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sticker-upload"><span class="icon-upload" aria-hidden="true"></span> ' . Text::_('COM_ASKDESK_STICKERS_UPLOAD') . '</button>');

        if ($this->pack || $this->packList) {
            ToolbarHelper::publish('stickers.publish', 'COM_ASKDESK_STICKERS_OFFER', true);
            ToolbarHelper::unpublish('stickers.unpublish', 'COM_ASKDESK_STICKERS_STOP', true);
        }

        if ($this->pack && $this->stickers) {
            ToolbarHelper::custom('stickers.webp', 'image', '', 'COM_ASKDESK_STICKERS_TO_WEBP', true);
        }

        if ($this->pack || $this->packList) {
            ToolbarHelper::deleteList(Text::_($this->pack ? 'COM_ASKDESK_STICKERS_DELETE_PICKED_ASK' : 'COM_ASKDESK_STICKERS_PACK_DELETE_PICKED_ASK'), 'stickers.delete');
        }

        if ($this->pack) {
            ToolbarHelper::cancel('stickers.cancel', 'JTOOLBAR_CLOSE');
        }

        ToolbarHelper::preferences('com_askdesk');
    }
}
