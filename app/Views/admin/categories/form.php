<?php
/** @var array<string, mixed>|null $category */
/** @var array<string, mixed> $old */
/** @var bool $submitted */
/** @var array<string, string> $errors */

$value = static function (string $key, string $fallback = '') use ($submitted, $old, $category): string {
    if ($submitted && array_key_exists($key, $old)) {
        $posted = $old[$key];
        return is_scalar($posted) ? (string) $posted : $fallback;
    }
    if ($category !== null && array_key_exists($key, $category) && is_scalar($category[$key])) {
        return (string) $category[$key];
    }

    return $fallback;
};
$isPublished = $submitted ? !empty($old['is_published']) : ($category !== null && !empty($category['is_published']));
$formAction = $category === null
    ? admin_url('category-store')
    : admin_url('category-update', ['id' => (int) $category['id']]);
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('categories')) ?>">← Service categories</a>
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

  <section class="admin-panel" aria-labelledby="category-details-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow"><?= $category === null ? 'NEW CATEGORY' : 'EDIT CATEGORY' ?></p>
        <h2 id="category-details-title">Category details</h2>
      </div>
    </div>

    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="name">Category name <span class="admin-required">*</span></label>
        <input id="name" name="name" type="text" maxlength="120" required value="<?= e($value('name')) ?>" class="<?= e(field_invalid($errors, 'name')) ?>" data-slug-source>
        <?= field_error($errors, 'name') ?>
      </div>

      <div class="admin-field">
        <label for="slug">URL slug</label>
        <input id="slug" name="slug" type="text" maxlength="170" value="<?= e($value('slug')) ?>" class="<?= e(field_invalid($errors, 'slug')) ?>" data-slug-target placeholder="generated-from-name">
        <p class="admin-help">Leave blank to generate a unique slug from the name.</p>
        <?= field_error($errors, 'slug') ?>
      </div>

      <div class="admin-field">
        <label for="icon">Icon character</label>
        <input id="icon" name="icon" type="text" maxlength="16" value="<?= e($value('icon', '•')) ?>" class="<?= e(field_invalid($errors, 'icon')) ?>">
        <p class="admin-help">A single symbol such as ♡, ✚, ↗, or ⌂.</p>
        <?= field_error($errors, 'icon') ?>
      </div>

      <div class="admin-field">
        <label for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" step="1" value="<?= e($value('sort_order', '0')) ?>" class="<?= e(field_invalid($errors, 'sort_order')) ?>">
        <p class="admin-help">Lower numbers appear first.</p>
        <?= field_error($errors, 'sort_order') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="summary">Short summary</label>
        <textarea id="summary" name="summary" rows="3" maxlength="500" class="<?= e(field_invalid($errors, 'summary')) ?>"><?= e($value('summary')) ?></textarea>
        <p class="admin-help">Keep this general and factual. Product-specific claims belong to verified products.</p>
        <?= field_error($errors, 'summary') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label for="description">Longer description</label>
        <textarea id="description" name="description" rows="6" maxlength="10000" class="<?= e(field_invalid($errors, 'description')) ?>"><?= e($value('description')) ?></textarea>
        <?= field_error($errors, 'description') ?>
      </div>

      <div class="admin-field admin-field-full">
        <label class="admin-check">
          <input type="checkbox" name="is_published" value="1"<?= checked($isPublished) ?>>
          <span><strong>Show this category on the public website</strong><small>Hiding a category also hides its published products from the public carousel.</small></span>
        </label>
      </div>
    </div>
  </section>

  <div class="admin-form-actions">
    <button class="admin-button admin-button-primary" type="submit"><?= $category === null ? 'Create category' : 'Save changes' ?></button>
    <a class="admin-button admin-button-quiet" href="<?= e(admin_url('categories')) ?>">Cancel</a>
  </div>
</form>
