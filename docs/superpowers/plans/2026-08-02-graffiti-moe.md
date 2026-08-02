# graffiti.moe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a Dreamhost-hosted PHP/SQLite graffiti wall with safe curl/CLI random reads, web + CLI spraypaint submits, controlled colors, recent-10 wall, and admin delete.

**Architecture:** Single PHP front controller behind Apache rewrite; domain logic in small `src/` classes; SQLite + config outside the web root; thin `cli/graffiti` shell wrapper around curl; PHPUnit for unit tests.

**Tech Stack:** PHP 8.1+, SQLite3 PDO, PHPUnit via Composer (dev), Apache `.htaccess`, POSIX shell + curl for the CLI.

## Global Constraints

- Max message length: **1000** characters (including newlines)
- Palette keys only: `default`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`
- Never persist or pass through user ANSI; server emits SGR only when `color=always`
- Preserve internal spaces/newlines; expand tabs to 4 spaces; strip other controls
- Plain-text default; empty pool fallback: `The wall is blank. Be the first: https://graffiti.moe/add`
- Submit rate limit target: **5 per 10 minutes** per IP (config-tunable)
- Config secrets and DB path never committed
- Commit messages: subject + `@new`/`@changed`/`@fixed`/`@improved` body lines with `**bold**` topics (no Co-authored-by)

---

## File Structure

```text
composer.json
phpunit.xml
.gitignore
README.md
config/config.example.php          # committed template
config/config.php                  # gitignored local secrets
data/.gitkeep                      # local SQLite dir (db file gitignored)
public/
  .htaccess
  index.php                        # front controller only
  assets/style.css
src/
  bootstrap.php
  Database.php
  MessageRepository.php
  MessageSanitizer.php
  Color.php
  RateLimiter.php
  Http/Request.php
  Http/Response.php
  Handlers/RandomHandler.php
  Handlers/AddHandler.php
  Handlers/AdminHandler.php
  views/add.php
  views/admin.php
  views/admin_login.php
cli/graffiti
tests/
  MessageSanitizerTest.php
  ColorTest.php
  MessageRepositoryTest.php
  RateLimiterTest.php
  RequestTest.php
  RandomHandlerTest.php
  AddHandlerTest.php
  AdminHandlerTest.php
  fixtures/
brew/graffiti.rb.example           # Formula stub for the maintainer's tap
```

**Responsibility notes:**
- `public/` is the only web-reachable tree in production (point Dreamhost docroot here, or deploy so `public` is the site root)
- `MessageSanitizer` / `Color` are pure and have no I/O
- Handlers return `Response` objects; `index.php` emits them
- CLI never imports PHP — HTTP only

---

### Task 1: Project scaffold

**Files:**
- Create: `composer.json`, `phpunit.xml`, `.gitignore`, `config/config.example.php`, `data/.gitkeep`, `src/bootstrap.php`, `public/index.php` (stub), `README.md` (minimal stub)

**Interfaces:**
- Produces: Composer autoload PSR-4 `Graffiti\\` → `src/`; PHPUnit runnable; `config.example.php` shape used by later tasks

- [ ] **Step 1: Create `composer.json`**

```json
{
  "name": "ttscoff/graffiti-moe",
  "description": "graffiti.moe — curl-able public graffiti wall",
  "type": "project",
  "require": {
    "php": ">=8.1"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  },
  "autoload": {
    "psr-4": {
      "Graffiti\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Graffiti\\Tests\\": "tests/"
    }
  }
}
```

- [ ] **Step 2: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
  <testsuites>
    <testsuite name="default">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `.gitignore`**

```gitignore
/vendor/
/config/config.php
/data/*.sqlite
/data/*.sqlite-journal
/.phpunit.cache/
.DS_Store
```

- [ ] **Step 4: Create `config/config.example.php`**

```php
<?php

declare(strict_types=1);

return [
    'db_path' => __DIR__ . '/../data/graffiti.sqlite',
    'admin_password' => 'change-me',
    'ip_hash_secret' => 'change-me-long-random',
    'rate_limit_max' => 5,
    'rate_limit_window_seconds' => 600,
    'base_url' => 'https://graffiti.moe',
    'session_name' => 'graffiti_admin',
];
```

- [ ] **Step 5: Create stub `src/bootstrap.php`**

```php
<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    $configPath = dirname(__DIR__) . '/config/config.example.php';
}

/** @var array<string, mixed> $config */
$config = require $configPath;

return $config;
```

- [ ] **Step 6: Create stub `public/index.php` and `data/.gitkeep`**

`public/index.php`:

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo "graffiti.moe scaffold\n";
```

`data/.gitkeep`: empty file.

- [ ] **Step 7: Install deps and verify PHPUnit**

```bash
composer install
./vendor/bin/phpunit --version
```

Expected: PHPUnit 10.x version line.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock phpunit.xml .gitignore config/config.example.php data/.gitkeep src/bootstrap.php public/index.php README.md
git commit -m "$(cat <<'EOF'
Scaffold PHP project for graffiti.moe.

@new **Project layout** with Composer, PHPUnit, config template, and public front-controller stub.
EOF
)"
```

---

