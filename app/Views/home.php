<!-- Homepage Responsive Three-Column Desktop / Responsive Layout -->
<div class="homepage-two-column-layout">

  <!-- Center Feed Column: Personalized Header, Composer, Tabs & Feed Stream -->
  <div class="homepage-main-column">

    <!-- 1. Personalized Feed Header & Prominent Search -->
    <div class="feed-header-section glass-panel">
      <div class="feed-greeting-wrapper">
        <?php if (!empty($currentUser)): ?>
          <h1 class="feed-greeting-title">
            Good <?= htmlspecialchars($timeGreeting ?? 'day', ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'] ?? 'Friend', ENT_QUOTES, 'UTF-8') ?> 👋
          </h1>
        <?php else: ?>
          <h1 class="feed-greeting-title">
            Welcome to Infinity <span class="brand-text-accent">Blog</span> ✦
          </h1>
        <?php endif; ?>
        <p class="feed-greeting-sub">Discover ideas, ask questions, and grow together.</p>
      </div>

      <!-- Redundant Search Bar hidden in favor of single global search in compact header -->
      <div class="homepage-search-section" style="display: none;">
        <form action="/" method="GET" class="homepage-main-search-bar" role="search" id="hero-search-form">
          <div class="homepage-search-icon-left" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </div>
          <input type="search"
                 name="search"
                 id="hero-search-input"
                 class="homepage-main-search-input"
                 placeholder="Search articles, authors, topics, or communities…"
                 value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 aria-label="Search articles, authors, topics, or communities"
                 autocomplete="off">
          <button type="button"
                  class="search-clear-btn homepage-search-clear"
                  id="hero-search-clear"
                  aria-label="Clear search query"
                  style="<?= empty($searchQuery) ? 'display: none;' : '' ?>">×</button>
          <button type="submit" class="homepage-main-search-btn" aria-label="Submit search">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
        </form>
      </div>
    </div>

    <!-- 2. Community Post Composer -->
    <div class="feed-composer-card glass-panel" id="feed-composer-card">
      <?php if (!empty($currentUser)): ?>
        <?php
          $myAvatar = !empty($currentUser['avatar_url']) ? $currentUser['avatar_url'] : \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($currentUser['avatar_path'] ?? null);
          $myInitial = strtoupper(mb_substr($currentUser['display_name'] ?? $currentUser['username'] ?? 'U', 0, 1));
        ?>
        <div class="composer-header-row">
          <?php if (!empty($myAvatar)): ?>
            <img src="<?= htmlspecialchars($myAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($currentUser['display_name'] ?? 'You', ENT_QUOTES, 'UTF-8') ?>" class="composer-user-avatar">
          <?php else: ?>
            <div class="composer-user-avatar composer-avatar-fallback"><?= htmlspecialchars($myInitial, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <div class="composer-field-wrapper">
            <!-- Exactly ONE single text input field that expands smoothly -->
            <textarea id="composer-text-input"
                      class="composer-text-input"
                      rows="1"
                      placeholder="Share an idea, ask a question, or start a discussion…"
                      maxlength="10000"
                      aria-label="Compose post"
                      onclick="toggleFeedComposer(true)"
                      onfocus="toggleFeedComposer(true)"></textarea>
          </div>
        </div>

        <!-- Attached Image Preview Container -->
        <div id="composer-image-preview-tray" class="composer-image-preview-tray" style="display: none;">
          <div class="composer-image-preview-wrapper">
            <img id="composer-image-preview" src="" alt="Attached post preview">
            <button type="button" class="composer-remove-image-btn" onclick="removeComposerAttachedImage()" title="Remove attached image" aria-label="Remove attached image">×</button>
          </div>
        </div>

        <!-- Hidden File Input for Image Upload -->
        <input type="file" id="composer-image-file-input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleComposerImageUpload(this)">

        <!-- Expandable Controls Tray: Category, Image Attach, Cancel, Publish -->
        <div id="composer-expanded-tray" class="composer-expanded-tray" style="display: none;">
          <div class="composer-tray-footer">
            <div class="composer-tray-meta">
              <label for="composer-category-select" class="visually-hidden">Select Topic</label>
              <select id="composer-category-select" class="form-select composer-select" aria-label="Select Category">
                <?php foreach (($categories ?? []) as $cat): ?>
                  <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>

              <label for="composer-content-type-select" class="visually-hidden">Select Content Type</label>
              <select id="composer-content-type-select" class="form-select composer-select" aria-label="Select Content Type" style="max-width: 130px;">
                <option value="article">ARTICLE</option>
                <option value="blog">BLOG</option>
                <option value="research_paper">RESEARCH</option>
                <option value="photo">PHOTO</option>
              </select>

              <button type="button" class="btn btn-secondary btn-sm btn-pill composer-attach-btn" id="composer-attach-btn" onclick="document.getElementById('composer-image-file-input').click()" title="Attach an image to this post">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                  <circle cx="9" cy="9" r="2"></circle>
                  <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
                </svg>
                <span>Add Image</span>
              </button>

              <a href="/posts/new" class="composer-full-editor-link" title="Open full rich Markdown editor">Switch to Full Editor →</a>
            </div>

            <div class="composer-tray-actions">
              <button type="button" id="composer-cancel-btn" class="btn btn-secondary btn-sm btn-pill" onclick="toggleFeedComposer(false)">Cancel</button>
              <button type="button" id="composer-submit-btn" class="btn btn-primary btn-sm btn-pill" onclick="submitQuickPost(event)">Publish Idea ✦</button>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="composer-top-row">
          <div class="composer-user-avatar composer-avatar-fallback">✦</div>
          <div class="composer-guest-trigger" onclick="location.href='/login?redirect=' + encodeURIComponent(window.location.pathname)">
            <span class="composer-placeholder-text">Share an idea, ask a question, or start a discussion…</span>
            <span class="btn btn-primary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.78rem;">Sign In</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- 3. Feed Navigation Tabs -->
    <nav class="feed-tabs-bar" role="tablist" aria-label="Community Feed Selection">
      <a href="/?tab=for-you<?= !empty($activeCategorySlug) ? '&category=' . urlencode($activeCategorySlug) : '' ?>"
         class="feed-tab-btn <?= ($activeTab ?? 'for-you') === 'for-you' ? 'active' : '' ?>"
         role="tab"
         aria-selected="<?= ($activeTab ?? 'for-you') === 'for-you' ? 'true' : 'false' ?>">
        <span>✦</span> For You
      </a>
      <a href="/?tab=following<?= !empty($activeCategorySlug) ? '&category=' . urlencode($activeCategorySlug) : '' ?>"
         class="feed-tab-btn <?= ($activeTab ?? '') === 'following' ? 'active' : '' ?>"
         role="tab"
         aria-selected="<?= ($activeTab ?? '') === 'following' ? 'true' : 'false' ?>">
        <span>👥</span> Following
      </a>
      <a href="/?tab=trending<?= !empty($activeCategorySlug) ? '&category=' . urlencode($activeCategorySlug) : '' ?>"
         class="feed-tab-btn <?= in_array($activeTab ?? '', ['trending', 'popular']) ? 'active' : '' ?>"
         role="tab"
         title="Trending &amp; Popular Stories"
         aria-selected="<?= in_array($activeTab ?? '', ['trending', 'popular']) ? 'true' : 'false' ?>">
        <span>🔥</span> Trending
      </a>
      <a href="/?tab=latest<?= !empty($activeCategorySlug) ? '&category=' . urlencode($activeCategorySlug) : '' ?>"
         class="feed-tab-btn <?= ($activeTab ?? '') === 'latest' ? 'active' : '' ?>"
         role="tab"
         aria-selected="<?= ($activeTab ?? '') === 'latest' ? 'true' : 'false' ?>">
        <span>⚡</span> Latest
      </a>
    </nav>

    <!-- 4. Horizontally Scrollable Topic/Category Chips -->
    <?php if (!empty($categories)): ?>
      <div class="category-carousel-wrapper" role="region" aria-label="Category Filters">
        <button type="button" class="category-scroll-btn category-scroll-prev" aria-label="Scroll categories left" style="display: none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <div class="category-filter-bar">
          <a href="/?tab=<?= urlencode($activeTab ?? 'for-you') ?>" class="filter-pill <?= empty($activeCategorySlug) ? 'active' : '' ?>">
            <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon(null, 15) ?>
            <span>All Topics</span>
          </a>
          <?php foreach ($categories as $cat): ?>
            <a href="/?tab=<?= urlencode($activeTab ?? 'for-you') ?>&category=<?= urlencode($cat['slug']) ?>" class="filter-pill <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'active' : '' ?>">
              <?= \InfinityBlog\Categories\CategoryIconHelper::renderIcon($cat, 15) ?>
              <span><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <button type="button" class="category-scroll-btn category-scroll-next" aria-label="Scroll categories right">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
      </div>
    <?php endif; ?>

    <!-- 5. Feed Stream & Articles Container -->
    <section id="articles" class="feed-stream-section">
      <div class="section-header" style="margin-top: 4px; margin-bottom: 16px;">
        <div>
          <h2 class="section-title">
            <?php if (!empty($searchQuery)): ?>
              Search Results for "<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
            <?php elseif (!empty($activeCategoryName)): ?>
              <?= htmlspecialchars($activeCategoryName, ENT_QUOTES, 'UTF-8') ?> Articles
            <?php elseif (($activeTab ?? '') === 'following'): ?>
              Stories from Authors You Follow
            <?php elseif (($activeTab ?? '') === 'popular'): ?>
              Trending &amp; Popular Stories
            <?php else: ?>
              Latest Articles
            <?php endif; ?>
          </h2>
          <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 2px;">
            <?= (int) ($totalPosts ?? count($posts)) ?> <?= ($totalPosts ?? count($posts)) === 1 ? 'article' : 'articles' ?> available
          </p>
        </div>

        <div style="display: flex; gap: 12px; align-items: center;">
          <?php if (!empty($searchQuery) || !empty($activeCategorySlug)): ?>
            <a href="/?tab=<?= urlencode($activeTab ?? 'for-you') ?>" class="section-link">Clear filters ×</a>
          <?php endif; ?>
          <a href="/explore" class="section-link" style="font-weight: 700;">Explore All →</a>
        </div>
      </div>

      <!-- Feed Content / Empty States -->
      <?php if (!empty($guestFollowing)): ?>
        <!-- Guest viewing Following tab -->
        <div class="glass-panel empty-state-compact" style="padding: 36px 20px; text-align: center;">
          <div style="font-size: 2.2rem; margin-bottom: 8px;">👥</div>
          <h3 class="empty-title">Follow your favorite authors</h3>
          <p class="empty-desc" style="max-width: 440px; margin: 0 auto 18px;">
            Sign in to your Infinity Blog account to follow thinkers, engineers, and creators. Their latest stories and discussions will appear here.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center;">
            <a href="/login?redirect=<?= urlencode('/?tab=following') ?>" class="btn btn-primary btn-pill btn-sm">Sign In</a>
            <a href="/register" class="btn btn-secondary btn-pill btn-sm">Create Free Account</a>
          </div>
        </div>

      <?php elseif (!empty($emptyFollowing)): ?>
        <!-- Authenticated user with 0 followed authors -->
        <div class="glass-panel empty-state-compact" style="padding: 36px 20px; text-align: center;">
          <div style="font-size: 2.2rem; margin-bottom: 8px;">✍️</div>
          <h3 class="empty-title">You're not following any authors yet</h3>
          <p class="empty-desc" style="max-width: 440px; margin: 0 auto 18px;">
            Discover active writers and perspectives from the "Authors You May Know" sidebar, or browse all writers in our directory.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center;">
            <a href="/authors" class="btn btn-primary btn-pill btn-sm">Discover Authors →</a>
            <a href="/?tab=for-you" class="btn btn-secondary btn-pill btn-sm">Browse For You Feed</a>
          </div>
        </div>

      <?php elseif (empty($posts)): ?>
        <!-- Generic empty state -->
        <div class="glass-panel empty-state-compact">
          <img src="/assets/images/empty-state-background.png" alt="No articles yet" class="empty-state-artwork" width="120" height="73">
          <h3 class="empty-title">No articles found</h3>
          <p class="empty-desc">There are no published articles matching your criteria right now. Check back soon!</p>
          <?php if (!empty($currentUser)): ?>
            <a href="/posts/new" class="btn btn-primary btn-pill btn-sm">+ Write First Post</a>
          <?php else: ?>
            <a href="/register" class="btn btn-primary btn-pill btn-sm">Join &amp; Write</a>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- Feed Cards Stream (Screenshot 2 layout: compact, readable, thumb on right, actions footer) -->
        <div class="feed-cards-container homepage-articles-grid" id="feed-cards-stream">
          <?php
            $renderer = new \InfinityBlog\Posts\ArticleContentRenderer();
          ?>
          <?php foreach ($posts as $idx => $post): ?>
            <?php
              $postId = (int) $post['id'];
              $authorId = (int) ($post['author_id'] ?? 1);
              $authorAvatar = $post['author_avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($post['avatar_path'] ?? null);
              $authorInitial = strtoupper(mb_substr($post['author_name'] ?? 'A', 0, 1));
              $thumb = $post['thumbnail_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, $idx);
              $commentCount = (int) ($post['comment_count'] ?? 0);
              $scoreCount = (int) ($post['reader_score']['count'] ?? 0);
              $isLiked = ($post['user_score'] ?? 0) === 5;
              $isDisliked = ($post['user_score'] ?? 0) === 1;
              $isBookmarked = !empty($post['is_bookmarked']);
              $canonicalPostUrl = ($basePath ?? '') . '/article/' . $postId;
              $relativeTime = $post['relative_time'] ?? 'recently';

              $rawContent = $post['content'] ?? $post['excerpt'] ?? '';
              $snippetData = $renderer->renderFeedSnippet(!empty($post['summary']) ? $post['summary'] : $rawContent, 200);
              $primaryInlineImage = $snippetData['primary_image'];
              $excerptText = !empty($post['summary']) ? $post['summary'] : (!empty($snippetData['text']) ? $snippetData['text'] : ($post['excerpt'] ?? ''));
              $hasInlineImage = ($primaryInlineImage !== null);
            ?>
            <article class="glass-panel feed-card article-card <?= $hasInlineImage ? 'has-inline-image' : '' ?>"
                     data-post-id="<?= $postId ?>"
                     data-article-url="<?= htmlspecialchars($canonicalPostUrl, ENT_QUOTES, 'UTF-8') ?>"
                     tabindex="0"
                     role="article"
                     aria-label="Article: <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
              <!-- Main Feed Card Container -->
              <div class="feed-card-main">
                <!-- Card Header: Avatar, Author, Badge, Handle, Category, Time, Overflow -->
                <div class="feed-card-header">
                  <a href="/author/<?= $authorId ?>" class="feed-author-avatar-link" title="View <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>'s profile">
                    <?php if (!empty($authorAvatar)): ?>
                      <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>"
                           alt="<?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>"
                           class="feed-author-avatar-img"
                           width="34" height="34"
                           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                      <div class="user-avatar feed-avatar-fallback" style="display: none; width: 34px; height: 34px; font-size: 0.85rem; font-weight: 700;">
                        <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php else: ?>
                      <div class="user-avatar feed-avatar-fallback" style="width: 34px; height: 34px; font-size: 0.85rem; font-weight: 700;">
                        <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                  </a>

                  <div class="feed-author-meta-inline">
                    <a href="/author/<?= $authorId ?>" class="feed-author-name" title="View profile">
                      <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php if (!empty($post['author_has_badge'])): ?>
                      <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                    <?php endif; ?>

                    <?php
                      $feedAuthorHandle = $post['author_handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($post['author_username'] ?? null, $post['author_name'] ?? null);
                    ?>
                    <span class="feed-author-handle"><?= htmlspecialchars($feedAuthorHandle, ENT_QUOTES, 'UTF-8') ?></span>

                    <span class="feed-meta-dot">·</span>

                    <?php if (!empty($post['category_name'])): ?>
                      <a href="/?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="feed-card-category-tag" title="Browse category: <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($post['category_slug'] ?? '', 12) ?>
                        <span><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                      </a>
                    <?php endif; ?>

                    <span class="feed-meta-dot">·</span>
                    <span class="feed-card-timestamp" title="<?= htmlspecialchars($post['published_at'] ?? $post['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($relativeTime, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="feed-meta-dot">·</span>
                    <?= \InfinityBlog\Posts\ContentTypeHelper::renderBadge($post['content_type'] ?? 'article') ?>
                  </div>

                  <!-- Overflow Options Menu -->
                  <div class="feed-card-menu dropdown">
                    <button type="button" class="feed-menu-btn" aria-label="More options for this post" onclick="toggleFeedCardMenu(event, <?= $postId ?>)">
                      •••
                    </button>
                    <div class="feed-menu-dropdown glass-panel" id="feed-menu-<?= $postId ?>" style="display: none;">
                      <button type="button" class="feed-menu-item" onclick="openShareModal('<?= htmlspecialchars($canonicalPostUrl, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>', <?= $postId ?>)">
                        <span>🔗</span> Share story
                      </button>
                      <button type="button" class="feed-menu-item" onclick="toggleFeedBookmark(event, <?= $postId ?>)">
                        <span>🔖</span> <?= $isBookmarked ? 'Remove bookmark' : 'Bookmark story' ?>
                      </button>
                      <a href="/author/<?= $authorId ?>" class="feed-menu-item">
                        <span>👤</span> View author
                      </a>
                      <?php if (!empty($post['remixed_from_post_id'])): ?>
                        <a href="/article/<?= (int) $post['remixed_from_post_id'] ?>" class="feed-menu-item">
                          <span>🔀</span> Original story
                        </a>
                      <?php endif; ?>
                      <button type="button" class="feed-menu-item" onclick="openReportPostModal(<?= $postId ?>, '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>')">
                        <span>🚩</span> Report story
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Card Content: Title & Excerpt -->
                <div class="feed-card-body">
                  <?php if (!empty($post['title'])): ?>
                    <h3 class="feed-card-title">
                      <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . $postId) ?>">
                        <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                      </a>
                    </h3>
                  <?php endif; ?>

                  <?php if (!empty($excerptText)): ?>
                    <p class="feed-card-excerpt">
                      <?= htmlspecialchars($excerptText, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                  <?php endif; ?>

                  <!-- Prominent Full-Width Featured Media Image Banner -->
                  <?php if (!empty($thumb)): ?>
                    <div class="feed-card-featured-media feed-card-media">
                      <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . $postId) ?>" class="feed-media-link" aria-label="Read <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                             class="feed-card-featured-img feed-card-thumb-img"
                             loading="lazy"
                             onerror="this.onerror=null; this.closest('.feed-card-media').style.display='none';">
                      </a>
                    </div>
                  <?php elseif ($hasInlineImage): ?>
                    <div class="feed-card-featured-media feed-card-media">
                      <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . $postId) ?>" class="feed-media-link" aria-label="Read <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($primaryInlineImage['url'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($primaryInlineImage['alt'] ?: $post['title'], ENT_QUOTES, 'UTF-8') ?>"
                             class="feed-card-featured-img feed-card-thumb-img feed-card-inline-image"
                             loading="lazy"
                             onerror="this.closest('.feed-card-media').style.display='none';">
                      </a>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Engagement Summary Row (Likes · Comments · Reader Rating) -->
                <div class="feed-card-engagement-summary" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="engagement-summary-stat">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                      <span class="engagement-count-val" data-post-id="<?= $postId ?>"><?= $scoreCount ?></span>
                    </span>
                    <span class="engagement-dot">·</span>
                    <span class="engagement-summary-stat comments-stat-trigger"
                          data-post-id="<?= $postId ?>"
                          role="button"
                          tabindex="0"
                          title="View discussion"
                          onclick="openCommentsDrawer(event, <?= $postId ?>)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                      <span class="engagement-comments-count-val" data-post-id="<?= $postId ?>"><?= $commentCount ?> <?= $commentCount === 1 ? 'comment' : 'comments' ?></span>
                    </span>
                  </div>
                  <div>
                    <?php
                      $ratingInfo = \InfinityBlog\Support\RatingHelper::getRatingInfo($post['reader_score']['average'] ?? 0, $post['reader_score']['count'] ?? 0);
                      echo $ratingInfo['badge_html'];
                    ?>
                  </div>
                </div>

                <!-- Card Footer: Engagement Action Buttons -->
                <div class="feed-card-actions">
                  <!-- Like / Upvote -->
                  <button type="button"
                          class="feed-action-btn btn-feed-like <?= $isLiked ? 'active' : '' ?>"
                          data-post-id="<?= $postId ?>"
                          title="Like this story"
                          onclick="handleFeedScore(event, <?= $postId ?>, 5)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $isLiked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                    </svg>
                    <span class="like-counter" data-post-id="<?= $postId ?>"><?= $scoreCount ?></span>
                  </button>

                  <!-- Dislike / Downvote -->
                  <button type="button"
                          class="feed-action-btn btn-feed-dislike <?= $isDisliked ? 'active' : '' ?>"
                          data-post-id="<?= $postId ?>"
                          title="Dislike"
                          onclick="handleFeedScore(event, <?= $postId ?>, 1)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $isDisliked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path>
                    </svg>
                  </button>

                  <!-- Comments / Discussion -->
                  <a href="/article/<?= $postId ?>#commentsSection"
                     class="feed-action-btn feed-comments-btn"
                     data-post-id="<?= $postId ?>"
                     data-open-comments="1"
                     title="View discussion"
                     onclick="openCommentsDrawer(event, <?= $postId ?>)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span class="feed-comment-counter" data-post-id="<?= $postId ?>"><?= $commentCount ?></span>
                  </a>

                  <!-- Share Button (Opens Share Modal with Contacts & WhatsApp) -->
                  <button type="button"
                          class="feed-action-btn btn-feed-share"
                          title="Share story"
                          onclick="openShareModal('<?= htmlspecialchars($canonicalPostUrl, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>', <?= $postId ?>)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <circle cx="18" cy="5" r="3"></circle>
                      <circle cx="6" cy="12" r="3"></circle>
                      <circle cx="18" cy="19" r="3"></circle>
                      <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                      <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                    <span>Share</span>
                  </button>

                  <!-- Save / Bookmark Button -->
                  <button type="button"
                          class="feed-action-btn btn-feed-bookmark <?= $isBookmarked ? 'active' : '' ?>"
                          data-post-id="<?= $postId ?>"
                          title="<?= $isBookmarked ? 'Remove bookmark' : 'Bookmark story' ?>"
                          onclick="toggleFeedBookmark(event, <?= $postId ?>)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $isBookmarked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span class="bookmark-label"><?= $isBookmarked ? 'Saved' : 'Save' ?></span>
                  </button>

                  <!-- Remix Button -->
                  <a href="/posts/new?remix=<?= $postId ?>" class="feed-action-btn btn-feed-remix" title="Remix this article with attribution">
                    <span>🔀</span>
                  </a>

                  <!-- Report Button -->
                  <button type="button"
                          class="feed-action-btn btn-feed-report"
                          title="Report post"
                          onclick="openReportPostModal(<?= $postId ?>, '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                      <line x1="4" y1="22" x2="4" y2="15"></line>
                    </svg>
                  </button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

  </div><!-- /homepage-main-column -->


  <!-- Right Sidebar (~320-350px width): People You Know, Popular Communities & Trending Topics -->
  <aside class="homepage-sidebar" aria-label="People You Know, Communities, and Topics">

    <!-- Card 1: People You Know -->
    <div class="glass-panel sidebar-card sidebar-card-authors">
      <div class="sidebar-card-header">
        <div class="sidebar-card-title-group">
          <span class="sidebar-card-icon" aria-hidden="true">👥</span>
          <h3 class="sidebar-card-title">People You Know</h3>
        </div>
      </div>

      <?php if (empty($sidebarAuthors)): ?>
        <div class="sidebar-empty-state">
          <p class="sidebar-empty-text">No author recommendations available at this time. Check back soon!</p>
          <a href="/authors" class="btn btn-secondary btn-sm btn-pill" style="margin-top: 8px;">Discover Authors</a>
        </div>
      <?php else: ?>
        <div class="sidebar-item-list">
          <?php foreach ($sidebarAuthors as $author): ?>
            <?php
              // Server-side invariant: ensure viewer cannot appear in recommendations under any circumstance
              if (!empty($currentUser) && (int) ($author['id'] ?? 0) === (int) ($currentUser['id'] ?? 0)) {
                  continue;
              }
              $authorAvatar = $author['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($author['avatar_path'] ?? null);
              $authorInitial = strtoupper(mb_substr($author['display_name'] ?? 'A', 0, 1));
              $isFollowing = !empty($author['is_following']);
              $followerCount = (int) ($author['follower_count'] ?? 0);
            ?>
            <div class="sidebar-row sidebar-author-row" data-author-id="<?= (int) $author['id'] ?>">
              <a href="/author/<?= (int) $author['id'] ?>" class="sidebar-avatar-link" title="View <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>'s profile">
                <?php if (!empty($authorAvatar)): ?>
                  <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>"
                       alt="<?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>"
                       class="sidebar-avatar-img"
                       width="42" height="42"
                       onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="user-avatar sidebar-avatar-fallback" style="display: none; width: 42px; height: 42px; font-size: 0.95rem; font-weight: 700;">
                    <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php else: ?>
                  <div class="user-avatar sidebar-avatar-fallback" style="width: 42px; height: 42px; font-size: 0.95rem; font-weight: 700;">
                    <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
              </a>

              <div class="sidebar-row-info">
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; overflow: hidden;">
                  <a href="/author/<?= (int) $author['id'] ?>" class="sidebar-row-title" title="<?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                  <?php if (!empty($author['has_author_badge'])): ?>
                    <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                  <?php endif; ?>
                </div>
                <div class="sidebar-row-meta">
                  <span class="sidebar-row-sub"><?= htmlspecialchars($author['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($author['username'] ?? null, $author['display_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="sidebar-meta-dot">·</span>
                  <span class="sidebar-follower-meta"><span class="author-follower-count" data-author-id="<?= (int) $author['id'] ?>"><?= $followerCount ?></span> <?= $followerCount === 1 ? 'follower' : 'followers' ?></span>
                </div>
              </div>

              <div class="sidebar-row-action">
                <button type="button"
                        class="btn btn-primary btn-sm btn-pill btn-follow-toggle <?= $isFollowing ? 'following' : '' ?>"
                        data-author-id="<?= (int) $author['id'] ?>"
                        data-following="<?= $isFollowing ? '1' : '0' ?>"
                        aria-label="<?= $isFollowing ? 'Unfollow' : 'Follow' ?> <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>">
                  <?php if ($isFollowing): ?>
                    <span>✓</span> Following
                  <?php else: ?>
                    <span>+</span> Follow
                  <?php endif; ?>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="sidebar-card-footer">
        <a href="/authors" class="sidebar-card-footer-link">Discover Authors →</a>
      </div>
    </div>


    <!-- Card 2: Popular Communities -->
    <div class="glass-panel sidebar-card sidebar-card-communities">
      <div class="sidebar-card-header">
        <div class="sidebar-card-title-group">
          <span class="sidebar-card-icon" aria-hidden="true">💬</span>
          <h3 class="sidebar-card-title">Popular Communities</h3>
        </div>
      </div>

      <?php if (empty($sidebarCommunities)): ?>
        <div class="sidebar-empty-state">
          <p class="sidebar-empty-text">No active communities found right now. Check back soon!</p>
          <a href="/communities" class="btn btn-secondary btn-sm btn-pill" style="margin-top: 8px;">Explore Communities</a>
        </div>
      <?php else: ?>
        <div class="sidebar-item-list">
          <?php foreach ($sidebarCommunities as $comm): ?>
            <?php
              $isMember = !empty($comm['is_member']);
              $memberCount = (int) ($comm['member_count'] ?? 0);
              $commCleanName = \InfinityBlog\Community\CommunityNameFormatter::format($comm['name'] ?? '');
            ?>
            <div class="sidebar-row sidebar-community-row" data-room-id="<?= (int) $comm['id'] ?>" data-slug="<?= htmlspecialchars($comm['slug'], ENT_QUOTES, 'UTF-8') ?>">
              <a href="/community/<?= urlencode($comm['slug']) ?>" class="sidebar-community-icon-tile" style="padding: 0; background: transparent; border: none; box-shadow: none;" aria-label="Open <?= htmlspecialchars($commCleanName, ENT_QUOTES, 'UTF-8') ?> community discussion">
                <?= \InfinityBlog\Community\CommunityLogoHelper::renderLogoHtml($comm, 38) ?>
              </a>

              <div class="sidebar-row-info">
                <a href="/community/<?= urlencode($comm['slug']) ?>" class="sidebar-row-title" title="<?= htmlspecialchars($commCleanName, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($commCleanName, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php if (!empty($comm['description'])): ?>
                  <div class="sidebar-row-desc" title="<?= htmlspecialchars($comm['description'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($comm['description'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php elseif (!empty($comm['category_name'])): ?>
                  <div class="sidebar-row-desc">
                    <?= htmlspecialchars($comm['category_name'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
                <div class="sidebar-row-meta">
                  <span class="community-member-count" data-slug="<?= htmlspecialchars($comm['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $memberCount ?> <?= $memberCount === 1 ? 'member' : 'members' ?>
                  </span>
                  <?php if (!empty($comm['message_count'])): ?>
                    <span class="sidebar-meta-dot">·</span>
                    <span><?= (int) $comm['message_count'] ?> messages</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="sidebar-row-action">
                <button type="button"
                        class="btn btn-primary btn-sm btn-pill btn-community-join <?= $isMember ? 'joined' : '' ?>"
                        data-slug="<?= htmlspecialchars($comm['slug'], ENT_QUOTES, 'UTF-8') ?>"
                        data-room-id="<?= (int) $comm['id'] ?>"
                        data-is-member="<?= $isMember ? '1' : '0' ?>"
                        aria-label="<?= $isMember ? 'Joined' : 'Join' ?> <?= htmlspecialchars($commCleanName, ENT_QUOTES, 'UTF-8') ?>">
                  <?php if ($isMember): ?>
                    <span>✓</span> Joined
                  <?php else: ?>
                    <span>+</span> Join
                  <?php endif; ?>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="sidebar-card-footer">
        <a href="/communities" class="sidebar-card-footer-link">Explore Communities →</a>
      </div>
    </div>


    <!-- Card 3: Trending Topics -->
    <div class="glass-panel sidebar-card sidebar-card-topics">
      <div class="sidebar-card-header">
        <div class="sidebar-card-title-group">
          <span class="sidebar-card-icon" aria-hidden="true">🔥</span>
          <h3 class="sidebar-card-title">Trending Topics</h3>
        </div>
      </div>

      <?php if (empty($trendingTopics)): ?>
        <div class="sidebar-empty-state">
          <p class="sidebar-empty-text">No trending topics available right now.</p>
          <a href="/categories" class="btn btn-secondary btn-sm btn-pill" style="margin-top: 8px;">Browse Categories</a>
        </div>
      <?php else: ?>
        <div class="sidebar-item-list sidebar-topics-list">
          <?php foreach ($trendingTopics as $rank => $topic): ?>
            <?php
              $rankNum = $rank + 1;
              $postCount = (int) ($topic['post_count'] ?? 0);
              $topicSlug = $topic['slug'] ?? '';
              $topicName = $topic['name'] ?? '';
            ?>
            <a href="/?category=<?= urlencode($topicSlug) ?>" class="sidebar-topic-row" title="Explore #<?= htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') ?>">
              <span class="topic-rank-badge"><?= $rankNum ?></span>
              <div class="sidebar-topic-info">
                <span class="sidebar-topic-name">#<?= htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="sidebar-topic-meta"><?= $postCount ?> <?= $postCount === 1 ? 'post' : 'posts' ?></span>
              </div>
              <span class="sidebar-topic-trend" aria-label="Trending up">↗</span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="sidebar-card-footer">
        <a href="/categories" class="sidebar-card-footer-link">See All Topics →</a>
      </div>
    </div>

  </aside><!-- /homepage-sidebar -->

</div><!-- /homepage-two-column-layout -->
