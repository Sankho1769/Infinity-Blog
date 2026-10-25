<?php
declare(strict_types=1);
/**
 * @var array<string, mixed> $user
 * @var array<string, string> $errors
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 * @var list<array<string, mixed>> $categories
 */
$errors = $errors ?? [];
$categories = $categories ?? [];
$avatarSrc = $user['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($user['avatar_path'] ?? null);
$bannerSrc = $user['banner_url'] ?? ($user['banner_path'] ?? null);
$currentInterests = array_filter(array_map('trim', explode(',', (string) ($user['interests'] ?? ''))));
?>
<div class="profile-edit-container" style="max-width: 860px; margin: 32px auto;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
      <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; letter-spacing: -0.02em;">
        Edit Your Profile
      </h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Update your personal details, social connections, and writing presence on Infinity Blog.
      </p>
    </div>
    <a href="/profile" class="btn btn-secondary btn-pill">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      Back to Profile
    </a>
  </div>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert-banner alert-danger" role="alert" style="margin-bottom: 24px;">
      <span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <div style="display: grid; grid-template-columns: 1fr; gap: 28px;">
    <!-- Avatar Upload Card with Live Client-Side Preview (PART 5) -->
    <div class="glass-card" style="padding: 28px 24px; border-radius: 20px;">
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
        Profile Avatar
      </h2>
      <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
        Upload a high-resolution picture (PNG, JPEG, WebP, or GIF up to 2MB). Preview it immediately before uploading.
      </p>

      <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
        <!-- Avatar Display / Preview Frame -->
        <div style="position: relative; flex-shrink: 0;" id="avatar-frame-container">
          <img id="avatar-preview-img"
               src="<?= !empty($avatarSrc) ? htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') : '' ?>"
               alt="Current Avatar"
               style="<?= empty($avatarSrc) ? 'display: none;' : '' ?> width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255, 255, 255, 0.9); box-shadow: 0 4px 14px rgba(0,0,0,0.08); transition: all 0.25s ease;"
               onerror="this.style.display='none'; const fb = document.getElementById('avatar-fallback-initial'); if (fb) fb.style.display='flex';">

          <div id="avatar-fallback-initial"
               style="<?= !empty($avatarSrc) ? 'display: none;' : 'display: flex;' ?> width: 96px; height: 96px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; align-items: center; justify-content: center; font-size: 2.3rem; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.9); box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
            <?= strtoupper(substr($user['display_name'] ?? $user['username'] ?? 'C', 0, 1)) ?>
          </div>

          <span id="avatar-preview-badge" style="display: none; position: absolute; bottom: -4px; right: -4px; background: #10b981; color: white; font-size: 0.68rem; font-weight: 700; padding: 2px 6px; border-radius: 9999px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
            PREVIEW
          </span>
        </div>

        <!-- Avatar Upload Form -->
        <form id="avatar-upload-form" action="/profile/avatar" method="POST" enctype="multipart/form-data" style="flex: 1; min-width: 260px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          
          <div class="custom-file-upload-zone" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <input type="file" name="avatar" id="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="custom-file-input-hidden" required>
            
            <label for="avatar" class="btn btn-secondary btn-pill" id="avatar-choose-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              Choose Avatar
            </label>
            
            <span id="avatar-filename-display" class="custom-file-name-display" style="font-size: 0.85rem; color: var(--text-muted);">No file selected</span>
          </div>

          <!-- Live Preview Confirmation Bar -->
          <div id="avatar-preview-container">
            <div id="avatar-actions-bar" class="avatar-preview-container" style="display: none; margin-top: 14px; padding: 12px 14px; background: rgba(99, 102, 241, 0.08); border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2); align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
              <div style="font-size: 0.85rem; color: var(--text-heading); font-weight: 600;">
                ✓ Image ready to upload
              </div>
              <div style="display: flex; gap: 8px;">
                <button type="button" id="avatar-cancel-btn" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px;">Cancel</button>
                <button type="submit" id="avatar-confirm-btn" class="btn btn-primary btn-sm btn-pill" style="padding: 4px 16px;">Confirm Upload</button>
              </div>
            </div>
          </div>

          <?php if (!empty($errors['avatar'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 6px;"><?= htmlspecialchars($errors['avatar'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Profile Banner Upload Card with Live Preview (PART 5) -->
    <div class="glass-card" style="padding: 28px 24px; border-radius: 20px;">
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
        Profile Banner
      </h2>
      <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
        Upload a header banner image (JPEG, PNG, or WebP up to 4MB). Live preview updates immediately.
      </p>

      <div id="banner-preview-frame" style="margin-bottom: 16px; border-radius: 14px; overflow: hidden; height: 130px; max-width: 100%; border: 1px solid rgba(255, 255, 255, 0.6); box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: relative; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15)); display: flex; align-items: center; justify-content: center;">
        <img id="banner-preview-img"
             src="<?= !empty($bannerSrc) ? htmlspecialchars($bannerSrc, ENT_QUOTES, 'UTF-8') : '' ?>"
             alt="Banner Preview"
             style="<?= empty($bannerSrc) ? 'display: none;' : '' ?> width: 100%; height: 100%; object-fit: cover; transition: all 0.25s ease;"
             onerror="this.style.display='none'; document.getElementById('banner-fallback-text').style.display='block';">
        <div id="banner-fallback-text" style="<?= !empty($bannerSrc) ? 'display: none;' : 'display: block;' ?> font-size: 0.88rem; color: var(--text-muted); font-weight: 500;">
          No banner uploaded yet
        </div>
        <span id="banner-preview-badge" style="display: none; position: absolute; top: 10px; right: 10px; background: #10b981; color: white; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 9999px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
          PREVIEW
        </span>
      </div>

      <form id="banner-upload-form" action="/profile/banner" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="custom-file-upload-zone" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
          <input type="file" name="banner" id="banner" accept="image/png,image/jpeg,image/webp" class="custom-file-input-hidden" required>
          <label for="banner" class="btn btn-secondary btn-pill" id="banner-choose-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Choose Banner
          </label>
          <span id="banner-filename-display" class="custom-file-name-display" style="font-size: 0.85rem; color: var(--text-muted);">No file selected</span>
        </div>

        <!-- Banner Actions Bar -->
        <div id="banner-preview-container">
          <div id="banner-actions-bar" class="banner-preview-container" style="display: none; margin-top: 14px; padding: 12px 14px; background: rgba(99, 102, 241, 0.08); border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2); align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 0.85rem; color: var(--text-heading); font-weight: 600;">
              ✓ Banner image ready to upload
            </div>
            <div style="display: flex; gap: 8px;">
              <button type="button" id="banner-cancel-btn" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px;">Cancel</button>
              <button type="submit" id="banner-confirm-btn" class="btn btn-primary btn-sm btn-pill" style="padding: 4px 16px;">Confirm Upload</button>
            </div>
          </div>
        </div>

        <?php if (!empty($errors['banner'])): ?>
          <p style="color: #dc2626; font-size: 0.85rem; margin-top: 6px;"><?= htmlspecialchars($errors['banner'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </form>
    </div>

    <!-- Main Profile Details Form -->
    <div class="glass-card" style="padding: 32px 28px; border-radius: 20px;">
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 20px;">
        Profile Information
      </h2>

      <form action="/profile/edit" method="POST" class="profile-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Name & Username Row (PART 3: Instagram-Style Public Usernames) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
          <div>
            <label for="display_name" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Full Name <span style="color: #dc2626;">*</span>
            </label>
            <input type="text" id="display_name" name="display_name" class="form-input <?= !empty($errors['display_name']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['display_name'] ?? $user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Cristine Palmer" required maxlength="190">
            <span style="font-size: 0.76rem; color: var(--text-muted); margin-top: 3px; display: block;">Your authentic human identity displayed prominently on all articles.</span>
            <?php if (!empty($errors['display_name'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['display_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="username" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Username / Handle <span style="color: #dc2626;">*</span>
            </label>
            <div style="position: relative; display: flex; align-items: center;">
              <span style="position: absolute; left: 14px; font-weight: 700; color: var(--text-muted); font-size: 1rem; pointer-events: none;">@</span>
              <input type="text" id="username" name="username" class="form-input <?= !empty($errors['username']) ? 'input-error' : '' ?>" style="padding-left: 32px;" value="<?= htmlspecialchars(ltrim((string) ($_POST['username'] ?? $user['username'] ?? ''), '@'), ENT_QUOTES, 'UTF-8') ?>" placeholder="cristine_palmer" required minlength="3" maxlength="50" pattern="^[a-zA-Z0-9_.-]+$">
            </div>
            <span style="font-size: 0.76rem; color: var(--text-muted); margin-top: 3px; display: block;">Unique public handle (e.g. @cristine_palmer). Emails are never used as handles.</span>
            <?php if (!empty($errors['username'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Email & Private Phone Row -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
          <div>
            <label for="email" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Account Email <span style="color: #dc2626;">*</span> <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(Private)</span>
            </label>
            <input type="email" id="email" name="email" class="form-input <?= !empty($errors['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. cristine.palmer@example.com" required maxlength="190">
            <span style="font-size: 0.76rem; color: var(--text-muted); margin-top: 3px; display: block;">Used for login and security alerts. Never exposed on public profile cards.</span>
            <?php if (!empty($errors['email'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="phone" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Account Mobile Number <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(🔒 Private & Encrypted)</span>
            </label>
            <?php if (!empty($user['mobile_number_masked'])): ?>
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 0.82rem; background: rgba(59, 130, 246, 0.08); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.2); flex-wrap: wrap;">
                <span style="font-weight: 600; color: var(--text-heading);">Stored:</span>
                <span style="font-family: monospace; letter-spacing: 0.05em; font-weight: 700; color: var(--primary-color);"><?= htmlspecialchars($user['mobile_number_masked'], ENT_QUOTES, 'UTF-8') ?></span>
                <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; background: rgba(59, 130, 246, 0.1); color: var(--primary-color); font-weight: 600;">🔒 AES-256</span>
              </div>
            <?php endif; ?>
            <input type="tel" id="phone" name="phone" class="form-input <?= !empty($errors['phone']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['phone'] ?? $user['mobile_number'] ?? $user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+1 555-0199 or +91 98765 43210" maxlength="30">
            <span style="display: block; font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">
              Used for two-factor recovery. Stored with AES-256-GCM encryption.
            </span>
            <?php if (!empty($errors['phone'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Bio -->
        <div style="margin-bottom: 18px;">
          <label for="bio" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
            Bio <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(Max 1,000 characters)</span>
          </label>
          <textarea id="bio" name="bio" rows="4" class="form-input <?= !empty($errors['bio']) ? 'input-error' : '' ?>" style="resize: vertical;" maxlength="1000" placeholder="A brief introduction about your passions, background, and what you write about..."><?= htmlspecialchars($_POST['bio'] ?? $user['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          <?php if (!empty($errors['bio'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['bio'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>

        <!-- Location & Personal Website Row -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 24px;">
          <div>
            <label for="location" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Location <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(City, Country)</span>
            </label>
            <input type="text" id="location" name="location" class="form-input <?= !empty($errors['location']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['location'] ?? $user['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. San Francisco, CA" maxlength="120">
            <?php if (!empty($errors['location'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['location'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="website" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Personal Website / Portfolio
            </label>
            <input type="url" id="website" name="website" class="form-input <?= !empty($errors['website']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['website'] ?? $user['website'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://cristinepalmer.com" maxlength="255">
            <?php if (!empty($errors['website'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['website'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- PART 4: Enhanced Dedicated Social Media Links & Contact Section -->
        <div style="margin-bottom: 28px; padding: 22px; border-radius: 16px; background: rgba(99, 102, 241, 0.04); border: 1px solid rgba(99, 102, 241, 0.15);">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color);">
              <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
              <rect x="2" y="9" width="4" height="12"></rect>
              <circle cx="4" cy="4" r="2"></circle>
            </svg>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-heading); margin: 0;">
              Social Links & Public Contact
            </h3>
          </div>
          <p style="color: var(--text-muted); font-size: 0.86rem; margin-bottom: 20px;">
            Add your social handles or full URLs. They will be formatted automatically and displayed on your public author profile.
          </p>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 18px;">
            <!-- Instagram -->
            <div>
              <label for="instagram_url" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E1306C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                  <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                  <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                </svg>
                Instagram
              </label>
              <input type="text" id="instagram_url" name="instagram_url" class="form-input <?= !empty($errors['instagram_url']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['instagram_url'] ?? $user['instagram_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@username or https://instagram.com/username" maxlength="255">
              <?php if (!empty($errors['instagram_url'])): ?>
                <p style="color: #dc2626; font-size: 0.82rem; margin-top: 3px;"><?= htmlspecialchars($errors['instagram_url'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>

            <!-- X / Twitter -->
            <div>
              <label for="twitter_url" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="color: var(--text-heading);">
                  <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
                X (Twitter)
              </label>
              <input type="text" id="twitter_url" name="twitter_url" class="form-input <?= !empty($errors['twitter_url']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['twitter_url'] ?? $user['twitter_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@handle or https://x.com/handle" maxlength="255">
              <?php if (!empty($errors['twitter_url'])): ?>
                <p style="color: #dc2626; font-size: 0.82rem; margin-top: 3px;"><?= htmlspecialchars($errors['twitter_url'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>

            <!-- GitHub -->
            <div>
              <label for="github_url" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="color: var(--text-heading);">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                </svg>
                GitHub
              </label>
              <input type="text" id="github_url" name="github_url" class="form-input <?= !empty($errors['github_url']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['github_url'] ?? $user['github_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="username or https://github.com/username" maxlength="255">
              <?php if (!empty($errors['github_url'])): ?>
                <p style="color: #dc2626; font-size: 0.82rem; margin-top: 3px;"><?= htmlspecialchars($errors['github_url'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>

            <!-- Facebook -->
            <div>
              <label for="facebook_url" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2">
                  <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Facebook
              </label>
              <input type="text" id="facebook_url" name="facebook_url" class="form-input <?= !empty($errors['facebook_url']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['facebook_url'] ?? $user['facebook_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="username or https://facebook.com/username" maxlength="255">
              <?php if (!empty($errors['facebook_url'])): ?>
                <p style="color: #dc2626; font-size: 0.82rem; margin-top: 3px;"><?= htmlspecialchars($errors['facebook_url'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>

            <!-- LinkedIn -->
            <div>
              <label for="linkedin_url" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#0A66C2">
                  <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.62 1.62 0 1 0 0-3.24 1.62 1.62 0 0 0 0 3.24M7.85 18.5V10.13H5.07V18.5h2.78z"/>
                </svg>
                LinkedIn
              </label>
              <input type="text" id="linkedin_url" name="linkedin_url" class="form-input <?= !empty($errors['linkedin_url']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['linkedin_url'] ?? $user['linkedin_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="in/username or https://linkedin.com/in/username" maxlength="255">
              <?php if (!empty($errors['linkedin_url'])): ?>
                <p style="color: #dc2626; font-size: 0.82rem; margin-top: 3px;"><?= htmlspecialchars($errors['linkedin_url'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          </div>

          <!-- WhatsApp Contact & Public Privacy Toggle -->
          <div style="padding-top: 16px; border-top: 1px dashed rgba(99, 102, 241, 0.2);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; align-items: start;">
              <div>
                <label for="whatsapp_number" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="#25D366">
                    <path d="M17.472 14.382c-.301-.15-1.782-.879-2.057-.98-.276-.1-.476-.15-.677.15-.2.3-.777.98-.952 1.18-.175.2-.351.226-.652.075-.3-.15-1.267-.467-2.414-1.489-.893-.796-1.496-1.78-1.671-2.08-.175-.3-.019-.462.131-.612.135-.135.301-.35.452-.525.15-.176.2-.3.301-.5.1-.2.05-.376-.025-.526-.075-.15-.677-1.631-.927-2.233-.244-.587-.492-.507-.677-.516-.175-.01-.376-.01-.577-.01-.2 0-.526.075-.802.376-.276.3-1.053 1.028-1.053 2.508 0 1.48 1.078 2.909 1.229 3.109.15.2 2.122 3.24 5.141 4.544.718.31 1.279.496 1.716.634.721.23 1.378.197 1.9.12.58-.088 1.782-.728 2.032-1.431.25-.702.25-1.304.175-1.43-.075-.125-.276-.201-.577-.351z"/>
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.83.49 3.55 1.34 5.03L2 22l5.12-1.33A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.2c-1.57 0-3.04-.43-4.32-1.18l-.31-.18-3.04.79.81-2.96-.2-.32A8.17 8.17 0 013.8 12c0-4.52 3.68-8.2 8.2-8.2s8.2 3.68 8.2 8.2-3.68 8.2-8.2 8.2z"/>
                  </svg>
                  WhatsApp Contact Number
                </label>
                <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-input <?= !empty($errors['whatsapp_number']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['whatsapp_number'] ?? $user['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+14155552671 or +919876543210" maxlength="50">
                <span style="font-size: 0.76rem; color: var(--text-muted); margin-top: 3px; display: block;">Include country code with leading + (e.g. +14155552671). Generates a direct wa.me link.</span>
                <?php if (!empty($errors['whatsapp_number'])): ?>
                  <p style="color: #dc2626; font-size: 0.82rem; margin-top: 3px;"><?= htmlspecialchars($errors['whatsapp_number'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
              </div>

              <!-- Explicit Privacy Toggle (Default OFF / Private) -->
              <div style="padding-top: 24px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; user-select: none;">
                  <input type="checkbox" name="whatsapp_public" value="1" id="whatsapp_public" style="width: 18px; height: 18px; margin-top: 2px; accent-color: #25D366; cursor: pointer;" <?= !empty($_POST['whatsapp_public'] ?? $user['whatsapp_public']) ? 'checked' : '' ?>>
                  <div>
                    <span style="font-weight: 600; font-size: 0.9rem; color: var(--text-heading); display: block;">
                      Display WhatsApp on public author profile
                    </span>
                    <span style="font-size: 0.78rem; color: var(--text-muted); display: block; margin-top: 2px;">
                      🔒 <strong>Private by default.</strong> If unchecked, your WhatsApp number will remain completely hidden from other readers.
                    </span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- PART 6: Consistent Categories & Profile Interests -->
        <div style="margin-bottom: 24px;">
          <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
            <label class="form-label" style="font-weight: 600; font-size: 0.9rem; display: block; margin: 0;">
              Interests & Topics of Focus
            </label>
            <span style="font-size: 0.78rem; color: var(--text-muted);">Click categories to toggle</span>
          </div>

          <!-- Category Chips Selector -->
          <?php if (!empty($categories)): ?>
            <div id="category-chips-picker" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; padding: 12px 14px; background: rgba(255, 255, 255, 0.4); border-radius: 12px; border: 1px solid rgba(226, 232, 240, 0.7);">
              <?php foreach ($categories as $cat): ?>
                <?php
                  $catName = (string) $cat['name'];
                  $isSelected = in_array(strtolower($catName), array_map('strtolower', $currentInterests), true);
                ?>
                <button type="button"
                        class="category-select-chip category-chip <?= $isSelected ? 'chip-selected active' : '' ?>"
                        data-category-name="<?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>"
                        style="padding: 5px 12px; border-radius: 9999px; font-size: 0.82rem; font-weight: 600; cursor: pointer; border: 1px solid <?= $isSelected ? 'var(--primary-color)' : 'rgba(203, 213, 225, 0.8)' ?>; background: <?= $isSelected ? 'linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15))' : 'rgba(255, 255, 255, 0.7)' ?>; color: <?= $isSelected ? 'var(--primary-color)' : 'var(--text-body)' ?>; transition: all 0.15s ease;">
                  <?= $isSelected ? '✓ ' : '+ ' ?><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Synchronized Comma-separated Input -->
          <input type="text" id="interests" name="interests" class="form-input <?= !empty($errors['interests']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['interests'] ?? $user['interests'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Technology, Web Development, Design & UX, AI & Machine Learning" maxlength="255">
          <span style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px; display: block;">
            Selected topics appear on your profile. You may select above or type custom comma-separated topics.
          </span>
          <?php if (!empty($errors['interests'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['interests'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>

        <!-- Profile Visibility -->
        <div style="margin-bottom: 28px; padding: 18px 20px; border-radius: 14px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.7);">
          <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-heading); margin-bottom: 6px;">
            Profile Privacy & Directory Visibility
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
            Control whether your author profile appears in public directories and author search results.
          </p>

          <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <?php $vis = $_POST['profile_visibility'] ?? $user['profile_visibility'] ?? 'public'; ?>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.92rem; font-weight: 500;">
              <input type="radio" name="profile_visibility" value="public" <?= ($vis !== 'private') ? 'checked' : '' ?>>
              <span>🌐 Public (Visible to all readers)</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.92rem; font-weight: 500;">
              <input type="radio" name="profile_visibility" value="private" <?= ($vis === 'private') ? 'checked' : '' ?>>
              <span>🔒 Private (Hidden from public author lists)</span>
            </label>
          </div>
          <?php if (!empty($errors['profile_visibility'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['profile_visibility'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>

        <!-- Submit Button Row -->
        <div style="display: flex; justify-content: flex-end; gap: 14px; align-items: center; padding-top: 20px; border-top: 1px solid rgba(226, 232, 240, 0.7);">
          <a href="/profile" class="btn btn-secondary btn-pill">Cancel</a>
          <button type="submit" class="btn btn-primary btn-pill" style="min-width: 150px;">Save Profile Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Client-Side Live Image Preview & Category Picker Script (PART 5 & PART 6) -->
<script>
(function() {
  function initProfileEdit() {
  // -------------------------------------------------------------
  // Live Avatar Preview Flow
  // -------------------------------------------------------------
  const avatarInput = document.getElementById('avatar');
  const avatarPreviewImg = document.getElementById('avatar-preview-img');
  const avatarFallback = document.getElementById('avatar-fallback-initial');
  const avatarBadge = document.getElementById('avatar-preview-badge');
  const avatarActionsBar = document.getElementById('avatar-actions-bar');
  const avatarNameDisplay = document.getElementById('avatar-filename-display');
  const avatarCancelBtn = document.getElementById('avatar-cancel-btn');
  
  let currentAvatarObjectUrl = null;
  const originalAvatarSrc = avatarPreviewImg ? avatarPreviewImg.src : '';
  const originalAvatarHadSrc = <?= !empty($avatarSrc) ? 'true' : 'false' ?>;

  function resetAvatarPreview() {
    if (currentAvatarObjectUrl) {
      URL.revokeObjectURL(currentAvatarObjectUrl);
      currentAvatarObjectUrl = null;
    }
    if (avatarInput) avatarInput.value = '';
    if (avatarNameDisplay) avatarNameDisplay.textContent = 'No file selected';
    if (avatarBadge) avatarBadge.style.display = 'none';
    if (avatarActionsBar) avatarActionsBar.style.display = 'none';

    if (originalAvatarHadSrc && originalAvatarSrc) {
      if (avatarPreviewImg) {
        avatarPreviewImg.src = originalAvatarSrc;
        avatarPreviewImg.style.display = 'block';
        avatarPreviewImg.style.border = '3px solid rgba(255, 255, 255, 0.9)';
      }
      if (avatarFallback) avatarFallback.style.display = 'none';
    } else {
      if (avatarPreviewImg) avatarPreviewImg.style.display = 'none';
      if (avatarFallback) avatarFallback.style.display = 'flex';
    }
  }

  if (avatarInput) {
    avatarInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) {
        resetAvatarPreview();
        return;
      }

      // Validate 2MB limit
      if (file.size > 2 * 1024 * 1024) {
        alert('Avatar image exceeds 2MB limit. Please choose a smaller image.');
        resetAvatarPreview();
        return;
      }

      // Validate MIME type
      const allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
      if (!allowed.includes(file.type)) {
        alert('Only PNG, JPEG, WebP, or GIF image formats are supported.');
        resetAvatarPreview();
        return;
      }

      // Revoke previous URL if any
      if (currentAvatarObjectUrl) {
        URL.revokeObjectURL(currentAvatarObjectUrl);
      }

      currentAvatarObjectUrl = URL.createObjectURL(file);
      if (avatarPreviewImg) {
        avatarPreviewImg.src = currentAvatarObjectUrl;
        avatarPreviewImg.style.display = 'block';
        avatarPreviewImg.style.border = '3px solid #10b981';
      }
      if (avatarFallback) avatarFallback.style.display = 'none';
      if (avatarBadge) avatarBadge.style.display = 'block';
      if (avatarActionsBar) avatarActionsBar.style.display = 'flex';
      if (avatarNameDisplay) avatarNameDisplay.textContent = file.name;
    });
  }

  if (avatarCancelBtn) {
    avatarCancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      resetAvatarPreview();
    });
  }

  // -------------------------------------------------------------
  // Live Banner Preview Flow
  // -------------------------------------------------------------
  const bannerInput = document.getElementById('banner');
  const bannerPreviewImg = document.getElementById('banner-preview-img');
  const bannerFallbackText = document.getElementById('banner-fallback-text');
  const bannerBadge = document.getElementById('banner-preview-badge');
  const bannerActionsBar = document.getElementById('banner-actions-bar');
  const bannerNameDisplay = document.getElementById('banner-filename-display');
  const bannerCancelBtn = document.getElementById('banner-cancel-btn');

  let currentBannerObjectUrl = null;
  const originalBannerSrc = bannerPreviewImg ? bannerPreviewImg.src : '';
  const originalBannerHadSrc = <?= !empty($bannerSrc) ? 'true' : 'false' ?>;

  function resetBannerPreview() {
    if (currentBannerObjectUrl) {
      URL.revokeObjectURL(currentBannerObjectUrl);
      currentBannerObjectUrl = null;
    }
    if (bannerInput) bannerInput.value = '';
    if (bannerNameDisplay) bannerNameDisplay.textContent = 'No file selected';
    if (bannerBadge) bannerBadge.style.display = 'none';
    if (bannerActionsBar) bannerActionsBar.style.display = 'none';

    if (originalBannerHadSrc && originalBannerSrc) {
      if (bannerPreviewImg) {
        bannerPreviewImg.src = originalBannerSrc;
        bannerPreviewImg.style.display = 'block';
      }
      if (bannerFallbackText) bannerFallbackText.style.display = 'none';
    } else {
      if (bannerPreviewImg) bannerPreviewImg.style.display = 'none';
      if (bannerFallbackText) bannerFallbackText.style.display = 'block';
    }
  }

  if (bannerInput) {
    bannerInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (!file) {
        resetBannerPreview();
        return;
      }

      // Validate 4MB limit
      if (file.size > 4 * 1024 * 1024) {
        alert('Banner image exceeds 4MB limit. Please choose a smaller image.');
        resetBannerPreview();
        return;
      }

      const allowed = ['image/png', 'image/jpeg', 'image/webp'];
      if (!allowed.includes(file.type)) {
        alert('Only PNG, JPEG, or WebP images are permitted for banners.');
        resetBannerPreview();
        return;
      }

      if (currentBannerObjectUrl) {
        URL.revokeObjectURL(currentBannerObjectUrl);
      }

      currentBannerObjectUrl = URL.createObjectURL(file);
      if (bannerPreviewImg) {
        bannerPreviewImg.src = currentBannerObjectUrl;
        bannerPreviewImg.style.display = 'block';
      }
      if (bannerFallbackText) bannerFallbackText.style.display = 'none';
      if (bannerBadge) bannerBadge.style.display = 'block';
      if (bannerActionsBar) bannerActionsBar.style.display = 'flex';
      if (bannerNameDisplay) bannerNameDisplay.textContent = file.name;
    });
  }

  if (bannerCancelBtn) {
    bannerCancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      resetBannerPreview();
    });
  }

  // -------------------------------------------------------------
  // Category Chips & Interests Synchronization (PART 6)
  // -------------------------------------------------------------
  const interestsInput = document.getElementById('interests');
  const chipButtons = document.querySelectorAll('.category-select-chip');

  function updateChipStates() {
    if (!interestsInput) return;
    const currentList = interestsInput.value
      .split(',')
      .map(s => s.trim().toLowerCase())
      .filter(Boolean);

    chipButtons.forEach(chip => {
      const catName = chip.getAttribute('data-category-name') || '';
      const isSelected = currentList.includes(catName.toLowerCase());

      if (isSelected) {
        chip.classList.add('chip-selected', 'active');
        chip.style.borderColor = 'var(--primary-color)';
        chip.style.background = 'linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15))';
        chip.style.color = 'var(--primary-color)';
        chip.textContent = '✓ ' + catName;
      } else {
        chip.classList.remove('chip-selected', 'active');
        chip.style.borderColor = 'rgba(203, 213, 225, 0.8)';
        chip.style.background = 'rgba(255, 255, 255, 0.7)';
        chip.style.color = 'var(--text-body)';
        chip.textContent = '+ ' + catName;
      }
    });
  }

  chipButtons.forEach(chip => {
    chip.addEventListener('click', () => {
      if (!interestsInput) return;
      const catName = chip.getAttribute('data-category-name') || '';
      if (!catName) return;

      const rawItems = interestsInput.value.split(',').map(s => s.trim()).filter(Boolean);
      const index = rawItems.findIndex(item => item.toLowerCase() === catName.toLowerCase());

      if (index >= 0) {
        rawItems.splice(index, 1);
      } else {
        rawItems.push(catName);
      }

      interestsInput.value = rawItems.join(', ');
      updateChipStates();
    });
  });

  if (interestsInput) {
    interestsInput.addEventListener('input', updateChipStates);
  }

  // Initialize chip states on load
  updateChipStates();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfileEdit);
  } else {
    initProfileEdit();
  }
})();
</script>
