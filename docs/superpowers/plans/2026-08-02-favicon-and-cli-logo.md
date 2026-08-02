# Favicon and CLI ANSI Logo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the spray-paint icon as the site favicon, show a TTY-aware ANSI spray-can logo on CLI `--help`/`--version`, and ship graffiti CLI `0.1.2` (release + Homebrew formula).

**Architecture:** Static favicon under `public/assets/` linked from PHP views. CLI embeds ASCII art and a `print_logo` helper that reuses `want_color`. Upstream `ttscoff/graffiti` is the release source; this repo’s `cli/graffiti` stays in sync; `brew/graffiti.rb.example` and live `ttscoff/homebrew-thelab` Formula track the new tarball.

**Tech Stack:** PHP views, bash CLI, GitHub Releases (`gh`), Homebrew formula Ruby.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-02-favicon-and-cli-logo-design.md`
- Color map: `%` red, `*` cyan, `#` magenta, `=` yellow, `@` white; other chars uncolored
- Color only when `want_color` is true (TTY / `--color=always`; off for `--color=never` / `NO_COLOR` in auto)
- Logo on main `--help` and `--version` only; spraypaint `--help` stays text-only
- Version bump: `0.1.1` → `0.1.2`
- Update both `cli/graffiti` (graffitimoe) and `/Users/ttscoff/Sites/dev/graffiti/graffiti`
- Homebrew tap path: `/Users/ttscoff/Desktop/Code/homebrew-thelab/Formula/graffiti.rb`
- Favicon in tab only — not in page body
- Commit messages: subject + `@new`/`@changed`/`@fixed`/`@improved` body with `**bold**` topics

---

## File Structure

```text
public/assets/favicon.png          # 150x150 colorful icon
src/views/add.php                  # <link rel="icon">
src/views/admin.php
src/views/admin_login.php
cli/graffiti                       # print_logo + VERSION 0.1.2
tests/cli_logo.sh                  # smoke checks for logo / version / color
brew/graffiti.rb.example           # url/sha/version → 0.1.2

# Upstream
/Users/ttscoff/Sites/dev/graffiti/graffiti
/Users/ttscoff/Desktop/Code/homebrew-thelab/Formula/graffiti.rb
```

---

### Task 1: Favicon assets and view links

**Files:**
- Create: `public/assets/favicon.png`
- Modify: `src/views/add.php`, `src/views/admin.php`, `src/views/admin_login.php`, `tests/AddViewTest.php`

**Interfaces:**
- Consumes: `/Users/ttscoff/.cursor/projects/Users-ttscoff-Sites-dev-graffitimoe/assets/spray-paint-svgrepo-com-f819c627-ec51-4e22-8ccf-3b9d860896e1.png` (150×150)
- Produces: `/assets/favicon.png` linked from all HTML views

- [ ] **Step 1: Copy favicon into public assets**

```bash
cp "/Users/ttscoff/.cursor/projects/Users-ttscoff-Sites-dev-graffitimoe/assets/spray-paint-svgrepo-com-f819c627-ec51-4e22-8ccf-3b9d860896e1.png" \
  /Users/ttscoff/Sites/dev/graffitimoe/public/assets/favicon.png
```

Do **not** use the black outline SVG from Downloads as the favicon (it does not match the colorful mark). Skip `favicon.svg` unless a matching colorful SVG is available.

- [ ] **Step 2: Add icon link to all views**

In each of `src/views/add.php`, `src/views/admin.php`, `src/views/admin_login.php`, inside `<head>` before `</head>`:

```html
<link rel="icon" href="/assets/favicon.png" type="image/png">
```

- [ ] **Step 3: Assert favicon link in AddViewTest**

In `tests/AddViewTest.php`, assert on rendered HTML:

```php
$this->assertStringContainsString('/assets/favicon.png', $html);
```

Run: `./vendor/bin/phpunit tests/AddViewTest.php`  
Expected: PASS

- [ ] **Step 4: Commit (graffitimoe)**

```bash
git add public/assets/favicon.png src/views/add.php src/views/admin.php src/views/admin_login.php tests/AddViewTest.php
git commit -m "$(cat <<'EOF'
Add site favicon from spray-paint icon.

@new **Favicon** colorful spray mark on /add and admin tabs.
EOF
)"
```

