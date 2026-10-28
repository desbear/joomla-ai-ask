# AI Ask for Joomla

A search box for your visitors that answers their questions from your site's own content with AI, and names the pages each answer came from. It can also hand the conversation to helpers that get things done (fill in a form, write an analysis report, pass the visitor on to a person), show up on your other websites with one line of script, and turn its floating button into an animated pet.

With it, **AI Ask Desk**: a live chat where the people of your site take over in the same window, every message translated both ways, and a console with a dashboard of their cases.

> **Status: experimental.** AI Ask for Joomla 0.20.4 and AI Ask Desk 0.6.0 run on Joomla 4.4, 5 and 6 with the OpenAI service.

[繁體中文說明](#繁體中文)

## Two packages

| Package | What it is | Release tag |
|---|---|---|
| `pkg_mcpask` | **AI Ask for Joomla**: the module, the index, the answers, agents, embeds, pets | `v0.20.x` |
| `pkg_askdesk` | **AI Ask Desk**: live chat with people, its console, its dashboard (needs AI Ask 0.20.0 or later) | `askdesk-v0.x.y` |

Both are built from this repository and released separately: download each from its own [release](https://github.com/desbear/joomla-ai-ask/releases) (under **Assets**).

## AI Ask for Joomla: features

- **Module "AI Ask"** (`mod_mcp_ask`): an icon, a search box, or a button floating in a corner of the window, that turns into a full-screen conversation with the answer streamed in, numbered references and source cards. Choosing a source puts the conversation away and moves to that page; the conversation can be reopened there.
- **Component "AI Ask"** (`com_mcpask`): cuts published content into passages, stores an embedding for each in the site's own database, finds the passages closest to a question and streams the answer written from them. Dashboard with setup checks, index status, a "synchronise" button and the questions visitors asked.
- **Content**: Joomla articles; FLEXIcontent items when FLEXIcontent is installed (with text custom fields, or, for types whose text is in their template, read from their page); EasyBlog posts when EasyBlog is installed. Only what a visitor who is not signed in may read is indexed, and that is checked again before a passage is quoted.
- **Documents** (`plg_mcpask_dropfiles`, enable it when Dropfiles is installed): the public files of chosen Dropfiles categories (PDF, Word .docx, PowerPoint .pptx, Excel .xlsx, Markdown, text) are read on the server and indexed together with the site's pages, so every answer can draw on both. A cited document is shown as a file card (kind, page, size) that opens the PDF at that page or downloads the file. The Documents tab of Indexed pages shows whether each file is answered from and why not, reads one at once, and takes pasted text or Markdown for a file whose text cannot be read. Other plugins can add sources of their own with the `onMcpaskSources` event. PDFs are read with [PdfParser](https://github.com/smalot/pdfparser) (LGPL-3.0), bundled; scanned PDFs without a text layer have no text to read.
- **Several sites, one answer**: a site can read the index of other sites that run this extension and hold each other's export key, and answer from their public content as well. Embeddings are reused when both sites make them the same way, so reading another site costs nothing at the AI service.
- **Websites by feed, sitemap or crawl**: list other websites by their RSS or Atom feed, their sitemap, or just their home page; their pages are read (keeping to robots.txt), indexed and answered from, each source marked by its site.
- **Screenshots, tables and callouts in answers**: pages are indexed as light Markdown with the site's own images kept where they stand; an answer may show a step's screenshot, a table, or a tip or warning box. An image is shown only when it is a file of this site found in the pages the answer was written from.
- **Indexed pages**: every page the answers come from, with the passages the AI service is given of each; pages added by their address (one per line, each read on its own and again every day); pages ignored, singly or by address patterns, so that they are not answered from and do not come back.
- **Answer quality**: visitors rate answers with an optional reason; an Answer quality page lists the questions rated badly or not answered, and source rules point such questions to the pages that answer them.
- **Appearance described in words**: describe the look ("calm dark blue") and the AI service proposes the module's colours, shown in a live preview; text that would be hard to read is made readable (WCAG contrast).
- **Agents**: helpers that take over the conversation to get one thing done, offered by plugins of the `mcpask` group. Listed beside the search box, offered below answers to questions close to their trigger phrases, or offered when the AI judges they fit. See [docs/agents.md](docs/agents.md).
  - **RSForm! Pro** (`plg_mcpask_rsform`, shown only when RSForm! Pro is installed): fills in a form in conversation, a page at a time, and sends it as RSForm does. It may end with an **analysis report**: a result type and scores, sections streamed in, the pages it cites, a private address, and the link e-mailed to the visitor.
  - **LiveChat** (`plg_mcpask_livechat`): passes visitors on to people in LiveChat, with the conversation so far; a chat within office hours when someone is there, otherwise a message kept as a ticket, or other agents such as a contact form; every handoff is logged.
  - Agents pass visitors on to each other (**Referral**): when one cannot help just now, or when it is done.
  - Pictures made by AI for reports come from an image plugin, such as `plg_mcpask_imagegen` (OpenAI or Open WebUI); without one, the back end offers no such pictures.
- **Embeds**: AI Ask on other websites and products with one line of script (a frame of this site, allowed only on the websites listed), with the page the visitor is on, an optional sign-in in a small window, or a token signed by the other website's server. See [docs/embeds.md](docs/embeds.md).
- **Pets**: the floating button may be a pet whose moments (idle, greeting, picked up, thinking, talking, glad, puzzled, asleep, waking) follow the visitor and the answer, with a whole-body picture and a square thumbnail for each. No pet comes with the extension: make your own on the Pets page. See [docs/pets.md](docs/pets.md).
- **Cost control**: limits per visitor (per minute, per day), per user group and for the whole site; Joomla permissions for who may ask and who is not limited; a maximum answer length; no model call when nothing relevant is found; probes (markup, scripts, commands) are turned away before anything is sent to the AI service.
- **With [MCP Server for Joomla](https://github.com/desbear/joomla-mcp)** (optional): the plugin `MCP - AI Ask` gives AI assistants connected to your site tools to review the questions visitors asked, how answers were rated, source rules, the indexed pages (and to add or ignore pages), agents, handoffs and reports. Super-User-only.

## AI Ask Desk: features

The whole of it in [docs/askdesk.md](docs/askdesk.md).

- **Live chat in AI Ask's window**: **Talk to a person** (or an offer below an answer that did not help) hands the visitor to the people answering, with what they asked the AI and the page they were on. Office hours, days closed, and what is offered when no one is there (a message to leave, other agents).
- **Each side in its own language**: AI translates every message both ways (a glossary keeps names as they are); each side can see the words first written. Messages that need no translating are not sent to the AI; the browser's own translation shows first where it can.
- **The console** (works on a phone, can be installed as an app): the chats in three tabs (all not closed, awaiting an answer, unhandled), each with its count and loaded a page at a time, ordered as chosen, with those closed shown on a switch. Chats waiting are taken by one person; a new one rings and can notify the phone (Web Push).
- **Closing cases (結案)**: a chat that ends with the visitor's words unanswered, and a message left, stay until someone closes them; ended by the one answering, a chat is closed with it.
- **Beside the chat**: suggested replies from the site's own pages (judged strictly by AI, drafted in the language of the one answering), AI search of the site, the visitor's earlier chats and questions, and panels that plugins add (a CRM's view of the visitor, an AI's brief of them, a note to write back).
- **Files, stickers and the screen**: pictures, videos and documents both ways (checked for what they are, kept 30 days); sticker packs; visitors can send a screenshot they mark up (pixelate, crop, arrows, words) or record their tab; a file library (with Dropfiles) to send again.
- **After the chat**: the chat e-mailed to the visitor as a PDF, in their words; a rating of what is set (stars, thumbs, 0–10, choices, a comment); an AI check, before ending, whether something is left undone.
- **E-mail alerts**: a message left, or a chat ended unanswered, e-mailed to the people answering (or the addresses set) with a link that opens it; what is still unhandled after the hours set is told once more, together.
- **The dashboard**, when no chat is open: widgets on 12 columns (a number, a battery, pie and doughnut, bars, stacked bars, a line, an area, a gantt, a table, an editable table), moved and sized in place, all at once in an overview, found with a minimap at the screen's edge. Each shows one template of a source: the desk's own cases, or another system's records given by a plugin (`onAskdeskSources`). Narrowed by words, by person (everyone, only me, another) and by time (today, 7 or 30 days, this or last month, this year).
- **A dashboard template**: one given the permission saves their dashboard as everyone's; everyone keeps its widgets (moved and sized as they like, not removed) and adds their own.
- **Who sees what**: only the people made **people answering** open the console. A setting keeps each of them to their own chats and those no one has taken; two permissions of the desk's own (see everyone's cases, design the dashboard template) are given to groups in its Options.
- **What is kept**: guests' chats go an hour after they are closed; signed-in visitors' for the days set (365); files 30 days; nothing by IP address. The people answering see each person's chats, waiting times and ratings.

## Requirements

Joomla 4.4 or later (5 and 6 included), PHP 8.1 or later, and an OpenAI API key (or a service with the same API). The answer is streamed, so the web server must not buffer PHP output (on nginx, `X-Accel-Buffering` is honoured by default). AI Ask Desk's notifications to phones need HTTPS.

## Installation

1. Download `pkg_mcpask-x.y.z.zip` from the [latest release](https://github.com/desbear/joomla-ai-ask/releases/latest) (under **Assets**) and install it in **System → Install → Extensions**. Do not use GitHub's "Source code" zip: that is the repository, not an installable package.
2. Open **Components → AI Ask → Options** and enter the API key.
3. Press **Synchronise the index** on the dashboard.
4. Publish the module **AI Ask** in a position of your template (or as a floating button).
5. Add the scheduled task **AI Ask - Synchronise the index** to keep the index up to date.

The module's **Demo mode** shows the interface with a canned answer before any of this is done.

**AI Ask Desk**, after AI Ask:

1. Download `pkg_askdesk-x.y.z.zip` from its [release](https://github.com/desbear/joomla-ai-ask/releases) (tag `askdesk-v…`) and install it.
2. **AI Ask → Agents → New**, kind **AI Ask Desk live chat**: office hours, and what is offered when no one is there.
3. **AI Ask Desk → People answering**: make the members who answer people answering.
4. They open the console on the site: `index.php?option=com_askdesk&view=console`, or a menu item **AI Ask Desk → Console**.
5. **AI Ask Desk → Options**: alerts by e-mail, whether staff see others' cases, ratings, privacy; **Permissions** for the dashboard template and seeing everyone's cases.

On MySQL and MariaDB a search has two passes: the database shortlists passages by comparing compact bit codes, and only the shortlist is compared exactly in PHP. On other databases every embedding is compared in PHP.

## In this repository

- `src/`: the extensions. AI Ask: `com_mcpask`, `mod_mcp_ask`, `mod_mcp_ask_log`, `plg_content_mcpask`, `plg_easyblog_mcpask`, `plg_task_mcpask`, `plg_mcp_mcpask`, and the agents and sources `plg_mcpask_rsform`, `plg_mcpask_livechat`, `plg_mcpask_dropfiles`, `plg_mcpask_imagegen`; packed by `pkg_mcpask`. AI Ask Desk: `com_askdesk`, `plg_mcpask_askdesk`, `plg_task_askdesk` and `plg_askdesk_faq`, packed by `pkg_askdesk`.
- `board/`: AI Ask Desk's dashboard, a React app built into `src/com_askdesk/media/js/board.js` and `css/board.css`.
- `docs/`: [agents](docs/agents.md), [embeds](docs/embeds.md), [pets](docs/pets.md), [AI Ask Desk](docs/askdesk.md).
- `demo/`, `tests/`, `build/`.

## Development

```bash
build/build.sh                      # creates dist/pkg_mcpask-<version>.zip and dist/pkg_askdesk-<version>.zip
(cd board && npm install && npm run build)   # the dashboard (build.sh rebuilds it when its sources are newer)
build/dev-sync.sh /path/to/joomla   # copy the source into an installed site
php tests/OutputFilterTest.php      # unit tests of the answer filter
php tests/ChunkerTest.php           # unit tests of indexing
```

`demo/ask/index.html` shows the module outside Joomla with its canned answer; serve the repository root with any static web server.

## Security

The extension sends visitors' questions and passages of the site to an AI service and shows what comes back, so it has been reviewed as such: see [SECURITY-AUDIT.md](SECURITY-AUDIT.md). Please report vulnerabilities privately, see [SECURITY.md](SECURITY.md).

## License

GNU General Public License version 2 or later.

---

## 繁體中文

AI Ask for Joomla 是給訪客用的搜尋框：透過 AI 依網站自己的內容回答問題，並標示每段回答來自哪些頁面。還可以把對話交給能完成事情的小幫手（填表單、寫分析報告、轉接真人客服），用一行程式碼放到你的其他網站，也可以把浮動按鈕換成會動的小寵物。

另外還有 **AI Ask Desk**：網站的客服人員在同一個視窗接手對話，每則訊息雙向翻譯，並有一個附儀表板的客服主控台。

> **狀態：實驗性。** AI Ask for Joomla 0.20.4、AI Ask Desk 0.6.0，可在 Joomla 4.4、5、6 搭配 OpenAI 服務執行。

**兩個套件**：`pkg_mcpask`（AI Ask for Joomla，版本標籤 `v0.20.x`）與 `pkg_askdesk`（AI Ask Desk，需要 AI Ask 0.20.0 以上，版本標籤 `askdesk-v0.x.y`），從同一個 repo 建置、分開發佈。

**AI Ask for Joomla 功能**

- **模組「AI 問答」**：圖示、搜尋框，或固定在視窗角落的浮動按鈕，送出後展開成全螢幕對話，回答逐字出現並附來源編號與來源卡片。
- **元件「AI 問答」**：把已發佈的內容切成段落並存成向量（存在網站自己的資料庫），找出與問題最接近的段落，串流輸出依這些段落寫成的回答；儀表板有設定檢查、索引狀態、同步按鈕與提問紀錄。
- **內容來源**：Joomla 文章；裝了 FLEXIcontent 時讀 FLEXIcontent 項目（含文字自訂欄位；內容由類型模板呈現的類型，可改為讀取頁面實際顯示的內容）；裝了 EasyBlog 時也納入部落格文章。只索引未登入訪客可讀的內容，引用前會再檢查一次。
- **文件**（`plg_mcpask_dropfiles`，裝了 Dropfiles 後啟用）：指定 Dropfiles 分類中的公開檔案（PDF、Word .docx、PowerPoint .pptx、Excel .xlsx、Markdown、純文字）在主機上讀取，與網站頁面放在同一個索引一起回答。引用到的文件以檔案卡片呈現（類型、頁碼、大小），可直接開到該頁或下載。「已索引頁面」的「文件」分頁可查看每個檔案是否已用於回答、沒有的原因，可立即讀取，也可為讀不到文字的檔案貼上文字或 Markdown。其他外掛也能透過 `onMcpaskSources` 事件加入自己的來源。PDF 以內附的 [PdfParser](https://github.com/smalot/pdfparser)（LGPL-3.0）讀取；沒有文字層的掃描 PDF 讀不到文字。
- **多站共用**：可讀取其他裝有本擴充、並互相持有匯出金鑰的網站索引；兩站向量做法相同時直接沿用，不產生 AI 費用。
- **外部網站**：用 RSS／Atom、Sitemap 或只填首頁（自動爬取，遵守 robots.txt）讀取其他網站並用來回答。
- **回答可帶截圖、表格與提示框**：圖片只有在它是本站的檔案、而且出自這次引用的頁面時才會顯示。
- **已索引頁面**：列出回答取材的每一頁，可看到每頁實際交給 AI 的段落；可用網址手動加入頁面（一行一個，只讀那一頁、每天重讀）；不該用來回答的頁面可單筆或用網址規則「忽略」，不會再被加回來。
- **回答品質**：訪客可評分並填原因；「回答品質」頁列出評價差或答不出來的問題，可用「指定來源規則」導向正確頁面。
- **用一句話設定外觀**：描述想要的風格，AI 提出配色並即時預覽，自動確保文字對比易讀。
- **Agents**：接手對話、完成一件事的小幫手，由 `mcpask` 群組的外掛提供，詳見 [docs/agents.md](docs/agents.md)。
  - **RSForm! Pro**（裝了 RSForm! Pro 才會出現）：用對話填寫表單並依 RSForm 原本的流程送出；可產生**分析報告**（結果類型、評分、逐段撰寫、引用頁面、專屬網址、寄送連結）。
  - **LiveChat**：轉接真人客服並帶入前面的對話；服務時間內有人在線就對話，否則留言轉工單，並留下轉接紀錄。
  - 報告的 AI 插圖由生圖外掛提供，例如 `plg_mcpask_imagegen`（OpenAI 或 Open WebUI）；沒有安裝並啟用時，後台不會出現這個選項。
- **嵌入**：其他網站或產品貼一行程式碼即可使用（本站的 iframe，只允許設定的網站），可帶入訪客所在頁面、在小視窗登入，或由對方伺服器簽 token 帶入身分，詳見 [docs/embeds.md](docs/embeds.md)。
- **小寵物**：浮動按鈕可換成一隻小寵物，依訪客操作與回答播放九種動作，每個動作有全身圖與正方形縮圖。不附預設角色，請在「小寵物」頁建立，詳見 [docs/pets.md](docs/pets.md)。
- **費用控制**：可依訪客、使用者群組與全站限制提問數，用 Joomla 權限設定誰能提問；找不到相關內容時不呼叫模型；測試弱點的探測內容在送出前就被擋下。
- **搭配 [MCP Server for Joomla](https://github.com/desbear/joomla-mcp)**（選用）：外掛「MCP - AI Ask」讓連到網站的 AI 助理可以查看提問、評分、來源規則、Agent、轉接紀錄與報告（限超級管理員）。

**AI Ask Desk 功能**（完整說明見 [docs/askdesk.md](docs/askdesk.md)）

- **在 AI Ask 的視窗裡轉真人客服**：按「找真人」（或回答沒幫上忙時的建議），對話連同訪客問過 AI 的內容、所在頁面一起交給客服。可設定服務時間、休假日，沒人在線時改為留言或其他 Agent。
- **各自用自己的語言**：每則訊息由 AI 雙向翻譯（詞彙表可保留產品名稱），雙方都能看原文；不需要翻譯的訊息不送 AI，瀏覽器內建翻譯能用時先顯示。
- **客服主控台**（手機可用，可安裝成 App）：清單分「全部、待接手、待回覆」三個分頁，各有數字、捲動時分批載入，可排序，開關可顯示已結案；等待中的對話由一人接手；新案件有聲音，也能推播到手機。
- **結案**：訪客最後一句沒人回就結束的對話，和離線留言，會留在清單直到有人按「結案」；客服自己結束的對話一起結案。
- **對話旁的輔助**：依網站頁面建議回覆（AI 嚴格判斷、用客服的語言起草）、AI 搜尋網站、訪客過去的對話與提問，以及外掛加入的面板（CRM 客戶資料、AI 客戶摘要、回寫記事）。
- **檔案、貼圖與螢幕**：雙向傳圖片、影片、文件（檢查實際格式，保留 30 天）；貼圖包；訪客可截圖並標註（馬賽克、裁切、箭頭、文字）或錄下分頁；搭配 Dropfiles 的檔案庫可重複傳送。
- **對話之後**：對話以 PDF 寄給訪客（訪客看到的語言）；依設定評分（星等、讚／倒讚、0–10、選項、意見）；結束前由 AI 檢查是否還有沒處理完的事。
- **寄信通知**：離線留言、沒人回就結束的對話，寄信給客服（或指定信箱），信裡連結直接打開對話；超過設定時數還沒結案的，合併再提醒一次。
- **儀表板**（沒開對話時顯示）：12 欄的 widget（數字、電池、圓餅、甜甜圈、長條、堆疊、折線、面積、甘特、表格、可編輯表格），可直接拖曳與調整大小、俯視全部、用畫面邊緣的全覽小地圖找位置。每個 widget 顯示一個資料來源的一種樣板：客服自己的案件，或外掛（`onAskdeskSources`）提供的其他系統資料。可依關鍵字、人（全部、只有我、某位同事）與時間（今天、近 7／30 天、本月、上個月、今年）篩選。
- **儀表板樣板**：有權限的人把自己的儀表板存成所有人的樣板；每個人都會有這些 widget（可搬移、調整大小，不能刪除），也能另外加自己的。
- **誰看得到什麼**：只有被設為「客服人員」的人能開主控台；可設定讓客服只看自己接手和沒人接手的案件；「看所有人的案件」「設計儀表板樣板」兩個權限可在選項的「權限」給指定群組。
- **資料保留**：訪客（未登入）的對話結案一小時後刪除；會員的對話依設定天數保留（預設 365 天）；檔案 30 天；不記錄 IP。後台可看每位客服的對話數、等待時間與評分。

**下載**：到 [最新版本頁](https://github.com/desbear/joomla-ai-ask/releases/latest)，在 **Assets** 下載 `pkg_mcpask-x.y.z.zip`（不要下載「Source code」，那是原始碼，不能直接安裝）。

**安裝**：安裝 `pkg_mcpask-x.y.z.zip` →「元件 → AI 問答 → 選項」填入 API 金鑰 → 儀表板按「同步索引」→ 發佈「AI 問答」模組 → 新增排程工作「AI 問答 - 同步索引」。還沒設定前，可開啟模組的「示範模式」先看介面。

**安裝 AI Ask Desk**（先裝好 AI Ask）：到 [版本頁](https://github.com/desbear/joomla-ai-ask/releases) 下載 `pkg_askdesk-x.y.z.zip`（標籤 `askdesk-v…`）並安裝 →「AI Ask → Agents → 新增」，類型選「AI Ask Desk live chat」，設定服務時間 →「AI Ask Desk → 客服人員」把負責回覆的會員設為客服人員 → 客服在網站開啟主控台 `index.php?option=com_askdesk&view=console`（或建立選單「AI Ask Desk → 客服主控台」）→ 在「AI Ask Desk → 選項」設定寄信通知、客服能否看別人的案件、評分與隱私，並在「權限」設定儀表板樣板與看所有案件的群組。
