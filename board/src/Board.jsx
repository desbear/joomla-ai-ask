// One person's dashboard: widgets on 12 columns, each one template of a source drawn as a table or a
// chart. Laid out in place (edit), all at once (overview); a widget grows into a full page with its
// settings; a record opens in a side card.
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Icon, Grip } from './icons.jsx';
import { COLS, KINDS, isGrid, needs, recommend, settle, firstFree, bottom, fmt, ymd } from './kinds.js';
import { SignIn } from './api.js';
import { Grid } from './widgets/Grid.jsx';
import { NumberW, BatteryW, ChartW, GanttW } from './widgets/Simple.jsx';

const ROW = 64;
const GAP = 14;
const KIND_ORDER = ['number', 'battery', 'pie', 'doughnut', 'bar', 'hbar', 'stacked', 'line', 'area', 'table', 'editgrid', 'gantt'];
const ENTRIES = [['table', 'entryTable'], ['editgrid', 'entryEditgrid'], ['chart', 'entryChart', 'pie'], ['line', 'entryLine'], ['number', 'entryNumber'], ['battery', 'entryBattery'], ['gantt', 'entryGantt']];
let seq = 0;
const newId = () => `w${Date.now().toString(36)}${(seq += 1)}`;

// ---------- A widget's fields and records ----------
const useWidget = (widget, api, query, mine) => {
  const [all, setAll] = useState(null);
  const [data, setData] = useState(null);
  const [failed, setFailed] = useState(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let live = true;

    setFailed(null);
    api.fields(widget.source, widget.template).then((got) => { if (live) setAll(got); }, (error) => { if (live) setFailed(error); });

    return () => { live = false; };
  }, [api, widget.source, widget.template]);

  useEffect(() => {
    if (isGrid(widget.kind)) return undefined;

    let live = true;

    setData(null);
    api.all({ source: widget.source, template: widget.template, query, mine }).then((got) => { if (live) setData(got); }, (error) => { if (live) setFailed(error); });

    return () => { live = false; };
  }, [api, widget.source, widget.template, widget.kind, query, mine, tick]);

  const fields = useMemo(() => (all ? all.fields.filter((f) => widget.fields.includes(f.key)) : []), [all, widget.fields]);

  return { template: all, fields, data, failed, again: () => setTick((n) => n + 1) };
};

// What a widget draws, by its kind
const Body = ({ widget, state, api, ctx, query, mine, full, onWidget, onOpenRow, openId, onAll }) => {
  const { t } = ctx;
  const { template, fields, data, failed } = state;

  if (failed instanceof SignIn) {
    return <div className="adb-empty"><p>{t('signin', ctx.sourceTitle(widget.source))}</p><a className="adb-main" href={failed.url}>{t('connect', ctx.sourceTitle(widget.source))}</a></div>;
  }

  if (failed) return <div className="adb-empty">{failed.message || t('error')}</div>;
  if (!template) return <div className="adb-empty"><span className="adb-dots"><i /><i /><i /></span></div>;

  const why = needs(widget.kind, fields);

  if (why) return <div className="adb-empty">{t(why)}<br />{t('goSettings')}</div>;

  if (isGrid(widget.kind)) {
    return <Grid widget={widget} fields={fields} allFields={template.fields} api={api} ctx={ctx} full={full} query={query} mine={mine} onWidget={onWidget} onOpenRow={onOpenRow} openId={openId} onAll={onAll} />;
  }

  if (!data) return <div className="adb-empty"><span className="adb-dots"><i /><i /><i /></span></div>;

  const note = data.partial ? <p className="adb-partial">{t('partial', data.rows.length.toLocaleString(ctx.lang), data.total.toLocaleString(ctx.lang))}</p> : null;
  const props = { widget, fields, data, ctx, big: full };
  const inner = {
    number: <NumberW {...props} />,
    battery: <BatteryW {...props} />,
    gantt: <GanttW {...props} />,
  }[widget.kind] || <ChartW {...props} />;

  return <>{inner}{note}</>;
};