### Task 2: Message sanitizer

**Files:**
- Create: `src/MessageSanitizer.php`, `tests/MessageSanitizerTest.php`

**Interfaces:**
- Produces:
  - `Graffiti\MessageSanitizer::sanitizeBody(string $raw): string` — throws `InvalidArgumentException` if empty/too long after sanitize
  - `Graffiti\MessageSanitizer::normalizeColor(?string $color): string`
  - `Graffiti\MessageSanitizer::normalizeBold(mixed $bold): bool`
  - Constant `MessageSanitizer::MAX_LENGTH = 1000`

- [ ] **Step 1: Write failing tests**

Create `tests/MessageSanitizerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\MessageSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessageSanitizerTest extends TestCase
{
    public function test_preserves_multiline_ascii_art_and_spaces(): void
    {
        $art = "  /\\_/\\\n ( o.o )\n  > ^ <";
        $this->assertSame($art, MessageSanitizer::sanitizeBody($art));
    }

    public function test_normalizes_crlf_and_expands_tabs(): void
    {
        $this->assertSame("a\nb    c", MessageSanitizer::sanitizeBody("a\r\nb\tc"));
    }

    public function test_strips_control_chars_and_escapes(): void
    {
        $raw = "hi\x07there\x1b[31mX";
        $this->assertSame('hithere[31mX', MessageSanitizer::sanitizeBody($raw));
    }

    public function test_trims_edges_but_keeps_internal_blank_line(): void
    {
        $this->assertSame("a\n\nb", MessageSanitizer::sanitizeBody("\n a\n\nb \n"));
    }

    public function test_rejects_empty_and_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody("   \n");
    }

    public function test_rejects_over_1000_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MessageSanitizer::sanitizeBody(str_repeat('a', 1001));
    }

    public function test_color_and_bold_normalization(): void
    {
        $this->assertSame('red', MessageSanitizer::normalizeColor('red'));
        $this->assertSame('default', MessageSanitizer::normalizeColor('neon'));
        $this->assertTrue(MessageSanitizer::normalizeBold('1'));
        $this->assertTrue(MessageSanitizer::normalizeBold('on'));
        $this->assertFalse(MessageSanitizer::normalizeBold(null));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/MessageSanitizerTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/MessageSanitizer.php`**

```php
<?php

declare(strict_types=1);

namespace Graffiti;

use InvalidArgumentException;

final class MessageSanitizer
{
    public const MAX_LENGTH = 1000;

    /** @var list<string> */
    public const COLORS = ['default', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan'];

    public static function sanitizeBody(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = str_replace("\t", '    ', $text);
        // Strip ESC and other controls except newline (\n = 0x0A)
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text) ?? '';
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Message is empty');
        }
        if (mb_strlen($text, 'UTF-8') > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Message exceeds 1000 characters');
        }
        return $text;
    }

    public static function normalizeColor(?string $color): string
    {
        $color = strtolower(trim((string) $color));
        return in_array($color, self::COLORS, true) ? $color : 'default';
    }

    public static function normalizeBold(mixed $bold): bool
    {
        if (is_bool($bold)) {
            return $bold;
        }
        if (is_int($bold)) {
            return $bold !== 0;
        }
        $value = strtolower(trim((string) $bold));
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
```

Note: `trim()` after control stripping removes leading/trailing spaces and newlines. Internal blank lines remain. The test `test_trims_edges_but_keeps_internal_blank_line` expects `"\n a\n\nb \n"` → `"a\n\nb"` (leading space on `a` trimmed by full-string trim — adjust test expectation if you intentionally want only newline trim: use a custom trim that only strips `\n`/` ` from ends consistently). **Implement edge trim as `trim($text, " \n")` behavior matching PHP `trim` defaults (spaces + newlines).** Update the test input to `"\na\n\nb\n"` → `"a\n\nb"` if the leading space case is undesirable.

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/MessageSanitizerTest.php
```

Expected: PASS. Fix the trim test to match chosen trim semantics if needed.

- [ ] **Step 5: Commit**

```bash
git add src/MessageSanitizer.php tests/MessageSanitizerTest.php
git commit -m "$(cat <<'EOF'
Add message sanitizer with ASCII art support.

@new **Sanitizer** preserves newlines/spaces, expands tabs, strips controls, and enforces the 1000-character limit.
EOF
)"
```

---

### Task 3: Color helpers

**Files:**
- Create: `src/Color.php`, `tests/ColorTest.php`

**Interfaces:**
- Produces:
  - `Color::wrapPlain(string $body, string $color, bool $bold, bool $enable): string`
  - `Color::cssClass(string $color, bool $bold): string` → e.g. `term-red term-bold`
  - SGR map: default=`0`/`39`, red=`31`, green=`32`, yellow=`33`, blue=`34`, magenta=`35`, cyan=`36`; bold adds `1`

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function test_wrap_disabled_returns_body_unchanged(): void
    {
        $this->assertSame("hi\n", Color::wrapPlain("hi\n", 'red', true, false));
    }

    public function test_wrap_enabled_uses_allowlisted_sgr_and_reset(): void
    {
        $out = Color::wrapPlain('X', 'red', true, true);
        $this->assertSame("\033[1;31mX\033[0m", $out);
    }

    public function test_css_class(): void
    {
        $this->assertSame('term-cyan term-bold', Color::cssClass('cyan', true));
        $this->assertSame('term-default', Color::cssClass('default', false));
    }
}
```

