# Live wall polling — Design

**Date:** 2026-08-02  
**Status:** Approved for planning  
**Scope:** Live updates of the recent sprays wall on `/add` via short polling

## Goal

Open `/add` tabs should show new sprays without a full page refresh, by prepending new terminal frames and reconciling with the server’s recent-10 (including admin deletes). Cap remains 10 frames.

## Approach

Short poll every **10 seconds** against a JSON recent-list endpoint. Reconcile the DOM against the full recent-10 each tick (not `after_id`-only). Pause polling while the tab is hidden. No WebSockets, SSE, or external pub/sub.

Own sprays keep the existing form POST + redirect flow.

## API

- **Route:** `GET /recent`
- **Response:** JSON array of up to 10 messages, newest first, same fields as the wall:
  - `id` (int)
  - `body` (string)
  - `color` (string)
  - `bold` (bool)
  - `created_at` (string)
- **Implementation:** thin `RecentHandler` calling `MessageRepository::recent(10)`; add `Response::json(...)` (`Content-Type: application/json; charset=utf-8`).
- **Caching:** `Cache-Control: no-store` (or equivalent) so polls are not served stale.
- **Auth:** none (public wall data already rendered on `/add`).

## Client

- Script: `public/assets/wall.js`, loaded only from `/add`.
- Interval: **10s**; skip ticks when `document.visibilityState === 'hidden'`; resume on `visibilitychange` when visible.
- Markup hooks:
  - Always render `.wall-grid` and `.wall-empty` (toggle visibility / presence from JS).
  - Each `.terminal` has `data-id="{id}"`.
- Reconcile against fetched list:
  1. **New IDs** (in server list, not in DOM) → build frames matching existing terminal chrome and **prepend** in newest-first order.
  2. **Gone IDs** (in DOM, not in server list) → remove nodes (admin deletes).
  3. **Cap at 10** → remove excess from the bottom of the grid.
  4. Empty list → show empty copy (“the wall is blank…”); hide empty state when any frames exist.
- Escape `body` (and any interpolated strings) when building HTML — same safety bar as PHP `e()`.
- Color/bold classes: same as server (`term-{color}`, optional `term-bold`).
- Optional: short CSS enter animation on newly prepended frames (nice-to-have, not required for acceptance).

## Files

| File | Change |
|------|--------|
| `src/Handlers/RecentHandler.php` | New |
| `src/Http/Response.php` | `json()` helper |
| `public/index.php` | Route `GET /recent` |
| `src/views/add.php` | `data-id`, always-present grid/empty, script tag |
| `public/assets/wall.js` | New poller |
| `public/assets/style.css` | Optional enter animation |
| `tests/*` | RecentHandler / JSON coverage; AddViewTest for hooks |

## Out of scope

- WebSockets / SSE / Redis pub-sub
- Changing spray POST to AJAX
- Live updates on admin UI
- Polling faster than 10s or configurable intervals in v1

## Acceptance

- With `/add` open, a spray from another client appears at the top of the wall within ~10s without refresh.
- Admin-deleted sprays disappear from open walls on the next successful poll.
- Wall never shows more than 10 frames after reconcile.
- Hidden tabs do not keep polling until focused again.
- Message bodies remain escaped in client-built HTML.
- Existing PHPUnit suite + new handler/view coverage pass.
