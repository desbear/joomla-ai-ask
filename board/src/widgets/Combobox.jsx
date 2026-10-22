// A select with a search, after Vittal's Combobox: the trigger as Vittal's select, its popup with a
// search bar on top and the chosen item ticked. What it lists is asked for as written (a little after
// the last key) and a page more as the end of the list comes near, so a long list is never all loaded.
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from '../icons.jsx';

export const Combobox = ({ value, label, load, onPick, placeholder, searchPlaceholder, empty, loading, disabled }) => {
  const trigger = useRef(null);
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        ref={trigger}
        className="adb-combo"
        aria-haspopup="listbox"
        aria-expanded={open}
        data-open={open || undefined}
        data-placeholder={!label || undefined}
        disabled={disabled}
        onClick={() => setOpen(!open)}
        onKeyDown={(e) => { if (e.key === 'ArrowDown' && !open) { e.preventDefault(); setOpen(true); } }}
      >
        <span>{label || placeholder}</span>
        <Icon name={open ? 'up' : 'down'} />
      </button>
      {open ? createPortal(
        <Popup anchor={trigger.current} value={value} load={load} searchPlaceholder={searchPlaceholder} empty={empty} loading={loading}
          onPick={(item) => { onPick(item); setOpen(false); trigger.current.focus(); }}
          onClose={() => { setOpen(false); trigger.current.focus(); }} />,
        trigger.current.closest('.adb') || document.body,
      ) : null}
    </>
  );
};

const Popup = ({ anchor, value, load, searchPlaceholder, empty, loading, onPick, onClose }) => {
  const box = useRef(null);
  const list = useRef(null);
  const asked = useRef(0);
  const [place, setPlace] = useState(null);
  const [words, setWords] = useState('');
  const [items, setItems] = useState(null);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState(false);
  const [at, setAt] = useState(-1);

  // Under the trigger as wide as it, or above it when there is no room below
  useEffect(() => {
    const r = anchor.getBoundingClientRect();
    const h = 300;
    const below = r.bottom + 4 + h < window.innerHeight || r.top < h;

    setPlace(below ? { top: r.bottom + 4, left: r.left, width: r.width } : { bottom: window.innerHeight - r.top + 4, left: r.left, width: r.width });
  }, [anchor]);

  const fetchPage = useCallback((q, p) => {
    const mine = ++asked.current;

    setBusy(true);
    load(q, p).then((got) => {
      if (mine !== asked.current) return;
      setItems((before) => (p === 1 ? got.items : [...(before || []), ...got.items]));
      setTotal(got.total ?? got.items.length);
      setPage(p);
      setBusy(false);
    }, () => { if (mine === asked.current) setBusy(false); });
  }, [load]);

  // Asked for again a little after the last key
  useEffect(() => {
    const later = setTimeout(() => { setAt(-1); fetchPage(words.trim(), 1); }, words ? 250 : 0);

    return () => clearTimeout(later);
  }, [words, fetchPage]);

  // The chosen one in view, once listed
  useEffect(() => {
    if (items && at === -1) {
      const i = items.findIndex((x) => x.id === value);

      if (i >= 0) {
        setAt(i);
        list.current?.children[i]?.scrollIntoView({ block: 'nearest' });
      }
    }
  }, [items]);

  // Away from it, or Escape: closed
  useEffect(() => {
    const away = (e) => { if (box.current && !box.current.contains(e.target) && !anchor.contains(e.target)) onClose(); };

    document.addEventListener('pointerdown', away, true);

    return () => document.removeEventListener('pointerdown', away, true);
  }, [anchor, onClose]);

  const more = () => {
    const el = list.current;

    if (el && !busy && items && items.length < total && el.scrollTop + el.clientHeight > el.scrollHeight - 80) fetchPage(words.trim(), page + 1);
  };

  const move = (to) => {
    if (!items || !items.length) return;
    const i = Math.max(0, Math.min(items.length - 1, to));

    setAt(i);
    list.current?.children[i]?.scrollIntoView({ block: 'nearest' });
  };

  const key = (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); move(at + 1); }
    if (e.key === 'ArrowUp') { e.preventDefault(); move(at - 1); }
    if (e.key === 'Enter' && items && items[at]) { e.preventDefault(); onPick(items[at]); }
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); onClose(); }
  };

  return (
    <div ref={box} className="adb-combo__pop" style={place || { visibility: 'hidden' }} onKeyDown={key}>
      <div className="adb-combo__search">
        <label>
          <Icon name="search" />
          <input type="search" autoFocus value={words} placeholder={searchPlaceholder} onChange={(e) => setWords(e.target.value)} aria-controls="adb-combo-list" aria-activedescendant={at >= 0 ? `adb-combo-${at}` : undefined} />
        </label>
      </div>
      <ul ref={list} id="adb-combo-list" role="listbox" onScroll={more}>
        {(items || []).map((x, i) => (
          <li key={x.id} id={`adb-combo-${i}`} role="option" aria-selected={x.id === value} data-highlighted={i === at || undefined}
            onPointerEnter={() => setAt(i)} onClick={() => onPick(x)}>
            <span>{x.name}</span>
            {x.id === value ? <Icon name="check" /> : null}
          </li>
        ))}
      </ul>
      {items && !items.length && !busy ? <p className="adb-combo__empty">{empty}</p> : null}
      {busy ? <p className="adb-combo__empty">{loading}</p> : null}
    </div>
  );
};
