<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Gss\Component\Askdesk\Administrator\View\Cases\HtmlView $this */
$e = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
	<p class="text-muted mb-0 me-auto"><?php echo Text::_('COM_ASKDESK_CASES_HOUSEKEEPING_INTRO'); ?></p>
	<div class="btn-group" role="group" aria-label="<?php echo Text::_('COM_ASKDESK_CASES_UNTOUCHED'); ?>">
		<?php foreach ($this::AGES as $age) : ?>
			<a class="btn btn-sm <?php echo $this->age === $age ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo $e('index.php?option=com_askdesk&view=cases&age=' . $age); ?>"><?php echo $age ? Text::sprintf('COM_ASKDESK_CASES_OLDER', $age) : Text::_('COM_ASKDESK_CASES_ANY_AGE'); ?></a>
		<?php endforeach; ?>
	</div>
</div>

<?php if (!$this->cases) : ?>
	<div class="alert alert-success"><?php echo Text::_('COM_ASKDESK_CASES_NONE_LEFT'); ?></div>
<?php else : ?>
	<form action="index.php?option=com_askdesk" method="post" id="askdesk-cases"
		onsubmit="return confirm(this.dataset.ask.replace('%s', this.querySelectorAll('input[name=&quot;cid[]&quot;]:checked').length))"
		data-ask="<?php echo $e(Text::_('COM_ASKDESK_CASES_SETTLE_ASK')); ?>">
		<input type="hidden" name="task" value="cases.settle">
		<input type="hidden" name="age" value="<?php echo (int) $this->age; ?>">
		<?php echo HTMLHelper::_('form.token'); ?>
		<div class="d-flex align-items-center gap-3 mb-2">
			<strong><?php echo Text::sprintf('COM_ASKDESK_CASES_COUNT', $this->total); ?></strong>
			<?php if ($this->total > count($this->cases)) : ?><span class="text-muted small"><?php echo Text::sprintf('COM_ASKDESK_CASES_SHOWN', count($this->cases)); ?></span><?php endif; ?>
			<button class="btn btn-primary btn-sm ms-auto" type="submit"><span class="icon-checkbox-checked" aria-hidden="true"></span> <?php echo Text::_('COM_ASKDESK_CASES_SETTLE_PICKED'); ?></button>
		</div>
		<table class="table align-middle">
			<thead>
				<tr>
					<th scope="col" class="w-1"><input class="form-check-input" type="checkbox" aria-label="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="this.closest('table').querySelectorAll('input[name=&quot;cid[]&quot;]').forEach((box) => { box.checked = this.checked; })"></th>
					<th scope="col">#</th>
					<th scope="col"><?php echo Text::_('COM_ASKDESK_CASES_F_NAME'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ASKDESK_CASES_F_STATE'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ASKDESK_CASES_LAST_WORDS'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ASKDESK_CASES_F_STAFF'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ASKDESK_CASES_LAST_MOVED'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->cases as $case) : ?>
					<tr>
						<td><input class="form-check-input" type="checkbox" name="cid[]" value="<?php echo (int) $case->id; ?>" aria-label="#<?php echo (int) $case->id; ?>"></td>
						<td class="text-nowrap">#<?php echo (int) $case->id; ?></td>
						<td>
							<?php echo $e($case->name !== '' ? $case->name : Text::_('COM_ASKDESK_FILES_GUEST')); ?>
							<?php if ($case->email !== '' || $case->company !== '') : ?><br><small class="text-muted"><?php echo $e(trim($case->company . ' ' . $case->email)); ?></small><?php endif; ?>
						</td>
						<td><span class="badge <?php echo $case->status === 'message' ? 'bg-info' : 'bg-warning text-dark'; ?>"><?php echo Text::_($case->status === 'message' ? 'COM_ASKDESK_CASES_S_MESSAGE' : 'COM_ASKDESK_CASES_S_UNHANDLED'); ?></span></td>
						<td class="text-muted small"><?php echo $e(mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', (string) $case->last_text)), 0, 90, '…')); ?></td>
						<td><?php echo $e($this->names[(int) $case->staff_id] ?? ''); ?></td>
						<td class="text-nowrap"><?php echo HTMLHelper::_('date', $case->modified, Text::_('DATE_FORMAT_LC5')); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</form>
<?php endif; ?>
