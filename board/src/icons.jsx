// The dashboard's icons: a kind's, and the few the controls use. Static drawings only.
const PATHS = {
  table: <><rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" strokeWidth="1.8" /><path d="M3 9h18M3 14h18M9 9v11" fill="none" stroke="currentColor" strokeWidth="1.8" /></>,
  editgrid: <><path d="M20 11V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6M3 9h17M9 9v11" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" /><path d="m17.5 13.5 3 3-5 5H12.5v-3z" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" /></>,
  number: <path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />,
  battery: <><rect x="2" y="7" width="17" height="10" rx="2" fill="none" stroke="currentColor" strokeWidth="1.8" /><path d="M22 10v4" stroke="currentColor" strokeWidth="1.8" /><rect x="4.5" y="9.5" width="7" height="5" rx="1" fill="currentColor" /></>,
  pie: <><path d="M12 3a9 9 0 1 0 9 9h-9z" fill="none" stroke="currentColor" strokeWidth="1.8" /><path d="M15 3.5A9 9 0 0 1 20.5 9H15z" fill="currentColor" /></>,
  doughnut: <circle cx="12" cy="12" r="7.5" fill="none" stroke="currentColor" strokeWidth="3.2" strokeDasharray="30 18" />,
  bar: <g fill="currentColor"><rect x="4" y="11" width="3.5" height="9" rx="1" /><rect x="10.25" y="5" width="3.5" height="15" rx="1" /><rect x="16.5" y="14" width="3.5" height="6" rx="1" /></g>,
  hbar: <g fill="currentColor"><rect x="4" y="4" width="14" height="3.5" rx="1" /><rect x="4" y="10.25" width="9" height="3.5" rx="1" /><rect x="4" y="16.5" width="16" height="3.5" rx="1" /></g>,
  stacked: <g fill="currentColor"><rect x="4" y="12" width="3.5" height="8" rx="1" /><rect x="4" y="7" width="3.5" height="4" rx="1" opacity=".45" /><rect x="10.25" y="9" width="3.5" height="11" rx="1" /><rect x="10.25" y="4" width="3.5" height="4" rx="1" opacity=".45" /><rect x="16.5" y="14" width="3.5" height="6" rx="1" /><rect x="16.5" y="10" width="3.5" height="3" rx="1" opacity=".45" /></g>,
  line: <path d="m3 17 5-6 4 3 5-7 4 4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  area: <><path d="M3 18 8 11l4 3 5-7 4 4v8H3z" fill="currentColor" opacity=".35" /><path d="m3 18 5-7 4 3 5-7 4 4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinejoin="round" /></>,
  gantt: <g fill="currentColor"><rect x="3" y="4" width="9" height="3.5" rx="1" /><rect x="8" y="10.25" width="10" height="3.5" rx="1" opacity=".7" /><rect x="13" y="16.5" width="8" height="3.5" rx="1" opacity=".45" /></g>,
  plus: <path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />,
  search: <><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" strokeWidth="2" /><path d="m20 20-3.5-3.5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></>,
  user: <><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" strokeWidth="2" /><path d="M4 21a8 8 0 0 1 16 0" fill="none" stroke="currentColor" strokeWidth="2" /></>,
  layout: <g fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="5" rx="1" /><rect x="14" y="12" width="7" height="9" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /></g>,
  overview: <><path d="M4 9V5h4M20 9V5h-4M4 15v4h4M20 15v4h-4" fill="none" stroke="currentColor" strokeWidth="2" /><rect x="9" y="9" width="6" height="6" rx="1" fill="none" stroke="currentColor" strokeWidth="2" /></>,
  expand: <path d="M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />,
  more: <g fill="currentColor"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></g>,
  close: <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />,
  sliders: <><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /><circle cx="16" cy="6" r="2" fill="none" stroke="currentColor" strokeWidth="2" /><circle cx="10" cy="12" r="2" fill="none" stroke="currentColor" strokeWidth="2" /><circle cx="18" cy="18" r="2" fill="none" stroke="currentColor" strokeWidth="2" /></>,
  calendar: <g fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M3.5 10h17M8 3v4M16 3v4" /></g>,
  link: <path d="M9 15l6-6M11 6l.5-.5a5 5 0 0 1 7 7l-.5.5M13 18l-.5.5a5 5 0 0 1-7-7l.5-.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  edit: <path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  download: <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 11l5 5 5-5M12 4v12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  trash: <path d="M4 7h16M10 11v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  lock: <><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" strokeWidth="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></>,
  alert: <path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  copy: <g fill="none" stroke="currentColor" strokeWidth="2" strokeLinejoin="round"><rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" /></g>,
  chev: <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />,
  up: <path d="m18 15-6-6-6 6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  down: <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  check: <path d="m5 12 5 5 9-10" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />,
  pin: <path d="M12 17v5M9 3h6l-1 6 3 3v2H7v-2l3-3z" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />,
  doc: <><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" /><path d="M14 3v5h5M9 13h6M9 17h4" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" /></>,
};

export const Icon = ({ name, className = 'adb-icon' }) => (
  <svg className={className} viewBox="0 0 24 24" aria-hidden="true" focusable="false">{PATHS[name] || null}</svg>
);

export const Grip = () => (
  <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor" aria-hidden="true">
    {[3, 8, 13].map((y) => <g key={y}><circle cx="2.5" cy={y} r="1.4" /><circle cx="7.5" cy={y} r="1.4" /></g>)}
  </svg>
);
