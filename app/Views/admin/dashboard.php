<?php
/** @var int $newInquiryCount */
/** @var int $totalInquiryCount */
/** @var array<string, int> $inquiryStatusCounts */
/** @var list<array<string, mixed>> $recentInquiries */
/** @var array<string, int> $productCounts */
/** @var int $unverifiedCount */
/** @var list<array<string, mixed>> $recentProducts */
/** @var int $categoryCount */
/** @var int $mediaCount */
/** @var array<string, mixed>|null $advisor */
/** @var bool $titleVerified */
/** @var bool $organizationVerified */

$inquiryTypeLabels = \AxaKenneth\Repositories\InquiryRepository::TYPE_LABELS;
$inquiryStatusLabels = \AxaKenneth\Repositories\InquiryRepository::STATUS_LABELS;
$statusLabels = [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived',
];
$verificationLabels = [
    'unverified' => 'Unverified',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
];
?>
<section class="admin-stat-grid" aria-label="Website summary">
  <article class="admin-stat-card<?= $newInquiryCount > 0 ? ' admin-stat-card-attention' : '' ?>">
    <p class="admin-stat-label">New inquiries</p>
    <p class="admin-stat-value"><?= e((string) $newInquiryCount) ?></p>
    <p class="admin-stat-note"><?= e((string) $totalInquiryCount) ?> total · <?= e((string) ($inquiryStatusCounts['closed'] ?? 0)) ?> closed</p>
  </article>
  <article class="admin-stat-card">
    <p class="admin-stat-label">Published products</p>
    <p class="admin-stat-value"><?= e((string) ($productCounts['published'] ?? 0)) ?></p>
    <p class="admin-stat-note"><?= e((string) ($productCounts['draft'] ?? 0)) ?> draft · <?= e((string) ($productCounts['archived'] ?? 0)) ?> archived</p>
  </article>
  <article class="admin-stat-card">
    <p class="admin-stat-label">Published but unverified</p>
    <p class="admin-stat-value"><?= e((string) $unverifiedCount) ?></p>
    <p class="admin-stat-note">Product details stay masked publicly.</p>
  </article>
  <article class="admin-stat-card">
    <p class="admin-stat-label">Service categories</p>
    <p class="admin-stat-value"><?= e((string) $categoryCount) ?></p>
    <p class="admin-stat-note">Shown when marked published.</p>
  </article>
  <article class="admin-stat-card">
    <p class="admin-stat-label">Media items</p>
    <p class="admin-stat-value"><?= e((string) $mediaCount) ?></p>
    <p class="admin-stat-note">Stored privately and served through PHP.</p>
  </article>
</section>

<section class="admin-panel" aria-labelledby="recent-inquiries-title">
  <div class="admin-panel-heading">
    <div>
      <p class="admin-eyebrow">PRIVATE INBOX</p>
      <h2 id="recent-inquiries-title">Newest inquiries</h2>
    </div>
    <a class="admin-text-link" href="<?= e(admin_url('inquiries')) ?>">Open inbox →</a>
  </div>

  <?php if ($recentInquiries === []): ?>
    <p class="admin-empty">No inquiries have been received. The public forms are in the contact section of the <a href="<?= e(url('/#contact-forms')) ?>" target="_blank" rel="noopener">public website</a>.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-recent-inquiries">
        <caption class="admin-sr-only">Newest inquiries</caption>
        <thead>
          <tr>
            <th scope="col">Contact</th>
            <th scope="col">Type</th>
            <th scope="col">Source</th>
            <th scope="col">Status</th>
            <th scope="col">Received</th>
            <th scope="col"><span class="admin-sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentInquiries as $inquiry): ?>
            <tr>
              <td>
                <a class="admin-table-title" href="<?= e(admin_url('inquiry-view', ['id' => (int) $inquiry['id']])) ?>"><?= e((string) $inquiry['name']) ?></a>
                <span class="admin-table-sub"><?= e((string) $inquiry['email']) ?></span>
              </td>
              <td><span class="admin-inquiry-type"><?= e($inquiryTypeLabels[(string) $inquiry['inquiry_type']] ?? (string) $inquiry['inquiry_type']) ?></span></td>
              <td><code><?= e((string) $inquiry['source']) ?></code></td>
              <td><span class="admin-status admin-status-inquiry-<?= e((string) $inquiry['status']) ?>"><?= e($inquiryStatusLabels[(string) $inquiry['status']] ?? (string) $inquiry['status']) ?></span></td>
              <td class="admin-nowrap"><?= e(format_datetime((string) $inquiry['created_at'])) ?></td>
              <td><a class="admin-button admin-button-small admin-button-secondary" href="<?= e(admin_url('inquiry-view', ['id' => (int) $inquiry['id']])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($unverifiedCount > 0): ?>
  <section class="admin-callout admin-callout-gold" aria-labelledby="verification-callout-title">
    <div>
      <h2 id="verification-callout-title">Verification review needed</h2>
      <p><?= e((string) $unverifiedCount) ?> published product<?= $unverifiedCount === 1 ? '' : 's' ?> still carr<?= $unverifiedCount === 1 ? 'ies' : 'y' ?> unverified details. The public site shows only the name, category, and image for those records.</p>
    </div>
    <a class="admin-button admin-button-secondary" href="<?= e(admin_url('products', ['status' => 'published'])) ?>">Review products</a>
  </section>
