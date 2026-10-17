# Agents

An agent takes over the conversation to get one thing done, then hands it back. Kinds of agents come from plugins of the `mcpask` group; you set up as many agents of each kind as you need under **AI Ask → Agents**.

## Setting one up

Every agent has, besides the settings of its kind:

- **Conversation**: its name, opening and closing words, and an avatar (a pet of the library, or one picture; an APNG moves).
- **Triggers**: listed beside the search box; offered below an answer when the question means much the same as one of its trigger phrases (the question's embedding is reused, so this costs nothing more); or offered when the AI judges it fits, from a description you write.
- **Scope**: languages, access level, and how many times a visitor may start it a day.

What an agent keeps between turns stays on the server, encrypted, bound to the visitor, for a day at most. Every turn passes the same checks as a question.

## Kinds that come with the package

| Kind | Plugin | Shown when |
|---|---|---|
| RSForm! Pro form | `plg_mcpask_rsform` | RSForm! Pro is installed |
| LiveChat | `plg_mcpask_livechat` | always (needs your LiveChat licence number) |

### RSForm! Pro form

Fills in a form in conversation, a page at a time (cut at the form's page breaks). Visitors fill in the card or answer in their own words: the AI puts into the fields only values they take, and says what is missing. A summary is shown before anything is sent; the form is then stored as RSForm stores a submission, with its e-mails.

The form is shown in the language of the page, from RSForm's own translations, and RSForm's checks of each field (its validation rules, a site's custom ones included, and its maximum length) apply. A box the form only shows to read (terms with `readonly`) is shown as text and sent as it is. What a form's own scripts do on its page is not run in the conversation; for the usual cases there are settings instead:

- **Fields left out**: names of fields not to show, such as text the form's page hides with CSS.
- **Field for the page**: a (hidden) field sent with the address of the page the visitor started from.
- **Look-ups**: a field that suggests values from a JSON service as the visitor types, and fills in other fields from the one chosen (a company found by name, filling in its registration number). The visitor's browser asks the service, so it must allow the website (CORS).

**Analysis report** (optional): once sent, a report written for the visitor from their answers and the site's pages: a result type (as in a personality test), scores, sections each with what it is for and how long, the pages it cites, buttons at the end, a private address kept for some days, and the link e-mailed. You write the types, scores and sections; nothing is filled in for you. Pictures may be the type's own, or made by AI when an image plugin is enabled (see below).

### LiveChat

Passes the visitor on to people in LiveChat. LiveChat's window is loaded only then (it sets cookies of its own); a page that has LiveChat already keeps it. The people answering see, beside the chat or ticket, what you choose of: the whole conversation before, the last question, the page, the report's link. Within office hours with someone online the visitor chats; otherwise they leave a message. Every handoff is logged under **AI Ask → Handoffs**, with no names or contact details.

## Pictures made by AI

Reports can show pictures made by AI only when a plugin offers an image service. `plg_mcpask_imagegen` (OpenAI's image API or an Open WebUI server) comes with the package but is not enabled: enable it and enter its settings, and report sections offer "Made by AI". Pictures are cached by prompt and capped per day; no personal detail goes into a prompt.

## Writing your own kind

A plugin of the `mcpask` group listens to `onMcpaskAgentTypes` and appends an object implementing `Gss\Component\Mcpask\Administrator\Agent\AgentInterface` to the `types` argument. It shows cards (text, fields, suggestions, summary, actions, done, error), never HTML of its own. An image service implements `ImageProviderInterface` and is appended to `providers` on `onMcpaskImageProviders`.

---

**繁體中文**：Agent 是接手對話、完成一件事的小幫手，由 `mcpask` 群組的外掛提供，在「AI 問答 → Agents」建立。每個 Agent 可設定名稱、開場白、頭像、觸發方式（列在搜尋框旁、問句相近時推薦、由 AI 判斷推薦）與適用範圍。內附兩種：RSForm! Pro 表單（裝了 RSForm 才出現，可產生分析報告，類型、評分、段落都由你自己建立）與 LiveChat 轉接（帶入前面的對話，留下轉接紀錄）。報告的 AI 插圖需要啟用生圖外掛（內附 `plg_mcpask_imagegen`，預設未啟用）。
