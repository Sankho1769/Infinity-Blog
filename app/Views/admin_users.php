<!-- Author Accounts Management (Admin) -->
<div class="dashboard-header dashboard-page-bg">
  <div>
    <h1 class="dashboard-greeting">Author Accounts</h1>
    <p class="dashboard-subhead">Manage author access and provision new writing accounts.</p>
  </div>

  <button type="button" class="btn btn-primary btn-pill" onclick="document.getElementById('new-user-modal').classList.add('open')">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="5" x2="12" y2="19"></line>
      <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    Add Author
  </button>
</div>

<div class="glass-panel" style="padding: 24px;">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 50px;">#</th>
          <th>Name / Username</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= (int) $user['id'] ?></td>
            <td>
              <div style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($user['display_name'] ?? $user['username'], ENT_QUOTES, 'UTF-8') ?></div>
              <div style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::formatHandle($user['username'] ?? null, $user['display_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
            </td>
            <td>
              <span class="user-role-badge <?= $user['role'] === 'admin' ? 'admin' : '' ?>"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td>
              <?php if ($user['status'] === 'active'): ?>
                <span class="badge badge-published">Active</span>
              <?php else: ?>
                <span class="badge badge-draft">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="font-size: 0.85rem; color: var(--text-muted);">
              <?= !empty($user['last_login_at']) ? date('M j, Y H:i', strtotime($user['last_login_at'])) : 'Never' ?>
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <?php if ($user['role'] !== 'admin'): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleUserStatus(<?= (int) $user['id'] ?>, '<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>')">
                  <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add Author Account -->
<div id="new-user-modal" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-card">
    <h3 class="modal-title">Create Author Account</h3>
    <p class="modal-desc">Provision a new author account. Passwords must be at least 12 characters.</p>

    <div id="user-error-alert" class="alert-banner alert-error" style="display: none;"></div>

    <form id="create-user-form">
      <div class="form-group">
        <label for="u-name" class="form-label">Full Name / Display Name <span style="color: var(--danger-color);">*</span></label>
        <input type="text" id="u-display-name" class="form-input" placeholder="e.g. Cristine Palmer" required>
      </div>

      <div class="form-group">
        <label for="u-username" class="form-label">Username <span style="color: var(--danger-color);">*</span></label>
        <input type="text" id="u-username" class="form-input" placeholder="e.g. cristine_palmer" required>
      </div>

      <div class="form-group">
        <label for="u-password" class="form-label">Temporary Password <span style="color: var(--danger-color);">*</span></label>
        <input type="password" id="u-password" class="form-input" placeholder="At least 12 characters" required minlength="12">
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('new-user-modal').classList.remove('open')">Cancel</button>
        <button type="submit" id="save-user-btn" class="btn btn-primary">Create Account</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('create-user-form');
  const errorBox = document.getElementById('user-error-alert');
  const saveBtn = document.getElementById('save-user-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';

    const username = document.getElementById('u-username').value.trim();
    const display_name = document.getElementById('u-display-name').value.trim();
    const password = document.getElementById('u-password').value;

    if (!username || !display_name || !password) {
      errorBox.textContent = 'All fields are required.';
      errorBox.style.display = 'flex';
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Creating Account...';
    try {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      const res = await fetch('/admin/users', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        },
        body: JSON.stringify({ username, display_name, password })
      });
      const data = await res.json();
      if (res.ok) {
        window.location.reload();
      } else {
        errorBox.textContent = data.error?.message || 'Could not create author account.';
        errorBox.style.display = 'flex';
        saveBtn.disabled = false;
        saveBtn.textContent = 'Create Account';
      }
    } catch (err) {
      errorBox.textContent = 'Network error.';
      errorBox.style.display = 'flex';
      saveBtn.disabled = false;
      saveBtn.textContent = 'Create Account';
    }
  });

  window.toggleUserStatus = async function (id, newStatus) {
    if (!confirm(`Are you sure you want to change this author's status to ${newStatus}?`)) return;
    try {
      const csrf = await window.BlogCMS.ensureCsrfToken();
      const res = await fetch(`/admin/users/${id}/status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ status: newStatus })
      });
      if (res.ok) window.location.reload();
      else alert('Failed to update status.');
    } catch (e) {
      alert('Network error.');
    }
  };
});
</script>
