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
// Rows changed since a moment: a page of this many, and at most so many pages (more: the table is read again)
export const SINCE_PAGE = 50;
export const SINCE_PAGES = 10;

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
  // What has already come, to be drawn at once (a widget opened shows what its card showed, not a skeleton first)
  const came = new Map();
  const latestRows = new Map();
  // (Only the latest asked: one forgotten and asked again is not overwritten by the older answer)
  const keep = (key, asked, latest) => asked.then((got) => { if (latest() === asked) came.set(key, got); }, () => null);

  return {
    board: () => post(config.urls.board, {}),
    save: (widgets) => post(config.urls.boardSave, { widgets }),
    // A Super User's board, as the dashboard template for everyone
    saveTemplate: (widgets) => post(config.urls.boardTemplate, { widgets }),
    templates: (source, query = '', page = 1) => queued(() => post(config.urls.sourceTemplates, { source, query, page })),

    fields(source, template) {
      const key = `${source}:${template}`;

      if (!fields.has(key)) {
        const asked = queued(() => post(config.urls.sourceFields, { source, template }));

        asked.catch(() => fields.delete(key));
        fields.set(key, asked);
        keep(`f:${key}`, asked, () => fields.get(key));
      }

      return fields.get(key);
    },

    fieldsNow: (source, template) => came.get(`f:${source}:${template}`) || null,

    // A page of records
    rows({ source, template, page = 1, size = 100, query = '', mine = 'all', range = 'all' }) {
      const asked = queued(() => post(config.urls.sourceRows, { source, template, page, size, query, ...whose(mine), ...rangeDays(range) }));

      // The first page kept: a table opened (or shown again) starts from it while it is asked for afresh
      if (page === 1) {
        const key = `r:${JSON.stringify([source, template, size, query, mine, range])}`;

        latestRows.set(key, asked);
        keep(key, asked, () => latestRows.get(key));
      }

      return asked;
    },

    rowsNow: ({ source, template, size = 100, query = '', mine = 'all', range = 'all' }) => came.get(`r:${JSON.stringify([source, template, size, query, mine, range])}`) || null,

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
        keep(`a:${key}`, asked, () => sums.get(key));
      }

      return sums.get(key);
    },

    allNow: ({ source, template, query = '', mine = 'all', range = 'all' }) => came.get(`a:${JSON.stringify([source, template, query, mine, range])}`) || null,

    // Changes to a record go through the queue too: the source's limit counts every request
    update: (body) => queued(() => post(config.urls.sourceUpdate, body)),
    // A record deleted, for good (0.6.1; a source that can)
    remove: (body) => queued(() => post(config.urls.sourceDelete, body)),
    // Something a source offers to do to a record (0.6.2: the desk's cases closed)
    act: (body) => queued(() => post(config.urls.sourceAct, body)),
    // A record's page of the source's own, for a frame; row '' for a new one (0.6.5). Asked each time: what
    // starts it may carry a sign-in that does not last
    embed: (body) => (config.urls.sourceEmbed ? queued(() => post(config.urls.sourceEmbed, body)) : Promise.reject(new Error(config.t('error')))),
    // Which records others changed from the dashboard since a point (0.6.2)
    changes: (body) => (config.urls.sourceChanges ? post(config.urls.sourceChanges, body) : Promise.resolve({ changes: [], last: 0 })),
    // The rows changed since a moment (a table checking for others' changes), a page of them
    since: ({ source, template, query = '', mine = 'all', range = 'all', since, page = 1 }) => queued(() => post(config.urls.sourceRows, { source, template, page, size: SINCE_PAGE, query, since, ...whose(mine), ...rangeDays(range) })),

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
