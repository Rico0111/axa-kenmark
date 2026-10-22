<?php
/** @var array<string, mixed> $account */
/** @var array<string, string> $errors */
/** @var bool $isSelf */
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('admins')) ?>">← Administrators</a>
  <a class="admin-text-link" href="<?= e(admin_url('admin-edit', ['id' => (int) $account['id']])) ?>">Edit account</a>
</div>

<?php if ($errors !== []): ?>
  <div class="admin-flash admin-flash-error" role="alert">
    <strong>Please correct the highlighted fields.</strong>
    <ul class="admin-error-list">
      <?php foreach (array_unique(array_values($errors)) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="admin-form admin-form-wide" method="post" action="<?= e(admin_url('admin-password-update', ['id' => (int) $account['id']])) ?>" novalidate>
  <?= csrf_field() ?>

  <section class="admin-panel" aria-labelledby="password-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">SECURITY</p>
        <h2 id="password-title">Reset password</h2>
      </div>
    </div>

    <div class="admin-callout admin-callout-gold">
      <div>
        <strong><?= e((string) $account['name']) ?> · <?= e((string) $account['email']) ?></strong>
        <p>Setting a new password signs out every other active session for this account. Share the new password only through a secure channel and ask the administrator to change it after signing in.</p>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="password">New password <span class="admin-required">*</span></label>
        <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password" class="<?= e(field_invalid($errors, 'password')) ?>">
        <p class="admin-help">At least 12 characters with letters and numbers.</p>
        <?= field_error($errors, 'password') ?>
      </div>

      <div class="admin-field">
        <label for="password_confirm">Confirm new password <span class="admin-required">*</span></label>
        <input id="password_confirm" name="password_confirm" type="password" minlength="12" required autocomplete="new-password" class="<?= e(field_invalid($errors, 'password_confirm')) ?>">
        <?= field_error($errors, 'password_confirm') ?>
      </div>
    </div>

    <?php if ($isSelf): ?>
      <p class="admin-callout-inline admin-self-account-note">You are resetting your own password. Your current session stays signed in, while any other sessions for this account will be signed out.</p>
    <?php endif; ?>
  </section>

  <div class="admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit">Reset password</button>
    <a class="admin-button admin-button-quiet" href="<?= e(admin_url('admins')) ?>">Cancel</a>
  </div>
</form>
