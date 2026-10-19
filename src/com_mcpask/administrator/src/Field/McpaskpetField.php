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
use Joomla\CMS\Uri\Uri;
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

        // Cleaning pets (0.18.0): without any, the saucer that comes with the extension does the work
        if ($this->pets() || $this->kind() === 'cleaner') {
            return $this->picker(parent::getInput());
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

    /** Whether the picker's script and styles are on the page already */
    private static bool $assets = false;

    /**
     * The list as pictures (0.19.0): a button with the chosen pet's picture that opens the pets
     * with theirs. The list itself stays, out of sight, for the form, its conditions and its rows.
     */
    private function picker(string $select): string
    {
        $e     = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $faces = $this->faces();
        $none  = $this->kind() === 'cleaner' ? Uri::root(true) . '/media/mod_mcp_ask/images/ufo.png' : '';
        $face  = static fn (string $url) => '<span class="mcpask-petpick__face">' . ($url !== '' ? '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">' : '') . '</span>';
        $items = '';
        $shown = '';

        foreach ($this->getOptions() as $option) {
            $value  = (string) $option->value;
            $chosen = $value === (string) $this->value;
            $inner  = $face($value === '' ? $none : ($faces[$value]['face'] ?? '')) . '<span class="mcpask-petpick__name">' . $e($option->text) . '</span>';
            $items .= '<button type="button" class="mcpask-petpick__item" role="option" data-value="' . $e($value) . '" aria-selected="' . ($chosen ? 'true' : 'false') . '">' . $inner . '</button>';

            // The button shows the chosen one, else the first
            if ($chosen || $shown === '') {
                $shown = $inner;
            }
        }

        $this->assets();

        return '<div class="mcpask-petpick" data-mcpask-petpick>' . $select
            . '<button type="button" class="form-select mcpask-petpick__button" aria-haspopup="listbox" aria-expanded="false" aria-label="' . $e(Text::_('COM_MCPASK_PET_PICK')) . '">' . $shown . '</button>'
            . '<div class="mcpask-petpick__list" role="listbox" hidden>' . $items . '</div></div>';
    }

    private function assets(): void
    {
        if (self::$assets) {
            return;
        }

        self::$assets = true;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $wa->addInlineStyle('.mcpask-petpick{position:relative;max-inline-size:26rem}'
            . '.mcpask-petpick>select{position:absolute;inline-size:1px;block-size:1px;opacity:0;pointer-events:none}'
            . '.mcpask-petpick__button{display:flex;align-items:center;gap:.6rem;text-align:start}'
            . '.mcpask-petpick__face{display:inline-grid;flex:none;place-items:center;inline-size:36px;block-size:36px;overflow:hidden;border-radius:10px;background:var(--body-bg,#f3f4f7);box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}'
            . '.mcpask-petpick__face img{inline-size:100%;block-size:100%;object-fit:cover}'
            . '.mcpask-petpick__list{position:absolute;inset-inline:0;inset-block-start:calc(100% + 4px);z-index:1050;display:grid;gap:2px;max-block-size:22rem;padding:6px;overflow:auto;border:1px solid var(--border-color,#dfe3e7);border-radius:10px;background:var(--body-bg,#fff);box-shadow:0 10px 30px rgba(0,0,0,.15)}'
            . '.mcpask-petpick__list[hidden]{display:none}'
            . '.mcpask-petpick__item{display:flex;align-items:center;gap:.6rem;inline-size:100%;padding:6px 8px;border:0;border-radius:8px;background:none;color:inherit;text-align:start}'
            . '.mcpask-petpick__item:hover,.mcpask-petpick__item:focus-visible{background:rgba(0,0,0,.06);outline:0}'
            . '.mcpask-petpick__item[aria-selected="true"]{font-weight:600;box-shadow:inset 3px 0 0 var(--template-link-color,#2a69b8)}');

        $wa->addInlineScript(<<<'JS'
(() => {
  const close = (except) => document.querySelectorAll('[data-mcpask-petpick] .mcpask-petpick__list:not([hidden])').forEach((list) => {
    if (list !== except) {
      list.hidden = true;
      list.parentNode.querySelector('.mcpask-petpick__button').setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('click', (event) => {
    const picker = event.target.closest('[data-mcpask-petpick]');
    const button = event.target.closest('.mcpask-petpick__button');
    const item = event.target.closest('.mcpask-petpick__item');

    if (!picker) {
      close(null);

      return;
    }

    const list = picker.querySelector('.mcpask-petpick__list');

    if (button) {
      close(list);
      list.hidden = !list.hidden;
      button.setAttribute('aria-expanded', String(!list.hidden));

      if (!list.hidden) {
        (list.querySelector('[aria-selected="true"]') || list.querySelector('button')).focus();
      }
    } else if (item) {
      const select = picker.querySelector('select');

      select.value = item.dataset.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      list.querySelectorAll('.mcpask-petpick__item').forEach((other) => other.setAttribute('aria-selected', String(other === item)));
      picker.querySelector('.mcpask-petpick__button').innerHTML = item.innerHTML;
      close(null);
      picker.querySelector('.mcpask-petpick__button').focus();
    }
  });

  document.addEventListener('keydown', (event) => {
    const list = event.target.closest('.mcpask-petpick__list');

    if (event.key === 'Escape' && list) {
      close(null);
      list.parentNode.querySelector('.mcpask-petpick__button').focus();
    } else if (list && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
      const items = [...list.querySelectorAll('.mcpask-petpick__item')];
      const next = items[(items.indexOf(event.target) + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];

      event.preventDefault();
      next.focus();
    }
  });
})();
JS);
    }

    /**
     * @return  array<string, array{title: string, face: string}>
     */
    private function faces(): array
    {
        try {
            return (new Pets(Factory::getContainer()->get(DatabaseInterface::class)))->faces($this->kind());
        } catch (\Throwable) {
            return [];
        }
    }

    private function kind(): string
    {
        return (string) ($this->element['kind'] ?? '') === 'cleaner' ? 'cleaner' : 'agent';
    }

    /**
     * @return  array<string, string>
     */
    private function pets(): array
    {
        if ($this->pets === null) {
            try {
                $this->pets = (new Pets(Factory::getContainer()->get(DatabaseInterface::class)))->choices($this->kind());
            } catch (\Throwable) {
                // Nothing to choose until the component is up to date
                $this->pets = [];
            }
        }

        return $this->pets;
    }
}
