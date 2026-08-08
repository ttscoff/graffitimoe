# Infinite Scroll & Solo Spray Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Infinite-scroll the wall 10 sprays at a time via `before` cursor, and show a light HTML solo page at `/id/NNN` (curl stays plain text) with `msg #N` linking there.

**Architecture:** Extend `MessageRepository::recent($limit, ?$beforeId)` and `RecentHandler` query params. Rewrite wall poll to prepend-only (stop deleting older frames / trimming to MAX). Extend `IdHandler` for browser HTML via new `render_id` view. Link titles on SSR + JS frames.

**Tech Stack:** PHP 8.1+, SQLite/PDO, PHPUnit, vanilla JS (`wall.js`), existing session/CSRF patterns.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-08-infinite-scroll-and-solo-spray-design.md`
- Page size: **10** (`limit` default); max **50**
- Cursor: `before` = positive id → `id < before`, newest-first
- Invalid `before` → treat as omitted (newest page)
- Poll: newest window only; **prepend** new ids; never remove older loaded frames
- Solo: browser HTML light page; non-browser plain text (existing `IdHandler` behavior)
- `msg #N` → `/id/N`
- Commit subjects + body; `@new`/`@changed`/`@fixed`/`@improved` with `**bold**` for user-facing lines only

---

## File Structure

```text
src/MessageRepository.php          — recent($limit, ?$beforeId)
src/Handlers/RecentHandler.php     — parse before/limit; scroll pages always limit 10 default
src/Handlers/IdHandler.php         — HTML vs plain; inject session/owned/flagged/render
src/views/id.php                   — light solo page (NEW)
src/views.php                      — render_id()
src/views/add.php                  — title links + scroll sentinel
public/assets/wall.js              — prepend-only poll + infinite scroll + title links
public/assets/style.css            — solo page + title link + sentinel
public/index.php                   — wire IdHandler deps if needed
tests/MessageRepositoryTest.php
tests/RecentHandlerTest.php
tests/IdHandlerTest.php
tests/IdViewTest.php               — NEW (or AddViewTest-style)
tests/AddViewTest.php
```

**Prerequisite:** `MessageRepository::find` and plain-text `IdHandler` + `GET /id/(\d+)` route may already exist from the CLI `get` work. If missing, add them in Task 1/3 as noted. Do not break `graffiti get` / plain `/id/N`.

---

### Task 1: Repository `recent` cursor

**Files:**
- Modify: `src/MessageRepository.php`
- Modify: `tests/MessageRepositoryTest.php`

**Interfaces:**
- Change: `recent(int $limit = 10, ?int $beforeId = null): array`
- When `$beforeId !== null && $beforeId > 0`: `WHERE id < :before ORDER BY created_at DESC, id DESC LIMIT :limit`
- Clamp `$limit` to at least 1 in caller (handler); repo may assume positive limit

- [ ] **Step 1: Failing tests**

Add to `MessageRepositoryTest`:

```php
public function test_recent_before_returns_older_ids_newest_first(): void
{
    $a = $this->repo->create('aaaaaaaaaa', 'red', false, 'h');
    $b = $this->repo->create('bbbbbbbbbb', 'cyan', false, 'h');
    $c = $this->repo->create('cccccccccc', 'green', false, 'h');
    // ids increase: a < b < c
    $page = $this->repo->recent(10, $c);
    $ids = array_map(static fn (array $m): int => $m['id'], $page);
    $this->assertSame([$b, $a], $ids);

    $page2 = $this->repo->recent(1, $c);
    $this->assertSame([$b], array_map(static fn (array $m): int => $m['id'], $page2));

    $this->assertSame([], $this->repo->recent(10, $a));
}
```

Run: `./vendor/bin/phpunit tests/MessageRepositoryTest.php --filter recent_before`  
Expected: FAIL (wrong arity or no filter).

- [ ] **Step 2: Implement**

```php
public function recent(int $limit = 10, ?int $beforeId = null): array
{
    if ($beforeId !== null && $beforeId > 0) {
        $stmt = $this->pdo->prepare(
            'SELECT id, body, color, bold, spans, flagged, created_at FROM messages
             WHERE id < :before
             ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':before', $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    $stmt = $this->pdo->prepare(
        'SELECT id, body, color, bold, spans, flagged, created_at FROM messages
         ORDER BY created_at DESC, id DESC LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return array_map([$this, 'hydrate'], $stmt->fetchAll());
}
```