// ---------- A record in a side card (the form system's embedded form goes in the frame) ----------
const Sheet = ({ widget, template, row, list, onMove, onClose, ctx }) => {
  const { t, lang } = ctx;
  const at = list.findIndex((r) => r.id === row.id);
  const status = template.fields.find((f) => f.kind === 'select' && /狀態|status/i.test(f.label));
  const title = row.title || (template.fields.find((f) => f.kind === 'text') ? row.values[template.fields.find((f) => f.kind === 'text').key] : '');
  const close = useRef(null);
  const bf = (v) => (/完成|已續約|通過|done|approved/i.test(v) ? 'ok' : /進行|洽談|progress/i.test(v) ? 'blue' : /待|未|todo/i.test(v) ? 'warn' : 'wait');
  const value = (f) => {
    const v = row.values[f.key];

    if (v === '' || v === undefined || (Array.isArray(v) && !v.length)) return <span className="adb-missing">{t('missing')}</span>;
    if (f.kind === 'select') return <span className={`adb-bfpill ${bf(v)}`}>{v}</span>;
    if (f.kind === 'multi') return v.join('、');
    if (f.kind === 'date') return ymd(v);
    if (f.kind === 'number') return fmt(f, v, lang);

    return String(v);
  };

  useEffect(() => { close.current && close.current.focus(); }, []);

  return (
    <aside className="adb-sheet" aria-label={`${template.name} ${row.no}`}>
      <div className="adb-sheet__top">
        <span className="adb-muted">{t('sheetOf', template.name, at + 1, list.length)}</span>
        <span className="adb-sp" />
        <button type="button" className="adb-iconbtn" disabled={at <= 0} onClick={() => onMove(list[at - 1])} aria-label={t('sheetPrev')}><Icon name="up" /></button>
        <button type="button" className="adb-iconbtn" disabled={at < 0 || at >= list.length - 1} onClick={() => onMove(list[at + 1])} aria-label={t('sheetNext')}><Icon name="down" /></button>
        <button type="button" className="adb-iconbtn" ref={close} onClick={onClose} aria-label={t('close')}><Icon name="close" /></button>
      </div>
      <div className="adb-sheet__body">
        <article className="adb-bf">
          <header className="adb-bf__head">
            <span className="adb-bf__ic"><Icon name="doc" /></span>
            <div className="adb-bf__ttl"><h3>{template.name} {row.no}</h3><p>{title}</p></div>
            {status && row.values[status.key] ? <span className={`adb-bfpill ${bf(row.values[status.key])}`}>{row.values[status.key]}</span> : null}
          </header>
          <div className="adb-bf__frame">
            <span className="adb-bf__tag">{t('sheetFrame')}</span>
            <dl className="adb-bf__meta">{template.fields.map((f) => [<dt key={`k${f.key}`}>{f.label}</dt>, <dd key={`v${f.key}`}>{value(f)}</dd>])}</dl>
          </div>
        </article>
        <p className="adb-sheet__note">{t('sheetNote')}</p>
      </div>
    </aside>
  );
};

