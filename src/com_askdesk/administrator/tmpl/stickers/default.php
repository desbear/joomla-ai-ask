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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * The packs, each a block shown by one of its stickers, in the order they are dragged to (0.6.8), ticked to be
 * offered, stopped or deleted from the toolbar. One pack opened: an edit page (saved from the toolbar) of the pack
 * as a whole and its stickers, dragged in order, one picked to show it, ticked to be offered, stopped, made WebPs or
 * deleted. Pictures are added from the toolbar's button, in a window they are dropped in, many at once.
 *
 * @var \Gss\Component\Askdesk\Administrator\View\Stickers\HtmlView $this
 */
$e     = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$root  = Uri::root();
$token = HTMLHelper::_('form.token');
$pack  = $this->pack;
$whos  = ['all' => 'COM_ASKDESK_STICKERS_WHO_ALL', 'staff' => 'COM_ASKDESK_STICKERS_WHO_STAFF', 'mixed' => 'COM_ASKDESK_STICKERS_WHO_MIXED'];
$who   = $pack && $pack->who !== 'mixed' ? $pack->who : (string) $this->defaults->get('sticker_who', 'all');
$webp  = (int) $this->defaults->get('sticker_webp', 1) === 1;

HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('bootstrap.modal', '#sticker-upload');
Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('dragula')->useStyle('dragula');
?>
<style>
	.askdesk-stickers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
	.askdesk-sticker, .askdesk-pack { position: relative; display: grid; gap: 8px; align-content: start; padding: 12px; border: 1px solid var(--template-bg-dark-7, #dfe3e7); border-radius: 14px; background: var(--body-bg, #fff); }
	.askdesk-sticker--off { opacity: .55; }
	.askdesk-sticker--cover { border-color: var(--template-link-color, #2a69b8); box-shadow: 0 0 0 1px var(--template-link-color, #2a69b8); }
	.askdesk-sticker__picture, .askdesk-pack__picture { display: grid; place-items: center; block-size: 130px; border-radius: 10px; background: repeating-conic-gradient(#f3f5f8 0% 25%, #fff 0% 50%) 50% / 16px 16px; color: inherit; text-decoration: none; }
	.askdesk-sticker__picture img, .askdesk-pack__picture img { max-inline-size: 118px; max-block-size: 118px; object-fit: contain; }
	.askdesk-pack__picture:hover, .askdesk-pack__picture:focus-visible { outline: 2px solid var(--template-link-color, #2a69b8); outline-offset: 2px; }
	.askdesk-card__top { display: flex; gap: 8px; align-items: center; }
	.askdesk-card__top .form-check-input { margin: 0; }
	.askdesk-drag { display: grid; place-items: center; inline-size: 28px; block-size: 28px; margin-inline-start: auto; padding: 0; border: 0; border-radius: 6px; background: none; color: var(--template-text-dark, #495057); cursor: grab; line-height: 1; }
	.askdesk-drag:hover, .askdesk-drag:focus-visible { background: rgba(0, 0, 0, .06); }
	.askdesk-packs { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
	.askdesk-pack__name { margin: 0; font-size: 1rem; font-weight: 600; overflow-wrap: anywhere; }
	.askdesk-pack__meta { margin: 0; font-size: .85rem; }
	.askdesk-sticker__cover { display: flex; gap: 6px; align-items: center; margin: 0; font-size: .85rem; }
	.askdesk-sticker__cover .form-check-input { margin: 0; }
	.gu-mirror { opacity: .9; transform: rotate(1.5deg); }
	.gu-transit { opacity: .35; }
	.askdesk-drop { display: grid; place-items: center; gap: 6px; min-block-size: 150px; padding: 18px; border: 2px dashed var(--template-bg-dark-20, #b9c3cf); border-radius: 14px; text-align: center; cursor: pointer; }
	.askdesk-drop.is-over { border-color: var(--template-link-color, #2a69b8); background: rgba(42, 105, 184, .06); }
	.askdesk-drop__thumbs { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; }
	.askdesk-drop__thumbs img { inline-size: 48px; block-size: 48px; object-fit: contain; border-radius: 6px; background: #f3f5f8; }
</style>

<form action="<?php echo Route::_('index.php?option=com_askdesk&view=stickers' . ($pack ? '&pack=' . $pack->id : '')); ?>" method="post" name="adminForm" id="adminForm">
	<?php if (!$pack) : ?>
		<?php if (!$this->packList) : ?>
			<div class="alert alert-info"><?php echo Text::_('COM_ASKDESK_STICKERS_NONE'); ?></div>
		<?php else : ?>
			<p class="text-muted"><?php echo Text::_('COM_ASKDESK_STICKERS_PACKS_DESC'); ?></p>
			<div class="askdesk-packs" data-order-url="<?php echo $e(Route::_('index.php?option=com_askdesk&task=stickers.orderPacks', false)); ?>" data-token="<?php echo $e(Session::getFormToken()); ?>">
				<?php foreach ($this->packList as $at => $item) :
					$open = Route::_('index.php?option=com_askdesk&view=stickers&pack=' . $item->id);
					?>
					<div class="askdesk-pack" data-id="<?php echo $item->id; ?>">
						<div class="askdesk-card__top">
							<?php echo HTMLHelper::_('grid.id', $at, $item->id, false, 'cid', 'cb', $item->name); ?>
							<button class="askdesk-drag" type="button" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_DRAG')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_DRAG')); ?>"><span class="icon-arrows-alt" aria-hidden="true"></span></button>
						</div>
						<a class="askdesk-pack__picture" href="<?php echo $open; ?>" aria-label="<?php echo $e(Text::sprintf('COM_ASKDESK_STICKERS_PACK_OPEN', $item->name)); ?>">
							<?php if ($item->picture !== '') : ?><img src="<?php echo $e($item->picture); ?>" alt="" loading="lazy"><?php endif; ?>
						</a>
						<h2 class="askdesk-pack__name"><a href="<?php echo $open; ?>"><?php echo $e($item->name); ?></a></h2>
						<p class="askdesk-pack__meta text-muted"><?php echo Text::sprintf('COM_ASKDESK_STICKERS_PACK_COUNT', $item->count, $item->offered); ?> · <?php echo Text::_($whos[$item->who] ?? $whos['all']); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="card mb-3">
			<div class="card-body row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label" for="pack-name"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_NAME'); ?></label>
					<input class="form-control" type="text" id="pack-name" name="pack_name" value="<?php echo $e($pack->name); ?>" maxlength="100" required>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="pack-who"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_WHO'); ?></label>
					<select class="form-select" id="pack-who" name="pack_who">
						<option value=""><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_EACH'); ?></option>
						<option value="all"><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_ALL'); ?></option>
						<option value="staff"><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_STAFF'); ?></option>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="pack-published"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_OFFERED'); ?></label>
					<select class="form-select" id="pack-published" name="pack_published">
						<option value=""><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_EACH'); ?></option>
						<option value="1"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_ALL_ON'); ?></option>
						<option value="0"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_ALL_OFF'); ?></option>
					</select>
				</div>
				<p class="form-text m-0"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK_DESC'); ?></p>
			</div>
		</div>

		<div class="d-flex align-items-center gap-2 mb-2">
			<?php echo HTMLHelper::_('grid.checkall'); ?>
			<span class="small text-muted"><?php echo Text::_('COM_ASKDESK_STICKERS_PICK_DESC'); ?></span>
		</div>
		<div class="askdesk-stickers-grid" data-sortable>
			<?php foreach ($this->stickers as $at => $sticker) :
				$id = (int) $sticker->id;
				?>
				<div class="askdesk-sticker<?php echo $sticker->published ? '' : ' askdesk-sticker--off'; ?><?php echo $id === $pack->cover ? ' askdesk-sticker--cover' : ''; ?>">
					<input type="hidden" name="order[]" value="<?php echo $id; ?>">
					<div class="askdesk-card__top">
						<?php echo HTMLHelper::_('grid.id', $at, $id, false, 'cid', 'cb', $sticker->title); ?>
						<label class="askdesk-sticker__cover">
							<input class="form-check-input" type="radio" name="cover" value="<?php echo $id; ?>"<?php echo $id === $pack->cover ? ' checked' : ''; ?>>
							<span><?php echo Text::_('COM_ASKDESK_STICKERS_COVER'); ?></span>
						</label>
						<button class="askdesk-drag" type="button" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_DRAG')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_DRAG')); ?>"><span class="icon-arrows-alt" aria-hidden="true"></span></button>
					</div>
					<div class="askdesk-sticker__picture"><img src="<?php echo $e($root . $sticker->image); ?>" alt="<?php echo $e($sticker->title); ?>" loading="lazy"></div>
					<input class="form-control form-control-sm" type="text" name="stickers[<?php echo $id; ?>][title]" value="<?php echo $e($sticker->title); ?>" maxlength="100" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_TITLE')); ?>" placeholder="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_TITLE')); ?>">
					<select class="form-select form-select-sm" name="stickers[<?php echo $id; ?>][pack]" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_PACK')); ?>" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_PACK_MOVE')); ?>">
						<?php foreach ($this->packs as $name) : ?>
							<option value="<?php echo $e($name); ?>"<?php echo $name === $pack->name ? ' selected' : ''; ?>><?php echo $e($name); ?></option>
						<?php endforeach; ?>
					</select>
					<select class="form-select form-select-sm" name="stickers[<?php echo $id; ?>][who]" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_WHO')); ?>">
						<option value="all"<?php echo $sticker->who === 'all' ? ' selected' : ''; ?>><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_ALL'); ?></option>
						<option value="staff"<?php echo $sticker->who === 'staff' ? ' selected' : ''; ?>><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_STAFF'); ?></option>
					</select>
					<label class="form-check form-switch m-0">
						<input class="form-check-input" type="checkbox" name="stickers[<?php echo $id; ?>][published]" value="1"<?php echo $sticker->published ? ' checked' : ''; ?>>
						<span class="form-check-label small"><?php echo Text::_('COM_ASKDESK_STICKERS_OFFERED'); ?></span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="pack_id" value="<?php echo $pack->id; ?>">
		<input type="hidden" name="back_pack" value="<?php echo $e($pack->name); ?>">
	<?php endif; ?>
	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<?php echo $token; ?>
</form>

<div class="modal fade" id="sticker-upload" tabindex="-1" aria-labelledby="sticker-upload-title" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<form class="modal-content" action="index.php?option=com_askdesk" method="post" enctype="multipart/form-data">
			<div class="modal-header">
				<h2 class="modal-title h5" id="sticker-upload-title"><?php echo Text::_($pack ? 'COM_ASKDESK_STICKERS_ADD_TO' : 'COM_ASKDESK_STICKERS_ADD'); ?></h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $e(Text::_('JCLOSE')); ?>"></button>
			</div>
			<div class="modal-body d-grid gap-3">
				<label class="askdesk-drop" for="sticker-files">
					<span class="icon-upload fs-3" aria-hidden="true"></span>
					<span data-drop-say data-none="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_DROP')); ?>" data-some="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_DROP_N')); ?>"><?php echo Text::_('COM_ASKDESK_STICKERS_DROP'); ?></span>
					<span class="askdesk-drop__thumbs" data-drop-thumbs></span>
				</label>
				<input class="visually-hidden" type="file" id="sticker-files" name="files[]" accept="image/png,image/gif,image/webp,image/jpeg" multiple required>
				<p class="text-muted small m-0"><?php echo Text::sprintf('COM_ASKDESK_STICKERS_ADD_DESC', (int) (Stickers::MAX_BYTES / 1024)); ?></p>
				<div class="row g-2">
					<div class="col-md-6">
						<label class="form-label" for="sticker-pack"><?php echo Text::_('COM_ASKDESK_STICKERS_PACK'); ?></label>
						<input class="form-control" type="text" id="sticker-pack" name="pack" list="sticker-packs" maxlength="100" value="<?php echo $pack ? $e($pack->name) : ''; ?>" placeholder="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_PACK_DEFAULT')); ?>">
						<datalist id="sticker-packs">
							<?php foreach ($this->packs as $name) : ?>
								<option value="<?php echo $e($name); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>
					<div class="col-md-6">
						<label class="form-label" for="sticker-who"><?php echo Text::_('COM_ASKDESK_STICKERS_WHO'); ?></label>
						<select class="form-select" id="sticker-who" name="who">
							<option value="all"<?php echo $who === 'staff' ? '' : ' selected'; ?>><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_ALL'); ?></option>
							<option value="staff"<?php echo $who === 'staff' ? ' selected' : ''; ?>><?php echo Text::_('COM_ASKDESK_STICKERS_WHO_STAFF'); ?></option>
						</select>
					</div>
				</div>
				<label class="form-check m-0">
					<input class="form-check-input" type="checkbox" name="webp" value="1"<?php echo $webp ? ' checked' : ''; ?>>
					<span class="form-check-label"><?php echo Text::_('COM_ASKDESK_STICKERS_WEBP'); ?></span>
					<span class="form-text d-block"><?php echo Text::_('COM_ASKDESK_STICKERS_WEBP_DESC'); ?></span>
				</label>
			</div>
			<div class="modal-footer">
				<input type="hidden" name="task" value="stickers.upload">
				<?php if ($pack) : ?><input type="hidden" name="back_pack" value="<?php echo $e($pack->name); ?>"><?php endif; ?>
				<?php echo $token; ?>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
				<button type="submit" class="btn btn-success"><span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('COM_ASKDESK_STICKERS_UPLOAD'); ?></button>
			</div>
		</form>
	</div>
</div>

<script>
// After Joomla's deferred scripts (dragula among them)
document.addEventListener('DOMContentLoaded', () => {
	// Pictures dropped in the window join those already picked; each shown small
	const input = document.getElementById('sticker-files');
	const zone = document.querySelector('.askdesk-drop');
	const say = zone.querySelector('[data-drop-say]');
	const thumbs = zone.querySelector('[data-drop-thumbs]');
	const show = () => {
		thumbs.replaceChildren(...[...input.files].slice(0, 40).map((file) => Object.assign(document.createElement('img'), { src: URL.createObjectURL(file), alt: file.name, title: file.name })));
		say.textContent = input.files.length ? say.dataset.some.replace('%d', input.files.length) : say.dataset.none;
	};

	input.addEventListener('change', show);
	['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (e) => { e.preventDefault(); zone.classList.add('is-over'); }));
	['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, () => zone.classList.remove('is-over')));
	zone.addEventListener('drop', (e) => {
		e.preventDefault();
		const all = new DataTransfer();
		[...input.files, ...e.dataTransfer.files].filter((file) => /^image\/(png|gif|webp|jpeg)$/.test(file.type)).slice(0, 50).forEach((file) => all.items.add(file));
		input.files = all.files;
		show();
	});

	// Dragged in order by their handle: the packs kept at once, a pack's stickers with the page
	const grip = (el, from, handle) => !!handle.closest('.askdesk-drag');
	const packs = document.querySelector('.askdesk-packs');
	const stickers = document.querySelector('[data-sortable]');

	if (packs && window.dragula) {
		dragula([packs], { moves: grip }).on('drop', () => {
			const body = new FormData();
			packs.querySelectorAll('.askdesk-pack').forEach((el) => body.append('order[]', el.dataset.id));
			body.append(packs.dataset.token, '1');
			fetch(packs.dataset.orderUrl, { method: 'POST', body, credentials: 'same-origin' })
				.then((r) => r.json())
				.then((r) => { if (!r.ok) throw new Error(); })
				.catch(() => Joomla.renderMessages({ error: [<?php echo json_encode(Text::_('COM_ASKDESK_STICKERS_ORDER_FAILED')); ?>] }));
		});
	}

	if (stickers && window.dragula) {
		dragula([stickers], { moves: grip });
	}

	// The one shown for the pack marked as soon as it is picked
	document.querySelectorAll('input[name="cover"]').forEach((radio) => radio.addEventListener('change', () => {
		document.querySelectorAll('.askdesk-sticker--cover').forEach((el) => el.classList.remove('askdesk-sticker--cover'));
		radio.closest('.askdesk-sticker').classList.add('askdesk-sticker--cover');
	}));
});
</script>
