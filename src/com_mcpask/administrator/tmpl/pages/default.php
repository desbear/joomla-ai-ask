<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\UserFactoryInterface;

/** @var \Gss\Component\Mcpask\Administrator\View\Pages\HtmlView $this */

$e      = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$action = Route::_('index.php?option=com_mcpask');
$token  = HTMLHelper::_('form.token');
$filter = $this->filter;
$option = static fn ($value, $label, $current) => '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' . ((string) $value === (string) $current ? ' selected' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
// What a page of the list keeps when an action sends it back here
$keep   = static function () use ($filter, $e): string {
    $html = '';

    foreach (['search' => $filter['search'], 'kind' => $filter['kind'], 'status' => $filter['status'], 'thin' => $filter['thin'] ? '1' : ''] as $name => $value) {
        if ($value !== '') {
            $html .= '<input type="hidden" name="' . $name . '" value="' . $e($value) . '">';
        }
    }

    return $html . '<input type="hidden" name="limitstart" value="' . (int) Factory::getApplication()->getInput()->getInt('limitstart', 0) . '">';
};
$sourceLabel = function (object $row) use ($e): string {
    return match ($row->kind) {
        'own'    => $e($this->labels[(string) $row->source] ?? $row->source),
        'manual' => Text::sprintf('COM_MCPASK_PAGES_SOURCE_MANUAL', $e($row->site)),
        default  => $e($row->site !== '' ? $row->site : $row->source),
    };
};
$tabs = [
    'pages'   => Text::_('COM_MCPASK_PAGES_TAB_PAGES') . ' <span class="badge bg-secondary">' . array_sum($this->counts) . '</span>',
    'manual'  => Text::_('COM_MCPASK_PAGES_TAB_MANUAL') . ' <span class="badge bg-secondary">' . (int) $this->counts['manual'] . '</span>',
    'ignored' => Text::_('COM_MCPASK_PAGES_TAB_IGNORED'),
];

// Documents: shown when a source of files (such as Dropfiles) is installed
if ($this->hasFiles) {
    $tabs = \array_slice($tabs, 0, 1, true) + ['files' => Text::_('COM_MCPASK_FILES_TAB')] + \array_slice($tabs, 1, null, true);
}

$fileSize = static fn (int $bytes): string => $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';

Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(<<<'JS'
document.addEventListener('toggle', async (event) => {
  const details = event.target;

  if (!(details instanceof HTMLDetailsElement) || !details.open || !details.dataset.passages || details.dataset.loaded) {
    return;
  }

  details.dataset.loaded = '1';

  const list = details.querySelector('.mcpask-passages');

  try {
    const response = await fetch(details.dataset.passages, { credentials: 'same-origin' });
    const data = await response.json();

    list.replaceChildren(...(data.passages || []).map((passage, index) => {
      const item = document.createElement('li');
      const head = document.createElement('div');
      const text = document.createElement('div');

      head.className = 'small text-muted';
      head.textContent = '#' + (index + 1) + (passage.heading ? ' · ' + passage.heading : '');
      text.className = 'mcpask-passage';
      text.textContent = passage.text;
      item.append(head, text);

      return item;
    }));
  } catch (error) {
    list.textContent = String(error);
  }
}, true);

document.addEventListener('submit', (event) => {
  const form = event.target;

  if (form.dataset.ask) {
    const note = window.prompt(form.dataset.ask, '');

    if (note === null) {
      event.preventDefault();

      return;
    }

    form.elements.note.value = note;
  }

  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
});
JS);
Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineStyle('.mcpask-passages{max-height:28rem;overflow:auto;padding-left:1.2rem}.mcpask-passages li{margin-bottom:.6rem}.mcpask-passage{white-space:pre-wrap;font-size:.875rem}.mcpask-url{max-width:40rem}');
?>
<p class="text-muted"><?php echo Text::_('COM_MCPASK_PAGES_DESC'); ?></p>

<ul class="nav nav-tabs mb-3">
	<?php foreach ($tabs as $name => $label) : ?>
		<li class="nav-item">
			<a class="nav-link<?php echo $this->tab === $name ? ' active' : ''; ?>"<?php echo $this->tab === $name ? ' aria-current="page"' : ''; ?> href="<?php echo Route::_('index.php?option=com_mcpask&view=pages' . ($name !== 'pages' ? '&tab=' . $name : '')); ?>"><?php echo $label; ?></a>
		</li>
	<?php endforeach; ?>
</ul>

<?php if ($this->tab === 'pages') : ?>
	<form action="<?php echo Route::_('index.php?option=com_mcpask&view=pages'); ?>" method="get" class="d-flex flex-wrap gap-2 mb-3">
		<input type="hidden" name="option" value="com_mcpask">
		<input type="hidden" name="view" value="pages">
		<input type="search" name="search" value="<?php echo $e($filter['search']); ?>" class="form-control w-auto" placeholder="<?php echo $e(Text::_('COM_MCPASK_PAGES_SEARCH')); ?>" aria-label="<?php echo $e(Text::_('COM_MCPASK_PAGES_SEARCH')); ?>">
		<select name="kind" class="form-select w-auto" onchange="this.form.submit()" aria-label="<?php echo $e(Text::_('COM_MCPASK_PAGES_SOURCE')); ?>">
			<?php echo $option('', Text::_('COM_MCPASK_PAGES_KIND_ALL'), $filter['kind']); ?>
			<?php foreach ($this->counts as $kind => $count) : ?>
				<?php if ($count) : ?>
					<?php echo $option($kind, Text::_('COM_MCPASK_PAGES_KIND_' . strtoupper($kind)) . ' (' . $count . ')', $filter['kind']); ?>
					<?php if ($kind === 'own' && \count($this->ownCounts) > 1) : ?>
						<?php foreach ($this->ownCounts as $name => $mine) : ?>
							<?php echo $option('own:' . $name, '— ' . ($this->labels[$name] ?? $name) . ' (' . $mine . ')', $filter['kind']); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
		<select name="status" class="form-select w-auto" onchange="this.form.submit()" aria-label="<?php echo $e(Text::_('COM_MCPASK_PAGES_STATUS')); ?>">
			<?php echo $option('', Text::_('COM_MCPASK_PAGES_STATUS_ALL'), $filter['status']); ?>
			<?php echo $option('ok', Text::_('COM_MCPASK_PAGES_STATUS_OK'), $filter['status']); ?>
			<?php echo $option('pending', Text::_('COM_MCPASK_PAGES_STATUS_PENDING'), $filter['status']); ?>
		</select>
		<div class="form-check align-self-center">
			<input class="form-check-input" type="checkbox" name="thin" value="1" id="mcpask-thin"<?php echo $filter['thin'] ? ' checked' : ''; ?> onchange="this.form.submit()">
			<label class="form-check-label" for="mcpask-thin"><?php echo Text::_('COM_MCPASK_PAGES_THIN'); ?></label>
		</div>
		<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
	</form>

	<?php if (!$this->rows) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_MCPASK_PAGES_NONE'); ?></div>
	<?php else : ?>
		<p class="small text-muted"><?php echo Text::plural('COM_MCPASK_PAGES_FOUND', $this->total); ?></p>
		<table class="table align-middle">
			<thead>
				<tr>
					<th><?php echo Text::_('COM_MCPASK_PAGES_PAGE'); ?></th>
					<th><?php echo Text::_('COM_MCPASK_PAGES_SOURCE'); ?></th>
					<th class="text-end"><?php echo Text::_('COM_MCPASK_INDEX_PASSAGES'); ?></th>
					<th><?php echo Text::_('COM_MCPASK_INDEX_CHECKED'); ?></th>
					<?php if ($this->canEdit) : ?><th><span class="visually-hidden"><?php echo Text::_('JACTIONS'); ?></span></th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->rows as $row) : ?>
					<tr>
						<td>
							<div class="fw-semibold">
								<?php echo $e($row->title); ?>
								<?php if ($row->language !== '*' && $row->language !== '') : ?><span class="badge bg-light text-dark"><?php echo $e($row->language); ?></span><?php endif; ?>
								<?php if ($row->status !== 'ok') : ?><span class="badge bg-warning text-dark" title="<?php echo $e($row->message); ?>"><?php echo Text::_('COM_MCPASK_PAGES_STATUS_PENDING'); ?></span><?php endif; ?>
							</div>
							<div class="small text-truncate mcpask-url"><a href="<?php echo $e($row->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($row->url); ?></a></div>
							<?php if ($row->status !== 'ok' && $row->message !== '') : ?><div class="small text-danger"><?php echo $e($row->message); ?></div><?php endif; ?>
							<?php if ((int) $row->chunks) : ?>
								<details data-passages="<?php echo $e(Route::_('index.php?option=com_mcpask&task=pages.passages&format=raw&id=' . (int) $row->id . '&' . Session::getFormToken() . '=1', false)); ?>">
									<summary class="small"><?php echo Text::_('COM_MCPASK_PAGES_SHOW_PASSAGES'); ?></summary>
									<ol class="mcpask-passages mt-2"><li class="text-muted"><?php echo Text::_('COM_MCPASK_PAGES_LOADING'); ?></li></ol>
								</details>
							<?php endif; ?>
						</td>
						<td><?php echo $sourceLabel($row); ?><?php if ($row->kind === 'own') : ?><div class="small text-muted">#<?php echo (int) $row->item_id; ?></div><?php endif; ?></td>
						<td class="text-end"><?php echo (int) $row->chunks; ?></td>
						<td class="text-nowrap small"><?php echo HTMLHelper::_('date', $row->checked, Text::_('DATE_FORMAT_LC5')); ?></td>
						<?php if ($this->canEdit) : ?>
							<td class="text-nowrap">
								<?php if ($row->kind !== 'peer') : ?>
									<form action="<?php echo $action; ?>" method="post" class="d-inline">
										<input type="hidden" name="task" value="pages.reread">
										<input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
										<?php echo $keep() . $token; ?>
										<button type="submit" class="btn btn-sm btn-outline-secondary"><span class="icon-refresh" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PAGES_REREAD'); ?></button>
									</form>
								<?php endif; ?>
								<form action="<?php echo $action; ?>" method="post" class="d-inline" data-ask="<?php echo $e(Text::sprintf('COM_MCPASK_PAGES_IGNORE_ASK', $row->title)); ?>">
									<input type="hidden" name="task" value="pages.ignore">
									<input type="hidden" name="cid[]" value="<?php echo (int) $row->id; ?>">
									<input type="hidden" name="note" value="">
									<?php echo $keep() . $token; ?>
									<button type="submit" class="btn btn-sm btn-outline-danger"><span class="icon-eye-slash" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PAGES_IGNORE'); ?></button>
								</form>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>

