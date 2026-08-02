# Compose Character Counter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Live `N / 1000` character counter on `/add` that turns red when over the limit and disables spray until under again.

**Architecture:** Drop HTML `maxlength`; small `compose.js` updates a counter on `input` and toggles the submit button; CSS for dim/red states. Server still enforces 1000 after sanitize.

**Tech Stack:** PHP view, vanilla JS, existing CSS variables, PHPUnit AddViewTest.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-02-compose-char-counter-design.md`
- Limit: **1000** (`MessageSanitizer::MAX_LENGTH`)
- Format: `N / 1000` (no `+over` suffix)
- Over: red counter + disabled spray button
- Count: `textarea.value.length` on `input` + initial run
- Remove `maxlength` attribute
- Commit messages: subject + `@new`/`@changed`/`@fixed`/`@improved` with `**bold**` topics

---

## File Structure

```text
src/views/add.php
public/assets/compose.js
public/assets/style.css
tests/AddViewTest.php
```

---

### Task 1: Markup, CSS, tests, and compose.js

**Files:**
- Modify: `src/views/add.php`, `public/assets/style.css`, `tests/AddViewTest.php`
- Create: `public/assets/compose.js`

**Interfaces:**
- Counter: `<p id="char-count" class="char-count" aria-live="polite">0 / 1000</p>` after textarea
- Script: `/assets/compose.js` with `defer` (alongside `wall.js`)
- JS reads `#body`, `#char-count`, `.spray-btn`

- [ ] **Step 1: Failing AddViewTest**

Add to `tests/AddViewTest.php`:

```php
public function test_add_view_has_char_counter_hooks(): void
{
    $html = render_add([
        'recent' => [],
        'ok' => false,
        'error' => null,
        'colors' => \Graffiti\MessageSanitizer::COLORS,
    ]);

    $this->assertStringContainsString('id="char-count"', $html);
    $this->assertStringContainsString('aria-live="polite"', $html);
    $this->assertStringContainsString('/assets/compose.js', $html);
    $this->assertStringNotContainsString('maxlength="1000"', $html);
}
```

Run: `./vendor/bin/phpunit tests/AddViewTest.php --filter char_counter`  
Expected: FAIL.

- [ ] **Step 2: Update add.php**

1. Remove `maxlength="1000"` from the textarea.
2. Immediately after `</textarea>`, add:

```php
    <p id="char-count" class="char-count" aria-live="polite">0 / 1000</p>
```

3. Before `</body>` (with existing scripts):

```html
<script src="/assets/compose.js" defer></script>
<script src="/assets/wall.js" defer></script>
```

(Keep wall.js; add compose.js.)

- [ ] **Step 3: Add CSS**

In `public/assets/style.css` near compose styles:

```css
.char-count {
  margin: 0.4rem 0 0;
  text-align: right;
  font-family: var(--font-mono);
  font-size: 0.75rem;
  color: var(--text-dim);
}

.char-count.is-over {
  color: var(--term-red);
  font-weight: 600;
}

.spray-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
```

(Confirm `--term-red` exists; if not, use an existing red token from the palette.)

- [ ] **Step 4: Implement compose.js**

Create `public/assets/compose.js`:

```javascript
(function () {
  'use strict';

  var MAX = 1000;
  var body = document.getElementById('body');
  var counter = document.getElementById('char-count');
  var spray = document.querySelector('.spray-btn');
  if (!body || !counter || !spray) return;

  function update() {
    var n = body.value.length;
    counter.textContent = n + ' / ' + MAX;
    var over = n > MAX;
    counter.classList.toggle('is-over', over);
    spray.disabled = over;
  }

  body.addEventListener('input', update);
  update();
})();
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/phpunit tests/AddViewTest.php
./vendor/bin/phpunit
```

Expected: PASS.

Manual: open `/add`, paste 1001 chars → counter red, spray disabled; delete one → green path again.

- [ ] **Step 6: Commit**

```bash
git add src/views/add.php public/assets/compose.js public/assets/style.css tests/AddViewTest.php
git commit -m "$(cat <<'EOF'
Add live compose character counter.

@new **Char counter** live N/1000 under the spray field with red overage state.
@changed **Spray button** disables when the message is over 1000 characters.
EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Remove maxlength | Task 1 |
| `N / 1000` + red when over | Task 1 |
| Disable spray when over | Task 1 |
| input + load update | Task 1 |
| aria-live | Task 1 |
| Tests | Task 1 |
