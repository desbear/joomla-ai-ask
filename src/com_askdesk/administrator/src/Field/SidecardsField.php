<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Field;

use Gss\Component\Askdesk\Administrator\Desk\Panels;
use Joomla\CMS\Form\Field\CheckboxesField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * The cards beside a chat (0.6.1), to tick those not to show: the desk's own (suggested replies, the
 * visitor's history, the library), then each extension's panel by its title.
 *
 * @since  0.6.1
 */
class SidecardsField extends CheckboxesField
{
    protected $type = 'Sidecards';

    protected function getOptions()
    {
        $options = [];

        foreach (['suggest', 'history', 'library'] as $name) {
            $options[] = HTMLHelper::_('select.option', $name, Text::_('COM_ASKDESK_CONFIG_SIDE_' . strtoupper($name)));
        }

        foreach (Panels::all() as $name => $panel) {
            $title = method_exists($panel, 'title') ? (string) $panel->title() : '';
            $options[] = HTMLHelper::_('select.option', $name, $title !== '' ? $title : $name);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
