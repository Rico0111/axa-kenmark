<?php
/** @var list<array<string, mixed>> $mediaItems */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var int $maxMegabytes */
?>
<div class="admin-page-actions">
  <p class="admin-page-count"><?= e((string) $total) ?> image<?= $total === 1 ? '' : 's' ?></p>
</div>

<section class="admin-panel" aria-labelledby="upload-title">
  <div class="admin-panel-heading">
    <div>
      <p class="admin-eyebrow">PRIVATE STORAGE</p>
      <h2 id="upload-title">Upload an image</h2>
    </div>
  </div>

  <form class="admin-form" method="post" action="<?= e(admin_url('media-upload')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="admin-form-grid">
      <div class="admin-field">
        <label for="image">Image file <span class="admin-required">*</span></label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
        <p class="admin-help">JPEG, PNG, or WebP. Maximum <?= e((string) $maxMegabytes) ?> MB. Files are stored outside the public web root.</p>
      </div>

      <div class="admin-field">
        <label for="alt_text">Alternative text</label>
        <input id="alt_text" name="alt_text" type="text" maxlength="190" placeholder="Describe the image">
      </div>

      <div class="admin-field">
        <label for="title">Internal title</label>
        <input id="title" name="title" type="text" maxlength="190" placeholder="Optional label">
      </div>
    </div>

    <div class="admin-form-actions admin-form-actions-start">
      <button class="admin-button admin-button-primary" type="submit">Upload image</button>
    </div>
  </form>
</section>

<section class="admin-panel" aria-labelledby="library-title">
  <div class="admin-panel-heading">
    <div>
      <p class="admin-eyebrow">LIBRARY</p>
      <h2 id="library-title">Uploaded images</h2>
    </div>
  </div>

  <?php if ($mediaItems === []): ?>
    <p class="admin-empty">No images uploaded yet. Use the form above to add the first image.</p>
  <?php else: ?>
    <div class="admin-media-grid">
      <?php foreach ($mediaItems as $media): ?>
        <article class="admin-media-card">
          <a class="admin-media-image" href="<?= e(media_url((int) $media['id']) ?? url('media/' . (int) $media['id'])) ?>" target="_blank" rel="noopener">
            <img src="<?= e(media_url((int) $media['id']) ?? '') ?>" alt="<?= e((string) ($media['alt_text'] ?? '')) ?>" loading="lazy" width="320" height="200">
          </a>
          <div class="admin-media-body">
            <p class="admin-media-name" title="<?= e((string) $media['original_name']) ?>"><?= e(\AxaKenneth\Support\Str::truncate((string) $media['original_name'], 48)) ?></p>
            <p class="admin-media-meta"><?= e((string) $media['mime_type']) ?> · <?= e((string) $media['width']) ?>×<?= e((string) $media['height']) ?> · <?= e(number_format(((int) $media['size_bytes']) / 1024, 0)) ?> KB</p>
            <?php if (!empty($media['alt_text'])): ?><p class="admin-media-alt">Alt: <?= e((string) $media['alt_text']) ?></p><?php endif; ?>
            <p class="admin-media-meta">Used by <?= e((string) $media['usage_count']) ?> record<?= (int) $media['usage_count'] === 1 ? '' : 's' ?></p>
            <code class="admin-media-url"><?= e(media_url((int) $media['id']) ?? '') ?></code>
          </div>
          <div class="admin-media-actions">
            <?php if ((int) $media['usage_count'] === 0): ?>
              <form method="post" action="<?= e(admin_url('media-delete', ['id' => (int) $media['id']])) ?>" data-confirm="Delete this image permanently?">
                <?= csrf_field() ?>
                <button class="admin-button admin-button-small admin-button-danger" type="submit">Delete</button>
              </form>
            <?php else: ?>
              <span class="admin-help">In use — detach before deleting.</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="admin-pagination" aria-label="Media pages">
        <?php if ($page > 1): ?><a href="<?= e(admin_page_url('media', $page - 1)) ?>">← Previous</a><?php endif; ?>
        <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(admin_page_url('media', $page + 1)) ?>">Next →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
