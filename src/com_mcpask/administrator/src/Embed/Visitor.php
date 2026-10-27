<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Embed;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * A visitor who signed in at an account centre without an account here (0.22.1): their address and name,
 * vouched for by the account centre, kept in their session only, for some hours. No account is made and
 * nothing of them is written anywhere else; signing out, or the session ending, forgets them.
 *
 * @since  0.22.1
 */
final class Visitor
{
    private const SESSION = 'mcpask.visitor';

    /** How long a sign-in lasts, used or not */
    private const HOURS = 8;

    /**
     * Who the visitor is, while their sign-in lasts: a member signed in here is not one.
     *
     * @return  array{email: string, name: string, via: string}|null
     */
    public static function current(): ?array
    {
        try {
            $app = Factory::getApplication();
        } catch (\Throwable) {
            return null;
        }

        $user = $app->getIdentity();

        if ($user && !$user->guest) {
            return null;
        }

        $kept = (array) $app->getSession()->get(self::SESSION, []);

        if (!filter_var((string) ($kept['email'] ?? ''), FILTER_VALIDATE_EMAIL) || (int) ($kept['until'] ?? 0) < time()) {
            return null;
        }

        return ['email' => (string) $kept['email'], 'name' => (string) ($kept['name'] ?? ''), 'via' => (string) ($kept['via'] ?? '')];
    }

    /**
     * Kept, in a session of its own (a session id known before is no good after).
     */
    public static function keep(string $email, string $name, string $via): void
    {
        $session = Factory::getApplication()->getSession();

        $session->fork();
        $session->set(self::SESSION, [
            'email' => mb_substr($email, 0, 255),
            'name'  => mb_substr(trim(strip_tags($name)), 0, 100) ?: (string) strstr($email, '@', true),
            'via'   => mb_substr($via, 0, 40),
            'until' => time() + self::HOURS * 3600,
        ]);
    }

    public static function forget(): void
    {
        Factory::getApplication()->getSession()->remove(self::SESSION);
    }
}
