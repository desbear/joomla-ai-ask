// A PDF: its pages drawn as they come into view (PDF.js, its worker from the site). No script in the PDF runs.
import * as pdfjs from 'pdfjs-dist/build/pdf.mjs';

window.AskdeskPreviewKinds = window.AskdeskPreviewKinds || {};
window.AskdeskPreviewKinds.pdf = async (bytes, mount, ctx) => {
  pdfjs.GlobalWorkerOptions.workerSrc = ctx.asset('preview/pdf.worker.js');

  const doc = await pdfjs.getDocument({ data: bytes, isEvalSupported: false, enableXfa: false }).promise;
  const pages = el('div', 'adp-pages');
  const width = () => Math.min(mount.clientWidth - 32, 960);
  const drawn = new Set();

  mount.append(pages);

  const draw = async (n, box) => {
    if (drawn.has(n)) return;
    drawn.add(n);

    const page = await doc.getPage(n);
    const plain = page.getViewport({ scale: 1 });
    const scale = width() / plain.width;
    const view = page.getViewport({ scale: scale * Math.min(2, window.devicePixelRatio || 1) });
    const canvas = document.createElement('canvas');

    canvas.width = Math.floor(view.width);
    canvas.height = Math.floor(view.height);
    canvas.style.width = '100%';
    box.style.aspectRatio = `${plain.width} / ${plain.height}`;
    box.append(canvas);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport: view }).promise;
    box.classList.add('is-drawn');
  };

  const seen = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (entry.isIntersecting) draw(Number(entry.target.dataset.page), entry.target);
  }), { root: mount, rootMargin: '600px 0px' });

  for (let n = 1; n <= doc.numPages; n += 1) {
    const box = el('div', 'adp-page');

    box.dataset.page = String(n);
    box.style.aspectRatio = '1 / 1.414';
    box.setAttribute('aria-label', ctx.label('page', n, doc.numPages));
    pages.append(box);
    seen.observe(box);
  }

  ctx.onClose(() => { seen.disconnect(); doc.destroy(); });
};

function el(tag, name) {
  const node = document.createElement(tag);

  node.className = name;

  return node;
}
