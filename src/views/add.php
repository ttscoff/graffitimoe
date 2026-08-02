<?php

declare(strict_types=1);

use Graffiti\Color;

/**
 * @var list<array{id:int,body:string,color:string,bold:bool,created_at:string}> $recent
 * @var bool $ok
 * @var string|null $error
 * @var list<string> $colors
 */
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
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="page">

  <header class="hero">
    <h1 class="brand">graffiti<span class="brand-dot">.</span>moe</h1>
    <p class="tagline">a public wall for anonymous, terminal-friendly graffiti &mdash; spray a line, <code>curl</code> a random one back.</p>
  </header>

  <?php if ($ok): ?>
    <p class="flash flash-ok">sprayed. it&rsquo;s on the wall below.</p>
  <?php elseif ($error !== null && $error !== ''): ?>
    <p class="flash flash-error">couldn&rsquo;t spray that &mdash; too long, empty, or you&rsquo;re going too fast. try again.</p>
  <?php endif; ?>

  <form class="compose" method="post" action="/add">
    <label class="compose-label" for="body">your message</label>
    <textarea
      id="body"
      name="body"
      class="mono"
      rows="6"
      maxlength="1000"
      required
      placeholder="spray something... multi-line + ascii art welcome"
    ></textarea>

    <div class="controls">
      <fieldset class="palette">
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

      <label class="bold-toggle">
        <input type="checkbox" name="bold" value="1">
        <span>bold</span>
      </label>

      <button type="submit" class="spray-btn">spray it</button>
    </div>

    <div class="honeypot">
      <label for="website">website</label>
      <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>
  </form>

  <p class="compose-notice">No language filter. Posts are anonymous. Don&rsquo;t spray hate or porn &mdash; it gets wiped.</p>

  <section class="wall">
    <h2 class="wall-title">recent sprays</h2>

    <?php if ($recent === []): ?>
      <p class="wall-empty">the wall is blank. be the first.</p>
    <?php else: ?>
      <div class="wall-grid">
        <?php foreach ($recent as $message): ?>
          <div class="terminal">
            <div class="terminal-bar">
              <span class="terminal-dot terminal-dot-red"></span>
              <span class="terminal-dot terminal-dot-yellow"></span>
              <span class="terminal-dot terminal-dot-green"></span>
              <span class="terminal-title">msg #<?= e((string) $message['id']) ?></span>
            </div>
            <pre class="terminal-body <?= e(Color::cssClass($message['color'], $message['bold'])) ?>"><?= e($message['body']) ?></pre>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
</body>
</html>
