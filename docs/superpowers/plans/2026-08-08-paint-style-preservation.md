# Paint Style Preservation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep per-character paint colors/bold when switching paint ↔ edit, remapping styles onto edited text via character-level LCS alignment.

**Architecture:** Extract a pure `remapStyles` helper (testable with Node). `compose.js` saves a paint snapshot on exit and remaps on enter instead of wiping/re-initing. Simple spray still clears the spans field.

**Tech Stack:** Vanilla JS (ES5-ish style matching `compose.js`), Node smoke test (no new test runner).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-08-paint-style-preservation-design.md`
- Unicode via `Array.from` (code points)
- Remap on enter paint only
- Inserts get `selectedColor()` + bold checkbox state
- Simple spray: no spans (`spansInput` cleared when not in paint mode)
- n ≤ 1000; O(n²) LCS OK
- Commit messages: subject + `@new`/`@changed`/`@fixed`/`@improved` with `**bold**` topics

---

## File Structure

```text
public/assets/paint-remap.js   # pure remapStyles (+ optional save helpers)
public/assets/compose.js       # snapshot + enter/exit wiring
tests/paint_remap_smoke.js     # Node assertions for remap
src/views/add.php              # script tag for paint-remap.js before compose.js
```

---

### Task 1: Pure remap helper + Node smoke test (TDD)

**Files:**
- Create: `public/assets/paint-remap.js`
- Create: `tests/paint_remap_smoke.js`
- Modify: `src/views/add.php` (script tag order)

**Interfaces:**
- `GraffitiPaintRemap.remapStyles(oldText, oldColors, oldBolds, newText, insertColor, insertBold) → { colors: string[], bolds: boolean[] }`
- Characters compared with `Array.from`
- Matched chars keep old style; inserts use `insertColor` / `insertBold`

- [ ] **Step 1: Write failing smoke test**

Create `tests/paint_remap_smoke.js`:

```javascript
#!/usr/bin/env node
'use strict';

var fs = require('fs');
var path = require('path');
var vm = require('vm');

var root = path.join(__dirname, '..');
var code = fs.readFileSync(path.join(root, 'public/assets/paint-remap.js'), 'utf8');
var sandbox = { globalThis: {} };
sandbox.globalThis = sandbox;
vm.runInNewContext(code, sandbox);

var remap = sandbox.GraffitiPaintRemap.remapStyles;

function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL:', msg);
    process.exit(1);
  }
}

// Unchanged text keeps styles
var r1 = remap(
  'abc',
  ['red', 'cyan', 'yellow'],
  [false, true, false],
  'abc',
  'default',
  false
);
assert(r1.colors.join(',') === 'red,cyan,yellow', 'unchanged colors');
assert(r1.bolds[1] === true, 'unchanged bold');

// Insert in middle
var r2 = remap(
  'ab',
  ['red', 'cyan'],
  [false, false],
  'aXb',
  'magenta',
  true
);
assert(r2.colors.join(',') === 'red,magenta,cyan', 'insert color');
assert(r2.bolds[0] === false && r2.bolds[1] === true && r2.bolds[2] === false, 'insert bold');

// Delete
var r3 = remap(
  'abcd',
  ['red', 'cyan', 'yellow', 'green'],
  [false, false, false, false],
  'ad',
  'default',
  false
);
assert(r3.colors.join(',') === 'red,green', 'delete middle');

// Prefix append
var r4 = remap(
  'hi',
  ['red', 'red'],
  [true, true],
  'hi!',
  'blue',
  false
);
assert(r4.colors.join(',') === 'red,red,blue', 'append');

