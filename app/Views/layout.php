<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Infinity Blog', ENT_QUOTES, 'UTF-8') ?> — BlogCMS</title>
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
      <a href="/" class="brand-logo">
        <span class="brand-logo-icon">✦</span>
        <span>Blog<span class="brand-text-accent">CMS</span></span>
      </a>

      <ul class="nav-links">
        <li><a href="/" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
        <?php if (!empty($currentUser)): ?>
          <li><a href="/dashboard" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
          <li><a href="/posts/manage" class="nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>">All Posts</a></li>
          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <li><a href="/admin/categories-page" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">Categories</a></li>
            <li><a href="/admin/users-page" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">Users</a></li>
            <li><a href="/admin/audit-logs-page" class="nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>">Audit Logs</a></li>
          <?php endif; ?>
        <?php else: ?>
          <li><a href="/#articles" class="nav-link">Articles</a></li>
        <?php endif; ?>
      </ul>

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
          <!-- Logged in Author / Admin Menu -->
          <div class="user-menu-pill">
            <div class="user-avatar"><?= strtoupper(substr($currentUser['display_name'] ?? $currentUser['username'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="user-role-badge <?= $currentUser['role'] === 'admin' ? 'admin' : '' ?>"><?= htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="/logout" class="btn btn-secondary btn-sm btn-pill" title="Sign out" style="padding: 4px 10px; margin-left: 4px;">Logout</a>
          </div>
        <?php else: ?>
          <!-- Guest Sign In Button -->
          <a href="/login" class="btn btn-primary btn-pill">Sign In</a>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <!-- Flash Messages Container -->
  <div class="container flash-messages">
    <?php if (!empty($_GET['deleted'])): ?>
      <div class="alert-banner alert-success">Post was permanently deleted.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['created'])): ?>
      <div class="alert-banner alert-success">Post created successfully!</div>
    <?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?>
      <div class="alert-banner alert-success">Post updated successfully!</div>
    <?php endif; ?>
    <?php if (!empty($_GET['published'])): ?>
      <div class="alert-banner alert-success">Post published successfully!</div>
    <?php endif; ?>
    <?php if (!empty($_GET['logged_out'])): ?>
      <div class="alert-banner alert-success">You have been signed out.</div>
    <?php endif; ?>
  </div>

  <!-- Main View Slot -->
  <main class="container">
    <?= $content ?? '' ?>
  </main>

  <!-- Footer -->
  <footer class="container" style="margin-top: 80px; padding-bottom: 40px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
    <p>© <?= date('Y') ?> Infinity Blog CMS — Powered by PHP & MySQL.</p>
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
