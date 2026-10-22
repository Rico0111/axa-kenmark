<?php
/** @var string $content */
/** @var array<string, mixed>|null $admin */
/** @var string $activeNav */
/** @var string $pageTitle */
/** @var int $newInquiryCount */
$admin = $admin ?? null;
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? (string) $pageTitle : 'Administration';
$activeNav = isset($activeNav) ? (string) $activeNav : '';
$newInquiryCount = isset($newInquiryCount) ? (int) $newInquiryCount : 0;
$flashMessages = \AxaKenneth\Support\Flash::consume();
$navClass = static function (string $key) use ($activeNav): string {
    return $activeNav === $key ? 'admin-nav-link is-active' : 'admin-nav-link';
};
$isSuperAdmin = $admin !== null && (string) ($admin['role'] ?? '') === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="theme-color" content="#062f61">
  <title><?= e($pageTitle) ?> · AXA-Kenneth Administration</title>
  <link rel="icon" type="image/x-icon" href="<?= e(url('favicon.ico')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">
<a class="admin-skip-link" href="#admin-content">Skip to administration content</a>

<?php if ($admin === null): ?>
  <main class="admin-auth" id="admin-content">
    <div class="admin-auth-brand" aria-label="AXA-Kenneth administration">
      <span class="admin-brand-mark" aria-hidden="true">AXA</span>
      <span class="admin-brand-divider" aria-hidden="true"></span>
      <span class="admin-brand-caption">KENNETH · ADMINISTRATION</span>
    </div>

    <?php foreach ($flashMessages as $flash): ?>
      <div class="admin-flash admin-flash-<?= e((string) $flash['type']) ?>" role="status"><?= e((string) $flash['message']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>

    <p class="admin-auth-foot">Authorized administrators only. Sign-in attempts and account actions are recorded.</p>
  </main>
<?php else: ?>
  <div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Sidebar">
      <a class="admin-brand" href="<?= e(admin_url('dashboard')) ?>" aria-label="Administration dashboard">
        <span class="admin-brand-mark" aria-hidden="true">AXA</span>
        <span class="admin-brand-divider" aria-hidden="true"></span>
        <span class="admin-brand-caption">KENNETH<br>ADMINISTRATION</span>
      </a>

      <nav class="admin-nav" aria-label="Administration">
        <p class="admin-nav-label">Manage</p>
        <a class="<?= e($navClass('dashboard')) ?>" href="<?= e(admin_url('dashboard')) ?>">Dashboard</a>
        <a class="<?= e($navClass('inquiries')) ?>" href="<?= e(admin_url('inquiries')) ?>">
          <span>Inquiries<?php if ($newInquiryCount > 0): ?><span class="admin-nav-count"><?= e((string) $newInquiryCount) ?></span><?php endif; ?></span>
          <span aria-hidden="true">→</span>
        </a>
        <a class="<?= e($navClass('advisor')) ?>" href="<?= e(admin_url('advisor')) ?>">Advisor profile</a>
        <a class="<?= e($navClass('categories')) ?>" href="<?= e(admin_url('categories')) ?>">Service categories</a>
        <a class="<?= e($navClass('products')) ?>" href="<?= e(admin_url('products')) ?>">Products</a>
        <a class="<?= e($navClass('media')) ?>" href="<?= e(admin_url('media')) ?>">Media library</a>
        <p class="admin-nav-label">Website</p>
        <?php if ($isSuperAdmin): ?>
          <a class="<?= e($navClass('settings')) ?>" href="<?= e(admin_url('settings')) ?>">Site settings</a>
          <a class="<?= e($navClass('admins')) ?>" href="<?= e(admin_url('admins')) ?>">Administrators</a>
        <?php endif; ?>
        <a class="admin-nav-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View public site <span aria-hidden="true">↗</span></a>
      </nav>

      <div class="admin-account">
        <p class="admin-account-name"><?= e((string) $admin['name']) ?></p>
        <p class="admin-account-role"><?= e($isSuperAdmin ? 'Super administrator' : 'Administrator') ?></p>
        <form method="post" action="<?= e(admin_url('logout')) ?>">
          <?= csrf_field() ?>
          <button class="admin-logout" type="submit">Sign out</button>
        </form>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <p class="admin-topbar-label">AXA-Kenneth website</p>
          <h1><?= e($pageTitle) ?></h1>
        </div>
        <div class="admin-topbar-actions">
          <span class="admin-role-badge"><?= e($isSuperAdmin ? 'Super administrator' : 'Administrator') ?></span>
          <span class="admin-topbar-name"><?= e((string) $admin['name']) ?></span>
        </div>
      </header>

      <main class="admin-content" id="admin-content">
        <?php foreach ($flashMessages as $flash): ?>
          <div class="admin-flash admin-flash-<?= e((string) $flash['type']) ?>" role="status"><?= e((string) $flash['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?>
      </main>

      <footer class="admin-foot">
        <p>Changes to website content are recorded in the audit log. Do not publish unverified product claims, credentials, or authorization statements.</p>
      </footer>
    </div>
  </div>
<?php endif; ?>

<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