console.log('paint remap smoke test passed.');
```

```bash
chmod +x tests/paint_remap_smoke.js
node tests/paint_remap_smoke.js
```

Expected: FAIL (missing `paint-remap.js`).

- [ ] **Step 2: Implement `public/assets/paint-remap.js`**

```javascript
(function (root) {
  'use strict';

  function charsFromText(text) {
    return Array.from(text || '');
  }

  /**
   * Character-level LCS remap of per-char styles from oldText onto newText.
   * @returns {{colors:string[], bolds:boolean[]}}
   */
  function remapStyles(oldText, oldColors, oldBolds, newText, insertColor, insertBold) {
    var oldChars = charsFromText(oldText);
    var newChars = charsFromText(newText);
    var n = oldChars.length;
    var m = newChars.length;
    var insertC = insertColor || 'default';
    var insertB = !!insertBold;

    var colors = [];
    var bolds = [];
    for (var z = 0; z < m; z++) {
      colors[z] = insertC;
      bolds[z] = insertB;
    }

    if (n === 0 || m === 0) {
      return { colors: colors, bolds: bolds };
    }

    // LCS lengths
    var dp = [];
    for (var i = 0; i <= n; i++) {
      dp[i] = [];
      for (var j = 0; j <= m; j++) {
        dp[i][j] = 0;
      }
    }
    for (i = 1; i <= n; i++) {
      for (j = 1; j <= m; j++) {
        if (oldChars[i - 1] === newChars[j - 1]) {
          dp[i][j] = dp[i - 1][j - 1] + 1;
        } else {
          dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
        }
      }
    }

    // Backtrack: copy styles for matches
    i = n;
    j = m;
    while (i > 0 && j > 0) {
      if (oldChars[i - 1] === newChars[j - 1]) {
        colors[j - 1] = oldColors[i - 1] || 'default';
        bolds[j - 1] = !!oldBolds[i - 1];
        i--;
        j--;
      } else if (dp[i - 1][j] >= dp[i][j - 1]) {
        i--;
      } else {
        j--;
      }
    }

    return { colors: colors, bolds: bolds };
  }

  root.GraffitiPaintRemap = {
    remapStyles: remapStyles,
    charsFromText: charsFromText,
  };
})(typeof globalThis !== 'undefined' ? globalThis : this);
```

- [ ] **Step 3: Re-run smoke test**

```bash
node tests/paint_remap_smoke.js
```

Expected: PASS.

- [ ] **Step 4: Load script on /add before compose.js**

In `src/views/add.php`, before `compose.js`:

```html
<script src="<?= e(asset_url('/assets/paint-remap.js')) ?>" defer></script>
<script src="<?= e(asset_url('/assets/compose.js')) ?>" defer></script>
```

(Keep `wall.js` after.)

Optionally assert in `AddViewTest` that `/assets/paint-remap.js` appears and precedes `compose.js` (strpos order).

- [ ] **Step 5: Commit**

```bash
git add public/assets/paint-remap.js tests/paint_remap_smoke.js src/views/add.php tests/AddViewTest.php
git commit -m "$(cat <<'EOF'
Add paint style remap helper for edit round-trips.

@new **Paint remap** character-level style preservation across text edits.
EOF
)"
```

---

### Task 2: Wire snapshot + remap into compose.js

**Files:**
- Modify: `public/assets/compose.js`

**Interfaces:**
- Consumes: `GraffitiPaintRemap.remapStyles`
- `paintSnapshot = { text, colors, bolds } | null`
- `savePaintSnapshot()` before leaving paint / when styles are current
- `enterPaintMode` remaps if snapshot; else init
- `exitPaintMode` saves snapshot, clears spans, does **not** discard snapshot arrays permanently without saving

- [ ] **Step 1: Add snapshot state and helpers**

Near other vars:

```javascript
  /** @type {{text:string,colors:string[],bolds:boolean[]}|null} */
  var paintSnapshot = null;

  function savePaintSnapshot() {
    if (!charColors.length) {
      paintSnapshot = null;
      return;
    }
    paintSnapshot = {
      text: body.value,
      colors: charColors.slice(),
      bolds: charBolds.slice(),
    };
  }

  function applyRemapOrInit() {
    var remapApi = globalThis.GraffitiPaintRemap;
    if (paintSnapshot && remapApi && typeof remapApi.remapStyles === 'function') {
      var remapped = remapApi.remapStyles(
        paintSnapshot.text,
        paintSnapshot.colors,
        paintSnapshot.bolds,
        body.value,
        selectedColor(),
        brushBold()
      );
      charColors = remapped.colors;
      charBolds = remapped.bolds;
      return;
    }
    initCharStylesFromBody();
  }
```

- [ ] **Step 2: Change `enterPaintMode`**

Replace `initCharStylesFromBody();` with `applyRemapOrInit();`. After render/sync, call `savePaintSnapshot()` so the snapshot matches the live paint buffers.

- [ ] **Step 3: Change `exitPaintMode`**

Before clearing live buffers:

```javascript
    savePaintSnapshot();
```

Then clear UI state as today (`charColors = []` etc. for live buffers is OK **after** save). Keep `spansInput.value = ''`. Do **not** set `paintSnapshot = null` on exit.

- [ ] **Step 4: Body input handler**

When exiting paint because textarea got input while somehow in paint mode, `exitPaintMode` already saves. When editing in simple mode, leave `paintSnapshot` alone (remap happens on next enter). Still clear `spansInput`.

- [ ] **Step 5: Manual smoke**

1. Type a message (≥10 chars), enter paint, color some runs, leave paint, re-enter → colors intact.
2. Leave paint, insert a character in the middle, re-enter → neighbors keep colors; new char uses message color.
3. Leave paint, spray with simple button → no multi-color spans in POST (spans empty).
4. Re-enter paint and spray from paint mode → spans present.

Also run: `node tests/paint_remap_smoke.js` and `./vendor/bin/phpunit`.

- [ ] **Step 6: Commit**

```bash
git add public/assets/compose.js
git commit -m "$(cat <<'EOF'
Preserve paint colors when toggling edit mode.

@fixed **Paint colors** survive leaving paint mode and remount onto edited text.
EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Snapshot on exit | Task 2 |
| Remap on enter via LCS | Task 1 + 2 |
| Inserts use message color/bold | Task 1 + 2 |
| Simple spray without spans | Task 2 |
| Unchanged text keeps styles | Task 1 tests + Task 2 |
| Testable remap | Task 1 |
