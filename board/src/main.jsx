// AI Ask Desk's dashboard, mounted by the console in its empty view:
//   window.AskdeskBoard.mount(element, {urls, token, language}) → unmount()
import { createRoot } from 'react-dom/client';
import { Board } from './Board.jsx';
import { makeApi } from './api.js';
import { makeT } from './i18n.js';
import './board.css';

export const mount = (element, config) => {
  const t = makeT(config.language || document.documentElement.lang || 'en-GB');
  const api = makeApi({ ...config, t });
  const root = createRoot(element);
  const ctx = { t, lang: config.language || 'en-GB' };

  root.render(<Board api={api} ctx={ctx} />);

  return () => root.unmount();
};
