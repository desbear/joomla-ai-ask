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
 * A panel another extension adds to the console, beside a chat (0.2.0): what it knows of the visitor
 * from elsewhere (a CRM, say), and what the person answering may do with it. Offered by plugins of the
 * group "askdesk" on the event onAskdeskPanels (append to the argument "panels").
 *
 * What a panel shows is plain data, drawn by the console the same way for all of them:
 *
 *  - name (string): set by the console to the panel's own name
 *  - title, subtitle (string)
 *  - state (string): "found", "none", "error" or "setup"
 *  - message (string): a line to say, when it has nothing to show or something went wrong
 *  - fields (list of {label, value}), tags (list of strings), link ({title, url}: https only)
 *  - search ({placeholder}): the person may look for another match; results ({ref, title, meta}),
 *    one to choose
 *
 * Text only: nothing in it is read as HTML.
 *
 * @since  0.2.0
 */
interface PanelInterface
{
    /** A short name of its own, letters and digits: "vitalcrm". */
    public function name(): string;

    /**
     * What it shows beside a chat.
     *
     * @param   object  $room   The chat (name, email, user_id, page, language…)
     * @param   object  $staff  The person answering (user_id, language…)
     *
     * @return  array<string, mixed>
     */
    public function view(object $room, object $staff): array;

    /**
     * Something the person answering did in it: "search" ({text}), "choose" ({ref}), "refresh".
     *
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>  What it shows now
     */
    public function act(object $room, object $staff, string $action, array $input): array;
}
