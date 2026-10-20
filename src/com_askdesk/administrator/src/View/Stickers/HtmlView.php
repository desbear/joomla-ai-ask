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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The stickers to send in chats: added by the picture, in packs, for both sides or the people answering only.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $stickers = [];

    /** @var string[] */
    public array $packs = [];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->stickers = (new Stickers(Factory::getContainer()->get(DatabaseInterface::class)))->all();
        $this->packs    = array_values(array_unique(array_map(static fn ($row) => (string) $row->pack, $this->stickers)));

        ToolbarHelper::title(Text::_('COM_ASKDESK_STICKERS'), 'smile');
        ToolbarHelper::preferences('com_askdesk');

        parent::display($tpl);
    }
}
