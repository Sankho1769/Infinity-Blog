<!-- Screen 6: Manage Posts (All Posts) -->
<div class="dashboard-header">
  <div>
    <h1 class="dashboard-greeting">All Posts</h1>
    <p class="dashboard-subhead">Manage, edit, publish or delete your articles.</p>
  </div>

  <a href="/posts/new" class="btn btn-primary btn-pill">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="5" x2="12" y2="19"></line>
      <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    Create Post
  </a>
</div>

<!-- Tabs & Filter Bar -->
<div class="glass-panel" style="padding: 16px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
  <!-- Filter Tabs -->
  <div style="display: flex; gap: 8px;">
    <a href="/posts/manage" class="filter-pill <?= empty($currentStatusFilter) ? 'active' : '' ?>">
      All (<?= (int) ($counts['total'] ?? 0) ?>)
    </a>
    <a href="/posts/manage?status=published" class="filter-pill <?= ($currentStatusFilter ?? '') === 'published' ? 'active' : '' ?>">
      Published (<?= (int) ($counts['published'] ?? 0) ?>)
    </a>
    <a href="/posts/manage?status=draft" class="filter-pill <?= ($currentStatusFilter ?? '') === 'draft' ? 'active' : '' ?>">
      Drafts (<?= (int) ($counts['draft'] ?? 0) ?>)
    </a>
  </div>

  <!-- Search form -->
  <form action="/posts/manage" method="GET" style="display: flex; gap: 10px; align-items: center;">
    <?php if (!empty($currentStatusFilter)): ?>
      <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatusFilter, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <input type="search" name="search" class="form-input" style="padding: 8px 16px; font-size: 0.9rem; width: 220px;" placeholder="Search in your posts..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  </form>
</div>

<!-- Posts Table Panel -->
<div class="glass-panel" style="padding: 24px;">
  <?php if (empty($posts)): ?>
    <div class="empty-state">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
      </svg>
      <h3 class="empty-title">No posts found</h3>
      <p class="empty-desc">No articles match your current filter. Create a new post or adjust your filters.</p>
      <a href="/posts/new" class="btn btn-primary btn-pill">Create New Post</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 45%;">Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Last Updated</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($posts as $post): ?>
            <tr>
              <td>
                <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 3px;">
                  <a href="/posts/<?= (int) $post['id'] ?>/edit" style="color: inherit; text-decoration: none;">
                    <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                </div>
                <?php if (($currentUser['role'] ?? '') === 'admin' && !empty($post['author_name'])): ?>
                  <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Author: <?= htmlspecialchars($post['author_name'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($post['category_name'])): ?>
                  <span class="badge badge-category"><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                  <span style="font-size: 0.85rem; color: var(--text-subtle);">None</span>
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
                <?= date('M j, Y H:i', strtotime($post['updated_at'] ?? $post['created_at'])) ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <?php if ($post['status'] === 'published'): ?>
                  <a href="/article/<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm" title="View Published Article">
                    👁️ View
                  </a>
                <?php endif; ?>
                <a href="/posts/<?= (int) $post['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit Post">
                  ✏️ Edit
                </a>
                <button type="button" class="btn btn-danger btn-sm" title="Delete Post"
                        onclick="openDeleteModal('<?= (($currentUser['role'] ?? '') === 'admin') ? '/admin/posts' : '/author/posts' ?>/<?= (int) $post['id'] ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>')">
                  🗑️ Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if (!empty($totalPages) && $totalPages > 1): ?>
      <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="/posts/manage?page=<?= $p ?><?= !empty($currentStatusFilter) ? '&status=' . urlencode($currentStatusFilter) : '' ?>"
             class="btn btn-sm <?= ($currentPage ?? 1) === $p ? 'btn-primary' : 'btn-secondary' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
