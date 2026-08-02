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
<link rel="icon" href="/assets/favicon.png" type="image/png">
</head>
<body>
<main>
  <h1>Admin login</h1>
  <?php if ($error !== null): ?>
    <p role="alert"><?= e($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/admin">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>
    <button type="submit">Log in</button>
  </form>
</main>
</body>
</html>
