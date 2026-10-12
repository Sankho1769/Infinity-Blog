<!-- Screen 1: Public Homepage -->
<section class="hero-section">
  <div class="hero-content">
    <h1 class="hero-headline">Ideas.<br>Stories.<br><span class="brand-text-accent">Perspectives.</span></h1>
    <p class="hero-subhead">Discover insightful articles on technology, lifestyle, education and more from passionate writers.</p>

    <form action="/" method="GET" class="hero-search-bar" role="search">
      <input type="search" name="search" class="hero-search-input" placeholder="Search articles by title or keyword..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles">
      <button type="submit" class="btn btn-primary btn-pill btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        Search
      </button>
    </form>
  </div>

  <div class="hero-visual" aria-hidden="true">
    <div class="hero-card-stack">
      <div class="hero-floating-card card-1">
        <span class="badge badge-category" style="margin-bottom: 8px;">Featured</span>
        <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">Modern Web Development</h4>
        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">A beginner-friendly guide to building modern scalable web applications.</p>
      </div>
      <div class="hero-floating-card card-2">
        <span class="badge badge-published" style="margin-bottom: 8px;">Trending</span>
        <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">The Power of Focus</h4>
        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">Daily strategies to improve mental clarity, deep work and creative output.</p>
      </div>
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
<section id="articles" style="margin-top: 16px;">
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
    
    <?php if (!empty($searchQuery) || !empty($activeCategorySlug)): ?>
      <a href="/" class="section-link">Clear filters ×</a>
    <?php endif; ?>
  </div>

  <?php if (empty($posts)): ?>
    <div class="glass-panel empty-state">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
      </svg>
      <h3 class="empty-title">No articles found</h3>
      <p class="empty-desc">There are no published articles matching your criteria right now. Check back soon!</p>
      <?php if (!empty($currentUser)): ?>
        <a href="/posts/new" class="btn btn-primary btn-pill">Write First Post</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="articles-grid">
      <?php foreach ($posts as $post): ?>
        <article class="glass-panel glass-panel-hover article-card">
          <div class="article-card-header">
            <?php if (!empty($post['category_name'])): ?>
              <span class="badge badge-category"><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
              <span class="badge badge-published">Published</span>
            <?php endif; ?>
            <time style="font-size: 0.8rem; color: var(--text-subtle);">
              <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
            </time>
          </div>

          <h3 class="article-card-title">
            <a href="/article/<?= (int) $post['id'] ?>" style="color: inherit; text-decoration: none;">
              <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </h3>

          <p class="article-card-excerpt">
            <?php
              $excerpt = !empty($post['summary']) ? $post['summary'] : strip_tags($post['content'] ?? '');
              echo htmlspecialchars(mb_substr($excerpt, 0, 140) . (mb_strlen($excerpt) > 140 ? '...' : ''), ENT_QUOTES, 'UTF-8');
            ?>
          </p>

          <div class="article-card-footer">
            <div class="article-author-info">
              <div class="user-avatar" style="width: 24px; height: 24px; font-size: 0.75rem;">
                <?= strtoupper(substr($post['author_name'] ?? 'Author', 0, 1)) ?>
              </div>
              <span><?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            
            <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.8rem;">
              Read Story →
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
