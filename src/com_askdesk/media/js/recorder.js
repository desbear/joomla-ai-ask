/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

// The recorder's own window (0.1.0): a recording of a tab goes on while that tab changes page,
// which a recording made in the page itself cannot (it ends with the page). The chat window opens
// this window and hands it the chat (its key, where files go, the words to show); the visitor
// chooses the tab, shows what happens there, stops, watches, and sends. Then the window closes.
((window, document) => {
  'use strict';

  const root = document.querySelector('.rec');
  const title = root.querySelector('.rec__title');
  const note = root.querySelector('.rec__note');
  const status = root.querySelector('.rec__status');
  const start = root.querySelector('.rec__start');
  const local = (url) => (typeof url === 'string' && /^\/(?!\/)[^\s]*$/.test(url) ? url : '');

  let chat = null;
  // A recording not sent yet (sending failed): sent again rather than recorded again
  let unsent = null;

  const state = (name, words = '') => {
    root.dataset.state = name;
    status.textContent = words;
  };

  const paint = () => {
    const t = chat.texts;

    document.documentElement.lang = chat.language || '';
    document.title = t.title || 'Recording';
    title.textContent = t.title || '';
    note.textContent = t.note || '';
    start.textContent = t.start || 'Start';
    state('ready');
  };

  // Only the chat window of this site that opened it may hand it a chat
  window.addEventListener('message', (event) => {
    const data = event.data;

    if (event.source !== window.opener || event.origin !== window.location.origin || !data || data.mcpAskRecorder !== 'chat') {
      return;
    }

    const texts = {};

    Object.entries(data.texts && typeof data.texts === 'object' ? data.texts : {}).forEach(([name, value]) => {
      texts[String(name).slice(0, 40)] = String(value).slice(0, 300);
    });

    const headers = {};

    ['X-Mcp-Ask-Embed', 'X-Mcp-Ask-Identity'].forEach((name) => {
      if (data.headers && typeof data.headers[name] === 'string') {
        headers[name] = data.headers[name].slice(0, 2000);
      }
    });

    if (!/^[0-9a-f]{48}$/.test(String(data.key || '')) || !local(data.upload)) {
      return;
    }

    chat = { key: data.key, upload: local(data.upload), headers, texts, language: String(data.language || '').slice(0, 20) };
    paint();
  });

  const tell = (type) => {
    try {
      if (window.opener) {
        window.opener.postMessage({ mcpAskRecorder: type }, window.location.origin);
      }
    } catch (error) {
      // The chat's page is gone (another page now): the chat hears of the file when it asks
    }
  };

  async function send(file) {
    const t = chat.texts;
    const form = new FormData();

    form.append('key', chat.key);
    form.append('file', file);
    state('sending', t.sending || '…');

    try {
      const response = await fetch(chat.upload, { method: 'POST', body: form, headers: { Accept: 'application/json', ...chat.headers }, credentials: 'same-origin' });
      const data = await response.json();

      if (data.error) {
        throw new Error(data.error);
      }

      unsent = null;
      state('sent', t.sent || '');
      tell('sent');
      window.setTimeout(() => window.close(), 1800);
    } catch (error) {
      // The words of the site when it said no; otherwise (no connection) the chat's own
      unsent = file;
      start.textContent = t.send || t.start || '';
      state('ready', error instanceof TypeError ? (t.failed || '') : (error.message || t.failed || ''));
    }
  }

  start.addEventListener('click', async () => {
    if (!chat || !window.McpAskCapture) {
      return;
    }

    const t = chat.texts;

    start.disabled = true;

    if (unsent) {
      await send(unsent);
      start.disabled = false;

      return;
    }

    const file = await window.McpAskCapture.record({
      elsewhere: true,
      texts: t,
      hide: () => state('recording', t.back || ''),
      show: () => {
        state('ready');
        window.focus();
      },
    });

    start.disabled = false;

    if (file) {
      await send(file);
    } else {
      state('ready');
    }
  });

  if (window.opener) {
    tell('ready');
  } else {
    state('alone');
    note.textContent = 'Open this from the chat.';
  }
})(window, document);
