<?php
/**
 * Password Reset View — Infinity Blog
 */
$resetToken = $token ?? ($_GET['token'] ?? '');
$cleanBase = $basePath ?? '';
?>

<div class="auth-wrapper" style="display: flex; align-items: center; justify-content: center; min-height: 80vh; padding: 24px 16px;">
  <div class="glass-panel" style="max-width: 440px; width: 100%; padding: 36px 28px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); text-align: center;">

    <div class="auth-brand-logo-wrap" style="margin-bottom: 20px;">
      <a href="<?= htmlspecialchars($cleanBase . '/', ENT_QUOTES, 'UTF-8') ?>" aria-label="Infinity Blog Home">
        <img src="/assets/images/infinity-blog-logo-horizontal.png" alt="Infinity Blog" style="height: 32px; width: auto;" loading="eager">
      </a>
    </div>

    <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-heading); margin-bottom: 8px;">
      Reset Your Password
    </h1>
    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 24px;">
      Enter and confirm your new account password below.
    </p>

    <div id="reset-error-alert" class="alert-banner alert-error" style="display: none; margin-bottom: 18px; font-size: 0.85rem; text-align: left;">
      <span id="reset-error-text"></span>
    </div>

    <div id="reset-success-alert" class="alert-banner alert-success" style="display: none; margin-bottom: 18px; font-size: 0.85rem; text-align: left;">
      <span>Your password has been successfully updated! Redirecting to sign in...</span>
    </div>

    <?php if (empty($resetToken)): ?>
      <div id="missing-token-alert" class="alert-banner alert-error" style="display: flex; margin-bottom: 18px; font-size: 0.85rem; text-align: left;">
        <span>Invalid or missing reset token. Please request a new password reset link.</span>
      </div>
      <p style="margin-top: 20px;">
        <a href="<?= htmlspecialchars($cleanBase . '/login', ENT_QUOTES, 'UTF-8') ?>" class="btn auth-btn-primary" style="display: inline-block; padding: 10px 24px; text-decoration: none;">
          Return to Sign In
        </a>
      </p>
    <?php else: ?>
      <form id="password-reset-form" method="POST" action="<?= htmlspecialchars($cleanBase . '/auth/password/reset', ENT_QUOTES, 'UTF-8') ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" id="reset-token-input" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group" style="margin-bottom: 16px; text-align: left;">
          <label for="new_password" style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-heading); margin-bottom: 6px;">New Password</label>
          <div class="input-with-icon" style="position: relative;">
            <input type="password" id="new_password" name="password" class="form-input" placeholder="At least 8 characters" required autocomplete="new-password" style="width: 100%;">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 24px; text-align: left;">
          <label for="confirm_password" style="display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-heading); margin-bottom: 6px;">Confirm New Password</label>
          <div class="input-with-icon" style="position: relative;">
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Repeat new password" required autocomplete="new-password" style="width: 100%;">
          </div>
        </div>

        <button type="submit" id="reset-submit-btn" class="btn auth-btn-primary" style="width: 100%; padding: 12px; font-weight: 700; font-size: 0.95rem; border-radius: 12px; cursor: pointer;">
          UPDATE PASSWORD
        </button>

        <p style="margin-top: 20px; font-size: 0.85rem; color: var(--text-muted);">
          Remembered your password?
          <a href="<?= htmlspecialchars($cleanBase . '/login', ENT_QUOTES, 'UTF-8') ?>" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Sign In</a>
        </p>
      </form>
    <?php endif; ?>

  </div>
</div>

<script>
(() => {
  const form = document.getElementById('password-reset-form');
  const errorBox = document.getElementById('reset-error-alert');
  const errorText = document.getElementById('reset-error-text');
  const successBox = document.getElementById('reset-success-alert');
  const submitBtn = document.getElementById('reset-submit-btn');
  const basePath = window.APP_BASE_PATH || '';

  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';
    successBox.style.display = 'none';

    const token = document.getElementById('reset-token-input')?.value.trim();
    const password = document.getElementById('new_password')?.value || '';
    const confirmPassword = document.getElementById('confirm_password')?.value || '';

    if (!password || password.length < 8) {
      errorText.textContent = 'Password must be at least 8 characters long.';
      errorBox.style.display = 'flex';
      return;
    }
    if (password !== confirmPassword) {
      errorText.textContent = 'Passwords do not match.';
      errorBox.style.display = 'flex';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'UPDATING...';

    try {
      const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
      const res = await fetch(basePath + '/auth/password/reset', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ token, password, confirm_password: confirmPassword })
      });

      const contentType = res.headers.get('content-type') || '';
      let data = null;
      if (contentType.includes('application/json')) {
        try { data = await res.json(); } catch (e) { data = null; }
      }

      if (res.ok) {
        successBox.style.display = 'flex';
        submitBtn.textContent = 'UPDATED!';
        setTimeout(() => {
          window.location.href = basePath + '/login?reset=1';
        }, 1500);
      } else {
        errorText.textContent = data?.error?.message || 'Password reset failed. Please request a new link.';
        errorBox.style.display = 'flex';
        submitBtn.disabled = false;
        submitBtn.textContent = 'UPDATE PASSWORD';
      }
    } catch (err) {
      errorText.textContent = 'Connection error. Please try again.';
      errorBox.style.display = 'flex';
      submitBtn.disabled = false;
      submitBtn.textContent = 'UPDATE PASSWORD';
    }
  });
})();
</script>
