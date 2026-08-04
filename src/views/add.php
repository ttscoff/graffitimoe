<?php

declare(strict_types=1);

use Graffiti\Color;

/**
 * @var list<array{id:int,body:string,color:string,bold:bool,spans:?list<array{t:string,c:string}>,created_at:string}> $recent
 * @var bool $ok
 * @var string|null $error
 * @var list<string> $colors
 * @var bool $isAdmin
 * @var list<int> $ownedIds
 * @var string $csrfToken
 * @var list<int> $flaggedIds
 */
$isAdmin = $isAdmin ?? false;
$ownedIds = $ownedIds ?? [];
$csrfToken = $csrfToken ?? '';
$flaggedIds = $flaggedIds ?? [];
$ownedLookup = array_fill_keys($ownedIds, true);
$flaggedLookup = array_fill_keys($flaggedIds, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>graffiti.moe &mdash; spray something</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Permanent+Marker&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
<link rel="icon" href="/assets/favicon.png" type="image/png">
</head>
<body>
<div class="page">

  <header class="hero">
    <h1 class="brand">graffiti<span class="brand-dot">.</span>moe</h1>
    <p class="tagline">a public wall for anonymous, terminal-friendly graffiti &mdash; spray a line, <code>curl</code> a random one back.</p>
  </header>

  <?php if ($ok): ?>
    <p class="flash flash-ok">sprayed. it&rsquo;s on the wall below.</p>
  <?php elseif ($error === 'action'): ?>
    <p class="flash flash-error">that action didn&rsquo;t go through &mdash; refresh and try again.</p>
  <?php elseif ($error !== null && $error !== ''): ?>
    <p class="flash flash-error">couldn&rsquo;t spray that &mdash; too short, too long, empty, or you&rsquo;re going too fast. try again.</p>
  <?php endif; ?>

  <form class="compose" method="post" action="/add" id="compose-form">
    <label class="compose-label" for="body">your message</label>
    <textarea
      id="body"
      name="body"
      class="mono"
      rows="6"
      required
      placeholder="spray something... multi-line + ascii art welcome"
    ></textarea>
    <pre id="paint-surface" class="paint-surface mono" hidden aria-hidden="true"></pre>
    <input type="hidden" name="spans" id="spans" value="">
    <p id="char-count" class="char-count" aria-live="polite">0 / 1000</p>

    <div class="palette-row">
      <fieldset id="color-palette" class="palette">
        <legend>color</legend>
        <?php foreach ($colors as $colorOption): ?>
          <label class="swatch <?= e(Color::cssClass($colorOption, false)) ?>">
            <input
              type="radio"
              name="color"
              value="<?= e($colorOption) ?>"
              <?= $colorOption === 'default' ? 'checked' : '' ?>
            >
            <span><?= e($colorOption) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <fieldset id="brush-palette" class="palette brush-palette" hidden>
        <legend>brush</legend>
        <?php foreach ($colors as $colorOption): ?>
          <label class="swatch <?= e(Color::cssClass($colorOption, false)) ?>">
            <input
              type="radio"
              name="brush"
              value="<?= e($colorOption) ?>"
              <?= $colorOption === 'red' ? 'checked' : '' ?>
            >
            <span><?= e($colorOption) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <button
        type="button"
        id="paint-toggle"
        class="paint-toggle"
        aria-pressed="false"
        title="Paint mode"
        aria-label="Toggle paint mode"
      >
        <img src="/assets/favicon.png" alt="" width="22" height="22" class="paint-toggle-icon">
      </button>
    </div>

    <p id="compose-hint" class="paint-hint">Enter your graffiti above. 20 character minimum, 1000 character maximum.</p>
    <p id="paint-hint" class="paint-hint" hidden>Choose a color and optional bold, then drag a rectangle to paint — bold is part of the brush</p>

    <div class="controls" id="compose-actions">
      <label class="bold-toggle">
        <input type="checkbox" name="bold" value="1" id="bold-toggle">
        <span>bold</span>
      </label>

      <button type="submit" class="spray-btn" id="spray-simple" disabled>spray it</button>
      <button type="submit" class="spray-btn" id="spray-paint" hidden disabled>spray it</button>
    </div>

    <div class="honeypot">
      <label for="website">website</label>
      <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>
  </form>

  <p class="compose-notice">No language filter. Posts are anonymous. Don&rsquo;t spray hate or porn &mdash; it gets wiped.</p>

  <section
    class="wall"
    data-wall-max="<?= $isAdmin ? '50' : '10' ?>"
    <?php if ($isAdmin): ?>
      data-admin="1"
    <?php endif; ?>
    data-csrf="<?= e($csrfToken) ?>"
    <?php if ($ownedIds !== []): ?>
      data-owned="<?= e(implode(',', array_map('strval', $ownedIds))) ?>"
    <?php endif; ?>
    <?php if ($flaggedIds !== []): ?>
      data-flagged-ids="<?= e(implode(',', array_map('strval', $flaggedIds))) ?>"
    <?php endif; ?>
  >
    <h2 class="wall-title"><?= $isAdmin ? 'recent sprays (admin)' : 'recent sprays' ?></h2>
    <p class="wall-empty"<?= $recent === [] ? '' : ' hidden' ?>>the wall is blank. be the first.</p>
    <div class="wall-grid">
      <?php foreach ($recent as $message): ?>
        <?php
          $spans = $message['spans'] ?? null;
          $outerClass = Color::outerCssClass($message['color'], $message['bold'], $spans);
          $canDelete = $isAdmin || isset($ownedLookup[$message['id']]);
          $deleteAction = $isAdmin ? '/admin' : '/delete';
          $flaggedByMe = isset($flaggedLookup[$message['id']]);
        ?>
        <div class="terminal<?= !empty($message['flagged']) && $isAdmin ? ' is-flagged' : '' ?>" data-id="<?= e((string) $message['id']) ?>"<?= !empty($message['flagged']) ? ' data-flagged="1"' : '' ?>>
          <div class="terminal-bar">
            <span class="terminal-dot terminal-dot-red"></span>
            <span class="terminal-dot terminal-dot-yellow"></span>
            <span class="terminal-dot terminal-dot-green"></span>
            <span class="terminal-title">msg #<?= e((string) $message['id']) ?></span>
            <?php if ($csrfToken !== ''): ?>
              <form class="wall-flag" method="post" action="/flag">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <input type="hidden" name="next" value="/add">
                <button
                  type="submit"
                  class="wall-flag-btn<?= $flaggedByMe ? ' is-flagged-by-me' : '' ?>"
                  title="<?= $flaggedByMe ? 'Remove your flag' : 'Flag this spray' ?>"
                >flag</button>
              </form>
            <?php endif; ?>
            <?php if ($isAdmin && !empty($message['flagged'])): ?>
              <span class="flag-badge" title="Flagged as low-effort or test">flagged</span>
              <form class="wall-approve" method="post" action="/admin">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <input type="hidden" name="approve" value="1">
                <input type="hidden" name="next" value="/add">
                <button type="submit" class="wall-approve-btn" title="Clear flag">approve</button>
              </form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <form class="wall-delete" method="post" action="<?= e($deleteAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <input type="hidden" name="next" value="/add">
                <button type="submit" class="wall-delete-btn" title="<?= $isAdmin ? 'Delete this spray' : 'Delete your spray' ?>">delete</button>
              </form>
            <?php endif; ?>
          </div>
          <pre class="terminal-body<?= $outerClass !== '' ? ' ' . e($outerClass) : '' ?>"><?= Color::renderHtmlBody($message['body'], $message['color'], $message['bold'], $spans) ?></pre>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="site-section" id="house-rules">
    <h2 class="wall-title">house rules</h2>
    <p class="site-section-body">There&rsquo;s no automated language filtering. Contributions are anonymous. The developer takes no responsibility for what others write. Hate speech and pornographic content will be removed by the admin as quickly as possible.</p>
  </section>

  <section class="site-section" id="cli">
    <h2 class="wall-title">from your terminal</h2>
    <p class="site-section-body">Install the CLI with Homebrew:</p>
    <pre class="cli-block"><code>brew tap ttscoff/thelab
brew install graffiti</code></pre>
    <p class="site-section-body">Then <code>graffiti</code> for a random spray, or <code>graffiti spraypaint 'your message'</code> to post.</p>
    <p class="site-section-body">Read with color: <code>graffiti --color=always</code> (or <code>never</code> / <code>auto</code>). Spray with palette options: <code>graffiti spraypaint --color cyan --bold 'your message'</code> &mdash; colors match the form (<code>default</code>, <code>red</code>, <code>green</code>, <code>yellow</code>, <code>blue</code>, <code>magenta</code>, <code>cyan</code>).</p>
    <p class="site-section-body">No Homebrew? <code>curl graffiti.moe</code> (or <code>curl 'graffiti.moe?color=always'</code> for color).</p>
  </section>

</div>
<script src="<?= e(asset_url('/assets/compose.js')) ?>" defer></script>
<script src="<?= e(asset_url('/assets/wall.js')) ?>" defer></script>
</body>
</html>
