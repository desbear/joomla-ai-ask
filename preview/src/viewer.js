// AI Ask Desk's viewer of a document in a chat (0.6.1): a PDF, a Word document or a spreadsheet opened over
// the console, read in the browser by a library of its own kind (fetched from the site the first time one
// is opened). The document goes nowhere else. Word and spreadsheets are drawn in a frame where nothing in
// them can run; a PDF's own scripts are not run.

const KINDS = { pdf: 'pdf', docx: 'docx', xlsx: 'xlsx', xlsm: 'xlsx', xls: 'xlsx', ods: 'xlsx', csv: 'xlsx' };
const LARGEST = 30 * 1024 * 1024;
const loading = {};

const kindOf = (name) => KINDS[String(name || '').toLowerCase().split('.').pop()] || null;

const el = (tag, name, text) => {
  const node = document.createElement(tag);

  if (name) node.className = name;
  if (text !== undefined) node.textContent = text;

  return node;
};

const style = () => {
  if (document.getElementById('adp-style')) return;

  const css = el('style');

  css.id = 'adp-style';
  css.textContent = `
.adp{position:fixed;inset:0;z-index:2000;display:grid;place-items:center;box-sizing:border-box;width:100%;height:100%;max-width:none;max-height:none;margin:0;padding:16px;border:0;background:transparent;animation:adp-in .2s ease}
.adp::backdrop{background:rgba(15,18,30,.42);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}
.adp-sheet{display:flex;flex-direction:column;width:min(1040px,100%);height:min(92vh,100%);overflow:hidden;border-radius:16px;background:#eef0f3;box-shadow:0 30px 80px -20px rgba(10,10,40,.55);animation:adp-rise .28s cubic-bezier(.22,1,.36,1)}
.adp-head{display:flex;align-items:center;gap:10px;min-width:0;padding:10px 12px 10px 18px;background:#fff;border-bottom:1px solid #e3e6ea;font:500 14px/1.4 system-ui,-apple-system,"PingFang TC","Microsoft JhengHei",sans-serif;color:#1f2329}
.adp-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.adp-head a,.adp-head button{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:34px;padding:0 12px;border:0;border-radius:10px;background:#f1f3f6;color:#1f2329;font:inherit;font-size:13px;text-decoration:none;cursor:pointer}
.adp-head a:hover,.adp-head button:hover{background:#e6e9ee}
.adp-head .adp-close{width:34px;padding:0;font-size:18px}
.adp-tabs{display:flex;gap:4px;padding:6px 12px 0;overflow-x:auto;background:#fff;border-bottom:1px solid #e3e6ea}
.adp-tabs[hidden]{display:none}
.adp-tabs button{flex:none;height:32px;padding:0 12px;border:0;border-bottom:2px solid transparent;background:none;color:#5f6368;font:13px system-ui,sans-serif;cursor:pointer}
.adp-tabs button[aria-selected="true"]{border-bottom-color:#0e86fe;color:#0e86fe}
.adp-head,.adp-tabs{flex:none}.adp-body{position:relative;display:flex;flex:1;flex-direction:column;min-height:0;overflow:auto;overscroll-behavior:contain}
.adp-body>iframe{flex:1;width:100%;min-height:0;border:0}
.adp-pages{display:grid;gap:14px;justify-items:center;padding:16px}
.adp-page{position:relative;width:min(960px,100%);background:#fff;box-shadow:0 2px 10px -4px rgba(20,20,40,.25)}
.adp-page canvas{display:block}
.adp-state{margin:auto;padding:40px 20px;color:#5f6368;font:14px/1.6 system-ui,sans-serif;text-align:center}
.adp-dots{display:inline-flex;gap:5px;margin-bottom:10px}.adp-dots i{width:7px;height:7px;border-radius:50%;background:#0e86fe;animation:adp-dot 1s infinite ease-in-out}.adp-dots i:nth-child(2){animation-delay:.15s}.adp-dots i:nth-child(3){animation-delay:.3s}
@keyframes adp-in{from{opacity:0}}@keyframes adp-rise{from{opacity:0;transform:translateY(14px) scale(.98)}}@keyframes adp-dot{0%,80%,100%{opacity:.25}40%{opacity:1}}
@media (prefers-reduced-motion:reduce){.adp,.adp-sheet,.adp-dots i{animation:none}}
@media (max-width:640px){.adp{padding:0}.adp-sheet{width:100%;height:100%;border-radius:0}}
`;
  document.head.append(css);
};

// A kind's library, fetched once
const library = (kind, asset) => {
  if (!loading[kind]) {
    loading[kind] = new Promise((resolve, reject) => {
      const script = el('script');

      script.src = asset(`preview/${kind}.js`);
      script.onload = () => (window.AskdeskPreviewKinds && window.AskdeskPreviewKinds[kind] ? resolve(window.AskdeskPreviewKinds[kind]) : reject(new Error(kind)));
      script.onerror = () => { loading[kind] = null; reject(new Error(kind)); };
      document.head.append(script);
    });
  }

  return loading[kind];
};

