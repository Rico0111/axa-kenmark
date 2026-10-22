<?php
/** @var list<array<string, mixed>> $inquiries */
/** @var string $search */
/** @var string $type */
/** @var string $status */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var array<string, int> $statusCounts */
/** @var array<string, int> $typeCounts */

$typeLabels = \AxaKenneth\Repositories\InquiryRepository::TYPE_LABELS;
$statusLabels = \AxaKenneth\Repositories\InquiryRepository::STATUS_LABELS;
$paginationParams = array_filter(
    ['q' => $search, 'type' => $type, 'status' => $status],
    static fn (string $value): bool => $value !== ''
);
?>
<div class="admin-page-actions">
  <p class="admin-page-count"><?= e((string) $total) ?> recorded submission<?= $total === 1 ? '' : 's' ?></p>
</div>

<?php if (($statusCounts['new'] ?? 0) > 0): ?>
  <div class="admin-callout admin-callout-gold">
    <div>
      <strong><?= e((string) $statusCounts['new']) ?> new submission<?= $statusCounts['new'] === 1 ? '' : 's' ?> awaiting review.</strong>
      <p>Use the workflow panel on each record to record review, contact, scheduling, closure, or spam classification. Submission contents stay private to signed-in administrators.</p>
    </div>
    <a class="admin-button admin-button-secondary" href="<?= e(admin_url('inquiries', ['status' => 'new'])) ?>">Review new</a>
  </div>
<?php endif; ?>

<section class="admin-panel" aria-labelledby="inquiries-title">
  <div class="admin-panel-heading admin-panel-heading-wrap">
    <div>
      <p class="admin-eyebrow">PRIVATE INBOX</p>
      <h2 id="inquiries-title">Inquiries &amp; requests</h2>
    </div>
    <form class="admin-search admin-search-wide" method="get" action="<?= e(url('admin/')) ?>" role="search">
      <input type="hidden" name="r" value="inquiries">
      <label class="admin-sr-only" for="inquiry-search">Search submissions</label>
      <input id="inquiry-search" name="q" type="search" value="<?= e($search) ?>" placeholder="Search name, email, or message" maxlength="100">
      <label class="admin-sr-only" for="inquiry-type-filter">Filter by type</label>
      <select id="inquiry-type-filter" name="type">
        <option value="">All types</option>
        <?php foreach ($typeLabels as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= selected($type === $key) ?>><?= e($label) ?> (<?= e((string) ($typeCounts[$key] ?? 0)) ?>)</option>
        <?php endforeach; ?>
      </select>
      <label class="admin-sr-only" for="inquiry-status-filter">Filter by status</label>
      <select id="inquiry-status-filter" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statusLabels as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= selected($status === $key) ?>><?= e($label) ?> (<?= e((string) ($statusCounts[$key] ?? 0)) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="admin-button admin-button-secondary admin-button-small" type="submit">Filter</button>
      <?php if ($search !== '' || $type !== '' || $status !== ''): ?><a class="admin-text-link" href="<?= e(admin_url('inquiries')) ?>">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if ($inquiries === []): ?>
    <p class="admin-empty">No submissions match the current filters. Public forms appear in the contact section of the <a href="<?= e(url('/#contact-forms')) ?>" target="_blank" rel="noopener">public website</a>.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-inquiries">
        <caption class="admin-sr-only">Inquiries and requests</caption>
        <thead>
          <tr>
            <th scope="col">Contact</th>
            <th scope="col">Type</th>
            <th scope="col">Message</th>
            <th scope="col">Source</th>
            <th scope="col">Status</th>
            <th scope="col">Received</th>
            <th scope="col"><span class="admin-sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inquiries as $inquiry): ?>
            <tr>
              <td>
                <a class="admin-table-title" href="<?= e(admin_url('inquiry-view', ['id' => (int) $inquiry['id']])) ?>"><?= e((string) $inquiry['name']) ?></a>
                <span class="admin-table-sub"><?= e((string) $inquiry['email']) ?></span>
                <?php if ((string) $inquiry['phone'] !== ''): ?><span class="admin-table-sub"><?= e((string) $inquiry['phone']) ?></span><?php endif; ?>
              </td>
              <td><span class="admin-inquiry-type"><?= e($typeLabels[(string) $inquiry['inquiry_type']] ?? (string) $inquiry['inquiry_type']) ?></span></td>
              <td><span class="admin-inquiry-preview"><?= e(\AxaKenneth\Support\Str::truncate((string) $inquiry['message'], 110)) ?></span></td>
              <td><code><?= e((string) $inquiry['source']) ?></code></td>
              <td><span class="admin-status admin-status-inquiry-<?= e((string) $inquiry['status']) ?>"><?= e($statusLabels[(string) $inquiry['status']] ?? (string) $inquiry['status']) ?></span></td>
              <td class="admin-nowrap"><?= e(format_datetime((string) $inquiry['created_at'])) ?></td>
              <td><a class="admin-button admin-button-small admin-button-secondary" href="<?= e(admin_url('inquiry-view', ['id' => (int) $inquiry['id']])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="admin-pagination" aria-label="Inquiry pages">
        <?php if ($page > 1): ?><a href="<?= e(admin_page_url('inquiries', $page - 1, $paginationParams)) ?>">← Previous</a><?php endif; ?>
        <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(admin_page_url('inquiries', $page + 1, $paginationParams)) ?>">Next →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
