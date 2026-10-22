<?php
/** @var list<array<string, mixed>> $products */
/** @var string $search */
/** @var string $status */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var array<string, int> $statusCounts */
/** @var int $unverifiedCount */

$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
$verificationLabels = ['unverified' => 'Unverified', 'verified' => 'Verified', 'rejected' => 'Rejected'];
$paginationParams = array_filter(['q' => $search, 'status' => $status], static fn (string $value): bool => $value !== '');
?>
<div class="admin-page-actions">
  <p class="admin-page-count"><?= e((string) $total) ?> product<?= $total === 1 ? '' : 's' ?></p>
  <a class="admin-button admin-button-primary" href="<?= e(admin_url('product-create')) ?>">Add product</a>
</div>

<?php if ($unverifiedCount > 0): ?>
  <div class="admin-callout admin-callout-gold">
    <div>
      <strong><?= e((string) $unverifiedCount) ?> published product<?= $unverifiedCount === 1 ? '' : 's' ?> unverified.</strong>
      <p>Public visitors see only the name, category, and image until details are verified. Do not mark a product verified without checking current official materials.</p>
    </div>
  </div>
<?php endif; ?>

<section class="admin-panel" aria-labelledby="products-title">
  <div class="admin-panel-heading admin-panel-heading-wrap">
    <div>
      <p class="admin-eyebrow">WEBSITE CONTENT</p>
      <h2 id="products-title">Products</h2>
    </div>
    <form class="admin-search admin-search-wide" method="get" action="<?= e(url('admin/')) ?>" role="search">
      <input type="hidden" name="r" value="products">
      <label class="admin-sr-only" for="product-search">Search products</label>
      <input id="product-search" name="q" type="search" value="<?= e($search) ?>" placeholder="Search products" maxlength="100">
      <label class="admin-sr-only" for="status-filter">Filter by status</label>
      <select id="status-filter" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statusLabels as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= selected($status === $key) ?>><?= e($label) ?> (<?= e((string) ($statusCounts[$key] ?? 0)) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="admin-button admin-button-secondary admin-button-small" type="submit">Filter</button>
      <?php if ($search !== '' || $status !== ''): ?><a class="admin-text-link" href="<?= e(admin_url('products')) ?>">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if ($products === []): ?>
    <p class="admin-empty">No products match the current filters. <a href="<?= e(admin_url('product-create')) ?>">Add a product</a>.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-products">
        <caption class="admin-sr-only">Products</caption>
        <thead>
          <tr>
            <th scope="col">Product</th>
            <th scope="col">Category</th>
            <th scope="col">Status</th>
            <th scope="col">Verification</th>
            <th scope="col">Sort</th>
            <th scope="col"><span class="admin-sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <?php
            $isPublished = (string) $product['status'] === 'published';
            $isVerified = (string) $product['verification_status'] === 'verified';
            ?>
            <tr>
              <td>
                <div class="admin-product-cell">
                  <img class="admin-thumb" src="<?= e(product_image_url($product)) ?>" alt="" width="52" height="52" loading="lazy">
                  <div>
                    <a class="admin-table-title" href="<?= e(admin_url('product-edit', ['id' => (int) $product['id']])) ?>"><?= e((string) $product['name']) ?></a>
                    <span class="admin-table-sub"><?= e((string) $product['product_type']) ?></span>
                  </div>
                </div>
              </td>
              <td><?= e((string) $product['category_name']) ?></td>
              <td><span class="admin-status admin-status-<?= e((string) $product['status']) ?>"><?= e($statusLabels[(string) $product['status']] ?? (string) $product['status']) ?></span></td>
              <td>
                <span class="admin-verification admin-verification-<?= e((string) $product['verification_status']) ?>"><?= e($verificationLabels[(string) $product['verification_status']] ?? (string) $product['verification_status']) ?></span>
                <?php if ($isPublished && !$isVerified): ?><span class="admin-table-sub admin-warning-text">Details masked publicly</span><?php endif; ?>
              </td>
              <td><?= e((string) $product['sort_order']) ?></td>
              <td>
                <div class="admin-row-actions">
                  <a class="admin-button admin-button-small admin-button-secondary" href="<?= e(admin_url('product-edit', ['id' => (int) $product['id']])) ?>">Edit</a>
                  <form method="post" action="<?= e(admin_url('product-delete', ['id' => (int) $product['id']])) ?>" data-confirm="Delete this product permanently?">
                    <?= csrf_field() ?>
                    <button class="admin-button admin-button-small admin-button-danger" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="admin-pagination" aria-label="Product pages">
        <?php if ($page > 1): ?><a href="<?= e(admin_page_url('products', $page - 1, $paginationParams)) ?>">← Previous</a><?php endif; ?>
        <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(admin_page_url('products', $page + 1, $paginationParams)) ?>">Next →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
