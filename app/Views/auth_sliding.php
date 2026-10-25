<?php
/**
 * Unified Sliding Login & Registration Component
 * - Desktop (>768px): Glassmorphic sliding dual card with organic curved overlay
 * - Mobile (<=768px): Claymorphic tactile single card with segmented toggle switch
 */

$authMode = $initialAuthMode ?? 'signin';
$pendingReg = $_SESSION['pending_registration'] ?? null;
$tokenParam = $_GET['token'] ?? ($pendingReg['challenge_token'] ?? '');
$isVerifyStep = (isset($_GET['step']) && $_GET['step'] === 'verify') || (!empty($_GET['token']) && !empty($tokenParam));
if ($isVerifyStep) {
    $authMode = 'signup';
}
$initialMaskedEmail = $pendingReg['masked_email'] ?? '';
$effectiveRedirect = $redirectUrl ?? ($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));

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

$isSignUpActive = ($authMode === 'signup');
?>

<div class="auth-wrapper">
  <div class="auth-sliding-card glass-panel <?= $isSignUpActive ? 'right-panel-active mobile-signup-active' : '' ?>" id="auth-sliding-card">

    <!-- Mobile Segmented Toggle Control (<= 768px only) -->
    <div class="auth-mobile-toggle-bar" role="tablist" aria-label="Sign In or Register">
      <button type="button" class="auth-mobile-toggle-btn <?= !$isSignUpActive ? 'active' : '' ?>" id="mobile-toggle-signin" role="tab" aria-selected="<?= !$isSignUpActive ? 'true' : 'false' ?>" aria-controls="auth-signin-panel">
        Sign In
      </button>
      <button type="button" class="auth-mobile-toggle-btn <?= $isSignUpActive ? 'active' : '' ?>" id="mobile-toggle-signup" role="tab" aria-selected="<?= $isSignUpActive ? 'true' : 'false' ?>" aria-controls="auth-signup-panel">
        Sign Up
      </button>
    </div>

    <!-- Mobile Welcome Clay Banner -->
    <div class="auth-mobile-welcome-banner" id="auth-mobile-welcome-banner">
      <img src="/assets/images/infinity-blog-symbol.png" alt="Infinity Blog" class="auth-mobile-welcome-symbol" width="28" height="17">
      <span class="auth-mobile-welcome-text" id="auth-mobile-welcome-text">
        <?= $isSignUpActive ? 'Hello, Friend! Register to join Infinity Blog.' : 'Welcome back! Sign in to your account.' ?>
      </span>
    </div>

    <!-- =========================================================================
         PANEL 1: SIGN IN FORM
         ========================================================================= -->
    <div class="auth-form-container auth-signin-panel" id="auth-signin-panel" role="tabpanel" aria-labelledby="mobile-toggle-signin">
      <div class="auth-panel-inner">
        <div class="auth-brand-logo-wrap">
          <a href="/" aria-label="Infinity Blog Home">
            <img src="/assets/images/infinity-blog-logo-horizontal.png" alt="Infinity Blog" class="auth-card-logo-img" width="160" height="32" loading="eager">
          </a>
        </div>
        <h1 class="auth-form-title">Sign In</h1>



        <?php if (!empty($_GET['registered'])): ?>
          <div class="alert-banner alert-success" style="display: flex; margin-bottom: 16px; font-size: 0.85rem;">
            <span>Account created successfully! Please sign in with your credentials.</span>
          </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error']) && !$isSignUpActive): ?>
          <div class="alert-banner alert-error" style="display: flex; margin-bottom: 16px; font-size: 0.85rem;">
            <span><?= htmlspecialchars($_GET['error'] === 'csrf' ? 'Security check or session expired. Please try again.' : ($_GET['error'] === 'rate_limited' ? 'Too many failed login attempts. Please try again later.' : 'The login identifier or password is invalid.'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endif; ?>

        <div id="login-error-alert" class="alert-banner alert-error" style="display: none; margin-bottom: 16px; font-size: 0.85rem;"></div>

        <!-- Credentials Form -->
        <form id="login-form" class="auth-inner-form" method="POST" action="/auth/login<?= !empty($effectiveRedirect) ? '?redirect=' . urlencode($effectiveRedirect) : '' ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($effectiveRedirect)): ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($effectiveRedirect, ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>

          <div class="form-group">
            <div class="input-with-icon">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <input type="text" id="identifier" name="identifier" class="form-input" placeholder="Email, Username or Full Name" required autocomplete="username" autofocus>
            </div>
          </div>

          <div class="form-group">
            <div class="input-with-icon">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <input type="password" id="password" name="password" class="form-input" placeholder="Password" required autocomplete="current-password">
              <button type="button" class="input-toggle-btn" aria-label="Toggle password visibility">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </div>
          </div>

          <a href="javascript:void(0)" class="auth-forgot-link" id="auth-forgot-pwd">Forget Your Password?</a>

          <button type="submit" id="login-submit-btn" class="btn auth-btn-primary">
            SIGN IN
          </button>

          <p class="auth-mobile-switch-text">
            Don't have an account? <button type="button" class="auth-inline-switch-btn" id="mobile-inline-to-signup">Sign Up</button>
          </p>

          <p class="auth-return-link">
            <a href="/">← Return to Home</a>
          </p>
        </form>

        <!-- Two-Step Challenge Form (Login 2FA) -->
        <form id="challenge-form" class="auth-inner-form" method="POST" action="/auth/challenge/verify<?= !empty($effectiveRedirect) ? '?redirect=' . urlencode($effectiveRedirect) : '' ?>" style="display: none;" novalidate>
          <input type="hidden" name="csrf_token" id="challenge-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="challenge_token" name="challenge_token" value="">
          <?php if (!empty($effectiveRedirect)): ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($effectiveRedirect, ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>

          <div style="text-align: center; margin-bottom: 16px;">
            <div style="font-size: 2rem; margin-bottom: 6px;">🔐</div>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;">
              Two-Step Verification
            </h2>
            <p style="color: var(--text-muted); font-size: 0.86rem; line-height: 1.4;">
              Enter the 6-digit code sent to:
              <strong id="challenge-masked-email" style="display: block; color: var(--primary-color); margin-top: 3px; font-family: monospace; font-size: 0.92rem;"></strong>
            </p>
          </div>

          <div id="challenge-delivery-notice" style="display: none; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 8px 12px; border-radius: 10px; font-size: 0.8rem; margin-bottom: 14px; color: var(--text-body);">
            <span id="challenge-delivery-text"></span>
          </div>

          <div class="form-group" style="margin-bottom: 16px;">
            <input type="text" id="challenge_code" name="code" class="form-input" style="font-size: 1.6rem; letter-spacing: 0.3em; text-align: center; font-weight: 700; font-family: monospace;" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required>
          </div>

          <button type="submit" id="challenge-submit-btn" class="btn auth-btn-primary">
            VERIFY & SIGN IN
          </button>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: 0.82rem; width: 100%;">
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

    <!-- =========================================================================
         PANEL 2: SIGN UP / REGISTRATION FORM
         ========================================================================= -->
    <div class="auth-form-container auth-signup-panel" id="auth-signup-panel" role="tabpanel" aria-labelledby="mobile-toggle-signup">
      <div class="auth-panel-inner">
        <div class="auth-brand-logo-wrap">
          <a href="/" aria-label="Infinity Blog Home">
            <img src="/assets/images/infinity-blog-logo-horizontal.png" alt="Infinity Blog" class="auth-card-logo-img" width="160" height="32" loading="eager">
          </a>
        </div>
        <h1 class="auth-form-title">Create Account</h1>



        <div id="register-error-alert" class="alert-banner alert-error" role="alert" aria-live="polite" style="<?= !empty($initialError) && $isSignUpActive ? 'display: flex; margin-bottom: 14px;' : 'display: none; margin-bottom: 14px;' ?>">
          <span id="register-error-text"><?= htmlspecialchars((string)$initialError, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- Registration Step 1 Form -->
        <form id="register-form" class="auth-inner-form" method="POST" action="/register" style="<?= $isVerifyStep ? 'display: none;' : '' ?>" novalidate>
          <input type="hidden" name="csrf_token" id="reg-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <div class="form-group" id="group-display_name">
            <div class="input-with-icon <?= !empty($fieldErrors['display_name']) ? 'has-error' : '' ?>">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <input type="text" id="display_name" name="display_name" class="form-input" placeholder="Full Name" value="<?= htmlspecialchars((string)$oldDisplayName, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name" aria-describedby="field-error-display_name">
            </div>
            <div class="field-error-msg" id="field-error-display_name" role="alert" style="<?= !empty($fieldErrors['display_name']) ? 'display: block;' : 'display: none;' ?>">
              <?= htmlspecialchars($fieldErrors['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>

          <div class="form-group" id="group-email">
            <div class="input-with-icon <?= !empty($fieldErrors['email']) ? 'has-error' : '' ?>">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                <polyline points="22,6 12,13 2,6"></polyline>
              </svg>
              <input type="email" id="email" name="email" class="form-input" placeholder="Email Address" value="<?= htmlspecialchars((string)$oldEmail, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" aria-describedby="field-error-email">
            </div>
            <div class="field-error-msg" id="field-error-email" role="alert" style="<?= !empty($fieldErrors['email']) ? 'display: block;' : 'display: none;' ?>">
              <?= htmlspecialchars($fieldErrors['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>

          <div class="form-group" id="group-phone">
            <div class="input-with-icon <?= !empty($fieldErrors['phone']) ? 'has-error' : '' ?>">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
              </svg>
              <input type="tel" id="phone" name="phone" class="form-input" placeholder="Mobile Phone (+91 ...)" value="<?= htmlspecialchars((string)$oldPhone, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="tel" aria-describedby="field-error-phone">
            </div>
            <div class="field-error-msg" id="field-error-phone" role="alert" style="<?= !empty($fieldErrors['phone']) ? 'display: block;' : 'display: none;' ?>">
              <?= htmlspecialchars($fieldErrors['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>

          <div class="form-group" id="group-password">
            <div class="input-with-icon <?= !empty($fieldErrors['password']) ? 'has-error' : '' ?>">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <input type="password" id="register_password" name="password" class="form-input" placeholder="Password (min 8 chars)" required autocomplete="new-password" aria-describedby="field-error-password">
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
            <div class="input-with-icon <?= !empty($fieldErrors['confirm_password']) ? 'has-error' : '' ?>">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
              <input type="password" id="register_confirm_password" name="confirm_password" class="form-input" placeholder="Confirm Password" required autocomplete="new-password" aria-describedby="field-error-confirm_password">
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

          <!-- Optional Collapsible Profile Details -->
          <details class="auth-optional-details">
            <summary class="auth-optional-summary">
              Optional Details (Bio, Location, Website)
            </summary>
            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
              <div>
                <input type="text" id="location" name="location" class="form-input" placeholder="Location (e.g. London, UK)" maxlength="120" value="<?= htmlspecialchars((string)$oldLocation, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div>
                <input type="text" id="interests" name="interests" class="form-input" placeholder="Interests (e.g. AI, Web Dev)" maxlength="255" value="<?= htmlspecialchars((string)$oldInterests, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div>
                <input type="url" id="website" name="website" class="form-input" placeholder="Website (https://...)" maxlength="255" value="<?= htmlspecialchars((string)$oldWebsite, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div>
                <textarea id="bio" name="bio" rows="2" class="form-input" placeholder="Brief Bio..." maxlength="1000"><?= htmlspecialchars((string)$oldBio, ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
            </div>
          </details>

          <button type="submit" id="register-submit-btn" class="btn auth-btn-primary">
            SIGN UP
          </button>

          <p class="auth-mobile-switch-text">
            Already have an account? <button type="button" class="auth-inline-switch-btn" id="mobile-inline-to-signin">Sign In</button>
          </p>

          <p class="auth-return-link">
            <a href="/">← Return to Home</a>
          </p>
        </form>

        <!-- Step 2: Email Verification Challenge Form -->
        <form id="register-challenge-form" class="auth-inner-form" method="POST" action="/auth/register/verify" style="<?= $isVerifyStep ? 'display: block;' : 'display: none;' ?>" novalidate>
          <input type="hidden" name="csrf_token" id="reg-challenge-csrf-token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="reg_challenge_token" name="challenge_token" value="<?= htmlspecialchars((string)$tokenParam, ENT_QUOTES, 'UTF-8') ?>">

          <div style="text-align: center; margin-bottom: 16px;">
            <div style="font-size: 2rem; margin-bottom: 6px;">✉️</div>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-heading); margin-bottom: 4px;">
              Verify Your Email
            </h2>
            <p style="color: var(--text-muted); font-size: 0.86rem; line-height: 1.4;">
              We sent a 6-digit verification code to:
              <strong id="reg-challenge-masked-email" style="display: block; color: var(--primary-color); margin-top: 3px; font-family: monospace; font-size: 0.92rem;"><?= htmlspecialchars((string)$initialMaskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
          </div>

          <div id="reg-challenge-delivery-notice" style="display: none; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 8px 12px; border-radius: 10px; font-size: 0.8rem; margin-bottom: 14px; color: var(--text-body);">
            <span id="reg-challenge-delivery-text"></span>
          </div>

          <div class="form-group" style="margin-bottom: 16px;">
            <input type="text" id="reg_challenge_code" name="code" class="form-input" style="font-size: 1.6rem; letter-spacing: 0.3em; text-align: center; font-weight: 700; font-family: monospace;" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required aria-describedby="field-error-code">
            <div class="field-error-msg" id="field-error-code" role="alert" style="text-align: center; margin-top: 6px;"></div>
          </div>

          <button type="submit" id="reg-challenge-submit-btn" class="btn auth-btn-primary">
            VERIFY EMAIL
          </button>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; font-size: 0.82rem; width: 100%;">
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

    <!-- =========================================================================
         ANIMATED SLIDING OVERLAY (Big Screen / Desktop > 768px)
         ========================================================================= -->
    <div class="auth-overlay-container" id="auth-overlay-container" aria-hidden="true">
      <div class="auth-overlay" id="auth-overlay">
        <!-- Overlay Panel Left: Shown when user is on Sign Up, prompts to Sign In -->
        <div class="auth-overlay-panel auth-overlay-left">
          <img src="/assets/images/infinity-blog-symbol.png" alt="Infinity Blog" class="auth-overlay-symbol" width="56" height="35">
          <h2 class="auth-overlay-title">Welcome Back!</h2>
          <p class="auth-overlay-desc">Enter your personal details to use all of site features</p>
          <button type="button" class="btn auth-btn-ghost" id="to-signin-btn" aria-label="Switch to Sign In">
            SIGN IN
          </button>
        </div>

        <!-- Overlay Panel Right: Shown when user is on Sign In, prompts to Sign Up -->
        <div class="auth-overlay-panel auth-overlay-right">
          <img src="/assets/images/infinity-blog-symbol.png" alt="Infinity Blog" class="auth-overlay-symbol" width="56" height="35">
          <h2 class="auth-overlay-title">Hello, Friend!</h2>
          <p class="auth-overlay-desc">Register with your personal details to use all of site features</p>
          <button type="button" class="btn auth-btn-ghost" id="to-signup-btn" aria-label="Switch to Sign Up">
            SIGN UP
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function() {
  function initSlidingAuth() {
    const card = document.getElementById('auth-sliding-card');
    const toSignUpBtn = document.getElementById('to-signup-btn');
    const toSignInBtn = document.getElementById('to-signin-btn');
    const mobileToggleSignIn = document.getElementById('mobile-toggle-signin');
    const mobileToggleSignUp = document.getElementById('mobile-toggle-signup');
    const mobileInlineToSignUp = document.getElementById('mobile-inline-to-signup');
    const mobileInlineToSignIn = document.getElementById('mobile-inline-to-signin');
    const mobileWelcomeText = document.getElementById('auth-mobile-welcome-text');
    const forgotPwdBtn = document.getElementById('auth-forgot-pwd');

    if (!card) return;

    // Toggle Functionality
    function switchToSignUp(updateHistory = true) {
      card.classList.add('right-panel-active', 'mobile-signup-active');
      if (mobileToggleSignIn && mobileToggleSignUp) {
        mobileToggleSignIn.classList.remove('active');
        mobileToggleSignIn.setAttribute('aria-selected', 'false');
        mobileToggleSignUp.classList.add('active');
        mobileToggleSignUp.setAttribute('aria-selected', 'true');
      }
      if (mobileWelcomeText) {
        mobileWelcomeText.textContent = 'Hello, Friend! Register to join Infinity Blog.';
      }
      if (updateHistory && window.history && window.history.pushState) {
        window.history.pushState({ mode: 'signup' }, '', '/register');
        document.title = 'Create Account — Infinity Blog';
      }
      const firstInput = document.getElementById('display_name');
      if (firstInput && window.innerWidth > 768) {
        setTimeout(() => firstInput.focus(), 300);
      }
    }

    function switchToSignIn(updateHistory = true) {
      card.classList.remove('right-panel-active', 'mobile-signup-active');
      if (mobileToggleSignIn && mobileToggleSignUp) {
        mobileToggleSignUp.classList.remove('active');
        mobileToggleSignUp.setAttribute('aria-selected', 'false');
        mobileToggleSignIn.classList.add('active');
        mobileToggleSignIn.setAttribute('aria-selected', 'true');
      }
      if (mobileWelcomeText) {
        mobileWelcomeText.textContent = 'Welcome back! Sign in to your account.';
      }
      if (updateHistory && window.history && window.history.pushState) {
        window.history.pushState({ mode: 'signin' }, '', '/login');
        document.title = 'Sign In — Infinity Blog';
      }
      const firstInput = document.getElementById('identifier');
      if (firstInput && window.innerWidth > 768) {
        setTimeout(() => firstInput.focus(), 300);
      }
    }

    if (toSignUpBtn) {
      toSignUpBtn.addEventListener('click', () => switchToSignUp(true));
    }
    if (toSignInBtn) {
      toSignInBtn.addEventListener('click', () => switchToSignIn(true));
    }
    if (mobileToggleSignUp) {
      mobileToggleSignUp.addEventListener('click', () => switchToSignUp(true));
    }
    if (mobileToggleSignIn) {
      mobileToggleSignIn.addEventListener('click', () => switchToSignIn(true));
    }
    if (mobileInlineToSignUp) {
      mobileInlineToSignUp.addEventListener('click', () => switchToSignUp(true));
    }
    if (mobileInlineToSignIn) {
      mobileInlineToSignIn.addEventListener('click', () => switchToSignIn(true));
    }

    // Handle Browser Back / Forward buttons
    window.addEventListener('popstate', (e) => {
      const path = window.location.pathname;
      if (path === '/register' || window.location.hash === '#register' || e.state?.mode === 'signup') {
        switchToSignUp(false);
      } else {
        switchToSignIn(false);
      }
    });

    // Password visibility toggles
    document.querySelectorAll('.input-toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const input = btn.previousElementSibling;
        if (input && (input.type === 'password' || input.type === 'text')) {
          input.type = input.type === 'password' ? 'text' : 'password';
        }
      });
    });

    // Forgot password prompt
    if (forgotPwdBtn) {
      forgotPwdBtn.addEventListener('click', () => {
        const email = document.getElementById('identifier')?.value.trim();
        const mailto = 'mailto:support@infinityblog.local?subject=' + encodeURIComponent('Password Reset Request') + '&body=' + encodeURIComponent('Hello Infinity Blog Support,\n\nI need to reset the password for my account: ' + (email || '[Enter your email/username]') + '\n\nThank you!');
        window.location.href = mailto;
      });
    }

    // Helper: Obtain CSRF token
    async function getCsrfToken() {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      if (!csrf) {
        try {
          const res = await fetch('/auth/csrf', { headers: { 'Accept': 'application/json' } });
          if (res.ok) {
            const d = await res.json();
            csrf = d.data?.csrf_token || '';
          }
        } catch (e) {}
      }
      return csrf || '';
    }

    function syncAllCsrfTokens(newToken) {
      if (!newToken) return;
      document.querySelectorAll('input[name="csrf_token"]').forEach(el => el.value = newToken);
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.setAttribute('content', newToken);
    }

    const getTargetUrl = () => {
      const urlParams = new URLSearchParams(window.location.search);
      const redirectParam = urlParams.get('redirect') || document.querySelector('input[name="redirect"]')?.value;
      return (redirectParam && redirectParam.startsWith('/') && !redirectParam.startsWith('//'))
        ? redirectParam
        : '/dashboard';
    };

    // =========================================================================
    // SIGN IN LOGIC & 2FA CHALLENGE
    // =========================================================================
    const loginForm = document.getElementById('login-form');
    const challengeForm = document.getElementById('challenge-form');
    const loginErrorBox = document.getElementById('login-error-alert');
    const loginSubmitBtn = document.getElementById('login-submit-btn');
    const challengeSubmitBtn = document.getElementById('challenge-submit-btn');
    const challengeTokenInput = document.getElementById('challenge_token');
    const challengeCodeInput = document.getElementById('challenge_code');
    const challengeMaskedEmail = document.getElementById('challenge-masked-email');
    const challengeDeliveryNotice = document.getElementById('challenge-delivery-notice');
    const challengeDeliveryText = document.getElementById('challenge-delivery-text');
    const challengeBackBtn = document.getElementById('challenge-back-btn');
    const challengeResendBtn = document.getElementById('challenge-resend-btn');

    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginErrorBox.style.display = 'none';

        const identifier = document.getElementById('identifier')?.value.trim();
        const password = document.getElementById('password')?.value;

        if (!identifier || !password) {
          loginErrorBox.textContent = 'Please enter both your login identifier and password.';
          loginErrorBox.style.display = 'flex';
          return;
        }

        loginSubmitBtn.disabled = true;
        loginSubmitBtn.textContent = 'VERIFYING...';

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
              challengeTokenInput.value = data.data.challenge_token;
              challengeMaskedEmail.textContent = data.data.masked_email;
              if (data.data.csrf_token) syncAllCsrfTokens(data.data.csrf_token);

              if (data.data.delivery_status === 'BLOCKED') {
                challengeDeliveryNotice.style.display = 'block';
                if (data.data.dev_code) {
                  challengeDeliveryText.innerHTML = '⚡ Local Dev Mode: Email is unconfigured. Verification code is: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
                  challengeCodeInput.value = data.data.dev_code;
                } else {
                  challengeDeliveryText.textContent = 'Notice: Email delivery is simulated in this environment.';
                }
              } else {
                challengeDeliveryNotice.style.display = 'none';
              }

              loginForm.style.display = 'none';
              challengeForm.style.display = 'block';
              challengeCodeInput.focus();
            } else {
              loginSubmitBtn.textContent = 'SIGNED IN! REDIRECTING...';
              window.location.href = getTargetUrl();
            }
          } else {
            loginErrorBox.textContent = data.error?.message || 'The login identifier or password is invalid.';
            loginErrorBox.style.display = 'flex';
            loginSubmitBtn.disabled = false;
            loginSubmitBtn.textContent = 'SIGN IN';
          }
        } catch (err) {
          loginErrorBox.textContent = 'Connection error. Please try again.';
          loginErrorBox.style.display = 'flex';
          loginSubmitBtn.disabled = false;
          loginSubmitBtn.textContent = 'SIGN IN';
        }
      });
    }

    if (challengeForm) {
      challengeForm.addEventListener('submit', async (e) => {
        if (e) e.preventDefault();
        loginErrorBox.style.display = 'none';

        const challenge_token = challengeTokenInput.value.trim();
        const code = challengeCodeInput.value.trim();

        if (!code || code.length !== 6) {
          loginErrorBox.textContent = 'Please enter the complete 6-digit verification code.';
          loginErrorBox.style.display = 'flex';
          return;
        }

        challengeSubmitBtn.disabled = true;
        challengeSubmitBtn.textContent = 'VERIFYING CODE...';

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
            challengeSubmitBtn.textContent = 'VERIFIED! REDIRECTING...';
            window.location.href = getTargetUrl();
          } else {
            loginErrorBox.textContent = data.error?.message || 'The verification code is incorrect or has expired.';
            loginErrorBox.style.display = 'flex';
            challengeSubmitBtn.disabled = false;
            challengeSubmitBtn.textContent = 'VERIFY & SIGN IN';
            challengeCodeInput.select();
          }
        } catch (err) {
          challengeForm.submit();
        }
      });

      if (challengeResendBtn) {
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
              loginErrorBox.textContent = data.error?.message || 'Failed to resend code.';
              loginErrorBox.style.display = 'flex';
            }
          } catch {
            challengeResendBtn.disabled = false;
            challengeResendBtn.textContent = 'Resend Code';
          }
        });
      }

      if (challengeBackBtn) {
        challengeBackBtn.addEventListener('click', () => {
          challengeForm.style.display = 'none';
          loginForm.style.display = 'block';
          loginErrorBox.style.display = 'none';
          loginSubmitBtn.disabled = false;
          loginSubmitBtn.textContent = 'SIGN IN';
        });
      }
    }

    // =========================================================================
    // REGISTRATION LOGIC & EMAIL VERIFICATION CHALLENGE
    // =========================================================================
    const regForm = document.getElementById('register-form');
    const regChallengeForm = document.getElementById('register-challenge-form');
    const regErrorBox = document.getElementById('register-error-alert');
    const regErrorText = document.getElementById('register-error-text');
    const regSubmitBtn = document.getElementById('register-submit-btn');
    const regChallengeSubmitBtn = document.getElementById('reg-challenge-submit-btn');
    const regChallengeTokenInput = document.getElementById('reg_challenge_token');
    const regChallengeCodeInput = document.getElementById('reg_challenge_code');
    const regChallengeMaskedEmail = document.getElementById('reg-challenge-masked-email');
    const regChallengeDeliveryNotice = document.getElementById('reg-challenge-delivery-notice');
    const regChallengeDeliveryText = document.getElementById('reg-challenge-delivery-text');
    const regChallengeBackBtn = document.getElementById('reg-challenge-back-btn');
    const regChallengeResendBtn = document.getElementById('reg-challenge-resend-btn');

    let isRegSubmitting = false;

    function setFieldError(fieldName, message) {
      const errElem = document.getElementById('field-error-' + fieldName);
      if (errElem) {
        errElem.textContent = message;
        errElem.style.display = 'block';
      }
      const input = document.getElementById(fieldName === 'password' ? 'register_password' : (fieldName === 'confirm_password' ? 'register_confirm_password' : fieldName));
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
      const input = document.getElementById(fieldName === 'password' ? 'register_password' : (fieldName === 'confirm_password' ? 'register_confirm_password' : fieldName));
      if (input) {
        input.removeAttribute('aria-invalid');
        const wrapper = input.closest('.input-with-icon');
        if (wrapper) wrapper.classList.remove('has-error');
      }
    }

    function clearAllRegErrors() {
      ['display_name', 'email', 'phone', 'password', 'confirm_password', 'code'].forEach(clearFieldError);
      if (regErrorBox) {
        regErrorBox.style.display = 'none';
        if (regErrorText) regErrorText.textContent = '';
      }
    }

    function showRegGeneralError(msg) {
      if (regErrorBox && regErrorText) {
        regErrorText.textContent = msg;
        regErrorBox.style.display = 'flex';
      }
    }

    function restoreRegSubmitBtn() {
      if (regSubmitBtn) {
        regSubmitBtn.disabled = false;
        regSubmitBtn.removeAttribute('aria-busy');
        regSubmitBtn.textContent = 'SIGN UP';
      }
      isRegSubmitting = false;
    }

    // Dynamic clear on input
    ['display_name', 'email', 'phone', 'register_password', 'register_confirm_password'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      const fieldKey = id === 'register_password' ? 'password' : (id === 'register_confirm_password' ? 'confirm_password' : id);
      el.addEventListener('input', () => {
        clearFieldError(fieldKey);
        const remaining = document.querySelectorAll('#auth-signup-panel .field-error-msg[style*="display: block"]');
        if (remaining.length === 0 && regErrorBox) {
          regErrorBox.style.display = 'none';
        }
      });
    });

    if (regChallengeCodeInput) {
      regChallengeCodeInput.addEventListener('input', () => {
        clearFieldError('code');
        if (regErrorBox) regErrorBox.style.display = 'none';
      });
    }

    if (regForm) {
      regForm.addEventListener('submit', async (e) => {
        if (e) e.preventDefault();
        clearAllRegErrors();

        const displayNameInput = document.getElementById('display_name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('register_password');
        const confirmPasswordInput = document.getElementById('register_confirm_password');

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
          let firstInvalid = null;
          for (const [fName, fMsg] of Object.entries(errors)) {
            setFieldError(fName, fMsg);
            if (!firstInvalid) firstInvalid = document.getElementById(fName === 'password' ? 'register_password' : (fName === 'confirm_password' ? 'register_confirm_password' : fName));
          }
          showRegGeneralError('Please correct the highlighted fields below.');
          if (firstInvalid) firstInvalid.focus();
          return;
        }

        if (isRegSubmitting) return;
        isRegSubmitting = true;

        regSubmitBtn.disabled = true;
        regSubmitBtn.setAttribute('aria-busy', 'true');
        regSubmitBtn.textContent = 'CREATING ACCOUNT...';

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
          const csrf = await getCsrfToken();
          if (!csrf) {
            restoreRegSubmitBtn();
            showRegGeneralError('Unable to establish a secure session. Please refresh and try again.');
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
          } catch (jsonErr) {}

          if (res.ok && data && data.data) {
            syncAllCsrfTokens(data.data.csrf_token);

            if (data.data.challenge_required) {
              if (data.data.delivery_status === 'FAILED') {
                restoreRegSubmitBtn();
                showRegGeneralError(data.data.message || 'Could not send verification code. Please check your email or try again.');
                return;
              }

              regChallengeTokenInput.value = data.data.challenge_token || '';
              if (regChallengeMaskedEmail) {
                regChallengeMaskedEmail.textContent = data.data.masked_email || email;
              }

              if (data.data.delivery_status === 'BLOCKED') {
                regChallengeDeliveryNotice.style.display = 'block';
                if (data.data.dev_code) {
                  regChallengeDeliveryText.innerHTML = '⚡ Local Dev Mode: SMTP is unconfigured. Your verification code is: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
                  regChallengeCodeInput.value = data.data.dev_code;
                } else {
                  regChallengeDeliveryText.textContent = 'Notice: Email delivery is simulated in this environment.';
                }
              } else {
                regChallengeDeliveryNotice.style.display = 'none';
              }

              regForm.style.display = 'none';
              regChallengeForm.style.display = 'block';
              if (regChallengeCodeInput) regChallengeCodeInput.focus();
            } else {
              regSubmitBtn.textContent = 'ACCOUNT CREATED! REDIRECTING...';
              window.location.href = '/login?registered=1';
            }
          } else {
            restoreRegSubmitBtn();
            if (passwordInput) passwordInput.value = '';
            if (confirmPasswordInput) confirmPasswordInput.value = '';

            if (data && data.error && data.error.fields) {
              let firstServerInvalid = null;
              for (const [fName, fMsg] of Object.entries(data.error.fields)) {
                setFieldError(fName, fMsg);
                if (!firstServerInvalid) firstServerInvalid = document.getElementById(fName === 'password' ? 'register_password' : (fName === 'confirm_password' ? 'register_confirm_password' : fName));
              }
              showRegGeneralError(data.error.message || 'Please correct the highlighted errors.');
              if (firstServerInvalid) firstServerInvalid.focus();
            } else {
              showRegGeneralError(data?.error?.message || 'Registration could not be started. Please try again.');
            }
          }
        } catch (err) {
          restoreRegSubmitBtn();
          showRegGeneralError('Network error. Unable to connect to server. Please try again.');
        }
      });
    }

    if (regChallengeForm) {
      regChallengeForm.addEventListener('submit', async (e) => {
        if (e) e.preventDefault();
        clearFieldError('code');
        if (regErrorBox) regErrorBox.style.display = 'none';

        const challenge_token = regChallengeTokenInput.value.trim();
        const code = regChallengeCodeInput.value.trim();

        if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
          setFieldError('code', 'Please enter the complete 6-digit verification code.');
          regChallengeCodeInput.focus();
          return;
        }

        regChallengeSubmitBtn.disabled = true;
        regChallengeSubmitBtn.setAttribute('aria-busy', 'true');
        regChallengeSubmitBtn.textContent = 'VERIFYING CODE...';

        try {
          const csrf = await getCsrfToken();
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
          try { data = await res.json(); } catch (je) {}

          if (res.ok && data) {
            regChallengeSubmitBtn.textContent = 'VERIFIED! REDIRECTING...';
            window.location.href = '/login?registered=1';
          } else {
            const failMsg = data?.error?.message || 'The verification code is incorrect or has expired.';
            setFieldError('code', failMsg);
            showRegGeneralError(failMsg);
            regChallengeSubmitBtn.disabled = false;
            regChallengeSubmitBtn.textContent = 'VERIFY EMAIL';
            regChallengeCodeInput.select();
          }
        } catch (err) {
          regChallengeSubmitBtn.disabled = false;
          regChallengeSubmitBtn.textContent = 'VERIFY EMAIL';
          showRegGeneralError('Network connection error. Unable to verify code.');
        }
      });

      if (regChallengeResendBtn) {
        regChallengeResendBtn.addEventListener('click', async () => {
          const challenge_token = regChallengeTokenInput.value.trim();
          if (!challenge_token) return;

          regChallengeResendBtn.disabled = true;
          regChallengeResendBtn.textContent = 'Sending...';

          try {
            const csrf = await getCsrfToken();
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
            try { data = await res.json(); } catch (je) {}

            if (res.ok && data) {
              syncAllCsrfTokens(data.data?.csrf_token);
              if (data.data?.dev_code) {
                regChallengeDeliveryNotice.style.display = 'block';
                regChallengeDeliveryText.innerHTML = '⚡ Local Dev Mode: A new verification code was generated: <strong style="font-size: 1.05rem; color: var(--primary-color); font-family: monospace;">' + data.data.dev_code + '</strong>';
                regChallengeCodeInput.value = data.data.dev_code;
              }
              regChallengeResendBtn.textContent = 'Code Sent!';
              setTimeout(() => {
                regChallengeResendBtn.disabled = false;
                regChallengeResendBtn.textContent = 'Resend Code';
              }, 30000);
            } else {
              showRegGeneralError(data?.error?.message || 'Failed to resend code.');
              regChallengeResendBtn.disabled = false;
              regChallengeResendBtn.textContent = 'Resend Code';
            }
          } catch {
            showRegGeneralError('Network error resending code.');
            regChallengeResendBtn.disabled = false;
            regChallengeResendBtn.textContent = 'Resend Code';
          }
        });
      }

      if (regChallengeBackBtn) {
        regChallengeBackBtn.addEventListener('click', () => {
          regChallengeForm.style.display = 'none';
          regForm.style.display = 'block';
          restoreRegSubmitBtn();
          clearAllRegErrors();
        });
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlidingAuth);
  } else {
    initSlidingAuth();
  }
})();
</script>
