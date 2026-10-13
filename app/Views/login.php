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
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const errorBox = document.getElementById('login-error-alert');
  const submitBtn = document.getElementById('login-submit-btn');

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
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      const res = await fetch('/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        },
        body: JSON.stringify({ identifier, password })
      });

      const data = await res.json();
      if (res.ok) {
        // Successful login
        submitBtn.textContent = 'Signed in! Redirecting...';
        const urlParams = new URLSearchParams(window.location.search);
        const redirectParam = urlParams.get('redirect');
        const targetUrl = (redirectParam && redirectParam.startsWith('/') && !redirectParam.startsWith('//'))
          ? redirectParam
          : '/dashboard';
        window.location.href = targetUrl;
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
});
</script>
