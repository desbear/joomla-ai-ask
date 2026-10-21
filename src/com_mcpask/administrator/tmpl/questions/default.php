<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Component\Mcpask\Administrator\Ask\UsageLog;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Gss\Component\Mcpask\Administrator\View\Questions\HtmlView $this */

$e      = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$filter = $this->filter;
$option = static fn ($value, $label, $current) => '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' . ((string) $value === (string) $current ? ' selected' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
$flags  = ['injection_suspect' => 'COM_MCPASK_FLAG_INJECTION_SUSPECT', 'html_payload' => 'COM_MCPASK_FLAG_HTML_PAYLOAD', 'output_filtered' => 'COM_MCPASK_FLAG_OUTPUT_FILTERED', 'probe' => 'COM_MCPASK_FLAG_PROBE', 'dismissed' => 'COM_MCPASK_FLAG_DISMISSED'];
?>
<form action="<?php echo Route::_('index.php?option=com_mcpask&view=questions'); ?>" method="get" class="card mb-3">
	<div class="card-body">
		<input type="hidden" name="option" value="com_mcpask">
		<input type="hidden" name="view" value="questions">
		<div class="row g-2 align-items-end">
			<div class="col-sm-6 col-lg-3">
				<label class="form-label small" for="mcpask-q-search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
				<input type="search" class="form-control" id="mcpask-q-search" name="search" value="<?php echo $e($filter['search']); ?>" placeholder="<?php echo $e(Text::_('COM_MCPASK_QUESTIONS_SEARCH')); ?>">
			</div>
			<div class="col-6 col-lg-2">
				<label class="form-label small" for="mcpask-q-from"><?php echo Text::_('COM_MCPASK_QUESTIONS_FROM'); ?></label>
				<input type="date" class="form-control" id="mcpask-q-from" name="from" value="<?php echo $e($filter['from']); ?>">
			</div>
			<div class="col-6 col-lg-2">
				<label class="form-label small" for="mcpask-q-to"><?php echo Text::_('COM_MCPASK_QUESTIONS_TO'); ?></label>
				<input type="date" class="form-control" id="mcpask-q-to" name="to" value="<?php echo $e($filter['to']); ?>">
			</div>
			<div class="col-sm-6 col-lg-2">
				<label class="form-label small" for="mcpask-q-source"><?php echo Text::_('COM_MCPASK_QUESTIONS_SOURCE'); ?></label>
				<select class="form-select" id="mcpask-q-source" name="source">
					<?php echo $option('', Text::_('COM_MCPASK_QUESTIONS_SOURCE_ALL'), $filter['source']); ?>
					<?php echo $option('site', Text::_('COM_MCPASK_QUESTIONS_SOURCE_SITE'), $filter['source']); ?>
					<?php foreach ($this->embeds as $id => $title) : ?>
						<?php echo $option('embed:' . (int) $id, Text::sprintf('COM_MCPASK_QUESTION_VIA', $title), $filter['source']); ?>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-6 col-lg-1">
				<label class="form-label small" for="mcpask-q-status"><?php echo Text::_('JSTATUS'); ?></label>
				<select class="form-select" id="mcpask-q-status" name="status">
					<?php echo $option('', Text::_('JALL'), $filter['status']); ?>
					<?php echo $option('unanswered', Text::_('COM_MCPASK_QUESTIONS_UNANSWERED'), $filter['status']); ?>
					<?php foreach (UsageLog::STATUSES as $status) : ?>
						<?php echo $option($status, $status, $filter['status']); ?>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-6 col-lg-2">
				<label class="form-label small" for="mcpask-q-rating"><?php echo Text::_('COM_MCPASK_QUESTIONS_RATING'); ?></label>
				<select class="form-select" id="mcpask-q-rating" name="rating">
					<?php echo $option('', Text::_('JALL'), $filter['rating']); ?>
					<?php echo $option('good', Text::_('COM_MCPASK_RATED_GOOD'), $filter['rating']); ?>
					<?php echo $option('bad', Text::_('COM_MCPASK_RATED_BAD'), $filter['rating']); ?>
					<?php echo $option('none', Text::_('COM_MCPASK_QUESTIONS_NOT_RATED'), $filter['rating']); ?>
				</select>
			</div>
			<div class="col-6 col-lg-2">
				<label class="form-label small" for="mcpask-q-who"><?php echo Text::_('COM_MCPASK_ASKED_BY'); ?></label>
				<select class="form-select" id="mcpask-q-who" name="who">
					<?php echo $option('', Text::_('JALL'), $filter['who']); ?>
					<?php echo $option('member', Text::_('COM_MCPASK_QUESTIONS_MEMBERS'), $filter['who']); ?>
					<?php echo $option('guest', Text::_('COM_MCPASK_ASKED_BY_GUEST'), $filter['who']); ?>
				</select>
			</div>
			<div class="col-6 col-lg-2">
				<label class="form-label small" for="mcpask-q-flag"><?php echo Text::_('COM_MCPASK_FLAGS_FILTER'); ?></label>
				<select class="form-select" id="mcpask-q-flag" name="flag">
					<?php echo $option('', Text::_('COM_MCPASK_FLAGS_ALL'), $filter['flag']); ?>
					<?php foreach ($flags as $value => $label) : ?>
						<?php echo $option($value, Text::_($label), $filter['flag']); ?>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-auto">
				<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<a class="btn btn-outline-secondary" href="<?php echo $e(Route::_('index.php?option=com_mcpask&view=questions', false)); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
			</div>
		</div>
	</div>
</form>

<p class="small text-muted"><?php echo Text::plural('COM_MCPASK_QUESTIONS_FOUND', $this->total); ?></p>

<div class="card">
	<div class="card-body">
		<?php echo LayoutHelper::render('questions', ['rows' => $this->rows], JPATH_ADMINISTRATOR . '/components/com_mcpask/layouts'); ?>
	</div>
</div>

<?php echo $this->pagination->getListFooter(); ?>
