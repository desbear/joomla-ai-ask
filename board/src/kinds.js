// Kinds of widget: how small each can be, where it is placed at first, what fields it needs. Fields come
// in a source's few kinds: text, number, date (Y-m-d), select (one option), multi (several).

export const COLS = 12;

export const KINDS = {
  number: { min: [2, 2], def: [3, 2] },
  battery: { min: [3, 2], def: [3, 2] },
  pie: { min: [4, 4], def: [5, 5] },
  doughnut: { min: [4, 4], def: [5, 5] },
  bar: { min: [4, 4], def: [6, 5] },
  hbar: { min: [4, 4], def: [6, 5] },
  stacked: { min: [5, 4], def: [7, 5] },
  line: { min: [4, 4], def: [6, 5] },
  area: { min: [4, 4], def: [6, 5] },
  table: { min: [6, 4], def: [12, 5] },
  editgrid: { min: [6, 4], def: [12, 6] },
  gantt: { min: [8, 5], def: [12, 5] },
};

export const isGrid = (kind) => kind === 'table' || kind === 'editgrid';

// A widget's fields in its own order (as dragged), not the template's
export const inOrder = (all, keys) => keys.map((k) => all.find((f) => f.key === k)).filter(Boolean);

// One key moved to before the one at `to` (0…n; n = to the end)
export const moved = (keys, from, to) => {
  const next = keys.filter((_, i) => i !== from);

  next.splice(to > from ? to - 1 : to, 0, keys[from]);

  return next;
};

// Where something dragged would go among these elements: before the first whose middle is past the pointer
export const slotAt = (els, at, axis) => {
  const i = els.findIndex((el) => { const r = el.getBoundingClientRect(); return at < (axis === 'x' ? r.left + r.width / 2 : r.top + r.height / 2); });

  return i < 0 ? els.length : i;
};

export const parts = (fields) => ({
  select: fields.find((f) => f.kind === 'select'),
  num: fields.find((f) => f.kind === 'number'),
  dates: fields.filter((f) => f.kind === 'date'),
  text: fields.find((f) => f.kind === 'text'),
});

// Why a kind cannot be drawn from these fields (nothing when it can): a word's key
export const needs = (kind, fields) => {
  const p = parts(fields);

  if (!fields.length) return 'needFields';

  switch (kind) {
    case 'battery': return p.select ? '' : 'needSelect';
    case 'pie': case 'doughnut': case 'bar': case 'hbar': return p.select ? '' : 'needCategory';
    case 'stacked': return p.dates.length && p.select ? '' : 'needDateCategory';
    case 'line': case 'area': return p.dates.length ? '' : 'needDate';
    case 'gantt': return p.dates.length >= 2 ? '' : 'needTwoDates';
    default: return '';
  }
};

export const recommend = (fields) => {
  const p = parts(fields);

  if (p.dates.length >= 2) return 'gantt';
  if (fields.length > 4) return 'table';
  if (p.dates.length && p.select) return 'stacked';
  if (p.dates.length) return 'line';
  if (p.select && !p.num && /狀態|status|state/i.test(p.select.label)) return 'battery';
  if (p.select) return 'pie';
  if (p.num) return 'number';

  return 'table';
};

// Vittal Design's colours: primary, success, warning, then its tag and avatar hues
export const PALETTE = ['#0E86FE', '#2BCD86', '#FFA700', '#5A51AE', '#00B9AF', '#EB5000', '#9A83FF', '#0576BD', '#AD5E0B', '#901957'];

// The option that means done, when the last one of a select says so
export const doneOf = (field) => {
  const last = field && field.options && field.options[field.options.length - 1];

  return last && /完成|結案|已續約|關閉|done|closed|complete/i.test(last) ? last : null;
};

// A tone for an option, by what it says
export const tone = (value) => {
  const v = String(value);

  if (/完成|已續約|結案|通過|done|closed|complete|approved/i.test(v)) return 'ok';
  if (/進行|洽談|處理中|progress|doing/i.test(v)) return 'info';
  if (/待|未|高|急|todo|new|high|urgent/i.test(v)) return 'warn';

  return '';
};

export const total = (rows, num) => rows.reduce((sum, row) => sum + (num ? Number(row.values[num.key]) || 0 : 1), 0);

export const groupBy = (rows, key, num) => {
  const m = new Map();

  rows.forEach((row) => {
    const value = row.values[key];

    (Array.isArray(value) ? value : [value]).forEach((v) => {
      if (v === '' || v === undefined) return;
      m.set(v, (m.get(v) || 0) + (num ? Number(row.values[num.key]) || 0 : 1));
    });
  });

  return m;
};

