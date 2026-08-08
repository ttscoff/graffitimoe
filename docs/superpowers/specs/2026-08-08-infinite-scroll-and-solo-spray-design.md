# Infinite Scroll Wall & Solo Spray Pages

## Goal

Let visitors browse beyond the newest 10 sprays via infinite scroll, open a single spray at `/id/NNN` in the browser, and click `msg #N` on the wall to reach that solo page. Curl/CLI behavior for `/id/NNN` stays plain text.

## Decisions

| Topic | Choice |
|-------|--------|
| Older page API | `GET /recent?before={id}&limit=10` (cursor by id) |
| Live poll | Unchanged newest-window poll; **prepend** only; leave older loaded frames alone |
| Solo `/id/NNN` | Browser → light HTML; curl/CLI → plain text (existing) |
| Solo chrome | Brand, one frame, link back to `/add`; flag/delete/admin when allowed |
| Wall titles | `msg #N` links to `/id/N` (SSR + `wall.js`) |
| Admin | Same infinite scroll; newest poll still uses admin limit (50) |

## API

### `GET /recent`

Query params:

| Param | Meaning |
|-------|---------|
| `before` | Optional positive int. When set, return sprays with `id < before`, ordered newest-first within that older set. |
| `limit` | Optional int, default **10**, max **50**. |

Without `before`: newest `limit` sprays (same as today, subject to caller). Wall poll continues to request the newest window (public default 10; admin 50 via existing session-based limit in `RecentHandler`, or explicit `limit` — keep current admin/public defaults when `limit` omitted).

With `before`: ignore the admin “50 newest” special case for scroll pages — always page size `limit` (default 10). Admin scroll uses the same cursor pages of 10.

Invalid `before` (missing, non-numeric, ≤0): treat as omitted (newest page) or return 400 — **prefer omit** for resilience.

### Repository

```php
/** @return list<hydrated> */
public function recent(int $limit = 10, ?int $beforeId = null): array
```

- `beforeId === null`: `ORDER BY created_at DESC, id DESC LIMIT :limit`
- `beforeId > 0`: `WHERE id < :before ORDER BY created_at DESC, id DESC LIMIT :limit`

## Wall infinite scroll

1. Initial paint: SSR newest 10 (admin 50) as today.
2. `wall.js` tracks loaded ids; on scroll near bottom (or sentinel element), fetch `/recent?before={oldestLoadedId}&limit=10`.
3. Append frames not already present (`data-id` dedupe).
4. If response length `< limit`, set `exhausted` and stop further loads.
5. Poll (10s): fetch newest window **without** `before`; prepend any ids not already on the wall; do **not** remove or reshuffle older frames.
6. Flag/delete/admin actions unchanged on appended frames.

## Solo page `/id/NNN`

### Content negotiation

- Browser (`Request::isBrowser()` and not preferring plain): HTML.
- Else: existing plain `Color::wrapMessage` + `Response::plain` (404 `Not found.`).

### HTML (light)

- Same site chrome basics: CSS, brand header (compact), no compose form.
- One terminal frame (body/spans/color/bold), title linking to self or plain `msg #N`.
- Flag / delete / approve when session allows; `next=/id/{id}` so redirects return to the solo page.
- Link: “back to the wall” → `/add`.
- 404 HTML: short message + link to `/add`.

### Wall links

- SSR and JS: wrap `msg #N` (or the title text) in `<a href="/id/{id}" class="terminal-title-link">`.

## Out of scope

- “Load more” button (scroll/sentinel only)
- Restoring scroll position from URL
- Changing CLI beyond current `get`
- Public flag counts

## Testing

- `MessageRepository::recent` with/without `before`
- `RecentHandler` honors `before` + `limit` cap
- `IdHandler`: plain text unchanged; browser HTML contains body + back link; 404 branches
- Add view / wall: title links to `/id/`
- Solo view render test
- Optional: JS not unit-tested (no harness); manual scroll smoke

## File touch list (expected)

- `src/MessageRepository.php`
- `src/Handlers/RecentHandler.php`
- `src/Handlers/IdHandler.php` (+ view render callback or dedicated view)
- `src/views/id.php` (new) / `src/views.php`
- `src/views/add.php`, `public/assets/wall.js`, `public/assets/style.css`
- `public/index.php`
- Tests under `tests/`
