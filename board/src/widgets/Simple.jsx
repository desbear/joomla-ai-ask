// The widgets that sum records: a number, a battery, the charts, a gantt. Each draws from the records it
// is given (data.rows) and the fields ticked for it.
import { useEffect, useRef } from 'react';
import { Chart, registerables } from 'chart.js';
import { PALETTE, parts, doneOf, tone, total, groupBy, months, monthLabels, short, fmt, today } from '../kinds.js';

Chart.register(...registerables);

const cssVar = (el, name) => getComputedStyle(el).getPropertyValue(name).trim();

export const NumberW = ({ fields, data, ctx }) => {
  const { t, lang } = ctx;
  const p = parts(fields);
  const done = doneOf(p.select);
  const rows = done ? data.rows.filter((r) => r.values[p.select.key] !== done) : data.rows;
  const value = total(rows, p.num);
  let delta = null;

  if (p.dates.length) {
    const key = p.dates[0].key;
    const now = today().slice(0, 7);
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    const before = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    const a = total(rows.filter((r) => String(r.values[key]).startsWith(now)), p.num);
    const b = total(rows.filter((r) => String(r.values[key]).startsWith(before)), p.num);

    if (b) {
      const pct = Math.round(((a - b) / b) * 100);

      delta = <span className={`adb-num__delta ${pct >= 0 ? 'up' : 'down'}`}>{t('thisMonth', short(a, lang, t), pct >= 0 ? t('moreThan') : t('lessThan'), Math.abs(pct))}</span>;
    }
  }

  return (
    <div className="adb-num">
      <b>{p.num ? short(value, lang, t) : value.toLocaleString(lang)}</b>
      <span>{p.num ? t('sumOf', p.num.label) : t('count')}{done ? t('without', done) : ''}</span>
      {delta}
    </div>
  );
};

// Vittal's success, primary and warning
const COLORS = { ok: '#2BCD86', info: '#0E86FE', warn: '#FFA700' };

export const BatteryW = ({ fields, data, ctx }) => {
  const { t } = ctx;
  const p = parts(fields);
  const counts = groupBy(data.rows, p.select.key);
  const options = p.select.options.length ? p.select.options : [...counts.keys()];
  const all = data.rows.length || 1;
  const done = doneOf(p.select) || options[options.length - 1];
  const parts_ = options.map((o, i) => ({ o, n: counts.get(o) || 0, c: COLORS[tone(o)] || PALETTE[i % PALETTE.length] }));

  return (
    <div className="adb-bat">
      <div className="adb-bat__top"><b>{Math.round(((counts.get(done) || 0) / all) * 100)}%</b><span>{done} {counts.get(done) || 0} / {data.rows.length}</span></div>
      <div className="adb-bat__track">{parts_.map((s) => <i key={s.o} style={{ width: `${(s.n / all) * 100}%`, background: s.c }} title={`${s.o} ${s.n}`} />)}</div>
      <div className="adb-bat__legend">{parts_.map((s) => <span key={s.o} style={{ '--c': s.c }}>{s.o} {s.n}</span>)}</div>
    </div>
  );
};

