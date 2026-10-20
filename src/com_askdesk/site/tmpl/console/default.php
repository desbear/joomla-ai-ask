<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Gss\Component\Askdesk\Site\View\Console\HtmlView $this */
?>
<?php if (!$this->allowed) : ?>
	<div class="askdesk-denied">
		<h1><?php echo Text::_('COM_ASKDESK_CONSOLE'); ?></h1>
		<?php if ($this->signedIn) : ?>
			<p><?php echo Text::_('COM_ASKDESK_CONSOLE_NOT_STAFF'); ?></p>
		<?php else : ?>
			<p><?php echo Text::_('COM_ASKDESK_CONSOLE_SIGN_IN'); ?></p>
			<p><a class="btn btn-primary" href="<?php echo htmlspecialchars($this->login, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('JLOGIN'); ?></a></p>
		<?php endif; ?>
	</div>
<?php else : ?>
	<div class="askdesk" data-askdesk="<?php echo htmlspecialchars(json_encode($this->config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
		<noscript><?php echo Text::_('COM_ASKDESK_CONSOLE_NOSCRIPT'); ?></noscript>
	</div>
<?php endif; ?>
