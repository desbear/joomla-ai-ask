// The viewer of documents (PDF, Word, Excel) built into com_askdesk/media: a small viewer, and one script per
// kind of document, each fetched only when a document of that kind is opened. Nothing is fetched from
// anywhere but the site itself: the libraries are in these scripts, the document is read in the browser.
import { build } from 'esbuild';
import { copyFileSync, mkdirSync } from 'node:fs';

const out = '../src/com_askdesk/media/js';
const common = { bundle: true, minify: true, format: 'iife', target: 'es2020', legalComments: 'none', logLevel: 'warning' };

mkdirSync(`${out}/preview`, { recursive: true });
await build({ ...common, entryPoints: ['src/viewer.js'], outfile: `${out}/preview.js` });
await build({ ...common, entryPoints: ['src/pdf.js'], outfile: `${out}/preview/pdf.js`, external: ['canvas', 'path2d', 'fs', 'http', 'https', 'url', 'zlib'], define: { 'import.meta.url': '""' } });
await build({ ...common, entryPoints: ['src/docx.js'], outfile: `${out}/preview/docx.js` });
await build({ ...common, entryPoints: ['src/xlsx.js'], outfile: `${out}/preview/xlsx.js` });

// PDF.js reads in a worker of its own, served as a script from the site (.js: a .mjs may be refused as such)
copyFileSync('node_modules/pdfjs-dist/build/pdf.worker.min.mjs', `${out}/preview/pdf.worker.js`);