<?php elseif ($this->tab === 'files') : ?>
	<p class="text-muted"><?php echo Text::_('COM_MCPASK_FILES_DESC'); ?></p>
	<?php foreach ($this->files as $name => $group) : ?>
		<?php
		$read    = \count(array_filter($group['files'], static fn ($file) => $file['doc'] && (int) $file['doc']->chunks > 0));
		$skipped = \count(array_filter($group['files'], static fn ($file) => $file['reason'] !== ''));
		?>
		<h2 class="h5"><?php echo $e($group['label']); ?></h2>
		<p class="small">
			<span class="badge bg-success"><?php echo Text::sprintf('COM_MCPASK_FILES_COUNT_READ', $read); ?></span>
			<span class="badge bg-warning text-dark"><?php echo Text::sprintf('COM_MCPASK_FILES_COUNT_WAITING', \count($group['files']) - $read - $skipped); ?></span>
			<span class="badge bg-secondary"><?php echo Text::sprintf('COM_MCPASK_FILES_COUNT_SKIPPED', $skipped); ?></span>
		</p>
		<?php if ($group['error'] !== '') : ?>
			<div class="alert alert-danger"><?php echo $e($group['error']); ?></div>
		<?php elseif (!$group['files']) : ?>
			<div class="alert alert-info"><?php echo Text::_('COM_MCPASK_FILES_NONE'); ?></div>
		<?php else : ?>
			<table class="table align-middle mb-5">
				<thead>
					<tr>
						<th><?php echo Text::_('COM_MCPASK_FILES_FILE'); ?></th>
						<th><?php echo Text::_('COM_MCPASK_FILES_STATE'); ?></th>
						<th class="text-end"><?php echo Text::_('COM_MCPASK_INDEX_PASSAGES'); ?></th>
						<?php if ($this->canEdit) : ?><th><span class="visually-hidden"><?php echo Text::_('JACTIONS'); ?></span></th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($group['files'] as $file) : ?>
						<?php
						$doc   = $file['doc'];
						$state = match (true) {
							$file['reason'] !== ''                     => ['secondary', Text::_('COM_MCPASK_FILES_REASON_' . strtoupper($file['reason']))],
							$doc && $doc->status !== 'ok'              => ['warning', Text::_('COM_MCPASK_PAGES_STATUS_PENDING')],
							$doc && (int) $doc->chunks > 0             => ['success', Text::_('COM_MCPASK_FILES_STATE_READ')],
							default                                    => ['warning', Text::_('COM_MCPASK_FILES_STATE_WAITING')],
						};
						?>
						<tr>
							<td>
								<div class="fw-semibold"><?php echo $e($file['title']); ?></div>
								<div class="small text-muted">
									<span class="badge bg-light text-dark text-uppercase"><?php echo $e($file['type']); ?></span>
									<?php echo $fileSize($file['size']); ?>
									<?php if ($file['folder'] !== '') : ?>· <?php echo $e($file['folder']); ?><?php endif; ?>
									· #<?php echo (int) $file['id']; ?>
									<?php if ($file['given']) : ?><span class="badge bg-info text-dark"><?php echo Text::_('COM_MCPASK_FILES_HAS_TEXT'); ?></span><?php endif; ?>
								</div>
								<?php if ($doc && (int) $doc->chunks) : ?>
									<details data-passages="<?php echo $e(Route::_('index.php?option=com_mcpask&task=pages.passages&format=raw&id=' . (int) $doc->id . '&' . Session::getFormToken() . '=1', false)); ?>">
										<summary class="small"><?php echo Text::_('COM_MCPASK_PAGES_SHOW_PASSAGES'); ?></summary>
										<ol class="mcpask-passages mt-2"><li class="text-muted"><?php echo Text::_('COM_MCPASK_PAGES_LOADING'); ?></li></ol>
									</details>
								<?php endif; ?>
								<?php if ($this->canEdit && $file['reason'] !== 'private' && $file['reason'] !== 'remote') : ?>
									<?php $given = $file['given'] ? $this->suppliedText($name, (int) $file['id']) : null; ?>
									<details<?php echo $file['given'] ? ' open' : ''; ?>>
										<summary class="small"><?php echo Text::_('COM_MCPASK_FILES_TEXT'); ?></summary>
										<form action="<?php echo $action; ?>" method="post" class="mt-2">
											<textarea class="form-control font-monospace mb-2" name="text" rows="6" placeholder="<?php echo $e(Text::_('COM_MCPASK_FILES_TEXT_PLACEHOLDER')); ?>"><?php echo $e($given->text ?? ''); ?></textarea>
											<div class="d-flex flex-wrap gap-3 align-items-center">
												<?php foreach (['add', 'replace'] as $mode) : ?>
													<div class="form-check">
														<input class="form-check-input" type="radio" name="mode" value="<?php echo $mode; ?>" id="mcpask-mode-<?php echo $e($name . '-' . $file['id'] . '-' . $mode); ?>"<?php echo ($given->mode ?? 'add') === $mode ? ' checked' : ''; ?>>
														<label class="form-check-label small" for="mcpask-mode-<?php echo $e($name . '-' . $file['id'] . '-' . $mode); ?>"><?php echo Text::_('COM_MCPASK_FILES_MODE_' . strtoupper($mode)); ?></label>
													</div>
												<?php endforeach; ?>
												<input type="hidden" name="task" value="pages.text">
												<input type="hidden" name="source" value="<?php echo $e($name); ?>">
												<input type="hidden" name="item" value="<?php echo (int) $file['id']; ?>">
												<?php echo $token; ?>
												<button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_('COM_MCPASK_FILES_TEXT_SAVE'); ?></button>
											</div>
											<div class="form-text"><?php echo Text::_('COM_MCPASK_FILES_TEXT_DESC'); ?></div>
										</form>
									</details>
								<?php endif; ?>
							</td>
							<td>
								<span class="badge bg-<?php echo $state[0]; ?><?php echo $state[0] === 'warning' ? ' text-dark' : ''; ?>"><?php echo $e($state[1]); ?></span>
								<?php if ($doc) : ?><div class="small text-muted text-nowrap"><?php echo HTMLHelper::_('date', $doc->checked, Text::_('DATE_FORMAT_LC5')); ?></div><?php endif; ?>
								<?php if ($doc && $doc->status !== 'ok' && $doc->message !== '') : ?><div class="small text-danger"><?php echo $e($doc->message); ?></div><?php endif; ?>
							</td>
							<td class="text-end"><?php echo $doc ? (int) $doc->chunks : '—'; ?></td>
							<?php if ($this->canEdit) : ?>
								<td class="text-nowrap">
									<?php if ($file['reason'] === '') : ?>
										<form action="<?php echo $action; ?>" method="post" class="d-inline">
											<input type="hidden" name="task" value="pages.readfile">
											<input type="hidden" name="source" value="<?php echo $e($name); ?>">
											<input type="hidden" name="item" value="<?php echo (int) $file['id']; ?>">
											<?php echo $token; ?>
											<button type="submit" class="btn btn-sm btn-outline-secondary"><span class="icon-refresh" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_FILES_READ_NOW'); ?></button>
										</form>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endforeach; ?>