// ---------- A widget grown into a full page, its settings at the side ----------
const Full = ({ widget, api, ctx, query, mine, onWidget, onClose, from, wrap }) => {
  const { t } = ctx;
  const state = useWidget(widget, api, query, mine);
  const [settings, setSettings] = useState(!isGrid(widget.kind));
  const [sheet, setSheet] = useState(null);
  const box = useRef(null);
  const template = state.template;
  const chosen = state.fields;
  const rec = recommend(chosen);
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Grown from where the widget is
  useEffect(() => {
    if (!from || !wrap || reduced || !box.current) return;

    const r = from.getBoundingClientRect();
    const o = wrap.getBoundingClientRect();

    box.current.animate([{ clipPath: `inset(${r.top - o.top}px ${o.right - r.right}px ${o.bottom - r.bottom}px ${r.left - o.left}px round 6px)` }, { clipPath: 'inset(0 0 0 0 round 0)' }], { duration: 360, easing: 'cubic-bezier(.2,.8,.2,1)' });
  }, []);

  const shut = () => {
    if (!from || !wrap || reduced || !box.current) return onClose();

    const r = from.getBoundingClientRect();
    const o = wrap.getBoundingClientRect();

    box.current.animate([{ clipPath: 'inset(0 0 0 0 round 0)' }, { clipPath: `inset(${r.top - o.top}px ${o.right - r.right}px ${o.bottom - r.bottom}px ${r.left - o.left}px round 6px)` }], { duration: 280, easing: 'cubic-bezier(.4,0,.2,1)' }).onfinish = onClose;

    return undefined;
  };

  useEffect(() => {
    const key = (e) => { if (e.key === 'Escape' && !e.defaultPrevented) { if (sheet) setSheet(null); else shut(); } };

    window.addEventListener('keydown', key);

    return () => window.removeEventListener('keydown', key);
  });

  const change = (patch) => {
    const next = { ...widget, ...patch };
    const [mw, mh] = KINDS[next.kind].min;

    next.w = Math.min(COLS, Math.max(next.w, mw));
    next.h = Math.max(next.h, mh);
    next.x = Math.min(next.x, COLS - next.w);
    onWidget(next);
  };

  return (
    <section className="adb-full" ref={box} role="dialog" aria-modal="true" aria-label={widget.title}>
      <header className="adb-full__head">
        <h2>{widget.title}</h2>
        {template ? <span className="adb-form">{template.name}</span> : null}
        <span className="adb-sp" />
        <button type="button" className="adb-btn" aria-pressed={settings} onClick={() => setSettings(!settings)}><Icon name="sliders" />{t('settings')}</button>
        <button type="button" className="adb-iconbtn" onClick={shut} aria-label={t('close')}><Icon name="close" /></button>
      </header>
      <div className={`adb-full__body${settings ? '' : ' no-set'}`}>
        <div className={`adb-full__main${isGrid(widget.kind) ? ' is-grid' : ''}`}>
          <Body widget={widget} state={state} api={api} ctx={ctx} query={query} mine={mine} full onWidget={onWidget} openId={sheet ? sheet.row.id : null} onOpenRow={(row, list) => setSheet({ row, list: list || [row] })} />
          {sheet && template ? <Sheet widget={widget} template={template} row={sheet.row} list={sheet.list} onMove={(row) => setSheet({ ...sheet, row })} onClose={() => setSheet(null)} ctx={ctx} /> : null}
        </div>
        {settings ? (
          <aside className="adb-set" aria-label={t('widgetSettings')}>
            <h4><Icon name="sliders" />{t('widgetSettings')}</h4>
            <label className="adb-row">{t('name')}<input type="text" value={widget.title} onChange={(e) => onWidget({ ...widget, title: e.target.value })} /></label>
            <h5>{t('kinds')}</h5>
            <div className="adb-kinds">
              {KIND_ORDER.map((k) => {
                const why = needs(k, chosen);

                return (
                  <button type="button" key={k} className="adb-kind" aria-pressed={widget.kind === k} disabled={!!why} title={why ? t(why) : t(`k_${k}`)} onClick={() => change({ kind: k })}>
                    {k === rec && !why ? <span className="adb-rec">{t('recommended')}</span> : null}<Icon name={k} />{t(`k_${k}`)}
                  </button>
                );
              })}
            </div>
            <p className="adb-why">{chosen.length ? t('recommend', t(`k_${rec}`)) : ''}</p>
            {template ? (
              <>
                <h5>{t('fieldsOf', template.name)}</h5>
                <div className="adb-fields">
                  {template.fields.map((f) => (
                    <label className="adb-field" key={f.key}>
                      <input type="checkbox" checked={widget.fields.includes(f.key)} onChange={(e) => {
                        const keys = e.target.checked ? [...widget.fields, f.key] : widget.fields.filter((k) => k !== f.key);
                        const fieldsNow = template.fields.filter((x) => keys.includes(x.key));
                        const patch = { fields: fieldsNow.map((x) => x.key) };

                        if (needs(widget.kind, fieldsNow) && !needs(recommend(fieldsNow), fieldsNow)) patch.kind = recommend(fieldsNow);
                        change(patch);
                      }} />
                      {f.label}<span className="adb-ftype">{t(`f_${f.kind}`)}</span>
                    </label>
                  ))}
                </div>
              </>
            ) : null}
            {isGrid(widget.kind) ? (
              <>
                <h5>{t('freeze')}</h5>
                <label className="adb-row">{t('freezeHint')}
                  <select value={widget.freeze ?? 1} onChange={(e) => onWidget({ ...widget, freeze: Number(e.target.value) })}>
                    {[0, 1, 2, 3].filter((n) => n <= chosen.length).map((n) => <option key={n} value={n}>{n ? t('freezeN', n, chosen.slice(0, n).map((f) => f.label).join('、')) : t('freezeNone')}</option>)}
                  </select>
                </label>
              </>
            ) : null}
            <p className="adb-sizeinfo">{t('size', widget.w, widget.h, t(`k_${widget.kind}`), KINDS[widget.kind].min[0], KINDS[widget.kind].min[1])}</p>
          </aside>
        ) : null}
      </div>
    </section>
  );
};

