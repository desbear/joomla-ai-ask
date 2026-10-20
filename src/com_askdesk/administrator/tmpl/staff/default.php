<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Gss\Component\Askdesk\Administrator\View\Staff\HtmlView $this */
$e    = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$here = Factory::getDate('-' . Staff::PRESENT_SECONDS . ' seconds')->toUnix();
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
	<p class="text-muted mb-0 me-auto"><?php echo Text::_('COM_ASKDESK_STAFF_INTRO'); ?></p>
	<div class="btn-group" role="group" aria-label="<?php echo Text::_('COM_ASKDESK_STAFF_PERIOD'); ?>">
		<?php foreach ([7, 30, 90, 365] as $days) : ?>
			<a class="btn btn-sm <?php echo $this->days === $days ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo $e('index.php?option=com_askdesk&view=staff&days=' . $days); ?>"><?php echo Text::sprintf('COM_ASKDESK_STAFF_DAYS', $days); ?></a>
		<?php endforeach; ?>
	</div>
</div>
<div class="card mb-3">
	<div class="card-body">
		<h2 class="h5 card-title"><?php echo Text::_('COM_ASKDESK_STAFF_ADD'); ?></h2>
		<form action="index.php" method="get" class="d-flex gap-2 mb-2" role="search">
			<input type="hidden" name="option" value="com_askdesk">
			<input type="hidden" name="view" value="staff">
			<input type="hidden" name="days" value="<?php echo (int) $this->days; ?>">
			<input type="search" name="q" class="form-control" value="<?php echo $e($this->search); ?>" placeholder="<?php echo $e(Text::_('COM_ASKDESK_STAFF_SEARCH')); ?>" aria-label="<?php echo $e(Text::_('COM_ASKDESK_STAFF_SEARCH')); ?>">
			<button class="btn btn-primary text-nowrap" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
		</form>
		<?php if ($this->search !== '') : ?>
			<?php if (!$this->found) : ?>
				<p class="text-muted mb-0"><?php echo Text::_('COM_ASKDESK_STAFF_NOT_FOUND'); ?></p>
			<?php else : ?>
				<ul class="list-group">
					<?php foreach ($this->found as $member) : ?>
						<li class="list-group-item d-flex align-items-center gap-3">
							<span class="me-auto"><?php echo $e($member->name); ?> <small class="text-muted"><?php echo $e($member->username); ?> · <?php echo $e($member->email); ?></small></span>
							<form action="index.php?option=com_askdesk" method="post" class="m-0">
								<input type="hidden" name="task" value="person.add">
								<input type="hidden" name="id" value="<?php echo (int) $member->id; ?>">
								<?php echo HTMLHelper::_('form.token'); ?>
								<button class="btn btn-sm btn-success" type="submit"><?php echo Text::_('COM_ASKDESK_STAFF_MAKE'); ?></button>
							</form>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php if (!$this->people) : ?>
	<div class="alert alert-info"><?php echo Text::_('COM_ASKDESK_STAFF_NONE'); ?></div>
