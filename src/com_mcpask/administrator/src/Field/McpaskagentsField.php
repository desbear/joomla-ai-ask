<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Field;

use Gss\Component\Mcpask\Administrator\Agent\Agents;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The agents to choose from, but the one being edited.
 *
 * @since  0.17.0
 */
class McpaskagentsField extends ListField
{
    protected $type = 'Mcpaskagents';

    protected function getOptions()
    {
        $options = [];
        $current = (int) Factory::getApplication()->getInput()->getInt('id', 0);

        try {
            foreach ((new Agents(Factory::getContainer()->get(DatabaseInterface::class)))->all() as $agent) {
                if ((int) $agent->id !== $current) {
                    $options[] = (object) [
                        'value' => (int) $agent->id,
                        'text'  => (string) $agent->title . ((int) $agent->published === 1 ? '' : ' (' . \Joomla\CMS\Language\Text::_('JUNPUBLISHED') . ')'),
                    ];
                }
            }
        } catch (\Throwable) {
            // No agents yet
        }

        return array_merge(parent::getOptions(), $options);
    }
}
