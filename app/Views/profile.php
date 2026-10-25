<?php
declare(strict_types=1);
/**
 * @var array<string, mixed> $user
 * @var array<string, mixed> $stats
 * @var list<array<string, mixed>> $userPosts
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 */
?>
<div class="profile-page-container" style="max-width: 920px; margin: 32px auto;">
  <?php if (!empty($_GET['updated'])): ?>
    <div class="alert-banner alert-success alert-dismissible" role="alert" style="margin-bottom: 24px;">
      <span>Your profile details have been saved successfully!</span>
      <button type="button" class="alert-close-btn" aria-label="Close" onclick="this.closest('.alert-banner').remove()">×</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_GET['avatar_updated'])): ?>
    <div class="alert-banner alert-success alert-dismissible" role="alert" style="margin-bottom: 24px;">
      <span>Your profile avatar has been updated successfully!</span>
      <button type="button" class="alert-close-btn" aria-label="Close" onclick="this.closest('.alert-banner').remove()">×</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_GET['banner_updated'])): ?>
    <div class="alert-banner alert-success alert-dismissible" role="alert" style="margin-bottom: 24px;">
      <span>Your profile banner has been updated successfully!</span>
      <button type="button" class="alert-close-btn" aria-label="Close" onclick="this.closest('.alert-banner').remove()">×</button>
    </div>
  <?php endif; ?>

  <!-- Profile Header Card (Screen 9 / Profile Overview) -->
  <div class="glass-card profile-card" style="padding: 0; border-radius: 24px; position: relative; overflow: hidden; margin-bottom: 28px;">
    <?php $bannerSrc = $user['banner_url'] ?? ($user['banner_path'] ?? null); ?>
    <?php if (!empty($bannerSrc)): ?>
      <div class="profile-banner-hero" style="height: 180px; width: 100%; overflow: hidden; position: relative; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(168, 85, 247, 0.2));">
        <img src="<?= htmlspecialchars($bannerSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Profile Banner" style="width: 100%; height: 100%; object-fit: cover;">
      </div>
    <?php endif; ?>
    <div style="padding: 32px 32px; position: relative;">
      <div class="profile-header" style="display: flex; gap: 28px; align-items: flex-start; flex-wrap: wrap;">
        <!-- Avatar -->
        <div class="profile-avatar-wrapper" style="position: relative; <?= !empty($bannerSrc) ? 'margin-top: -60px;' : '' ?> flex-shrink: 0;">
          <?php $avatarSrc = $user['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($user['avatar_path'] ?? null); ?>
          <?php if (!empty($avatarSrc)): ?>
            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>"
                 class="profile-avatar-img"
                 style="width: 104px; height: 104px; border-radius: 50%; object-fit: cover; box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2); border: 3px solid rgba(255, 255, 255, 0.9);"
                 onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
            <div class="profile-avatar-initial" style="display: none; width: 104px; height: 104px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2); border: 3px solid rgba(255, 255, 255, 0.9);">
              <?= strtoupper(substr($user['display_name'] ?? $user['username'] ?? 'C', 0, 1)) ?>
            </div>
          <?php else: ?>
            <div class="profile-avatar-initial" style="width: 104px; height: 104px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2); border: 3px solid rgba(255, 255, 255, 0.9);">
              <?= strtoupper(substr($user['display_name'] ?? $user['username'] ?? 'C', 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Main Profile Info -->
        <div class="profile-info" style="flex: 1; min-width: 260px;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; margin-bottom: 8px;">
            <div>
              <h1 class="profile-name" style="font-size: 1.85rem; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; letter-spacing: -0.02em;">
                <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>
              </h1>
              <p class="profile-handle" style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 8px;">
                <?= htmlspecialchars($user['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($user['username'] ?? null, $user['display_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
              </p>
            </div>
            <!-- Action Buttons: Primary Blue Gradient & Glass Secondary -->
            <div class="profile-action-group" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
              <a href="/profile/edit" class="btn btn-primary btn-pill">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Edit Profile
              </a>
              <a href="/posts/new" class="btn btn-secondary btn-pill">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                + New Post
              </a>
            </div>
          </div>

          <!-- Badges Row -->
          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px;">
            <span class="user-role-badge <?= ($user['role'] === 'admin') ? 'admin' : 'author' ?>" style="font-size: 0.78rem; text-transform: uppercase;">
              <?= ($user['role'] === 'admin') ? 'Administrator' : 'Author' ?>
            </span>
            <span class="badge-status-pill badge-active" style="font-size: 0.78rem; padding: 2px 10px; border-radius: 9999px; font-weight: 600;">
              Active
            </span>
            <span class="badge-status-pill <?= ($user['profile_visibility'] === 'private') ? 'badge-private' : 'badge-public' ?>" style="font-size: 0.78rem; padding: 2px 10px; border-radius: 9999px; font-weight: 600;">
              <?= ($user['profile_visibility'] === 'private') ? '🔒 Private Profile' : '🌐 Public Profile' ?>
            </span>
          </div>

          <!-- Bio Preview -->
          <?php if (!empty($user['bio'])): ?>
            <p style="color: var(--text-body); font-size: 0.98rem; line-height: 1.55; margin-bottom: 12px;">
              <?= nl2br(htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
          <?php else: ?>
            <p style="color: var(--text-muted); font-size: 0.92rem; font-style: italic; margin-bottom: 12px;">
              No bio provided yet. <a href="/profile/edit" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Add a bio</a> to tell readers about yourself.
            </p>
          <?php endif; ?>

          <!-- Public Social Presence Bar -->
          <?php $socialLinks = \InfinityBlog\Support\UserIdentityHelper::getSocialLinks($user, $user['display_name'] ?? null, true); ?>
          <?php if (!empty($socialLinks)): ?>
            <div class="profile-social-links-bar" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 10px;">
              <?php foreach ($socialLinks as $slink): ?>
                <a href="<?= htmlspecialchars($slink['url'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="profile-social-link social-link-btn btn btn-secondary btn-sm btn-pill"
                   style="padding: 4px 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; <?= !empty($slink['text_color']) ? 'color: ' . $slink['text_color'] . '; font-weight: 600;' : '' ?>"
                   aria-label="<?= htmlspecialchars($slink['aria_label'], ENT_QUOTES, 'UTF-8') ?>"
                   title="<?= htmlspecialchars($slink['title'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= $slink['svg_icon'] ?>
                  <?= htmlspecialchars($slink['name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Summary Stats Row -->
      <div style="border-top: 1px solid rgba(226, 232, 240, 0.7); margin-top: 20px; padding-top: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 14px; text-align: center;">
        <div class="profile-stat-box">
          <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary-color);"><?= (int) ($stats['published_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Published Posts</div>
        </div>
        <div class="profile-stat-box">
          <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;"><?= (int) ($stats['draft_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Drafts</div>
        </div>
        <div class="profile-stat-box">
          <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;"><?= (int) ($stats['remix_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Remixes</div>
        </div>
        <div class="profile-stat-box" style="cursor: pointer;" onclick="document.getElementById('profile-tab-followers')?.click();">
          <div style="font-size: 1.5rem; font-weight: 800; color: #8b5cf6;" id="profile-stat-followers"><?= (int) ($stats['follower_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Followers</div>
        </div>
        <div class="profile-stat-box" style="cursor: pointer;" onclick="document.getElementById('profile-tab-following')?.click();">
          <div style="font-size: 1.5rem; font-weight: 800; color: #ec4899;" id="profile-stat-following"><?= (int) ($stats['following_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Following</div>
        </div>
        <?php if (!empty($user['created_at'])): ?>
          <div class="profile-stat-box">
            <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-heading); margin-top: 4px;">
              <?= date('M Y', strtotime($user['created_at'])) ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Member Since</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Segmented Tabs Navigation Bar (Screen 9 Reference) -->
  <div class="profile-tabs-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div class="segmented-tabs" role="tablist" aria-label="Profile navigation">
      <button type="button" role="tab" id="profile-tab-posts" aria-selected="true" aria-controls="profile-panel-posts" tabindex="0" class="tab-pill active">
        Articles (<?= count($userPosts) ?>)
      </button>
      <button type="button" role="tab" id="profile-tab-followers" aria-selected="false" aria-controls="profile-panel-followers" tabindex="-1" class="tab-pill">
        Followers (<?= (int) ($stats['follower_count'] ?? count($followers ?? [])) ?>)
      </button>
      <button type="button" role="tab" id="profile-tab-following" aria-selected="false" aria-controls="profile-panel-following" tabindex="-1" class="tab-pill">
        Following (<?= (int) ($stats['following_count'] ?? count($following ?? [])) ?>)
      </button>
      <button type="button" role="tab" id="profile-tab-about" aria-selected="false" aria-controls="profile-panel-about" tabindex="-1" class="tab-pill">
        About
      </button>
    </div>
    <div class="profile-tab-actions" style="display: flex; gap: 8px;">
      <a href="/posts/manage" class="btn btn-secondary btn-sm btn-pill">Manage All Posts</a>
    </div>
  </div>

  <!-- Tab Panel 1: Articles (Default Visible) -->
  <section id="profile-panel-posts" role="tabpanel" aria-labelledby="profile-tab-posts" class="user-articles-section">
    <?php if (empty($userPosts)): ?>
      <div class="glass-card empty-state" style="padding: 36px 24px; text-align: center;">
        <h3 class="empty-title">No articles written yet</h3>
        <p class="empty-desc">Share your perspectives, tutorials, and insights with the Infinity Blog community.</p>
        <a href="/posts/new" class="btn btn-primary btn-pill" style="margin-top: 14px;">Create Your First Post</a>
      </div>
    <?php else: ?>
      <div class="articles-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
        <?php foreach ($userPosts as $post): ?>
          <?php
            $postId = (int) $post['id'];
            $isPublished = ($post['status'] === 'published');
            $canonicalPostUrl = $isPublished ? ('/article/' . $postId) : ('/posts/' . $postId . '/edit');
          ?>
          <article class="glass-card article-card"
                   data-post-id="<?= $postId ?>"
                   data-article-url="<?= $canonicalPostUrl ?>"
                   tabindex="0"
                   role="article"
                   aria-label="Article: <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                   style="display: flex; flex-direction: column; overflow: hidden; border-radius: 18px;">
            <div class="article-card-body" style="padding: 20px; display: flex; flex-direction: column; flex: 1;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span class="category-pill" style="font-size: 0.76rem; padding: 3px 10px;">
                  <?= htmlspecialchars($post['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span style="font-size: 0.74rem; font-weight: 600; padding: 2px 8px; border-radius: 9999px; background: <?= ($post['status'] === 'published') ? 'rgba(34, 197, 94, 0.15); color: #15803d;' : 'rgba(245, 158, 11, 0.15); color: #b45309;' ?>">
                  <?= ucfirst($post['status']) ?>
                </span>
              </div>
              <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-heading); margin-bottom: 8px; line-height: 1.35;">
                <a href="<?= ($post['status'] === 'published') ? '/article/' . (int) $post['id'] : '/posts/' . (int) $post['id'] . '/edit' ?>" style="text-decoration: none; color: inherit;">
                  <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h3>
              <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; flex: 1;">
                <?= htmlspecialchars(mb_substr(strip_tags((string) ($post['summary'] ?? $post['content'])), 0, 110) . '...', ENT_QUOTES, 'UTF-8') ?>
              </p>
              <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(226, 232, 240, 0.6); font-size: 0.8rem;">
                <span style="color: var(--text-muted);">
                  <?= date('M d, Y', strtotime($post['created_at'])) ?>
                </span>
                <div style="display: flex; gap: 8px;">
                  <a href="/posts/<?= (int) $post['id'] ?>/edit" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 10px;">Edit</a>
                  <?php if ($post['status'] === 'published'): ?>
                    <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-primary btn-sm btn-pill" style="padding: 4px 10px;">View</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Tab Panel 2: About (Toggled via Segmented Tabs) -->
  <section id="profile-panel-about" role="tabpanel" aria-labelledby="profile-tab-about" class="user-about-section" style="display: none;">
    <div class="glass-card" style="padding: 32px 28px; border-radius: 20px;">
      <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-heading); margin-bottom: 18px;">
        About <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>
      </h2>

      <!-- Bio Full -->
      <?php if (!empty($user['bio'])): ?>
        <p style="color: var(--text-body); font-size: 1rem; line-height: 1.65; margin-bottom: 24px;">
          <?= nl2br(htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8')) ?>
        </p>
      <?php else: ?>
        <p style="color: var(--text-muted); font-size: 0.95rem; font-style: italic; margin-bottom: 24px;">
          No bio provided yet. You can add one by editing your profile.
        </p>
      <?php endif; ?>

      <!-- Contact & Presence Grid -->
      <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; letter-spacing: 0.05em;">
        Contact & Presence
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; font-size: 0.92rem; color: var(--text-body); margin-bottom: 28px;">
        <?php if (!empty($user['email'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.1rem;" title="Email Address">✉️</span>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Email</div>
              <span><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['location'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.1rem;" title="Location">📍</span>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Location</div>
              <span><?= htmlspecialchars($user['location'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['website']) && preg_match('#^https?://#i', $user['website'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.1rem;" title="Website">🔗</span>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Website</div>
              <a href="<?= htmlspecialchars($user['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                <?= htmlspecialchars(preg_replace('#^https?://#', '', $user['website']), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['instagram_url'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E1306C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
              <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
              <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
            </svg>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Instagram</div>
              <a href="<?= htmlspecialchars($user['instagram_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                <?= htmlspecialchars(preg_replace('#^https?://(www\.)?instagram\.com/#i', '@', $user['instagram_url']), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['twitter_url'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" style="color: var(--text-heading);">
              <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
            </svg>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">X (Twitter)</div>
              <a href="<?= htmlspecialchars($user['twitter_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                <?= htmlspecialchars(preg_replace('#^https?://(www\.)?(twitter\.com|x\.com)/#i', '@', $user['twitter_url']), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['github_url'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="color: var(--text-heading);">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
            </svg>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">GitHub</div>
              <a href="<?= htmlspecialchars($user['github_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                <?= htmlspecialchars(preg_replace('#^https?://(www\.)?github\.com/#i', '', $user['github_url']), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['linkedin_url'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#0A66C2" aria-hidden="true">
              <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.62 1.62 0 1 0 0-3.24 1.62 1.62 0 0 0 0 3.24M7.85 18.5V10.13H5.07V18.5h2.78z"/>
            </svg>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">LinkedIn</div>
              <a href="<?= htmlspecialchars($user['linkedin_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                <?= htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::formatDisplayHandle($user['linkedin_url'], 'linkedin'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['facebook_url'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Facebook</div>
              <a href="<?= htmlspecialchars($user['facebook_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
                <?= htmlspecialchars(preg_replace('#^https?://(www\.)?facebook\.com/#i', '', $user['facebook_url']), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['whatsapp_number'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366">
              <path d="M17.472 14.382c-.301-.15-1.782-.879-2.057-.98-.276-.1-.476-.15-.677.15-.2.3-.777.98-.952 1.18-.175.2-.351.226-.652.075-.3-.15-1.267-.467-2.414-1.489-.893-.796-1.496-1.78-1.671-2.08-.175-.3-.019-.462.131-.612.135-.135.301-.35.452-.525.15-.176.2-.3.301-.5.1-.2.05-.376-.025-.526-.075-.15-.677-1.631-.927-2.233-.244-.587-.492-.507-.677-.516-.175-.01-.376-.01-.577-.01-.2 0-.526.075-.802.376-.276.3-1.053 1.028-1.053 2.508 0 1.48 1.078 2.909 1.229 3.109.15.2 2.122 3.24 5.141 4.544.718.31 1.279.496 1.716.634.721.23 1.378.197 1.9.12.58-.088 1.782-.728 2.032-1.431.25-.702.25-1.304.175-1.43-.075-.125-.276-.201-.577-.351z"/>
              <path d="M12 2C6.48 2 2 6.48 2 12c0 1.83.49 3.55 1.34 5.03L2 22l5.12-1.33A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.2c-1.57 0-3.04-.43-4.32-1.18l-.31-.18-3.04.79.81-2.96-.2-.32A8.17 8.17 0 013.8 12c0-4.52 3.68-8.2 8.2-8.2s8.2 3.68 8.2 8.2-3.68 8.2-8.2 8.2z"/>
            </svg>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                <span>WhatsApp</span>
                <span style="font-size: 0.68rem; padding: 1px 6px; border-radius: 4px; font-weight: 700; background: <?= !empty($user['whatsapp_public']) ? 'rgba(34, 197, 94, 0.15); color: #15803d;' : 'rgba(100, 116, 139, 0.15); color: #64748b;' ?>">
                  <?= !empty($user['whatsapp_public']) ? '🌐 Public' : '🔒 Private' ?>
                </span>
              </div>
              <?php if (!empty($user['whatsapp_link'])): ?>
                <a href="<?= htmlspecialchars($user['whatsapp_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: #25D366; font-weight: 600; text-decoration: none;">
                  <?= htmlspecialchars($user['whatsapp_number'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php else: ?>
                <span style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($user['whatsapp_number'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['mobile_number_masked']) || !empty($user['phone'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.1rem;" title="Account Mobile Number (Private & Encrypted)">📱</span>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px;">
                <span>Account Mobile (Private)</span>
                <span style="font-size: 0.68rem; background: rgba(59, 130, 246, 0.1); color: var(--primary-color); padding: 1px 5px; border-radius: 4px; font-weight: 600;">🔒 AES-256</span>
              </div>
              <span style="font-family: monospace; font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($user['mobile_number_masked'] ?? $user['phone'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Interests & Topics -->
      <?php if (!empty($user['interests'])): ?>
        <div style="margin-bottom: 24px;">
          <div style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; letter-spacing: 0.05em;">
            Topics & Interests
          </div>
          <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach (array_map('trim', explode(',', $user['interests'])) as $interest): ?>
              <?php if ($interest !== ''): ?>
                <span style="display: inline-block; padding: 5px 14px; border-radius: 9999px; background: rgba(99, 102, 241, 0.09); color: var(--primary-color); font-size: 0.84rem; font-weight: 600;">
                  <?= htmlspecialchars($interest, ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Profile Actions -->
      <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 18px; border-top: 1px solid rgba(226, 232, 240, 0.7);">
        <a href="/profile/edit" class="btn btn-primary btn-pill">Edit Profile Details</a>
      </div>
    </div>
  </section>

  <!-- Tab Panel 3: Followers -->
  <section id="profile-panel-followers" role="tabpanel" aria-labelledby="profile-tab-followers" class="user-followers-section" style="display: none;">
    <?php if (empty($followers)): ?>
      <div class="glass-card empty-state" style="padding: 28px 20px; text-align: center;">
        <div style="font-size: 2.4rem; margin-bottom: 10px;">👥</div>
        <h3 class="empty-title">No followers yet</h3>
        <p class="empty-desc">When readers or fellow authors follow you, they'll appear here.</p>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
        <?php foreach ($followers as $f): ?>
          <?php
            $fAvatar = $f['avatar_url'] ?? \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($f['avatar_path'] ?? null);
            $fInitial = strtoupper(mb_substr($f['display_name'] ?? $f['username'] ?? 'U', 0, 1));
            $fHandle = $f['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($f['username'] ?? null, $f['display_name'] ?? null);
          ?>
          <div class="glass-card" style="padding: 16px 20px; border-radius: 16px; display: flex; align-items: center; gap: 14px;">
            <?php if (!empty($fAvatar)): ?>
              <img src="<?= htmlspecialchars($fAvatar, ENT_QUOTES, 'UTF-8') ?>"
                   alt="<?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>"
                   style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div style="display: none; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; font-size: 1.05rem;">
                <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php else: ?>
              <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; font-size: 1.05rem;">
                <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
            <div style="flex: 1; min-width: 0;">
              <a href="/author/<?= (int) ($f['id'] ?? 0) ?>" style="font-weight: 700; color: var(--text-heading); text-decoration: none; font-size: 0.95rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($fHandle, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <a href="/author/<?= (int) ($f['id'] ?? 0) ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 10px; font-size: 0.78rem;">View</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Tab Panel 4: Following -->
  <section id="profile-panel-following" role="tabpanel" aria-labelledby="profile-tab-following" class="user-following-section" style="display: none;">
    <?php if (empty($following)): ?>
      <div class="glass-card empty-state" style="padding: 28px 20px; text-align: center;">
        <div style="font-size: 2.4rem; margin-bottom: 10px;">👥</div>
        <h3 class="empty-title">Not following anyone yet</h3>
        <p class="empty-desc">Explore authors on Infinity Blog and follow them to see their latest work in your feed.</p>
        <a href="/authors" class="btn btn-primary btn-pill btn-sm" style="margin-top: 14px;">Explore Authors</a>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
        <?php foreach ($following as $f): ?>
          <?php
            $fAvatar = $f['avatar_url'] ?? \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($f['avatar_path'] ?? null);
            $fInitial = strtoupper(mb_substr($f['display_name'] ?? $f['username'] ?? 'U', 0, 1));
            $fHandle = $f['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($f['username'] ?? null, $f['display_name'] ?? null);
          ?>
          <div class="glass-card" style="padding: 16px 20px; border-radius: 16px; display: flex; align-items: center; gap: 14px;">
            <?php if (!empty($fAvatar)): ?>
              <img src="<?= htmlspecialchars($fAvatar, ENT_QUOTES, 'UTF-8') ?>"
                   alt="<?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>"
                   style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div style="display: none; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; font-size: 1.05rem;">
                <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php else: ?>
              <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0; font-size: 1.05rem;">
                <?= htmlspecialchars($fInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
            <div style="flex: 1; min-width: 0;">
              <a href="/author/<?= (int) ($f['id'] ?? 0) ?>" style="font-weight: 700; color: var(--text-heading); text-decoration: none; font-size: 0.95rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars($f['display_name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($fHandle, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <a href="/author/<?= (int) ($f['id'] ?? 0) ?>" class="btn btn-secondary btn-sm btn-pill" style="padding: 4px 10px; font-size: 0.78rem;">View</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabs = {
    posts: { tab: document.getElementById('profile-tab-posts'), panel: document.getElementById('profile-panel-posts') },
    followers: { tab: document.getElementById('profile-tab-followers'), panel: document.getElementById('profile-panel-followers') },
    following: { tab: document.getElementById('profile-tab-following'), panel: document.getElementById('profile-panel-following') },
    about: { tab: document.getElementById('profile-tab-about'), panel: document.getElementById('profile-panel-about') }
  };

  function switchTab(key) {
    Object.keys(tabs).forEach(k => {
      const item = tabs[k];
      if (!item.tab || !item.panel) return;
      if (k === key) {
        item.tab.classList.add('active');
        item.tab.setAttribute('aria-selected', 'true');
        item.tab.setAttribute('tabindex', '0');
        item.panel.style.display = 'block';
      } else {
        item.tab.classList.remove('active');
        item.tab.setAttribute('aria-selected', 'false');
        item.tab.setAttribute('tabindex', '-1');
        item.panel.style.display = 'none';
      }
    });
  }

  Object.keys(tabs).forEach(key => {
    const item = tabs[key];
    if (item.tab) {
      item.tab.addEventListener('click', () => switchTab(key));
    }
  });

  // Keyboard navigation for tab list
  const tabList = Object.values(tabs).map(i => i.tab).filter(Boolean);
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
