# Live Wall Polling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Live-update the `/add` recent wall every 10s via `GET /recent` JSON, prepending new frames and reconciling deletes, capped at 10.

**Architecture:** Thin `RecentHandler` returns `MessageRepository::recent(10)` as JSON. Vanilla `wall.js` polls while the tab is visible and reconciles DOM nodes keyed by `data-id`. Form POST/redirect for spraying is unchanged.

**Tech Stack:** PHP 8.1+, existing handlers/Response patterns, vanilla JS, PHPUnit.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-02-live-wall-polling-design.md`
- Poll interval: **10 seconds**
- Cap: **10** frames; newest first
- Reconcile full recent-10 (prepend new, remove gone, trim)
- Pause when `document.visibilityState === 'hidden'`
- Escape client-built HTML (same bar as PHP `e()`)
- Cache-Control: `no-store` on `/recent`
- Out of scope: WebSockets/SSE, AJAX spray POST, admin live updates
- Commit messages: subject + `@new`/`@changed`/`@fixed`/`@improved` with `**bold**` topics

---

## File Structure

```text
src/Http/Response.php              # json() helper
src/Handlers/RecentHandler.php     # GET /recent
public/index.php                   # route
src/views/add.php                  # data-id, grid/empty always, wall.js
public/assets/wall.js              # poll + reconcile
public/assets/style.css            # optional enter animation
tests/RecentHandlerTest.php
tests/AddViewTest.php
```

---

### Task 1: JSON response helper + RecentHandler + route

**Files:**
- Modify: `src/Http/Response.php`
- Create: `src/Handlers/RecentHandler.php`
- Modify: `public/index.php`
- Create: `tests/RecentHandlerTest.php`

**Interfaces:**
- Produces: `Response::json(mixed $data, int $status = 200, array $extraHeaders = []): Response`
- Produces: `RecentHandler::handle(Request): Response` → JSON list from `recent(10)`
- Route: `GET /recent`

- [ ] **Step 1: Write failing RecentHandlerTest**

Create `tests/RecentHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\Handlers\RecentHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class RecentHandlerTest extends TestCase
{
    public function test_returns_json_recent_messages_newest_first(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_recent_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $repo->create('first', 'red', false, 'h1');
        usleep(10000);
        $repo->create('second', 'cyan', true, 'h2');

        $handler = new RecentHandler($repo);
        $res = $handler->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'));

        $this->assertSame(200, $res->status);
        $this->assertSame('application/json; charset=utf-8', $res->headers['Content-Type']);
        $this->assertSame('no-store', $res->headers['Cache-Control']);

        $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame('second', $data[0]['body']);
        $this->assertSame('cyan', $data[0]['color']);
        $this->assertTrue($data[0]['bold']);
        $this->assertSame('first', $data[1]['body']);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('created_at', $data[0]);

        @unlink($path);
    }

    public function test_empty_wall_returns_empty_array(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_recent_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $handler = new RecentHandler($repo);
        $res = $handler->handle(new Request('GET', '/recent', [], [], '', [], '1.1.1.1'));
        $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $data);
        @unlink($path);
    }
}
```

Run: `./vendor/bin/phpunit tests/RecentHandlerTest.php`  
Expected: FAIL (class missing).

- [ ] **Step 2: Implement Response::json, RecentHandler, route**

Add to `src/Http/Response.php`:

```php
/** @param array<string, string> $extraHeaders */
public static function json(mixed $data, int $status = 200, array $extraHeaders = []): self
{
    $headers = array_merge(
        [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ],
        $extraHeaders,
    );
    return new self($status, $headers, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
}
```

Create `src/Handlers/RecentHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;

final class RecentHandler
{
    public function __construct(private MessageRepository $repo)
    {
    }

    public function handle(Request $request): Response
    {
        return Response::json($this->repo->recent(10));
    }
}
```

In `public/index.php`, add a branch before the 404 else (alongside other routes):

```php
} elseif ($request->method === 'GET' && $request->path === '/recent') {
    $response = (new \Graffiti\Handlers\RecentHandler($repo))->handle($request);
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/phpunit tests/RecentHandlerTest.php
./vendor/bin/phpunit
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add src/Http/Response.php src/Handlers/RecentHandler.php public/index.php tests/RecentHandlerTest.php
git commit -m "$(cat <<'EOF'
Add GET /recent JSON endpoint for live wall.

@new **Recent API** JSON recent-10 with no-store for wall polling.
EOF
)"
```

---

### Task 2: Add view hooks for live wall

**Files:**
- Modify: `src/views/add.php`
- Modify: `tests/AddViewTest.php`

**Interfaces:**
- Consumes: existing `render_add`
- Produces: always-present `.wall-grid` + `.wall-empty`; `.terminal[data-id]`; `<script src="/assets/wall.js" defer>`

- [ ] **Step 1: Extend AddViewTest (failing)**

Add assertions to an existing test or new method:

```php
public function test_add_view_has_live_wall_hooks(): void
{
    $html = render_add([
        'recent' => [['id' => 9, 'body' => 'hi', 'color' => 'red', 'bold' => false, 'created_at' => 'x']],
        'ok' => false,
        'error' => null,
        'colors' => \Graffiti\MessageSanitizer::COLORS,
    ]);
    $this->assertStringContainsString('data-id="9"', $html);
    $this->assertStringContainsString('wall-grid', $html);
    $this->assertStringContainsString('wall-empty', $html);
    $this->assertStringContainsString('/assets/wall.js', $html);
}

