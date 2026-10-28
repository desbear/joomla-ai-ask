/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * One e-mail of the desk designed (0.6.3): GrapesJS with its newsletter blocks, all of it from this site. Its
 * placeholders are put in where one is writing (or in the subject); what is saved is the e-mail with its styles
 * inline, as mail programs want it, and the design to go on from.
 */
(() => {
  'use strict';

  const source = document.getElementById('askdesk-mail-data');
  const form = document.getElementById('adminForm');

  if (!source || !form || !window.grapesjs) {
    return;
  }

  const data = JSON.parse(source.textContent || '{}');
  const s = data.strings || {};
  const subject = form.querySelector('#askdesk-mail-subject');
  const status = form.querySelector('.askdesk-mail__status');
  const preset = window['grapesjs-preset-newsletter'];
  const newsletter = preset && (preset.default || preset);
  let dirty = false;

  // Taiwan's words for the editor's own (GrapesJS has only Simplified Chinese)
  const zhTW = {
    assetManager: { addButton: '加入圖片', inputPlh: 'https://www.example.com/picture.jpg', modalTitle: '選擇圖片', uploadTitle: '把圖片拖到這裡，或點一下上傳' },
    domComponents: {
      names: {
        '': '區塊', wrapper: '本文', text: '文字', comment: '註解', image: '圖片', video: '影片', label: '標籤', link: '連結', map: '地圖',
        tfoot: '表尾', tbody: '表格內容', thead: '表頭', table: '表格', row: '列', cell: '儲存格',
      },
    },
    deviceManager: { device: '裝置', devices: { desktop: '電腦', tablet: '平板', mobileLandscape: '手機橫向', mobilePortrait: '手機直向' } },
    panels: {
      buttons: {
        titles: {
          preview: '預覽', fullscreen: '全螢幕', 'sw-visibility': '顯示元件框線', 'export-template': '檢視原始碼',
          'open-sm': '樣式', 'open-tm': '設定', 'open-layers': '圖層', 'open-blocks': '區塊',
        },
      },
    },
    selectorManager: { label: '類別', selected: '已選', emptyState: '- 狀態 -', states: { hover: '滑過', active: '點按', 'nth-of-type(2n)': '偶數／奇數' } },
    styleManager: {
      empty: '請先選一個元件，再設定樣式',
      layer: '圖層',
      fileButton: '圖片',
      sectors: { general: '一般', layout: '版面', typography: '文字', decorations: '外觀', extra: '其他', flex: '彈性排版', dimension: '尺寸' },
    },
    traitManager: {
      empty: '請先選一個元件，再調整設定',
      label: '元件設定',
      traits: {
        attributes: {
          id: { placeholder: '例：文字' },
          alt: { placeholder: '例：圖片說明' },
          title: { placeholder: '例：文字' },
          href: { placeholder: '例：https://www.example.com' },
        },
        options: { target: { false: '同一個視窗', _blank: '新視窗' } },
      },
    },
  };

  const labels = {
    sect100: s.blockSect100, sect50: s.blockSect50, sect30: s.blockSect30, sect37: s.blockSect37, button: s.blockButton,
    divider: s.blockDivider, text: s.blockText, 'text-sect': s.blockTextSect, image: s.blockImage, quote: s.blockQuote,
    link: s.blockLink, 'link-block': s.blockLinkBlock, 'grid-items': s.blockGridItems, 'list-items': s.blockListItems,
  };
  const chinese = String(data.locale || '').startsWith('zh');
  const plugins = newsletter ? [(editor) => newsletter(editor, {
    block: (id) => (labels[id] ? { label: labels[id] } : {}),
    modalTitleImport: s.importTitle,
    modalBtnImport: s.importButton,
    modalTitleExport: s.exportTitle,
    textCleanCanvas: s.clearConfirm,
  })] : [];
  const start = data.design ? { projectData: data.design } : { components: data.html || data.starter.html };

  const editor = window.grapesjs.init({
    container: '#askdesk-mail-editor',
    // (GrapesJS writes it on the container itself, over any style sheet's)
    height: 'max(560px, calc(100dvh - 300px))',
    fromElement: false,
    storageManager: false,
    // The editor's icons from this site (not a CDN)
    cssIcons: data.urls.icons,
    i18n: chinese ? { locale: 'zh-tw', localeFallback: 'en', detectLocale: false, messages: { 'zh-tw': zhTW } } : { locale: 'en', detectLocale: false },
    assetManager: {
      assets: data.pictures || [],
      upload: data.urls.upload,
      uploadName: 'files',
      multiUpload: true,
      autoAdd: true,
      credentials: 'same-origin',
      params: { [data.token]: '1' },
    },
    plugins,
    ...start,
  });

  // The blocks' pictures are a placeholder of this site's (theirs come from another)
  editor.on('load', () => {
    editor.Blocks.getAll().forEach((block) => {
      const content = block.get('content');

      if (typeof content === 'string' && content.includes('via.placeholder.com')) {
        block.set('content', content.replace(/https?:\/\/via\.placeholder\.com\/[^"'\s)]*/g, data.placeholder));
      }
    });
    dirty = false;
  });

  // ---------- Placeholders ----------

  let lastField = null;

  subject.addEventListener('focus', () => { lastField = subject; });
  editor.on('rte:enable', () => { lastField = 'rte'; });
  editor.on('component:selected', () => { if (lastField === subject) lastField = null; });

  const say = (text, kind = 'info') => {
    status.hidden = !text;
    status.textContent = text || '';
    status.className = `askdesk-mail__status alert alert-${kind}`;
  };

  const insert = (token) => {
    const rte = editor.RichTextEditor.globalRte;

    if (lastField === 'rte' && editor.getEditing() && rte) {
      rte.insertHTML(token);
      dirty = true;
      return;
    }

    if (lastField === subject) {
      const at = subject.selectionStart ?? subject.value.length;
      const end = subject.selectionEnd ?? at;

      subject.value = subject.value.slice(0, at) + token + subject.value.slice(end);
      subject.focus();
      subject.setSelectionRange(at + token.length, at + token.length);
      dirty = true;
      return;
    }

    // Nowhere being written: kept to be pasted
    navigator.clipboard?.writeText(token).catch(() => {});
    say((s.varHint || '%s').replace('%s', token));
  };

  form.querySelectorAll('[data-mail-var]').forEach((button) => {
    // Put in as it is pressed: the editor stops writing in a text once something outside it is clicked
    button.addEventListener('mousedown', (event) => {
      event.preventDefault();
      insert(button.dataset.mailVar);
    });
    // From the keyboard
    button.addEventListener('click', (event) => {
      if (event.detail === 0) {
        insert(button.dataset.mailVar);
      }
    });
  });

  // ---------- What is saved ----------

  editor.on('update', () => { dirty = true; });
  subject.addEventListener('input', () => { dirty = true; });
  window.addEventListener('beforeunload', (event) => {
    if (dirty) {
      event.preventDefault();
      event.returnValue = s.leave || '';
    }
  });

  /** The words being written put in first, then the e-mail with its styles inline */
  const finished = async () => {
    const editing = editor.getEditing();

    if (editing && editing.getView && editing.getView()?.disableEditing) {
      await editing.getView().disableEditing();
    }

    return newsletter ? tableWidths(editor.runCommand('gjs-get-inlined-html')) : `${editor.getHtml()}<style>${editor.getCss()}</style>`;
  };

  /**
   * A table's width attribute as it is written (600 for Outlook, its style letting it be narrower on a phone):
   * putting the styles inline writes it over from the style's width (100%)
   */
  const tableWidths = (html) => {
    const widths = {};

    editor.getWrapper().find('table').forEach((table) => {
      const attributes = table.getAttributes();

      if (attributes.width && attributes.id) {
        widths[attributes.id] = String(attributes.width);
      }
    });

    if (!Object.keys(widths).length) {
      return html;
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');

    doc.querySelectorAll('table[id]').forEach((table) => {
      if (widths[table.id]) {
        table.setAttribute('width', widths[table.id]);
      }
    });

    return doc.body.outerHTML;
  };

  const blank = (html) => !html.replace(/<img\b/gi, 'x').replace(/<[^>]+>|&nbsp;|\s/g, '');

  const submit = Joomla.submitbutton;

  Joomla.submitbutton = async (task) => {
    if (task !== 'mails.apply' && task !== 'mails.save') {
      dirty = false;
      submit ? submit(task) : Joomla.submitform(task, form);
      return;
    }

    const html = await finished();

    if (!subject.value.trim() || blank(html)) {
      say(s.empty, 'warning');
      return;
    }

    form.elements.html.value = html;
    form.elements.design.value = JSON.stringify(editor.getProjectData());
    dirty = false;
    Joomla.submitform(task, form);
  };

  // ---------- Tried, and started again ----------

  form.querySelector('[data-mail-test]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const body = new FormData();

    body.append('subject', subject.value);
    body.append('html', await finished());
    body.append(data.token, '1');
    button.disabled = true;
    say(s.testSent);

    try {
      const response = await fetch(data.urls.test, { method: 'POST', body, credentials: 'same-origin' });
      const answer = await response.json();

      say(answer.message || s.testFailed, answer.ok ? 'success' : 'danger');
    } catch {
      say(s.testFailed, 'danger');
    } finally {
      button.disabled = false;
    }
  });

  form.querySelector('[data-mail-restore]')?.addEventListener('click', () => {
    if (!window.confirm(s.restoreConfirm)) {
      return;
    }

    editor.setComponents(data.starter.html);
    editor.setStyle('');
    subject.value = data.starter.subject;
    dirty = true;
  });
})();
