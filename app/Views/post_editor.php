<?php
  $isEdit = !empty($post['id']);
  $pageHeading = $isEdit ? 'Edit Post' : 'Create New Post';
  $remixedFromId = !empty($post['remixed_from_post_id']) ? (int) $post['remixed_from_post_id'] : (!empty($remixSource['id']) ? (int) $remixSource['id'] : null);
  $remixedFromTitle = $post['remixed_from_title'] ?? ($remixSource['title'] ?? null);
  $remixedFromAuthor = $post['remixed_from_author_name'] ?? ($remixSource['author_name'] ?? null);

  $initialCoverPath = $post['cover_image_path'] ?? '';
  $initialCoverUrl = !empty($initialCoverPath) ? (\InfinityBlog\Posts\ArticleImageHelper::resolveCoverUrl($initialCoverPath) ?? \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post)) : '';
  $initialVideoPath = $post['video_path'] ?? '';

  $studioData = $studioData ?? [
    'subtitle' => '',
    'research_notes' => '',
    'quick_notes' => [
      ['id' => 'qn-1', 'text' => 'Add statistic about screen time', 'done' => true],
      ['id' => 'qn-2', 'text' => 'Include personal experience', 'done' => false],
      ['id' => 'qn-3', 'text' => 'Find a relevant quote', 'done' => false],
      ['id' => 'qn-4', 'text' => 'Add image suggestions', 'done' => false],
    ],
    'target_words' => 2000,
    'settings' => [
      'theme' => 'lavender',
      'font' => 'serif',
      'textSize' => 18,
      'editorWidth' => 'comfortable',
      'lineSpacing' => 'relaxed',
      'focusDefault' => false,
    ],
  ];

  $savedSettings = $studioData['settings'] ?? [];
  $activeTheme = $savedSettings['theme'] ?? 'lavender';
  $activeFont = $savedSettings['font'] ?? 'serif';
  $targetWords = !empty($studioData['target_words']) ? (int) $studioData['target_words'] : 2000;
  $subtitle = $studioData['subtitle'] ?? '';
  $researchNotes = $studioData['research_notes'] ?? '';
  $quickNotes = $studioData['quick_notes'] ?? [];

  $userInitials = 'U';
  if (!empty($currentUser['display_name'])) {
    $userInitials = mb_strtoupper(mb_substr($currentUser['display_name'], 0, 1));
  } elseif (!empty($currentUser['username'])) {
    $userInitials = mb_strtoupper(mb_substr($currentUser['username'], 0, 1));
  }
?>

