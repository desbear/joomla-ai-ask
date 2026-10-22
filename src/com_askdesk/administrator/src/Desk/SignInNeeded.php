<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

\defined('_JEXEC') or die;

/**
 * A source needs the person to sign in to it (again) first: where that starts (0.5.0).
 *
 * @since  0.5.0
 */
final class SignInNeeded extends \RuntimeException
{
    public function __construct(public readonly string $connect, string $message = '')
    {
        parent::__construct($message ?: 'Sign in first');
    }
}
