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
<div class="profile-edit-container" style="max-width: 840px; margin: 32px auto;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; letter-spacing: -0.02em;">
        Edit Your Profile
      </h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Update your personal details, public bio, and presence on Infinity Blog.
      </p>
    </div>
    <a href="/profile" class="btn btn-secondary btn-pill">Back to Profile</a>
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
        <div style="position: relative;">
          <?php $avatarSrc = $user['avatar_url'] ?? ($user['avatar_path'] ?? null); ?>
          <?php if (!empty($avatarSrc)): ?>
            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Current Avatar" style="width: 88px; height: 88px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255, 255, 255, 0.8); box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
          <?php else: ?>
            <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; border: 3px solid rgba(255, 255, 255, 0.8); box-shadow: 0 4px 14px rgba(0,0,0,0.08);">
              <?= strtoupper(substr($user['display_name'] ?? $user['username'], 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <form action="/profile/avatar" method="POST" enctype="multipart/form-data" style="flex: 1; min-width: 240px;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <input type="file" name="avatar" id="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="form-input" style="padding: 8px 12px; flex: 1; min-width: 200px;" required>
            <button type="submit" class="btn btn-secondary btn-pill">Upload Avatar</button>
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
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <input type="file" name="banner" id="banner" accept="image/png,image/jpeg,image/webp" class="form-input" style="padding: 8px 12px; flex: 1; min-width: 200px;" required>
          <button type="submit" class="btn btn-secondary btn-pill">Upload Banner</button>
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
            <input type="text" id="display_name" name="display_name" class="form-input <?= !empty($errors['display_name']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['display_name'] ?? $user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="190">
            <?php if (!empty($errors['display_name'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['display_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="username" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Username <span style="color: #dc2626;">*</span>
            </label>
            <input type="text" id="username" name="username" class="form-input <?= !empty($errors['username']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['username'] ?? $user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required minlength="3" maxlength="50" pattern="^[a-zA-Z0-9_.-]+$">
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
            <input type="email" id="email" name="email" class="form-input <?= !empty($errors['email']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="190">
            <?php if (!empty($errors['email'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="phone" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Phone Number <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(Private, optional)</span>
            </label>
            <input type="tel" id="phone" name="phone" class="form-input <?= !empty($errors['phone']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="+1 555-0199" maxlength="30">
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
            <input type="text" id="location" name="location" class="form-input <?= !empty($errors['location']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['location'] ?? $user['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="San Francisco, CA" maxlength="120">
            <?php if (!empty($errors['location'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['location'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <div>
            <label for="website" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
              Personal Website / Portfolio
            </label>
            <input type="url" id="website" name="website" class="form-input <?= !empty($errors['website']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['website'] ?? $user['website'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://example.com" maxlength="255">
            <?php if (!empty($errors['website'])): ?>
              <p style="color: #dc2626; font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($errors['website'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Interests -->
        <div style="margin-bottom: 22px;">
          <label for="interests" class="form-label" style="font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; display: block;">
            Interests & Topics <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">(Comma-separated, e.g. PHP, Machine Learning, Design Systems)</span>
          </label>
          <input type="text" id="interests" name="interests" class="form-input <?= !empty($errors['interests']) ? 'input-error' : '' ?>" value="<?= htmlspecialchars($_POST['interests'] ?? $user['interests'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Web Development, Artificial Intelligence, Design" maxlength="255">
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

        <!-- Submit Button Row -->
        <div style="display: flex; justify-content: flex-end; gap: 14px; align-items: center;">
          <a href="/profile" class="btn btn-secondary btn-pill">Cancel</a>
          <button type="submit" class="btn btn-primary btn-pill" style="min-width: 140px;">Save Profile</button>
        </div>
      </form>
    </div>
  </div>
</div>
