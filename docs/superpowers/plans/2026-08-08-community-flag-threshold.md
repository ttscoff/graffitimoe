# Community Flag Threshold Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the admin community-flag surfacing threshold configurable (default `1`) via `config/config.php`, and promote already-counted flags on boot so existing sprays appear immediately.

**Architecture:** Replace `MessageRepository::COMMUNITY_FLAG_THRESHOLD` with a constructor-injected int. Wire it from config in `public/index.php`. Add `promoteFlaggedByThreshold()` and call it once per request after connecting. Tests that need the old “3 distinct IPs” behavior pass an explicit threshold of `3`.

**Tech Stack:** PHP 8.1+, SQLite/PDO, PHPUnit, existing `config/config.php` array.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-08-community-flag-threshold-design.md`
- Default threshold: **1**
- Config key: `community_flag_threshold` (positive int; missing/invalid → `1`)
- Sync promotes only (`flagged = 0` → `1` when `flag_count >= threshold`); never auto-clears on raise
- Auto quality flags / clear-on-cross-below logic unchanged (with threshold `1`, unflagging the last community flag clears `flagged`, including auto-flagged rows — tests that assert auto-flag survival must use threshold `≥ 2`)
- Commit subjects + body; `@new`/`@changed`/`@fixed`/`@improved` with `**bold**` for user-facing lines only
- Do not commit secrets from local `config/config.php`

---

## File Structure

```text
config/config.example.php          — add community_flag_threshold => 1
config/config.php                  — add key locally (gitignored; not committed)
src/MessageRepository.php          — ctor threshold; replace const; sync method
public/index.php                   — parse config, inject, call sync
tests/MessageRepositoryTest.php    — threshold=1, threshold=3, sync tests
README.md                          — one-line note on the config key (optional, fold into Task 3)
```

---

### Task 1: Repository threshold + sync (TDD)

**Files:**
- Modify: `src/MessageRepository.php`
- Modify: `tests/MessageRepositoryTest.php`

**Interfaces:**
- `public function __construct(private PDO $pdo, private int $communityFlagThreshold = 1)` — clamp to `max(1, $communityFlagThreshold)` in the constructor body (assign to a private property; do not use an invalid promoted default).
- Remove `public const COMMUNITY_FLAG_THRESHOLD`.
- Use `$this->communityFlagThreshold` everywhere the old const was used in `toggleCommunityFlag`.
- `public function promoteFlaggedByThreshold(): int` — runs the promote SQL; returns rows affected (`PDOStatement::rowCount()`).

- [ ] **Step 1: Write failing tests**

In `tests/MessageRepositoryTest.php`:

1. Change `setUp` so the default repo uses threshold `1` explicitly (documents the new default):

```php
$this->repo = new MessageRepository($this->pdo, 1);
```

2. Update existing multi-IP tests to construct a **separate** repo with threshold `3` (do not rely on the default):

```php
public function test_third_distinct_ip_sets_admin_flagged(): void
{
    $repo = new MessageRepository($this->pdo, 3);
    $id = $repo->create('hello painted world!!', 'red', false, 'poster');
    $repo->toggleCommunityFlag($id, 'ip-1');
    $repo->toggleCommunityFlag($id, 'ip-2');
    $repo->toggleCommunityFlag($id, 'ip-3');
    $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(3, (int) $row['flag_count']);
    $this->assertSame(1, (int) $row['flagged']);
}

public function test_unflag_clears_admin_flag_only_when_crossing_below_threshold(): void
{
    $repo = new MessageRepository($this->pdo, 3);
    $id = $repo->create('hello painted world!!', 'red', false, 'poster');
    $repo->toggleCommunityFlag($id, 'ip-1');
    $repo->toggleCommunityFlag($id, 'ip-2');
    $repo->toggleCommunityFlag($id, 'ip-3');
    $this->assertSame('unflagged', $repo->toggleCommunityFlag($id, 'ip-2'));
    $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(2, (int) $row['flag_count']);
    $this->assertSame(0, (int) $row['flagged']);
}

