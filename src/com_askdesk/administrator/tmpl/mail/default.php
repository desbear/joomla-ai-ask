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

/** @var \Gss\Component\Askdesk\Administrator\View\Mail\HtmlView $this */
$e = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<form action="index.php?option=com_askdesk" method="post" name="adminForm" id="adminForm" class="askdesk-mail">
	<div class="askdesk-mail__head">
		<div class="askdesk-mail__subject">
			<label class="form-label" for="askdesk-mail-subject"><?php echo Text::_('COM_ASKDESK_MAILS_SUBJECT'); ?></label>
			<input class="form-control" type="text" id="askdesk-mail-subject" name="subject" maxlength="255" required value="<?php echo $e($this->subject); ?>">
		</div>
		<label class="form-check form-switch askdesk-mail__on">
			<input class="form-check-input" type="checkbox" name="published" value="1"<?php echo $this->published ? ' checked' : ''; ?>>
			<span class="form-check-label"><?php echo Text::_('COM_ASKDESK_MAILS_USE'); ?></span>
		</label>
		<div class="askdesk-mail__tools">
			<button class="btn btn-outline-primary" type="button" data-mail-test><?php echo Text::_('COM_ASKDESK_MAILS_TEST'); ?></button>
			<button class="btn btn-outline-secondary" type="button" data-mail-restore><?php echo Text::_('COM_ASKDESK_MAILS_RESTORE'); ?></button>
		</div>
	</div>

	<div class="askdesk-mail__vars" role="group" aria-label="<?php echo $e(Text::_('COM_ASKDESK_MAILS_VARS')); ?>">
		<span class="askdesk-mail__vars-title"><?php echo Text::_('COM_ASKDESK_MAILS_VARS'); ?></span>
		<?php foreach ($this->data['vars'] as $var) : ?>
			<button class="askdesk-mail__var" type="button" data-mail-var="<?php echo $e($var['token']); ?>" title="<?php echo $e($var['label']); ?>">
				<code><?php echo $e($var['token']); ?></code> <span><?php echo $e($var['label']); ?></span>
			</button>
		<?php endforeach; ?>
		<span class="askdesk-mail__vars-hint"><?php echo Text::_('COM_ASKDESK_MAILS_VARS_HINT'); ?></span>
	</div>

	<div class="askdesk-mail__status" role="status" aria-live="polite" hidden></div>

	<div class="askdesk-mail__editor" id="askdesk-mail-editor"></div>

	<input type="hidden" name="html" value="">
	<input type="hidden" name="design" value="">
	<input type="hidden" name="kind" value="<?php echo $e($this->kind); ?>">
	<input type="hidden" name="language" value="<?php echo $e($this->language); ?>">
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
<script type="application/json" id="askdesk-mail-data"><?php echo json_encode($this->data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
