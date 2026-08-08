<?php

declare(strict_types=1);

use Graffiti\Color;

/**
 * @var array{id:int,body:string,color:string,bold:bool,spans:?list<array{t:string,c:string,b?:bool}>,flagged?:bool,created_at:string}|null $message
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
$notFound = $message === null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $notFound ? 'not found' : 'msg #' . e((string) $message['id']) ?> &mdash; graffiti.moe</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Permanent+Marker&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
<link rel="icon" href="/assets/favicon.png" type="image/png">
</head>
<body>
<div class="page solo-page">

  <header class="hero hero-solo">
    <h1 class="brand"><a class="brand-link" href="/add">graffiti<span class="brand-dot">.</span>moe</a></h1>
  </header>

  <?php if ($notFound): ?>
    <p class="flash flash-error">spray not found.</p>
    <p class="solo-back"><a href="/add">back to the wall</a></p>
  <?php else: ?>
    <?php
      $spans = $message['spans'] ?? null;
      $outerClass = Color::outerCssClass($message['color'], $message['bold'], $spans);
      $canDelete = $isAdmin || isset($ownedLookup[$message['id']]);
      $deleteAction = $isAdmin ? '/admin' : '/delete';
      $flaggedByMe = isset($flaggedLookup[$message['id']]);
      $next = '/id/' . $message['id'];
    ?>
    <div class="wall solo-wall">
      <div class="wall-grid">
        <div class="terminal<?= !empty($message['flagged']) && $isAdmin ? ' is-flagged' : '' ?>" data-id="<?= e((string) $message['id']) ?>"<?= !empty($message['flagged']) ? ' data-flagged="1"' : '' ?>>
          <div class="terminal-bar">
            <span class="terminal-dot terminal-dot-red"></span>
            <span class="terminal-dot terminal-dot-yellow"></span>
            <span class="terminal-dot terminal-dot-green"></span>
            <a class="terminal-title terminal-title-link" href="/id/<?= e((string) $message['id']) ?>">msg #<?= e((string) $message['id']) ?></a>
            <?php if ($csrfToken !== ''): ?>
              <form class="wall-flag" method="post" action="/flag">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <input type="hidden" name="next" value="<?= e($next) ?>">
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
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <button type="submit" class="wall-approve-btn" title="Clear flag">approve</button>
              </form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <form class="wall-delete" method="post" action="<?= e($deleteAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <button type="submit" class="wall-delete-btn" title="<?= $isAdmin ? 'Delete this spray' : 'Delete your spray' ?>">delete</button>
              </form>
            <?php endif; ?>
          </div>
          <pre class="terminal-body<?= $outerClass !== '' ? ' ' . e($outerClass) : '' ?>"><?= Color::renderHtmlBody($message['body'], $message['color'], $message['bold'], $spans) ?></pre>
        </div>
      </div>
    </div>
    <p class="solo-back"><a href="/add">back to the wall</a></p>
  <?php endif; ?>

</div>
</body>
</html>
