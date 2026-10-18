<!-- Screen 1: Public Homepage -->
<section class="hero-section">
  <div class="hero-content">
    <h1 class="hero-headline">Ideas worth sharing.<br><span class="brand-text-accent">Perspectives.</span></h1>
    <p class="hero-subhead">Discover thoughtful stories, connect with curious minds, and find your next idea across technology, creative arts, and engineering.</p>

    <div class="hero-actions" style="display: flex; gap: 12px; margin-top: 16px; margin-bottom: 20px; flex-wrap: wrap;">
      <?php if (!empty($currentUser)): ?>
        <a href="/posts/new" class="btn btn-primary btn-pill btn-md">+ Write a Story</a>
        <a href="/explore" class="btn btn-secondary btn-pill btn-md">Explore Stories →</a>
      <?php else: ?>
        <a href="/explore" class="btn btn-primary btn-pill btn-md">Start Reading Free →</a>
        <a href="/register" class="btn btn-secondary btn-pill btn-md">Join as Writer</a>
      <?php endif; ?>
    </div>

    <!-- Unified Homepage Search Bar -->
    <form action="/" method="GET" class="hero-search-bar" role="search" id="hero-search-form">
      <input type="search" name="search" id="hero-search-input" class="hero-search-input" placeholder="Search by title, author, keyword, or topic..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search articles" autocomplete="off">
      <button type="button" class="search-clear-btn" id="hero-search-clear" aria-label="Clear search query" style="<?= empty($searchQuery) ? 'display: none;' : '' ?>">×</button>
      <button type="submit" class="hero-search-btn" aria-label="Submit search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
    </form>
  </div>

  <div class="hero-visual" aria-hidden="true">
    <div class="hero-illustration-wrapper">
      <img src="/assets/images/floating-blog-documents.png" class="hero-floating-illustration" alt="Infinity Blog editorial illustration" width="460" height="340" fetchpriority="high">
    </div>
  </div>
</section>

<!-- Category Filter Bar -->
<?php if (!empty($categories)): ?>
  <div class="category-carousel-wrapper" role="region" aria-label="Category Filters">
    <button type="button" class="category-scroll-btn category-scroll-prev" aria-label="Scroll categories left" style="display: none;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </button>
    <div class="category-filter-bar">
      <a href="/" class="filter-pill <?= empty($activeCategorySlug) ? 'active' : '' ?>">
        <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon(null, 15) ?>
        <span>All Topics</span>
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="/?category=<?= urlencode($cat['slug']) ?>" class="filter-pill <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'active' : '' ?>">
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

<!-- Section D: Latest Articles Section -->
<section id="articles" style="margin-top: 8px;">
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
        <?= (int) ($totalPosts ?? count($posts)) ?> <?= ($totalPosts ?? count($posts)) === 1 ? 'article' : 'articles' ?> published
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
          <!-- Card Visual Banner Header with Fallback Gradient and Graceful Error Handling -->
          <div class="article-card-banner has-image">
            <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                 class="article-banner-img"
                 width="360"
                 height="200"
                 loading="lazy"
                 onerror="this.onerror=null; this.src='/assets/images/technology-article-laptop.png';">
            <div class="article-banner-badge-wrapper">
              <?php if (!empty($post['category_name'])): ?>
                <span class="badge badge-category">
                  <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($post['category_slug'] ?? '', 13) ?>
                  <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php else: ?>
                <span class="badge badge-published">Published</span>
              <?php endif; ?>
              <?php if (!empty($post['remixed_from_post_id'])): ?>
                <span class="badge badge-remix" title="Remixed from an earlier article">🔀 Remix</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="article-card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <time class="article-date-meta">
                  <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
                </time>
                <span style="font-size: 0.78rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 3px;" title="Estimated reading time">
                  ⏱️ <?= $post['reading_time'] ?? max(1, (int) ceil(str_word_count(strip_tags($post['content'] ?? '')) / 200)) ?> min
                </span>
              </div>
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
                  <?= strtoupper(substr($post['author_name'] ?? 'Author', 0, 1)) ?>
                </a>
                <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" style="color: var(--text-dark); text-decoration: none; font-weight: 600;" title="View Author Profile">
                  <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
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