Ensure all existing `recent($n)` call sites still work (second arg optional).

- [ ] **Step 3: Run tests — PASS**

`./vendor/bin/phpunit tests/MessageRepositoryTest.php`

- [ ] **Step 4: Commit**

```bash
git add src/MessageRepository.php tests/MessageRepositoryTest.php
git commit -m "$(cat <<'EOF'
Add before-cursor to recent message queries.

Support paging older sprays for wall infinite scroll.
EOF
)"
```

---

### Task 2: `RecentHandler` query params

**Files:**
- Modify: `src/Handlers/RecentHandler.php`
- Modify: `tests/RecentHandlerTest.php`

**Interfaces:**
- Parse `before` from query: `(int)`; if ≤0 treat as null
- Parse `limit`: default = admin? 50 : 10 when `before` is null; when `before` is set, default **10** (scroll page) for everyone
- Cap limit at 50; floor at 1
- Call `$this->repo->recent($limit, $beforeId)`

- [ ] **Step 1: Failing tests**

In `RecentHandlerTest`, add cases (use existing setUp pattern):

```php
public function test_recent_before_returns_older_page(): void
{
    $a = $this->repo->create(str_repeat('a', 10), 'red', false, 'h');
    $b = $this->repo->create(str_repeat('b', 10), 'cyan', false, 'h');
    $c = $this->repo->create(str_repeat('c', 10), 'green', false, 'h');
    $res = $this->handler->handle(new Request(
        'GET', '/recent', ['before' => (string) $c, 'limit' => '10'],
        ['HTTP_ACCEPT' => 'application/json'], '', [], '1.1.1.1',
    ));
    $this->assertSame(200, $res->status);
    $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame([$b, $a], array_column($data, 'id'));
}

public function test_recent_invalid_before_ignored(): void
{
    $this->repo->create(str_repeat('a', 10), 'red', false, 'h');
    $res = $this->handler->handle(new Request(
        'GET', '/recent', ['before' => 'nope'],
        ['HTTP_ACCEPT' => 'application/json'], '', [], '1.1.1.1',
    ));
    $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
    $this->assertCount(1, $data);
}

public function test_recent_limit_capped_at_50(): void
{
    for ($i = 0; $i < 55; $i++) {
        $this->repo->create(str_repeat('x', 10) . $i, 'red', false, 'h');
    }
    $res = $this->handler->handle(new Request(
        'GET', '/recent', ['limit' => '999'],
        ['HTTP_ACCEPT' => 'application/json'], '', [], '1.1.1.1',
    ));
    $data = json_decode($res->body, true, 512, JSON_THROW_ON_ERROR);
    $this->assertCount(50, $data);
}
```

- [ ] **Step 2: Implement RecentHandler**

```php
public function handle(Request $request): Response
{
    $beforeRaw = $request->query['before'] ?? null;
    $beforeId = null;
    if ($beforeRaw !== null && $beforeRaw !== '' && ctype_digit((string) $beforeRaw)) {
        $n = (int) $beforeRaw;
        if ($n > 0) {
            $beforeId = $n;
        }
    }

    $defaultLimit = $beforeId !== null
        ? AddHandler::PUBLIC_RECENT_LIMIT
        : ($this->session->isAdmin()
            ? AddHandler::ADMIN_RECENT_LIMIT
            : AddHandler::PUBLIC_RECENT_LIMIT);

    $limit = $defaultLimit;
    if (isset($request->query['limit']) && ctype_digit((string) $request->query['limit'])) {
        $limit = (int) $request->query['limit'];
    }
    $limit = max(1, min(50, $limit));

    return Response::json($this->repo->recent($limit, $beforeId));
}
```

- [ ] **Step 3: Full RecentHandlerTest PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
Support before and limit on /recent.