// ---------- Adding a widget: a kind, then a source's template and its fields ----------
const Adder = ({ entry, sources, api, ctx, onAdd, onClose }) => {
  const { t } = ctx;
  const usable = sources.filter((s) => s.connected);
  const [source, setSource] = useState(usable[0] ? usable[0].name : '');
  const [templates, setTemplates] = useState(null);
  const [template, setTemplate] = useState('');
  const [all, setAll] = useState(null);
  const [keys, setKeys] = useState([]);
  const [kind, setKind] = useState(entry === 'chart' ? null : entry);
  const [title, setTitle] = useState('');
  const [failed, setFailed] = useState(null);

  useEffect(() => {
    if (!source) return;
    setTemplates(null);
    api.templates(source).then((got) => { setTemplates(got.items); setTemplate(got.items[0] ? got.items[0].id : ''); }, setFailed);
  }, [source]);

  useEffect(() => {
    if (!template) return;
    setAll(null);
    api.fields(source, template).then((got) => {
      setAll(got);

      // At first: every field for a table, otherwise those the kind can use
      const firstOf = (kind) => got.fields.find((f) => f.kind === kind);
      const pick = isGrid(entry)
        ? got.fields.slice(0, 8)
        : entry === 'chart'
          ? [firstOf('date'), firstOf('select'), firstOf('number')].filter(Boolean)
          : got.fields.filter((f) => ({ number: ['number', 'date', 'select'], battery: ['select'], line: ['date', 'number'], gantt: ['text', 'date', 'number'] }[entry] || []).includes(f.kind));

      setKeys((pick.length ? pick : got.fields).map((f) => f.key));
    }, setFailed);
  }, [template]);

  const fields = all ? all.fields.filter((f) => keys.includes(f.key)) : [];
  const rec = recommend(fields);
  const using = kind && !needs(kind, fields) ? kind : (needs(rec, fields) ? 'table' : rec);
  const [w, h] = KINDS[using].def;

  return (
    <div className="adb-scrim" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="adb-dlg" role="dialog" aria-modal="true" aria-labelledby="adb-dlg-t" onKeyDown={(e) => { if (e.key === 'Escape') { e.stopPropagation(); onClose(); } }}>
        <header><h2 id="adb-dlg-t">{t('newWidget')}</h2><p>{t('newWidgetLead')}</p></header>
        <div className="adb-dlg__body">
          <div>
            {usable.length > 1 ? <label className="adb-row">{t('source')}<select value={source} onChange={(e) => setSource(e.target.value)}>{usable.map((s) => <option key={s.name} value={s.name}>{s.title}</option>)}</select></label> : null}
            <label className="adb-row">{t('template')}
              <select value={template} onChange={(e) => setTemplate(e.target.value)} disabled={!templates}>
                {templates ? templates.map((x) => <option key={x.id} value={x.id}>{x.name}</option>) : <option>{t('loading')}</option>}
              </select>
            </label>
            <label className="adb-row">{t('name')}<input type="text" value={title} placeholder={all ? `${all.name}・${t(`k_${using}`)}` : ''} onChange={(e) => setTitle(e.target.value)} /></label>
            <h5>{t('useFields')}</h5>
            <div className="adb-fields">
              {all ? all.fields.map((f) => (
                <label className="adb-field" key={f.key}>
                  <input type="checkbox" checked={keys.includes(f.key)} onChange={(e) => setKeys(e.target.checked ? all.fields.filter((x) => x.key === f.key || keys.includes(x.key)).map((x) => x.key) : keys.filter((k) => k !== f.key))} />
                  {f.label}<span className="adb-ftype">{t(`f_${f.kind}`)}</span>
                </label>
              )) : <p className="adb-muted">{failed ? failed.message : t('loading')}</p>}
            </div>
          </div>
          <div>
            <h5>{t('kinds')}</h5>
            <div className="adb-kinds">
              {KIND_ORDER.map((k) => {
                const why = needs(k, fields);

                return (
                  <button type="button" key={k} className="adb-kind" aria-pressed={using === k} disabled={!!why} title={why ? t(why) : ''} onClick={() => setKind(k)}>
                    {k === rec && !why ? <span className="adb-rec">{t('recommended')}</span> : null}<Icon name={k} />{t(`k_${k}`)}
                  </button>
                );
              })}
            </div>
            <p className="adb-why">{fields.length ? t('recommend', t(`k_${rec}`)) : t('pickFields')}</p>
          </div>
        </div>
        <footer>
          <span className="adb-muted">{t('placeAt', t(`k_${using}`), w, h, KINDS[using].min[0], KINDS[using].min[1])}</span>
          <button type="button" className="adb-btn" onClick={onClose}>{t('cancel')}</button>
          <button type="button" className="adb-btn adb-btn--main" disabled={!fields.length || !template} onClick={() => onAdd({ id: newId(), kind: using, source, template, fields: keys, title: title.trim() || `${all.name}・${t(`k_${using}`)}`, w, h, freeze: 1, cols: {} })}>{t('addIt')}</button>
        </footer>
      </div>
    </div>
  );
};

