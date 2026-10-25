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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \Gss\Component\Askdesk\Site\View\Console\HtmlView $this */
?>
<?php if (!$this->allowed) : ?>
	<?php
	$e      = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	$here   = Uri::getInstance()->toString();
	// Signed in with an account that may not answer: out of it, and back here to sign in with another
	$switch = Route::_('index.php?option=com_users&task=user.logout&' . Session::getFormToken() . '=1&return=' . base64_encode($here), false);
	?>
	<style>
		.askdesk-gate {
			--gate-text: #232332; --gate-muted: rgba(35, 35, 50, 0.6); --gate-canvas: #f5f7fa; --gate-surface: #fff;
			--gate-line: rgba(35, 35, 50, 0.08); --gate-accent: #0e86fe; --gate-soft: #ebf5ff;
			box-sizing: border-box; display: grid; place-items: center; min-height: 100dvh; margin: 0; padding: 24px 16px;
			background: radial-gradient(60% 50% at 85% 0%, rgba(51, 207, 255, 0.16), transparent 70%),
				radial-gradient(50% 40% at 0% 100%, rgba(14, 134, 254, 0.1), transparent 70%), var(--gate-canvas);
			color: var(--gate-text);
			font: 15px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "PingFang TC", "Noto Sans TC", "Microsoft JhengHei", sans-serif;
		}
		.askdesk-gate *, .askdesk-gate *::before { box-sizing: border-box; }
		.askdesk-gate__card {
			inline-size: min(100%, 400px); padding: 36px 32px 28px; border: 1px solid var(--gate-line); border-radius: 24px;
			background: var(--gate-surface); box-shadow: 0 24px 60px rgba(14, 60, 120, 0.12); text-align: center;
		}
		.askdesk-gate__icon { display: block; inline-size: 72px; block-size: 72px; margin: 0 auto 18px; border-radius: 20px; box-shadow: 0 8px 20px rgba(14, 134, 254, 0.25); }
		.askdesk-gate__title { margin: 0 0 6px; font-size: 24px; font-weight: 700; letter-spacing: 0.01em; line-height: 1.3; }
		.askdesk-gate__lead { margin: 0 0 22px; color: var(--gate-muted); font-size: 14px; }
		.askdesk-gate__note {
			display: flex; gap: 10px; align-items: flex-start; margin: 0 0 22px; padding: 12px 14px; border-radius: 14px;
			background: var(--gate-soft); color: var(--gate-text); font-size: 14px; text-align: start;
		}
		.askdesk-gate__note svg { flex: none; inline-size: 18px; block-size: 18px; margin-block-start: 2px; color: var(--gate-accent); }
		.askdesk-gate__button {
			display: flex; align-items: center; justify-content: center; gap: 8px; inline-size: 100%; min-block-size: 48px; padding: 0 20px;
			border: 0; border-radius: 14px; background: linear-gradient(45deg, #33cfff 0%, #0e86fe 100%); color: #fff;
			font: inherit; font-size: 16px; font-weight: 600; text-decoration: none; box-shadow: 0 6px 14px rgba(14, 134, 254, 0.3);
			transition: transform 0.15s, box-shadow 0.15s;
		}
		.askdesk-gate__button:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 10px 20px rgba(14, 134, 254, 0.34); }
		.askdesk-gate__button:focus-visible, .askdesk-gate__link:focus-visible { outline: 2px solid #a6d2ff; outline-offset: 2px; }
		.askdesk-gate__button svg { inline-size: 18px; block-size: 18px; }
		.askdesk-gate__links { display: flex; justify-content: center; gap: 18px; margin-block-start: 18px; font-size: 14px; }
		.askdesk-gate__link { color: var(--gate-muted); text-decoration: none; }
		.askdesk-gate__link:hover { color: var(--gate-accent); text-decoration: underline; }
		@media (prefers-color-scheme: dark) {
			.askdesk-gate {
				--gate-text: #e8ebf2; --gate-muted: rgba(232, 235, 242, 0.62); --gate-canvas: #12141a; --gate-surface: #1b1e26;
				--gate-line: rgba(255, 255, 255, 0.08); --gate-accent: #5eaeff; --gate-soft: rgba(14, 134, 254, 0.14);
			}
			.askdesk-gate__card { box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4); }
		}
		@media (max-width: 480px) { .askdesk-gate__card { padding: 28px 22px 22px; border-radius: 20px; } }
	</style>
	<main class="askdesk-gate">
		<div class="askdesk-gate__card">
			<img class="askdesk-gate__icon" src="<?php echo $e(Uri::root(true) . '/media/com_askdesk/images/icon-192.png'); ?>" alt="" width="72" height="72">
			<h1 class="askdesk-gate__title"><?php echo Text::_('COM_ASKDESK_CONSOLE'); ?></h1>
			<p class="askdesk-gate__lead"><?php echo Text::_('COM_ASKDESK_CONSOLE_GATE_LEAD'); ?></p>
			<?php if ($this->signedIn) : ?>
				<p class="askdesk-gate__note" role="status">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
					<span><?php echo Text::_('COM_ASKDESK_CONSOLE_NOT_STAFF'); ?></span>
				</p>
				<a class="askdesk-gate__button" href="<?php echo $e($switch); ?>"><?php echo Text::_('COM_ASKDESK_CONSOLE_OTHER_ACCOUNT'); ?></a>
			<?php else : ?>
				<p class="askdesk-gate__note">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
					<span><?php echo Text::_('COM_ASKDESK_CONSOLE_SIGN_IN'); ?></span>
				</p>
				<a class="askdesk-gate__button" href="<?php echo $e($this->login); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
					<?php echo Text::_('JLOGIN'); ?>
				</a>
			<?php endif; ?>
			<div class="askdesk-gate__links">
				<a class="askdesk-gate__link" href="<?php echo $e(Uri::root()); ?>"><?php echo Text::_('COM_ASKDESK_CONSOLE_GATE_BACK'); ?></a>
			</div>
		</div>
	</main>
<?php else : ?>
	<div class="askdesk" data-askdesk="<?php echo htmlspecialchars(json_encode($this->config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
		<noscript><?php echo Text::_('COM_ASKDESK_CONSOLE_NOSCRIPT'); ?></noscript>
	</div>
<?php endif; ?>
