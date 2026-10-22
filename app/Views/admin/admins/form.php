<?php
/** @var array<string, mixed>|null $account */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var array<string, string> $roleLabels */
/** @var bool $isSelf */
/** @var bool $submitted */

$value = static function (string $key, string $fallback = '') use ($submitted, $old, $account): string {
    if ($submitted && array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($account !== null && array_key_exists($key, $account)) {
        return (string) $account[$key];
    }

    return $fallback;
};
$currentRole = $value('role', 'admin');
$isActive = $submitted
    ? ($old['is_active'] ?? '0') === '1'
    : ($account === null || (int) $account['is_active'] === 1);
$formAction = $account === null
    ? admin_url('admin-store')
    : admin_url('admin-update', ['id' => (int) $account['id']]);
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('admins')) ?>">← Administrators</a>
  <?php if ($account !== null && !$isSelf): ?>
    <a class="admin-button admin-button-secondary" href="<?= e(admin_url('admin-password', ['id' => (int) $account['id']])) ?>">Reset password</a>
  <?php endif; ?>
</div>

<?php if ($errors !== []): ?>
  <div class="admin-flash admin-flash-error" role="alert">
    <strong>Please correct the highlighted fields.</strong>
    <ul class="admin-error-list">
      <?php foreach (array_unique(array_values($errors)) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="admin-form admin-form-wide" method="post" action="<?= e($formAction) ?>" novalidate>
  <?= csrf_field() ?>

  <section class="admin-panel" aria-labelledby="account-details-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow"><?= $account === null ? 'NEW ACCOUNT' : 'EDIT ACCOUNT' ?></p>
        <h2 id="account-details-title">Administrator details</h2>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="name">Name <span class="admin-required">*</span></label>
        <input id="name" name="name" type="text" maxlength="120" required autocomplete="name" value="<?= e($value('name')) ?>" class="<?= e(field_invalid($errors, 'name')) ?>">
        <?= field_error($errors, 'name') ?>
      </div>

      <div class="admin-field">
        <label for="email">Email address <span class="admin-required">*</span></label>
        <input id="email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= e($value('email')) ?>" class="<?= e(field_invalid($errors, 'email')) ?>">
        <?= field_error($errors, 'email') ?>
      </div>

      <div class="admin-field">
        <label for="role">Role <span class="admin-required">*</span></label>
        <select id="role" name="role" required class="<?= e(field_invalid($errors, 'role')) ?>">
          <?php foreach ($roleLabels as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= selected($currentRole === $key) ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="admin-help">Super administrators can additionally verify content and manage settings and administrator accounts.</p>
        <?= field_error($errors, 'role') ?>
      </div>

      <div class="admin-field">
        <label class="admin-check">
          <input type="checkbox" name="is_active" value="1"<?= checked($isActive) ?>>
          <span><strong>Account is active</strong><small>Inactive accounts cannot sign in, and their existing sessions are invalidated.</small></span>
        </label>
        <?= field_error($errors, 'is_active') ?>
      </div>

      <?php if ($account === null): ?>
        <div class="admin-field">
          <label for="password">Initial password <span class="admin-required">*</span></label>
          <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password" class="<?= e(field_invalid($errors, 'password')) ?>">
          <p class="admin-help">At least 12 characters with letters and numbers. Share it through a secure channel and ask the administrator to change it.</p>
          <?= field_error($errors, 'password') ?>
        </div>

        <div class="admin-field">
          <label for="password_confirm">Confirm initial password <span class="admin-required">*</span></label>
          <input id="password_confirm" name="password_confirm" type="password" minlength="12" required autocomplete="new-password" class="<?= e(field_invalid($errors, 'password_confirm')) ?>">
          <?= field_error($errors, 'password_confirm') ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($isSelf): ?>
      <p class="admin-callout-inline admin-self-account-note">This is your signed-in account. You cannot deactivate it or remove your own super-administrator role. Use another super administrator for that change if it is genuinely required.</p>
    <?php endif; ?>
  </section>

  <div class="admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit"><?= $account === null ? 'Create administrator' : 'Save account' ?></button>
    <a class="admin-button admin-button-quiet" href="<?= e(admin_url('admins')) ?>">Cancel</a>
  </div>
</form>
