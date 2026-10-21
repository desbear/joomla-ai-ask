# Changelog

Until 0.13.2 this extension was called "Ask the Site" and lived in the MCP Server for Joomla repository.

## AI Ask Desk 0.3.9

- The side beside the chat starts level with the chat's card: its search at rest, its held headings once
  scrolled.

## AI Ask Desk 0.3.8

- The round search: a magnifier, flat, violet above and blue below, a violet shadow; pointed at, a clearer violet
  edge.

## AI Ask Desk 0.3.7

- The side's search is a pill. As the side scrolls down it shrinks away toward the top end and a round
  blue-violet search grows there in its place (following how far the side is scrolled); back at the top, the
  search opens again. A tap on the round one brings it back.

## AI Ask Desk 0.3.6

- The work's card: 8px corners at its top, down to the window's bottom edge (no gap there); the cards in it 6px,
  what is in those 4px.
- The side no longer shows a scrollbar along its bottom (its foot reached a little past its edges).

## AI Ask Desk 0.3.5

- **The colour under the whole window**: the work is a card on it, 15px from the window's top, end and bottom,
  its corners rounded. Online's colour is a richer blue-violet, with glows of cyan and violet; it drifts a little
  faster.

## AI Ask Desk 0.3.4

- **One menu on one's face**: the statuses, then notifications on this device (a switch), the language (the
  others unfolding in place) and one's profile; the bell and the globe are no longer buttons of their own.
- **Who answers now**, beside whether visitors can chat: their faces stacked with how many; a click lists them
  with their statuses.
- The side is 267px at its narrowest; narrower, it folds away.
- **Marking a message dealt with**: a green gradient button, ending where the search box below ends; pressed, it
  says done, glows and sinks in, not to be pressed again.
- Online shows green everywhere.

## AI Ask Desk 0.3.3

- **A chat on a phone is a page over the list**: it slides in from the right; a swipe from the left edge takes it
  back, following the finger, the list showing under it. Going back (the button, the swipe, the browser's own
  back or swipe) always comes to the list, and back from the list stays in the console: it no longer leaves for
  the page before it (the sign-in page, say).

## AI Ask Desk 0.3.2

- **No bar above the work**: the work's colour reaches the top. What the bar held is at the side's foot, set apart
  by a shadow: whether visitors can chat now (in white, said shorter: "Chats open", "Messages only"), then one's
  face, name, the language and the bell. The bar shows only with the side folded away, they in it.
- **One's status on one's face**: ringed green, amber or grey, with a dot; a click opens the statuses and one's
  profile, upward.
- A chat being opened shows three dots bouncing until it is there.
- The visitor's conversation with AI before the chat: a deep blue-violet, its words white.

## AI Ask Desk 0.3.1

- **A logo of one's own**: the desk's options take a logo for the top of the console's side (in place of its
  card and title; white on transparency suits the colour), and a mark alone for when the side is narrow.
- **One's account at the foot of the side**: face, name and who else answers now, kept at the bottom as the list
  scrolls; a click opens one's profile upward from there. With the side folded away the face goes to the bar.
- The handle to drag the side's width sits on the light side of the edge, in grey (blue when held).

## AI Ask Desk 0.3.0

- **The console as an app, notified on the phone**: it can be added to a phone's home screen (or installed on a
  computer) as an app of its own, with its icon and name. The bell then subscribes the device to push
  notifications, the console closed or not: someone new waiting, a message in one's own chat, a message left (to
  everyone not away). A tap opens the console at that chat. Sent by the site itself (Web Push, encrypted for each
  device, signed with keys the desk makes once), with no outside service or library; a device the push service no
  longer knows is let go. On an iPhone or iPad, from iOS 16.4: add to the home screen first, then turn the bell on
  there (the console says so).
- **Phones**: the list fills the screen in its colour; a chat takes the whole height, even margins; the side panel
  opens over the chat as on a narrower computer (it had dropped below it); the box to write in no longer makes an
  iPhone zoom in, and keeps above the keyboard.

## AI Ask Desk 0.2.7

- The held headings: the topmost takes the card's top corners and the bottommost its bottom ones; a shadow only
  where a stack meets what scrolls, none between headings.

## AI Ask Desk 0.2.6

- The side's headings stay in view as it scrolls: those passed stack at the top, those still below at the bottom.
  One held there brings its part into view (opening it) when clicked; in its place, it folds as before.

## AI Ask Desk 0.2.5

- A panel's labels: the first eight, the rest behind "+N more" (and put away again); up to 200.
- calc() in the console's styles written without "+", which some optimisers (JCH Optimize) break by taking the
  spaces around it away.

## AI Ask Desk 0.2.4

- A panel's choices can come in groups, each a row with its name (a CRM's nested note categories, say).
- A panel can show records of the visitor kept there (their notes before, say): the latest three, cut short, and
  all of them in a side card that slides in over the side.

## AI Ask Desk 0.2.3

- The bar to take a screenshot with ("take the picture", "cancel") is whole again when the chat window is in
  another site: centred by its margins, it no longer had only half of the narrow frame to lay out in, its words a
  character a line and its buttons cut off (seen first in Safari).

## AI Ask Desk 0.2.2

- A panel's note can say who it will be written as, or why it can't be sent (sending is then off), and offer
  choices to tick before sending, one at least (the note's categories in a CRM, say), drawn as pills.

## AI Ask Desk 0.2.1

- **A note in a panel**: a plugin's panel can carry a note the person answering reads, changes and sends on
  themselves (to keep the chat in a CRM, say): AI drafts it from the chat, nothing goes until they confirm, and
  once sent it shows when and where it went. `Summary::write()` drafts it, as a brief (request, done, next) or as
  the chat made short, one "Q:"/"A:" line a turn.

## AI Ask Desk 0.2.0

- **Panels from plugins**: beside a chat, plugins of the new `askdesk` group can show a section of their own at the
  top of the side card (a CRM's customer, say): fields, labels, a link, and a search with results to choose from,
  each refreshable. Plugins answer `onAskdeskPanels` with a `PanelInterface`; the console draws only text and https
  links, and what a plugin calls stays on the server (see docs/askdesk.md, "Beside the chat").

## AI Ask Desk 0.1.3

- **Phrases**: sentences the people answering often write. Shared ones are kept in the back end (AI Ask Desk →
  Phrases); those pinned are a row of buttons inside the console's box, a click puts one in where the cursor is, to
  change before it is sent (and translated for the visitor). The rest are under "More phrases", with a search and
  tabs for all, shared and one's own. Each person keeps phrases of their own there (what is in the box, with a
  click), pins them to the row or deletes them; nobody else sees them.

## AI Ask Desk 0.1.2

- The console's conversation fades at its top and bottom edges once there is more to scroll, as the side and the
  inbox do.

## AI Ask Desk 0.1.1

- The console's box of the visitor's conversation with AI before the chat: its colours fade out within it, with no
  edge partway down when the conversation is long.
- The picture editor's bar is no longer half covered by the picture: the picture takes the whole window, the bar
  floats over it.

## AI Ask Desk 0.1.0 (new)

- **A lean live chat in AI Ask's own window**, a package of its own (`pkg_askdesk`, see docs/askdesk.md): visitors
  chat with people where they were asking questions, with what they asked the AI and the page handed over.
- **Translated both ways by AI**: each side writes in its own language; every message is kept as written and
  translated, and either side can see the words first written. A glossary keeps product names as they are.
  Messages are kept at once in the order written and translated afterwards; those already in the reader's language
  (told on the server by their letters) are not translated at all; translating has a model of its own with reasoning
  at its least; Chrome's own translation shows first where it can.
- **A console for the people answering**, a page of the site that works on a phone: chats waiting, one's own, others',
  messages left; taking a chat (one person gets it), online, busy or away, one's own language, sound and
  notifications, unread counts. Polling, no server of its own.
- **Suggested replies beside the chat**: pages of AI Ask's index that its AI judges, strictly, to answer the visitor's
  latest message, each with a reply drafted from it to put in the box and change; none when none does.
- **The visitor's history**, signed-in visitors only: their earlier chats, and their questions to AI Ask.
- **Short links and article cards**: links to the site's articles (in any of the site's address forms) are made short, and shown to the visitor as cards
  with the article's title, first words (translated) and picture.