// ---------- The board ----------
export const Board = ({ api, ctx }) => {
  const { t } = ctx;
  const [widgets, setWidgets] = useState(null);
  const [sources, setSources] = useState([]);
  const [failed, setFailed] = useState(null);
  const [editing, setEditing] = useState(false);
  const [over, setOver] = useState(false);
  const [mine, setMine] = useState(false);
  const [query, setQuery] = useState('');
  const [full, setFull] = useState(null);
  const [adding, setAdding] = useState(null);
  const [menu, setMenu] = useState(null);
  const [toast, setToast] = useState(null);
  const [width, setWidth] = useState(900);
  const [drag, setDrag] = useState(null);
  const [scrollTop, setScrollTop] = useState(0);
  const wrap = useRef(null);
  const scroller = useRef(null);
  const grid = useRef(null);
  const saving = useRef(0);
  const nodes = useRef(new Map());
  const typing = useRef(0);

  const say = useCallback((text, undo) => {
    setToast({ text, undo, at: Date.now() });
  }, []);

  useEffect(() => {
    if (!toast) return undefined;
    const gone = setTimeout(() => setToast(null), 5000);

    return () => clearTimeout(gone);
  }, [toast]);

  const sourceTitle = useCallback((name) => (sources.find((s) => s.name === name) || { title: name }).title, [sources]);
  const context = useMemo(() => ({ ...ctx, toast: say, sourceTitle }), [ctx, say, sourceTitle]);

  useEffect(() => {
    api.board().then((got) => { setWidgets(settle(got.widgets.map((w) => ({ ...w })))); setSources(got.sources); }, setFailed);
  }, [api]);

  // Kept a moment after the last change
  const keep = useCallback((list) => {
    clearTimeout(saving.current);
    saving.current = setTimeout(() => { api.save(list).catch((error) => say(error.message)); }, 600);
  }, [api, say]);

  const change = useCallback((list) => {
    const settled = settle(list.map((w) => ({ ...w })));

    setWidgets(settled);
    keep(settled);
  }, [keep]);

  const update = useCallback((next) => change(widgets.map((w) => (w.id === next.id ? next : w))), [widgets, change]);

  // Columns as wide as the board is
  useEffect(() => {
    const measure = () => { if (grid.current) setWidth(grid.current.clientWidth); };
    const watch = new ResizeObserver(measure);

    if (scroller.current) watch.observe(scroller.current);
    measure();

    return () => watch.disconnect();
  }, [widgets === null]);

  const phone = width < 640;
  const colW = (width - GAP * (COLS - 1)) / COLS;
  const px = (w) => ({ left: w.x * (colW + GAP), top: w.y * (ROW + GAP), width: w.w * colW + (w.w - 1) * GAP, height: w.h * ROW + (w.h - 1) * GAP });
  const rows = widgets ? bottom(widgets) + (editing || over ? 3 : 0) : 0;
  const height = rows * (ROW + GAP);
  const viewH = scroller.current ? scroller.current.clientHeight : 600;
  const scale = over ? Math.min(1, Math.max(0.18, (viewH - 30) / Math.max(1, height))) : 1;

  // Moving and sizing: the rest laid out around where it would land
  const start = (e, w, mode) => {
    if (phone || e.button !== 0) return;
    e.preventDefault();

    const base = widgets.map((o) => ({ ...o }));
    const p = px(w);
    const at = { x: e.clientX, y: e.clientY };
    const [minW, minH] = KINDS[w.kind].min;
    let target = { x: w.x, y: w.y, w: w.w, h: w.h };
    let latest = base;

    const move = (ev) => {
      const dx = (ev.clientX - at.x) / scale;
      const dy = (ev.clientY - at.y) / scale;
      const box = { ...p };

      if (mode === 'move') {
        box.left = p.left + dx;
        box.top = Math.max(0, p.top + dy);
        target = { ...target, x: Math.min(COLS - w.w, Math.max(0, Math.round(box.left / (colW + GAP)))), y: Math.max(0, Math.round(box.top / (ROW + GAP))) };
      } else {
        box.width = Math.max(colW, p.width + dx);
        box.height = Math.max(ROW, p.height + dy);
        target = { ...target, w: Math.min(COLS - w.x, Math.max(minW, Math.round((box.width + GAP) / (colW + GAP)))), h: Math.max(minH, Math.round((box.height + GAP) / (ROW + GAP))) };
      }

      latest = settle(base.map((o) => (o.id === w.id ? { ...o, ...target } : { ...o })), w.id);
      setWidgets(latest);
      setDrag({ id: w.id, box, target, min: target.w === minW && target.h === minH });
    };
    const up = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      setDrag(null);
      change(latest);
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  const add = (w) => {
    const at = firstFree(widgets, w.w, w.h);
    const next = [...widgets, { ...w, ...at }];

    setAdding(null);
    change(next);
    setTimeout(() => {
      const node = nodes.current.get(w.id);

      if (node) {
        node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        node.classList.add('is-fresh');
        setTimeout(() => node.classList.remove('is-fresh'), 1700);
      }
    }, 60);
  };

  const remove = (w) => {
    const before = widgets;

    change(widgets.filter((o) => o.id !== w.id));
    say(t('removed', w.title), () => change(before));
  };

  if (failed) return <div className="adb adb-empty">{failed.message || t('error')}</div>;
  if (!widgets) return <div className="adb adb-empty"><span className="adb-dots"><i /><i /><i /></span></div>;

  const connected = sources.filter((s) => s.connected);
  const fullWidget = full ? widgets.find((w) => w.id === full) : null;
  const ordered = [...widgets].sort((a, b) => a.y - b.y || a.x - b.x);
  const miniShow = !over && !phone && height > viewH + 20;
  const miniScale = 112 / Math.max(1, width);

  return (
    <div className={`adb${editing || over ? ' is-edit' : ''}${over ? ' is-over' : ''}${phone ? ' is-phone' : ''}`}>
      <header className="adb-bar">
        <h2 className="adb-bar__title">{t('title')}</h2>
        <div className="adb-tools">
          <button type="button" className="adb-btn adb-btn--main" disabled={!connected.length} aria-haspopup="menu" onClick={(e) => setMenu(menu ? null : e.currentTarget.getBoundingClientRect())}><Icon name="plus" />{t('add')}</button>
          <label className="adb-search"><Icon name="search" /><input type="search" placeholder={t('search')} aria-label={t('search')} onChange={(e) => { const v = e.target.value.trim(); clearTimeout(typing.current); typing.current = setTimeout(() => setQuery(v), 350); }} /></label>
          <button type="button" className="adb-btn" aria-pressed={mine} onClick={() => setMine(!mine)}><Icon name="user" />{t('mine')}</button>
          <span className="adb-sp" />
          {!phone ? <button type="button" className="adb-btn" aria-pressed={editing} onClick={() => setEditing(!editing)}><Icon name="layout" />{t('edit')}</button> : null}
          {!phone ? <button type="button" className="adb-btn" aria-pressed={over} onClick={() => { setOver(!over); if (!over) { scroller.current.scrollTop = 0; say(t('overviewHint')); } }}><Icon name="overview" />{t('overview')}</button> : null}
        </div>
      </header>

      <div className="adb-wrap" ref={wrap}>
        <div className="adb-scroll" ref={scroller} onScroll={(e) => setScrollTop(e.currentTarget.scrollTop)}>
          {!widgets.length ? (
            <div className="adb-start">
              <p>{sources.length ? t('empty') : t('noSources')}</p>
              <ul className="adb-sources">
                {sources.map((s) => (
                  <li key={s.name} className={s.connected ? 'is-on' : ''}>
                    <i className="adb-dot" />
                    <div><b>{s.title}</b>{s.connected ? <span>{s.account}</span> : null}</div>
                    {s.ready && !s.connected && s.connect ? <a className="adb-main" href={s.connect}>{t('connect', s.title)}</a> : null}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          <div className="adb-grid" ref={grid} style={phone ? undefined : { height, transform: over ? `scale(${scale})` : undefined, '--cw': `${colW + GAP}px`, '--rh': `${ROW + GAP}px`, '--gap': `${GAP}px`, '--s': scale }}>
            {(phone ? ordered : widgets).map((w) => {
              const p = drag && drag.id === w.id ? drag.box : px(w);

              return (
                <article key={w.id} ref={(n) => { if (n) nodes.current.set(w.id, n); else nodes.current.delete(w.id); }}
                  className={`adb-w${drag && drag.id === w.id ? ' is-moving' : ''}`}
                  style={phone ? { height: Math.max(2, w.h) * ROW * 0.8 + 40 } : { left: p.left, top: p.top, width: p.width, height: p.height }}
                  onPointerDown={over ? (e) => start(e, w, 'move') : undefined}>
                  <header className="adb-w__head" onPointerDown={editing && !over ? (e) => { if (!e.target.closest('button')) start(e, w, 'move'); } : undefined}>
                    {editing ? <span className="adb-grip"><Grip /></span> : null}
                    <h3>{w.title}</h3>
                    <span className="adb-w__acts">
                      <button type="button" className="adb-iconbtn" onClick={() => setFull(w.id)} aria-label={t('open')} title={t('open')}><Icon name="expand" /></button>
                      <button type="button" className="adb-iconbtn" onClick={(e) => setMenu({ widget: w, rect: e.currentTarget.getBoundingClientRect() })} aria-label={t('more')} title={t('more')}><Icon name="more" /></button>
                    </span>
                  </header>
                  <div className="adb-w__body">
                    {over ? null : <WidgetBody widget={w} api={api} ctx={context} query={query} mine={mine} onWidget={update} onAll={() => setFull(w.id)} />}
                  </div>
                  <div className="adb-w__face"><Icon name={w.kind} /><b>{w.title}</b><small>{t(`k_${w.kind}`)} · {w.w}×{w.h}</small></div>
                  {drag && drag.id === w.id ? <span className="adb-sizetag">{drag.target.w} × {drag.target.h}</span> : null}
                  {editing && !over ? <span className="adb-rs-corner" onPointerDown={(e) => start(e, w, 'size')} title="↘" /> : null}
                </article>
              );
            })}
            {drag ? <div className="adb-drop" style={px(drag.target)} /> : null}
          </div>
        </div>

        {miniShow ? (
          <div className="adb-mini" aria-hidden="true"
            onPointerDown={(e) => {
              const box = e.currentTarget.querySelector('.adb-mini__in').getBoundingClientRect();
              const go = (ev) => { scroller.current.scrollTop = (ev.clientY - box.top) / miniScale - viewH / 2; };

              go(e);
              const up = () => { window.removeEventListener('pointermove', go); window.removeEventListener('pointerup', up); };

              window.addEventListener('pointermove', go);
              window.addEventListener('pointerup', up);
            }}>
            <span className="adb-mini__label">{t('minimap')}</span>
            <div className="adb-mini__in" style={{ height: height * miniScale }}>
              {widgets.map((w) => { const p = px(w); return <i key={w.id} style={{ left: p.left * miniScale, top: p.top * miniScale, width: p.width * miniScale, height: p.height * miniScale }} />; })}
              <div className="adb-mini__view" style={{ top: scrollTop * miniScale, height: Math.min(height, viewH) * miniScale }} />
            </div>
          </div>
        ) : null}

        {fullWidget ? <Full key={fullWidget.id} widget={fullWidget} api={api} ctx={context} query={query} mine={mine} onWidget={update} onClose={() => setFull(null)} from={nodes.current.get(fullWidget.id)} wrap={wrap.current} /> : null}
      </div>

      {menu && menu.widget ? (
        <div className="adb-pop adb-menu" style={{ top: menu.rect.bottom + 4, left: Math.max(8, menu.rect.right - 180) }} role="menu" onMouseLeave={() => setMenu(null)}>
          <button type="button" role="menuitem" onClick={() => { setMenu(null); setFull(menu.widget.id); }}>{t('open')}</button>
          <button type="button" role="menuitem" onClick={() => { setMenu(null); add({ ...menu.widget, id: newId(), title: t('copyTitle', menu.widget.title) }); }}>{t('copy')}</button>
          <button type="button" role="menuitem" className="danger" onClick={() => { setMenu(null); remove(menu.widget); }}>{t('remove')}</button>
        </div>
      ) : null}

      {menu && !menu.widget ? (
        <div className="adb-pop adb-entries" style={{ top: menu.bottom + 8, left: Math.max(8, Math.min(menu.left, window.innerWidth - 360)) }} role="menu" onMouseLeave={() => setMenu(null)}>
          {ENTRIES.map(([k, key, icon]) => {
            const [name, lead] = t(key);

            return <button type="button" role="menuitem" key={k} onClick={() => { setMenu(null); setAdding(k); }}><span className="adb-entries__ic"><Icon name={icon || k} /></span><b>{name}</b><small>{lead}</small></button>;
          })}
        </div>
      ) : null}

      {adding ? <Adder entry={adding} sources={sources} api={api} ctx={context} onAdd={add} onClose={() => setAdding(null)} /> : null}

      {toast ? <div className="adb-toast" role="status"><span>{toast.text}</span>{toast.undo ? <button type="button" onClick={() => { toast.undo(); setToast(null); }}>{t('undo')}</button> : null}</div> : null}
    </div>
  );
};

const WidgetBody = ({ widget, api, ctx, query, mine, onWidget, onAll }) => {
  const state = useWidget(widget, api, query, mine);

  return <Body widget={widget} state={state} api={api} ctx={ctx} query={query} mine={mine} full={false} onWidget={onWidget} onAll={onAll} onOpenRow={onAll} />;
};

