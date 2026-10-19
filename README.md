# AI Ask for Joomla

A search box for your visitors that answers their questions from your site's own content with AI, and names the pages each answer came from. It can also hand the conversation to helpers that get things done (fill in a form, write an analysis report, pass the visitor on to a person), show up on your other websites with one line of script, and turn its floating button into an animated pet.

> **Status: experimental (0.18.0).** Runs on Joomla 4.4, 5 and 6 with the OpenAI service. Not yet used on a production site.

[繁體中文說明](#繁體中文)

## Features

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

## Requirements

Joomla 4.4 or later (5 and 6 included), PHP 8.1 or later, and an OpenAI API key (or a service with the same API). The answer is streamed, so the web server must not buffer PHP output (on nginx, `X-Accel-Buffering` is honoured by default).

## Installation

1. Download `pkg_mcpask-x.y.z.zip` from the [latest release](https://github.com/desbear/joomla-ai-ask/releases/latest) (under **Assets**) and install it in **System → Install → Extensions**. Do not use GitHub's "Source code" zip: that is the repository, not an installable package.
2. Open **Components → AI Ask → Options** and enter the API key.
3. Press **Synchronise the index** on the dashboard.
4. Publish the module **AI Ask** in a position of your template (or as a floating button).
5. Add the scheduled task **AI Ask - Synchronise the index** to keep the index up to date.

The module's **Demo mode** shows the interface with a canned answer before any of this is done.

On MySQL and MariaDB a search has two passes: the database shortlists passages by comparing compact bit codes, and only the shortlist is compared exactly in PHP. On other databases every embedding is compared in PHP.

## Development

```bash
build/build.sh                      # creates dist/pkg_mcpask-<version>.zip
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

> **狀態：實驗性（0.18.0）。** 可在 Joomla 4.4、5、6 搭配 OpenAI 服務執行，尚未用於正式網站。

**功能**

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

**下載**：到 [最新版本頁](https://github.com/desbear/joomla-ai-ask/releases/latest)，在 **Assets** 下載 `pkg_mcpask-x.y.z.zip`（不要下載「Source code」，那是原始碼，不能直接安裝）。

**安裝**：安裝 `pkg_mcpask-x.y.z.zip` →「元件 → AI 問答 → 選項」填入 API 金鑰 → 儀表板按「同步索引」→ 發佈「AI 問答」模組 → 新增排程工作「AI 問答 - 同步索引」。還沒設定前，可開啟模組的「示範模式」先看介面。
