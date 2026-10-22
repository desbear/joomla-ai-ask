// A template's records as a table, after the Vittal data-table blocks: a bar of search and filters
// (on the full page), a column of checkboxes, the first columns frozen, columns resized from their edge,
// groups by an option field; in the editable kind, a cell changed in place (text typed in it, an option
// or a date picked from a popover). Rows come a page at a time as the end comes near, and only the rows
// in view are drawn (TanStack Virtual).
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getCoreRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { useVirtualizer } from '@tanstack/react-virtual';
import { Icon } from '../icons.jsx';
import { fmt, tone, today, ymd } from '../kinds.js';
import { SignIn } from '../api.js';

const WIDTH = { text: 190, number: 112, date: 118, select: 120, multi: 170 };
const SEL = 40;
const ACT = 8;
const PAGE = 100;

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

  useEffect(() => {
    const r = anchor.getBoundingClientRect();
    const h = box.current ? box.current.offsetHeight : 240;
    const w = box.current ? box.current.offsetWidth : 240;
    const below = r.bottom + 4 + h < window.innerHeight;

    setPlace({ top: below ? r.bottom + 4 : Math.max(8, r.top - 4 - h), left: Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) });
    (box.current.querySelector('[aria-selected="true"], .on, button') || box.current).focus();

    const away = (e) => { if (box.current && !box.current.contains(e.target) && !anchor.contains(e.target)) onClose(); };
    const key = (e) => { if (e.key === 'Escape') { e.stopPropagation(); onClose(); } };

    document.addEventListener('pointerdown', away, true);
    document.addEventListener('keydown', key, true);

    return () => {
      document.removeEventListener('pointerdown', away, true);
      document.removeEventListener('keydown', key, true);
    };
  }, [anchor, onClose]);

  return <div ref={box} className="adb-pop adb-edpop" role="dialog" aria-label={label} tabIndex={-1} style={place}>{children}</div>;
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
export const Grid = ({ widget, fields, allFields, api, ctx, full, query, mine, onWidget, onOpenRow, openId, onAll }) => {
  const { t, lang, toast } = ctx;
  const edit = widget.kind === 'editgrid';
  const [rows, setRows] = useState([]);
  const [count, setCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(null);
  const [search, setSearch] = useState('');
  const [group, setGroup] = useState(() => (full ? (fields.find((f) => f.kind === 'select') || {}).key || '' : ''));
  const [shut, setShut] = useState(() => new Set());
  const [sorting, setSorting] = useState([]);
  const [picked, setPicked] = useState(() => new Set());
  const [editing, setEditing] = useState(null);
  const [menu, setMenu] = useState(null);
  const scroller = useRef(null);
  const typing = useRef(0);
  const pages = useRef(0);
  const asked = useRef(0);
  const words = [query, search].filter(Boolean).join(' ');

  // From the first page again whenever what is asked for changes
  const load = useCallback(async (page) => {
    const mark = asked.current;

    setLoading(true);
    setFailed(null);

    try {
      const got = await api.rows({ source: widget.source, template: widget.template, page, size: PAGE, query: words, mine });

      if (mark !== asked.current) return;

      pages.current = page;
      setRows((now) => (page === 1 ? got.rows : [...now, ...got.rows]));
      setCount(got.total);
    } catch (error) {
      if (mark === asked.current) setFailed(error);
    } finally {
      if (mark === asked.current) setLoading(false);
    }
  }, [api, widget.source, widget.template, words, mine]);

  useEffect(() => {
    asked.current += 1;
    pages.current = 0;
    setRows([]);
    load(1);
  }, [load]);

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

  const commit = async (row, field, value) => {
    setEditing(null);

    const before = row.values[field.key];

    setRows((now) => now.map((r) => (r.id === row.id ? { ...r, values: { ...r.values, [field.key]: value } } : r)));

    try {
      const got = await api.update({ source: widget.source, template: widget.template, row: row.id, field: field.key, value });

      if (got.row) setRows((now) => now.map((r) => (r.id === row.id ? got.row : r)));
      api.forget(widget.source, widget.template);
      ctx.onChanged && ctx.onChanged(widget.source, widget.template);
    } catch (error) {
      setRows((now) => now.map((r) => (r.id === row.id ? { ...r, values: { ...r.values, [field.key]: before } } : r)));
      toast(t('saveFailed', error.message));
    }
  };

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

    const th = e.currentTarget.closest('th');
    const put = () => { const r1 = th.getBoundingClientRect(); const r0 = scroller.current.getBoundingClientRect(); Object.assign(guide.style, { left: `${r1.right - r0.left + scroller.current.scrollLeft - 1}px`, top: `${scroller.current.scrollTop}px`, height: `${scroller.current.clientHeight}px` }); };
    const move = (ev) => { now = Math.min(480, Math.max(64, base + ev.clientX - start)); col.style.width = `${now}px`; tbl.style.width = `${tableWidth + now - base}px`; put(); };
    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); document.body.classList.remove('adb-resizing'); guide.remove(); onWidget({ ...widget, cols: { ...(widget.cols || {}), [f.key]: now } }); };

    put();
    document.body.classList.add('adb-resizing');
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
          <th key={f.key} className={`${pinClass(i)}${f.kind === 'number' ? ' n' : ''}${full ? ' sortable' : ''}`} style={pinStyle(i)}
            onClick={full ? () => setSorting(s ? (s.desc ? [] : [{ id: f.key, desc: true }]) : [{ id: f.key, desc: false }]) : undefined}>
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

  const rowOf = (row) => (
    <tr key={row.id} className={`adb-dt__row${picked.has(row.id) ? ' is-sel' : ''}${openId === row.id ? ' is-open' : ''}`} style={{ height: rowH }}
      onClick={!edit ? () => onOpenRow(row, sorted) : undefined}>
      {full ? <td className={`pin sel${freeze ? '' : ' last'}`} style={{ position: 'sticky', left: 0 }} onClick={(e) => e.stopPropagation()}><input type="checkbox" className="adb-cb" checked={picked.has(row.id)} onChange={(e) => setPicked((now) => { const next = new Set(now); e.target.checked ? next.add(row.id) : next.delete(row.id); return next; })} aria-label={row.no} /></td> : null}
      {fields.map((f, i) => (
        <td key={f.key} className={`${pinClass(i)}${f.kind === 'number' ? ' n' : ''}${edit ? ' e' : ''}${i === 0 ? ' first' : ''}`} style={pinStyle(i)}>
          {edit
            ? (f.kind === 'text' || f.kind === 'number'
              ? <TextCell field={f} value={row.values[f.key]} onCommit={(v) => commit(row, f, v)} lang={lang} t={t} />
              : <button type="button" className="adb-ed adb-ed--pop" data-open={editing && editing.row.id === row.id && editing.field.key === f.key ? '' : undefined} aria-haspopup="listbox" aria-label={f.label} onClick={(e) => setEditing({ row, field: f, anchor: e.currentTarget })}>{shown(f, row.values[f.key], lang, t, true)}</button>)
            : <span className="adb-cv">{shown(f, row.values[f.key], lang, t, false)}</span>}
          {i === 0 ? <button type="button" className="adb-dt__open" onClick={(e) => { e.stopPropagation(); onOpenRow(row, sorted); }}>{t('openRow')}</button> : null}
        </td>
      ))}
      <td className="act" />
      <td className="fill" />
    </tr>
  );

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
        <table className="adb-dt__table" style={{ width: tableWidth }}>
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
        {loading && !rows.length ? <div className="adb-empty adb-empty--over">{t('loading')}</div> : null}
        {failed && !(failed instanceof SignIn) ? <div className="adb-empty adb-empty--over">{failed.message || t('error')}</div> : null}
      </div>
      {full ? (
        <div className="adb-dt__foot">
          <span>{t('loaded', rows.length.toLocaleString(lang), count.toLocaleString(lang))}{picked.size ? ` · ${picked.size}` : ''}</span>
          <span className="adb-muted">{t('drawn', drawn.filter((v) => items[v.index] && items[v.index].type === 'r').length)}{edit ? ` · ${t('editHint')}` : ''} · {t('openHint')}</span>
        </div>
      ) : null}
      {editing ? <Editor field={editing.field} value={editing.row.values[editing.field.key]} anchor={editing.anchor} onCommit={(v) => commit(editing.row, editing.field, v)} onClose={() => setEditing(null)} t={t} /> : null}
      {menu ? (
        <Popover anchor={menu.anchor} onClose={() => setMenu(null)} label={t(menu.kind === 'cols' ? 'columns' : menu.kind)}>
          <div className="adb-edlist" role="menu">
            {menu.kind === 'group' ? [<button type="button" role="menuitemradio" aria-checked={!group} key="" onClick={() => { setGroup(''); setMenu(null); }}><span className="adb-tick">{!group ? <Icon name="check" /> : null}</span>{t('groupNone')}</button>,
              ...allFields.filter((f) => f.kind === 'select').map((f) => <button type="button" role="menuitemradio" aria-checked={group === f.key} key={f.key} onClick={() => { setGroup(f.key); setShut(new Set()); setMenu(null); }}><span className="adb-tick">{group === f.key ? <Icon name="check" /> : null}</span>{t('groupOf', f.label)}</button>)] : null}
            {menu.kind === 'freeze' ? [0, 1, 2, 3].filter((n) => n <= fields.length).map((n) => <button type="button" role="menuitemradio" aria-checked={freeze === n} key={n} onClick={() => { onWidget({ ...widget, freeze: n }); setMenu(null); }}><span className="adb-tick">{freeze === n ? <Icon name="check" /> : null}</span>{n ? t('freezeN', n, fields.slice(0, n).map((f) => f.label).join('、')) : t('freezeNone')}</button>) : null}
            {menu.kind === 'cols' ? allFields.map((f) => <label className="adb-checkrow" key={f.key}><input type="checkbox" checked={widget.fields.includes(f.key)} onChange={(e) => { const keys = e.target.checked ? [...widget.fields, f.key] : widget.fields.filter((k) => k !== f.key); if (keys.length) onWidget({ ...widget, fields: allFields.filter((x) => keys.includes(x.key)).map((x) => x.key) }); }} />{f.label}</label>) : null}
          </div>
        </Popover>
      ) : null}
    </div>
  );
};