@new **Older spray pages** via /recent?before=ID for infinite scroll
EOF
)"
```

---

### Task 3: Solo HTML page (`IdHandler` + view)

**Files:**
- Create: `src/views/id.php`
- Modify: `src/views.php` — `render_id`
- Modify: `src/Handlers/IdHandler.php`
- Modify: `public/index.php` — pass session, owned, flagged, render callback
- Create/modify: `tests/IdHandlerTest.php`, `tests/IdViewTest.php`
- Modify: `public/assets/style.css` — light solo layout

**Interfaces:**
- `IdHandler::__construct(MessageRepository $repo, SessionBag $session, OwnedMessages $owned, FlaggedMessages $flagged, string $ipSecret, callable $renderIdPage)`
- Browser + not `wantsPlainText()` → HTML; else plain (existing)
- HTML 404 vs plain 404
- Hydrate flagged ids for this one message via `flaggedMessageIdsForIp` + sync (same as AddHandler)

- [ ] **Step 1: Failing IdHandler / view tests**

```php
// IdHandlerTest — keep existing plain tests; add:
public function test_browser_gets_html_solo_page(): void
{
    $id = $this->repo->create('solo spray!!', 'cyan', false, 'h');
    $html = '';
    $handler = new IdHandler(
        $this->repo,
        $this->session,
        $this->owned,
        $this->flagged,
        'secret',
        function (array $vars) use (&$html): string {
            $html = render_id($vars);
            return $html;
        },
    );
    $res = $handler->handle(new Request(
        'GET',
        '/id/' . $id,
        [],
        ['HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_ACCEPT' => 'text/html'],
        '',
        [],
        '1.2.3.4',
    ), $id);
    $this->assertSame(200, $res->status);
    $this->assertStringContainsString('solo spray!!', $res->body);
    $this->assertStringContainsString('back to the wall', $res->body);
    $this->assertStringContainsString('/add', $res->body);
    $this->assertStringContainsString('msg #' . $id, $res->body);
}

public function test_browser_missing_id_html_404(): void
{
    // assert 404, contains not found + /add link, Content-Type html
}
```

Plain-text tests must still pass with updated constructor.

- [ ] **Step 2: `render_id` + `id.php`**

Light page: brand, optional flash unused, one terminal (reuse markup patterns from `add.php`), flag/delete/approve with `next=/id/{id}`, `<p class="solo-back"><a href="/add">back to the wall</a></p>`. 404 variant when `$message === null`.

- [ ] **Step 3: IdHandler content negotiation**

```php
if ($row === null) {
    if ($request->isBrowser() && !$request->wantsPlainText()) {
        return Response::html(($this->renderIdPage)([
            'message' => null,
            'isAdmin' => $this->session->isAdmin(),
            // csrf, owned, flagged...
        ]), 404);
    }
    return Response::plain('Not found.', 404);
}
if ($request->isBrowser() && !$request->wantsPlainText()) {
    // sync flagged for this id; return HTML 200
}
// existing plain wrapMessage
```

Wire `index.php` with `$owned`, `$flagged`, `$config['ip_hash_secret']`, `'render_id'`.

- [ ] **Step 4: CSS for `.solo-page` / `.solo-back` / title link styles as needed**

- [ ] **Step 5: Tests PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
Add light HTML solo pages for /id/N.

@new **Solo spray pages** at /id/N in the browser, with a link back to the wall
EOF
)"
```

---

### Task 4: Wall title links (SSR + JS)

**Files:**
- Modify: `src/views/add.php`
- Modify: `public/assets/wall.js` (`buildFrame`)
- Modify: `tests/AddViewTest.php`
- Modify: `public/assets/style.css` — `.terminal-title-link`

- [ ] **Step 1: Failing AddViewTest**

```php
public function test_wall_titles_link_to_solo_pages(): void
{
    $html = render_add([
        'recent' => [[
            'id' => 8,
            'body' => 'hello wall!!',
            'color' => 'red',
            'bold' => false,
            'created_at' => 'x',
        ]],
        'ok' => false,
        'error' => null,
        'colors' => \Graffiti\MessageSanitizer::COLORS,
        'csrfToken' => 'tok',
    ]);
    $this->assertStringContainsString('href="/id/8"', $html);
    $this->assertStringContainsString('msg #8', $html);
}
```

- [ ] **Step 2: SSR**

Replace title span with:

```php
<a class="terminal-title terminal-title-link" href="/id/<?= e((string) $message['id']) ?>">msg #<?= e((string) $message['id']) ?></a>
```

- [ ] **Step 3: wall.js `buildFrame`**

```javascript
'<a class="terminal-title terminal-title-link" href="/id/' + id + '">msg #' + id + '</a>' +
```

(use escaped id)

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
Link wall message titles to solo spray pages.

