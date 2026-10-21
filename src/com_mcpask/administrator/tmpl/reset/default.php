<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use Gss\Component\Mcpask\Administrator\Ask\Reset;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/** @var \Gss\Component\Mcpask\Administrator\View\Reset\HtmlView $this */

$e = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Reports have private addresses that visitors were sent: not ticked unless chosen
$ticked = ['questions', 'handoffs', 'agents'];
?>
<form action="<?php echo Route::_('index.php?option=com_mcpask&task=reset.clear'); ?>" method="post" class="mcp-reset"
    onsubmit="return confirm(<?php echo $e(json_encode(Text::_('COM_MCPASK_RESET_SURE'), JSON_UNESCAPED_UNICODE)); ?>);">
    <div class="card mb-3">
        <div class="card-body">
            <p><?php echo Text::_('COM_MCPASK_RESET_INTRO'); ?></p>
            <ul class="list-unstyled mb-0">
                <?php foreach (array_keys(Reset::KINDS) as $kind) : ?>
                    <?php $name = 'COM_MCPASK_RESET_' . strtoupper($kind); ?>
                    <li class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="kinds[]" value="<?php echo $e($kind); ?>" id="reset-<?php echo $e($kind); ?>"<?php echo \in_array($kind, $ticked, true) && $this->counts[$kind] ? ' checked' : ''; ?><?php echo $this->counts[$kind] ? '' : ' disabled'; ?>>
                        <label class="form-check-label" for="reset-<?php echo $e($kind); ?>">
                            <strong><?php echo Text::_($name); ?></strong>
                            <span class="badge bg-secondary ms-1"><?php echo (int) $this->counts[$kind]; ?></span>
                            <span class="d-block small text-muted"><?php echo Text::_($name . '_DESC'); ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="alert alert-warning">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="confirm" value="1" id="reset-confirm" required>
            <label class="form-check-label" for="reset-confirm"><?php echo Text::_('COM_MCPASK_RESET_CONFIRM'); ?></label>
        </div>
        <button type="submit" class="btn btn-danger"><span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('COM_MCPASK_RESET_BUTTON'); ?></button>
    </div>
    <p class="small text-muted"><?php echo Text::_('COM_MCPASK_RESET_KEPT'); ?></p>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
