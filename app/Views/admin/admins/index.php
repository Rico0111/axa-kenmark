<?php
/** @var list<array<string, mixed>> $admins */
/** @var array<string, string> $roleLabels */
$currentAdmin = \AxaKenneth\Support\Auth::admin();
$currentId = $currentAdmin !== null ? (int) $currentAdmin['id'] : 0;
?>
<div class="admin-page-actions">
  <p class="admin-page-count"><?= e((string) count($admins)) ?> administrator account<?= count($admins) === 1 ? '' : 's' ?></p>
  <a class="admin-button admin-button-primary" href="<?= e(admin_url('admin-create')) ?>">Add administrator</a>
</div>

<div class="admin-callout admin-callout-gold">
  <div>
    <strong>Administrator access is restricted.</strong>
    <p>Only a super administrator can create accounts, change roles, deactivate access, or reset passwords. Password resets and deactivation invalidate that account's other signed-in sessions. At least one active super administrator must remain.</p>
  </div>
</div>

<section class="admin-panel" aria-labelledby="admins-title">
  <div class="admin-panel-heading">
    <div>
      <p class="admin-eyebrow">ACCESS CONTROL</p>
      <h2 id="admins-title">Administrators</h2>
    </div>
  </div>

  <?php if ($admins === []): ?>
    <p class="admin-empty">No administrator accounts exist. Use the provisioning script on the server.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-admins">
        <caption class="admin-sr-only">Administrator accounts</caption>
        <thead>
          <tr>
            <th scope="col">Administrator</th>
            <th scope="col">Role</th>
            <th scope="col">Status</th>
            <th scope="col">Last sign-in</th>
            <th scope="col">Created</th>
            <th scope="col"><span class="admin-sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $account): ?>
            <?php
            $isActive = (int) $account['is_active'] === 1;
            $isSuper = (string) $account['role'] === 'super_admin';
            $isCurrent = (int) $account['id'] === $currentId;
            ?>
            <tr>
              <td>
                <span class="admin-table-title"><?= e((string) $account['name']) ?><?= $isCurrent ? ' (you)' : '' ?></span>
                <span class="admin-table-sub"><?= e((string) $account['email']) ?></span>
              </td>
              <td><span class="admin-role-tag<?= $isSuper ? ' admin-role-tag-super' : '' ?>"><?= e($roleLabels[(string) $account['role']] ?? (string) $account['role']) ?></span></td>
              <td><span class="admin-status admin-account-status-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span></td>
              <td class="admin-nowrap"><?= e(format_datetime($account['last_login_at'] ?? null)) ?></td>
              <td class="admin-nowrap"><?= e(format_date((string) $account['created_at'])) ?></td>
              <td>
                <div class="admin-row-actions">
                  <a class="admin-button admin-button-small admin-button-secondary" href="<?= e(admin_url('admin-edit', ['id' => (int) $account['id']])) ?>">Edit</a>
                  <a class="admin-button admin-button-small admin-button-secondary" href="<?= e(admin_url('admin-password', ['id' => (int) $account['id']])) ?>">Reset password</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
