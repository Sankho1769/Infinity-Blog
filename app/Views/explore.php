<!-- Screen: Explore & Advanced Discovery -->
<div class="content-header content-page-hero">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Discover & Learn</span>
  <h1 style="font-size: 2.3rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Explore Articles</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 620px; margin: 10px auto 0;">
    Search topics, filter by category, author, date, and reading time to discover thoughtful perspectives across Infinity Blog.
  </p>

  <!-- Centered Explore Quick Search Bar -->
  <form action="/explore" method="GET" class="hero-search-bar" role="search" style="max-width: 580px; margin: 24px auto 0;">
    <?php if (!empty($activeCategorySlug)): ?>
      <input type="hidden" name="category" value="<?= htmlspecialchars($activeCategorySlug, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!empty($activeAuthorId)): ?>
      <input type="hidden" name="author" value="<?= (int) $activeAuthorId ?>">
    <?php endif; ?>
    <?php if (!empty($activeReadingTime)): ?>
      <input type="hidden" name="reading_time" value="<?= htmlspecialchars($activeReadingTime, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!empty($activeSort) && $activeSort !== 'newest'): ?>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($activeSort, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <input type="search" name="search" class="hero-search-input" placeholder="Search by keyword, topic, or title..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles">
    <button type="submit" class="btn btn-primary btn-pill btn-sm">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      Search
    </button>
  </form>
</div>

<!-- Category Carousel Filter Bar -->
<?php if (!empty($categories)): ?>
  <?php
    $baseCategoryQuery = $currentQueryParams ?? [];
    unset($baseCategoryQuery['category'], $baseCategoryQuery['page']);
  ?>
  <div class="category-carousel-wrapper" role="region" aria-label="Category Filters" style="margin-bottom: 24px;">
    <button type="button" class="category-scroll-btn category-scroll-prev" aria-label="Scroll categories left" style="display: none;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </button>
    <div class="category-filter-bar">
      <a href="/explore<?= !empty($baseCategoryQuery) ? '?' . http_build_query($baseCategoryQuery) : '' ?>" class="filter-pill <?= empty($activeCategorySlug) && empty($forYou) ? 'active' : '' ?>">
        <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon(null, 15) ?>
        <span>All Categories</span>
      </a>
      <?php if (!empty($currentUser)): ?>
        <?php
          $forYouQuery = $baseCategoryQuery;
          if (empty($forYou)) {
              $forYouQuery['for_you'] = '1';
          } else {
              unset($forYouQuery['for_you']);
          }
        ?>
        <a href="/explore<?= !empty($forYouQuery) ? '?' . http_build_query($forYouQuery) : '' ?>" class="filter-pill <?= !empty($forYou) ? 'active' : '' ?>" style="<?= !empty($forYou) ? 'background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff;' : '' ?>">
          <span>✨</span>
          <span>For You</span>
        </a>
      <?php endif; ?>
      <?php foreach ($categories as $cat): ?>
        <?php
          $catQuery = array_merge($baseCategoryQuery, ['category' => $cat['slug']]);
        ?>
        <a href="/explore?<?= http_build_query($catQuery) ?>" class="filter-pill <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'active' : '' ?>">
          <?= \InfinityBlog\Categories\CategoryIconHelper::renderIcon($cat, 15) ?>
          <span><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php if (!empty($cat['post_count'])): ?>
            <span style="font-size: 0.75rem; opacity: 0.75; margin-left: 2px;">(<?= (int) $cat['post_count'] ?>)</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <button type="button" class="category-scroll-btn category-scroll-next" aria-label="Scroll categories right">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>
  </div>
<?php endif; ?>

<!-- Mobile Filter Button Trigger -->
<div style="margin-bottom: 16px; display: flex; justify-content: flex-end;">
  <button type="button" class="mobile-filter-drawer-toggle btn btn-secondary btn-pill btn-sm" id="openMobileFiltersBtn" aria-label="Open filter options">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
    Filter & Sort
    <?php if (!empty($activeFilterChips)): ?>
      <span style="background: var(--primary-color); color: #fff; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; margin-left: 4px;"><?= count($activeFilterChips) ?></span>
    <?php endif; ?>
  </button>