// A frame where nothing runs (no scripts, no forms, no pop-ups), its page written here
const frame = (mount, body, css = '') => new Promise((resolve) => {
  const box = el('iframe');

  box.setAttribute('sandbox', 'allow-same-origin');
  box.setAttribute('referrerpolicy', 'no-referrer');
  box.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0}body{${body}}${css}</style></head><body></body></html>`;
  box.addEventListener('load', () => resolve(box), { once: true });
  mount.append(box);
});

// What a document drew, made inert: no scripts, no handlers, no links out of it, nothing embedded
const tame = (doc) => {
  doc.querySelectorAll('script, iframe, object, embed, form, meta[http-equiv], link').forEach((node) => node.remove());
  doc.querySelectorAll('*').forEach((node) => {
    [...node.attributes].forEach((attribute) => {
      if (/^on/i.test(attribute.name) || (/^(href|src|xlink:href|action|formaction)$/i.test(attribute.name) && /^\s*(javascript|vbscript|data:text)/i.test(attribute.value))) {
        node.removeAttribute(attribute.name);
      }
    });

    if (node.tagName === 'A' && !/^#/.test(node.getAttribute('href') || '#')) {
      node.removeAttribute('href');
    }
  });
};

/**
 * Opens a document: {url, name, base (the site's media of com_askdesk), version, labels: {download, close,
 * loading, failed, tooLarge, page, truncated}}
 */
const open = async (file) => {
  const kind = kindOf(file.name);

  if (!kind) return false;

  style();

  const labels = file.labels || {};
  const label = (key, ...args) => {
    let text = String(labels[key] || '');

    args.forEach((value, i) => { text = text.replace(`%${i + 1}`, String(value)).replace('%s', String(value)); });

    return text;
  };
  const asset = (path) => `${file.base}js/${path}?${encodeURIComponent(file.version || '')}`;
  const closers = [];
  const before = document.activeElement;
  // A dialog of its own, shown modal: over whatever else is (AI Ask's conversation is a modal dialog too)
  const overlay = el('dialog', 'adp');
  const sheet = el('div', 'adp-sheet');
  const head = el('div', 'adp-head');
  const name = el('span', 'adp-name', file.name);
  const save = el('a', '', labels.download || '');
  const shut = el('button', 'adp-close', '×');
  const body = el('div', 'adp-body');
  const tabsSlot = el('div', 'adp-tabs');
  const state = el('div', 'adp-state');
  const dots = el('span', 'adp-dots');

  overlay.setAttribute('aria-label', file.name);
  save.href = file.url;
  save.setAttribute('download', file.name);
  shut.type = 'button';
  shut.setAttribute('aria-label', labels.close || '');
  tabsSlot.hidden = true;
  dots.append(el('i'), el('i'), el('i'));
  state.append(dots, el('div', '', labels.loading || ''));
  head.append(name, save, shut);
  body.append(state);
  sheet.append(head, tabsSlot, body);
  overlay.append(sheet);
  document.body.append(overlay);

  if (overlay.showModal) {
    overlay.showModal();
  } else {
    overlay.setAttribute('open', '');
  }

  const lock = document.documentElement.style.overflow;
  document.documentElement.style.overflow = 'hidden';

  const close = () => {
    closers.forEach((fn) => { try { fn(); } catch (e) { /* gone anyway */ } });

    if (overlay.open && overlay.close) {
      overlay.close();
    }

    overlay.remove();
    document.documentElement.style.overflow = lock;
    document.removeEventListener('keydown', key, true);

    if (before && before.focus) before.focus();
  };
  const key = (event) => {
    if (event.key === 'Escape') {
      event.stopPropagation();
      close();
    }
  };

  shut.addEventListener('click', close);
  overlay.addEventListener('cancel', (event) => { event.preventDefault(); close(); });
  overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
  document.addEventListener('keydown', key, true);
  shut.focus();

  const fail = (text) => {
    state.replaceChildren(el('div', '', text));

    if (!state.isConnected) {
      body.replaceChildren(state);
    }
  };

  try {
    const [draw, bytes] = await Promise.all([
      library(kind, asset),
      fetch(file.url, { credentials: 'same-origin' }).then((reply) => {
        if (!reply.ok) throw new Error(String(reply.status));

        const size = Number(reply.headers.get('content-length') || 0);

        if (size > LARGEST) throw Object.assign(new Error('large'), { large: true });

        return reply.arrayBuffer();
      }),
    ]);

    if (!overlay.isConnected) return true;
    if (bytes.byteLength > LARGEST) throw Object.assign(new Error('large'), { large: true });

    state.remove();

    // A spreadsheet's tabs go above what scrolls
    const mount = body;
    const ctx = { asset, label, frame, tame, onClose: (fn) => closers.push(fn) };

    await draw(new Uint8Array(bytes), mount, ctx);

    const tabs = mount.querySelector(':scope > .adp-tabs');

    if (tabs) {
      tabsSlot.replaceWith(tabs);
    }
  } catch (error) {
    if (overlay.isConnected) fail(error && error.large ? labels.tooLarge || '' : labels.failed || '');
  }

  return true;
};

window.AskdeskPreview = { kindOf, open };
