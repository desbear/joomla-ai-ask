// What a widget will look like, drawn in grey while its records load: rows for a table, bars for a chart,
// a ring for a pie, a big number, a battery, a gantt's spans. A light sweeps across them.

import { useEffect, useRef, useState } from 'react';

// Widths that look like words, not a ruler (the same each time, so nothing jumps between draws)
const W = [72, 54, 86, 64, 48, 78, 60, 90, 44, 68];
const w = (i) => `${W[i % W.length]}%`;

const Bar = ({ width, height = 10, round }) => <i className={`adb-sk__b${round ? ' is-round' : ''}`} style={{ width, height }} />;

const Rows = ({ cols = 5, rows = 6, head = true }) => (
  <div className="adb-sk__table" style={{ '--cols': cols }}>
    {head ? <div className="adb-sk__row is-head">{Array.from({ length: cols }, (_, c) => <Bar key={c} width={`${W[(c + 3) % W.length] * 0.6}%`} height={8} />)}</div> : null}
    {Array.from({ length: rows }, (_, r) => (
      <div key={r} className="adb-sk__row" style={{ '--d': `${r * 60}ms` }}>
        {Array.from({ length: cols }, (_, c) => <Bar key={c} width={w(r * 3 + c)} round={c > 1 && (r + c) % 3 === 0} />)}
      </div>
    ))}
  </div>
);

export const Skeleton = ({ kind, cols, head = true }) => {
  let inner;

  if (kind === 'table' || kind === 'editgrid') inner = <Rows cols={Math.min(Math.max(cols || 5, 3), 8)} head={head} />;
  else if (kind === 'number') {
    inner = <div className="adb-sk__number"><Bar width="38%" height={34} /><Bar width="24%" height={9} /></div>;
  } else if (kind === 'battery') {
    inner = <div className="adb-sk__battery"><Bar width="30%" height={22} /><i className="adb-sk__b adb-sk__cell" /><Bar width="52%" height={9} /></div>;
  } else if (kind === 'pie' || kind === 'doughnut') {
    inner = (
      <div className="adb-sk__pie">
        <i className={`adb-sk__b adb-sk__ring${kind === 'doughnut' ? ' is-hollow' : ''}`} />
        <div className="adb-sk__legend">{[0, 1, 2, 3].map((n) => <span key={n}><Bar width={10} height={10} round /><Bar width={`${W[n + 2]}%`} height={8} /></span>)}</div>
      </div>
    );
  } else if (kind === 'hbar' || kind === 'gantt') {
    inner = <div className="adb-sk__hbars">{[0, 1, 2, 3, 4].map((n) => <div key={n}><Bar width={kind === 'gantt' ? '18%' : '14%'} height={8} /><Bar width={`${kind === 'gantt' ? 24 + n * 9 : W[n * 2] * 0.8}%`} height={14} /></div>)}</div>;
  } else {
    // bar, stacked, line, area: columns standing on an axis
    inner = <div className="adb-sk__bars">{[46, 70, 38, 88, 60, 74, 52].map((h, n) => <i key={n} className="adb-sk__b" style={{ height: `${h}%`, '--d': `${n * 50}ms` }} />)}</div>;
  }

  return <div className="adb-sk" role="status" aria-busy="true">{inner}</div>;
};

// Kept over what was loading until it is drawn, then faded out: no blank between the grey and the records.
// "on" says it is still loading (or drawn not yet); once it turns off, the skeleton fades and goes. "fade":
// put in as what loaded is first drawn, only to fade (a chart, coming after the skeleton it replaces).
export const SkeletonCover = ({ on, fade, kind, cols, head, className = '' }) => {
  const [leaving, setLeaving] = useState(Boolean(fade && !on));
  const was = useRef(Boolean(on || fade));

  useEffect(() => {
    if (was.current && !on) {
      setLeaving(true);

      const id = setTimeout(() => setLeaving(false), 500);

      was.current = on;

      return () => clearTimeout(id);
    }

    was.current = on;

    return undefined;
  }, [on]);

  if (!on && !leaving) return null;

  return <div className={`adb-sk-cover${on ? '' : ' is-out'} ${className}`} aria-hidden={!on || undefined}><Skeleton kind={kind} cols={cols} head={head} /></div>;
};
