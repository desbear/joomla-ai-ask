<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Field;

use Gss\Component\Mcpask\Administrator\Ask\Source\FlexicontentSource;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The FLEXIcontent content types to choose from; none when FLEXIcontent is not installed.
 *
 * @since  0.15.0
 */
class McpaskflexitypesField extends ListField
{
    protected $type = 'Mcpaskflexitypes';

    protected function getOptions()
    {
        $options = [];

        try {
            if (FlexicontentSource::isAvailable()) {
                $db    = Factory::getContainer()->get(DatabaseInterface::class);
                $types = $db->setQuery($db->getQuery(true)->select('id, name')->from($db->quoteName('#__flexicontent_types'))->order('name'))->loadObjectList();

                foreach ($types as $type) {
                    $options[] = (object) ['value' => (int) $type->id, 'text' => (string) $type->name];
                }
            }
        } catch (\Throwable) {
            // FLEXIcontent's tables are not there
        }

        return array_merge(parent::getOptions(), $options);
    }
}
