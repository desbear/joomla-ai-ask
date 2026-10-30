// A record's page of its source's own, in a frame (0.6.5): in the side card a record opens in, and in a dialog to
// fill a new one. The page is asked for each time (what starts it may carry a sign-in that does not last); when it
// says it is ready, what the source gave is sent back to it, to its origin only. A page that never says so (its
// system not letting this site show it, say) is told as such after a while, with a way to try again.
import { useEffect, useRef, useState } from 'react';
import { Icon } from '../icons.jsx';

const SILENT_AFTER = 12000;

export const Frame = ({ api, source, template, row, title, t, onDone, onCancel, onFail }) => {
  const frame = useRef(null);
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState(null);
  const [state, setState] = useState('asking');
  const [error, setError] = useState('');
  const said = useRef({});

  said.current = { onDone, onCancel, onFail };

  useEffect(() => {
    let live = true;

    setGot(null);
    setState('asking');
    setError('');
    api.embed({ source, template, row }).then((page) => { if (live) { setGot(page); setState('loading'); } }, (e) => { if (live) { setError(e.message || ''); setState('failed'); } });

    return () => { live = false; };
  }, [api, source, template, row, tick]);

  useEffect(() => {
    if (!got) return undefined;

    const hear = (e) => {
      // Only its own page, from where it was loaded
      if (!frame.current || e.source !== frame.current.contentWindow || e.origin !== got.origin) return;

      const event = e.data && typeof e.data === 'object' ? String(e.data.event || '') : '';

      if (got.hello && event === got.hello.wait) {
        frame.current.contentWindow.postMessage(got.hello.send, got.origin);
        setState('ready');
      } else if (got.done.includes(event)) {
        if (said.current.onDone) said.current.onDone(e.data);
      } else if (got.cancel.includes(event)) {
        if (said.current.onCancel) said.current.onCancel();
      } else if ((got.fail || []).includes(event)) {
        if (said.current.onFail) said.current.onFail(e.data);
      }
    };
    // This site's own Content-Security-Policy not letting the page be framed (frame-src, or child-src / default-src
    // standing for it): told as such at once, rather than waiting to find it silent (0.6.8)
    const barred = (e) => {
      if (!/^(frame|child|default)-src/.test(e.effectiveDirective || e.violatedDirective || '')) return;

      let from = '';

      try { from = new URL(e.blockedURI).origin; } catch { return; }

      if (from === got.origin) setState((now) => (now === 'loading' ? 'barred' : now));
    };
    const silent = window.setTimeout(() => setState((now) => (now === 'loading' ? 'silent' : now)), SILENT_AFTER);

    window.addEventListener('message', hear);
    document.addEventListener('securitypolicyviolation', barred);

    return () => {
      window.removeEventListener('message', hear);
      document.removeEventListener('securitypolicyviolation', barred);
      window.clearTimeout(silent);
    };
  }, [got]);

  return (
    <div className={`adb-frame is-${state}`}>
      {got ? <iframe ref={frame} src={got.url} title={title} referrerPolicy="strict-origin-when-cross-origin" allow="clipboard-write" onLoad={() => { if (!got.hello) setState('ready'); }} /> : null}
      {state === 'asking' || state === 'loading' ? <div className="adb-frame__wait"><span className="adb-dots"><i /><i /><i /></span></div> : null}
      {state === 'silent' || state === 'failed' || state === 'barred' ? (
        <div className="adb-frame__note" role="status">
          <Icon name="alert" />
          <p>{state === 'failed' ? error : state === 'barred' ? t('frameBarred', got.origin) : t('frameSilent')}</p>
          <button type="button" className="adb-btn" onClick={() => setTick((n) => n + 1)}>{t('frameAgain')}</button>
        </div>
      ) : null}
    </div>
  );
};

// A new record filled in a dialog over the table: it stays until closed from its own button (a click beside it
// or Escape asks first, as what is not sent yet would be lost); sent, it closes by itself
export const FillDialog = ({ api, source, template, name, ctx, onSaved, onClose }) => {
  const { t } = ctx;
  const [asking, setAsking] = useState(false);
  const [nudge, setNudge] = useState(0);
  const sent = useRef(false);
  const box = useRef(null);
  const leave = () => (sent.current ? onClose() : setAsking(true));

  useEffect(() => {
    // Before the full page's own Escape (which would close it, and this with it)
    const key = (e) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      e.stopPropagation();
      if (asking) setAsking(false); else leave();
    };

    window.addEventListener('keydown', key, true);

    return () => window.removeEventListener('keydown', key, true);
  });

  useEffect(() => { if (box.current) box.current.focus(); }, []);

  const saved = () => {
    sent.current = true;
    onSaved();
    ctx.toast(t('fillSaved'));
    window.setTimeout(onClose, 600);
  };
  const beside = (e) => {
    if (e.target !== e.currentTarget) return;
    setNudge((n) => n + 1);
    ctx.toast(t('fillStay'));
  };

  return (
    <div className="adb-fill" onPointerDown={beside}>
      <section key={nudge} className={`adb-fill__box${nudge ? ' is-nudged' : ''}`} role="dialog" aria-modal="true" aria-label={t('fillOf', name)} ref={box} tabIndex={-1}>
        <header className="adb-fill__head">
          <span className="adb-fill__ic"><Icon name="doc" /></span>
          <h3>{t('fillOf', name)}</h3>
          <span className="adb-sp" />
          <button type="button" className="adb-iconbtn" onClick={leave} aria-label={t('close')} title={t('close')}><Icon name="close" /></button>
        </header>
        <Frame api={api} source={source} template={template} row="" title={t('frameTitle', name)} t={t} onDone={saved} onCancel={leave} onFail={() => ctx.toast(t('frameFailed'))} />
        {asking ? (
          <div className="adb-fill__ask" role="alertdialog" aria-label={t('fillAsk')}>
            <Icon name="alert" />
            <span>{t('fillAsk')}</span>
            <button type="button" className="adb-btn" onClick={() => setAsking(false)}>{t('fillKeep')}</button>
            <button type="button" className="adb-btn adb-btn--danger" onClick={onClose}>{t('fillLeave')}</button>
          </div>
        ) : null}
      </section>
    </div>
  );
};
