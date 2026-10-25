// A spreadsheet (.xlsx, .xls, .ods, .csv): each sheet a table, a tab for each, in a frame of its own where
// nothing in it can run. A long sheet shows its first rows.
import { read, utils } from 'xlsx';

const ROWS = 2000;

window.AskdeskPreviewKinds = window.AskdeskPreviewKinds || {};
window.AskdeskPreviewKinds.xlsx = async (bytes, mount, ctx) => {
  const book = read(bytes, { type: 'array', sheetRows: ROWS + 1, cellFormula: false, cellHTML: false, cellStyles: false });
  const tabs = document.createElement('div');

  tabs.className = 'adp-tabs';
  tabs.setAttribute('role', 'tablist');
  mount.append(tabs);

  const frame = await ctx.frame(mount, 'background:#fff;', `
    table{border-collapse:collapse;font:13px/1.5 system-ui,-apple-system,"PingFang TC","Microsoft JhengHei",sans-serif;color:#1f2329}
    td,th{border:1px solid #e3e6ea;padding:4px 8px;white-space:nowrap;max-width:420px;overflow:hidden;text-overflow:ellipsis;vertical-align:top}
    tr:first-child td{background:#f6f7f9;font-weight:600;position:sticky;top:0}
    body{margin:0;padding:12px}
    .adp-note{margin:12px 0 0;color:#8a8f98;font-size:12px}
  `);
  const doc = frame.contentDocument;

  const show = (name, tab) => {
    const sheet = book.Sheets[name];
    const range = sheet && sheet['!ref'] ? utils.decode_range(sheet['!ref']) : null;

    // The table SheetJS writes (its cells' words escaped), in the frame where nothing runs
    doc.body.innerHTML = sheet ? utils.sheet_to_html(sheet, { header: '', footer: '' }) : '';

    if (range && range.e.r >= ROWS) {
      const note = doc.createElement('p');

      note.className = 'adp-note';
      note.textContent = ctx.label('truncated', ROWS);
      doc.body.append(note);
    }

    ctx.tame(doc);
    tabs.querySelectorAll('button').forEach((b) => b.setAttribute('aria-selected', String(b === tab)));
  };

  book.SheetNames.forEach((name, i) => {
    const tab = document.createElement('button');

    tab.type = 'button';
    tab.textContent = name;
    tab.setAttribute('role', 'tab');
    tab.addEventListener('click', () => show(name, tab));
    tabs.append(tab);

    if (i === 0) show(name, tab);
  });

  tabs.hidden = book.SheetNames.length < 2;
};
