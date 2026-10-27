<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Access;

use Joomla\CMS\User\User;

\defined('_JEXEC') or die;

/**
 * The back end's pages, each a permission of its own (0.22.0), set in the component's Permissions:
 * who may open the questions, the indexed pages, the answers' quality, the agents, the handoffs,
 * the appearance, the pets and the embed keys, and do there what that page does. The dashboard is
 * for everyone who may manage AI Ask; Super Users may do everything.
 *
 * What a page asks for besides (editing, deleting, the options) is asked as before; the pages that
 * needed the options to be opened at all (agents, handoffs, appearance, pets, embeds) need only
 * their own permission now, given on installing to the groups that had the options.
 *
 * @since  0.22.0
 */
final class Areas
{
    /** Each page, and what was needed to open it before: its permission is given at first to those who had that */
    public const AREAS = [
        'questions'  => 'core.manage',
        'pages'      => 'core.manage',
        'quality'    => 'core.manage',
        'agents'     => 'core.options',
        'handoffs'   => 'core.options',
        'appearance' => 'core.options',
        'pets'       => 'core.options',
        'embeds'     => 'core.options',
    ];

    public static function action(string $area): string
    {
        return 'mcpask.area.' . $area;
    }

    /**
     * Whether the user may open a page of the back end, and do there what it does.
     */
    public static function allows(User $user, string $area): bool
    {
        if ($user->authorise('core.admin', 'com_mcpask')) {
            return true;
        }

        return isset(self::AREAS[$area]) && $user->authorise('core.manage', 'com_mcpask') && $user->authorise(self::action($area), 'com_mcpask');
    }
}
