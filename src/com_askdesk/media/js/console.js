/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

// The console of the people answering: the chats waiting and answered, one chat open, one's
// status and language. It asks the desk what is new every two seconds while in view, less often
// when not; a new chat or message rings and, when allowed, shows a notification.
((window, document) => {
  'use strict';

  const root = document.querySelector('[data-askdesk]');

  if (!root) {
    return;
  }

  let config;

  try {
    config = JSON.parse(root.dataset.askdesk);
  } catch (error) {
    return;
  }

  const t = config.strings || {};
  const locale = document.documentElement.lang || undefined;

  const el = (tag, className, text) => {
    const node = document.createElement(tag);

    if (className) {
      node.className = className;
    }

    if (text !== undefined) {
      node.textContent = text;
    }

    return node;
  };

  const button = (className, text, onClick) => {
    const node = el('button', className, text);

    node.type = 'button';
    node.addEventListener('click', onClick);

    return node;
  };

  const post = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': config.token },
      body: JSON.stringify(body || {}),
      credentials: 'same-origin',
    });
    const data = await response.json().catch(() => null);

    if (!data) {
      throw new Error(t.error);
    }

    return data;
  };

  const timeOf = (iso) => {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
      return '';
    }

    const today = new Date().toDateString() === date.toDateString();

    return new Intl.DateTimeFormat(locale, today ? { hour: '2-digit', minute: '2-digit' } : { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
  };

  // Plain text, with addresses as links
  const linked = (text) => {
    const box = el('div', 'askdesk-text');
    const parts = String(text || '').split(/(https?:\/\/[^\s<>"']+)/g);

    parts.forEach((part, index) => {
      if (index % 2) {
        const link = el('a', '', part);

        link.href = part;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        box.append(link);
      } else {
        box.append(document.createTextNode(part));
      }
    });

    return box;
  };

  // Outline icons, drawn with the text's colour
  const ICONS = {
    headset: 'M4 14v-3a8 8 0 0 1 16 0v3M4 14a2 2 0 0 0 2 2h1v-5H6a2 2 0 0 0-2 2zm16 0a2 2 0 0 1-2 2h-1v-5h1a2 2 0 0 1 2 2zm-3 2v1a3 3 0 0 1-3 3h-2',
    send: 'M10 14L21 3M21 3l-6.5 18a.55.55 0 0 1-1 0L10 14l-7-3.5a.55.55 0 0 1 0-1L21 3',
    link: 'M9 15l6-6M11 6l.5-.5a5 5 0 0 1 7 7l-.5.5M13 18l-.5.5a5 5 0 0 1-7-7l.5-.5',
    sparkles: 'M16 18a2 2 0 0 1 2 2 2 2 0 0 1 2-2 2 2 0 0 1-2-2 2 2 0 0 1-2 2zm0-12a2 2 0 0 1 2 2 2 2 0 0 1 2-2 2 2 0 0 1-2-2 2 2 0 0 1-2 2zM9 18a6 6 0 0 1 6-6 6 6 0 0 1-6-6 6 6 0 0 1-6 6 6 6 0 0 1 6 6z',
    language: 'M4 5h7M9 3v2c0 4.4-2.2 8-5 8M5 9c0 2.1 2.9 3.9 6.6 4M12 20l4-9 4 9M19.1 18h-6.2',
    sidebar: 'M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM9 4v16',
    bulb: 'M3 12h1m8-9v1m8 8h1M5.6 5.6l.7.7m12.1-.7l-.7.7M9 16a5 5 0 1 1 6 0 3.5 3.5 0 0 0-1 3 2 2 0 0 1-4 0 3.5 3.5 0 0 0-1-3M9.7 17h4.6',
    history: 'M12 8v4l2 2M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5',
    external: 'M12 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6M11 13l9-9M15 4h5v5',
    up: 'M12 19V5M5 12l7-7 7 7',
    enter: 'M9 10l-5 5 5 5M20 4v7a4 4 0 0 1-4 4H4',
    down: 'M6 9l6 6 6-6',
    close: 'M6 6l12 12M18 6L6 18',
    quote: 'M10 11H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v6c0 2.7-1.3 4.3-4 5M19 11h-4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v6c0 2.7-1.3 4.3-4 5',
    pin: 'M15 4.5l-4 4L7 10l-1.5 1.5 7 7L14 17l1.5-4 4-4M9 15l-4.5 4.5M14.5 4L20 9.5',
    trash: 'M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3',
    plus: 'M12 5v14M5 12h14',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3.6 9h16.8M3.6 15h16.8M11.5 3a17 17 0 0 0 0 18M12.5 3a17 17 0 0 1 0 18',
    panel: 'M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM15 4v16M11 10l-2 2 2 2',
    clip: 'M15 7l-6.5 6.5a1.5 1.5 0 0 0 3 3L18 10a3 3 0 0 0-6-6l-6.5 6.5a4.5 4.5 0 0 0 9 9L21 13',
    file: 'M14 3v4a1 1 0 0 0 1 1h4M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z',
    download: 'M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 11l5 5 5-5M12 4v12',
    bell: 'M10 5a2 2 0 1 1 4 0 7 7 0 0 1 4 6v3a4 4 0 0 0 2 3H4a4 4 0 0 0 2-3v-3a7 7 0 0 1 4-6M9 17v1a3 3 0 0 0 6 0v-1',
    folder: 'M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2',
    photo: 'M15 8h.01M3 6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3zm0 10l5-5c.9-.9 2.1-.9 3 0l5 5m-2-2l1-1c.9-.9 2.1-.9 3 0l2 2',
    video: 'M15 10l4.6-2.3A1 1 0 0 1 21 8.6v6.8a1 1 0 0 1-1.4.9L15 14M3 8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
    check: 'M5 12l5 5L20 7',
    alert: 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4',
    refresh: 'M20 11a8 8 0 0 0-14.8-3.5M4 4v4h4M4 13a8 8 0 0 0 14.8 3.5M20 20v-4h-4',
    chat: 'M8 9h8M8 13h6M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-5l-5 3v-3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3z',
  };

  const icon = (name) => {
    const box = el('span', 'askdesk-icon');

    box.setAttribute('aria-hidden', 'true');
    box.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="${ICONS[name]}"/></svg>`;

    return box;
  };

  const pill = (text, kind = 'wait') => el('span', `askdesk-pill askdesk-pill--${kind}`, text);

  // ---------- Opening and closing, eased ----------

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const EASE = 'cubic-bezier(0.2, 0.8, 0.2, 1)';
  const phone = window.matchMedia('(max-width: 760px)');
  // Messages marked dealt with in this console (their button stays, sunk in, once they are closed)
  const markedDone = new Set();

  // A <details> opens and closes by growing and shrinking, its content fading; its state
  // (data-state) changes at once, so the arrow turns with it
  const smooth = (details) => {
    const summary = details.querySelector(':scope > summary');
    let moving = null;

    details.dataset.state = details.open ? 'open' : 'closed';

    summary.addEventListener('click', (event) => {
      event.preventDefault();

      const opening = details.dataset.state !== 'open';
      const from = details.getBoundingClientRect().height;

      details.dataset.state = opening ? 'open' : 'closed';

      if (moving) {
        moving.cancel();
      }

      details.open = true;

      if (reduced.matches) {
        details.open = opening;

        return;
      }

      // Where it ends: whole (within its own limits), or the summary alone
      const to = opening ? details.getBoundingClientRect().height : summary.getBoundingClientRect().height + (details.offsetHeight - details.clientHeight);

      details.style.overflow = 'hidden';
      moving = details.animate([{ height: `${from}px` }, { height: `${to}px` }], { duration: opening ? 260 : 200, easing: EASE });

      // What is inside fades in as it opens, out as it closes
      [...details.children].filter((child) => child !== summary).forEach((child) => {
        const fade = child.animate([{ opacity: opening ? 0 : 1 }, { opacity: opening ? 1 : 0 }], { duration: opening ? 320 : 160, easing: EASE, fill: 'both' });

        fade.finished.then(() => fade.cancel(), () => {});
      });

      const current = moving;

      current.finished.then(() => {
        details.open = opening;
        details.style.overflow = '';
        moving = null;
      }, () => {
        // Turned the other way meanwhile: the next movement ends it
      });
    });

    return details;
  };

  // Something that comes into view on a click rises and fades in
  const rise = (node) => {
    if (!reduced.matches && node && node.animate) {
      node.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: EASE });
    }

    return node;
  };

  // One text for another (the original, the translation) in the same bubble: the bubble eases to
  // its new size while the new words fade in
  const swap = (box, before, after) => {
    const from = box.getBoundingClientRect();

    before.replaceWith(after);

    if (reduced.matches) {
      return;
    }

    const to = box.getBoundingClientRect();

    box.animate([{ height: `${from.height}px`, width: `${from.width}px` }, { height: `${to.height}px`, width: `${to.width}px` }], { duration: 240, easing: EASE });
    after.animate([{ opacity: 0, filter: 'blur(2px)' }, { opacity: 1, filter: 'none' }], { duration: 280, easing: EASE });
  };

  // ---------- Layout ----------

  const bar = el('header', 'askdesk-bar');
  const title = el('h1', 'askdesk-bar__title', document.title);
  const statusSelect = el('select', 'askdesk-select');
  const languageSelect = el('select', 'askdesk-select');
  // Notifications: a bell, lit once they may be shown. Where the browser can (a phone with the console
  // added to its home screen, most computers) they come by push: with the console closed too
  const notify = button('askdesk-bell', '', () => toggleNotify());
  const presence = el('p', 'askdesk-bar__staff');

  ['online', 'busy', 'away'].forEach((status) => {
    const option = el('option', '', t[status]);

    option.value = status;
    statusSelect.append(option);
  });

  (config.languages || []).forEach((language) => {
    const option = el('option', '', language.name);

    option.value = language.tag;
    languageSelect.append(option);
  });

  statusSelect.setAttribute('aria-label', t.online);
  languageSelect.setAttribute('aria-label', t.language);
  languageSelect.hidden = !config.translate;

  // ---------- Push: the console as an app that is told, closed or not ----------

  const pushing = { possible: false, on: false, worker: null };
  // An iPhone or iPad: push only from the console added to the home screen and opened from there
  const apple = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  const paintNotify = () => {
    const granted = 'Notification' in window && Notification.permission === 'granted';
    const on = pushing.possible ? pushing.on : granted;

    notify.hidden = !pushing.possible && !(apple && !standalone) && (!('Notification' in window) || Notification.permission === 'denied');
    notify.classList.toggle('askdesk-bell--on', on);
    notify.title = on ? (pushing.possible ? t.pushOn : t.notifyOn) : t.notify;
    notify.setAttribute('aria-label', notify.title);
  };

  // A word under the bell, for a few seconds
  const bubble = (text) => {
    const word = el('div', 'askdesk-bubble', text);

    word.setAttribute('role', 'status');
    root.append(word);
    window.setTimeout(() => word.classList.add('askdesk-bubble--out'), 5200);
    window.setTimeout(() => word.remove(), 5600);
  };

  const keyOf = (base64) => {
    const raw = window.atob((base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
  };

  const b64 = (buffer) => window.btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

  const subscribe = async () => {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
      bubble(t.pushDenied);

      return;
    }

    const subscription = await pushing.worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyOf(config.push) });
    const data = await post(config.urls.pushSubscribe, {
      endpoint: subscription.endpoint,
      p256dh: b64(subscription.getKey('p256dh')),
      auth: b64(subscription.getKey('auth')),
    });

    if (data.error) {
      throw new Error(data.error);
    }

    pushing.on = true;
    bubble(t.pushOn);
  };

  const unsubscribe = async () => {
    const subscription = await pushing.worker.pushManager.getSubscription();

    if (subscription) {
      await post(config.urls.pushForget, { endpoint: subscription.endpoint }).catch(() => null);
      await subscription.unsubscribe();
    }

    pushing.on = false;
    bubble(t.pushOff);
  };

  const toggleNotify = async () => {
    // On an iPhone, in Safari: add to the home screen first
    if (!pushing.possible && apple && !standalone) {
      bubble(t.pushInstall);

      return;
    }

    if (!pushing.possible) {
      if ('Notification' in window && Notification.permission === 'default') {
        await Notification.requestPermission();
      }

      paintNotify();

      return;
    }

    notify.disabled = true;

    try {
      await (pushing.on ? unsubscribe() : subscribe());
    } catch (error) {
      bubble(t.pushFailed);
    }

    notify.disabled = false;
    paintNotify();
  };

  // The worker that shows them, and whether this browser is told already (renewed with this site's
  // key, in case the subscription was made with another or lost on the server)
  const startPush = async () => {
    if (!config.push || !config.urls.worker || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      return;
    }

    try {
      pushing.worker = await navigator.serviceWorker.register(config.urls.worker, { scope: config.scope || '/' });
      await navigator.serviceWorker.ready;
      pushing.possible = true;

      const subscription = await pushing.worker.pushManager.getSubscription();

      if (subscription && Notification.permission === 'granted') {
        pushing.on = true;
        post(config.urls.pushSubscribe, { endpoint: subscription.endpoint, p256dh: b64(subscription.getKey('p256dh')), auth: b64(subscription.getKey('auth')) }).catch(() => null);
      }
    } catch (error) {
      pushing.possible = false;
    }

    paintNotify();
  };

  // What the worker says: a push while the console is in front (its own sound), a notification clicked
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
      const data = event.data || {};

      if (data.askdesk === 'open' && data.room) {
        wanted = data.room;
        later(true);
      }
    });
  }

  // A chat asked for by the address (a notification opened the console at it): opened once it is there
  let wanted = Number((window.location.hash.match(/^#room=(\d+)$/) || [])[1]) || 0;

  if (wanted) {
    window.history.replaceState(null, '', window.location.pathname + window.location.search);
  }

  paintNotify();
  startPush();

  const tile = el('span', 'askdesk-tile');
  const heading = el('div', 'askdesk-bar__heading');

  tile.append(icon('headset'));
  heading.append(title);

  // At the top of the side: the desk's card, as a logo would be; or the site's own logo, set in the
  // desk's options (its mark alone where the side is narrow)
  const brand = el('div', 'askdesk-brand');
  const logos = config.logo || {};

  if (logos.logo) {
    const logo = el('span', 'askdesk-brand__logo');
    const full = el('img', 'askdesk-brand__full');

    full.src = logos.logo;
    full.alt = title.textContent;
    logo.append(full);

    if (logos.mark) {
      const mark = el('img', 'askdesk-brand__mark');

      mark.src = logos.mark;
      mark.alt = title.textContent;
      logo.append(mark);
    }

    brand.classList.add('askdesk-brand--logo');
    brand.append(logo);
  } else {
    brand.append(tile, heading);
  }

  // In the bar, at the far end: who one is
  // In the bar, at the far end: who one is; a click to change how one shows oneself
  const meFace = button('askdesk-me', '', () => {});

  const paintMeFace = (name, src) => {
    accountName.textContent = name || '';
    meFace.replaceChildren();
    meFace.title = `${name || ''} · ${t.profile}`;
    meFace.setAttribute('aria-label', meFace.title);

    if (src && /^(https?:|\/)/.test(src)) {
      const picture = el('img', '');

      picture.src = src;
      picture.alt = '';
      meFace.append(picture);
    } else {
      meFace.textContent = Array.from(String(name || '?').trim() || '?')[0].toUpperCase();
    }
  };

  // At the foot of the side, as an account's: whether visitors can chat now, in a line of its own;
  // under it one's face, ringed in the colour of one's status (a click: the statuses, and one's
  // profile), one's name, who else answers, the language and the bell. With the side folded away
  // they all go to the bar
  const account = el('div', 'askdesk-account');
  const accountRow = el('div', 'askdesk-account__row');
  const accountName = button('askdesk-account__name', '', () => openProfile());
  const accountText = el('span', 'askdesk-account__text');
  const avatar = el('div', 'askdesk-avatar');
  const reachRow = el('div', 'askdesk-account__reach');
  // Who answers now: their faces stacked, how many; a click lists them
  const people = el('div', 'askdesk-people');
  const peopleButton = button('askdesk-people__faces', '', () => {
    people.classList.toggle('askdesk-people--open');
    peopleButton.setAttribute('aria-expanded', String(people.classList.contains('askdesk-people--open')));
  });
  const peopleList = el('div', 'askdesk-people__list');

  peopleButton.setAttribute('aria-expanded', 'false');
  peopleButton.setAttribute('aria-haspopup', 'true');
  peopleList.setAttribute('role', 'list');
  people.append(peopleButton, peopleList);
  people.hidden = true;
  reachRow.append(people);
  document.addEventListener('pointerdown', (event) => {
    if (!people.contains(event.target)) {
      people.classList.remove('askdesk-people--open');
      peopleButton.setAttribute('aria-expanded', 'false');
    }
  });

  const faceOf = (person, className) => {
    const face = el('span', className);

    if (person.face && /^(https?:|\/)/.test(person.face)) {
      const picture = el('img', '');

      picture.src = person.face;
      picture.alt = '';
      face.append(picture);
    } else {
      face.textContent = Array.from(String(person.name || '?').trim() || '?')[0].toUpperCase();
    }

    return face;
  };

  const paintPeople = (staff) => {
    const list = Array.isArray(staff) ? staff : [];
    const key = list.map((person) => `${person.id}:${person.status}:${person.face}:${person.name}`).join('|');

    people.hidden = !list.length;

    if (people.dataset.key === key) {
      return;
    }

    people.dataset.key = key;
    peopleButton.replaceChildren(...list.slice(0, 3).map((person) => faceOf(person, 'askdesk-people__face')), el('span', 'askdesk-people__count', String(list.length)));
    peopleButton.title = `${t.staffNow}: ${list.map((person) => person.name).join(', ')}`;
    peopleButton.setAttribute('aria-label', peopleButton.title);
    peopleList.replaceChildren(el('strong', 'askdesk-people__title', `${t.staffNow} · ${list.length}`), ...list.map((person) => {
      const row = el('div', 'askdesk-people__row');

      row.setAttribute('role', 'listitem');
      row.append(faceOf(person, 'askdesk-people__face'), el('span', 'askdesk-people__name', person.name), el('i', `askdesk-menu__dot askdesk-menu__dot--${person.status}`));

      return row;
    }));
  };

  avatar.append(meFace);
  accountText.append(accountName, presence);
  accountRow.append(avatar, accountText);
  account.append(accountRow);

  paintMeFace(config.meName, config.meFace);

  notify.append(icon('bell'));
  const main = el('div', 'askdesk-main');
  const stage = el('div', 'askdesk-stage');
  const inbox = el('nav', 'askdesk-inbox');
  const splitter = el('div', 'askdesk-splitter');
  const pane = el('section', 'askdesk-room');
  // No chat open: one's dashboard (0.5.0). For now the sources it will draw from and whether one is
  // connected to each; the widgets come next
  const empty = el('div', 'askdesk-room__empty');
  const home = el('div', 'askdesk-home');
  let homeAt = 0;

  // The dashboard itself (0.5.1): its script and styles fetched the first time no chat is open, then
  // mounted in the empty card, where it stays while chats come and go
  let board = null;

  const startBoard = () => {
    if (board || !config.board || !config.urls.board) {
      return Boolean(board);
    }

    board = 'loading';

    const style = el('link');
    const script = el('script');

    style.rel = 'stylesheet';
    style.href = config.board.style;
    script.src = config.board.script;
    script.async = true;
    script.onload = () => {
      if (!window.AskdeskBoard) {
        board = null;

        return;
      }

      empty.classList.add('askdesk-room__empty--board');
      home.replaceChildren();
      board = window.AskdeskBoard.mount(home, { urls: config.urls, token: config.token, language: config.board.language });
    };
    script.onerror = () => { board = null; paintHome(); };
    document.head.append(style, script);

    return true;
  };

  const paintHome = async () => {
    if (startBoard() || !config.urls.board || Date.now() - homeAt < 30000) {
      return;
    }

    homeAt = Date.now();

    try {
      const data = await post(config.urls.board, {});
      const sources = el('ul', 'askdesk-home__sources');

      (data.sources || []).forEach((source) => {
        const row = el('li', `askdesk-home__source${source.connected ? ' is-on' : ''}`);
        const what = el('div', 'askdesk-home__what');

        what.append(el('b', '', source.title));

        if (!source.ready) {
          what.append(el('span', '', t.homeNotReady));
        } else if (source.connected) {
          what.append(el('span', '', t.homeConnected.replace('%s', source.account)));
        }

        row.append(el('i', 'askdesk-home__dot'), what);

        const link = source.ready && !source.connected ? source.connect : source.manage;

        if (link) {
          const go = el('a', `askdesk-home__go${source.connected ? '' : ' is-main'}`, source.connected ? t.homeManage : t.homeConnect.replace('%s', source.title));

          go.href = link;
          row.append(go);
        }

        sources.append(row);
      });

      const parts = [el('h2', 'askdesk-home__title', t.homeTitle), el('p', 'askdesk-home__lead', t.homeEmpty), el('h3', 'askdesk-home__head', t.homeSources)];

      parts.push((data.sources || []).length ? sources : el('p', 'askdesk-home__none', t.homeNone));

      if ((data.widgets || []).length) {
        parts.push(el('p', 'askdesk-home__none', t.homeWidgets.replace('%d', String(data.widgets.length))));
      }

      home.replaceChildren(...parts);
    } catch (error) {
      homeAt = 0;
    }
  };

  home.append(el('p', 'askdesk-home__lead', t.pick));
  empty.append(home);
  const opener = button('askdesk-button askdesk-button--text askdesk-bar__inbox', '', () => setSide(state.lastSide || SIDE_DEFAULT));

  opener.append(icon('sidebar'));
  opener.setAttribute('aria-label', t.showInbox);
  opener.title = t.showInbox;
  // AI search of the site's pages, for the people answering: at the top of the side panel, the
  // answers below it
  const search = el('form', 'askdesk-search');
  const searchField = el('input', 'askdesk-search__field');
  const results = el('div', 'askdesk-search__results');

  searchField.type = 'search';
  searchField.placeholder = t.searchPlaceholder;
  searchField.setAttribute('aria-label', t.search);
  search.setAttribute('role', 'search');
  search.append(icon('sparkles'), searchField);
  results.hidden = true;
  // Whether visitors asking for a person get a chat now, and why not (hours, a day closed, no one online)
  const reach = el('span', 'askdesk-reach');

  reach.hidden = true;
  reach.setAttribute('role', 'status');
  // One's own status: a large pill, its colour the status's, that opens a menu of the statuses
  const presenceButton = el('div', 'askdesk-presence');
  const presenceText = el('span', 'askdesk-presence__text', t.online);
  const presenceTrigger = button('askdesk-presence__button', '', () => {});

  // A list to choose from, drawn here: a button, and below it a menu that grows down, the current one
  // ticked (the list kept, unseen, as the value: its change is what happens on a choice)
  const dropdown = (host, trigger, select, decorate = () => {}, extras = []) => {
    const menu = el('div', 'askdesk-menu');

    menu.setAttribute('role', 'menu');
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-expanded', 'false');
    select.hidden = true;
    host.classList.add('askdesk-dropdown');

    const close = () => {
      host.classList.remove('askdesk-dropdown--open');
      trigger.setAttribute('aria-expanded', 'false');
    };

    const open = () => {
      menu.replaceChildren();
      [...select.options].forEach((option) => {
        const current = option.value === select.value;
        const item = button(`askdesk-menu__item${current ? ' askdesk-menu__item--on' : ''}`, '', () => {
          close();
          trigger.focus({ preventScroll: true });

          if (!current) {
            select.value = option.value;
            select.dispatchEvent(new Event('change'));
          }
        });

        item.setAttribute('role', 'menuitemradio');
        item.setAttribute('aria-checked', String(current));
        decorate(item, option);
        item.append(el('span', 'askdesk-menu__label', option.textContent), icon('check'));
        menu.append(item);
      });

      // Other things to do from the same button, under a line
      extras.forEach((extra, index) => {
        if (index === 0) {
          menu.append(el('hr', 'askdesk-menu__line'));
        }

        // A row of its own drawing (a switch, a list that unfolds), or one that does something and closes
        if (extra.draw) {
          const drawn = extra.draw(close);

          if (drawn) {
            menu.append(...[].concat(drawn));
          }

          return;
        }

        const item = button('askdesk-menu__item askdesk-menu__item--action', '', () => {
          close();
          extra.run();
        });

        item.setAttribute('role', 'menuitem');
        item.append(icon(extra.icon), el('span', 'askdesk-menu__label', extra.label));
        menu.append(item);
      });

      host.classList.add('askdesk-dropdown--open');
      trigger.setAttribute('aria-expanded', 'true');
      (menu.querySelector('.askdesk-menu__item--on') || menu.firstChild)?.focus({ preventScroll: true });
    };

    trigger.addEventListener('click', () => (host.classList.contains('askdesk-dropdown--open') ? close() : open()));

    document.addEventListener('pointerdown', (event) => {
      if (!host.contains(event.target)) {
        close();
      }
    });

    host.addEventListener('keydown', (event) => {
      const items = [...menu.querySelectorAll('.askdesk-menu__item')];
      const at = items.indexOf(document.activeElement);

      if (event.key === 'Escape' && host.classList.contains('askdesk-dropdown--open')) {
        close();
        trigger.focus();
      } else if (host.classList.contains('askdesk-dropdown--open') && (event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
        event.preventDefault();
        items[(at + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
      }
    });

    host.append(menu, select);
  };

  // The language: a globe
  const globe = el('div', 'askdesk-globe');
  const globeButton = button('askdesk-globe__button', '', () => {});

  presenceTrigger.append(el('i', 'askdesk-presence__dot'), presenceText, icon('down'));
  presenceButton.append(presenceTrigger);
  // Each status in the menu with its own coloured dot; the menu is the face's, one's profile after them
  dropdown(avatar, meFace, statusSelect, (item, option) => {
    item.append(el('i', `askdesk-menu__dot askdesk-menu__dot--${option.value}`));
  }, [
    // Notifications on this device: a switch (push where it can, the browser's own otherwise)
    {
      draw: () => {
        if (notify.hidden) {
          return null;
        }

        const knob = el('span', 'askdesk-switch');
        const row = button('askdesk-menu__item askdesk-menu__item--action askdesk-menu__switch', '', async () => {
          await toggleNotify();
          paint();
        });
        const paint = () => {
          const on = notify.classList.contains('askdesk-bell--on');

          row.setAttribute('aria-checked', String(on));
          knob.classList.toggle('askdesk-switch--on', on);
        };

        row.setAttribute('role', 'menuitemcheckbox');
        row.append(icon('bell'), el('span', 'askdesk-menu__label', t.notifications), knob);
        paint();

        return row;
      },
    },
    // The language one works in: the current one, the others unfolding under it
    {
      draw: (close) => {
        if (!config.translate) {
          return null;
        }

        const current = languageSelect.options[languageSelect.selectedIndex];
        const list = el('div', 'askdesk-menu__sub');
        const head = button('askdesk-menu__item askdesk-menu__item--action askdesk-menu__fold', '', () => {
          list.hidden = !list.hidden;
          head.setAttribute('aria-expanded', String(!list.hidden));
        });

        list.hidden = true;
        head.setAttribute('aria-expanded', 'false');
        head.append(icon('globe'), el('span', 'askdesk-menu__label', t.language), el('span', 'askdesk-menu__value', current ? current.textContent : ''), icon('down'));
        [...languageSelect.options].forEach((option) => {
          const on = option.value === languageSelect.value;
          const item = button(`askdesk-menu__item${on ? ' askdesk-menu__item--on' : ''}`, '', () => {
            close();

            if (!on) {
              languageSelect.value = option.value;
              languageSelect.dispatchEvent(new Event('change'));
            }
          });

          item.setAttribute('role', 'menuitemradio');
          item.setAttribute('aria-checked', String(on));
          item.append(el('span', 'askdesk-menu__label', option.textContent), icon('check'));
          list.append(item);
        });

        return [head, list];
      },
    },
    { label: t.profile, icon: 'user', run: () => openProfile() },
  ]);
  globeButton.append(icon('globe'));
  globe.append(globeButton);
  dropdown(globe, globeButton, languageSelect);
  globe.hidden = !config.translate;

  const paintPresence = (status) => {
    const option = [...statusSelect.options].find((each) => each.value === status);

    presenceButton.dataset.status = status;
    avatar.dataset.status = status;
    presence.textContent = option ? option.textContent : status;
    root.dataset.status = status;
    meFace.title = `${option ? option.textContent : status} · ${t.profile}`;
    presenceText.textContent = option ? option.textContent : status;
  };

  const paintLanguage = () => {
    const option = languageSelect.options[languageSelect.selectedIndex];

    globeButton.title = option ? `${t.language}: ${option.textContent}` : t.language;
    globeButton.setAttribute('aria-label', globeButton.title);
  };

  paintPresence('online');
  paintLanguage();

  // Behind the side, a deep wash of the status's colours drifting slowly (soft gradients moved as a
  // whole, nothing redrawn); the work beside it stays white
  const wash = el('span', 'askdesk__wash');

  wash.setAttribute('aria-hidden', 'true');
  // No bar above the work while the side is there: its things are at the side's foot (above). The bar
  // shows with the side folded away, and they come to it
  const barSpace = el('span', 'askdesk-bar__space');

  reachRow.prepend(reach);
  account.prepend(reachRow);
  bar.append(opener);

  const paintReach = (visitors) => {
    if (!visitors || !Array.isArray(visitors.agents)) {
      reach.hidden = true;

      return;
    }

    const agents = visitors.agents;
    const why = [];

    if (!agents.length) {
      why.push(t.reachNoAgent);
    } else if (!agents.some((agent) => agent.hours)) {
      agents.forEach((agent) => why.push((agent.closedToday ? t.reachClosedDay : t.reachOutside).replace('%1$s', agent.title).replace('%2$s', `${agent.now} ${agent.zone}`)));
    }

    if (!visitors.online) {
      why.push(t.reachNoOne);
    }

    const open = !why.length;
    const text = open ? t.reachOpen : t.reachAway;

    reach.hidden = false;
    reach.className = `askdesk-reach askdesk-reach--${open ? 'open' : 'away'}`;
    reach.title = open ? agents.map((agent) => `${agent.title} · ${agent.now} ${agent.zone}`).join('\n') : why.join('\n');

    if (reach.dataset.text !== `${text}|${reach.title}`) {
      reach.dataset.text = `${text}|${reach.title}`;
      reach.replaceChildren(el('i', ''), el('span', '', text), ...(open ? [] : [el('small', '', why[0])]));
    }
  };

  splitter.tabIndex = 0;
  splitter.setAttribute('role', 'separator');
  splitter.setAttribute('aria-orientation', 'vertical');
  splitter.setAttribute('aria-label', t.resize);
  splitter.title = t.resize;

  pane.append(empty);
  stage.append(bar, pane);
  paintHome();

  // What scrolls fades at an edge only where there is more beyond it: at the top once scrolled down,
  // at the bottom until the end is reached
  const fades = (box) => {
    const paint = () => {
      box.classList.toggle('askdesk-fade--top', box.scrollTop > 2);
      box.classList.toggle('askdesk-fade--bottom', box.scrollTop + box.clientHeight < box.scrollHeight - 2);
    };

    box.addEventListener('scroll', paint, { passive: true });

    if ('ResizeObserver' in window) {
      new ResizeObserver(paint).observe(box);
    }

    new MutationObserver(() => window.requestAnimationFrame(paint)).observe(box, { childList: true, subtree: true });
    paint();
  };

  fades(inbox);
  main.append(wash, inbox, splitter, stage);
  root.replaceChildren(main);

  // The console is the whole window, like an application
  document.documentElement.classList.add('askdesk-app');

  // What is visible of it: on a phone the keyboard takes the bottom of the screen, and the console
  // keeps to what is left above it (its box at the top of what shows, its height that of it), so the
  // box to write in stays in sight and nothing is pushed aside
  const viewport = window.visualViewport;

  if (viewport) {
    const fit = () => {
      root.style.setProperty('--askdesk-top', `${Math.round(viewport.offsetTop)}px`);
      root.style.setProperty('--askdesk-height', `${Math.round(viewport.height)}px`);
    };

    viewport.addEventListener('resize', fit);
    viewport.addEventListener('scroll', fit);
    fit();
  }

  // ---------- State ----------

  const state = {
    lastSide: 0,
    me: null,
    rooms: [],
    open: 0,
    after: 0,
    detail: null,
    messages: [],
    unread: 0,
    heard: new Set(),
    first: true,
  };

  // ---------- The inbox's width: dragged, at most 30% of the window; narrower than 100px it folds away ----------

  const SIDE = 'askdesk:side';
  const SIDE_DEFAULT = 280;
  // Its narrowest: anything narrower folds it away
  const SIDE_MIN = 267;

  const setSide = (width, keep = true) => {
    const max = Math.max(SIDE_MIN, Math.round(main.clientWidth * 0.3));
    const folded = width < SIDE_MIN;
    const size = folded ? 0 : Math.min(Math.round(width), max);

    if (!folded) {
      state.lastSide = size;
    }

    main.style.setProperty('--askdesk-side', `${size}px`);
    main.classList.toggle('askdesk-main--folded', folded);
    opener.hidden = !folded;

    if (folded && avatar.parentElement !== bar) {
      bar.append(reach, barSpace, avatar);
    } else if (!folded && avatar.parentElement !== accountRow) {
      reachRow.prepend(reach);
      accountRow.prepend(avatar);
    }

    brand.classList.toggle('askdesk-brand--narrow', size < 200);
    splitter.setAttribute('aria-valuenow', String(size));
    splitter.setAttribute('aria-valuemax', String(max));

    if (keep) {
      try {
        window.localStorage.setItem(SIDE, String(size));
      } catch (error) {
        // Not remembered
      }
    }
  };

  splitter.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    splitter.setPointerCapture(event.pointerId);
    root.classList.add('askdesk--dragging');

    const move = (moved) => setSide(moved.clientX - main.getBoundingClientRect().left, false);
    const stop = (ended) => {
      splitter.removeEventListener('pointermove', move);
      splitter.removeEventListener('pointerup', stop);
      splitter.removeEventListener('pointercancel', stop);
      root.classList.remove('askdesk--dragging');
      setSide(ended.clientX - main.getBoundingClientRect().left);
    };

    splitter.addEventListener('pointermove', move);
    splitter.addEventListener('pointerup', stop);
    splitter.addEventListener('pointercancel', stop);
  });

  splitter.addEventListener('keydown', (event) => {
    const now = inbox.getBoundingClientRect().width;

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      setSide(now + (event.key === 'ArrowLeft' ? -24 : 24));
    }
  });

  window.addEventListener('resize', () => {
    if (!main.classList.contains('askdesk-main--folded')) {
      setSide(state.lastSide || SIDE_DEFAULT, false);
    }
  });

  (() => {
    let kept = SIDE_DEFAULT;

    try {
      const stored = window.localStorage.getItem(SIDE);

      kept = stored === null ? SIDE_DEFAULT : Number(stored);

      // Kept narrower than it may be now (before 0.3.4): at its narrowest instead; folded stays folded
      kept = kept > 0 && kept < SIDE_MIN ? SIDE_MIN : kept;
    } catch (error) {
      // The default
    }

    state.lastSide = kept >= SIDE_MIN ? kept : SIDE_DEFAULT;
    setSide(Number.isFinite(kept) ? kept : SIDE_DEFAULT, false);
  })();

  // From the next frame on, folding eases (not as the page opens)
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => root.classList.add('askdesk--ready')));

  let timer = 0;
  let polling = false;

  const later = (soon = false) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(poll, soon ? 200 : document.hidden ? 8000 : 2000);
  };

  // ---------- Ringing ----------

  let audio = null;

  const ring = () => {
    if (!config.sound) {
      return;
    }

    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();

      const tone = audio.createOscillator();
      const gain = audio.createGain();

      tone.frequency.value = 880;
      gain.gain.setValueAtTime(0.0001, audio.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.2, audio.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + 0.4);
      tone.connect(gain).connect(audio.destination);
      tone.start();
      tone.stop(audio.currentTime + 0.45);
    } catch (error) {
      // No sound here
    }
  };

  // Browsers let a page make sounds once it was touched
  document.addEventListener('pointerdown', () => {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();

      if (audio.state === 'suspended') {
        audio.resume();
      }
    } catch (error) {
      // No sound here
    }
  }, { once: true });

  const tell = (text) => {
    ring();

    if (document.hidden && !pushing.on && 'Notification' in window && Notification.permission === 'granted') {
      try {
        const note = new Notification(document.title, { body: text, tag: 'askdesk' });

        note.onclick = () => {
          window.focus();
          note.close();
        };
      } catch (error) {
        // Not shown
      }
    }
  };

  const paintTitle = () => {
    const count = state.rooms.reduce((sum, room) => sum + (room.status === 'waiting' ? 1 : 0) + (room.staff === config.me || !room.staff ? room.unread : 0), 0);

    document.title = (count ? `(${count}) ` : '') + title.textContent;
  };

  // ---------- The inbox ----------

  const groups = [
    ['waiting', (room) => room.status === 'waiting'],
    ['mine', (room) => room.status === 'active' && room.staff === config.me],
    ['active', (room) => room.status === 'active' && room.staff !== config.me],
    ['message', (room) => room.status === 'message'],
    ['closed', (room) => room.status === 'closed'],
  ];

  // How long a visitor has waited for an answer
  const waited = (iso) => {
    const minutes = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));

    if (!iso || minutes < 1) {
      return t.justNow;
    }

    return minutes < 60 ? t.minutes.replace('%s', minutes) : t.hours.replace('%s', Math.floor(minutes / 60));
  };

  // The inbox shows everything, or only the visitors waiting for an answer, longest first
  const FILTER = 'askdesk:filter';

  state.filter = (() => {
    try {
      return window.localStorage.getItem(FILTER) === 'awaiting' ? 'awaiting' : 'all';
    } catch (error) {
      return 'all';
    }
  })();

  const setFilter = (filter) => {
    // The white pill behind the tabs slides from the one left to the one chosen
    state.pillFrom = state.filter;
    state.filter = filter;

    try {
      window.localStorage.setItem(FILTER, filter);
    } catch (error) {
      // Not remembered
    }

    paintInbox();

    if (!reduced.matches) {
      inbox.querySelectorAll('.askdesk-item').forEach((item, index) => {
        item.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 240, delay: Math.min(index, 8) * 30, easing: EASE, fill: 'backwards' });
      });
    }
  };

  // Which chats were waiting at the last look, so a new one lights up once (none on the first look);
  // when each new one came, and when the count last rang
  let knownWaiting = null;
  const lit = new Map();
  let rangAt = 0;

  // The chats that ended: folded away unless opened, remembered
  const ENDED = 'askdesk:ended';

  state.endedOpen = (() => {
    try {
      return window.localStorage.getItem(ENDED) === '1';
    } catch (error) {
      return false;
    }
  })();

  const paintInbox = () => {
    inbox.replaceChildren(brand);

    const awaiting = state.rooms.filter((room) => room.awaiting);
    const waitingNow = new Set(awaiting.filter((room) => room.status !== 'closed').map((room) => `${room.id}|${room.since}`));
    const fresh = knownWaiting ? new Set([...waitingNow].filter((key) => !knownWaiting.has(key))) : new Set();
    const now = Date.now();

    knownWaiting = waitingNow;

    // The list is drawn again at every look: what is still playing goes on from where it was
    fresh.forEach((key) => lit.set(key, now));
    lit.forEach((at, key) => {
      if (now - at > 2600 || !waitingNow.has(key)) {
        lit.delete(key);
      }
    });

    if (fresh.size) {
      rangAt = now;
    }

    const ringFor = now - rangAt < 2400 ? now - rangAt : -1;

    // The rows new this time, to slide in once they are in place
    const arrived = [];
    const filters = el('div', 'askdesk-filters');
    const slider = el('span', `askdesk-filters__pill askdesk-filters__pill--${state.pillFrom || state.filter}`);

    slider.setAttribute('aria-hidden', 'true');
    filters.setAttribute('role', 'tablist');
    filters.append(slider);

    if (state.pillFrom && state.pillFrom !== state.filter) {
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
        slider.className = `askdesk-filters__pill askdesk-filters__pill--${state.filter}`;
      }));
    }

    state.pillFrom = '';

    [['all', t.all, state.rooms.length], ['awaiting', t.awaiting, awaiting.length]].forEach(([name, label, count]) => {
      const chip = button(`askdesk-filter${state.filter === name ? ' askdesk-filter--on' : ''}${name === 'awaiting' && count ? ' askdesk-filter--alert' : ''}`, '', () => setFilter(name));

      chip.setAttribute('role', 'tab');
      chip.setAttribute('aria-selected', String(state.filter === name));
      const total = el('b', '', String(count));

      chip.append(el('span', '', label), total);

      // Someone new waiting: the count grows with a bell beside it that swings, then eases back.
      // Drawn as a layer over the count, so nothing beside it moves
      if (name === 'awaiting' && ringFor >= 0 && !reduced.matches) {
        const ring = el('span', 'askdesk-filter__ring');
        const bell = el('span', 'askdesk-filter__bell');

        ring.setAttribute('aria-hidden', 'true');
        bell.append(icon('bell'));
        ring.append(bell, el('span', '', String(count)));
        [ring, bell, bell.firstChild].forEach((part) => {
          part.style.animationDelay = `-${ringFor}ms`;
        });
        total.append(ring);
        total.classList.add('askdesk-filter__total--ringing');
        ring.addEventListener('animationend', (event) => {
          if (event.target === ring) {
            ring.remove();
            total.classList.remove('askdesk-filter__total--ringing');
          }
        });
      }
      filters.append(chip);
    });

    inbox.append(filters);

    const visible = state.filter === 'awaiting'
      ? [['awaiting', () => true, [...awaiting].sort((a, b) => String(a.since).localeCompare(String(b.since)))]]
      : groups.map(([name, test]) => [name, test, state.rooms]);

    visible.forEach(([name, test, pool]) => {
      const rooms = pool.filter(test);

      if (!rooms.length && name !== 'waiting' && name !== 'awaiting') {
        return;
      }

      const section = el('section', `askdesk-group askdesk-group--${name}`);
      const heading = el('h2', 'askdesk-group__title', name === 'awaiting' ? t.awaitingNote : `${t[name]} · ${rooms.length}`);
      // Nobody waiting to be taken: the heading alone, with its nought
      const empty = !rooms.length && name === 'waiting';

      if (name === 'closed') {
        // The ended ones, folded: what needs doing comes first
        const fold = button(`askdesk-group__fold${state.endedOpen ? ' askdesk-group__fold--open' : ''}`, '', () => {
          state.endedOpen = !state.endedOpen;

          try {
            window.localStorage.setItem(ENDED, state.endedOpen ? '1' : '0');
          } catch (error) {
            // Not remembered
          }

          paintInbox();
        });

        fold.setAttribute('aria-expanded', String(state.endedOpen));
        fold.append(el('span', '', `${t[name]} · ${rooms.length}`), icon('down'));
        heading.replaceChildren(fold);
      } else if (empty) {
        heading.textContent = t[name];
        heading.append(el('span', 'askdesk-group__count', '0'));
      }

      section.append(heading);

      if (name === 'closed' && !state.endedOpen) {
        inbox.append(section);

        return;
      }

      rooms.forEach((room) => {
        // Waiting for an answer: a red light by the face, and a new one lights the whole row up once
        const ringing = room.awaiting && room.status !== 'closed';
        const litAt = lit.get(`${room.id}|${room.since}`);
        const isNew = ringing && litAt !== undefined && !reduced.matches;
        const item = button(`askdesk-item${room.id === state.open ? ' askdesk-item--open' : ''}${ringing ? ' askdesk-item--ringing' : ''}${isNew ? ' askdesk-item--fresh' : ''}`, '', () => openRoom(room.id));

        if (isNew) {
          item.style.animationDelay = `-${now - litAt}ms`;

          // Slid in only the first time it is drawn
          if (litAt === now) {
            arrived.push(item);
          }
        }
        const top = el('span', 'askdesk-item__top');
        const name = el('strong', 'askdesk-item__name', room.name || `${t.visitor} #${room.id}`);

        if (room.company) {
          name.title = room.company;
        }

        // Right of the name: how long they have waited, or when it last moved
        const when = el('time', `askdesk-item__time${ringing ? ' askdesk-item__time--wait' : ''}`, ringing ? `${t.awaiting} · ${waited(room.since)}` : timeOf(room.modified));

        top.append(name, when);

        // A face for each visitor, as the items of a menu have their icon
        const face = el('span', `askdesk-item__face${room.member ? ' askdesk-item__face--member' : ''}`, Array.from((room.name || '').trim() || '#')[0].toUpperCase());
        const words = el('span', 'askdesk-item__words');

        face.setAttribute('aria-hidden', 'true');

        if (ringing) {
          face.append(el('span', 'askdesk-item__light'));
        }

        // Then what was said last; then, quietly, in a line of small words, the rest
        const line = el('span', 'askdesk-item__line');
        const meta = el('span', 'askdesk-item__meta');
        const facts = [
          state.filter === 'awaiting' ? (room.status === 'message' ? t.message : t[groups.find(([, test]) => test(room))?.[0] || ''] || '') : '',
          room.language,
          room.member ? t.member : '',
          room.status === 'active' && room.staff !== config.me && room.staffName ? t.answeredBy.replace('%s', room.staffName) : '',
        ].filter(Boolean);

        line.append(el('span', 'askdesk-item__preview', room.preview || (room.status === 'waiting' ? t.newChat : '')));

        if (room.awaiting && room.status === 'closed') {
          meta.append(pill(t.endedUnanswered, 'wait'));
        }

        meta.append(el('span', '', facts.join(' · ')));
        words.append(top, line, meta);
        item.append(face, words);

        if (room.unread && (room.staff === config.me || !room.staff)) {
          item.append(el('span', 'askdesk-badge', String(room.unread)));
        }

        // A chat waiting: taken from the list at once, without opening it first
        if (room.status === 'waiting') {
          const row = el('div', 'askdesk-item-row');
          const take = button('askdesk-button askdesk-button--primary askdesk-item__take', t.take, async () => {
            take.disabled = true;
            openRoom(room.id);

            try {
              const data = await post(config.urls.take, { room: room.id });

              if (data.error) {
                window.alert(data.error);
              } else {
                field.focus();
              }
            } catch (error) {
              window.alert(t.error);
            }

            later(true);
          });

          row.append(item, take);
          section.append(row);

          return;
        }

        section.append(item);
      });

      inbox.append(section);
    });

    // The blue bar by the chat open: one bar for the inbox, sliding from where it was to the chat now
    // open (in place at once when the list is merely drawn again)
    const open = inbox.querySelector('.askdesk-item--open');
    const bar = el('span', 'askdesk-inbox__bar');

    bar.setAttribute('aria-hidden', 'true');
    inbox.append(bar, account);

    if (open) {
      const where = () => {
        const box = open.getBoundingClientRect();
        const frame = inbox.getBoundingClientRect();

        return { top: box.top - frame.top + inbox.scrollTop + 8, height: Math.max(0, box.height - 16) };
      };
      const to = where();
      const from = state.barAt && state.barAt.open !== state.open ? state.barAt : null;

      bar.style.transform = `translateY(${(from || to).top}px)`;
      bar.style.blockSize = `${(from || to).height}px`;
      bar.classList.add('askdesk-inbox__bar--on');

      if (from && !reduced.matches) {
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
          bar.classList.add('askdesk-inbox__bar--moving');
          bar.style.transform = `translateY(${to.top}px)`;
          bar.style.blockSize = `${to.height}px`;
        }));
      }

      state.barAt = { ...to, open: state.open };
    } else {
      state.barAt = null;
    }

    // A new one makes room for itself and slides in from the side (its red then fades, in CSS)
    arrived.forEach((item) => {
      const node = item.parentElement && item.parentElement.classList.contains('askdesk-item-row') ? item.parentElement : item;
      const height = node.offsetHeight;
      const after = getComputedStyle(node).marginBlockEnd;

      node.animate([
        { blockSize: '0px', marginBlockEnd: '0px', opacity: 0, transform: 'translateX(-24px)', overflow: 'hidden' },
        { blockSize: `${height}px`, marginBlockEnd: after, opacity: 1, transform: 'none', overflow: 'hidden' },
      ], { duration: 500, easing: 'cubic-bezier(0.2, 0.9, 0.3, 1.05)' });
    });

    paintTitle();
  };

  // ---------- One chat ----------

  // The head across; below it the conversation, a column in the middle, with what came before at
  // its top and the box to write in at its foot; beside it, room for suggested replies
  const head = el('header', 'askdesk-room__head');
  const body = el('div', 'askdesk-room__body');
  const column = el('div', 'askdesk-room__column');
  const log = el('div', 'askdesk-log');

  fades(log);
  const thread = el('div', 'askdesk-thread');
  const intro = el('div', 'askdesk-intro');
  const list = el('div', 'askdesk-list');
  const aside = el('aside', 'askdesk-room__aside');
  // What scrolls in the side is a layer within it, the side itself keeping its corners and its
  // shadow; no fading edges there, the headings held at its top and bottom saying there is more
  const asideScroll = el('div', 'askdesk-room__aside-scroll');

  aside.append(asideScroll);
  const composer = el('form', 'askdesk-composer');
  const box = el('div', 'askdesk-composer__box');
  const field = el('textarea', 'askdesk-composer__field');
  const send = el('button', 'askdesk-send');
  const sendLabel = el('span', 'askdesk-send__label', t.send);
  const note = el('p', 'askdesk-composer__note');

  // The line under the box: what came of sending, rising in when it says something new
  const say = (text) => {
    const changed = note.textContent !== text;

    note.textContent = text;

    if (text && changed) {
      rise(note);
    }
  };

  field.rows = 1;
  field.placeholder = t.placeholder;
  send.type = 'submit';
  send.setAttribute('aria-label', t.send);
  // At rest only the Enter key's sign; pointed at, the blue button with its arrow and its word
  const sendRest = icon('enter');
  const sendGo = icon('up');

  sendRest.classList.add('askdesk-send__rest');
  sendGo.classList.add('askdesk-send__go');
  send.append(sendRest, sendGo, sendLabel);
  // A file to send: pictures (1 MB), videos and documents (10 MB), kept 30 days
  const attach = button('askdesk-attach', '', () => attachInput.click());
  const attachInput = el('input');

  attach.append(icon('clip'));
  attach.title = t.attach;
  attach.setAttribute('aria-label', t.attach);
  attachInput.type = 'file';
  attachInput.hidden = true;
  attachInput.accept = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip';
  // Stickers: the packs of the site, asked for when first opened
  const stickerButton = button('askdesk-attach askdesk-sticker-button', '', () => toggleStickers());
  const stickerMenu = el('div', 'askdesk-sticker-menu');
  let stickerPacks = null;

  stickerButton.append(el('span', 'askdesk-icon'));
  stickerButton.firstChild.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 18 0 9 9 0 1 0-18 0M9 10h.01M15 10h.01M9.5 15a3.5 3.5 0 0 0 5 0"/></svg>';
  stickerButton.title = t.stickers;
  stickerButton.setAttribute('aria-label', t.stickers);
  stickerButton.setAttribute('aria-expanded', 'false');
  stickerMenu.hidden = true;
  stickerMenu.setAttribute('role', 'dialog');
  stickerMenu.setAttribute('aria-label', t.stickers);
  box.append(attach, attachInput, stickerButton, field, send, stickerMenu);

  // Phrases (0.1.3): those pinned on a row inside the box, a click puts one in; the rest, and one's
  // own to keep, delete or pin, in a list that opens from the end of the row
  const phraseRow = el('div', 'askdesk-phrases');
  const phrasePills = el('div', 'askdesk-phrases__pills');
  const phraseMore = button('askdesk-phrases__more', '', () => togglePhrases());
  const phraseMenu = el('div', 'askdesk-phrase-menu');
  const phraseTabs = el('div', 'askdesk-phrase-menu__tabs');
  const phraseFind = el('input', 'askdesk-phrase-menu__find');
  const phraseList = el('div', 'askdesk-phrase-menu__list');
  const phraseKeep = button('askdesk-phrase-menu__keep', '', () => keepPhrase());
  let phrases = [];
  let phraseTab = 'all';

  phraseMore.append(icon('quote'), el('span', '', t.phrasesMore));
  phraseMore.setAttribute('aria-haspopup', 'dialog');
  phraseMore.setAttribute('aria-expanded', 'false');
  phraseFind.type = 'search';
  phraseFind.placeholder = t.phrasesSearch;
  phraseFind.setAttribute('aria-label', t.phrasesSearch);
  phraseKeep.append(icon('plus'), el('span', '', t.phraseSaveMine));
  phraseMenu.hidden = true;
  phraseMenu.setAttribute('role', 'dialog');
  phraseMenu.setAttribute('aria-label', t.phrases);
  phraseMenu.append(phraseTabs, phraseFind, phraseList, phraseKeep);
  phraseRow.append(phrasePills, phraseMore);
  phraseRow.hidden = true;
  box.prepend(phraseRow);
  box.append(phraseMenu);

  // A phrase into the box, where the cursor is, ready to change before it is sent
  const putPhrase = (text) => {
    const start = field.selectionStart ?? field.value.length;
    const end = field.selectionEnd ?? field.value.length;
    const before = field.value.slice(0, start);
    const glue = before && !/\s$/.test(before) ? ' ' : '';

    field.value = `${before}${glue}${text}${field.value.slice(end)}`;
    field.focus();
    field.setSelectionRange(start + glue.length + text.length, start + glue.length + text.length);
    field.dispatchEvent(new Event('input'));
  };

  const paintPhrases = () => {
    const pinned = phrases.filter((item) => item.pinned);

    phraseRow.hidden = !phrases.length;
    phrasePills.replaceChildren(...pinned.map((item) => {
      const pill = button(`askdesk-phrases__pill${item.mine ? ' askdesk-phrases__pill--mine' : ''}`, item.title, () => putPhrase(item.text));

      pill.title = item.text;

      return pill;
    }));

    phraseTabs.replaceChildren(...[['all', t.phrasesAll], ['shared', t.phrasesShared], ['mine', t.phrasesMine]].map(([name, label]) => {
      const tab = button(`askdesk-phrase-menu__tab${phraseTab === name ? ' askdesk-phrase-menu__tab--on' : ''}`, label, () => {
        phraseTab = name;
        paintPhrases();
      });

      tab.setAttribute('aria-pressed', String(phraseTab === name));

      return tab;
    }));

    const find = phraseFind.value.trim().toLowerCase();
    const shown = phrases.filter((item) => (phraseTab === 'all' || (phraseTab === 'mine') === item.mine)
      && (!find || `${item.title} ${item.text}`.toLowerCase().includes(find)));

    phraseList.replaceChildren(...(shown.length ? shown.map((item) => {
      const row = el('div', 'askdesk-phrase-menu__item');
      const use = button('askdesk-phrase-menu__use', '', () => {
        closePhrases();
        putPhrase(item.text);
      });

      use.append(el('strong', '', item.title), el('span', '', item.text));
      row.append(use);

      // One's own: pinned to the row or not, deleted
      if (item.mine) {
        const pin = button(`askdesk-phrase-menu__tool${item.pinned ? ' askdesk-phrase-menu__tool--on' : ''}`, '', () => savePhrase({ ...item, pinned: !item.pinned }));
        const drop = button('askdesk-phrase-menu__tool askdesk-phrase-menu__tool--drop', '', () => dropPhrase(item));

        pin.append(icon('pin'));
        pin.title = t.phrasePin;
        pin.setAttribute('aria-label', t.phrasePin);
        pin.setAttribute('aria-pressed', String(item.pinned));
        drop.append(icon('trash'));
        drop.title = t.phraseDelete;
        drop.setAttribute('aria-label', `${t.phraseDelete} ${item.title}`);
        row.append(pin, drop);
      }

      return row;
    }) : [el('p', 'askdesk-phrase-menu__none', t.phrasesNone)]));

    phraseKeep.disabled = !field.value.trim();
  };

  const loadPhrases = async () => {
    try {
      const data = await post(config.urls.phrases, {});

      phrases = Array.isArray(data.phrases) ? data.phrases : [];
    } catch (error) {
      phrases = [];
    }

    paintPhrases();
  };

  const savePhrase = async (item) => {
    try {
      const data = await post(config.urls.phraseSave, { id: item.id || 0, title: item.title || '', text: item.text, pinned: Boolean(item.pinned) });

      if (data.error) {
        say(data.error);
      } else if (Array.isArray(data.phrases)) {
        phrases = data.phrases;
      }
    } catch (error) {
      say(t.error);
    }

    paintPhrases();
  };

  const dropPhrase = async (item) => {
    try {
      const data = await post(config.urls.phraseDelete, { id: item.id });

      if (Array.isArray(data.phrases)) {
        phrases = data.phrases;
      }
    } catch (error) {
      say(t.error);
    }

    paintPhrases();
  };

  // What is in the box kept as one's own phrase, pinned so it is at hand
  const keepPhrase = async () => {
    const text = field.value.trim();

    if (!text) {
      return;
    }

    await savePhrase({ text, pinned: true });
    phraseTab = 'mine';
    paintPhrases();
  };

  const closePhrases = () => {
    phraseMenu.hidden = true;
    phraseMore.setAttribute('aria-expanded', 'false');
  };

  const togglePhrases = () => {
    if (!phraseMenu.hidden) {
      closePhrases();

      return;
    }

    phraseMenu.hidden = false;
    phraseMore.setAttribute('aria-expanded', 'true');
    paintPhrases();
    phraseFind.focus();
  };

  phraseFind.addEventListener('input', paintPhrases);
  field.addEventListener('input', () => {
    phraseKeep.disabled = !field.value.trim();
  });
  document.addEventListener('pointerdown', (event) => {
    if (!phraseMenu.hidden && !phraseMenu.contains(event.target) && !phraseMore.contains(event.target)) {
      closePhrases();
    }
  });
  phraseMenu.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closePhrases();
      phraseMore.focus();
    }
  });

  if (config.urls.phrases) {
    loadPhrases();
  }

  // What was chosen to send (files, pictures, stickers) waits above the box, each to take away again,
  // until one sends: then it goes, before the words written
  const tray = el('div', 'askdesk-tray');
  let staged = [];

  tray.hidden = true;

  const paintTray = () => {
    tray.replaceChildren();
    tray.hidden = !staged.length;

    staged.forEach((item) => {
      const chip = el('div', `askdesk-tray__item askdesk-tray__item--${item.kind}`);
      const remove = button('askdesk-tray__remove', '', () => {
        if (item.url) {
          URL.revokeObjectURL(item.url);
        }

        staged = staged.filter((other) => other !== item);
        paintTray();
        field.focus();
      });

      if (item.preview) {
        const picture = el('img', '');

        picture.src = item.preview;
        picture.alt = '';
        chip.append(picture);
      } else {
        chip.append(icon(item.kind === 'video' ? 'video' : 'file'), el('span', 'askdesk-tray__name', item.name));
      }

      remove.append(icon('close'));
      remove.title = `${t.remove} ${item.name}`;
      remove.setAttribute('aria-label', remove.title);
      chip.title = item.name;
      chip.append(remove);
      tray.append(chip);
    });
  };

  const stageFile = (file) => {
    if (!file || staged.length >= 5) {
      return;
    }

    const kind = /^image\//.test(file.type) ? 'image' : (/^video\//.test(file.type) ? 'video' : 'file');
    const url = kind === 'image' ? URL.createObjectURL(file) : '';

    staged.push({ kind, file, url, preview: url, name: file.name || 'file' });
    paintTray();
    field.focus();
  };

  const clearTray = () => {
    staged.forEach((item) => item.url && URL.revokeObjectURL(item.url));
    staged = [];
    paintTray();
  };

  const closeStickers = () => {
    stickerMenu.hidden = true;
    stickerButton.setAttribute('aria-expanded', 'false');
  };

  const paintStickers = (index = 0) => {
    if (!stickerPacks.length) {
      stickerMenu.replaceChildren(el('p', 'askdesk-panel__hint', t.noStickers));

      return;
    }

    const pack = stickerPacks[index] || stickerPacks[0];
    const tabs = el('div', 'askdesk-sticker-menu__tabs');
    const grid = el('div', 'askdesk-sticker-menu__grid');

    stickerPacks.forEach((each, at) => {
      const tab = button('askdesk-library__tab', each.pack, () => paintStickers(at));

      tab.setAttribute('aria-pressed', String(each === pack));
      tabs.append(tab);
    });

    pack.stickers.forEach((sticker) => {
      const pick = button('askdesk-sticker-menu__pick', '', () => {
        closeStickers();

        if (staged.length < 5) {
          staged.push({ kind: 'sticker', sticker, preview: sticker.url, name: sticker.title || '' });
          paintTray();
          field.focus();
        }
      });
      const picture = el('img', '');

      pick.title = sticker.title;
      pick.setAttribute('aria-label', sticker.title);
      picture.src = sticker.url;
      picture.alt = '';
      picture.loading = 'lazy';
      pick.append(picture);
      grid.append(pick);
    });

    stickerMenu.replaceChildren(...(stickerPacks.length > 1 ? [tabs] : []), grid);
  };

  async function toggleStickers() {
    if (!stickerMenu.hidden) {
      closeStickers();

      return;
    }

    stickerMenu.hidden = false;
    stickerButton.setAttribute('aria-expanded', 'true');
    rise(stickerMenu);

    if (stickerPacks === null) {
      stickerMenu.replaceChildren(skeleton(2));

      try {
        const data = await post(config.urls.stickers, {});

        stickerPacks = (data.packs || []).filter((pack) => (pack.stickers || []).some((item) => item.url)).map((pack) => ({ ...pack, stickers: pack.stickers.filter((item) => item.url) }));
      } catch (error) {
        stickerMenu.replaceChildren(el('p', 'askdesk-panel__hint', t.error));

        return;
      }
    }

    paintStickers();
  }

  document.addEventListener('pointerdown', (event) => {
    if (!stickerMenu.hidden && !stickerMenu.contains(event.target) && !stickerButton.contains(event.target)) {
      closeStickers();
    }
  });

  stickerMenu.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.stopPropagation();
      closeStickers();
      stickerButton.focus();
    }
  });
  composer.append(tray, box, note);
  composer.addEventListener('animationend', (event) => {
    if (event.target === box) {
      composer.classList.remove('askdesk-composer--enter');
    }
  });
  list.setAttribute('aria-live', 'polite');
  thread.append(intro, list);
  log.append(thread);
  column.append(log, composer);
  body.append(column, aside);

  const atBottom = () => log.scrollHeight - log.scrollTop - log.clientHeight < 80;
  const toBottom = (eased = false) => {
    log.scrollTo({ top: log.scrollHeight, behavior: eased && !reduced.matches ? 'smooth' : 'auto' });
  };

  // An address kept short: its middle left out
  const shortUrl = (url, most = 34) => {
    const plain = String(url || '').replace(/^https?:\/\//i, '');

    return plain.length > most ? `${plain.slice(0, Math.ceil(most * 0.55))}…${plain.slice(-Math.floor(most * 0.4))}` : plain;
  };

  // The visitor's page, beside their name: a little timeline, the page now at the bottom, the one before
  // fading above it; on another page it scrolls up. Pointed at, it tells how many; clicked, all of them
  const TRAIL_ROW = 40;

  const pageView = (room, before = 0) => {
    if (!room.page) {
      return null;
    }

    const known = (room.pages || []).filter((item) => item && /^https?:\/\//i.test(item.url));
    const pages = known.length ? known : [{ url: room.page, title: '', at: '' }];
    const box = el('div', 'askdesk-trail');
    const face = button('askdesk-trail__face', '', () => {
      box.classList.toggle('askdesk-trail--open');
      state.trailOpen = box.classList.contains('askdesk-trail--open');
      face.setAttribute('aria-expanded', String(state.trailOpen));
    });
    const view = el('span', 'askdesk-trail__window');
    const list = el('span', 'askdesk-trail__list');
    const hint = el('span', 'askdesk-trail__hint', pages.length > 1 ? `${t.pagesVisited.replace('%s', pages.length)} · ${t.pagesAll}` : t.page);
    const pop = el('div', 'askdesk-trail__pop');
    const all = el('ol', '');

    pages.forEach((item, index) => {
      const row = el('span', `askdesk-trail__row${index === pages.length - 1 ? ' askdesk-trail__row--now' : ''}`);

      row.append(el('i', 'askdesk-trail__dot'), el('strong', '', item.title || shortUrl(item.url, 30)), el('small', '', shortUrl(item.url)));
      list.append(row);
    });

    // Scrolled to the page now; from the one seen before when there are new ones
    const offset = (count) => `translateY(${-Math.max(0, count - 1) * TRAIL_ROW}px)`;

    list.style.transform = offset(before && before < pages.length ? before : pages.length);

    if (before && before < pages.length && !reduced.matches) {
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
        list.classList.add('askdesk-trail__list--moving');
        list.style.transform = offset(pages.length);
      }));
    }

    [...pages].reverse().forEach((item, index) => {
      const row = el('li', index === 0 ? 'askdesk-trail__item--now' : '');
      const go = el('a', '', item.title || shortUrl(item.url, 40));

      go.href = item.url;
      go.target = '_blank';
      go.rel = 'noopener noreferrer';
      row.append(go, el('small', '', item.url), el('time', '', item.at ? timeOf(item.at) : ''));
      all.append(row);
    });

    pop.append(el('p', 'askdesk-trail__heading', pages.length > 1 ? t.pagesVisited.replace('%s', pages.length) : t.page), all);
    view.append(list);
    face.append(view);
    // Its own hint says what it is; the address in full for screen readers
    face.setAttribute('aria-label', `${t.page}: ${room.page}`);
    face.setAttribute('aria-haspopup', 'dialog');
    face.setAttribute('aria-expanded', String(Boolean(state.trailOpen)));
    box.append(face, hint, pop);
    box.classList.toggle('askdesk-trail--open', Boolean(state.trailOpen));
    box.dataset.page = room.page;
    box.dataset.count = String(pages.length);

    return box;
  };

  // The list of pages closes on a click elsewhere, or Escape
  document.addEventListener('pointerdown', (event) => {
    if (state.pageNode && state.trailOpen && !state.pageNode.contains(event.target)) {
      state.trailOpen = false;
      state.pageNode.classList.remove('askdesk-trail--open');
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && state.pageNode && state.trailOpen) {
      state.trailOpen = false;
      state.pageNode.classList.remove('askdesk-trail--open');
    }
  });

  const paintHead = () => {
    const room = state.detail;

    head.replaceChildren();
    intro.replaceChildren();
    state.shape = room ? `${room.status}|${room.staff}|${room.rating ? 1 : 0}` : '';

    if (!room) {
      return;
    }

    const back = button('askdesk-button askdesk-button--text askdesk-room__back', t.back, () => goBack());
    const who = el('div', 'askdesk-room__who');
    const line = el('div', 'askdesk-room__name');

    line.append(el('strong', '', room.name || `${t.visitor} #${room.id}`));

    if (room.company) {
      line.append(el('span', 'askdesk-room__company', room.company));
    }

    if (room.language) {
      line.append(pill(room.language, 'blue'));
    }

    line.append(pill(room.member ? t.member : t.guest, room.member ? 'ok' : 'wait'));
    who.append(line);

    if (room.email) {
      const mail = el('a', 'askdesk-room__mail', room.email);

      mail.href = `mailto:${room.email}`;
      who.append(mail);
    }

    const actions = el('div', 'askdesk-room__actions');

    if (room.status === 'waiting' && !room.staff) {
      actions.append(button('askdesk-button askdesk-button--primary askdesk-button--lg', t.take, () => act('take')));
    }

    if (room.status !== 'closed' && (room.staff === config.me || !room.staff)) {
      // A message left is just dealt with; a chat ends for the visitor too, so it is asked once more
      if (room.status === 'message') {
        // A green button with a tick: pressed, it says done, glows, and sinks in (not to be pressed
        // again); then the message is dealt with
        const label = el('span', '', t.done);
        const done = button('askdesk-button askdesk-button--done', '', () => {
          if (done.classList.contains('askdesk-button--done-on')) {
            return;
          }

          done.classList.add('askdesk-button--done-on');
          done.setAttribute('aria-disabled', 'true');
          label.textContent = t.doneOn;
          markedDone.add(room.id);
          window.setTimeout(() => act('close'), reduced.matches ? 0 : 900);
        });

        done.append(icon('check'), label);
        actions.append(done);
      } else {
        const end = button('askdesk-button askdesk-button--alarm askdesk-room__end', t.close, () => confirmEnd(end));

        ['pointerenter', 'focus'].forEach((type) => end.addEventListener(type, () => endCheck().catch(() => {}), { once: true }));

        actions.append(end);
      }
    }

    // Dealt with here a moment ago: the button stays, sunk in
    if (room.status === 'closed' && markedDone.has(room.id)) {
      const done = el('span', 'askdesk-button askdesk-button--done askdesk-button--done-on askdesk-button--done-still');

      done.setAttribute('aria-disabled', 'true');
      done.append(icon('check'), el('span', '', t.doneOn));
      actions.append(done);
    }

    if (room.status === 'active' && room.staff !== config.me && room.staffName) {
      actions.append(pill(t.answeredBy.replace('%s', room.staffName), 'blue'));
    }

    // On a narrower screen the side panel opens over the chat
    // An icon at the end: a panel pulled out from the right; open, the icon turns about to put it back
    const refer = button('askdesk-icon-button askdesk-room__refer', '', () => {
      refer.setAttribute('aria-expanded', String(body.classList.toggle('askdesk-room__body--aside')));
    });

    refer.append(icon('panel'));
    refer.title = t.reference;
    refer.setAttribute('aria-label', t.reference);
    refer.setAttribute('aria-expanded', String(body.classList.contains('askdesk-room__body--aside')));
    actions.append(refer);
    head.append(back, who, actions);

    // Where the visitor is now (it follows them from page to page), and the pages they went through
    state.trailOpen = false;
    state.pageNode = pageView(room);

    if (state.pageNode) {
      who.after(state.pageNode);
    }

    // How the visitor rated the chat that ended
    if (room.rating && room.rating.ratings) {
      const rated = el('div', 'askdesk-rated');
      const values = el('div', 'askdesk-rated__values');

      rated.append(el('strong', '', t.rated));

      Object.values(room.rating.ratings).forEach((item) => {
        const shown = item.type === 'stars'
          ? `${'★'.repeat(Number(item.value))}${'☆'.repeat(5 - Number(item.value))}`
          : item.type === 'thumbs' ? (Number(item.value) > 0 ? t.helpful : t.notHelpful) : String(item.value);
        const kind = item.type === 'thumbs' ? (Number(item.value) > 0 ? 'ok' : 'warn')
          : item.type === 'stars' ? (Number(item.value) >= 4 ? 'ok' : Number(item.value) <= 2 ? 'warn' : 'blue')
            : item.type === 'scale' ? (Number(item.value) >= 9 ? 'ok' : Number(item.value) <= 6 ? 'warn' : 'blue') : 'blue';

        values.append(pill(`${item.label} ${shown}`, kind));
      });

      rated.append(values);

      if (room.rating.comment) {
        rated.append(el('p', 'askdesk-rated__comment', room.rating.comment));
      }

      intro.append(rise(rated));
    }

    const entry = room.entry || {};

    if ((entry.conversation && entry.conversation.length) || entry.question) {
      const before = el('details', 'askdesk-before');

      const summary = el('summary', '');

      summary.append(icon('sparkles'), el('span', '', `${t.before} · ${(entry.conversation || []).length || 1}`));
      before.append(summary);
      smooth(before);

      (entry.conversation && entry.conversation.length ? entry.conversation : [{ role: 'user', text: entry.question }]).forEach((turn) => {
        const line = el('div', `askdesk-before__turn askdesk-before__turn--${turn.role === 'user' ? 'visitor' : 'ai'}`);

        line.append(el('span', 'askdesk-before__who', turn.role === 'user' ? t.visitor : t.ai), linked(turn.text));
        before.append(line);
      });

      if (entry.report) {
        const report = el('a', '', t.report);

        report.href = entry.report;
        report.target = '_blank';
        report.rel = 'noopener noreferrer';
        before.append(report);
      }

      intro.append(before);
    }

    const mine = room.status === 'active' && room.staff === config.me;
    // A message left is answered by e-mail: written here, translated, sent from one's own mail program
    const email = room.status === 'message' && (!room.staff || room.staff === config.me) && Boolean(room.email);

    if (email) {
      const how = el('p', 'askdesk-room__note');

      how.append(icon('send'), el('span', '', t.replyNote));
      intro.append(how);
    }

    const wasHidden = composer.hidden;

    composer.hidden = !mine && !email;

    // Coming into view (a chat taken): its glow rises first, then the box floats up into it
    if (wasHidden && !composer.hidden) {
      composer.classList.remove('askdesk-composer--enter');
      void composer.offsetWidth;
      composer.classList.add('askdesk-composer--enter');
    }

    composer.dataset.mode = email ? 'email' : 'chat';
    field.placeholder = email ? t.replyPlaceholder : t.placeholder;
    attach.hidden = email;
    stickerButton.hidden = email;
    sendLabel.textContent = email ? t.replySend : t.send;
    send.setAttribute('aria-label', sendLabel.textContent);

    // Suggestions go into the box only when there is one
    root.querySelectorAll('.askdesk-suggest__use').forEach((use) => {
      use.disabled = composer.hidden;
    });
    // Files from the library only into one's own chat
    root.querySelectorAll('.askdesk-library__send').forEach((use) => {
      use.disabled = !mine;
    });
  };

  const sizeOf = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

  // How long a file is kept still
  const expiry = (iso) => {
    const days = Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000));

    return t.expiresIn.replace('%s', days);
  };

  // A picture to open whole, a video to play, a document to download; with its size and its days
  const fileView = (file) => {
    const view = el('div', `askdesk-file askdesk-file--${file.kind}`);

    if (file.gone || !file.url) {
      view.classList.add('askdesk-file--gone');
      view.append(icon('file'), el('span', '', `${file.name} · ${t.expired}`));

      return view;
    }

    if (file.kind === 'image') {
      const picture = el('img', 'askdesk-file__image');

      picture.src = file.url;
      picture.alt = file.name;
      picture.loading = 'lazy';
      picture.addEventListener('click', () => showLightbox(file.url, file.name));
      view.append(picture);
    } else if (file.kind === 'video') {
      const video = el('video', 'askdesk-file__video');

      // From its first moment, so a frame shows rather than black
      video.src = `${file.url}#t=0.1`;
      video.controls = true;
      video.preload = 'metadata';
      view.append(video);
    } else {
      const card = el('a', 'askdesk-file__doc');
      const words = el('span', 'askdesk-file__words');

      card.href = file.url;
      card.setAttribute('download', file.name);
      words.append(el('strong', '', file.name), el('span', '', sizeOf(file.size)));
      card.append(icon('file'), words, icon('download'));
      card.title = t.download;
      view.append(card);
    }

    return view;
  };

  // A file of the chat kept in one's library or the shared one: a small choice, then a word that it is
  function keepButton(file) {
    const box = el('span', 'askdesk-keep');
    const open = button('askdesk-keep__open', '', () => {
      choices.hidden = !choices.hidden;

      if (!choices.hidden) {
        rise(choices);
      }
    });
    const choices = el('span', 'askdesk-keep__choices');
    const keep = async (folder, chosen) => {
      choices.querySelectorAll('button').forEach((node) => { node.disabled = true; });

      try {
        const data = await post(config.urls.libraryKeep, { file: file.id, folder });

        if (data.error) {
          throw new Error(data.error);
        }

        box.replaceChildren(rise(el('span', 'askdesk-keep__done', data.kept || t.kept)));
        library.data = null;

        if (libraryPanel.open) {
          library.which = folder;
          loadLibrary();
        }
      } catch (error) {
        chosen.after(rise(el('span', 'askdesk-keep__error', error.message || t.error)));
        choices.querySelectorAll('button').forEach((node) => { node.disabled = false; });
      }
    };

    open.append(icon('folder'));
    open.title = t.keepMine;
    open.setAttribute('aria-label', t.keepMine);
    choices.hidden = true;
    [['mine', t.keepMine], ['shared', t.keepShared]].forEach(([folder, label]) => {
      const choice = button('askdesk-keep__choice', label, () => keep(folder, choice));

      choices.append(choice);
    });
    box.append(open, choices);

    return box;
  }

  // A picture, whole, over the console
  function showLightbox(src, name) {
    const shade = el('div', 'askdesk-lightbox');
    const picture = el('img', '');

    picture.src = src;
    picture.alt = name;
    shade.append(picture, el('span', '', name));
    shade.addEventListener('click', () => shade.remove());
    document.addEventListener('keydown', function escape(event) {
      if (event.key === 'Escape') {
        shade.remove();
        document.removeEventListener('keydown', escape);
      }
    });
    root.append(shade);
  }

  // The browser's own translation (Chrome's, on the computer, nothing sent anywhere), for a message
  // the AI is still translating: a first translation for the moment. Only where the browser has the
  // two languages already; nothing is downloaded for it
  const browserCode = (tag) => {
    const lower = String(tag || '').toLowerCase();

    if (lower === 'zh-tw' || lower === 'zh-hk' || lower === 'zh-hant') {
      return 'zh-Hant';
    }

    return lower.split('-')[0];
  };
  const browserTranslators = {};

  // Each step of it given a moment at most: a browser that does not answer must hold nothing up
  const inTime = (promise, ms = 1500) => Promise.race([promise, new Promise((resolve, reject) => { window.setTimeout(() => reject(new Error('time')), ms); })]);

  const browserTranslate = async (text, from, to) => {
    if (!config.browser || !('Translator' in window) || !to || !text) {
      return '';
    }

    let pair = '';

    try {
      let source = browserCode(from);

      if (!source && 'LanguageDetector' in window && (await inTime(window.LanguageDetector.availability())) === 'available') {
        const found = await inTime((await inTime(window.LanguageDetector.create())).detect(text));

        source = found && found[0] && found[0].confidence > 0.6 ? found[0].detectedLanguage : '';
      }

      const target = browserCode(to);

      if (!source || source === target) {
        return '';
      }

      pair = `${source}>${target}`;

      if (browserTranslators[pair] === false) {
        return '';
      }

      if (!browserTranslators[pair]) {
        if ((await inTime(window.Translator.availability({ sourceLanguage: source, targetLanguage: target }))) !== 'available') {
          browserTranslators[pair] = false;

          return '';
        }

        browserTranslators[pair] = window.Translator.create({ sourceLanguage: source, targetLanguage: target });
      }

      return String(await inTime((await inTime(browserTranslators[pair])).translate(text), 2500) || '');
    } catch (error) {
      if (pair) {
        browserTranslators[pair] = false;
      }

      return '';
    }
  };

  const showMessage = (item) => {
    if (item.author === 'system') {
      const note = el('p', 'askdesk-msg askdesk-msg--system');

      note.append(pill(`${item.text} · ${timeOf(item.time)}`));
      list.append(note);
      item.node = note;

      if (item.fresh) {
        rise(note);
        item.fresh = false;
      }

      return note;
    }

    const visitor = item.author === 'visitor';
    const node = el('article', `askdesk-msg askdesk-msg--${visitor ? 'visitor' : 'staff'}${item.pending ? ' askdesk-msg--pending' : ''}`);
    const bubble = el('div', 'askdesk-msg__bubble');
    const meta = el('div', 'askdesk-msg__meta');

    // Who wrote it: their face, as visitors see it
    if (!visitor && item.face && /^https?:|^\//.test(item.face.src || '')) {
      const face = el('img', 'askdesk-msg__face');

      face.src = reduced.matches && item.face.still ? item.face.still : item.face.src;
      face.alt = '';
      face.decoding = 'async';
      meta.append(face);
    }

    meta.append(el('span', '', [visitor ? '' : item.name, timeOf(item.time)].filter(Boolean).join(' · ')));

    // A sticker: the picture alone
    if (item.sticker) {
      node.classList.add('askdesk-msg--sticker');

      if (item.sticker.url) {
        const picture = el('img', 'askdesk-sticker');

        picture.src = item.sticker.url;
        picture.alt = item.sticker.title;
        picture.title = item.sticker.title;
        bubble.append(picture);
      } else {
        bubble.append(el('span', 'askdesk-sticker askdesk-sticker--gone', item.sticker.title));
      }

      node.append(bubble, meta);
      list.append(node);
      item.node = node;

      if (item.fresh || item.pending) {
        node.classList.add('askdesk-msg--pop');
        item.fresh = false;
      }

      return node;
    }

    // A file: the file itself, and how long it is kept
    if (item.file) {
      node.classList.add('askdesk-msg--file');
      bubble.append(fileView(item.file));

      if (!item.file.gone && item.file.expires) {
        meta.append(pill(expiry(item.file.expires), 'wait'));
      }

      // Kept in the library (Dropfiles), to send again after its days
      if (!item.file.gone && item.file.id && config.library) {
        meta.append(keepButton(item.file));
      }

      node.append(bubble, meta);
      list.append(node);
      item.node = node;

      if (item.fresh || item.pending) {
        node.classList.add('askdesk-msg--pop');
        item.fresh = false;
      }

      return node;
    }

    let text = linked(item.text);

    bubble.append(text);

    // The visitor's words still being translated: said so, and the browser's first translation below
    if (visitor && item.translating) {
      const busy = pill(t.translating, 'blue');

      busy.classList.add('askdesk-pill--busy');
      meta.append(busy);
      // The AI slow: the server's quick translation (Google's); else the browser's own, if it has one
      const draftLine = (label, words) => {
        const line = el('p', 'askdesk-msg__draft');

        line.append(el('small', '', label), el('span', '', words));
        bubble.append(line);
        rise(line);
      };

      if (item.draft) {
        draftLine(t.quick, item.draft);
      } else {
        browserTranslate(item.text, item.from, item.to).then((draft) => {
          if (draft && item.node === node) {
            draftLine(t.draft, draft);
          }
        });
      }
    }

    // One's own words on their way to the visitor's language, or not translated after all
    if (!visitor && item.mine && item.translating) {
      const busy = pill(t.translating, 'blue');

      busy.classList.add('askdesk-pill--busy');
      meta.append(busy);
    } else if (!visitor && item.mine && item.failed) {
      meta.append(pill(t.untranslatedSent, 'warn'));
    }

    if (visitor && item.original) {
      // Translated for us: the words the visitor wrote, a click away
      let original = false;
      const toggle = button('askdesk-button askdesk-button--text askdesk-msg__toggle', t.original, () => {
        original = !original;

        const next = linked(original ? item.original : item.text);

        swap(bubble, text, next);
        text = next;
        toggle.textContent = original ? t.translation : t.original;
      });

      meta.append(toggle);
    }

    if (visitor && item.foreign && config.translate) {
      const mineButton = button('askdesk-button askdesk-button--text askdesk-msg__toggle', t.translateMine, async () => {
        mineButton.disabled = true;

        try {
          const data = await post(config.urls.translate, { room: state.open, message: item.id });

          if (data.text) {
            const next = linked(data.text);

            swap(bubble, text, next);
            text = next;
            mineButton.remove();
          } else {
            mineButton.disabled = false;
          }
        } catch (error) {
          mineButton.disabled = false;
        }
      });

      meta.append(mineButton);
    }

    // The articles linked, as the visitor sees them (below the message)
    let linkCards = null;

    if (!visitor && Array.isArray(item.links) && item.links.length) {
      const cards = el('div', 'askdesk-linkcards');

      item.links.forEach((link) => {
        if (!/^https?:\/\//.test(link.url || '')) {
          return;
        }

        const card = el('a', 'askdesk-linkcard');
        const words = el('span', 'askdesk-linkcard__words');

        card.href = link.url;
        card.target = '_blank';
        card.rel = 'noopener noreferrer';

        if (link.image && /^\/(?!\/)/.test(link.image)) {
          const picture = el('img', 'askdesk-linkcard__image');

          picture.src = link.image;
          picture.alt = '';
          picture.loading = 'lazy';
          card.append(picture);
        }

        words.append(el('strong', '', link.title || link.url));

        if (link.excerpt) {
          words.append(el('span', '', link.excerpt));
        }

        card.append(words);
        cards.append(card);
      });

      linkCards = cards;
    }

    if (!visitor && item.translation) {
      // What the visitor reads, a click away
      const shown = el('details', 'askdesk-msg__translation');
      const summary = el('summary', '');

      summary.append(icon('language'), t.translatedTo.replace('%s', item.translatedTo || ''));
      shown.append(summary, linked(item.translation));
      smooth(shown);
      meta.append(shown);
    }

    if (item.warning) {
      bubble.append(el('p', 'askdesk-msg__warning', item.warning));
    }

    node.append(bubble, meta);

    if (linkCards) {
      node.append(linkCards);
    }

    list.append(node);
    item.node = node;

    // Just written or just come: it pops in from its corner, once
    if (item.fresh || item.pending) {
      node.classList.add('askdesk-msg--pop');
      item.fresh = false;
    }

    return node;
  };

  // ---------- Beside the chat: suggested replies, and the visitor's history ----------

  const side = { for: -1, loading: false, items: null, failed: '' };
  const suggestList = el('div', 'askdesk-suggest');
  const historyList = el('div', 'askdesk-history');

  // A part of the side panel, that folds. Its heading and what is under it are put in the card side
  // by side (the part itself draws no box), so the heading can stay in view while the side scrolls:
  // stacked at the top once passed, at the bottom while still below. Like a details element, it has
  // .open, data-state and a "toggle" event
  const panel = (title, iconName, content) => {
    const section = el('div', 'askdesk-panel');
    const mark = el('i', 'askdesk-panel__mark');
    const head = el('button', 'askdesk-panel__title');
    const body = el('div', 'askdesk-panel__body');
    let moving = null;

    head.type = 'button';
    head.setAttribute('aria-expanded', 'true');
    head.append(icon(iconName), el('span', '', title));
    body.append(content);
    section.append(mark, head, body);
    section.dataset.state = 'open';

    const show = (open, animate) => {
      if (section.dataset.state === (open ? 'open' : 'closed')) {
        return;
      }

      section.dataset.state = open ? 'open' : 'closed';
      head.setAttribute('aria-expanded', String(open));

      if (moving) {
        moving.cancel();
        moving = null;
      }

      body.hidden = false;

      if (animate && !reduced.matches) {
        const full = body.scrollHeight;

        body.style.overflow = 'hidden';
        moving = body.animate(
          [{ height: `${open ? 0 : full}px`, opacity: open ? 0 : 1 }, { height: `${open ? full : 0}px`, opacity: open ? 1 : 0 }],
          { duration: open ? 260 : 200, easing: EASE },
        );

        const current = moving;

        current.finished.then(() => {
          body.hidden = !open;
          body.style.overflow = '';
          moving = null;
        }, () => {
          body.style.overflow = '';
        });
      } else {
        body.hidden = !open;
      }

      section.dispatchEvent(new Event('toggle'));
    };

    Object.defineProperty(section, 'open', {
      get: () => section.dataset.state === 'open',
      set: (open) => show(Boolean(open), false),
    });

    // A heading held at the top or the bottom brings its part into view (opened); in its place, it folds
    head.addEventListener('click', () => {
      const at = head.getBoundingClientRect().top;
      const own = mark.getBoundingClientRect().top;

      if (Math.abs(at - own) < 2) {
        show(section.dataset.state !== 'open', true);

        return;
      }

      show(true, true);

      const above = Number(head.style.getPropertyValue('--above')) || 0;

      asideScroll.scrollBy({ top: own - asideScroll.getBoundingClientRect().top - above * head.offsetHeight, behavior: reduced.matches ? 'auto' : 'smooth' });
    });

    return section;
  };

  // The library in Dropfiles: one's own files and the shared ones, to send in the chat
  const library = { which: 'mine', data: null, loading: false };
  const libraryBox = el('div', 'askdesk-library');
  const libraryTabs = el('div', 'askdesk-library__tabs');
  const libraryList = el('div', 'askdesk-library__list');
  const libraryInput = el('input');
  const libraryAdd = button('askdesk-button askdesk-button--text askdesk-library__add', '', () => libraryInput.click());

  libraryInput.type = 'file';
  libraryInput.hidden = true;
  libraryInput.accept = 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip';
  libraryAdd.append(icon('up'), el('span', '', t.libraryAdd));
  ['mine', 'shared'].forEach((which) => {
    const tab = button('askdesk-library__tab', which === 'mine' ? t.libraryMine : t.libraryShared, () => {
      library.which = which;
      paintLibrary(true);
    });

    tab.dataset.which = which;
    tab.setAttribute('aria-pressed', String(which === library.which));
    libraryTabs.append(tab);
  });
  libraryTabs.append(libraryAdd, libraryInput);
  libraryBox.append(libraryTabs, libraryList);

  const libraryPanel = panel(t.library, 'folder', libraryBox);

  libraryPanel.open = false;
  libraryPanel.dataset.state = 'closed';
  libraryPanel.addEventListener('toggle', () => {
    if (libraryPanel.open && !library.data && !library.loading) {
      loadLibrary();
    }
  });

  // The suggested replies, the visitor's history and the library: one card, parted by lines
  const panels = el('div', 'askdesk-panels');
  // A part that is an AI's reading (a brief of the visitor): a card of its own above the rest, blue-violet
  const featured = el('div', 'askdesk-featured');

  panels.append(panel(t.suggestions, 'bulb', suggestList), panel(t.history, 'history', historyList), libraryPanel);
  asideScroll.append(search, results, featured, panels);

  // The search, as the side scrolls down, shrinks away toward the top end, and a round search button
  // grows there in its place; scrolled back to the top, the search opens again. Both follow how far the
  // side is scrolled (0 to 1 over the search's own height), not a switch
  const searchDot = button('askdesk-search-dot', '', () => {
    asideScroll.scrollTo({ top: 0, behavior: reduced.matches ? 'auto' : 'smooth' });
    window.setTimeout(() => searchField.focus({ preventScroll: true }), reduced.matches ? 0 : 380);
  });
  let shrinking = 0;

  searchDot.append(icon('search'));
  searchDot.setAttribute('aria-label', t.search);
  searchDot.tabIndex = -1;
  aside.append(searchDot);

  const shrink = () => {
    window.cancelAnimationFrame(shrinking);
    shrinking = window.requestAnimationFrame(() => {
      const reach = Math.max(1, search.offsetHeight + 12);
      const progress = Math.min(1, Math.max(0, asideScroll.scrollTop / reach));
      const compact = progress > 0.75;

      aside.style.setProperty('--askdesk-search-p', progress.toFixed(3));
      aside.classList.toggle('askdesk-room__aside--compact', compact);
      searchDot.tabIndex = compact ? 0 : -1;
    });
  };

  asideScroll.addEventListener('scroll', shrink, { passive: true });
  shrink();

  // The headings' places in the stack: how many above each, how many below; and whether each is held
  // now (a line of shadow on the side toward the rest)
  const restack = () => {
    const parts = [...panels.children].filter((part) => part.classList.contains('askdesk-panel'));

    parts.forEach((part, index) => {
      const head = part.querySelector(':scope > .askdesk-panel__title');

      head.style.setProperty('--above', String(index));
      head.style.setProperty('--below', String(parts.length - 1 - index));
      head.classList.toggle('is-first', index === 0);
      head.classList.toggle('is-last', index === parts.length - 1);
    });
    held();
  };

  let holding = 0;

  const held = () => {
    window.cancelAnimationFrame(holding);
    holding = window.requestAnimationFrame(() => {
      const heads = [...panels.querySelectorAll(':scope > .askdesk-panel')].map((part) => {
        const head = part.querySelector(':scope > .askdesk-panel__title');
        const shift = head.getBoundingClientRect().top - part.querySelector(':scope > .askdesk-panel__mark').getBoundingClientRect().top;

        head.classList.toggle('is-held-top', shift > 1);
        head.classList.toggle('is-held-bottom', shift < -1);

        return head;
      });
      const top = heads.filter((head) => head.classList.contains('is-held-top'));
      const bottom = heads.filter((head) => head.classList.contains('is-held-bottom'));

      // The shadow only where a stack meets what scrolls: the last held at the top, the first at the bottom
      heads.forEach((head) => head.classList.toggle('is-edge', head === top[top.length - 1] || head === bottom[0]));
    });
  };

  asideScroll.addEventListener('scroll', held, { passive: true });
  new MutationObserver(restack).observe(panels, { childList: true });

  if ('ResizeObserver' in window) {
    new ResizeObserver(held).observe(asideScroll);
    new ResizeObserver(held).observe(panels);
  }

  restack();

  // What plugins add beside a chat (a CRM's customer, say): drawn from their view, all as text
  const addons = new Map();
  // The actions panels asked to run on opening, already run (for which chat)
  const followed = new Set();

  // A brief to read (an AI's of the visitor): its parts, each point with where it comes from
  const paintBrief = (brief) => {
    const box = el('div', `askdesk-brief${brief.stale ? ' askdesk-brief--stale' : ''}`);

    if (brief.notice) {
      box.append(el('p', 'askdesk-brief__notice', brief.notice));
    }

    brief.sections.forEach((section) => {
      const part = el('section', `askdesk-brief__part askdesk-brief__part--${section.tone}`);
      const points = el('ul', 'askdesk-brief__points');

      section.points.forEach((point) => {
        const item = el('li', '');

        item.append(el('span', 'askdesk-brief__text', point.text));

        if (point.sources.length) {
          const sources = el('span', 'askdesk-brief__sources');

          point.sources.forEach((source) => sources.append(el('small', '', source)));
          item.append(sources);
        }

        points.append(item);
      });

      part.append(el('h4', 'askdesk-brief__title', section.title), points);
      box.append(part);
    });

    if (brief.generated) {
      box.append(el('p', 'askdesk-brief__when', brief.generated));
    }

    return box;
  };
  const noteEdits = new Map();
  const noteTicks = new Map();

  // Records kept elsewhere (notes before, in a CRM): one, short or whole
  const paintRecord = (item, short) => {
    const record = el('article', short ? 'askdesk-addon__record is-short' : 'askdesk-addon__record');
    const head = el('div', 'askdesk-addon__record-head');

    head.append(el('strong', '', item.title));

    if (item.meta) {
      head.append(el('span', '', item.meta));
    }

    record.append(head);

    if (item.tags.length) {
      const tags = el('div', 'askdesk-addon__record-tags');

      item.tags.forEach((tag) => tags.append(el('span', '', tag)));
      record.append(tags);
    }

    if (item.text) {
      record.append(el('p', 'askdesk-addon__record-text', item.text));
    }

    return record;
  };

  // All of them in a card over the side, sliding in from its edge
  const drawer = el('section', 'askdesk-drawer');
  const drawerHead = el('div', 'askdesk-drawer__head');
  const drawerTitle = el('strong', 'askdesk-drawer__title');
  const drawerBody = el('div', 'askdesk-drawer__body');
  const drawerClose = button('askdesk-drawer__close', '', () => closeDrawer());

  drawer.hidden = true;
  drawer.setAttribute('role', 'dialog');
  drawer.setAttribute('aria-modal', 'false');
  drawerClose.setAttribute('aria-label', t.panelListClose);
  drawerClose.append(icon('close'));
  drawerHead.append(drawerTitle, drawerClose);
  drawer.append(drawerHead, drawerBody);
  fades(drawerBody);
  aside.append(drawer);

  const closeDrawer = () => {
    if (drawer.hidden || drawer.classList.contains('is-leaving')) {
      return;
    }

    drawer.classList.add('is-leaving');
    window.setTimeout(() => {
      drawer.hidden = true;
      drawer.classList.remove('is-leaving');
    }, 220);
  };

  const openDrawer = (list) => {
    drawerTitle.textContent = list.title;
    drawerBody.replaceChildren(...list.items.map((item) => paintRecord(item, false)));

    if (list.more) {
      const more = el('a', 'askdesk-addon__link askdesk-drawer__more');

      more.href = list.more.url;
      more.target = '_blank';
      more.rel = 'noopener noreferrer';
      more.append(el('span', '', list.more.title), icon('external'));
      drawerBody.append(more);
    }

    drawer.classList.remove('is-leaving');
    drawer.hidden = false;
    drawerBody.scrollTop = 0;
    drawerClose.focus();
  };

  drawer.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeDrawer();
    }
  });

  const paintList = (view) => {
    const list = view.list;
    const box = el('div', 'askdesk-addon__records');
    const head = el('div', 'askdesk-addon__records-head');
    const count = Math.max(list.total, list.items.length);

    head.append(icon('history'), el('strong', '', list.title));

    if (count) {
      head.append(el('span', 'askdesk-addon__records-count', String(count)));
    }

    box.append(head);

    if (!list.items.length) {
      box.append(el('p', 'askdesk-panel__hint', list.empty));

      return box;
    }

    list.items.slice(0, 3).forEach((item) => box.append(paintRecord(item, true)));

    if (list.items.length > 3 || list.more) {
      box.append(button('askdesk-addon__records-all', t.panelListAll.replace('%s', String(count)), () => openDrawer(list)));
    }

    return box;
  };

  // A note to keep elsewhere: changed in the box, sent only when the person confirms it
  const paintNote = (view) => {
    const note = view.note;
    const key = `${view.name}:${state.open}`;
    const box = el('div', 'askdesk-addon__memo');
    const head = el('div', 'askdesk-addon__memo-head');

    head.append(icon(note.sent ? 'check' : 'quote'), el('strong', '', note.title));
    box.append(head);

    if (note.sent) {
      box.classList.add('is-sent');
      box.append(el('p', 'askdesk-addon__memo-sent', note.sent), el('p', 'askdesk-addon__memo-text', note.text));

      return box;
    }

    const field = el('textarea', 'askdesk-addon__memo-field');
    const actions = el('div', 'askdesk-addon__memo-actions');
    const ticked = noteTicks.get(key) || new Set(note.choices.filter((choice) => choice.checked).map((choice) => choice.value));
    const ready = () => !note.blocked && field.value.trim() !== '' && (!note.choices.length || ticked.size > 0);
    const draft = button('askdesk-addon__memo-draft', '', () => actAddon(view.name, 'draft', {}));
    const send = button('askdesk-addon__memo-send', t.panelNoteSend, () => {
      if (ready()) {
        actAddon(view.name, 'send', { note: field.value.trim(), choices: [...ticked] });
      }
    });

    field.rows = 6;
    field.placeholder = t.panelNotePlaceholder;
    field.value = noteEdits.has(key) ? noteEdits.get(key) : note.text;
    send.disabled = !ready();
    field.addEventListener('input', () => {
      noteEdits.set(key, field.value);
      send.disabled = !ready();
    });

    // Its categories, ticked as pills, those of a group on a row of their own: one at least
    if (note.choices.length) {
      const chips = el('div', 'askdesk-addon__chips');
      const rows = new Map();

      chips.setAttribute('role', 'group');
      chips.setAttribute('aria-label', t.panelNoteChoose);
      note.choices.forEach((choice) => {
        if (!rows.has(choice.group)) {
          const row = el('div', 'askdesk-addon__chip-row');

          if (choice.group) {
            row.append(el('span', 'askdesk-addon__chip-group', choice.group));
          }

          rows.set(choice.group, row);
          chips.append(row);
        }

        const chip = button('askdesk-addon__chip', choice.label, () => {
          if (ticked.has(choice.value)) {
            ticked.delete(choice.value);
          } else {
            ticked.add(choice.value);
          }

          noteTicks.set(key, ticked);
          chip.setAttribute('aria-pressed', String(ticked.has(choice.value)));
          chips.classList.toggle('is-empty', ticked.size === 0);
          send.disabled = !ready();
        });

        chip.title = choice.value;
        chip.setAttribute('aria-pressed', String(ticked.has(choice.value)));
        rows.get(choice.group).append(chip);
      });
      chips.classList.toggle('is-empty', ticked.size === 0);
      chips.append(el('span', 'askdesk-addon__chips-hint', t.panelNoteChoose));
      box.append(chips);
    }
    draft.append(icon('sparkles'), el('span', '', field.value.trim() ? t.panelNoteRedraft : t.panelNoteDraft));
    actions.append(draft, send);
    box.append(field);

    if (note.blocked) {
      box.append(el('p', 'askdesk-addon__memo-blocked', note.blocked));
    } else if (note.author) {
      box.append(el('p', 'askdesk-addon__memo-hint', note.author));
    }

    if (note.hint) {
      box.append(el('p', 'askdesk-addon__memo-hint', note.hint));
    }

    box.append(actions);

    return box;
  };

  const paintAddon = (body, view) => {
    const parts = [];

    if (view.subtitle) {
      parts.push(el('p', 'askdesk-addon__subtitle', view.subtitle));
    }

    if (view.state !== 'found' || view.message) {
      const fallback = view.state === 'error' ? t.panelError : (view.state === 'setup' ? t.panelSetup : '');

      if (view.message || fallback) {
        parts.push(el('p', `askdesk-panel__hint askdesk-addon__note askdesk-addon__note--${view.state}`, view.message || fallback));
      }
    }

    if (view.brief) {
      parts.push(paintBrief(view.brief));
    }

    if (view.fields.length) {
      const list = el('dl', 'askdesk-addon__fields');

      view.fields.forEach((field) => {
        const row = el('div', 'askdesk-addon__field');

        row.append(el('dt', '', field.label), el('dd', '', field.value));
        list.append(row);
      });
      parts.push(list);
    }

    // Labels: the first few, the rest behind "+N" (and put away again)
    if (view.tags.length) {
      const tags = el('div', 'askdesk-addon__tags');
      const FEW = 8;

      view.tags.forEach((tag, index) => {
        const pill = el('span', 'askdesk-addon__tag', tag);

        pill.hidden = index >= FEW;
        pill.title = tag;
        tags.append(pill);
      });

      if (view.tags.length > FEW) {
        const toggle = button('askdesk-addon__tag askdesk-addon__tag--more', '', () => {
          const open = toggle.getAttribute('aria-expanded') !== 'true';

          tags.querySelectorAll('.askdesk-addon__tag:not(.askdesk-addon__tag--more)').forEach((pill, index) => {
            pill.hidden = !open && index >= FEW;
          });
          toggle.setAttribute('aria-expanded', String(open));
          toggle.textContent = open ? t.panelTagsLess : t.panelTagsMore.replace('%s', String(view.tags.length - FEW));
          tags.append(toggle);
        });

        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = t.panelTagsMore.replace('%s', String(view.tags.length - FEW));
        tags.append(toggle);
      }

      parts.push(tags);
    }

    if (view.search) {
      const form = el('form', 'askdesk-addon__search');
      const input = el('input');
      const go = el('button', 'askdesk-addon__go');

      input.type = 'search';
      input.placeholder = view.search.placeholder || t.panelSearch;
      input.setAttribute('aria-label', t.panelSearch);
      go.type = 'submit';
      go.setAttribute('aria-label', t.panelSearch);
      go.append(icon('search'));
      form.append(input, go);
      form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (input.value.trim()) {
          actAddon(view.name, 'search', { text: input.value.trim() });
        }
      });
      parts.push(form);
    }

    if (view.results.length) {
      const results = el('div', 'askdesk-addon__results');

      view.results.forEach((item) => {
        const row = el('div', 'askdesk-addon__result');
        const text = el('div', 'askdesk-addon__result-text');

        text.append(el('strong', '', item.title || item.ref));

        if (item.meta) {
          text.append(el('span', '', item.meta));
        }

        row.append(text, button('askdesk-addon__choose', t.panelChoose, () => actAddon(view.name, 'choose', { ref: item.ref })));
        results.append(row);
      });
      parts.push(results);
    }

    if (view.list) {
      parts.push(paintList(view));
    }

    if (view.note) {
      parts.push(paintNote(view));
    }

    const foot = el('div', 'askdesk-addon__foot');

    if (view.link) {
      const link = el('a', 'askdesk-addon__link');

      link.href = view.link.url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.append(el('span', '', view.link.title || view.title), icon('external'));
      foot.append(link);
    }

    // Read again: at the foot; for an AI's reading, beside its heading's words
    const head = body.closest('.askdesk-panel')?.querySelector(':scope > .askdesk-panel__title');
    let beside = head?.querySelector(':scope > .askdesk-panel__again');

    if (view.look === 'ai' && head) {
      if (!beside) {
        beside = el('span', 'askdesk-panel__again');
        beside.setAttribute('role', 'button');
        beside.tabIndex = 0;
        beside.title = t.panelRefresh;
        beside.setAttribute('aria-label', t.panelRefresh);
        beside.append(icon('refresh'));
        head.querySelector(':scope > span:not(.askdesk-icon)').after(beside);

        // Within the heading's button, but not opening or closing it
        const go = (event) => {
          event.preventDefault();
          event.stopPropagation();

          if (!beside.classList.contains('is-busy')) {
            beside.classList.add('is-busy');
            actAddon(beside.dataset.name, 'refresh', {});
          }
        };

        beside.addEventListener('click', go);
        beside.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            go(event);
          }
        });
      }

      beside.dataset.name = view.name;
    } else {
      beside?.remove();
      beside = null;

      const again = button('askdesk-addon__refresh', '', () => actAddon(view.name, 'refresh', {}));

      again.title = t.panelRefresh;
      again.setAttribute('aria-label', t.panelRefresh);
      again.append(icon('refresh'));
      foot.append(again);
    }

    // Busy while it is being drawn again (a draft) and done once drawn
    beside?.classList.toggle('is-busy', false);

    if (foot.childElementCount) {
      parts.push(foot);
    }

    body.dataset.state = view.state;
    body.replaceChildren(...parts);
  };

  const showAddons = (views) => {
    const seen = new Set();
    let before = panels.firstChild;

    views.forEach((view) => {
      seen.add(view.name);

      let entry = addons.get(view.name);

      if (!entry) {
        const body = el('div', 'askdesk-addon');

        entry = { body, details: panel(view.title || view.name, view.icon && ICONS[view.icon] ? view.icon : 'user', body) };
        entry.details.classList.add('askdesk-panel--addon');
        addons.set(view.name, entry);
      }

      entry.details.querySelector('.askdesk-panel__title > span:not(.askdesk-icon)').textContent = view.title || view.name;
      paintAddon(entry.body, view);
      entry.details.classList.toggle('askdesk-panel--ai', view.look === 'ai');

      // Slow work it asks for (an AI's brief, say): once for this chat, after it is drawn
      const asked = `${view.name}:${state.open}`;

      if (view.follow && !followed.has(asked)) {
        followed.add(asked);
        actAddon(view.name, view.follow, {});
      }
      if (view.look === 'ai') {
        if (entry.details.parentNode !== featured) {
          featured.append(entry.details);
        }
      } else {
        panels.insertBefore(entry.details, before);
        before = entry.details.nextSibling;
      }
    });

    addons.forEach((entry, name) => {
      if (!seen.has(name)) {
        entry.details.remove();
        addons.delete(name);
      }
    });
  };

  // Once for each chat opened
  const loadAddons = async () => {
    const room = state.open;

    addons.forEach((entry) => entry.body.replaceChildren(skeleton(3)));

    try {
      const data = await post(config.urls.panels, { room });

      if (room === state.open) {
        showAddons(Array.isArray(data.panels) ? data.panels : []);
      }
    } catch (error) {
      addons.forEach((entry) => entry.body.replaceChildren(el('p', 'askdesk-panel__hint', t.panelError)));
    }
  };

  const actAddon = async (name, action, extra) => {
    const room = state.open;
    const entry = addons.get(name);

    if (!entry) {
      return;
    }

    entry.body.replaceChildren(...(action === 'draft' ? [el('p', 'askdesk-panel__hint', t.panelNoteWriting), skeleton(4)] : [skeleton(3)]));
    entry.details.querySelector(':scope > .askdesk-panel__title > .askdesk-panel__again')?.classList.add('is-busy');

    try {
      const data = await post(config.urls.panel, { room, name, action, ...extra });

      // A new draft, or the note sent: what was being changed in the box is done with
      if (data.panel && (action === 'draft' || (action === 'send' && data.panel.note && data.panel.note.sent))) {
        noteEdits.delete(`${name}:${room}`);

        if (action === 'send') {
          noteTicks.delete(`${name}:${room}`);
        }
      }

      if (room === state.open && data.panel) {
        paintAddon(entry.body, data.panel);
      } else if (room === state.open) {
        entry.body.replaceChildren(el('p', 'askdesk-panel__hint', data.error || t.panelError));
      }
    } catch (error) {
      entry.body.replaceChildren(el('p', 'askdesk-panel__hint', t.panelError));
    } finally {
      entry.details.querySelector(':scope > .askdesk-panel__title > .askdesk-panel__again')?.classList.remove('is-busy');
    }
  };


  const skeleton = (lines = 2) => {
    const box = el('div', 'askdesk-skeleton');

    for (let index = 0; index < lines; index += 1) {
      box.append(el('span', ''));
    }

    return box;
  };

  // A suggestion into the box, ready to change before it is sent: the reply, and the page's address
  const useReply = (item) => {
    field.value = [item.reply, item.url].filter(Boolean).join('\n');
    field.focus();
    field.setSelectionRange(field.value.length, field.value.length);
    body.classList.remove('askdesk-room__body--aside');

    if (!reduced.matches) {
      box.animate([{ boxShadow: '0 0 0 4px rgba(14, 134, 254, 0.35)' }, { boxShadow: '0 0 0 0 rgba(14, 134, 254, 0)' }], { duration: 700, easing: EASE });
    }
  };

  // A page that answers: its picture and title (to open), how well it answers, a reply drawn from
  // it, and the button that puts the reply in the box
  const answerCard = (item, index, arrived) => {
    const card = el('article', 'askdesk-suggest__card');
    const top = el('div', 'askdesk-suggest__top');
    const page = el('a', 'askdesk-suggest__page');
    const use = button('askdesk-button askdesk-button--text askdesk-suggest__use', t.useReply, () => useReply(item));

    page.href = item.url;
    page.target = '_blank';
    page.rel = 'noopener noreferrer';
    page.title = t.openPage;

    if (item.image && /^\/(?!\/)/.test(item.image)) {
      const picture = el('img', 'askdesk-suggest__image');

      picture.src = item.image;
      picture.alt = '';
      picture.loading = 'lazy';
      picture.addEventListener('error', () => picture.remove());
      card.append(picture);
    }

    page.append(el('span', '', item.title), icon('external'));
    top.append(page, pill(t.relevance.replace('%s', `${item.score}%`), 'ok'));
    card.append(top);

    if (item.heading) {
      card.append(el('p', 'askdesk-suggest__heading', item.heading));
    }

    card.append(el('p', 'askdesk-suggest__reply', item.reply), use);
    use.disabled = composer.hidden;

    if (arrived && !reduced.matches) {
      card.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 300, delay: index * 70, easing: EASE, fill: 'backwards' });
    }

    return card;
  };

  const paintSuggestions = (arrived = false) => {
    suggestList.replaceChildren();

    if (side.loading) {
      suggestList.append(el('p', 'askdesk-panel__hint', t.suggesting), skeleton(3), skeleton(2));

      return;
    }

    if (side.failed) {
      suggestList.append(el('p', 'askdesk-panel__hint', side.failed));

      return;
    }

    if (side.items === null) {
      return;
    }

    if (!side.items.length) {
      suggestList.append(arrived ? rise(el('p', 'askdesk-panel__hint', t.noSuggestion)) : el('p', 'askdesk-panel__hint', t.noSuggestion));

      return;
    }

    side.items.forEach((item, index) => suggestList.append(answerCard(item, index, arrived)));
  };

  // Asked for each new message of the visitor (the desk keeps what it found with the message)
  const suggestNow = async () => {
    const last = [...state.messages].reverse().find((item) => item.author === 'visitor' && item.id);
    const room = state.open;

    if (!state.detail || side.loading) {
      return;
    }

    if (!last) {
      side.items = null;
      paintSuggestions();

      return;
    }

    if (side.for === last.id) {
      return;
    }

    side.loading = true;
    side.for = last.id;
    side.failed = '';
    paintSuggestions();

    try {
      const data = await post(config.urls.suggest, { room });

      if (room === state.open) {
        side.items = Array.isArray(data.items) ? data.items : [];
        side.failed = data.error && !side.items.length ? data.error : '';
      }
    } catch (error) {
      side.failed = t.error;
      side.for = -1;
    }

    side.loading = false;

    if (room === state.open) {
      paintSuggestions(true);
    } else {
      side.for = -1;
    }
  };

  const transcript = (data) => {
    const box = el('div', 'askdesk-history__transcript');

    (data.messages || []).forEach((item) => {
      const line = el('p', `askdesk-history__line askdesk-history__line--${item.author}`);

      if (item.author !== 'system') {
        line.append(el('b', '', item.author === 'visitor' ? t.visitor : item.name || ''));
      }

      line.append(el('span', '', item.text));
      box.append(line);
    });

    return box;
  };

  const paintHistory = (data) => {
    historyList.replaceChildren();

    if (!data.chats.length && !data.questions.length) {
      historyList.append(el('p', 'askdesk-panel__hint', t.noHistory));

      return;
    }

    if (data.chats.length) {
      historyList.append(el('h3', 'askdesk-history__title', t.pastChats));

      data.chats.forEach((chat) => {
        const item = el('details', 'askdesk-history__chat');
        const summary = el('summary', '');
        const content = el('div', 'askdesk-history__content', '…');
        const meta = el('span', 'askdesk-history__meta', [timeOf(chat.time), chat.staffName, t.messages.replace('%s', chat.messages)].filter(Boolean).join(' · '));

        summary.append(el('span', 'askdesk-history__preview', chat.preview || `#${chat.id}`), meta);
        item.append(summary, content);
        smooth(item);

        // Read when first opened
        item.addEventListener('toggle', async () => {
          if (!item.open || item.dataset.loaded) {
            return;
          }

          item.dataset.loaded = '1';

          try {
            const next = transcript(await post(config.urls.past, { room: chat.id }));

            content.replaceWith(next);
            rise(next);
          } catch (error) {
            content.textContent = t.error;
            delete item.dataset.loaded;
          }
        });

        historyList.append(item);
      });
    }

    if (data.questions.length) {
      historyList.append(el('h3', 'askdesk-history__title', t.pastQuestions));

      data.questions.forEach((question) => {
        const item = el('div', 'askdesk-history__question');
        const meta = el('div', 'askdesk-history__meta');

        meta.append(el('span', '', timeOf(question.time)), pill(question.answered ? t.answered : t.notAnswered, question.answered ? 'blue' : 'warn'));

        if (question.rating) {
          meta.append(pill(question.rating > 0 ? t.helpful : t.notHelpful, question.rating > 0 ? 'ok' : 'warn'));
        }

        item.append(meta, el('p', 'askdesk-history__asked', question.question));

        if (question.answer) {
          item.append(el('p', 'askdesk-history__answer', question.answer));
        }

        historyList.append(item);
      });
    }

    rise(historyList);
  };

  // Once for each chat opened: only signed-in visitors have a history
  const loadHistory = async () => {
    const room = state.open;

    if (!state.detail.member) {
      historyList.replaceChildren(el('p', 'askdesk-panel__hint', t.guestHistory));

      return;
    }

    historyList.replaceChildren(skeleton(2), skeleton(2));

    try {
      const data = await post(config.urls.history, { room });

      if (room === state.open) {
        paintHistory({ chats: data.chats || [], questions: data.questions || [] });
      }
    } catch (error) {
      historyList.replaceChildren(el('p', 'askdesk-panel__hint', t.error));
    }
  };

  const kindIcon = (kind) => icon(kind === 'image' ? 'photo' : (kind === 'video' ? 'video' : 'file'));

  function paintLibrary(moved = false) {
    libraryTabs.querySelectorAll('.askdesk-library__tab').forEach((tab) => {
      tab.setAttribute('aria-pressed', String(tab.dataset.which === library.which));
    });

    if (!library.data) {
      return;
    }

    if (!library.data.available) {
      libraryTabs.hidden = true;
      libraryList.replaceChildren(el('p', 'askdesk-panel__hint', library.data.error || t.libraryNone));

      return;
    }

    const files = library.data[library.which] || [];
    const open = state.detail && state.detail.status === 'active' && state.detail.staff === config.me;

    libraryList.replaceChildren();

    if (!files.length) {
      libraryList.append(el('p', 'askdesk-panel__hint', t.libraryEmpty));
    }

    files.forEach((file) => {
      const item = el('div', `askdesk-library__item askdesk-library__item--${file.kind}`);
      const words = el('span', 'askdesk-library__words');
      const sendIt = button('askdesk-button askdesk-button--text askdesk-library__send', t.librarySend, async () => {
        sendIt.disabled = true;
        say(t.uploading);

        try {
          const data = await post(config.urls.librarySend, { room: state.open, file: file.id });

          say(data.error || '');
        } catch (error) {
          say(t.error);
        }

        sendIt.disabled = composer.hidden || composer.dataset.mode === 'email';
        later(true);
      });

      words.append(el('strong', '', file.name), el('span', '', [sizeOf(file.size), file.by, timeOf(file.added)].filter(Boolean).join(' · ')));
      words.title = file.name;
      sendIt.disabled = !open;
      item.append(kindIcon(file.kind), words, sendIt);
      libraryList.append(item);
    });

    if (moved) {
      rise(libraryList);
    }
  }

  async function loadLibrary() {
    library.loading = true;
    libraryList.replaceChildren(skeleton(2), skeleton(2));

    try {
      library.data = await post(config.urls.library, {});
    } catch (error) {
      library.data = null;
      libraryList.replaceChildren(el('p', 'askdesk-panel__hint', t.error));
    }

    library.loading = false;
    paintLibrary(true);
  }

  libraryInput.addEventListener('change', async () => {
    const file = libraryInput.files[0];

    libraryInput.value = '';

    if (!file) {
      return;
    }

    const image = /^image\//.test(file.type);

    if (file.size > (image ? 1048576 : 10485760)) {
      libraryList.prepend(rise(el('p', 'askdesk-panel__hint askdesk-library__error', image ? t.imageTooLarge : t.tooLarge)));

      return;
    }

    const form = new FormData();

    form.append('folder', library.which);
    form.append('file', file);
    libraryAdd.disabled = true;
    libraryList.prepend(el('p', 'askdesk-panel__hint', t.uploading));

    try {
      const response = await fetch(config.urls.libraryAdd, { method: 'POST', body: form, headers: { 'X-CSRF-Token': config.token, Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await response.json();

      if (data.error) {
        libraryList.firstChild.remove();
        libraryList.prepend(rise(el('p', 'askdesk-panel__hint askdesk-library__error', data.error)));
      } else {
        await loadLibrary();
      }
    } catch (error) {
      libraryList.firstChild.remove();
      libraryList.prepend(rise(el('p', 'askdesk-panel__hint askdesk-library__error', t.error)));
    }

    libraryAdd.disabled = false;
  });

  const paintRoom = () => {
    if (!state.detail) {
      pane.replaceChildren(empty);
      paintHome();

      return;
    }

    pane.replaceChildren(head, body);
    paintHead();
    list.replaceChildren();

    if (!reduced.matches) {
      pane.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 200, easing: EASE });
    }
    state.messages.forEach(showMessage);
    toBottom();

    // Beside it, for this chat
    side.for = -1;
    side.items = null;
    side.failed = '';
    paintSuggestions();
    loadHistory();
    loadAddons();
    suggestNow();
  };

  // A chat on its way: three dots bouncing where it will be
  const loadingRoom = () => {
    const box = el('div', 'askdesk-room__loading');

    box.setAttribute('role', 'status');
    box.setAttribute('aria-label', '…');
    box.append(el('i', ''), el('i', ''), el('i', ''));

    return box;
  };

  const openRoom = (id) => {
    if (state.open !== id) {
      clearTray();
      closeDrawer();
      state.open = id;
      state.after = 0;
      state.detail = null;
      state.messages = [];
      pane.replaceChildren(loadingRoom());
    }

    main.classList.add('askdesk-main--room');

    // On a phone the chat is a page over the list: a step in the history, so going back (the button,
    // a swipe, the browser's own) comes to the list
    if (phone.matches && !(window.history.state && window.history.state.askdesk === 'room')) {
      window.history.pushState({ askdesk: 'room', room: id }, '');
    }

    paintInbox();
    later(true);
  };

  // ---------- On a phone: the list, a chat over it, and back ----------

  // The chat slid away to the right, the list under it; then the list alone
  let leaving = false;

  const toList = (animate = true) => {
    if (!main.classList.contains('askdesk-main--room') || leaving) {
      return;
    }

    const done = () => {
      leaving = false;
      main.classList.remove('askdesk-main--room', 'askdesk-main--peek');
      stage.style.transform = '';
      inbox.style.transform = '';
    };

    if (!animate || !phone.matches || reduced.matches) {
      done();

      return;
    }

    const from = new DOMMatrix(getComputedStyle(stage).transform).m41;

    leaving = true;
    main.classList.add('askdesk-main--peek');
    stage.animate([{ transform: `translateX(${from}px)` }, { transform: 'translateX(100%)' }], { duration: 260, easing: 'cubic-bezier(0.3, 0.7, 0.3, 1)', fill: 'forwards' })
      .finished.then((animation) => {
        done();
        animation.cancel();
      }, () => done());
    inbox.animate([{ transform: inbox.style.transform || 'translateX(-30%)' }, { transform: 'none' }], { duration: 260, easing: 'cubic-bezier(0.3, 0.7, 0.3, 1)' });
  };

  // Back from a chat: the chat slides away, and the history steps back with it when the chat is a
  // step in it (its popstate then finds the list already there)
  const goBack = () => {
    toList();

    if (window.history.state && window.history.state.askdesk === 'room') {
      window.history.back();
    }
  };

  const heardRoom = (room) => {
    if (!room || room.id !== state.open) {
      return;
    }

    const fresh = !state.detail;
    const stay = atBottom();

    state.detail = fresh ? room : { ...state.detail, status: room.status, staff: room.staff, staffName: room.staffName, rating: room.rating, ...(room.page ? { page: room.page, pages: room.pages || [] } : {}) };

    // Messages sent from here wait for their copy from the desk
    const incoming = (room.messages || []).filter((item) => item.id > state.after);

    if (incoming.length) {
      // What was sent from here comes back from the desk: it takes the place of its copy, quietly
      const echoes = state.messages.filter((item) => item.pending && incoming.some((other) => other.author === 'staff' && other.mine && other.text === item.text));

      echoes.forEach((item) => item.node && item.node.remove());
      incoming.forEach((item) => {
        item.fresh = !fresh && !echoes.some((echo) => item.mine && echo.text === item.text);
      });
      state.after = incoming[incoming.length - 1].id;
      state.messages = state.messages.filter((item) => !echoes.includes(item));
      state.messages.push(...incoming);
    }

    // Translated since: each takes the place of its untranslated self
    (room.updates || []).forEach((update) => {
      const index = state.messages.findIndex((item) => item.id === update.id);

      if (index < 0) {
        return;
      }

      const before = state.messages[index];

      // The same quick translation again: nothing to show anew
      if (update.translating && update.draft === before.draft) {
        return;
      }

      state.messages[index] = update;

      if (before.node && before.node.isConnected) {
        const node = showMessage(update);

        before.node.replaceWith(node);
        rise(node);
      }
    });

    if (fresh) {
      paintRoom();
    } else {
      // The head again only when the chat changed hands or state: an open list of what came before stays open
      const shape = `${room.status}|${room.staff}|${room.rating ? 1 : 0}`;

      if (shape !== state.shape) {
        paintHead();
      } else if (room.page && state.pageNode && (state.pageNode.dataset.page !== room.page || state.pageNode.dataset.count !== String((room.pages || []).length))) {
        // The visitor went to another page: the timeline scrolls up to it
        const next = pageView(room, Number(state.pageNode.dataset.count) || 0);

        state.pageNode.replaceWith(next);
        state.pageNode = next;
      }

      if (incoming.length) {
        reveal(incoming, stay);
      } else if (!revealing) {
        showTyping(Boolean(room.typing));
      }
    }
  };

  // ---------- The visitor writing: dots, and their messages a moment after ----------

  let typingNode = null;
  let revealing = false;
  let staffTyped = 0;
  let staffTypingSent = 0;
  const pause = (ms) => new Promise((resolve) => { window.setTimeout(resolve, reduced.matches ? 0 : ms); });

  function showTyping(on) {
    if (!on) {
      if (typingNode) {
        const leaving = typingNode;

        typingNode = null;
        leaving.classList.add('askdesk-msg--leaving');
        window.setTimeout(() => leaving.remove(), reduced.matches ? 0 : 160);
      }

      return;
    }

    if (!typingNode) {
      typingNode = el('article', 'askdesk-msg askdesk-msg--visitor askdesk-msg--typing askdesk-msg--pop');

      const bubble = el('div', 'askdesk-msg__bubble');

      bubble.append(el('span', 'askdesk-dots'));
      bubble.firstChild.append(el('i'), el('i'), el('i'));
      typingNode.append(bubble);
    }

    list.append(typingNode);
  }

  // What the visitor wrote comes in after a moment of dots; the rest at once
  async function reveal(items, stay) {
    const room = state.open;

    revealing = true;

    for (const item of items) {
      if (item.author === 'visitor') {
        showTyping(true);

        if (stay) {
          toBottom(true);
        }

        await pause(500);
        showTyping(false);
      }

      if (room !== state.open) {
        break;
      }

      showMessage(item);

      if (stay) {
        toBottom(true);
      }
    }

    revealing = false;
    suggestNow();
  }

  // Ending a chat: asked once more beside the button, the visitor told what follows
  let confirming = null;

  const closeConfirm = () => {
    if (!confirming) {
      return;
    }

    const leaving = confirming;

    confirming = null;

    if (reduced.matches) {
      leaving.remove();
    } else {
      leaving.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-4px) scale(0.97)' }], { duration: 140, easing: 'ease-in' }).finished.then(() => leaving.remove(), () => leaving.remove());
    }
  };

  // The check, asked as soon as the button is pointed at (it takes a while), kept until the chat moves on
  let endChecked = null;

  const endCheck = () => {
    const room = state.open;
    const after = state.after;

    if (!endChecked || endChecked.room !== room || endChecked.after !== after) {
      endChecked = { room, after, answer: post(config.urls.endCheck, { room }) };
      endChecked.answer.catch(() => { endChecked = null; });
    }

    return endChecked.answer;
  };

  function confirmEnd(anchor) {
    if (confirming) {
      closeConfirm();

      return;
    }

    const box = el('div', 'askdesk-confirm');
    const actions = el('div', 'askdesk-confirm__actions');
    const yes = button('askdesk-button askdesk-button--alarm', t.close, () => {
      closeConfirm();
      act('close');
    });

    // AI reads the chat meanwhile: done, the button turns calm; something left, it stays alarming
    const check = el('div', 'askdesk-confirm__check askdesk-confirm__check--busy');
    const room = state.open;

    check.setAttribute('role', 'status');
    check.append(el('span', 'askdesk-confirm__dot'), el('span', '', t.endChecking));
    box.setAttribute('role', 'alertdialog');
    box.setAttribute('aria-label', t.confirmEnd);
    actions.append(button('askdesk-button askdesk-button--text', t.cancel, closeConfirm), yes);
    box.append(el('strong', '', t.confirmEnd), el('p', '', t.confirmEndNote), check, actions);
    anchor.parentElement.append(box);
    confirming = box;
    yes.focus();

    endCheck().then((data) => {
      if (confirming !== box || state.open !== room) {
        return;
      }

      const words = el('span', '');

      check.className = 'askdesk-confirm__check';

      if (data.error) {
        check.classList.add('askdesk-confirm__check--failed');
        words.textContent = data.error;
      } else if (data.ok) {
        check.classList.add('askdesk-confirm__check--ok');
        words.textContent = data.reason || t.endOk;
        yes.className = 'askdesk-button askdesk-button--primary';
      } else {
        check.classList.add('askdesk-confirm__check--wait');
        words.append(el('strong', '', t.endWait), document.createTextNode(` ${data.reason || ''}`));
        yes.textContent = t.endAnyway;
      }

      check.replaceChildren(icon(data.ok && !data.error ? 'check' : 'alert'), words);
      rise(check);
    }).catch(() => {
      if (confirming === box) {
        check.className = 'askdesk-confirm__check askdesk-confirm__check--failed';
        check.replaceChildren(el('span', '', t.endCheckFailed));
      }
    });

    if (!reduced.matches) {
      box.animate([{ opacity: 0, transform: 'translateY(-6px) scale(0.96)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'cubic-bezier(0.2, 0.9, 0.3, 1.2)' });
    }
  }

  document.addEventListener('pointerdown', (event) => {
    if (confirming && !confirming.contains(event.target) && !event.target.closest('.askdesk-room__end')) {
      closeConfirm();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeConfirm();
    }
  });

  const act = async (what) => {
    try {
      const data = await post(config.urls[what], { room: state.open });

      if (data.error) {
        window.alert(data.error);
      }

      if (what === 'take' && !data.error) {
        field.focus();
      }
    } catch (error) {
      window.alert(t.error);
    }

    later(true);
  };

  composer.addEventListener('submit', async (event) => {
    event.preventDefault();

    const text = field.value.trim();

    if ((!text && !staged.length) || !state.open) {
      return;
    }

    if (composer.dataset.mode === 'email') {
      if (text) {
        await replyByEmail(text);
      }

      return;
    }

    // What waits above the box goes first; then the words, if any
    if (staged.length) {
      await sendStaged();
    }

    if (!text) {
      return;
    }

    const pending = { id: 0, author: 'staff', mine: true, name: '', text, time: new Date().toISOString(), pending: true };

    field.value = '';
    state.messages.push(pending);
    showMessage(pending);
    toBottom(true);
    say('');

    try {
      const data = await post(config.urls.send, { room: state.open, text });

      if (data.error) {
        state.messages.splice(state.messages.indexOf(pending), 1);
        field.value = text;
        say(data.error);
        paintRoom();
      } else if (data.warning) {
        say(data.warning);
      }
    } catch (error) {
      state.messages.splice(state.messages.indexOf(pending), 1);
      field.value = text;
      say(t.error);
      paintRoom();
    }

    later(true);
  });

  // A picture made smaller before it is sent: 1920 pixels at most on its longer side, as WebP (JPEG
  // where the browser cannot write WebP), smaller still until under 1 MB. Moving GIFs, and pictures
  // that would not get smaller, are sent as they are
  const shrinkPicture = async (file) => {
    if (!/^image\/(?:jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) {
      return file;
    }

    let bitmap;

    try {
      bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch (error) {
      return file;
    }

    const encode = (canvas, type, quality) => new Promise((resolve) => { canvas.toBlob(resolve, type, quality); });
    let scale = Math.min(1, 1920 / Math.max(bitmap.width, bitmap.height));
    let made = null;

    for (let round = 0; round < 5 && !made; round += 1) {
      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d');

      canvas.width = Math.max(1, Math.round(bitmap.width * scale));
      canvas.height = Math.max(1, Math.round(bitmap.height * scale));
      context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

      for (const quality of [0.82, 0.68]) {
        let blob = await encode(canvas, 'image/webp', quality);

        if (!blob || blob.type !== 'image/webp') {
          // JPEG has no transparency: on white
          context.globalCompositeOperation = 'destination-over';
          context.fillStyle = '#fff';
          context.fillRect(0, 0, canvas.width, canvas.height);
          blob = await encode(canvas, 'image/jpeg', quality);
        }

        if (blob && blob.size <= 1048576) {
          made = blob;
          break;
        }
      }

      scale *= 0.75;
    }

    if (bitmap.close) {
      bitmap.close();
    }

    if (!made || (made.size >= file.size && file.size <= 1048576)) {
      return file;
    }

    const base = (file.name || 'picture').replace(/\.[^.]+$/, '') || 'picture';

    return new File([made], `${base}.${made.type === 'image/webp' ? 'webp' : 'jpg'}`, { type: made.type });
  };

  // A file sent in one's chat: the attach button, dropped on the chat, or a picture pasted in the box
  async function sendFile(given) {
    if (!given || !state.open) {
      return;
    }

    attach.disabled = true;
    say(t.uploading);

    const file = await shrinkPicture(given);
    const image = /^image\//.test(file.type);

    if (file.size > (image ? 1048576 : 10485760)) {
      say(image ? t.imageTooLarge : t.tooLarge);
      attach.disabled = false;

      return;
    }

    const form = new FormData();

    form.append('room', String(state.open));
    form.append('file', file);

    try {
      const response = await fetch(config.urls.upload, { method: 'POST', body: form, headers: { 'X-CSRF-Token': config.token, Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await response.json();

      say(data.error || '');
    } catch (error) {
      say(t.error);
    }

    attach.disabled = false;
    later(true);
  }

  const sendFiles = (files) => {
    files.slice(0, 5).forEach(stageFile);
  };

  // What waits above the box, sent in the order chosen
  const sendStaged = async () => {
    const items = staged;

    staged = [];
    paintTray();

    for (const item of items) {
      if (item.kind === 'sticker') {
        try {
          const data = await post(config.urls.send, { room: state.open, sticker: item.sticker.id });

          say(data.error || '');
        } catch (error) {
          say(t.error);
        }
      } else {
        await sendFile(item.file);
      }

      if (item.url) {
        URL.revokeObjectURL(item.url);
      }
    }

    later(true);
  };

  attachInput.addEventListener('change', () => {
    const file = attachInput.files[0];

    attachInput.value = '';
    stageFile(file);
  });

  // Only into one's own chat, while it goes on
  const takesFiles = () => !composer.hidden && composer.dataset.mode === 'chat' && !attach.hidden;
  let dragDepth = 0;

  pane.dataset.drop = t.dropHere;
  pane.addEventListener('dragenter', (event) => {
    if (takesFiles() && [...event.dataTransfer.types].includes('Files')) {
      dragDepth += 1;
      pane.classList.add('askdesk-dropping');
    }
  });
  pane.addEventListener('dragover', (event) => {
    if (takesFiles() && [...event.dataTransfer.types].includes('Files')) {
      event.preventDefault();
      event.dataTransfer.dropEffect = 'copy';
    }
  });
  pane.addEventListener('dragleave', () => {
    dragDepth = Math.max(0, dragDepth - 1);

    if (!dragDepth) {
      pane.classList.remove('askdesk-dropping');
    }
  });
  pane.addEventListener('drop', (event) => {
    dragDepth = 0;
    pane.classList.remove('askdesk-dropping');

    if (takesFiles() && event.dataTransfer.files.length) {
      event.preventDefault();
      sendFiles([...event.dataTransfer.files]);
    }
  });
  field.addEventListener('paste', (event) => {
    const files = [...((event.clipboardData && event.clipboardData.files) || [])];

    if (takesFiles() && files.length) {
      event.preventDefault();
      sendFiles(files);
    }
  });

  // The reply, translated, opens in one's own mail program, addressed and with a subject; it is kept
  // with the message. Nothing is sent from the site.
  async function replyByEmail(text) {
    send.disabled = true;
    say('');

    try {
      const data = await post(config.urls.reply, { room: state.open, text });

      if (data.error) {
        say(data.error);
      } else {
        const mail = el('a');

        mail.href = `mailto:${encodeURIComponent(data.email)}?subject=${encodeURIComponent(data.subject)}&body=${encodeURIComponent(data.body)}`;
        mail.click();
        field.value = '';
        say(data.warning || t.replyOpened);
        note.classList.toggle('askdesk-composer__note--ok', !data.warning);
      }
    } catch (error) {
      say(t.error);
    }

    send.disabled = false;
    later(true);
  }

  // An address of one of the site's articles, however long, ends at its number
  // (index.php?option=com_content&view=article&id=12:alias, or with the option left out as a menu
  // item supplies it: /tw/?view=article&id=12). Addresses as the address bar shows them are made
  // short by the desk when the message is sent
  const ARTICLE = new RegExp(`${(config.root || window.location.origin).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:/[^\\s?#]*)?\\?[^\\s]*?\\bview=article&(?:amp;)?id=(\\d+)[^\\s]*`, 'g');
  const shortArticle = (found, id) => {
    // A full stop or bracket after the address stays where it was
    const tail = (found.match(/[.,;:!?)\]}」』。，、！？]+$/) || [''])[0];

    return !/[?&](?:amp;)?option=/.test(found) || /\boption=com_content\b/.test(found)
      ? `${config.root || window.location.origin}/index.php?option=com_content&view=article&id=${id}${tail}`
      : found;
  };

  field.addEventListener('input', () => {
    staffTyped = Date.now();

    // Told soon, not only at the next turn of asking
    if (field.value.trim() && Date.now() - staffTypingSent > 3000) {
      later(true);
    }

    const short = field.value.replace(ARTICLE, shortArticle);

    if (short !== field.value) {
      const end = field.value.length - field.selectionEnd;

      field.value = short;
      field.setSelectionRange(short.length - end, short.length - end);
    }
  });

  field.addEventListener('keydown', (event) => {
    // Enter confirms a candidate while composing Chinese or Japanese, so it must not send; in an
    // e-mail it starts a new line
    if (composer.dataset.mode !== 'email' && event.key === 'Enter' && !event.shiftKey && !event.isComposing && event.keyCode !== 229) {
      event.preventDefault();
      composer.requestSubmit();
    }
  });

  // ---------- AI search ----------

  let searching = 0;

  const closeResults = () => {
    if (results.hidden) {
      return;
    }

    if (reduced.matches) {
      results.hidden = true;

      return;
    }

    results.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-6px)' }], { duration: 150, easing: EASE }).finished.then(() => {
      results.hidden = true;
    }, () => {});
  };

  const openResults = (...nodes) => {
    const was = results.hidden;

    results.replaceChildren(...nodes);
    results.hidden = false;

    if (was && !reduced.matches) {
      results.animate([{ opacity: 0, transform: 'translateY(-6px) scale(0.98)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: EASE });
    }
  };

  search.addEventListener('submit', async (event) => {
    event.preventDefault();

    const text = searchField.value.trim();
    const mine = ++searching;

    if (text.length < 2) {
      return;
    }

    openResults(el('p', 'askdesk-panel__hint', t.searching), skeleton(3), skeleton(2));

    try {
      const data = await post(config.urls.search, { text });

      if (mine !== searching) {
        return;
      }

      const items = Array.isArray(data.items) ? data.items : [];

      openResults(...(items.length
        ? items.map((item, index) => answerCard(item, index, true))
        : [rise(el('p', 'askdesk-panel__hint', data.error || t.noResults))]));
    } catch (error) {
      if (mine === searching) {
        openResults(el('p', 'askdesk-panel__hint', t.error));
      }
    }
  });

  searchField.addEventListener('focus', () => {
    if (results.childElementCount && searchField.value.trim()) {
      openResults(...results.childNodes);
    }
  });

  searchField.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeResults();
    }
  });

  // Cleared: the answers go
  searchField.addEventListener('input', () => {
    if (!searchField.value.trim()) {
      searching += 1;
      closeResults();
    }
  });

  // ---------- Status and language ----------

  const paintMe = () => {
    if (state.me) {
      statusSelect.value = state.me.status;
      statusSelect.dataset.status = state.me.status;
      languageSelect.value = state.me.language;
      paintPresence(state.me.status);
      paintLanguage();
    }
  };

  // ---------- How one shows oneself: a name, and a pet that bows or a picture of one's own ----------

  let profileBox = null;

  const closeProfile = () => {
    if (!profileBox) {
      return;
    }

    const leaving = profileBox;

    profileBox = null;
    leaving.classList.add('askdesk-profile--leaving');
    window.setTimeout(() => leaving.remove(), reduced.matches ? 0 : 160);
  };

  async function openProfile() {
    if (profileBox) {
      closeProfile();

      return;
    }

    let data;

    try {
      data = await post(config.urls.profile, {});
    } catch (error) {
      return;
    }

    if (!data || data.error) {
      return;
    }

    const box = el('form', 'askdesk-profile');
    const preview = el('span', 'askdesk-me askdesk-profile__face');
    const nameField = el('input', 'askdesk-profile__name');
    const pets = el('div', 'askdesk-profile__pets');
    const picture = el('input');
    const thanks = el('input');
    const problem = el('p', 'askdesk-profile__problem');
    const actions = el('div', 'askdesk-confirm__actions');
    const clear = el('input');
    let chosen = data.avatar || '';

    const paintPreview = (src) => {
      preview.replaceChildren();

      if (src) {
        const img = el('img', '');

        img.src = src;
        img.alt = '';
        preview.append(img);
      } else {
        preview.textContent = Array.from(String(nameField.value || data.account || '?').trim() || '?')[0].toUpperCase();
      }
    };

    nameField.type = 'text';
    nameField.maxLength = 100;
    nameField.value = data.display || '';
    nameField.placeholder = data.account || '';
    nameField.setAttribute('aria-label', t.displayName);
    picture.type = 'file';
    picture.accept = 'image/png,image/jpeg,image/webp,image/gif';
    thanks.type = 'file';
    thanks.accept = picture.accept;
    clear.type = 'hidden';
    clear.name = 'clear';
    clear.value = '0';

    // The pets: those that can bow to choose, the others shown, not to be chosen
    const choose = (id, chip) => {
      chosen = id;
      pets.querySelectorAll('.askdesk-profile__pet').forEach((item) => item.classList.toggle('askdesk-profile__pet--on', item === chip));
      paintPreview(id ? (data.pets.find((pet) => pet.id === id) || {}).thumb : (data.picture || ''));
    };

    const none = button('askdesk-profile__pet', t.noPet, () => choose('', none));

    pets.append(none);

    (data.pets || []).forEach((pet) => {
      const chip = button('askdesk-profile__pet', '', () => choose(pet.id, chip));

      if (pet.thumb) {
        const img = el('img', '');

        img.src = pet.thumb;
        img.alt = '';
        chip.append(img);
      }

      chip.append(el('span', '', pet.title));
      chip.disabled = !pet.thanks;
      chip.title = pet.thanks ? pet.title : t.petNoThanks;
      chip.classList.toggle('askdesk-profile__pet--on', chosen === pet.id);
      pets.append(chip);
    });

    none.classList.toggle('askdesk-profile__pet--on', chosen === '');

    picture.addEventListener('change', () => {
      const file = picture.files[0];

      if (file) {
        if (file.size > 1024 * 1024) {
          problem.textContent = t.imageTooLarge;
          picture.value = '';

          return;
        }

        choose('', none);
        paintPreview(URL.createObjectURL(file));
      }
    });

    const label = (text, control) => {
      const row = el('label', 'askdesk-profile__row');

      row.append(el('span', '', text), control);

      return row;
    };

    // A picture to upload, as a tile: what there is now (or the one chosen), its name and size, a
    // button to choose or change it; a picture dropped on it is taken too
    const tile = (title, hint, input, current) => {
      const box = el('label', 'askdesk-upload');
      const thumb = el('span', 'askdesk-upload__thumb');
      const words = el('span', 'askdesk-upload__words');
      const note = el('small', '', hint);
      const action = el('span', 'askdesk-upload__action', current ? t.change : t.choosePicture);
      const show = (src) => {
        thumb.replaceChildren();

        if (src) {
          const picture = el('img', '');

          picture.src = src;
          picture.alt = '';
          thumb.append(picture);
        } else {
          thumb.append(icon('photo'));
        }
      };

      words.append(el('strong', '', title), note);
      box.append(thumb, words, action, input);
      show(current);

      input.addEventListener('change', () => {
        const file = input.files[0];

        if (!file) {
          return;
        }

        show(URL.createObjectURL(file));
        note.textContent = `${file.name} · ${sizeOf(file.size)}`;
        action.textContent = t.change;
        box.classList.add('askdesk-upload--chosen');
        rise(thumb);
      });

      box.addEventListener('dragover', (event) => {
        event.preventDefault();
        box.classList.add('askdesk-upload--over');
      });
      box.addEventListener('dragleave', () => box.classList.remove('askdesk-upload--over'));
      box.addEventListener('drop', (event) => {
        event.preventDefault();
        box.classList.remove('askdesk-upload--over');

        const file = [...event.dataTransfer.files].find((item) => /^image\//.test(item.type));

        if (file) {
          const chosen = new DataTransfer();

          chosen.items.add(file);
          input.files = chosen.files;
          input.dispatchEvent(new Event('change'));
        }
      });

      return box;
    };

    const uploads = el('div', 'askdesk-upload-pair');

    uploads.append(
      tile(t.faceTile, t.faceHint, picture, data.picture || ''),
      tile(t.thanksTile, t.thanksHint, thanks, data.thanksPicture || ''),
    );

    actions.append(
      button('askdesk-button askdesk-button--text', t.clearFace, () => {
        clear.value = '1';
        choose('', none);
        data.picture = '';
        paintPreview('');
      }),
      el('span', 'askdesk-bar__space'),
      button('askdesk-button askdesk-button--text', t.cancel, closeProfile),
    );

    const save = el('button', 'askdesk-button askdesk-button--primary', t.save);

    save.type = 'submit';
    actions.append(save);
    box.append(el('strong', '', t.profile), preview, label(t.displayName, nameField), el('span', 'askdesk-profile__label', t.pet), pets, el('span', 'askdesk-profile__label', t.uploadOwn), uploads, clear, problem, actions);
    paintPreview(data.face ? data.face.src : '');
    // From the foot of the side, upward; from the bar (the side folded away), downward
    if (account.contains(meFace)) {
      box.classList.add('askdesk-profile--side');
      main.append(box);
    } else {
      bar.append(box);
    }

    profileBox = box;
    nameField.focus();

    if (!reduced.matches) {
      box.animate([{ opacity: 0, transform: 'translateY(-6px) scale(0.97)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'cubic-bezier(0.2, 0.9, 0.3, 1.2)' });
    }

    box.addEventListener('submit', async (event) => {
      event.preventDefault();
      save.disabled = true;
      problem.textContent = '';

      const form = new FormData();

      form.append('display_name', nameField.value.trim());
      form.append('avatar', chosen);
      form.append('clear', clear.value);

      if (picture.files[0]) {
        form.append('picture', picture.files[0]);
      }

      if (thanks.files[0]) {
        form.append('thanks', thanks.files[0]);
      }

      try {
        const response = await fetch(config.urls.saveProfile, { method: 'POST', body: form, headers: { 'X-CSRF-Token': config.token, Accept: 'application/json' }, credentials: 'same-origin' });
        const saved = await response.json();

        if (saved.error) {
          problem.textContent = saved.error;
          save.disabled = false;

          return;
        }

        config.meName = saved.name;
        paintMeFace(saved.name, saved.face ? saved.face.src : '');
        closeProfile();
      } catch (error) {
        problem.textContent = t.error;
        save.disabled = false;
      }
    });
  }

  document.addEventListener('pointerdown', (event) => {
    if (profileBox && !profileBox.contains(event.target) && !meFace.contains(event.target) && !account.contains(event.target)) {
      closeProfile();
    }
  });

  const setMe = async (values) => {
    try {
      const data = await post(config.urls.status, values);

      if (data.me) {
        state.me = data.me;
        paintMe();
      }
    } catch (error) {
      // Shown as it was
    }
  };

  statusSelect.addEventListener('change', () => {
    paintPresence(statusSelect.value);
    setMe({ status: statusSelect.value });
  });
  // Another language: the console comes again in it (its words are the server's)
  languageSelect.addEventListener('change', async () => {
    languageSelect.disabled = true;
    await setMe({ language: languageSelect.value });
    window.location.reload();
  });

  // ---------- Asking what is new ----------

  const heardRooms = (rooms) => {
    const before = new Map(state.rooms.map((room) => [room.id, room]));

    // Rung for: a chat that starts waiting, a visitor writing in one's own chat
    if (!state.first) {
      rooms.forEach((room) => {
        const was = before.get(room.id);

        if (room.status === 'waiting' && (!was || was.status !== 'waiting')) {
          tell(`${t.waiting}: ${room.name || `${t.visitor} #${room.id}`}`);
        } else if (room.status === 'message' && !was) {
          tell(`${t.message}: ${room.name || `${t.visitor} #${room.id}`}`);
        } else if (was && room.staff === config.me && room.unread > was.unread && !(room.id === state.open && !document.hidden)) {
          tell(room.preview);
        }
      });
    }

    state.first = false;
    state.rooms = rooms;
    paintInbox();
  };

  async function poll() {
    if (polling) {
      return;
    }

    polling = true;

    try {
      // Writing in one's own chat just now: the visitor sees dots
      const typing = !composer.hidden && composer.dataset.mode === 'chat' && Boolean(field.value.trim()) && Date.now() - staffTyped < 4000;

      if (typing) {
        staffTypingSent = Date.now();
      }

      // Messages seen still being translated, to have them again once they are
      const pending = state.messages.filter((item) => item.translating).map((item) => item.id).slice(-50);
      const data = await post(config.urls.poll, { room: state.open, after: state.after, typing, pending });

      if (data.error) {
        if (data.signedOut || data.expired) {
          root.replaceChildren(el('p', 'askdesk-denied', data.signedOut ? t.signedOut : t.expired));
          polling = false;

          return;
        }
      } else {
        if (!state.me && data.me) {
          state.me = data.me;
          paintMe();

          // Opening the console means being there, the first time
          if (data.me.status === 'away' && !window.sessionStorage.getItem('askdesk:away')) {
            setMe({ status: 'online' });
          }
        }

        paintReach(data.visitors);
        paintPeople(data.staff);
        heardRooms(Array.isArray(data.rooms) ? data.rooms : []);
        heardRoom(data.room);

        if (wanted && state.rooms.some((room) => room.id === wanted)) {
          const id = wanted;

          wanted = 0;
          openRoom(id);
        }
      }
    } catch (error) {
      // Asked again in a moment
    }

    polling = false;
    later();
  }

  // Set away on purpose: not set online again on the next page
  statusSelect.addEventListener('change', () => {
    try {
      if (statusSelect.value === 'away') {
        window.sessionStorage.setItem('askdesk:away', '1');
      } else {
        window.sessionStorage.removeItem('askdesk:away');
      }
    } catch (error) {
      // Not remembered
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      later(true);
    }
  });

  // The history of a phone (or of the console as an app): a first step under the list, so going back
  // from the list stays in the console instead of leaving it (for the sign-in page, say); a step for a
  // chat over it (openRoom). Back to the list from a chat; back from the list: still the list
  const app = () => phone.matches || window.matchMedia('(display-mode: standalone)').matches;

  if (app() && !(window.history.state && window.history.state.askdesk)) {
    window.history.replaceState({ askdesk: 'base' }, '');
    window.history.pushState({ askdesk: 'list' }, '');
  }

  window.addEventListener('popstate', (event) => {
    const step = (event.state && event.state.askdesk) || '';

    if (step === 'room') {
      if (state.open) {
        main.classList.add('askdesk-main--room');
      }

      return;
    }

    // Back by the browser itself (Safari's swipe has drawn its own going): the list at once
    toList(false);

    if (step === 'base' || (!step && app())) {
      window.history.pushState({ askdesk: 'list' }, '');
    }
  });

  // A swipe from the left edge of a chat takes it back to the list, the chat following the finger and
  // the list showing under it (Safari's own swipe back, where it wins, ends at the list too)
  let swipe = null;

  stage.addEventListener('touchstart', (event) => {
    if (!phone.matches || !main.classList.contains('askdesk-main--room') || event.touches.length !== 1 || event.touches[0].clientX > 32) {
      swipe = null;

      return;
    }

    swipe = { x: event.touches[0].clientX, y: event.touches[0].clientY, t: Date.now(), dx: 0, on: false };
  }, { passive: true });

  stage.addEventListener('touchmove', (event) => {
    if (!swipe) {
      return;
    }

    const touch = event.touches[0];
    const dx = touch.clientX - swipe.x;
    const dy = touch.clientY - swipe.y;

    if (!swipe.on) {
      if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(dx)) {
        swipe = null;

        return;
      }

      if (dx < 10) {
        return;
      }

      swipe.on = true;
      main.classList.add('askdesk-main--peek');
    }

    event.preventDefault();
    swipe.dx = Math.max(0, dx);

    const width = stage.clientWidth || window.innerWidth;

    stage.style.transform = `translateX(${swipe.dx}px)`;
    inbox.style.transform = `translateX(${-30 + 30 * Math.min(1, swipe.dx / width)}%)`;
  }, { passive: false });

  const swipeEnd = () => {
    if (!swipe || !swipe.on) {
      swipe = null;

      return;
    }

    const width = stage.clientWidth || window.innerWidth;
    const fast = swipe.dx / Math.max(1, Date.now() - swipe.t) > 0.6;
    const away = swipe.dx > width * 0.35 || fast;

    swipe = null;

    if (away) {
      goBack();

      return;
    }

    // Not far enough: back where it was
    const from = stage.style.transform;

    stage.animate([{ transform: from }, { transform: 'translateX(0)' }], { duration: 200, easing: 'cubic-bezier(0.3, 0.7, 0.3, 1)' });
    stage.style.transform = '';
    inbox.style.transform = '';
    window.setTimeout(() => main.classList.remove('askdesk-main--peek'), 200);
  };

  stage.addEventListener('touchend', swipeEnd);
  stage.addEventListener('touchcancel', swipeEnd);

  poll();
})(window, document);