<!-- Section E: Trending & Editor's Picks -->
<?php if (empty($searchQuery) && empty($activeCategorySlug) && !empty($trendingPosts)): ?>
  <section class="home-trending-section" style="margin-top: 48px; margin-bottom: 40px;">
    <div class="section-header">
      <div>
        <div class="badge badge-category" style="margin-bottom: 8px; display: inline-flex; align-items: center; gap: 5px;">
          <span>🔥</span> Popular Perspectives
        </div>
        <h2 class="section-title">Trending &amp; Highly Rated</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
          Stories sparking the most engagement and reader appreciation across Infinity Blog.
        </p>
      </div>
    </div>
    <div class="articles-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
      <?php foreach ($trendingPosts as $tIdx => $tPost): ?>
        <?php $tThumbnail = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($tPost, $tIdx + 10); ?>
        <article class="glass-panel glass-panel-hover article-card trending-card">
          <div class="article-card-banner has-image">
            <img src="<?= htmlspecialchars($tThumbnail, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($tPost['title'], ENT_QUOTES, 'UTF-8') ?>"
                 class="article-banner-img"
                 width="360"
                 height="200"
                 loading="lazy"
                 onerror="this.onerror=null; this.src='/assets/images/technology-article-laptop.png';">
            <div class="article-banner-badge-wrapper">
              <span class="badge badge-category">
                <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($tPost['category_slug'] ?? '', 13) ?>
                <?= htmlspecialchars($tPost['category_name'] ?? 'Featured', ENT_QUOTES, 'UTF-8') ?>
              </span>
              <?php if (!empty($tPost['reader_score']['average']) && $tPost['reader_score']['count'] > 0): ?>
                <span class="badge badge-score">★ <?= number_format($tPost['reader_score']['average'], 1) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="article-card-body">
            <h3 class="article-card-title">
              <a href="/article/<?= (int) $tPost['id'] ?>">
                <?= htmlspecialchars($tPost['title'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </h3>
            <p class="article-card-excerpt">
              <?php
                $tExcerpt = !empty($tPost['summary']) ? $tPost['summary'] : strip_tags($tPost['content'] ?? '');
                echo htmlspecialchars(mb_substr($tExcerpt, 0, 110) . (mb_strlen($tExcerpt) > 110 ? '...' : ''), ENT_QUOTES, 'UTF-8');
              ?>
            </p>
            <div class="article-card-footer">
              <div class="article-author-info">
                <a href="/author/<?= (int) ($tPost['author_id'] ?? 1) ?>" class="user-avatar" style="width: 24px; height: 24px; font-size: 0.72rem; text-decoration: none;">
                  <?= strtoupper(substr($tPost['author_name'] ?? 'A', 0, 1)) ?>
                </a>
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">
                  <?= htmlspecialchars($tPost['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
              <a href="/article/<?= (int) $tPost['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.8rem;">
                Read Story →
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Section F: Communities to Explore -->
<?php if (!empty($featuredCommunities)): ?>
  <section class="home-communities-section" style="margin-top: 48px; margin-bottom: 48px;">
    <div class="section-header">
      <div>
        <div class="badge badge-category" style="margin-bottom: 8px; display: inline-flex; align-items: center; gap: 5px; background: rgba(59, 130, 246, 0.1); color: var(--primary-color);">
          <span>💬</span> Active Communities
        </div>
        <h2 class="section-title">Communities to Explore</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
          Connect with curious minds, join topic-specific chat rooms, and exchange ideas in real time.
        </p>
      </div>
      <a href="/chat" class="section-link" style="font-weight: 700;">All Communities →</a>
    </div>

    <div class="communities-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
      <?php foreach ($featuredCommunities as $comm): ?>
        <div class="glass-panel glass-panel-hover community-preview-card" style="padding: 22px 20px; display: flex; flex-direction: column; justify-content: space-between; border-radius: var(--radius-lg);">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 12px;">
              <span class="badge badge-category" style="font-size: 0.78rem;">
                <?= htmlspecialchars($comm['category_name'] ?? 'Community', ENT_QUOTES, 'UTF-8') ?>
              </span>
              <span style="font-size: 0.78rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 4px;">
                👥 <?= (int) ($comm['member_count'] ?? 0) ?> members
              </span>
            </div>
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 8px; color: var(--text-dark);">
              <a href="/chat/<?= urlencode($comm['slug']) ?>" style="color: inherit; text-decoration: none;">
                #<?= htmlspecialchars($comm['name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </h3>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.45; margin-bottom: 16px;">
              <?= htmlspecialchars($comm['description'] ?: 'Open discussion and collaborative exchange for passionate community members.', ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 14px;">
            <span style="font-size: 0.8rem; color: var(--text-muted);">
              💬 <?= (int) ($comm['message_count'] ?? 0) ?> messages
            </span>
            <a href="/chat/<?= urlencode($comm['slug']) ?>" class="btn btn-primary btn-sm btn-pill" style="padding: 6px 14px; font-size: 0.82rem;">
              Join Discussion →
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Section G: Featured Authors -->
<?php if (!empty($featuredAuthors)): ?>
  <section class="home-authors-section" style="margin-top: 48px; margin-bottom: 48px;">
    <div class="section-header">
      <div>
        <div class="badge badge-category" style="margin-bottom: 8px; display: inline-flex; align-items: center; gap: 5px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
          <span>✍️</span> Voices of Infinity
        </div>
        <h2 class="section-title">Featured Authors</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
          Discover passionate writers, researchers, and engineers sharing authoritative insights.
        </p>
      </div>
      <a href="/authors" class="section-link" style="font-weight: 700;">Meet All Authors →</a>
    </div>

    <div class="authors-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
      <?php foreach ($featuredAuthors as $author): ?>
        <?php
          $authorAvatar = \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($author['avatar_path'] ?? null);
          $authorInitial = strtoupper(mb_substr($author['display_name'] ?? 'A', 0, 1));
        ?>
        <div class="glass-panel glass-panel-hover author-card" style="padding: 22px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; border-radius: var(--radius-lg);">
          <?php if (!empty($authorAvatar)): ?>
            <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>" style="width: 54px; height: 54px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; border: 2px solid var(--primary-color);" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
            <div class="user-avatar" style="display: none; width: 54px; height: 54px; border-radius: 50%; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; margin-bottom: 12px; background: var(--btn-primary-gradient); color: #fff;">
              <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php else: ?>
            <div class="user-avatar" style="width: 54px; height: 54px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; margin-bottom: 12px; background: var(--btn-primary-gradient); color: #fff;">
              <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>
          <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 2px; color: var(--text-dark);">
            <a href="/author/<?= (int) $author['id'] ?>" style="color: inherit; text-decoration: none;">
              <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </h3>
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px;">
            @<?= htmlspecialchars($author['username'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 14px; max-height: 2.8em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
            <?= htmlspecialchars($author['bio'] ?: 'Passionate contributor sharing stories and perspectives on Infinity Blog.', ENT_QUOTES, 'UTF-8') ?>
          </p>
          <div style="margin-top: auto; display: flex; align-items: center; justify-content: space-between; width: 100%; border-top: 1px solid var(--glass-border); padding-top: 12px;">
            <span style="font-size: 0.78rem; color: var(--text-muted);">
              📖 <?= (int) ($author['post_count'] ?? 0) ?> stories
            </span>
            <a href="/author/<?= (int) $author['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.78rem;">
              View Profile →
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
