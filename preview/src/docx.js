// A Word document (.docx), laid out as pages in a frame of its own where nothing in it can run (no scripts,
// no links followed out of it)
import { renderAsync } from 'docx-preview';

window.AskdeskPreviewKinds = window.AskdeskPreviewKinds || {};
window.AskdeskPreviewKinds.docx = async (bytes, mount, ctx) => {
  const frame = await ctx.frame(mount, 'background:#eef0f3;');
  const doc = frame.contentDocument;

  await renderAsync(bytes, doc.body, doc.head, {
    inWrapper: true,
    ignoreLastRenderedPageBreak: true,
    useBase64URL: true,
    renderComments: false,
    experimental: false,
  });
  ctx.tame(doc);
};
