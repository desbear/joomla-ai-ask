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

/** @var \Gss\Component\Askdesk\Administrator\View\Dashboard\HtmlView $this */
$e = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<div class="row">
	<div class="col-lg-7">
		<div class="card mb-3">
			<div class="card-body">
				<h2 class="card-title h4"><?php echo Text::_('COM_ASKDESK_DASHBOARD_RATINGS'); ?>
					<?php if ($this->ratings['count']) : ?><small class="text-muted fs-6 ms-2"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_RATINGS_COUNT', (int) $this->ratings['count']); ?></small><?php endif; ?>
				</h2>
				<?php if (!$this->ratings['count']) : ?>
					<p class="text-muted mb-0"><?php echo Text::_('COM_ASKDESK_DASHBOARD_RATINGS_NONE'); ?></p>
				<?php else : ?>
					<div class="row g-3 mb-2">
						<?php foreach ($this->ratings['dimensions'] as $dimension) : ?>
							<div class="col-sm-6 col-xl-4">
								<div class="border rounded-3 p-3 h-100">
									<div class="text-muted small"><?php echo $e($dimension['label']); ?></div>
									<?php if ($dimension['type'] === 'choice') : ?>
										<?php arsort($dimension['choices']); ?>
										<?php foreach ($dimension['choices'] as $choice => $count) : ?>
											<div class="small"><?php echo $e($choice); ?> <strong><?php echo round($count / max(1, $dimension['n']) * 100); ?>%</strong></div>
										<?php endforeach; ?>
									<?php elseif ($dimension['type'] === 'thumbs') : ?>
										<div class="fs-3 fw-semibold"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_THUMBS_UP', round($dimension['sum'] / max(1, $dimension['n']) * 100)); ?></div>
									<?php else : ?>
										<div class="fs-3 fw-semibold"><?php echo number_format($dimension['sum'] / max(1, $dimension['n']), 1); ?><small class="text-muted fs-6"> / <?php echo $dimension['type'] === 'stars' ? 5 : 10; ?></small></div>
									<?php endif; ?>
									<div class="text-muted small"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_RATINGS_COUNT', (int) $dimension['n']); ?></div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ($this->ratings['comments']) : ?>
						<h3 class="h6 mt-3"><?php echo Text::_('COM_ASKDESK_DASHBOARD_COMMENTS'); ?></h3>
						<ul class="list-unstyled mb-0">
							<?php foreach ($this->ratings['comments'] as $comment) : ?>
								<li class="border-bottom py-2"><?php echo $e($comment['comment']); ?><br><small class="text-muted"><?php echo $e(HTMLHelper::_('date', $comment['time'], Text::_('DATE_FORMAT_LC5'))); ?><?php echo $comment['staff'] !== '' ? ' · ' . $e($comment['staff']) : ''; ?></small></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="card mb-3">
			<div class="card-body">
				<h2 class="card-title h4"><?php echo Text::_('COM_ASKDESK_DASHBOARD_SETUP'); ?></h2>
				<ul class="list-unstyled mb-0">
					<?php foreach ($this->checks as $name => $ok) : ?>
						<li class="mb-2">
							<span class="badge <?php echo $ok ? 'bg-success' : 'bg-warning'; ?>"><?php echo Text::_($ok ? 'JYES' : 'JNO'); ?></span>
							<?php echo Text::_('COM_ASKDESK_DASHBOARD_CHECK_' . strtoupper($name)); ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="mt-3 mb-0 text-muted"><?php echo Text::_('COM_ASKDESK_DASHBOARD_HOWTO'); ?></p>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<?php $archive = $this->archive; $last = $archive['last']; ?>
		<div class="card mb-3">
			<div class="card-body">
				<h2 class="card-title h4"><?php echo Text::_('COM_ASKDESK_DASHBOARD_ARCHIVE'); ?></h2>
				<?php if ($archive['late'] || ($last && $last['error'] !== '') || $archive['problem'] !== '') : ?>
					<div class="alert alert-warning mb-3">
						<?php echo $archive['late'] ? Text::_('COM_ASKDESK_DASHBOARD_ARCHIVE_LATE') : ''; ?>
						<?php foreach (array_unique(array_filter([$archive['problem'], $last['error'] ?? ''])) as $error) : ?>
							<div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p class="mb-1">
					<?php echo $last
						? Text::sprintf('COM_ASKDESK_DASHBOARD_ARCHIVE_LAST', HTMLHelper::_('date', $last['time'], Text::_('DATE_FORMAT_LC2')), $last['exported'], $last['deleted'])
						: Text::_('COM_ASKDESK_DASHBOARD_ARCHIVE_NEVER'); ?>
				</p>
				<p class="mb-1"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_ARCHIVE_DUE', $archive['due']); ?></p>
				<p class="mb-0 text-muted small"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_ARCHIVE_FOLDER', htmlspecialchars($archive['folder'], ENT_QUOTES, 'UTF-8')); ?></p>
			</div>
		</div>
		<div class="card mb-3">
			<div class="card-body">
				<h2 class="card-title h4"><?php echo Text::_('COM_ASKDESK_DASHBOARD_NOW'); ?></h2>
				<p>
					<?php foreach (['waiting', 'active', 'message'] as $status) : ?>
						<span class="me-3"><?php echo Text::_('COM_ASKDESK_CONSOLE_' . strtoupper($status)); ?>: <strong><?php echo (int) ($this->counts[$status] ?? 0); ?></strong></span>
					<?php endforeach; ?>
				</p>
				<p class="mb-1"><?php echo Text::_('COM_ASKDESK_CONSOLE_STAFF_NOW'); ?>:</p>
				<?php if ($this->present) : ?>
					<ul>
						<?php foreach ($this->present as $person) : ?>
							<li><?php echo $e($person['name']); ?> · <?php echo Text::_('COM_ASKDESK_CONSOLE_' . strtoupper($person['status'])); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="text-muted"><?php echo Text::_('COM_ASKDESK_DASHBOARD_NOBODY'); ?></p>
				<?php endif; ?>
				<a class="btn btn-primary" href="<?php echo $e($this->console); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_ASKDESK_DASHBOARD_OPEN_CONSOLE'); ?></a>
				<a class="btn btn-secondary ms-2" href="<?php echo $e('index.php?option=com_askdesk&view=staff'); ?>"><?php echo Text::_('COM_ASKDESK_DASHBOARD_STAFF'); ?></a>
				<a class="btn btn-secondary ms-2" href="<?php echo $e('index.php?option=com_askdesk&view=files'); ?>"><?php echo Text::_('COM_ASKDESK_SUBMENU_FILES'); ?></a>
			</div>
		</div>
	</div>
</div>