- **AI search for the people answering**, above the suggested replies.
- **Guests say who they are first**: a name and an e-mail (required), a company (optional), before a chat starts.
- **Stickers**: packs of small pictures (moving ones too) managed in the back end, for both sides or staff only, sent
  from a picker beside the box.
- **Typing dots** on both sides, each message after a moment of dots, and messages that pop in.
- **Rating chats**: when a chat ends the visitor rates it in a window of its own, the pet of the person who answered
  bowing thanks; what is rated (stars, thumbs, 0–10, choices) is set in the Options; the averages on the dashboard.
- **Awaiting reply**: an inbox filter for the visitors who wrote last and were not answered, the longest waiting first.
- **Ending a chat asks once more**, in warning colours, while AI reads the chat: when it looks done the button turns
  calm; when something is left (a question not answered, a promise not kept) it says what.
- **People answering show themselves**: a name and a face (a pet of AI Ask, or a picture) for each, set in the back
  end, shown to visitors beside their messages and in the console.
- **Files in chats**: pictures (1 MB), videos and documents (10 MB) from both sides, by the paperclip, dropped on the
  chat or pasted; pictures made smaller in the browser first (1920 px, WebP), checked for what they are; pictures
  open whole, videos play, documents download with their size; each says the days it is kept (30), then it is deleted.
  **Files** in the back end lists them with who sent each.
- **Showing the screen**: visitors take a screenshot of the page (or choose a picture of their own) and mark it up
  before sending (pixelate what must not be seen, boxes, arrows, words) with Painterro (MIT), or record the tab for up
  to 60 seconds and watch it before sending, in a window of its own so pages can change while recording. The chat
  window steps aside meanwhile.
- **A library in Dropfiles**: each person answering has a folder, and there is a shared one, for files to send again
  from the console; in a category for registered users only, so AI Ask does not read it. Files sent in a chat can be
  kept there too.
- **The chat by e-mail**: when a chat ends the visitor is sent it as a PDF, in the words they read.
- **Only the people chosen answer**: a super user makes members people answering (no group permission); they set
  their name and face in the console too. The back end shows each one's chats, waits and ratings.
- **Messages left are answered by e-mail**: the reply, translated, opens in one's own mail program, and is kept.
- **Office hours, days closed, and no one there**: a message to leave (answered by e-mail), other agents, or both.
- **Kept only for signed-in visitors**, for the days set, and they are told so; guests' chats are gone an hour after
  they end. Nothing by IP address.
