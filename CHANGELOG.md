# Changelog

Until 0.13.2 this extension was called "Ask the Site" and lived in the MCP Server for Joomla repository.

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

