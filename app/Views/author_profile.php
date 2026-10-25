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
        <div class="author-avatar-initial-fallback" style="width: 88px; height: 88px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #4f46e5); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2.2rem; font-weight: 800; box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35); flex-shrink: 0; <?= !empty($bannerSrc) ? 'margin-top: -50px;' : '' ?>">
          <?= strtoupper(substr($displayName, 0, 1)) ?>
        </div>
      <?php endif; ?>

      <div style="max-width: 540px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px; flex-wrap: wrap;">
          <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-dark); margin: 0; letter-spacing: -0.02em;">
            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <?php if (!empty($author['has_author_badge'])): ?>
            <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
          <?php endif; ?>
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
          <span style="font-size: 0.85rem; color: var(--text-subtle);"><?= htmlspecialchars($author['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($author['username'] ?? null, $displayName), ENT_QUOTES, 'UTF-8') ?></span>
          <?php if (!empty($author['website']) && preg_match('#^https?://#i', $author['website'])): ?>
            <span style="color: var(--glass-border);">·</span>
            <a href="<?= htmlspecialchars($author['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm btn-pill" style="padding: 3px 12px; font-size: 0.78rem;">
              🔗 <?= htmlspecialchars(preg_replace('#^https?://#', '', $author['website']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endif; ?>
        </div>

        <!-- Public Social Profiles -->
        <?php $authorSocialLinks = \InfinityBlog\Support\UserIdentityHelper::getSocialLinks($author, $author['display_name'] ?? null, true); ?>
        <?php if (!empty($authorSocialLinks)): ?>
          <div class="author-social-links-bar" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 10px;">
            <?php foreach ($authorSocialLinks as $aslink): ?>
              <a href="<?= htmlspecialchars($aslink['url'], ENT_QUOTES, 'UTF-8') ?>"
                 target="_blank"
                 rel="noopener noreferrer"
                 class="profile-social-link social-link-btn btn btn-secondary btn-sm btn-pill"
                 style="padding: 3px 10px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 5px; <?= !empty($aslink['text_color']) ? 'color: ' . $aslink['text_color'] . '; font-weight: 600;' : '' ?>"
                 aria-label="<?= htmlspecialchars($aslink['aria_label'], ENT_QUOTES, 'UTF-8') ?>"
                 title="<?= htmlspecialchars($aslink['title'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $aslink['svg_icon'] ?>
                <?= htmlspecialchars($aslink['name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

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
          $postId = (int) $post['id'];
          $canonicalPostUrl = '/article/' . $postId;
          $thumbnailUrl = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, $idx);
        ?>
        <article class="glass-panel glass-panel-hover article-card"
                 data-post-id="<?= $postId ?>"
                 data-article-url="<?= $canonicalPostUrl ?>"
                 tabindex="0"
                 role="article"
                 aria-label="Article: <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($thumbnailUrl)): ?>
            <div class="article-card-banner has-image">
              <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="article-banner-img" loading="lazy" onerror="this.onerror=null; this.closest('.article-card-banner').style.display='none';">
              <div class="article-banner-badge-wrapper">
                <?php if (!empty($post['category_name'])): ?>
                  <a href="/explore?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="badge badge-category" style="text-decoration: none;" title="Browse category: <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                <?php else: ?>
                  <span class="badge badge-published">Published</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="article-card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <time class="article-date-meta">
                <?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
              </time>
              <?php if (empty($thumbnailUrl) && !empty($post['category_name'])): ?>
                <a href="/explore?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="badge badge-category" style="text-decoration: none;" title="Browse category: <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
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
        <?php
          $fAvatar = $f['avatar_url'] ?? \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($f['avatar_path'] ?? null);
          $fInitial = strtoupper(mb_substr($f['display_name'] ?? 'A', 0, 1));
          $fHandle = $f['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($f['username'] ?? null, $f['display_name'] ?? null);
        ?>
        <div class="glass-panel glass-panel-hover" style="padding: 16px 20px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 14px;">
          <?php if (!empty($fAvatar)): ?>
            <img src="<?= htmlspecialchars($fAvatar, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>"
                 style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div style="display: none; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
              <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php else: ?>
            <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
              <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>
          <div style="flex: 1; min-width: 0;">
            <a href="/author/<?= (int) $f['id'] ?>" style="font-weight: 700; color: var(--text-dark); text-decoration: none; font-size: 0.95rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($fHandle, ENT_QUOTES, 'UTF-8') ?></span>
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
        <?php
          $fAvatar = $f['avatar_url'] ?? \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($f['avatar_path'] ?? null);
          $fInitial = strtoupper(mb_substr($f['display_name'] ?? 'A', 0, 1));
          $fHandle = $f['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($f['username'] ?? null, $f['display_name'] ?? null);
        ?>
        <div class="glass-panel glass-panel-hover" style="padding: 16px 20px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 14px;">
          <?php if (!empty($fAvatar)): ?>
            <img src="<?= htmlspecialchars($fAvatar, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>"
                 style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div style="display: none; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
              <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php else: ?>
            <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
              <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>
          <div style="flex: 1; min-width: 0;">
            <a href="/author/<?= (int) $f['id'] ?>" style="font-weight: 700; color: var(--text-dark); text-decoration: none; font-size: 0.95rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($fHandle, ENT_QUOTES, 'UTF-8') ?></span>
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

      <?php if (!empty($author['website']) && preg_match('#^https?://#i', $author['website'])): ?>
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

      <?php if (!empty($author['instagram_url'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E1306C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
          </svg>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Instagram</div>
            <a href="<?= htmlspecialchars($author['instagram_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
              <?= htmlspecialchars(preg_replace('#^https?://(www\.)?instagram\.com/#i', '@', $author['instagram_url']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($author['twitter_url'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="color: var(--text-heading);">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">X (Twitter)</div>
            <a href="<?= htmlspecialchars($author['twitter_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
              <?= htmlspecialchars(preg_replace('#^https?://(www\.)?(twitter\.com|x\.com)/#i', '@', $author['twitter_url']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($author['github_url'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="color: var(--text-heading);">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
          </svg>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">GitHub</div>
            <a href="<?= htmlspecialchars($author['github_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
              <?= htmlspecialchars(preg_replace('#^https?://(www\.)?github\.com/#i', '', $author['github_url']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($author['linkedin_url'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="#0A66C2" aria-hidden="true">
            <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.62 1.62 0 1 0 0-3.24 1.62 1.62 0 0 0 0 3.24M7.85 18.5V10.13H5.07V18.5h2.78z"/>
          </svg>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">LinkedIn</div>
            <a href="<?= htmlspecialchars($author['linkedin_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
              <?= htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::formatDisplayHandle($author['linkedin_url'], 'linkedin'), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($author['facebook_url'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877F2">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
          </svg>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Facebook</div>
            <a href="<?= htmlspecialchars($author['facebook_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
              <?= htmlspecialchars(preg_replace('#^https?://(www\.)?facebook\.com/#i', '', $author['facebook_url']), ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($author['whatsapp_public']) && !empty($author['whatsapp_link'])): ?>
        <div style="display: flex; align-items: center; gap: 10px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="#25D366">
            <path d="M17.472 14.382c-.301-.15-1.782-.879-2.057-.98-.276-.1-.476-.15-.677.15-.2.3-.777.98-.952 1.18-.175.2-.351.226-.652.075-.3-.15-1.267-.467-2.414-1.489-.893-.796-1.496-1.78-1.671-2.08-.175-.3-.019-.462.131-.612.135-.135.301-.35.452-.525.15-.176.2-.3.301-.5.1-.2.05-.376-.025-.526-.075-.15-.677-1.631-.927-2.233-.244-.587-.492-.507-.677-.516-.175-.01-.376-.01-.577-.01-.2 0-.526.075-.802.376-.276.3-1.053 1.028-1.053 2.508 0 1.48 1.078 2.909 1.229 3.109.15.2 2.122 3.24 5.141 4.544.718.31 1.279.496 1.716.634.721.23 1.378.197 1.9.12.58-.088 1.782-.728 2.032-1.431.25-.702.25-1.304.175-1.43-.075-.125-.276-.201-.577-.351z"/>
            <path d="M12 2C6.48 2 2 6.48 2 12c0 1.83.49 3.55 1.34 5.03L2 22l5.12-1.33A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.2c-1.57 0-3.04-.43-4.32-1.18l-.31-.18-3.04.79.81-2.96-.2-.32A8.17 8.17 0 013.8 12c0-4.52 3.68-8.2 8.2-8.2s8.2 3.68 8.2 8.2-3.68 8.2-8.2 8.2z"/>
          </svg>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">WhatsApp</div>
            <a href="<?= htmlspecialchars($author['whatsapp_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.92rem; font-weight: 600; color: #16a34a; text-decoration: none;">
              Chat on WhatsApp (<?= htmlspecialchars($author['whatsapp_number'], ENT_QUOTES, 'UTF-8') ?>)
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
