// What the dashboard asks the console's server for. Records come through a queue, one request at a time
// and a little apart, as the systems behind the sources limit how fast they are asked (BizForm: three
// queries a second). Widgets of the same template share what was fetched.

export class SignIn extends Error {
  constructor(url, message) {
    super(message);
    this.url = url;
  }
}

const GAP = 380;
// Records read for a chart or a number: up to this many pages of 100
export const PAGES_FOR_SUMS = 5;

export const makeApi = (config) => {
  const post = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': config.token },
      body: JSON.stringify(body || {}),
      credentials: 'same-origin',
    });
    const data = await response.json().catch(() => null);

    if (!data) {
      throw new Error(config.t('error'));
    }

    if (data.signin) {
      throw new SignIn(data.signin, data.error);
    }

    if (data.error) {
      // Why, when the source says (0.6.1): not this person's to change, under review
      throw Object.assign(new Error(data.error), { detail: data.detail || '', locked: data.locked || '' });
    }

    return data;
  };

  // One at a time, a little apart
  let chain = Promise.resolve();
  const queued = (work) => {
    const run = chain.then(work);

    chain = run.catch(() => {}).then(() => new Promise((done) => { setTimeout(done, GAP); }));

    return run;
  };

  const fields = new Map();
  const sums = new Map();

  return {
    board: () => post(config.urls.board, {}),
    save: (widgets) => post(config.urls.boardSave, { widgets }),
    // A Super User's board, as the dashboard template for everyone
    saveTemplate: (widgets) => post(config.urls.boardTemplate, { widgets }),
    templates: (source, query = '', page = 1) => post(config.urls.sourceTemplates, { source, query, page }),

    fields(source, template) {
      const key = `${source}:${template}`;

      if (!fields.has(key)) {
        const asked = post(config.urls.sourceFields, { source, template });

        asked.catch(() => fields.delete(key));
        fields.set(key, asked);
      }

      return fields.get(key);
    },

    // A page of records
    rows: ({ source, template, page = 1, size = 100, query = '', mine = 'all', range = 'all' }) => queued(() => post(config.urls.sourceRows, { source, template, page, size, query, ...whose(mine), ...rangeDays(range) })),

    // Records for sums: the first pages, shared by all widgets of the template; partial when there are more
    all({ source, template, query = '', mine = 'all', range = 'all' }) {
      const key = JSON.stringify([source, template, query, mine, range]);

      if (!sums.has(key)) {
        const asked = (async () => {
          const rows = [];
          let total = 0;

          for (let page = 1; page <= PAGES_FOR_SUMS; page += 1) {
            const got = await queued(() => post(config.urls.sourceRows, { source, template, page, size: 100, query, ...whose(mine), ...rangeDays(range) }));

            rows.push(...got.rows);
            total = got.total;

            if (rows.length >= total || !got.rows.length) {
              break;
            }
          }

          return { rows, total, partial: rows.length < total };
        })();

        asked.catch(() => sums.delete(key));
        sums.set(key, asked);
      }

      return sums.get(key);
    },

    update: (body) => post(config.urls.sourceUpdate, body),
    // A record deleted, for good (0.6.1; a source that can)
    remove: (body) => post(config.urls.sourceDelete, body),

    // What was fetched for a template is old: fetched again when next asked
    forget(source, template) {
      [...sums.keys()].forEach((key) => {
        const [s, t] = JSON.parse(key);

        if (s === source && t === template) {
          sums.delete(key);
        }
      });
    },
  };
};

// The times the dashboard can cover, and their days (from, to; Y-m-d, here): none for all time
export const RANGES = ['all', 'today', 'd7', 'd30', 'month', 'lastMonth', 'year'];

export const rangeDays = (range) => {
  const day = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const now = new Date();
  const back = (n) => new Date(now.getFullYear(), now.getMonth(), now.getDate() - n);

  switch (range) {
    case 'today': return { from: day(now), to: day(now) };
    case 'd7': return { from: day(back(6)), to: day(now) };
    case 'd30': return { from: day(back(29)), to: day(now) };
    case 'month': return { from: day(new Date(now.getFullYear(), now.getMonth(), 1)), to: day(now) };
    case 'lastMonth': return { from: day(new Date(now.getFullYear(), now.getMonth() - 1, 1)), to: day(new Date(now.getFullYear(), now.getMonth(), 0)) };
    case 'year': return { from: day(new Date(now.getFullYear(), 0, 1)), to: day(now) };
    default: return {};
  }
};

// Whose records: one's own (mine), another person's (person: their id), or everyone's
export const whose = (who) => ({ mine: who === 'me' || who === true, person: /^\d+$/.test(String(who)) ? Number(who) : 0 });