- [ ] **Step 2: Run to verify fail**

```bash
./vendor/bin/phpunit tests/ColorTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `src/Color.php`**

```php
<?php

declare(strict_types=1);

namespace Graffiti;

final class Color
{
    /** @var array<string, string> */
    private const FG = [
        'default' => '39',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
    ];

    public static function wrapPlain(string $body, string $color, bool $bold, bool $enable): string
    {
        if (!$enable) {
            return $body;
        }
        $color = MessageSanitizer::normalizeColor($color);
        $codes = [];
        if ($bold) {
            $codes[] = '1';
        }
        $codes[] = self::FG[$color];
        return "\033[" . implode(';', $codes) . 'm' . $body . "\033[0m";
    }

    public static function cssClass(string $color, bool $bold): string
    {
        $color = MessageSanitizer::normalizeColor($color);
        $class = 'term-' . $color;
        if ($bold) {
            $class .= ' term-bold';
        }
        return $class;
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
./vendor/bin/phpunit tests/ColorTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Color.php tests/ColorTest.php
git commit -m "$(cat <<'EOF'
Add controlled terminal and CSS color helpers.

@new **Color palette** maps allowlisted colors to safe SGR codes and CSS classes.
EOF
)"
```

---

### Task 4: Database and message repository

**Files:**
- Create: `src/Database.php`, `src/MessageRepository.php`, `tests/MessageRepositoryTest.php`

**Interfaces:**
- Produces:
  - `Database::connect(string $path): PDO` — creates parent dir; enables foreign keys; runs migrations
  - `MessageRepository`:
    - `create(string $body, string $color, bool $bold, string $ipHash): int` (new id)
    - `random(): ?array{id:int,body:string,color:string,bold:bool,created_at:string}`
    - `recent(int $limit = 10): list<array{...}>`
    - `allNewestFirst(): list<array{...}>` (admin)
    - `delete(int $id): bool`
  - Schema: `messages` + `submissions` (for rate limit counts) OR rate limiter uses `messages.ip_hash`+`created_at` only — **use `messages` timestamps for rate limiting in Task 5** (no separate table unless needed). Schema:

```sql
CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  body TEXT NOT NULL,
  color TEXT NOT NULL DEFAULT 'default',
  bold INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  ip_hash TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_messages_ip_created ON messages(ip_hash, created_at);
```

- [ ] **Step 1: Write failing repository tests** using a temp sqlite file in `sys_get_temp_dir()`.

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class MessageRepositoryTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_test_' . uniqid('', true) . '.sqlite';
        $pdo = Database::connect($this->path);
        $this->repo = new MessageRepository($pdo);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_create_recent_random_delete(): void
    {
        $this->assertNull($this->repo->random());
        $id = $this->repo->create("line1\nline2", 'green', true, 'hash1');
        $this->assertGreaterThan(0, $id);
        $row = $this->repo->random();
        $this->assertNotNull($row);
        $this->assertSame("line1\nline2", $row['body']);
        $this->assertSame('green', $row['color']);
        $this->assertTrue($row['bold']);
        $recent = $this->repo->recent(10);
        $this->assertCount(1, $recent);
        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->random());
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/phpunit tests/MessageRepositoryTest.php
```

- [ ] **Step 3: Implement `Database.php` and `MessageRepository.php`**

`src/Database.php`:

```php
<?php

declare(strict_types=1);

namespace Graffiti;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                body TEXT NOT NULL,
                color TEXT NOT NULL DEFAULT \'default\',
                bold INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                ip_hash TEXT NOT NULL
            );'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at DESC);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_ip_created ON messages(ip_hash, created_at);');
        return $pdo;
    }
}
```

`src/MessageRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Graffiti;

use PDO;

final class MessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $body, string $color, bool $bold, string $ipHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (body, color, bold, created_at, ip_hash) VALUES (:body, :color, :bold, :created_at, :ip_hash)'
        );
        $stmt->execute([
            ':body' => $body,
            ':color' => $color,
            ':bold' => $bold ? 1 : 0,
            ':created_at' => gmdate('c'),
            ':ip_hash' => $ipHash,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,body:string,color:string,bold:bool,created_at:string}|null */
    public function random(): ?array
    {
        $stmt = $this->pdo->query('SELECT id, body, color, bold, created_at FROM messages ORDER BY RANDOM() LIMIT 1');
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** @return list<array{id:int,body:string,color:string,bold:bool,created_at:string}> */
    public function recent(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, body, color, bold, created_at FROM messages ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /** @return list<array{id:int,body:string,color:string,bold:bool,created_at:string}> */
    public function allNewestFirst(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, body, color, bold, created_at FROM messages ORDER BY created_at DESC, id DESC'
        );
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM messages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countRecentByIpHash(string $ipHash, int $windowSeconds): int
    {
        $since = gmdate('c', time() - $windowSeconds);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM messages WHERE ip_hash = :ip_hash AND created_at >= :since'
        );
        $stmt->execute([':ip_hash' => $ipHash, ':since' => $since]);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'body' => (string) $row['body'],
            'color' => (string) $row['color'],
            'bold' => ((int) $row['bold']) === 1,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
./vendor/bin/phpunit tests/MessageRepositoryTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Database.php src/MessageRepository.php tests/MessageRepositoryTest.php
git commit -m "$(cat <<'EOF'
Add SQLite schema and message repository.

@new **Message storage** with create, random, recent, delete, and IP window counting.
EOF
)"
```

---

### Task 5: Rate limiter + IP hashing

**Files:**
- Create: `src/RateLimiter.php`, `tests/RateLimiterTest.php`

**Interfaces:**
- Consumes: `MessageRepository::countRecentByIpHash`
- Produces:
  - `RateLimiter::hashIp(string $ip, string $secret): string` — `hash_hmac('sha256', $ip, $secret)`
  - `RateLimiter::allowSubmit(string $ipHash): bool`
  - Constructor `(MessageRepository $repo, int $max, int $windowSeconds)`

- [ ] **Step 1: Write failing tests** that insert via repo until limit trips.

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\MessageRepository;
use Graffiti\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function test_allows_until_max_then_blocks(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rl_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $limiter = new RateLimiter($repo, 2, 600);
        $hash = RateLimiter::hashIp('1.2.3.4', 'secret');
        $this->assertTrue($limiter->allowSubmit($hash));
        $repo->create('a', 'default', false, $hash);
        $this->assertTrue($limiter->allowSubmit($hash));
        $repo->create('b', 'default', false, $hash);
        $this->assertFalse($limiter->allowSubmit($hash));
        @unlink($path);
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement `src/RateLimiter.php`**

```php
<?php

declare(strict_types=1);

namespace Graffiti;

final class RateLimiter
{
    public function __construct(
        private MessageRepository $repo,
        private int $max,
        private int $windowSeconds,
    ) {
    }

    public static function hashIp(string $ip, string $secret): string
    {
        return hash_hmac('sha256', $ip, $secret);
    }

    public function allowSubmit(string $ipHash): bool
    {
        return $this->repo->countRecentByIpHash($ipHash, $this->windowSeconds) < $this->max;
    }
}
```

- [ ] **Step 4: Run — expect PASS**

```bash
./vendor/bin/phpunit tests/RateLimiterTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/RateLimiter.php tests/RateLimiterTest.php
git commit -m "$(cat <<'EOF'
Add submit rate limiting by hashed IP.

@new **Rate limits** cap sprays per IP window using hashed addresses.
EOF
)"
```

---

### Task 6: HTTP Request/Response + browser detection

**Files:**
- Create: `src/Http/Request.php`, `src/Http/Response.php`, `tests/RequestTest.php`

**Interfaces:**
- Produces:
  - `Request::fromGlobals(): Request`
  - Properties/methods: `method`, `path`, `query`, `headers`, `rawBody`, `post`, `ip`, `wantsPlainText(): bool`, `isBrowser(): bool`, `colorEnabled(): bool` (`color=always`)
  - `Response::plain(string $body, int $status = 200): self`
  - `Response::html(string $body, int $status = 200): self`
  - `Response::redirect(string $location, int $status = 302): self`
  - `Response::emit(): void`

Browser heuristic: `isBrowser()` true if `User-Agent` matches `/Mozilla|Chrome|Safari|Firefox|Edg/i` **or** `Accept` contains `text/html`. Curl default Accept is `*/*` and UA contains `curl` → not browser.

- [ ] **Step 1: Write `tests/RequestTest.php` covering browser vs curl and `color=always`.**

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function test_curl_is_not_browser(): void
    {
        $req = new Request('GET', '/', [], [
            'HTTP_USER_AGENT' => 'curl/8.0.0',
            'HTTP_ACCEPT' => '*/*',
        ], '', [], '1.2.3.4');
        $this->assertFalse($req->isBrowser());
        $this->assertTrue($req->wantsPlainText());
    }

    public function test_browser_accept_html(): void
    {
        $req = new Request('GET', '/', [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ], '', [], '1.2.3.4');
        $this->assertTrue($req->isBrowser());
    }

    public function test_color_flag(): void
    {
        $req = new Request('GET', '/random', ['color' => 'always'], [], '', [], '1.2.3.4');
        $this->assertTrue($req->colorEnabled());
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement Request and Response**

`src/Http/Response.php`:

```php
<?php

declare(strict_types=1);

namespace Graffiti\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    public static function plain(string $body, int $status = 200): self
    {
        if ($body !== '' && !str_ends_with($body, "\n")) {
            $body .= "\n";
        }
        return new self($status, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], '');
    }

    public function emit(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
```

`src/Http/Request.php` — implement constructor used by tests plus `fromGlobals()` reading `$_SERVER`, `$_GET`, `$_POST`, and `php://input`. Path from `PARSE_URL` of `REQUEST_URI` (strip query). `wantsPlainText()`: true when Accept is missing/`*/*` or explicitly prefers text/plain, or UA contains `curl`/`Wget`, or `!isBrowser()`.

- [ ] **Step 4: Run — expect PASS**

```bash
./vendor/bin/phpunit tests/RequestTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Http/Request.php src/Http/Response.php tests/RequestTest.php
git commit -m "$(cat <<'EOF'
Add HTTP request and response helpers.

@new **HTTP layer** detects browsers vs curl and supports color query flags.
EOF
)"
```

---

### Task 7: RandomHandler

**Files:**
- Create: `src/Handlers/RandomHandler.php`, `tests/RandomHandlerTest.php`

**Interfaces:**
- Consumes: `MessageRepository`, `Color`, `Request`
- Produces: `RandomHandler::handle(Request $request): Response`
- Empty pool body exactly: `The wall is blank. Be the first: https://graffiti.moe/add` (use config `base_url` in handler constructor for the URL)

- [ ] **Step 1: Write tests** for empty pool, plain body, colored wrap, trailing newline via `Response::plain`.

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\Handlers\RandomHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use PHPUnit\Framework\TestCase;

final class RandomHandlerTest extends TestCase
{
    public function test_empty_pool_fallback(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rand_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $handler = new RandomHandler($repo, 'https://graffiti.moe');
        $res = $handler->handle(new Request('GET', '/random', [], [], '', [], '1.1.1.1'));
        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('The wall is blank', $res->body);
        @unlink($path);
    }

    public function test_returns_body_and_optional_color(): void
    {
        $path = sys_get_temp_dir() . '/graffiti_rand_' . uniqid('', true) . '.sqlite';
        $repo = new MessageRepository(Database::connect($path));
        $repo->create('hello', 'red', false, 'h');
        $handler = new RandomHandler($repo, 'https://graffiti.moe');
        $plain = $handler->handle(new Request('GET', '/random', [], [], '', [], '1.1.1.1'));
        $this->assertSame("hello\n", $plain->body);
        $colored = $handler->handle(new Request('GET', '/random', ['color' => 'always'], [], '', [], '1.1.1.1'));
        $this->assertStringContainsString("\033[31mhello\033[0m", $colored->body);
        @unlink($path);
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement handler**

```php
<?php

declare(strict_types=1);

namespace Graffiti\Handlers;

use Graffiti\Color;
use Graffiti\Http\Request;
use Graffiti\Http\Response;
use Graffiti\MessageRepository;

final class RandomHandler
{
    public function __construct(
        private MessageRepository $repo,
        private string $baseUrl,
    ) {
    }

    public function handle(Request $request): Response
    {
        $row = $this->repo->random();
        if ($row === null) {
            $body = 'The wall is blank. Be the first: ' . rtrim($this->baseUrl, '/') . '/add';
            return Response::plain($body);
        }
        $body = Color::wrapPlain($row['body'], $row['color'], $row['bold'], $request->colorEnabled());
        return Response::plain($body);
    }
}
```

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Handlers/RandomHandler.php tests/RandomHandlerTest.php
git commit -m "$(cat <<'EOF'
Add random message plain-text handler.

@new **Random endpoint logic** serves fortune-style text with optional safe colors.
EOF
)"
```

---

### Task 8: AddHandler (POST create + GET page data)

**Files:**
- Create: `src/Handlers/AddHandler.php`, `tests/AddHandlerTest.php`
- Views wired in Task 9; this task returns HTML via a simple rendered string callback or builds status + recent list for the view

**Interfaces:**
- Consumes: sanitizer, repo, rate limiter, config secret
- Produces:
  - `AddHandler::handle(Request $request): Response`
  - GET → will render view (Task 9); for now return HTML placeholder OR accept a `callable $renderer`
  - POST: honeypot field name `website` — if non-empty, return fake success without insert
  - POST plain: `201` + `Sprayed.\n` ; errors `400`/`429` plain
  - POST browser form: redirect to `/add?ok=1` or `/add?error=...`

Prefer implementing full handle logic now with an injectable renderer:

```php
/** @param callable(array<string,mixed>):string $renderAddPage */
public function __construct(
    private MessageRepository $repo,
    private RateLimiter $limiter,
    private string $ipSecret,
    private $renderAddPage,
) {}
```

- [ ] **Step 1: Write tests** for successful plain POST, too-long 400, rate limit 429, honeypot no-insert, multiline body stored.

```php
<?php

declare(strict_types=1);

namespace Graffiti\Tests;

use Graffiti\Database;
use Graffiti\Handlers\AddHandler;
use Graffiti\Http\Request;
use Graffiti\MessageRepository;
use Graffiti\RateLimiter;
use PHPUnit\Framework\TestCase;

final class AddHandlerTest extends TestCase
{
    private string $path;
    private MessageRepository $repo;
    private AddHandler $handler;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/graffiti_add_' . uniqid('', true) . '.sqlite';
        $this->repo = new MessageRepository(Database::connect($this->path));
        $limiter = new RateLimiter($this->repo, 5, 600);
        $this->handler = new AddHandler(
            $this->repo,
            $limiter,
            'secret',
            fn (array $vars): string => 'html:' . count($vars['recent']),
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_plain_post_creates_multiline_message(): void
    {
        $req = new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => "a\nb", 'color' => 'blue'],
            '9.9.9.9',
        );
        $res = $this->handler->handle($req);
        $this->assertSame(201, $res->status);
        $this->assertSame("Sprayed.\n", $res->body);
        $this->assertSame("a\nb", $this->repo->recent(1)[0]['body']);
        $this->assertSame('blue', $this->repo->recent(1)[0]['color']);
    }

    public function test_honeypot_skips_persist(): void
    {
        $req = new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => 'hi', 'website' => 'http://spam.example'],
            '9.9.9.9',
        );
        $res = $this->handler->handle($req);
        $this->assertSame(201, $res->status);
        $this->assertSame("Sprayed.\n", $res->body);
        $this->assertNull($this->repo->random());
    }

    public function test_rate_limit(): void
    {
        $limiter = new RateLimiter($this->repo, 1, 600);
        $handler = new AddHandler($this->repo, $limiter, 'secret', fn (): string => '');
        $mk = fn () => new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => 'x'],
            '8.8.8.8',
        );
        $this->assertSame(201, $handler->handle($mk())->status);
        $second = $handler->handle($mk());
        $this->assertSame(429, $second->status);
        $this->assertStringContainsString('Slow down', $second->body);
    }

    public function test_too_long_is_400(): void
    {
        $req = new Request(
            'POST',
            '/add',
            [],
            ['HTTP_ACCEPT' => 'text/plain'],
            '',
            ['body' => str_repeat('a', 1001)],
            '7.7.7.7',
        );
        $this->assertSame(400, $this->handler->handle($req)->status);
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement `AddHandler`**

Logic outline:
1. If GET → `Response::html(($this->renderAddPage)(['recent' => $this->repo->recent(10), 'ok' => ..., 'error' => ..., 'colors' => MessageSanitizer::COLORS]))`
2. If POST:
   - If honeypot `website` non-empty → success response without DB write
   - Resolve body from `post['body']` or `rawBody` if content-type text/plain
   - Sanitize; on exception → 400
   - Hash IP; if `!$limiter->allowSubmit` → 429 `Slow down.\n`
   - `create(...)`; plain clients get `Response::plain('Sprayed.', 201)`; browsers `Response::redirect('/add?ok=1')`

- [ ] **Step 4: Run — expect PASS**

```bash
./vendor/bin/phpunit tests/AddHandlerTest.php
```

- [ ] **Step 5: Commit**

```bash
git add src/Handlers/AddHandler.php tests/AddHandlerTest.php
git commit -m "$(cat <<'EOF'
Add message create handler with honeypot and limits.

@new **Spray endpoint** accepts web and CLI posts with rate limiting and bot honeypot.
EOF
)"
```

---

### Task 9: `/add` views + CSS (terminal frames)

**Files:**
- Create: `src/views/add.php`, `public/assets/style.css`
- Modify: wire renderer in bootstrap/index (Task 11 may finish wiring); for this task add a `Graffiti\Views` helper or closure in `src/views.php`:

```php
function render_add(array $vars): string { ob_start(); extract($vars); require __DIR__ . '/views/add.php'; return (string) ob_get_clean(); }
```

**Interfaces:**
- Produces: HTML page with brand, monospace textarea, palette radios/select, bold checkbox, honeypot, recent 10 terminal frames, `white-space: pre-wrap`, CSS color classes `.term-red` etc.

- [ ] **Step 1: Implement `public/assets/style.css`** with a clear non-generic look (avoid purple-on-white / cream-serif clichés). Dark terminal chrome on a textured/graffiti-ish background is on-brand; use expressive fonts via Google/fonts CDN or system mono stack for terminals + one display font for the brand. Keep first viewport focused: brand, one line of help, form — recent wall below.

Minimum CSS classes: `.page`, `.brand`, `.compose`, `textarea`, `.palette`, `.terminal`, `.terminal-bar`, `.terminal-body`, `.term-*`, `.term-bold`, `.flash`.

- [ ] **Step 2: Implement `src/views/add.php`** escaping with `htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`, body class from `Color::cssClass`, form `method=post action=/add`, honeypot field hidden with CSS (`position:absolute;left:-9999px`), `autocomplete=off`, tabindex=-1, name=`website`.

- [ ] **Step 3: Manual visual check later with PHP server (Task 11). For now, add a tiny test that `render_add` escapes a malicious body:

```php
public function test_add_view_escapes_html(): void
{
    $html = render_add([
        'recent' => [['id' => 1, 'body' => '<script>alert(1)</script>', 'color' => 'red', 'bold' => false, 'created_at' => 'x']],
        'ok' => false,
        'error' => null,
        'colors' => \Graffiti\MessageSanitizer::COLORS,
    ]);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
}
```

- [ ] **Step 4: Run that test — PASS**

- [ ] **Step 5: Commit**

```bash
git add src/views/add.php src/views.php public/assets/style.css tests/AddViewTest.php
git commit -m "$(cat <<'EOF'
Add /add page with terminal-framed recent graffiti.

@new **Add page** with monospace compose, color picker, and recent terminal frames.
EOF
)"
```

---

### Task 10: AdminHandler

**Files:**
- Create: `src/Handlers/AdminHandler.php`, `src/views/admin.php`, `src/views/admin_login.php`, `tests/AdminHandlerTest.php`

**Interfaces:**
- Consumes: repo, `admin_password`, session
- Produces: login form; on success set `$_SESSION['admin']=1`; list + delete POST `id`
- Unauthorized list/delete → login page (403 if plain, else HTML login)

Use `session_name` from config; in tests, call `session_start()` with `@session_start` carefully or inject a small `Session` interface:

```php
interface SessionBag {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
    public function isAdmin(): bool;
}
```

Implement `ArraySession` for tests and `PhpSession` for production.

- [ ] **Step 1: Write tests** — wrong password fails; right password can delete.

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement AdminHandler + views** (escape bodies; delete button per row; no IP hash shown).

Password check: `hash_equals((string)$configPassword, (string)$postedPassword)`.

- [ ] **Step 4: Run — PASS**

- [ ] **Step 5: Commit**

```bash
git add src/Handlers/AdminHandler.php src/Session.php src/views/admin.php src/views/admin_login.php tests/AdminHandlerTest.php
git commit -m "$(cat <<'EOF'
Add password-gated admin delete UI.

@new **Admin tools** list and hard-delete graffiti after password login.
EOF
)"
```

---

### Task 11: Front controller, rewrite rules, local smoke

**Files:**
- Modify: `public/index.php`
- Create: `public/.htaccess`
- Create: `scripts/smoke.sh` (optional but recommended)

**Interfaces:**
- Produces: routing table:
  - `GET /` → browser? redirect `/add` : `RandomHandler`
  - `GET /random` → `RandomHandler`
  - `GET|POST /add` → `AddHandler`
  - `GET|POST /admin` → `AdminHandler`
  - else 404 plain/html

- [ ] **Step 1: Write `public/.htaccess`**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

- [ ] **Step 2: Implement full `public/index.php`** wiring config → PDO → services → match path → emit response. Wrap fatals in try/catch returning `Response::plain('Something went wrong.', 500)`.

Path normalization: strip trailing slash except root; ensure `/index.php/...` not required when using built-in server router.

- [ ] **Step 3: Add `public/router.php` for PHP built-in server**

```php
<?php
// router for: php -S localhost:8080 -t public public/router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
```

- [ ] **Step 4: Copy config and smoke test**

```bash
cp config/config.example.php config/config.php
# set db_path to data/graffiti.sqlite, random secrets
php -S localhost:8080 -t public public/router.php &
sleep 1
curl -s http://localhost:8080/random
curl -s -X POST -H 'Accept: text/plain' -d 'body=hello%20wall&color=cyan' http://localhost:8080/add
curl -s http://localhost:8080/random
curl -s -o /dev/null -w '%{http_code}' -H 'Accept: text/html' -H 'User-Agent: Mozilla/5.0' http://localhost:8080/
# expect 302
kill %1
```

Expected: blank wall message, then `Sprayed.`, then `hello wall`, redirect status 302.

- [ ] **Step 5: Run full PHPUnit suite**

```bash
./vendor/bin/phpunit
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add public/index.php public/.htaccess public/router.php scripts/smoke.sh config/config.example.php
git commit -m "$(cat <<'EOF'
Wire front controller routes for graffiti.moe.

@new **HTTP routing** serves random, add, and admin paths from one controller.
EOF
)"
```

---

### Task 12: CLI `graffiti`

**Files:**
- Create: `cli/graffiti` (executable), `tests/cli_smoke.sh`, `brew/graffiti.rb.example`

**Interfaces:**
- Produces: shell script with:
  - `GRAFFITI_URL` default `https://graffiti.moe`
  - no args → `GET $GRAFFITI_URL/random` with optional `?color=always`
  - color auto: if `NO_COLOR` set → never; else if `--color=always|never|auto`; auto uses `[ -t 1 ]`
  - `spraypaint` → POST form `body`, `color`, `bold` with `Accept: text/plain`
  - help/`-h`

- [ ] **Step 1: Write `cli/graffiti`**

```bash
#!/usr/bin/env bash
set -euo pipefail

BASE="${GRAFFITI_URL:-https://graffiti.moe}"
BASE="${BASE%/}"
COLOR_MODE="${GRAFFITI_COLOR:-auto}" # overridden by flags

usage() {
  cat <<'EOF'
Usage:
  graffiti [--color=always|never|auto]     Print a random graffiti message
  graffiti spraypaint [--color NAME] [--bold] [MESSAGE]
  graffiti spraypaint [--color NAME] [--bold] < file.txt
  graffiti help
EOF
}

want_color() {
  case "$COLOR_MODE" in
    always) return 0 ;;
    never) return 1 ;;
    auto)
      if [[ -n "${NO_COLOR:-}" ]]; then return 1; fi
      [[ -t 1 ]]
      ;;
    *) return 1 ;;
  esac
}

cmd_random() {
  local url="$BASE/random"
  if want_color; then url+="?color=always"; fi
  curl -fsS "$url"
}

cmd_spraypaint() {
  local color=default bold=0 message=""
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --color) color="${2:-default}"; shift 2 ;;
      --bold) bold=1; shift ;;
      --) shift; break ;;
      -*) echo "Unknown option: $1" >&2; exit 2 ;;
      *) message="$1"; shift; break ;;
    esac
  done
  if [[ $# -gt 0 ]]; then message+=" $*"; fi
  if [[ -z "$message" ]]; then
    message="$(cat)"
  fi
  curl -fsS -X POST "$BASE/add" \
    -H 'Accept: text/plain' \
    --data-urlencode "body=${message}" \
    --data-urlencode "color=${color}" \
    --data-urlencode "bold=${bold}"
}

# parse global --color= before subcommand
args=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --color=*) COLOR_MODE="${1#--color=}"; shift ;;
    --color) COLOR_MODE="${2:-auto}"; shift 2 ;;
    -h|--help|help) usage; exit 0 ;;
    *) args+=("$1"); shift ;;
  esac
done
set -- "${args[@]:-}"

case "${1:-}" in
  "" ) cmd_random ;;
  spraypaint) shift; cmd_spraypaint "$@" ;;
  help) usage ;;
  *) echo "Unknown command: $1" >&2; usage >&2; exit 2 ;;
esac
```

Make executable: `chmod +x cli/graffiti`.

Fix `--data-urlencode "body=${message}"` for multiline: prefer `curl --data-urlencode body@-` with printf, or use `curl -F` / `--data-binary` carefully. **Required implementation detail:** use:

```bash
curl -fsS -X POST "$BASE/add" \
  -H 'Accept: text/plain' \
  --data-urlencode "body@-" \
  --data-urlencode "color=${color}" \
  --data-urlencode "bold=${bold}" <<<"$message"
```

Actually `body@-` reads stdin file style — verify curl supports `--data-urlencode body@-`. Alternative: write temp file. Document the chosen approach that preserves newlines.

- [ ] **Step 2: Smoke against local server**

```bash
export GRAFFITI_URL=http://localhost:8080
./cli/graffiti spraypaint $'hi\nart'
./cli/graffiti --color=never
```

Expected: `Sprayed.` then the message text.

- [ ] **Step 3: Add `brew/graffiti.rb.example`**

```ruby
class Graffiti < Formula
  desc "Fortune-style client for graffiti.moe"
  homepage "https://graffiti.moe"
  url "https://github.com/EXAMPLE/graffiti-moe/archive/refs/tags/v0.1.0.tar.gz"
  sha256 "REPLACE"
  license "MIT"

  depends_on "curl"

  def install
    bin.install "cli/graffiti"
  end

  test do
    assert_match "Usage", shell_output("#{bin}/graffiti help")
  end
end
```

- [ ] **Step 4: Commit**

```bash
git add cli/graffiti brew/graffiti.rb.example tests/cli_smoke.sh
git commit -m "$(cat <<'EOF'
Add graffiti CLI and Homebrew formula stub.

@new **CLI** fetches random graffiti and spraypaints new messages via curl.
EOF
)"
```

---

### Task 13: README + deploy notes

**Files:**
- Modify: `README.md`
- Create: `docs/deploy-dreamhost.md` (short)

**Interfaces:**
- Produces: user-facing docs for curl, CLI, brew tap steps, Dreamhost docroot=`public`, config outside web root, Hover DNS, admin URL

- [ ] **Step 1: Write README sections** — What it is; `curl https://graffiti.moe`; `curl https://graffiti.moe/random?color=always`; brew install from tap; local dev (`composer install`, copy config, `php -S`); admin; security notes (no raw ANSI).

- [ ] **Step 2: Write Dreamhost deploy checklist** — upload/rsync repo; point domain to `public`; place `config/config.php` and `data/` outside or protected; `chmod 700 data`; Let’s Encrypt; verify curl/browser/admin.

- [ ] **Step 3: Final full test**

```bash
./vendor/bin/phpunit
./scripts/smoke.sh   # if present
```

Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add README.md docs/deploy-dreamhost.md
git commit -m "$(cat <<'EOF'
Document local dev and Dreamhost deployment.

@new **Docs** cover curl usage, CLI/brew install, and Dreamhost hosting setup.
EOF
)"
```

---

## Spec coverage checklist (self-review)

| Spec item | Task |
|-----------|------|
| Smart `/` + `/random` | 7, 11 |
| `/add` form + POST API | 8, 9, 11 |
| Recent 10 terminal UI | 9 |
| ASCII art / newlines | 2, 8, 9, 12 |
| Controlled colors + `?color=` | 3, 7, 12 |
| Rate limit + honeypot | 5, 8 |
| Admin delete | 10 |
| SQLite outside web root | 1, 4, 13 |
| Dreamhost / Hover | 13 |
| CLI + brew stub | 12 |
| Empty pool message | 7 |
| Escape/safe output | 2, 3, 7, 9 |

## Placeholder / consistency notes

- Handler namespaces: `Graffiti\Handlers\*`, HTTP: `Graffiti\Http\*`
- Success plain create body: `Sprayed.`
- Honeypot field name: `website`
- CLI subcommand: `spraypaint`
- `Response::plain` always ensures trailing newline

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-02-graffiti-moe.md`. Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
