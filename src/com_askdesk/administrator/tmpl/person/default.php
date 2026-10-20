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
use Joomla\CMS\Router\Route;

/** @var \Gss\Component\Askdesk\Administrator\View\Person\HtmlView $this */
$e = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

HTMLHelper::_('behavior.formvalidator');
$this->getDocument()->getWebAssetManager()->useScript('showon');
?>
<form action="<?php echo Route::_('index.php?option=com_askdesk&view=person&id=' . (int) $this->person->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
	<div class="row">
		<div class="col-lg-8">
			<div class="card">
				<div class="card-body">
					<p class="text-muted"><?php echo Text::_('COM_ASKDESK_PERSON_INTRO'); ?></p>
					<?php echo $this->form->renderFieldset('person'); ?>
				</div>
			</div>
		</div>
		<div class="col-lg-4">
			<div class="card">
				<div class="card-body text-center">
					<p class="text-muted small mb-2"><?php echo Text::_('COM_ASKDESK_PERSON_PREVIEW'); ?></p>
					<?php if ($this->profile['face']) : ?>
						<img src="<?php echo $e($this->profile['face']['src']); ?>" alt="" width="96" height="96" style="border-radius: 50%; object-fit: cover; background: #ebf5ff">
					<?php else : ?>
						<span class="d-inline-grid rounded-circle text-primary fs-2" style="width: 96px; height: 96px; place-items: center; background: #ebf5ff"><?php echo $e(mb_substr($this->profile['name'], 0, 1)); ?></span>
					<?php endif; ?>
					<p class="mt-2 mb-0 fw-medium"><?php echo $e($this->profile['name']); ?></p>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" name="task" value="">
	<input type="hidden" name="id" value="<?php echo (int) $this->person->id; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