<?php endif; ?>

<div class="admin-dashboard-grid">
  <section class="admin-panel" aria-labelledby="recent-products-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">CONTENT</p>
        <h2 id="recent-products-title">Recently updated products</h2>
      </div>
      <a class="admin-text-link" href="<?= e(admin_url('products')) ?>">All products →</a>
    </div>

    <?php if ($recentProducts === []): ?>
      <p class="admin-empty">No products yet. <a href="<?= e(admin_url('product-create')) ?>">Create the first product</a>.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="admin-sr-only">Recently updated products</caption>
          <thead>
            <tr>
              <th scope="col">Product</th>
              <th scope="col">Status</th>
              <th scope="col">Verification</th>
              <th scope="col">Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentProducts as $product): ?>
              <tr>
                <td>
                  <a class="admin-table-title" href="<?= e(admin_url('product-edit', ['id' => (int) $product['id']])) ?>"><?= e((string) $product['name']) ?></a>
                  <span class="admin-table-sub"><?= e((string) $product['category_name']) ?></span>
                </td>
                <td><span class="admin-status admin-status-<?= e((string) $product['status']) ?>"><?= e($statusLabels[(string) $product['status']] ?? (string) $product['status']) ?></span></td>
                <td><span class="admin-verification admin-verification-<?= e((string) $product['verification_status']) ?>"><?= e($verificationLabels[(string) $product['verification_status']] ?? (string) $product['verification_status']) ?></span></td>
                <td class="admin-nowrap"><?= e(format_date((string) $product['updated_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="admin-panel" aria-labelledby="advisor-status-title">
    <div class="admin-panel-heading">
      <div>
        <p class="admin-eyebrow">PROFILE</p>
        <h2 id="advisor-status-title">Advisor verification</h2>
      </div>
      <a class="admin-text-link" href="<?= e(admin_url('advisor')) ?>">Edit profile →</a>
    </div>

    <?php if ($advisor === null): ?>
      <p class="admin-empty">No advisor profile exists. Run the database seeder.</p>
    <?php else: ?>
      <dl class="admin-detail-list">
        <div>
          <dt>Display name</dt>
          <dd><?= e((string) $advisor['display_name']) ?></dd>
        </div>
        <div>
          <dt>Title</dt>
          <dd><?= e((string) ($advisor['title'] !== '' ? $advisor['title'] : 'Not set')) ?> <span class="admin-verification admin-verification-<?= $titleVerified ? 'verified' : 'unverified' ?>"><?= $titleVerified ? 'Verified' : 'Masked publicly' ?></span></dd>
        </div>
        <div>
          <dt>Organization / authorization</dt>
          <dd><?= e((string) ($advisor['organization'] !== '' ? $advisor['organization'] : 'Not set')) ?> <span class="admin-verification admin-verification-<?= $organizationVerified ? 'verified' : 'unverified' ?>"><?= $organizationVerified ? 'Verified' : 'Masked publicly' ?></span></dd>
        </div>
      </dl>
      <p class="admin-help">Only a super administrator can mark the title or AXA authorization as verified. Any title or organization edit clears that verification until it is reviewed again.</p>
    <?php endif; ?>
  </section>
</div>

<section class="admin-panel" aria-labelledby="quick-actions-title">
  <div class="admin-panel-heading">
    <div>
      <p class="admin-eyebrow">SHORTCUTS</p>
      <h2 id="quick-actions-title">Common tasks</h2>
    </div>
  </div>
  <div class="admin-quick-actions">
    <a class="admin-quick-link" href="<?= e(admin_url('inquiries', ['status' => 'new'])) ?>"><strong>Review inquiries</strong><span>Open new messages and requests.</span></a>
    <a class="admin-quick-link" href="<?= e(admin_url('product-create')) ?>"><strong>Add a product</strong><span>Create a draft or published record.</span></a>
    <a class="admin-quick-link" href="<?= e(admin_url('category-create')) ?>"><strong>Add a category</strong><span>Group related service options.</span></a>
    <a class="admin-quick-link" href="<?= e(admin_url('media')) ?>"><strong>Upload an image</strong><span>Add a JPEG, PNG, or WebP.</span></a>
    <a class="admin-quick-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><strong>View public website</strong><span>Check the visitor-facing result.</span></a>
  </div>
</section>
