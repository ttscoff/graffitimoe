Title: graffiti.moe build notes
remote_host: dh
remote_dir: ~/graffiti.moe/

# graffiti.moe

## Project Structure

PHP/SQLite graffiti wall: browsers compose on `/add`, terminals curl a random message.

- **`public/`** — Web root (`index.php` front controller, `.htaccess`, `assets/`, `router.php` for local PHP server)
- **`src/`** — App code (`Handlers/`, `Http/`, sanitizer, color, DB, rate limit, session, views)
- **`config/`** — `config.example.php` (committed); `config.php` (local/prod secrets, gitignored)
- **`data/`** — SQLite DB outside the web root (gitignored `*.sqlite*`)
- **`cli/graffiti`** — Homebrew-friendly curl wrapper (`spraypaint` subcommand)
- **`brew/graffiti.rb.example`** — Formula stub for your tap
- **`tests/`** — PHPUnit + `cli_smoke.sh`, `cli_logo.sh`
- **`scripts/smoke.sh`** — Local HTTP smoke against a temp server
- **`docs/`** — Specs, plans, `deploy-dreamhost.md`
- **`composer.json`** — PHP >= 8.1, PHPUnit for tests

**Requirements:** PHP 8.1+, Composer, curl. Production: Dreamhost docroot must be `public/`.

## Install Dependencies

Install Composer packages (includes PHPUnit).

@run(composer install)

## Install Dependencies Production

Install production autoloader only (use on the server after rsync, or locally before uploading `vendor/`).

@run(composer install --no-dev --optimize-autoloader)

## Setup Local Config

Copy the example config, create the data directory, and open the secrets file for editing.

```run
#!/bin/bash

cp -n config/config.example.php config/config.php
mkdir -p data
chmod 700 data
${EDITOR:-nano} config/config.php
```

Set at least `admin_password`, `ip_hash_secret` (`openssl rand -hex 32`), and `base_url`.

## Generate IP Hash Secret

Print a 256-bit hex secret for `ip_hash_secret` in `config/config.php`.

@run(openssl rand -hex 32)

## Serve Locally (port)

Start the PHP built-in server on port 8080 or custom port (docroot `public/`, custom router).

@run(php -S localhost:${port:8080} -t public public/router.php)

## Test PHPUnit

Run the full PHPUnit suite.

@run(./vendor/bin/phpunit)

## Test Smoke

HTTP smoke test (starts a temporary server; does not mutate `config/config.php`).

@run(./scripts/smoke.sh)

## Test CLI Smoke

CLI smoke against a temporary local server.

@run(./tests/cli_smoke.sh)

## Test CLI Logo

CLI logo smoke (help/version output, no server required).

@run(./tests/cli_logo.sh)

## Test All

Run PHPUnit, then all smoke scripts.

@include(Test PHPUnit)
@include(Test Smoke)
@include(Test CLI Smoke)
@include(Test CLI Logo)

## Deploy Rsync

Rsync the app to Dreamhost. Excludes `.git`, local secrets, SQLite data, `vendor/`, caches, and other detritus. Does **not** overwrite remote `config/config.php` or `data/*.sqlite*`.

Confirm the remote path exists and the Dreamhost web directory for `graffiti.moe` points at `[%remote_dir]public/`.

Dreamhost has no global `composer` binary (`compose` is unrelated). Either run **Deploy Rsync With Vendor** after a local production install, or rsync without `vendor/` and use **Deploy Server Composer**.

```run
#!/bin/bash

rsync -avz \
  --exclude '.git/' \
  --exclude '.worktrees/' \
  --exclude '.superpowers/' \
  --exclude '.cursor/' \
  --exclude '.github/' \
  --exclude 'vendor/' \
  --exclude 'config/config.php' \
  --exclude 'data/*.sqlite' \
  --exclude 'data/*.sqlite-journal' \
  --exclude 'data/*.sqlite-wal' \
  --exclude 'data/*.sqlite-shm' \
  --exclude '.phpunit.cache/' \
  --exclude '.phpunit.result.cache' \
  --exclude '.DS_Store' \
  --exclude 'commit_message.txt' \
  --exclude 'docs/superpowers/' \
  --exclude '.howzit*' \
  ./ [%remote_host]:[%remote_dir]
```

## Deploy Rsync With Vendor

Build production `vendor/` locally, then rsync **including** `vendor/` so the server does not need Composer. Still excludes secrets and SQLite data.

```run
#!/bin/bash

composer install --no-dev --optimize-autoloader
rsync -avz \
  --exclude '.git/' \
  --exclude '.worktrees/' \
  --exclude '.superpowers/' \
  --exclude '.cursor/' \
  --exclude '.github/' \
  --exclude 'config/config.php' \
  --exclude 'data/*.sqlite' \
  --exclude 'data/*.sqlite-journal' \
  --exclude 'data/*.sqlite-wal' \
  --exclude 'data/*.sqlite-shm' \
  --exclude '.phpunit.cache/' \
  --exclude '.phpunit.result.cache' \
  --exclude '.DS_Store' \
  --exclude 'commit_message.txt' \
  --exclude 'docs/superpowers/' \
  --exclude '.howzit*' \
  ./ [%remote_host]:[%remote_dir]
```

## Deploy Server Composer

