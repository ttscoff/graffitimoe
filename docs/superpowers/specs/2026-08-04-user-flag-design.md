# User Flagging on the Wall

## Goal

Let visitors flag sprays on the public wall. Each IP may flag a given spray at most once. After **3** distinct IP flags, the spray becomes admin-`flagged` (same queue as auto-flag). Visitors only see whether **they** have flagged a spray — not the global count.

## Decisions

| Topic | Choice |
|-------|--------|
| Storage | `flag_count` on `messages` + `message_flags(message_id, ip_hash)` unique |
| Dedup | IP hash (same `RateLimiter::hashIp` as posts) + session list for UI |
| Unflag | Allowed; removes this IP’s row and decrements count |
| Public count | Hidden; only “flagged by me” state |
| Auto-flag | Still sets `flagged=1` immediately on create (`flag_count` stays 0) |
| Clear admin flag | Only when community count crosses **3 → &lt;3** |
| Threshold | On every successful flag, if `flag_count >= 3` ensure `flagged=1` |

## Schema

### `messages.flag_count`

- `INTEGER NOT NULL DEFAULT 0`
- Guarded `ALTER` migration (same pattern as `spans` / `flagged`)

### `message_flags`

```sql
CREATE TABLE IF NOT EXISTS message_flags (
  message_id INTEGER NOT NULL,
  ip_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY (message_id, ip_hash),
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_message_flags_ip ON message_flags(ip_hash);
```

SQLite: ensure foreign keys are on if not already (`PRAGMA foreign_keys = ON`).

## Session helper

`FlaggedMessages` (mirror of `OwnedMessages`):

- Session key e.g. `flagged_ids`
- `remember(id)` / `forget(id)` / `has(id)` / `ids()`
- Used for bright UI and to avoid useless POSTs; **authoritative** dedupe is `message_flags`

On wall load, hydrate session (and `data-flagged-ids`) from DB: IDs among current wall messages that this IP has already flagged, so a new session on the same IP still shows bright controls.

## Behavior

### Flag (`POST /flag` when not yet flagged by this IP)

1. Validate CSRF; parse positive `id`.
2. Hash request IP.
3. Insert `(message_id, ip_hash)` into `message_flags`. Unique violation → idempotent success (ensure session remembers; do not increment).
4. Else: `flag_count = flag_count + 1`; if `flag_count >= 3` then `flagged = 1`.
5. Session `remember(id)`.
6. Redirect via `next` (default `/add`) or plain `Flagged.` for non-HTML clients.

### Unflag (`POST /flag` when already flagged by this IP — toggle)

Detect toggle from DB row presence (not only session):

1. If row exists for this IP + message: delete row; `flag_count = max(0, flag_count - 1)`; if previous count was `>= 3` and new count `< 3` then `flagged = 0`; session `forget(id)`.
2. Else: treat as flag (above).

Prefer a single endpoint that toggles based on current IP row.

### Auto-flag

Unchanged: `MessageQuality::shouldFlag` → `flagged=1` at create, `flag_count=0`, no `message_flags` rows.

Unflagging never clears auto-flag unless the spray had reached community ≥3 and then dropped below 3 (3→2 rule). An auto-flagged spray with `flag_count` 0–2 stays `flagged=1` through low-count toggles.

### Admin approve

- Clears `flagged` only; leaves `flag_count` and `message_flags` rows.
- Admin approve wins until the next successful **increment** (a new IP flag, or an IP that unflagged then flagged again). Do not auto-re-set `flagged` merely because `flag_count` remains ≥3 after approve.
- Ensure `flagged=1` only inside the flag (increment) path when the new count ≥ 3.

### Deletes

Deleting a message cascades `message_flags` rows (FK) or explicit cleanup in `delete` / `deleteMany`.

## UI

- Every wall frame: **Flag** control (form POST `/flag` with `csrf_token`, `id`, `next=/add`).
- CSS: default dim link; `.is-flagged-by-me` (or equivalent) brightens / fills when this IP/session has flagged it.
- No public count text.
- Always expose CSRF on the wall (flagging is available to everyone).
- Wall attrs: `data-csrf`, `data-flagged-ids` (comma-separated).
- `wall.js`: build the same control for polled frames; optional progressive enhancement — intercept submit, toggle class, keep form working without JS.
- Admin badge / approve UI unchanged and still admin-only.

## API / routing

| Method | Path | Role |
|--------|------|------|
| POST | `/flag` | Toggle flag for current IP |

Wire in `public/index.php` like `DeleteHandler`.

Do not expose `flag_count` on `/recent` JSON for now (not needed for UI).

## Errors

| Case | Response |
|------|----------|
| Not POST | 405 |
| Bad CSRF | 403 |
| Missing/invalid id | 400 |
| Message not found | 404 |
| Unique already flagged | 200/redirect success (idempotent) |

## Testing

- Migration creates `flag_count` + `message_flags`
- Flag increments count; second flag same IP is no-op
- Different IPs increment independently; at 3 set `flagged`
- Unflag decrements; 3→2 clears `flagged`
- Auto-flagged message with count &lt;3 stays flagged across flag/unflag that never reaches 3
- Admin approve clears flagged with count still ≥3; stays clear until a new flag from an IP that wasn’t counted… (if all 3 rows remain, a 4th IP flag increments and re-sets flagged; if an existing IP “flags” again it’s idempotent and should **not** re-escalate — document: re-escalate only when increment path runs). To re-escalate after approve with count still ≥3 without a new IP: out of scope; admin can leave it or delete.
- CSRF rejection
- Wall markup includes Flag control + flagged-by-me class when hydrated
- Cascade / cleanup on message delete

## Out of scope

- Public flag counts
- Cross-device identity beyond IP hash
- Notifying authors
- Changing the Terminal Widget beyond existing `/flagged` admin count
- Rate limiting flags beyond IP uniqueness (can add later if abused)
