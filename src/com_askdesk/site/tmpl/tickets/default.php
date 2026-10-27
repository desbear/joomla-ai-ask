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

/** @var \Gss\Component\Askdesk\Site\View\Tickets\HtmlView $this */
?>
<main class="askdesk-tickets" data-askdesk-tickets="<?php echo htmlspecialchars(json_encode($this->config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
	<?php if (!$this->enabled) : ?>
		<div class="askdesk-tickets__card"><p><?php echo Text::_('COM_ASKDESK_TICKETS_OFF'); ?></p></div>
	<?php else : ?>
		<noscript><?php echo Text::_('COM_ASKDESK_CONSOLE_NOSCRIPT'); ?></noscript>
	<?php endif; ?>
</main>