Install PHP Composer on Dreamhost if needed, then `composer install` in the remote app dir. Dreamhost does not ship a `composer` command; use `php composer.phar` (see [DreamHost docs](https://help.dreamhost.com/hc/en-us/articles/214899037-Installing-Composer-overview)).

```run
#!/bin/bash

ssh [%remote_host] 'cd [%remote_dir] && \
  if [ ! -f composer.phar ]; then curl -sS https://getcomposer.org/installer | php; fi && \
  php composer.phar install --no-dev --optimize-autoloader'
```

## Deploy First-Time Config

One-time remote setup: copy example config (if missing), create `data/`, set permissions. Edit secrets on the server afterward (`admin_password`, `ip_hash_secret`, `base_url`).

```run
#!/bin/bash

ssh [%remote_host] 'cd [%remote_dir] && \
  test -f config/config.php || cp config/config.example.php config/config.php && \
  mkdir -p data && chmod 700 data && \
  echo "Edit config/config.php on the server, then enable Lets Encrypt and point the web directory to public/"'
```

## Deploy Checklist

Manual Dreamhost + Hover steps (no automated run):

- **Web directory** for `graffiti.moe` → preferably `[%remote_dir]public/`. If it must stay `[%remote_dir]`, rely on the repo-root `.htaccess` (rewrites into `public/`, blocks `config/`/`data/`/…)
- **Layout:** `data/` is `[%remote_dir]data/` (sibling of `public/` and `config/`, via config `../data`)
- **PHP** 8.1+ for that domain
- **`config/config.php`** on the server with production secrets (`openssl rand -hex 32` for `ip_hash_secret`)
- **`data/`** writable by the web user (`chmod 700 data`)
- **Let's Encrypt** enabled for `graffiti.moe`
- **Hover DNS** A record `@` → Dreamhost IP (or Dreamhost nameservers)
- Verify: `curl https://graffiti.moe/random`, browser `/add`, `/admin`
- Confirm `config/` and `data/` return 403: `curl -sI https://graffiti.moe/config/config.php`

See `docs/deploy-dreamhost.md` for the full write-up.

## Install CLI Locally

Symlink the in-repo CLI onto your PATH for quick local use (no Homebrew).

@run(ln -sf "$PWD/cli/graffiti" /usr/local/bin/graffiti)

## Install CLI Homebrew

Install from `ttscoff/thelab` (formula fetches https://github.com/ttscoff/graffiti).

```run
#!/bin/bash

brew tap ttscoff/thelab
brew install graffiti
graffiti --version
```

## Verify Production Random

Fetch a random message from production.

@run(curl -fsS https://graffiti.moe/random)

## Verify Production Color

Fetch a colored random message from production.

@run(curl -fsS 'https://graffiti.moe/random?color=always')

## Cursor Commands (command)

/accessibility-audit
: WCAG-oriented accessibility audit of the current UI

/add-documentation
: Add documentation for the current code or feature

/add-error-handling
: Add robust error handling while preserving behavior

/address-github-pr-comments
: Process PR review feedback and apply fixes

/apple-changes
: Extract Apple-facing release notes from CHANGELOG.md

/blogpost
: Draft a casual technical blog post from the work

/buildnotes
: Regenerate or update this `buildnotes.md` file

/changelog
: Generate a `commit_message.txt` with `@tags` for staged and unstaged changes

/clarify-task
: Clarify requirements before coding

/code-review
: Thorough code review for functionality and maintainability

/commit
: Commit helper (see command file)

/create-pr
: Create a structured pull request

/database-migration
: Create or manage database migrations

/debug-issue
: Systematic debugging walkthrough

/deslop
: Remove AI slop from the diff against main

/diagrams
: Generate Mermaid diagrams for architecture or concepts

/docker-logs
: Tail Docker container logs

/email-markdown
: Format the last email response as raw Markdown

/fish-setup
: Prefer the Fish shell for terminal commands

/fix-compile-errors
: Analyze and fix compilation errors

/fix-git-issues
: Resolve common Git problems

/generate-api-docs
: Generate API documentation (OpenAPI or project style)

/generate-pr-description
: Write a PR description from the branch diff

/git-commit
: Create a short commit message and commit staged changes

/git-push
: Push the current branch to origin

/ithoughts
: Work with an iThoughts `.itmz` mind map

/light-review-existing-diffs
: Quick quality pass on current diffs

/lint-fix
: Lint and fix the current file

/lint-suite
: Run project linters and apply fixes

/markdown-lint
: Lint project Markdown files

/onboard-new-developer
: Onboarding checklist for a new developer

/optimize-performance
: Find and recommend performance improvements

/overview
: Generate Mermaid product overview diagrams

/prefix_commands
: Use mise for Ruby/Python/Node commands

/readme
: Update README (or `_src/_README.md` if present)

/refactor-code
: Refactor selected code without changing behavior

/roadmap
: Generate a visual feature roadmap

/run-all-tests-and-fix
: Run the full test suite and fix failures

/security-audit
: Security review and remediation

/security-review
: Security review with concrete code fixes

/setup-new-feature
: Plan and scaffold a new feature

/visualize
: Mermaid data-lineage visualization

/wip
: Write a WIP commit message to `commit_message.txt`

/write-unit-tests
: Create unit tests for the current code

@run(cursor-agent --output-format text --print .cursor/commands/${command:changelog}.md && afplay /System/Library/Sounds/Hero.aiff)
