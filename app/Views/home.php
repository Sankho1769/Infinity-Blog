<!-- Screen 1: Public Homepage -->
<section class="hero-section">
  <div class="hero-content">
    <h1 class="hero-headline">Ideas. Stories.<br><span class="brand-text-accent">Perspectives.</span></h1>
    <p class="hero-subhead">Discover insightful articles on technology, lifestyle, education and more from passionate writers.</p>

    <form action="/" method="GET" class="hero-search-bar" role="search">
      <input type="search" name="search" class="hero-search-input" placeholder="Search articles by title or keyword..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles">
      <button type="submit" class="hero-search-btn" aria-label="Search articles">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>
  </div>

  <div class="hero-visual" aria-hidden="true">
    <div class="hero-illustration-wrapper">
      <img src="/assets/images/floating-blog-documents.png" class="hero-floating-illustration" alt="Infinity Blog editorial illustration">
    </div>
  </div>
</section>

<!-- Category Filter Bar -->
<?php if (!empty($categories)): ?>
  <div class="category-filter-bar">
    <a href="/" class="filter-pill <?= empty($activeCategorySlug) ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="/?category=<?= urlencode($cat['slug']) ?>" class="filter-pill <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'active' : '' ?>">
        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Latest Articles Section -->
<section id="articles" style="margin-top: 0;">
  <div class="section-header">
    <div>
      <h2 class="section-title">
        <?php if (!empty($searchQuery)): ?>
          Search Results for "<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
        <?php elseif (!empty($activeCategoryName)): ?>
          <?= htmlspecialchars($activeCategoryName, ENT_QUOTES, 'UTF-8') ?> Articles
        <?php else: ?>
          Latest Articles
        <?php endif; ?>
      </h2>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
        <?= count($posts) ?> <?= count($posts) === 1 ? 'article' : 'articles' ?> published
      </p>
    </div>

    <div style="display: flex; gap: 12px; align-items: center;">
      <?php if (!empty($searchQuery) || !empty($activeCategorySlug)): ?>
        <a href="/" class="section-link">Clear filters ×</a>
      <?php endif; ?>
      <a href="/explore" class="section-link" style="font-weight: 700;">Explore All →</a>
    </div>
  </div>

  <?php if (empty($posts)): ?>
    <div class="glass-panel empty-state-compact">
      <img src="/assets/images/empty-state-background.png" alt="No articles yet" class="empty-state-artwork" width="120" height="73">
      <h3 class="empty-title">No articles found</h3>
      <p class="empty-desc">There are no published articles matching your criteria right now. Check back soon!</p>
      <?php if (!empty($currentUser)): ?>
        <a href="/posts/new" class="btn btn-primary btn-pill btn-sm">+ Write First Post</a>
      <?php else: ?>
        <a href="/register" class="btn btn-primary btn-pill btn-sm">Join & Write</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="articles-grid">
      <?php foreach ($posts as $idx => $post): ?>
        <?php
          $thumbnailUrl = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, $idx);
        ?>
        <article class="glass-panel glass-panel-hover article-card">
          <!-- Card Visual Banner Header matching Reference 2 -->
          <div class="article-card-banner has-image">
            <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="article-banner-img" loading="lazy">
            <div class="article-banner-badge-wrapper">
              <?php if (!empty($post['category_name'])): ?>
                <span class="badge badge-category"><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php else: ?>
                <span class="badge badge-published">Published</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="article-card-body">
            <time class="article-date-meta">
              <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
            </time>

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

              <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.8rem;">
                Read Story →
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
