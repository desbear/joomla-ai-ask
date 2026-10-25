# AI Ask Desk

A lean live chat for AI Ask. Visitors chat with people in AI Ask's own window, where they were already asking
questions; what they asked the AI and the page they were on go to the person answering. Each side writes in its own
language: AI translates every message both ways, and each side can see the words first written.

It is a package of its own, `pkg_askdesk`, which needs AI Ask 0.20.0 or later:

- `com_askdesk`: the console the people answering use, its options, and the chats.
- `plg_mcpask_askdesk`: an agent kind for AI Ask, **AI Ask Desk live chat**.

## Setting it up

1. Install `pkg_askdesk` (after AI Ask).
2. **AI Ask → Agents → New**, kind **AI Ask Desk live chat**: office hours, days closed, and what visitors are offered
   when no one is there (a message to leave, other agents, or both). The first agent that passes visitors on to
   people is the one behind **Talk to a person**.
3. **AI Ask Desk → People answering**: find members and **make them people answering**; only they can open the
   console. For each, the name visitors see (their account's unless set) and their face, a pet of AI Ask's library or
   one picture; otherwise the first letter of their name. They can change these themselves in the console (their face
   at the top right). The same page shows each person's chats, messages answered by e-mail, how long visitors waited
   for them, and their ratings, over 7, 30, 90 or 365 days.
4. The people answering sign in on the site and open the console:
   `index.php?option=com_askdesk&view=console`, or a menu item **AI Ask Desk → Console**. It works on a phone.
5. **Options**: **Alerts** (e-mails about messages left and chats ended unanswered), whether staff see others'
   cases (**Desk**), ratings, privacy. **Permissions**: who designs the dashboard template, and who sees everyone's
   cases whatever that setting says (Super Users have both).

## How a chat goes

- **Guests say who they are first** (on by default, in the agent's settings): a name to be called by and an e-mail,
  required, and a company, optional; signed-in visitors start at once. What a guest gave fills in the form the next
  time in the same session (not stored for it). The same fields come with a message left.
- **Talk to a person** (or an offer below an answer that did not help) starts the agent. Within office hours, with
  someone online in the console, a chat starts at once in the same window; the visitor can write while waiting.
- Chats wait in every console until someone **takes** one: only one person gets it. Others see who answers it.
- Every message is translated when it is sent: the visitor's into the language of the person answering (their own
  choice in the console, or the desk's), theirs into the visitor's, as their own words tell it. The glossary keeps
  product names as they are, or translates terms always the same way. When a translation fails the message goes as
  written, and the person answering is told.
- The visitor's page asks for news every two seconds while the chat is in view, less often when not; the console
  every two seconds while in view. A new chat or a visitor's message rings, and shows a notification when allowed.
- A chat ends when either side ends it, or when the visitor's page has not been heard from for the minutes set.
- Outside office hours, or with no one online: the visitor leaves their e-mail and a message, which waits under
  **Messages left** in the console, translated, for an answer by e-mail. Or other agents are offered, or both.
  The people answering are told by e-mail too (below).
- Every handoff is in AI Ask's log of handoffs, as LiveChat's are.

## Beside the chat

- **Suggested replies**: for each new message of the visitor, AI Ask searches its index (the site's pages, and the
  documents it reads) with the visitor's words in the language of the pages, then has its AI judge strictly, of 100,
  how well each of the closest pages answers the latest message: sharing a subject is not answering it. Only pages
  of 75 or more are shown, with a reply drafted from that page alone in the language of the person answering, and
  the page to open; nothing is shown when nothing answers it. **Use this reply** puts the reply and the page's address
  into the box, to change before sending (it is then translated as any message). Two AI requests per message (an
  embedding, one judgement), worked out once and kept with the message.
- **The visitor's history**, for signed-in visitors only: their earlier chats here (to open and read), and the
  questions they asked AI Ask, with whether the AI answered and how they rated it (as long as AI Ask's log keeps
  questions and records who asked). Guests have none.
- **Panels from plugins** (0.2.0): a plugin of the `askdesk` group can add its own section at the top, such as
  who the visitor is in a CRM. It answers the `onAskdeskPanels` event with an object implementing
  `Gss\Component\Askdesk\Administrator\Desk\PanelInterface`: `view()` says what to show for a chat (a title,
  fields, labels, an https link, a search box and results to choose from), `act()` handles a search, a choice or a
  refresh. A panel may also carry a note (0.2.1): drafted by AI from the chat with `Summary::write()`, changed
  by the person answering and sent only when they confirm. And a brief (0.4.0): parts with points and their
  sources, an AI's of the visitor, say; a panel can have the console run an action once on opening (`follow`),
  so slow work runs after it is drawn, choose its icon, and return nothing for a chat it has nothing for. With
  `look: "ai"` (0.4.1) it is drawn as a blue-violet card of its own above the others, read again from beside its
  heading. A
  panel may carry a small form (0.6.1): fields of a few kinds, drafted by AI from the chat on asking
  (`Summary::fill()`) and sent only on its button, a to-do drawn out of the chat into another system, say;
  and buttons of its own, and links on its list's items. The
  console draws it all as text; whatever the plugin calls stays on the server.
- On a narrower screen these open over the chat with **Reference**.

## The list and closing cases (0.6.0)

- **Three tabs**: **All** that is not closed yet (waiting, answered, messages left, chats ended unanswered, and
  one's own chats ended in the last hours); **To take** (the visitor wrote last and no one answered, or
  the chat waits to be taken; the longest waiting first); **To answer** (messages left, and chats that ended by
  themselves with the visitor's words unanswered). Each has its count; a tab shows 30 chats and 30 more as its
  end comes into view. Under the tabs, a menu orders the list (latest activity, untouched longest, latest
  started) and, in All, a switch shows those closed too.
- **Closing (結案)**: an unhandled chat stays until someone closes it, with the button in the chat or at the row's
  end in To answer; a message left is closed when marked dealt with. A chat ended by the one answering, or ended
  with the visitor answered, is closed with it. Each chat keeps who wrote last, and when and by whom it was closed.
- What rings, and the count in the window's title, follow what goes on now whatever the tab.
- **Whose cases**: with **Staff see others' cases** off (Options → Desk), the people answering see only the chats
  they took and those no one has taken: in the list, its counts and what rings, opening a chat by its address,
  the dashboard's cases, and the e-mails. Those given **See everyone's cases** in Permissions (and Super Users)
  see them all.

## E-mail alerts (0.6.0)

- A message left, and a chat that ended unanswered (when set), are e-mailed to everyone answering or to the
  addresses set (Options → Alerts): who, what they said last, when, and a link that opens the chat in the
  console. In the language of the people answering.
- What is still unhandled after the hours set (4) is told once more, all together in one e-mail, each once.
- With others' cases not to be seen, a chat taken by someone is told to them only.

## The dashboard (0.5.0 – 0.6.0)

With no chat open, the console shows each person's dashboard: widgets on 12 columns, each one template of a
source drawn as a number, a battery, a pie or doughnut, bars, stacked bars, a line, an area, a gantt, a table
or an editable table. A widget's **Open** grows it into a full page with its settings (its name, how it is
shown, which fields; the kinds that cannot be drawn greyed out with why). Tables draw only the rows in view and
load the next 100 as the end comes near; charts and numbers sum the first 500 records.

- **Sources**: the desk's own **cases** (its lists as templates: all not closed, awaiting, unhandled, closed; a
  chat's visitor, company, state, last words, who answered, dates; a row opens the chat), and what plugins of the
  `askdesk` group give by answering the `onAskdeskSources` event with an object implementing
  `Gss\Component\Askdesk\Administrator\Desk\SourceInterface`: its templates, a template's fields (text, number,
  date, select or multi), its records a page at a time, and one value of a record changed, all read as the person
  looking. When they must sign in to the other system first, it throws `SignInNeeded` with where that starts. A
  record may carry `link` (its page in that system, for an **Edit there** button in the side card) and `room` (a
  chat of the desk's, opened in the console).
- **Narrowing**: by words; by person (everyone, only me, another of the people answering; a source asked for
  `mine` or `person`); by time (today, the last 7 or 30 days, this or last month, this year; asked for as `from`
  and `to`, days in the site's time zone).
- **Laying out**: **Edit layout** moves widgets by their heading and sizes them from their corner; **Overview**
  shows them all at once. Both are in the overview box at the screen's lower right, faint until pointed at, with
  a map of the board. Folded (as it starts), it is a thin bar in the right margin, a third of the board high,
  the part on screen in violet; the pointer thrown to that corner opens it, and 15 seconds away folds it again.
  The wheel there scrolls the board.
- **The dashboard template**: one given **Design the dashboard template** saves their dashboard as everyone's.
  Everyone has its widgets, tagged as the template's: moved and sized as they like, not removed or changed
  (the server puts them back as the template has them); their own widgets beside. The template changed,
  everyone's follows. It is kept as the board of no one (`user_id` 0 of `#__askdesk_boards`).

The dashboard is a React app in `board/`, built with Vite into `media/js/board.js` and `media/css/board.css`
(`npm install`, then `npm run build` in `board/`; `build/build.sh` builds it again when its sources are newer).
The console loads it the first time no chat is open, and gives it whether the person may design the template.

## On a phone, and notifications

The console works on a phone: the list, then a chat full screen, its side panel over it. It shows the
dashboard first, over the list, the list's edge showing at the left:
dragged from there (or the edge tapped), the list; the logo at the list's top, the dashboard again. Someone new
waiting, or a message left, nudges the dashboard open a little and back. On iPhone and iPad the screen's left
edge is the console's own: a swipe from it does not go back a page in Safari. It can be added to the
home screen as an app (Share → Add to Home Screen on an iPhone; Install on Android and on computers). The bell
in its bar turns on notifications for that device: pushed by the site itself (Web Push, VAPID), they come with the
console closed: someone new waiting (to everyone not away), a message in one's own chat (to the one answering it),
a message left (to everyone). A tap opens the console at that chat. On iPhone and iPad (iOS 16.4 or later) only
the app added to the home screen can be notified. The site needs HTTPS. Nothing else is set up: the desk makes its
keys the first time; the browsers subscribed are kept in their own table, and let go when the push service no
longer knows them.

## Links, search and typing

- **Links to the site's articles** that the people answering write or paste are made short (the article's number,
  nothing more), in the box as they paste and again when sent: `index.php?option=com_content&view=article&id=…`,
  the same with the option left out (`/tw/?view=article&id=…`), and addresses as the address bar shows them, whose
  last part is the article's number and alias, or an alias only one article has. Below the message the visitor sees a card for each
  article (up to three): its title and first words, translated with the message, and its first picture when the
  site has it. Only articles a guest may read; nothing is fetched from other websites.
- **AI search** at the top of the console's side panel, above the suggested replies: the people answering ask the site's pages themselves; the pages that answer
  (less strictly than suggestions) come with a short answer to put in the box.
- **Typing**: each side sees dots while the other writes (told with the requests the pages make anyway), and each
  message of the other side comes after a moment of dots. Messages pop in from their corner.

## Files

- **Pictures, videos and documents** can be sent by both sides: the paperclip beside the box, files dropped on the
  chat (five at a time), or a picture pasted into the box. Pictures are made smaller in the browser before they go
  (1920 pixels at most, WebP or JPEG, under 1 MB; GIFs as they are, for they may move): pictures (JPG, PNG,
  GIF, WebP) up to 1 MB, videos (MP4, MOV, WebM) and documents (PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP) up to
  10 MB. A file is checked for what it is, not only what it is called; kinds that could run in a page (SVG, HTML) are
  refused.
- Pictures open whole on a click, videos play where they are, documents only download and show their size. Every
  file says how many days it is kept: **30**, then it is deleted (and the chat says it is gone).
- Files are kept under random names in a folder that cannot be read directly, and given out only through the desk.
- **AI Ask Desk → Files** lists every file sent: who sent it (the visitor, or the person answering by name), in which
  chat, its size and the days left; each can be opened or deleted.

## Stickers

- **AI Ask Desk → Stickers**: small pictures to send in chats (PNG, GIF that may move, WebP, JPEG; 512 KB at most),
  added several at a time, in packs, each named (for screen readers and the PDF), for visitors and staff or for
  staff only, offered or not, in the order set.
- Both sides send them with the smiling button beside the box: the packs as tabs, the stickers in a grid. A sticker
  is a message of its own, shown without a bubble; it is not translated, and it does not ask for suggested replies.
  In the PDF it is written as its name.

## Showing the screen

- Beside the paperclip, visitors in a chat have **Show us your screen**:
  - **Screenshot and mark up** (computers): the browser asks what to share (this tab, another, a window); the chat
    window steps aside and a bar waits at the bottom: the visitor may scroll to what to show or go to another tab
    first, then **Take the picture** (or **Cancel**). The bar steps aside too as the picture is taken, and sharing
    stops at once. The picture opens in an editor over the
    page: select an area, then **pixelate** it so it cannot be read, or crop it; draw boxes, arrows, lines and words.
    **Done** sends it into the chat as a picture (WebP, or JPEG where the browser cannot write WebP; made smaller
    until under 1 MB) and the window comes back.
  - **Mark up a picture**: a picture of one's own (on a phone, its own screenshot), marked up the same way.
  - **Record the screen** (computers): in a small window of its own, so the recording goes on while the visitor
    changes pages (one made in the page would end with it). The visitor presses **Start recording**, chooses the tab
    (the window itself is not offered), shows what happens there, and comes back to **Stop**; the browser's own
    "stop sharing" works too. 60 seconds at most (720p). The recording is watched before it goes: **Send**, **Record
    again** or **Cancel**; sent from that window straight into the chat, which then closes. MP4 where the browser
    records it, otherwise WebM; under the 10 MB of a file. Where the browser blocks the window, the tab is recorded
    from the page itself, and the browser asks before the page is left.
- What is pixelated never leaves the browser: only the finished picture is sent. A recording cannot be pixelated, so
  only this tab is recorded, and only once the visitor chooses to.
- Phones' browsers cannot capture the screen: there, a picture is marked up, and a video recorded with the phone is
  sent with the paperclip.
- Embedded on another website (docs/embeds.md), the page around puts the frame aside the same way, and what is
  captured is that website's page. A frame without `embed.js`, or not allowed `display-capture`, offers only a picture
  of one's own to mark up.
- The editor is [Painterro](https://github.com/devforth/painterro) 1.2.92 (MIT), in `media/com_askdesk/vendor`,
  loaded only when used.
- Pictures and recordings are files of the chat like any other: kept 30 days, seen only in that chat. The person
  answering can keep one in the library (below).

## The library (Dropfiles)

- With Dropfiles installed, the people answering keep files to send again in a **File library** in the console's side
  panel: **Mine** and **Shared**. They add files there (by the same rules as in a chat) and send one into their chat
  with a click: it is copied into the chat as a file of the chat, kept 30 days; the library keeps its own.
- A file sent in a chat (a visitor's screenshot, say) can be kept in the library from the chat: the folder button
  beside it, then **Keep in my library** or **Keep in the shared library**. It is copied; the chat's goes after its
  days.
- The files are in Dropfiles, under a category of the desk's (made as **AI Ask Desk**, or one chosen under
  **Options → Library**), with a folder for each person answering and one shared, all for registered users only
  (Special): not shown on the site, and not read by AI Ask. They can be managed in Dropfiles too.

## The chat by e-mail

- When a chat someone answered ends, the visitor is sent it as a **PDF**, to the e-mail they gave (signed-in
  visitors: their account's), in the words they read: what they wrote, and what the people answering wrote as it was
  translated for them. Files are listed by name. On by default, under **Options → Privacy**.
- When the visitor wrote in another language than the page's, the PDF's and the e-mail's own words (title, who,
  files, system messages) are translated into theirs too, in one AI request; it is sent once the request that ended
  the chat has its answer, so ending a chat waits for nothing.
- The PDF uses the CJK fonts PDF readers bring with them (Traditional and Simplified Chinese, Japanese, Korean), so it
  stays small; text in other languages is set in Helvetica.

## Ending, rating, and the visitors waiting

- **Ending a chat** asks once more: in the console in a small window beside the button (in warning colours), in
  the visitor's window by pressing a second time.
- **AI reads the chat before it ends** (console): from the moment the button is pointed at, AI Ask's AI judges
  whether the chat looks done (a question of the visitor not answered, something promised and not done, the
  visitor's last words waiting for a reply). Done: the button to end turns from orange to the usual blue, with why;
  something left: it stays orange as **End anyway**, saying what is left. Advice only; one AI request, kept until a
  new message comes.
- **Rating**: when a chat someone answered ends (either side ending it), the visitor's window asks for a rating, the
  face of the person who answered on top; once sent, their pet bows to say thank you. What is rated is set under
  **Options → Rating chats**: up to 8 things, each with 1–5 stars, thumbs up or down, 0–10, or one of several
  choices, required or not, and a comment box. With nothing set, one overall rating with stars. The console shows
  the rating of a chat that ended; the dashboard the averages of the last 30 days and the latest comments.
  Ratings keep nothing of the visitor but their comment, for the days set; guests' ratings stay after their chat
  is deleted.
- **A pet that bows**: AI Ask's pets have a new moment, **Bowing thanks**. Only a pet that has it can be chosen
  for a person answering; with a picture as the face, a thank-you picture can be given beside it.
- **To take**: the tab of chats whose visitor wrote last and no one answered yet, the longest waiting
  first, with how long they have waited. Chats that ended unanswered are under **To answer** (above).

## What is kept

- **Closed chats (結案), archived then deleted (0.6.2)**: a chat closed longer ago than the days set (90 by default),
  a guest's too, is written out whole (the chat, its messages, its work cards, its rating, who handled it, and its
  files themselves) into the **archive folder**, marked as archived, and only then deleted from the site. One that
  cannot be written out stays and is tried again; one written out is never written out again. A daily scheduled
  task does it (**AI Ask Desk - Archive closed chats**, in System → Scheduled Tasks); the desk does it itself
  once a day when the task has not run. The dashboard says when it last ran and warns after two days without.
- **The archive folder**: a full path **outside the website** (inside it, nothing is archived), by default
  `askdesk-archive` beside the website's own folder. The site **only writes it and never reads it back**: there is
  no way in the desk to bring an archived chat back; that is done by hand. Have the site's backups take it (an
  Akeeba Backup profile that includes only this folder, encrypted as `.jps`, sent to Dropbox by its
  post-processing, on a schedule); once sent, it may be emptied. In it: `{year-month}/room-{id}/chat.json` (the
  chat as it was kept, `"format": "askdesk-archive/1"`), `{year-month}/room-{id}/files/…` and `archive.log` (a
  line for each chat archived, with the SHA-256 of its `chat.json`).
- **Not archived**: a guest's chat never taken up nor finished is deleted after a day. A chat ended unanswered,
  or a message left, stays until someone closes it.
- **Files**: given out for 30 days; kept until their chat is archived with them, then deleted with it.
- **Ratings and who handled what**: for the days set (365 by default).
- Nothing is recorded by IP address.

## Translating

- **Kept first, translated after**: a message is kept the moment it is sent, in the order written, and translated
  once the request that brought it has its answer (nothing waits for the AI). The console shows a visitor's words
  as written with **Translating…** until the translation takes their place; the visitor sees dots until the person
  answering's words come translated.
- **Not translated when it needs no translating**: the server tells the language of a message by its own letters
  (kana, Hangul, Thai, Traditional or Simplified Chinese by the characters only one of them writes, English by its
  common words, Vietnamese by its marks); a message surely in the reader's language (or Chinese that reads the same
  in both scripts, numbers, addresses) is not sent to the AI at all.
- **A model for translating, reasoning at its least**: Options → Desk → *Model for translating* (a small, quick one;
  empty for AI Ask's chat model) and *Reasoning while translating* (minimal by default: one model took 80 seconds to
  translate a message left to itself, 2 at minimal; *Off* for models that know it).
- **The browser's translation first** (Chrome on computers, nothing sent anywhere): where the browser has both
  languages already, a message shows its translation at once, marked as the browser's first translation, until the
  AI's comes. Each step of it is given a moment at most, so a browser that does not answer holds nothing up.


Translations use the AI service set up in AI Ask (its chat model): one short request per message, with the last
messages of the chat for sense. The visitor's message is data to translate: instructions in it are not followed.
