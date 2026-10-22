<?php
/** @var list<array<string, mixed>> $categories */
/** @var string $search */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
?>
<div class="admin-page-actions">
  <p class="admin-page-count"><?= e((string) $total) ?> categor<?= $total === 1 ? 'y' : 'ies' ?></p>
  <a class="admin-button admin-button-primary" href="<?= e(admin_url('category-create')) ?>">Add category</a>
</div>

<section class="admin-panel" aria-labelledby="categories-title">
  <div class="admin-panel-heading admin-panel-heading-wrap">
    <div>
      <p class="admin-eyebrow">WEBSITE CONTENT</p>
      <h2 id="categories-title">Service categories</h2>
    </div>
    <form class="admin-search" method="get" action="<?= e(url('admin/')) ?>" role="search">
      <input type="hidden" name="r" value="categories">
      <label class="admin-sr-only" for="category-search">Search categories</label>
      <input id="category-search" name="q" type="search" value="<?= e($search) ?>" placeholder="Search categories" maxlength="100">
      <button class="admin-button admin-button-secondary admin-button-small" type="submit">Search</button>
      <?php if ($search !== ''): ?><a class="admin-text-link" href="<?= e(admin_url('categories')) ?>">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if ($categories === []): ?>
    <p class="admin-empty"><?= $search !== '' ? 'No categories match that search.' : 'No service categories yet.' ?> <a href="<?= e(admin_url('category-create')) ?>">Create a category</a>.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <caption class="admin-sr-only">Service categories</caption>
        <thead>
          <tr>
            <th scope="col">Category</th>
            <th scope="col">Slug</th>
            <th scope="col">Products</th>
            <th scope="col">Sort</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="admin-sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $category): ?>
            <tr>
              <td>
                <span class="admin-category-name"><span aria-hidden="true"><?= e((string) $category['icon']) ?></span> <?= e((string) $category['name']) ?></span>
                <?php if ((string) $category['summary'] !== ''): ?><span class="admin-table-sub"><?= e(\AxaKenneth\Support\Str::truncate((string) $category['summary'], 90)) ?></span><?php endif; ?>
              </td>
              <td><code><?= e((string) $category['slug']) ?></code></td>
              <td><?= e((string) $category['product_count']) ?></td>
              <td><?= e((string) $category['sort_order']) ?></td>
              <td><span class="admin-status admin-status-<?= !empty($category['is_published']) ? 'published' : 'draft' ?>"><?= !empty($category['is_published']) ? 'Published' : 'Hidden' ?></span></td>
              <td>
                <div class="admin-row-actions">
                  <a class="admin-button admin-button-small admin-button-secondary" href="<?= e(admin_url('category-edit', ['id' => (int) $category['id']])) ?>">Edit</a>
                  <form method="post" action="<?= e(admin_url('category-delete', ['id' => (int) $category['id']])) ?>" data-confirm="Delete this service category? Products must be moved or deleted first.">
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
      <nav class="admin-pagination" aria-label="Category pages">
        <?php if ($page > 1): ?><a href="<?= e(admin_page_url('categories', $page - 1, $search !== '' ? ['q' => $search] : [])) ?>">← Previous</a><?php endif; ?>
        <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(admin_page_url('categories', $page + 1, $search !== '' ? ['q' => $search] : [])) ?>">Next →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
