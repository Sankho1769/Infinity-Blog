<!-- Screen 9: Author Profile Page (Public View) -->
<div class="breadcrumb-bar" style="margin-top: 16px; margin-bottom: 24px; font-size: 0.88rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
  <a href="/" style="color: var(--text-muted); text-decoration: none;">Home</a>
  <span>›</span>
  <a href="/authors" style="color: var(--text-muted); text-decoration: none;">Authors</a>
  <span>›</span>
  <span style="color: var(--primary-color); font-weight: 600;"><?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<!-- Author Bio Header Card -->
<div class="glass-panel" style="padding: 36px 32px; border-radius: var(--radius-xl); margin-bottom: 36px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 24px;">
  <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
    <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #4f46e5); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2.2rem; font-weight: 800; box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35); flex-shrink: 0;">
      <?= strtoupper(substr($author['display_name'] ?? 'C', 0, 1)) ?>
    </div>

    <div>
      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
        <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-dark); margin: 0; letter-spacing: -0.02em;">
          <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
      </div>

      <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 540px; margin: 6px 0 14px; line-height: 1.5;">
        I write about modern software architecture, web development, and thoughtful perspectives. Passionate about learning and sharing knowledge through articles.
      </p>

      <div style="display: flex; gap: 12px; align-items: center;">
        <span style="font-size: 0.85rem; color: var(--text-subtle);">@<?= htmlspecialchars($author['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <span style="color: var(--border-color);">·</span>
        <a href="https://twitter.com" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pill" style="padding: 3px 10px; font-size: 0.78rem;">Twitter</a>
        <a href="https://linkedin.com" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pill" style="padding: 3px 10px; font-size: 0.78rem;">LinkedIn</a>
      </div>
    </div>
  </div>

  <!-- Author Stats Counter -->
  <div style="display: flex; gap: 20px; background: var(--glass-bg-elevated); backdrop-filter: var(--glass-blur-sm); -webkit-backdrop-filter: var(--glass-blur-sm); padding: 18px 24px; border-radius: var(--radius-lg); border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow-sm);">
    <div style="text-align: center;">
      <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?= (int) $totalPosts ?></div>
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Articles</div>
    </div>
    <div style="width: 1px; background: rgba(203, 213, 225, 0.7);"></div>
    <div style="text-align: center;">
      <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a;"><?= (int) $totalPosts ?></div>
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Published</div>
    </div>
    <div style="width: 1px; background: rgba(203, 213, 225, 0.7);"></div>
    <div style="text-align: center;">
      <div style="font-size: 1.5rem; font-weight: 800; color: #6366f1;"><?= max(12, (int) $totalPosts * 14) ?></div>
      <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Readers</div>
    </div>
  </div>
</div>

<!-- Published Articles by Author -->
<div class="section-header" style="margin-bottom: 24px;">
  <div>
    <h2 class="section-title">Articles by <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
      <?= (int) $totalPosts ?> <?= (int) $totalPosts === 1 ? 'article' : 'articles' ?> published
    </p>
  </div>
  <a href="/authors" class="section-link">← All Authors</a>
</div>

<?php if (empty($posts)): ?>
  <div class="glass-panel empty-state">
    <div style="font-size: 2.5rem; margin-bottom: 12px;">✍️</div>
    <h3 class="empty-title">No articles published yet</h3>
    <p class="empty-desc">Check back soon for stories and perspectives from this author.</p>
    <a href="/" class="btn btn-primary btn-pill">Return to Home</a>
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
              echo htmlspecialchars(mb_substr($excerpt, 0, 120) . (mb_strlen($excerpt) > 120 ? '...' : ''), ENT_QUOTES, 'UTF-8');
            ?>
          </p>

          <div class="article-card-footer">
            <span style="font-size: 0.8rem; color: var(--text-muted);">
              <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
            </span>
            <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 12px; font-size: 0.8rem;">
              Read Story →
            </a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
