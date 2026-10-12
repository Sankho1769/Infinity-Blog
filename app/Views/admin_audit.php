<!-- Audit Logs Viewer (Admin) -->
<div class="dashboard-header dashboard-page-bg">
  <div>
    <h1 class="dashboard-greeting">Security & Audit Logs</h1>
    <p class="dashboard-subhead">System-wide record of authentication, content moderation and administrative changes.</p>
  </div>
</div>

<div class="glass-panel" style="padding: 24px;">
  <?php if (empty($logs)): ?>
    <div class="empty-state">
      <h3 class="empty-title">No audit records</h3>
      <p class="empty-desc">No security or administrative events have been recorded yet.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 60px;">ID</th>
            <th>Event Action</th>
            <th>Actor User ID</th>
            <th>Target Entity</th>
            <th>Timestamp (UTC)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace;">#<?= (int) $log['id'] ?></td>
              <td>
                <span class="badge badge-category" style="font-family: monospace; font-size: 0.8rem;">
                  <?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td>User #<?= (int) $log['actor_user_id'] ?></td>
              <td style="color: var(--text-muted); font-size: 0.85rem;">
                <?= htmlspecialchars($log['entity_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?> #<?= (int) ($log['entity_id'] ?? 0) ?>
              </td>
              <td style="font-size: 0.85rem; color: var(--text-muted);">
                <?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
