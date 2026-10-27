<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Mcpask\Administrator\Pet\Pets;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\HTML\Helpers\Number;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Gss\Component\Mcpask\Administrator\View\Pet\HtmlView $this */

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('form.validate');

$e      = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$pet    = $this->pet;
$id     = (int) ($pet->id ?? 0);
$params = $pet ? $pet->params : null;
$total  = array_sum($this->sizes);
$url    = static fn ($path) => $path !== '' ? rtrim(Uri::root(true), '/') . '/' . $path : '';
$plays  = [];

foreach (($this->shown['actions'] ?? []) as $name => $action) {
    foreach ([$action, ...($action['more'] ?? [])] as $play) {
        $plays[] = ['name' => Text::_('COM_MCPASK_PET_ACTION_' . strtoupper($name)), 'src' => $play['src'], 'seconds' => $play['seconds'] ?: 3];
    }
}

// The library, and the pictures of each moment (0.22.0)
[$items, $blocks] = $params && $this->kind !== 'cleaner' ? Pets::library($params) : [[], []];

// Trying it out: each moment in turn, for as long as it lasts (three seconds for those that go on)
$wa->addInlineScript(<<<'JS'
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-mcpask-try]');

      if (!button) {
        return;
      }

      const plays = JSON.parse(button.dataset.mcpaskTry);
      const picture = document.getElementById('mcpask-pet-try');
      const label = document.getElementById('mcpask-pet-try-label');
      let index = 0;

      button.disabled = true;

      const next = () => {
        if (index >= plays.length) {
          button.disabled = false;
          label.textContent = '';

          return;
        }

        const play = plays[index++];

        picture.src = play.src + (play.src.includes('?') ? '&' : '?') + 't=' + Date.now();
        label.textContent = play.name;
        window.setTimeout(next, play.seconds * 1000);
      };

      next();
    });
    JS);

