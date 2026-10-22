<?php

/** @var int $status */
/** @var string $message */
/** @var string $appName */
$status = isset($status) ? (int) $status : 500;
$message = isset($message) ? (string) $message : (isset($publicMessage) ? (string) $publicMessage : 'Something went wrong.');
$appName = isset($appName) ? (string) $appName : 'Kenneth | AXA Philippines';
$titles = [
    403 => 'Access denied',
    404 => 'Page not found',
    419 => 'Session expired',
    429 => 'Please slow down',
    503 => 'Service unavailable',
];
$title = $titles[$status] ?? 'Something went wrong';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> · <?= e($appName) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
  <style>
    .error-shell { min-height: 100vh; display: grid; place-items: center; padding: 32px 20px; background: #f4f8fc; }
    .error-card { width: min(100%, 560px); padding: 36px; border: 1px solid #dce6f0; background: #fff; box-shadow: 0 18px 50px #06264d12; }
    .error-code { color: #b28b47; font-size: 12px; font-weight: 800; letter-spacing: 2px; }
    .error-card h1 { margin: 10px 0 12px; color: #062f61; font-family: Georgia, serif; font-size: 38px; line-height: 1.1; }
    .error-card p { color: #53677d; font-size: 16px; line-height: 1.8; }
    .error-card a { display: inline-flex; align-items: center; min-height: 44px; margin-top: 20px; padding: 10px 18px; background: #0755a4; color: #fff; font-weight: 700; }
  </style>
</head>
<body>
  <main class="error-shell">
    <section class="error-card">
      <p class="error-code">ERROR <?= e((string) $status) ?></p>
      <h1><?= e($title) ?></h1>
      <p><?= e($message) ?></p>
      <a href="<?= e(url('/')) ?>">Return to the website</a>
    </section>
  </main>
</body>
</html>
