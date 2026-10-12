<!-- Screen 8: Single Post Page (Public View) -->
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
  <a href="/">Home</a>
  <span>›</span>
  <?php if (!empty($post['category_name'])): ?>
    <a href="/?category=<?= urlencode($post['category_slug'] ?? '') ?>">
      <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
    </a>
    <span>›</span>
  <?php endif; ?>
  <span style="color: var(--text-dark); font-weight: 500;">
    <?= htmlspecialchars(mb_substr($post['title'], 0, 30) . (mb_strlen($post['title']) > 30 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?>
  </span>
</nav>

<div class="article-detail-layout">
  <!-- Main Article Card -->
  <article class="glass-panel article-main-card">
    <header class="article-header">
      <?php if (!empty($post['category_name'])): ?>
        <span class="badge badge-category"><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
        <span class="badge badge-published">Published Article</span>
      <?php endif; ?>

      <h1 class="article-title-large">
        <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
      </h1>

      <div class="article-meta-row">
        <div class="article-author-info">
          <div class="user-avatar" style="width: 36px; height: 36px; font-size: 0.95rem;">
            <?= strtoupper(substr($post['author_name'] ?? 'Author', 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
              <?= date('F j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?> ·
              <?php
                $wordCount = str_word_count(strip_tags($post['content'] ?? ''));
                $minutes = max(1, (int) ceil($wordCount / 200));
                echo $minutes . ' min read';
              ?>
            </div>
          </div>
        </div>

        <?php if (!empty($currentUser) && ($currentUser['id'] === $post['author_id'] || $currentUser['role'] === 'admin')): ?>
          <div style="margin-left: auto;">
            <a href="/posts/<?= (int) $post['id'] ?>/edit" class="btn btn-secondary btn-sm btn-pill">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
              </svg>
              Edit Post
            </a>
          </div>
        <?php endif; ?>
      </div>
    </header>

    <!-- Stored AI Summary Box (if present and current) -->
    <?php if (!empty($post['summary']) && \InfinityBlog\Posts\PostRepository::isSummaryCurrent($post)): ?>
      <aside class="ai-summary-card">
        <div class="ai-summary-header">
          <span>✨</span>
          <span>Article Summary</span>
        </div>
        <p class="ai-summary-text"><?= nl2br(htmlspecialchars($post['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
      </aside>
    <?php endif; ?>

    <!-- Full Article Content (safely entity-escaped) -->
    <div class="article-body-content">
      <?= nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')) ?>
    </div>

    <div style="margin-top: 48px; padding-top: 24px; border-top: 1px solid rgba(226, 232, 240, 0.7); display: flex; justify-content: space-between; align-items: center;">
      <a href="/" class="btn btn-secondary btn-pill">
        ← Back to All Articles
      </a>
      <span style="font-size: 0.85rem; color: var(--text-subtle);">
        Infinity Blog
      </span>
    </div>
  </article>

  <!-- Sidebar: Recent Articles -->
  <aside>
    <div class="glass-panel sidebar-card">
      <h3 class="sidebar-title">Recent Stories</h3>
      <?php if (empty($recentPosts)): ?>
        <p style="font-size: 0.85rem; color: var(--text-muted);">No other articles yet.</p>
      <?php else: ?>
        <div class="mini-article-list">
          <?php foreach ($recentPosts as $item): ?>
            <a href="/article/<?= (int) $item['id'] ?>" class="mini-article-item">
              <div class="mini-article-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="mini-article-date">
                <?= date('M j, Y', strtotime($item['published_at'] ?? $item['created_at'])) ?>
                <?php if (!empty($item['category_name'])): ?>
                  · <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>
</div>
