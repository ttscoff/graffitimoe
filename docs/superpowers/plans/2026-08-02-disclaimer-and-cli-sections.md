# Disclaimer and CLI Sections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a short posting notice, full house-rules disclaimer, and Homebrew/curl CLI docs to the `/add` page.

**Architecture:** Pure view + CSS change on the existing `render_add` template. No handlers, config, or API changes. Coverage via `AddViewTest` string assertions on rendered HTML.

**Tech Stack:** PHP 8.1+ view (`src/views/add.php`), existing `public/assets/style.css`, PHPUnit.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-02-disclaimer-and-cli-sections-design.md`
- Copy must match the approved strings (lowercase, terminal tone)
- Formula: `brew tap ttscoff/thelab` then `brew install graffiti`
- No cards, modals, or collapsibles
- Out of scope: API, config, admin UI, CLI binary, Homebrew formula repo
- Commit messages: subject + `@new`/`@changed`/`@fixed`/`@improved` body lines with `**bold**` topics (no Co-authored-by)

---

## File Structure

```text
src/views/add.php              # short notice + house rules + CLI sections; remove old footer
public/assets/style.css        # .compose-notice, .site-section, .cli-block styles
tests/AddViewTest.php          # assertions for new content
```

**Responsibility notes:**
- `add.php` owns markup and copy
- `style.css` owns quiet section styling that reuses `.wall-title` rhythm
- `AddViewTest` only checks rendered HTML contains the required strings/classes

---

### Task 1: Failing tests for disclaimer and CLI content

**Files:**
- Modify: `tests/AddViewTest.php`
- Test: `tests/AddViewTest.php`

**Interfaces:**
- Consumes: `render_add(array $vars): string` from `src/views.php`
- Produces: failing assertions that require notice / house rules / CLI markup in the add view

- [ ] **Step 1: Write the failing test**

Add this method to `tests/AddViewTest.php` inside `AddViewTest`:

```php
public function test_add_view_includes_disclaimer_and_cli_sections(): void
{
    $html = render_add([
        'recent' => [],
        'ok' => false,
        'error' => null,
        'colors' => \Graffiti\MessageSanitizer::COLORS,
    ]);

    $this->assertStringContainsString('compose-notice', $html);
    $this->assertStringContainsString('No language filter. Posts are anonymous.', $html);
    $this->assertStringContainsString('house rules', $html);
    $this->assertStringContainsString('no automated language filtering', $html);
    $this->assertStringContainsString('takes no responsibility', $html);
    $this->assertStringContainsString('Hate speech and pornographic content', $html);
    $this->assertStringContainsString('from your terminal', $html);
    $this->assertStringContainsString('brew tap ttscoff/thelab', $html);
    $this->assertStringContainsString('brew install graffiti', $html);
    $this->assertStringContainsString('graffiti spraypaint', $html);
    $this->assertStringContainsString('curl graffiti.moe', $html);
    $this->assertStringContainsString('color=always', $html);
    $this->assertStringNotContainsString('Want color?', $html);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/AddViewTest.php --filter test_add_view_includes_disclaimer_and_cli_sections`

Expected: FAIL — at least one `assertStringContainsString` fails because the markup is missing.

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/AddViewTest.php
git commit -m "$(cat <<'EOF'
Add failing test for disclaimer and CLI sections.

@new **Add view coverage** for house rules notice and Homebrew/curl CLI docs.
EOF
)"
```

---

### Task 2: Markup and styles for notice, house rules, and CLI

**Files:**
- Modify: `src/views/add.php`
- Modify: `public/assets/style.css`
- Test: `tests/AddViewTest.php`

**Interfaces:**
- Consumes: existing `.wall-title` visual rhythm; approved copy from the design spec
- Produces: rendered HTML that satisfies `test_add_view_includes_disclaimer_and_cli_sections`

- [ ] **Step 1: Add short notice under the compose form**

In `src/views/add.php`, immediately after the closing `</form>` (before `<section class="wall">`), insert:

```php
  <p class="compose-notice">No language filter. Posts are anonymous. Don&rsquo;t spray hate or porn &mdash; it gets wiped.</p>
```

- [ ] **Step 2: Replace the footer with house rules + CLI sections**

Replace the existing `<footer class="footer">...</footer>` block with:

```php
  <section class="site-section" id="house-rules">
    <h2 class="wall-title">house rules</h2>
    <p class="site-section-body">There&rsquo;s no automated language filtering. Contributions are anonymous. The developer takes no responsibility for what others write. Hate speech and pornographic content will be removed by the admin as quickly as possible.</p>
  </section>

  <section class="site-section" id="cli">
    <h2 class="wall-title">from your terminal</h2>
    <p class="site-section-body">Install the CLI with Homebrew:</p>
    <pre class="cli-block"><code>brew tap ttscoff/thelab
brew install graffiti</code></pre>
    <p class="site-section-body">Then <code>graffiti</code> for a random spray, or <code>graffiti spraypaint</code> to post. No Homebrew? <code>curl graffiti.moe</code> (add <code>?color=always</code> for color).</p>
  </section>
```

Do not leave the old footer paragraphs (`Want color?` / curl-only tip block).

- [ ] **Step 3: Add CSS for notice and sections**

In `public/assets/style.css`, replace the `/* ---- Footer ---- */` block (`.footer` rules) with:

```css
/* ---- Compose notice ---- */

.compose-notice {
  margin: 1rem 0 0;
  text-align: center;
  color: var(--text-dim);
  font-size: 0.75rem;
  line-height: 1.45;
}

/* ---- Site sections (house rules / CLI) ---- */

.site-section {
  margin-top: 2.5rem;
  text-align: center;
}

.site-section-body {
  margin: 0.5rem auto 0;
  max-width: 36rem;
  color: var(--text-dim);
  font-size: 0.85rem;
  line-height: 1.5;
}

.site-section code {
  color: var(--electric-cyan);
}

.cli-block {
  margin: 0.85rem auto 0;
  max-width: 28rem;
  padding: 0.85rem 1rem;
  text-align: left;
  background: var(--panel);
  border: 1px solid var(--panel-border);
  border-radius: 6px;
  font-family: var(--font-mono);
  font-size: 0.8rem;
  line-height: 1.5;
  color: var(--electric-cyan);
  overflow-x: auto;
}

.cli-block code {
  color: inherit;
  background: none;
  padding: 0;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/AddViewTest.php`

Expected: PASS (all methods in the file, including the new one).

Also run: `./vendor/bin/phpunit`

Expected: PASS for the full suite.

- [ ] **Step 5: Manual spot-check (optional but recommended)**

Serve locally if already set up, open `/add`, confirm:

1. Short notice sits under the form, above recent sprays.
2. `house rules` and `from your terminal` appear below the wall.
3. Brew commands are readable on a narrow viewport.

- [ ] **Step 6: Commit**

```bash
git add src/views/add.php public/assets/style.css
git commit -m "$(cat <<'EOF'
Add house rules and CLI install sections on /add.

@new **House rules** short form notice and full disclaimer about anonymity and content removal.
@new **CLI docs** Homebrew install plus curl fallback on the wall page.
EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Short notice under compose form | Task 2 Step 1 |
| Full house rules after wall | Task 2 Step 2 |
| From your terminal (Homebrew + curl) | Task 2 Step 2 |
| Retire old curl-only footer | Task 2 Step 2 + test asserts no `Want color?` |
| Quiet CSS, wall-title rhythm | Task 2 Step 3 |
| AddViewTest coverage | Task 1 |
| Formula `graffiti` / tap `ttscoff/thelab` | Task 2 Step 2 copy |
