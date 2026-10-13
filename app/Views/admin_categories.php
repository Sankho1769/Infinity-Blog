<!-- Screen 7: Categories Page (Admin) -->
<div class="dashboard-header dashboard-page-bg">
  <div>
    <h1 class="dashboard-greeting">Categories</h1>
    <p class="dashboard-subhead">Organise your blog articles with clean, searchable categories and custom visual branding.</p>
  </div>

  <button type="button" class="btn btn-primary btn-pill" onclick="openCreateModal()">
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
      <button type="button" class="btn btn-primary btn-pill" onclick="openCreateModal()">Add First Category</button>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 50px;">#</th>
            <th style="width: 70px; text-align: center;">Visual</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th style="text-align: center;">Articles</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td style="color: var(--text-muted); font-size: 0.85rem;"><?= (int) $cat['id'] ?></td>
              <td style="text-align: center;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 10px; background: rgba(37, 99, 235, 0.08); border: 1px solid var(--glass-border);">
                  <?= \InfinityBlog\Categories\CategoryIconHelper::renderIcon($cat, 22) ?>
                </div>
              </td>
              <td>
                <strong><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($cat['custom_logo_path'])): ?>
                  <span class="badge" style="margin-left: 6px; font-size: 0.7rem; background: rgba(16, 185, 129, 0.12); color: #059669;">Custom Logo</span>
                <?php elseif (!empty($cat['icon_name'])): ?>
                  <span class="badge" style="margin-left: 6px; font-size: 0.7rem; background: rgba(99, 102, 241, 0.12); color: #4f46e5;"><?= htmlspecialchars($cat['icon_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </td>
              <td><code><?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?></code></td>
              <td style="color: var(--text-muted); font-size: 0.85rem; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars($cat['description'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td style="text-align: center;">
                <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: var(--text-dark); font-weight: 700; padding: 4px 10px; border-radius: 999px; font-size: 0.78rem;">
                  <?= (int) ($cat['post_count'] ?? 0) ?>
                </span>
              </td>
              <td>
                <?php if ($cat['is_active']): ?>
                  <span class="badge badge-published">Active</span>
                <?php else: ?>
                  <span class="badge badge-draft">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                  Edit
                </button>
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
<div id="new-category-modal" class="modal-overlay" role="dialog" aria-modal="true" style="display: none;">
  <div class="modal-card" style="max-width: 580px; width: 92%; max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 class="modal-title" style="margin: 0;">Create Category</h3>
      <button type="button" class="alert-close-btn" onclick="closeCreateModal()" style="font-size: 1.4rem;">×</button>
    </div>
    <p class="modal-desc" style="margin-bottom: 16px;">Add a new category with distinct iconography or custom branding.</p>

    <div id="category-error-alert" class="alert-banner alert-error" style="display: none; margin-bottom: 16px;"></div>

    <form id="create-category-form">
      <div class="form-group">
        <label for="cat-name" class="form-label">Category Name <span style="color: var(--danger-color);">*</span></label>
        <input type="text" id="cat-name" class="form-input" placeholder="e.g. Distributed Systems, Product Management" required maxlength="100">
      </div>

      <div class="form-group">
        <label for="cat-desc" class="form-label">Description (Optional)</label>
        <textarea id="cat-desc" class="form-textarea" style="min-height: 70px;" placeholder="Brief summary of articles in this topic..."></textarea>
      </div>

      <!-- Icon & Logo Management Section -->
      <div style="margin-top: 18px; padding: 16px; background: rgba(248, 250, 252, 0.85); border-radius: 12px; border: 1px solid var(--glass-border);">
        <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 8px;">Visual Identity (Icon / Logo)</div>

        <!-- Toggle Option A vs Option B -->
        <div style="display: flex; gap: 8px; margin-bottom: 14px;">
          <button type="button" class="btn btn-sm btn-primary btn-pill branding-tab-btn active" id="create-tab-icon-btn" onclick="switchCreateBrandingTab('icon')">
            Option A: Allowlist Icon
          </button>
          <button type="button" class="btn btn-sm btn-secondary btn-pill branding-tab-btn" id="create-tab-logo-btn" onclick="switchCreateBrandingTab('logo')">
            Option B: Upload Custom Logo
          </button>
        </div>

        <!-- Option A: Icon Picker -->
        <div id="create-icon-picker-section">
          <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
            <input type="text" id="create-icon-search" class="form-input" placeholder="Search icons (e.g. code, brain, chart, rocket)..." style="font-size: 0.84rem; padding: 6px 12px;">
            <div id="create-selected-icon-preview" style="display: flex; align-items: center; gap: 8px; background: #fff; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--glass-border); flex-shrink: 0;">
              <span id="create-icon-preview-box"><?= \InfinityBlog\Categories\CategoryIconHelper::getIcon('globe-code', 20) ?></span>
              <span id="create-icon-preview-name" style="font-size: 0.78rem; font-weight: 600; color: var(--text-dark);">globe-code</span>
            </div>
          </div>
          <input type="hidden" id="create-cat-icon-name" value="globe-code">

          <div id="create-icons-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 8px; max-height: 180px; overflow-y: auto; padding: 8px; background: #ffffff; border-radius: 8px; border: 1px solid var(--glass-border);">
            <?php foreach (($iconAllowlist ?? \InfinityBlog\Categories\CategoryIconHelper::getAllowlist()) as $ic): ?>
              <button type="button" class="icon-picker-item <?= $ic['id'] === 'globe-code' ? 'selected' : '' ?>" data-id="<?= htmlspecialchars($ic['id'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($ic['name'], ENT_QUOTES, 'UTF-8') ?>" data-keywords="<?= htmlspecialchars(strtolower($ic['keywords'] . ' ' . $ic['id']), ENT_QUOTES, 'UTF-8') ?>" onclick="selectCreateIcon('<?= htmlspecialchars($ic['id'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($ic['name'], ENT_QUOTES, 'UTF-8') ?>')" title="<?= htmlspecialchars($ic['name'], ENT_QUOTES, 'UTF-8') ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 8px 4px; border-radius: 8px; border: 1px solid <?= $ic['id'] === 'globe-code' ? 'var(--primary-color)' : 'transparent' ?>; background: <?= $ic['id'] === 'globe-code' ? 'rgba(37, 99, 235, 0.1)' : 'transparent' ?>; cursor: pointer; transition: all 0.15s ease;">
                <span><?= $ic['svg'] ?></span>
                <span style="font-size: 0.65rem; color: var(--text-muted); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60px;"><?= htmlspecialchars($ic['id'], ENT_QUOTES, 'UTF-8') ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Option B: Custom Logo Upload -->
        <div id="create-logo-upload-section" style="display: none;">
          <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0; margin-bottom: 10px;">
            Upload a distinct logo or brand mark (JPEG, PNG, or WebP; maximum size 1 MB).
          </p>
          <input type="file" id="create-cat-logo-file" class="form-input" accept="image/jpeg,image/png,image/webp" style="padding: 6px; font-size: 0.85rem;" onchange="previewCreateLogoFile(this)">
          <input type="hidden" id="create-cat-custom-logo-path" value="">

          <div id="create-logo-preview-card" style="display: none; align-items: center; gap: 12px; margin-top: 10px; padding: 10px; background: #fff; border-radius: 8px; border: 1px solid var(--glass-border);">
            <img id="create-logo-preview-img" src="" alt="Preview" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px;">
            <div style="flex: 1; min-width: 0;">
              <div id="create-logo-preview-filename" style="font-size: 0.82rem; font-weight: 700; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
              <div id="create-logo-preview-filesize" style="font-size: 0.72rem; color: var(--text-muted);"></div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearCreateLogoFile()" style="font-size: 0.75rem; padding: 3px 8px;">Clear</button>
          </div>
        </div>
      </div>

      <div class="modal-actions" style="margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
        <button type="submit" id="save-cat-btn" class="btn btn-primary">Create Category</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit Category -->
<div id="edit-category-modal" class="modal-overlay" role="dialog" aria-modal="true" style="display: none;">
  <div class="modal-card" style="max-width: 580px; width: 92%; max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 class="modal-title" style="margin: 0;">Edit Category</h3>
      <button type="button" class="alert-close-btn" onclick="closeEditModal()" style="font-size: 1.4rem;">×</button>
    </div>
    <p class="modal-desc" style="margin-bottom: 16px;">Update name, description, and visual icon or logo branding.</p>

    <div id="edit-category-error-alert" class="alert-banner alert-error" style="display: none; margin-bottom: 16px;"></div>

    <form id="edit-category-form">
      <input type="hidden" id="edit-cat-id" value="">

      <div class="form-group">
        <label for="edit-cat-name" class="form-label">Category Name <span style="color: var(--danger-color);">*</span></label>
        <input type="text" id="edit-cat-name" class="form-input" required maxlength="100">
      </div>

      <div class="form-group">
        <label for="edit-cat-desc" class="form-label">Description (Optional)</label>
        <textarea id="edit-cat-desc" class="form-textarea" style="min-height: 70px;"></textarea>
      </div>

      <!-- Icon & Logo Management Section -->
      <div style="margin-top: 18px; padding: 16px; background: rgba(248, 250, 252, 0.85); border-radius: 12px; border: 1px solid var(--glass-border);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark);">Visual Identity (Icon / Logo)</div>
          <div id="edit-current-visual-badge" style="display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted);">
            <span>Current:</span>
            <span id="edit-current-visual-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background: #fff; border-radius: 6px; border: 1px solid var(--glass-border);"></span>
          </div>
        </div>

        <!-- Toggle Option A vs Option B -->
        <div style="display: flex; gap: 8px; margin-bottom: 14px;">
          <button type="button" class="btn btn-sm btn-primary btn-pill branding-tab-btn active" id="edit-tab-icon-btn" onclick="switchEditBrandingTab('icon')">
            Option A: Allowlist Icon
          </button>
          <button type="button" class="btn btn-sm btn-secondary btn-pill branding-tab-btn" id="edit-tab-logo-btn" onclick="switchEditBrandingTab('logo')">
            Option B: Upload Custom Logo
          </button>
        </div>

        <!-- Option A: Icon Picker -->
        <div id="edit-icon-picker-section">
          <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
            <input type="text" id="edit-icon-search" class="form-input" placeholder="Search icons (e.g. code, brain, chart, rocket)..." style="font-size: 0.84rem; padding: 6px 12px;">
            <div id="edit-selected-icon-preview" style="display: flex; align-items: center; gap: 8px; background: #fff; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--glass-border); flex-shrink: 0;">
              <span id="edit-icon-preview-box"><?= \InfinityBlog\Categories\CategoryIconHelper::getIcon('globe-code', 20) ?></span>
              <span id="edit-icon-preview-name" style="font-size: 0.78rem; font-weight: 600; color: var(--text-dark);">globe-code</span>
            </div>
          </div>
          <input type="hidden" id="edit-cat-icon-name" value="">

          <div id="edit-icons-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 8px; max-height: 180px; overflow-y: auto; padding: 8px; background: #ffffff; border-radius: 8px; border: 1px solid var(--glass-border);">
            <?php foreach (($iconAllowlist ?? \InfinityBlog\Categories\CategoryIconHelper::getAllowlist()) as $ic): ?>
              <button type="button" class="icon-picker-item" data-id="<?= htmlspecialchars($ic['id'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($ic['name'], ENT_QUOTES, 'UTF-8') ?>" data-keywords="<?= htmlspecialchars(strtolower($ic['keywords'] . ' ' . $ic['id']), ENT_QUOTES, 'UTF-8') ?>" onclick="selectEditIcon('<?= htmlspecialchars($ic['id'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($ic['name'], ENT_QUOTES, 'UTF-8') ?>')" title="<?= htmlspecialchars($ic['name'], ENT_QUOTES, 'UTF-8') ?>" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 8px 4px; border-radius: 8px; border: 1px solid transparent; background: transparent; cursor: pointer; transition: all 0.15s ease;">
                <span><?= $ic['svg'] ?></span>
                <span style="font-size: 0.65rem; color: var(--text-muted); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60px;"><?= htmlspecialchars($ic['id'], ENT_QUOTES, 'UTF-8') ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Option B: Custom Logo Upload -->
        <div id="edit-logo-upload-section" style="display: none;">
          <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0; margin-bottom: 10px;">
            Upload a distinct logo or brand mark (JPEG, PNG, or WebP; maximum size 1 MB).
          </p>

          <div id="edit-existing-logo-box" style="display: none; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 10px 14px; background: rgba(16, 185, 129, 0.08); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.25);">
            <div style="display: flex; align-items: center; gap: 10px;">
              <img id="edit-existing-logo-img" src="" alt="Current logo" style="width: 32px; height: 32px; object-fit: contain; border-radius: 6px;">
              <div>
                <div style="font-size: 0.82rem; font-weight: 700; color: #065f46;">Current Custom Logo</div>
                <div style="font-size: 0.72rem; color: #047857;" id="edit-existing-logo-path-text"></div>
              </div>
            </div>
            <button type="button" class="btn btn-danger-subtle btn-sm" id="edit-remove-logo-btn" onclick="markRemoveEditLogo()" style="font-size: 0.78rem; padding: 4px 10px;">
              Remove Logo
            </button>
          </div>

          <input type="file" id="edit-cat-logo-file" class="form-input" accept="image/jpeg,image/png,image/webp" style="padding: 6px; font-size: 0.85rem;" onchange="previewEditLogoFile(this)">
          <input type="hidden" id="edit-cat-custom-logo-path" value="">
          <input type="hidden" id="edit-cat-remove-logo" value="0">

          <div id="edit-logo-preview-card" style="display: none; align-items: center; gap: 12px; margin-top: 10px; padding: 10px; background: #fff; border-radius: 8px; border: 1px solid var(--glass-border);">
            <img id="edit-logo-preview-img" src="" alt="Preview" style="width: 36px; height: 36px; object-fit: contain; border-radius: 6px;">
            <div style="flex: 1; min-width: 0;">
              <div id="edit-logo-preview-filename" style="font-size: 0.82rem; font-weight: 700; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
              <div id="edit-logo-preview-filesize" style="font-size: 0.72rem; color: var(--text-muted);"></div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearEditLogoFile()" style="font-size: 0.75rem; padding: 3px 8px;">Clear</button>
          </div>
        </div>
      </div>

      <div class="modal-actions" style="margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" id="edit-save-cat-btn" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const iconList = <?= json_encode(\InfinityBlog\Categories\CategoryIconHelper::getAllowlist(), JSON_UNESCAPED_SLASHES) ?>;

  // Search filter for create icon picker
  const createSearch = document.getElementById('create-icon-search');
  if (createSearch) {
    createSearch.addEventListener('input', () => {
      const q = createSearch.value.toLowerCase().trim();
      document.querySelectorAll('#create-icons-grid .icon-picker-item').forEach(btn => {
        const kw = btn.getAttribute('data-keywords') || '';
        btn.style.display = kw.includes(q) ? 'flex' : 'none';
      });
    });
  }

  // Search filter for edit icon picker
  const editSearch = document.getElementById('edit-icon-search');
  if (editSearch) {
    editSearch.addEventListener('input', () => {
      const q = editSearch.value.toLowerCase().trim();
      document.querySelectorAll('#edit-icons-grid .icon-picker-item').forEach(btn => {
        const kw = btn.getAttribute('data-keywords') || '';
        btn.style.display = kw.includes(q) ? 'flex' : 'none';
      });
    });
  }

  // Create form submission
  const createForm = document.getElementById('create-category-form');
  const createErrorBox = document.getElementById('category-error-alert');
  const createSaveBtn = document.getElementById('save-cat-btn');

  createForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    createErrorBox.style.display = 'none';

    const name = document.getElementById('cat-name').value.trim();
    const description = document.getElementById('cat-desc').value.trim();
    let iconName = document.getElementById('create-cat-icon-name').value.trim();
    let customLogoPath = document.getElementById('create-cat-custom-logo-path').value.trim();
    const logoFile = document.getElementById('create-cat-logo-file').files[0];

    if (!name) {
      createErrorBox.textContent = 'Category name is required.';
      createErrorBox.style.display = 'flex';
      return;
    }

    createSaveBtn.disabled = true;
    createSaveBtn.textContent = 'Saving category...';

    try {
      const csrf = (window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function')
        ? await window.BlogCMS.ensureCsrfToken()
        : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

      // Upload logo first if file chosen
      if (logoFile) {
        createSaveBtn.textContent = 'Uploading logo...';
        const formData = new FormData();
        formData.append('logo', logoFile);
        formData.append('csrf_token', csrf);

        const uploadRes = await fetch('/admin/categories/upload-logo', {
          method: 'POST',
          headers: {
            'X-CSRF-Token': csrf
          },
          body: formData
        });

        const uploadData = await uploadRes.json();
        if (!uploadRes.ok) {
          throw new Error(uploadData.error?.message || 'Failed to upload category logo.');
        }
        customLogoPath = uploadData.data?.path || uploadData.data?.url || '';
      }

      createSaveBtn.textContent = 'Creating category...';
      const payload = {
        name,
        description: description || null,
        icon_name: iconName || null,
        custom_logo_path: customLogoPath || null
      };

      const res = await fetch('/admin/categories', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (res.ok) {
        window.location.reload();
      } else {
        createErrorBox.textContent = data.error?.message || 'Could not create category.';
        createErrorBox.style.display = 'flex';
        createSaveBtn.disabled = false;
        createSaveBtn.textContent = 'Create Category';
      }
    } catch (err) {
      createErrorBox.textContent = err.message || 'Network error.';
      createErrorBox.style.display = 'flex';
      createSaveBtn.disabled = false;
      createSaveBtn.textContent = 'Create Category';
    }
  });

  // Edit form submission
  const editForm = document.getElementById('edit-category-form');
  const editErrorBox = document.getElementById('edit-category-error-alert');
  const editSaveBtn = document.getElementById('edit-save-cat-btn');

  editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    editErrorBox.style.display = 'none';

    const id = document.getElementById('edit-cat-id').value;
    const name = document.getElementById('edit-cat-name').value.trim();
    const description = document.getElementById('edit-cat-desc').value.trim();
    let iconName = document.getElementById('edit-cat-icon-name').value.trim();
    let customLogoPath = document.getElementById('edit-cat-custom-logo-path').value.trim();
    const removeLogo = document.getElementById('edit-cat-remove-logo').value === '1';
    const logoFile = document.getElementById('edit-cat-logo-file').files[0];

    if (!name) {
      editErrorBox.textContent = 'Category name is required.';
      editErrorBox.style.display = 'flex';
      return;
    }

    editSaveBtn.disabled = true;
    editSaveBtn.textContent = 'Updating...';

    try {
      const csrf = (window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function')
        ? await window.BlogCMS.ensureCsrfToken()
        : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

      // Upload logo first if file chosen
      if (logoFile) {
        editSaveBtn.textContent = 'Uploading logo...';
        const formData = new FormData();
        formData.append('logo', logoFile);
        formData.append('csrf_token', csrf);

        const uploadRes = await fetch('/admin/categories/upload-logo', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: formData
        });

        const uploadData = await uploadRes.json();
        if (!uploadRes.ok) {
          throw new Error(uploadData.error?.message || 'Failed to upload category logo.');
        }
        customLogoPath = uploadData.data?.path || uploadData.data?.url || '';
      }

      editSaveBtn.textContent = 'Saving changes...';
      const payload = {
        name,
        description: description || null,
        icon_name: iconName || null,
        custom_logo_path: customLogoPath || null,
        remove_custom_logo: removeLogo
      };

      const res = await fetch(`/admin/categories/${id}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (res.ok) {
        window.location.reload();
      } else {
        editErrorBox.textContent = data.error?.message || 'Could not update category.';
        editErrorBox.style.display = 'flex';
        editSaveBtn.disabled = false;
        editSaveBtn.textContent = 'Save Changes';
      }
    } catch (err) {
      editErrorBox.textContent = err.message || 'Network error.';
      editErrorBox.style.display = 'flex';
      editSaveBtn.disabled = false;
      editSaveBtn.textContent = 'Save Changes';
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

/* Modal & Tab Control Functions */
function openCreateModal() {
  document.getElementById('new-category-modal').style.display = 'flex';
}
function closeCreateModal() {
  document.getElementById('new-category-modal').style.display = 'none';
}

function openEditModal(cat) {
  document.getElementById('edit-cat-id').value = cat.id;
  document.getElementById('edit-cat-name').value = cat.name;
  document.getElementById('edit-cat-desc').value = cat.description || '';
  document.getElementById('edit-cat-icon-name').value = cat.icon_name || '';
  document.getElementById('edit-cat-custom-logo-path').value = cat.custom_logo_path || '';
  document.getElementById('edit-cat-remove-logo').value = '0';

  // Highlight selected icon in grid
  const initialIcon = cat.icon_name || 'globe-code';
  selectEditIcon(initialIcon, initialIcon);

  // If custom logo exists, show current logo box
  const existingBox = document.getElementById('edit-existing-logo-box');
  const existingImg = document.getElementById('edit-existing-logo-img');
  const existingText = document.getElementById('edit-existing-logo-path-text');
  if (cat.custom_logo_path) {
    existingBox.style.display = 'flex';
    existingImg.src = cat.custom_logo_path.startsWith('http') || cat.custom_logo_path.startsWith('/')
      ? cat.custom_logo_path
      : '/uploads/category-logos/' + cat.custom_logo_path;
    existingText.textContent = cat.custom_logo_path;
    switchEditBrandingTab('logo');
  } else {
    existingBox.style.display = 'none';
    switchEditBrandingTab('icon');
  }

  // Update current visual indicator
  const curIndicator = document.getElementById('edit-current-visual-icon');
  if (cat.custom_logo_path) {
    curIndicator.innerHTML = `<img src="${existingImg.src}" style="width: 20px; height: 20px; object-fit: contain;">`;
  } else {
    const selBtn = document.querySelector(`#edit-icons-grid .icon-picker-item[data-id="${initialIcon}"]`);
    curIndicator.innerHTML = selBtn ? selBtn.querySelector('span').innerHTML : '📁';
  }

  clearEditLogoFile();
  document.getElementById('edit-category-modal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('edit-category-modal').style.display = 'none';
}

function switchCreateBrandingTab(tab) {
  const iconSec = document.getElementById('create-icon-picker-section');
  const logoSec = document.getElementById('create-logo-upload-section');
  const iconBtn = document.getElementById('create-tab-icon-btn');
  const logoBtn = document.getElementById('create-tab-logo-btn');

  if (tab === 'icon') {
    iconSec.style.display = 'block';
    logoSec.style.display = 'none';
    iconBtn.className = 'btn btn-sm btn-primary btn-pill branding-tab-btn active';
    logoBtn.className = 'btn btn-sm btn-secondary btn-pill branding-tab-btn';
  } else {
    iconSec.style.display = 'none';
    logoSec.style.display = 'block';
    iconBtn.className = 'btn btn-sm btn-secondary btn-pill branding-tab-btn';
    logoBtn.className = 'btn btn-sm btn-primary btn-pill branding-tab-btn active';
  }
}

function switchEditBrandingTab(tab) {
  const iconSec = document.getElementById('edit-icon-picker-section');
  const logoSec = document.getElementById('edit-logo-upload-section');
  const iconBtn = document.getElementById('edit-tab-icon-btn');
  const logoBtn = document.getElementById('edit-tab-logo-btn');

  if (tab === 'icon') {
    iconSec.style.display = 'block';
    logoSec.style.display = 'none';
    iconBtn.className = 'btn btn-sm btn-primary btn-pill branding-tab-btn active';
    logoBtn.className = 'btn btn-sm btn-secondary btn-pill branding-tab-btn';
  } else {
    iconSec.style.display = 'none';
    logoSec.style.display = 'block';
    iconBtn.className = 'btn btn-sm btn-secondary btn-pill branding-tab-btn';
    logoBtn.className = 'btn btn-sm btn-primary btn-pill branding-tab-btn active';
  }
}

function selectCreateIcon(id, name) {
  document.getElementById('create-cat-icon-name').value = id;
  document.getElementById('create-icon-preview-name').textContent = id;
  document.querySelectorAll('#create-icons-grid .icon-picker-item').forEach(btn => {
    const isThis = btn.getAttribute('data-id') === id;
    btn.style.borderColor = isThis ? 'var(--primary-color)' : 'transparent';
    btn.style.background = isThis ? 'rgba(37, 99, 235, 0.1)' : 'transparent';
    if (isThis) {
      document.getElementById('create-icon-preview-box').innerHTML = btn.querySelector('span').innerHTML;
    }
  });
}

function selectEditIcon(id, name) {
  document.getElementById('edit-cat-icon-name').value = id;
  document.getElementById('edit-icon-preview-name').textContent = id;
  document.querySelectorAll('#edit-icons-grid .icon-picker-item').forEach(btn => {
    const isThis = btn.getAttribute('data-id') === id;
    btn.style.borderColor = isThis ? 'var(--primary-color)' : 'transparent';
    btn.style.background = isThis ? 'rgba(37, 99, 235, 0.1)' : 'transparent';
    if (isThis) {
      document.getElementById('edit-icon-preview-box').innerHTML = btn.querySelector('span').innerHTML;
    }
  });
}

function previewCreateLogoFile(input) {
  const file = input.files[0];
  const card = document.getElementById('create-logo-preview-card');
  const img = document.getElementById('create-logo-preview-img');
  const name = document.getElementById('create-logo-preview-filename');
  const size = document.getElementById('create-logo-preview-filesize');

  if (file) {
    if (file.size > 1024 * 1024) {
      alert('Selected file exceeds the 1 MB limit. Please select a smaller logo.');
      input.value = '';
      card.style.display = 'none';
      return;
    }
    img.src = URL.createObjectURL(file);
    name.textContent = file.name;
    size.textContent = (file.size / 1024).toFixed(1) + ' KB';
    card.style.display = 'flex';
  } else {
    card.style.display = 'none';
  }
}

function clearCreateLogoFile() {
  document.getElementById('create-cat-logo-file').value = '';
  document.getElementById('create-cat-custom-logo-path').value = '';
  document.getElementById('create-logo-preview-card').style.display = 'none';
}

function previewEditLogoFile(input) {
  const file = input.files[0];
  const card = document.getElementById('edit-logo-preview-card');
  const img = document.getElementById('edit-logo-preview-img');
  const name = document.getElementById('edit-logo-preview-filename');
  const size = document.getElementById('edit-logo-preview-filesize');

  if (file) {
    if (file.size > 1024 * 1024) {
      alert('Selected file exceeds the 1 MB limit. Please select a smaller logo.');
      input.value = '';
      card.style.display = 'none';
      return;
    }
    img.src = URL.createObjectURL(file);
    name.textContent = file.name;
    size.textContent = (file.size / 1024).toFixed(1) + ' KB';
    card.style.display = 'flex';
    document.getElementById('edit-cat-remove-logo').value = '0';
  } else {
    card.style.display = 'none';
  }
}

function clearEditLogoFile() {
  document.getElementById('edit-cat-logo-file').value = '';
  document.getElementById('edit-logo-preview-card').style.display = 'none';
}

function markRemoveEditLogo() {
  if (confirm('Revert category logo to use the standard icon from the allowlist?')) {
    document.getElementById('edit-cat-remove-logo').value = '1';
    document.getElementById('edit-cat-custom-logo-path').value = '';
    document.getElementById('edit-existing-logo-box').style.display = 'none';
    clearEditLogoFile();
    switchEditBrandingTab('icon');
  }
}
</script>