<?php else : ?>
	<table class="table align-middle">
		<thead>
			<tr>
				<th scope="col" style="width: 64px"><?php echo Text::_('COM_ASKDESK_PERSON_AVATAR'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_STAFF_SHOWN_AS'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_STAFF_ACCOUNT'); ?></th>
				<th scope="col" class="text-end"><?php echo Text::_('COM_ASKDESK_STAFF_CHATS'); ?></th>
				<th scope="col" class="text-end"><?php echo Text::_('COM_ASKDESK_STAFF_MESSAGES'); ?></th>
				<th scope="col" class="text-end"><?php echo Text::_('COM_ASKDESK_STAFF_WAITED'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_STAFF_SATISFACTION'); ?></th>
				<th scope="col"><?php echo Text::_('COM_ASKDESK_STAFF_NOW'); ?></th>
				<th scope="col"><span class="visually-hidden"><?php echo Text::_('COM_ASKDESK_ACTIONS'); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->people as $person) :
				$profile = $this->profiles[(int) $person->id] ?? ['name' => $person->name, 'face' => null];
				$present = $person->seen && strtotime($person->seen . ' UTC') >= $here && $person->status !== 'away';
				$link    = 'index.php?option=com_askdesk&view=person&id=' . (int) $person->id;
				?>
				<tr>
					<td>
						<?php if ($profile['face']) : ?>
							<img src="<?php echo $e($profile['face']['src']); ?>" alt="" width="44" height="44" style="border-radius: 50%; object-fit: cover; background: #ebf5ff">
						<?php else : ?>
							<span class="d-inline-grid rounded-circle text-primary fw-medium" style="width: 44px; height: 44px; place-items: center; background: #ebf5ff"><?php echo $e(mb_substr($profile['name'], 0, 1)); ?></span>
						<?php endif; ?>
					</td>
					<td><a href="<?php echo $e($link); ?>"><?php echo $e($profile['name']); ?></a></td>
					<td><?php echo $e($person->name); ?><br><small class="text-muted"><?php echo $e($person->email); ?></small></td>
					<?php $stat = $this->stats[(int) $person->id] ?? null; ?>
					<td class="text-end fs-5"><?php echo (int) ($stat['chats'] ?? 0); ?></td>
					<td class="text-end"><?php echo (int) ($stat['messages'] ?? 0); ?></td>
					<td class="text-end"><?php
						$waited = (int) ($stat['waited'] ?? 0);
						echo !$stat || !($stat['chats'] ?? 0) ? '–' : ($waited < 60 ? Text::sprintf('COM_ASKDESK_STAFF_SECONDS', $waited) : Text::sprintf('COM_ASKDESK_STAFF_MINUTES', round($waited / 60, 1)));
					?></td>
					<td>
						<?php if (!$stat || !$stat['ratings']) : ?>
							<span class="text-muted">–</span>
						<?php else : ?>
							<?php if ($stat['stars'] !== null) : ?>
								<span class="text-warning" aria-hidden="true"><?php echo str_repeat('★', (int) round($stat['stars'])) . str_repeat('☆', 5 - (int) round($stat['stars'])); ?></span>
								<strong><?php echo number_format($stat['stars'], 1); ?></strong>
							<?php endif; ?>
							<?php if ($stat['thumbs'] !== null) : ?>
								<span class="badge bg-success ms-1"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_THUMBS_UP', $stat['thumbs']); ?></span>
							<?php endif; ?>
							<?php if ($stat['scale'] !== null) : ?>
								<span class="badge bg-info ms-1"><?php echo number_format($stat['scale'], 1); ?> / 10</span>
							<?php endif; ?>
							<br><small class="text-muted"><?php echo Text::sprintf('COM_ASKDESK_DASHBOARD_RATINGS_COUNT', (int) $stat['ratings']); ?></small>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($present) : ?>
							<span class="badge <?php echo $person->status === 'online' ? 'bg-success' : 'bg-warning'; ?>"><?php echo Text::_('COM_ASKDESK_CONSOLE_' . strtoupper($person->status)); ?></span>
						<?php else : ?>
							<span class="badge bg-secondary"><?php echo Text::_('COM_ASKDESK_CONSOLE_AWAY'); ?></span>
						<?php endif; ?>
					</td>
					<td class="text-nowrap">
						<a class="btn btn-sm btn-outline-primary" href="<?php echo $e($link); ?>"><?php echo Text::_('COM_ASKDESK_STAFF_EDIT'); ?></a>
						<form action="index.php?option=com_askdesk" method="post" class="d-inline m-0" onsubmit="return confirm(this.dataset.ask)" data-ask="<?php echo $e(Text::sprintf('COM_ASKDESK_STAFF_REMOVE_ASK', $person->name)); ?>">
							<input type="hidden" name="task" value="person.remove">
							<input type="hidden" name="id" value="<?php echo (int) $person->id; ?>">
							<?php echo HTMLHelper::_('form.token'); ?>
							<button class="btn btn-sm btn-outline-danger" type="submit"><?php echo Text::_('COM_ASKDESK_STAFF_REMOVE'); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
