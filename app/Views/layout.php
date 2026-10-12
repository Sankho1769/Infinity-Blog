<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Ideas. Stories. Perspectives.', ENT_QUOTES, 'UTF-8') ?> — Infinity Blog</title>
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
          <div class="user-menu-pill">
            <div class="user-avatar" title="<?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?>">
              <?= strtoupper(substr($currentUser['display_name'] ?? $currentUser['username'], 0, 1)) ?>
            </div>
            <span class="user-display-name"><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
              <span class="user-role-badge admin">Admin</span>
            <?php endif; ?>
            <a href="/logout" class="btn btn-secondary btn-sm btn-pill" title="Sign out" style="padding: 4px 10px; margin-left: 4px;">Logout</a>
          </div>
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
          <li><a href="/dashboard" class="mobile-nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
          <li><a href="/posts/manage" class="mobile-nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>">All Posts</a></li>
          <li><a href="/posts/new" class="mobile-nav-link">Create Post</a></li>
          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <li><a href="/admin/categories-page" class="mobile-nav-link">Categories Admin</a></li>
            <li><a href="/admin/users-page" class="mobile-nav-link">Users</a></li>
            <li><a href="/admin/audit-logs-page" class="mobile-nav-link">Audit Logs</a></li>
          <?php endif; ?>
          <li style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(226, 232, 240, 0.7); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?><?= (($currentUser['role'] ?? '') === 'admin') ? ' (Admin)' : '' ?></span>
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

  <!-- Footer with Asset Background -->
  <footer class="container site-footer">
    <p>© <?= date('Y') ?> Infinity Blog — Powered by PHP & MySQL. Ideas. Stories. Perspectives.</p>
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
