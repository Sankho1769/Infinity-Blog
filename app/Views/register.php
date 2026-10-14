<!-- Screen: User Registration Page -->
<div class="auth-wrapper">
  <div class="glass-panel auth-card">
    <div style="width: 48px; height: 48px; background: var(--primary-gradient); border-radius: 14px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.4rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
      ✦
    </div>

    <h1 class="auth-title">Join Infinity Blog</h1>
    <p class="auth-subtitle">Create your account to read, write, and share perspectives on Infinity Blog.</p>

    <?php if (!empty($_GET['error'])): ?>
      <div class="alert-banner alert-error" style="display: flex; margin-bottom: 20px;">
        <span><?= htmlspecialchars(urldecode((string)$_GET['error']), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endif; ?>

    <div id="register-error-alert" class="alert-banner alert-error" style="display: none;"></div>

    <!-- Step 1: Initial Registration Form -->
    <form id="register-form" method="POST" action="/register" novalidate>
      <input type="hidden" name="csrf_token" id="reg-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-group">
        <label for="display_name" class="form-label">Full Name</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <input type="text" id="display_name" name="display_name" class="form-input" placeholder="e.g. Cristine Palmer" required autocomplete="name" autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
          <input type="email" id="email" name="email" class="form-input" placeholder="e.g. cristine.palmer@example.com" required autocomplete="email">
        </div>
      </div>

      <div class="form-group">
        <label for="phone" class="form-label">Mobile Phone Number <span style="color: var(--primary-color); font-size: 0.8rem; font-weight: 500;">(Required for Verification)</span></label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <input type="tel" id="phone" name="phone" class="form-input" placeholder="+91 98765 43210 or 9876543210" required autocomplete="tel">
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
          Enter 10-digit Indian number or international number with country code.
        </div>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" id="password" name="password" class="form-input" placeholder="At least 8 characters" required autocomplete="new-password">
          <button type="button" class="input-toggle-btn" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password" required autocomplete="new-password">
          <button type="button" class="input-toggle-btn" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <details style="margin-bottom: 20px; padding: 12px 16px; border-radius: 12px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.7);">
        <summary style="font-weight: 600; font-size: 0.88rem; color: var(--primary-color); cursor: pointer; user-select: none;">
          Optional: Add Bio & Location (or do this later)
        </summary>
        <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 12px;">
          <div>
            <label for="bio" class="form-label" style="font-size: 0.82rem;">Bio</label>
            <textarea id="bio" name="bio" rows="2" class="form-input" placeholder="Brief introduction..." maxlength="1000"></textarea>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
              <label for="location" class="form-label" style="font-size: 0.82rem;">Location</label>
              <input type="text" id="location" name="location" class="form-input" placeholder="e.g. London, UK" maxlength="120">
            </div>
            <div>
              <label for="interests" class="form-label" style="font-size: 0.82rem;">Interests</label>
              <input type="text" id="interests" name="interests" class="form-input" placeholder="e.g. AI, Web Dev" maxlength="255">
            </div>
          </div>
          <div>
            <label for="website" class="form-label" style="font-size: 0.82rem;">Website</label>
            <input type="url" id="website" name="website" class="form-input" placeholder="https://..." maxlength="255">
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
    <form id="register-challenge-form" method="POST" action="/auth/register/verify" style="display: none;" novalidate>
      <input type="hidden" name="csrf_token" id="reg-challenge-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="reg_challenge_token" name="challenge_token" value="">

      <div style="text-align: center; margin-bottom: 20px;">
        <div style="font-size: 2.2rem; margin-bottom: 8px;">✉️</div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
          Verify Your Email Address
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.45;">
          We sent a 6-digit verification code to:
          <strong id="reg-challenge-masked-email" style="display: block; color: var(--primary-color); margin-top: 4px; font-family: monospace; font-size: 0.95rem;"></strong>
        </p>
      </div>

      <div id="reg-challenge-delivery-notice" style="display: none; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; margin-bottom: 18px; color: var(--text-body);">
        <span id="reg-challenge-delivery-text"></span>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label for="reg_challenge_code" class="form-label" style="text-align: center; display: block; font-weight: 600;">
          Email Verification Code
        </label>
        <input type="text" id="reg_challenge_code" name="code" class="form-input" style="font-size: 1.75rem; letter-spacing: 0.35em; text-align: center; font-weight: 700; font-family: monospace;" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required>
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

    async function handleSubmit(e) {
      if (e) e.preventDefault();
      if (errorBox) errorBox.style.display = 'none';

      const displayName = document.getElementById('display_name')?.value.trim() || '';
      const email = document.getElementById('email')?.value.trim() || '';
      const phone = document.getElementById('phone')?.value.trim() || '';
      const password = document.getElementById('password')?.value || '';
      const confirmPassword = document.getElementById('confirm_password')?.value || '';
      const bio = document.getElementById('bio')?.value.trim() || '';
      const location = document.getElementById('location')?.value.trim() || '';
      const interests = document.getElementById('interests')?.value.trim() || '';
      const website = document.getElementById('website')?.value.trim() || '';

      if (!displayName) {
        showError('Please enter your full name.');
        return;
      }
      if (!email || !email.includes('@')) {
        showError('Please enter a valid email address.');
        return;
      }
      if (!phone) {
        showError('Please enter your mobile phone number for verification.');
        return;
      }
      if (password.length < 8) {
        showError('Password must be at least 8 characters long.');
        return;
      }
      if (password !== confirmPassword) {
        showError('Passwords do not match.');
        return;
      }

      submitBtn.disabled = true;
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
        payload.csrf_token = csrf;

        const res = await fetch('/auth/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (res.ok) {
          syncCsrfToken(data.data?.csrf_token);

          if (data.data?.challenge_required) {
            challengeTokenInput.value = data.data.challenge_token;
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
            challengeCodeInput.focus();
          } else {
            // Direct registration complete
            submitBtn.textContent = 'Account created! Redirecting to sign in...';
            window.location.href = '/login?registered=1';
          }
        } else {
          const errorMsg = data.error?.fields
            ? Object.values(data.error.fields).join(' ')
            : (data.error?.message || 'Registration could not be started. Please check your inputs.');
          showError(errorMsg);
          submitBtn.disabled = false;
          submitBtn.textContent = 'Continue to Verification';
        }
      } catch (err) {
        // Fallback to standard form post
        const hiddenCsrf = document.getElementById('reg-csrf-token');
        if (hiddenCsrf && !hiddenCsrf.value) {
          hiddenCsrf.value = await obtainCsrf();
        }
        form.submit();
      }
    }

    // Step 2 Challenge Submission Handler
    challengeForm.addEventListener('submit', async (e) => {
      if (e) e.preventDefault();
      if (errorBox) errorBox.style.display = 'none';

      const challenge_token = challengeTokenInput.value.trim();
      const code = challengeCodeInput.value.trim();

      if (!code || code.length !== 6) {
        showError('Please enter the complete 6-digit verification code.');
        return;
      }

      challengeSubmitBtn.disabled = true;
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

        const data = await res.json();
        if (res.ok) {
          challengeSubmitBtn.textContent = 'Verified! Redirecting to sign in...';
          window.location.href = '/login?registered=1';
        } else {
          showError(data.error?.message || 'The verification code is incorrect or has expired.');
          challengeSubmitBtn.disabled = false;
          challengeSubmitBtn.textContent = 'Verify Email & Complete Registration';
          challengeCodeInput.select();
        }
      } catch (err) {
        const chCsrf = document.getElementById('reg-challenge-csrf-token');
        if (chCsrf && csrf) chCsrf.value = csrf;
        challengeForm.submit();
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

        const data = await res.json();
        if (res.ok) {
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
          showError(data.error?.message || 'Failed to resend code. Please try again.');
          challengeResendBtn.disabled = false;
          challengeResendBtn.textContent = 'Resend Code';
        }
      } catch (err) {
        showError('Network error resending code.');
        challengeResendBtn.disabled = false;
        challengeResendBtn.textContent = 'Resend Code';
      }
    });

    // Step 2 Back Button
    challengeBackBtn.addEventListener('click', () => {
      challengeForm.style.display = 'none';
      form.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Continue to Verification';
      if (errorBox) errorBox.style.display = 'none';
    });

    function showError(msg) {
      if (errorBox) {
        errorBox.textContent = msg;
        errorBox.style.display = 'flex';
      }
    }

    form.addEventListener('submit', handleSubmit);
    submitBtn.addEventListener('click', (e) => {
      if (form.checkValidity && !form.checkValidity()) {
        // Let browser validation fire
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRegister);
  } else {
    initRegister();
  }
})();
</script>
