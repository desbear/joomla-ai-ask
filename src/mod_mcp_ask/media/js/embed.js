/**
 * @package     AI Ask for Joomla
 * @subpackage  mod_mcp_ask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 *
 * AI Ask on another website. One line in that website's pages:
 *
 *   <script src="https://(the Joomla site)/media/mod_mcp_ask/js/embed.js" data-key="pk_…" async></script>
 *
 * It puts a frame of the Joomla site in a corner of the window: the size of a button, the open
 * search box, or the whole window for the conversation. What is asked and answered stays in the
 * frame; this page tells it where the visitor is, changes page when a source of this website is
 * opened, and opens LiveChat here when the visitor asks for a person. In a chat with a person
 * (AI Ask Desk) the frame may capture this tab, a screenshot or a short recording, when the visitor
 * chooses to: this page puts the frame aside meanwhile.
 *
 * Optional attributes: data-lang (a language tag), data-user (a token the website's server signed
 * for its signed-in visitor), data-login (where the website signs its visitors in: the frame's
 * sign-in button leads there), data-logout (where it signs them out: signing out in the
 * conversation goes there), data-size (the pet's size in pixels), data-title (the frame's name
 * for screen readers). The visitor may drag the button; where they leave it is kept for this website.
 *
 * window.McpAsk: setContext({...}) what this page is about (a few short values), setUser(token,
 * name) or setUser(null), open(), close().
 */
