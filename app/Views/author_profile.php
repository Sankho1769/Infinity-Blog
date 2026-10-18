<!-- Screen 9: Author Profile Page (Public View) -->
<div class="breadcrumb-bar" style="margin-top: 16px; margin-bottom: 24px; font-size: 0.88rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
  <a href="/" style="color: var(--text-muted); text-decoration: none;">Home</a>
  <span>›</span>
  <a href="/authors" style="color: var(--text-muted); text-decoration: none;">Authors</a>
  <span>›</span>
  <span style="color: var(--primary-color); font-weight: 600;"><?= htmlspecialchars($author['display_name'] ?? 'Cristine Palmer', ENT_QUOTES, 'UTF-8') ?></span>
</div>

<!-- Author Bio Header Card (Matching Screen 9 Reference) -->
<?php
  $bannerSrc = $author['banner_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveBannerUrl($author['banner_path'] ?? null);
  $avatarSrc = $author['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($author['avatar_path'] ?? null);
  $displayName = $author['display_name'] ?? 'Cristine Palmer';
?>
<div class="glass-panel" style="padding: 0; border-radius: var(--radius-xl); margin-bottom: 32px; overflow: hidden;">
  <?php if (!empty($bannerSrc)): ?>
    <div style="height: 180px; width: 100%; overflow: hidden; position: relative; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(168, 85, 247, 0.2));">
      <img src="<?= htmlspecialchars($bannerSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Author Banner" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.style.display='none';">
    </div>
  <?php endif; ?>
  <div style="padding: 32px 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 24px;">
    <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
      <?php if (!empty($avatarSrc)): ?>
        <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" style="width: 88px; height: 88px; border-radius: 50%; object-fit: cover; box-shadow: 0 8px 24px rgba(79, 70, 229, 0.25); border: 3px solid rgba(255, 255, 255, 0.9); flex-shrink: 0; <?= !empty($bannerSrc) ? 'margin-top: -50px;' : '' ?>" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
        <div class="author-avatar-initial-fallback" style="display: none; width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #4f46e5); align-items: center; justify-content: center; color: #fff; font-size: 2.2rem; font-weight: 800; box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35); flex-shrink: 0; <?= !empty($bannerSrc) ? 'margin-top: -50px;' : '' ?>">
          <?= strtoupper(substr($displayName, 0, 1)) ?>
        </div>
      <?php else: ?>
        <div style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #4f46e5); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2.2rem; font-weight: 800; box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35); flex-shrink: 0; <?= !empty($bannerSrc) ? 'margin-top: -50px;' : '' ?>">
          <?= strtoupper(substr($displayName, 0, 1)) ?>
        </div>
      <?php endif; ?>

      <div style="max-width: 540px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px; flex-wrap: wrap;">
          <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-dark); margin: 0; letter-spacing: -0.02em;">
            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <?php if (!empty($author['location'])): ?>
            <span style="font-size: 0.82rem; padding: 2px 10px; border-radius: 9999px; background: rgba(99, 102, 241, 0.1); color: var(--primary-color); font-weight: 500;">
              📍 <?= htmlspecialchars($author['location'], ENT_QUOTES, 'UTF-8') ?>
            </span>
          <?php endif; ?>
        </div>

        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 6px 0 12px; line-height: 1.55;">
          <?= !empty($author['bio']) ? nl2br(htmlspecialchars($author['bio'], ENT_QUOTES, 'UTF-8')) : 'Contributing perspectives, stories, and insights to the Infinity Blog community.' ?>
        </p>

        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <span style="font-size: 0.85rem; color: var(--text-subtle);">@<?= htmlspecialchars($author['username'] ?? 'cristine_palmer', ENT_QUOTES, 'UTF-8') ?></span>
          <?php if (!empty($author['website'])): ?>
            <span style="color: var(--glass-border);">·</span>
            <a href="<?= htmlspecialchars($author['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm btn-pill" style="padding: 3px 12px; font-size: 0.78rem;">
              🔗 <?= htmlspecialchars(preg_replace('#^https?://#', '', $author['website']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endif; ?>
        </div>

        <?php if (!empty($author['interests'])): ?>
          <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px;">
            <?php foreach (array_map('trim', explode(',', $author['interests'])) as $interest): ?>
              <?php if ($interest !== ''): ?>
                <span style="display: inline-block; padding: 2px 9px; border-radius: 9999px; background: rgba(99, 102, 241, 0.08); color: var(--primary-color); font-size: 0.76rem; font-weight: 500;">
                  <?= htmlspecialchars($interest, ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Social Actions: Follow / Message -->
        <div style="display: flex; gap: 10px; align-items: center; margin-top: 16px; flex-wrap: wrap;">
          <?php if (!empty($isSelf)): ?>
            <a href="/profile" class="btn btn-secondary btn-sm btn-pill">
              ✏️ Edit Profile
            </a>
          <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm btn-pill btn-follow-toggle <?= !empty($isFollowing) ? 'following' : '' ?>" data-author-id="<?= (int) $author['id'] ?>" data-following="<?= !empty($isFollowing) ? '1' : '0' ?>">
              <?php if (!empty($isFollowing)): ?>
                <span>✓</span> Following
              <?php else: ?>
                <span>+</span> Follow
              <?php endif; ?>
            </button>
            <a href="/messages/<?= (int) $author['id'] ?>" class="btn btn-secondary btn-sm btn-pill" title="Send direct message">
              ✉️ Send Message
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Author Stats Counter (Matching Screen 9 Right Column) -->
    <div style="display: flex; gap: 20px; background: var(--glass-bg-elevated); backdrop-filter: var(--glass-blur-sm); -webkit-backdrop-filter: var(--glass-blur-sm); padding: 18px 24px; border-radius: var(--radius-lg); border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow-sm);">
      <div style="text-align: center;">
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-dark);"><?= (int) $totalPosts ?></div>
        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Articles</div>
      </div>
      <div style="width: 1px; background: rgba(203, 213, 225, 0.7);"></div>
      <div style="text-align: center;">
        <div id="author-follower-count" class="author-follower-count" data-author-id="<?= (int) $author['id'] ?>" style="font-size: 1.5rem; font-weight: 800; color: var(--primary-color);"><?= (int) ($followerCount ?? 0) ?></div>
        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Followers</div>
      </div>
      <div style="width: 1px; background: rgba(203, 213, 225, 0.7);"></div>
      <div style="text-align: center;">
        <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a;"><?= (int) ($followingCount ?? 0) ?></div>
        <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Following</div>
      </div>
    </div>
  </div>
</div>

<!-- Segmented Tabs Navigation Bar (Screen 9 Exact Pattern) -->
<div class="author-tabs-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
  <div class="segmented-tabs" role="tablist" aria-label="Author Profile Sections">
    <button type="button" role="tab" id="author-tab-posts" aria-selected="true" aria-controls="author-panel-posts" tabindex="0" class="tab-pill active">
      Posts (<?= (int) $totalPosts ?>)
    </button>
    <button type="button" role="tab" id="author-tab-followers" aria-selected="false" aria-controls="author-panel-followers" tabindex="-1" class="tab-pill">
      Followers (<span class="author-follower-count" data-author-id="<?= (int) $author['id'] ?>"><?= (int) ($followerCount ?? 0) ?></span>)
    </button>
    <button type="button" role="tab" id="author-tab-following" aria-selected="false" aria-controls="author-panel-following" tabindex="-1" class="tab-pill">
      Following (<?= (int) ($followingCount ?? 0) ?>)
    </button>
    <button type="button" role="tab" id="author-tab-about" aria-selected="false" aria-controls="author-panel-about" tabindex="-1" class="tab-pill">
      About
    </button>
  </div>
  <a href="/authors" class="section-link">← All Authors</a>
</div>

<!-- Tab Panel: Posts -->
<div id="author-panel-posts" role="tabpanel" aria-labelledby="author-tab-posts">
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
            <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="article-banner-img" loading="lazy" onerror="this.onerror=null; this.src='/assets/images/technology-article-laptop.png';">
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
</div>

<!-- Tab Panel: Followers -->
<div id="author-panel-followers" role="tabpanel" aria-labelledby="author-tab-followers" style="display: none;">
  <?php if (empty($followers)): ?>
    <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center;">
      <div style="font-size: 2.4rem; margin-bottom: 10px;">👥</div>
      <h3 class="empty-title">No followers yet</h3>
      <p class="empty-desc">Be the first to follow <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> to stay notified about new stories.</p>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
      <?php foreach ($followers as $f): ?>
        <div class="glass-panel glass-panel-hover" style="padding: 16px 20px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 14px;">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
            <?= strtoupper(substr($f['display_name'] ?? $f['username'], 0, 1)) ?>
          </div>
          <div style="flex: 1; min-width: 0;">
            <a href="/author/<?= (int) $f['id'] ?>" style="font-weight: 700; color: var(--text-dark); text-decoration: none; font-size: 0.95rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span style="font-size: 0.8rem; color: var(--text-muted);">@<?= htmlspecialchars($f['username'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Tab Panel: Following -->
<div id="author-panel-following" role="tabpanel" aria-labelledby="author-tab-following" style="display: none;">
  <?php if (empty($following)): ?>
    <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center;">
      <div style="font-size: 2.4rem; margin-bottom: 10px;">👥</div>
      <h3 class="empty-title">Not following any authors yet</h3>
      <p class="empty-desc"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> is not currently following other writers.</p>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
      <?php foreach ($following as $f): ?>
        <div class="glass-panel glass-panel-hover" style="padding: 16px 20px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 14px;">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
            <?= strtoupper(substr($f['display_name'] ?? $f['username'], 0, 1)) ?>
          </div>
          <div style="flex: 1; min-width: 0;">
            <a href="/author/<?= (int) $f['id'] ?>" style="font-weight: 700; color: var(--text-dark); text-decoration: none; font-size: 0.95rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span style="font-size: 0.8rem; color: var(--text-muted);">@<?= htmlspecialchars($f['username'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Tab Panel: About -->
<div id="author-panel-about" role="tabpanel" aria-labelledby="author-tab-about" style="display: none;">
  <div class="glass-panel" style="padding: 32px 28px; border-radius: var(--radius-xl);">
    <h3 style="font-size: 1.35rem; font-weight: 800; color: var(--text-dark); margin-bottom: 16px;">
      About <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
    </h3>
    <p style="color: var(--text-body); font-size: 1rem; line-height: 1.65; margin-bottom: 24px;">
      <?= !empty($author['bio']) ? nl2br(htmlspecialchars($author['bio'], ENT_QUOTES, 'UTF-8')) : 'Contributing perspectives, stories, and insights to the Infinity Blog community.' ?>
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
      <?php if (!empty($author['location'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 1.2rem;">📍</span>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Location</div>
            <div style="font-size: 0.92rem; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($author['location'], ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($author['website'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 1.2rem;">🔗</span>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Website</div>
            <a href="<?= htmlspecialchars($author['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
              <?= htmlspecialchars(preg_replace('#^https?://#', '', $author['website']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 1.2rem;">✍️</span>
        <div>
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Activity</div>
          <div style="font-size: 0.92rem; font-weight: 600; color: var(--text-dark);"><?= (int) $totalPosts ?> published articles</div>
        </div>
      </div>
    </div>

    <?php if (!empty($author['interests'])): ?>
      <div style="border-top: 1px solid rgba(226, 232, 240, 0.7); padding-top: 20px;">
        <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; letter-spacing: 0.05em;">
          Topics of Focus
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
          <?php foreach (array_map('trim', explode(',', $author['interests'])) as $interest): ?>
            <?php if ($interest !== ''): ?>
              <span style="display: inline-block; padding: 4px 12px; border-radius: 9999px; background: rgba(99, 102, 241, 0.08); color: var(--primary-color); font-size: 0.82rem; font-weight: 600;">
                <?= htmlspecialchars($interest, ENT_QUOTES, 'UTF-8') ?>
              </span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabs = {
    posts: { btn: document.getElementById('author-tab-posts'), panel: document.getElementById('author-panel-posts') },
    followers: { btn: document.getElementById('author-tab-followers'), panel: document.getElementById('author-panel-followers') },
    following: { btn: document.getElementById('author-tab-following'), panel: document.getElementById('author-panel-following') },
    about: { btn: document.getElementById('author-tab-about'), panel: document.getElementById('author-panel-about') }
  };

  function switchTab(target) {
    Object.keys(tabs).forEach(k => {
      const t = tabs[k];
      if (!t.btn || !t.panel) return;
      if (k === target) {
        t.btn.classList.add('active');
        t.btn.setAttribute('aria-selected', 'true');
        t.btn.setAttribute('tabindex', '0');
        t.panel.style.display = 'block';
      } else {
        t.btn.classList.remove('active');
        t.btn.setAttribute('aria-selected', 'false');
        t.btn.setAttribute('tabindex', '-1');
        t.panel.style.display = 'none';
      }
    });
  }

  Object.keys(tabs).forEach(k => {
    if (tabs[k].btn) {
      tabs[k].btn.addEventListener('click', () => switchTab(k));
    }
  });

  const tabList = Object.values(tabs).map(t => t.btn).filter(Boolean);
  tabList.forEach((tab, index) => {
    tab.addEventListener('keydown', (e) => {
      let nextIndex = null;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        nextIndex = (index + 1) % tabList.length;
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        nextIndex = (index - 1 + tabList.length) % tabList.length;
      } else if (e.key === 'Home') {
        nextIndex = 0;
      } else if (e.key === 'End') {
        nextIndex = tabList.length - 1;
      }
      if (nextIndex !== null) {
        e.preventDefault();
        tabList[nextIndex].focus();
        tabList[nextIndex].click();
      }
    });
  });
});
</script>