public function test_auto_flagged_stays_flagged_below_threshold(): void
{
    // Threshold 3: community count 0↔1 never crosses the boundary, so auto flag stays.
    $repo = new MessageRepository($this->pdo, 3);
    $id = $repo->create('Hello world!!!!!!!!!!', 'red', false, 'poster', null, true);
    $repo->toggleCommunityFlag($id, 'ip-1');
    $repo->toggleCommunityFlag($id, 'ip-1'); // unflag
    $stored = $repo->recent(1)[0];
    $this->assertTrue($stored['flagged']);
    $row = $this->pdo()->query('SELECT flag_count FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(0, (int) $row['flag_count']);
}
```

3. Add new tests:

```php
public function test_threshold_one_flags_on_first_distinct_ip(): void
{
    $id = $this->repo->create('hello painted world!!', 'red', false, 'poster');
    $this->assertSame('flagged', $this->repo->toggleCommunityFlag($id, 'ip-a'));
    $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(1, (int) $row['flag_count']);
    $this->assertSame(1, (int) $row['flagged']);
}

public function test_promote_flagged_by_threshold_sets_existing_counts(): void
{
    $repoHigh = new MessageRepository($this->pdo, 3);
    $id = $repoHigh->create('hello painted world!!', 'red', false, 'poster');
    $repoHigh->toggleCommunityFlag($id, 'ip-1');
    $repoHigh->toggleCommunityFlag($id, 'ip-2');
    $row = $this->pdo()->query('SELECT flag_count, flagged FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(2, (int) $row['flag_count']);
    $this->assertSame(0, (int) $row['flagged']);

    $repoLow = new MessageRepository($this->pdo, 1);
    $this->assertSame(1, $repoLow->promoteFlaggedByThreshold());
    $row = $this->pdo()->query('SELECT flagged FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(1, (int) $row['flagged']);
    $this->assertSame(0, $repoLow->promoteFlaggedByThreshold());
}

public function test_constructor_clamps_threshold_below_one_to_one(): void
{
    $repo = new MessageRepository($this->pdo, 0);
    $id = $repo->create('hello painted world!!', 'red', false, 'poster');
    $repo->toggleCommunityFlag($id, 'ip-a');
    $row = $this->pdo()->query('SELECT flagged FROM messages WHERE id=' . $id)->fetch();
    $this->assertSame(1, (int) $row['flagged']);
}
```

Also update `test_toggle_community_flag_increments_and_dedupes_ip` expectations if needed: with threshold `1`, the first flag sets `flagged=1`, and unflag clears it (already asserts `flagged=0` after unflag — still correct).

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./vendor/bin/phpunit tests/MessageRepositoryTest.php --filter 'threshold_one|promote_flagged|clamps_threshold|third_distinct|unflag_clears|auto_flagged'
```

Expected: FAIL — `promoteFlaggedByThreshold` missing and/or constructor arity / const still `3` so first-IP test fails on `flagged`.

- [ ] **Step 3: Implement repository changes**

In `src/MessageRepository.php`:

```php
final class MessageRepository
{
    private int $communityFlagThreshold;

    public function __construct(private PDO $pdo, int $communityFlagThreshold = 1)
    {
        $this->communityFlagThreshold = max(1, $communityFlagThreshold);
    }

    /** Promote unflagged rows whose flag_count already meets the threshold. @return int rows updated */
    public function promoteFlaggedByThreshold(): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE messages SET flagged = 1
             WHERE flag_count >= :threshold AND flagged = 0'
        );
        $stmt->execute([':threshold' => $this->communityFlagThreshold]);
        return $stmt->rowCount();
    }

    // In toggleCommunityFlag, replace every self::COMMUNITY_FLAG_THRESHOLD
    // with $this->communityFlagThreshold. Delete the public const.
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run:

```bash
./vendor/bin/phpunit tests/MessageRepositoryTest.php
```

Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit**

```bash
git add src/MessageRepository.php tests/MessageRepositoryTest.php
git commit -m "$(cat <<'EOF'
Make community flag threshold configurable in the repository.

@changed **Community flags** surface in admin after a configurable minimum count (default 1).
EOF
)"
```

---

### Task 2: Config + boot wiring

**Files:**
- Modify: `config/config.example.php`
- Modify: `config/config.php` (local only — gitignored; do not `git add`)
- Modify: `public/index.php`

**Interfaces:**
- Config key: `'community_flag_threshold' => 1`
- Normalize in `index.php` before constructing the repo:

```php
$rawThreshold = $config['community_flag_threshold'] ?? 1;
$communityFlagThreshold = (is_int($rawThreshold) && $rawThreshold >= 1)
    ? $rawThreshold
    : 1;
$repo = new \Graffiti\MessageRepository(
    \Graffiti\Database::connect($config['db_path']),
    $communityFlagThreshold,
);
$repo->promoteFlaggedByThreshold();
```

Update the `@var` phpdoc on `$config` in `index.php` to include `community_flag_threshold?: int`.

- [ ] **Step 1: Add key to example config**

In `config/config.example.php`, after the rate-limit keys:

```php
'community_flag_threshold' => 1,
```

- [ ] **Step 2: Add key to local config (not committed)**

In `config/config.php` (gitignored), add the same line so local/dev picks it up immediately:

```php
'community_flag_threshold' => 1,
```

- [ ] **Step 3: Wire `public/index.php`**

Replace the bare `MessageRepository` construction with the normalize + inject + `promoteFlaggedByThreshold()` block above. Extend the config array phpdoc:

```php
/** @var array{
 *     db_path: string,
 *     admin_password: string,
 *     ip_hash_secret: string,
 *     rate_limit_max: int,
 *     rate_limit_window_seconds: int,
 *     community_flag_threshold?: int,
 *     base_url: string,
 *     session_name: string
 * } $config
 */
```

- [ ] **Step 4: Smoke-check with PHPUnit suite**

Other tests still use `new MessageRepository($pdo)` / `Database::connect(...)` with the default threshold `1` — that is intentional. Run:

```bash
./vendor/bin/phpunit
```

Expected: PASS.

- [ ] **Step 5: Commit (example + index only)**

```bash
git add config/config.example.php public/index.php
git commit -m "$(cat <<'EOF'
Wire community_flag_threshold from config on boot.

@new **Config** `community_flag_threshold` controls how many community flags surface a spray in admin (default 1).
@changed **Existing sprays** already at the threshold are promoted to flagged on each request boot.
EOF
)"
```

Confirm `git status` does **not** stage `config/config.php`.

---

### Task 3: README note

**Files:**
- Modify: `README.md` (admin / config section near the existing `config/config.php` mention)

- [ ] **Step 1: Document the key**

Near the admin / config setup prose (after editing `config/config.php` instructions), add one short sentence:

```markdown
`community_flag_threshold` (default `1`) is how many distinct community flags are required before a spray is marked flagged for admin review.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "$(cat <<'EOF'
Document community_flag_threshold in the README.
EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Config key in example + operator config | Task 2 |
| Default `1` | Task 1 (ctor default) + Task 2 |
| Inject into `MessageRepository` from `index.php` | Task 2 |
| Boot sync promote SQL | Task 1 method + Task 2 call |
| Raise threshold does not auto-clear | Implicit (sync is promote-only); no demote code |
| Auto quality flag path unchanged | Task 1 keeps clear-on-cross-below; auto test uses threshold 3 |
| Tests: threshold 1 first IP | Task 1 |
| Tests: multi-IP with explicit 3 | Task 1 |
| Tests: sync promotes existing | Task 1 |
| Invalid/missing config → 1 | Task 1 clamp + Task 2 normalize |
