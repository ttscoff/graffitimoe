# Configurable Community Flag Threshold

## Goal

Surface community-flagged sprays in admin after fewer flags (starting at **1**), and make the minimum flag count a config value that is easy to change without editing application code.

## Decisions

| Topic | Choice |
|-------|--------|
| Config location | `community_flag_threshold` in `config/config.php` (documented in `config.example.php`) |
| Default | `1` |
| Wiring | Pass into `MessageRepository` constructor from `public/index.php` |
| Existing counts | On boot, sync: set `flagged = 1` where `flag_count >= threshold` and currently unflagged |
| Raising threshold | Does **not** auto-clear `flagged`; admin approve remains the clear path |
| Auto quality flags | Unchanged: `MessageQuality`-flagged sprays stay flagged when community count is below threshold |

## Behavior

### Flag / unflag

`toggleCommunityFlag` continues to:

1. Deduplicate by IP in `message_flags`
2. Maintain `flag_count` on `messages`
3. Set `flagged = 1` when `flag_count` crosses **at or above** the configured threshold
4. Clear `flagged` only when community count crosses **from at/above threshold to below**, and only for sprays that were community-flagged that way (existing auto-flag preservation stays)

### Immediate sync

After connecting the repository at request start, call a sync method that promotes any rows already meeting the threshold:

```sql
UPDATE messages
SET flagged = 1
WHERE flag_count >= :threshold
  AND flagged = 0
```

This makes sprays with 1–2 existing community flags appear under admin “flagged” as soon as the threshold is lowered, without a separate migration.

### Config

```php
'community_flag_threshold' => 1,
```

- Must be a positive integer (`>= 1`). Invalid or missing values fall back to `1`.
- Example and local/prod `config.php` both get the key (example is the template; local copy is edited by the operator).

## Out of scope

- Changing auto low-effort flagging (`MessageQuality`)
- Auto-unflagging when the threshold is raised
- UI to edit the threshold from admin
- Backfilling `flag_count` for historical data beyond the SQL sync above

## Tests

- Keep existing multi-IP threshold coverage by constructing the repo with an explicit threshold (e.g. `3`) so crossing and unflag-below-threshold cases stay meaningful.
- Assert threshold `1` sets `flagged` on the first distinct IP flag.
- Assert sync promotes existing `flag_count >= threshold` rows that were still `flagged = 0`.
