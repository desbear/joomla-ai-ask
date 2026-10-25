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
 *  - note ({title, text, hint, sent, author, blocked, choices}): a note the person answering reads,
 *    changes and sends on themselves (to keep the chat in a CRM, say): text the draft in the box,
 *    sent a line once it went (the box is then shown, not changed), author who it goes as, blocked
 *    why it can't go (sending is then off), choices ({value, label, group, checked}) to tick, one
 *    at least (a note's categories, say; those of a group shown together). Summary::write() drafts
 *    one from the chat.
 *  - list ({title, empty, total, more, items: {title, meta, text, tags}}): records of the visitor
 *    kept there (notes before, say): the latest few shown, all of them in a side card; more a link
 *    (https) to the rest when there are more than given.
 *
 *  - brief ({generated, stale, notice, sections: {title, tone (info, warn, ok), points: {text, sources}}}):
 *    a summary to read, in parts, each point with where it comes from (an AI's brief of the visitor,
 *    say); stale when what it was made from has changed since
 *  - form ({title, hint, sent, blocked, submit, fill, fields: {key, label, kind (text, textarea, select,
 *    date, number), options, value, required}}, 0.6.1): a small form the person answering fills and sends
 *    (submit, the button's word), a draft of it written by AI from the chat on asking (fill, its button's word;
 *    Summary::fill() does it); sent a line once it went, the form then empty again for another; folded
 *    (with made, the item just made, and again, the word that opens it again) once something was made
 *    from it for this chat, so it is not made twice by mistake
 *  - buttons ({action ("make", "refresh" or "fill"), label, primary}, 0.6.1): a few of its own (make: something
 *    made in that system for the visitor, say); a list's items may carry url (https) to open there, or
 *    ref to open in the console's side card (show), room the chat it came from (another of the visitor's
 *    to go to, or this one)
 *  - record ({title, meta, url, open, fields: {label, value}}, 0.6.1): one record whole, in the side card,
 *    after "show"; url opens it where it is kept, open the link's word
 *  - icon (a name of the console's icons) for its heading; follow ("draft" or "refresh"): an action the
 *    console runs once, at once, after drawing it (slow work, such as an AI's, not holding up the others);
 *    look ("ai"): drawn as a blue-violet card of its own above the others, read again from its heading
 *
 * A view that is an empty array is not shown (nothing for this chat).
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
     * Something the person answering did in it: "search" ({text}), "choose" ({ref}), "refresh",
     * "draft" (the note written again by AI), "send" ({note, choices}: the note as they confirmed
     * it, the choices ticked), and (0.6.1) "fill" ({values}: the form drafted by AI from the chat, what
     * was written kept), "submit" ({values}: the form as they confirmed it), "make" (a button's) or
     * "show" ({ref}: an item of the list, whole, as record).
     *
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>  What it shows now
     */
    public function act(object $room, object $staff, string $action, array $input): array;
}
