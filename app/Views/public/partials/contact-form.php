<?php
/** @var string $type */
/** @var string $formType */
/** @var array<string, string> $formOld */
/** @var array<string, string> $formErrors */
/** @var string $privacyVersion */

$config = [
    'general' => [
        'prefix' => 'general',
        'kicker' => 'ASK A QUESTION',
        'title' => 'Send a general inquiry',
        'intro' => 'Share a question about protection planning, a product, or how to get started.',
        'action' => 'inquiry',
        'button' => 'Send inquiry',
        'message_label' => 'Your question',
        'message_placeholder' => 'Tell Kenneth what you would like to understand better.',
    ],
    'consultation' => [
        'prefix' => 'consultation',
        'kicker' => 'REQUEST A CONVERSATION',
        'title' => 'Request a consultation',
        'intro' => 'Ask for a no-obligation conversation about your priorities, questions, and possible next steps.',
        'action' => 'consultation',
        'button' => 'Request consultation',
        'message_label' => 'What would you like to discuss?',
        'message_placeholder' => 'Share your general goals or questions. Please do not include sensitive records.',
    ],
    'recruitment' => [
        'prefix' => 'recruitment',
        'kicker' => 'GROW WITH PURPOSE',
        'title' => 'Explore a career conversation',
        'intro' => 'Ask about financial advising, building a team, training, and what the role involves.',
        'action' => 'recruitment',
        'button' => 'Start a career conversation',
        'message_label' => 'Tell Kenneth about your interest',
        'message_placeholder' => 'What interests you about the role, and what would you like to learn?',
    ],
];