// The library's pictures dragged into the moments (0.22.0): from the library a copy, from a moment
// moved; the list beside each moment does the same without dragging
$wa->addInlineStyle(<<<'CSS'
    .mcpask-library{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem}
    .mcpask-item{cursor:grab}
    .mcpask-item__pic{display:grid;place-items:center;height:110px;background:repeating-conic-gradient(#f3f4f7 0 25%,#fff 0 50%) 0 0/16px 16px;border-radius:.375rem .375rem 0 0}
    .mcpask-item__pic img{object-fit:contain}
    .mcpask-zone{display:flex;flex-wrap:wrap;gap:.375rem;min-height:52px;padding:.375rem;border:2px dashed #c9ced6;border-radius:.5rem;align-items:center}
    .mcpask-zone.is-over{border-color:var(--bs-primary,#2a69b8);background:rgba(42,105,184,.06)}
    .mcpask-zone__empty{font-size:.8125rem;color:#6c757d}
    .mcpask-zone:has(.mcpask-chip) .mcpask-zone__empty{display:none}
    .mcpask-chip{display:inline-flex;align-items:center;gap:.375rem;padding:.125rem .375rem .125rem .125rem;border:1px solid #d6dbe1;border-radius:2rem;background:#fff;font-size:.8125rem;cursor:grab}
    .mcpask-chip img{width:32px;height:32px;object-fit:contain;border-radius:50%;background:#f3f4f7}
    .mcpask-chip .btn-close{width:.5rem;height:.5rem;padding:.25rem}
    CSS);

$wa->addInlineScript(<<<'JS'
    document.addEventListener('DOMContentLoaded', () => {
      const blocks = document.getElementById('mcpask-pet-blocks');
      const library = document.getElementById('mcpask-pet-library');

      if (!blocks || !library) {
        return;
      }

      const template = document.getElementById('mcpask-pet-chip');
      let dragged = null;

      const sync = (zone) => {
        zone.closest('[data-block]').querySelector('input[type="hidden"]').value = [...zone.querySelectorAll('.mcpask-chip')].map((chip) => chip.dataset.key).join(',');
      };

      const make = (key) => {
        const card = library.querySelector(`.mcpask-item[data-key="${CSS.escape(key)}"]`);
        const chip = template.content.firstElementChild.cloneNode(true);

        chip.dataset.key = key;
        chip.querySelector('img').src = card ? card.querySelector('.mcpask-item__pic img').src : '';
        chip.querySelector('[data-mcpask-label]').textContent = (card && card.querySelector('[data-mcpask-name]').value) || key;

        return chip;
      };

      const put = (zone, key, chip, before) => {
        const there = zone.querySelector(`.mcpask-chip[data-key="${CSS.escape(key)}"]`);
        const from = chip ? chip.closest('[data-zone]') : null;

        // Once in a moment; from another moment it moves
        if (there && there !== chip) {
          if (!chip) {
            return;
          }

          there.remove();
        }

        chip = chip || make(key);
        zone.insertBefore(chip, before && before !== chip ? before : null);
        sync(zone);

        if (from && from !== zone) {
          sync(from);
        }
      };

      document.addEventListener('dragstart', (event) => {
        const chip = event.target.closest('.mcpask-chip');
        const card = event.target.closest('.mcpask-item');

        if (!chip && !card) {
          return;
        }

        dragged = { key: (chip || card).dataset.key, chip };
        event.dataTransfer.setData('text/plain', dragged.key);
        event.dataTransfer.effectAllowed = chip ? 'move' : 'copy';
      });

      document.addEventListener('dragend', () => {
        dragged = null;
        blocks.querySelectorAll('.is-over').forEach((zone) => zone.classList.remove('is-over'));
      });

      blocks.querySelectorAll('[data-zone]').forEach((zone) => {
        zone.addEventListener('dragover', (event) => {
          if (dragged) {
            event.preventDefault();
            zone.classList.add('is-over');
          }
        });
        zone.addEventListener('dragleave', (event) => {
          if (!zone.contains(event.relatedTarget)) {
            zone.classList.remove('is-over');
          }
        });
        zone.addEventListener('drop', (event) => {
          event.preventDefault();
          zone.classList.remove('is-over');

          if (dragged) {
            put(zone, dragged.key, dragged.chip, event.target.closest('.mcpask-chip'));
          }
        });
      });

      // Dragged out of a moment onto the library: taken out of it
      library.addEventListener('dragover', (event) => {
        if (dragged && dragged.chip) {
          event.preventDefault();
        }
      });
      library.addEventListener('drop', (event) => {
        if (dragged && dragged.chip) {
          event.preventDefault();

          const zone = dragged.chip.closest('[data-zone]');

          dragged.chip.remove();
          sync(zone);
        }
      });

      blocks.addEventListener('click', (event) => {
        const out = event.target.closest('[data-mcpask-out]');

        if (out) {
          const zone = out.closest('[data-zone]');

          out.closest('.mcpask-chip').remove();
          sync(zone);
        }
      });

      blocks.addEventListener('change', (event) => {
        const select = event.target.closest('[data-mcpask-add]');

        if (select && select.value) {
          put(select.closest('[data-block]').querySelector('[data-zone]'), select.value, null, null);
          select.value = '';
        }
      });

      // A name changed: changed wherever the picture is
      library.addEventListener('input', (event) => {
        const field = event.target.closest('[data-mcpask-name]');

        if (!field) {
          return;
        }

        const key = field.closest('.mcpask-item').dataset.key;

        blocks.querySelectorAll(`.mcpask-chip[data-key="${CSS.escape(key)}"] [data-mcpask-label], [data-mcpask-add] option[value="${CSS.escape(key)}"]`).forEach((label) => {
          label.textContent = field.value || key;
        });
      });

      // Walking tried out (0.22.0): standing, turning, walking left and back, turning to the front, with
      // the pictures and seconds on the page now, saved or not, to see whether the seconds are right
      const preview = document.getElementById('mcpask-walk-preview');

      if (preview) {
        const words = JSON.parse(preview.dataset.words);
        const stage = preview.querySelector('[data-stage]');
        const pet = preview.querySelector('[data-pet]');
        const label = preview.querySelector('[data-phase]');
        const steps = preview.querySelector('[data-steps]');
        const button = preview.querySelector('[data-play]');
        const blobs = {};
        let x = 0;

        // The first picture of a moment, with its seconds as on the page
        const pick = (block) => {
          const chip = blocks.querySelector(`[data-block="${block}"] .mcpask-chip`);
          const card = chip && library.querySelector(`.mcpask-item[data-key="${CSS.escape(chip.dataset.key)}"]`);

          if (!card) {
            return null;
          }

          return {
            src: card.querySelector('.mcpask-item__pic img').src,
            name: card.querySelector('[data-mcpask-name]').value || chip.dataset.key,
            seconds: parseFloat(card.querySelector('input[type="number"]').value) || words.defaults[block] || 0,
          };
        };

        // A fresh copy, so an animation plays from its first frame
        const fresh = async (src) => {
          try {
            blobs[src] = blobs[src] || await (await fetch(src)).blob();

            return URL.createObjectURL(blobs[src]);
          } catch {
            return src + (src.includes('?') ? '&' : '?') + 't=' + Date.now();
          }
        };

        const wait = (seconds) => new Promise((resolve) => { window.setTimeout(resolve, seconds * 1000); });
        let started = 0;

        const show = async (item, phase, seconds) => {
          const at = ((performance.now() - started) / 1000).toFixed(1);
          const line = `${at}s  ${words[phase]}：${item.name}${seconds ? `（${seconds} ${words.unit}）` : ''}`;

          pet.src = await fresh(item.src);
          label.textContent = line;
          steps.textContent += line + '\n';
        };

        const leg = async (to, left, pictures) => {
          const { idle, turn, walk, stop } = pictures;

          pet.style.scale = left ? '-1 1' : '';

          if (turn) {
            await show(turn, 'turn', turn.seconds);
            await wait(turn.seconds);
          }

          const seconds = Math.abs(to - x) / 70;

          await show(walk, 'walk', Math.round(seconds * 10) / 10);
          await pet.animate([{ translate: `${x}px 0` }, { translate: `${to}px 0` }], { duration: seconds * 1000, easing: 'linear', fill: 'forwards' }).finished;
          x = to;

          if (stop) {
            await show(stop, 'stop', stop.seconds);
            await wait(stop.seconds);
          }

          pet.style.scale = '';
          await show(idle, 'idle', 0);
          await wait(1.5);
        };

        button.addEventListener('click', async () => {
          const pictures = { idle: pick('idle'), turn: pick('turn'), walk: pick('walk'), stop: pick('stop') };

          if (!pictures.idle || !pictures.walk) {
            label.textContent = words.needs;

            return;
          }

          button.disabled = true;
          steps.textContent = '';
          started = performance.now();
          x = 0;

          await show(pictures.idle, 'idle', 0);
          await wait(1);
          await leg(-Math.max(120, Math.min(360, stage.clientWidth - pet.clientWidth - 48)), true, pictures);
          await leg(0, false, pictures);
          pet.getAnimations().forEach((animation) => animation.cancel());
          label.textContent = '';
          button.disabled = false;
        });
      }

      // Pictures chosen to add: saved at once, so they can be dragged
      document.addEventListener('change', (event) => {
        if (event.target.matches('[data-mcpask-upload]') && event.target.files.length) {
          Joomla.submitbutton('pets.apply');
        }
      });
    });
    JS);
?>
<form action="<?php echo Route::_('index.php?option=com_mcpask'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">
	<p class="text-muted"><?php echo Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_CLEANER_DESC' : 'COM_MCPASK_PET_DESC'); ?></p>

	<div class="row">
		<div class="col-lg-7">
			<?php echo $this->form->renderFieldset('main'); ?>
			<?php if ($this->kind === 'cleaner') : ?>
				<?php echo $this->form->renderField('size', 'params', null, ['description' => Text::_('COM_MCPASK_CLEANER_SIZE_DESC')]); ?>
			<?php else : ?>
				<?php echo $this->form->renderFieldset('look'); ?>
			<?php endif; ?>
		</div>
		<div class="col-lg-5">
			<div class="card text-center">
				<div class="card-body">
					<div style="height:180px;display:grid;place-items:center;background:repeating-conic-gradient(#f3f4f7 0 25%,#fff 0 50%) 0 0/20px 20px;border-radius:12px">
						<img id="mcpask-pet-try" src="<?php echo $e($this->kind === 'cleaner' ? $url(Pets::clean((string) ($params ? $params->get('clean_image', '') : ''))) : ($this->shown['actions']['idle']['src'] ?? '')); ?>" alt="" width="160" height="160" style="object-fit:contain">
					</div>
					<div id="mcpask-pet-try-label" class="small text-muted mt-2" aria-live="polite"></div>
					<?php if ($plays) : ?>
						<button type="button" class="btn btn-secondary btn-sm mt-2" data-mcpask-try="<?php echo $e(json_encode($plays, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"><?php echo Text::_('COM_MCPASK_PET_TRY'); ?></button>
					<?php endif; ?>
					<div class="small mt-2 <?php echo $total > 4 * 1048576 ? 'text-danger' : 'text-muted'; ?>"><?php echo Text::sprintf('COM_MCPASK_PET_TOTAL', Number::bytes($total)); ?></div>
				</div>
			</div>
		</div>
	</div>

	<h3 class="h5 mt-4"><?php echo Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_PET_PICTURES' : 'COM_MCPASK_PET_STILLS'); ?></h3>
	<p class="small text-muted"><?php echo Text::_($this->kind === 'cleaner' ? 'COM_MCPASK_PET_PICTURES_DESC' : 'COM_MCPASK_PET_STILLS_DESC'); ?></p>

	<?php
	// One picture's cell: what it is now (with its size), a new one, and taking it away
	$cell = function (string $field) use ($params, $e, $url): string {
		$param = Pets::pictures()[$field];
		$path  = $params ? Pets::clean((string) $params->get($param, '')) : '';
		$bytes = $this->sizes[$param] ?? 0;
		$still = in_array($field, ['static', 'thumb'], true);
		$html  = '<div class="d-flex align-items-center gap-2">';
		$html .= $path !== ''
			? '<div class="text-center flex-none"><img src="' . $e($url($path)) . '" alt="" width="64" height="64" style="object-fit:contain;border-radius:12px;background:#f3f4f7"><div class="small ' . ($bytes > Pets::ADVISED_BYTES ? 'text-danger' : 'text-muted') . '">' . Number::bytes($bytes) . '</div></div>'
			: '<div class="text-muted small text-center" style="width:64px">' . Text::_(\in_array($field, ['idle', 'static', 'clean'], true) ? 'COM_MCPASK_PET_NONE_YET' : ($field === 'point' ? 'COM_MCPASK_CLEANER_USES_CLEAN' : (str_ends_with($field, 'thumb') ? 'COM_MCPASK_PET_USES_FULL' : 'COM_MCPASK_PET_USES_IDLE'))) . '</div>';
		$html .= '<div class="flex-grow-1"><input type="file" class="form-control form-control-sm" name="pictures[' . $field . ']" accept="' . ($still ? '.png,.webp,.gif,.avif,.jpg,.jpeg' : '.png,.apng,.avif,.webp,.gif') . '">';

		if ($path !== '' && !\in_array($field, ['idle', 'clean'], true)) {
			$html .= '<label class="small mt-1"><input type="checkbox" name="remove[' . $field . ']" value="1"> ' . Text::_('COM_MCPASK_PET_REMOVE') . '</label>';
		}

		return $html . '</div></div>';
	};
	?>
	<table class="table align-middle">
		<thead>
			<tr>
				<th><?php echo Text::_('COM_MCPASK_PET_MOMENT'); ?></th>
				<th><?php echo Text::_('COM_MCPASK_PET_FULL'); ?></th>
				<th><?php echo Text::_('COM_MCPASK_PET_THUMB'); ?></th>
				<th><?php echo Text::_('COM_MCPASK_PET_SECONDS'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->kind === 'cleaner' ? array_keys(Pets::CLEANER_ACTIONS) : ['static'] as $name) : ?>
				<?php $once = (Pets::ACTIONS[$name] ?? 0) > 0; ?>
				<tr>
					<td style="min-width:12rem">
						<strong><?php echo Text::_('COM_MCPASK_PET_ACTION_' . strtoupper($name)); ?></strong>
						<?php if ($name === 'idle' || $name === 'clean') : ?><span class="badge bg-warning"><?php echo Text::_('JREQUIRED'); ?></span><?php endif; ?>
						<div class="small text-muted"><?php echo Text::_('COM_MCPASK_PET_ACTION_' . strtoupper($name) . '_DESC'); ?></div>
					</td>
					<td><?php echo $cell($name); ?></td>
					<td><?php echo $this->kind === 'cleaner' ? '<span class="small text-muted">—</span>' : $cell($name === 'static' ? 'thumb' : $name . '_thumb'); ?></td>
					<td style="width:110px">
						<?php if ($once) : ?>
							<input type="number" class="form-control form-control-sm" name="seconds[<?php echo $name; ?>]" min="0.3" max="10" step="0.1" value="<?php echo $e($params ? $params->get($name . '_seconds', Pets::ACTIONS[$name]) : Pets::ACTIONS[$name]); ?>">
						<?php else : ?>
							<span class="small text-muted"><?php echo Text::_($name === 'static' ? 'COM_MCPASK_PET_STILL' : 'COM_MCPASK_PET_LOOPS'); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ($this->kind !== 'cleaner') : ?>
		<?php
		$picture = static fn (array $item): string => $url(Pets::clean($item['thumb']) ?: Pets::clean($item['image']));
		$chip    = static fn (string $key, array $item): string => '<span class="mcpask-chip" draggable="true" data-key="' . $e($key) . '">'
			. '<img src="' . $e($picture($item)) . '" alt="" width="32" height="32"><span data-mcpask-label>' . $e($item['name'] ?: $key) . '</span>'
			. '<button type="button" class="btn-close" data-mcpask-out aria-label="' . $e(Text::_('COM_MCPASK_PET_BLOCK_OUT')) . '"></button></span>';
		?>
		<h3 class="h5 mt-4"><?php echo Text::_('COM_MCPASK_PET_LIBRARY'); ?></h3>
		<p class="small text-muted"><?php echo Text::_('COM_MCPASK_PET_LIBRARY_DESC'); ?></p>
		<label class="btn btn-primary btn-sm mb-3">
			<span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PET_LIBRARY_ADD'); ?>
			<input type="file" name="new[]" multiple accept=".png,.apng,.avif,.webp,.gif" data-mcpask-upload hidden>
		</label>
		<div id="mcpask-pet-library" class="mcpask-library">
			<?php foreach ($items as $key => $item) : ?>
				<?php $bytes = Pets::clean($item['image']) !== '' ? (int) filesize(JPATH_ROOT . '/' . Pets::clean($item['image'])) : 0; ?>
				<div class="mcpask-item card" draggable="true" data-key="<?php echo $e($key); ?>">
					<div class="mcpask-item__pic"><img src="<?php echo $e($url(Pets::clean($item['image']))); ?>" alt="" width="96" height="96" draggable="false"></div>
					<div class="card-body p-2">
						<input type="text" class="form-control form-control-sm mb-1" name="items[<?php echo $e($key); ?>][name]" value="<?php echo $e($item['name']); ?>" maxlength="60" placeholder="<?php echo $e(Text::_('COM_MCPASK_PET_ITEM_NAME')); ?>" aria-label="<?php echo $e(Text::_('COM_MCPASK_PET_ITEM_NAME')); ?>" data-mcpask-name>
						<div class="input-group input-group-sm mb-1">
							<input type="number" class="form-control" name="items[<?php echo $e($key); ?>][seconds]" value="<?php echo $item['seconds'] > 0 ? $e($item['seconds']) : ''; ?>" min="0.3" max="10" step="0.1" placeholder="<?php echo $e(Text::_('COM_MCPASK_PET_ITEM_SECONDS_AUTO')); ?>" aria-label="<?php echo $e(Text::_('COM_MCPASK_PET_SECONDS')); ?>">
							<span class="input-group-text"><?php echo Text::_('COM_MCPASK_PET_ITEM_SECONDS_UNIT'); ?></span>
						</div>
						<div class="small <?php echo $bytes > Pets::ADVISED_BYTES ? 'text-danger' : 'text-muted'; ?>"><?php echo Number::bytes($bytes); ?></div>
						<details class="small mt-1">
							<summary><?php echo Text::_('COM_MCPASK_PET_ITEM_MORE'); ?></summary>
							<label class="d-block mt-1"><?php echo Text::_('COM_MCPASK_PET_ITEM_REPLACE'); ?>
								<input type="file" class="form-control form-control-sm" name="item_image[<?php echo $e($key); ?>]" accept=".png,.apng,.avif,.webp,.gif"></label>
							<label class="d-block mt-1"><?php echo Text::_('COM_MCPASK_PET_THUMB'); ?>
								<?php if (Pets::clean($item['thumb']) !== '') : ?><img src="<?php echo $e($url(Pets::clean($item['thumb']))); ?>" alt="" width="32" height="32" style="object-fit:contain"><?php endif; ?>
								<input type="file" class="form-control form-control-sm" name="item_thumb[<?php echo $e($key); ?>]" accept=".png,.webp,.gif,.avif,.jpg,.jpeg"></label>
							<?php if (Pets::clean($item['thumb']) !== '') : ?>
								<label class="d-block"><input type="checkbox" name="items[<?php echo $e($key); ?>][thumb_remove]" value="1"> <?php echo Text::_('COM_MCPASK_PET_ITEM_THUMB_REMOVE'); ?></label>
							<?php endif; ?>
							<label class="d-block text-danger mt-1"><input type="checkbox" name="items[<?php echo $e($key); ?>][remove]" value="1"> <?php echo Text::_('COM_MCPASK_PET_ITEM_REMOVE'); ?></label>
						</details>
					</div>
				</div>
			<?php endforeach; ?>
			<?php if (!$items) : ?>
				<p class="text-muted small mb-0"><?php echo Text::_('COM_MCPASK_PET_LIBRARY_EMPTY'); ?></p>
			<?php endif; ?>
		</div>

		<h3 class="h5 mt-4"><?php echo Text::_('COM_MCPASK_PET_BLOCKS'); ?></h3>
		<p class="small text-muted"><?php echo Text::_('COM_MCPASK_PET_BLOCKS_DESC'); ?></p>
		<input type="hidden" name="blocks_posted" value="1">
		<div id="mcpask-pet-blocks">
			<?php foreach (Pets::GROUPS as $group => $names) : ?>
				<h4 class="h6 mt-3"><?php echo Text::_('COM_MCPASK_PET_GROUP_' . strtoupper($group)); ?></h4>
				<div class="row g-3">
					<?php foreach ($names as $name) : ?>
						<?php $label = 'COM_MCPASK_PET_ACTION_' . strtoupper($name); ?>
						<div class="col-md-6 col-xxl-4">
							<div class="card h-100 mcpask-block" data-block="<?php echo $name; ?>">
								<div class="card-body p-2">
									<div class="d-flex justify-content-between align-items-start gap-2">
										<strong><?php echo Text::_($label); ?></strong>
										<span class="d-flex gap-1">
											<?php if ($name === 'idle') : ?><span class="badge bg-warning"><?php echo Text::_('JREQUIRED'); ?></span><?php endif; ?>
											<span class="badge bg-light text-dark"><?php echo Text::_(Pets::ACTIONS[$name] > 0 ? 'COM_MCPASK_PET_ONCE' : 'COM_MCPASK_PET_LOOPS'); ?></span>
										</span>
									</div>
									<div class="small text-muted mb-2"><?php echo Text::_($label . '_DESC'); ?></div>
									<div class="mcpask-zone" data-zone>
										<span class="mcpask-zone__empty"><?php echo Text::_($name === 'idle' ? 'COM_MCPASK_PET_NONE_YET' : (Factory::getApplication()->getLanguage()->hasKey($label . '_EMPTY') ? $label . '_EMPTY' : 'COM_MCPASK_PET_USES_IDLE')); ?></span>
										<?php foreach ($blocks[$name] ?? [] as $key) : ?>
											<?php echo $chip($key, $items[$key]); ?>
										<?php endforeach; ?>
									</div>
									<input type="hidden" name="blocks[<?php echo $name; ?>]" value="<?php echo $e(implode(',', $blocks[$name] ?? [])); ?>">
									<div class="d-flex gap-2 mt-2">
										<select class="form-select form-select-sm" data-mcpask-add aria-label="<?php echo $e(Text::_('COM_MCPASK_PET_BLOCK_ADD') . ' ' . Text::_($label)); ?>">
											<option value=""><?php echo Text::_('COM_MCPASK_PET_BLOCK_ADD'); ?></option>
											<?php foreach ($items as $key => $item) : ?>
												<option value="<?php echo $e($key); ?>"><?php echo $e($item['name'] ?: $key); ?></option>
											<?php endforeach; ?>
										</select>
										<label class="btn btn-outline-secondary btn-sm text-nowrap mb-0">
											<?php echo Text::_('COM_MCPASK_PET_BLOCK_UPLOAD'); ?>
											<input type="file" name="new_block[<?php echo $name; ?>][]" multiple accept=".png,.apng,.avif,.webp,.gif" data-mcpask-upload hidden>
										</label>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		$words = ['unit' => Text::_('COM_MCPASK_PET_ITEM_SECONDS_UNIT'), 'needs' => Text::_('COM_MCPASK_PET_WALK_PREVIEW_NEEDS'), 'defaults' => Pets::ACTIONS];

		foreach (['idle', 'turn', 'walk', 'stop'] as $name) {
			$words[$name] = Text::_('COM_MCPASK_PET_ACTION_' . strtoupper($name));
		}
		?>
		<div id="mcpask-walk-preview" class="card mt-4" data-words="<?php echo $e(json_encode($words, JSON_UNESCAPED_UNICODE)); ?>">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
					<div>
						<h3 class="h6 mb-1"><?php echo Text::_('COM_MCPASK_PET_WALK_PREVIEW'); ?></h3>
						<div class="small text-muted"><?php echo Text::_('COM_MCPASK_PET_WALK_PREVIEW_DESC'); ?></div>
					</div>
					<button type="button" class="btn btn-secondary btn-sm" data-play><span class="icon-play" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_PET_WALK_PREVIEW_PLAY'); ?></button>
				</div>
				<div data-stage style="position:relative;height:170px;margin-top:.75rem;overflow:hidden;border-radius:12px;background:repeating-conic-gradient(#f3f4f7 0 25%,#fff 0 50%) 0 0/20px 20px">
					<img data-pet src="<?php echo $e($this->shown['actions']['idle']['src'] ?? ''); ?>" alt="" width="140" height="140" style="position:absolute;right:24px;bottom:10px;object-fit:contain">
				</div>
				<div class="small fw-bold mt-2" data-phase aria-live="polite"></div>
				<pre class="small text-muted mb-0 mt-1" data-steps style="white-space:pre-wrap"></pre>
			</div>
		</div>
		<template id="mcpask-pet-chip"><?php echo $chip('KEY', ['name' => '', 'image' => '', 'thumb' => '']); ?></template>
	<?php endif; ?>


	<input type="hidden" name="task" value="">
	<input type="hidden" name="id" value="<?php echo $id; ?>">
	<input type="hidden" name="kind" value="<?php echo $e($this->kind); ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
