<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Field;

use Gss\Component\Mcpask\Administrator\Ask\Crypto;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * A secret (an API key, a peer's key, another site's secret), kept encrypted (0.20.8). Once saved it
 * is never shown again, only that one is set: the box carries it sealed, and clicked into, it is
 * emptied for a new one (left empty, the one there was comes back). Used by the component, the
 * module and plugins (with addfieldprefix).
 *
 * @since  0.20.8
 */
class McpasksecretField extends FormField
{
    protected $type = 'Mcpasksecret';

    protected function getInput()
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_mcpask', JPATH_ADMINISTRATOR);
        $this->script();

        $e    = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $kept = Crypto::seal((string) $this->value);
        $set  = $kept !== '';

        return '<div class="mcpask-secret" data-mcpask-secret>'
            . ($set ? '<div class="small text-success mb-1" data-mcpask-secret-state><span class="icon-lock" aria-hidden="true"></span> ' . Text::_('COM_MCPASK_SECRET_SET') . '</div>' : '')
            . $this->box($e, $kept)
            . ($set ? '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" data-mcpask-secret-clear id="' . $e($this->id) . '_clear">'
                . '<label class="form-check-label small" for="' . $e($this->id) . '_clear">' . Text::_('COM_MCPASK_SECRET_CLEAR') . '</label></div>' : '')
            . '</div>';
    }

    /**
     * The box: the secret there is, sealed, until a new one is typed.
     */
    protected function box(callable $e, string $kept): string
    {
        return '<input type="password" class="form-control" name="' . $e($this->name) . '" id="' . $e($this->id) . '" value="' . $e($kept) . '"'
            . ' data-mcpask-secret-box data-kept="' . $e($kept) . '" autocomplete="new-password" spellcheck="false" maxlength="999"'
            . ' placeholder="' . $e($kept !== '' ? Text::_('COM_MCPASK_SECRET_KEEP') : '') . '">';
    }

    /**
     * Clicked into, the box empties for a new secret; left empty, the sealed one comes back (unless
     * Clear is ticked).
     */
    protected function script(): void
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(<<<'JS'
            document.addEventListener('focusin', (event) => {
              const box = event.target.closest('[data-mcpask-secret-box]');

              if (box && box.dataset.kept && box.value === box.dataset.kept) {
                box.value = '';
              }
            });
            document.addEventListener('focusout', (event) => {
              const box = event.target.closest('[data-mcpask-secret-box]');
              const clear = box && box.closest('[data-mcpask-secret]').querySelector('[data-mcpask-secret-clear]');

              if (box && box.dataset.kept && box.value.trim() === '' && !(clear && clear.checked)) {
                box.value = box.dataset.kept;
              }
            });
            document.addEventListener('change', (event) => {
              const clear = event.target.closest('[data-mcpask-secret-clear]');

              if (clear) {
                const box = clear.closest('[data-mcpask-secret]').querySelector('[data-mcpask-secret-box]');

                box.value = clear.checked ? '' : box.dataset.kept;
                box.disabled = false;
              }
            });
            JS);
    }

    /**
     * Sealed as it came (the one there was), sealed when new, empty when cleared.
     */
    public function filter($value, $group = null, ?Registry $input = null)
    {
        return Crypto::seal(trim((string) $value));
    }
}
