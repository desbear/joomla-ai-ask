# Pets

The floating button may be a pet. No pet comes with the extension: make one under **AI Ask → Pets**, then choose it in the module ("Pet", for the floating button) or an embed key. Agents may use a pet as their avatar too.

## Pictures

A pet's pictures are its library (0.22.0): upload as many as you like on the pet's page (APNG, animated AVIF, WebP or GIF; AVIF is usually a fifth of the size of APNG), name them as you like, then drag them into the moments below. A moment may have several, one picked at random each time it plays; one picture may be in several moments. Each picture may have:

- **Whole pet**: the floating button, and beside the conversation on wide windows (1360px and more).
- **Square thumbnail** (256×256 advised, a close-up): faces in the conversation (48px), agent buttons, the head of the conversation on narrower windows.
- **Seconds**: how long it plays once; empty, as long as the file says, or the moment's default.

Without a thumbnail the whole picture is used; only Idle is needed, and moments without a picture use it (or another, below). A still picture (and its thumbnail) is shown to visitors who ask for less motion, and until the pictures load. Pictures are checked by their first bytes and kept in `images/mcpask/pets/`.

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
| Moments of its own | once | now and then while it waits at its place (nothing without a picture) |
| Walking | over and over, moved along the window | now and then on wide screens; facing right, turned round to go left (no walking without a picture) |
| Getting sleepy | once | before it falls asleep, its last frame meeting Asleep's first (else straight to sleep) |
| Setting off | once, on the spot | before walking: from the front to facing right, its last frame meeting Walking's first (else it sets off at once) |
| Stopping | once | when a walk ends or is stopped: back to the front, its last frame meeting Idle's first (else straight to Idle) |
| Pointer near | once | the pointer comes near, on wide screens (else Greeting) |
| Words selected | once | words on the page selected; it offers to look them up (else Moments of its own, or Greeting) |
| End of the page | once | the end of a long page reached, once a page (else Glad) |
| Back to the page | once | back to the tab after a minute or more (else Waking) |

Idle, Setting off, Walking and Stopping should share the size of their pictures and the line their feet stand on, so one goes into the next without a jump. Only pictures facing right are needed: going left, the pet is turned round.

Walking and noticing the pointer and selected words are on wide screens only, never in an embed's frame; nothing of this for visitors who ask for less motion. The module can switch off its moments, walking and noticing.

What plays once is played from a copy kept in memory, so it starts from its first frame every time without being fetched again.

---

**繁體中文**：浮動按鈕可以換成小寵物，但不附預設角色，請先在「AI 問答 → 小寵物」建立，模組與嵌入金鑰才能選用；Agent 也可以用寵物當頭像。圖片先上傳到寵物的「圖庫」，名稱自己取，再拖曳到各個動作區塊（同一區塊放多張時每次隨機挑一張；一張可以放進好幾個區塊）。每張可以有全身圖（浮動按鈕、寬螢幕時對話視窗右側）與正方形縮圖（對話中的頭像、按鈕、窄螢幕）。除了原本的動作，還有「閒置小動作」「走路」（只在寬螢幕）「游標靠近」「反白文字」「捲到頁尾」「回到分頁」。只有「待機」必填，另可上傳給「減少動態效果」訪客看的靜態圖。
