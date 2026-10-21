<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Push;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * The console as an app on a phone or a computer (0.3.0): its manifest, and the service worker
 * that shows its notifications. Neither holds anything of anyone: the worker only draws what a
 * push brings and opens the console.
 *
 * The worker comes with Service-Worker-Allowed for the site's root, so its reach (its scope) is the
 * whole site and takes in the console's page wherever its menu item puts it.
 *
 * @since  0.3.0
 */
class PwaController extends BaseController
{
    /**
     * The service worker.
     */
    public function worker(): void
    {
        $file = JPATH_ROOT . '/media/com_askdesk/js/worker.js';
        $code = is_file($file) ? (string) file_get_contents($file) : '';

        $this->out('text/javascript', '/* AI Ask Desk ' . $this->version() . " */\n" . $code, ['Service-Worker-Allowed' => Uri::root(true) . '/']);
    }

    /**
     * The manifest: its name, its icons, where it opens.
     */
    public function manifest(): void
    {
        $this->app->getLanguage()->load('com_askdesk', JPATH_SITE);

        $console = Push::consoleUrl();
        $root    = Uri::root(true) . '/';
        $media   = Uri::root(true) . '/media/com_askdesk/images/';
        // Where it opens: the console's page it was added from (a path of this site only), else its menu item's
        $given   = $this->app->getInput()->getString('start', '');
        $start   = str_starts_with($given, $root) && !preg_match('#//|\\\\|[\s<>"]#', $given) ? mb_substr($given, 0, 500)
            : (string) (parse_url($console, PHP_URL_PATH) ?: $root) . (parse_url($console, PHP_URL_QUERY) ? '?' . parse_url($console, PHP_URL_QUERY) : '');

        $this->out('application/manifest+json', json_encode([
            'name'             => Text::_('COM_ASKDESK_CONSOLE'),
            'short_name'       => Text::_('COM_ASKDESK_PWA_SHORT'),
            'id'               => $start,
            'start_url'        => $start,
            'scope'            => $root,
            'display'          => 'standalone',
            'background_color' => '#f4f6fb',
            'theme_color'      => '#3b1fc9',
            'icons'            => [
                ['src' => $media . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $media . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $media . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function version(): string
    {
        $manifest = JPATH_ADMINISTRATOR . '/components/com_askdesk/askdesk.xml';

        return is_file($manifest) && preg_match('#<version>([^<]+)</version>#', (string) file_get_contents($manifest), $match) ? $match[1] : '';
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function out(string $type, string $body, array $headers = []): never
    {
        $this->app->setHeader('Content-Type', $type . '; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-cache', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);

        foreach ($headers as $name => $value) {
            $this->app->setHeader($name, $value, true);
        }

        $this->app->sendHeaders();
        echo $body;
        $this->app->close();
    }
}