$formConfig = $config[$type] ?? $config['general'];
$prefix = 'contact-' . $formConfig['prefix'];
$formId = $prefix;
$isOpen = $formType === $type;
$value = static function (string $key, string $fallback = '') use ($formOld): string {
    if (!array_key_exists($key, $formOld)) {
        return $fallback;
    }

    return is_scalar($formOld[$key]) ? (string) $formOld[$key] : $fallback;
};
$error = static function (string $key) use ($formErrors): string {
    $message = $formErrors[$key] ?? '';
    return is_string($message) ? $message : '';
};
$errorId = static fn (string $key): string => $prefix . '-' . $key . '-error';
$describedBy = static function (string $key, string $extra = '') use ($error, $errorId, $prefix): string {
    $ids = [];
    if ($error($key) !== '') {
        $ids[] = $errorId($key);
    }
    if ($extra !== '') {
        $ids[] = $prefix . '-' . $extra;
    }

    return $ids === [] ? '' : ' aria-describedby="' . e(implode(' ', $ids)) . '"';
};
$inputClass = static fn (string $key): string => $error($key) !== '' ? 'is-invalid' : '';
?>
<details class="contact-form-card" id="<?= e($formId) ?>"<?= $isOpen ? ' open' : '' ?>>
  <summary>
    <span class="contact-form-summary">
      <span class="contact-form-kicker"><?= e($formConfig['kicker']) ?></span>
      <span class="contact-form-title"><?= e($formConfig['title']) ?></span>
    </span>
    <span class="contact-form-toggle" aria-hidden="true"></span>
  </summary>

  <form class="contact-form" method="post" action="<?= e(url($formConfig['action'])) ?>">
    <?= csrf_field() ?>

    <p class="contact-form-intro"><?= e($formConfig['intro']) ?></p>

    <div class="form-honeypot" aria-hidden="true">
      <label for="<?= e($prefix) ?>-company-website">Company website</label>
      <input id="<?= e($prefix) ?>-company-website" name="company_website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <?php if ($error('form') !== ''): ?>
      <p class="form-alert" role="alert"><?= e($error('form')) ?></p>
    <?php endif; ?>

    <?php if ($formErrors !== [] && $error('form') === ''): ?>
      <p class="form-alert" role="alert">Please correct the highlighted fields and try again.</p>
    <?php endif; ?>

    <div class="contact-form-grid">
      <div class="form-field">
        <label for="<?= e($prefix) ?>-name">Your name <span class="form-required" aria-hidden="true">*</span></label>
        <input id="<?= e($prefix) ?>-name" name="name" type="text" maxlength="120" required autocomplete="name" value="<?= e($value('name')) ?>" class="<?= e($inputClass('name')) ?>"<?= $describedBy('name') ?>>
        <?php if ($error('name') !== ''): ?><p class="form-error" id="<?= e($errorId('name')) ?>"><?= e($error('name')) ?></p><?php endif; ?>
      </div>

      <div class="form-field">
        <label for="<?= e($prefix) ?>-email">Email address <span class="form-required" aria-hidden="true">*</span></label>
        <input id="<?= e($prefix) ?>-email" name="email" type="email" maxlength="190" required autocomplete="email" inputmode="email" value="<?= e($value('email')) ?>" class="<?= e($inputClass('email')) ?>"<?= $describedBy('email') ?>>
        <?php if ($error('email') !== ''): ?><p class="form-error" id="<?= e($errorId('email')) ?>"><?= e($error('email')) ?></p><?php endif; ?>
      </div>

      <div class="form-field">
        <label for="<?= e($prefix) ?>-phone">Phone number <span class="form-optional">(optional)</span></label>
        <input id="<?= e($prefix) ?>-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?= e($value('phone')) ?>" class="<?= e($inputClass('phone')) ?>"<?= $describedBy('phone') ?>>
        <?php if ($error('phone') !== ''): ?><p class="form-error" id="<?= e($errorId('phone')) ?>"><?= e($error('phone')) ?></p><?php endif; ?>
      </div>

      <?php if ($type === 'general'): ?>
        <div class="form-field">
          <label for="<?= e($prefix) ?>-topic">What is this about? <span class="form-optional">(optional)</span></label>
          <select id="<?= e($prefix) ?>-topic" name="topic" class="<?= e($inputClass('topic')) ?>"<?= $describedBy('topic') ?>>
            <option value="">Choose a topic</option>
            <?php foreach (['General question', 'Product information', 'Existing concern', 'Something else'] as $option): ?>
              <option value="<?= e($option) ?>"<?= selected($value('topic') === $option) ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($error('topic') !== ''): ?><p class="form-error" id="<?= e($errorId('topic')) ?>"><?= e($error('topic')) ?></p><?php endif; ?>
        </div>
      <?php elseif ($type === 'consultation'): ?>
        <div class="form-field">
          <label for="<?= e($prefix) ?>-topic">Area of interest <span class="form-optional">(optional)</span></label>
          <select id="<?= e($prefix) ?>-topic" name="topic" class="<?= e($inputClass('topic')) ?>"<?= $describedBy('topic') ?>>
            <option value="">Choose an area</option>
            <?php foreach (['Life & family protection', 'Health & critical illness', 'Savings & investments', 'Income & asset protection', 'Not sure yet'] as $option): ?>
              <option value="<?= e($option) ?>"<?= selected($value('topic') === $option) ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($error('topic') !== ''): ?><p class="form-error" id="<?= e($errorId('topic')) ?>"><?= e($error('topic')) ?></p><?php endif; ?>
        </div>

        <div class="form-field">
          <label for="<?= e($prefix) ?>-preferred-contact">Preferred contact method</label>
          <select id="<?= e($prefix) ?>-preferred-contact" name="preferred_contact" class="<?= e($inputClass('preferred_contact')) ?>"<?= $describedBy('preferred_contact') ?>>
            <?php foreach (['email' => 'Email', 'phone' => 'Phone call', 'whatsapp' => 'WhatsApp'] as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= selected($value('preferred_contact', 'email') === $key) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($error('preferred_contact') !== ''): ?><p class="form-error" id="<?= e($errorId('preferred_contact')) ?>"><?= e($error('preferred_contact')) ?></p><?php endif; ?>
        </div>

        <div class="form-field">
          <label for="<?= e($prefix) ?>-preferred-time">Best time to reach you <span class="form-optional">(optional)</span></label>
          <input id="<?= e($prefix) ?>-preferred-time" name="preferred_time" type="text" maxlength="120" value="<?= e($value('preferred_time')) ?>" class="<?= e($inputClass('preferred_time')) ?>" placeholder="e.g. weekday evenings"<?= $describedBy('preferred_time') ?>>
          <?php if ($error('preferred_time') !== ''): ?><p class="form-error" id="<?= e($errorId('preferred_time')) ?>"><?= e($error('preferred_time')) ?></p><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="form-field">
          <label for="<?= e($prefix) ?>-topic">Area of interest <span class="form-optional">(optional)</span></label>
          <select id="<?= e($prefix) ?>-topic" name="topic" class="<?= e($inputClass('topic')) ?>"<?= $describedBy('topic') ?>>
            <option value="">Choose an area</option>
            <?php foreach (['Becoming a financial advisor', 'Building a team', 'Training and development', 'Learning more'] as $option): ?>
              <option value="<?= e($option) ?>"<?= selected($value('topic') === $option) ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($error('topic') !== ''): ?><p class="form-error" id="<?= e($errorId('topic')) ?>"><?= e($error('topic')) ?></p><?php endif; ?>
        </div>

        <div class="form-field">
          <label for="<?= e($prefix) ?>-preferred-contact">Preferred contact method</label>
          <select id="<?= e($prefix) ?>-preferred-contact" name="preferred_contact" class="<?= e($inputClass('preferred_contact')) ?>"<?= $describedBy('preferred_contact') ?>>
            <?php foreach (['email' => 'Email', 'phone' => 'Phone call', 'whatsapp' => 'WhatsApp'] as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= selected($value('preferred_contact', 'email') === $key) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($error('preferred_contact') !== ''): ?><p class="form-error" id="<?= e($errorId('preferred_contact')) ?>"><?= e($error('preferred_contact')) ?></p><?php endif; ?>
        </div>

        <div class="form-field">
          <label for="<?= e($prefix) ?>-location">Location <span class="form-optional">(optional)</span></label>
          <input id="<?= e($prefix) ?>-location" name="location" type="text" maxlength="120" autocomplete="address-level2" value="<?= e($value('location')) ?>" class="<?= e($inputClass('location')) ?>"<?= $describedBy('location') ?>>
          <?php if ($error('location') !== ''): ?><p class="form-error" id="<?= e($errorId('location')) ?>"><?= e($error('location')) ?></p><?php endif; ?>
        </div>

        <div class="form-field form-field-full">
          <label for="<?= e($prefix) ?>-experience">Relevant background <span class="form-optional">(optional)</span></label>
          <textarea id="<?= e($prefix) ?>-experience" name="experience" rows="3" maxlength="500" class="<?= e($inputClass('experience')) ?>"<?= $describedBy('experience') ?>><?= e($value('experience')) ?></textarea>
          <p class="form-help" id="<?= e($prefix) ?>-experience-help">Keep this brief. Do not include government ID numbers or sensitive records.</p>
          <?php if ($error('experience') !== ''): ?><p class="form-error" id="<?= e($errorId('experience')) ?>"><?= e($error('experience')) ?></p><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="form-field form-field-full">
        <label for="<?= e($prefix) ?>-message"><?= e($formConfig['message_label']) ?> <span class="form-required" aria-hidden="true">*</span></label>
        <textarea id="<?= e($prefix) ?>-message" name="message" rows="5" minlength="10" maxlength="5000" required class="<?= e($inputClass('message')) ?>" placeholder="<?= e($formConfig['message_placeholder']) ?>"<?= $describedBy('message', 'message-help') ?>><?= e($value('message')) ?></textarea>
        <p class="form-help" id="<?= e($prefix) ?>-message-help">Please do not include medical records, government ID numbers, bank details, or policy numbers.</p>
        <?php if ($error('message') !== ''): ?><p class="form-error" id="<?= e($errorId('message')) ?>"><?= e($error('message')) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="form-consent-wrap">
      <label class="form-consent" for="<?= e($prefix) ?>-consent">
        <input id="<?= e($prefix) ?>-consent" name="consent" type="checkbox" value="1" required<?= checked($value('consent') === '1') ?><?= $error('consent') !== '' ? ' aria-invalid="true"' : '' ?><?= $describedBy('consent') ?>>
        <span>I have read the <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener">privacy notice</a> (version <?= e($privacyVersion) ?>) and consent to being contacted about this request using the details I provide. <span class="form-required" aria-hidden="true">*</span></span>
      </label>
      <?php if ($error('consent') !== ''): ?><p class="form-error" id="<?= e($errorId('consent')) ?>"><?= e($error('consent')) ?></p><?php endif; ?>
    </div>

    <div class="contact-form-actions">
      <button class="primary-button contact-submit" type="submit"><?= e($formConfig['button']) ?> <span aria-hidden="true">→</span></button>
      <p class="contact-form-privacy">A record of your consent is stored with the message. This form does not bind coverage, issue a policy, or provide personalized financial advice.</p>
    </div>
  </form>
</details>