((window, document) => {
  'use strict';

  const script = document.currentScript || document.querySelector('script[src*="mod_mcp_ask/js/embed.js"][data-key]');

  if (!script || window.McpAskEmbed) {
    return;
  }

  const key = String(script.dataset.key || '');

  if (!/^pk_[0-9a-f]{24}$/.test(key)) {
    return;
  }

  // Which version this copy is (embed.js?v=…; none on a line written before 0.20.7), and the one the
  // frame last said is current. Browsers keep this file long: a copy that is not the current one loads
  // the current one in its place (once a page), before it puts anything in the page
  const VERSION = `mcp-ask-embed-script:${key}`;
  const own = (() => {
    try {
      return new URL(script.src, document.baseURI).searchParams.get('v') || '';
    } catch (error) {
      return '';
    }
  })();
  const fit = (version) => /^[0-9A-Za-z.-]{1,40}$/.test(String(version || ''));

  const takeVersion = (version) => {
    if (window.McpAskEmbedTaking) {
      return false;
    }

    window.McpAskEmbedTaking = true;

    const next = document.createElement('script');
    const url = new URL(script.src, document.baseURI);

    url.searchParams.set('v', version);
    [...script.attributes].forEach((attribute) => next.setAttribute(attribute.name, attribute.value));
    next.src = url.href;
    next.async = true;
    (document.head || document.documentElement).append(next);

    return true;
  };

  try {
    const current = window.localStorage.getItem(VERSION);

    if (fit(current) && current !== own && takeVersion(current)) {
      return;
    }
  } catch (error) {
    // Kept as it is
  }

  window.McpAskEmbed = true;

  const source = new URL(script.src, document.baseURI);
  const base = source.href.replace(/media\/mod_mcp_ask\/js\/embed\.js.*$/, '');
  const frameOrigin = source.origin;
  const frame = document.createElement('iframe');
  const address = new URL(`${base}index.php`);

  address.search = new URLSearchParams({
    option: 'com_mcpask',
    task: 'embed.page',
    format: 'raw',
    key,
    host: window.location.origin,
    ...(script.dataset.lang ? { lang: script.dataset.lang } : {}),
  }).toString();

  frame.src = address.href;
  frame.title = script.dataset.title || 'Ask';
  // Copying answers; showing this page to a person answering (a screenshot or a recording of the tab, AI Ask Desk)
  frame.setAttribute('allow', 'clipboard-write; display-capture');
  frame.setAttribute('allowtransparency', 'true');

  // Hidden until the page in it says it is ready, so nothing shows of a frame that is refused
  Object.assign(frame.style, {
    position: 'fixed',
    zIndex: '2147483000',
    inset: 'auto 0 0 auto',
    width: '0',
    height: '0',
    maxWidth: '100vw',
    maxHeight: '100dvh',
    border: '0',
    margin: '0',
    padding: '0',
    background: 'transparent',
    colorScheme: 'normal',
    visibility: 'hidden',
  });

  let ready = false;
  let side = 'right';
  let mode = 'button';
  // While the page is captured: the frame out of sight (a screenshot), or only the bar to stop a recording
  let capture = 'none';
  let button = 96;
  // On a phone (narrow window) the open box is the site's phone card across the bottom, as tall as the frame says
  const phoneAt = 640;
  let phone = window.innerWidth <= phoneAt;
  let tall = 320;
  let context = {};
  let user = script.dataset.user ? { token: script.dataset.user, name: '' } : null;
  // Where this website signs its visitor out (data-logout): signing out in the conversation goes there
  const logoutUrl = (() => {
    try {
      const url = script.dataset.logout ? new URL(script.dataset.logout, window.location.href) : null;

      return url && ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    } catch (error) {
      return '';
    }
  })();
  // The pet's size here (data-size, pixels), when this website sets one (0.20.4)
  const petSize = Math.min(240, Math.max(48, Number(script.dataset.size) || 0)) || 0;
  // Where the visitor last dragged the button to on this website: the side, and the height as a
  // share of the window (0 top, 1 bottom), so it stays put when the window changes size
  const SPOT = `mcp-ask-embed:${key}`;
  // Left for this website's sign-in from the conversation: opened again on coming back signed in
  const REOPEN = `mcp-ask-embed-reopen:${key}`;
  const REOPEN_WITHIN = 15 * 60 * 1000;
  let spot = (() => {
    try {
      const kept = JSON.parse(window.localStorage.getItem(SPOT));

      return kept && ['left', 'right'].includes(kept.side) && kept.y >= 0 && kept.y <= 1 ? kept : null;
    } catch (error) {
      return null;
    }
  })();
  // Where this website signs its visitors in (data-login): the frame's sign-in button leads there
  // instead of to the frame's own site, and the visitor comes back signed in (0.20.3)
  const loginUrl = (() => {
    try {
      const url = script.dataset.login ? new URL(script.dataset.login, window.location.href) : null;

      return url && ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    } catch (error) {
      return '';
    }
  })();
  const waiting = [];

  const post = (type, data = {}) => {
    if (frame.contentWindow) {
      frame.contentWindow.postMessage({ ...data, mcpAsk: type }, frameOrigin);
    }
  };

  // Calls made before the frame is ready wait for it
  const whenReady = (callback) => (ready ? callback() : waiting.push(callback));

  const sendContext = () => post('context', { url: window.location.href, title: document.title, context });

  // ---------- Size ----------

  const place = () => {
    if (capture === 'bar') {
      const wide = Math.min(window.innerWidth, 440);

      Object.assign(frame.style, {
        top: 'auto',
        bottom: '0',
        left: `${Math.max(0, (window.innerWidth - wide) / 2)}px`,
        right: 'auto',
        width: `${wide}px`,
        height: '96px',
        visibility: 'visible',
      });

      return;
    }

    frame.style.visibility = ready && capture !== 'hidden' ? 'visible' : 'hidden';

    // While the button is dragged the frame covers the window, see-through, so it can be put anywhere
    const full = mode === 'full' || mode === 'drag';

    // Open on a phone: across the bottom of the screen, as tall as the card and the pet over it
    if (phone && mode === 'bar') {
      const style = { top: 'auto', bottom: '0', left: '0', right: '0', width: '100vw', height: `${Math.min(window.innerHeight, tall)}px` };

      Object.entries(style).forEach(([name, value]) => {
        if (frame.style[name] !== value) {
          frame.style[name] = value;
        }
      });

      return;
    }

    const wide = Math.min(window.innerWidth, 480);
    const height = full ? window.innerHeight : Math.min(window.innerHeight, mode === 'bar' ? 260 + Math.max(0, button - 96) : button);
    const at = spot ? spot.side : side;
    // Moved by the visitor: at the height they left it, the button's frame; a bigger frame (the open
    // box) keeps its bottom where the button's was, so the pet stays where it is and the box opens above
    const buttonTop = spot ? spot.y * Math.max(0, window.innerHeight - Math.min(window.innerHeight, button)) : 0;
    const top = !full && spot ? Math.round(Math.min(Math.max(0, buttonTop + Math.min(window.innerHeight, button) - height), window.innerHeight - height)) : null;

    const style = {
      top: full ? '0' : (top === null ? 'auto' : `${top}px`),
      bottom: full || top === null ? '0' : 'auto',
      left: full || at === 'left' ? '0' : 'auto',
      right: full || at === 'right' ? '0' : 'auto',
      width: full ? '100vw' : `${mode === 'bar' ? Math.min(window.innerWidth, wide + (button > 96 ? button - 40 : 0)) : button}px`,
      height: full ? '100dvh' : `${height}px`,
    };

    // Only what changes: setting the same again still makes the frame lay out anew (it flickered)
    Object.entries(style).forEach(([name, value]) => {
      if (frame.style[name] !== value) {
        frame.style[name] = value;
      }
    });
  };

  window.addEventListener('resize', () => {
    const now = window.innerWidth <= phoneAt;

    if (ready && now !== phone) {
      phone = now;
      post('screen', { phone });
    }

    if (ready) {
      place();
    }
  });

  // ---------- LiveChat, when the visitor asks for a person ----------

  // The same as in the frame (ask.js): LiveChat already on this page is kept and driven through
  // the API it has; otherwise it is loaded now
  const liveChatApi = () => {
    const widget = window.LiveChatWidget;

    if (widget && widget._h) {
      return {
        availability: () => ((widget.get('state') || {}).availability === 'online' ? 'online' : 'offline'),
        variables: (values) => widget.call('set_session_variables', values),
        name: (name) => widget.call('set_customer_name', name),
        email: (email) => widget.call('set_customer_email', email),
        open: () => widget.call('maximize'),
        onChat: (callback) => widget.on('customer_status_changed', (data) => data && data.status === 'chatting' && callback()),
        onMessage: (callback) => widget.on('form_submitted', (data) => data && data.type === 'ticket' && callback()),
      };
    }

    const api = window.LC_API;

    if (api && typeof api.is_loaded === 'function' && api.is_loaded()) {
      const chain = (name, callback) => {
        const before = api[name];
        api[name] = (...args) => {
          if (typeof before === 'function') {
            before(...args);
          }

          callback();
        };
      };

      return {
        availability: () => (api.agents_are_available() ? 'online' : 'offline'),
        variables: (values) => (api.update_custom_variables || api.set_custom_variables).call(api, Object.entries(values).map(([name, value]) => ({ name, value }))),
        name: (name) => api.set_visitor_name(name),
        email: (email) => api.set_visitor_email(email),
        open: () => api.open_chat_window(),
        onChat: (callback) => chain('on_chat_started', callback),
        onMessage: (callback) => chain('on_ticket_created', callback),
      };
    }

    return null;
  };

  let liveChat = null;
  let liveChatHeard = false;

  const loadLiveChat = (license, group) => {
    if (liveChat) {
      return liveChat;
    }

    liveChat = new Promise((resolve, reject) => {
      let polling = 0;
      const fail = (error) => {
        window.clearTimeout(timer);
        window.clearInterval(polling);
        liveChat = null;
        reject(error);
      };
      const timer = window.setTimeout(() => fail(new Error('LiveChat did not load')), 15000);
      const check = () => {
        const api = liveChatApi();

        if (api) {
          window.clearTimeout(timer);
          window.clearInterval(polling);
          resolve(api);
        }

        return Boolean(api);
      };

      if (check()) {
        return;
      }

      if (window.LiveChatWidget || window.LC_API || (window.__lc && window.__lc.license) || document.querySelector('script[src*="cdn.livechatinc.com/tracking.js"]')) {
        polling = window.setInterval(check, 300);

        return;
      }

      window.__lc = { license, integration_name: 'manual_onboarding', product_name: 'livechat', ...(group > 0 ? { group } : {}) };

      const widget = { _q: [], _h: null, _v: '2.0' };
      const queue = (args) => (widget._h ? widget._h.apply(null, args) : widget._q.push(args));

      ['on', 'once', 'off', 'call'].forEach((name) => {
        widget[name] = (...args) => queue([name, args]);
      });
      widget.get = (...args) => {
        if (!widget._h) {
          throw new Error('LiveChat is not ready');
        }

        return queue(['get', args]);
      };
      widget.init = () => {
        const tag = document.createElement('script');
        tag.async = true;
        tag.src = 'https://cdn.livechatinc.com/tracking.js';
        tag.addEventListener('error', () => fail(new Error('LiveChat was blocked')));
        document.head.append(tag);
      };
      window.LiveChatWidget = widget;
      widget.once('ready', check);
      widget.init();
    });

    return liveChat;
  };

  const handoff = async (data) => {
    try {
      const api = await loadLiveChat(Number(data.license) || 0, Number(data.group) || 0);
      const variables = data.variables && typeof data.variables === 'object' ? data.variables : {};
      const customer = data.customer && typeof data.customer === 'object' ? data.customer : {};

      if (data.first) {
        const clean = Object.fromEntries(Object.entries(variables).slice(0, 10).map(([name, value]) => [String(name).slice(0, 50), String(value).slice(0, 1000)]));

        if (Object.keys(clean).length) {
          api.variables(clean);
        }

        if (customer.name) {
          api.name(String(customer.name).slice(0, 100));
        }

        if (customer.email) {
          api.email(String(customer.email).slice(0, 200));
        }

        if (!liveChatHeard) {
          liveChatHeard = true;
          api.onChat(() => post('handoff-event', { event: 'chatting' }));
          api.onMessage(() => post('handoff-event', { event: 'message' }));
        }
      }

      const availability = api.availability();

      api.open();
      post('handoff-result', { id: data.id, availability });
    } catch (error) {
      post('handoff-result', { id: data.id, error: String(error && error.message ? error.message : error) });
    }
  };

  // ---------- What the frame says ----------

  window.addEventListener('message', (event) => {
    const data = event.data;

    if (event.source !== frame.contentWindow || event.origin !== frameOrigin || !data || typeof data.mcpAsk !== 'string') {
      return;
    }

    switch (data.mcpAsk) {
      case 'ready':
        // Not the current script: kept as current for this website's next pages, and taken now, the frame
        // let go (once a page: the copy taken says it is current)
        if (fit(data.script)) {
          try {
            window.localStorage.setItem(VERSION, data.script);
          } catch (error) {
            // Taken now only
          }

          if (data.script !== own && !window.McpAskEmbedTaking) {
            frame.remove();
            window.McpAskEmbed = false;
            takeVersion(data.script);

            break;
          }
        }

        ready = true;
        side = data.side === 'left' ? 'left' : 'right';
        place();
        frame.style.visibility = 'visible';
        sendContext();
        // This page lets the frame capture it, and puts the frame aside meanwhile
        post('host', { capture: true, login: Boolean(loginUrl) && !user, logout: Boolean(logoutUrl) && Boolean(user), drag: true, size: petSize, side: spot ? spot.side : side });

        // On a phone or not: the frame lays the open box out for it
        post('screen', { phone });

        // Who is signed in on this page; nobody (signed out on this website meanwhile): whoever this
        // website said before is forgotten in the frame too (0.20.3)
        post('user', user || { token: null });

        waiting.splice(0).forEach((callback) => callback());

        // Back from signing in to ask more: the conversation again (it was kept in the frame)
        try {
          const left = Number(window.localStorage.getItem(REOPEN)) || 0;

          window.localStorage.removeItem(REOPEN);

          if (user && left && Date.now() - left < REOPEN_WITHIN) {
            post('open');
          }
        } catch (error) {
          // Not opened again by itself
        }

        break;
      case 'size':
        // A pet needs more room than the round button
        if (data.mode === 'button' || !['bar', 'full'].includes(data.mode)) {
          button = Math.min(300, Math.max(96, Number(data.button) || 96));
        }

        // While the button is dragged the frame keeps the whole window: the button's size is kept for
        // when it is let go (the frame shrank under the finger and the button flickered, 0.20.4)
        if (mode !== 'drag') {
          mode = ['button', 'bar', 'full'].includes(data.mode) ? data.mode : 'button';
          place();
        }

        // Answered once the new size is laid out
        window.requestAnimationFrame(() => post('sized', { mode }));
        break;
      case 'tall':
        // How tall the open box is on a phone (the card and the pet over it)
        tall = Math.min(600, Math.max(160, Math.round(Number(data.height) || 0)));
        place();
        break;
      case 'navigate': {
        let url;

        try {
          url = new URL(String(data.url || ''), window.location.href);
        } catch (error) {
          return;
        }

        if (!['http:', 'https:'].includes(url.protocol)) {
          return;
        }

        // A page of this website: go there; any other site: a new tab
        if (url.origin === window.location.origin) {
          window.location.assign(url.href);
        } else {
          window.open(url.href, '_blank', 'noopener');
        }

        break;
      }
      // The button is dragged (0.20.4): the frame covers the window meanwhile, and says where it was,
      // so the button stays under the finger; let go, it is the button's size again where it was put
      case 'drag': {
        if (data.phase === 'start' && mode === 'button') {
          const before = frame.getBoundingClientRect();

          mode = 'drag';
          place();
          // Once the frame has its new size (a timer: animation frames wait while a tab is hidden)
          window.setTimeout(() => post('dragging', { left: before.left, top: before.top }), 30);
        } else if (data.phase === 'end' && mode === 'drag') {
          const rect = data.rect || {};
          const at = data.side === 'left' ? 'left' : 'right';
          const height = Math.min(window.innerHeight, button);
          // The frame where the button now is, the button keeping its place in the frame
          const top = Math.min(Math.max(0, Number(rect.top) + Number(rect.height) + (button - Number(rect.height)) / 2 - height), window.innerHeight - height);
          const range = window.innerHeight - height;

          spot = { side: at, y: range > 0 ? Math.min(1, Math.max(0, top / range)) : 1 };

          try {
            window.localStorage.setItem(SPOT, JSON.stringify(spot));
          } catch (error) {
            // Where it is until the page is left
          }

          mode = 'button';
          place();
          window.requestAnimationFrame(() => post('sized', { mode }));
        }

        break;
      }
      case 'logout':
        if (logoutUrl) {
          window.location.assign(logoutUrl);
        }

        break;
      case 'login':
        if (loginUrl) {
          try {
            window.localStorage.setItem(REOPEN, String(Date.now()));
          } catch (error) {
            // Not opened again by itself
          }

          window.location.assign(loginUrl);
        }

        break;
      case 'handoff':
        handoff(data);
        break;
      case 'capture':
        capture = ['hidden', 'bar'].includes(data.phase) ? data.phase : 'none';
        place();
        // Answered once the page is laid out without the frame (or with only the bar)
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => post('captured', { phase: capture })));
        break;
      default:
    }
  });

  // A page that changes address without loading (single-page applications) says so too
  ['pushState', 'replaceState'].forEach((name) => {
    const original = window.history[name];

    window.history[name] = function change(...args) {
      const result = original.apply(this, args);
      whenReady(sendContext);

      return result;
    };
  });
  window.addEventListener('popstate', () => whenReady(sendContext));

  // ---------- For this website's own scripts ----------

  window.McpAsk = {
    setContext(values) {
      context = values && typeof values === 'object' ? { ...values } : {};
      whenReady(sendContext);
    },
    setUser(token, name = '') {
      user = typeof token === 'string' && token ? { token, name: String(name || '') } : null;
      whenReady(() => post('user', user || { token: null }));
    },
    open() {
      whenReady(() => post('open'));
    },
    close() {
      whenReady(() => post('close'));
    },
  };

  const attach = () => document.body.append(frame);

  if (document.body) {
    attach();
  } else {
    document.addEventListener('DOMContentLoaded', attach);
  }
})(window, document);