@new **msg #N links** open that spray's solo page
EOF
)"
```

---

### Task 5: Infinite scroll + prepend-only poll

**Files:**
- Modify: `public/assets/wall.js` (major)
- Modify: `src/views/add.php` — add `<div class="wall-sentinel" aria-hidden="true"></div>` after grid
- Modify: `public/assets/style.css` — sentinel height 1px

**Critical behavior change:** Current `reconcile()` **removes** ids not in the newest page and trims to `MAX`. That breaks infinite scroll. Replace with:

1. **`prependNew(messages)`** for poll: for each message in newest-first list, if `data-id` missing, `insertBefore` at top (iterate reverse or insert in order carefully). Do **not** delete other frames. Do **not** trim to MAX.
2. **`appendOlder(messages)`** for scroll: append frames not already present.
3. **`oldestId()`** / **`hasId(id)`** helpers.
4. **IntersectionObserver** (or scroll listener) on `.wall-sentinel`: if not `loadingOlder` and not `exhausted`, fetch `/recent?before=${oldest}&limit=10`, append, set `exhausted` if `data.length < 10`.
5. Keep `inFlight` separate from `loadingOlder` so poll and scroll don’t stomp each other (or one shared lock with care).
6. `setEmpty` still based on whether any `.terminal` exists.
7. Title links + flag forms already in `buildFrame` from Task 4.

- [ ] **Step 1: Rewrite poll path**

Remove: remove-gone loop, reorder-to-match-server-only list, `while length > MAX` trim.

Poll fetch stays `GET /recent` (no before) so admin still gets 50 newest for prepend discovery.

- [ ] **Step 2: Add infinite scroll loader**

```javascript
var PAGE = 10;
var exhausted = false;
var loadingOlder = false;
var sentinel = document.querySelector('.wall-sentinel');

function oldestId() {
  var nodes = grid.querySelectorAll('.terminal[data-id]');
  if (!nodes.length) return null;
  return parseInt(nodes[nodes.length - 1].getAttribute('data-id'), 10);
}

function loadOlder() {
  if (exhausted || loadingOlder || inFlight) return;
  var before = oldestId();
  if (!before) return;
  loadingOlder = true;
  fetch('/recent?before=' + before + '&limit=' + PAGE, {
    headers: { Accept: 'application/json' },
    cache: 'no-store',
  })
    .then(function (r) {
      if (!r.ok) throw new Error('older ' + r.status);
      return r.json();
    })
    .then(function (data) {
      if (!Array.isArray(data) || data.length < PAGE) exhausted = true;
      (data || []).forEach(function (msg) {
        if (!grid.querySelector('.terminal[data-id="' + msg.id + '"]')) {
          grid.appendChild(buildFrame(msg));
        }
      });
      setEmpty(grid.querySelectorAll('.terminal').length === 0);
    })
    .catch(function () { /* ignore */ })
    .then(function () { loadingOlder = false; });
}

if (sentinel && 'IntersectionObserver' in window) {
  new IntersectionObserver(function (entries) {
    if (entries.some(function (e) { return e.isIntersecting; })) loadOlder();
  }, { rootMargin: '200px' }).observe(sentinel);
}
```

- [ ] **Step 3: Manual smoke** (optional): many sprays, scroll loads more; new spray prepends while scrolled; titles navigate to solo HTML.

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
Add infinite scroll and prepend-only wall polling.

@new **Infinite scroll** loads 10 older sprays at a time on the wall
@changed **Live wall** keeps older loaded sprays when new ones arrive
EOF
)"
```

---

### Task 6: Verification

- [ ] **Step 1:** `./vendor/bin/phpunit` — all green
- [ ] **Step 2:** Spec checklist

| Spec item | Task |
|-----------|------|
| `recent(before)` | 1 |
| `/recent?before=&limit=` | 2 |
| Prepend-only poll | 5 |
| Infinite scroll 10 | 5 |
| Solo HTML / plain | 3 |
| Title links | 4 |
| Admin poll 50 newest | 2+5 |

- [ ] **Step 3:** Commit any leftover fixes or skip

---

## Self-review (plan vs spec)

1. **Coverage:** Cursor API, scroll, prepend poll, solo HTML, links — all tasked. CLI unchanged (out of scope).
2. **Placeholders:** None; `reconcile` rewrite fully specified because current MAX-trim would break the feature.
3. **Types:** `recent(int, ?int)`, IdHandler gains session deps — consistent across tasks.
4. **Note:** Uncommitted prior `IdHandler` / `find` / CLI `get` work may already be in the tree — Task 3 extends rather than duplicates; commit that prerequisite first if still dirty before starting Task 1.
