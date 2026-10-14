<!-- Screen: User Registration Page -->
<style>
  .field-error-msg {
    color: #dc2626;
    font-size: 0.8rem;
    font-weight: 500;
    margin-top: 6px;
    display: none;
    line-height: 1.35;
  }
  .input-with-icon.has-error .form-input,
  .form-input.is-invalid {
    border-color: #ef4444 !important;
    background-color: rgba(254, 242, 242, 0.7) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
  }
  .input-with-icon.has-error .input-icon {
    color: #ef4444 !important;
  }
  .alert-banner.alert-error {
    background: rgba(254, 242, 242, 0.95);
    border: 1px solid rgba(254, 202, 202, 0.9);
    color: #991b1b;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.875rem;
    line-height: 1.45;
    margin-bottom: 20px;
    align-items: center;
    gap: 10px;
  }
</style>

<div class="auth-wrapper">
  <div class="glass-panel auth-card">
    <div style="width: 48px; height: 48px; background: var(--primary-gradient); border-radius: 14px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.4rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
      ✦
    </div>

    <h1 class="auth-title">Join Infinity Blog</h1>
    <p class="auth-subtitle">Create your account to read, write, and share perspectives on Infinity Blog.</p>

    <?php
    $pendingReg = $_SESSION['pending_registration'] ?? null;
    $tokenParam = $_GET['token'] ?? ($pendingReg['challenge_token'] ?? '');
    $isVerifyStep = (isset($_GET['step']) && $_GET['step'] === 'verify') || (!empty($_GET['token']) && !empty($tokenParam));
    $initialMaskedEmail = $pendingReg['masked_email'] ?? '';

    $regErrors = $_SESSION['register_errors'] ?? null;
    $regOldInput = $_SESSION['register_old_input'] ?? null;
    unset($_SESSION['register_errors'], $_SESSION['register_old_input']);

    $initialError = '';
    if (!empty($_GET['error'])) {
        $initialError = urldecode((string)$_GET['error']);
    } elseif (!empty($regErrors['message'])) {
        $initialError = (string)$regErrors['message'];
    }

    $fieldErrors = $regErrors['fields'] ?? [];
    $oldDisplayName = $regOldInput['display_name'] ?? ($_POST['display_name'] ?? '');
    $oldEmail = $regOldInput['email'] ?? ($_POST['email'] ?? '');
    $oldPhone = $regOldInput['phone'] ?? ($_POST['phone'] ?? '');
    $oldBio = $regOldInput['bio'] ?? ($_POST['bio'] ?? '');
    $oldLocation = $regOldInput['location'] ?? ($_POST['location'] ?? '');
    $oldInterests = $regOldInput['interests'] ?? ($_POST['interests'] ?? '');
    $oldWebsite = $regOldInput['website'] ?? ($_POST['website'] ?? '');
    ?>

    <div id="register-error-alert" class="alert-banner alert-error" role="alert" aria-live="polite" style="<?= !empty($initialError) ? 'display: flex;' : 'display: none;' ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
      <span id="register-error-text"><?= htmlspecialchars((string)$initialError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <!-- Step 1: Initial Registration Form -->
    <form id="register-form" method="POST" action="/register" style="<?= $isVerifyStep ? 'display: none;' : '' ?>" novalidate>
      <input type="hidden" name="csrf_token" id="reg-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-group" id="group-display_name">
        <label for="display_name" class="form-label">Full Name</label>
        <div class="input-with-icon <?= !empty($fieldErrors['display_name']) ? 'has-error' : '' ?>">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <input type="text" id="display_name" name="display_name" class="form-input" placeholder="e.g. Cristine Palmer" value="<?= htmlspecialchars((string)$oldDisplayName, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name" autofocus aria-describedby="field-error-display_name" <?= !empty($fieldErrors['display_name']) ? 'aria-invalid="true"' : '' ?>>
        </div>
        <div class="field-error-msg" id="field-error-display_name" role="alert" style="<?= !empty($fieldErrors['display_name']) ? 'display: block;' : 'display: none;' ?>">
          <?= htmlspecialchars($fieldErrors['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>

      <div class="form-group" id="group-email">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-with-icon <?= !empty($fieldErrors['email']) ? 'has-error' : '' ?>">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
          <input type="email" id="email" name="email" class="form-input" placeholder="e.g. cristine.palmer@example.com" value="<?= htmlspecialchars((string)$oldEmail, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" aria-describedby="field-error-email" <?= !empty($fieldErrors['email']) ? 'aria-invalid="true"' : '' ?>>
        </div>
        <div class="field-error-msg" id="field-error-email" role="alert" style="<?= !empty($fieldErrors['email']) ? 'display: block;' : 'display: none;' ?>">
          <?= htmlspecialchars($fieldErrors['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>

      <div class="form-group" id="group-phone">
        <label for="phone" class="form-label">Mobile Phone Number <span style="color: var(--primary-color); font-size: 0.8rem; font-weight: 500;">(Required for Verification)</span></label>
        <div class="input-with-icon <?= !empty($fieldErrors['phone']) ? 'has-error' : '' ?>">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <input type="tel" id="phone" name="phone" class="form-input" placeholder="+91 98765 43210 or 9876543210" value="<?= htmlspecialchars((string)$oldPhone, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="tel" aria-describedby="field-error-phone phone-help" <?= !empty($fieldErrors['phone']) ? 'aria-invalid="true"' : '' ?>>
        </div>
        <div id="phone-help" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
          Enter 10-digit Indian number or international number with country code.
        </div>
        <div class="field-error-msg" id="field-error-phone" role="alert" style="<?= !empty($fieldErrors['phone']) ? 'display: block;' : 'display: none;' ?>">
          <?= htmlspecialchars($fieldErrors['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>

      <div class="form-group" id="group-password">
        <label for="password" class="form-label">Password</label>
        <div class="input-with-icon <?= !empty($fieldErrors['password']) ? 'has-error' : '' ?>">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" id="password" name="password" class="form-input" placeholder="At least 8 characters" required autocomplete="new-password" aria-describedby="field-error-password" <?= !empty($fieldErrors['password']) ? 'aria-invalid="true"' : '' ?>>
          <button type="button" class="input-toggle-btn" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
        <div class="field-error-msg" id="field-error-password" role="alert" style="<?= !empty($fieldErrors['password']) ? 'display: block;' : 'display: none;' ?>">
          <?= htmlspecialchars($fieldErrors['password'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>

      <div class="form-group" id="group-confirm_password">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <div class="input-with-icon <?= !empty($fieldErrors['confirm_password']) ? 'has-error' : '' ?>">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password" required autocomplete="new-password" aria-describedby="field-error-confirm_password" <?= !empty($fieldErrors['confirm_password']) ? 'aria-invalid="true"' : '' ?>>
          <button type="button" class="input-toggle-btn" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
        <div class="field-error-msg" id="field-error-confirm_password" role="alert" style="<?= !empty($fieldErrors['confirm_password']) ? 'display: block;' : 'display: none;' ?>">
          <?= htmlspecialchars($fieldErrors['confirm_password'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>

      <details style="margin-bottom: 20px; padding: 12px 16px; border-radius: 12px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.7);">
        <summary style="font-weight: 600; font-size: 0.88rem; color: var(--primary-color); cursor: pointer; user-select: none;">
          Optional: Add Bio & Location (or do this later)
        </summary>
        <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 12px;">
          <div>
            <label for="bio" class="form-label" style="font-size: 0.82rem;">Bio</label>
            <textarea id="bio" name="bio" rows="2" class="form-input" placeholder="Brief introduction..." maxlength="1000"><?= htmlspecialchars((string)$oldBio, ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
              <label for="location" class="form-label" style="font-size: 0.82rem;">Location</label>
              <input type="text" id="location" name="location" class="form-input" placeholder="e.g. London, UK" maxlength="120" value="<?= htmlspecialchars((string)$oldLocation, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
              <label for="interests" class="form-label" style="font-size: 0.82rem;">Interests</label>
              <input type="text" id="interests" name="interests" class="form-input" placeholder="e.g. AI, Web Dev" maxlength="255" value="<?= htmlspecialchars((string)$oldInterests, ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>
          <div>
            <label for="website" class="form-label" style="font-size: 0.82rem;">Website</label>
            <input type="url" id="website" name="website" class="form-input" placeholder="https://..." maxlength="255" value="<?= htmlspecialchars((string)$oldWebsite, ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
      </details>

      <div style="margin-bottom: 20px; font-size: 0.82rem; color: var(--text-muted); text-align: center;">
        Password must be at least 8 characters long. A verification code will be sent to your email.
      </div>

      <button type="submit" id="register-submit-btn" class="btn btn-primary btn-full">
        Continue to Verification
      </button>

      <p style="margin-top: 22px; font-size: 0.88rem; color: var(--text-muted); text-align: center;">
        Already have an account? <a href="/login" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Sign In</a>
      </p>

      <p style="margin-top: 10px; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
        <a href="/" style="color: var(--text-muted); text-decoration: none;">← Return to Home</a>
      </p>
    </form>

    <!-- Step 2: Registration Email Verification Form -->
    <form id="register-challenge-form" method="POST" action="/auth/register/verify" style="<?= $isVerifyStep ? 'display: block;' : 'display: none;' ?>" novalidate>
      <input type="hidden" name="csrf_token" id="reg-challenge-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="reg_challenge_token" name="challenge_token" value="<?= htmlspecialchars((string)$tokenParam, ENT_QUOTES, 'UTF-8') ?>">

      <div style="text-align: center; margin-bottom: 20px;">
        <div style="font-size: 2.2rem; margin-bottom: 8px;">✉️</div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
          Verify Your Email Address
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.45;">
          We sent a 6-digit verification code to:
          <strong id="reg-challenge-masked-email" style="display: block; color: var(--primary-color); margin-top: 4px; font-family: monospace; font-size: 0.95rem;"><?= htmlspecialchars((string)$initialMaskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
      </div>

      <div id="reg-challenge-delivery-notice" style="display: none; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; margin-bottom: 18px; color: var(--text-body);">
        <span id="reg-challenge-delivery-text"></span>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label for="reg_challenge_code" class="form-label" style="text-align: center; display: block; font-weight: 600;">
          Email Verification Code
        </label>
        <input type="text" id="reg_challenge_code" name="code" class="form-input" style="font-size: 1.75rem; letter-spacing: 0.35em; text-align: center; font-weight: 700; font-family: monospace;" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required aria-describedby="field-error-code">
        <div class="field-error-msg" id="field-error-code" role="alert" style="text-align: center;"></div>
      </div>

      <button type="submit" id="reg-challenge-submit-btn" class="btn btn-primary btn-full">
        Verify Email & Complete Registration
      </button>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; font-size: 0.85rem;">
        <button type="button" id="reg-challenge-back-btn" style="background: none; border: none; color: var(--text-muted); cursor: pointer; text-decoration: underline; padding: 0;">
          ← Change Details
        </button>
        <button type="button" id="reg-challenge-resend-btn" style="background: none; border: none; color: var(--primary-color); font-weight: 600; cursor: pointer; padding: 0;">
          Resend Code
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  function initRegister() {
    const form = document.getElementById('register-form');
    const challengeForm = document.getElementById('register-challenge-form');
    const errorBox = document.getElementById('register-error-alert');
    const errorText = document.getElementById('register-error-text');
    const submitBtn = document.getElementById('register-submit-btn');
    const challengeSubmitBtn = document.getElementById('reg-challenge-submit-btn');
    const challengeTokenInput = document.getElementById('reg_challenge_token');
    const challengeCodeInput = document.getElementById('reg_challenge_code');
    const challengeMaskedEmail = document.getElementById('reg-challenge-masked-email');
    const challengeDeliveryNotice = document.getElementById('reg-challenge-delivery-notice');
    const challengeDeliveryText = document.getElementById('reg-challenge-delivery-text');
    const challengeBackBtn = document.getElementById('reg-challenge-back-btn');
    const challengeResendBtn = document.getElementById('reg-challenge-resend-btn');

    if (!form || !submitBtn) return;

    let isSubmitting = false;

    async function obtainCsrf() {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
      const input = document.getElementById('reg-csrf-token') || document.querySelector('input[name="csrf_token"]');
      if (input && input.value) return input.value;
      if (window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        const t = await window.BlogCMS.ensureCsrfToken();
        if (t) return t;
      }
      try {
        const res = await fetch('/auth/csrf', { headers: { 'Accept': 'application/json' } });
        if (res.ok) {
          const d = await res.json();
          return d.data?.csrf_token || '';
        }
      } catch (e) {}
      return '';
    }

    function syncCsrfToken(newToken) {
      if (!newToken) return;
      const regCsrf = document.getElementById('reg-csrf-token');
      if (regCsrf) regCsrf.value = newToken;
      const chCsrf = document.getElementById('reg-challenge-csrf-token');
      if (chCsrf) chCsrf.value = newToken;
      const metaCsrf = document.querySelector('meta[name="csrf-token"]');
      if (metaCsrf) metaCsrf.setAttribute('content', newToken);
    }

    function setFieldError(fieldName, message) {
      const errElem = document.getElementById('field-error-' + fieldName);
      if (errElem) {
        errElem.textContent = message;
        errElem.style.display = 'block';
      }
      const input = document.getElementById(fieldName);
      if (input) {
        input.setAttribute('aria-invalid', 'true');
        const wrapper = input.closest('.input-with-icon');
        if (wrapper) wrapper.classList.add('has-error');
      }
    }

    function clearFieldError(fieldName) {
      const errElem = document.getElementById('field-error-' + fieldName);
      if (errElem) {
        errElem.textContent = '';
        errElem.style.display = 'none';
      }
      const input = document.getElementById(fieldName);
      if (input) {
        input.removeAttribute('aria-invalid');
        const wrapper = input.closest('.input-with-icon');
        if (wrapper) wrapper.classList.remove('has-error');
      }
    }

    function clearAllErrors() {
      ['display_name', 'email', 'phone', 'password', 'confirm_password', 'code'].forEach(clearFieldError);
      if (errorBox) {
        errorBox.style.display = 'none';
        if (errorText) errorText.textContent = '';
      }
    }

    function showGeneralError(msg) {
      if (errorBox && errorText) {
        errorText.textContent = msg;
        errorBox.style.display = 'flex';
        try {
          errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {}
      }
    }

    function restoreSubmitBtn() {
      submitBtn.disabled = false;
      submitBtn.removeAttribute('aria-busy');
      submitBtn.textContent = 'Continue to Verification';
      isSubmitting = false;
    }

    // Attach dynamic input event listeners to clear field errors as user types
    ['display_name', 'email', 'phone', 'password', 'confirm_password'].forEach(fieldName => {
      const input = document.getElementById(fieldName);
      if (!input) return;
      input.addEventListener('input', () => {
        clearFieldError(fieldName);
        // Check if other field errors remain visible
        const remainingErrors = document.querySelectorAll('.field-error-msg[style*="display: block"]');
        if (remainingErrors.length === 0 && errorBox) {
          errorBox.style.display = 'none';
        }
      });
    });

    if (challengeCodeInput) {
      challengeCodeInput.addEventListener('input', () => {
        clearFieldError('code');
        if (errorBox) errorBox.style.display = 'none';
      });
    }

    async function handleSubmit(e) {
      if (e) e.preventDefault();
      clearAllErrors();

      const displayNameInput = document.getElementById('display_name');
      const emailInput = document.getElementById('email');
      const phoneInput = document.getElementById('phone');
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('confirm_password');

      const displayName = displayNameInput?.value.trim() || '';
      const email = emailInput?.value.trim() || '';
      const phone = phoneInput?.value.trim() || '';
      const password = passwordInput?.value || '';
      const confirmPassword = confirmPasswordInput?.value || '';
      const bio = document.getElementById('bio')?.value.trim() || '';
      const location = document.getElementById('location')?.value.trim() || '';
      const interests = document.getElementById('interests')?.value.trim() || '';
      const website = document.getElementById('website')?.value.trim() || '';

      const errors = {};

      if (!displayName) {
        errors.display_name = 'Please enter your full name.';
      } else if (displayName.length > 190) {
        errors.display_name = 'Name cannot exceed 190 characters.';
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email) {
        errors.email = 'Please enter your email address.';
      } else if (!emailRegex.test(email)) {
        errors.email = 'Please enter a valid email address (e.g. name@example.com).';
      }

      const digitsOnly = phone.replace(/\D/g, '');
      if (!phone) {
        errors.phone = 'Please enter your mobile phone number for verification.';
      } else if (digitsOnly.length < 7 || digitsOnly.length > 15) {
        errors.phone = 'Please enter a valid phone number (e.g. +91 98765 43210 or 10-digit number).';
      }

      if (!password) {
        errors.password = 'Please enter a password.';
      } else if (password.length < 8) {
        errors.password = 'Password must be at least 8 characters long.';
      }

      if (!confirmPassword) {
        errors.confirm_password = 'Please confirm your password.';
      } else if (password !== confirmPassword) {
        errors.confirm_password = 'Passwords do not match.';
      }

      if (Object.keys(errors).length > 0) {
        let firstInvalidElem = null;
        for (const [fName, fMsg] of Object.entries(errors)) {
          setFieldError(fName, fMsg);
          if (!firstInvalidElem) {
            firstInvalidElem = document.getElementById(fName);
          }
        }
        showGeneralError('Please correct the highlighted fields below.');
        if (firstInvalidElem) {
          try {
            firstInvalidElem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidElem.focus();
          } catch (e) {}
        }
        return;
      }

      if (isSubmitting) return;
      isSubmitting = true;

      submitBtn.disabled = true;
      submitBtn.setAttribute('aria-busy', 'true');
      submitBtn.textContent = 'Sending verification code...';

      const payload = {
        display_name: displayName,
        email: email,
        phone: phone,
        password: password,
        confirm_password: confirmPassword
      };
      if (bio) payload.bio = bio;
      if (location) payload.location = location;
      if (interests) payload.interests = interests;
      if (website) payload.website = website;

      try {
        const csrf = await obtainCsrf();
        if (!csrf) {
          restoreSubmitBtn();
          showGeneralError('Unable to establish a secure session. Please refresh the page and try again.');
          return;
        }
        payload.csrf_token = csrf;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 20000);

        let res;
        try {
          res = await fetch('/auth/register', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify(payload),
            signal: controller.signal
          });
        } finally {
          clearTimeout(timeoutId);
        }

        let data = null;
        try {
          data = await res.json();
        } catch (jsonErr) {
          data = null;
        }

        if (res.ok && data && data.data) {
          syncCsrfToken(data.data.csrf_token);

          if (data.data.challenge_required) {
            if (data.data.delivery_status === 'FAILED') {
              restoreSubmitBtn();
              showGeneralError(data.data.message || 'We could not send the verification code to your email. Please check your email address or try again in a few moments.');
              return;
            }

            challengeTokenInput.value = data.data.challenge_token || '';
            if (challengeMaskedEmail) {
              challengeMaskedEmail.textContent = data.data.masked_email || email;
            }

            if (data.data.delivery_status === 'BLOCKED') {
              challengeDeliveryNotice.style.display = 'block';
              if (data.data.dev_code) {
                challengeDeliveryText.innerHTML = '⚡ Local Dev Mode: SMTP is unconfigured. Your verification code is: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
                challengeCodeInput.value = data.data.dev_code;
              } else {
                challengeDeliveryText.textContent = 'Notice: Email delivery is simulated or blocked in this environment.';
              }
            } else {
              challengeDeliveryNotice.style.display = 'none';
            }

            form.style.display = 'none';
            challengeForm.style.display = 'block';
            if (challengeCodeInput) {
              challengeCodeInput.focus();
            }
          } else {
            // Direct registration complete (when challenges are disabled)
            submitBtn.textContent = 'Account created! Redirecting to sign in...';
            window.location.href = '/login?registered=1';
          }
        } else {
          // Server returned non-2xx status
          restoreSubmitBtn();

          // Clear sensitive password fields on failed attempt; retain name, email, phone, bio, etc.
          if (passwordInput) passwordInput.value = '';
          if (confirmPasswordInput) confirmPasswordInput.value = '';

          if (data && data.error && data.error.fields) {
            let firstServerInvalid = null;
            for (const [fName, fMsg] of Object.entries(data.error.fields)) {
              setFieldError(fName, fMsg);
              if (!firstServerInvalid) {
                firstServerInvalid = document.getElementById(fName);
              }
            }
            showGeneralError(data.error.message || 'Please correct the highlighted errors and try again.');
            if (firstServerInvalid) {
              try {
                firstServerInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstServerInvalid.focus();
              } catch (e) {}
            }
          } else if (data && data.error && data.error.code === 'CSRF_INVALID') {
            showGeneralError(data.error.message || 'Your session or security token has expired. Please refresh the page and try again.');
          } else {
            const serverMsg = (data && data.error && data.error.message)
              ? data.error.message
              : 'Registration could not be started. Please check your inputs and try again.';
            showGeneralError(serverMsg);
          }
        }
      } catch (err) {
        restoreSubmitBtn();
        if (passwordInput) passwordInput.value = '';
        if (confirmPasswordInput) confirmPasswordInput.value = '';
        showGeneralError('Network connection error. Unable to reach the server. Please check your internet connection and try again.');
      }
    }

    // Step 2 Challenge Submission Handler
    challengeForm.addEventListener('submit', async (e) => {
      if (e) e.preventDefault();
      clearFieldError('code');
      if (errorBox) errorBox.style.display = 'none';

      const challenge_token = challengeTokenInput.value.trim();
      const code = challengeCodeInput.value.trim();

      if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
        setFieldError('code', 'Please enter the complete 6-digit verification code.');
        challengeCodeInput.focus();
        return;
      }

      challengeSubmitBtn.disabled = true;
      challengeSubmitBtn.setAttribute('aria-busy', 'true');
      challengeSubmitBtn.textContent = 'Verifying email...';

      let csrf = '';
      try {
        csrf = await obtainCsrf();
        const res = await fetch('/auth/register/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ challenge_token, code, csrf_token: csrf })
        });

        let data = null;
        try {
          data = await res.json();
        } catch (jsonErr) {
          data = null;
        }

        if (res.ok && data) {
          challengeSubmitBtn.textContent = 'Verified! Redirecting to sign in...';
          window.location.href = '/login?registered=1';
        } else {
          const failMsg = (data && data.error && data.error.message)
            ? data.error.message
            : 'The verification code is incorrect or has expired.';
          setFieldError('code', failMsg);
          showGeneralError(failMsg);
          challengeSubmitBtn.disabled = false;
          challengeSubmitBtn.removeAttribute('aria-busy');
          challengeSubmitBtn.textContent = 'Verify Email & Complete Registration';
          challengeCodeInput.select();
        }
      } catch (err) {
        challengeSubmitBtn.disabled = false;
        challengeSubmitBtn.removeAttribute('aria-busy');
        challengeSubmitBtn.textContent = 'Verify Email & Complete Registration';
        showGeneralError('Network connection error. Unable to verify code. Please try again.');
      }
    });

    // Step 2 Resend Handler
    challengeResendBtn.addEventListener('click', async () => {
      const challenge_token = challengeTokenInput.value.trim();
      if (!challenge_token) return;

      challengeResendBtn.disabled = true;
      challengeResendBtn.textContent = 'Sending...';

      try {
        const csrf = await obtainCsrf();
        const res = await fetch('/auth/register/resend', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ challenge_token, csrf_token: csrf })
        });

        let data = null;
        try {
          data = await res.json();
        } catch (jsonErr) {
          data = null;
        }

        if (res.ok && data) {
          syncCsrfToken(data.data?.csrf_token);
          if (data.data?.dev_code) {
            challengeDeliveryNotice.style.display = 'block';
            challengeDeliveryText.innerHTML = '⚡ Local Dev Mode: A new verification code was generated: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
            challengeCodeInput.value = data.data.dev_code;
          }
          challengeResendBtn.textContent = 'Code Sent!';
          setTimeout(() => {
            challengeResendBtn.disabled = false;
            challengeResendBtn.textContent = 'Resend Code';
          }, 30000);
        } else {
          const failMsg = (data && data.error && data.error.message)
            ? data.error.message
            : 'Failed to resend code. Please try again.';
          showGeneralError(failMsg);
          challengeResendBtn.disabled = false;
          challengeResendBtn.textContent = 'Resend Code';
        }
      } catch (err) {
        showGeneralError('Network error resending code. Please check your connection.');
        challengeResendBtn.disabled = false;
        challengeResendBtn.textContent = 'Resend Code';
      }
    });

    // Step 2 Back Button
    challengeBackBtn.addEventListener('click', () => {
      challengeForm.style.display = 'none';
      form.style.display = 'block';
      restoreSubmitBtn();
      clearAllErrors();
    });

    form.addEventListener('submit', handleSubmit);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRegister);
  } else {
    initRegister();
  }
})();
</script>
