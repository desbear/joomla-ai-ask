<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Where the account centre sends visitors back (Embed\Cas::service()), to be allowed there as a
 * service (0.22.6): the start of the address, the page each visitor came from following it, and
 * the same as a regular expression for an account centre that takes one (Apereo CAS's serviceId).
 * Nothing is saved.
 *
 * @since  0.22.6
 */
class McpaskcasbackField extends FormField
{
    protected $type = 'Mcpaskcasback';

    protected function getInput()
    {
        $e     = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $start = rtrim(Uri::root(), '/') . '/index.php?option=com_mcpask&task=cas.back';
        // Only what a regular expression reads as more than itself is escaped, so it stays legible
        $regex = '^' . preg_replace('/[.?*+^$()\\[\\]{}|\\\\]/', '\\\\$0', $start) . '(&.*)?$';
        $copy  = Text::_('COM_MCPASK_KEY_COPY');

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(<<<'JS'
            document.addEventListener('click', (event) => {
              const button = event.target.closest('[data-mcpask-casback]');

              if (button) {
                const input = button.closest('.input-group').querySelector('input');

                input.select();
                (navigator.clipboard?.writeText(input.value) ?? Promise.reject()).catch(() => document.execCommand('copy'));
              }
            });
            JS);

        $row = static fn (string $label, string $value) => '<div class="small text-muted mt-2 mb-1">' . $e($label) . '</div>'
            . '<div class="input-group"><input type="text" class="form-control font-monospace" readonly value="' . $e($value) . '">'
            . '<button type="button" class="btn btn-secondary" data-mcpask-casback>' . $e($copy) . '</button></div>';

        return $row(Text::_('COM_MCPASK_CONFIG_CAS_BACK_START'), $start) . $row(Text::_('COM_MCPASK_CONFIG_CAS_BACK_REGEX'), $regex);
    }
}
