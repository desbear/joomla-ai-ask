<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Field;

use Gss\Component\Mcpask\Administrator\Agent\Holidays;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextareaField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Days off, one a line ("YYYY-MM-DD note"), with a way to fill in a country's public holidays of
 * a year: they are added to what is there, each day once, in order, to be checked before saving.
 *
 * @since  0.17.0
 */
class McpaskholidaysField extends TextareaField
{
    protected $type = 'Mcpaskholidays';

    protected function getInput()
    {
        $textarea = parent::getInput();
        $e        = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $language = Factory::getApplication()->getLanguage()->getTag();
        $year     = (int) date('Y');
        $url      = Route::_('index.php?option=com_mcpask&task=holidays.fetch&format=json&' . Session::getFormToken() . '=1', false);
        $id       = $this->id;
        $options  = '';

        foreach (array_keys(Holidays::COUNTRIES) as $code) {
            $name     = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-' . $code, str_replace('-', '_', $language)) : $code;
            // The short name people use
            $name     = str_replace(['中國香港特別行政區', '中国香港特别行政区', 'Hong Kong SAR China', 'Hong Kong SAR'], ['香港', '香港', 'Hong Kong', 'Hong Kong'], (string) $name);
            $options .= '<option value="' . $code . '">' . $e($name ?: $code) . '</option>';
        }

        $options .= '<option value="">' . $e(Text::_('COM_MCPASK_HOLIDAYS_OTHER')) . '</option>';

        $years = '';

        foreach ([$year, $year + 1] as $value) {
            $years .= '<option value="' . $value . '">' . $value . '</option>';
        }

        $strings = json_encode([
            'loading' => Text::_('COM_MCPASK_HOLIDAYS_LOADING'),
            'added'   => Text::_('COM_MCPASK_HOLIDAYS_ADDED'),
            'none'    => Text::_('COM_MCPASK_HOLIDAYS_NONE'),
            'worked'  => Text::_('COM_MCPASK_HOLIDAYS_WORKED'),
            'failed'  => Text::_('COM_MCPASK_HOLIDAYS_FAILED_SHORT'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        $jsId  = json_encode($id);
        // The field the weekend days worked instead go to (Taiwan, China), next to this one
        $other = (string) ($this->element['workdays'] ?? '');
        $jsTo  = json_encode($other !== '' ? (string) preg_replace('/' . preg_quote((string) $this->fieldname, '/') . '$/', $other, $id) : '');
        $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
(() => {
  const area = document.getElementById({$jsId});
  const box = document.querySelector('[data-mcpask-holidays=' + JSON.stringify({$jsId}) + ']');
  if (!area || !box) { return; }
  const strings = {$strings};
  const country = box.querySelector('[data-part="country"]');
  const other = box.querySelector('[data-part="other"]');
  const year = box.querySelector('[data-part="year"]');
  const button = box.querySelector('button');
  const status = box.querySelector('.mcpask-holidays__status');
  country.addEventListener('change', () => { other.hidden = country.value !== ''; if (!other.hidden) { other.focus(); } });
  button.addEventListener('click', async () => {
    const code = (country.value || other.value).trim().toUpperCase();
    if (!/^[A-Z]{2}$/.test(code)) { other.hidden = false; other.focus(); return; }
    button.disabled = true;
    status.textContent = strings.loading;
    try {
      const response = await fetch({$jsUrl} + '&country=' + code + '&year=' + year.value, { credentials: 'same-origin' });
      const data = await response.json();
      if (data.error) { throw new Error(data.error); }
      // What is there stays, notes and all; a day already there is not added again
      const merge = (target, days) => {
        if (!target || !days || !days.length) { return 0; }
        const lines = target.value.split(/\\r?\\n/).map((line) => line.trim()).filter(Boolean);
        const known = new Set(lines.map((line) => line.slice(0, 10)));
        const added = days.filter((day) => !known.has(day.date)).map((day) => (day.date + ' ' + day.name).trim());
        const all = lines.concat(added);
        const dated = all.filter((line) => /^\\d{4}-\\d{2}-\\d{2}/.test(line)).sort();
        target.value = dated.concat(all.filter((line) => !/^\\d{4}-\\d{2}-\\d{2}/.test(line))).join('\\n');
        target.dispatchEvent(new Event('change', { bubbles: true }));
        return added.length;
      };
      const added = merge(area, data.days);
      const worked = merge({$jsTo} ? document.getElementById({$jsTo}) : null, data.workdays);
      status.textContent = added || worked
        ? strings.added.replace('%d', added) + (worked ? ' ' + strings.worked.replace('%d', worked) : '')
        : strings.none;
    } catch (error) {
      status.textContent = error.message || strings.failed;
    }
    button.disabled = false;
  });
})();
JS;

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript($script, [], ['type' => 'module']);

        return $textarea . '<div class="d-flex flex-wrap align-items-center gap-2 mt-2" data-mcpask-holidays="' . $e($id) . '">'
            . '<select data-part="country" class="form-select form-select-sm w-auto" aria-label="' . $e(Text::_('COM_MCPASK_HOLIDAYS_COUNTRY')) . '">' . $options . '</select>'
            . '<input data-part="other" class="form-control form-control-sm w-auto" maxlength="2" size="4" placeholder="' . $e(Text::_('COM_MCPASK_HOLIDAYS_CODE')) . '" hidden>'
            . '<select data-part="year" class="form-select form-select-sm w-auto" aria-label="' . $e(Text::_('COM_MCPASK_HOLIDAYS_YEAR')) . '">' . $years . '</select>'
            . '<button type="button" class="btn btn-sm btn-outline-primary"><span class="icon-download" aria-hidden="true"></span> ' . $e(Text::_('COM_MCPASK_HOLIDAYS_FETCH')) . '</button>'
            . '<span class="mcpask-holidays__status small text-muted" role="status"></span>'
            . '</div>';
    }
}
