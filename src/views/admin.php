<?php

declare(strict_types=1);

/** @var list<array{id:int,body:string,color:string,bold:bool,flagged?:bool,created_at:string}> $messages */
/** @var string $csrfToken */
/** @var bool $flaggedOnly */
$flaggedOnly = $flaggedOnly ?? false;
$adminListNext = $flaggedOnly ? '/admin?flagged=1' : '/admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>graffiti.moe admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Permanent+Marker&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/style.css')) ?>">
<link rel="icon" href="/assets/favicon.png" type="image/png">
</head>
<body>
<div class="page admin-page">
  <header class="hero">
    <h1 class="brand">graffiti<span class="brand-dot">.</span>moe</h1>
    <p class="tagline">admin &mdash; moderate the wall</p>
  </header>

  <nav class="admin-nav">
    <a href="/add">back to wall</a>
    <form class="admin-logout" method="post" action="/admin">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="logout" value="1">
      <button type="submit" class="admin-logout-btn">sign out</button>
    </form>
  </nav>

  <section class="admin-panel">
    <div class="admin-panel-header">
      <h2 class="wall-title"><?= $flaggedOnly ? 'flagged sprays' : 'all sprays' ?></h2>
      <p class="admin-filter">
        <a href="/admin" class="<?= $flaggedOnly ? '' : 'is-active' ?>">all</a>
        <span aria-hidden="true">|</span>
        <a href="/admin?flagged=1" class="<?= $flaggedOnly ? 'is-active' : '' ?>">flagged</a>
      </p>
    </div>
    <?php if ($messages === []): ?>
      <p class="admin-empty"><?= $flaggedOnly ? 'No flagged messages.' : 'No messages.' ?></p>
    <?php else: ?>
      <form method="post" action="/admin" id="admin-batch-form" class="admin-batch-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="next" value="<?= e($adminListNext) ?>">
        <div class="admin-batch-bar">
          <label class="admin-select-all">
            <input type="checkbox" id="admin-select-all">
            <span>select all</span>
          </label>
          <div class="admin-batch-actions">
            <button type="submit" name="batch_approve" value="1" class="admin-approve-btn" id="admin-batch-approve" disabled>
              Approve All
            </button>
            <button
              type="submit"
              name="batch_delete"
              value="1"
              class="admin-delete-btn"
              id="admin-batch-delete"
              disabled
              data-confirm="Delete selected sprays? This cannot be undone."
            >
              Delete All
            </button>
          </div>
        </div>
      </form>

      <ul class="admin-list">
        <?php foreach ($messages as $message): ?>
          <?php $isFlagged = !empty($message['flagged']); ?>
          <li class="admin-item<?= $isFlagged ? ' is-flagged' : '' ?>">
            <div class="admin-item-top">
              <label class="admin-item-select">
                <input
                  type="checkbox"
                  class="admin-item-checkbox"
                  form="admin-batch-form"
                  name="ids[]"
                  value="<?= e((string) $message['id']) ?>"
                >
                <span class="visually-hidden">select #<?= e((string) $message['id']) ?></span>
              </label>
              <div class="admin-item-meta">
                <strong>#<?= e((string) $message['id']) ?></strong>
                <time datetime="<?= e($message['created_at']) ?>"><?= e($message['created_at']) ?></time>
                <?php if ($isFlagged): ?>
                  <span class="flag-badge">flagged</span>
                <?php endif; ?>
              </div>
            </div>
            <pre class="admin-item-body"><?= e($message['body']) ?></pre>
            <div class="admin-item-actions">
              <?php if ($isFlagged): ?>
                <form method="post" action="/admin" class="admin-approve-form">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                  <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                  <input type="hidden" name="approve" value="1">
                  <input type="hidden" name="next" value="<?= e($adminListNext) ?>">
                  <button type="submit" class="admin-approve-btn">Approve</button>
                </form>
              <?php endif; ?>
              <form method="post" action="/admin" class="admin-delete-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <input type="hidden" name="next" value="<?= e($adminListNext) ?>">
                <button type="submit" class="admin-delete-btn">Delete</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<script src="<?= e(asset_url('/assets/admin.js')) ?>" defer></script>
</body>
</html>