<?php elseif ($this->tab === 'manual') : ?>
	<?php if ($this->canEdit) : ?>
		<form action="<?php echo $action; ?>" method="post" class="card mb-4">
			<div class="card-body">
				<h2 class="h5"><?php echo Text::_('COM_MCPASK_PAGES_ADD'); ?></h2>
				<p class="text-muted small"><?php echo Text::_('COM_MCPASK_PAGES_ADD_DESC'); ?></p>
				<div class="mb-3">
					<label class="form-label" for="mcpask-urls"><?php echo Text::_('COM_MCPASK_PAGES_URLS'); ?></label>
					<textarea class="form-control font-monospace" id="mcpask-urls" name="urls" rows="6" placeholder="https://www.example.com/pricing&#10;https://www.example.com/terms" required></textarea>
				</div>
				<div class="mb-3">
					<label class="form-label" for="mcpask-selector"><?php echo Text::_('COM_MCPASK_CONFIG_FLEXI_PAGES_SELECTOR'); ?></label>
					<input class="form-control w-auto" id="mcpask-selector" name="selector" maxlength="200" placeholder="main, #prices">
					<div class="form-text"><?php echo Text::_('COM_MCPASK_PAGES_SELECTOR_DESC'); ?></div>
				</div>
				<input type="hidden" name="task" value="pages.add">
				<?php echo $token; ?>
				<button type="submit" class="btn btn-primary"><span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PAGES_ADD_BUTTON'); ?></button>
			</div>
		</form>
	<?php endif; ?>

	<?php if (!$this->manual) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_MCPASK_PAGES_MANUAL_NONE'); ?></div>
	<?php else : ?>
		<form action="<?php echo $action; ?>" method="post" data-confirm="<?php echo $e(Text::_('COM_MCPASK_PAGES_REMOVE_CONFIRM')); ?>">
			<table class="table align-middle">
				<thead>
					<tr>
						<?php if ($this->canEdit) : ?><th class="w-1"><span class="visually-hidden"><?php echo Text::_('JSELECT'); ?></span></th><?php endif; ?>
						<th><?php echo Text::_('COM_MCPASK_PAGES_PAGE'); ?></th>
						<th class="text-end"><?php echo Text::_('COM_MCPASK_INDEX_PASSAGES'); ?></th>
						<th><?php echo Text::_('COM_MCPASK_PAGES_ADDED_ON'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->manual as $row) : ?>
						<tr>
							<?php if ($this->canEdit) : ?><td><input class="form-check-input" type="checkbox" name="cid[]" value="<?php echo (int) $row->id; ?>" aria-label="<?php echo $e($row->url); ?>"></td><?php endif; ?>
							<td>
								<div class="fw-semibold"><?php echo $row->doc ? $e($row->doc->title) : '<span class="badge bg-secondary">' . Text::_('COM_MCPASK_PAGES_NOT_READ') . '</span>'; ?></div>
								<div class="small text-truncate mcpask-url"><a href="<?php echo $e($row->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($row->url); ?></a></div>
								<?php if ($row->selector !== '') : ?><div class="small text-muted"><code><?php echo $e($row->selector); ?></code></div><?php endif; ?>
							</td>
							<td class="text-end"><?php echo $row->doc ? (int) $row->doc->chunks : '—'; ?></td>
							<td class="text-nowrap small"><?php echo HTMLHelper::_('date', $row->created, Text::_('DATE_FORMAT_LC5')); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ($this->canEdit) : ?>
				<input type="hidden" name="task" value="pages.remove">
				<?php echo $token; ?>
				<button type="submit" class="btn btn-outline-danger"><span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PAGES_REMOVE'); ?></button>
			<?php endif; ?>
		</form>
		<p class="small text-muted mt-3"><?php echo Text::_('COM_MCPASK_PAGES_MANUAL_NOTE'); ?></p>
	<?php endif; ?>

