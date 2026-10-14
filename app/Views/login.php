<!-- Screen 2: Login Page -->
<div class="auth-wrapper">
  <div class="glass-panel auth-card">
    <div style="width: 48px; height: 48px; background: var(--primary-gradient); border-radius: 14px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.4rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
      ✦
    </div>

    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Sign in to your account to read, write, or manage your blog.</p>

    <?php if (!empty($_GET['registered'])): ?>
      <div class="alert-banner alert-success" style="display: flex; margin-bottom: 20px;">
        <span>Account created successfully! Please sign in with your credentials.</span>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
      <div class="alert-banner alert-error" style="display: flex; margin-bottom: 20px;">
        <span><?= htmlspecialchars($_GET['error'] === 'csrf' ? 'Security check or session expired. Please try again.' : ($_GET['error'] === 'rate_limited' ? 'Too many failed login attempts. Please try again later.' : 'The login identifier or password is invalid.'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endif; ?>

    <div id="login-error-alert" class="alert-banner alert-error" style="display: none;"></div>

    <form id="login-form" method="POST" action="/auth/login" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-group">
        <label for="identifier" class="form-label">Email, Username or Full Name</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <input type="text" id="identifier" name="identifier" class="form-input" placeholder="e.g. cristine.palmer@example.com or Cristine Palmer" required autocomplete="username" autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" class="input-toggle-btn" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <div style="margin-bottom: 20px; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
        Sign in with your email or username and password.
      </div>

      <button type="submit" id="login-submit-btn" class="btn btn-primary btn-full">
        Sign In
      </button>

      <p style="margin-top: 22px; font-size: 0.88rem; color: var(--text-muted); text-align: center;">
        Don't have an account? <a href="/register" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Create Account</a>
      </p>

      <p style="margin-top: 10px; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
        <a href="/" style="color: var(--text-muted); text-decoration: none;">← Return to Home</a>
      </p>
    </form>

    <!-- Step 2: Verification Challenge Form -->
    <form id="challenge-form" method="POST" action="/auth/challenge/verify" style="display: none;" novalidate>
      <input type="hidden" name="csrf_token" id="challenge-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="challenge_token" name="challenge_token" value="">

      <div style="text-align: center; margin-bottom: 20px;">
        <div style="font-size: 2.2rem; margin-bottom: 8px;">🔐</div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
          Two-Step Verification
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.45;">
          Enter the 6-digit code sent to your registered email:
          <strong id="challenge-masked-email" style="display: block; color: var(--primary-color); margin-top: 4px; font-family: monospace; font-size: 0.95rem;"></strong>
        </p>
      </div>

      <div id="challenge-delivery-notice" style="display: none; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; margin-bottom: 18px; color: var(--text-body);">
        <span id="challenge-delivery-text"></span>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label for="challenge_code" class="form-label" style="text-align: center; display: block; font-weight: 600;">
          Verification Code
        </label>
        <input type="text" id="challenge_code" name="code" class="form-input" style="font-size: 1.75rem; letter-spacing: 0.35em; text-align: center; font-weight: 700; font-family: monospace;" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required>
      </div>

      <button type="submit" id="challenge-submit-btn" class="btn btn-primary btn-full">
        Verify & Sign In
      </button>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; font-size: 0.85rem;">
        <button type="button" id="challenge-back-btn" style="background: none; border: none; color: var(--text-muted); cursor: pointer; text-decoration: underline; padding: 0;">
          ← Back to Login
        </button>
        <button type="button" id="challenge-resend-btn" style="background: none; border: none; color: var(--primary-color); font-weight: 600; cursor: pointer; padding: 0;">
          Resend Code
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const challengeForm = document.getElementById('challenge-form');
  const errorBox = document.getElementById('login-error-alert');
  const submitBtn = document.getElementById('login-submit-btn');
  const challengeSubmitBtn = document.getElementById('challenge-submit-btn');
  const challengeTokenInput = document.getElementById('challenge_token');
  const challengeCodeInput = document.getElementById('challenge_code');
  const challengeMaskedEmail = document.getElementById('challenge-masked-email');
  const challengeDeliveryNotice = document.getElementById('challenge-delivery-notice');
  const challengeDeliveryText = document.getElementById('challenge-delivery-text');
  const challengeBackBtn = document.getElementById('challenge-back-btn');
  const challengeResendBtn = document.getElementById('challenge-resend-btn');

  const getCsrfToken = async () => {
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || document.querySelector('input[name="csrf_token"]')?.value;
    if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
      csrf = await window.BlogCMS.ensureCsrfToken();
    }
    return csrf || '';
  };

  const getTargetUrl = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const redirectParam = urlParams.get('redirect');
    return (redirectParam && redirectParam.startsWith('/') && !redirectParam.startsWith('//'))
      ? redirectParam
      : '/dashboard';
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';

    const identifier = document.getElementById('identifier').value.trim();
    const password = document.getElementById('password').value;

    if (!identifier || !password) {
      errorBox.textContent = 'Please enter both your login identifier and password.';
      errorBox.style.display = 'flex';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Verifying credentials...';

    try {
      const csrf = await getCsrfToken();
      const res = await fetch('/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ identifier, password })
      });

      const data = await res.json();
      if (res.ok) {
        if (data.data?.challenge_required) {
          // Two-step challenge triggered
          challengeTokenInput.value = data.data.challenge_token;
          challengeMaskedEmail.textContent = data.data.masked_email;
          if (data.data.csrf_token) {
            const chCsrf = document.getElementById('challenge-csrf-token');
            if (chCsrf) chCsrf.value = data.data.csrf_token;
            const metaCsrf = document.querySelector('meta[name="csrf-token"]');
            if (metaCsrf) metaCsrf.setAttribute('content', data.data.csrf_token);
          }
          if (data.data.delivery_status === 'BLOCKED') {
            challengeDeliveryNotice.style.display = 'block';
            if (data.data.dev_code) {
              challengeDeliveryText.innerHTML = '⚡ Local Dev Mode: Email gateway is unconfigured. Your verification code is: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
              challengeCodeInput.value = data.data.dev_code;
            } else {
              challengeDeliveryText.textContent = 'Notice: Email delivery is simulated in this environment.';
            }
          } else {
            challengeDeliveryNotice.style.display = 'none';
          }

          form.style.display = 'none';
          challengeForm.style.display = 'block';
          challengeCodeInput.focus();
        } else {
          // Direct login (null email / recovery path)
          submitBtn.textContent = 'Signed in! Redirecting...';
          window.location.href = getTargetUrl();
        }
      } else {
        errorBox.textContent = data.error?.message || 'The login identifier or password is invalid.';
        errorBox.style.display = 'flex';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In';
      }
    } catch (err) {
      errorBox.textContent = 'Connection error. Please try again.';
      errorBox.style.display = 'flex';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Sign In';
    }
  });

  // Handle Challenge Verification
  challengeForm.addEventListener('submit', async (e) => {
    if (e) e.preventDefault();
    errorBox.style.display = 'none';

    const challenge_token = challengeTokenInput.value.trim();
    const code = challengeCodeInput.value.trim();

    if (!code || code.length !== 6) {
      errorBox.textContent = 'Please enter the complete 6-digit verification code.';
      errorBox.style.display = 'flex';
      return;
    }

    challengeSubmitBtn.disabled = true;
    challengeSubmitBtn.textContent = 'Verifying code...';

    let csrf = '';
    try {
      csrf = await getCsrfToken();
      const res = await fetch('/auth/challenge/verify', {
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
        challengeSubmitBtn.textContent = 'Verified! Redirecting...';
        window.location.href = getTargetUrl();
      } else {
        errorBox.textContent = data.error?.message || 'The verification code is incorrect or has expired.';
        errorBox.style.display = 'flex';
        challengeSubmitBtn.disabled = false;
        challengeSubmitBtn.textContent = 'Verify & Sign In';
        challengeCodeInput.select();
      }
    } catch (err) {
      // Fallback to standard form post
      const chCsrf = document.getElementById('challenge-csrf-token');
      if (chCsrf && csrf) chCsrf.value = csrf;
      challengeForm.submit();
    }
  });

  // Handle Challenge Resend
  challengeResendBtn.addEventListener('click', async () => {
    const challenge_token = challengeTokenInput.value.trim();
    if (!challenge_token) return;

    challengeResendBtn.disabled = true;
    challengeResendBtn.textContent = 'Sending...';

    try {
      const csrf = await getCsrfToken();
      const res = await fetch('/auth/challenge/resend', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ challenge_token })
      });

      const data = await res.json();
      if (res.ok && data.data?.challenge_token) {
        challengeTokenInput.value = data.data.challenge_token;
        if (data.data.dev_code) {
          challengeDeliveryText.innerHTML = '⚡ New code generated: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
          challengeCodeInput.value = data.data.dev_code;
        }
        let countdown = 60;
        challengeResendBtn.textContent = `Resend in ${countdown}s`;
        const interval = setInterval(() => {
          countdown--;
          if (countdown <= 0) {
            clearInterval(interval);
            challengeResendBtn.disabled = false;
            challengeResendBtn.textContent = 'Resend Code';
          } else {
            challengeResendBtn.textContent = `Resend in ${countdown}s`;
          }
        }, 1000);
      } else {
        challengeResendBtn.disabled = false;
        challengeResendBtn.textContent = 'Resend Code';
        errorBox.textContent = data.error?.message || 'Failed to resend code.';
        errorBox.style.display = 'flex';
      }
    } catch {
      challengeResendBtn.disabled = false;
      challengeResendBtn.textContent = 'Resend Code';
    }
  });

  // Back to step 1
  challengeBackBtn.addEventListener('click', () => {
    challengeForm.style.display = 'none';
    form.style.display = 'block';
    errorBox.style.display = 'none';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Sign In';
  });
});
</script>
