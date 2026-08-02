# Favicon and CLI ANSI logo — Design

**Date:** 2026-08-02  
**Status:** Approved for planning  
**Scope:** Site favicon + ANSI spray-can logo on CLI `--help` / `--version`

## Goal

Use the spray-paint icon as the site favicon (not inline on the page). Show a colored ANSI-art version of the spray can above CLI help and version output when the terminal supports color.

## Favicon

- Source assets: colorful PNG (and SVG if suitable) currently outside the repo (`Downloads` / Cursor assets); copy into `public/assets/` as `favicon.png` and optionally `favicon.svg`.
- Add `<link rel="icon" …>` in `src/views/add.php`, `admin.php`, and `admin_login.php`.
- Do not render the icon in page body content.

## CLI logo

- Source art: `/Users/ttscoff/Downloads/ascii-art.txt` (spray can silhouette).
- Embed in a `print_logo` helper inside the shell script (no sidecar file).
- Color map when coloring is enabled:
  - `%` → red
  - `*` → cyan
  - `#` → magenta
  - `=` → yellow
  - `@` → white / bright default
  - other characters → uncolored
- Coloring follows existing `want_color` rules: TTY + not `--color=never` + empty/`unset` `NO_COLOR` for auto mode; plain ASCII when color is off.
- Print logo, blank line, then existing help or version text for main `--help` / `--version`.
- Spraypaint subcommand `--help` stays text-only (no logo).
- Update both:
  - `cli/graffiti` in this repo
  - `/Users/ttscoff/Sites/dev/graffiti/graffiti` (upstream Homebrew CLI)

## Out of scope

- Showing the icon in the page hero or footer
- Changing Homebrew formula packaging beyond the script itself
- Multi-color message (spraypaint) roadmap item

## Acceptance

- Browser tab shows the favicon on `/add` and admin pages.
- `graffiti --help` and `graffiti --version` show the spray-can art; colors appear on a TTY with auto/always, plain otherwise.
- Both CLI copies stay in sync for the logo behavior.
