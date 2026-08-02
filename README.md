# graffiti.moe

A tiny public graffiti wall: anyone can spray a short message; anyone can fetch a random one from the terminal, fortune-style.

- **Web:** Browsers land on `/add` to compose a message and browse the 10 most recent sprays.
- **Terminal:** `curl` (or the `graffiti` CLI) returns one random message as plain text.
- **Moderation:** Password-gated admin at `/admin` for hard deletes.

## curl

Fetch a random message (CLI-like requests to `/` behave the same as `/random`):

```bash
curl https://graffiti.moe
curl https://graffiti.moe/random
```

Add terminal color (server-controlled palette only; see [Security](#security)):

```bash
curl 'https://graffiti.moe/random?color=always'
```

Post a message (plain-text response):

```bash
curl -X POST https://graffiti.moe/add \
  -H 'Accept: text/plain' \
  --data-urlencode 'body=hello wall' \
  --data-urlencode 'color=cyan' \
  --data-urlencode 'bold=1'
```

If the wall is empty, random endpoints return a helpful plain-text hint instead of a message.

## CLI

The repo includes a bash wrapper around `curl`:

```bash
./cli/graffiti              # random message (color when stdout is a TTY)
./cli/graffiti --color=never
./cli/graffiti --color=always
./cli/graffiti spraypaint   # open /add in your default browser
./cli/graffiti spraypaint --color magenta --bold 'hello world'
echo 'ascii art' | ./cli/graffiti spraypaint
```

- **`--color` (read):** `always`, `never`, or `auto` (default). Auto colors when stdout is a TTY unless `NO_COLOR` is set. Requests `?color=always` from the server when coloring.
- **`--color` / `--bold` (spraypaint):** set the message palette (`default`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`) and optional bold — same options as the web form.

Environment overrides:

- `GRAFFITI_URL` — base URL (default `https://graffiti.moe`)
- `GRAFFITI_COLOR` — `always`, `never`, or `auto` (default)

### Homebrew

```bash
brew tap ttscoff/thelab
brew install graffiti
```

CLI source: [ttscoff/graffiti](https://github.com/ttscoff/graffiti). Formula: `ttscoff/thelab`. A mirror of the formula lives at [`brew/graffiti.rb.example`](brew/graffiti.rb.example).

## Local development

Requirements: PHP 8.1+, Composer, curl.

```bash
composer install
cp config/config.example.php config/config.php
mkdir -p data && chmod 700 data
php -S localhost:8080 -t public public/router.php
```

Edit `config/config.php` — set `admin_password`, `ip_hash_secret`, and `base_url` for local use.

Smoke tests (start nothing yourself; scripts spin up a temporary server):

```bash
./scripts/smoke.sh
./tests/cli_smoke.sh
./tests/cli_logo.sh
./vendor/bin/phpunit
```

## Admin

Visit `/admin` and sign in with the password from `config/config.php`. The admin UI lists messages newest-first and supports hard delete. Sessions use the configured `session_name`.

## Security

graffiti.moe is designed for safe terminal output:

- **No raw ANSI from users.** Submitters pick a palette key (`default`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`); the server emits escape codes only when `?color=always` is set on `/` or `/random`.
- **Control-character stripping.** Bodies are sanitized on write: ESC and other control bytes are removed (newlines preserved); max 1000 characters after trim.
- **Rate limiting.** POST `/add` is limited per hashed IP (see config).
- **Honeypot.** The HTML form includes a hidden `website` field; bots that fill it are rejected quietly.
- **Secrets outside the web root.** Keep `config/config.php` and the SQLite file under `data/` out of the public docroot in production (see [docs/deploy-dreamhost.md](docs/deploy-dreamhost.md)).

## Deployment

See [docs/deploy-dreamhost.md](docs/deploy-dreamhost.md) for a Dreamhost + Hover DNS checklist.
