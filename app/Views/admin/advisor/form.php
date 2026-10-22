<?php
/** @var array<string, mixed> $profile */
/** @var list<array<string, mixed>> $mediaItems */
/** @var bool $isSuperAdmin */
/** @var array<string, mixed> $old */
/** @var bool $submitted */
/** @var array<string, string> $errors */

$value = static function (string $key, string $fallback = '') use ($submitted, $old, $profile): string {
    if ($submitted && array_key_exists($key, $old)) {
        $posted = $old[$key];
        return is_scalar($posted) ? (string) $posted : $fallback;
    }

    return isset($profile[$key]) && is_scalar($profile[$key]) ? (string) $profile[$key] : $fallback;
};
$titleCurrentlyVerified = !empty($profile['title_verified_at']);
$organizationCurrentlyVerified = !empty($profile['authorization_verified_at']);
$titleChecked = $submitted ? isset($old['title_verified']) : $titleCurrentlyVerified;
$organizationChecked = $submitted ? isset($old['authorization_verified']) : $organizationCurrentlyVerified;
$selectedMediaId = $value('photo_media_id', '');
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('dashboard')) ?>">← Dashboard</a>
  <a class="admin-button admin-button-secondary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View public profile</a>
</div>

<?php if ($errors !== []): ?>
  <div class="admin-flash admin-flash-error" role="alert">
    <strong>Please correct the highlighted fields.</strong>
    <ul class="admin-error-list">
      <?php foreach (array_unique(array_values($errors)) as $error): ?>
        <li><?= e($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="admin-form admin-form-wide" method="post" action="<?= e(admin_url('advisor-update')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>

  <section class="admin-panel" aria-labelledby="identity-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">PUBLIC PROFILE</p>
        <h2 id="identity-title">Identity</h2>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="display_name">Display name <span class="admin-required">*</span></label>
        <input id="display_name" name="display_name" type="text" maxlength="120" required value="<?= e($value('display_name')) ?>" class="<?= e(field_invalid($errors, 'display_name')) ?>">
        <?= field_error($errors, 'display_name') ?>
      </div>

      <div class="admin-field">
        <label for="tagline">Tagline</label>
        <input id="tagline" name="tagline" type="text" maxlength="255" value="<?= e($value('tagline')) ?>" class="<?= e(field_invalid($errors, 'tagline')) ?>">
        <?= field_error($errors, 'tagline') ?>
      </div>

      <div class="admin-field">
        <label for="title">Professional title</label>
        <input id="title" name="title" type="text" maxlength="160" value="<?= e($value('title')) ?>" class="<?= e(field_invalid($errors, 'title')) ?>" aria-describedby="title-help">
        <p class="admin-help" id="title-help">This is hidden from the public site until a super administrator verifies it.</p>
        <?= field_error($errors, 'title') ?>
      </div>

      <div class="admin-field">
        <label for="organization">Organization / AXA affiliation</label>
        <input id="organization" name="organization" type="text" maxlength="160" value="<?= e($value('organization')) ?>" class="<?= e(field_invalid($errors, 'organization')) ?>" aria-describedby="organization-help">
        <p class="admin-help" id="organization-help">Hidden publicly until the authorization review is recorded.</p>
        <?= field_error($errors, 'organization') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="bio">Biography</label>
        <textarea id="bio" name="bio" rows="6" maxlength="5000" class="<?= e(field_invalid($errors, 'bio')) ?>"><?= e($value('bio')) ?></textarea>
        <p class="admin-help">Use general language. Do not state unverified credentials, rankings, income claims, or coverage guarantees.</p>
        <?= field_error($errors, 'bio') ?>
      </div>
    </div>
  </section>

  <section class="admin-panel" aria-labelledby="contact-details-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">CONTACT</p>
        <h2 id="contact-details-title">Published contact details</h2>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="190" value="<?= e($value('email')) ?>" class="<?= e(field_invalid($errors, 'email')) ?>">
        <?= field_error($errors, 'email') ?>
      </div>

      <div class="admin-field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" type="tel" maxlength="40" value="<?= e($value('phone')) ?>" class="<?= e(field_invalid($errors, 'phone')) ?>">
        <?= field_error($errors, 'phone') ?>
      </div>

      <div class="admin-field">
        <label for="whatsapp">WhatsApp number</label>
        <input id="whatsapp" name="whatsapp" type="tel" maxlength="40" value="<?= e($value('whatsapp')) ?>" class="<?= e(field_invalid($errors, 'whatsapp')) ?>">
        <?= field_error($errors, 'whatsapp') ?>
      </div>

      <div class="admin-field">
        <label for="messenger_url">Messenger URL</label>
        <input id="messenger_url" name="messenger_url" type="url" maxlength="500" value="<?= e($value('messenger_url')) ?>" class="<?= e(field_invalid($errors, 'messenger_url')) ?>">
        <?= field_error($errors, 'messenger_url') ?>
      </div>

      <div class="admin-field">
        <label for="facebook_url">Facebook URL</label>
        <input id="facebook_url" name="facebook_url" type="url" maxlength="500" value="<?= e($value('facebook_url')) ?>" class="<?= e(field_invalid($errors, 'facebook_url')) ?>">
        <?= field_error($errors, 'facebook_url') ?>
      </div>
    </div>
  </section>

  <section class="admin-panel" aria-labelledby="photo-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">PORTRAIT</p>
        <h2 id="photo-title">Profile photo</h2>
      </div>
    </div>

    <div class="admin-photo-preview">
      <img src="<?= e(advisor_photo_url($profile)) ?>" alt="Current profile photo preview" width="180" height="180">
      <div>
        <p class="admin-help">A selected media item overrides the fallback URL. Upload images in the <a href="<?= e(admin_url('media')) ?>">media library</a>.</p>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="photo_media_id">Media library image</label>
        <select id="photo_media_id" name="photo_media_id" class="<?= e(field_invalid($errors, 'photo_media_id')) ?>">
          <option value="">Use fallback URL / built-in portrait</option>
          <?php foreach ($mediaItems as $media): ?>
            <option value="<?= e((string) $media['id']) ?>"<?= selected($selectedMediaId === (string) $media['id']) ?>><?= e((string) $media['original_name']) ?> (ID <?= e((string) $media['id']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'photo_media_id') ?>
      </div>

      <div class="admin-field">
        <label for="photo_url">Fallback image URL or path</label>
        <input id="photo_url" name="photo_url" type="text" maxlength="1000" value="<?= e($value('photo_url')) ?>" class="<?= e(field_invalid($errors, 'photo_url')) ?>" placeholder="/assets/images/kenneth.jpg">
        <?= field_error($errors, 'photo_url') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="photo_alt">Alternative text</label>
        <input id="photo_alt" name="photo_alt" type="text" maxlength="190" value="<?= e($value('photo_alt')) ?>" class="<?= e(field_invalid($errors, 'photo_alt')) ?>">
        <?= field_error($errors, 'photo_alt') ?>
      </div>
    </div>
  </section>

  <section class="admin-panel admin-verification-panel" aria-labelledby="verification-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">COMPLIANCE REVIEW</p>
        <h2 id="verification-title">Title and authorization verification</h2>
      </div>
    </div>

    <?php if (!$isSuperAdmin): ?>
      <p class="admin-callout-inline">Only a super administrator can change verification. Current public status: title <strong><?= $titleCurrentlyVerified ? 'verified' : 'masked' ?></strong>; authorization <strong><?= $organizationCurrentlyVerified ? 'verified' : 'masked' ?></strong>. Editing the title or organization clears its previous verification.</p>
    <?php else: ?>
      <p class="admin-help">Record verification only after checking the supporting document or confirmation directly. Verification is not a claim that AXA sponsors this personal website.</p>

      <div class="admin-check-grid">
        <label class="admin-check">
          <input type="checkbox" name="title_verified" value="1"<?= checked($titleChecked) ?>>
          <span><strong>Title verified</strong><small>Approves the professional title for public display.</small></span>
        </label>
        <?= field_error($errors, 'title_verified') ?>

        <label class="admin-check">
          <input type="checkbox" name="authorization_verified" value="1"<?= checked($organizationChecked) ?>>
          <span><strong>AXA authorization verified</strong><small>Approves the organization line for public display.</small></span>
        </label>
        <?= field_error($errors, 'authorization_verified') ?>
      </div>

      <dl class="admin-detail-list admin-detail-list-compact">
        <div><dt>Title last verified</dt><dd><?= e(format_datetime($profile['title_verified_at'] ?? null)) ?></dd></div>
        <div><dt>Authorization last verified</dt><dd><?= e(format_datetime($profile['authorization_verified_at'] ?? null)) ?></dd></div>
      </dl>

      <div class="admin-field">
        <label for="verification_notes">Internal verification notes</label>
        <textarea id="verification_notes" name="verification_notes" rows="3" maxlength="2000" class="<?= e(field_invalid($errors, 'verification_notes')) ?>"><?= e($value('verification_notes')) ?></textarea>
        <p class="admin-help">Do not paste identity documents or sensitive personal information here.</p>
        <?= field_error($errors, 'verification_notes') ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit">Save advisor profile</button>
    <a class="admin-button admin-button-quiet" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
  </div>
</form>