public function test_add_view_empty_still_has_grid_and_empty(): void
{
    $html = render_add([
        'recent' => [],
        'ok' => false,
        'error' => null,
        'colors' => \Graffiti\MessageSanitizer::COLORS,
    ]);
    $this->assertStringContainsString('wall-grid', $html);
    $this->assertStringContainsString('wall-empty', $html);
}
```

Run: `./vendor/bin/phpunit tests/AddViewTest.php --filter live_wall`  
Expected: FAIL.

- [ ] **Step 2: Update add.php wall markup**

Replace the wall section body so both empty and grid always exist, and terminals carry `data-id`. Example structure:

```php
  <section class="wall">
    <h2 class="wall-title">recent sprays</h2>
    <p class="wall-empty"<?= $recent === [] ? '' : ' hidden' ?>>the wall is blank. be the first.</p>
    <div class="wall-grid">
      <?php foreach ($recent as $message): ?>
        <div class="terminal" data-id="<?= e((string) $message['id']) ?>">
          ... unchanged chrome ...
          <pre class="terminal-body <?= e(Color::cssClass($message['color'], $message['bold'])) ?>"><?= e($message['body']) ?></pre>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
```

Use the HTML `hidden` attribute (or a `.is-hidden` class already in CSS). If using `hidden`, add CSS if needed:

```css
[hidden] { display: none !important; }
```

Before `</body>`:

```html
<script src="/assets/wall.js" defer></script>
```

- [ ] **Step 3: Run AddViewTest + full suite**

Expected: PASS (wall.js may 404 until Task 3 — that’s OK if only the script tag is asserted).

- [ ] **Step 4: Commit**

```bash
git add src/views/add.php tests/AddViewTest.php public/assets/style.css
git commit -m "$(cat <<'EOF'
Add live wall markup hooks on /add.

