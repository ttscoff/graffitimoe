<?php

declare(strict_types=1);

/** @var string|null $error */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>graffiti.moe admin login</title>
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
    <p class="tagline">admin login</p>
  </header>

  <form class="compose admin-login" method="post" action="/admin">
    <?php if ($error !== null): ?>
      <p class="flash flash-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <label class="compose-label" for="password">password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <div class="controls">
      <button type="submit" class="spray-btn">log in</button>
    </div>
  </form>

  <p class="compose-notice"><a href="/add">back to the wall</a></p>
</div>
</body>
</html>
