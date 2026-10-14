<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitleFormatted ?? ($pageTitle ?? 'Ideas. Stories. Perspectives. — Infinity Blog'), ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescriptionFormatted ?? ($metaDescription ?? 'Infinity Blog — A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.'), ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:site_name" content="Infinity Blog">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitleFormatted ?? ($pageTitle ?? 'Ideas. Stories. Perspectives. — Infinity Blog'), ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescriptionFormatted ?? ($metaDescription ?? 'A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.'), ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="<?= htmlspecialchars($ogType ?? 'website', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl ?? (($baseUrl ?? '') . ($canonicalPath ?? ($_SERVER['REQUEST_URI'] ?? '/'))), ENT_QUOTES, 'UTF-8') ?>">
  <?php if (!empty($ogImageAbsolute)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImageAbsolute, ENT_QUOTES, 'UTF-8') ?>">
  <?php elseif (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitleFormatted ?? ($pageTitle ?? 'Ideas. Stories. Perspectives. — Infinity Blog'), ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDescriptionFormatted ?? ($metaDescription ?? 'A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.'), ENT_QUOTES, 'UTF-8') ?>">
  <?php if (!empty($ogImageAbsolute)): ?>
    <meta property="twitter:image" content="<?= htmlspecialchars($ogImageAbsolute, ENT_QUOTES, 'UTF-8') ?>">
  <?php elseif (!empty($ogImage)): ?>
    <meta property="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? (($baseUrl ?? '') . ($canonicalPath ?? ($_SERVER['REQUEST_URI'] ?? '/'))), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/favicon-apple.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta name="theme-color" content="#3b82f6">
  <?php if (!empty($gscVerificationCode)): ?>
    <meta name="google-site-verification" content="<?= htmlspecialchars($gscVerificationCode, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json"><?= $jsonLd ?></script>
  <?php endif; ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <?php
    $cssFile = dirname(__DIR__, 2) . '/public/assets/css/style.css';
    $cssVersion = file_exists($cssFile) ? (string) filemtime($cssFile) : '20261013';
  ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $cssVersion ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Ambient glow backdrop for pastel claymorphism/glassmorphism -->
  <div class="ambient-glow" aria-hidden="true">
    <div class="ambient-blob-1"></div>
    <div class="ambient-blob-2"></div>
    <div class="ambient-blob-3"></div>
  </div>

  <!-- Sticky Glass Navigation Header -->
  <header class="site-header container container-wide<?= !empty($currentUser) ? ' is-authenticated' : ' is-guest' ?>">
    <nav class="nav-bar glass-panel" aria-label="Main Navigation">
      <!-- Left Section: Brand Logo & Title -->
      <div class="nav-section-left">
        <a href="/" class="brand-logo" aria-label="Infinity Blog Home">
          <span class="brand-logo-icon">✦</span>
          <span class="brand-name-text">Infinity <span class="brand-text-accent">Blog</span></span>
        </a>
      </div>

      <!-- Middle Section: Navigation Links -->
      <div class="nav-section-middle">
        <ul class="nav-links">
          <li><a href="/" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
          <li><a href="/categories" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">Categories</a></li>
          <li><a href="/authors" class="nav-link <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>">Authors</a></li>
          <li><a href="/explore" class="nav-link <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>">Explore</a></li>
          <li class="nav-item-collapsible"><a href="/chat" class="nav-link <?= ($activeNav ?? '') === 'chat' ? 'active' : '' ?>">Community Chat</a></li>
          <li class="nav-item-collapsible"><a href="/messages" class="nav-link <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>">Messages <span id="nav-unread-badge" class="badge-unread-dot" style="display: none;"></span></a></li>
          <?php if (!empty($currentUser)): ?>
            <!-- More / Workspace Dropdown -->
            <li class="nav-dropdown" id="more-nav-dropdown">
              <button type="button" class="nav-link nav-dropdown-btn <?= in_array($activeNav ?? '', ['library', 'following', 'dashboard', 'posts', 'chat', 'messages']) ? 'active' : '' ?>" id="more-nav-dropdown-btn" aria-haspopup="true" aria-expanded="false" title="More destinations">
                More <span class="nav-dropdown-caret" aria-hidden="true">▾</span>
              </button>
              <div class="nav-dropdown-menu glass-panel" id="more-nav-dropdown-menu" role="menu" aria-label="More Navigation">
                <a href="/chat" class="nav-dropdown-link nav-overflow-link <?= ($activeNav ?? '') === 'chat' ? 'active' : '' ?>" role="menuitem">
                  <span class="dropdown-item-icon">💬</span> Community Chat
                </a>
                <a href="/messages" class="nav-dropdown-link nav-overflow-link <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>" role="menuitem">
                  <span class="dropdown-item-icon">✉️</span> Messages <span id="more-unread-badge" class="badge-unread-dot" style="display: none; margin-left: 6px;"></span>
                </a>
                <a href="/library" class="nav-dropdown-link <?= ($activeNav ?? '') === 'library' ? 'active' : '' ?>" role="menuitem">
                  <span class="dropdown-item-icon">📚</span> Library
                </a>
                <a href="/following" class="nav-dropdown-link <?= ($activeNav ?? '') === 'following' ? 'active' : '' ?>" role="menuitem">
                  <span class="dropdown-item-icon">👥</span> Following Feed
                </a>
                <a href="/dashboard" class="nav-dropdown-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" role="menuitem">
                  <span class="dropdown-item-icon">📊</span> Dashboard
                </a>
                <a href="/posts/manage" class="nav-dropdown-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>" role="menuitem">
                  <span class="dropdown-item-icon">📝</span> All Posts
                </a>
              </div>
            </li>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
              <li class="nav-dropdown" id="admin-nav-dropdown">
                <button type="button" class="nav-link nav-dropdown-btn <?= in_array($activeNav ?? '', ['admin_categories', 'users', 'audit', 'contact_messages', 'reported_posts']) ? 'active' : '' ?>" id="admin-nav-dropdown-btn" aria-haspopup="true" aria-expanded="false" title="Administration">
                  Admin <span class="nav-dropdown-caret" aria-hidden="true">▾</span>
                </button>
                <div class="nav-dropdown-menu glass-panel" id="admin-nav-dropdown-menu" role="menu" aria-label="Admin Navigation">
                  <a href="/admin/reported-posts-page" class="nav-dropdown-link <?= ($activeNav ?? '') === 'reported_posts' ? 'active' : '' ?>" role="menuitem">
                    <span class="dropdown-item-icon">🚩</span> Reported Posts
                  </a>
                  <a href="/admin/categories-page" class="nav-dropdown-link <?= ($activeNav ?? '') === 'admin_categories' ? 'active' : '' ?>" role="menuitem">
                    <span class="dropdown-item-icon">📁</span> Categories Admin
                  </a>
                  <a href="/admin/users-page" class="nav-dropdown-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>" role="menuitem">
                    <span class="dropdown-item-icon">👤</span> Users Management
                  </a>
                  <a href="/admin/audit-logs-page" class="nav-dropdown-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>" role="menuitem">
                    <span class="dropdown-item-icon">🛡️</span> Audit Logs
                  </a>
                  <a href="/admin/contact-messages-page" class="nav-dropdown-link <?= ($activeNav ?? '') === 'contact_messages' ? 'active' : '' ?>" role="menuitem">
                    <span class="dropdown-item-icon">✉️</span> Contact Inquiries
                  </a>
                </div>
              </li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Right Section: Desktop Header Actions -->
      <div class="nav-actions nav-section-right">
        <!-- Search bar -->
        <form action="/" method="GET" class="nav-search-form" role="search">
          <svg class="nav-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="search" name="search" class="nav-search-input" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles">
        </form>

        <?php if (!empty($currentUser)): ?>
          <?php
            $navAvatarUrl = $currentUser['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($currentUser['avatar_path'] ?? null);
            $navDisplayName = $currentUser['display_name'] ?? $currentUser['username'] ?? 'User';
            $navInitial = strtoupper(mb_substr($navDisplayName, 0, 1));
          ?>
          <!-- Logged in User Menu -->
          <a href="/posts/new" class="btn btn-primary btn-pill btn-sm nav-create-btn" title="Create a new article">+ Create Post</a>

          <!-- Notification Bell Menu -->
          <div class="nav-notif-container" id="nav-notif-container">
            <button type="button" class="nav-notif-btn" id="nav-notif-btn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" title="Notifications">
              <svg class="nav-notif-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <span class="nav-notif-badge" id="nav-notif-badge" style="display: none;">0</span>
            </button>
            <div class="nav-notif-dropdown glass-panel" id="nav-notif-dropdown" role="menu" aria-label="Notifications Dropdown" style="display: none;">
              <div class="nav-notif-header">
                <span class="nav-notif-header-title">Notifications</span>
                <button type="button" class="nav-notif-mark-all" id="nav-notif-mark-all">Mark all read</button>
              </div>
              <div class="nav-notif-list" id="nav-notif-list">
                <div class="nav-notif-empty">No notifications yet</div>
              </div>
            </div>
          </div>

          <a href="/profile" class="user-menu-pill user-profile-link" title="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?> — My Profile" aria-label="View user profile for <?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>">
            <?php if (!empty($navAvatarUrl)): ?>
              <img src="<?= htmlspecialchars($navAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                   alt="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>"
                   class="user-avatar-img"
                   style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                   onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
              <div class="user-avatar nav-avatar-fallback" title="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>" style="display: none; width: 28px; height: 28px; border-radius: 50%; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0;">
                <?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php else: ?>
              <div class="user-avatar" title="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>" style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0;">
                <?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
            <span class="user-display-name"><?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
              <span class="user-role-badge admin">Admin</span>
            <?php endif; ?>
          </a>
          <a href="/logout" class="btn btn-secondary btn-sm btn-pill nav-logout-btn" title="Sign out of Infinity Blog">Logout</a>
        <?php else: ?>
          <!-- Guest Navigation Buttons: Sign In (Primary Blue) | Create Account (Secondary Glass) -->
          <a href="/login" class="btn btn-primary btn-pill btn-sm" style="padding: 7px 16px; white-space: nowrap;">Sign In</a>
          <a href="/register" class="btn btn-secondary btn-pill btn-sm" style="padding: 7px 16px; white-space: nowrap;">Create Account</a>
        <?php endif; ?>

        <!-- Mobile Navigation Toggle Button -->
        <button type="button" class="mobile-nav-toggle btn btn-secondary btn-sm" id="mobile-nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-nav-drawer">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
      </div>
    </nav>

    <!-- Mobile Nav Drawer (Collapsible) -->
    <div id="mobile-nav-drawer" class="mobile-nav-drawer glass-panel" role="dialog" aria-modal="true" aria-label="Mobile Navigation" style="display: none;">
      <!-- Mobile Search Form -->
      <form action="/" method="GET" class="mobile-search-form" role="search">
        <svg class="mobile-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="search" name="search" class="mobile-search-input" placeholder="Search articles..." value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles">
      </form>

      <!-- Section: Discover -->
      <div class="mobile-nav-section">
        <div class="mobile-nav-section-title">Discover</div>
        <ul class="mobile-nav-list">
          <li><a href="/" class="mobile-nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>"><span class="mobile-link-icon">🏠</span> Home</a></li>
          <li><a href="/categories" class="mobile-nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>"><span class="mobile-link-icon">📁</span> Categories</a></li>
          <li><a href="/authors" class="mobile-nav-link <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>"><span class="mobile-link-icon">✍️</span> Authors</a></li>
          <li><a href="/explore" class="mobile-nav-link <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>"><span class="mobile-link-icon">🧭</span> Explore</a></li>
          <li><a href="/chat" class="mobile-nav-link <?= ($activeNav ?? '') === 'chat' ? 'active' : '' ?>"><span class="mobile-link-icon">💬</span> Community Chat</a></li>
          <li><a href="/messages" class="mobile-nav-link <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>"><span class="mobile-link-icon">✉️</span> Messages <span id="mobile-unread-badge" class="badge-unread-dot" style="display: none;"></span></a></li>
          <?php if (!empty($currentUser)): ?>
            <li><a href="#notifications" class="mobile-nav-link" id="mobile-drawer-notif-link"><span class="mobile-link-icon">🔔</span> Notifications <span id="mobile-drawer-notif-badge" class="badge-unread-count" style="display: none;">0</span></a></li>
          <?php endif; ?>
        </ul>
      </div>

      <?php if (!empty($currentUser)): ?>
        <!-- Section: My Workspace -->
        <div class="mobile-nav-section">
          <div class="mobile-nav-section-title">My Workspace</div>
          <ul class="mobile-nav-list">
            <li><a href="/posts/new" class="mobile-nav-link mobile-create-link"><span class="mobile-link-icon">✏️</span> + Create New Post</a></li>
            <li><a href="/library" class="mobile-nav-link <?= ($activeNav ?? '') === 'library' ? 'active' : '' ?>"><span class="mobile-link-icon">📚</span> My Library</a></li>
            <li><a href="/following" class="mobile-nav-link <?= ($activeNav ?? '') === 'following' ? 'active' : '' ?>"><span class="mobile-link-icon">👥</span> Following Feed</a></li>
            <li><a href="/dashboard" class="mobile-nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><span class="mobile-link-icon">📊</span> Dashboard</a></li>
            <li><a href="/posts/manage" class="mobile-nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>"><span class="mobile-link-icon">📝</span> All Posts</a></li>
          </ul>
        </div>

        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <!-- Section: Administration -->
          <div class="mobile-nav-section">
            <div class="mobile-nav-section-title">Administration</div>
            <ul class="mobile-nav-list">
              <li><a href="/admin/reported-posts-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'reported_posts' ? 'active' : '' ?>"><span class="mobile-link-icon">🚩</span> Reported Posts</a></li>
              <li><a href="/admin/categories-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'admin_categories' ? 'active' : '' ?>"><span class="mobile-link-icon">📁</span> Categories Admin</a></li>
              <li><a href="/admin/users-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"><span class="mobile-link-icon">👤</span> Users Management</a></li>
              <li><a href="/admin/audit-logs-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>"><span class="mobile-link-icon">🛡️</span> Audit Logs</a></li>
              <li><a href="/admin/contact-messages-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'contact_messages' ? 'active' : '' ?>"><span class="mobile-link-icon">✉️</span> Contact Inquiries</a></li>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Section: Account -->
        <div class="mobile-nav-section mobile-account-section">
          <div class="mobile-user-card">
            <a href="/profile" class="mobile-user-info" aria-label="View user profile">
              <?php if (!empty($navAvatarUrl)): ?>
                <img src="<?= htmlspecialchars($navAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>"
                     class="user-avatar-img"
                     style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                     onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
                <div class="user-avatar" title="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>" style="display: none; width: 28px; height: 28px; font-size: 0.8rem; flex-shrink: 0;">
                  <?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php else: ?>
                <div class="user-avatar" title="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>" style="width: 28px; height: 28px; font-size: 0.8rem; flex-shrink: 0;">
                  <?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>
              <div class="mobile-user-meta">
                <span class="mobile-user-name"><?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                  <span class="user-role-badge admin">Admin</span>
                <?php endif; ?>
              </div>
            </a>
            <a href="/logout" class="btn btn-secondary btn-sm btn-pill">Sign Out</a>
          </div>
        </div>
      <?php else: ?>
        <!-- Section: Guest Account -->
        <div class="mobile-nav-section mobile-account-section">
          <div class="mobile-nav-section-title">Account</div>
          <div class="mobile-guest-actions">
            <a href="/login" class="btn btn-primary btn-pill btn-full">Sign In</a>
            <a href="/register" class="btn btn-secondary btn-pill btn-full">Create Account</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <!-- Flash Messages Container -->
  <div class="container container-wide flash-messages">
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
  <main class="container container-wide">
    <?= $content ?? '' ?>
  </main>

  <!-- Footer with Clean Multi-Column Layout -->
  <footer class="site-footer-wrapper container container-wide">
    <div class="site-footer glass-panel">
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
              <li><a href="/about" style="color: var(--text-muted); text-decoration: none;">About</a></li>
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
              <li><a href="/about" style="color: var(--text-muted); text-decoration: none;">About Platform</a></li>
              <li><a href="/contact" style="color: var(--text-muted); text-decoration: none;">Contact Us</a></li>
              <li><a href="/privacy" style="color: var(--text-muted); text-decoration: none;">Privacy Policy</a></li>
              <li><a href="/terms" style="color: var(--text-muted); text-decoration: none;">Terms &amp; Conditions</a></li>
              <li><button type="button" class="footer-link-btn" onclick="openCookiePreferences()" style="background: none; border: none; padding: 0; color: var(--text-muted); font-size: inherit; font-family: inherit; cursor: pointer; text-align: left;">Cookie Preferences</button></li>
            </ul>
          </div>

          <div>
            <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-heading); margin-bottom: 12px; letter-spacing: 0.05em;">Connect</div>
            <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem; color: var(--text-muted);">
              <?php if (!empty($supportEmail)): ?>
                <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" style="color: var(--primary-600, #4f46e5); text-decoration: none;"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
              <?php endif; ?>
              <?php if (!empty($editorialEmail)): ?>
                <a href="mailto:<?= htmlspecialchars($editorialEmail, ENT_QUOTES, 'UTF-8') ?>" style="color: var(--primary-600, #4f46e5); text-decoration: none;"><?= htmlspecialchars($editorialEmail, ENT_QUOTES, 'UTF-8') ?></a>
              <?php endif; ?>
              <a href="/contact" style="color: var(--primary-600, #4f46e5); text-decoration: none; font-weight: 600;">Support &amp; Feedback Form →</a>
            </div>
          </div>
        </div>
      </div>

      <div style="border-top: 1px solid rgba(226, 232, 240, 0.6); padding-top: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.84rem; color: var(--text-muted);">
        <div>© <?= date('Y') ?> Infinity Blog. All rights reserved.</div>
        <div>Crafted with PHP, PDO &amp; MySQL.</div>
      </div>
    </div>
  </footer>

  <!-- Mobile Bottom Navigation (Claymorphism on Mobile) -->
  <nav class="mobile-bottom-nav" aria-label="Mobile Quick Navigation">
    <div class="mobile-bottom-nav-inner">
      <a href="/" class="mobile-bottom-nav-item <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>" aria-label="Home">
        <span class="mobile-bottom-nav-icon">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
        </span>
        <span class="mobile-bottom-nav-label">Home</span>
      </a>

      <a href="/explore" class="mobile-bottom-nav-item <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>" aria-label="Explore">
        <span class="mobile-bottom-nav-icon">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </span>
        <span class="mobile-bottom-nav-label">Explore</span>
      </a>

      <a href="<?= !empty($currentUser) ? '/posts/new' : '/login?redirect=' . urlencode('/posts/new') ?>" class="mobile-bottom-nav-item mobile-bottom-center-action" aria-label="Create Post">
        <span class="mobile-bottom-center-btn" title="Create New Post">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
        </span>
      </a>

      <a href="/categories" class="mobile-bottom-nav-item <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>" aria-label="Topics">
        <span class="mobile-bottom-nav-icon">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
            <line x1="7" y1="7" x2="7.01" y2="7"></line>
          </svg>
        </span>
        <span class="mobile-bottom-nav-label">Topics</span>
      </a>

      <?php if (!empty($currentUser)): ?>
        <a href="/profile" class="mobile-bottom-nav-item <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>" aria-label="Profile">
          <span class="mobile-bottom-nav-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>
          <span class="mobile-bottom-nav-label">Profile</span>
        </a>
      <?php else: ?>
        <a href="/login" class="mobile-bottom-nav-item <?= in_array($activeNav ?? '', ['login', 'register']) ? 'active' : '' ?>" aria-label="Sign In">
          <span class="mobile-bottom-nav-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>
          <span class="mobile-bottom-nav-label">Sign In</span>
        </a>
      <?php endif; ?>
    </div>
  </nav>

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

  <!-- Cookie Consent Banner -->
  <div id="cookie-consent-banner" class="cookie-banner glass-panel" role="region" aria-label="Cookie consent banner" style="display: none;">
    <div class="cookie-banner-content">
      <div class="cookie-banner-icon" aria-hidden="true">🍪</div>
      <div class="cookie-banner-text">
        <h4 class="cookie-banner-title">We value your privacy</h4>
        <p class="cookie-banner-desc">
          We use essential cookies for account security and session maintenance. Optional analytics cookies help us measure performance and improve your reading experience. Read our <a href="/privacy" class="link-inline">Privacy Policy</a> to learn more.
        </p>
      </div>
    </div>
    <div class="cookie-banner-actions">
      <button type="button" class="btn btn-secondary btn-sm btn-pill" id="cookie-btn-reject">Reject Non-Essential</button>
      <button type="button" class="btn btn-outline btn-sm btn-pill" id="cookie-btn-preferences">Preferences</button>
      <button type="button" class="btn btn-primary btn-sm btn-pill" id="cookie-btn-accept">Accept All</button>
    </div>
  </div>

  <!-- Cookie Preferences Modal -->
  <div id="cookie-preferences-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cookie-pref-title" style="display: none;">
    <div class="modal-card" style="max-width: 540px; text-align: left;">
      <h3 id="cookie-pref-title" class="modal-title" style="margin-bottom: 8px;">Cookie &amp; Privacy Preferences</h3>
      <p class="modal-desc" style="margin-bottom: 20px;">
        Choose which cookies Infinity Blog may store. Essential cookies are required to authenticate and safeguard your account.
      </p>

      <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">
        <!-- Strictly Necessary -->
        <div style="background: rgba(255, 255, 255, 0.65); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; gap: 14px;">
          <div>
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-heading);">Strictly Necessary Cookies</div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">Enables authentication, CSRF tokens, and security. Cannot be disabled.</div>
          </div>
          <div>
            <span class="badge" style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.15); color: #2563eb; font-weight: 700; padding: 4px 10px; border-radius: 999px;">Required</span>
          </div>
        </div>

        <!-- Performance & Analytics -->
        <div style="background: rgba(255, 255, 255, 0.65); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; gap: 14px;">
          <div>
            <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-heading);">Performance &amp; Analytics</div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;">Measures page views and reading trends anonymously. Scripts execute only with your consent.</div>
          </div>
          <div>
            <input type="checkbox" id="cookie-pref-analytics" style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--primary-color);">
          </div>
        </div>
      </div>

      <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeCookiePreferences()">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm btn-pill" id="cookie-pref-save-btn">Save Preferences</button>
      </div>
    </div>
  </div>

  <?php
    $jsFile = dirname(__DIR__, 2) . '/public/assets/js/app.js';
    $jsVersion = file_exists($jsFile) ? (string) filemtime($jsFile) : '20261013';
  ?>
  <script src="/assets/js/app.js?v=<?= $jsVersion ?>"></script>

  <!-- Cookie Consent & Privacy Gatekeeper Script -->
  <script>
    (function() {
      const GA_ID = <?= !empty($gaMeasurementId) ? json_encode($gaMeasurementId) : 'null' ?>;
      const CONSENT_KEY = 'infinity_blog_cookie_consent';

      function loadAnalytics() {
        if (!GA_ID || window.__ga_loaded) return;
        window.__ga_loaded = true;
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA_ID);
        document.head.appendChild(script);
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        window.gtag = gtag;
        gtag('js', new Date());
        gtag('config', GA_ID, { 'anonymize_ip': true });
      }

      function getConsent() {
        try {
          const raw = localStorage.getItem(CONSENT_KEY);
          return raw ? JSON.parse(raw) : null;
        } catch (e) {
          return null;
        }
      }

      function saveConsent(analyticsAllowed) {
        const consent = {
          essential: true,
          analytics: Boolean(analyticsAllowed),
          timestamp: new Date().toISOString()
        };
        try {
          localStorage.setItem(CONSENT_KEY, JSON.stringify(consent));
        } catch (e) {}

        const banner = document.getElementById('cookie-consent-banner');
        if (banner) banner.style.display = 'none';

        if (consent.analytics) {
          loadAnalytics();
        }
      }

      window.openCookiePreferences = function() {
        const consent = getConsent();
        const checkbox = document.getElementById('cookie-pref-analytics');
        if (checkbox) {
          checkbox.checked = consent ? Boolean(consent.analytics) : false;
        }
        const modal = document.getElementById('cookie-preferences-modal');
        if (modal) modal.style.display = 'flex';
      };

      window.closeCookiePreferences = function() {
        const modal = document.getElementById('cookie-preferences-modal');
        if (modal) modal.style.display = 'none';
      };

      // Check current consent on page load
      const current = getConsent();
      if (!current) {
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) banner.style.display = 'flex';
      } else if (current.analytics) {
        loadAnalytics();
      }

      document.getElementById('cookie-btn-accept')?.addEventListener('click', function() {
        saveConsent(true);
      });

      document.getElementById('cookie-btn-reject')?.addEventListener('click', function() {
        saveConsent(false);
      });

      document.getElementById('cookie-btn-preferences')?.addEventListener('click', function() {
        window.openCookiePreferences();
      });

      document.getElementById('cookie-pref-save-btn')?.addEventListener('click', function() {
        const checkbox = document.getElementById('cookie-pref-analytics');
        saveConsent(checkbox ? checkbox.checked : false);
        window.closeCookiePreferences();
      });
    })();
  </script>
</body>
</html>
