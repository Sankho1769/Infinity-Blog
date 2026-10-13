<?php
declare(strict_types=1);
/**
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 * @var bool $isSubmitted
 * @var int|null $ticketId
 * @var string|null $errorMessage
 * @var array<string, string> $errors
 * @var array<string, string> $formData
 */
$isSubmitted = !empty($isSubmitted) || (!empty($_GET['submitted']) && $_GET['submitted'] === '1');
$ticketId = $ticketId ?? (!empty($_GET['ticket']) ? (int) $_GET['ticket'] : null);
$errors = $errors ?? [];
$formData = $formData ?? [];
?>
<section class="page-container" style="max-width: 860px; margin: 40px auto; padding: 0 16px;">
  <div class="glass-card" style="padding: 40px 36px; border-radius: var(--radius-xl);">
    <div style="display: inline-block; padding: 4px 14px; border-radius: 9999px; background: rgba(99, 102, 241, 0.12); color: var(--primary-600, #4f46e5); font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px;">
      Get In Touch
    </div>
    <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-heading); margin-bottom: 12px; letter-spacing: -0.02em;">
      Contact Infinity Blog
    </h1>
    <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.6; margin-bottom: 32px;">
      Have a question about our publishing platform, need editorial assistance, or wish to report a bug? Send us a message below and our support team will get back to you.
    </p>

    <?php if ($isSubmitted): ?>
      <!-- Confirmation State -->
      <div class="alert-banner alert-success" role="status" aria-live="polite" style="padding: 24px; border-radius: var(--radius-lg); margin-bottom: 32px; background: rgba(236, 253, 245, 0.9); border: 1px solid rgba(16, 185, 129, 0.4); color: #065f46;">
        <div style="display: flex; align-items: flex-start; gap: 14px;">
          <span style="font-size: 1.8rem; line-height: 1;">✅</span>
          <div>
            <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 6px; color: #047857;">Message Received Successfully</h3>
            <p style="font-size: 0.95rem; line-height: 1.6; margin-bottom: 12px; color: #065f46;">
              Thank you for reaching out! Your message has been safely recorded in our support queue
              <?php if (!empty($ticketId)): ?>
                (<strong>Reference #<?= (int) $ticketId ?></strong>)
              <?php endif; ?>.
              Our team reviews inquiries Monday through Friday and will reply to your provided email address.
            </p>
            <div style="font-size: 0.82rem; color: #047857;">
              Notice: Direct SMTP transmission is configured for asynchronous background dispatch. If your inquiry is urgent, you may also email support directly.
            </div>
          </div>
        </div>
        <div style="margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
          <a href="/contact" class="btn btn-secondary btn-sm btn-pill">Send Another Message</a>
          <a href="/explore" class="btn btn-primary btn-sm btn-pill">Explore Articles</a>
        </div>
      </div>
    <?php else: ?>

      <?php if (!empty($errorMessage)): ?>
        <div class="alert-banner alert-error" role="alert" style="margin-bottom: 24px;">
          <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <!-- Direct Contact Channels -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 36px;">
        <div class="glass-card" style="padding: 22px; border-radius: 16px; background: rgba(255, 255, 255, 0.6);">
          <div style="font-size: 1.3rem; margin-bottom: 6px;">✍️</div>
          <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; color: var(--text-heading);">Editorial Inquiries</h3>
          <p style="font-size: 0.86rem; color: var(--text-muted); margin-bottom: 8px;">Article pitches, publishing feedback &amp; remix permissions.</p>
          <?php if (!empty($editorialEmail)): ?>
            <a href="mailto:<?= htmlspecialchars($editorialEmail, ENT_QUOTES, 'UTF-8') ?>" style="font-weight: 600; font-size: 0.9rem; color: var(--primary-600, #4f46e5); text-decoration: none;"><?= htmlspecialchars($editorialEmail, ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            <span style="font-size: 0.84rem; color: var(--text-muted);">Submit via the message form below</span>
          <?php endif; ?>
        </div>

        <div class="glass-card" style="padding: 22px; border-radius: 16px; background: rgba(255, 255, 255, 0.6);">
          <div style="font-size: 1.3rem; margin-bottom: 6px;">🛠️</div>
          <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; color: var(--text-heading);">Technical Support</h3>
          <p style="font-size: 0.86rem; color: var(--text-muted); margin-bottom: 8px;">Account access, security, bug reports &amp; data requests.</p>
          <?php if (!empty($supportEmail)): ?>
            <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" style="font-weight: 600; font-size: 0.9rem; color: var(--primary-600, #4f46e5); text-decoration: none;"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            <span style="font-size: 0.84rem; color: var(--text-muted);">Submit via the message form below</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Functional Glassmorphism Contact Form -->
      <div style="background: rgba(255, 255, 255, 0.5); padding: 32px; border-radius: var(--radius-lg); border: 1px solid var(--glass-border);">
        <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--text-heading); margin-bottom: 8px;">Send a Direct Message</h2>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 24px;">All fields marked with an asterisk are required.</p>

        <form method="POST" action="/contact" id="contactForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <!-- Anti-Spam Honeypot field (hidden from genuine users) -->
          <div style="display: none !important;" aria-hidden="true">
            <label for="contact_website_hp">Leave this field blank</label>
            <input type="text" name="website_hp" id="contact_website_hp" tabindex="-1" autocomplete="off">
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label for="contact-name" class="form-label">Your Full Name <span style="color: var(--danger-color);">*</span></label>
              <input type="text" id="contact-name" name="name" class="form-input <?= !empty($errors['name']) ? 'has-error' : '' ?>" placeholder="e.g. Cristine Palmer" value="<?= htmlspecialchars($formData['name'] ?? ($currentUser['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
              <?php if (!empty($errors['name'])): ?>
                <div class="form-error-msg" style="color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label for="contact-email" class="form-label">Email Address <span style="color: var(--danger-color);">*</span></label>
              <input type="email" id="contact-email" name="email" class="form-input <?= !empty($errors['email']) ? 'has-error' : '' ?>" placeholder="e.g. cristine.palmer@example.com" value="<?= htmlspecialchars($formData['email'] ?? ($currentUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required maxlength="190">
              <?php if (!empty($errors['email'])): ?>
                <div class="form-error-msg" style="color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label for="contact-subject" class="form-label">Topic / Subject <span style="color: var(--danger-color);">*</span></label>
            <input type="text" id="contact-subject" name="subject" class="form-input <?= !empty($errors['subject']) ? 'has-error' : '' ?>" placeholder="Brief summary of your inquiry..." value="<?= htmlspecialchars($formData['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="150">
            <?php if (!empty($errors['subject'])): ?>
              <div class="form-error-msg" style="color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;"><?= htmlspecialchars($errors['subject'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="contact-message" class="form-label">Message <span style="color: var(--danger-color);">*</span></label>
            <textarea id="contact-message" name="message" class="form-textarea <?= !empty($errors['message']) ? 'has-error' : '' ?>" placeholder="How can we assist you? Please provide sufficient detail..." style="min-height: 140px;" required maxlength="3000"><?= htmlspecialchars($formData['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php if (!empty($errors['message'])): ?>
              <div class="form-error-msg" style="color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;"><?= htmlspecialchars($errors['message'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 24px;">
            <div style="font-size: 0.8rem; color: var(--text-muted);">
              🔒 Protected by CSRF validation & anti-spam rate limits.
            </div>
            <button type="submit" id="contactSubmitBtn" class="btn btn-primary btn-pill">
              Send Message
            </button>
          </div>
        </form>
      </div>

    <?php endif; ?>

    <div style="border-top: 1px solid rgba(226, 232, 240, 0.6); padding-top: 24px; margin-top: 36px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <span style="font-size: 0.86rem; color: var(--text-muted);">Support response window: Monday – Friday, 9:00 AM – 6:00 PM UTC</span>
      <a href="/" class="btn btn-secondary btn-pill btn-sm">Return Home</a>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactForm');
  const submitBtn = document.getElementById('contactSubmitBtn');
  if (form && submitBtn) {
    form.addEventListener('submit', () => {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
    });
  }
});
</script>
