# Disclaimer and CLI sections — Design

**Date:** 2026-08-02  
**Status:** Approved for planning  
**Scope:** `/add` page copy and layout only

## Goal

Make posting expectations explicit (no language filter, anonymity, no developer liability, admin removal of hate speech and porn), and document Homebrew CLI install plus curl usage on the public wall page.

## Placement

On `/add` only:

1. **Short notice** under the compose form (before the recent sprays wall) so posters see it before/while posting.
2. **`house rules` section** after the wall — full disclaimer.
3. **`from your terminal` section** after house rules — Homebrew install + curl tips.
4. Retire the existing curl-only footer content once those sections carry it; no separate footer needed unless a tiny remnant remains useful.

Tone: lowercase, terminal-chrome, matching the existing page. No cards, modals, or collapsibles.

## Copy

### Near the form (short)

> No language filter. Posts are anonymous. Don’t spray hate or porn — it gets wiped.

### House rules (full)

> There’s no automated language filtering. Contributions are anonymous. The developer takes no responsibility for what others write. Hate speech and pornographic content will be removed by the admin as quickly as possible.

### From your terminal

Install the CLI with Homebrew:

```
brew tap ttscoff/thelab
brew install graffiti
```

Then `graffiti` for a random spray, or `graffiti spraypaint` to post. No Homebrew? `curl graffiti.moe` (add `?color=always` for color).

Formula name: `graffiti` (tap `ttscoff/thelab`), matching README and `brew/graffiti.rb.example`.

## Implementation

| File | Change |
|------|--------|
| `src/views/add.php` | Short notice under compose; `house rules` + `from your terminal` after wall; remove obsolete footer curl-only blurb |
| `public/assets/style.css` | Quiet section styles (reuse `.wall-title` rhythm; `pre`/`code` for brew; dimmer text for short notice) |
| `tests/AddViewTest.php` | Assert new notice / section content is present |

Out of scope: API, config, admin UI, CLI binary, Homebrew formula repo.

## Acceptance

- Short disclaimer visible near the compose form.
- Full house rules and CLI/Homebrew + curl section visible below the wall.
- Page still matches existing visual language on desktop and mobile.
- Add view tests pass with assertions for the new content.
