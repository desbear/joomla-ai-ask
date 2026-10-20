<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Askdesk\Administrator\Desk\Stickers;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \Gss\Component\Askdesk\Administrator\View\Stickers\HtmlView $this */
$e     = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$root  = Uri::root();
$token = HTMLHelper::_('form.token');
?>
<style>
	.askdesk-stickers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
	.askdesk-sticker { display: grid; gap: 8px; padding: 12px; border: 1px solid var(--template-bg-dark-7, #dfe3e7); border-radius: 14px; background: var(--body-bg, #fff); }
	.askdesk-sticker--off { opacity: .55; }
	.askdesk-sticker__picture { display: grid; place-items: center; block-size: 120px; border-radius: 10px; background: repeating-conic-gradient(#f3f5f8 0% 25%, #fff 0% 50%) 50% / 16px 16px; }
	.askdesk-sticker__picture img { max-inline-size: 110px; max-block-size: 110px; object-fit: contain; }
	.askdesk-sticker__tools { display: flex; gap: 4px; align-items: center; }
	.askdesk-sticker__tools .ms-auto { margin-inline-start: auto; }
</style>

<div class="card mb-3">
	<div class="card-body">
		<h2 class="h5 card-title"><?php echo Text::_('COM_ASKDESK_STICKERS_ADD'); ?></h2>
		<p class="text-muted"><?php echo Text::sprintf('COM_ASKDESK_STICKERS_ADD_DESC', (int) (Stickers::MAX_BYTES / 1024)); ?></p>
		<form action="index.php?option=com_askdesk" method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
			<div class="col-md-5">
				<label class="form-label" for="sticker-files"><?php echo Text::_('COM_ASKDESK_STICKERS_FILES'); ?></label>
				<input class="form-control" type="file" id="sticker-files" name="files[]" accept="image/png,image/gif,image/webp,image/jpeg" multiple required>
			</div>
			<div class="col-md-3">
				<label class="form-label" for="sticker-pack"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK'); ?></label>
				<input class="form-control" type="text" id="sticker-pack" name="pack" list="sticker-packs" maxlength="100" placeholder="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_PACK_DEFAULT')); ?>">
				<datalist id="sticker-packs">
					<?php foreach ($this->packs as $pack) : ?>
						<option value="<?php echo $e($pack); ?>"></option>
					<?php endforeach; ?>
				</datalist>
			</div>
			<div class="col-md-2">
				<label class="form-label" for="sticker-who"><?php echo Text::_('COM_ASKDESK_STICKERS_WHO'); ?></label>
				<select class="form-select" id="sticker-who" name="who">
					<option value="all"><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_ALL'); ?></option>
					<option value="staff"><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_STAFF'); ?></option>
				</select>
			</div>
			<div class="col-md-2">
				<input type="hidden" name="task" value="stickers.upload">
				<?php echo $token; ?>
				<button class="btn btn-primary w-100" type="submit"><?php echo Text::_('COM_ASKDESK_STICKERS_UPLOAD'); ?></button>
			</div>
		</form>
	</div>
</div>

<?php if (!$this->stickers) : ?>
	<div class="alert alert-info"><?php echo Text::_('COM_ASKDESK_STICKERS_NONE'); ?></div>
<?php else : ?>
	<form action="index.php?option=com_askdesk" method="post" id="stickers-form">
		<?php foreach ($this->packs as $pack) : ?>
			<h2 class="h5 mt-3 mb-2"><?php echo $e($pack); ?></h2>
			<div class="askdesk-stickers-grid">
				<?php foreach ($this->stickers as $sticker) :
					if ((string) $sticker->pack !== $pack) {
						continue;
					}
					$id = (int) $sticker->id;
					?>
					<div class="askdesk-sticker<?php echo $sticker->published ? '' : ' askdesk-sticker--off'; ?>">
						<div class="askdesk-sticker__picture"><img src="<?php echo $e($root . $sticker->image); ?>" alt="<?php echo $e($sticker->title); ?>" loading="lazy"></div>
						<input class="form-control form-control-sm" type="text" name="stickers[<?php echo $id; ?>][title]" value="<?php echo $e($sticker->title); ?>" maxlength="100" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_TITLE')); ?>" placeholder="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_TITLE')); ?>">
						<input class="form-control form-control-sm" type="text" name="stickers[<?php echo $id; ?>][pack]" value="<?php echo $e($sticker->pack); ?>" list="sticker-packs" maxlength="100" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_PACK')); ?>">
						<select class="form-select form-select-sm" name="stickers[<?php echo $id; ?>][who]" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_WHO')); ?>">
							<option value="all"<?php echo $sticker->who === 'all' ? ' selected' : ''; ?>><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_ALL'); ?></option>
							<option value="staff"<?php echo $sticker->who === 'staff' ? ' selected' : ''; ?>><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_STAFF'); ?></option>
						</select>
						<div class="askdesk-sticker__tools">
							<label class="form-check form-switch m-0">
								<input class="form-check-input" type="checkbox" name="stickers[<?php echo $id; ?>][published]" value="1"<?php echo $sticker->published ? ' checked' : ''; ?>>
								<span class="form-check-label small"><?php echo Text::_('COM_ASKDESK_STICKERS_OFFERED'); ?></span>
							</label>
							<button class="btn btn-sm btn-outline-secondary ms-auto" type="submit" form="sticker-move-<?php echo $id; ?>-up" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_EARLIER')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_EARLIER')); ?>">←</button>
							<button class="btn btn-sm btn-outline-secondary" type="submit" form="sticker-move-<?php echo $id; ?>-down" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_LATER')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_LATER')); ?>">→</button>
							<button class="btn btn-sm btn-outline-danger" type="submit" form="sticker-delete-<?php echo $id; ?>" title="<?php echo $e(Text::_('COM_ASKDESK_FILES_DELETE')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_FILES_DELETE')); ?>">✕</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<input type="hidden" name="task" value="stickers.save">
		<?php echo $token; ?>
		<div class="mt-3"><button class="btn btn-success" type="submit"><?php echo Text::_('JSAVE'); ?></button></div>
	</form>

	<?php foreach ($this->stickers as $sticker) : $id = (int) $sticker->id; ?>
		<?php foreach (['up' => -1, 'down' => 1] as $way => $by) : ?>
			<form id="sticker-move-<?php echo $id; ?>-<?php echo $way; ?>" action="index.php?option=com_askdesk" method="post" hidden>
				<input type="hidden" name="task" value="stickers.move"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="by" value="<?php echo $by; ?>"><?php echo $token; ?>
			</form>
		<?php endforeach; ?>
		<form id="sticker-delete-<?php echo $id; ?>" action="index.php?option=com_askdesk" method="post" hidden onsubmit="return confirm(this.dataset.ask)" data-ask="<?php echo $e(Text::sprintf('COM_ASKDESK_STICKERS_DELETE_ASK', $sticker->title)); ?>">
			<input type="hidden" name="task" value="stickers.delete"><input type="hidden" name="id" value="<?php echo $id; ?>"><?php echo $token; ?>
		</form>
	<?php endforeach; ?>
<?php endif; ?>
