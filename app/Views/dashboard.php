<?php
  $hour = (int) date('G');
  if ($hour >= 5 && $hour < 12) {
      $dynamicGreeting = 'Good morning';
  } elseif ($hour >= 12 && $hour < 17) {
      $dynamicGreeting = 'Good afternoon';
  } else {
      $dynamicGreeting = 'Good evening';
  }
?>
<!-- Screen 3: Author Dashboard -->
<div class="dashboard-header">
  <div>
    <h1 class="dashboard-greeting">
      <?= $dynamicGreeting ?>, <?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?>! 👋
    </h1>
    <p class="dashboard-subhead">Here's what's happening with your blog.</p>
  </div>

  <a href="/posts/new" class="btn btn-primary btn-pill">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="5" x2="12" y2="19"></line>
      <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    Create New Post
  </a>
</div>

<!-- Database-derived Stats Row -->
<div class="stats-grid">
  <div class="glass-panel stat-card">
    <div class="stat-icon blue">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
      </svg>
    </div>
    <div>
      <div class="stat-value"><?= (int) ($counts['total'] ?? 0) ?></div>
      <div class="stat-label">Total Posts</div>
    </div>
  </div>

  <div class="glass-panel stat-card">
    <div class="stat-icon green">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>
    </div>
    <div>
      <div class="stat-value"><?= (int) ($counts['published'] ?? 0) ?></div>
      <div class="stat-label">Published Articles</div>
    </div>
  </div>

  <div class="glass-panel stat-card">
    <div class="stat-icon amber">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
      </svg>
    </div>
    <div>
      <div class="stat-value"><?= (int) ($counts['draft'] ?? 0) ?></div>
      <div class="stat-label">Drafts</div>
    </div>
  </div>

  <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
    <div class="glass-panel stat-card">
      <div class="stat-icon purple">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= (int) ($adminCounts['users_total'] ?? 2) ?></div>
        <div class="stat-label">System Accounts</div>
      </div>
    </div>
  <?php else: ?>
    <div class="glass-panel stat-card">
      <div class="stat-icon purple">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </div>
      <div>
        <div class="stat-value"><?= (int) ($counts['total'] ?? 0) ?></div>
        <div class="stat-label">Total Written</div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Dashboard Main Grid -->
<div class="dashboard-grid">
  <!-- Recent Posts -->
  <div class="glass-panel" style="padding: 28px;">
    <div class="section-header" style="margin-bottom: 20px;">
      <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark);">Recent Posts</h2>
      <a href="/posts/manage" class="section-link">View All →</a>
    </div>

    <?php if (empty($recentPosts)): ?>
      <div class="empty-state" style="padding: 36px 12px;">
        <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        <h3 class="empty-title">No posts yet</h3>
        <p class="empty-desc">You haven't written any posts yet. Start sharing your ideas with the world!</p>
        <a href="/posts/new" class="btn btn-primary btn-pill">Write Your First Post</a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Status</th>
              <th>Date</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPosts as $post): ?>
              <tr>
                <td>
                  <a href="/posts/<?= (int) $post['id'] ?>/edit" style="font-weight: 600; color: var(--text-dark); text-decoration: none;">
                    <?= htmlspecialchars(mb_substr($post['title'], 0, 42) . (mb_strlen($post['title']) > 42 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?>
                  </a>
                  <?php if (!empty($post['category_name'])): ?>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                      <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($post['status'] === 'published'): ?>
                    <span class="badge badge-published">Published</span>
                  <?php else: ?>
                    <span class="badge badge-draft">Draft</span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">
                  <?= date('M j, Y', strtotime($post['updated_at'] ?? $post['created_at'])) ?>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <?php if ($post['status'] === 'published'): ?>
                    <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm" title="View Public Article" style="padding: 4px 8px;">
                      👁️
                    </a>
                  <?php endif; ?>
                  <a href="/posts/<?= (int) $post['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit Post" style="padding: 4px 8px;">
                    ✏️
                  </a>
                  <button type="button" class="btn btn-danger btn-sm" title="Delete Post" style="padding: 4px 8px;"
                          onclick="openDeleteModal('<?= (($currentUser['role'] ?? '') === 'admin') ? '/admin/posts' : '/author/posts' ?>/<?= (int) $post['id'] ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>')">
                    🗑️
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Quick Actions Sidebar matching Reference Image 2 -->
  <aside>
    <div class="glass-panel" style="padding: 24px; margin-bottom: 24px;">
      <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 16px;">Quick Actions</h3>
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <a href="/posts/new" class="btn btn-primary" style="justify-content: flex-start; padding: 12px 18px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Create New Post
        </a>
        <a href="/posts/manage" class="btn btn-secondary" style="justify-content: flex-start; padding: 12px 18px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
          </svg>
          Manage Posts
        </a>
        <a href="/" class="btn btn-secondary" style="justify-content: flex-start; padding: 12px 18px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
          </svg>
          View Site
        </a>
      </div>
    </div>

    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
      <div class="glass-panel" style="padding: 24px;">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: #4338ca; margin-bottom: 16px;">Admin Controls</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <a href="/admin/categories-page" class="btn btn-secondary" style="justify-content: flex-start; padding: 10px 16px;">
            <span style="margin-right: 8px;">🏷️</span> Manage Categories
          </a>
          <a href="/admin/users-page" class="btn btn-secondary" style="justify-content: flex-start; padding: 10px 16px;">
            <span style="margin-right: 8px;">👥</span> Author Accounts
          </a>
          <a href="/admin/audit-logs-page" class="btn btn-secondary" style="justify-content: flex-start; padding: 10px 16px;">
            <span style="margin-right: 8px;">📜</span> View Audit Logs
          </a>
        </div>
      </div>
    <?php endif; ?>
  </aside>
</div>
