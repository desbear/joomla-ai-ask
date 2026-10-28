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
 * Work done in a chat (0.6.1), offered by a panel (a PanelInterface that also implements this): forms
 * the person answering picks from the box's form button, filled in a card in the chat that only the
 * people answering see (drafted by AI on asking, sent on confirming). What they make, the panel shows
 * beside the chat as it does the rest.
 *
 * A card is a form as a panel's ({title, hint, sent, blocked, submit, fill, fields: {key, label, kind,
 * options, value, required, readonly}, made: {title, meta, ref, url, mark}}): made, the record it made, opened
 * whole in the side card by the panel's "show" ({ref}); mark (0.6.4), a word said of it beside the suggested
 * replies too ("in the knowledge base"). A field of the kind "tags" is several of its options, one a line.
 *
 * @since  0.6.1
 */
interface WorkInterface
{
    /**
     * The forms to pick from for this chat.
     *
     * @return  array<int, array{id: string, title: string, description?: string, first?: bool, group?: string, icon?: string, suggest?: string, before?: string}>
     *          first: the one picked at once (the usual one); group (0.6.4): where it goes, the heading
     *          it is listed under ("CRM", say); icon: one of the console's; suggest and before: a word on filling
     *          another form first, and which ("no customer record yet: make it first", say), asked on picking
     */
    public function forms(object $room, object $staff): array;

    /**
     * A card of a form: "start" (a new one, drafted by AI when it can), "fill" ({values, note}: drafted
     * again, what was written kept; note, words of the person answering on how to fill it) or "submit" ({values}: as confirmed; made, it says so in sent and made).
     *
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>  The card (a form)
     */
    public function work(object $room, object $staff, string $form, string $action, array $input): array;
}
