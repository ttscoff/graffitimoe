# Paint style preservation — Design

**Date:** 2026-08-08  
**Status:** Approved for planning  
**Scope:** Preserve per-character paint colors/bold across paint ↔ edit mode switches, including text edits

## Goal

Leaving paint mode for edit must not throw away painted styles. Re-entering paint restores previous colors (and bold), remapped onto the current text if the user edited in between.

## Behavior

- On exit paint (or whenever painted state is current): save `paintSnapshot = { text, colors[], bolds[] }` (Unicode code points via `Array.from`).
- Exit still clears `spans` for simple spray (mono message color). Spray from paint mode continues to sync spans as today.
- On enter paint:
  - If snapshot exists → remap styles from snapshot text onto `textarea.value`.
  - Else → `initCharStylesFromBody()` (message color + bold) as today.
- Remap algorithm (character-level alignment):
  - Align old snapshot text to new text (LCS/diff over code points).
  - Matched characters keep color + bold from the snapshot.
  - Inserted characters get `selectedColor()` and the current bold checkbox state.
  - Deleted characters drop out with their styles.
- Remap runs on enter paint only (not on every keystroke in edit mode).
- Limit remains ≤1000 characters; O(n²) LCS is acceptable at that size.

## Implementation

| File | Change |
|------|--------|
| `public/assets/compose.js` | Snapshot on exit; remap on enter; stop wiping paints without snapshot |
| Optional: extract pure `remapStyles(oldText, oldColors, oldBolds, newText, insertColor, insertBold)` for testability |
| Tests | Prefer a small Node/PHPUnit-free check or extract remap to a testable function if the repo has a JS test path; otherwise document manual smoke |

## Out of scope

- Showing multi-color preview in the plain textarea while in edit mode
- Server-side changes
- Undoing individual paint strokes

## Acceptance

- Paint some chars → leave paint → re-enter paint without editing → same colors/bolds.
- Paint → leave → edit text (insert/delete) → re-enter → surviving chars keep styles; new chars use message color/bold.
- Simple spray after leaving paint still posts without spans (unless user re-enters paint and sprays from paint mode).
