<?php
/** @var array<string, mixed> $profile */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $products */
/** @var array<string, string> $settings */
/** @var array<string, mixed> $structuredData */
/** @var string $privacyVersion */
/** @var string $formType */
/** @var array<string, string> $formOld */
/** @var array<string, string> $formErrors */

$displayName = trim((string) ($profile['display_name'] ?? '')) ?: 'Kenneth';
$tagline = trim((string) ($profile['tagline'] ?? '')) ?: 'Protection · Purpose · People';
$bio = trim((string) ($profile['bio'] ?? ''));
$titleVerified = is_verified($profile, 'title') && trim((string) ($profile['title'] ?? '')) !== '';
$organizationVerified = is_verified($profile, 'authorization') && trim((string) ($profile['organization'] ?? '')) !== '';
$verificationPending = !$titleVerified || !$organizationVerified;

$roleParts = [];
if ($titleVerified) {
    $roleParts[] = (string) $profile['title'];
}
if ($organizationVerified) {
    $roleParts[] = (string) $profile['organization'];
}
if ($roleParts === []) {
    $roleParts[] = 'Protection planning';
}

$email = trim((string) ($profile['email'] ?? ''));
$phone = trim((string) ($profile['phone'] ?? ''));
$whatsapp = trim((string) ($profile['whatsapp'] ?? ''));
$messengerUrl = safe_web_url(isset($profile['messenger_url']) ? (string) $profile['messenger_url'] : null);
$facebookUrl = safe_web_url(isset($profile['facebook_url']) ? (string) $profile['facebook_url'] : null);
$contactNote = trim((string) ($settings['contact_response_note'] ?? ''));
$productCount = count($products);
?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<section class="hero" id="home" aria-labelledby="hero-title">
  <div class="hero-copy">
    <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span> PERSONAL INTRODUCTION</p>
    <div class="hero-kicker">A more confident future starts here.</div>
    <h1 id="hero-title">Plan with purpose.<br><em>Protect what matters.</em></h1>
    <p class="hero-intro">A personal connection for your protection needs, financial goals, and a future built with confidence.</p>
  </div>

  <div class="portrait-stage">
    <div class="portrait-orbit-label" aria-hidden="true"><?= e(mb_strtoupper($tagline)) ?></div>
    <div class="halo halo-one" aria-hidden="true"></div>
    <div class="halo halo-two" aria-hidden="true"></div>
    <div class="portrait-frame">
      <img id="portrait-photo" src="<?= e(advisor_photo_url($profile)) ?>" alt="<?= e((string) ($profile['photo_alt'] ?? $displayName)) ?>" width="1086" height="1086" fetchpriority="high">
      <div class="photo-placeholder" id="photo-placeholder" aria-hidden="true">
        <span class="placeholder-crown" aria-hidden="true">♛</span>
        <span>Photo unavailable</span>
        <small>Please use the contact options below</small>
      </div>
    </div>
    <div class="floating-crown" aria-hidden="true">♛</div>
    <div class="podium" aria-hidden="true">
      <div class="podium-top"></div>
      <div class="podium-front">
        <span class="podium-name"><?= e(mb_strtoupper($displayName)) ?></span>
        <span class="podium-role">PROTECTION PLANNING</span>
      </div>
    </div>
  </div>

  <div class="profile-name">
    <p class="name-label">MEET YOUR ADVISOR</p>
    <h2><?= e($displayName) ?></h2>
    <p class="role-line"><?= e(implode(' • ', $roleParts)) ?></p>
    <?php if ($bio !== ''): ?>
      <p class="profile-description"><?= e($bio) ?></p>
    <?php endif; ?>
    <?php if ($verificationPending): ?>
      <p class="verification-note">Advisor title, credentials, and AXA authorization are pending independent verification. This personal website does not represent an official AXA Philippines page.</p>
    <?php endif; ?>
    <a class="primary-button" href="#contact">Start a conversation <span aria-hidden="true">→</span></a>
  </div>
</section>

