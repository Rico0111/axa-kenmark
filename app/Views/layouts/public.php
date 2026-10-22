<?php
/** @var string $content */
/** @var string $pageTitle */
/** @var string $metaDescription */
/** @var string $canonical */
/** @var string $ogImage */
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? (string) $pageTitle : 'Kenneth | AXA Philippines Protection Planning';
$metaDescription = isset($metaDescription) && $metaDescription !== '' ? (string) $metaDescription : 'A personal connection for protection planning, financial goals, and informed decisions.';
$canonical = isset($canonical) ? (string) $canonical : url('/');
$ogImage = isset($ogImage) && $ogImage !== '' ? (string) $ogImage : url('assets/images/kenneth.jpg');
$robots = isset($robots) ? (string) $robots : 'index,follow';
$flashMessages = \AxaKenneth\Support\Flash::consume();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#073b78">
  <meta name="description" content="<?= e($metaDescription) ?>">
  <meta name="robots" content="<?= e($robots) ?>">
  <title><?= e($pageTitle) ?></title>
  <link rel="canonical" href="<?= e($canonical) ?>">
  <link rel="icon" type="image/x-icon" href="<?= e(url('favicon.ico')) ?>">
  <link rel="icon" href="<?= e(url('assets/images/axa_savicon.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(url('assets/images/axa_savicon.png')) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Kenneth | AXA Philippines">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($metaDescription) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:alt" content="Portrait of Kenneth">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($metaDescription) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&amp;family=Manrope:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
  <noscript>
    <style>
      .axa-loader, .showcase-controls, .showcase-hint { display: none !important; }
      .plans-showcase { overflow: visible; }
      .plans-track { display: block !important; transform: none !important; }
      .plans-track .plan-card { display: grid !important; width: 100% !important; margin-bottom: 16px; }
      .js-motion [data-reveal], .js-motion [data-letter] { opacity: 1 !important; transform: none !important; }
    </style>
  </noscript>
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="axa-loader" id="axa-loader" role="status" aria-live="polite" aria-label="Loading Kenneth's website">
    <div class="loader-orbit loader-orbit-one" aria-hidden="true"></div>
    <div class="loader-orbit loader-orbit-two" aria-hidden="true"></div>
    <div class="loader-content">
      <div class="loader-logo" role="img" aria-label="AXA">AXA<span class="loader-logo-slash" aria-hidden="true"></span></div>
      <div class="loader-rule" aria-hidden="true"><span></span></div>
      <p class="loader-tagline">PROTECT WHAT MATTERS</p>
      <p class="loader-loading">Preparing your experience<span class="loader-dots" aria-hidden="true">...</span></p>
    </div>
    <div class="loader-bottom-line" aria-hidden="true"><span></span></div>
  </div>
  <div class="scroll-progress" aria-hidden="true"><span id="scroll-progress-bar"></span></div>
  <main class="site-shell" id="main-content" tabindex="-1">
    <header class="topbar">
      <a class="brand" href="<?= e(url('/')) ?>" aria-label="Kenneth home">
        <span class="brand-mark" aria-hidden="true">AXA</span><span class="brand-divider" aria-hidden="true"></span><span class="brand-caption">BY YOUR SIDE</span>
      </a>
      <a class="top-contact" href="<?= e(url('/#contact')) ?>" aria-label="Jump to contact options">Connect <span aria-hidden="true">↗</span></a>
    </header>

    <?php if ($flashMessages !== []): ?>
      <div class="public-flashes" role="status" aria-live="polite">
        <?php foreach ($flashMessages as $flash): ?>
          <p class="public-flash public-flash-<?= e((string) ($flash['type'] ?? 'info')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $content ?>

    <footer class="footer">
      <div class="footer-brand"><span class="brand-mark" aria-hidden="true">AXA</span><span class="brand-divider" aria-hidden="true"></span><span class="brand-caption">BY YOUR SIDE</span></div>
      <p class="footer-thanks">Thank you for connecting.</p>
      <p class="footer-role">KENNETH · PROTECTION PLANNING</p>
      <p class="footer-note">This is a personal website, not an official AXA Philippines website. AXA Philippines product information is subject to official terms and availability. Advisor credentials, AXA authorization, and brand permissions have not been independently verified here. Please consult an authorized AXA Financial Advisor and AXA's official materials before making a decision.</p>
      <p class="footer-links"><a href="<?= e(url('/')) ?>">Home</a><span aria-hidden="true">·</span><a href="<?= e(url('privacy')) ?>">Privacy notice</a></p>
    </footer>
  </main>
  <script src="<?= e(asset('js/script.js')) ?>" defer></script>
</body>
</html>
