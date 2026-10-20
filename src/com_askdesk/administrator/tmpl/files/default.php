<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Askdesk\Administrator\Desk\Files;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Gss\Component\Askdesk\Administrator\View\Files\HtmlView $this */
$e    = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$size = static fn (int $bytes) => $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
$now  = time();
?>
<div class="card mb-3">
	<div class="card-body">
		<h2 class="h5 card-title"><?php echo Text::_('COM_ASKDESK_FILES_LIBRARY'); ?></h2>
		<?php if (!$this->library) : ?>
			<p class="text-muted mb-0"><?php echo Text::_('COM_ASKDESK_FILES_NO_DROPFILES'); ?></p>
		<?php elseif ($this->libraryError !== '') : ?>
			<div class="alert alert-warning mb-0"><?php echo $e($this->libraryError); ?></div>
		<?php else : ?>
			<p class="text-muted"><?php echo Text::_('COM_ASKDESK_FILES_LIBRARY_DESC'); ?></p>
			<div class="d-flex flex-wrap gap-2">
				<?php foreach ($this->folders as $folder) : ?>
					<span class="btn btn-outline-secondary btn-sm pe-none">
						<span class="icon-folder" aria-hidden="true"></span>
						<?php echo $e($folder['user'] ? $folder['title'] : Text::_('COM_ASKDESK_LIBRARY_SHARED')); ?>
						<span class="badge bg-secondary ms-1"><?php echo (int) $folder['files']; ?></span>
					</span>
				<?php endforeach; ?>
				<a class="btn btn-primary btn-sm ms-auto" href="index.php?option=com_dropfiles"><?php echo Text::_('COM_ASKDESK_FILES_OPEN_DROPFILES'); ?></a>
				<?php if (!$this->folders) : ?>
					<span class="text-muted"><?php echo Text::_('COM_ASKDESK_FILES_NO_FOLDERS'); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
	<h2 class="h5 mb-0 me-auto"><?php echo Text::_('COM_ASKDESK_FILES_SENT'); ?></h2>
	<div class="btn-group" role="group" aria-label="<?php echo Text::_('COM_ASKDESK_FILES_KIND'); ?>">
		<?php foreach (['' => 'COM_ASKDESK_FILES_ALL', 'image' => 'COM_ASKDESK_FILES_IMAGES', 'video' => 'COM_ASKDESK_FILES_VIDEOS', 'document' => 'COM_ASKDESK_FILES_DOCUMENTS'] as $kind => $label) : ?>
			<a class="btn btn-sm <?php echo $this->kind === $kind ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo $e('index.php?option=com_askdesk&view=files' . ($kind !== '' ? '&kind=' . $kind : '')); ?>"><?php echo Text::_($label); ?></a>
		<?php endforeach; ?>
	</div>
</div>
<p class="text-muted"><?php echo Text::sprintf('COM_ASKDESK_FILES_INTRO', Files::KEEP_DAYS); ?></p>

<?php if (!$this->files) : ?>
	<div class="alert alert-info"><?php echo Text::_('COM_ASKDESK_FILES_NONE'); ?></div>
<?php else : ?>
	<table class="table align-middle">
		<thead>
			<tr>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_FILES_NAME'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_FILES_BY'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_FILES_CHAT'); ?></th>
				<th scope="col" class="text-end"><?php echo Text::_('COM_ASKDESK_FILES_SIZE'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_FILES_SENT_AT'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_FILES_EXPIRES'); ?></th>
				<th scope="col"><span class="visually-hidden"><?php echo Text::_('COM_ASKDESK_ACTIONS'); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->files as $file) :
				$left = (int) ceil((strtotime($file->expires . ' UTC') - $now) / 86400);
				$icon = ['image' => 'icon-image', 'video' => 'icon-video', 'document' => 'icon-file'][$file->kind] ?? 'icon-file';
				?>
				<tr>
					<td>
						<span class="<?php echo $icon; ?> text-primary me-1" aria-hidden="true"></span>
						<?php echo $e($file->name); ?>
					</td>
					<td>
						<?php if ($file->author === 'staff') : ?>
							<span class="badge bg-primary"><?php echo Text::_('COM_ASKDESK_FILES_STAFF'); ?></span>
							<?php echo $e($this->names[(int) $file->staff_id] ?? ('#' . (int) $file->staff_id)); ?>
						<?php else : ?>
							<span class="badge bg-info"><?php echo Text::_('COM_ASKDESK_FILES_VISITOR'); ?></span>
							<?php echo $e($file->room_name ?: Text::_('COM_ASKDESK_FILES_GUEST')); ?>
						<?php endif; ?>
					</td>
					<td>
						#<?php echo (int) $file->room_id; ?>
						<?php if ($file->room_email) : ?><br><small class="text-muted"><?php echo $e($file->room_email); ?></small><?php endif; ?>
					</td>
					<td class="text-end text-nowrap"><?php echo $size((int) $file->size); ?></td>
					<td class="text-nowrap"><?php echo HTMLHelper::_('date', $file->created, Text::_('DATE_FORMAT_LC5')); ?></td>
					<td class="text-nowrap">
						<?php if ($left <= 0) : ?>
							<span class="badge bg-secondary"><?php echo Text::_('COM_ASKDESK_FILES_EXPIRED'); ?></span>
						<?php else : ?>
							<span class="badge <?php echo $left <= 3 ? 'bg-warning' : 'bg-light text-dark'; ?>"><?php echo Text::sprintf('COM_ASKDESK_FILES_DAYS_LEFT', $left); ?></span>
						<?php endif; ?>
					</td>
					<td class="text-nowrap text-end">
						<a class="btn btn-sm btn-outline-primary" href="<?php echo $e('index.php?option=com_askdesk&task=files.download&id=' . (int) $file->id); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_ASKDESK_FILES_OPEN'); ?></a>
						<?php if ($this->canDelete) : ?>
							<form action="index.php?option=com_askdesk" method="post" class="d-inline m-0" onsubmit="return confirm(this.dataset.ask)" data-ask="<?php echo $e(Text::sprintf('COM_ASKDESK_FILES_DELETE_ASK', $file->name)); ?>">
								<input type="hidden" name="task" value="files.delete">
								<input type="hidden" name="id" value="<?php echo (int) $file->id; ?>">
								<?php echo HTMLHelper::_('form.token'); ?>
								<button class="btn btn-sm btn-outline-danger" type="submit"><?php echo Text::_('COM_ASKDESK_FILES_DELETE'); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
