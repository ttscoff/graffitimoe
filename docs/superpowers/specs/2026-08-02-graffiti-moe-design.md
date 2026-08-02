# graffiti.moe Design

A tiny public graffiti wall: anyone can spray a short message; anyone can fetch a random one from the terminal (`curl` or a Homebrew `graffiti` CLI), fortune-style.

## Goals

- `curl https://graffiti.moe` (CLI) returns one random message as plain text
- Browsers hitting `/` land on `/add` to submit a message
- `/random` always returns a random plain-text message
- Messages are anonymous (names may be typed into the message body if desired)
- Instant publish with soft moderation (admin can delete)
- Output is safe for terminals: no user-controlled escape sequences or control characters
- Optional controlled color via a server-owned palette (never raw user ANSI)
- Host as cheaply as possible on existing Dreamhost shared hosting
- Offer a Homebrew-installable `graffiti` CLI that reads and writes via curl

## Non-goals (v1)

- User accounts, editing, likes, search, API keys
- Arbitrary ANSI / markup from submitters
- Content classification beyond length, control-char stripping, rate limits, and admin delete
- Paid hosting or serverless platforms

## Architecture

**Stack:** PHP app on Dreamhost shared hosting + SQLite.

**Layout (conceptual):**

- Web-accessible docroot serves the front controller and static assets only
- SQLite database file and local config live **outside** the web root
- Apache `.htaccess` rewrites app routes to the front controller

**Routes:**

| Method | Path | Behavior |
|--------|------|----------|
| `GET` | `/` | Browser-like request → redirect to `/add`. CLI/`curl`-like request → one random message (`text/plain`) |
| `GET` | `/random` | Always one random message (`text/plain`). Supports `?color=never\|always` |
| `GET` | `/add` | HTML compose form + palette picker + 10 most recent messages |
| `POST` | `/add` | Create message (HTML form or machine-friendly body). HTML or plain-text response by `Accept` / content type |
| `GET`/`POST` | `/admin` | Password-gated list + hard-delete |

**CLI detection on `/`:** Treat as browser when `Accept` prefers HTML and/or `User-Agent` looks like a common browser; otherwise serve plain text. `/random` remains the reliable, documentable curl path. Both `/` and `/random` share the same random-message handler.

**CLI tool:** A shell script wrapping `curl`, distributed via the maintainer's Homebrew tap. No runtime dependency beyond `curl`.

```text
Browser -----> GET /  ----------> 302 /add
Browser -----> GET /add --------> HTML form + recent 10
Browser -----> POST /add -------> create message
curl/CLI ----> GET / or /random -> text/plain random message
CLI ---------> POST /add -------> create message (plain response)
Admin ------> /admin -----------> list + delete
                 |
                 v
              SQLite (outside web root)
```

## Data model

**Table `messages`:**

