<!-- Screen 7: Categories Page (Admin) -->
<div class="dashboard-header dashboard-page-bg">
  <div>
    <h1 class="dashboard-greeting">Categories</h1>
    <p class="dashboard-subhead">Organise your blog articles with clean, searchable categories.</p>
  </div>

  <button type="button" class="btn btn-primary btn-pill" onclick="document.getElementById('new-category-modal').classList.add('open')">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="5" x2="12" y2="19"></line>
      <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    Add Category
  </button>
</div>

<div class="glass-panel" style="padding: 24px;">
  <?php if (empty($categories)): ?>
    <div class="empty-state">
      <h3 class="empty-title">No categories yet</h3>
      <p class="empty-desc">Create your first category so authors can assign articles to topics.</p>
      <button type="button" class="btn btn-primary btn-pill" onclick="document.getElementById('new-category-modal').classList.add('open')">Add First Category</button>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 50px;">#</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td style="color: var(--text-muted); font-size: 0.85rem;"><?= (int) $cat['id'] ?></td>
              <td><strong><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
              <td><code><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></code></td>
              <td style="color: var(--text-muted); font-size: 0.85rem;">
                <?= htmlspecialchars($cat['description'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td>
                <?php if ($cat['is_active']): ?>
                  <span class="badge badge-published">Active</span>
                <?php else: ?>
                  <span class="badge badge-draft">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleCategoryStatus(<?= (int) $cat['id'] ?>, <?= $cat['is_active'] ? 'false' : 'true' ?>)">
                  <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
                <button type="button" class="btn btn-danger-subtle btn-sm" onclick="deleteCategory(<?= (int) $cat['id'] ?>)">
                  Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: Add Category -->
<div id="new-category-modal" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-card">
    <h3 class="modal-title">Create Category</h3>
    <p class="modal-desc">Add a new category for articles. The URL-safe slug is generated automatically.</p>

    <div id="category-error-alert" class="alert-banner alert-error" style="display: none;"></div>

    <form id="create-category-form">
      <div class="form-group">
        <label for="cat-name" class="form-label">Category Name <span style="color: var(--danger-color);">*</span></label>
        <input type="text" id="cat-name" class="form-input" placeholder="e.g. Technology, Lifestyle" required maxlength="100">
      </div>
      <div class="form-group">
        <label for="cat-desc" class="form-label">Description (Optional)</label>
        <textarea id="cat-desc" class="form-textarea" style="min-height: 90px;" placeholder="Brief description of this category..."></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('new-category-modal').classList.remove('open')">Cancel</button>
        <button type="submit" id="save-cat-btn" class="btn btn-primary">Create Category</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('create-category-form');
  const errorBox = document.getElementById('category-error-alert');
  const saveBtn = document.getElementById('save-cat-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';
    const name = document.getElementById('cat-name').value.trim();
    const description = document.getElementById('cat-desc').value.trim();

    if (!name) {
      errorBox.textContent = 'Category name is required.';
      errorBox.style.display = 'flex';
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Creating...';
    try {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      const res = await fetch('/admin/categories', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        },
        body: JSON.stringify({ name, description })
      });
      const data = await res.json();
      if (res.ok) {
        window.location.reload();
      } else {
        errorBox.textContent = data.error?.message || 'Could not create category.';
        errorBox.style.display = 'flex';
        saveBtn.disabled = false;
        saveBtn.textContent = 'Create Category';
      }
    } catch (err) {
      errorBox.textContent = 'Network error.';
      errorBox.style.display = 'flex';
      saveBtn.disabled = false;
      saveBtn.textContent = 'Create Category';
    }
  });

  window.toggleCategoryStatus = async function (id, activate) {
    if (!confirm(`Are you sure you want to ${activate ? 'activate' : 'deactivate'} this category?`)) return;
    try {
      const csrf = await window.BlogCMS.ensureCsrfToken();
      const endpoint = activate ? `/admin/categories/${id}/activate` : `/admin/categories/${id}/deactivate`;
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
      });
      if (res.ok) window.location.reload();
      else alert('Failed to update category status.');
    } catch (e) {
      alert('Network error.');
    }
  };

  window.deleteCategory = async function (id) {
    if (!confirm('Are you sure you want to delete this category? (Only unreferenced categories can be deleted)')) return;
    try {
      const csrf = await window.BlogCMS.ensureCsrfToken();
      const res = await fetch(`/admin/categories/${id}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
      });
      if (res.ok) window.location.reload();
      else {
        const d = await res.json();
        alert(d.error?.message || 'Cannot delete category with associated posts.');
      }
    } catch (e) {
      alert('Network error.');
    }
  };
});
</script>
