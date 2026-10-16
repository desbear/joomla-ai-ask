<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Field;

use Gss\Component\Mcpask\Administrator\Pet\Pets;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * A pet of the library (or none: the icon, the spark). No pet comes with the extension: until one
 * is made on the Pets page, there is nothing to choose, and the field says where to make one.
 *
 * @since  0.12.0
 */
class McpaskpetField extends ListField
{
    protected $type = 'Mcpaskpet';

    /** @var array<string, string>|null */
    private ?array $pets = null;

    protected function getInput()
    {
        // Also on the module's and embeds' pages
        Factory::getApplication()->getLanguage()->load('com_mcpask', JPATH_ADMINISTRATOR);

        if ($this->pets()) {
            return parent::getInput();
        }

        $link = Route::_('index.php?option=com_mcpask&view=pets', false);

        return '<input type="hidden" name="' . $this->name . '" value="">'
            . '<div class="form-text mt-0">' . Text::_('COM_MCPASK_PET_NONE_MADE')
            . ' <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_('COM_MCPASK_PET_MAKE_FIRST') . '</a></div>';
    }

    protected function getOptions()
    {
        $options = [HTMLHelper::_('select.option', '', Text::_((string) ($this->element['none'] ?? '') ?: 'COM_MCPASK_PET_NONE'))];

        foreach ($this->pets() as $value => $title) {
            $options[] = HTMLHelper::_('select.option', $value, $title);
        }

        return array_merge(parent::getOptions(), $options);
    }

    /**
     * @return  array<string, string>
     */
    private function pets(): array
    {
        if ($this->pets === null) {
            try {
                $this->pets = (new Pets(Factory::getContainer()->get(DatabaseInterface::class)))->choices();
            } catch (\Throwable) {
                // Nothing to choose until the component is up to date
                $this->pets = [];
            }
        }

        return $this->pets;
    }
}
