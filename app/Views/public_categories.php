<!-- Public Categories Page -->
<div class="content-header content-page-hero">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Browse Knowledge</span>
  <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Explore Categories</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin: 10px auto 0;">
    Discover articles grouped by topic across software engineering, artificial intelligence, architecture, and design.
  </p>
</div>

<?php if (empty($categories)): ?>
  <div class="glass-panel empty-state" style="margin-top: 30px; padding: 60px 24px; text-align: center;">
    <div style="font-size: 2.5rem; margin-bottom: 12px;">📁</div>
    <h2 class="empty-title">No Categories Established Yet</h2>
    <p class="empty-desc">Check back soon as new topics and articles are published.</p>
    <a href="/" class="btn btn-primary btn-pill">Return to Home</a>
  </div>
<?php else: ?>
  <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
    <?php foreach ($categories as $cat): ?>
      <a href="/?category=<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" class="glass-panel glass-panel-hover category-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 26px; text-decoration: none; border-radius: var(--radius-lg); transition: all 0.2s ease;">
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div style="width: 42px; height: 42px; border-radius: 12px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.15rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
              ✦
            </div>
            <span style="background: rgba(224, 231, 255, 0.7); color: #4338ca; font-weight: 700; padding: 4px 12px; border-radius: var(--radius-pill); font-size: 0.8rem;">
              <?= (int) $cat['post_count'] ?> <?= (int) $cat['post_count'] === 1 ? 'Article' : 'Articles' ?>
            </span>
          </div>

          <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">
            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
          </h3>

          <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 20px;">
            <?= htmlspecialchars($cat['description'] ?? 'Curated perspectives and deep dives.', ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>

        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.9rem; font-weight: 600; color: var(--primary-color);">
          <span>Browse Articles</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