const pad = (n) => String(n).padStart(2, '0');

// Every month from the first to the last date in the records ("2026-10")
export const months = (rows, key) => {
  const keys = rows.map((row) => String(row.values[key] || '').slice(0, 7)).filter((k) => /^\d{4}-\d\d$/.test(k)).sort();

  if (!keys.length) return [];

  const out = [];
  let [y, m] = keys[0].split('-').map(Number);
  const [y2, m2] = keys[keys.length - 1].split('-').map(Number);

  while ((y < y2 || (y === y2 && m <= m2)) && out.length < 60) {
    out.push(`${y}-${pad(m)}`);
    m += 1;

    if (m > 12) {
      m = 1;
      y += 1;
    }
  }

  return out;
};

export const monthLabels = (list, t) => {
  const years = new Set(list.map((k) => k.slice(0, 4)));
  const names = t('months');

  return list.map((k) => (years.size > 1 ? `${k.slice(2, 4)}/${+k.slice(5)}` : names[+k.slice(5) - 1]));
};

export const today = () => {
  const d = new Date();

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export const ymd = (s) => (s ? String(s).replace(/-/g, '/') : '');

export const fmt = (field, value, language) => Number(value).toLocaleString(language, { maximumFractionDigits: 2 });

// 12,345,678 as 1,234.6 萬 (in Chinese) or 12.3M
export const short = (value, language, t) => {
  const n = Math.abs(value);

  if (String(language).startsWith('zh')) {
    if (n >= 1e8) return `${(value / 1e8).toFixed(1)} ${t('hundredMillion')}`;
    if (n >= 1e4) return `${(value / 1e4).toFixed(n >= 1e5 ? 0 : 1)} ${t('tenThousand')}`;

    return Number(value).toLocaleString(language, { maximumFractionDigits: 1 });
  }

  return Intl.NumberFormat(language, { notation: 'compact', maximumFractionDigits: 1 }).format(value);
};

// Where a new widget fits first: the first free place, top to bottom
export const hit = (a, b) => a !== b && a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;

export const firstFree = (widgets, w, h) => {
  for (let y = 0; y < 400; y += 1) {
    for (let x = 0; x <= COLS - w; x += 1) {
      const probe = { x, y, w, h };

      if (!widgets.some((o) => hit(probe, o))) return { x, y };
    }
  }

  return { x: 0, y: widgets.reduce((m, o) => Math.max(m, o.y + o.h), 0) };
};

// Everything up as far as it goes, in order; the one being moved stays where it is put
export const settle = (list, fixedId) => {
  const fixed = list.find((w) => w.id === fixedId);
  const placed = fixed ? [fixed] : [];

  list.filter((w) => w !== fixed).sort((a, b) => a.y - b.y || a.x - b.x).forEach((w) => {
    w.y = 0;

    for (let c = placed.find((p) => hit(w, p)); c; c = placed.find((p) => hit(w, p))) {
      w.y = c.y + c.h;
    }

    placed.push(w);
  });

  return list;
};

export const bottom = (list) => list.reduce((m, w) => Math.max(m, w.y + w.h), 0);

// ---------- A number widget's look (0.6.5): its card's colour, and its text white or black, whichever stands out
// more against it (WCAG contrast); its figure's size ----------
export const LOOK_COLORS = ['#1e6ff0', '#7c3aed', '#0f9d58', '#f59e0b', '#e11d48', '#0f172a', '#eaf3ff', '#fff1e0'];
export const LOOK_SIZES = ['s', 'm', 'l', 'xl'];

const luminance = (hex) => {
  const n = parseInt(hex.slice(1), 16);
  const lin = (c) => { const v = c / 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };

  return 0.2126 * lin(n >> 16) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
};

export const inkOn = (bg) => {
  if (!/^#[0-9a-f]{6}$/i.test(bg || '')) return null;

  const dark = '#141824';
  const l = luminance(bg);
  const white = 1.05 / (l + 0.05);
  const black = (l + 0.05) / (luminance(dark) + 0.05);

  return white >= black ? { fg: '#ffffff', muted: 'rgba(255, 255, 255, .78)' } : { fg: dark, muted: 'rgba(20, 24, 36, .66)' };
};

// The card's style for its colour; nothing when it has none (or is not a number)
export const lookStyle = (widget) => {
  const bg = widget.kind === 'number' && widget.look ? widget.look.bg : '';
  const ink = inkOn(bg);

  return ink ? { background: bg, color: ink.fg, borderColor: 'transparent', '--adb-fg': ink.fg, '--adb-muted': ink.muted } : undefined;
};

