<?php

declare(strict_types=1);

/** @var list<array{id:int,body:string,color:string,bold:bool,created_at:string}> $messages */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>graffiti.moe admin</title>
</head>
<body>
<main>
  <h1>Graffiti admin</h1>
  <?php if ($messages === []): ?>
    <p>No messages.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($messages as $message): ?>
        <li>
          <article>
            <p><strong>#<?= e((string) $message['id']) ?></strong> <time><?= e($message['created_at']) ?></time></p>
            <pre><?= e($message['body']) ?></pre>
            <form method="post" action="/admin">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
              <button type="submit">Delete</button>
            </form>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
</body>
</html>
