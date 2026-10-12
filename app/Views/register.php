<!-- Screen: User Registration Page -->
<div class="auth-wrapper">
  <div class="glass-panel auth-card">
    <div style="width: 48px; height: 48px; background: var(--primary-gradient); border-radius: 14px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.4rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
      ✦
    </div>

    <h1 class="auth-title">Join Infinity Blog</h1>
    <p class="auth-subtitle">Create your reader account to explore perspectives and engage with articles.</p>

    <div id="register-error-alert" class="alert-banner alert-error" style="display: none;"></div>

    <form id="register-form" method="POST" action="/auth/register" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-group">
        <label for="display_name" class="form-label">Full Name</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <input type="text" id="display_name" name="display_name" class="form-input" placeholder="e.g. Jane Doe" required autocomplete="name" autofocus>
        </div>
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-with-icon">
          <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
          <input type="email" id="email" name="email" class="form-input" placeholder="e.g. jane@example.com" required autocomplete="email">
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

      <div style="margin-bottom: 20px; font-size: 0.82rem; color: var(--text-muted); text-align: center;">
        Password must be at least 8 characters long.
      </div>

      <button type="submit" id="register-submit-btn" class="btn btn-primary btn-full">
        Create Account
      </button>

      <p style="margin-top: 22px; font-size: 0.88rem; color: var(--text-muted); text-align: center;">
        Already have an account? <a href="/login" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Sign In</a>
      </p>

      <p style="margin-top: 10px; font-size: 0.85rem; color: var(--text-muted); text-align: center;">
        <a href="/" style="color: var(--text-muted); text-decoration: none;">← Return to Home</a>
      </p>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('register-form');
  const errorBox = document.getElementById('register-error-alert');
  const submitBtn = document.getElementById('register-submit-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';

    const displayName = document.getElementById('display_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (!displayName) {
      errorBox.textContent = 'Please enter your full name.';
      errorBox.style.display = 'flex';
      return;
    }

    if (!email || !email.includes('@')) {
      errorBox.textContent = 'Please enter a valid email address.';
      errorBox.style.display = 'flex';
      return;
    }

    if (password.length < 8) {
      errorBox.textContent = 'Password must be at least 8 characters long.';
      errorBox.style.display = 'flex';
      return;
    }

    if (password !== confirmPassword) {
      errorBox.textContent = 'Passwords do not match.';
      errorBox.style.display = 'flex';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';

    try {
      const csrf = await window.BlogCMS.ensureCsrfToken();
      const res = await fetch('/auth/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({
          display_name: displayName,
          email: email,
          password: password,
          confirm_password: confirmPassword
        })
      });

      const data = await res.json();
      if (res.ok) {
        submitBtn.textContent = 'Account created! Redirecting to sign in...';
        window.location.href = '/login?registered=1';
      } else {
        const errorMsg = data.error?.fields
          ? Object.values(data.error.fields).join(' ')
          : (data.error?.message || 'Registration failed. Please check your inputs.');
        errorBox.textContent = errorMsg;
        errorBox.style.display = 'flex';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
      }
    } catch (err) {
      errorBox.textContent = 'Connection error. Please try again.';
      errorBox.style.display = 'flex';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Create Account';
    }
  });
});
</script>
