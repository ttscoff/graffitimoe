# Compose character counter — Design

**Date:** 2026-08-02  
**Status:** Approved for planning  
**Scope:** Live character counter on `/add` compose textarea

## Goal

Show a live `N / 1000` counter under the message field. Turn it red when over the limit; disable spray until the count is back within range. Allow typing past 1000 so overage is visible (server still enforces the limit).

## Behavior

- Limit: **1000** characters (`MessageSanitizer::MAX_LENGTH`).
- Display: `N / 1000` (no separate `+over` suffix).
- Under/at limit: dim mono styling.
- Over limit (`N > 1000`): red text; spray button `disabled`.
- Count: `textarea.value.length` on `input` (typing + paste); update once on load.
- Remove HTML `maxlength` so the browser does not block overage.
- Accessibility: counter has `aria-live="polite"`.
- Server-side validation unchanged (sanitize + reject over 1000).

## Implementation

| File | Change |
|------|--------|
| `src/views/add.php` | Drop `maxlength`; add counter element; load `compose.js` |
| `public/assets/compose.js` | Count, style, disable button |
| `public/assets/style.css` | Counter layout + over-limit color |
| `tests/AddViewTest.php` | Assert counter + compose.js; no `maxlength="1000"` |

## Out of scope

- Matching post-sanitize length in the client (tabs/control stripping)
- Changing the 1000 limit
- Counters on admin fields

## Acceptance

- Counter updates as you type/paste.
- At 1001+ characters the counter is red and spray is disabled.
- At ≤1000 spray is enabled again (subject to empty/`required`).
- Submitting over-limit via crafted request still fails server-side.
