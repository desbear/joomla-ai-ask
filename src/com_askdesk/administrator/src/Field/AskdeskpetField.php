<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Field;

use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * A pet of AI Ask's library for a person answering: only pets with a moment of bowing thanks may be
 * chosen (visitors see it when they rate a chat); the others are listed, not to be chosen, saying
 * what they lack.
 *
 * @since  0.1.0
 */
class AskdeskpetField extends ListField
{
    protected $type = 'Askdeskpet';

    protected function getOptions()
    {
        $options = [HTMLHelper::_('select.option', '', Text::_((string) ($this->element['none'] ?? '') ?: 'JNONE'))];

        foreach (Staff::pets(Factory::getContainer()->get(DatabaseInterface::class)) as $id => $pet) {
            $options[] = HTMLHelper::_('select.option', (string) $id, $pet['thanks'] ? $pet['title'] : Text::sprintf('COM_ASKDESK_PERSON_PET_NO_THANKS', $pet['title']), 'value', 'text', !$pet['thanks']);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
