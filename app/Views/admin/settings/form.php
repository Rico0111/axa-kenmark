<?php
/** @var array<string, string> $settings */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var bool $submitted */

$value = static function (string $key, string $fallback = '') use ($submitted, $old, $settings): string {
    if ($submitted && array_key_exists($key, $old)) {
        return (string) $old[$key];
    }

    return (string) ($settings[$key] ?? $fallback);
};
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('dashboard')) ?>">← Dashboard</a>
  <a class="admin-button admin-button-secondary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View public site</a>
</div>

<?php if ($errors !== []): ?>
  <div class="admin-flash admin-flash-error" role="alert">
    <strong>Please correct the highlighted fields.</strong>
    <ul class="admin-error-list">
      <?php foreach (array_unique(array_values($errors)) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="admin-form admin-form-wide" method="post" action="<?= e(admin_url('settings-update')) ?>" novalidate>
  <?= csrf_field() ?>

  <section class="admin-panel" aria-labelledby="settings-public-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">PUBLIC WEBSITE</p>
        <h2 id="settings-public-title">Search and sharing details</h2>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field admin-field-full">
        <label for="site_title">Site title</label>
        <input id="site_title" name="site_title" type="text" maxlength="200" required value="<?= e($value('site_title')) ?>" class="<?= e(field_invalid($errors, 'site_title')) ?>">
        <p class="admin-help">Used in the browser title and social sharing metadata. Do not use it to claim unverified credentials or official AXA representation.</p>
        <?= field_error($errors, 'site_title') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="site_description">Site description</label>
        <textarea id="site_description" name="site_description" rows="3" maxlength="500" required class="<?= e(field_invalid($errors, 'site_description')) ?>"><?= e($value('site_description')) ?></textarea>
        <p class="admin-help">A general, factual description for search results and shared links.</p>
        <?= field_error($errors, 'site_description') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="og_image">Social sharing image URL or path</label>
        <input id="og_image" name="og_image" type="text" maxlength="1000" required value="<?= e($value('og_image')) ?>" class="<?= e(field_invalid($errors, 'og_image')) ?>" placeholder="/assets/images/kenneth.jpg">
        <p class="admin-help">Use an https URL or a root-relative path beginning with /. External images must come from a source you are permitted to use.</p>
        <?= field_error($errors, 'og_image') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="contact_response_note">Contact response note</label>
        <input id="contact_response_note" name="contact_response_note" type="text" maxlength="300" value="<?= e($value('contact_response_note')) ?>" class="<?= e(field_invalid($errors, 'contact_response_note')) ?>">
        <p class="admin-help">A short expectation-setting note shown under the public contact options. Do not promise response times you cannot meet.</p>
        <?= field_error($errors, 'contact_response_note') ?>
      </div>
    </div>
  </section>

  <section class="admin-panel admin-verification-panel" aria-labelledby="settings-privacy-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">CONSENT RECORD</p>
        <h2 id="settings-privacy-title">Privacy notice version</h2>
      </div>
    </div>

    <p class="admin-help">Every form submission stores the privacy notice version that the visitor accepted. Update these values only after the <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener">public privacy notice</a> has been reviewed. Existing consent records keep their original version.</p>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="privacy_notice_version">Notice version</label>
        <input id="privacy_notice_version" name="privacy_notice_version" type="text" maxlength="40" required value="<?= e($value('privacy_notice_version')) ?>" class="<?= e(field_invalid($errors, 'privacy_notice_version')) ?>" placeholder="2026-10-22">
        <?= field_error($errors, 'privacy_notice_version') ?>
      </div>

      <div class="admin-field">
        <label for="privacy_effective_date">Effective date label</label>
        <input id="privacy_effective_date" name="privacy_effective_date" type="text" maxlength="60" required value="<?= e($value('privacy_effective_date')) ?>" class="<?= e(field_invalid($errors, 'privacy_effective_date')) ?>" placeholder="22 October 2026">
        <?= field_error($errors, 'privacy_effective_date') ?>
      </div>
    </div>
  </section>

  <div class="admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit">Save settings</button>
    <a class="admin-button admin-button-quiet" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
  </div>
</form>
