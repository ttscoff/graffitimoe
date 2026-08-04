(function () {
  'use strict';

  var MIN = 20;
  var MAX = 1000;

  var body = document.getElementById('body');
  var counter = document.getElementById('char-count');
  var sprayBtns = document.querySelectorAll('.spray-btn');
  var paintToggle = document.getElementById('paint-toggle');
  var paintSurface = document.getElementById('paint-surface');
  var spansInput = document.getElementById('spans');
  var brushPalette = document.getElementById('brush-palette');
  var colorPalette = document.getElementById('color-palette');
  var paintHint = document.getElementById('paint-hint');
  var composeHint = document.getElementById('compose-hint');
  var spraySimple = document.getElementById('spray-simple');
  var sprayPaint = document.getElementById('spray-paint');
  var boldToggle = document.getElementById('bold-toggle');
  var boldToggleLabel = boldToggle
    ? boldToggle.closest('label') && boldToggle.closest('label').querySelector('span')
    : null;
  var form = document.getElementById('compose-form');

  if (!body || !counter || !paintToggle || !paintSurface || !spansInput) return;

  /** @type {string[]} */
  var charColors = [];
  /** @type {boolean[]} */
  var charBolds = [];
  /** @type {Array<{row:number,col:number,newline:boolean}>} */
  var layout = [];
  var paintMode = false;
  var painting = false;
  /** @type {{colors:string[],bolds:boolean[]}|null} */
  var strokeSnapshot = null;
  var strokeStartIndex = -1;

  function selectedBrush() {
    var checked = document.querySelector('input[name="brush"]:checked');
    return checked ? checked.value : 'red';
  }

  function selectedColor() {
    var checked = document.querySelector('input[name="color"]:checked');
    return checked ? checked.value : 'default';
  }

  function brushBold() {
    return !!(boldToggle && boldToggle.checked);
  }

  function updateCount() {
    var n = body.value.length;
    counter.textContent = n + ' / ' + MAX;
    var over = n > MAX;
    var under = n > 0 && n < MIN;
    counter.classList.toggle('is-over', over);
    counter.classList.toggle('is-under', under);
    sprayBtns.forEach(function (btn) {
      btn.disabled = over || n < MIN;
    });
    paintToggle.disabled = n === 0 && !paintMode;
  }

  function charsFromText(text) {
    return Array.from(text);
  }

  function buildLayout(chars) {
    var map = [];
    var row = 0;
    var col = 0;
    for (var i = 0; i < chars.length; i++) {
      if (chars[i] === '\n') {
        map[i] = { row: row, col: col, newline: true };
        row += 1;
        col = 0;
      } else {
        map[i] = { row: row, col: col, newline: false };
        col += 1;
      }
    }
    return map;
  }

  function styleKey(index) {
    return (charColors[index] || 'default') + '\0' + (charBolds[index] ? '1' : '0');
  }

  function buildRuns() {
    var chars = charsFromText(body.value);
    if (chars.length === 0) return null;

    var runs = [];
    var i = 0;
    while (i < chars.length) {
      var c = charColors[i] || 'default';
      var b = !!charBolds[i];
      var t = chars[i];
      var key = styleKey(i);
      var j = i + 1;
      while (j < chars.length && styleKey(j) === key) {
        t += chars[j];
        j++;
      }
      var run = { t: t, c: c };
      if (b) run.b = true;
      runs.push(run);
      i = j;
    }

    if (runs.length <= 1) return null;
    return runs;
  }

  function syncSpansField() {
    if (!paintMode) {
      spansInput.value = '';
      return;
    }
    var runs = buildRuns();
    spansInput.value = runs ? JSON.stringify(runs) : '';
    if (runs && runs[0]) {
      var colorRadio = document.querySelector('input[name="color"][value="' + runs[0].c + '"]');
      if (colorRadio) colorRadio.checked = true;
    }
  }

  function setCharStyle(index, color, bold) {
    if (index < 0 || index >= charColors.length) return;
    var nextBold = !!bold;
    if (charColors[index] === color && !!charBolds[index] === nextBold) return;
    charColors[index] = color;
    charBolds[index] = nextBold;
    var el = paintSurface.querySelector('[data-index="' + index + '"]');
    if (el) {
      el.className = 'paint-char term-' + color + (nextBold ? ' term-bold' : '');
    }
  }

  function renderPaintSurface() {
    var chars = charsFromText(body.value);
    layout = buildLayout(chars);
    paintSurface.textContent = '';
    paintSurface.classList.remove('is-bold');
    chars.forEach(function (ch, index) {
      var span = document.createElement('span');
      var boldClass = charBolds[index] ? ' term-bold' : '';
      span.className = 'paint-char term-' + (charColors[index] || 'default') + boldClass;
      span.textContent = ch === '\n' ? '\n' : ch;
      span.dataset.index = String(index);
      paintSurface.appendChild(span);
    });
  }

  function initCharStylesFromBody() {
    var chars = charsFromText(body.value);
    var base = selectedColor();
    var baseBold = brushBold();
    charColors = chars.map(function () {
      return base;
    });
    charBolds = chars.map(function () {
      return baseBold;
    });
  }

  function setComposeSurface(paintOn) {
    if (paintOn) {
      body.setAttribute('hidden', '');
      body.removeAttribute('required');
      paintSurface.removeAttribute('hidden');
      paintSurface.setAttribute('aria-hidden', 'false');
    } else {
      paintSurface.setAttribute('hidden', '');
      paintSurface.setAttribute('aria-hidden', 'true');
      paintSurface.textContent = '';
      paintSurface.classList.remove('is-bold');
      body.removeAttribute('hidden');
      body.setAttribute('required', '');
    }
  }

  function syncBoldLabel() {
    if (!boldToggleLabel) return;
    boldToggleLabel.textContent = paintMode ? 'bold brush' : 'bold';
  }

  function enterPaintMode() {
    if (!body.value) return;
    paintMode = true;
    paintToggle.setAttribute('aria-pressed', 'true');
    paintToggle.classList.add('is-active');
    initCharStylesFromBody();
    setComposeSurface(true);
    renderPaintSurface();
    if (brushPalette) brushPalette.hidden = false;
    if (colorPalette) colorPalette.hidden = true;
    if (paintHint) paintHint.hidden = false;
    if (composeHint) composeHint.hidden = true;
    if (spraySimple) spraySimple.hidden = true;
    if (sprayPaint) sprayPaint.hidden = false;
    syncBoldLabel();
    syncSpansField();
  }

  function exitPaintMode() {
    paintMode = false;
    painting = false;
    strokeSnapshot = null;
    strokeStartIndex = -1;
    paintToggle.setAttribute('aria-pressed', 'false');
    paintToggle.classList.remove('is-active');
    setComposeSurface(false);
    if (brushPalette) brushPalette.hidden = true;
    if (colorPalette) colorPalette.hidden = false;
    if (paintHint) paintHint.hidden = true;
    if (composeHint) composeHint.hidden = false;
    if (spraySimple) spraySimple.hidden = false;
    if (sprayPaint) sprayPaint.hidden = true;
    charColors = [];
    charBolds = [];
    layout = [];
    spansInput.value = '';
    syncBoldLabel();
    updateCount();
  }

  function indexInRect(index, minRow, maxRow, minCol, maxCol) {
    var cell = layout[index];
    if (!cell || cell.newline) return false;
    return cell.row >= minRow && cell.row <= maxRow && cell.col >= minCol && cell.col <= maxCol;
  }

  function applyStrokeRect(endIndex) {
    if (!strokeSnapshot || strokeStartIndex < 0 || endIndex < 0) return;
    var start = layout[strokeStartIndex];
    var end = layout[endIndex];
    if (!start || !end) return;

    var minRow = Math.min(start.row, end.row);
    var maxRow = Math.max(start.row, end.row);
    var minCol = Math.min(start.col, end.col);
    var maxCol = Math.max(start.col, end.col);
    var brush = selectedBrush();
    var bold = brushBold();

    for (var i = 0; i < charColors.length; i++) {
      if (indexInRect(i, minRow, maxRow, minCol, maxCol)) {
        setCharStyle(i, brush, bold);
      } else {
        setCharStyle(i, strokeSnapshot.colors[i], strokeSnapshot.bolds[i]);
      }
    }
    syncSpansField();
  }

  function indexFromPoint(clientX, clientY) {
    var el = document.elementFromPoint(clientX, clientY);
    if (!el || !paintSurface.contains(el)) return -1;
    while (el && el !== paintSurface && !el.dataset.index) {
      el = el.parentElement;
    }
    if (!el || !el.dataset.index) return -1;
    return parseInt(el.dataset.index, 10);
  }

  paintToggle.addEventListener('click', function () {
    if (paintMode) {
      exitPaintMode();
    } else {
      enterPaintMode();
    }
  });

  body.addEventListener('input', function () {
    if (paintMode) {
      // Text edited while somehow still in paint mode — clear paints
      exitPaintMode();
    }
    spansInput.value = '';
    updateCount();
  });

  paintSurface.addEventListener('pointerdown', function (e) {
    if (!paintMode) return;
    e.preventDefault();
    paintSurface.setPointerCapture(e.pointerId);
    var index = indexFromPoint(e.clientX, e.clientY);
    if (index < 0) return;
    painting = true;
    strokeStartIndex = index;
    strokeSnapshot = {
      colors: charColors.slice(),
      bolds: charBolds.slice(),
    };
    applyStrokeRect(index);
  });

  paintSurface.addEventListener('pointermove', function (e) {
    if (!painting) return;
    var index = indexFromPoint(e.clientX, e.clientY);
    if (index < 0) return;
    applyStrokeRect(index);
  });

  function endPaint(e) {
    if (!painting) return;
    painting = false;
    strokeSnapshot = null;
    strokeStartIndex = -1;
    try {
      paintSurface.releasePointerCapture(e.pointerId);
    } catch (err) { /* ignore */ }
  }

  paintSurface.addEventListener('pointerup', endPaint);
  paintSurface.addEventListener('pointercancel', endPaint);

  if (form) {
    form.addEventListener('submit', function () {
      if (paintMode) {
        // Match server sanitizeBody(): strip edge newlines so spans still partition body.
        while (body.value.startsWith('\n') && charColors.length > 0) {
          body.value = body.value.slice(1);
          charColors.shift();
          charBolds.shift();
        }
        while (body.value.endsWith('\n') && charColors.length > 0) {
          body.value = body.value.slice(0, -1);
          charColors.pop();
          charBolds.pop();
        }
        syncSpansField();
        var runs = buildRuns();
        if (runs && runs[0]) {
          var colorRadio = document.querySelector('input[name="color"][value="' + runs[0].c + '"]');
          if (colorRadio) colorRadio.checked = true;
        } else if (boldToggle && charBolds.length > 0) {
          // Uniform paint collapses to no spans; message-level bold must match brush.
          boldToggle.checked = !!charBolds[0];
        }
      } else {
        spansInput.value = '';
      }
    });
  }

  syncBoldLabel();
  updateCount();
})();
