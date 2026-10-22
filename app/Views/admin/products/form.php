<?php
/** @var array<string, mixed>|null $product */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $mediaItems */
/** @var bool $isSuperAdmin */
/** @var array<string, mixed> $old */
/** @var bool $submitted */
/** @var array<string, string> $errors */

$value = static function (string $key, string $fallback = '') use ($submitted, $old, $product): string {
    if ($submitted && array_key_exists($key, $old)) {
        $posted = $old[$key];
        return is_scalar($posted) ? (string) $posted : $fallback;
    }
    if ($product !== null && array_key_exists($key, $product) && is_scalar($product[$key])) {
        return (string) $product[$key];
    }

    return $fallback;
};
$formAction = $product === null
    ? admin_url('product-store')
    : admin_url('product-update', ['id' => (int) $product['id']]);
$currentStatus = $value('status', 'draft');
$currentVerification = $value('verification_status', 'unverified');
$selectedMediaId = $value('image_media_id', '');
$selectedCategoryId = $value('category_id', '');
$verificationLabels = [
    'unverified' => 'Unverified — public details masked',
    'verified' => 'Verified — summary may be published',
    'rejected' => 'Rejected — public details masked',
];
$statusLabels = [
    'draft' => 'Draft — hidden from the public site',
    'published' => 'Published — visible on the public site',
    'archived' => 'Archived — hidden from the public site',
];
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('products')) ?>">← Products</a>
  <?php if ($product !== null && (string) $product['status'] === 'published'): ?>
    <a class="admin-button admin-button-secondary" href="<?= e(url('/#plans-title')) ?>" target="_blank" rel="noopener">View public plans</a>
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

  <section class="admin-panel" aria-labelledby="product-details-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow"><?= $product === null ? 'NEW PRODUCT' : 'EDIT PRODUCT' ?></p>
        <h2 id="product-details-title">Product details</h2>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="name">Product name <span class="admin-required">*</span></label>
        <input id="name" name="name" type="text" maxlength="160" required value="<?= e($value('name')) ?>" class="<?= e(field_invalid($errors, 'name')) ?>" data-slug-source>
        <?= field_error($errors, 'name') ?>
      </div>

      <div class="admin-field">
        <label for="slug">URL slug</label>
        <input id="slug" name="slug" type="text" maxlength="180" value="<?= e($value('slug')) ?>" class="<?= e(field_invalid($errors, 'slug')) ?>" data-slug-target placeholder="generated-from-name">
        <p class="admin-help">Leave blank to generate a unique slug.</p>
        <?= field_error($errors, 'slug') ?>
      </div>

      <div class="admin-field">
        <label for="category_id">Service category <span class="admin-required">*</span></label>
        <select id="category_id" name="category_id" required class="<?= e(field_invalid($errors, 'category_id')) ?>">
          <option value="">Choose a category</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e((string) $category['id']) ?>"<?= selected($selectedCategoryId === (string) $category['id']) ?>><?= e((string) $category['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($categories === []): ?><p class="admin-help">No categories exist. <a href="<?= e(admin_url('category-create')) ?>">Create one first</a>.</p><?php endif; ?>
        <?= field_error($errors, 'category_id') ?>
      </div>

      <div class="admin-field">
        <label for="product_type">Product type <span class="admin-required">*</span></label>
        <input id="product_type" name="product_type" type="text" maxlength="120" required value="<?= e($value('product_type')) ?>" class="<?= e(field_invalid($errors, 'product_type')) ?>" placeholder="Health &amp; Critical Illness">
        <?= field_error($errors, 'product_type') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="summary">Product summary</label>
        <textarea id="summary" name="summary" rows="5" maxlength="10000" class="<?= e(field_invalid($errors, 'summary')) ?>"><?= e($value('summary')) ?></textarea>
        <p class="admin-help">This text is withheld from the public site unless the product is both published and verified. Avoid guarantees, premiums, or eligibility claims that are not in current official materials.</p>
        <?= field_error($errors, 'summary') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="official_url">Official product URL</label>
        <input id="official_url" name="official_url" type="url" maxlength="500" value="<?= e($value('official_url')) ?>" class="<?= e(field_invalid($errors, 'official_url')) ?>" placeholder="https://www.axa.com.ph/...">
        <p class="admin-help">Shown publicly only when the product is verified.</p>
        <?= field_error($errors, 'official_url') ?>
      </div>

      <div class="admin-field">
        <label for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" step="1" value="<?= e($value('sort_order', '0')) ?>" class="<?= e(field_invalid($errors, 'sort_order')) ?>">
        <?= field_error($errors, 'sort_order') ?>
      </div>

      <div class="admin-field">
        <label for="status">Publish status</label>
        <select id="status" name="status" class="<?= e(field_invalid($errors, 'status')) ?>" required>
          <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= selected($currentStatus === $key) ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'status') ?>
      </div>
    </div>
  </section>

  <section class="admin-panel" aria-labelledby="product-image-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">ARTWORK</p>
        <h2 id="product-image-title">Product image</h2>
      </div>
    </div>

    <?php if ($product !== null): ?>
      <div class="admin-photo-preview">
        <img src="<?= e(product_image_url($product)) ?>" alt="Current product image preview" width="160" height="100">
        <p class="admin-help">A selected media item overrides the image URL. Upload new images in the <a href="<?= e(admin_url('media')) ?>">media library</a>.</p>
      </div>
    <?php endif; ?>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="image_media_id">Media library image</label>
        <select id="image_media_id" name="image_media_id" class="<?= e(field_invalid($errors, 'image_media_id')) ?>">
          <option value="">Use the image URL or fallback</option>
          <?php foreach ($mediaItems as $media): ?>
            <option value="<?= e((string) $media['id']) ?>"<?= selected($selectedMediaId === (string) $media['id']) ?>><?= e((string) $media['original_name']) ?> (ID <?= e((string) $media['id']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'image_media_id') ?>
      </div>

      <div class="admin-field">
        <label for="image_url">External image URL</label>
        <input id="image_url" name="image_url" type="url" maxlength="1000" value="<?= e($value('image_url')) ?>" class="<?= e(field_invalid($errors, 'image_url')) ?>" placeholder="https://images.unsplash.com/...">
        <?= field_error($errors, 'image_url') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="image_alt">Image alternative text</label>
        <input id="image_alt" name="image_alt" type="text" maxlength="190" value="<?= e($value('image_alt')) ?>" class="<?= e(field_invalid($errors, 'image_alt')) ?>">
        <p class="admin-help">Describe the image briefly for visitors using screen readers.</p>
        <?= field_error($errors, 'image_alt') ?>
      </div>
    </div>
  </section>

  <section class="admin-panel admin-verification-panel" aria-labelledby="product-verification-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">COMPLIANCE REVIEW</p>
        <h2 id="product-verification-title">Verification</h2>
      </div>
    </div>

    <?php if ($isSuperAdmin): ?>
      <p class="admin-help">Confirm the summary and official URL against current AXA Philippines materials before marking verified. Verification controls whether the summary is visible to the public.</p>

      <div class="admin-form-grid">
        <div class="admin-field">
          <label for="verification_status">Verification status</label>
          <select id="verification_status" name="verification_status" class="<?= e(field_invalid($errors, 'verification_status')) ?>">
            <?php foreach ($verificationLabels as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= selected($currentVerification === $key) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'verification_status') ?>
        </div>

        <div class="admin-field admin-field-full">
          <label for="verification_notes">Internal verification notes</label>
          <textarea id="verification_notes" name="verification_notes" rows="3" maxlength="2000" class="<?= e(field_invalid($errors, 'verification_notes')) ?>"><?= e($value('verification_notes')) ?></textarea>
          <p class="admin-help">Record the source checked and date. Do not paste sensitive personal information.</p>
          <?= field_error($errors, 'verification_notes') ?>
        </div>
      </div>

      <?php if ($product !== null): ?>
        <dl class="admin-detail-list admin-detail-list-compact">
          <div><dt>Last verified</dt><dd><?= e(format_datetime($product['verified_at'] ?? null)) ?></dd></div>
          <div><dt>Verified by admin ID</dt><dd><?= e((string) ($product['verified_by'] ?? '—')) ?></dd></div>
        </dl>
      <?php endif; ?>
    <?php else: ?>
      <p class="admin-callout-inline">Only a super administrator can verify product details. Current status: <strong><?= e(strtoupper((string) ($product['verification_status'] ?? 'unverified'))) ?></strong>. Editing product content clears a previous verification automatically. You can still save drafts and publish the product; unverified details stay masked.</p>
    <?php endif; ?>
  </section>

  <div class="admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit"><?= $product === null ? 'Create product' : 'Save changes' ?></button>
    <a class="admin-button admin-button-quiet" href="<?= e(admin_url('products')) ?>">Cancel</a>
  </div>
</form>
