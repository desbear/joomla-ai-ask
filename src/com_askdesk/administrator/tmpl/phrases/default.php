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

/** @var \Gss\Component\Askdesk\Administrator\View\Phrases\HtmlView $this */
$e     = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$token = HTMLHelper::_('form.token');
?>
<style>
	.askdesk-phrase { display: grid; grid-template-columns: minmax(140px, 220px) minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 12px 0; border-block-start: 1px solid var(--template-bg-dark-7, #dfe3e7); }
	.askdesk-phrase--off { opacity: .55; }
	.askdesk-phrase__tools { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: flex-end; max-inline-size: 260px; }
	.askdesk-phrase textarea { min-block-size: 64px; }
	@media (max-width: 760px) { .askdesk-phrase { grid-template-columns: 1fr; } .askdesk-phrase__tools { justify-content: flex-start; } }
</style>

<div class="card mb-3">
	<div class="card-body">
		<h2 class="h5 card-title"><?php echo Text::_('COM_ASKDESK_PHRASES_ADD'); ?></h2>
		<p class="text-muted"><?php echo Text::_('COM_ASKDESK_PHRASES_ADD_DESC'); ?></p>
		<form action="index.php?option=com_askdesk" method="post" class="row g-2 align-items-end">
			<div class="col-md-3">
				<label class="form-label" for="phrase-title"><?php echo Text::_('COM_ASKDESK_PHRASES_TITLE'); ?></label>
				<input class="form-control" type="text" id="phrase-title" name="title" maxlength="60" placeholder="<?php echo $e(Text::_('COM_ASKDESK_PHRASES_TITLE_HINT')); ?>">
			</div>
			<div class="col-md-6">
				<label class="form-label" for="phrase-text"><?php echo Text::_('COM_ASKDESK_PHRASES_TEXT'); ?></label>
				<textarea class="form-control" id="phrase-text" name="text" rows="2" maxlength="2000" required></textarea>
			</div>
			<div class="col-md-1">
				<label class="form-check form-switch m-0">
					<input class="form-check-input" type="checkbox" name="pinned" value="1" checked>
					<span class="form-check-label small"><?php echo Text::_('COM_ASKDESK_PHRASES_PINNED'); ?></span>
				</label>
			</div>
			<div class="col-md-2">
				<input type="hidden" name="task" value="phrases.add">
				<?php echo $token; ?>
				<button class="btn btn-primary w-100" type="submit"><?php echo Text::_('COM_ASKDESK_PHRASES_ADD_BUTTON'); ?></button>
			</div>
		</form>
	</div>
</div>

<?php if (!$this->phrases) : ?>
	<div class="alert alert-info"><?php echo Text::_('COM_ASKDESK_PHRASES_NONE'); ?></div>
<?php else : ?>
	<form action="index.php?option=com_askdesk" method="post" class="card">
		<div class="card-body">
			<?php foreach ($this->phrases as $phrase) : $id = (int) $phrase->id; ?>
				<div class="askdesk-phrase<?php echo $phrase->published ? '' : ' askdesk-phrase--off'; ?>">
					<input class="form-control" type="text" name="phrases[<?php echo $id; ?>][title]" value="<?php echo $e($phrase->title); ?>" maxlength="60" aria-label="<?php echo $e(Text::_('COM_ASKDESK_PHRASES_TITLE')); ?>">
					<textarea class="form-control" name="phrases[<?php echo $id; ?>][text]" rows="2" maxlength="2000" aria-label="<?php echo $e(Text::_('COM_ASKDESK_PHRASES_TEXT')); ?>"><?php echo $e($phrase->text); ?></textarea>
					<div class="askdesk-phrase__tools">
						<label class="form-check form-switch m-0">
							<input class="form-check-input" type="checkbox" name="phrases[<?php echo $id; ?>][pinned]" value="1"<?php echo $phrase->pinned ? ' checked' : ''; ?>>
							<span class="form-check-label small"><?php echo Text::_('COM_ASKDESK_PHRASES_PINNED'); ?></span>
						</label>
						<label class="form-check form-switch m-0">
							<input class="form-check-input" type="checkbox" name="phrases[<?php echo $id; ?>][published]" value="1"<?php echo $phrase->published ? ' checked' : ''; ?>>
							<span class="form-check-label small"><?php echo Text::_('COM_ASKDESK_STICKERS_OFFERED'); ?></span>
						</label>
						<button class="btn btn-sm btn-outline-secondary" type="submit" form="phrase-move-<?php echo $id; ?>-up" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_EARLIER')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_EARLIER')); ?>">↑</button>
						<button class="btn btn-sm btn-outline-secondary" type="submit" form="phrase-move-<?php echo $id; ?>-down" title="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_LATER')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STICKERS_LATER')); ?>">↓</button>
						<button class="btn btn-sm btn-outline-danger" type="submit" form="phrase-delete-<?php echo $id; ?>" title="<?php echo $e(Text::_('COM_ASKDESK_FILES_DELETE')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_FILES_DELETE')); ?>">✕</button>
					</div>
				</div>
			<?php endforeach; ?>
			<input type="hidden" name="task" value="phrases.save">
			<?php echo $token; ?>
			<div class="mt-3"><button class="btn btn-success" type="submit"><?php echo Text::_('JSAVE'); ?></button></div>
		</div>
	</form>

	<?php foreach ($this->phrases as $phrase) : $id = (int) $phrase->id; ?>
		<?php foreach (['up' => -1, 'down' => 1] as $way => $by) : ?>
			<form id="phrase-move-<?php echo $id; ?>-<?php echo $way; ?>" action="index.php?option=com_askdesk" method="post" hidden>
				<input type="hidden" name="task" value="phrases.move"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="by" value="<?php echo $by; ?>"><?php echo $token; ?>
			</form>
		<?php endforeach; ?>
		<form id="phrase-delete-<?php echo $id; ?>" action="index.php?option=com_askdesk" method="post" hidden onsubmit="return confirm(this.dataset.ask)" data-ask="<?php echo $e(Text::sprintf('COM_ASKDESK_PHRASES_DELETE_ASK', $phrase->title)); ?>">
			<input type="hidden" name="task" value="phrases.delete"><input type="hidden" name="id" value="<?php echo $id; ?>"><?php echo $token; ?>
		</form>
	<?php endforeach; ?>
<?php endif; ?>
