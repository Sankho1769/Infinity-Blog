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
  <?php else: ?>
    <meta property="og:image" content="<?= htmlspecialchars(($baseUrl ?? '') . '/assets/images/infinity-blog-logo.png', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="twitter:image" content="<?= htmlspecialchars(($baseUrl ?? '') . '/assets/images/infinity-blog-logo.png', ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? (($baseUrl ?? '') . ($canonicalPath ?? ($_SERVER['REQUEST_URI'] ?? '/'))), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicon-apple.png">
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
  <script>
    (function() {
      try {
        var pref = localStorage.getItem('ib_theme_preference') || 'light';
        if (pref === 'device') {
          pref = 'light';
          try { localStorage.setItem('ib_theme_preference', 'light'); } catch (e) {}
        }
        var theme = (pref === 'dark') ? 'dark' : 'light';
        pref = theme;
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-preference', pref);
        if (theme === 'dark') {
          document.documentElement.classList.add('theme-dark');
        } else {
          document.documentElement.classList.remove('theme-dark');
        }
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-theme-preference', 'light');
        document.documentElement.classList.remove('theme-dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $cssVersion ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&display=swap" rel="stylesheet">
</head>
<body class="<?= ($activeNav ?? '') === 'home' ? 'is-home-page' : '' ?> <?= !empty($isStudioPage) || ($activeNav ?? '') === 'posts_studio' ? 'is-studio-page' : '' ?> <?= in_array($activeNav ?? '', ['messages', 'chat'], true) ? 'is-chat-page' : '' ?>" data-user-logged-in="<?= !empty($currentUser) ? '1' : '0' ?>">
  <!-- Ambient glow backdrop for pastel claymorphism/glassmorphism -->
  <div class="ambient-glow" aria-hidden="true">
    <div class="ambient-blob-1"></div>
    <div class="ambient-blob-2"></div>
    <div class="ambient-blob-3"></div>
  </div>

  <!-- Compact Horizontal Top Header (Desktop Glassmorphism & Mobile Claymorphism) -->
  <header class="app-top-header glass-panel" id="app-top-header" role="banner">
    <div class="top-header-inner">
      <!-- Left: Logo & Wordmark -->
      <div class="top-header-left">
        <?= \InfinityBlog\Support\BrandHelper::render('responsive', [
            'link' => true,
            'height' => 38,
            'class' => 'header-brand-logo',
            'aria_label' => 'Infinity Blog Home'
        ]) ?>
      </div>

      <!-- Center: Global Search Bar -->
      <div class="top-header-center">
        <form action="/" method="GET" class="header-search-form" role="search" id="header-search-form">
          <div class="header-search-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>
          <input type="search"
                 name="search"
                 id="header-search-input"
                 class="header-search-input"
                 placeholder="Search posts, people, communities…"
                 value="<?= htmlspecialchars($_GET['search'] ?? ($searchQuery ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                 aria-label="Search posts, people, communities"
                 autocomplete="off">
          <button type="button"
                  class="header-search-clear"
                  id="header-search-clear"
                  aria-label="Clear search"
                  style="<?= empty($_GET['search']) && empty($searchQuery) ? 'display: none;' : '' ?>">×</button>
        </form>
      </div>

      <!-- Right: Shortcuts & Account Controls -->
      <div class="top-header-right">
        <!-- Home Shortcut -->
        <a href="/" class="header-nav-icon-btn <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>" title="Home" aria-label="Home">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        </a>

        <!-- Authors / People Shortcut -->
        <a href="/authors" class="header-nav-icon-btn <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>" title="People &amp; Authors" aria-label="People and Authors">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </a>

        <!-- Direct Messages Shortcut with Live Unread Badge -->
        <a href="/messages" class="header-nav-icon-btn header-msg-btn <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>" title="Messages" aria-label="Direct Messages" style="position: relative;">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
          <span id="nav-unread-badge" class="badge-unread-dot" style="display: none;"></span>
        </a>

        <!-- Notifications Center Button & Dropdown -->
        <div class="header-notif-container" style="position: relative;">
          <button type="button" class="header-nav-icon-btn header-notif-btn" id="nav-notif-btn" title="Open Notifications" aria-label="Notifications" aria-expanded="false">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span id="nav-notif-badge" class="badge-unread-count" style="display: none;">0</span>
          </button>
          <div class="nav-notif-dropdown glass-panel header-notif-dropdown" id="nav-notif-dropdown" role="menu" aria-label="Notifications Dropdown" style="display: none;">
            <div class="nav-notif-header">
              <span class="nav-notif-header-title">Notifications</span>
              <button type="button" class="nav-notif-mark-all" id="nav-notif-mark-all">Mark all read</button>
            </div>
            <div class="nav-notif-list" id="nav-notif-list">
              <div class="nav-notif-empty">No notifications yet</div>
            </div>
          </div>
        </div>

        <!-- Theme Mode Direct Toggle Button -->
        <div class="header-theme-container">
          <button type="button"
                  class="header-nav-icon-btn header-theme-btn"
                  id="header-theme-toggle-btn"
                  title="Switch to Dark Mode"
                  aria-label="Switch to Dark Mode">
            <span class="theme-btn-icon" id="header-theme-current-icon" aria-hidden="true"><svg class="theme-svg-sun" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg></span>
          </button>
        </div>

        <!-- Account Controls: Dropdown or Guest CTAs -->
        <?php if (!empty($currentUser)): ?>
          <?php
            $hdrAvatarUrl = $currentUser['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($currentUser['avatar_path'] ?? null);
            $hdrDisplayName = $currentUser['display_name'] ?? $currentUser['username'] ?? 'User';
            $hdrInitial = strtoupper(mb_substr($hdrDisplayName, 0, 1));
          ?>
          <div class="header-user-menu-wrapper" style="position: relative;">
            <button type="button" class="header-user-avatar-btn" id="header-user-menu-btn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
              <?php if (!empty($hdrAvatarUrl)): ?>
                <img src="<?= htmlspecialchars($hdrAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($hdrDisplayName, ENT_QUOTES, 'UTF-8') ?>" class="header-avatar-img" onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                <div class="header-avatar-fallback" style="display: none;"><?= htmlspecialchars($hdrInitial, ENT_QUOTES, 'UTF-8') ?></div>
              <?php else: ?>
                <div class="header-avatar-fallback"><?= htmlspecialchars($hdrInitial, ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </button>
            <div class="header-user-dropdown-panel glass-panel" id="header-user-dropdown" role="menu" aria-label="User Account Menu" style="display: none;">
              <div class="header-user-dropdown-header">
                <div class="header-user-name-row">
                  <span class="header-user-display-name"><?= htmlspecialchars($hdrDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <span class="user-role-badge admin">Admin</span>
                  <?php endif; ?>
                </div>
                <div class="header-user-handle"><?= htmlspecialchars($currentUser['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($currentUser['username'] ?? null, $hdrDisplayName), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="header-dropdown-sep"></div>
              <a href="/profile" class="header-dropdown-link"><span class="hdr-item-icon">👤</span> My Profile</a>
              <a href="/posts/manage" class="header-dropdown-link"><span class="hdr-item-icon">📄</span> My Articles</a>
              <a href="/ebooks" class="header-dropdown-link"><span class="hdr-item-icon">📚</span> eBook Library</a>
              <a href="/ebooks/manage" class="header-dropdown-link"><span class="hdr-item-icon">📖</span> My Uploaded eBooks</a>
              <a href="/library" class="header-dropdown-link"><span class="hdr-item-icon">🔖</span> Reading Library</a>
              <a href="/posts/new" class="header-dropdown-link"><span class="hdr-item-icon">✍️</span> Create Post</a>
              <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                <div class="header-dropdown-sep"></div>
                <a href="/admin/reported-posts-page" class="header-dropdown-link"><span class="hdr-item-icon">🚩</span> Reported Posts</a>
                <a href="/admin/categories-page" class="header-dropdown-link"><span class="hdr-item-icon">📁</span> Categories Admin</a>
                <a href="/admin/users-page" class="header-dropdown-link"><span class="hdr-item-icon">👥</span> Users Management</a>
                <a href="/admin/audit-logs-page" class="header-dropdown-link"><span class="hdr-item-icon">🛡️</span> Audit Logs</a>
              <?php endif; ?>
              <div class="header-dropdown-sep"></div>
              <div class="header-user-theme-section">
                <div class="header-theme-label">Appearance</div>
                <div class="theme-segmented-group" role="radiogroup" aria-label="Theme Appearance">
                  <button type="button" class="theme-segmented-btn active" data-theme-value="light" role="radio" aria-checked="true" title="Light Theme">
                    <span class="theme-seg-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg></span> Light
                  </button>
                  <button type="button" class="theme-segmented-btn" data-theme-value="dark" role="radio" aria-checked="false" title="Dark Theme">
                    <span class="theme-seg-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg></span> Dark
                  </button>
                </div>
              </div>
              <div class="header-dropdown-sep"></div>
              <a href="/logout" class="header-dropdown-link text-danger"><span class="hdr-item-icon">🚪</span> Sign Out</a>
            </div>
          </div>
        <?php else: ?>
          <div class="header-auth-buttons">
            <a href="/login" class="btn btn-secondary btn-pill btn-sm">Sign In</a>
            <a href="/register" class="btn btn-primary btn-pill btn-sm">Register</a>
          </div>
        <?php endif; ?>

        <!-- Mobile Drawer Toggle Button -->
        <button type="button" class="mobile-nav-toggle header-mobile-toggle" id="mobile-nav-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-nav-drawer">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- App Layout Shell: Left Sidebar (Desktop) + Main Content Area -->
  <div class="app-layout-shell" id="app-layout-shell">
    <!-- Desktop Vertical Navigation Sidebar (~250px wide) -->
    <aside class="app-left-sidebar glass-panel" id="app-left-sidebar" aria-label="Main Navigation">
      <!-- Scrollable Navigation Groups -->
      <nav class="sidebar-scrollable-content" aria-label="Sidebar Menu">
        <!-- Group 1: Discover -->
        <div class="sidebar-nav-group">
          <div class="sidebar-group-title">Discover</div>
          <ul class="sidebar-nav-list">
            <li>
              <a href="/" class="nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </span>
                <span class="sidebar-link-text">Home</span>
              </a>
            </li>
            <li>
              <a href="/explore" class="nav-link <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                </span>
                <span class="sidebar-link-text">Explore Stories</span>
              </a>
            </li>
            <li>
              <a href="/categories" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"></path></svg>
                </span>
                <span class="sidebar-link-text">Categories</span>
              </a>
            </li>
            <li>
              <a href="/authors" class="nav-link <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                <span class="sidebar-link-text">Authors</span>
              </a>
            </li>
            <li>
              <a href="/communities" class="nav-link <?= ($activeNav ?? '') === 'communities' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" x2="22" y1="12" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                </span>
                <span class="sidebar-link-text">Discover Communities</span>
              </a>
            </li>
            <li>
              <a href="/ebooks" class="nav-link <?= ($activeNav ?? '') === 'ebooks' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path><path d="M6 6h10"></path><path d="M6 10h10"></path></svg>
                </span>
                <span class="sidebar-link-text">eBook Library</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Group 2: Community -->
        <div class="sidebar-nav-group">
          <div class="sidebar-group-title">Community</div>
          <ul class="sidebar-nav-list">
            <li>
              <a href="/chat" class="nav-link <?= ($activeNav ?? '') === 'chat' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </span>
                <span class="sidebar-link-text">Community Chat</span>
              </a>
            </li>
            <li>
              <a href="/messages" class="nav-link <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                </span>
                <span class="sidebar-link-text">Messages</span>
                <span id="nav-unread-badge" class="badge-unread-dot" style="display: none; margin-left: auto;"></span>
              </a>
            </li>
            <li style="position: relative;">
              <button type="button" class="nav-link sidebar-notif-btn" id="sidebar-notif-btn" title="Open Notifications">
                <span class="sidebar-link-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </span>
                <span class="sidebar-link-text">Notifications</span>
                <span id="sidebar-notif-badge" class="badge-unread-count" style="display: none; margin-left: auto;">0</span>
              </button>
              <div class="nav-notif-dropdown glass-panel sidebar-notif-dropdown" id="nav-notif-dropdown" role="menu" aria-label="Notifications Dropdown" style="display: none;">
                <div class="nav-notif-header">
                  <span class="nav-notif-header-title">Notifications</span>
                  <button type="button" class="nav-notif-mark-all" id="nav-notif-mark-all">Mark all read</button>
                </div>
                <div class="nav-notif-list" id="nav-notif-list">
                  <div class="nav-notif-empty">No notifications yet</div>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <?php if (!empty($currentUser)): ?>
          <div class="sidebar-divider"></div>

          <!-- Group 3: Your Space (More / Workspace) -->
          <div class="sidebar-nav-group nav-dropdown" id="more-nav-dropdown">
            <div class="sidebar-group-title">
              <button type="button" class="sidebar-group-btn nav-link nav-dropdown-btn <?= in_array($activeNav ?? '', ['library', 'following', 'dashboard', 'posts', 'profile', 'post_editor']) ? 'active' : '' ?>" id="more-nav-dropdown-btn" aria-haspopup="true" aria-expanded="true">
                Your Space
              </button>
            </div>
            <ul class="sidebar-nav-list" id="more-nav-dropdown-menu">
              <li>
                <a href="/profile" class="nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>">
                  <span class="sidebar-link-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                  </span>
                  <span class="sidebar-link-text">My Profile</span>
                </a>
              </li>
              <li>
                <a href="/posts/manage" class="nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>">
                  <span class="sidebar-link-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" x2="8" y1="13" y2="13"></line><line x1="16" x2="8" y1="17" y2="17"></line><line x1="10" x2="8" y1="9" y2="9"></line></svg>
                  </span>
                  <span class="sidebar-link-text">My Articles</span>
                </a>
              </li>
              <li>
                <a href="/library" class="nav-link <?= ($activeNav ?? '') === 'library' ? 'active' : '' ?>">
                  <span class="sidebar-link-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 6 4 14"></path><path d="M12 6v14"></path><path d="M8 8v12"></path><path d="M4 4v16"></path></svg>
                  </span>
                  <span class="sidebar-link-text">Reading Library</span>
                </a>
              </li>
              <li>
                <a href="/posts/new" class="nav-link sidebar-create-highlight nav-create-btn <?= ($activeNav ?? '') === 'post_editor' ? 'active' : '' ?>">
                  <span class="sidebar-link-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="16"></line><line x1="8" x2="16" y1="12" y2="12"></line></svg>
                  </span>
                  <span class="sidebar-link-text">+ Create Post</span>
                </a>
              </li>
            </ul>
          </div>

          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
            <div class="sidebar-divider"></div>

            <!-- Group 4: Administration -->
            <div class="sidebar-nav-group nav-dropdown" id="admin-nav-dropdown">
              <div class="sidebar-group-title">
                <button type="button" class="sidebar-group-btn nav-link nav-dropdown-btn <?= in_array($activeNav ?? '', ['admin_categories', 'users', 'audit', 'contact_messages', 'reported_posts', 'reported_ebooks', 'ebook_moderation']) ? 'active' : '' ?>" id="admin-nav-dropdown-btn" aria-haspopup="true" aria-expanded="true">
                  Administration
                </button>
              </div>
              <ul class="sidebar-nav-list" id="admin-nav-dropdown-menu">
                <li><a href="/admin/ebook-moderation" class="nav-link <?= ($activeNav ?? '') === 'ebook_moderation' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">📖</span> <span class="sidebar-link-text">eBook Moderation</span></a></li>
                <li><a href="/admin/reported-posts-page" class="nav-link <?= ($activeNav ?? '') === 'reported_posts' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">🚩</span> <span class="sidebar-link-text">Reported Posts</span></a></li>
                <li><a href="/admin/reported-ebooks-page" class="nav-link <?= ($activeNav ?? '') === 'reported_ebooks' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">📚</span> <span class="sidebar-link-text">Reported eBooks</span></a></li>
                <li><a href="/admin/categories-page" class="nav-link <?= ($activeNav ?? '') === 'admin_categories' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">📁</span> <span class="sidebar-link-text">Categories Admin</span></a></li>
                <li><a href="/admin/users-page" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">👤</span> <span class="sidebar-link-text">Users Management</span></a></li>
                <li><a href="/admin/audit-logs-page" class="nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">🛡️</span> <span class="sidebar-link-text">Audit Logs</span></a></li>
                <li><a href="/admin/contact-messages-page" class="nav-link <?= ($activeNav ?? '') === 'contact_messages' ? 'active' : '' ?>"><span class="sidebar-link-icon" aria-hidden="true">✉️</span> <span class="sidebar-link-text">Contact Inquiries</span></a></li>
              </ul>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </nav>

      <!-- Sidebar Bottom Area: Account Controls / Guest CTAs -->
      <div class="sidebar-bottom-account">
        <?php if (!empty($currentUser)): ?>
          <?php
            $navAvatarUrl = $currentUser['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($currentUser['avatar_path'] ?? null);
            $navDisplayName = $currentUser['display_name'] ?? $currentUser['username'] ?? 'User';
            $navInitial = strtoupper(mb_substr($navDisplayName, 0, 1));
          ?>
          <div class="sidebar-user-pill user-menu-pill">
            <a href="/profile" class="sidebar-user-info" title="View profile for <?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>">
              <?php if (!empty($navAvatarUrl)): ?>
                <img src="<?= htmlspecialchars($navAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?>"
                     class="sidebar-user-avatar"
                     onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
                <div class="sidebar-user-avatar sidebar-avatar-fallback" style="display: none;">
                  <?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php else: ?>
                <div class="sidebar-user-avatar sidebar-avatar-fallback">
                  <?= htmlspecialchars($navInitial, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>
              <div class="sidebar-user-meta">
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span class="sidebar-user-name"><?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <span class="user-role-badge admin">Admin</span>
                  <?php endif; ?>
                </div>
                <span class="sidebar-user-handle"><?= htmlspecialchars($currentUser['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($currentUser['username'] ?? null, $navDisplayName), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </a>
            <a href="/logout" class="sidebar-logout-btn" title="Sign out / Logout" aria-label="Sign Out">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
            </a>
          </div>
        <?php else: ?>
          <div class="sidebar-guest-actions">
            <a href="/login" class="btn btn-primary btn-pill btn-sm btn-full">Sign In</a>
            <a href="/register" class="btn btn-secondary btn-pill btn-sm btn-full" style="margin-top: 6px;">Create Account</a>
          </div>
        <?php endif; ?>
      </div>
    </aside>

    <!-- Main Content Wrapper: Page Body + Footer (Top bar removed) -->
    <div class="app-main-wrapper" id="app-main-wrapper">


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
  </div><!-- /#app-main-wrapper -->
</div><!-- /#app-layout-shell -->

  <!-- Page-Level Full-Width Footer -->
  <footer class="site-footer-wrapper" id="site-footer-wrapper">
    <div class="site-footer glass-panel">
      <div class="footer-top-grid">
        <!-- Column 1: Brand & Concise Description -->
        <div class="footer-brand-col">
          <?= \InfinityBlog\Support\BrandHelper::render('horizontal', [
              'link' => true,
              'height' => 38,
              'class' => 'footer-brand-logo',
              'aria_label' => 'Infinity Blog Home'
          ]) ?>
          <p class="footer-tagline">
            A community for sharing ideas, stories, and knowledge.
          </p>
        </div>

        <!-- Column 2: Explore -->
        <div class="footer-nav-col">
          <div class="footer-col-heading">Explore</div>
          <ul class="footer-link-list">
            <li><a href="/explore" class="footer-nav-link">Explore Stories</a></li>
            <li><a href="/categories" class="footer-nav-link">Categories</a></li>
            <li><a href="/authors" class="footer-nav-link">Authors</a></li>
            <li><a href="/communities" class="footer-nav-link">Discover Communities</a></li>
            <li><a href="/ebooks" class="footer-nav-link">eBook Library</a></li>
          </ul>
        </div>

        <!-- Column 3: Platform -->
        <div class="footer-nav-col">
          <div class="footer-col-heading">Platform</div>
          <ul class="footer-link-list">
            <li><a href="/about" class="footer-nav-link">About</a></li>
            <li><a href="/contact" class="footer-nav-link">Contact</a></li>
            <li><a href="/privacy" class="footer-nav-link">Privacy Policy</a></li>
            <li><a href="/terms" class="footer-nav-link">Terms of Service</a></li>
          </ul>
        </div>

        <!-- Column 4: Support & Legal -->
        <div class="footer-nav-col">
          <div class="footer-col-heading">Support &amp; Legal</div>
          <ul class="footer-link-list">
            <?php if (!empty($supportEmail)): ?>
              <li><a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="footer-nav-link"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endif; ?>
            <li><a href="/contact" class="footer-nav-link">Support &amp; Feedback Form</a></li>
            <li><button type="button" class="footer-link-btn" onclick="openCookiePreferences()">Cookie Preferences</button></li>
          </ul>
        </div>
      </div>

      <!-- Bottom Row: Subtle Divider & Copyright -->
      <div class="footer-bottom-row">
        <div class="footer-copyright">
          © <?= date('Y') ?> Infinity Blog. All rights reserved.
        </div>
        <div class="footer-badges">
          <span>Crafted for modern editorial communities</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- Off-Canvas Mobile Navigation Backdrop Overlay -->
  <div id="mobile-nav-backdrop" class="mobile-nav-backdrop" aria-hidden="true"></div>

  <!-- Accessible Slide-Out Mobile Navigation Drawer (Anchored to Left Edge) -->
  <div id="mobile-nav-drawer" class="mobile-nav-drawer" role="dialog" aria-modal="true" aria-label="Mobile Navigation" aria-hidden="true">
    <div class="mobile-drawer-header">
      <?= \InfinityBlog\Support\BrandHelper::render('horizontal', [
          'link' => true,
          'height' => 32,
          'class' => 'mobile-drawer-brand',
          'aria_label' => 'Infinity Blog Home'
      ]) ?>
      <button type="button" class="mobile-drawer-close-btn" id="mobile-nav-close-btn" aria-label="Close navigation menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>

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
        <li>
          <a href="/" class="mobile-nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>
            <span>Home</span>
          </a>
        </li>
        <li>
          <a href="/explore" class="mobile-nav-link <?= ($activeNav ?? '') === 'explore' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg></span>
            <span>Explore Stories</span>
          </a>
        </li>
        <li>
          <a href="/categories" class="mobile-nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></span>
            <span>Categories</span>
          </a>
        </li>
        <li>
          <a href="/authors" class="mobile-nav-link <?= ($activeNav ?? '') === 'authors' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
            <span>Authors</span>
          </a>
        </li>
        <li>
          <a href="/communities" class="mobile-nav-link <?= ($activeNav ?? '') === 'communities' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></span>
            <span>Discover Communities</span>
          </a>
        </li>
        <li>
          <a href="/ebooks" class="mobile-nav-link <?= ($activeNav ?? '') === 'ebooks' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path><path d="M6 6h10"></path><path d="M6 10h10"></path></svg></span>
            <span>eBook Library</span>
          </a>
        </li>
        <li>
          <a href="/chat" class="mobile-nav-link <?= ($activeNav ?? '') === 'chat' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></span>
            <span>Community Chat</span>
          </a>
        </li>
        <li>
          <a href="/messages" class="mobile-nav-link <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>">
            <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg></span>
            <span>Messages</span>
            <span id="mobile-unread-badge" class="badge-unread-dot" style="display: none; margin-left: auto;"></span>
          </a>
        </li>
        <?php if (!empty($currentUser)): ?>
          <li>
            <a href="#notifications" class="mobile-nav-link" id="mobile-drawer-notif-link">
              <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></span>
              <span>Notifications</span>
              <span id="mobile-drawer-notif-badge" class="badge-unread-count" style="display: none; margin-left: auto;">0</span>
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Section: Appearance Theme Selector -->
    <div class="mobile-nav-section mobile-theme-section">
      <div class="mobile-nav-section-title">Appearance</div>
      <div class="theme-segmented-group mobile-theme-group" role="radiogroup" aria-label="Theme Appearance">
        <button type="button" class="theme-segmented-btn active" data-theme-value="light" role="radio" aria-checked="true" title="Light Theme">
          <span class="theme-seg-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg></span> Light
        </button>
        <button type="button" class="theme-segmented-btn" data-theme-value="dark" role="radio" aria-checked="false" title="Dark Theme">
          <span class="theme-seg-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg></span> Dark
        </button>
      </div>
    </div>

    <?php if (!empty($currentUser)): ?>
      <?php
        $navAvatarUrl = $navAvatarUrl ?? ($currentUser['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($currentUser['avatar_path'] ?? null));
        $navDisplayName = $navDisplayName ?? ($currentUser['display_name'] ?? $currentUser['username'] ?? 'User');
        $navInitial = $navInitial ?? strtoupper(mb_substr($navDisplayName, 0, 1));
      ?>
      <!-- Section: My Workspace -->
      <div class="mobile-nav-section">
        <div class="mobile-nav-section-title">My Workspace</div>
        <ul class="mobile-nav-list">
          <li>
            <a href="/posts/new" class="mobile-nav-link mobile-create-link">
              <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg></span>
              <span>+ Create New Post</span>
            </a>
          </li>
          <li>
            <a href="/posts/manage" class="mobile-nav-link <?= ($activeNav ?? '') === 'posts' ? 'active' : '' ?>">
              <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" x2="8" y1="13" y2="13"></line><line x1="16" x2="8" y1="17" y2="17"></line><line x1="10" x2="8" y1="9" y2="9"></line></svg></span>
              <span>My Articles</span>
            </a>
          </li>
          <li>
            <a href="/library" class="mobile-nav-link <?= ($activeNav ?? '') === 'library' ? 'active' : '' ?>">
              <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 6 4 14"></path><path d="M12 6v14"></path><path d="M8 8v12"></path><path d="M4 4v16"></path></svg></span>
              <span>My Library</span>
            </a>
          </li>
          <li>
            <a href="/following" class="mobile-nav-link <?= ($activeNav ?? '') === 'following' ? 'active' : '' ?>">
              <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
              <span>Following Feed</span>
            </a>
          </li>
          <li>
            <a href="/dashboard" class="mobile-nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
              <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></span>
              <span>Dashboard</span>
            </a>
          </li>
        </ul>
      </div>

      <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
        <!-- Section: Administration -->
        <div class="mobile-nav-section">
          <div class="mobile-nav-section-title">Administration</div>
          <ul class="mobile-nav-list">
            <li>
              <a href="/admin/reported-posts-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'reported_posts' ? 'active' : '' ?>">
                <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></span>
                <span>Reported Posts</span>
              </a>
            </li>
            <li>
              <a href="/admin/categories-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'admin_categories' ? 'active' : '' ?>">
                <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></span>
                <span>Categories Admin</span>
              </a>
            </li>
            <li>
              <a href="/admin/users-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">
                <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
                <span>Users Management</span>
              </a>
            </li>
            <li>
              <a href="/admin/audit-logs-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>">
                <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></span>
                <span>Audit Logs</span>
              </a>
            </li>
            <li>
              <a href="/admin/contact-messages-page" class="mobile-nav-link <?= ($activeNav ?? '') === 'contact_messages' ? 'active' : '' ?>">
                <span class="mobile-link-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg></span>
                <span>Contact Inquiries</span>
              </a>
            </li>
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
              <div style="display: flex; align-items: center; gap: 6px;">
                <span class="mobile-user-name"><?= htmlspecialchars($navDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                  <span class="user-role-badge admin">Admin</span>
                <?php endif; ?>
              </div>
              <span class="mobile-user-handle" style="font-size: 0.78rem; color: var(--text-muted); display: block;"><?= htmlspecialchars($currentUser['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($currentUser['username'] ?? null, $navDisplayName), ENT_QUOTES, 'UTF-8') ?></span>
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

  <!-- Global Share Article Modal -->
  <div class="modal-backdrop" id="share-article-modal" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle" style="display: none;">
    <div class="modal-card share-modal-card" style="max-width: 540px; width: 92%; max-height: 90vh; overflow-y: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
        <h3 id="shareModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
          <span>🔗</span> Share this Story
        </h3>
        <button type="button" class="alert-close-btn" onclick="closeShareModal()" style="font-size: 1.4rem;" aria-label="Close dialog">×</button>
      </div>

      <!-- Story Preview Snippet in Dialog -->
      <div class="share-dialog-story-preview" id="shareDialogStoryPreview" style="padding: 12px 14px; background: rgba(241, 245, 249, 0.7); border-radius: 12px; margin-bottom: 16px; border: 1px solid rgba(226, 232, 240, 0.8);">
        <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 2px;">Sharing Article</div>
        <div id="shareStoryTitle" style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark); line-height: 1.35;">Story</div>
        <div id="shareStoryAuthor" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 3px;">Infinity Blog</div>
      </div>

      <!-- Section A: Send via Direct Message to Followed Contacts -->
      <div class="share-dialog-section share-dialog-section-dm" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <label style="font-size: 0.86rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
            <span>✉️</span> Share with Followed Contacts
          </label>
          <span id="shareSelectedCount" style="font-size: 0.75rem; color: var(--primary-color); font-weight: 600;">0 selected</span>
        </div>

        <?php if (!empty($currentUser)): ?>
          <div style="position: relative; margin-bottom: 10px;">
            <input type="text" id="shareFollowingSearch" class="form-input" placeholder="Search contacts..." style="padding: 8px 12px 8px 32px; font-size: 0.85rem; border-radius: 20px; width: 100%;">
            <span style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 0.82rem; color: var(--text-muted); pointer-events: none;">🔍</span>
          </div>

          <div id="shareFollowingList" class="share-following-list" style="max-height: 160px; overflow-y: auto; border: 1px solid var(--glass-border); border-radius: 12px; padding: 6px; background: rgba(255,255,255,0.6); display: flex; flex-direction: column; gap: 4px;">
            <div id="shareFollowingLoading" style="text-align: center; padding: 16px; font-size: 0.85rem; color: var(--text-muted);">
              Loading contacts...
            </div>
          </div>

          <div style="margin-top: 10px;">
            <textarea id="shareDmNote" class="form-input" rows="2" placeholder="Add an optional personal note..." maxlength="500" style="font-size: 0.85rem; padding: 8px 12px; border-radius: 10px; resize: none;"></textarea>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
            <div id="shareDmFeedback" style="font-size: 0.8rem; font-weight: 600;"></div>
            <button type="button" id="shareSendDmBtn" class="btn btn-primary btn-sm btn-pill" onclick="sendArticleDirectMessage()" disabled>
              <span>✉️</span> Send Story
            </button>
          </div>
        <?php else: ?>
          <div style="padding: 14px; background: rgba(248, 250, 252, 0.9); border: 1px dashed var(--glass-border); border-radius: 12px; text-align: center;">
            <p style="margin: 0 0 8px; font-size: 0.85rem; color: var(--text-muted);">
              Sign in to share this story directly with writers and contacts you follow on Infinity Blog.
            </p>
            <a href="/login" class="btn btn-secondary btn-sm btn-pill">
              Sign In to Share Privately
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Divider -->
      <div style="display: flex; align-items: center; gap: 12px; margin: 18px 0 16px;">
        <div style="flex: 1; height: 1px; background: rgba(226, 232, 240, 0.9);"></div>
        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">Or share externally</span>
        <div style="flex: 1; height: 1px; background: rgba(226, 232, 240, 0.9);"></div>
      </div>

      <!-- Section B: WhatsApp & External Social Platforms -->
      <div class="share-options-grid" style="margin: 0 0 16px;">
        <a href="https://wa.me/?text=" target="_blank" rel="noopener noreferrer" class="share-option-btn share-option-whatsapp" id="shareWhatsAppBtn" style="background: rgba(37, 211, 102, 0.1); border-color: rgba(37, 211, 102, 0.3); color: #15803d;">
          <span style="font-size: 1.25rem;">💬</span> WhatsApp
        </a>
        <a href="https://twitter.com/intent/tweet" target="_blank" rel="noopener noreferrer" class="share-option-btn" id="shareTwitterBtn">
          <span style="font-size: 1.2rem;">🐦</span> Twitter / X
        </a>
        <a href="https://www.linkedin.com/sharing/share-offsite/" target="_blank" rel="noopener noreferrer" class="share-option-btn" id="shareLinkedInBtn">
          <span style="font-size: 1.2rem;">💼</span> LinkedIn
        </a>
      </div>

      <!-- Copy Link Bar -->
      <div style="margin-top: 14px;">
        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Story Canonical Link</label>
        <div class="share-copy-link-wrapper">
          <input type="text" id="share-modal-url-input" class="share-copy-input" value="" readonly>
          <button type="button" class="btn btn-primary btn-sm btn-pill" onclick="copyShareLink()">
            Copy Link
          </button>
        </div>
        <span id="share-copy-tooltip" style="display: none; font-size: 0.8rem; color: #16a34a; font-weight: 600; margin-top: 6px;">✓ Link copied to clipboard!</span>
      </div>
    </div>
  </div>

  <!-- Global Report Article / Feed Post Modal -->
  <div class="modal-backdrop" id="report-article-modal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div class="modal-card" style="max-width: 520px; width: 100%; border-radius: var(--radius-xl); background: #ffffff; padding: 28px; box-shadow: var(--glass-shadow-dropdown); position: relative;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 1.4rem;">🚩</span>
          <div>
            <h3 id="reportModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark);">Report Content</h3>
            <p id="reportModalSubtitle" style="margin: 2px 0 0; font-size: 0.84rem; color: var(--text-muted);">
              Help keep Infinity Blog safe, trustworthy, and constructive.
            </p>
          </div>
        </div>
        <button type="button" class="alert-close-btn" onclick="closeReportPostModal()" aria-label="Close report dialog" style="font-size: 1.4rem; border: none; background: transparent; cursor: pointer; color: var(--text-muted); line-height: 1;">×</button>
      </div>

      <form id="reportPostForm" onsubmit="handleReportPostSubmit(event)">
        <input type="hidden" id="reportTargetPostId" name="post_id" value="">
        <div id="reportFormFeedback" style="display: none; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.88rem; font-weight: 600;"></div>

        <div style="margin-bottom: 16px;">
          <label for="reportReasonSelect" style="display: block; font-size: 0.86rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">
            Reason for reporting <span style="color: #ef4444;">*</span>
          </label>
          <select id="reportReasonSelect" class="form-input" required style="width: 100%; font-size: 0.9rem; padding: 9px 12px; border-radius: 8px;">
            <option value="" disabled selected>Select a violation reason...</option>
            <option value="Spam or misleading content">Spam or misleading content</option>
            <option value="Harassment or bullying">Harassment or bullying</option>
            <option value="Hate or abusive content">Hate or abusive content</option>
            <option value="Sexual or inappropriate content">Sexual or inappropriate content</option>
            <option value="Violence or dangerous content">Violence or dangerous content</option>
            <option value="Copyright or intellectual-property concern">Copyright or intellectual-property concern</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div style="margin-bottom: 18px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label for="reportExplanationTextarea" style="font-size: 0.86rem; font-weight: 700; color: var(--text-dark);">
              Additional details (Optional)
            </label>
            <span id="reportCharCounter" style="font-size: 0.76rem; color: var(--text-muted);">0 / 500</span>
          </div>
          <textarea id="reportExplanationTextarea" class="form-input" rows="3" maxlength="500" placeholder="Provide context or specific details for our moderation team (max 500 characters)..." oninput="updateReportCharCount(this)" style="width: 100%; resize: vertical; font-size: 0.88rem; padding: 8px 12px; border-radius: 8px;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeReportPostModal()">Cancel</button>
          <button type="submit" id="reportSubmitBtn" class="btn btn-danger btn-sm btn-pill" style="min-width: 130px;">
            Submit Report
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Global Report Reader Review Modal -->
  <div class="modal-backdrop" id="report-review-modal" role="dialog" aria-modal="true" aria-labelledby="reportReviewModalTitle" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
    <div class="modal-card" style="max-width: 520px; width: 100%; border-radius: var(--radius-xl); background: #ffffff; padding: 28px; box-shadow: var(--glass-shadow-dropdown); position: relative;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 1.4rem;">🚩</span>
          <div>
            <h3 id="reportReviewModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark);">Report Reader Review</h3>
            <p style="margin: 2px 0 0; font-size: 0.84rem; color: var(--text-muted);">
              Help keep evaluations helpful, honest, and respectful.
            </p>
          </div>
        </div>
        <button type="button" class="alert-close-btn" onclick="closeReportReviewModal()" aria-label="Close dialog" style="font-size: 1.4rem; border: none; background: transparent; cursor: pointer; color: var(--text-muted); line-height: 1;">×</button>
      </div>

      <form id="reportReviewForm" onsubmit="handleReportReviewSubmit(event)">
        <input type="hidden" id="reportTargetReviewId" name="review_id" value="">
        <div id="reportReviewFormFeedback" style="display: none; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.88rem; font-weight: 600;"></div>

        <div style="margin-bottom: 16px;">
          <label for="reportReviewReasonSelect" style="display: block; font-size: 0.86rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">
            Reason for reporting <span style="color: #ef4444;">*</span>
          </label>
          <select id="reportReviewReasonSelect" class="form-input" required style="width: 100%; font-size: 0.9rem; padding: 9px 12px; border-radius: 8px;">
            <option value="" disabled selected>Select a violation reason...</option>
            <option value="Spam or misleading content">Spam or misleading content</option>
            <option value="Harassment or bullying">Harassment or bullying</option>
            <option value="Hate or abusive content">Hate or abusive content</option>
            <option value="Sexual or inappropriate content">Sexual or inappropriate content</option>
            <option value="Violence or dangerous content">Violence or dangerous content</option>
            <option value="Copyright or intellectual-property concern">Copyright or intellectual-property concern</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div style="margin-bottom: 18px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label for="reportReviewExplanationTextarea" style="font-size: 0.86rem; font-weight: 700; color: var(--text-dark);">
              Additional details (Optional)
            </label>
            <span id="reportReviewCharCounter" style="font-size: 0.76rem; color: var(--text-muted);">0 / 500</span>
          </div>
          <textarea id="reportReviewExplanationTextarea" class="form-input" rows="3" maxlength="500" placeholder="Provide context for our moderation team (max 500 characters)..." oninput="updateReportReviewCharCount(this)" style="width: 100%; resize: vertical; font-size: 0.88rem; padding: 8px 12px; border-radius: 8px;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeReportReviewModal()">Cancel</button>
          <button type="submit" id="reportReviewSubmitBtn" class="btn btn-danger btn-sm btn-pill" style="min-width: 130px;">
            Submit Report
          </button>
        </div>
      </form>
    </div>
  </div>

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

  <!-- Threaded Discussion Panel & Bottom Sheet Modal -->
  <?php require __DIR__ . '/comments_modal.php'; ?>

  <?php
    $jsFile = dirname(__DIR__, 2) . '/public/assets/js/app.js';
    $jsVersion = file_exists($jsFile) ? (string) filemtime($jsFile) : '20261013';
    $commentsJsFile = dirname(__DIR__, 2) . '/public/assets/js/comments.js';
    $commentsJsVersion = file_exists($commentsJsFile) ? (string) filemtime($commentsJsFile) : '20261019';
  ?>
  <script src="/assets/js/app.js?v=<?= $jsVersion ?>"></script>
  <script src="/assets/js/comments.js?v=<?= $commentsJsVersion ?>"></script>

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