| Column | Type | Notes |
|--------|------|-------|
| `id` | INTEGER PK | Auto-increment |
| `body` | TEXT | Sanitized message, max 500 chars after trim |
| `color` | TEXT | Palette key: `default`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan` |
| `bold` | INTEGER | 0 or 1 |
| `created_at` | TEXT | UTC ISO-8601 timestamp |
| `ip_hash` | TEXT | Hash of submitter IP for rate limiting / abuse; never shown publicly |

Index on `created_at DESC` (supports recent list and admin newest-first).

No author column. Attribution, if any, lives inside `body`.

## Message sanitization and safety

**On write:**

1. Trim leading/trailing whitespace
2. Reject empty / whitespace-only
3. Enforce max length 500 characters (Unicode-aware where practical in PHP)
4. Strip ASCII control characters (U+0000–U+001F, U+007F) and any escape introducers; allow only normal printable text and ordinary spaces. Newlines inside the body: collapse to spaces (single-line fortunes keep curl output clean)
5. Accept `color` only from the allowlisted palette; unknown → `default`
6. Accept `bold` only as boolean
7. Never persist user-supplied ANSI or HTML

**On plain-text read (`/` / `/random` / CLI):**

- `Content-Type: text/plain; charset=utf-8`
- Emit sanitized `body` plus a trailing newline
- If `color=always`, wrap with server-generated SGR sequences derived only from stored `color`/`bold`, then reset (`\033[0m`)
- If `color=never` (default), emit bare text
- Do not implement server-side `color=auto` (server cannot know client TTY state)

**On HTML read (recent list / admin):**

- HTML-escape `body`
- Apply color/bold via CSS classes mapped from the same palette — never by injecting ANSI into HTML

**Why not pass through user ANSI:** Terminals interpret more than color (cursor control, window title, OSC sequences). User-controlled escapes are treated as an injection risk even for `text/plain`.

## Color system

**Palette (v1, whole-message only):** `default`, `red`, `green`, `yellow`, `blue`, `magenta`, `cyan`, each optionally bold.

**Web `/add`:** Palette picker on the compose form; selection submitted with the message.

**Query string:** `GET /random?color=never|always` (same for smart `/`). Default `never` so pipes and logs stay clean.

**CLI color policy:**

- Default: color when stdout is a TTY; plain when piped
- Honor `NO_COLOR`
- Support `--color=always|never|auto`
- When coloring on read, the CLI requests `?color=always` from the server
- `graffiti spraypaint` accepts `--color <name>` and `--bold` matching the web palette

## Rate limiting and abuse

- Rate limit submits by `ip_hash` (target: about 5 submits per 10 minutes per IP; tunable in config)
- Optional honeypot field on the HTML form only (filled → silent discard / fake success)
- Soft moderation: messages go live immediately; admin hard-deletes bad ones
- Random reads may use a looser limit only if needed against scraping; not required for launch

## UX

### Browser `/add`

- Minimal page: brand, short explanation, compose field, palette + bold controls, submit
- Success: quiet confirmation on the same page
- Errors: inline (too long, rate limited, etc.)
- Below the form: **10 most recent** messages
- Each recent message in a small terminal-chrome frame, monospace body, CSS color from palette
- Purpose: social proof that encourages spraying your own

### Empty pool

If there are no messages, plain-text random endpoints return a fixed fallback line, e.g.:

`The wall is blank. Be the first: https://graffiti.moe/add`

### Admin `/admin`

- Shared password from local config; HTML password form sets an HTTP session cookie
- Newest-first list with delete actions
- No public link in the main UI; URL is enough obscurity plus password

### CLI `graffiti`

| Invocation | Behavior |
|------------|----------|
| `graffiti` | Print one random message (fortune-style) |
| `graffiti spraypaint "…"` | POST a new message |
| `echo "…" \| graffiti spraypaint` | Message from stdin when no argument |
| `graffiti spraypaint --color red --bold "…"` | Submit with palette options |
| `graffiti help` / `-h` | Usage |
| `--color=always\|never\|auto` | Output coloring for read path |

Config: default base URL `https://graffiti.moe`, overridable via `GRAFFITI_URL` for local testing.

Exit non-zero on HTTP/network/validation failures; print brief errors appropriately (message on stdout for success reads; errors preferably stderr).

## Machine-friendly POST `/add`

Support at least:

- HTML form posts from the browser
- CLI posts via `curl` (`application/x-www-form-urlencoded` or `text/plain` body + query/header/form fields for `color`/`bold`)

Responses:

- Browser form: HTML redirect/render with flash status
- CLI/`Accept: text/plain`: short plain-text success or error + suitable status codes (`201` on create, `400`, `429`)

## Error handling

| Case | Response |
|------|----------|
| Invalid body / color | `400` |
| Rate limited | `429` |
| Admin unauthorized | `401` / `403` |
| Server/DB failure | `500` with a boring safe message; no stack traces to clients |

## Hosting and deployment

- Dreamhost shared hosting for `graffiti.moe`
- Hover DNS points the domain at Dreamhost; HTTPS via Dreamhost Let’s Encrypt
- Local config (admin password, DB path, rate-limit knobs, IP hash secret) not committed to git
- Deploy via git/SSH/rsync as convenient for Dreamhost

## Homebrew distribution

- Shell script lives in this repo (e.g. `cli/graffiti`)
- Formula in the maintainer's existing tap installs the script to `bin/graffiti`
- Declare dependency on `curl` as required by tap conventions
- Version via git tags / tap version bumps

## Testing

- Sanitize/length/palette validation tests
- Random selection + empty-pool fallback
- Submit + rate limit behavior
- Color output only when `color=always`, and only allowlisted SGR
- HTML escaping on recent list
- Admin delete
- CLI smoke against PHP built-in server or mocked HTTP
- Manual checks: browser form, `curl` `/` and `/random`, colored vs plain, brew-formula install path sanity

## Follow-ups (explicitly later)

- Richer in-message color spans (still server-mapped, never raw ANSI)
- Stronger anti-spam if needed
- Alternate hosts only if Dreamhost becomes painful

## Decisions log

- Soft moderation (instant publish + admin delete)
- Anonymous only; names optional inside message text
- Max length 500 characters
- Smart `/` plus explicit `/random`
- Dreamhost + PHP + SQLite
- Recent 10 on `/add` in terminal-chrome frames
- Controlled palette colors; default plain-text output; opt-in `?color=always`
- Homebrew shell CLI with `spraypaint` subcommand