---

### Task 2: ANSI logo in graffitimoe CLI + smoke test

**Files:**
- Modify: `cli/graffiti`
- Create: `tests/cli_logo.sh`

**Interfaces:**
- Consumes: existing `want_color`; art from `/Users/ttscoff/Downloads/ascii-art.txt`
- Produces: `print_logo`; `--help` / `--version` print logo then body; `VERSION=0.1.2`

- [ ] **Step 1: Write failing smoke test**

Create `tests/cli_logo.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cli="$root/cli/graffiti"

plain="$("$cli" --color=never --version)"
[[ "$plain" == *$'\n'* ]] || { echo "expected multiline version with logo"; exit 1; }
[[ "$plain" == *"@@@@@@@"* ]] || { echo "missing can body in logo"; exit 1; }
[[ "$plain" == *"graffiti 0.1.2"* ]] || { echo "missing version line"; exit 1; }
[[ "$plain" != *$'\033'* ]] || { echo "plain version should have no ANSI"; exit 1; }

help_plain="$("$cli" --color=never --help)"
[[ "$help_plain" == *"@@@@@@@"* ]] || { echo "missing logo on help"; exit 1; }
[[ "$help_plain" == *"Usage:"* ]] || { echo "missing usage"; exit 1; }

spray_help="$("$cli" spraypaint --help)"
[[ "$spray_help" != *"@@@@@@@"* ]] || { echo "spraypaint help should not show logo"; exit 1; }

colored="$("$cli" --color=always --version)"
[[ "$colored" == *$'\033[31m'* ]] || { echo "expected red ANSI for %"; exit 1; }
[[ "$colored" == *$'\033[36m'* ]] || { echo "expected cyan ANSI for *"; exit 1; }
[[ "$colored" == *$'\033[35m'* ]] || { echo "expected magenta ANSI for #"; exit 1; }
[[ "$colored" == *$'\033[33m'* ]] || { echo "expected yellow ANSI for ="; exit 1; }

echo "CLI logo smoke test passed."
```

```bash
chmod +x tests/cli_logo.sh
./tests/cli_logo.sh
```

Expected: FAIL (version still 0.1.1 / no logo).

- [ ] **Step 2: Implement `print_logo` and wire help/version**

In `cli/graffiti`:

1. Set `VERSION="0.1.2"`.
2. After `want_color`, add:

```bash
print_logo() {
    local art
    art=$(cat <<'EOF'
                               
     %%%    @@@@@@@            
            @@@@@@@            
  ***   ###    @@@@            
     ===    @@@@@@@            
         @@@@@@@@@@@@@         
        @@@@@@@@@@@@@@@        
        @          @@@@        
        @          @@@@        
        @          @@@@        
        @          @@@@        
        @          @@@@        
        @          @@@@        
        @@@@@@@@@@@@@@@        
        @@@@@@@@@@@@@@@        
         @@@@@@@@@@@@@         
                               
EOF
)
    if ! want_color; then
        printf '%s\n' "$art"
        return
    fi

    local red=$'\033[31m' cyan=$'\033[36m' magenta=$'\033[35m'
    local yellow=$'\033[33m' white=$'\033[37m' reset=$'\033[0m'
    local line i c out
    while IFS= read -r line || [[ -n "$line" ]]; do
        out=""
        for (( i = 0; i < ${#line}; i++ )); do
            c="${line:i:1}"
            case "$c" in
                %) out+="${red}%${reset}" ;;
                \*) out+="${cyan}*${reset}" ;;
                \#) out+="${magenta}#${reset}" ;;
                =) out+="${yellow}=${reset}" ;;
                @) out+="${white}@${reset}" ;;
                *) out+="$c" ;;
            esac
        done
        printf '%s\n' "$out"
    done <<<"$art"
}
```

3. Change `usage` to print logo first:

```bash
usage() {
    print_logo
    printf '\n'
    cat <<'EOF'
Usage:
  ...
EOF
}
```

4. Change `--version` handler:

```bash
-V|--version|version)
    print_logo
    printf '\n'
    echo "graffiti ${VERSION}"
    exit 0
    ;;
```

