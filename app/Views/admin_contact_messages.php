<!-- Screen: Contact Inquiries Inbox (Admin) -->
<div class="dashboard-header dashboard-page-bg">
  <div>
    <h1 class="dashboard-greeting">Contact Inquiries Inbox</h1>
    <p class="dashboard-subhead">Review and manage reader questions, support messages, copyright notices, and feedback.</p>
  </div>
</div>

<div class="glass-panel" style="padding: 24px;">
  <?php if (empty($messages)): ?>
    <div class="empty-state">
      <div style="font-size: 2.5rem; margin-bottom: 12px;">📬</div>
      <h3 class="empty-title">Inbox is clean</h3>
      <p class="empty-desc">No contact form inquiries have been submitted yet.</p>
    </div>
  <?php else: ?>
    <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
      <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
        Showing <?= count($messages) ?> of <?= (int) $total ?> total inquiries
      </div>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 70px;">Ticket</th>
            <th style="width: 180px;">Sender</th>
            <th style="width: 200px;">Subject</th>
            <th>Message</th>
            <th style="width: 110px;">Status</th>
            <th style="width: 140px;">Received</th>
            <th style="width: 130px; text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($messages as $msg): ?>
            <tr>
              <td style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace; font-weight: 700;">
                #<?= (int) $msg['id'] ?>
              </td>
              <td>
                <div style="font-weight: 700; color: var(--text-heading); font-size: 0.92rem;">
                  <?= htmlspecialchars($msg['name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                  <a href="mailto:<?= htmlspecialchars($msg['email'], ENT_QUOTES, 'UTF-8') ?>" style="color: var(--primary-600, #4f46e5); text-decoration: none;">
                    <?= htmlspecialchars($msg['email'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                </div>
              </td>
              <td style="font-weight: 600; color: var(--text-heading); font-size: 0.9rem;">
                <?= htmlspecialchars($msg['subject'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td style="font-size: 0.88rem; color: var(--text-body); max-width: 360px; word-break: break-word;">
                <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
              </td>
              <td>
                <?php
                  $badgeColor = match ($msg['status']) {
                    'resolved' => 'background: rgba(34, 197, 94, 0.15); color: #15803d;',
                    'read' => 'background: rgba(59, 130, 246, 0.15); color: #1d4ed8;',
                    default => 'background: rgba(245, 158, 11, 0.15); color: #b45309;',
                  };
                ?>
                <span class="badge" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; <?= $badgeColor ?>">
                  <?= htmlspecialchars($msg['status'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td style="font-size: 0.82rem; color: var(--text-muted);">
                <?= htmlspecialchars($msg['created_at'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td style="text-align: right;">
                <form method="POST" action="/admin/contact-messages/<?= (int) $msg['id'] ?>/status" style="display: inline-block;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                  <?php if ($msg['status'] === 'resolved'): ?>
                    <input type="hidden" name="status" value="received">
                    <button type="submit" class="btn btn-outline btn-sm btn-pill" style="font-size: 0.78rem; padding: 4px 10px;">Reopen</button>
                  <?php else: ?>
                    <input type="hidden" name="status" value="resolved">
                    <button type="submit" class="btn btn-primary btn-sm btn-pill" style="font-size: 0.78rem; padding: 4px 10px;">Resolve</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="/admin/contact-messages-page?page=<?= $p ?>" class="btn <?= $p === $currentPage ? 'btn-primary' : 'btn-secondary' ?> btn-sm btn-pill" style="min-width: 36px; padding: 4px 12px;">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
