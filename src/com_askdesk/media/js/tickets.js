/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 *
 * The visitor's tickets (0.6.2): the address they left a message with and the code sent to it (a member signed
 * in skips both), their tickets, one of them read and written to.
 */
(() => {
  const root = document.querySelector('[data-askdesk-tickets]');

  if (!root) {
    return;
  }

  const config = JSON.parse(root.dataset.askdeskTickets || '{}');

  if (!config.urls) {
    return;
  }

  const t = config.strings || {};
  const state = { email: '', who: null, room: 0, timer: 0 };

  const el = (tag, className = '', text = '') => {
    const node = document.createElement(tag);

    if (className) {
      node.className = className;
    }

    if (text) {
      node.textContent = text;
    }

    return node;
  };

  const button = (className, text, run) => {
    const node = el('button', className, text);

    node.type = 'button';
    node.addEventListener('click', run);

    return node;
  };

  const post = async (url, body = {}) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.token },
      body: JSON.stringify(body),
    });

    return response.json();
  };

  const when = (iso) => {
    try {
      return new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch (error) {
      return iso;
    }
  };

  // One card, its contents changed as one goes
  const card = el('div', 'askdesk-tickets__card');
  const problem = el('p', 'askdesk-tickets__problem');

  problem.setAttribute('role', 'alert');
  root.replaceChildren(card);

  const say = (text) => {
    problem.textContent = text || '';
    problem.hidden = !text;
  };

  const show = (...nodes) => {
    window.clearTimeout(state.timer);
    say('');
    card.replaceChildren(...nodes, problem);
  };

  const heading = () => {
    const head = el('div', 'askdesk-tickets__head');
    const icon = el('img', 'askdesk-tickets__icon');

    icon.src = config.icon;
    icon.alt = '';
    head.append(icon, el('h1', 'askdesk-tickets__title', t.title));

    return head;
  };

  const busy = (node, on) => {
    node.disabled = on;
    node.classList.toggle('askdesk-tickets__button--busy', on);
  };

  // ---------- The address, then the code ----------

  const askEmail = () => {
    const form = el('form', 'askdesk-tickets__form');
    const field = el('input', 'askdesk-tickets__field');
    const go = el('button', 'askdesk-tickets__button', t.sendCode);

    field.type = 'email';
    field.required = true;
    field.autocomplete = 'email';
    field.value = state.email;
    field.setAttribute('aria-label', t.email);
    field.placeholder = t.email;
    go.type = 'submit';
    form.append(field, go);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      busy(go, true);

      try {
        const data = await post(config.urls.code, { email: field.value.trim() });

        if (data.error) {
          say(data.error);
        } else {
          state.email = field.value.trim();
          askCode(data.text);

          return;
        }
      } catch (error) {
        say(t.failed);
      }

      busy(go, false);
    });

    const parts = [heading(), el('p', 'askdesk-tickets__lead', t.lead), form];

    // A member signs in instead, and sees their own
    if (config.login) {
      const member = el('a', 'askdesk-tickets__link', t.signIn);

      member.href = config.login;
      parts.push(el('p', 'askdesk-tickets__or', t.or), member);
    }

    show(...parts);
    field.focus();
  };

  const askCode = (sent) => {
    const form = el('form', 'askdesk-tickets__form');
    const field = el('input', 'askdesk-tickets__field askdesk-tickets__field--code');
    const go = el('button', 'askdesk-tickets__button', t.verify);

    field.inputMode = 'numeric';
    field.autocomplete = 'one-time-code';
    field.maxLength = 6;
    field.pattern = '[0-9]{6}';
    field.required = true;
    field.placeholder = '000000';
    field.setAttribute('aria-label', t.code);
    go.type = 'submit';
    form.append(field, go);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      busy(go, true);

      try {
        const data = await post(config.urls.verify, { email: state.email, code: field.value.trim() });

        if (data.error) {
          say(data.error);
          field.select();
        } else {
          config.token = data.token || config.token;
          list(data);

          return;
        }
      } catch (error) {
        say(t.failed);
      }

      busy(go, false);
    });

    const links = el('div', 'askdesk-tickets__links');

    links.append(
      button('askdesk-tickets__link', t.resend, async () => {
        try {
          const data = await post(config.urls.code, { email: state.email });

          say(data.error || '');
        } catch (error) {
          say(t.failed);
        }
      }),
      button('askdesk-tickets__link', t.change, askEmail),
    );
    show(heading(), el('p', 'askdesk-tickets__lead', sent), form, links);
    field.focus();
  };

  // ---------- The tickets ----------

  const status = (item) => {
    if (!item.open) {
      return el('span', 'askdesk-tickets__pill askdesk-tickets__pill--closed', t.closed);
    }

    return item.answered ? el('span', 'askdesk-tickets__pill askdesk-tickets__pill--answered', t.answered) : el('span', 'askdesk-tickets__pill', t.open);
  };

  const bar = () => {
    const top = el('div', 'askdesk-tickets__bar');
    const who = state.who || {};

    top.append(el('span', 'askdesk-tickets__who', who.member ? t.member.replace('%s', who.member) : who.email || ''));

    // Out of what the code opened (a member signs out of the site as anywhere)
    if (who.email) {
      top.append(button('askdesk-tickets__link', t.out, async () => {
        try {
          await post(config.urls.out);
        } catch (error) {
          // Gone with the session anyway
        }

        state.who = null;
        askEmail();
      }));
    }

    return top;
  };

  const list = (data) => {
    state.who = data.who;
    state.room = 0;

    const items = el('ul', 'askdesk-tickets__list');

    (data.tickets || []).forEach((item) => {
      const row = el('li');
      const open = button('askdesk-tickets__item', '', () => room(item.id));

      open.append(el('span', 'askdesk-tickets__item-title', item.title || '…'), status(item), el('span', 'askdesk-tickets__item-time', when(item.modified)));
      row.append(open);
      items.append(row);
    });

    show(heading(), bar(), data.tickets && data.tickets.length ? items : el('p', 'askdesk-tickets__lead', t.empty));
  };

  const reload = async () => {
    try {
      const data = await post(config.urls.list);

      if (data.error) {
        askEmail();
      } else if (data.who && (data.who.member || data.who.email)) {
        list(data);
      } else {
        askEmail();
      }
    } catch (error) {
      askEmail();
      say(t.failed);
    }
  };

  // ---------- One ticket ----------

  const message = (item) => {
    const mine = item.author === 'visitor';
    const row = el('div', `askdesk-tickets__message askdesk-tickets__message--${mine ? 'mine' : item.author}`);

    if (item.author === 'system') {
      row.textContent = item.text;

      return row;
    }

    const bubble = el('div', 'askdesk-tickets__bubble');

    if (item.file) {
      const link = el('a', '', item.file.name || '');

      link.href = item.file.url || '#';
      link.target = '_blank';
      link.rel = 'noopener';
      bubble.append(link);
    } else {
      bubble.textContent = item.text;
    }

    row.append(el('span', 'askdesk-tickets__by', `${mine ? t.you : item.name || t.staff} · ${when(item.time)}`), bubble);

    return row;
  };

  const room = async (id) => {
    window.clearTimeout(state.timer);

    let data;

    try {
      data = await post(config.urls.room, { id });
    } catch (error) {
      say(t.failed);

      return;
    }

    if (data.error) {
      if (data.gone) {
        reload();
      }

      say(data.error);

      return;
    }

    paint(data, state.room !== id);
    state.room = id;
  };

  const paint = (data, fresh) => {
    const back = button('askdesk-tickets__back', `‹ ${t.back}`, reload);
    const thread = el('div', 'askdesk-tickets__thread');

    thread.append(el('p', 'askdesk-tickets__started', t.started.replace('%s', when(data.created))), ...(data.messages || []).map(message));

    const parts = [back, thread];

    if (data.open) {
      const form = el('form', 'askdesk-tickets__write');
      const field = el('textarea', 'askdesk-tickets__field');
      const go = el('button', 'askdesk-tickets__button', t.send);

      field.rows = 3;
      field.maxLength = 2000;
      field.placeholder = t.write;
      field.setAttribute('aria-label', t.write);
      go.type = 'submit';
      form.append(field, go);
      form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!field.value.trim()) {
          return;
        }

        busy(go, true);

        try {
          const sent = await post(config.urls.write, { id: data.id, text: field.value });

          if (sent.error) {
            say(sent.error);
            busy(go, false);
          } else {
            paint(sent, false);
          }
        } catch (error) {
          say(t.failed);
          busy(go, false);
        }
      });
      parts.push(form);
    } else {
      parts.push(el('p', 'askdesk-tickets__note', t.closedNote));
    }

    show(...parts);
    thread.scrollTop = thread.scrollHeight;

    if (fresh) {
      window.scrollTo({ top: 0 });
    }

    // Looked at again now and then while the page is in view: an answer comes in on its own
    const tick = () => {
      if (state.room !== data.id) {
        return;
      }

      // Not while something is being written, nor while the page is out of sight
      if (document.visibilityState === 'visible' && !card.querySelector('textarea')?.value) {
        room(data.id);
      } else {
        state.timer = window.setTimeout(tick, 30000);
      }
    };

    state.timer = window.setTimeout(tick, 30000);
  };

  reload();
})();
