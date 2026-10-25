<!-- Public Authors Page -->
<div class="content-header content-page-hero">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Voices & Contributors</span>
  <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Our Authors</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin: 10px auto 0;">
    Meet the engineers, thinkers, and creators writing stories, architectural guides, and perspectives on Infinity Blog.
  </p>

  <!-- Author Search Form -->
  <form action="/authors" method="GET" class="hero-search-bar" role="search" style="max-width: 520px; margin: 24px auto 0;">
    <input type="search" name="search" class="hero-search-input" placeholder="Search authors by name or username..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search authors">
    <button type="submit" class="btn btn-primary btn-pill btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      Search
    </button>
  </form>
</div>

<?php if (!empty($searchQuery)): ?>
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; margin-top: 10px;">
    <span style="font-size: 0.9rem; color: var(--text-muted);">
      Search results for "<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" (<?= count($authors) ?> found)
    </span>
    <a href="/authors" class="section-link">Clear search ×</a>
  </div>
<?php endif; ?>

<?php if (empty($authors)): ?>
  <div class="glass-panel empty-state" style="margin-top: 20px; padding: 28px 20px; text-align: center;">
    <div style="font-size: 2.5rem; margin-bottom: 12px;">✍️</div>
    <h2 class="empty-title">No Active Authors Found</h2>
    <p class="empty-desc">
      <?= !empty($searchQuery) ? 'No authors matched your search criteria.' : 'Author profiles will appear here once registered.' ?>
    </p>
    <a href="/authors" class="btn btn-primary btn-pill">Browse All Authors</a>
  </div>
<?php else: ?>
  <div class="authors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 20px; margin-top: 16px;">
    <?php foreach ($authors as $author): ?>
      <?php
        $avatarSrc = $author['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($author['avatar_path'] ?? null);
        $displayName = $author['display_name'] ?? $author['username'];
      ?>
      <div class="glass-panel glass-panel-hover author-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: clamp(16px, 3.5vw, 24px); border-radius: var(--radius-xl); transition: all 0.2s ease;">
        <div>
          <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
            <?php if (!empty($avatarSrc)): ?>
              <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25); border: 2px solid #fff; flex-shrink: 0;" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
              <div style="display: none; width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.25rem; flex-shrink: 0;">
                <?= strtoupper(substr($displayName, 0, 1)) ?>
              </div>
            <?php else: ?>
              <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.25rem; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3); flex-shrink: 0;">
                <?= strtoupper(substr($displayName, 0, 1)) ?>
              </div>
            <?php endif; ?>

            <div style="flex: 1; min-width: 0;">
              <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin: 0 0 3px 0; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <a href="/author/<?= (int) $author['id'] ?>" style="color: inherit; text-decoration: none;">
                  <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php if (!empty($author['has_author_badge'])): ?>
                  <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                <?php endif; ?>
              </h3>
              <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                <span style="font-size: 0.82rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px; display: inline-block;">
                  <?= htmlspecialchars($author['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($author['username'] ?? null, $author['display_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if (!empty($author['is_self'])): ?>
                  <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary-color); font-size: 0.72rem; padding: 1px 8px; flex-shrink: 0;">You</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Stats Row -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 14px; padding: 12px 14px; background: var(--glass-bg-subtle); border-radius: var(--radius-md); border: 1px solid var(--glass-border-subtle); text-align: center;">
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Articles</div>
              <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-dark); margin-top: 2px;">
                <?= (int) $author['post_count'] ?>
              </div>
            </div>
            <div style="border-left: 1px solid rgba(226, 232, 240, 0.7);">
              <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Followers</div>
              <div class="author-follower-count" data-author-id="<?= (int) $author['id'] ?>" style="font-size: 1.05rem; font-weight: 800; color: var(--primary-color); margin-top: 2px;">
                <?= (int) ($author['follower_count'] ?? 0) ?>
              </div>
            </div>
          </div>
        </div>

        <div style="margin-top: 20px; display: flex; gap: 8px; align-items: center;">
          <a href="/author/<?= (int) $author['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="flex: 1; text-align: center; text-decoration: none;">
            Profile
          </a>
          <?php if (empty($author['is_self'])): ?>
            <button type="button" class="btn btn-primary btn-sm btn-pill btn-follow-toggle <?= !empty($author['is_following']) ? 'following' : '' ?>" data-author-id="<?= (int) $author['id'] ?>" data-following="<?= !empty($author['is_following']) ? '1' : '0' ?>" style="flex: 1;">
              <?= !empty($author['is_following']) ? '<span>✓</span> Following' : '<span>+</span> Follow' ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