<div id="infinity-writing-studio" class="studio-container studio-theme-<?= htmlspecialchars($activeTheme, ENT_QUOTES, 'UTF-8') ?> studio-font-<?= htmlspecialchars($activeFont, ENT_QUOTES, 'UTF-8') ?>">

  <!-- TOP NAVIGATION TOOLBAR -->
  <header class="studio-topbar" role="toolbar" aria-label="Writing Studio Toolbar">
    <!-- Left: Brand Mark & Management Link -->
    <div class="studio-topbar-left">
      <a href="/posts/manage" class="studio-brand-link" title="Back to All Articles">
        <img src="/assets/images/infinity-blog-symbol.png" alt="Infinity Blog" class="studio-brand-symbol-img" width="34" height="21" loading="eager">
        <span class="studio-brand-name">Infinity Blog</span>
      </a>
    </div>

    <!-- Center: Global Search & Shortcuts -->
    <div class="studio-topbar-search">
      <span class="studio-search-icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </span>
      <input type="search" id="studio-search-input" class="studio-search-input" placeholder="Search articles, ideas, or anything..." aria-label="Search studio articles and notes">
      <kbd class="studio-search-shortcut">⌘ K</kbd>
    </div>

    <!-- Right: Save Status, Preview, Publish, Theme, Profile -->
    <div class="studio-topbar-right">
      <!-- Live Truthful Save Indicator -->
      <div id="studio-save-indicator" class="studio-save-indicator" title="Draft Save Status">
        <span id="studio-save-dot" class="studio-save-dot"></span>
        <span id="studio-save-text">Saved just now</span>
      </div>

      <!-- Preview Button -->
      <button type="button" id="studio-preview-toggle-btn" class="studio-top-btn" title="Toggle Live Preview">
        <span id="studio-preview-btn-icon">👁️</span>
        <span id="studio-preview-btn-text">Preview</span>
      </button>

      <!-- Publish Split Dropdown -->
      <div class="studio-publish-split">
        <button type="button" id="studio-main-publish-btn" class="studio-publish-btn">
          <?= $isEdit && ($post['status'] ?? '') === 'published' ? 'Update' : 'Publish' ?>
        </button>
        <button type="button" id="studio-publish-dropdown-btn" class="studio-publish-dropdown-toggle" aria-label="More publishing actions" aria-expanded="false">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>
        <div id="studio-publish-menu" class="studio-publish-menu" role="menu">
          <button type="button" class="studio-publish-menu-item" id="menu-publish-now-btn" role="menuitem">
            <span>🚀</span> <?= $isEdit && ($post['status'] ?? '') === 'published' ? 'Update Post Now' : 'Publish Immediately' ?>
          </button>
          <button type="button" class="studio-publish-menu-item" id="menu-save-draft-btn" role="menuitem">
            <span>💾</span> Save as Draft
          </button>
          <button type="button" class="studio-publish-menu-item" id="menu-schedule-btn" role="menuitem">
            <span>📅</span> Schedule Publication...
          </button>
          <?php if ($isEdit && ($post['status'] ?? '') === 'published'): ?>
            <button type="button" class="studio-publish-menu-item" id="menu-unpublish-btn" role="menuitem" style="color: #dc2626;">
              <span>📥</span> Unpublish to Draft
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Theme Direct Toggle Button -->
      <button type="button" id="studio-theme-toggle-btn" class="studio-icon-btn" aria-label="Toggle Light/Dark Theme" title="Switch Theme">
        <svg id="studio-theme-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
        <svg id="studio-theme-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
      </button>

      <!-- User Notifications Shortcut -->
      <a href="/messages" class="studio-icon-btn" title="Messages & Notifications" aria-label="Messages">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
      </a>

      <!-- User Avatar Circle -->
      <a href="/profile/edit" class="studio-avatar-pill" title="My Profile" aria-label="Profile">
        <?= htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') ?>
      </a>

      <!-- Independent Sidebar Toggles (Left Navigation & Right Inspector) -->
      <div class="studio-sidebar-toggles-group" role="group" aria-label="Sidebar visibility controls">
        <!-- Left Sidebar Toggle -->
        <button type="button"
                id="toggle-left-sidebar-btn"
                class="studio-icon-btn studio-sidebar-toggle-btn active"
                aria-label="Toggle left sidebar"
                aria-expanded="true"
                aria-controls="studio-left-sidebar"
                title="Toggle left sidebar (Workspaces &amp; Navigation)">
          <svg class="studio-sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="9" y1="3" x2="9" y2="21"></line>
            <path class="studio-sidebar-icon-fill" d="M3 5a2 2 0 0 1 2-2h4v18H5a2 2 0 0 1-2-2z" fill="currentColor"></path>
          </svg>
        </button>

        <!-- Right Sidebar Toggle -->
        <button type="button"
                id="toggle-right-sidebar-btn"
                class="studio-icon-btn studio-sidebar-toggle-btn active"
                aria-label="Toggle right sidebar"
                aria-expanded="true"
                aria-controls="studio-right-sidebar"
                title="Toggle right sidebar (Outline &amp; Tools)">
          <svg class="studio-sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="15" y1="3" x2="15" y2="21"></line>
            <path class="studio-sidebar-icon-fill" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4z" fill="currentColor"></path>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- DRAFT RECOVERY BANNER (If local unsaved backup exists) -->
  <div id="draft-recovery-banner" class="alert-banner" style="display: none; align-items: center; justify-content: space-between; margin: 10px 20px 0; background: rgba(254, 243, 199, 0.95); border: 1px solid rgba(245, 158, 11, 0.6); color: #92400e; border-radius: var(--radius-md); padding: 10px 16px; z-index: 90;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <span style="font-size: 1.25rem;">💾</span>
      <div>
        <strong>Unsaved Local Draft Detected:</strong>
        <span style="font-size: 0.88rem; margin-left: 4px;">Found unsaved work from <span id="draft-saved-time" style="font-weight: 600;"></span>.</span>
      </div>
    </div>
    <div style="display: flex; gap: 8px;">
      <button type="button" id="restore-draft-btn" class="btn btn-sm btn-primary">Restore Draft</button>
      <button type="button" id="discard-draft-btn" class="btn btn-sm btn-secondary">Discard</button>
    </div>
  </div>

  <div id="editor-error-alert" class="alert-banner alert-error" style="display: none; margin: 10px 20px 0; z-index: 90;"></div>

  <!-- MASTER 4-PANEL WORKSPACE BODY -->
  <div class="studio-body">

    <!-- 1. LEFT SIDEBAR: Tools, Workspaces & Writing Goal -->
    <aside class="studio-left-sidebar" id="studio-left-sidebar" aria-label="Writing Studio Navigation">
      <div>
        <!-- Primary Navigation Items -->
        <nav class="studio-nav-group" aria-label="Studio Tools">
          <button type="button" class="studio-nav-item active" id="nav-item-write" data-view="write">
            <span class="studio-nav-icon">✏️</span>
            <span>Write</span>
          </button>
          <button type="button" class="studio-nav-item" id="nav-item-ideas" data-view="ideas">
            <span class="studio-nav-icon">💡</span>
            <span>Ideas</span>
          </button>
          <button type="button" class="studio-nav-item" id="nav-item-drafts" data-view="drafts">
            <span class="studio-nav-icon">📄</span>
            <span>Drafts</span>
            <span style="margin-left: auto; font-size: 0.75rem; background: rgba(99, 102, 241, 0.15); color: #4338ca; padding: 1px 7px; border-radius: 10px; font-weight: 700;">
              <?= count(array_filter($authorPosts ?? [], fn($p) => ($p['status'] ?? '') === 'draft')) ?>
            </span>
          </button>
          <button type="button" class="studio-nav-item" id="nav-item-media" data-view="media">
            <span class="studio-nav-icon">🖼️</span>
            <span>Media</span>
          </button>
          <button type="button" class="studio-nav-item" id="nav-item-planner" data-view="planner">
            <span class="studio-nav-icon">📅</span>
            <span>Planner</span>
          </button>
          <button type="button" class="studio-nav-item" id="nav-item-insights" data-view="insights">
            <span class="studio-nav-icon">📊</span>
            <span>Insights</span>
          </button>
          <a href="/communities" class="studio-nav-item" id="nav-item-community">
            <span class="studio-nav-icon">👥</span>
            <span>Community</span>
          </a>
        </nav>

        <!-- Workspaces Section -->
        <div class="studio-workspaces-section">
          <div class="studio-workspaces-header">
            <span>Workspaces</span>
            <button type="button" id="add-workspace-btn" style="background: none; border: none; cursor: pointer; color: var(--studio-muted); font-size: 1.1rem; line-height: 1; padding: 0 4px;" title="Create Workspace">+</button>
          </div>
          <div class="studio-workspaces-list">
            <div class="studio-workspace-item" data-workspace="personal">
              <span class="studio-workspace-dot" style="background: #8b5cf6;"></span>
              <span>Personal</span>
            </div>
            <div class="studio-workspace-item" data-workspace="travel">
              <span class="studio-workspace-dot" style="background: #6366f1;"></span>
              <span>Travel Stories</span>
            </div>
            <div class="studio-workspace-item" data-workspace="tech">
              <span class="studio-workspace-dot" style="background: #10b981;"></span>
              <span>Tech & AI</span>
            </div>
            <div class="studio-workspace-item" data-workspace="design">
              <span class="studio-workspace-dot" style="background: #f59e0b;"></span>
              <span>Design Ideas</span>
            </div>
            <a href="/posts/manage" class="studio-workspace-item" style="color: var(--studio-muted);">
              <span class="studio-workspace-dot" style="background: #94a3b8;"></span>
              <span>All Articles</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Writing Goal Card -->
      <div class="studio-goal-card" id="studio-writing-goal-card" title="Click to customize writing target">
        <div class="studio-goal-ring" id="goal-progress-ring-container">
          <svg width="38" height="38" viewBox="0 0 36 36">
            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(148, 163, 184, 0.25)" stroke-width="3.5"/>
            <path id="goal-ring-arc" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#6366f1" stroke-width="3.5" stroke-dasharray="0, 100" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="studio-goal-details">
          <div class="studio-goal-title">
            <span>Writing Goal</span>
            <span id="goal-percent-text" style="color: #6366f1;">0%</span>
          </div>
          <div class="studio-goal-sub">
            <span id="goal-words-current">0</span> / <span id="goal-words-target"><?= $targetWords ?></span> words
          </div>
        </div>
        <span style="color: var(--studio-muted); font-size: 0.9rem; margin-left: 2px;">›</span>
      </div>
    </aside>

    <!-- 2. CENTER EDITORIAL WRITING CANVAS -->
    <main class="studio-center-canvas" id="studio-center-canvas">
      <div class="studio-canvas-inner" id="studio-canvas-inner">

        <!-- Editorial Formatting Toolbar (Sticky) -->
        <div class="studio-formatting-toolbar" id="studio-formatting-toolbar" role="toolbar" aria-label="Rich text formatting">
          <!-- Heading / Block Dropdown -->
          <select id="studio-block-type-select" class="studio-tool-dropdown" title="Text Style">
            <option value="p">Paragraph</option>
            <option value="h1">Heading 1</option>
            <option value="h2">Heading 2</option>
            <option value="h3">Heading 3</option>
            <option value="quote">Blockquote</option>
            <option value="code">Code Block</option>
          </select>

          <div class="studio-toolbar-divider"></div>

          <!-- Inline Text Styles -->
          <button type="button" class="studio-tool-btn" id="format-bold-btn" title="Bold (Ctrl+B)"><strong>B</strong></button>
          <button type="button" class="studio-tool-btn" id="format-italic-btn" title="Italic (Ctrl+I)"><em>I</em></button>
          <button type="button" class="studio-tool-btn" id="format-underline-btn" title="Underline (Ctrl+U)"><span style="text-decoration: underline;">U</span></button>
          <button type="button" class="studio-tool-btn" id="format-strike-btn" title="Strikethrough"><span style="text-decoration: line-through;">S</span></button>

          <div class="studio-toolbar-divider"></div>

          <!-- Lists & Indents -->
          <button type="button" class="studio-tool-btn" id="format-bullet-btn" title="Bullet List">•≡</button>
          <button type="button" class="studio-tool-btn" id="format-number-btn" title="Numbered List">1.≡</button>
          <button type="button" class="studio-tool-btn" id="format-align-left-btn" title="Align Left">⇤</button>
          <button type="button" class="studio-tool-btn" id="format-align-center-btn" title="Align Center">⇥</button>

          <div class="studio-toolbar-divider"></div>

          <!-- Inserts -->
          <button type="button" class="studio-tool-btn" id="format-link-btn" title="Insert Link">🔗</button>
          <button type="button" class="studio-tool-btn" id="format-quote-btn" title="Insert Quote">”</button>
          <button type="button" class="studio-tool-btn" id="format-image-btn" title="Upload and Insert Image">🖼️</button>
          <button type="button" class="studio-tool-btn" id="format-video-btn" title="Insert Video / Embed">🎥</button>
          <button type="button" class="studio-tool-btn" id="format-code-btn" title="Code Block">{ }</button>

          <div class="studio-toolbar-divider"></div>

          <!-- AI Assistant Quick Trigger -->
          <button type="button" class="studio-tool-btn studio-ai-pill-btn" id="open-ai-panel-btn" title="Open AI Writing Assistant">
            <span>✨ AI</span>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left: 3px;"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>

          <!-- Studio Settings Trigger -->
          <button type="button" class="studio-tool-btn" id="open-settings-drawer-btn" title="Appearance & Editor Settings" style="margin-left: auto;">
            ⚙️
          </button>
        </div>

        <!-- Form for hidden inputs and sync -->
        <form id="post-editor-form" method="POST" action="<?= $isEdit ? '/author/posts/' . (int) $post['id'] : '/author/posts' ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($isEdit): ?>
            <input type="hidden" id="post-id" value="<?= (int) $post['id'] ?>">
          <?php endif; ?>
          <?php if ($remixedFromId !== null): ?>
            <input type="hidden" id="remixed-from-post-id" value="<?= (int) $remixedFromId ?>">
          <?php endif; ?>

          <input type="hidden" id="post-cover-image-path" name="cover_image_path" value="<?= htmlspecialchars($initialCoverPath, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="post-video-path" name="video_path" value="<?= htmlspecialchars($initialVideoPath, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="scheduled-at" name="scheduled_at" value="<?= !empty($post['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($post['scheduled_at'])) : '' ?>">

          <!-- Category Pill Selector -->
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
            <label for="post-category" style="font-size: 0.78rem; font-weight: 700; color: var(--studio-muted); text-transform: uppercase; letter-spacing: 0.05em;">Category:</label>
            <select id="post-category" name="category_id" class="studio-tool-dropdown" style="padding: 4px 10px; font-size: 0.82rem; border-radius: 12px;">
              <option value="">Select Category (Required to Publish)</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= (!empty($post['category_id']) && (int) $post['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Remix Attribution Banner -->
          <?php if ($remixedFromId !== null): ?>
            <div class="alert-banner" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(238, 242, 255, 0.7); border: 1px solid rgba(199, 210, 254, 0.85); color: #3730a3; border-radius: var(--radius-md); padding: 8px 14px; font-size: 0.85rem;">
              <span>🔀</span>
              <div>
                <strong>Remix:</strong> Based on <em><?= htmlspecialchars($remixedFromTitle ?? 'Original Article', ENT_QUOTES, 'UTF-8') ?></em>
                <?php if (!empty($remixedFromAuthor)): ?> by <?= htmlspecialchars($remixedFromAuthor, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>.
              </div>
            </div>
          <?php endif; ?>

          <!-- Article Title Field -->
          <input type="text"
                 id="post-title"
                 name="title"
                 class="studio-title-field"
                 placeholder="The Art of Mindful Technology"
                 value="<?= htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 required
                 maxlength="190"
                 autocomplete="off">

          <!-- Article Subtitle / Lead Description Field -->
          <input type="text"
                 id="post-subtitle"
                 name="subtitle"
                 class="studio-subtitle-field"
                 placeholder="A calmer, more intentional relationship with the digital world."
                 value="<?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?>"
                 autocomplete="off">

          <!-- Cover Image Preview Banner (if attached) -->
          <div id="studio-cover-banner" style="<?= empty($initialCoverUrl) ? 'display: none;' : '' ?> margin-bottom: 20px; position: relative; border-radius: 14px; overflow: hidden; border: 1px solid var(--studio-border); box-shadow: 0 4px 14px rgba(0,0,0,0.06);">
            <img id="studio-cover-img" src="<?= htmlspecialchars($initialCoverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cover Image" style="width: 100%; height: 260px; object-fit: cover; display: block;">
            <div style="position: absolute; top: 12px; right: 12px; display: flex; gap: 6px;">
              <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="document.getElementById('cover-file-input').click()">Change Cover</button>
              <button type="button" class="btn btn-danger-subtle btn-sm btn-pill" id="remove-cover-banner-btn">Remove</button>
            </div>
          </div>

          <!-- Video Preview Banner (if attached) -->
          <div id="studio-video-banner" style="<?= empty($initialVideoPath) ? 'display: none;' : '' ?> margin-bottom: 18px; padding: 10px 14px; background: rgba(59, 130, 246, 0.08); border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.2); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 1.3rem;">🎬</span>
              <div>
                <strong id="studio-video-label" style="font-size: 0.88rem;"><?= htmlspecialchars(basename($initialVideoPath), ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="font-size: 0.74rem; color: var(--studio-muted);">Protected video playable via signed access</div>
              </div>
            </div>
            <button type="button" class="btn btn-danger-subtle btn-sm btn-pill" id="remove-video-banner-btn">Remove</button>
          </div>

          <!-- Article Text Canvas (Clean Markdown / Rich Body) -->
          <textarea id="post-content"
                    name="content"
                    class="studio-editor-area"
                    placeholder="Start writing your story or draft in clean markdown. Format using the toolbar above, insert quotes, headings, and images..."
                    required><?= htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </form>

        <!-- Live Reader Preview Overlay (Hidden until user clicks Preview) -->
        <div id="studio-preview-container" style="display: none; padding: 24px; border-radius: 16px; background: var(--studio-panel-bg); border: 1px solid var(--studio-border); margin-top: 10px;">
          <div id="studio-preview-loading" style="text-align: center; padding: 30px; color: var(--studio-muted);">
            <span>Rendering reader preview...</span>
          </div>
          <div id="studio-preview-rendered" class="article-body-content" style="display: none;"></div>
        </div>

      </div>
    </main>

    <!-- 3. CONTEXTUAL AI WRITING ASSISTANT PANEL (Floating / Docked) -->
    <aside class="studio-ai-panel" id="studio-ai-panel" aria-label="AI Writing Assistant">
      <div class="studio-ai-header">
        <div class="studio-ai-title">
          <span>✨</span>
          <span>AI Writing Assistant</span>
        </div>
        <button type="button" class="studio-icon-btn" id="close-ai-panel-btn" style="width: 26px; height: 26px;" aria-label="Close Assistant">×</button>
      </div>

      <div style="font-size: 0.75rem; color: var(--studio-muted);">
        Select text in your draft, or apply action to the current section.
      </div>

      <!-- Tone selector pills (used for Change Tone) -->
      <div id="ai-tone-selector" style="display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 2px;">
        <button type="button" class="studio-tab-btn active ai-tone-pill" data-tone="formal" style="font-size: 0.72rem; padding: 3px 8px;">Formal</button>
        <button type="button" class="studio-tab-btn ai-tone-pill" data-tone="casual" style="font-size: 0.72rem; padding: 3px 8px;">Casual</button>
        <button type="button" class="studio-tab-btn ai-tone-pill" data-tone="persuasive" style="font-size: 0.72rem; padding: 3px 8px;">Persuasive</button>
        <button type="button" class="studio-tab-btn ai-tone-pill" data-tone="technical" style="font-size: 0.72rem; padding: 3px 8px;">Technical</button>
      </div>

      <!-- 8 Action Cards Matching Reference Screenshot -->
      <div class="studio-ai-actions-list">
        <!-- 1. Improve Writing -->
        <button type="button" class="studio-ai-action-card" data-action="improve_writing">
          <div class="studio-ai-action-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">✏️</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Improve writing</div>
            <div class="studio-ai-action-desc">Enhance clarity and flow</div>
          </div>
        </button>

        <!-- 2. Fix Grammar -->
        <button type="button" class="studio-ai-action-card" data-action="fix_grammar">
          <div class="studio-ai-action-icon" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;">%</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Fix grammar</div>
            <div class="studio-ai-action-desc">Correct errors and improve style</div>
          </div>
        </button>

        <!-- 3. Change Tone -->
        <button type="button" class="studio-ai-action-card" data-action="change_tone">
          <div class="studio-ai-action-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">🎭</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Change tone</div>
            <div class="studio-ai-action-desc">Make it more formal, casual, etc.</div>
          </div>
        </button>

        <!-- 4. Make Shorter -->
        <button type="button" class="studio-ai-action-card" data-action="make_shorter">
          <div class="studio-ai-action-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">⇥⇤</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Make shorter</div>
            <div class="studio-ai-action-desc">Keep the key ideas</div>
          </div>
        </button>

        <!-- 5. Make Longer -->
        <button type="button" class="studio-ai-action-card" data-action="make_longer">
          <div class="studio-ai-action-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">⇤⇥</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Make longer</div>
            <div class="studio-ai-action-desc">Add more detail and examples</div>
          </div>
        </button>

        <!-- 6. Simplify -->
        <button type="button" class="studio-ai-action-card" data-action="simplify">
          <div class="studio-ai-action-icon" style="background: rgba(6, 182, 212, 0.15); color: #06b6d4;">✨</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Simplify</div>
            <div class="studio-ai-action-desc">Use clearer, simpler language</div>
          </div>
        </button>

        <!-- 7. Suggest Headline -->
        <button type="button" class="studio-ai-action-card" data-action="suggest_headline">
          <div class="studio-ai-action-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">📰</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Suggest headline</div>
            <div class="studio-ai-action-desc">Generate title ideas</div>
          </div>
        </button>

        <!-- 8. Continue Writing -->
        <button type="button" class="studio-ai-action-card" data-action="continue_writing">
          <div class="studio-ai-action-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">🚀</div>
          <div class="studio-ai-action-info">
            <div class="studio-ai-action-name">Continue writing</div>
            <div class="studio-ai-action-desc">Based on your content</div>
          </div>
        </button>
      </div>

      <!-- AI Comparison / Review Card -->
      <div id="studio-ai-result-card" class="studio-ai-result-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <strong id="ai-result-title" style="font-size: 0.82rem; color: #4338ca;">AI Suggestion</strong>
          <span id="ai-result-source" style="font-size: 0.7rem; color: var(--studio-muted);"></span>
        </div>
        <div id="ai-result-content" class="studio-ai-result-text"></div>
        <div class="studio-ai-result-actions">
          <button type="button" id="ai-apply-replace-btn" class="btn btn-sm btn-primary" style="flex: 1; padding: 4px 8px; font-size: 0.78rem;">Replace</button>
          <button type="button" id="ai-apply-insert-btn" class="btn btn-sm btn-secondary" style="flex: 1; padding: 4px 8px; font-size: 0.78rem;">Insert</button>
          <button type="button" id="ai-discard-btn" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 0.78rem;">✕</button>
        </div>
      </div>
    </aside>

    <!-- 4. RIGHT SIDEBAR: Tabs (Outline, Research, Media), Quick Notes, SEO Readiness -->
    <aside class="studio-right-sidebar" id="studio-right-sidebar" aria-label="Article Outline and Tools">
      <!-- 3 Tab Switcher -->
      <div class="studio-tabs-nav" role="tablist">
        <button type="button" class="studio-tab-btn active" id="tab-nav-outline" role="tab" aria-selected="true">Outline</button>
        <button type="button" class="studio-tab-btn" id="tab-nav-research" role="tab" aria-selected="false">Research</button>
        <button type="button" class="studio-tab-btn" id="tab-nav-media" role="tab" aria-selected="false">Media</button>
      </div>

      <!-- TAB 1: OUTLINE CONTENT -->
      <div id="tab-panel-outline" class="studio-tab-content">
        <div class="studio-side-card">
          <div class="studio-side-card-header">
            <span>Article outline</span>
            <button type="button" id="outline-add-heading-btn" class="studio-icon-btn" style="width: 24px; height: 24px; font-size: 0.9rem;" title="Add Section">+</button>
          </div>
          <div id="studio-outline-container" class="studio-outline-list">
            <span style="font-size: 0.78rem; color: var(--studio-muted); font-style: italic;">Headings added in your draft will appear here.</span>
          </div>
          <button type="button" id="btn-add-section-action" class="studio-top-btn" style="width: 100%; justify-content: center; font-size: 0.8rem; padding: 6px 12px; margin-top: 6px;">
            + Add section
          </button>
        </div>
      </div>

      <!-- TAB 2: RESEARCH & NOTES CONTENT -->
      <div id="tab-panel-research" class="studio-tab-content" style="display: none;">
        <div class="studio-side-card">
          <div class="studio-side-card-header">
            <span>Research & Sources</span>
          </div>
          <textarea id="studio-research-notes" class="studio-editor-area" style="min-height: 180px; font-size: 0.82rem; line-height: 1.5; border: 1px solid var(--studio-border); border-radius: 8px; padding: 8px;" placeholder="Paste sources, statistics, URLs, quotes, and research ideas for this article..."><?= htmlspecialchars($researchNotes, ENT_QUOTES, 'UTF-8') ?></textarea>
          <span style="font-size: 0.72rem; color: var(--studio-muted);">Saved automatically to your draft.</span>
        </div>
      </div>

      <!-- TAB 3: MEDIA MANAGEMENT CONTENT -->
      <div id="tab-panel-media" class="studio-tab-content" style="display: none;">
        <div class="studio-side-card">
          <div class="studio-side-card-header">
            <span>Featured Cover</span>
          </div>
          <button type="button" class="btn btn-secondary btn-sm btn-full" onclick="document.getElementById('cover-file-input').click()">
            Upload Cover Artwork
          </button>
        </div>

        <div class="studio-side-card" style="margin-top: 10px;">
          <div class="studio-side-card-header">
            <span>Article Video</span>
          </div>
          <button type="button" class="btn btn-secondary btn-sm btn-full" onclick="window.openInsertVideoModal()">
            Embed or Upload Video
          </button>
        </div>
      </div>

      <!-- QUICK NOTES CHECKLIST CARD -->
      <div class="studio-side-card">
        <div class="studio-side-card-header">
          <span>Quick notes</span>
          <button type="button" id="add-quick-note-btn" class="studio-icon-btn" style="width: 24px; height: 24px; font-size: 0.9rem;" title="Add quick note">+</button>
        </div>
        <div id="studio-quick-notes-list" class="studio-notes-list">
          <?php foreach ($quickNotes as $qn): ?>
            <?php
              $qnId = htmlspecialchars($qn['id'] ?? uniqid('qn-'), ENT_QUOTES, 'UTF-8');
              $qnText = htmlspecialchars($qn['text'] ?? '', ENT_QUOTES, 'UTF-8');
              $isDone = !empty($qn['done']);
            ?>
            <div class="studio-note-item <?= $isDone ? 'is-done' : '' ?>" data-id="<?= $qnId ?>">
              <input type="checkbox" class="studio-note-checkbox" <?= $isDone ? 'checked' : '' ?>>
              <span class="studio-note-label" style="flex: 1;"><?= $qnText ?></span>
              <button type="button" class="studio-note-del-btn" style="background: none; border: none; color: var(--studio-muted); cursor: pointer; font-size: 0.8rem;" title="Delete">×</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="quick-note-add-row" style="display: none; gap: 6px; margin-top: 6px;">
          <input type="text" id="new-quick-note-input" class="form-input" style="font-size: 0.8rem; padding: 4px 8px;" placeholder="New note...">
          <button type="button" id="save-new-quick-note-btn" class="btn btn-primary btn-sm" style="padding: 4px 10px;">Add</button>
        </div>
      </div>

      <!-- SEO & PUBLISH READINESS CARD -->
      <div class="studio-side-card">
        <div class="studio-seo-gauge-row">
          <span style="font-weight: 700; font-size: 0.86rem; color: var(--studio-text);">SEO & Publish</span>
          <div class="studio-seo-meter-ring" id="seo-gauge-ring-container">
            <svg width="40" height="40" viewBox="0 0 36 36">
              <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(148, 163, 184, 0.25)" stroke-width="3.5"/>
              <path id="seo-gauge-arc" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#10b981" stroke-width="3.5" stroke-dasharray="40, 100" stroke-linecap="round"/>
            </svg>
            <span id="seo-score-fraction" style="position: absolute; font-size: 0.72rem; font-weight: 800; color: #10b981;">2/5</span>
          </div>
        </div>

        <div class="studio-checklist-list">
          <div class="studio-checklist-row" id="chk-meta-desc">
            <span class="chk-icon">☐</span>
            <span>Add meta description</span>
          </div>
          <div class="studio-checklist-row" id="chk-category">
            <span class="chk-icon">☐</span>
            <span>Choose category</span>
          </div>
          <div class="studio-checklist-row" id="chk-tags">
            <span class="chk-icon">☐</span>
            <span>Add article tags</span>
          </div>
          <div class="studio-checklist-row" id="chk-featured-image">
            <span class="chk-icon">☐</span>
            <span>Set featured image</span>
          </div>
          <div class="studio-checklist-row" id="chk-readability">
            <span class="chk-icon">☐</span>
            <span>Check readability</span>
            <span id="readability-badge" style="margin-left: auto; font-size: 0.7rem; color: #10b981; font-weight: 700;">Good</span>
          </div>
        </div>
      </div>
    </aside>

  </div><!-- /.studio-body -->

  <!-- BOTTOM STATUS BAR -->
  <footer class="studio-bottom-bar" id="studio-bottom-bar">
    <div class="studio-bottom-bar-left">
      <span id="studio-stat-words">0 words</span>
      <span>•</span>
      <span id="studio-stat-readtime">0 min read</span>
      <span>•</span>
      <span id="studio-stat-chars" style="opacity: 0.7;">0 characters</span>
    </div>

    <div class="studio-bottom-bar-right">
      <!-- Focus Mode Switch -->
      <label class="studio-toggle-switch" for="studio-focus-mode-toggle" title="Toggle Distraction-free Focus Mode">
        <span style="font-weight: 600; font-size: 0.8rem;">Focus mode</span>
        <input type="checkbox" id="studio-focus-mode-toggle" class="studio-toggle-checkbox" style="display: none;">
        <div class="studio-switch-track">
          <div class="studio-switch-thumb"></div>
        </div>
      </label>

      <!-- Expand Fullscreen Button -->
      <button type="button" id="studio-fullscreen-btn" class="studio-icon-btn" style="width: 28px; height: 28px;" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
      </button>
    </div>
  </footer>

  <!-- APPEARANCE & EDITOR SETTINGS DRAWER -->
  <div id="studio-settings-drawer" class="studio-settings-drawer" role="dialog" aria-modal="false" aria-labelledby="settings-drawer-title">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <h4 id="settings-drawer-title" style="margin: 0; font-size: 0.92rem; font-weight: 800; color: var(--studio-text);">Appearance & Editor Settings</h4>
      <button type="button" id="close-settings-drawer-btn" style="background: none; border: none; cursor: pointer; font-size: 1.1rem; color: var(--studio-muted);">×</button>
    </div>

    <!-- Theme Selection -->
    <div>
      <label style="font-size: 0.76rem; font-weight: 700; color: var(--studio-muted); text-transform: uppercase;">Theme</label>
      <div class="studio-theme-swatches" style="margin-top: 6px;">
        <div class="studio-theme-swatch studio-swatch-paper <?= $activeTheme === 'paper' ? 'active' : '' ?>" data-theme="paper">Paper</div>
        <div class="studio-theme-swatch studio-swatch-lavender <?= $activeTheme === 'lavender' ? 'active' : '' ?>" data-theme="lavender">Lavender</div>
        <div class="studio-theme-swatch studio-swatch-sage <?= $activeTheme === 'sage' ? 'active' : '' ?>" data-theme="sage">Sage</div>
        <div class="studio-theme-swatch studio-swatch-midnight <?= $activeTheme === 'midnight' ? 'active' : '' ?>" data-theme="midnight">Midnight</div>
      </div>
    </div>

    <!-- Font Selector -->
    <div>
      <label for="settings-font-select" style="font-size: 0.76rem; font-weight: 700; color: var(--studio-muted); text-transform: uppercase;">Font</label>
      <select id="settings-font-select" class="studio-tool-dropdown" style="width: 100%; margin-top: 4px;">
        <option value="serif" <?= $activeFont === 'serif' ? 'selected' : '' ?>>Literary Serif (Default)</option>
        <option value="inter" <?= $activeFont === 'inter' ? 'selected' : '' ?>>Inter Modern</option>
        <option value="sans" <?= $activeFont === 'sans' ? 'selected' : '' ?>>System Sans</option>
      </select>
    </div>

    <!-- Text Size Slider -->
    <div>
      <div style="display: flex; justify-content: space-between; font-size: 0.76rem; font-weight: 700; color: var(--studio-muted); text-transform: uppercase;">
        <span>Text size</span>
        <span id="settings-text-size-val">18px</span>
      </div>
      <input type="range" id="settings-text-size-slider" min="14" max="24" value="18" style="width: 100%; margin-top: 6px; accent-color: #6366f1;">
    </div>

    <!-- Editor Width -->
    <div>
      <label for="settings-width-select" style="font-size: 0.76rem; font-weight: 700; color: var(--studio-muted); text-transform: uppercase;">Editor width</label>
      <select id="settings-width-select" class="studio-tool-dropdown" style="width: 100%; margin-top: 4px;">
        <option value="compact">Compact (640px)</option>
        <option value="comfortable" selected>Comfortable (780px)</option>
        <option value="wide">Wide (980px)</option>
        <option value="full">Full (100%)</option>
      </select>
    </div>

    <!-- Line Spacing -->
    <div>
      <label for="settings-line-spacing-select" style="font-size: 0.76rem; font-weight: 700; color: var(--studio-muted); text-transform: uppercase;">Line spacing</label>
      <select id="settings-line-spacing-select" class="studio-tool-dropdown" style="width: 100%; margin-top: 4px;">
        <option value="normal">Normal (1.6)</option>
        <option value="relaxed" selected>Relaxed (1.85)</option>
        <option value="spacious">Spacious (2.1)</option>
      </select>
    </div>

    <!-- Focus Mode Default Toggle -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 6px; border-top: 1px solid var(--studio-border);">
      <span style="font-size: 0.82rem; font-weight: 600; color: var(--studio-text);">Focus mode by default</span>
      <input type="checkbox" id="settings-focus-default-toggle" style="accent-color: #6366f1;">
    </div>
  </div>

  <!-- HIDDEN FILE INPUTS FOR COVER & INLINE IMAGES -->
  <input type="file" id="cover-file-input" accept="image/png,image/jpeg,image/webp" style="display: none;">
  <input type="file" id="inline-image-file-input" accept="image/png,image/jpeg,image/webp" style="display: none;">

  <!-- Off-Canvas Mobile Drawer Backdrop Overlay -->
  <div id="studio-drawer-backdrop" class="studio-drawer-backdrop" aria-hidden="true"></div>

</div><!-- /#infinity-writing-studio -->

<!-- MODAL: INSERT VIDEO (Embed or Upload) -->
<div id="insert-video-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="insert-video-modal-title">
  <div class="modal-card">
    <h3 id="insert-video-modal-title" class="modal-title">Insert Video into Article</h3>
    <p class="modal-desc">Embed a YouTube or Vimeo video, or upload an MP4/WebM video file directly to Supabase private storage.</p>

    <div style="display: flex; gap: 8px; margin-bottom: 18px; border-bottom: 1px solid rgba(226, 232, 240, 0.8); padding-bottom: 8px;">
      <button type="button" class="btn btn-secondary btn-sm active" id="video-mode-embed-btn" onclick="switchVideoModalTab('embed')">Embed Web Video</button>
      <button type="button" class="btn btn-secondary btn-sm" id="video-mode-upload-btn" onclick="switchVideoModalTab('upload')">Upload Video File</button>
    </div>

    <!-- Embed Mode -->
    <div id="video-tab-embed">
      <div class="form-group">
        <label for="video-embed-url" class="form-label">Video URL (YouTube or Vimeo)</label>
        <input type="url" id="video-embed-url" class="form-input" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/...">
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Supports standard watch, embed, and short URLs.</div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeInsertVideoModal()">Cancel</button>
        <button type="button" id="confirm-embed-video-btn" class="btn btn-primary">Insert Embed</button>
      </div>
    </div>

    <!-- Upload Mode -->
    <div id="video-tab-upload" style="display: none;">
      <div class="form-group">
        <label class="form-label">Upload Video (MP4 or WebM · Max 50MB)</label>
        <div id="modal-video-upload-box" class="featured-image-placeholder-box" style="cursor: pointer; padding: 18px;" onclick="document.getElementById('modal-video-file-input').click()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color); margin-bottom: 4px;">
            <polygon points="5 3 19 12 5 21 5 3"></polygon>
          </svg>
          <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-dark);">Click to select video file</div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Stored in private bucket with short-lived playback signatures.</div>
        </div>
        <input type="file" id="modal-video-file-input" accept="video/mp4,video/webm" style="display: none;">
        <div id="modal-video-upload-status" style="font-size: 0.82rem; margin-top: 8px; display: none;"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeInsertVideoModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: SCHEDULE PUBLICATION -->
<div id="schedule-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="schedule-modal-title" style="display: none;">
  <div class="modal-card" style="max-width: 440px;">
    <h3 id="schedule-modal-title" class="modal-title">Schedule Publication</h3>
    <p class="modal-desc">Choose a date and time for this article to become publicly visible on Infinity Blog.</p>
    <div class="form-group" style="margin: 16px 0;">
      <label for="modal-scheduled-at" class="form-label">Publish Date & Time</label>
      <input type="datetime-local" id="modal-scheduled-at" class="form-input" value="<?= !empty($post['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($post['scheduled_at'])) : '' ?>">
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('schedule-modal').style.display='none'">Cancel</button>
      <button type="button" id="confirm-schedule-btn" class="btn btn-primary">Schedule & Save</button>
    </div>
  </div>
</div>

<!-- MODAL: REVISION HISTORY -->
<div id="versions-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="versions-modal-title" style="display: none;">
  <div class="modal-card" style="max-width: 600px;">
    <h3 id="versions-modal-title" class="modal-title">Revision History</h3>
    <p class="modal-desc">Review prior revisions and restore content if needed.</p>
    <div id="versions-list-container" style="max-height: 320px; overflow-y: auto; margin: 16px 0;">
      <p style="color: var(--text-muted); font-size: 0.88rem;">Loading revisions...</p>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('versions-modal').style.display='none'">Close</button>
    </div>
  </div>
</div>

<!-- MODAL: SET TARGET WORD COUNT GOAL (PART 8) -->
<div id="word-count-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="word-count-modal-title" style="display: none;">
  <div class="modal-card" style="max-width: 440px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
      <h3 id="word-count-modal-title" class="modal-title" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-heading);">Set Target Word Count</h3>
      <button type="button" id="word-count-modal-x-btn" class="studio-icon-btn" aria-label="Close dialog" style="font-size: 1.1rem; width: 28px; height: 28px;">✕</button>
    </div>
    <p class="modal-desc" style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5;">
      Define a target word count to focus your writing flow. Your live circular progress meter updates automatically.
    </p>

    <!-- Quick Presets -->
    <div style="margin-bottom: 14px;">
      <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: block;">Quick Presets</label>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="btn btn-secondary btn-sm btn-pill word-goal-preset preset-pill" data-words="500">500 (Quick Read)</button>
        <button type="button" class="btn btn-secondary btn-sm btn-pill word-goal-preset preset-pill" data-words="1000">1,000 (Standard)</button>
        <button type="button" class="btn btn-secondary btn-sm btn-pill word-goal-preset preset-pill" data-words="2000">2,000 (Deep Dive)</button>
        <button type="button" class="btn btn-secondary btn-sm btn-pill word-goal-preset preset-pill" data-words="3500">3,500 (Guide)</button>
      </div>
    </div>

    <!-- Numeric Input -->
    <div class="form-group" style="margin-bottom: 20px;">
      <label for="modal-target-words-input" class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: block;">Target Words</label>
      <input type="number" id="modal-target-words-input" class="form-input" min="50" max="50000" step="50" style="font-size: 1.15rem; font-weight: 700; padding: 10px 14px;">
      <span style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px; display: block;">Enter any goal between 50 and 50,000 words.</span>
    </div>

    <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
      <button type="button" id="modal-cancel-goal-btn" class="btn btn-secondary btn-pill">Cancel</button>
      <button type="button" id="modal-save-goal-btn" class="btn btn-primary btn-pill" style="min-width: 120px;">Save Goal</button>
    </div>
  </div>
</div>

<!-- SCRIPT: WRITING STUDIO CONTROLLER -->
<script src="/assets/js/studio.js"></script>
