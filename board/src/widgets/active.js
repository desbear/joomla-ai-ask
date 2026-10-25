// Whether a widget is seen now: not under a widget opened whole (the board says, as "active"), its page not
// hidden (another tab, the screen locked) and the widget on the screen. A widget that keeps working on its
// own (an animation loop, a game drawn with WebGL, a timer that asks for news) stops while it is not seen and
// carries on when it is again; one that only draws what it is given needs none of this.
import { useEffect, useState } from 'react';

export const useActive = (ref, active = true) => {
  const [shown, setShown] = useState(() => typeof document === 'undefined' || !document.hidden);
  const [onScreen, setOnScreen] = useState(true);

  useEffect(() => {
    const seen = () => setShown(!document.hidden);

    document.addEventListener('visibilitychange', seen);

    return () => document.removeEventListener('visibilitychange', seen);
  }, []);

  useEffect(() => {
    const el = ref && ref.current;

    if (!el || typeof IntersectionObserver === 'undefined') return undefined;

    const watch = new IntersectionObserver((entries) => setOnScreen(entries[entries.length - 1].isIntersecting));

    watch.observe(el);

    return () => watch.disconnect();
  }, [ref]);

  return Boolean(active) && shown && onScreen;
};
