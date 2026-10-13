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
              <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-heading); margin-bottom: 4px; letter-spacing: -0.02em;">
                <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>
              </h1>
              <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 8px;">
                @<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
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
            <span style="font-size: 0.78rem; padding: 2px 10px; border-radius: 9999px; background: rgba(34, 197, 94, 0.12); color: #15803d; font-weight: 600;">
              Active
            </span>
            <span style="font-size: 0.78rem; padding: 2px 10px; border-radius: 9999px; background: <?= ($user['profile_visibility'] === 'private') ? 'rgba(239, 68, 68, 0.12); color: #b91c1c;' : 'rgba(99, 102, 241, 0.12); color: #4338ca;' ?> font-weight: 600;">
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
        </div>
      </div>

      <!-- Quick Summary Stats Row -->
      <div style="border-top: 1px solid rgba(226, 232, 240, 0.7); margin-top: 20px; padding-top: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 14px; text-align: center;">
        <div style="padding: 10px 14px; border-radius: 12px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.6);">
          <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary-color);"><?= (int) ($stats['published_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Published Posts</div>
        </div>
        <div style="padding: 10px 14px; border-radius: 12px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.6);">
          <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;"><?= (int) ($stats['draft_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Drafts</div>
        </div>
        <div style="padding: 10px 14px; border-radius: 12px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.6);">
          <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;"><?= (int) ($stats['remix_count'] ?? 0) ?></div>
          <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Remixes</div>
        </div>
        <?php if (!empty($user['created_at'])): ?>
          <div style="padding: 10px 14px; border-radius: 12px; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.6);">
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
          <article class="glass-card article-card" style="display: flex; flex-direction: column; overflow: hidden; border-radius: 18px;">
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

        <?php if (!empty($user['website'])): ?>
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

        <?php if (!empty($user['phone'])): ?>
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.1rem;" title="Phone (Private)">📞</span>
            <div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Phone (Private)</div>
              <span><?= htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') ?></span>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabPosts = document.getElementById('profile-tab-posts');
  const tabAbout = document.getElementById('profile-tab-about');
  const panelPosts = document.getElementById('profile-panel-posts');
  const panelAbout = document.getElementById('profile-panel-about');

  if (!tabPosts || !tabAbout || !panelPosts || !panelAbout) return;

  function switchTab(target) {
    if (target === 'posts') {
      tabPosts.classList.add('active');
      tabPosts.setAttribute('aria-selected', 'true');
      tabPosts.setAttribute('tabindex', '0');

      tabAbout.classList.remove('active');
      tabAbout.setAttribute('aria-selected', 'false');
      tabAbout.setAttribute('tabindex', '-1');

      panelPosts.style.display = 'block';
      panelAbout.style.display = 'none';
    } else {
      tabAbout.classList.add('active');
      tabAbout.setAttribute('aria-selected', 'true');
      tabAbout.setAttribute('tabindex', '0');

      tabPosts.classList.remove('active');
      tabPosts.setAttribute('aria-selected', 'false');
      tabPosts.setAttribute('tabindex', '-1');

      panelAbout.style.display = 'block';
      panelPosts.style.display = 'none';
    }
  }

  tabPosts.addEventListener('click', () => switchTab('posts'));
  tabAbout.addEventListener('click', () => switchTab('about'));

  // Keyboard navigation for tab list
  const tabs = [tabPosts, tabAbout];
  tabs.forEach((tab, index) => {
    tab.addEventListener('keydown', (e) => {
      let nextIndex = null;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        nextIndex = (index + 1) % tabs.length;
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        nextIndex = (index - 1 + tabs.length) % tabs.length;
      } else if (e.key === 'Home') {
        nextIndex = 0;
      } else if (e.key === 'End') {
        nextIndex = tabs.length - 1;
      }
      if (nextIndex !== null) {
        e.preventDefault();
        tabs[nextIndex].focus();
        tabs[nextIndex].click();
      }
    });
  });
});
</script>
