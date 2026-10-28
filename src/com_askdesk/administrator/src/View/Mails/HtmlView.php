<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Mails;

use Gss\Component\Askdesk\Administrator\Desk\Mails;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * The e-mails the desk sends (0.6.3): each kind in each of the site's languages, designed or the desk's own words.
 *
 * @since  0.6.3
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, string> */
    public array $languages = [];

    /** @var array<string, array<string, object>> */
    public array $made = [];

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->languages = Mails::languages();
        $this->made      = Mails::make()->made();

        ToolbarHelper::title(Text::_('COM_ASKDESK_MAILS'), 'envelope');
        ToolbarHelper::preferences('com_askdesk');

        parent::display($tpl);
    }
}
