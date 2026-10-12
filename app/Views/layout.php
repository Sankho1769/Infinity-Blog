<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Ideas. Stories. Perspectives.', ENT_QUOTES, 'UTF-8') ?> — Infinity Blog</title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Infinity Blog — A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:site_name" content="Infinity Blog">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'Ideas. Stories. Perspectives.', ENT_QUOTES, 'UTF-8') ?> — Infinity Blog">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription ?? 'A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/favicon-apple.png">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Ambient glow backdrop for pastel claymorphism/glassmorphism -->
  <div class="ambient-glow" aria-hidden="true">
    <div class="ambient-blob-1"></div>
    <div class="ambient-blob-2"></div>
    <div class="ambient-blob-3"></div>
  </div>

  <!-- Sticky Glass Navigation Header -->
  <header class="site-header container container-wide">
    <nav class="nav-bar glass-panel" aria-label="Main Navigation">
      <a href="/" class="brand-logo" aria-label="Infinity Blog Home">
        <span class="brand-logo-icon">✦</span>
        <span class="brand-name-text">Infinity <span class="brand-text-accent">Blog</span></span>
      </a>

      <!-- Desktop Navigation Links -->
      <ul class="nav-links">
        <li><a href="/" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
        <li><a href="/categories" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">Categories</a></li>
        <li><a href="/authors" class="nav-link <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>">Authors</a></li>
        <li><a href="/explore" class="nav-link <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>">Explore</a></li>
        <?php if (!empty($currentUser)): ?>
          <li><a href="/dashboard" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
          <li><a href="/posts/manage" class="nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>">All Posts</a></li>
          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <li><a href="/admin/categories-page" class="nav-link <?= ($activeNav ?? '') === 'admin_categories' ? 'active' : '' ?>">Categories Admin</a></li>
            <li><a href="/admin/users-page" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">Users</a></li>
            <li><a href="/admin/audit-logs-page" class="nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>">Audit Logs</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <!-- Desktop Header Actions -->
      <div class="nav-actions">
        <!-- Search bar -->
        <form action="/" method="GET" class="nav-search-form" role="search">
          <svg class="nav-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="search" name="search" class="nav-search-input" placeholder="Search articles..." value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles">
        </form>

        <?php if (!empty($currentUser)): ?>
          <!-- Logged in User Menu -->
          <a href="/posts/new" class="btn btn-primary btn-pill btn-sm nav-create-btn">+ Create Post</a>
          <a href="/profile" class="user-menu-pill user-profile-link" title="My Profile" style="text-decoration: none;">
            <?php if (!empty($currentUser['avatar_path'])): ?>
              <img src="<?= htmlspecialchars($currentUser['avatar_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
              <div class="user-avatar" title="<?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?>">
                <?= strtoupper(substr($currentUser['display_name'] ?? $currentUser['username'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <span class="user-display-name"><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
              <span class="user-role-badge admin">Admin</span>
            <?php endif; ?>
          </a>
          <a href="/logout" class="btn btn-secondary btn-sm btn-pill" title="Sign out" style="padding: 4px 10px; margin-left: 2px;">Logout</a>
        <?php else: ?>
          <!-- Guest Navigation Buttons: Sign In | Create Account -->
          <a href="/login" class="btn btn-secondary btn-pill btn-sm" style="padding: 7px 15px; white-space: nowrap;">Sign In</a>
          <a href="/register" class="btn btn-primary btn-pill btn-sm" style="padding: 7px 15px; white-space: nowrap;">Create Account</a>
        <?php endif; ?>

        <!-- Mobile Navigation Toggle Button -->
        <button type="button" class="mobile-nav-toggle btn btn-secondary btn-sm" id="mobile-nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
      </div>
    </nav>

    <!-- Mobile Nav Drawer (Collapsible) -->
    <div id="mobile-nav-drawer" class="mobile-nav-drawer glass-panel" style="display: none;">
      <ul class="mobile-nav-list">
        <li><a href="/" class="mobile-nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
        <li><a href="/categories" class="mobile-nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">Categories</a></li>
        <li><a href="/authors" class="mobile-nav-link <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>">Authors</a></li>
        <li><a href="/explore" class="mobile-nav-link <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>">Explore</a></li>
        <?php if (!empty($currentUser)): ?>
          <li><a href="/profile" class="mobile-nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>">My Profile</a></li>
          <li><a href="/dashboard" class="mobile-nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
          <li><a href="/posts/manage" class="mobile-nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>">All Posts</a></li>
          <li><a href="/posts/new" class="mobile-nav-link">Create Post</a></li>
          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <li><a href="/admin/categories-page" class="mobile-nav-link">Categories Admin</a></li>
            <li><a href="/admin/users-page" class="mobile-nav-link">Users</a></li>
            <li><a href="/admin/audit-logs-page" class="mobile-nav-link">Audit Logs</a></li>
          <?php endif; ?>
          <li style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(226, 232, 240, 0.7); display: flex; justify-content: space-between; align-items: center;">
            <a href="/profile" style="text-decoration: none; font-size: 0.85rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?><?= (($currentUser['role'] ?? '') === 'admin') ? ' (Admin)' : '' ?></a>
            <a href="/logout" class="btn btn-secondary btn-sm btn-pill">Sign Out</a>
          </li>
        <?php else: ?>
          <li style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(226, 232, 240, 0.7); display: flex; flex-direction: column; gap: 8px;">
            <a href="/login" class="btn btn-secondary btn-pill btn-full">Sign In</a>
            <a href="/register" class="btn btn-primary btn-pill btn-full">Create Account</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </header>

  <!-- Flash Messages Container -->
  <div class="container flash-messages">
    <?php if (!empty($_GET['logged_out'])): ?>
      <div class="alert-banner alert-success alert-dismissible" role="alert">
        <span>You have been signed out.</span>
        <button type="button" class="alert-close-btn" aria-label="Close notification" onclick="this.closest('.alert-banner').remove()">×</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
      <div class="alert-banner alert-success alert-dismissible" role="alert">
        <span>Post was permanently deleted.</span>
        <button type="button" class="alert-close-btn" aria-label="Close notification" onclick="this.closest('.alert-banner').remove()">×</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['created'])): ?>
      <div class="alert-banner alert-success alert-dismissible" role="alert">
        <span>Post created successfully!</span>
        <button type="button" class="alert-close-btn" aria-label="Close notification" onclick="this.closest('.alert-banner').remove()">×</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?>
      <div class="alert-banner alert-success alert-dismissible" role="alert">
        <span>Post updated successfully!</span>
        <button type="button" class="alert-close-btn" aria-label="Close notification" onclick="this.closest('.alert-banner').remove()">×</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['published'])): ?>
      <div class="alert-banner alert-success alert-dismissible" role="alert">
        <span>Post published successfully!</span>
        <button type="button" class="alert-close-btn" aria-label="Close notification" onclick="this.closest('.alert-banner').remove()">×</button>
      </div>
    <?php endif; ?>
  </div>

  <!-- Main View Slot -->
  <main class="container">
    <?= $content ?? '' ?>
  </main>

  <!-- Footer with Clean Multi-Column Layout -->
  <footer class="container site-footer glass-panel" style="margin-top: 60px; margin-bottom: 24px; padding: 36px 32px; border-radius: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 28px; margin-bottom: 24px;">
      <div style="max-width: 320px;">
        <a href="/" class="brand-logo" aria-label="Infinity Blog Home" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 10px;">
          <span class="brand-logo-icon">✦</span>
          <span class="brand-name-text">Infinity <span class="brand-text-accent">Blog</span></span>
        </a>
        <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5;">
          A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.
        </p>
      </div>

      <div style="display: flex; gap: 40px; flex-wrap: wrap;">
        <div>
          <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-heading); margin-bottom: 12px; letter-spacing: 0.05em;">Explore</div>
          <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <li><a href="/" style="color: var(--text-muted); text-decoration: none;">Home</a></li>
            <li><a href="/explore" style="color: var(--text-muted); text-decoration: none;">Articles</a></li>
            <li><a href="/categories" style="color: var(--text-muted); text-decoration: none;">Categories</a></li>
            <li><a href="/authors" style="color: var(--text-muted); text-decoration: none;">Authors</a></li>
          </ul>
        </div>

        <div>
          <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-heading); margin-bottom: 12px; letter-spacing: 0.05em;">Platform</div>
          <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <?php if (!empty($currentUser)): ?>
              <li><a href="/profile" style="color: var(--text-muted); text-decoration: none;">My Profile</a></li>
              <li><a href="/dashboard" style="color: var(--text-muted); text-decoration: none;">Dashboard</a></li>
              <li><a href="/posts/new" style="color: var(--text-muted); text-decoration: none;">Write Post</a></li>
            <?php else: ?>
              <li><a href="/login" style="color: var(--text-muted); text-decoration: none;">Sign In</a></li>
              <li><a href="/register" style="color: var(--text-muted); text-decoration: none;">Create Account</a></li>
            <?php endif; ?>
            <li><a href="/contact" style="color: var(--text-muted); text-decoration: none;">Contact Us</a></li>
            <li><a href="/privacy" style="color: var(--text-muted); text-decoration: none;">Privacy Policy</a></li>
          </ul>
        </div>

        <div>
          <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-heading); margin-bottom: 12px; letter-spacing: 0.05em;">Connect</div>
          <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem; color: var(--text-muted);">
            <a href="mailto:support@infinityblog.local" style="color: var(--primary-600, #4f46e5); text-decoration: none;">support@infinityblog.local</a>
            <a href="tel:+18005550199" style="color: var(--primary-600, #4f46e5); text-decoration: none;">+1 (800) 555-0199</a>
          </div>
        </div>
      </div>
    </div>

    <div style="border-top: 1px solid rgba(226, 232, 240, 0.6); padding-top: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.84rem; color: var(--text-muted);">
      <div>© <?= date('Y') ?> Infinity Blog. All rights reserved.</div>
      <div>Crafted with PHP, PDO & MySQL.</div>
    </div>
  </footer>

  <!-- Global Delete Confirmation Modal Dialog -->
  <div id="delete-confirm-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-card">
      <h3 id="modal-title" class="modal-title">Confirm Deletion</h3>
      <p class="modal-desc">
        Are you sure you want to permanently delete <strong id="delete-modal-post-title">this post</strong>?
        This action is irreversible and cannot be restored.
      </p>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        <button type="button" id="modal-confirm-delete-btn" class="btn btn-danger">Delete Post</button>
      </div>
    </div>
  </div>

  <script src="/assets/js/app.js"></script>
</body>
</html>