<section class="section intro-section" aria-labelledby="options-title">
  <div class="section-heading">
    <p class="eyebrow">EXPLORE YOUR OPTIONS</p>
    <h2 id="options-title">Protection for every<br><em>chapter of life.</em></h2>
    <p>Explore these general insurance categories and selected AXA Philippines plans. Product details remain subject to verification and official policy terms. Eligibility, benefits, exclusions, and availability depend on the current official product documents.</p>
  </div>

  <?php if ($categories === []): ?>
    <p class="empty-note">Service categories are being updated. Please start a conversation to ask about current options.</p>
  <?php else: ?>
    <div class="category-grid">
      <?php foreach ($categories as $category): ?>
        <article class="category-card">
          <div class="category-icon" aria-hidden="true"><?= e((string) ($category['icon'] ?? '•')) ?></div>
          <h3><?= e((string) $category['name']) ?></h3>
          <?php if (trim((string) ($category['summary'] ?? '')) !== ''): ?>
            <p><?= e((string) $category['summary']) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="section plans-section" aria-labelledby="plans-title">
  <div class="section-heading split-heading">
    <div>
      <p class="eyebrow">SELECTED AXA PLANS</p>
      <h2 id="plans-title">Learn before<br><em>you decide.</em></h2>
    </div>
    <p>These examples are listed to help you start a conversation. Unverified product descriptions are withheld. Ask for the current official brochure and policy documents before making a decision.</p>
  </div>

  <?php if ($products === []): ?>
    <p class="empty-note">Selected plans are being reviewed. Please start a conversation to ask about current options.</p>
  <?php else: ?>
    <?php if ($productCount > 1): ?>
      <div class="showcase-controls">
        <button class="slide-arrow" id="prev-plan" type="button" aria-label="Previous plan" aria-controls="plans-track">‹</button>
        <div class="slide-dots" id="plan-dots" role="group" aria-label="Choose a plan"></div>
        <button class="slide-arrow" id="next-plan" type="button" aria-label="Next plan" aria-controls="plans-track">›</button>
      </div>
      <p class="sr-only" id="carousel-instructions">Use the previous and next buttons, the plan dots, or the left and right arrow keys to move between plans.</p>
      <p class="sr-only" id="plan-status" aria-live="polite"></p>
    <?php endif; ?>
    <div class="plans-showcase" id="plans-showcase" role="region" aria-roledescription="carousel" aria-label="AXA plan showcase"<?= $productCount > 1 ? ' aria-describedby="carousel-instructions" tabindex="0"' : '' ?>>
      <div class="plans-track" id="plans-track">
        <?php foreach ($products as $index => $product): ?>
          <?php
          $verified = (string) ($product['verification_status'] ?? '') === 'verified';
          $productName = (string) $product['name'];
          $productSummary = trim((string) ($product['summary'] ?? ''));
          $officialUrl = safe_web_url(isset($product['official_url']) ? (string) $product['official_url'] : null);
          $imageAlt = trim((string) ($product['image_alt'] ?? '')) ?: $productName;
          ?>
          <div class="plan-card" role="group" aria-roledescription="slide" aria-label="Plan <?= e((string) ($index + 1)) ?> of <?= e((string) $productCount) ?>: <?= e($productName) ?>">
            <img class="plan-photo" src="<?= e(product_image_url($product)) ?>" alt="<?= e($imageAlt) ?>" loading="lazy" decoding="async">
            <div class="plan-number" aria-hidden="true"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></div>
            <div class="plan-info">
              <p class="plan-type"><?= e(mb_strtoupper((string) ($product['product_type'] ?: $product['category_name'] ?? ''))) ?></p>
              <h3><?= e($productName) ?></h3>
              <?php if ($verified && $productSummary !== ''): ?>
                <p><?= e($productSummary) ?></p>
              <?php else: ?>
                <p class="product-verification-note">Product details are being verified and are not published here yet. Ask Kenneth for the current official product terms.</p>
              <?php endif; ?>
              <?php if ($verified && $officialUrl !== ''): ?>
                <a href="<?= e($officialUrl) ?>" target="_blank" rel="noopener noreferrer">Official product details <span aria-hidden="true">↗</span></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($productCount > 1): ?>
      <p class="showcase-hint"><span class="hint-pulse" aria-hidden="true"></span> Swipe, use the arrows, or press the left/right arrow keys to explore plans</p>
    <?php endif; ?>
  <?php endif; ?>

  <p class="disclaimer">Product names and general descriptions are provided for information and may change. This page is not a policy contract or a guarantee of coverage, returns, or acceptance. Unverified details are withheld. Refer to AXA Philippines' current official materials and policy documents for full terms, exclusions, charges, and risks.</p>
