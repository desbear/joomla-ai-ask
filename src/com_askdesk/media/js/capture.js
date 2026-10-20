/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

// Pictures and videos of the page for a chat with a person (0.1.0): a screenshot of this tab, or a
// picture of one's own, marked up (boxes, arrows, words; parts pixelated so they cannot be read)
// with Painterro before it is sent; or a short recording of this tab, watched before it is sent.
// What is hidden never leaves the browser: only the finished picture is. Loaded by the chat
// window when first needed; it gives back a file to send, or nothing.
((window, document) => {
  'use strict';

  if (window.McpAskCapture) {
    return;
  }

  const here = (document.currentScript && document.currentScript.src) || '';
  const PAINTERRO = new URL('../vendor/painterro/painterro.min.js', here || window.location.href).href;

  /** Pictures sent are at most this wide or high, and 1 MB */
  const MAX_SIDE = 1920;
  const MAX_IMAGE = 1048576;

  /** Recordings: this long at most, and under the 10 MB of a file */
  const MAX_SECONDS = 60;
  const MAX_VIDEO = 9.5 * 1048576;

  const media = navigator.mediaDevices;
  const narrow = window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(min-width: 900px)').matches;
  const can = {
    screen: Boolean(media && media.getDisplayMedia) && !narrow,
    record: Boolean(media && media.getDisplayMedia && window.MediaRecorder) && !narrow,
  };

  const el = (tag, className, text) => {
    const node = document.createElement(tag);

    if (className) {
      node.className = className;
    }

    if (text !== undefined) {
      node.textContent = text;
    }

    return node;
  };

  const pause = (ms) => new Promise((resolve) => { window.setTimeout(resolve, ms); });
  const frames = () => new Promise((resolve) => { requestAnimationFrame(() => requestAnimationFrame(resolve)); });
  // The local date and time, for the names of files: 20261020-0915
  const stamp = () => {
    const now = new Date();
    const two = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}${two(now.getMonth() + 1)}${two(now.getDate())}-${two(now.getHours())}${two(now.getMinutes())}`;
  };

  let painterro = null;

  const loadPainterro = () => {
    if (window.Painterro) {
      return Promise.resolve(window.Painterro);
    }

    painterro = painterro || new Promise((resolve, reject) => {
      const script = el('script');

      script.src = PAINTERRO;
      script.onload = () => (window.Painterro ? resolve(window.Painterro) : reject(new Error('Painterro')));
      script.onerror = () => {
        painterro = null;
        reject(new Error('Painterro'));
      };
      document.head.append(script);
    });

    return painterro;
  };

  // A layer over everything (the top layer, above the chat window's own)
  const layer = (className) => {
    const box = el('dialog', `mcp-ask-capture ${className}`);

    box.addEventListener('cancel', (event) => event.preventDefault());
    document.body.append(box);
    box.showModal();

    return box;
  };

  // Painterro's words in Chinese, which it does not have; Japanese and others it has
  const ZH = {
    lineColor: '線', lineColorFull: '線條顏色', fillColor: '填', fillColorFull: '填色', alpha: '透', alphaFull: '透明度',
    lineWidth: '粗', lineWidthFull: '線條粗細', arrowLength: '箭', arrowLengthFull: '箭頭大小', eraserWidth: '粗', eraserWidthFull: '橡皮擦大小',
    textColor: '色', textColorFull: '文字顏色', fontSize: '字', fontSizeFull: '字級', fontStrokeSize: '框', fontStrokeSizeFull: '外框粗細',
    fontStyle: '樣式', fontStyleFull: '字體樣式', fontName: '字體', fontNameFull: '字體', textStrokeColor: '框色', textStrokeColorFull: '文字外框顏色',
    apply: '套用', cancel: '取消', close: '關閉', clear: '清除', width: '寬', height: '高', keepRatio: '保持比例', fillPageWith: '以背景色填滿',
    pixelSize: '格', pixelSizeFull: '馬賽克大小', resizeScale: '縮放', resizeResize: '調整大小', backgroundColor: '背景色',
    pixelizePixelSize: '馬賽克大小', language: '語言', wrongPixelSizeValue: '馬賽克大小不正確',
    tools: {
      crop: '裁切', pixelize: '馬賽克（遮住看不到）', rect: '畫框', ellipse: '畫圓', line: '畫線', arrow: '箭頭', rotate: '旋轉', save: '完成並傳送',
      load: '載入圖片', text: '文字', brush: '畫筆', resize: '調整大小', open: '開啟圖片', select: '選取範圍（再按裁切或馬賽克）', close: '取消', eraser: '橡皮擦', filters: '濾鏡', clear: '清除',
      settings: '設定', undo: '復原', redo: '重做',
    },
    pasteOptions: { fit: '取代全部', extend_down: '往下延伸', extend_right: '往右延伸', extend: '延伸', over: '貼在上面', how_to_paste: '要怎麼貼上？' },
  };
  const CN = {
    ...ZH,
    lineColor: '线', lineColorFull: '线条颜色', fillColorFull: '填色', alphaFull: '透明度', lineWidthFull: '线条粗细', arrowLength: '箭', arrowLengthFull: '箭头大小',
    eraserWidthFull: '橡皮擦大小', textColorFull: '文字颜色', fontSizeFull: '字号', fontStrokeSizeFull: '描边粗细', fontStyle: '样式', fontStyleFull: '字体样式',
    fontName: '字体', fontNameFull: '字体', textStrokeColor: '描边', textStrokeColorFull: '文字描边颜色', apply: '应用', close: '关闭', width: '宽', keepRatio: '保持比例',
    fillPageWith: '用背景色填满', pixelSizeFull: '马赛克大小', resizeResize: '调整大小', backgroundColor: '背景色', pixelizePixelSize: '马赛克大小', language: '语言',
    wrongPixelSizeValue: '马赛克大小不正确',
    tools: {
      ...ZH.tools, crop: '裁剪', pixelize: '马赛克（遮住看不到）', rect: '画框', ellipse: '画圆', line: '画线', arrow: '箭头', rotate: '旋转', save: '完成并发送',
      load: '载入图片', brush: '画笔', resize: '调整大小', open: '打开图片', select: '选择范围（再按裁剪或马赛克）', filters: '滤镜', settings: '设置', undo: '撤销', redo: '重做',
    },
    pasteOptions: { fit: '替换全部', extend_down: '向下扩展', extend_right: '向右扩展', extend: '扩展', over: '贴在上面', how_to_paste: '要怎么粘贴？' },
  };

  const painterroLanguage = (tag) => {
    const lower = String(tag || document.documentElement.lang || 'en').toLowerCase();

    if (lower === 'zh-cn' || lower === 'zh-hans' || lower === 'zh-sg') {
      return { translation: { name: 'zh-CN', strings: CN } };
    }

    if (lower.startsWith('zh')) {
      return { translation: { name: 'zh-TW', strings: ZH } };
    }

    const short = lower.split('-')[0];
    const known = ['ca', 'de', 'en', 'es', 'fa', 'fr', 'ja', 'nl', 'pl', 'ru'];

    return { language: known.includes(short) ? short : (lower === 'pt-br' ? 'pt-BR' : (short === 'pt' ? 'pt-PT' : 'en')) };
  };

  // ---------- Pictures ----------

  const canvasOf = (source, width, height) => {
    const scale = Math.min(1, MAX_SIDE / Math.max(width, height));
    const canvas = el('canvas');

    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);

    return canvas;
  };

  const toBlob = (canvas, type, quality) => new Promise((resolve) => { canvas.toBlob(resolve, type, quality); });

  // As WebP (JPEG where the browser cannot write WebP), smaller until under 1 MB
  async function finish(canvas) {
    let current = canvas;

    for (let round = 0; round < 6; round += 1) {
      for (const quality of [0.86, 0.72, 0.58]) {
        let blob = await toBlob(current, 'image/webp', quality);

        if (!blob || blob.type !== 'image/webp') {
          blob = await toBlob(current, 'image/jpeg', quality);
        }

        if (blob && blob.size <= MAX_IMAGE) {
          const extension = blob.type === 'image/webp' ? 'webp' : 'jpg';

          return new File([blob], `screenshot-${stamp()}.${extension}`, { type: blob.type });
        }
      }

      current = canvasOf(current, current.width * 0.8, current.height * 0.8);
    }

    throw new Error('large');
  }

  // A picture marked up in Painterro, in a layer of its own: the finished picture, or nothing
  async function annotate(canvas, options) {
    const Painterro = await loadPainterro();
    const box = layer('mcp-ask-capture--editor');
    const holder = el('div', 'mcp-ask-capture__editor');

    holder.id = `mcp-ask-capture-${Date.now()}`;
    box.append(holder);

    return new Promise((resolve) => {
      let done = false;
      const end = (file) => {
        if (done) {
          return;
        }

        done = true;
        window.setTimeout(() => box.remove(), 0);
        resolve(file);
      };

      const editor = Painterro({
        id: holder.id,
        ...painterroLanguage(options.language),
        // Select first: crop and pixelate work on what is selected
        defaultTool: 'select',
        hiddenTools: ['open', 'bucket', 'resize', 'rotate', 'zoomin', 'zoomout', 'ellipse', 'eraser', 'filters', 'settings', 'clear'],
        initText: options.texts && options.texts.markHint ? options.texts.markHint : null,
        initTextColor: '#fff',
        initTextStyle: "20px system-ui, -apple-system, 'PingFang TC', sans-serif",
        activeColor: '#ff3b30',
        activeFillColorAlpha: 0,
        defaultLineWidth: 4,
        defaultArrowLength: 20,
        defaultFontSize: 24,
        pixelizePixelSize: '20%',
        pixelizeHideUserInput: true,
        hideByEsc: false,
        how_to_paste_actions: ['replace_all'],
        toolbarPosition: 'bottom',
        buttonSizePx: 36,
        toolbarHeightPx: 56,
        // Dark glass, as the bar floats over the picture (ask.css-like look drawn in capture's own styles)
        colorScheme: {
          main: 'rgba(22, 26, 35, 0.9)', control: 'transparent', controlShadow: 'none', controlContent: '#dfe5ef',
          activeControl: '#0e86fe', activeControlContent: '#fff', hoverControl: 'rgba(255, 255, 255, 0.1)', hoverControlContent: '#fff',
          inputBackground: 'rgba(255, 255, 255, 0.08)', inputText: '#fff', inputBorderColor: 'rgba(255, 255, 255, 0.16)', inputShadow: 'none',
          toolControlNameColor: 'rgba(255, 255, 255, 0.6)', backgroundColor: '#1d2129', dragOverBarColor: '#0e86fe',
        },
        saveHandler: (image, saved) => {
          const picture = new Image();

          picture.onload = async () => {
            try {
              const file = await finish(canvasOf(picture, picture.naturalWidth, picture.naturalHeight));

              saved(true);
              end(file);
              editor.hide();
            } catch (error) {
              saved(false);
              window.alert(options.texts.tooLarge || 'Too large');
            }
          };
          picture.src = image.asDataURL('image/png');
        },
        // Painterro hides itself as it is made: only its own close button ends it without a picture
        onClose: () => end(null),
      });

      editor.show(canvas.toDataURL('image/png'));
      settingsBar(holder, editor, options.language);
    });
  }

  // Width, colours and the size of text always in the bar, as on a Mac: each opens a little menu above
  // it, and those the tool in hand does not use are dimmed (Painterro's own, tool by tool, put away)
  const SWATCHES = ['#ff3b30', '#ff9500', '#ffcc00', '#34c759', '#0a84ff', '#af52de', '#ffffff', '#1d1d1f'];
  const USES = { line: ['width', 'line'], arrow: ['width', 'line'], brush: ['width', 'line'], rect: ['width', 'line', 'fill'], text: ['line', 'font'] };

  function settingsBar(holder, editor, language) {
    const bar = holder.querySelector('.ptro-bar');
    const right = holder.querySelector('.ptro-bar-right');

    if (!bar || !right) {
      return;
    }

    const lower = String(language || document.documentElement.lang || 'en').toLowerCase();
    const zh = lower.startsWith('zh');
    const words = zh ? (/^zh-(cn|hans|sg)/.test(lower) ? CN : ZH) : { lineWidthFull: 'Line width', lineColorFull: 'Line colour', fillColorFull: 'Fill', fontSizeFull: 'Text size' };
    const group = el('div', 'mcp-ask-capture__settings');
    const state = { width: 4, line: '#ff3b30', fill: '', font: 24 };
    const controls = {};
    let openMenu = null;

    const close = () => {
      if (openMenu) {
        openMenu.classList.remove('mcp-ask-capture__control--open');
        openMenu = null;
      }
    };

    const apply = {
      width: (value) => {
        editor.params.defaultLineWidth = value;
        editor.primitiveTool && editor.primitiveTool.setLineWidth && editor.primitiveTool.setLineWidth(value);
      },
      line: (value) => {
        editor.params.activeColor = value;
        editor.colorWidgetState.line = { target: 'line', palleteColor: value, alpha: 1, alphaColor: value };
        editor.textTool && editor.textTool.setFontColor && editor.textTool.setFontColor(value);
      },
      fill: (value) => {
        editor.params.activeFillColor = value || '#000000';
        editor.colorWidgetState.fill = { target: 'fill', palleteColor: value || '#000000', alpha: value ? 1 : 0, alphaColor: value || 'rgba(0, 0, 0, 0)' };
      },
      font: (value) => {
        editor.params.defaultFontSize = value;
        editor.textTool && editor.textTool.setFontSize && editor.textTool.setFontSize(value);
      },
    };

    // One control: a button showing what is chosen, a chevron, and its menu of choices
    const control = (name, title, options, show) => {
      const box = el('div', 'mcp-ask-capture__control');
      const face = el('button', 'mcp-ask-capture__face');
      const menu = el('div', 'mcp-ask-capture__menu');

      face.type = 'button';
      face.title = title;
      face.setAttribute('aria-label', title);
      face.setAttribute('aria-haspopup', 'true');
      face.addEventListener('click', () => {
        const was = openMenu === box;

        close();

        if (!was && !face.disabled) {
          box.classList.add('mcp-ask-capture__control--open');
          openMenu = box;
        }
      });

      options.forEach((value) => {
        const choice = el('button', 'mcp-ask-capture__choice');

        choice.type = 'button';
        choice.dataset.value = String(value);
        choice.append(show(value));
        choice.addEventListener('click', () => {
          state[name] = value;
          apply[name](value);
          paint();
          close();
        });
        menu.append(choice);
      });

      box.append(face, menu);
      controls[name] = { box, face, menu, show };
      group.append(box);
    };

    const dot = (size) => {
      const mark = el('span', 'mcp-ask-capture__width');

      mark.style.setProperty('--size', `${size}px`);

      return mark;
    };

    const swatch = (color) => {
      const mark = el('span', `mcp-ask-capture__swatch${color ? '' : ' mcp-ask-capture__swatch--none'}`);

      mark.style.setProperty('--color', color || 'transparent');

      return mark;
    };

    const letter = (size) => {
      const mark = el('span', 'mcp-ask-capture__font', 'A');

      mark.style.fontSize = `${Math.round(size * 0.6)}px`;

      return mark;
    };

    control('width', words.lineWidthFull, [2, 4, 8], dot);
    control('line', words.lineColorFull, SWATCHES, swatch);
    control('fill', words.fillColorFull, ['', ...SWATCHES], swatch);
    control('font', words.fontSizeFull, [16, 24, 36], letter);

    // What is chosen, on each button; the menus mark it; and those the tool does not use, dimmed
    const paint = () => {
      const tool = editor.activeTool && editor.activeTool.name;
      const uses = USES[tool] || [];

      Object.entries(controls).forEach(([name, item]) => {
        item.face.replaceChildren(item.show(state[name]), el('i', 'mcp-ask-capture__chevron'));
        item.face.disabled = !uses.includes(name);
        item.box.classList.toggle('mcp-ask-capture__control--off', item.face.disabled);
        item.menu.querySelectorAll('.mcp-ask-capture__choice').forEach((choice) => {
          choice.classList.toggle('mcp-ask-capture__choice--on', choice.dataset.value === String(state[name]));
        });
      });

      if (openMenu && openMenu.querySelector('.mcp-ask-capture__face').disabled) {
        close();
      }
    };

    Object.entries(state).forEach(([name, value]) => apply[name](value));
    right.before(group);
    paint();

    // Another tool taken (by the bar, or a key): the controls follow it
    bar.addEventListener('click', () => window.setTimeout(paint, 0));
    holder.addEventListener('keyup', () => window.setTimeout(paint, 0));
    holder.addEventListener('pointerdown', (event) => {
      if (openMenu && !openMenu.contains(event.target)) {
        close();
      }
    }, true);
  }

  // This tab as it is, the chat window put away for the moment
  // A bar in the page while the visitor gets ready: take the picture, or leave it. True when taken;
  // false when left, or when the sharing stops (stopped from the browser's bar, the page left)
  const ready = (stream, texts) => new Promise((resolve) => {
    const bar = el('div', 'mcp-ask-capture-bar mcp-ask-capture-bar--shot');
    const hint = el('span', 'mcp-ask-capture-bar__label', texts.shotHint || '');
    const take = el('button', 'mcp-ask-capture-bar__take', texts.shotNow || 'Take');
    const leave = el('button', 'mcp-ask-capture-bar__leave', texts.cancel || 'Cancel');
    const end = (taken) => {
      bar.remove();
      resolve(taken);
    };

    take.type = 'button';
    leave.type = 'button';
    bar.setAttribute('role', 'dialog');
    bar.append(hint, leave, take);
    take.addEventListener('click', () => end(true));
    leave.addEventListener('click', () => end(false));
    stream.getVideoTracks().forEach((track) => track.addEventListener('ended', () => end(false)));
    document.body.append(bar);
    take.focus();
  });

  // A picture of what the visitor chooses to share: this tab or another, a window; taken when they
  // say so, after scrolling or going to another tab first if they like. The chat window is put away
  // meanwhile, the bar too at the moment it is taken
  async function screenshot(options) {
    let stream;

    try {
      stream = await media.getDisplayMedia({
        video: { displaySurface: 'browser', frameRate: 5 },
        audio: false,
        preferCurrentTab: false,
        selfBrowserSurface: 'include',
        surfaceSwitching: 'include',
      });
    } catch (error) {
      // Not allowed, or not chosen
      return null;
    }

    // Put aside, only the bar to take the picture with staying (in a frame, the page around does that)
    await options.hide('bar');

    try {
      const video = el('video');

      video.muted = true;
      video.playsInline = true;
      video.srcObject = stream;
      await video.play();

      if (!(await ready(stream, options.texts || {}))) {
        return null;
      }

      // The bar gone too, and what is shared painted without it
      if (options.conceal) {
        await options.conceal();
      }

      await frames();
      await pause(350);

      const canvas = canvasOf(video, video.videoWidth, video.videoHeight);

      stream.getTracks().forEach((track) => track.stop());

      // The editor needs the whole window again
      if (options.reveal) {
        await options.reveal();
      }

      return await annotate(canvas, options);
    } finally {
      stream.getTracks().forEach((track) => track.stop());
      options.show();
    }
  }

  // A picture of one's own (a phone's screenshot), marked up the same way
  function picture(options) {
    return new Promise((resolve) => {
      const input = el('input');

      input.type = 'file';
      input.accept = 'image/*';
      input.addEventListener('change', async () => {
        const file = input.files[0];

        if (!file) {
          resolve(null);

          return;
        }

        try {
          const bitmap = await createImageBitmap(file);

          resolve(await annotate(canvasOf(bitmap, bitmap.width, bitmap.height), options));
        } catch (error) {
          resolve(null);
        }
      });
      input.addEventListener('cancel', () => resolve(null));
      input.click();
    });
  }

  // ---------- Recordings ----------

  const VIDEO_TYPES = ['video/mp4;codecs=avc1', 'video/mp4', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];

  const time = (seconds) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;

  // This tab recorded while the visitor shows what happens (60 seconds at most), then watched
  // before it is sent: sent, recorded again, or left
  async function record(options) {
    const texts = options.texts || {};
    // From the recorder's own window (recorder.html): the tab the visitor chooses, which may change
    // page as they show what happens; the window itself is not offered
    const elsewhere = Boolean(options.elsewhere);

    for (;;) {
      let stream;

      try {
        stream = await media.getDisplayMedia({
          video: { displaySurface: 'browser', width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 15, max: 24 } },
          audio: false,
          preferCurrentTab: !elsewhere,
          selfBrowserSurface: elsewhere ? 'exclude' : 'include',
          surfaceSwitching: elsewhere ? 'include' : 'exclude',
        });
      } catch (error) {
        return null;
      }

      await options.hide('record');

      const type = VIDEO_TYPES.find((candidate) => MediaRecorder.isTypeSupported(candidate)) || '';
      const recorder = new MediaRecorder(stream, { ...(type ? { mimeType: type } : {}), videoBitsPerSecond: 1000000 });
      const parts = [];
      let size = 0;

      // The bar while recording: in the page, outside the chat window, to stop with
      const bar = el('div', 'mcp-ask-capture-bar');
      const dot = el('span', 'mcp-ask-capture-bar__dot');
      const clock = el('span', 'mcp-ask-capture-bar__time', `0:00 / ${time(MAX_SECONDS)}`);
      const stop = el('button', 'mcp-ask-capture-bar__stop', texts.stop || 'Stop');

      stop.type = 'button';
      bar.setAttribute('role', 'status');
      bar.append(dot, el('span', 'mcp-ask-capture-bar__label', texts.recording || 'Recording'), clock, stop);
      document.body.append(bar);

      const finished = new Promise((resolve) => { recorder.addEventListener('stop', resolve, { once: true }); });
      const started = Date.now();
      const halt = () => {
        if (recorder.state !== 'inactive') {
          recorder.stop();
        }
      };
      const tick = window.setInterval(() => {
        const seconds = (Date.now() - started) / 1000;

        clock.textContent = `${time(seconds)} / ${time(MAX_SECONDS)}`;

        if (seconds >= MAX_SECONDS) {
          halt();
        }
      }, 250);

      recorder.addEventListener('dataavailable', (event) => {
        if (event.data && event.data.size) {
          parts.push(event.data);
          size += event.data.size;

          // Near the size of a file: it ends here
          if (size >= MAX_VIDEO) {
            halt();
          }
        }
      });
      stop.addEventListener('click', halt);
      // Stopped from the browser's own bar
      stream.getVideoTracks().forEach((track) => track.addEventListener('ended', halt));
      // Recording in this page: leaving it would end the recording, so the browser asks first
      const stay = (event) => {
        event.preventDefault();
        event.returnValue = '';
      };

      if (!elsewhere) {
        window.addEventListener('beforeunload', stay);
      }

      recorder.start(1000);

      await finished;
      window.removeEventListener('beforeunload', stay);
      window.clearInterval(tick);
      stream.getTracks().forEach((track) => track.stop());
      bar.remove();
      options.show();

      const kind = (recorder.mimeType || type || 'video/webm').split(';')[0];
      const blob = new Blob(parts, { type: kind });

      if (!blob.size) {
        return null;
      }

      const choice = await watch(blob, texts, size >= MAX_VIDEO);

      if (choice === 'send') {
        return new File([blob], `recording-${stamp()}.${kind === 'video/mp4' ? 'mp4' : 'webm'}`, { type: kind });
      }

      if (choice !== 'again') {
        return null;
      }
    }
  }

  // The recording, to watch before it goes
  function watch(blob, texts, cut) {
    const box = layer('mcp-ask-capture--watch');
    const card = el('div', 'mcp-ask-capture__card');
    const video = el('video', 'mcp-ask-capture__video');
    const url = URL.createObjectURL(blob);
    const actions = el('div', 'mcp-ask-capture__actions');
    const size = blob.size >= 1048576 ? `${(blob.size / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(blob.size / 1024))} KB`;

    video.src = url;
    video.controls = true;
    video.playsInline = true;
    card.append(el('h2', 'mcp-ask-capture__title', texts.watchTitle || 'Send this recording?'), video, el('p', 'mcp-ask-capture__note', `${size}${cut ? ` · ${texts.cut || ''}` : ''}`));

    return new Promise((resolve) => {
      const choose = (choice) => {
        URL.revokeObjectURL(url);
        box.remove();
        resolve(choice);
      };

      [['again', texts.again || 'Record again', ''], ['cancel', texts.cancel || 'Cancel', ''], ['send', texts.send || 'Send', 'mcp-ask-capture__primary']].forEach(([choice, label, extra]) => {
        const button = el('button', `mcp-ask-capture__button ${extra}`, label);

        button.type = 'button';
        button.addEventListener('click', () => choose(choice));
        actions.append(button);
      });
      card.append(actions);
      box.append(card);
      box.addEventListener('cancel', () => choose('cancel'));
    });
  }

  // ---------- Its looks ----------

  const style = el('style');

  style.textContent = `
    .mcp-ask-capture { position: fixed; inset: 0; inline-size: 100vw; block-size: 100dvh; max-inline-size: none; max-block-size: none; margin: 0; padding: 0; border: 0; background: transparent; color: #232332; font: 15px/1.5 system-ui, -apple-system, "PingFang TC", "Noto Sans TC", sans-serif; }
    .mcp-ask-capture::backdrop { background: rgba(20, 24, 33, 0.55); }
    .mcp-ask-capture__editor { position: absolute; inset: 0; background: #1d2129; }
    .mcp-ask-capture__editor .ptro-wrapper { bottom: 0 !important; }
    .mcp-ask-capture__editor .ptro-bar { z-index: 50 !important; position: absolute !important; inset: auto auto max(28px, calc(env(safe-area-inset-bottom) + 16px)) 50% !important; inline-size: max-content !important; max-inline-size: calc(100vw - 24px) !important; block-size: auto !important; padding: 6px !important; overflow: visible !important; border-radius: 20px; background: rgba(22, 26, 35, 0.88) !important; backdrop-filter: blur(14px) saturate(1.4); box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 18px 48px rgba(0, 0, 0, 0.45); transform: translateX(-50%); animation: mcp-ask-capture-rise 0.32s cubic-bezier(0.2, 0.9, 0.3, 1.2); }
    .mcp-ask-capture__editor .ptro-bar > div { display: flex; flex-wrap: nowrap; align-items: center; gap: 2px; max-inline-size: calc(100vw - 36px); padding-block: 2px; overflow: visible; }
    @media (max-width: 760px) { .mcp-ask-capture__editor .ptro-bar > div { flex-wrap: wrap; justify-content: center; row-gap: 6px; } }
    .mcp-ask-capture__editor .ptro-tool-controls > * { flex: none; }
    .mcp-ask-capture__editor .ptro-icon-btn { inline-size: 36px !important; block-size: 36px !important; margin: 0 !important; border-radius: 12px !important; transition: background-color 0.15s, transform 0.15s; }
    .mcp-ask-capture__editor .ptro-icon-btn:hover { transform: translateY(-1px); }
    .mcp-ask-capture__editor .ptro-icon-btn:focus-visible { outline: none; box-shadow: 0 0 0 2px #a6d2ff; }
    .mcp-ask-capture__editor .ptro-icon { display: inline-block; inline-size: 20px; block-size: 20px; background: currentColor; font-size: 0 !important; -webkit-mask: center / contain no-repeat; mask: center / contain no-repeat; vertical-align: middle; }
    .mcp-ask-capture__editor .ptro-icon::before { content: none !important; }
    .mcp-ask-capture__editor .ptro-icon-select { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 7V5a1 1 0 0 1 1-1h2M4 13v-2M4 17v2a1 1 0 0 0 1 1h2M10 4h4M10 20h4M17 4h2a1 1 0 0 1 1 1v2M20 11v2M17 20h2a1 1 0 0 0 1-1v-2'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 7V5a1 1 0 0 1 1-1h2M4 13v-2M4 17v2a1 1 0 0 0 1 1h2M10 4h4M10 20h4M17 4h2a1 1 0 0 1 1 1v2M20 11v2M17 20h2a1 1 0 0 0 1-1v-2'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-crop { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 3v13a1 1 0 0 0 1 1h12M3 8h13a1 1 0 0 1 1 1v12'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M8 3v13a1 1 0 0 0 1 1h12M3 8h13a1 1 0 0 1 1 1v12'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-pixelize { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 4h4v4H4zM12 4h4v4h-4zM8 8h4v4H8zM16 8h4v4h-4zM4 12h4v4H4zM12 12h4v4h-4zM8 16h4v4H8zM16 16h4v4h-4z'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 4h4v4H4zM12 4h4v4h-4zM8 8h4v4H8zM16 8h4v4h-4zM4 12h4v4H4zM12 12h4v4h-4zM8 16h4v4H8zM16 16h4v4h-4z'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-line { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 19L19 5'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 19L19 5'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-arrow { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 19L19 5M10 5h9v9'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 19L19 5M10 5h9v9'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-rect { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-brush { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 21v-4a4 4 0 1 1 4 4H3M21 3a16 16 0 0 0-12.8 10.2M21 3a16 16 0 0 1-10.2 12.8M10.6 9a9 9 0 0 1 4.4 4.4'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 21v-4a4 4 0 1 1 4 4H3M21 3a16 16 0 0 0-12.8 10.2M21 3a16 16 0 0 1-10.2 12.8M10.6 9a9 9 0 0 1 4.4 4.4'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-text { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 6V5h14v1M12 5v14M9 19h6'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 6V5h14v1M12 5v14M9 19h6'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-undo { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-redo { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M15 14l5-5-5-5M20 9H9.5a5.5 5.5 0 0 0 0 11H13'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M15 14l5-5-5-5M20 9H9.5a5.5 5.5 0 0 0 0 11H13'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-save { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12l5 5L20 7'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12l5 5L20 7'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-icon-close { -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 6l12 12M18 6L6 18'/%3E%3C/svg%3E"); mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 6l12 12M18 6L6 18'/%3E%3C/svg%3E"); }
    .mcp-ask-capture__editor .ptro-tool-controls { display: none !important; }
    .mcp-ask-capture__settings { display: inline-flex; align-items: center; gap: 2px; margin-inline-start: 6px; padding-inline-start: 8px; border-inline-start: 1px solid rgba(255, 255, 255, 0.12); }
    .mcp-ask-capture__control { position: relative; }
    .mcp-ask-capture__face { display: inline-flex; align-items: center; justify-content: center; gap: 4px; block-size: 36px; min-inline-size: 44px; padding: 0 8px; border: 0; border-radius: 12px; background: none; color: #dfe5ef; cursor: pointer; transition: background-color 0.15s, opacity 0.2s; }
    .mcp-ask-capture__face:hover:not(:disabled), .mcp-ask-capture__control--open .mcp-ask-capture__face { background: rgba(255, 255, 255, 0.1); color: #fff; }
    .mcp-ask-capture__face:focus-visible { outline: none; box-shadow: 0 0 0 2px #a6d2ff; }
    .mcp-ask-capture__face:disabled { opacity: 0.32; cursor: default; }
    .mcp-ask-capture__chevron { inline-size: 6px; block-size: 6px; border-block-end: 1.5px solid currentColor; border-inline-end: 1.5px solid currentColor; opacity: 0.7; transform: translateY(-2px) rotate(45deg); }
    .mcp-ask-capture__width { display: block; inline-size: 18px; block-size: var(--size); border-radius: 99px; background: currentColor; }
    .mcp-ask-capture__swatch { display: block; inline-size: 18px; block-size: 18px; border-radius: 6px; background: var(--color); box-shadow: inset 0 0 0 1.5px rgba(255, 255, 255, 0.55); }
    .mcp-ask-capture__swatch--none { background: linear-gradient(135deg, transparent 45%, #ff3b30 45%, #ff3b30 55%, transparent 55%); }
    .mcp-ask-capture__font { display: block; min-inline-size: 18px; font: 600 14px/1 system-ui, -apple-system, sans-serif; text-align: center; }
    .mcp-ask-capture__menu { position: absolute; inset-block-end: calc(100% + 10px); inset-inline-start: 50%; z-index: 5; display: grid; grid-template-columns: repeat(4, 34px); gap: 4px; padding: 6px; border-radius: 14px; background: rgba(30, 34, 44, 0.96); box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 14px 32px rgba(0, 0, 0, 0.45); opacity: 0; visibility: hidden; transform: translate(-50%, 6px) scale(0.96); transform-origin: bottom center; transition: opacity 0.15s, transform 0.2s cubic-bezier(0.2, 0.9, 0.3, 1.2), visibility 0s 0.2s; }
    .mcp-ask-capture__control--open .mcp-ask-capture__menu { opacity: 1; visibility: visible; transform: translate(-50%, 0); transition: opacity 0.15s, transform 0.2s cubic-bezier(0.2, 0.9, 0.3, 1.2); }
    .mcp-ask-capture__choice { display: grid; place-items: center; inline-size: 34px; block-size: 34px; padding: 0; border: 0; border-radius: 10px; background: none; color: #dfe5ef; cursor: pointer; }
    .mcp-ask-capture__choice:hover { background: rgba(255, 255, 255, 0.1); }
    .mcp-ask-capture__choice--on { background: #0e86fe !important; color: #fff; }
    .mcp-ask-capture__control:first-child .mcp-ask-capture__menu, .mcp-ask-capture__control:last-child .mcp-ask-capture__menu { grid-template-columns: repeat(3, 34px); }
    .mcp-ask-capture__editor .ptro-tool-controls:empty { display: none; }
    .mcp-ask-capture__editor .ptro-tool-ctl-name { padding: 0 2px 0 6px !important; background: none !important; color: rgba(255, 255, 255, 0.6) !important; font-size: 12px !important; }
    .mcp-ask-capture__editor .ptro-color-btn { inline-size: 24px !important; block-size: 24px !important; border-radius: 50% !important; }
    .mcp-ask-capture__editor .ptro-input { block-size: 28px !important; border-radius: 8px !important; }
    .mcp-ask-capture__editor .ptro-info { display: none !important; }
    .mcp-ask-capture__editor .ptro-bar-right { position: static !important; display: inline-flex !important; align-items: center; gap: 4px; margin-inline-start: 6px; padding-inline-start: 8px; border-inline-start: 1px solid rgba(255, 255, 255, 0.12); float: none !important; }
    .mcp-ask-capture__editor .ptro-bar-right .ptro-icon-btn:has(.ptro-icon-save) { inline-size: auto !important; display: inline-flex !important; align-items: center; gap: 6px; padding: 0 16px 0 12px !important; border-radius: 18px !important; background: linear-gradient(45deg, #33cfff, #0e86fe) !important; color: #fff !important; box-shadow: 0 6px 14px rgba(14, 134, 254, 0.45); }
    .mcp-ask-capture__editor .ptro-bar-right .ptro-icon-btn:has(.ptro-icon-save)::after { content: attr(title); font: 500 14px/1 system-ui, -apple-system, "PingFang TC", sans-serif; white-space: nowrap; }

    .mcp-ask-capture--watch { display: grid; place-items: center; }
    .mcp-ask-capture--watch[open] { display: grid; }
    .mcp-ask-capture__card { display: grid; gap: 12px; inline-size: min(720px, calc(100vw - 32px)); padding: 20px; border-radius: 20px; background: #fff; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25); animation: mcp-ask-capture-pop 0.32s cubic-bezier(0.2, 0.9, 0.3, 1.2); }
    .mcp-ask-capture__title { margin: 0; font-size: 17px; font-weight: 500; }
    .mcp-ask-capture__video { inline-size: 100%; max-block-size: 60dvh; border-radius: 12px; background: #000; }
    .mcp-ask-capture__note { margin: 0; color: rgba(35, 35, 50, 0.55); font-size: 13px; }
    .mcp-ask-capture__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .mcp-ask-capture__button { min-block-size: 36px; padding: 0 16px; border: 0.5px solid rgba(35, 35, 50, 0.15); border-radius: 18px; background: #fff; color: inherit; font: inherit; cursor: pointer; }
    .mcp-ask-capture__button:hover { border-color: #0e86fe; color: #0e86fe; }
    .mcp-ask-capture__button:focus-visible { outline: none; box-shadow: 0 0 0 2px #a6d2ff; }
    .mcp-ask-capture__primary { border: 0; background: linear-gradient(45deg, #33cfff, #0e86fe); color: #fff; box-shadow: 0 6px 10px rgba(14, 134, 254, 0.3); }
    .mcp-ask-capture__primary:hover { color: #fff; filter: brightness(1.05); }
    .mcp-ask-capture-bar { position: fixed; inset-block-end: 20px; inset-inline-start: 50%; z-index: 2147483647; display: flex; align-items: center; gap: 10px; padding: 8px 8px 8px 16px; border-radius: 24px; background: rgba(20, 24, 33, 0.92); color: #fff; font: 14px/1 system-ui, -apple-system, "PingFang TC", sans-serif; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); transform: translateX(-50%); animation: mcp-ask-capture-rise 0.3s ease-out; }
    .mcp-ask-capture-bar__dot { inline-size: 10px; block-size: 10px; border-radius: 50%; background: #ff3b30; animation: mcp-ask-capture-blink 1s ease-in-out infinite; }
    .mcp-ask-capture-bar__time { font-variant-numeric: tabular-nums; opacity: 0.8; }
    .mcp-ask-capture-bar--shot { gap: 8px; padding: 8px 8px 8px 18px; }
    .mcp-ask-capture-bar--shot .mcp-ask-capture-bar__label { max-inline-size: 46vw; opacity: 0.85; font-size: 13px; line-height: 1.4; white-space: normal; }
    .mcp-ask-capture-bar__take, .mcp-ask-capture-bar__leave { min-block-size: 34px; padding: 0 16px; border: 0; border-radius: 17px; font: inherit; font-weight: 500; cursor: pointer; white-space: nowrap; }
    .mcp-ask-capture-bar__take { background: linear-gradient(45deg, #33cfff, #0e86fe); color: #fff; box-shadow: 0 6px 10px rgba(14, 134, 254, 0.35); }
    .mcp-ask-capture-bar__leave { background: rgba(255, 255, 255, 0.12); color: #fff; }
    .mcp-ask-capture-bar__take:focus-visible, .mcp-ask-capture-bar__leave:focus-visible { outline: none; box-shadow: 0 0 0 2px #a6d2ff; }
    .mcp-ask-capture-bar__stop { min-block-size: 32px; padding: 0 14px; border: 0; border-radius: 16px; background: #ff3b30; color: #fff; font: inherit; font-weight: 500; cursor: pointer; }
    @keyframes mcp-ask-capture-pop { from { opacity: 0; transform: scale(0.94); } }
    @keyframes mcp-ask-capture-rise { from { opacity: 0; transform: translate(-50%, 12px); } }
    @keyframes mcp-ask-capture-blink { 50% { opacity: 0.35; } }
    @media (prefers-reduced-motion: reduce) { .mcp-ask-capture__card, .mcp-ask-capture-bar, .mcp-ask-capture-bar__dot { animation: none; } }
  `;
  document.head.append(style);

  // A picture already in hand (pasted, dropped) marked up the same way
  const markUp = async (blob, options) => {
    const bitmap = await createImageBitmap(blob);

    return annotate(canvasOf(bitmap, bitmap.width, bitmap.height), options);
  };

  window.McpAskCapture = { can, screenshot, picture, record, markUp };
})(window, document);
