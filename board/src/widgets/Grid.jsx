// A template's records as a table, after the Vittal data-table blocks: a bar of search and filters
// (on the full page), a column of checkboxes, the first columns frozen, columns resized from their edge,
// columns moved by dragging their heading, groups by an option field; in the editable kind, a cell changed in place (text typed in it, an option
// or a date picked from a popover). Rows come a page at a time as the end comes near, and only the rows
// in view are drawn (TanStack Virtual).
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getCoreRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { useVirtualizer } from '@tanstack/react-virtual';
import { SkeletonCover } from './Skeleton.jsx';
import { useActive } from './active.js';
import { Icon } from '../icons.jsx';
import { fmt, tone, today, ymd, moved, slotAt } from '../kinds.js';
import { SignIn, SINCE_PAGE, SINCE_PAGES } from '../api.js';

const WIDTH = { text: 190, number: 112, date: 118, select: 120, multi: 170 };
const SEL = 40;
const ACT = 8;
const PAGE = 100;

// Others' changes (0.6.2): a table seen looks for them this often (longer after a failure, up to five minutes),
// and not when no one has touched the page for ten minutes
const CHECK_EVERY = 30000;
const IDLE_AFTER = 10 * 60 * 1000;
let lastTouched = Date.now();

if (typeof document !== 'undefined') {
  ['pointerdown', 'keydown', 'wheel', 'pointermove'].forEach((type) => document.addEventListener(type, () => { lastTouched = Date.now(); }, { passive: true, capture: true }));
}

const shown = (field, value, lang, t, edit) => {
  if (value === '' || value === undefined || value === null || (Array.isArray(value) && !value.length)) {
    if (edit && field.kind === 'date') return <span className="adb-muted">{t('pickDate')}</span>;
    if (edit && (field.kind === 'text' || field.kind === 'number')) return <span className="adb-muted">{t('typeIn', field.label)}</span>;

    return <span className="adb-muted">—</span>;
  }

  if (field.kind === 'select') return <span className={`adb-tag ${tone(value)}`}>{value}</span>;
  if (field.kind === 'multi') return <span className="adb-tags">{value.map((v) => <span key={v} className="adb-tag">{v}</span>)}</span>;
  if (field.kind === 'date') return <span className={value < today() ? 'adb-late' : ''}>{ymd(value)}</span>;
  if (field.kind === 'number') return fmt(field, value, lang);

  return String(value);
};

// ---------- Editors: a popover under the cell, one at a time ----------
const Popover = ({ anchor, onClose, children, label }) => {
  const box = useRef(null);
  const [place, setPlace] = useState({ top: -9999, left: -9999 });
  // Placed (under its cell, or above when there is no room below): then it comes in, from that side
  const [side, setSide] = useState('');

  useEffect(() => {
    const r = anchor.getBoundingClientRect();
    const h = box.current ? box.current.offsetHeight : 240;
    const w = box.current ? box.current.offsetWidth : 240;
    const below = r.bottom + 4 + h < window.innerHeight;

    setPlace({ top: below ? r.bottom + 4 : Math.max(8, r.top - 4 - h), left: Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) });
    setSide(below ? 'is-down' : 'is-up');

    const away = (e) => { if (box.current && !box.current.contains(e.target) && !anchor.contains(e.target)) onClose(); };
    const key = (e) => { if (e.key === 'Escape') { e.stopPropagation(); onClose(); } };

    document.addEventListener('pointerdown', away, true);
    document.addEventListener('keydown', key, true);

    return () => {
      document.removeEventListener('pointerdown', away, true);
      document.removeEventListener('keydown', key, true);
    };
  }, [anchor, onClose]);

  // Seen once placed: then what is chosen in it (or its first) has the focus
  useEffect(() => {
    if (side && box.current) (box.current.querySelector('[aria-selected="true"], .on, button') || box.current).focus();
  }, [side]);

  return <div ref={box} className={`adb-pop adb-edpop ${side || 'is-waiting'}`} role="dialog" aria-label={label} tabIndex={-1} style={place}>{children}</div>;
};

