# Pets

The floating button may be a pet. No pet comes with the extension: make one under **AI Ask → Pets**, then choose it in the module ("Pet", for the floating button) or an embed key. Agents may use a pet as their avatar too.

## Pictures

For each moment, two pictures, uploaded on the pet's page (APNG, animated AVIF, WebP or GIF; AVIF is usually a fifth of the size of APNG):

- **Whole pet**: the floating button, and beside the conversation on wide windows (1360px and more).
- **Square thumbnail** (256×256 advised, a close-up): faces in the conversation (48px), agent buttons, the head of the conversation on narrower windows.

Without a thumbnail the whole picture is used; only Idle is needed, and moments without a picture use it. A still picture (and its thumbnail) is shown to visitors who ask for less motion, and until the pictures load. Pictures are checked by their first bytes and kept in `images/mcpask/pets/`.

## Moments

| Moment | Plays | When |
|---|---|---|
| Idle | over and over | usually |
| Greeting | once | the pointer or the keyboard comes to it; the word on the first visit |
| Picked up | over and over | while dragged |
| Thinking | over and over | while an answer or an agent's turn is awaited |
| Talking | over and over | while the answer is written; an agent replying; a report being written |
| Glad | once | an answer done or rated helpful; a form sent; a report ready; someone there to chat |
| Puzzled | once | nothing found, something failed, rated unhelpful |
| Asleep | over and over | after a while with nothing done |
| Waking | once | woken |

What plays once is played from a copy kept in memory, so it starts from its first frame every time without being fetched again.

---

**繁體中文**：浮動按鈕可以換成小寵物，但不附預設角色，請先在「AI 問答 → 小寵物」建立，模組與嵌入金鑰才能選用；Agent 也可以用寵物當頭像。每個動作上傳兩張：全身圖（浮動按鈕、寬螢幕時對話視窗右側）與正方形縮圖（對話中的頭像、按鈕、窄螢幕）。只有「待機」必填，另可上傳給「減少動態效果」訪客看的靜態圖。