- **No waiting on a slow translation**: when the AI takes longer than the seconds set (3 by default), Google's
  translation (Cloud Translation, an API key of one's own) is shown for the moment, marked as such, and the AI's takes
  its place; a reply held back for its translation still shows in the order written. Messages left are kept at once
  and translated afterwards too.
- **Where the visitor is**: the page they are on follows them from page to page, beside their name in the console as a
  little timeline that scrolls up when they move on; a click shows every page of the chat.
- **Chosen, then sent**: pictures, videos, files and stickers wait above the box, each to take away again, until
  send or Enter. The picture editor keeps width, colours and text size in its bar, dimmed where the tool has no use
  for them.
- **The console's look**: the side in a deep wash of one's status (blue online, amber busy, grey-black away), the
  status switched from the desk's card; new waiting chats light up red and ring a bell on the count; ended chats fold
  away; drop-down menus, Mac-like scroll bars, and the work on a calm grey-blue with white cards.

## AI Ask for Joomla 0.20.4

- **Clear data**: a page for Super Users (on the dashboard's toolbar) that clears what AI Ask has kept of its
  visitors: the question log, handoffs, agent use (and agent conversations under way), and analysis reports (not
  ticked by default: their private addresses stop working). It shows how many records of each there are, asks
  for a confirmation, and logs who cleared what. Settings, agents, embed keys, pets, source rules and the index
  are kept. For starting over, or when another site takes over answering.
- **Signing in through the other website, on this site's own pages**: a key whose website signs its visitors'
  tokens may also be used by this site's own visitors (Sign in through this website, and its sign-in address): the
  conversation's Sign in button goes to that website's sign-in and comes back to the page known, the conversation
  open again. For AI Ask only, for the browser tab, an hour at most. On a Joomla site, the module of mode Another
  site's AI Ask answers at index.php?option=com_mcpask&task=embed.signin&module=(its ID), back only to the website
  it names, with a one-time state. See docs/embeds.md.
  A sign-in page that does not come back to the address it is given (one offering other ways in) no longer strands
  the visitor: the other website's module sends them on from whichever of its pages they land on signed in (within
  a quarter of an hour). Visitors signed in on this site itself see their name and Sign out there instead.
- **An embedded pet no longer flickers when dragged**: the frame kept shrinking back to the button's size under
  the finger whenever the pet asked for its size meanwhile (the page now keeps the whole window until it is let
  go, and only changes the frame when something changes), and let go, the pet showed a moment in its old corner
  (it now stays where it was put until the frame is there).
- **No ring round a pet when the conversation opens or closes**: the panel grew out of (and shrank into) the pet's
  round, see-through box, its edge showing a moment as a ring; it now comes in and goes where it is.
- **No ring round a pet when its card closes**: the search box eased back to the pet's size, its line showing a
  moment as a ring round the pet; it now closes at once.
- **Sites that minify CSS (JCH Optimize)**: the card beside a pet could open off the window (below it, or over it),
  as the minifier made some sizes invalid (it took the "+" in calc() for a selector's and dropped its spaces).
  The stylesheet no longer uses "+" in calc().
- **Look-ups work again**: since 0.20.3 a form field's look-up was left out of the card (a check still wanted the
  service's address, no longer sent to the browser), so no suggestions came.

## AI Ask for Joomla 0.20.3

- **Signing in through the site that embeds AI Ask**: in the mode Another site's AI Ask, the conversation's Sign in
  button takes a visitor who is not signed in to this site's own sign-in (its single sign-on, say; the module's new
  Sign-in address, or this site's login page) and back to the page, where they are then known; it no longer opens
  the other site's login. The embed script takes it as `data-login`.
- **An embedded pet keeps its place**: the phone layout (a framed pet, the card docked at the bottom) is not used in
  an embed's frame, which is narrow on any screen; the pet is shown whole there, with 28 pixels of room all round it
  in the frame (it could be cut at a corner: the frame was sized from a value read too early, now from the pet as
  drawn). Opened where it was dragged to, the box opens upward from the pet, which stays where it was.
- The card beside a pet opens above it when it fits there or there is more room there, below it otherwise (it
  could open below and run off the window).
- **Agents kept to some user groups on the embedding website**: an embedded conversation lists them once the
  other website says who the visitor is (or they sign in), and stops listing them when they sign out. The frame
  is made before that is known, so it only listed the agents open to guests.
- **Look-ups asked by this site**: a form field's look-up (RSForm agent) is asked by this site's server for the
  visitor, so a service that does not allow the website in browsers (CORS) works too. Only the address set for
  that field, of the visitor's own agent conversation, on the public internet; the first 8 items come back, with
  plain values only. The address is no longer sent to the browser.
- **A dragged pet stays put when opened**: on the website itself too, a pet dragged up the window no longer moves up
  as the conversation opens (its height was taken from the open button's, smaller than the pet), which could push
  the card below it or the pet off the top of the window.
- **Embedded buttons can be dragged**: once the finger moves, the frame covers the window, see-through, and the
  button follows; let go, the frame is the button's size again where it was put, and the website keeps the side and
  height for its next pages. The embed script handles it; nothing to set.
- **The pet's size on the embedding website**: the module's Pet size (another site's AI Ask), passed as `data-size`;
  the frame is sized to it.
- When a signed-in visitor cannot be passed on (no secret saved, no valid e-mail address), the page's source says
  why in a comment.
- **Who is signed in, then Sign out**: in an embedded conversation the visitor's name is shown, followed by a Sign
  out button (instead of a button with the name and a dot). Signed in by the embedding website, signing out goes to
  that website's sign-out (the module's new Sign-out address, or its own, back to the page), since the next page
  would otherwise sign them in again. In the Sign-in and Sign-out addresses, `{return64}` stands for the page in
  base64, as Joomla and External Login take it.
- Signing out on the embedding website signs the visitor out of the conversation too: a page with nobody signed in
  makes the frame forget whom that website had named (it kept them for up to an hour).
- **Back from signing in, the conversation opens again**: a visitor who left the conversation for the website's
  sign-in finds it open when they come back signed in (within a quarter of an hour), what was asked still there.
- **Question log**: a page of its own (in the menu, and from the dashboard) with every question kept, a page at a
  time, filtered by days, where it was asked (this site or another website through an embed key), how it ended,
  its rating, its marks, who asked and words. Questions asked on another website are marked with the embed key's
  name ("Via GSS Cloud") here and on the dashboard.

## AI Ask for Joomla 0.20.2

- **A site can show another site's AI Ask, its members known there.** The AI Ask module has a mode **Another site's
  AI Ask (embedded)**: given the other site's address and an embed key with its secret, it writes that site's embed
  script and, for a signed-in visitor, a token signed on the server (valid an hour; the secret never reaches the
  page). Answers, agents and chats with people are the other site's, so one staff console serves both sites.
- **Embed keys can make accounts for members of the other website**: when a correctly signed token gives an e-mail
  address with no account here, one is made (e-mail and name, the key's group or the group of new users, a password
  nobody knows, nothing mailed). Off by default.
- Tokens signed by the other website are now accepted (a PHP notice had turned them away).

## AI Ask for Joomla 0.20.1

- In a chat with a person, what waits above the box to be sent has a small round button at its corner to take it
  away (it showed as a square before).

## AI Ask for Joomla 0.20.0

- **Chats with people in the window** (for AI Ask Desk): a new card, `chat`, after which what the visitor writes goes
  to the person answering; their messages show with their name and, when translated, the words they wrote. The chat
  goes on from page to page and comes back into view when someone writes.
- **Translating for agents**: `Ask\Translator` translates a message with the site's AI service and tells its language.
- **Suggested replies and a visitor's questions, for AI Ask Desk**: `Ask\Suggester` (the index searched, each page
  judged by the AI for whether it answers, a reply drafted from those that do) and `Ask\VisitorQuestions` (a
  signed-in visitor's questions in the log).
- **A new moment for pets, Bowing thanks**, played when a visitor rates a chat with a person (AI Ask Desk).
- **Office hours shared by agents**: `Agent\OfficeHours` (LiveChat's, unchanged).
- **Showing the screen to a person** (for AI Ask Desk): a button beside the paperclip in a chat with a person, for
  screenshots marked up and recordings of the tab, made by a script the chat names (from the same site). Embedded,
  the frame may capture the page (`display-capture`) and the page puts it aside meanwhile; links to other websites
  open in a new tab with the conversation left open.
- **Talk to a person** goes to the first agent that passes visitors on to people (one telling whether they answer
  now), LiveChat or AI Ask Desk.
- **What the visitor chose to send waits above the box** in a chat with a person (pictures, recordings, files,
  stickers), and the page the visitor is on goes with each look for news (of this site, or of a website the chat is
  embedded in).
- **"Done" said as the agent's own reply**, a bubble beside its face with a small tick; a chat's thank-you shows the
  face on white in a card of deep blue and violet.
- Addresses of this site are known by the site's own name too (its Live Site, with or without "www.").

## AI Ask for Joomla 0.19.1

- **Agents kept to user groups**: an agent can be kept to some user groups (a group counts with the groups under it).
  Others do not see it listed, are not offered it by the AI or passed to it by another agent, and cannot start or
  go on with it. The list of agents marks those kept to groups. None chosen: everyone the access level lets in.
- **Answers drawn from what was asked, not from pages written alike.** Passages scoring well below the best one are
  left out, and a passage that names a distinctive word of the question (a product name, say) counts for a little
  more: asked about a product with one page of its own, the answer is given that page rather than twenty pages of
  other products that only share the site's wording. A subject with many pages keeps them, as they score alike.
- **Sources: the pages the answer cites.** Once an answer is written, only the pages it cites are listed; the others
  it was given are folded into one line that opens them.
- Pets page: **Delete** is in the toolbar beside New, as in Joomla's own lists (it asks to choose pets first and
  confirms); deleting cleaning pets returns to their tab.

## AI Ask for Joomla 0.19.0

- **Documents answered from, with the site's pages.** A new plugin, **AI Ask - Dropfiles documents**
  (`plg_mcpask_dropfiles`, installed disabled), adds the public files of Dropfiles to the index: PDF, Word (.docx),
  PowerPoint (.pptx), Excel (.xlsx), Markdown and text, read on the server. Files are indexed and searched together
  with the articles, never in a separate place: one question may be answered from a page and a PDF at once. Only
  files a guest may download are read (published, in published public categories, within their dates, kept on this
  server), checked again before a passage is quoted. Settings: the categories (with those under them; none for all),
  the kinds of files and the largest size. A changed file is read again; one taken away leaves the index.
- **Documents are cited as files**: the source card shows the kind, the page the passage is on and the size, opens a
  PDF at that page (when Dropfiles shows PDFs in the browser) and has a download button. The AI is told which sources
  are documents the visitor can download.
- **Reading documents**: PDF page by page, Word with its headings and tables, PowerPoint slide by slide in the order of
  the deck with titles and speaker notes, Excel sheet by sheet as tables. PDFs are read with PdfParser (LGPL-3.0),
  bundled, without keeping images, within a memory limit.
- **Indexed pages, Documents tab**: every file of the chosen categories with how it stands: answered from (with the
  passages the AI is given), not read yet or without text, or not read and why (not public, in a cloud drive, a kind
  not read, larger than allowed). **Read now** reads a file at once and says what came of it.
- **Text given for a file**: paste plain text or Markdown for a file whose text cannot be read (a scan, a file too
  large) or says too little, added to what is read of it or used instead; the file is cited as before. A file too
  large or of a kind not read is answered from by that text alone.
- **Indexed pages filter by source**: articles, FLEXIcontent items, EasyBlog posts or Dropfiles files on their own.
- **Tests no longer count as visitors left without help.** A rating whose reason is a probe (markup, statements,
  template expressions, one character over and over) is not counted, only marked in the log; questions marked as
  steering the answer or carrying markup are left out of the questions to look at and of the share of helpful
  answers; and **Dismiss** takes any other question off that list. Long unbroken text no longer runs off the page.
- **Beside a pet, one card**: the box to type in and the helpers to try are one card the pet speaks, with a tail to
  it, above the pet (below it when the pet sits too high), to its upper left or upper right; it fits a phone's width.
  On phones the pet takes its phone size again (the size set for wide screens had been kept), shows its half-body
  thumbnails in a rounded frame, and, open, the card docks across the bottom of what is seen (just above the
  keyboard while typing: iOS moving the page no longer pushes the box under its bar), the framed pet pressed onto
  its upper corner on the pet's side.
- **At most two helpers beside a pet**, the rest in one button of stacked faces that drops a list open; in the
  conversation the helpers stay on one line, and those that do not fit go into the same button.
- **Suggested replies** are blocks with a plus, put in the box when chosen; choices in forms are rounded rectangles
  instead of pills.
- **Festive pets**: a tab of the module lists days and the pet (and cleaning pet) to show on them instead of the
  usual ones: every year on the same days (a period may run over the new year) or on those dates only; the first
  row that matches wins, by the site's time zone.
- **Pets in an order of your own**: drag a card on the Pets page, or use its arrows; new pets come last. The lists to
  choose a pet from follow that order.
- **Choosing a pet by its picture**: the pet and cleaning-pet settings (module, festive rows, agents, embeds) open a
  list with each pet's thumbnail instead of names only.
- **A new pet takes its pictures at once**: pictures can be chosen while a pet is being made, and are stored when it
  is first saved (before, the pet had to be saved with its name first).
- A cleaning pet clears without the saucer's beam of light under it: its own pictures can carry the light.
- The module's script and styles are fetched again after every install (Joomla's media version), not only when the
  version number changes.
- **Installing completes the database of the version**: its statements are run again after every install, so a
  site that had an earlier build of the same version gets what was added since (an order for pets, the texts given
  for files).
- **For developers**: the event `onMcpaskSources` lets a plugin add sources of its own (`SourceInterface`, or
  `FileSourceInterface` for files); they join the same index, the same answers and the indexed pages list.

## AI Ask for Joomla 0.18.0

- **Hard questions no longer end without an answer.** A reasoning model thinks within the same allowance as it writes,
  so a question that made it think long (a quote for several products, say) could leave nothing written, logged as
  answered. Reasoning models now get room to think besides the answer's length; a reply that is still empty is asked
  for once more, without tools and with little thinking; and an answer that never comes is logged as an error.
- **An answer that failed offers a way on**: ask the same question again (the failed turn gives way to the new one),
  or go on with a person or an agent (a contact form), the buttons as beside the box to type in.
- **Pets greet visitors**: at the start of each visit, signed-in visitors by name ("{name}, welcome back!") and others
  with a few lines in turn (an invitation to sign in or to make an account), the pet talking while it speaks.
- **Pet moments are not cut short**: a moment that plays once is not replaced by a lesser one while it plays (waking by
  the greeting the same movement of the pointer brings); and when a moment's picture is uploaded, how long it plays
  once is read from the file (APNG, GIF, animated WebP) and set as the moment's length.
- **Cleaning pets.** Pets has two tabs: pets of the button and agents, and cleaning pets, which take the old
  conversation away when a new one is started instead of the saucer: a picture while cleaning and asking, and one
  while waiting beside the box to type in (the first without it). Without one, the saucer does it as before.
- **Ways to clear the conversation**, in the module: taken away by the saucer (or a cleaning pet), the letters now
  pulled up with a trail and the boxes lifting and blurring away; blown away to the left in gusts; drawn round into a
  black hole that opens and closes; faded out; or at once.
- The saucer's speech bubble: its tail grows out of the bubble, shadow and all, instead of standing apart.

- **RSForm! Pro agent: signed-in visitors' details filled in.** Fields start with their default value as on the
  form's page, RSForm's code run and its global placeholders put in for the visitor (`{global:fullname}`,
  `{global:useremail}`, `{global:username}` and the like; before, such defaults were left out). For forms without
  them, **Signed-in visitors' details** on the agent: one per line, field=name, email or username. Visitors may change
  what was filled in.

## AI Ask for Joomla 0.17.0

- **Agents pass visitors on to other agents.** A new **Referral** tab for every agent: the agents it passes visitors on
  to, with the conversation so far, and whether to offer them when it is done (a contact form sent: "You can also chat
  with a person"). An agent already in the conversation is not offered again, and visitors are passed on at most twice
  in a row, so two agents never send a visitor back and forth. Agents are offered only when the visitor may meet them.
- **LiveChat: when no one is there.** Outside office hours, or within them when no one is online, LiveChat may offer a
  message to leave (as before), the agents under Referral instead (a contact form), or both. Outside office hours this
  is decided at once; within them, LiveChat is asked first, and its window opens only when someone is there. On another
  website's page (embedded) the window opens there as before, with the agents beside it.
- **A light on the buttons that lead to people** ("Talk to a person", and the agent's own button): red outside office
  hours; within them green or red once LiveChat has said whether someone is online, when it is on the page already or
  has been opened in this visit. LiveChat is never loaded just for the light (it sets cookies of its own).
- Saving an agent with agents chosen under Referral that would never be offered (LiveChat still set to a message when
  no one is there, and "Offer them when done" off) says so, and where to change it.
- **LiveChat: public holidays filled in.** Under the days off, a country, a year and **Add public holidays**: the days
  off of the whole country are added to the list (what is there stays, notes and all; each day once; in order), to be
  checked before saving. Taiwan from the office calendar of the Directorate-General of Personnel Administration
  (data.gov.tw), with days in lieu; China from the State Council's notices (holiday-cn); Malaysia and Thailand from
  Google's public holiday calendars; the United States, Japan, Korea, Hong Kong, Singapore, Vietnam, the Philippines,
  Indonesia, the United Kingdom (England), France, Germany, Australia, Canada and any other country by its code from
  Nager.Date. For Taiwan and China, weekend days worked instead (補行上班, 调休上班) go to the new field below.
- **LiveChat: also open on.** Days worked although they are not working days of the week (a weekend made up for a
  holiday), one a line: with hours after the date ("2027-02-20 09:00-12:00", several if need be) open then, without
  them as on a working day of the week. A day also among the closed days stays closed.
- Handoffs: a new outcome, **Passed on to another agent**, with the agent the visitor went on with (in the list and
  the CSV), to see how many visitors outside office hours fill in the form instead.

## AI Ask for Joomla 0.16.0

- **Indexed pages** (new page under AI Ask): every page the answers come from, from this site, peer sites, listed
  websites and pages added by address, with its address, passages and when it was read. Search by title, address or
  item ID; filter by where pages come from, by status, or to pages with little text (at most one passage), which are
  worth a look. Open a page to see exactly the passages the AI service is given of it; read a page again now.
- **Pages added by their address**, one per line, with an optional selector of the part to read. Each is read on its
  own (its links are not followed) and read again every day; robots.txt does not apply to pages the administrator
  names. An address of this site that is indexed already (by its address, its menu item's path or its alias) is not
  added twice; the message says what it is indexed as.
- **Ignoring pages instead of deleting them.** A page that would be indexed but should not be answered from is
  ignored: taken out of the index at once and never read again (nor embedded), however it is found, until restored.
  Single pages are kept by what they are, so a new address does not bring them back, with who ignored them, when and
  why. **Addresses not to answer from**: patterns with `*`, one per line, for any page of this site, peer sites, listed
  websites or added pages; saving them takes what they match out of the index.
- **RSForm! Pro agent: a form sent from the conversation is processed as on the form's own page.** Before, only the
  submission, its events and its e-mails were done; now also the form's own scripts ("on form process" and "after form
  process"), its mappings and its **Post to another location** (the silent post other systems receive submissions
  through, with its headers). A form whose script turns the answers down is not sent. A captcha and other fields the
  conversation does without do not keep a form from being sent; a post that sends the visitor's browser elsewhere (not
  silent) cannot be made from a conversation and is left out.
- The bar of the agent in charge ("Talking with …") no longer lies over the start of the conversation.
- MCP Server for Joomla tools: `list_indexed_pages` (with the pages that have little text), `show_indexed_page` (its
  passages), `add_pages`, `ignore_pages`, `list_ignored_pages`, `restore_ignored_page`.

## AI Ask for Joomla 0.15.0

- **FLEXIcontent items read from their page.** For types whose items keep their text in the type's template rather
  than in the item (a price list laid out from a few fields, say), **Options → Content → FLEXIcontent types read from
  their page** reads the items of those types as visitors see them, from the item's page on the site in the item's
  language, with an optional selector of the part to read. Such pages are read again every day; an embedding is made
  again only when what they show changed. A page that cannot be read just now keeps what was indexed; an item whose page
  leads to another website, is gone or holds no text where the selector points is indexed from its text and fields, as
  other types are. The dashboard lists both kinds with the reason.
- **The site's own pages are read even when the server cannot reach its own public address** (a firewall or NAT that
  does not let a server reach itself that way, "Could not connect to server"): they are then read through the web
  server on the same machine: an address the administrator gives (**Options → Content → This site's address on the
  server**), the address the request came in on, the loopback address; over https with the site's own name and
  certificate, or over plain http with `X-Forwarded-Proto: https` for a server behind a proxy that ends https. The way
  found is remembered; when none works, the message lists what each way answered.
- **Listed websites: a feed or sitemap is read even where robots.txt keeps crawlers out.** Sites often disallow
  their sitemap's address (JSitemap's `/component/jmap/sitemap/xml?format=xml` falls under the common
  `Disallow: */component/` and `Disallow: /*?format=*`), and the list was then never read ("HTTP 0"). robots.txt is
  about crawling pages: the list the administrator gave, the parts of a sitemap index and the sitemaps robots.txt
  names are read; each page they list is still checked.
- **A listed website that cannot be read says why**, instead of "HTTP 0": robots.txt, a name this server cannot
  find, a name that leads into the internal network (with its address), a connection refused or timed out (with
  curl's words), too many redirects. The message no longer speaks of a feed or sitemap when a home page was given.
- **Listed websites may be marked "Internal network"**: read even when, from this server, their name leads to an
  internal address (a website of the same company through its own DNS); refused by default, as before.
- Selectors of the part of a page to read (here and for listed websites) may name several parts separated by commas;
  a named part is read even when it is a dialog.
- **RSForm! Pro agent**
  - The form is shown in the page's language from RSForm's translations; before, a form written in English stayed in
    English on Chinese pages.
  - RSForm's checks of each field apply in the conversation (its validation rules, a site's custom ones, the maximum
    length); placeholders are shown.
  - Read-only boxes (terms to agree to) are shown as text to read and sent as they are; markup in option labels is
    shown as text.
  - New settings: fields left out, a field for the page the visitor started from, and look-ups that suggest values
    from a JSON service as the visitor types and fill in other fields from the one chosen.
- Checkboxes and radio buttons in agents' cards were invisible on templates that reset `appearance`, leaving an empty
  space before each option.

## AI Ask for Joomla 0.14.0

- **A project of its own, under a new name**: AI Ask for Joomla ("AI 問答"), apart from MCP Server for Joomla. Element
  names are unchanged (`com_mcpask`, `mod_mcp_ask`, …), so sites update in place. The `MCP - AI Ask` plugin, which
  gives tools to AI assistants connected through MCP Server for Joomla, now comes with this package; it does
  nothing without that server.
- **No pet comes with the extension.** Pets are made on the Pets page; until the first one is made, modules, embed
  keys and agents show where to make one instead of a list. Modules that used the module's own pet show the icon.
- **Pictures made by AI come from a plugin**: the image settings left the component's options for a new plugin,
  `AI Ask - Image generation (OpenAI / Open WebUI)`, installed but not enabled. Other plugins may offer an image
  service through the event `onMcpaskImageProviders` (interface `ImageProviderInterface`). Without one, report
  sections do not offer "Made by AI" and the daily cap is not shown. Sites that used these settings re-enter them in
  the plugin.
- **No defaults tied to any company**: no hosts are allowed for links besides the site's own until listed; agents
  have no report template until one is written (types, scores, sections, buttons); examples in the back end are
  neutral; the how-to answer template is in English (answers still follow the visitor's language).
- Tests use made-up data; the acceptance scripts that needed a particular site's content were removed.

## Ask the Site 0.13.2

- The component's menu (the left of the back end, and Components → Ask the Site) lists Agents, Handoffs,
  Appearance, Pets and Embeds besides the dashboard and answer quality.

## Ask the Site 0.13.1

- **The pet stays as it is when pressed, and the search box opens beside it** (to its left; to its right when it
  was moved to the left side), instead of the pet shrinking onto the box. Embedded, the frame makes room for both.
- The module's "Pet" setting is always shown (it applies to the floating button only, as its description says).
- Pages beside an answer that sit side by side (narrower windows) fill their rows: with two columns, 3 becomes 4,
  so no page is left alone on the last row.
- Fix: "0 more" was shown when every page was already listed; the line is hidden then.
- Fix: the Appearance page's preview said "N more" in English.

## Ask the Site 0.13.0

- **Pets have two pictures for each moment**: the whole pet, and a square thumbnail (a close-up). The whole pet is
  the floating button and, while the conversation is open on a wide window (1360px and more), stands at its right;
  on narrower windows a rounded-square thumbnail takes its place in the head. The module's pet comes with its
  thumbnails (head and scarf, about 0.75 MB). The pet page uploads, shows and removes both for each moment, and a
  still thumbnail beside the still picture.
- **The site's pet answers in the conversation**: with a pet as the floating button, each answer shows its thumbnail
  instead of the spark, half as large again (48px, a rounded square): thinking while the answer is awaited, talking
  while it is written, glad when it is done from the site's pages (puzzled when nothing was found or it failed),
  then still. Agents' faces are thumbnails too, of the same size.
- **Agents' faces follow what they do**: thinking while their turn is awaited; talking for a moment when they reply;
  glad when done (a form sent), puzzled on an error; a report: talking while written, glad when ready, puzzled if it
  fails; a person's window: thinking while it opens, glad when someone is there, puzzled when it does not open. The
  floating button's pet follows agents too.

## Ask the Site 0.12.6

- **Pets: a square thumbnail** (optional; PNG, WebP, JPG, GIF or AVIF): shown in the pet library and as the small
  face of agents (their buttons, beside the name above the conversation); the still picture when there is none.
  The module's pet uses its still picture.
- Fix: the pet library's buttons no longer overlap; each pet's actions ("Copy it to change", Edit, Select) are in
  its card, and "Delete the pets ticked" is below the list, asking for a selection first.
- Fix: links escaped twice (`&amp;amp;`) on the pet library and the dashboard's filters.

## Ask the Site 0.12.5

- How many pages are listed beside an answer before the rest fold into "N more" is a setting: "Pages shown" of the
  module, and of each embed key (3 by default; 0 lists them all).

## Ask the Site 0.12.4

- **Three pages beside an answer**, the rest in one pill under them: a mark for the first few of the rest (the
  first character of each title) and how many there are ("12 more"); pressed, the whole list opens and stays in
  view, and "Show less" folds it again. The list no longer shortens itself to the answer's height.

## Ask the Site 0.12.3

- **A helper offered below an answer comes right under its text**, not under the list of pages beside it (an
  answer's text, rating and offer are one column now; on phones they come before the pages).
- **Agents have a face** (an agent's "Avatar", on its Conversation tab): a pet of the library (it plays its Thinking
  moment while the agent works; animated AVIF or APNG) or one picture (an APNG moves too). It shows on the offer,
  on the helpers listed ("You can also try") and the "Talk to a person" button, beside its name above the
  conversation, and in place of the spark when the agent answers.
- **The offer and what comes of it are one block**: asking for a person from an offer shows, in the same block,
  whether someone is there and the button to open the chat window again, instead of a second block below.
- **While an answer is written or an agent is busy, agents wait visibly**: their buttons turn grey and cannot be
  pressed.
- **Thinking**: the spark turns twice, stops, flashes, and turns again, until the answer starts (still for visitors
  who ask for less motion).

## Ask the Site 0.12.2

- **LiveChat agent: the whole conversation is passed on** (new choice "The whole conversation before", on by default
  for new agents): the visitor's questions and the answers before the handoff, newest kept (20 turns, 8,000
  characters at most), shown beside the chat or ticket in parts the chat window shows whole ("Conversation 1/2"…).
  The last thing said may only be "a person, please". Agents set up before keep what they had chosen; tick it on
  their LiveChat tab.
- The Agent, Embed and Pet pages are saved from Joomla's toolbar at the top (Save, Save & Close, Close), as other
  edit pages are; the buttons below the form are gone.

## Ask the Site 0.12.1

- Fix: the "Save & Close" button of the Agent, Embed and Pet pages showed its language key (JSAVE_AND_CLOSE) instead of
  its words; it now uses Joomla's own JTOOLBAR_SAVE.

## Ask the Site 0.12.0

The floating button as a pet (docs/pet-spec.md).

- **Pets page** (Ask the Site → Pets, for those who may change the options): the pet library. One pet comes with
  the module (nine moments, animated AVIF, about 1.4 MB in all), ready to use or to copy and change. A pet has a
  name, sizes for desktops and phones, a word for the first visit, how soon it sleeps, a picture for each moment
  (uploaded on the page: APNG, animated AVIF, WebP or GIF, checked by their first bytes, kept in
  `images/mcpask/pets/`, so the media manager's own file types do not matter), how long the moments that play once
  last, and a still picture. Sizes are shown, larger than advised in red; "Try it" plays each moment in turn.
- **Chosen** by the module (floating button: "Button look") or by an embed key: the icon, the module's pet, or one of
  the library.
- **The moments**: idle; greeting when the pointer or the keyboard comes to it; picked up while dragged; thinking
  while an answer is awaited; talking while it comes in; glad when it is done or rated helpful; puzzled when nothing
  was found, something failed or it is rated unhelpful; asleep after a while with nothing done, waking at the first
  sign of the visitor. Moments without a picture use idle.
- **Shown**: closed, the pet is the button (no circle, no icon); open, it perches on the end of the search box; in
  the conversation, it takes the place of the dot in the head and does as the button's does. Two layers cross-fade
  between moments; what plays once is played from a copy kept in memory, so it starts from its first frame every
  time without being fetched again. Only idle is loaded at first, the rest when the page has time. Visitors who ask
  for less motion see the still picture, or the icon when there is none. A word beside it on the first visit, until
  closed or the pet is pressed. Embedded, the frame of the button grows to the pet's size.

## Ask the Site 0.11.0

Ask the Site on other websites and products (docs/embed-spec.md): one line of script in their pages.

- **Embeds page** (Ask the Site → Embeds, for those who may change the options): embed keys, each with the
  websites allowed (`https://…`, `https://*.example.com`; `http://` only for local addresses), the button's side,
  name and placeholder, the site's colours or one main colour, questions a day for the key, whether agents are
  offered, how visitors sign in, and questions asked today and in 30 days. The page of a key gives the line to
  copy, the public key, and the secret for the product's server (shown on request, renewable).
- **The line**: `<script src="…/media/mod_mcp_ask/js/embed.js" data-key="pk_…" async></script>` puts a frame of
  this site in a corner: the size of the button, of the open search box, or of the whole window for the
  conversation. The frame is served with `frame-ancestors` listing only the websites allowed, so browsers refuse it
  anywhere else; it shows nothing to a website not listed. What is asked and answered stays in the frame.
- **Where the visitor is**: the page's address and title go with each question, and its own scripts may add a few
  values (`McpAsk.setContext({ product: 'Billing', section: '…' })`). When the address is a page read from a peer
  site or website (a support site, say), the answer starts from that page, as the module does on this site.
- **Who the visitor is**, never taken from the other website's page unsigned:
  - a **sign-in button** above the conversation opens a small window of this site, which goes to the account
    centre (an External Login server, such as CAS) or this site's login page, comes back signed in, hands the frame
    a token for this key (8 hours), and closes; for websites without a sign-in of their own;
  - **tokens signed by the product's server** with the key's secret (JWT HS256: `aud` the public key, `email`,
    `name`, `exp` within an hour), given as `data-user` or `McpAsk.setUser()`; the e-mail must be an account here.
    Off by default.
  A known visitor is the request's user for that request only (questions logged to the member, the member's limits
  and access, names and e-mail addresses in forms, live chat and reports); nothing is kept in a session.
- **Links**: a source or report opened from the conversation changes the other website's page when it is one of
  its own, and opens a new tab otherwise.
- **Live chat**: asking for a person opens LiveChat on the other website's page (loaded there, or the one it has
  already), not in the frame; what happens there is logged as on this site. The page a handoff came from may be a
  page of the website the key is embedded in.
- `McpAsk.open()`, `McpAsk.close()` for the other website's scripts; the frame follows single-page applications'
  changes of address.
- The question log records the embed key each question came through.
- Fix: a link within the page in an answer or report (`#mcp-ask-handoff`) no longer reloads the page.

## Ask the Site 0.10.0 / MCP Server 0.9.3

Fourth stage of agents (docs/agents-spec.md): passing visitors on to people.

- **LiveChat agent** (`plg_mcpask_livechat`, new): passes the visitor on to people in LiveChat. LiveChat's window
  is loaded only when a visitor asks (it sets cookies of its own); a page that has LiveChat already (a template's
  own code, old or current) keeps it and is driven through the API it has, never loaded twice. The people answering
  see, beside the chat or ticket, what is chosen of: the visitor's last question, the AI's answer (shortened), the
  page, the analysis report's link; signed-in visitors' names and e-mail addresses are passed on too. Within office
  hours with someone online the visitor chats; otherwise they leave a message in the same window, which LiveChat
  keeps as a ticket. The conversation of the site closes as LiveChat's window opens; the card left in it opens the
  window again.
- **Settings**: licence number and group, time zone, office hours (rows of days and times; none for always), days
  closed (holidays), what is passed on, and the words shown when someone is there or not.
- **Ways in**: as any agent (listed, trigger phrases such as "talk to a person", offered by the AI); a "Talk to a
  person" button at the head of the conversation; offered below the second answer in a row rated not helpful; and
  any link to `#mcp-ask-handoff` on the site, a report's closing button among them.
- **Handoffs log** (Agents → Handoffs, for those who may change the options): when, by which agent, from where,
  within office hours or not, whether someone was there, and what came of it (window opened, chat began, message
  left, window did not open), with totals, filters and CSV export (cells a spreadsheet would read as formulas are
  written as text). Only the question (300 characters at most), the page and whether there was a report are kept;
  no names or contact details; kept for a year. What the window does is told by the visitor's page with a key bound
  to that visitor, for a day; an outcome never goes back to an earlier one.
- **MCP tools** (plugin *MCP - MCP Ask*, Super-User-only): `list_agents`, `agent_stats` (offered, started,
  completed, left, the rate of completion, and how handoffs came out), `list_handoffs`, and `list_agent_reports`
  (type, scores, section titles, whether e-mailed; never the visitor's name or the report's private address).
- An agent may be done in its first turn; its conversation is closed at once.
- Fix: a report action's address is no longer turned into "http://…" when it is a link within the page.

## Ask the Site 0.9.0

Third stage of agents (docs/agents-spec.md): analysis reports, written for the visitor once an agent is done.

- **Report settings** (an agent's "Analysis report" tab): the title ({type} for the result), an introduction, the
  result types (name, description, colour, picture) the AI chooses from, as in a personality test, the scores
  (0–100, shown as bars), and the sections written in turn, each with what it is for, its length, whether it
  recommends what the site offers, and its picture (none, the type's, or made by AI from a prompt). Also the
  categories whose indexed pages it draws on (with subcategories; empty for the whole site), buttons at the end,
  pictures made a day, days kept, and whether the link is e-mailed. The defaults were a sample assessment in
  Traditional Chinese (removed in 0.14.0).
- **Written as it is read**: the type and scores first, then each section streamed into the conversation through
  the same filter as answers (only the site's hosts, citations of the pages found), pictures last so the text is
  never held up by one being drawn. Only the pages the report cites are listed, under the numbers it cites them
  by. The conversation follows the report as it grows unless the visitor has scrolled up. A report already being
  written (another tab, a reload) is followed rather than written twice; one that fails goes back to waiting.
- **Pictures**: through another image plugin when one is installed and set up, otherwise with the component's own
  image settings (OpenAI or Open WebUI; a plugin of its own since 0.14.0). No name,
  e-mail address or other personal detail goes into a prompt; the same prompt is drawn once and kept, and a
  site-wide daily cap applies, past which the type's own picture is used. Pictures are WebP (or JPEG), 1200 wide,
  in images/mcpask/reports.
- **Its own address**: a private page (a random 48-character key, kept only as a hash; not indexed, no referrer)
  with the type, scores, sections with pictures, buttons, the cited pages and a print button, in the module's
  colours; kept for the days set, then deleted. What was answered is kept encrypted only until the report is
  written.
- **E-mail**: the link is sent to the address given in the form (RSForm agent: the first e-mail field), in the
  site's language, with the visitor's name; phone numbers and street addresses never reach the report.
- Fix: lists in reports keep their numbers when paragraphs come between items; indented lines stay with the item
  above them.

## Ask the Site 0.8.0

First stage of agents (docs/agents-spec.md, stages 1 and 2): helpers that take over the conversation to get one
thing done, offered by plugins of the new "mcpask" group.

- **Agents page** (Ask the Site → Agents, for those who may change the options): set up agents of the kinds the
  installed plugins offer, each with its opening and closing words, triggers, languages, access and a daily limit
  per visitor, and see how often each was offered, started and completed in the last 30 days.
- **Three ways in**: listed with the search box and above the question field ("You can also try"); offered below
  an answer when the question means much the same as one of its trigger phrases (the question's embedding is
  reused, so this costs nothing more); or offered when the answering model judges it fits (a tool it may call,
  for agents described to it). The offer is a card below the answer.
- **The conversation**: a bar names the agent in charge, with a button to leave; what the visitor writes goes to
  the agent until it is done or left. Agents show cards the browser draws from data (text, fields, suggested
  replies, a summary to confirm, buttons, done, error), never markup of their own. What an agent keeps between
  turns stays on the server, encrypted with a key made of the site's secret and the visitor's own random key,
  bound to the visitor, for a day at most, and emptied as soon as the conversation is done or left. Every turn
  passes the checks a question does (same-site JSON, access, permission, blocked addresses, probes before any AI
  service call); field values are checked for attacks without being refused for being a date or a number range.
  A turn that fails leaves the cards before it usable.
- **RSForm! Pro agent** (`plg_mcpask_rsform`, new): fills in a form in conversation, a page at a time (cut at the
  form's page breaks), with the form's own texts. Visitors may fill in the card or answer in their own words: the
  AI service puts into the page's fields only values the fields take (options by value or label, dates, e-mail
  addresses) and says what is still missing; a complete page moves on by itself. Two or three suggested replies
  per page, never for names, e-mail addresses or phone numbers. A summary with buttons to change each page; only
  "Confirm and send" sends it, after every page is checked again. It is stored as RSForm stores a submission, with
  RSForm's events and e-mails; the form's captcha, its own PHP scripts and its mappings are not run.
- Fix: the module's suggestions box is as wide as its content where the module sits in a narrow place.

## Ask the Site 0.7.3

- **Appearance page** (Ask the Site → Appearance, for those who may change the options): describe the look in a
  sentence and the AI service proposes the module's colours (18 named colours: main and second colours, text on
  them, background, tints, body text, secondary text, headings, links, code, table headers, tip, warning and
  error boxes, the "new conversation" button). A live preview runs the module's own styles and script with a
  canned answer holding every kind of content; colours can also be chosen one by one, saved, reverted, or reset
  to the module's own. What the service returns is kept only as six-digit colours of those names; text that
  would be hard to read (WCAG contrast 4.5:1, 3:1 for button icons and box colours) is darkened or lightened.
  The description is passed as data and cannot change the rules.
- **The module uses the site theme** (option "Colours": site theme or its own); the accent colour of a module
  still applies to it. The theme is CSS custom properties only, added after the module's styles.
- **Colours that were fixed are now variables**: text on the accent, headings, links, code, table headers, tip,
  warning and error colours.
- **Screenshots open in a lightbox** over the conversation, with their description, the other screenshots of the
  answer a click or an arrow key away, closed by Escape or a click outside (Escape no longer closes the
  conversation behind). A template rule that enlarges images on hover no longer reaches them.
- **Wide layout**: twice the space between the sources and the edge of the window (32px) and between the answer
  and the sources (40px).
- The conversation's header is protected from a template's styles for `<header>` elements.

## Ask the Site 0.7.2

- **Source cards name this site too.** A page of the site itself is shown with the site's name (Global
  Configuration → Site Name) and its section, as pages of related sites already were, instead of the start of
  its address (often just "/tw/").

## Ask the Site 0.7.1

- **Browsers load the new script at once after an update.** The module's script and styles are now
  versioned with the module (`ask.js?0.7.1`) instead of Joomla's media version, which an update of the
  module alone does not always change: with a cached 0.6.6 script, images in answers showed as
  `![](/images/…)` and numbered steps all started at 1. The build checks that the two versions agree.

## Ask the Site 0.7.0

Answers with the site's own screenshots, tables and callouts.

- **Pages indexed as light Markdown.** Numbered lists keep their numbers, tables their rows, bold its stars;
  embeddings are made from the words only, so the search is unchanged. The scheduled task looks at every page
  again once and embeds only those whose passages come out different.
- **Images of the site's own pages** are kept in their passages as `![description](/path)`: only files that
  exist in the site's folder, with an image extension, given as a path, an address of the site itself
  (remembered from the last request for runs from the command line), never with a query, `..`, another host,
  `data:` or a script. Pages of other websites and of related sites keep no images.
- **In answers**, the model may show a step's screenshot right after the step (also on how-to questions with a
  template saved before this version), a Markdown table, and a tip, note or warning box (`> [!TIP]`, also
  `[!提示]`, `[!注意]`). The output filter lets an image through only when that exact path stands in the pages
  the answer was written from; any other image is removed without a trace (`removed: image`). An image or
  link is no longer cut in two by the sentence buffer, and the digits of an image path are not taken for a
  phone number.
- **Rendering**: images as paths under this site only (checked again in the browser), shown smaller and whole
  on a tap, removed when they fail to load; a screenshot under a numbered step stays in that step, and
  numbering goes on after it; tables scroll sideways on a phone; callouts in their colours, labelled in the
  visitor's language. Still no HTML from the model.
- Tests: `tests/ChunkerTest.php` (16 cases of images kept and refused), six image cases in
  `tests/OutputFilterTest.php`.

## Ask the Site 0.6.6

First stage of step-by-step guides (docs/guides-spec.md, stage 1).

- **How-to answers.** Questions of how to do something (how-to words, editable) are answered by a template
  given to the model as plain text, so any model reads it (options: "How-to answers"): what it achieves,
  prerequisites, the steps in the order of the pages, numbered and cited, how to check, nothing made up.
- **Guides are given whole, in order.** A page with step headings ("步驟 1：", "Step 1:") is a guide; when any
  of its passages is found, all of them take its place in reading order (two guides, twelve passages at most),
  so an answer never starts at step 3 or puts steps out of turn. Any article written that way counts, made by
  hand or by the coming guides component.
- Citation groups wrapped in an extra pair of brackets, [[1][2]], no longer leave stray brackets.

## Ask the Site 0.6.5 / MCP Server 0.9.2

Hardening against prompt injection, after a visitor got the answer to repeat a marker they asked for.

- **Output filter** (new, `OutputFilter`): answers are held back a sentence at a time and filtered before
  the visitor sees anything. Removed: links to sites that are neither allowed (new option "Sites answers may
  link to") nor among the pages the answer was written from; phone numbers, e-mail addresses and LINE IDs
  the pages do not give; and sentences repeating text a visitor asked to have included (for questions marked
  as injection attempts, any 8 characters or more the pages do not hold; otherwise codes and markers such as
  RD-AIPT-K0). When nothing is left, a fixed polite refusal.
- **Prompt**: the question is marked off as `<user_question>` data; new rules not to repeat requested text,
  not to reveal or change the instructions, no contact details or promises the pages do not give, and a fixed
  refusal for questions that are only injection.
- **Rendering**: links in answers are clickable only over https to this site or the allowed sites, with
  `rel="noopener noreferrer nofollow"`; citations also in full-width brackets; a citation number with no page
  behind it is dropped (the widget had cut the list of pages at 12, so [13] and above showed raw).
- **Marks in the question log**: `injection_suspect` (new option "Phrases of injection attempts", with a list
  that comes with it), `html_payload`, `output_filtered`; shown as badges, filterable on the dashboard, with
  the answer as shown and as written before filtering. In the MCP tools: `flags`, the answer, a `flag` filter,
  counts per mark in `ask_stats` and `ask_overview`, flagged questions in the overview and pending list.
- **The ask icon** is now a speech bubble with two glowing stars instead of a magnifier.
- Tests: `tests/OutputFilterTest.php` (no Joomla needed) and `tests/acceptance.php` (T1–T9 against a site).

## Ask the Site 0.6.4

- **The on-screen keyboard no longer covers the search box or the conversation on iOS.** iOS lays its
  keyboard over the page without making the window smaller, so what is fixed to the bottom of the window
  ended up underneath it. The module now follows the visual viewport: while a keyboard is up, the open
  floating search box sits just above it (wherever the button was moved to), and the conversation keeps to
  the part of the window still seen, with the question field right above the keyboard.

## Ask the Site 0.6.3 / MCP Server 0.9.1

Security release after a full review; see SECURITY-AUDIT.md, "Review of 0.6.2". In short: requests
to listed websites and peers can no longer be steered into the server's own network; visitor
addresses are told apart correctly (IPv4 in IPv6 form, trusted proxies, CIDR in address lists);
hidden field text, FLEXIcontent type access and parent categories are respected; crawling and peer
syncing are bounded; finer back-end permissions. New option: Limits › Trusted proxies.

## Ask the Site 0.6.2

- The backup AI service has its own API format ("Backup answer API"), so Open WebUI on Chat Completions can
  fall back to OpenAI on the Responses API. When the main service fails part-way through a conversation with
  tools (before anything was shown), the backup starts the conversation over in its own format, since the
  rounds so far are written in the other one.

## Ask the Site 0.6.1

- **Backup AI service** (Options › AI service): when the main service is down (HTTP 5xx, 429, a timeout, no
  connection), for example an Open WebUI that sometimes answers 500, the answer is written by another,
  such as OpenAI itself, with its own address, key and chat model. It never steps in once part of an answer
  has been shown (the visitor would see it start over), and a refused request (a wrong key, an unknown model)
  is not hidden behind it. After a failure everyone goes straight to the backup for a minute instead of each
  waiting for the main service to fail. Embeddings use the backup only when allowed, which is right only when
  it offers the very same embedding model. The question log notes each answer the backup gave, and the
  dashboard shows when it last had to.

## Ask the Site 0.6.0

- **Visitors rate answers.** Each finished answer has 👍 / 👎; 👎 asks, optionally, what was missing or
  wrong. Ratings are kept in the site's own question log (no other extension needed), shown next to the
  questions on the dashboard, and remembered in the visitor's conversation across pages. A rating is
  accepted only with a reference signed by the site for that very answer, within a day.
- **Answer quality page** (Components › Ask the Site › Answer quality): how answers were rated over the
  last 30 days, and the questions to look at: rated not helpful, nothing found, or failed, the same
  question once, the worst first, with what visitors said.
- **Source rules.** For a question, choose the indexed pages it should be answered from (this site, peer
  sites, listed websites) and optionally a note. A visitor's question that means much the same, by
  embedding, so other wording counts too, is answered with those pages first and the note as the site
  owner's direction (followed, not quoted). A rule can hold other wordings of its question, and applies
  from a closeness of 0.5 by default (Options › Answers), measured: the same question in other words
  0.54 to 0.87, another product's 0.45, unrelated below 0.3. Pages no longer public are left out. "Try the
  question" shows the full answer with the rules as saved. Rules count how often they were used; the
  scheduled task embeds them again when the embedding model changes.

## Ask the Site 0.5.3

- **EasyBlog posts are indexed when they are saved.** A new plugin, "EasyBlog - Ask the Site", listens
  where EasyBlog announces saving, deleting and publishing or unpublishing from the list of posts (EasyBlog
  only loads its own plugin group then). A saved post is indexed again at once; one that is unpublished,
  trashed, deleted or no longer public leaves the index. It is switched on when first installed, also by
  an upgrade; plugins an administrator switched off stay off.
- **The scheduled task passes over what has not changed.** Indexed pages whose modified date is not later
  than the last time they were looked at are not read at all, unless the index settings changed since
  (then everything is looked at once) or they were last looked at more than 30 days ago (what a page shows
  can change without its date). The task log counts them as "passed over". Changed pages are still only
  embedded again when their text, title, address, language or access really changed.

## Ask the Site 0.5.2

- **Live progress for websites.** The dashboard's website card shows each site with a progress bar
  (pages handled of the pages to read), its state (not read yet, reading, to be continued, up to date,
  error), how it is read (RSS/Atom, sitemap, following links), pages indexed, when it was last read and
  why pages were passed over. Progress is stored, so it is there after a reload, on another computer,
  and while the scheduled task reads; the card checks every 5 seconds while something is under way.
- **A reading started from the dashboard carries on after a reload**, until everything is read or
  "Pause" is pressed.
- **One reading at a time.** The button and the scheduled task no longer read the same pages side by
  side: whichever comes second waits.

## Ask the Site 0.5.1

Found by reading seven real websites in one go:

- **Websites take turns.** Each run starts with the website read longest ago and gives each at least
  12 seconds, so one large site no longer keeps the others waiting at "0 / 0".
- **Less is read again on every run.** A crawl under way continues straight where it stopped, and the
  pages a feed or sitemap lists are reused for six hours instead of reading the list each time.
- **ASP.NET Web Forms pages** wrap the whole page in one form, which was removed as a form along with all
  the text ("no text"). A form holding most of the page is now unwrapped; ordinary forms still go.
- **Sitemap indexes** are read newest first, until there are enough pages, and their category, tag and
  author sitemaps are skipped: with WordPress (Yoast) the newest posts are in the last of many sitemaps.
- **An address that redirects to another host** (edu.example.com to www.example.com) is read where it
  leads, never to a private or reserved address.
- **A section only**: a website given as https://example.com/blog/ is crawled within /blog/.
- The dashboard shows why pages were passed over last time (HTTP 403, no text, robots.txt, no connection).

## Ask the Site 0.5.0

- **Who may ask, and how often, is set with Joomla's ACL.** Two new permissions on the component's
  Permissions tab: "Ask questions" (allowed for Public on install and upgrade, so nothing changes until an
  administrator says otherwise) and "Ask without limits" (Super Users always). The module is not shown to a
  visitor who may not ask; one who tries anyway is told to log in, or that the account may not ask.
- **Limits by user group** (Options › Limits): per-minute and per-day limits for chosen groups, for example
  more for signed-in members. The most generous limit of a visitor's groups applies, 0 meaning none; other
  groups keep the general limits. Signed-in users are counted by account (when who asked is recorded), so
  colleagues behind one office address no longer share a limit.
- **Websites without a feed or sitemap.** A listed website can now be given by its home page: its sitemap is
  looked for (robots.txt, `/sitemap.xml`, WordPress, JSitemap, OSMap), and when there is none its links are
  followed breadth first, to a crawl depth (3 by default) and the page limit. A reading may take several runs
  and resumes where it stopped; once through, pages no longer reached are removed, and the site is read again
  a day later. Sign-in, account, basket, search and file links are skipped, as are pages behind a login (401,
  403, a password field), `noindex` pages (whose links are still followed) and `nofollow` links; listing pages
  are followed but not answered from; tracking parameters are dropped and canonical addresses used.
- The crawler identifies itself as `Mozilla/5.0 (compatible; AskTheSite/0.4; +<site>)`.

## Ask the Site 0.4.0

- **EasyBlog posts are answered from** when EasyBlog is installed (Options › Content › "Include EasyBlog
  posts", on by default). Only posts everyone may read: published and not trashed or archived, within their
  dates, public, without a password or "log in to read", in a published public category, and for team blogs
  a published team open to everyone. Addresses come from EasyBlog's own router.
- The component now indexes several kinds of the site's own content side by side. The synchronise button
  works through them in turn, the scheduled task gives each its share of the time, and pruning keeps the
  pages of every source that is still answered from (switching EasyBlog off removes its posts).
- Not yet: EasyBlog posts are not re-indexed the moment they are saved (the scheduled task picks them up),
  and they are not shared with peer sites.

## Ask the Site 0.3.1

- **The floating button can be moved.** Drag it anywhere; on release it settles on the nearer side of the
  window at the height it was left, and opens towards the middle. The spot is kept in a cookie
  (`mcp_ask_float`, one year, this site only), which the module also reads, so later pages arrive with the
  button already there. A tap still opens it; moving only starts after a few pixels.

## Ask the Site 0.3.0

- **Websites by feed or sitemap.** Options › Other sites › "Websites by feed or sitemap": name, the
  address of an RSS/Atom feed or `sitemap.xml` (sitemap indexes too), an optional content selector and
  a page limit. Pages are read politely (same site only, robots.txt, a pause between requests), the main
  text is found by itself, and only new or changed pages are embedded again. Answers cite them with the
  site's name. A dashboard card shows each website's pages, last read and errors, with a "Read websites
  now" button; the scheduled task keeps them up to date.
- Excerpts from other sites are now marked as such for the model, so it can say where a point comes from.

