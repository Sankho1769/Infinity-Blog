<!-- Explore Page: Discover & Browse Articles -->
<div class="content-header content-page-hero">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Discover & Learn</span>
  <h1 style="font-size: 2.3rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Explore Articles</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 620px; margin: 10px auto 0;">
    Search topics, filter by category, and discover thoughtful perspectives from authors across Infinity Blog.
  </p>

  <!-- Centered Explore Search Bar -->
  <form action="/explore" method="GET" class="hero-search-bar" role="search" style="max-width: 580px; margin: 24px auto 0;">
    <?php if (!empty($activeCategorySlug)): ?>
      <input type="hidden" name="category" value="<?= htmlspecialchars($activeCategorySlug, ENT_QUOTES, 'UTF-8') ?>">
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

<!-- Category Pills Filter Bar -->
<?php if (!empty($categories)): ?>
  <div class="category-filter-bar" style="margin-bottom: 32px;">
    <a href="/explore<?= !empty($searchQuery) ? '?search=' . urlencode($searchQuery) : '' ?>" class="filter-pill <?= empty($activeCategorySlug) ? 'active' : '' ?>">All Categories</a>
    <?php foreach ($categories as $cat): ?>
      <a href="/explore?category=<?= urlencode($cat['slug']) ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="filter-pill <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'active' : '' ?>">
        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
        <?php if (!empty($cat['post_count'])): ?>
          <span style="font-size: 0.75rem; opacity: 0.75; margin-left: 2px;">(<?= (int) $cat['post_count'] ?>)</span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
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
          <?= htmlspecialchars($activeCategoryName, ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
          All Published Articles
        <?php endif; ?>
      </h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
        Showing <?= count($posts) ?> of <?= (int) ($totalPosts ?? count($posts)) ?> published <?= ((int) ($totalPosts ?? count($posts)) === 1) ? 'article' : 'articles' ?>
      </p>
    </div>

    <?php if (!empty($searchQuery) || !empty($activeCategorySlug)): ?>
      <a href="/explore" class="section-link">Clear filters ×</a>
    <?php endif; ?>
  </div>

  <?php if (empty($posts)): ?>
    <div class="glass-panel empty-state" style="padding: 60px 24px; text-align: center;">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 52px; height: 52px; margin: 0 auto 16px; color: var(--text-muted);">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
      </svg>
      <h3 class="empty-title" style="font-size: 1.35rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">No matching articles found</h3>
      <p class="empty-desc" style="color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">
        We couldn't find any published articles matching your search or category filter. Try refining your keywords or browse all categories.
      </p>
      <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="/explore" class="btn btn-secondary btn-pill">Browse All Articles</a>
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
          $thumbnailUrl = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, $idx);
        ?>
        <article class="glass-panel glass-panel-hover article-card">
          <div class="article-card-banner has-image">
            <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="article-banner-img" loading="lazy">
            <div class="article-banner-badge-wrapper">
              <?php if (!empty($post['category_name'])): ?>
                <span class="badge badge-category"><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php else: ?>
                <span class="badge badge-published">Published</span>
              <?php endif; ?>
              <?php if (!empty($post['remixed_from_post_id'])): ?>
                <span class="badge badge-remix" title="Remixed from an earlier article">🔀 Remix</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="article-card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <time class="article-date-meta">
                <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
              </time>
              <?php if (!empty($post['reader_score']['count']) && $post['reader_score']['count'] > 0): ?>
                <span class="badge badge-score" title="Reader Score: <?= number_format($post['reader_score']['average'], 1) ?> out of 5 based on <?= (int) $post['reader_score']['count'] ?> scores">
                  ★ <?= number_format($post['reader_score']['average'], 1) ?> (<?= (int) $post['reader_score']['count'] ?>)
                </span>
              <?php endif; ?>
            </div>

            <h3 class="article-card-title">
              <a href="/article/<?= (int) $post['id'] ?>">
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
                <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" class="user-avatar" style="width: 26px; height: 26px; font-size: 0.75rem; text-decoration: none;" title="View Author Profile">
                  <?= strtoupper(substr($post['author_name'] ?? 'Cristine Palmer', 0, 1)) ?>
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
  <?php endif; ?>
</section>