@changed **Wall markup** data-id frames, always-on grid/empty, wall.js hook.
EOF
)"
```

---

### Task 3: wall.js poller + optional enter animation

**Files:**
- Create: `public/assets/wall.js`
- Modify: `public/assets/style.css` (optional animation)

**Interfaces:**
- Consumes: `GET /recent` JSON; DOM `.wall-grid`, `.wall-empty`, `.terminal[data-id]`
- Produces: prepend / remove / trim; 10s interval; visibility pause

- [ ] **Step 1: Implement `public/assets/wall.js`**

```javascript
(function () {
  'use strict';

  var POLL_MS = 10000;
  var MAX = 10;
  var grid = document.querySelector('.wall-grid');
  var empty = document.querySelector('.wall-empty');
  if (!grid || !empty) return;

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function cssClass(color, bold) {
    var c = 'term-' + (color || 'default');
    if (bold) c += ' term-bold';
    return c;
  }

  function buildFrame(msg) {
    var el = document.createElement('div');
    el.className = 'terminal terminal-enter';
    el.setAttribute('data-id', String(msg.id));
    el.innerHTML =
      '<div class="terminal-bar">' +
      '<span class="terminal-dot terminal-dot-red"></span>' +
      '<span class="terminal-dot terminal-dot-yellow"></span>' +
      '<span class="terminal-dot terminal-dot-green"></span>' +
      '<span class="terminal-title">msg #' + escapeHtml(String(msg.id)) + '</span>' +
      '</div>' +
      '<pre class="terminal-body ' + escapeHtml(cssClass(msg.color, !!msg.bold)) + '">' +
      escapeHtml(msg.body) +
      '</pre>';
    return el;
  }

  function setEmpty(isEmpty) {
    if (isEmpty) empty.removeAttribute('hidden');
    else empty.setAttribute('hidden', '');
  }

  function reconcile(messages) {
    if (!Array.isArray(messages)) return;

    var serverIds = messages.map(function (m) { return String(m.id); });
    var existing = Array.prototype.slice.call(grid.querySelectorAll('.terminal[data-id]'));
    var byId = {};
    existing.forEach(function (node) {
      byId[node.getAttribute('data-id')] = node;
    });

    // Remove gone
    existing.forEach(function (node) {
      var id = node.getAttribute('data-id');
      if (serverIds.indexOf(id) === -1) node.remove();
    });

    // Prepend new in reverse so final order is newest-first
    for (var i = messages.length - 1; i >= 0; i--) {
      var msg = messages[i];
      var id = String(msg.id);
      if (!byId[id]) {
        var frame = buildFrame(msg);
        grid.insertBefore(frame, grid.firstChild);
        byId[id] = frame;
      }
    }

    // Reorder to match server list exactly
    messages.forEach(function (msg) {
      var node = byId[String(msg.id)];
      if (node) grid.appendChild(node);
    });

    // Cap (server already <=10; safety)
    while (grid.querySelectorAll('.terminal').length > MAX) {
      var last = grid.querySelector('.terminal:last-child');
      if (!last) break;
      last.remove();
    }

    setEmpty(grid.querySelectorAll('.terminal').length === 0);
  }

  function poll() {
    if (document.visibilityState === 'hidden') return;
    fetch('/recent', { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('recent ' + r.status);
        return r.json();
      })
      .then(reconcile)
      .catch(function () { /* ignore transient errors */ });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') poll();
  });

  setInterval(poll, POLL_MS);
})();
```

Note: the reverse-prepend + appendChild reorder ensures correct newest-first order without duplicate logic bugs.

- [ ] **Step 2: Optional CSS enter animation**

In `public/assets/style.css`:

```css
.terminal-enter {
  animation: terminal-in 0.35s ease-out;
}

@keyframes terminal-in {
  from { opacity: 0; transform: translateY(-0.4rem); }
  to { opacity: 1; transform: none; }
}
```

- [ ] **Step 3: Manual smoke**

1. Serve locally, open `/add`.
2. From another terminal: `curl -X POST …/add` with a message (or second browser tab).
3. Within ~10s, new frame appears at top without refresh.
4. Delete via `/admin`; within ~10s frame disappears.
5. Confirm wall stays ≤10.

Also run: `./vendor/bin/phpunit`

- [ ] **Step 4: Commit**

```bash
git add public/assets/wall.js public/assets/style.css
git commit -m "$(cat <<'EOF'
Add live wall polling client.

@new **Live wall** 10s poll of /recent with prepend and delete reconcile.
EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| GET /recent JSON + no-store | Task 1 |
| Response::json | Task 1 |
| data-id, grid/empty always, wall.js tag | Task 2 |
| 10s poll, visibility pause | Task 3 |
| Prepend / remove / cap 10 | Task 3 |
| Escape HTML in client | Task 3 |
| Optional enter animation | Task 3 |
| Tests | Task 1–2 |
