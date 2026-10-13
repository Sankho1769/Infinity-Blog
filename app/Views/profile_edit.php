<?php
declare(strict_types=1);
/**
 * @var array<string, mixed> $user
 * @var array<string, string> $errors
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 */
$errors = $errors ?? [];
?>
<div class="profile-edit-container" style="max-width: 860px; margin: 32px auto;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
      <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; letter-spacing: -0.02em;">
        Edit Your Profile
      </h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Update your personal details, public bio, and presence on Infinity Blog.
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
    <!-- Avatar Upload Card -->
    <div class="glass-card" style="padding: 28px 24px; border-radius: 20px;">
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
        Profile Avatar
      </h2>
      <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
        Upload a picture (PNG, JPEG, WebP, or GIF up to 2MB).
      </p>

      <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
        <div style="position: relative; flex-shrink: 0;">
          <?php $avatarSrc = $user['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($user['avatar_path'] ?? null); ?>
          <?php if (!empty($avatarSrc)): ?>
            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Current Avatar"
                 style="width: 88px; height: 88px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255, 255, 255, 0.8); box-shadow: 0 4px 14px rgba(0,0,0,0.08);"
                 onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
            <div style="display: none; width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.8); box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
              <?= strtoupper(substr($user['display_name'] ?? $user['username'] ?? 'C', 0, 1)) ?>
            </div>
          <?php else: ?>
            <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.8); box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
              <?= strtoupper(substr($user['display_name'] ?? $user['username'] ?? 'C', 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <form action="/profile/avatar" method="POST" enctype="multipart/form-data" style="flex: 1; min-width: 260px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="custom-file-upload-zone">
            <input type="file" name="avatar" id="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="custom-file-input-hidden" required onchange="document.getElementById('avatar-filename-display').textContent = this.files[0] ? this.files[0].name : 'No file selected';">
            <label for="avatar" class="btn btn-secondary btn-pill">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              Choose Image
            </label>
            <span id="avatar-filename-display" class="custom-file-name-display">No file selected</span>
            <button type="submit" class="btn btn-primary btn-pill">Upload Avatar</button>
          </div>
          <?php if (!empty($errors['avatar'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 6px;"><?= htmlspecialchars($errors['avatar'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Profile Banner Upload Card -->
    <div class="glass-card" style="padding: 28px 24px; border-radius: 20px;">
      <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">
        Profile Banner
      </h2>
      <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
        Upload a header banner image (JPEG, PNG, or WebP up to 4MB).
      </p>

      <?php $bannerSrc = $user['banner_url'] ?? ($user['banner_path'] ?? null); ?>
      <?php if (!empty($bannerSrc)): ?>
        <div style="margin-bottom: 16px; border-radius: 12px; overflow: hidden; height: 120px; max-width: 100%; border: 1px solid rgba(255, 255, 255, 0.6); box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
          <img src="<?= htmlspecialchars($bannerSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Current Banner" style="width: 100%; height: 100%; object-fit: cover;">
        </div>
      <?php endif; ?>

      <form action="/profile/banner" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="custom-file-upload-zone">
          <input type="file" name="banner" id="banner" accept="image/png,image/jpeg,image/webp" class="custom-file-input-hidden" required onchange="document.getElementById('banner-filename-display').textContent = this.files[0] ? this.files[0].name : 'No file selected';">
          <label for="banner" class="btn btn-secondary btn-pill">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Choose Banner
          </label>
          <span id="banner-filename-display" class="custom-file-name-display">No file selected</span>
          <button type="submit" class="btn btn-primary btn-pill">Upload Banner</button>
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

        <!-- Name & Username Row -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
          <div>
            <label for="display_name" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Full Name <span style="color: #dc2626;">*</span>
            </label>
            <input type="text" id="display_name" name="display_name" class="form-input <?= !empty($errors['display_name']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['display_name'] ?? $user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Cristine Palmer" required maxlength="190">
            <?php if (!empty($errors['display_name'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['display_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="username" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Username <span style="color: #dc2626;">*</span>
            </label>
            <input type="text" id="username" name="username" class="form-input <?= !empty($errors['username']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['username'] ?? $user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. cristine_palmer" required minlength="3" maxlength="50" pattern="^[a-zA-Z0-9_.-]+$">
            <?php if (!empty($errors['username'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Email & Phone Row -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
          <div>
            <label for="email" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Email Address <span style="color: #dc2626;">*</span>
            </label>
            <input type="email" id="email" name="email" class="form-input <?= !empty($errors['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. cristine.palmer@example.com" required maxlength="190">
            <?php if (!empty($errors['email'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="phone" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Mobile / Phone Number <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(🔒 Private & Encrypted)</span>
            </label>
            <?php if (!empty($user['mobile_number_masked'])): ?>
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 0.82rem; background: rgba(59, 130, 246, 0.08); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.2); flex-wrap: wrap;">
                <span style="font-weight: 600; color: var(--text-heading);">Current:</span>
                <span style="font-family: monospace; letter-spacing: 0.05em; font-weight: 700; color: var(--primary-color);"><?= htmlspecialchars($user['mobile_number_masked'], ENT_QUOTES, 'UTF-8') ?></span>
                <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; background: rgba(239, 68, 68, 0.1); color: #dc2626; font-weight: 600;">SMS BLOCKED (Provider unconfigured)</span>
              </div>
            <?php endif; ?>
            <input type="tel" id="phone" name="phone" class="form-input <?= !empty($errors['phone']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['phone'] ?? $user['mobile_number'] ?? $user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+91 98765 43210 or +1 555-0199" maxlength="30">
            <span style="display: block; font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
              Supports 10-digit Indian numbers (with/without +91) or international E.164 (+&lt;country&gt;&lt;digits&gt;). AES-256 encrypted. Leave blank to remove.
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

        <!-- Location & Website Row -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
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

        <!-- Interests -->
        <div style="margin-bottom: 22px;">
          <label for="interests" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
            Interests & Topics <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(Comma-separated, e.g. Web Development, Artificial Intelligence, Design Systems)</span>
          </label>
          <input type="text" id="interests" name="interests" class="form-input <?= !empty($errors['interests']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['interests'] ?? $user['interests'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Web Development, Artificial Intelligence, Design Systems" maxlength="255">
          <?php if (!empty($errors['interests'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['interests'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>

        <!-- Profile Visibility -->
        <div style="margin-bottom: 28px; padding: 18px 20px; border-radius: 14px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.7);">
          <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-heading); margin-bottom: 6px;">
            Profile Privacy & Visibility
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
            Control whether other visitors can find your author page in public directories.
          </p>

          <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <?php $vis = $_POST['profile_visibility'] ?? $user['profile_visibility'] ?? 'public'; ?>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.92rem; font-weight: 500;">
              <input type="radio" name="profile_visibility" value="public" <?= ($vis !== 'private') ? 'checked' : '' ?>>
              <span>🌐 Public (Visible to all readers)</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.92rem; font-weight: 500;">
              <input type="radio" name="profile_visibility" value="private" <?= ($vis === 'private') ? 'checked' : '' ?>>
              <span>🔒 Private (Hidden from author lists)</span>
            </label>
          </div>
          <?php if (!empty($errors['profile_visibility'])): ?>
            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['profile_visibility'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>

        <!-- Submit Button Row: Primary Blue and Glass Secondary -->
        <div style="display: flex; justify-content: flex-end; gap: 14px; align-items: center; padding-top: 20px; border-top: 1px solid rgba(226, 232, 240, 0.7);">
          <a href="/profile" class="btn btn-secondary btn-pill">Cancel</a>
          <button type="submit" class="btn btn-primary btn-pill" style="min-width: 150px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