</div>

<!-- Desktop Filter Toolbar -->
<form action="/explore" method="GET" class="explore-filter-toolbar" id="desktopFilterForm">
  <?php if (!empty($searchQuery)): ?>
    <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <div class="filter-toolbar-grid">
    <!-- Category Select -->
    <div class="filter-field-group">
      <label class="filter-field-label" for="filter-category">Category</label>
      <select name="category" id="filter-category" class="filter-select">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Author Select -->
    <div class="filter-field-group">
      <label class="filter-field-label" for="filter-author">Author</label>
      <select name="author" id="filter-author" class="filter-select">
        <option value="">All Authors</option>
        <?php foreach ($authors as $auth): ?>
          <option value="<?= (int) $auth['id'] ?>" <?= ((int) ($activeAuthorId ?? 0)) === (int) $auth['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($auth['display_name'] ?? $auth['username'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Reading Time Select -->
    <div class="filter-field-group">
      <label class="filter-field-label" for="filter-reading-time">Reading Time</label>
      <select name="reading_time" id="filter-reading-time" class="filter-select">
        <option value="">Any Length</option>
        <option value="quick" <?= ($activeReadingTime ?? '') === 'quick' ? 'selected' : '' ?>>Quick (&lt; 4 min)</option>
        <option value="medium" <?= ($activeReadingTime ?? '') === 'medium' ? 'selected' : '' ?>>Medium (4–8 min)</option>
        <option value="deep" <?= ($activeReadingTime ?? '') === 'deep' ? 'selected' : '' ?>>Deep Read (&gt; 8 min)</option>
      </select>
    </div>

    <!-- Date From & To -->
    <div class="filter-field-group">
      <label class="filter-field-label" for="filter-date-from">From Date</label>
      <input type="date" name="date_from" id="filter-date-from" class="filter-input" value="<?= htmlspecialchars($activeDateFrom ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="filter-field-group">
      <label class="filter-field-label" for="filter-date-to">To Date</label>
      <input type="date" name="date_to" id="filter-date-to" class="filter-input" value="<?= htmlspecialchars($activeDateTo ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <!-- Sort Dropdown -->
    <div class="filter-field-group">
      <label class="filter-field-label" for="filter-sort">Sort By</label>
      <select name="sort" id="filter-sort" class="filter-select">
        <option value="newest" <?= ($activeSort ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
        <option value="oldest" <?= ($activeSort ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
        <option value="popular" <?= ($activeSort ?? '') === 'popular' ? 'selected' : '' ?>>Most Popular</option>
        <option value="highest_rated" <?= ($activeSort ?? '') === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
        <option value="relevance" <?= ($activeSort ?? '') === 'relevance' ? 'selected' : '' ?>>Most Relevant</option>
      </select>
    </div>

    <!-- Action Buttons -->
    <div class="filter-actions-group" style="padding-bottom: 2px;">
      <button type="submit" class="btn btn-primary btn-sm btn-pill" style="height: 38px;">Apply Filters</button>
      <a href="/explore" class="btn btn-secondary btn-sm btn-pill" style="height: 38px; display: inline-flex; align-items: center;">Reset</a>
    </div>
  </div>
</form>

<!-- Mobile Filter Drawer Backdrop & Drawer -->
<div class="mobile-filter-backdrop" id="mobileFilterBackdrop"></div>
<div class="mobile-filter-drawer" id="mobileFilterDrawer">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--glass-border);">
    <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-dark);">Filter Articles</h3>
    <button type="button" id="closeMobileFiltersBtn" class="alert-close-btn" aria-label="Close filters" style="font-size: 1.4rem;">×</button>
  </div>

  <form action="/explore" method="GET" style="display: flex; flex-direction: column; gap: 16px;">
    <?php if (!empty($searchQuery)): ?>
      <div class="filter-field-group">
        <label class="filter-field-label" for="m-filter-search">Search Keyword</label>
        <input type="search" name="search" id="m-filter-search" class="filter-input" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
      </div>
    <?php endif; ?>

    <div class="filter-field-group">
      <label class="filter-field-label" for="m-filter-category">Category</label>
      <select name="category" id="m-filter-category" class="filter-select">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-field-group">
      <label class="filter-field-label" for="m-filter-author">Author</label>
      <select name="author" id="m-filter-author" class="filter-select">
        <option value="">All Authors</option>
        <?php foreach ($authors as $auth): ?>
          <option value="<?= (int) $auth['id'] ?>" <?= ((int) ($activeAuthorId ?? 0)) === (int) $auth['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($auth['display_name'] ?? $auth['username'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-field-group">
      <label class="filter-field-label" for="m-filter-reading-time">Reading Time</label>
      <select name="reading_time" id="m-filter-reading-time" class="filter-select">
        <option value="">Any Length</option>
        <option value="quick" <?= ($activeReadingTime ?? '') === 'quick' ? 'selected' : '' ?>>Quick (&lt; 4 min)</option>
        <option value="medium" <?= ($activeReadingTime ?? '') === 'medium' ? 'selected' : '' ?>>Medium (4–8 min)</option>
        <option value="deep" <?= ($activeReadingTime ?? '') === 'deep' ? 'selected' : '' ?>>Deep Read (&gt; 8 min)</option>
      </select>
    </div>

    <div class="filter-field-group">
      <label class="filter-field-label" for="m-filter-date-from">From Date</label>
      <input type="date" name="date_from" id="m-filter-date-from" class="filter-input" value="<?= htmlspecialchars($activeDateFrom ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="filter-field-group">
      <label class="filter-field-label" for="m-filter-date-to">To Date</label>
      <input type="date" name="date_to" id="m-filter-date-to" class="filter-input" value="<?= htmlspecialchars($activeDateTo ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="filter-field-group">
      <label class="filter-field-label" for="m-filter-sort">Sort By</label>
      <select name="sort" id="m-filter-sort" class="filter-select">
        <option value="newest" <?= ($activeSort ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
        <option value="oldest" <?= ($activeSort ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
        <option value="popular" <?= ($activeSort ?? '') === 'popular' ? 'selected' : '' ?>>Most Popular</option>
        <option value="highest_rated" <?= ($activeSort ?? '') === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
        <option value="relevance" <?= ($activeSort ?? '') === 'relevance' ? 'selected' : '' ?>>Most Relevant</option>
      </select>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 12px;">
      <button type="submit" class="btn btn-primary btn-pill btn-full">Apply Filters</button>
      <a href="/explore" class="btn btn-secondary btn-pill" style="text-align: center;">Reset</a>
    </div>
  </form>
</div>

<!-- Active Filter Chips Row -->
<?php if (!empty($activeFilterChips)): ?>
  <div class="filter-chips-row" role="region" aria-label="Active filters">
    <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-right: 4px;">Active Filters:</span>
    <?php foreach ($activeFilterChips as $chip): ?>
      <a href="<?= htmlspecialchars($chip['remove_url'], ENT_QUOTES, 'UTF-8') ?>" class="filter-chip" title="Click to remove this filter">
        <span><?= htmlspecialchars($chip['label'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="filter-chip-remove" aria-hidden="true">×</span>
      </a>
    <?php endforeach; ?>
    <a href="/explore" class="filter-chips-clear-all">Clear all filters</a>
  </div>
<?php endif; ?>

<!-- Explore Articles Grid Section -->
<section id="explore-results">
  <div class="section-header" style="margin-bottom: 24px;">
    <div>
      <h2 class="section-title">
        <?php if (!empty($searchQuery)): ?>
          Search Results for "<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
        <?php elseif (!empty($activeCategoryName)): ?>
          <?= htmlspecialchars($activeCategoryName, ENT_QUOTES, 'UTF-8') ?> Articles
        <?php else: ?>
          All Published Articles
        <?php endif; ?>
      </h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
        Showing <?= count($posts) ?> of <?= (int) ($totalPosts ?? count($posts)) ?> published <?= ((int) ($totalPosts ?? count($posts)) === 1) ? 'article' : 'articles' ?>
        <?php if (($totalPages ?? 1) > 1): ?>
          (Page <?= (int) ($page ?? 1) ?> of <?= (int) $totalPages ?>)
        <?php endif; ?>
      </p>
    </div>

    <?php if (!empty($activeFilterChips)): ?>
      <a href="/explore" class="section-link">Clear filters ×</a>
    <?php endif; ?>
  </div>

  <?php if (empty($posts)): ?>
    <div class="glass-panel empty-state" style="padding: 32px 20px; text-align: center;">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 52px; height: 52px; margin: 0 auto 16px; color: var(--text-muted);">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
      </svg>
      <h3 class="empty-title" style="font-size: 1.35rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">No matching articles found</h3>
      <p class="empty-desc" style="color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">
        We couldn't find any published articles matching your selected criteria. Try adjusting your keyword, categories, or date range.
      </p>
      <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="/explore" class="btn btn-secondary btn-pill">Reset All Filters</a>
        <?php if (!empty($currentUser)): ?>
          <a href="/posts/new" class="btn btn-primary btn-pill">Write New Post</a>
        <?php else: ?>
          <a href="/register" class="btn btn-primary btn-pill">Create Account to Write</a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="articles-grid">
      <?php foreach ($posts as $idx => $post): ?>
        <?php
          $postId = (int) $post['id'];
          $canonicalPostUrl = ($basePath ?? '') . '/article/' . $postId;
          $thumbnailUrl = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, $idx);
        ?>
        <article class="glass-panel glass-panel-hover article-card"
                 data-post-id="<?= $postId ?>"
                 data-article-url="<?= $canonicalPostUrl ?>"
                 tabindex="0"
                 role="article"
                 aria-label="Article: <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($thumbnailUrl)): ?>
            <div class="article-card-banner has-image">
              <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . $postId) ?>" class="article-banner-link" aria-label="Read <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="article-banner-img" width="360" height="200" loading="lazy" onerror="this.onerror=null; this.closest('.article-card-banner').style.display='none';">
              </a>
              <div class="article-banner-badge-wrapper">
                <?= \InfinityBlog\Posts\ContentTypeHelper::renderBadge($post['content_type'] ?? 'article') ?>
                <?php if (!empty($post['category_name'])): ?>
                  <a href="/explore?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="badge badge-category" style="text-decoration: none;" title="Browse category: <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($post['category_slug'] ?? '', 13) ?>
                    <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                <?php else: ?>
                  <span class="badge badge-published">Published</span>
                <?php endif; ?>
                <?php if (!empty($post['remixed_from_post_id'])): ?>
                  <span class="badge badge-remix" title="Remixed from an earlier article">🔀 Remix</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="article-card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <?php if (empty($thumbnailUrl)): ?>
                  <?= \InfinityBlog\Posts\ContentTypeHelper::renderBadge($post['content_type'] ?? 'article') ?>
                <?php endif; ?>
                <?php if (empty($thumbnailUrl) && !empty($post['category_name'])): ?>
                  <a href="/explore?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="badge badge-category" style="text-decoration: none;" title="Browse category: <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($post['category_slug'] ?? '', 13) ?>
                    <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                <?php endif; ?>
                <time class="article-date-meta">
                  <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
                </time>
                <span style="font-size: 0.78rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 3px;" title="Estimated reading time">
                  ⏱️ <?= $post['reading_time'] ?? max(1, (int) ceil(str_word_count(strip_tags($post['content'] ?? '')) / 200)) ?> min
                </span>
              </div>
              <div>
                <?= \InfinityBlog\Support\RatingHelper::getRatingInfo($post['reader_score']['average'] ?? 0, $post['reader_score']['count'] ?? 0)['badge_html'] ?>
              </div>
            </div>

            <h3 class="article-card-title">
              <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . (int) $post['id']) ?>">
                <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </h3>

            <p class="article-card-excerpt">
              <?php
                $excerpt = !empty($post['summary']) ? $post['summary'] : strip_tags($post['content'] ?? '');
                echo htmlspecialchars(mb_substr($excerpt, 0, 130) . (mb_strlen($excerpt) > 130 ? '...' : ''), ENT_QUOTES, 'UTF-8');
              ?>
            </p>

            <div class="article-card-footer">
              <div class="article-author-info">
                <?php
                  $authorAvatar = $post['author_avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($post['avatar_path'] ?? ($post['author_avatar_path'] ?? null));
                  $authorInitial = strtoupper(mb_substr($post['author_name'] ?? 'A', 0, 1));
                ?>
                <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" class="feed-author-avatar-link" title="View Author Profile" style="text-decoration: none; display: inline-flex; align-items: center;">
                  <?php if (!empty($authorAvatar)): ?>
                    <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>"
                         class="feed-author-avatar-img"
                         width="26" height="26" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="user-avatar" style="display: none; width: 26px; height: 26px; font-size: 0.75rem; text-decoration: none;">
                      <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php else: ?>
                    <div class="user-avatar" style="width: 26px; height: 26px; font-size: 0.75rem; text-decoration: none;">
                      <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </a>
                <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" style="color: var(--text-dark); text-decoration: none; font-weight: 600;" title="View Author Profile">
                  <?= htmlspecialchars($post['author_name'] ?? 'Cristine Palmer', ENT_QUOTES, 'UTF-8') ?>
                </a>
              </div>

              <div style="display: flex; gap: 6px; align-items: center;">
                <a href="/posts/new?remix=<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 8px; font-size: 0.8rem;" title="Remix this article">
                  🔀
                </a>
                <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.8rem;">
                  Read Story →
                </a>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Pagination Controls (Preserves All Query Filters) -->
    <?php if (($totalPages ?? 1) > 1): ?>
      <?php
        $queryParams = $currentQueryParams ?? [];
        $currentPage = (int) ($page ?? 1);
      ?>
      <nav class="pagination-wrapper" aria-label="Explore pagination">
        <?php if ($currentPage > 1): ?>
          <?php $prevQuery = array_merge($queryParams, ['page' => $currentPage - 1]); ?>
          <a href="/explore?<?= http_build_query($prevQuery) ?>" class="pagination-btn" aria-label="Previous page">« Prev</a>
        <?php else: ?>
          <span class="pagination-btn disabled" aria-disabled="true">« Prev</span>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p === $currentPage): ?>
            <span class="pagination-btn active" aria-current="page"><?= $p ?></span>
          <?php elseif ($p <= 3 || $p >= $totalPages - 2 || abs($p - $currentPage) <= 1): ?>
            <?php $pageQuery = array_merge($queryParams, ['page' => $p]); ?>
            <a href="/explore?<?= http_build_query($pageQuery) ?>" class="pagination-btn"><?= $p ?></a>
          <?php elseif ($p === 4 && $currentPage > 4): ?>
            <span class="pagination-btn disabled">...</span>
          <?php elseif ($p === $totalPages - 3 && $currentPage < $totalPages - 3): ?>
            <span class="pagination-btn disabled">...</span>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
          <?php $nextQuery = array_merge($queryParams, ['page' => $currentPage + 1]); ?>
          <a href="/explore?<?= http_build_query($nextQuery) ?>" class="pagination-btn" aria-label="Next page">Next »</a>
        <?php else: ?>
          <span class="pagination-btn disabled" aria-disabled="true">Next »</span>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('openMobileFiltersBtn');
  const closeBtn = document.getElementById('closeMobileFiltersBtn');
  const drawer = document.getElementById('mobileFilterDrawer');
  const backdrop = document.getElementById('mobileFilterBackdrop');

  function openFilters() {
    if (drawer) drawer.classList.add('open');
    if (backdrop) backdrop.classList.add('open');
  }

  function closeFilters() {
    if (drawer) drawer.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
  }

  if (openBtn) openBtn.addEventListener('click', openFilters);
  if (closeBtn) closeBtn.addEventListener('click', closeFilters);
  if (backdrop) backdrop.addEventListener('click', closeFilters);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeFilters();
  });
});
</script>
