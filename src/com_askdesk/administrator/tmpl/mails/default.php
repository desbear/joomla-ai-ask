<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Askdesk\Administrator\Desk\Mails;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/** @var \Gss\Component\Askdesk\Administrator\View\Mails\HtmlView $this */
$e     = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$token = Session::getFormToken();
$link  = static fn (string $task, string $kind, string $language, array $more = []) => 'index.php?' . http_build_query(['option' => 'com_askdesk', 'task' => 'mails.' . $task, 'kind' => $kind, 'language' => $language, $token => 1] + $more);
?>
<style>
	.askdesk-mails td, .askdesk-mails th { vertical-align: middle; }
	.askdesk-mails__kind { min-inline-size: 220px; }
	.askdesk-mails__cell { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
	.askdesk-mails__subject { display: block; max-inline-size: 260px; overflow: hidden; color: var(--template-text-light, #6b7280); font-size: .8125rem; text-overflow: ellipsis; white-space: nowrap; }
</style>

<div class="card mb-3">
	<div class="card-body">
		<p class="mb-0"><?php echo Text::_('COM_ASKDESK_MAILS_DESC'); ?></p>
	</div>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table askdesk-mails mb-0">
			<thead>
				<tr>
					<th scope="col"><?php echo Text::_('COM_ASKDESK_MAILS_KIND'); ?></th>
					<?php foreach ($this->languages as $tag => $name) : ?>
						<th scope="col"><?php echo $e($name); ?> <span class="text-muted small"><?php echo $e($tag); ?></span></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach (Mails::KINDS as $kind => [$to]) : $key = 'COM_ASKDESK_MAILS_KIND_' . strtoupper($kind); ?>
					<tr>
						<th scope="row" class="askdesk-mails__kind">
							<?php echo Text::_($key); ?>
							<span class="badge bg-<?php echo $to === 'visitor' ? 'info' : 'secondary'; ?> ms-1"><?php echo Text::_('COM_ASKDESK_MAILS_TO_' . strtoupper($to)); ?></span>
							<div class="small text-muted fw-normal"><?php echo Text::_($key . '_DESC'); ?></div>
						</th>
						<?php foreach ($this->languages as $tag => $name) : $row = $this->made[$kind][$tag] ?? null; $edit = 'index.php?option=com_askdesk&view=mail&kind=' . $kind . '&language=' . rawurlencode($tag); ?>
							<td>
								<div class="askdesk-mails__cell">
									<?php if (!$row) : ?>
										<span class="badge bg-light text-dark"><?php echo Text::_('COM_ASKDESK_MAILS_BUILTIN'); ?></span>
										<a class="btn btn-sm btn-outline-primary" href="<?php echo $e($edit); ?>"><?php echo Text::_('COM_ASKDESK_MAILS_DESIGN'); ?></a>
									<?php else : ?>
										<?php if ($row->published) : ?>
											<span class="badge bg-success"><?php echo Text::_('COM_ASKDESK_MAILS_IN_USE'); ?></span>
										<?php else : ?>
											<span class="badge bg-warning text-dark"><?php echo Text::_('COM_ASKDESK_MAILS_OFF'); ?></span>
										<?php endif; ?>
										<a class="btn btn-sm btn-primary" href="<?php echo $e($edit); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a>
										<a class="btn btn-sm btn-outline-secondary" href="<?php echo $e($link('publish', $kind, $tag, ['on' => $row->published ? 0 : 1])); ?>"><?php echo Text::_($row->published ? 'COM_ASKDESK_MAILS_SWITCH_OFF' : 'COM_ASKDESK_MAILS_SWITCH_ON'); ?></a>
										<a class="btn btn-sm btn-outline-danger" href="<?php echo $e($link('delete', $kind, $tag)); ?>" onclick="return confirm(<?php echo $e(json_encode(Text::_('COM_ASKDESK_MAILS_DELETE_CONFIRM'))); ?>)"><?php echo Text::_('COM_ASKDESK_MAILS_DELETE'); ?></a>
										<span class="askdesk-mails__subject" title="<?php echo $e($row->subject); ?>"><?php echo $e($row->subject); ?> · <?php echo HTMLHelper::_('date', $row->modified, Text::_('DATE_FORMAT_LC5')); ?></span>
									<?php endif; ?>
								</div>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
