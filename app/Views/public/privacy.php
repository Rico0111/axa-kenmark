<?php
/** @var array<string, mixed> $profile */
/** @var array<string, string> $settings */
/** @var string $privacyVersion */
/** @var string $effectiveDate */

$displayName = trim((string) ($profile['display_name'] ?? '')) ?: 'Kenneth';
$email = trim((string) ($profile['email'] ?? ''));
$phone = trim((string) ($profile['phone'] ?? ''));
?>
<section class="policy-hero" aria-labelledby="privacy-title">
  <p class="eyebrow">PRIVACY &amp; YOUR INFORMATION</p>
  <h1 id="privacy-title">Privacy notice</h1>
  <p class="policy-meta">Version <?= e($privacyVersion) ?> · Effective <?= e($effectiveDate) ?></p>
</section>

<section class="section policy-section">
  <div class="policy-content">
    <p class="policy-intro">This notice explains how information you choose to share through this personal website is handled. It applies to this website and to messages sent using the contact details published here.</p>

    <h2>Who is responsible</h2>
    <p>This is a personal website maintained by <?= e($displayName) ?>. It is not an official AXA Philippines website. Questions about this notice can be sent using the contact options at the end of this page.</p>

    <h2>Information that may be collected</h2>
    <ul>
      <li>Contact details and other information you choose to provide when you send a message or ask for a consultation, such as your name, email address, phone number, and the general nature of your inquiry.</li>
      <li>Recruitment information you choose to provide if you ask about joining a team, such as your background and contact details.</li>
      <li>Limited technical information created when the website is visited, such as server logs used for security, troubleshooting, and abuse prevention, plus a non-personal campaign or NFC source identifier when one is present in the link you used.</li>
      <li>A record that you gave consent before a form was submitted, including the privacy notice version you accepted and the time of submission.</li>
      <li>A strictly functional session cookie used to protect forms against cross-site request forgery and to keep administrative sign-in sessions secure. Public visitors do not need an account to browse the site.</li>
    </ul>
    <p>Please do not send sensitive information such as medical records, government identification numbers, bank details, or policy numbers through this website. Those details should only be discussed through an appropriate, verified channel.</p>

    <h2>How information is used</h2>
    <ul>
      <li>To respond to your question and arrange a conversation at your request.</li>
      <li>To understand which general topic you are asking about and route your inquiry appropriately.</li>
      <li>To maintain the security and reliability of the website and prevent misuse.</li>
      <li>To keep records needed to manage inquiries and recruitment conversations.</li>
    </ul>
    <p>Information is not sold. It is not used to issue a policy, bind coverage, process a claim, or collect a premium through this website.</p>

    <h2>Consent and choices</h2>
    <p>By submitting an inquiry, you consent to being contacted about that inquiry using the details you provide. Each accepted submission records the consent confirmation, the privacy notice version shown at the time, and a non-personal source identifier if you arrived through a campaign or NFC link. You may ask to withdraw that consent, request access or correction, or ask for your inquiry record to be deleted, subject to any records that must be retained for legitimate business or legal reasons.</p>

    <h2>Sharing and retention</h2>
    <p>Information may be shared with service providers that help operate the website or administer inquiries, only as needed for those purposes and with appropriate safeguards. It may also be disclosed when required by law or to protect the security of the website and its users.</p>
    <p>Inquiry records are kept only for as long as needed for the purpose they were collected, for dispute resolution, or as required by applicable rules. Technical logs are rotated or removed periodically.</p>

    <h2>Security</h2>
    <p>Reasonable technical and organizational measures are used to protect information, including restricted access, encrypted passwords for administrators, secure session handling, and validation of submitted data. No website or transmission method can guarantee absolute security. Please use caution when sharing information online.</p>

    <h2>Cookies and external links</h2>
    <p>This website uses a strictly functional session cookie. It is created when you open a public form or the administration area, and it protects submissions against cross-site request forgery, retains a campaign or NFC source identifier for the current visit, and keeps administrator sign-in sessions secure. It is not used for advertising or cross-site tracking. Fonts and some images may be loaded from external providers, which can receive basic technical information such as your IP address. Links to AXA Philippines or social-media services are governed by those services' own privacy notices.</p>

    <h2>Updates to this notice</h2>
    <p>This notice may be updated when the website or its processes change. The version and effective date at the top of this page identify the current notice.</p>

    <h2>Contact</h2>
    <p>To ask a privacy question or make a request, contact <?= e($displayName) ?>:</p>
    <ul class="policy-contact">
      <?php if ($email !== ''): ?><li>Email: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
      <?php if ($phone !== ''): ?><li>Phone: <a href="<?= e(\AxaKenneth\Support\Str::telHref($phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
      <li><a href="<?= e(url('/#contact')) ?>">Return to the contact options</a></li>
    </ul>

    <p class="policy-back"><a class="secondary-button policy-button" href="<?= e(url('/')) ?>">Back to the website <span aria-hidden="true">→</span></a></p>
  </div>
</section>
