<?php
/** @var array<string, mixed> $inquiry */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var array<string, string> $typeLabels */
/** @var array<string, string> $statusLabels */
/** @var array<string, string> $preferredContactLabels */

$submitted = $old !== [];
$statusValue = $submitted ? (string) ($old['status'] ?? '') : (string) $inquiry['status'];
$notesValue = $submitted ? (string) ($old['internal_notes'] ?? '') : (string) ($inquiry['internal_notes'] ?? '');
$phone = (string) $inquiry['phone'];
$type = (string) $inquiry['inquiry_type'];
?>
<div class="admin-page-actions">
  <a class="admin-text-link" href="<?= e(admin_url('inquiries')) ?>">← Inquiries</a>
  <span class="admin-status admin-status-inquiry-<?= e((string) $inquiry['status']) ?>"><?= e($statusLabels[(string) $inquiry['status']] ?? (string) $inquiry['status']) ?></span>
</div>

<?php if ($errors !== []): ?>
  <div class="admin-flash admin-flash-error" role="alert">
    <strong>Please correct the highlighted fields.</strong>
    <ul class="admin-error-list">
      <?php foreach (array_unique(array_values($errors)) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admin-inquiry-layout">
  <div class="admin-inquiry-main">
    <section class="admin-panel" aria-labelledby="inquiry-message-title">
      <div class="admin-panel-heading">
        <div>
          <p class="admin-eyebrow"><?= e(mb_strtoupper($typeLabels[$type] ?? $type)) ?></p>
          <h2 id="inquiry-message-title">Message from <?= e((string) $inquiry['name']) ?></h2>
        </div>
        <span class="admin-inquiry-source">Source: <code><?= e((string) $inquiry['source']) ?></code></span>
      </div>

      <?php if ((string) $inquiry['topic'] !== ''): ?>
        <p class="admin-inquiry-topic"><strong>Topic:</strong> <?= e((string) $inquiry['topic']) ?></p>
      <?php endif; ?>

      <div class="admin-message"><?= e((string) $inquiry['message']) ?></div>

      <?php if ((string) $inquiry['experience'] !== ''): ?>
        <div class="admin-inquiry-extra">
          <h3>Relevant background</h3>
          <p><?= nl2br(e((string) $inquiry['experience'])) ?></p>
        </div>
      <?php endif; ?>

      <p class="admin-help">Submitted content is untrusted visitor input. Do not paste it into another system as instructions or execute any links or files it may mention.</p>
    </section>

    <section class="admin-panel" aria-labelledby="inquiry-workflow-title">
      <div class="admin-panel-heading">
        <div>
          <p class="admin-eyebrow">FOLLOW-UP</p>
          <h2 id="inquiry-workflow-title">Workflow and internal notes</h2>
        </div>
      </div>

      <form class="admin-form" method="post" action="<?= e(admin_url('inquiry-update', ['id' => (int) $inquiry['id']])) ?>">
        <?= csrf_field() ?>

        <div class="admin-form-grid">
          <div class="admin-field">
            <label for="status">Status</label>
            <select id="status" name="status" class="<?= e(field_invalid($errors, 'status')) ?>" required>
              <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= selected($statusValue === $key) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'status') ?>
          </div>

          <div class="admin-field admin-field-full">
            <label for="internal_notes">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="5" maxlength="5000" class="<?= e(field_invalid($errors, 'internal_notes')) ?>" placeholder="Record next steps or context for authorized administrators only."><?= e($notesValue) ?></textarea>
            <p class="admin-help">Notes are private to administrators. Do not record medical details, identification numbers, bank details, policy numbers, or passwords here.</p>
            <?= field_error($errors, 'internal_notes') ?>
          </div>
        </div>

        <div class="admin-form-actions admin-form-actions-start">
          <button class="admin-button admin-button-primary" type="submit">Save workflow</button>
        </div>
      </form>

      <dl class="admin-detail-list admin-detail-list-compact admin-inquiry-timestamps">
        <div><dt>First reviewed</dt><dd><?= e(format_datetime($inquiry['first_reviewed_at'] ?? null)) ?></dd></div>
        <div><dt>First contacted</dt><dd><?= e(format_datetime($inquiry['first_contacted_at'] ?? null)) ?></dd></div>
        <div><dt>Closed</dt><dd><?= e(format_datetime($inquiry['closed_at'] ?? null)) ?></dd></div>
        <div><dt>Last handled by</dt><dd><?= e((string) ($inquiry['handled_by_name'] ?? 'Not yet assigned')) ?></dd></div>
      </dl>
    </section>
  </div>

  <aside class="admin-inquiry-side" aria-label="Inquiry summary">
    <section class="admin-panel" aria-labelledby="inquiry-contact-title">
      <div class="admin-panel-heading">
        <div>
          <p class="admin-eyebrow">CONTACT</p>
          <h2 id="inquiry-contact-title">Submission details</h2>
        </div>
      </div>

      <dl class="admin-detail-list">
        <div><dt>Name</dt><dd><?= e((string) $inquiry['name']) ?></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?= e((string) $inquiry['email']) ?>"><?= e((string) $inquiry['email']) ?></a></dd></div>
        <div><dt>Phone</dt><dd><?php if ($phone !== ''): ?><a href="<?= e(\AxaKenneth\Support\Str::telHref($phone)) ?>"><?= e($phone) ?></a><?php else: ?>—<?php endif; ?></dd></div>
        <div><dt>Preferred contact</dt><dd><?= e($preferredContactLabels[(string) $inquiry['preferred_contact']] ?? (string) $inquiry['preferred_contact']) ?></dd></div>
        <?php if ((string) $inquiry['preferred_time'] !== ''): ?><div><dt>Best time</dt><dd><?= e((string) $inquiry['preferred_time']) ?></dd></div><?php endif; ?>
        <?php if ((string) $inquiry['location'] !== ''): ?><div><dt>Location</dt><dd><?= e((string) $inquiry['location']) ?></dd></div><?php endif; ?>
        <div><dt>Received</dt><dd><?= e(format_datetime((string) $inquiry['created_at'])) ?></dd></div>
        <div><dt>Source</dt><dd><code><?= e((string) $inquiry['source']) ?></code></dd></div>
      </dl>

      <?php if ($phone !== ''): ?>
        <div class="admin-inquiry-contact-actions">
          <a class="admin-button admin-button-secondary" href="<?= e(\AxaKenneth\Support\Str::whatsappHref($phone)) ?>" target="_blank" rel="noopener noreferrer">Open WhatsApp <span aria-hidden="true">↗</span></a>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-panel" aria-labelledby="inquiry-consent-title">
      <div class="admin-panel-heading">
        <div>
          <p class="admin-eyebrow">PRIVACY</p>
          <h2 id="inquiry-consent-title">Consent record</h2>
        </div>
      </div>
      <dl class="admin-detail-list">
        <div><dt>Consent recorded</dt><dd><?= e(format_datetime((string) $inquiry['consent_at'])) ?></dd></div>
        <div><dt>Notice version</dt><dd><code><?= e((string) $inquiry['privacy_version']) ?></code></dd></div>
      </dl>
      <p class="admin-help">The visitor confirmed consent before this record was accepted. A later privacy notice version does not rewrite the version recorded here.</p>
    </section>
  </aside>
</div>
