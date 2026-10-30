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
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * This site's export key, with a button that makes a random one and a button that copies it to
 * give to the other sites. Kept encrypted like any secret (0.20.8): a new key is shown until it is
 * saved, then only that one is set, so it is copied before saving (Copy is there only until then).
 *
 * @since  0.2.0
 */
class McpaskkeyField extends McpasksecretField
{
    protected $type = 'Mcpaskkey';

    protected function box(callable $e, string $kept): string
    {
        // The key is made in the browser, from its own source of randomness: 24 bytes, written as 48 characters
        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(<<<'JS'
            // Copy only for a key not yet saved (0.22.5): a saved one is kept sealed and cannot be copied again
            const copyable = (event) => {
              const copy = event.target.closest?.('[data-mcpask-secret]')?.querySelector('[data-mcpask-key="copy"]');

              if (copy) {
                const input = copy.closest('.input-group').querySelector('input');

                copy.disabled = !input.value.trim() || input.value === input.dataset.kept;
              }
            };

            ['input', 'change', 'focusin', 'focusout'].forEach((type) => document.addEventListener(type, copyable));

            document.addEventListener('click', (event) => {
              const button = event.target.closest('[data-mcpask-key]');

              if (!button) {
                return;
              }

              const input = button.closest('.input-group').querySelector('input');

              if (button.dataset.mcpaskKey === 'copy') {
                if (!input.value.trim() || input.value === input.dataset.kept) {
                  return;
                }

                const done = () => {
                  const label = button.textContent;

                  button.textContent = button.dataset.done;
                  window.setTimeout(() => { button.textContent = label; }, 1500);
                };

                // Without the clipboard (a page not on HTTPS, or refused), the key is shown and selected to be copied by hand
                const byHand = () => {
                  input.type = 'text';
                  input.select();

                  if (document.execCommand('copy')) {
                    done();
                  } else {
                    window.alert(button.dataset.failed);
                  }
                };

                if (navigator.clipboard?.writeText) {
                  navigator.clipboard.writeText(input.value).then(done, byHand);
                } else {
                  byHand();
                }

                return;
              }

              if (input.value.trim() && !window.confirm(button.dataset.confirm)) {
                return;
              }

              const bytes = window.crypto.getRandomValues(new Uint8Array(24));

              // Shown until saved, to be copied to the other sites
              input.type = 'text';
              input.value = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
              input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            JS);

        return '<div class="input-group">'
            . parent::box($e, $kept)
            . '<button type="button" class="btn btn-primary" data-mcpask-key="generate" data-confirm="' . $e(Text::_('COM_MCPASK_KEY_REPLACE')) . '">'
            . '<span class="icon-refresh" aria-hidden="true"></span> ' . Text::_('COM_MCPASK_KEY_GENERATE') . '</button>'
            . '<button type="button" class="btn btn-secondary" disabled data-mcpask-key="copy" data-done="' . $e(Text::_('COM_MCPASK_KEY_COPIED')) . '" data-failed="' . $e(Text::_('COM_MCPASK_KEY_COPY_FAILED')) . '">'
            . Text::_('COM_MCPASK_KEY_COPY') . '</button>'
            . '</div>';
    }
}