Leave spraypaint’s internal `--help` unchanged (no `print_logo`).

- [ ] **Step 3: Re-run smoke test**

```bash
./tests/cli_logo.sh
```

Expected: PASS

Also run `./vendor/bin/phpunit` in graffitimoe.

- [ ] **Step 4: Commit (graffitimoe)**

```bash
git add cli/graffiti tests/cli_logo.sh
git commit -m "$(cat <<'EOF'
Add ANSI spray-can logo to CLI help and version.

@new **CLI logo** TTY-colored spray-can art on --help and --version.
@changed **CLI version** bump to 0.1.2.
EOF
)"
```

---

### Task 3: Sync upstream graffiti, release 0.1.2, update Homebrew

**Files:**
- Modify: `/Users/ttscoff/Sites/dev/graffiti/graffiti`
- Modify: `brew/graffiti.rb.example` (graffitimoe)
- Modify: `/Users/ttscoff/Desktop/Code/homebrew-thelab/Formula/graffiti.rb`

**Interfaces:**
- Consumes: Task 2 `cli/graffiti` as source of truth
- Produces: GitHub release `v0.1.2` asset `graffiti-0.1.2.tar.gz`; formulas updated

- [ ] **Step 1: Sync script into upstream repo and commit**

```bash
cp /Users/ttscoff/Sites/dev/graffitimoe/cli/graffiti /Users/ttscoff/Sites/dev/graffiti/graffiti
chmod +x /Users/ttscoff/Sites/dev/graffiti/graffiti
cd /Users/ttscoff/Sites/dev/graffiti
./graffiti --color=never --version | tail -1
# expect: graffiti 0.1.2
```

Commit and push in **graffiti**:

```bash
git add graffiti
git commit -m "$(cat <<'EOF'
Add ANSI logo and bump to 0.1.2.

@new **CLI logo** TTY-colored spray-can art on --help and --version.
@changed **Version** 0.1.2 for Homebrew release.
EOF
)"
git push origin HEAD
```

- [ ] **Step 2: Build tarball and create GitHub release**

```bash
cd /Users/ttscoff/Sites/dev/graffiti
tmpdir="$(mktemp -d)"
mkdir "$tmpdir/graffiti-0.1.2"
cp graffiti LICENSE README.md "$tmpdir/graffiti-0.1.2/"
tar -C "$tmpdir" -czf "$tmpdir/graffiti-0.1.2.tar.gz" graffiti-0.1.2
shasum -a 256 "$tmpdir/graffiti-0.1.2.tar.gz"
# record SHA256

gh release create v0.1.2 \
  "$tmpdir/graffiti-0.1.2.tar.gz" \
  --title "v0.1.2" \
  --notes "$(cat <<'EOF'
ANSI spray-can logo on --help and --version (TTY-aware colors).
EOF
)"
```

Verify: `https://github.com/ttscoff/graffiti/releases/download/v0.1.2/graffiti-0.1.2.tar.gz`

- [ ] **Step 3: Update formula example in graffitimoe**

In `brew/graffiti.rb.example`:

```ruby
  url "https://github.com/ttscoff/graffiti/releases/download/v0.1.2/graffiti-0.1.2.tar.gz"
  sha256 "<SHA256 from Step 2>"
  version "0.1.2"
```

And in `test do`, assert version `0.1.2` (keep Usage assertion).

Commit in graffitimoe.

- [ ] **Step 4: Update live homebrew-thelab formula**

Edit `/Users/ttscoff/Desktop/Code/homebrew-thelab/Formula/graffiti.rb` with the same url/sha256/version/test as Step 3. Commit and push that tap.

Smoke:

```bash
brew update
brew upgrade graffiti || brew reinstall ttscoff/thelab/graffiti
graffiti --version
```

Expected: logo + `graffiti 0.1.2`.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Favicon PNG + link tags | Task 1 |
| Not in page body | Task 1 |
| Embedded ANSI art + color map | Task 2 |
| TTY / want_color gating | Task 2 |
| Logo on --help / --version only | Task 2 |
| Sync both CLI copies | Task 2 + Task 3 |
| Ship 0.1.2 + Homebrew | Task 3 |