<?php else : ?>
	<div class="row g-4">
		<div class="col-lg-7">
			<h2 class="h5"><?php echo Text::_('COM_MCPASK_PAGES_IGNORED_ITEMS'); ?></h2>
			<?php if (!$this->ignored) : ?>
				<div class="alert alert-info"><?php echo Text::_('COM_MCPASK_PAGES_IGNORED_NONE'); ?></div>
			<?php else : ?>
				<table class="table align-middle">
					<tbody>
						<?php foreach ($this->ignored as $row) : ?>
							<tr>
								<td>
									<div class="fw-semibold"><?php echo $e($row->title); ?></div>
									<?php if ($row->url !== '') : ?><div class="small text-truncate mcpask-url"><a href="<?php echo $e($row->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo $e($row->url); ?></a></div><?php endif; ?>
									<div class="small text-muted">
										<?php echo HTMLHelper::_('date', $row->created, Text::_('DATE_FORMAT_LC5')); ?>
										<?php if ((int) $row->created_by) : ?>· <?php echo $e(Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $row->created_by)->name); ?><?php endif; ?>
										<?php if ($row->note !== '') : ?>· <?php echo $e($row->note); ?><?php endif; ?>
									</div>
								</td>
								<?php if ($this->canEdit) : ?>
									<td class="text-end">
										<form action="<?php echo $action; ?>" method="post">
											<input type="hidden" name="task" value="pages.restore">
											<input type="hidden" name="cid[]" value="<?php echo (int) $row->id; ?>">
											<?php echo $token; ?>
											<button type="submit" class="btn btn-sm btn-outline-secondary"><span class="icon-undo" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PAGES_RESTORE'); ?></button>
										</form>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<div class="col-lg-5">
			<form action="<?php echo $action; ?>" method="post" class="card">
				<div class="card-body">
					<h2 class="h5"><label for="mcpask-patterns"><?php echo Text::_('COM_MCPASK_PAGES_PATTERNS'); ?></label></h2>
					<p class="text-muted small"><?php echo Text::_('COM_MCPASK_PAGES_PATTERNS_DESC'); ?></p>
					<textarea class="form-control font-monospace mb-3" id="mcpask-patterns" name="patterns" rows="10" placeholder="# <?php echo $e(Text::_('COM_MCPASK_PAGES_PATTERNS_EXAMPLE')); ?>&#10;*/tag/*&#10;https://www.example.com/news/2019-*&#10;/*?print=1"<?php echo $this->canEdit ? '' : ' readonly'; ?>><?php echo $e($this->patterns); ?></textarea>
					<?php if ($this->canEdit) : ?>
						<input type="hidden" name="task" value="pages.patterns">
						<?php echo $token; ?>
						<button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>
