<!-- Public Authors Page -->
<div class="content-header content-page-hero">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Voices & Contributors</span>
  <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Our Authors</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin: 10px auto 0;">
    Meet the engineers, thinkers, and creators writing stories, architectural guides, and perspectives on Infinity Blog.
  </p>
</div>

<?php if (empty($authors)): ?>
  <div class="glass-panel empty-state" style="margin-top: 30px; padding: 60px 24px; text-align: center;">
    <div style="font-size: 2.5rem; margin-bottom: 12px;">✍️</div>
    <h2 class="empty-title">No Active Authors Found</h2>
    <p class="empty-desc">Author profiles will appear here once registered.</p>
    <a href="/" class="btn btn-primary btn-pill">Return to Home</a>
  </div>
<?php else: ?>
  <div class="authors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
    <?php foreach ($authors as $author): ?>
      <div class="glass-panel glass-panel-hover" style="display: flex; flex-direction: column; justify-content: space-between; padding: 26px; border-radius: var(--radius-lg); transition: all 0.2s ease;">
        <div>
          <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
            <div style="width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.25rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3); flex-shrink: 0;">
              <?= strtoupper(substr($author['display_name'] ?? $author['username'], 0, 1)) ?>
            </div>
            <div>
              <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin: 0 0 4px 0;">
                <?= htmlspecialchars($author['display_name'], ENT_QUOTES, 'UTF-8') ?>
              </h3>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">
                  @<?= htmlspecialchars($author['username'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
            </div>
          </div>

          <div style="margin-top: 14px; padding: 12px 14px; background: rgba(248, 250, 252, 0.6); border-radius: var(--radius-md); border: 1px solid rgba(226, 232, 240, 0.6); display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted);">Published Articles</span>
            <span style="font-size: 0.95rem; font-weight: 700; color: var(--primary-color);">
              <?= (int) $author['post_count'] ?>
            </span>
          </div>
        </div>

        <div style="margin-top: 20px;">
          <a href="/author/<?= (int) $author['id'] ?>" class="btn btn-secondary btn-sm btn-full btn-pill" style="text-decoration: none;">
            View Profile & Articles
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
