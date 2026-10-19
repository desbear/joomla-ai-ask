<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Mcpask\Administrator\Pet\Pets;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Gss\Component\Mcpask\Administrator\View\Pets\HtmlView $this */

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// One pet: its thumbnail (or its idle picture), name and moments; what can be done with it below
$card = function (string $choice, string $title, string $link, string $footer) use ($e): string {
    $shown   = $this->shown[$choice] ?? [];
    $picture = ($shown['thumb'] ?? '') ?: ($shown['actions']['idle']['src'] ?? '');
    // A cleaning pet shows its first picture; without one it does not show at all
    $missing = Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_CLEANER_NEEDS_PICTURE' : 'COM_MCPASK_PET_NEEDS_IDLE');
    $count   = count($shown['actions'] ?? []);

    return '<div class="card h-100">'
        . '<div class="card-body text-center">'
        . ($picture !== '' ? '<img src="' . $e($picture) . '" alt="" width="120" height="120" style="object-fit:contain;aspect-ratio:1">' : '<div class="text-danger small py-5">' . $missing . '</div>')
        . '<h3 class="h6 mt-2 mb-1">' . ($link !== '' ? '<a href="' . $e($link) . '">' . $e($title) . '</a>' : $e($title)) . '</h3>'
        . '<div class="small text-muted">' . Text::sprintf('COM_MCPASK_PET_ACTIONS_COUNT', $count, count($this->kind === 'cleaner' ? Pets::CLEANER_ACTIONS : Pets::ACTIONS)) . '</div>'
        . '</div>'
        . '<div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-center align-items-center">' . $footer . '</div>'
        . '</div>';
};
?>
<?php
// Dragged into place: the new order is kept at once
HTMLHelper::_('form.csrf');
\Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(<<<'JS'
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('[data-mcpask-pet-order]');
  let dragged = null;

  if (!grid) {
    return;
  }

  grid.addEventListener('dragstart', (event) => {
    dragged = event.target.closest('[data-id]');
    event.dataTransfer.effectAllowed = 'move';
    dragged.style.opacity = '0.4';
  });

  grid.addEventListener('dragover', (event) => {
    const over = event.target.closest('[data-id]');

    event.preventDefault();

    if (!dragged || !over || over === dragged) {
      return;
    }

    const box = over.getBoundingClientRect();
    const after = event.clientX > box.left + box.width / 2;

    grid.insertBefore(dragged, after ? over.nextSibling : over);
  });

  grid.addEventListener('dragend', async () => {
    if (!dragged) {
      return;
    }

    dragged.style.opacity = '';
    dragged = null;

    const body = new URLSearchParams();
    [...grid.querySelectorAll('[data-id]')].forEach((card) => body.append('ids[]', card.dataset.id));
    body.append(Joomla.getOptions('csrf.token'), '1');

    try {
      const response = await fetch(grid.dataset.mcpaskPetOrder, { method: 'POST', body, credentials: 'same-origin' });
      const json = await response.json();

      if (!json.success) {
        throw new Error(json.message || response.statusText);
      }
    } catch (error) {
      window.alert(error.message);
    }

    window.location.reload();
  });
});
JS);
?>
<form action="<?php echo Route::_('index.php?option=com_mcpask&view=pets'); ?>" method="post" name="adminForm" id="adminForm">
	<ul class="nav nav-tabs mb-3">
		<?php foreach (Pets::KINDS as $kind) : ?>
			<li class="nav-item">
				<a class="nav-link<?php echo $this->kind === $kind ? ' active' : ''; ?>"<?php echo $this->kind === $kind ? ' aria-current="page"' : ''; ?> href="<?php echo $e(Route::_('index.php?option=com_mcpask&view=pets' . ($kind === 'cleaner' ? '&kind=cleaner' : ''), false)); ?>"><?php echo Text::_('COM_MCPASK_PETS_TAB_' . strtoupper($kind)); ?> <span class="badge bg-secondary"><?php echo (int) ($this->counts[$kind] ?? 0); ?></span></a>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="text-muted"><?php echo Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_CLEANERS_DESC' : 'COM_MCPASK_PETS_DESC'); ?></p>

	<?php if (!$this->pets) : ?>
		<div class="alert alert-info">
			<?php echo Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_CLEANERS_NONE' : 'COM_MCPASK_PETS_NONE'); ?>
			<a class="btn btn-primary btn-sm ms-2" href="<?php echo $e(Route::_('index.php?option=com_mcpask&view=pet&kind=' . $this->kind, false)); ?>"><span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_CLEANER_NEW' : 'COM_MCPASK_PET_NEW'); ?></a>
		</div>
	<?php endif; ?>

	<?php if (count($this->pets) > 1) : ?>
		<p class="small text-muted"><?php echo Text::_('COM_MCPASK_PETS_ORDER_DESC'); ?></p>
	<?php endif; ?>

	<div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-3 mb-4" data-mcpask-pet-order="<?php echo $e(Route::_('index.php?option=com_mcpask&task=pets.reorder', false)); ?>">
		<?php foreach ($this->pets as $i => $pet) : ?>
			<div class="col" draggable="true" data-id="<?php echo (int) $pet->id; ?>" style="cursor:grab">
				<?php echo $card(
					(string) $pet->id,
					$pet->title,
					Route::_('index.php?option=com_mcpask&view=pet&id=' . (int) $pet->id, false),
					'<a class="btn btn-outline-primary btn-sm" href="' . $e(Route::_('index.php?option=com_mcpask&view=pet&id=' . (int) $pet->id, false)) . '"><span class="icon-edit" aria-hidden="true"></span> ' . Text::_('JTOOLBAR_EDIT') . '</a>'
					. '<label class="small d-inline-flex align-items-center gap-1 mb-0">' . HTMLHelper::_('grid.id', $i, (int) $pet->id) . ' ' . Text::_('JSELECT') . '</label>'
					. (count($this->pets) > 1 ? '<span class="btn-group btn-group-sm">'
						. '<button type="submit" class="btn btn-outline-secondary" name="move" value="' . (int) $pet->id . ':-1" onclick="this.form.task.value=\'pets.move\'"' . ($i === 0 ? ' disabled' : '') . ' title="' . $e(Text::_('COM_MCPASK_PET_MOVE_EARLIER')) . '" aria-label="' . $e(Text::_('COM_MCPASK_PET_MOVE_EARLIER')) . '"><span class="icon-arrow-left" aria-hidden="true"></span></button>'
						. '<button type="submit" class="btn btn-outline-secondary" name="move" value="' . (int) $pet->id . ':1" onclick="this.form.task.value=\'pets.move\'"' . ($i === count($this->pets) - 1 ? ' disabled' : '') . ' title="' . $e(Text::_('COM_MCPASK_PET_MOVE_LATER')) . '" aria-label="' . $e(Text::_('COM_MCPASK_PET_MOVE_LATER')) . '"><span class="icon-arrow-right" aria-hidden="true"></span></button>'
						. '</span>' : '')
				); ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ($this->pets) : ?>
		<div class="border-top pt-3">
			<button type="submit" class="btn btn-outline-danger btn-sm" onclick="if (!document.adminForm.boxchecked.value || document.adminForm.boxchecked.value === '0') { alert('<?php echo $e(Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST')); ?>'); return false; } if (!confirm('<?php echo $e(Text::_('COM_MCPASK_PET_DELETE_CONFIRM')); ?>')) return false; this.form.task.value='pets.delete';">
				<span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PET_DELETE_CHOSEN'); ?>
			</button>
		</div>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