const Calendar = ({ value, onPick, t }) => {
  const start = value || today();
  const [ym, setYm] = useState([+start.slice(0, 4), +start.slice(5, 7) - 1]);
  const [y, m] = ym;
  const first = new Date(y, m, 1).getDay();
  const days = new Date(y, m + 1, 0).getDate();
  const day = (d) => `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  const step = (n) => setYm(([yy, mm]) => { const k = mm + n; return [yy + Math.floor(k / 12), ((k % 12) + 12) % 12]; });

  return (
    <div className="adb-cal">
      <div className="adb-cal__h"><button type="button" className="adb-iconbtn" onClick={() => step(-1)} aria-label="‹">‹</button><b>{t('yearMonth', y, m + 1)}</b><button type="button" className="adb-iconbtn" onClick={() => step(1)} aria-label="›">›</button></div>
      <div className="adb-cal__w">{[...t('weekdays')].map((w, i) => <span key={i}>{w}</span>)}</div>
      <div className="adb-cal__d">
        {Array.from({ length: first }, (_, i) => <span key={`b${i}`} />)}
        {Array.from({ length: days }, (_, i) => { const d = day(i + 1); return <button type="button" key={d} className={`${d === value ? 'on' : ''}${d === today() ? ' today' : ''}`} onClick={() => onPick(d)}>{i + 1}</button>; })}
      </div>
      <div className="adb-cal__f"><button type="button" onClick={() => onPick(today())}>{t('today')}</button><button type="button" onClick={() => onPick('')}>{t('clear')}</button></div>
    </div>
  );
};

const Editor = ({ field, value, anchor, onCommit, onClose, t }) => {
  const [many, setMany] = useState(Array.isArray(value) ? value : []);

  if (field.kind === 'date') {
    return <Popover anchor={anchor} onClose={onClose} label={field.label}><Calendar value={value} onPick={(d) => onCommit(d)} t={t} /></Popover>;
  }

  if (field.kind === 'multi') {
    const toggle = (o) => setMany((now) => (now.includes(o) ? now.filter((x) => x !== o) : [...now, o]));

    return (
      <Popover anchor={anchor} onClose={() => { if (JSON.stringify(many) !== JSON.stringify(value || [])) onCommit(many); else onClose(); }} label={field.label}>
        <div className="adb-edlist" role="listbox" aria-multiselectable="true">
          {field.options.map((o) => (
            <button type="button" role="option" aria-selected={many.includes(o)} key={o} onClick={() => toggle(o)}>
              <span className={`adb-check${many.includes(o) ? ' on' : ''}`}>{many.includes(o) ? <Icon name="check" /> : null}</span><span className="adb-tag">{o}</span>
            </button>
          ))}
        </div>
      </Popover>
    );
  }

  return (
    <Popover anchor={anchor} onClose={onClose} label={field.label}>
      <div className="adb-edlist" role="listbox">
        {field.options.map((o) => (
          <button type="button" role="option" aria-selected={o === value} key={o} onClick={() => onCommit(o)}>
            <span className={`adb-tag ${tone(o)}`}>{o}</span>{o === value ? <span className="adb-tick"><Icon name="check" /></span> : null}
          </button>
        ))}
      </div>
    </Popover>
  );
};

// A value typed for the records picked: Enter or the button sets it
const BatchText = ({ field, onCommit, t }) => {
  const input = useRef(null);
  const go = () => {
    const raw = input.current.value.trim();

    onCommit(field.kind === 'number' ? (raw === '' ? '' : Number(raw)) : raw);
  };

  return (
    <div className="adb-batchtext">
      <p className="adb-edlist__head">{t('batchSetTo', field.label)}</p>
      <input ref={input} type={field.kind === 'number' ? 'number' : 'text'} aria-label={field.label} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); go(); } }} />
      <button type="button" className="adb-btn adb-btn--main" onClick={go}>{t('batchApply')}</button>
    </div>
  );
};

const TextCell = ({ field, value, onCommit, lang, t }) => {
  const [typing, setTyping] = useState(false);
  const input = useRef(null);
  const done = useRef(false);

  useEffect(() => { if (typing && input.current) { input.current.focus(); input.current.select(); done.current = false; } }, [typing]);

  const end = (keep) => {
    if (done.current) return;
    done.current = true;
    setTyping(false);

    const raw = input.current.value.trim();
    const next = field.kind === 'number' ? (raw === '' ? '' : Number(raw)) : raw;

    if (keep && next !== value && !(field.kind === 'number' && Number.isNaN(next))) onCommit(next);
  };

  if (typing) {
    return (
      <div className="adb-ed is-editing">
        <input ref={input} className="adb-ed__input" type={field.kind === 'number' ? 'number' : 'text'} defaultValue={value ?? ''} aria-label={field.label}
          onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); end(true); } if (e.key === 'Escape') { e.stopPropagation(); end(false); } }}
          onBlur={() => end(true)} />
      </div>
    );
  }

  return (
    <div className="adb-ed" role="button" tabIndex={0} aria-label={field.label} onClick={() => setTyping(true)} onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setTyping(true); } }}>
      {shown(field, value, lang, t, true)}
    </div>
  );
};

// ---------- The table ----------
export const Grid = ({ widget, fields, allFields, actions = [], active = true, api, ctx, full, query, mine, range, onWidget, onOpenRow, openId, onAll }) => {
  // A chat of the desk's own (its cases) opens in the console; a record of a form, in the side card
  const openRow = (row, sorted) => (row.room && ctx.openRoom ? ctx.openRoom(row.room) : onOpenRow(row, sorted));
  const { t, lang, toast } = ctx;
  const edit = widget.kind === 'editgrid';
  // The first page as last seen, drawn at once while it is asked for afresh (no skeleton between card and opened)
  const lastPage = (said) => api.rowsNow({ source: widget.source, template: widget.template, size: PAGE, query: said, mine, range });
  const [rows, setRows] = useState(() => (lastPage(query) || {}).rows || []);
  const [count, setCount] = useState(() => (lastPage(query) || {}).total || 0);
  // Loading from the start, so nothing (not "none") shows before the first page is asked for
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(null);
  const [search, setSearch] = useState('');
  const [group, setGroup] = useState(() => (full ? (fields.find((f) => f.kind === 'select') || {}).key || '' : ''));
  const [shut, setShut] = useState(() => new Set());
  const [sorting, setSorting] = useState([]);
  const [picked, setPicked] = useState(() => new Set());
  // Each record as it is being changed (saving, saved, failed: a moment each), and those learned not to be
  // this person's to change; a change to the records picked, its steps (0.6.1)
  const [status, setStatus] = useState({});
  const [locks, setLocks] = useState({});
  const [batch, setBatch] = useState(null);
  const rowsNow = useRef([]);
  const [editing, setEditing] = useState(null);
  const [menu, setMenu] = useState(null);
  const scroller = useRef(null);
  const typing = useRef(0);
  const pages = useRef(0);
  const asked = useRef(0);
  const words = [query, search].filter(Boolean).join(' ');

  const checkAt = useRef('');

  // From the first page again whenever what is asked for changes
  const load = useCallback(async (page) => {
    const mark = asked.current;

    setLoading(true);
    setFailed(null);

    try {
      const got = await api.rows({ source: widget.source, template: widget.template, page, size: PAGE, query: words, mine, range });

      if (mark !== asked.current) return;

      pages.current = page;

      // When the source was asked (its clock): others' changes are looked for from then on
      if (page === 1) checkAt.current = got.now || '';

      setRows((now) => (page === 1 ? got.rows : [...now, ...got.rows]));
      setCount(got.total);
    } catch (error) {
      if (mark === asked.current) setFailed(error);
    } finally {
      if (mark === asked.current) setLoading(false);
    }
  }, [api, widget.source, widget.template, words, mine, range]);

  useEffect(() => {
    asked.current += 1;
    pages.current = 0;
    setRows((lastPage(words) || {}).rows || []);
    load(1);
  }, [load]);

  // ---------- Others' changes, as they come (0.6.2) ----------
  // Records others changed: read again (as this person) and drawn as they are now, the cells that changed flashing;
  // a new one (when nothing narrows the list by its dates) comes in at the top. Looked for every 30 seconds while
  // the table is seen (only those changed since: mostly nothing), and at once when the desk says someone changed
  // one from the dashboard. A cell being changed here keeps what is being written in it
  const [flash, setFlash] = useState({});
  const seen = useActive(scroller, active);
  const checking = useRef(false);
  const every = useRef(CHECK_EVERY);
  const soon = useRef(0);
  const editingNow = useRef(null);

  editingNow.current = editing;

  const merge = (got, addNew) => {
    const byId = new Map(rowsNow.current.map((r) => [r.id, r]));
    const marks = {};
    const added = [];
    const held = editingNow.current;
    let kept = false;

    got.forEach((fresh) => {
      const old = byId.get(fresh.id);

      if (!old) {
        if (addNew) {
          added.push(fresh);
          marks[fresh.id] = { fresh: true };
        }

        return;
      }

      const values = { ...fresh.values };
      const keys = [...new Set([...Object.keys(old.values || {}), ...Object.keys(values)])]
        .filter((key) => JSON.stringify(old.values[key] ?? null) !== JSON.stringify(values[key] ?? null));

      if (held && held.row.id === fresh.id && keys.includes(held.field.key)) {
        values[held.field.key] = old.values[held.field.key];
        keys.splice(keys.indexOf(held.field.key), 1);
        kept = true;
      }

      if (keys.length) {
        marks[fresh.id] = { keys: new Set(keys) };
        byId.set(fresh.id, { ...old, ...fresh, values });
      }
    });

    if (kept) ctx.toast && ctx.toast(t('changedWhileEditing'));
    if (!Object.keys(marks).length) return;

    setRows((now) => [...added, ...now.map((r) => (marks[r.id] ? byId.get(r.id) || r : r))]);

    if (added.length) setCount((n) => n + added.length);

    setFlash((now) => ({ ...now, ...marks }));
    window.setTimeout(() => setFlash((now) => { const next = { ...now }; Object.keys(marks).forEach((id) => delete next[id]); return next; }), 2200);
  };

  const check = useCallback(async () => {
    if (checking.current || !checkAt.current || document.hidden) return;

    checking.current = true;

    try {
      // A little before the last look: the source's clock and this desk's may differ
      const since = new Date(Date.parse(checkAt.current) - 90000).toISOString().replace(/\.\d{3}Z$/, 'Z');
      const ask = { source: widget.source, template: widget.template, query: words, mine, range, since };
      const got = await api.since(ask);
      const rows = [...(got.rows || [])];
      let page = 1;

      // Every page of what changed before looking from a later moment: none is skipped
      // (A source that can answers only what changed and says so; one that cannot, its first page as usual)
      const all = got.since ? got.total || 0 : rows.length;

      while (rows.length < all && page < SINCE_PAGES) {
        page += 1;

        const more = await api.since({ ...ask, page });

        rows.push(...(more.rows || []));

        if ((more.rows || []).length < SINCE_PAGE) break;
      }

      // More changed than is read this way: the table is read again from its first page
      if (rows.length < all) {
        load(1);
        return;
      }

      if (got.now) checkAt.current = got.now;

      merge(rows, Boolean(got.since) && range === 'all');
      every.current = CHECK_EVERY;
    } catch (error) {
      every.current = Math.min(5 * 60 * 1000, every.current * 2);
    } finally {
      checking.current = false;
    }
  }, [api, widget.source, widget.template, words, mine, range, load]);

  useEffect(() => {
    let timer = 0;
    let gone = false;

    const loop = () => {
      timer = window.setTimeout(async () => {
        if (seen && Date.now() - lastTouched < IDLE_AFTER) await check();
        if (!gone) loop();
      }, every.current);
    };

    loop();

    return () => { gone = true; window.clearTimeout(timer); };
  }, [check, seen]);

  // Changed from the dashboard by someone else: looked at at once; deleted, taken away
  useEffect(() => (ctx.changes ? ctx.changes.subscribe(widget.source, widget.template, (change) => {
    if (change.deleted) {
      if (rowsNow.current.some((r) => r.id === change.row)) {
        setRows((now) => now.filter((r) => r.id !== change.row));
        setCount((n) => Math.max(0, n - 1));
      }

      return;
    }

    window.clearTimeout(soon.current);
    soon.current = window.setTimeout(check, 400);
  }) : undefined), [ctx.changes, widget.source, widget.template, check]);

  const freeze = Math.min(widget.freeze ?? 1, fields.length);
  const width = (f) => (widget.cols && widget.cols[f.key]) || WIDTH[f.kind] || 140;
  const sel = full ? SEL : 0;
  const lefts = [];
  fields.reduce((at, f, i) => { lefts[i] = at; return at + width(f); }, sel);
  const tableWidth = sel + fields.reduce((s, f) => s + width(f), 0) + (full ? 44 : ACT);

  const columns = useMemo(() => fields.map((f) => ({
    id: f.key,
    accessorFn: (row) => { const v = row.values[f.key]; return Array.isArray(v) ? v.join(', ') : v ?? ''; },
    header: f.label,
    sortingFn: f.kind === 'number' ? 'basic' : 'alphanumeric',
  })), [fields]);

  const table = useReactTable({
    data: rows,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getRowId: (row) => row.id,
  });
  const sorted = table.getRowModel().rows.map((r) => r.original);
  const groupField = group ? allFields.find((f) => f.key === group) : null;

  // Everything as a list of fixed-height items: groups, their rows, and a row that loads the next page
  const items = useMemo(() => {
    const list = [];

    if (groupField) {
      const order = groupField.options.length ? groupField.options : [...new Set(sorted.map((r) => r.values[groupField.key]))];
      const known = new Set(order);
      const lots = [...order, ...[...new Set(sorted.map((r) => r.values[groupField.key]))].filter((v) => !known.has(v))];

      lots.forEach((o) => {
        const inIt = sorted.filter((r) => (r.values[groupField.key] ?? '') === o);

        if (!inIt.length) return;
        list.push({ type: 'g', key: `g:${o}`, title: o === '' ? '—' : o, value: o, n: inIt.length });
        if (!shut.has(o)) inIt.forEach((r) => list.push({ type: 'r', key: r.id, row: r }));
      });
    } else {
      sorted.forEach((r) => list.push({ type: 'r', key: r.id, row: r }));
    }

    if (rows.length < count) list.push({ type: 'more', key: 'more' });
    if (!list.length && !loading && !failed) list.push({ type: 'none', key: 'none' });

    return list;
  }, [sorted, groupField, shut, rows.length, count, loading, failed]);

  const rowH = edit ? 44 : 40;
  const virtual = useVirtualizer({
    count: items.length,
    getScrollElement: () => scroller.current,
    estimateSize: (i) => (items[i].type === 'g' ? 36 : rowH),
    overscan: 10,
  });
  const drawn = virtual.getVirtualItems();

  // The loading row in view: the next page (on the full page; in a widget, a link to it)
  useEffect(() => {
    const last = drawn[drawn.length - 1];

    if (full && last && items[last.index] && items[last.index].type === 'more' && !loading && !failed) {
      load(pages.current + 1);
    }
  }, [drawn, items, full, loading, failed, load]);

  // The group the top row is in, held under the headings
  const topGroup = useMemo(() => {
    if (!groupField || !drawn.length) return null;

    for (let i = drawn[0].index; i >= 0; i -= 1) {
      if (items[i] && items[i].type === 'g') {
        // Only once its own row has gone up under the headings
        const own = drawn.find((v) => v.index === i);

        return own && own.start >= (virtual.scrollOffset || 0) ? null : items[i];
      }
    }

    return null;
  }, [drawn, items, groupField, virtual.scrollOffset]);

  rowsNow.current = rows;

  const lockOf = (row) => locks[row.id] || row.locked || '';
  const mark = (id, state) => {
    setStatus((now) => ({ ...now, [id]: state }));

    // Saved or not: shown a moment, then as it was
    if (state !== 'saving') {
      window.setTimeout(() => setStatus((now) => (now[id] === state ? (({ [id]: _, ...rest }) => rest)(now) : now)), 1600);
    }
  };

  // One record's field changed: the row says it is being saved, then saved (or not, and why)
  const save = async (row, field, value) => {
    const before = row.values[field.key];

    mark(row.id, 'saving');
    setRows((now) => now.map((r) => (r.id === row.id ? { ...r, values: { ...r.values, [field.key]: value } } : r)));

    try {
      const got = await api.update({ source: widget.source, template: widget.template, row: row.id, field: field.key, value });

      if (got.row) setRows((now) => now.map((r) => (r.id === row.id ? got.row : r)));
      mark(row.id, 'saved');

      return true;
    } catch (error) {
      setRows((now) => now.map((r) => (r.id === row.id ? { ...r, values: { ...r.values, [field.key]: before } } : r)));
      mark(row.id, 'failed');

      if (error.locked) setLocks((now) => ({ ...now, [row.id]: error.locked }));

      return error;
    }
  };

  const commit = async (row, field, value) => {
    setEditing(null);

    if (lockOf(row)) return;

    // A row among several picked: the change is offered for all of them (in the bar)
    if (picked.has(row.id) && picked.size > 1) {
      stage(field, value);

      return;
    }

    const done = await save(row, field, value);

    if (done === true) {
      api.forget(widget.source, widget.template);
      ctx.onChanged && ctx.onChanged(widget.source, widget.template);
    } else {
      toast(t('saveFailed', done.detail || done.message));
    }
  };

  // ---------- The records picked, changed together ----------
  // Of those picked, the ones this person may change (the rest left out, said how many)
  const pickedRows = rows.filter((r) => picked.has(r.id));
  const changeable = pickedRows.filter((r) => !lockOf(r));
  const editable = allFields.filter((f) => ['select', 'multi', 'date', 'text', 'number'].includes(f.kind));

  // A field and its value chosen: changed at once when the widget says so; else said first, to confirm
  // Asked again (another cell changed while one change waits): the bar flashes again, to be seen
  const barEl = useRef(null);
  const flashAt = batch && batch.stage === 'confirm' ? batch.n : 0;

  useEffect(() => {
    const el = barEl.current;

    if (!flashAt || !el) return;
    el.style.animation = 'none';
    void el.offsetWidth;
    el.style.animation = '';
  }, [flashAt]);

  // The column under the pointer in a picked row: lit in every picked row, what a change there would reach
  const [hot, setHot] = useState(null);

  const stage = (field, value) => {
    setBatchMenu(null);

    const ids = changeable.map((r) => r.id);

    if (!ids.length) return;
    if (widget.batchDirect) runBatch({ field, value }, ids);
    else setBatch({ stage: 'confirm', field, value, ids, skipped: pickedRows.length - ids.length, n: Date.now() });
  };

  // Deleted, for good: always said first (in red), whatever the widget says of changes
  const stageDelete = () => {
    const ids = changeable.map((r) => r.id);

    if (ids.length) setBatch({ stage: 'confirm', remove: true, ids, skipped: pickedRows.length - ids.length, n: Date.now() });
  };

  // What the source offers to do to the records picked (0.6.2: the desk's cases closed), always said first
  const stageAct = (action) => {
    const ids = changeable.map((r) => r.id);

    if (ids.length) setBatch({ stage: 'confirm', act: action, ids, skipped: pickedRows.length - ids.length, n: Date.now() });
  };

  const doAct = async (row, action) => {
    mark(row.id, 'saving');

    try {
      const got = await api.act({ source: widget.source, template: widget.template, row: row.id, action: action.name });
      const values = got && got.row ? got.row.values || {} : {};

      setRows((now) => now.map((r) => (r.id === row.id ? { ...r, values: { ...r.values, ...values } } : r)));
      mark(row.id, 'saved');

      return true;
    } catch (error) {
      mark(row.id, 'failed');

      return error;
    }
  };

  const drop = async (row) => {
    mark(row.id, 'saving');

    try {
      await api.remove({ source: widget.source, template: widget.template, row: row.id });
      setRows((now) => now.filter((r) => r.id !== row.id));
      setCount((n) => Math.max(0, n - 1));
      setPicked((now) => { const next = new Set(now); next.delete(row.id); return next; });

      return true;
    } catch (error) {
      mark(row.id, 'failed');

      if (error.locked) setLocks((now) => ({ ...now, [row.id]: error.locked }));

      return error;
    }
  };

  // Two at a time; each row says how it went, the bar how far it is
  const runBatch = async (task, ids) => {
    let done = 0;
    let failed = 0;
    const queue = [...ids];

    setBatch({ stage: 'run', ...task, ids, done, failed });

    const one = async () => {
      while (queue.length) {
        const id = queue.shift();
        const row = rowsNow.current.find((r) => r.id === id);

        if (row && (await (task.remove ? drop(row) : (task.act ? doAct(row, task.act) : save(row, task.field, task.value)))) !== true) failed += 1;
        done += 1;
        setBatch((now) => (now ? { ...now, done, failed } : now));
      }
    };

    await Promise.all([one(), one()]);
    api.forget(widget.source, widget.template);
    ctx.onChanged && ctx.onChanged(widget.source, widget.template);
    setBatch((now) => (now ? { ...now, stage: 'done', done, failed } : now));
    window.setTimeout(() => setBatch((now) => (now && now.stage === 'done' ? null : now)), 2800);
  };

  // The records picked as a file (their fields as shown), for a spreadsheet
  const exportPicked = () => {
    const cell = (v) => `"${String(Array.isArray(v) ? v.join('、') : v ?? '').replace(/"/g, '""')}"`;
    const lines = [fields.map((f) => cell(f.label)).join(','), ...pickedRows.map((r) => fields.map((f) => cell(r.values[f.key])).join(','))];
    const link = document.createElement('a');

    link.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    link.download = `${widget.title || 'records'}.csv`;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
  };

  const [batchMenu, setBatchMenu] = useState(null);

  // Columns resized from the edge of their heading
  const resize = (e, f) => {
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();

    const start = e.clientX;
    const base = width(f);
    const col = scroller.current.querySelector(`col[data-key="${CSS.escape(f.key)}"]`);
    const tbl = scroller.current.querySelector('table');
    const guide = document.createElement('div');
    let now = base;

    guide.className = 'adb-guide';
    scroller.current.append(guide);

    const grip = e.currentTarget;
    const th = grip.closest('th');
    // The line grows from the heading down the whole table as the drag starts
    let tall = false;
    const put = () => { const r1 = th.getBoundingClientRect(); const r0 = scroller.current.getBoundingClientRect(); Object.assign(guide.style, { left: `${r1.right - r0.left - scroller.current.clientLeft + scroller.current.scrollLeft - 1}px`, top: `${scroller.current.scrollTop}px`, height: `${tall ? scroller.current.clientHeight : th.offsetHeight}px` }); };
    const move = (ev) => { now = Math.min(480, Math.max(64, base + ev.clientX - start)); col.style.width = `${now}px`; tbl.style.width = `${tableWidth + now - base}px`; put(); };
    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); document.body.classList.remove('adb-resizing'); grip.classList.remove('is-on'); guide.remove(); onWidget({ ...widget, cols: { ...(widget.cols || {}), [f.key]: now } }); };

    put();
    grip.classList.add('is-on');
    requestAnimationFrame(() => { tall = true; put(); });
    document.body.classList.add('adb-resizing');
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  // Columns moved by dragging their heading sideways (on the full page; a template's widget only by whoever
  // makes the templates): a click that does not move still sorts
  const movable = full && !(widget.tpl && !ctx.templater);
  const dragged = useRef(false);
  const shift = (e, from) => {
    if (e.button !== 0 || !movable) return;

    const start = e.clientX;
    const box = scroller.current;
    const heads = [...box.querySelectorAll('th[data-col]')];
    const guide = document.createElement('div');
    // The heading picked up: a tag with its name, floating, held where it was grabbed, carried sideways
    const tag = document.createElement('div');
    const hr = heads[from].getBoundingClientRect();
    const grab = Math.min(Math.max(start - hr.left, 12), 40);
    let to = null;

    guide.className = 'adb-guide';
    tag.className = 'adb-coltag';
    tag.textContent = fields[from].label;

    const move = (ev) => {
      if (to === null && Math.abs(ev.clientX - start) < 6) return;

      if (to === null) {
        box.append(guide);
        document.body.append(tag);
        tag.style.top = `${hr.top + hr.height / 2}px`;
        heads[from].classList.add('is-lifted');
        document.body.classList.add('adb-sorting');
      }

      tag.style.left = `${ev.clientX - grab}px`;

      // Near an edge: the table scrolls along
      const r0 = box.getBoundingClientRect();
      if (ev.clientX < r0.left + 40) box.scrollLeft -= 12;
      if (ev.clientX > r0.right - 40) box.scrollLeft += 12;

      to = slotAt(heads, ev.clientX, 'x');
      const edge = to < heads.length ? heads[to].getBoundingClientRect().left : heads[heads.length - 1].getBoundingClientRect().right;
      Object.assign(guide.style, { left: `${edge - r0.left + box.scrollLeft - 1}px`, top: `${box.scrollTop}px`, height: `${box.clientHeight}px` });
    };
    const up = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);

      if (to === null) return;

      dragged.current = true;
      guide.remove();
      tag.classList.add('is-dropped');
      setTimeout(() => tag.remove(), 180);
      heads[from].classList.remove('is-lifted');
      document.body.classList.remove('adb-sorting');

      const keys = moved(fields.map((f) => f.key), from, to);

      if (keys.join() !== fields.map((f) => f.key).join()) onWidget({ ...widget, fields: [...keys, ...widget.fields.filter((k) => !keys.includes(k))] });
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  const pinStyle = (i) => (i < freeze ? { position: 'sticky', left: lefts[i] } : undefined);
  const pinClass = (i) => (i < freeze ? ` pin${i === freeze - 1 ? ' last' : ''}` : '');
  const allPicked = rows.length > 0 && rows.every((r) => picked.has(r.id));
  const span = fields.length + (full ? 1 : 0) + 2;

  const head = (
    <tr className="adb-dt__head">
      {full ? <th className={`pin sel${freeze ? '' : ' last'}`} style={{ position: 'sticky', left: 0 }}><input type="checkbox" className="adb-cb" checked={allPicked} onChange={(e) => setPicked(e.target.checked ? new Set(rows.map((r) => r.id)) : new Set())} aria-label={t('all')} /></th> : null}
      {fields.map((f, i) => {
        const s = sorting.find((x) => x.id === f.key);

        return (
          <th key={f.key} data-col={f.key} className={`${pinClass(i)}${f.kind === 'number' ? ' n' : ''}${full ? ' sortable' : ''}${movable ? ' movable' : ''}`} style={pinStyle(i)}
            title={movable ? t('dragCols') : undefined} onPointerDown={movable ? (e) => shift(e, i) : undefined}
            onClick={full ? () => { if (dragged.current) { dragged.current = false; return; } setSorting(s ? (s.desc ? [] : [{ id: f.key, desc: true }]) : [{ id: f.key, desc: false }]); } : undefined}>
            <span className="adb-th">{i < freeze && full ? <span className="adb-pinned"><Icon name="pin" /></span> : null}{f.label}{s ? <b className="adb-arrow">{s.desc ? '↓' : '↑'}</b> : null}</span>
            {full ? <span className="adb-rs" role="separator" aria-orientation="vertical" aria-label={f.label} onPointerDown={(e) => resize(e, f)} onClick={(e) => e.stopPropagation()} onDoubleClick={(e) => { e.stopPropagation(); const cols = { ...(widget.cols || {}) }; delete cols[f.key]; onWidget({ ...widget, cols }); }} /> : null}
          </th>
        );
      })}
      <th className="act" />
      <th className="fill" />
    </tr>
  );

  const groupRow = (it, held) => (
    <tr className={`adb-dt__group${held ? ' held' : ''}`} key={held ? 'held' : it.key}>
      <td colSpan={span}>
        <button type="button" className="adb-dt__gbtn" aria-expanded={!shut.has(it.value)} onClick={() => setShut((now) => { const next = new Set(now); next.has(it.value) ? next.delete(it.value) : next.add(it.value); return next; })}>
          <Icon name="chev" className={`adb-chev${shut.has(it.value) ? ' shut' : ''}`} />{it.title}<small>{it.n}</small>
        </button>
      </td>
    </tr>
  );

  const rowOf = (row) => {
    const locked = lockOf(row);
    const live = edit && !locked;
    const sel = picked.has(row.id);

    return (
    <tr key={row.id} className={`adb-dt__row${flash[row.id] ? (flash[row.id].fresh ? ' is-new' : ' is-touched') : ''}${picked.has(row.id) ? ' is-sel' : ''}${openId === row.id ? ' is-open' : ''}${locked ? ' is-locked' : ''}${status[row.id] ? ` is-${status[row.id]}` : ''}`} style={{ height: rowH }}
      title={locked ? t(`locked_${locked}`) : undefined} aria-busy={status[row.id] === 'saving' || undefined}
      onClick={!live ? () => openRow(row, sorted) : undefined}>
      {full ? <td className={`pin sel${freeze ? '' : ' last'}`} style={{ position: 'sticky', left: 0 }} onClick={(e) => e.stopPropagation()}><input type="checkbox" className="adb-cb" checked={picked.has(row.id)} onChange={(e) => setPicked((now) => { const next = new Set(now); e.target.checked ? next.add(row.id) : next.delete(row.id); return next; })} aria-label={row.no} /></td> : null}
      {fields.map((f, i) => (
        <td key={f.key} className={`${pinClass(i)}${f.kind === 'number' ? ' n' : ''}${live ? ' e' : ''}${i === 0 ? ' first' : ''}${sel && hot === f.key && !locked ? ' is-hot' : ''}${flash[row.id] && flash[row.id].keys && flash[row.id].keys.has(f.key) ? ' is-changed' : ''}`} style={{ ...pinStyle(i), '--x': `${lefts[i]}px` }}
          onMouseEnter={sel ? () => setHot(f.key) : undefined} onMouseLeave={sel ? () => setHot(null) : undefined}>
          {i === 0 && locked ? <span className="adb-lock" aria-label={t(`locked_${locked}`)}><Icon name="lock" /></span> : null}
          {live
            ? (f.kind === 'text' || f.kind === 'number'
              ? <TextCell field={f} value={row.values[f.key]} onCommit={(v) => commit(row, f, v)} lang={lang} t={t} />
              : <button type="button" className="adb-ed adb-ed--pop" data-open={editing && editing.row.id === row.id && editing.field.key === f.key ? '' : undefined} aria-haspopup="listbox" aria-label={f.label} onClick={(e) => setEditing({ row, field: f, anchor: e.currentTarget })}>{shown(f, row.values[f.key], lang, t, true)}</button>)
            : <span className="adb-cv">{shown(f, row.values[f.key], lang, t, false)}</span>}
          {i === 0 ? <button type="button" className="adb-dt__open" onClick={(e) => { e.stopPropagation(); openRow(row, sorted); }}>{t('openRow')}</button> : null}
        </td>
      ))}
      <td className="act" style={{ '--x': `${tableWidth - (full ? 44 : ACT)}px` }} />
      <td className="fill" style={{ '--x': `${tableWidth}px` }} />
    </tr>
    );
  };

  const itemRow = (it) => {
    if (it.type === 'g') return groupRow(it, false);
    if (it.type === 'r') return rowOf(it.row);
    if (it.type === 'none') return <tr key="none" className="adb-dt__note"><td colSpan={span}>{t('none')}</td></tr>;

    return (
      <tr key="more" className="adb-dt__note" style={{ height: 44 }}>
        <td colSpan={span}>{full
          ? <><span className="adb-dots"><i /><i /><i /></span>{t('loadMore', (count - rows.length).toLocaleString(lang))}</>
          : <button type="button" className="adb-link" onClick={onAll}>{t('showAll', (count - rows.length).toLocaleString(lang))}</button>}</td>
      </tr>
    );
  };

  const before = drawn.length ? drawn[0].start : 0;
  const after = drawn.length ? virtual.getTotalSize() - drawn[drawn.length - 1].end : 0;
  const bar = full ? (
    <div className="adb-dt__bar">
      <label className="adb-dt__search"><Icon name="search" /><input type="search" placeholder={t('searchIn', fields[0] ? fields[0].label : '')} aria-label={t('search')} onChange={(e) => { const v = e.target.value.trim(); clearTimeout(typing.current); typing.current = setTimeout(() => setSearch(v), 300); }} /></label>
      <button type="button" className={`adb-pill${group ? ' on' : ''}`} onClick={(e) => setMenu({ kind: 'group', anchor: e.currentTarget })}>{groupField ? t('groupBy', groupField.label) : t('group')}<Icon name="chev" className="adb-chev" /></button>
      <span className="adb-sp" />
      <button type="button" className="adb-pill" onClick={(e) => setMenu({ kind: 'freeze', anchor: e.currentTarget })}><Icon name="pin" />{t('freezeShort', freeze)}<Icon name="chev" className="adb-chev" /></button>
      <button type="button" className="adb-pill" onClick={(e) => setMenu({ kind: 'cols', anchor: e.currentTarget })}>{t('columns')}<Icon name="chev" className="adb-chev" /></button>
    </div>
  ) : null;

  if (failed instanceof SignIn) {
    return <div className="adb-empty"><p>{failed.message}</p><a className="adb-main" href={failed.url}>{t('connect', ctx.sourceTitle(widget.source))}</a></div>;
  }

  return (
    <div className={`adb-dt${full ? ' adb-dt--full' : ''}${edit ? ' adb-dt--edit' : ''}`}>
      {bar}
      <div className="adb-dt__scroll" ref={scroller}>
        <table className="adb-dt__table" style={{ width: tableWidth, '--w': `${tableWidth}px` }}>
          <colgroup>
            {full ? <col style={{ width: SEL }} /> : null}
            {fields.map((f) => <col key={f.key} data-key={f.key} style={{ width: width(f) }} />)}
            <col style={{ width: full ? 44 : ACT }} />
            <col style={{ width: '100%' }} />
          </colgroup>
          <thead>
            {topGroup ? groupRow(topGroup, true) : null}
            {head}
          </thead>
          <tbody>
            {before > 0 ? <tr className="adb-dt__spacer" aria-hidden="true"><td colSpan={span} style={{ height: before }} /></tr> : null}
            {drawn.map((v) => itemRow(items[v.index]))}
            {after > 0 ? <tr className="adb-dt__spacer" aria-hidden="true"><td colSpan={span} style={{ height: after }} /></tr> : null}
          </tbody>
        </table>
        <SkeletonCover on={(loading && !rows.length) || (rows.length > 0 && !drawn.length)} kind="table" cols={fields.length} head={false} className="adb-empty--over adb-sk-over" />
        {failed && !(failed instanceof SignIn) ? <div className="adb-empty adb-empty--over">{failed.message || t('error')}</div> : null}
      </div>
      {full ? (
        <div className="adb-dt__foot">
          <span>{t('loaded', rows.length.toLocaleString(lang), count.toLocaleString(lang))}{picked.size ? ` · ${picked.size}` : ''}</span>
          <span className="adb-muted">{t('drawn', drawn.filter((v) => items[v.index] && items[v.index].type === 'r').length)}{edit ? ` · ${t('editHint')}` : ''} · {t('openHint')}</span>
        </div>
      ) : null}
      {full && (picked.size || batch) ? (
        <div ref={barEl} className={`adb-batch${batch ? ` is-${batch.stage}` : ''}${batch && batch.act ? ' is-calm' : ''}`} role="region" aria-label={t('batchPicked', picked.size)} aria-live="polite">
          {!batch ? (
            <>
              <b className="adb-batch__n">{picked.size}</b>
              <span className="adb-batch__what">{t('batchPicked', picked.size)}{pickedRows.length > changeable.length ? <small>{t('batchLocked', pickedRows.length - changeable.length)}</small> : null}</span>
              <span className="adb-batch__sep" />
              {edit && editable.length ? <button type="button" className="adb-batch__act" disabled={!changeable.length} onClick={(e) => setBatchMenu({ anchor: e.currentTarget })}><Icon name="edit" /><span>{t('batchEdit')}</span></button> : null}
              <button type="button" className="adb-batch__act" onClick={exportPicked}><Icon name="download" /><span>{t('batchExport')}</span></button>
              {actions.map((action) => <button key={action.name} type="button" className="adb-batch__act" disabled={!changeable.length} onClick={() => stageAct(action)}><Icon name="check" /><span>{action.label}</span></button>)}
              {ctx.removable && ctx.removable(widget.source) ? <button type="button" className="adb-batch__act adb-batch__act--danger" disabled={!changeable.length} onClick={stageDelete}><Icon name="trash" /><span>{t('batchDelete')}</span></button> : null}
              <span className="adb-batch__sep" />
              <button type="button" className="adb-batch__x" onClick={() => setPicked(new Set())} aria-label={t('batchClear')} title={t('batchClear')}><Icon name="close" /></button>
            </>
          ) : batch.stage === 'confirm' ? (
            <>
              <Icon name="alert" />
              <span className="adb-batch__what">{batch.act ? (batch.act.confirm || batch.act.label).replace('%s', batch.ids.length) : batch.remove ? t('batchDeleteConfirm', batch.ids.length) : t('batchConfirm', batch.ids.length, batch.field.label, Array.isArray(batch.value) ? batch.value.join('、') : String(batch.value === '' ? t('emptyValue') : batch.value))}{batch.skipped ? <small>{t('batchLocked', batch.skipped)}</small> : null}</span>
              <button type="button" className="adb-batch__go" onClick={() => runBatch(batch.act ? { act: batch.act } : batch.remove ? { remove: true } : { field: batch.field, value: batch.value }, batch.ids)}>{batch.act ? batch.act.label : batch.remove ? t('batchDeleteGo') : t('batchGo')}</button>
              <button type="button" className="adb-batch__act" onClick={() => setBatch(null)}>{t('batchUndo')}</button>
            </>
          ) : (
            <>
              {batch.stage === 'done' ? <svg className="adb-batch__tick" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="m7.5 12.5 3 3 6-6.5" /></svg> : <span className="adb-dots"><i /><i /><i /></span>}
              <span className="adb-batch__what">{batch.stage === 'done' ? t(batch.remove ? 'batchDeleted' : 'batchDone', batch.done - batch.failed) : t(batch.remove ? 'batchDeleting' : 'batchRunning', batch.done, batch.ids.length)}{batch.failed ? <small>{t('batchFailed', batch.failed)}</small> : null}</span>
              <span className="adb-batch__progress" style={{ '--p': `${Math.round((batch.done / Math.max(1, batch.ids.length)) * 100)}%` }} />
            </>
          )}
        </div>
      ) : null}
      {batchMenu && !batchMenu.field ? (
        <Popover anchor={batchMenu.anchor} onClose={() => setBatchMenu(null)} label={t('batchEdit')}>
          <div className="adb-edlist" role="menu">
            <p className="adb-edlist__head">{t('batchWhich')}</p>
            {editable.map((f) => <button type="button" role="menuitem" key={f.key} onClick={() => setBatchMenu({ anchor: batchMenu.anchor, field: f })}>{f.label}</button>)}
          </div>
        </Popover>
      ) : null}
      {batchMenu && batchMenu.field ? (batchMenu.field.kind === 'text' || batchMenu.field.kind === 'number'
        ? <Popover anchor={batchMenu.anchor} onClose={() => setBatchMenu(null)} label={batchMenu.field.label}><BatchText field={batchMenu.field} onCommit={(v) => stage(batchMenu.field, v)} t={t} /></Popover>
        : <Editor field={batchMenu.field} value={batchMenu.field.kind === 'multi' ? [] : undefined} anchor={batchMenu.anchor} onCommit={(v) => stage(batchMenu.field, v)} onClose={() => setBatchMenu(null)} t={t} />) : null}
      {editing ? <Editor field={editing.field} value={editing.row.values[editing.field.key]} anchor={editing.anchor} onCommit={(v) => commit(editing.row, editing.field, v)} onClose={() => setEditing(null)} t={t} /> : null}
      {menu ? (
        <Popover anchor={menu.anchor} onClose={() => setMenu(null)} label={t(menu.kind === 'cols' ? 'columns' : menu.kind)}>
          <div className="adb-edlist" role="menu">
            {menu.kind === 'group' ? [<button type="button" role="menuitemradio" aria-checked={!group} key="" onClick={() => { setGroup(''); setMenu(null); }}><span className="adb-tick">{!group ? <Icon name="check" /> : null}</span>{t('groupNone')}</button>,
              ...allFields.filter((f) => f.kind === 'select').map((f) => <button type="button" role="menuitemradio" aria-checked={group === f.key} key={f.key} onClick={() => { setGroup(f.key); setShut(new Set()); setMenu(null); }}><span className="adb-tick">{group === f.key ? <Icon name="check" /> : null}</span>{t('groupOf', f.label)}</button>)] : null}
            {menu.kind === 'freeze' ? [0, 1, 2, 3].filter((n) => n <= fields.length).map((n) => <button type="button" role="menuitemradio" aria-checked={freeze === n} key={n} onClick={() => { onWidget({ ...widget, freeze: n }); setMenu(null); }}><span className="adb-tick">{freeze === n ? <Icon name="check" /> : null}</span>{n ? t('freezeN', n, fields.slice(0, n).map((f) => f.label).join('、')) : t('freezeNone')}</button>) : null}
            {menu.kind === 'cols' ? allFields.map((f) => <label className="adb-checkrow" key={f.key}><input type="checkbox" checked={widget.fields.includes(f.key)} onChange={(e) => { const keys = e.target.checked ? [...widget.fields, f.key] : widget.fields.filter((k) => k !== f.key); if (keys.length) onWidget({ ...widget, fields: keys }); }} />{f.label}</label>) : null}
            {menu.kind === 'cols' && Array.isArray(widget.baseFields) && widget.fields.join() !== widget.baseFields.join() ? (
              // The columns put in another order (kept, 0.6.2): back to the template's
              <button type="button" className="adb-edlist__reset" onClick={() => { onWidget({ ...widget, fields: [...widget.baseFields] }); setMenu(null); }}><Icon name="close" />{t('colsReset')}</button>
            ) : null}
          </div>
        </Popover>
      ) : null}
    </div>
  );
};
