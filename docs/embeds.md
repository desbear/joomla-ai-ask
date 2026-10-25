# Embeds

Put AI Ask on another website or web application with one line of script. Set up an embed key under **AI Ask → Embeds**; its page gives the line to copy:

```html
<script src="https://your-joomla-site/media/mod_mcp_ask/js/embed.js?v=0.20.7-1761300000" data-key="pk_…" async></script>
```

The `v` is the script's version (the module's version and when the file last changed). Browsers keep the file
long; a page holding an older copy is told the current version by the frame and takes it, then remembers it for
the website's next pages, so a line written once keeps working as AI Ask is updated (0.20.7). Copying the line
again after an update saves that first step.

The script puts a frame of your Joomla site in a corner of the page: the size of the button, of the open search box, or of the whole window for the conversation. What is asked and answered stays in the frame; the other website cannot read it.

## Settings of a key

- **Websites allowed**: one origin a line (`https://app.example.com`, `https://*.example.com`; `http://` only for local addresses). The frame is served with a `frame-ancestors` policy listing only these, so browsers refuse it anywhere else.
- **Look**: the button's side, name and placeholder, colours, a pet, how many sources are shown.
- **Use**: questions a day for the key, whether agents are offered.
- **Visitors**: a sign-in button, and tokens signed by the other website's server (see below).

## Where the visitor is

Visitors may drag the button anywhere on the page; where they leave it (its side and height) is kept for the website in the browser. `data-size` sets the pet's size in pixels on this website.

The page's address and title go with each question. The page's own scripts may add a few values:

```js
McpAsk.setContext({ product: 'Billing', section: 'Invoices' });
McpAsk.open();
McpAsk.close();
```

When the address is a page AI Ask reads from a peer site or website, the answer starts from that page.

## Who the visitor is

What the other website's page says about who the visitor is never counts unless it is signed:

- **Sign-in button**: a small window of your Joomla site, which may go straight to an External Login server (CAS single sign-on, say) or the site's login page, hands the frame a token for the key (8 hours) and closes. For websites without a sign-in of their own.
- **Tokens signed by the other website's server** (off by default): a JWT signed HS256 with the key's secret, with `aud` the public key, `email`, `name`, `exp` within an hour, given as `data-user` or `McpAsk.setUser(token)`. The e-mail address must belong to an account on the Joomla site.

- **Accounts for members of the other website** (off by default, with signed tokens): when a correctly signed token gives an e-mail address with no account on this site, one is made with that e-mail and name, in the group the key gives (or the group of new users), with a password nobody knows; nothing is mailed. For websites that share their members with this one, the same single sign-on, say.

A known visitor is the request's user for that request only: questions are logged to the account, its limits and access apply, and agents fill in names and e-mail addresses.

## Another Joomla site with AI Ask

A Joomla site that has AI Ask itself can show this site's AI Ask without writing any code: in its **AI Ask** module, choose the mode **Another site's AI Ask (embedded)** and give this site's address, the key and the secret. The module writes the embed script, and for each signed-in visitor signs a token on its server (valid an hour; the secret is never written into the page). Turn on **Tokens signed by the other website** for the key, and **Make accounts for new members** when its members have no account here yet. Answers, agents and chats with people are then this site's, and the people who answer see the other site's signed-in visitors by name.

## Signing in through the other website on this site's own pages

A site whose AI Ask is shown on another website may let its own visitors sign in through that website too
(0.20.4), for AI Ask only. On the key, with **Tokens signed by the other website** on, turn on **Sign in through
this website** and give its **Sign-in address there**: on a Joomla site, the module of mode *Another site's AI Ask*
answers at `index.php?option=com_mcpask&task=embed.signin&module=` followed by that module's ID.

The conversation's Sign in button then goes there with the page's address and a one-time state. That website signs
its visitor in (its own sign-in, its single sign-on) and sends them back to that page, and only to a page of the
website its module names, with a token for the key in the address's fragment (which no server sees). The page takes
the token only with the state it gave, removes it from the address, and opens the conversation again. The token is
kept for that browser tab, an hour at most, and sent with AI Ask's requests (`X-Mcp-Ask-Signin`,
`X-Mcp-Ask-Identity`); this site's own sign-in is not affected, and Sign out forgets it.

## Chats with people, and showing the screen

A chat with a person in AI Ask Desk works in the frame as on the site: messages, translation, files, ratings. Links to
pages of another website (an article of your Joomla site, say) open in a new tab, and the chat stays open.

The frame may capture the page for the person answering, when the visitor chooses to: a screenshot to mark up, or a
recording of the tab (see docs/askdesk.md). It is allowed to (`allow="clipboard-write; display-capture"`), and the
page puts it aside meanwhile: out of sight for a screenshot, shrunk to the bar with **Stop** for a recording, so the
page can be used. What is captured is the whole tab: the other website's page, as the visitor sees it.

- A frame of your own instead of `embed.js` needs `allow="display-capture"`, and takes part in putting itself aside
  only through `embed.js`; without it, visitors can still mark up a picture of their own.
- A `Permissions-Policy` header of the other website that leaves out `display-capture` turns it off there too.
- The browser asks the visitor which tab to share, naming your Joomla site; phones' browsers cannot capture at all.

---

**繁體中文**：在「AI 問答 → 嵌入」建立嵌入金鑰，把一行程式碼貼到其他網站，角落就會出現問答按鈕（本站的 iframe，只允許設定的網站，瀏覽器會拒絕其他網站）。對方網頁可以用 `McpAsk.setContext()` 帶入所在功能。身分只接受兩種：本站小視窗登入後發的憑證，或對方伺服器用金鑰密鑰簽的 JWT；email 必須是本站已有的帳號。與真人客服（AI Ask Desk）的對話在嵌入時同樣可用；訪客可截圖標註或錄製分頁畫面給客服看（iframe 已允許 `display-capture`，截圖時 iframe 會暫時隱藏、錄影時縮成底部的停止列）。自行撰寫 iframe 的網站需加上 `allow="display-capture"`；手機瀏覽器無法截取畫面。
