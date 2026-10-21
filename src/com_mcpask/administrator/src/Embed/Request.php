<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Embed;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * A request from AI Ask embedded on another website: the key it is embedded with
 * (X-Mcp-Ask-Embed) and, when the visitor is known, the token that says who they are
 * (X-Mcp-Ask-Identity). Such requests come from this site's own page in the frame, so the
 * same-origin checks hold as for the module.
 *
 * @since  0.11.0
 */
final class Request
{
    /** @var object|null|false  The key of this request, once looked up (false: not yet) */
    private static object|null|false $embed = false;

    /**
     * The embed key of this request, if any; the visitor its token stands for becomes the
     * request's user (for this request only, nothing is kept in the session).
     */
    public static function apply(CMSApplicationInterface $app): ?object
    {
        if (self::$embed !== false) {
            return self::$embed;
        }

        $server = $app->getInput()->server;
        $key    = $server->getString('HTTP_X_MCP_ASK_EMBED', '');

        self::$embed = null;

        if ($key === '') {
            self::signin($app);

            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $embed = (new Embeds($db))->byKey($key);

        if (!$embed) {
            return null;
        }

        self::$embed = $embed;
        $token       = $server->getString('HTTP_X_MCP_ASK_IDENTITY', '');
        $current     = $app->getIdentity();

        if ($token !== '' && (!$current || $current->guest) && method_exists($app, 'loadIdentity')) {
            $user = (new Identity($db, (string) $app->get('secret')))->user(mb_substr($token, 0, 4000), $embed);

            if ($user) {
                $app->loadIdentity($user);
            }
        }

        return $embed;
    }

    /**
     * This site's own page, its visitor signed in through another website (0.20.4): the key
     * (X-Mcp-Ask-Signin) and that website's token (X-Mcp-Ask-Identity). Only a key set for it, and
     * only a token that website signed; the request is this site's, not an embed's.
     */
    private static function signin(CMSApplicationInterface $app): void
    {
        $server  = $app->getInput()->server;
        $key     = $server->getString('HTTP_X_MCP_ASK_SIGNIN', '');
        $token   = trim(mb_substr($server->getString('HTTP_X_MCP_ASK_IDENTITY', ''), 0, 4000));
        $current = $app->getIdentity();

        if ($key === '' || substr_count($token, '.') !== 2 || str_starts_with($token, 'v1.') || ($current && !$current->guest) || !method_exists($app, 'loadIdentity')) {
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $embed = (new Embeds($db))->byKey($key);

        if (!$embed || Embeds::signinUrl($embed) === null) {
            return;
        }

        $user = (new Identity($db, (string) $app->get('secret')))->user($token, $embed);

        if ($user) {
            $app->loadIdentity($user);
        }
    }
}