export const ChartW = ({ widget, fields, data, ctx, big }) => {
  const canvas = useRef(null);
  const { t, lang } = ctx;

  useEffect(() => {
    const el = canvas.current;

    if (!el) return undefined;

    const p = parts(fields);
    const rows = data.rows;
    const text = cssVar(el, '--adb-muted') || '#777';
    const line = cssVar(el, '--adb-line') || '#ddd';
    const accent = cssVar(el, '--adb-accent') || '#5b3df0';
    const surface = cssVar(el, '--adb-surface') || '#fff';
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const label = (v) => (p.num ? fmt(p.num, v, lang) : Number(v).toLocaleString(lang));
    const base = {
      responsive: true,
      maintainAspectRatio: false,
      animation: reduced ? false : { duration: 400 },
      plugins: {
        legend: { labels: { color: text, boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'rectRounded', font: { size: big ? 12 : 11 } } },
        tooltip: { callbacks: { label: (c) => ` ${c.dataset.label ? `${c.dataset.label}：` : ''}${label(c.chart.options.indexAxis === 'y' ? c.parsed.x : c.parsed.y)}` } },
      },
    };
    const axes = (stacked) => ({
      x: { stacked, grid: { display: false }, ticks: { color: text, font: { size: 11 } }, border: { display: false } },
      y: { stacked, grid: { color: line }, ticks: { color: text, font: { size: 11 }, callback: (v) => short(v, lang, t) }, border: { display: false } },
    });
    let config;

    if (widget.kind === 'pie' || widget.kind === 'doughnut') {
      const m = [...groupBy(rows, p.select.key, p.num)].sort((a, b) => b[1] - a[1]);
      const wide = widget.w >= 5 || big;

      config = {
        type: widget.kind,
        data: { labels: m.map((e) => e[0]), datasets: [{ data: m.map((e) => e[1]), backgroundColor: m.map((_, i) => PALETTE[i % PALETTE.length]), borderColor: surface, borderWidth: 2 }] },
        options: {
          ...base,
          cutout: widget.kind === 'doughnut' ? '62%' : 0,
          plugins: {
            ...base.plugins,
            legend: { ...base.plugins.legend, position: wide ? 'right' : 'bottom', labels: { ...base.plugins.legend.labels, generateLabels: (ch) => ch.data.labels.map((l, i) => ({ text: `${l}：${short(ch.data.datasets[0].data[i], lang, t)}`, fillStyle: PALETTE[i % PALETTE.length], strokeStyle: 'transparent', fontColor: text, index: i, pointStyle: 'circle' })) } },
            tooltip: { callbacks: { label: (c) => ` ${c.label}：${label(c.parsed)}` } },
          },
        },
      };
    } else if (widget.kind === 'bar' || widget.kind === 'hbar') {
      const m = [...groupBy(rows, p.select.key, p.num)].sort((a, b) => b[1] - a[1]);
      const side = widget.kind === 'hbar';

      config = {
        type: 'bar',
        data: { labels: m.map((e) => e[0]), datasets: [{ label: p.num ? p.num.label : t('count'), data: m.map((e) => e[1]), backgroundColor: m.map((_, i) => PALETTE[i % PALETTE.length]), borderRadius: 4, maxBarThickness: 36 }] },
        options: {
          ...base,
          indexAxis: side ? 'y' : 'x',
          plugins: { ...base.plugins, legend: { display: false } },
          scales: side
            ? { x: { grid: { color: line }, ticks: { color: text, font: { size: 11 }, callback: (v) => short(v, lang, t) }, border: { display: false } }, y: { grid: { display: false }, ticks: { color: text }, border: { display: false } } }
            : axes(false),
        },
      };
    } else if (widget.kind === 'stacked') {
      const d = p.dates[0];
      const keys = months(rows, d.key);
      const cats = [...groupBy(rows, p.select.key, p.num)].sort((a, b) => b[1] - a[1]).map((e) => e[0]);

      config = {
        type: 'bar',
        data: { labels: monthLabels(keys, t), datasets: cats.map((c, i) => ({ label: c, data: keys.map((k) => total(rows.filter((r) => r.values[p.select.key] === c && String(r.values[d.key]).startsWith(k)), p.num)), backgroundColor: PALETTE[i % PALETTE.length], borderRadius: 2, maxBarThickness: 34 })) },
        options: { ...base, plugins: { ...base.plugins, legend: { ...base.plugins.legend, position: 'bottom' } }, scales: axes(true) },
      };
    } else {
      const d = p.dates[0];
      const keys = months(rows, d.key);

      config = {
        type: 'line',
        data: { labels: monthLabels(keys, t), datasets: [{ label: p.num ? p.num.label : t('count'), data: keys.map((k) => total(rows.filter((r) => String(r.values[d.key]).startsWith(k)), p.num)), borderColor: accent, backgroundColor: widget.kind === 'area' ? `${accent}2e` : accent, fill: widget.kind === 'area', tension: 0.35, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 }] },
        options: { ...base, plugins: { ...base.plugins, legend: { display: false } }, scales: axes(false) },
      };
    }

    const chart = new Chart(el, config);

    return () => chart.destroy();
  }, [widget.kind, widget.w, fields, data, big, lang, t]);

  return <div className="adb-chart"><canvas ref={canvas} /></div>;
};

export const GanttW = ({ fields, data, ctx }) => {
  const { t } = ctx;
  const p = parts(fields);
  const [s, e] = p.dates;
  const rows = data.rows.filter((r) => r.values[s.key] && r.values[e.key]).sort((a, b) => (a.values[s.key] > b.values[s.key] ? 1 : -1));

  if (!rows.length) return <div className="adb-empty">{t('none')}</div>;

  const keys = months(rows.map((r) => ({ values: { d: r.values[s.key] } })).concat(rows.map((r) => ({ values: { d: r.values[e.key] } }))), 'd');
  const t0 = new Date(`${keys[0]}-01T00:00:00`).getTime();
  const [ly, lm] = keys[keys.length - 1].split('-').map(Number);
  const t1 = new Date(ly, lm, 1).getTime();
  const at = (d) => ((new Date(`${d}T00:00:00`).getTime() - t0) / (t1 - t0)) * 100;
  const now = today();
  const label = p.text || p.select;
  const progress = p.num;

  return (
    <div className="adb-gantt" style={{ '--m': keys.length }}>
      <div className="adb-gantt__hd" />
      <div className="adb-gantt__hd" style={{ gridTemplateColumns: `repeat(${keys.length}, 1fr)` }}>{monthLabels(keys, t).map((m, i) => <span key={keys[i]}>{m}</span>)}</div>
      {rows.map((r, i) => (
        <div className="adb-gantt__row" key={r.id}>
          <div className="adb-gantt__lab" title={label ? r.values[label.key] : r.no}>{label ? r.values[label.key] : r.no}</div>
          <div className="adb-gantt__lane">
            <i style={{ '--c': PALETTE[i % PALETTE.length], left: `${at(r.values[s.key])}%`, width: `${Math.max(1, at(r.values[e.key]) - at(r.values[s.key]))}%` }} title={`${r.values[s.key]} → ${r.values[e.key]}`}>
              {progress && Number(r.values[progress.key]) <= 100 ? <b style={{ width: `${Number(r.values[progress.key]) || 0}%` }} /> : null}
            </i>
            {now >= keys[0] ? <span className="adb-gantt__today" style={{ left: `${at(now)}%` }} /> : null}
          </div>
        </div>
      ))}
    </div>
  );
};