</section>

<section class="recruit-section" aria-labelledby="recruit-title">
  <div class="recruit-decoration" aria-hidden="true">♛</div>
  <p class="eyebrow">GROW WITH PURPOSE</p>
  <h2 id="recruit-title">Interested in building<br><em>a career with us?</em></h2>
  <p>If you're curious about financial advising, leadership, or the opportunity to grow a team, connect with Kenneth to learn about the role, expectations, training, and next steps.</p>
  <a class="secondary-button" href="#contact-forms">Ask about joining the team <span aria-hidden="true">→</span></a>
</section>

<section class="section contact-section" id="contact" aria-labelledby="contact-title">
  <div class="section-heading">
    <p class="eyebrow">LET'S CONNECT</p>
    <h2 id="contact-title">Your goals.<br><em>One conversation at a time.</em></h2>
    <p>Ask a question, request a consultation, or learn more about joining the team.</p>
  </div>

  <div class="contact-grid">
    <?php if ($messengerUrl !== ''): ?>
      <a class="contact-button" href="<?= e($messengerUrl) ?>" target="_blank" rel="noopener noreferrer"><span class="contact-symbol" aria-hidden="true">➤</span><span><strong>Messenger</strong><small>Send Kenneth a message</small></span><b aria-hidden="true">↗</b></a>
    <?php endif; ?>
    <?php if ($whatsapp !== ''): ?>
      <a class="contact-button" href="<?= e(\AxaKenneth\Support\Str::whatsappHref($whatsapp)) ?>" target="_blank" rel="noopener noreferrer"><span class="contact-symbol" aria-hidden="true">◉</span><span><strong>WhatsApp</strong><small>Chat directly</small></span><b aria-hidden="true">↗</b></a>
    <?php endif; ?>
    <?php if ($phone !== ''): ?>
      <a class="contact-button" href="<?= e(\AxaKenneth\Support\Str::telHref($phone)) ?>"><span class="contact-symbol" aria-hidden="true">☎</span><span><strong>Call <?= e($displayName) ?></strong><small>Talk about your goals</small></span><b aria-hidden="true">↗</b></a>
    <?php endif; ?>
    <?php if ($email !== ''): ?>
      <a class="contact-button" href="mailto:<?= e($email) ?>"><span class="contact-symbol" aria-hidden="true">✉</span><span><strong>Email</strong><small>Send an inquiry</small></span><b aria-hidden="true">↗</b></a>
    <?php endif; ?>
    <?php if ($facebookUrl !== '' && $facebookUrl !== $messengerUrl): ?>
      <a class="contact-button" href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer"><span class="contact-symbol" aria-hidden="true">f</span><span><strong>Facebook</strong><small>Visit Kenneth's page</small></span><b aria-hidden="true">↗</b></a>
    <?php endif; ?>
  </div>

  <?php if ($contactNote !== ''): ?>
    <p class="contact-note"><?= e($contactNote) ?></p>
  <?php endif; ?>

  <div class="contact-forms" id="contact-forms">
    <div class="contact-forms-heading">
      <p class="eyebrow">SEND A MESSAGE</p>
      <h3>Choose the conversation<br><em>that fits your question.</em></h3>
      <p>Your message is saved securely in Kenneth's private administration inbox. Fields marked <span class="form-required">*</span> are required.</p>
    </div>

    <div class="contact-form-list">
      <?php foreach (['general', 'consultation', 'recruitment'] as $type): ?>
        <?php require __DIR__ . '/partials/contact-form.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="save-contact-wrap">
    <a class="save-contact" href="<?= e(url('contact.vcf')) ?>" download>＋ Save <?= e($displayName) ?> to Contacts</a>
  </div>
</section>

<?php if ($formType !== ''): ?>
  <script nonce="<?= e(csp_nonce()) ?>">
    (() => {
      const panel = document.getElementById("<?= e('contact-' . $formType) ?>");
      if (!panel) return;
      panel.setAttribute("open", "");
      panel.scrollIntoView({ block: "start" });
      const invalid = panel.querySelector(".is-invalid, [aria-invalid='true']");
      if (invalid instanceof HTMLElement) invalid.focus({ preventScroll: true });
    })();
  </script>
<?php endif; ?>
