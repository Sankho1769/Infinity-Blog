<!-- Screen: Reported Posts Moderation (Admin) -->
<div class="dashboard-header dashboard-page-bg">
  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
    <div>
      <h1 class="dashboard-greeting" style="display: flex; align-items: center; gap: 10px;">
        <span>🚩</span> Reported Posts Moderation
      </h1>
      <p class="dashboard-subhead">
        Review user-submitted content reports, investigate violations, and enforce community publishing guidelines.
      </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <a href="/admin/reported-posts-page?view=grouped" class="btn <?= ($viewMode ?? 'grouped') === 'grouped' ? 'btn-primary' : 'btn-secondary' ?> btn-sm btn-pill">
        📊 Grouped by Post
      </a>
      <a href="/admin/reported-posts-page?view=list" class="btn <?= ($viewMode ?? '') === 'list' ? 'btn-primary' : 'btn-secondary' ?> btn-sm btn-pill">
        📋 All Reports List
      </a>
    </div>
  </div>
</div>

<!-- Moderation Summary Stat Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
  <div class="glass-card stat-card" style="padding: 18px 20px; border-radius: 16px;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Total Reports</div>
    <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-dark); margin-top: 4px;"><?= (int) ($stats['total'] ?? 0) ?></div>
    <div style="font-size: 0.78rem; color: var(--text-subtle); margin-top: 2px;">All-time submissions</div>
  </div>
  <div class="glass-card stat-card" style="padding: 18px 20px; border-radius: 16px; border-left: 4px solid #ef4444;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #dc2626; letter-spacing: 0.05em;">Pending Review</div>
    <div style="font-size: 1.85rem; font-weight: 800; color: #dc2626; margin-top: 4px;"><?= (int) ($stats['pending'] ?? 0) ?></div>
    <div style="font-size: 0.78rem; color: var(--text-subtle); margin-top: 2px;">Awaiting moderation</div>
  </div>
  <div class="glass-card stat-card" style="padding: 18px 20px; border-radius: 16px; border-left: 4px solid #3b82f6;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #2563eb; letter-spacing: 0.05em;">Under Review</div>
    <div style="font-size: 1.85rem; font-weight: 800; color: #2563eb; margin-top: 4px;"><?= (int) ($stats['under_review'] ?? 0) ?></div>
    <div style="font-size: 0.78rem; color: var(--text-subtle); margin-top: 2px;">In-progress inquiry</div>
  </div>
  <div class="glass-card stat-card" style="padding: 18px 20px; border-radius: 16px; border-left: 4px solid #10b981;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #059669; letter-spacing: 0.05em;">Action Taken</div>
    <div style="font-size: 1.85rem; font-weight: 800; color: #059669; margin-top: 4px;"><?= (int) ($stats['action_taken'] ?? 0) ?></div>
    <div style="font-size: 0.78rem; color: var(--text-subtle); margin-top: 2px;">Unpublished or resolved</div>
  </div>
  <div class="glass-card stat-card" style="padding: 18px 20px; border-radius: 16px; border-left: 4px solid #94a3b8;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">Dismissed</div>
    <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-dark); margin-top: 4px;"><?= (int) ($stats['dismissed'] ?? 0) ?></div>
    <div style="font-size: 0.78rem; color: var(--text-subtle); margin-top: 2px;">No violation found</div>
  </div>
</div>

<!-- Moderation Filter Toolbar -->
<div class="glass-panel" style="padding: 16px 20px; margin-bottom: 24px; border-radius: var(--radius-md);">
  <form method="GET" action="/admin/reported-posts-page" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode ?? 'grouped', ENT_QUOTES, 'UTF-8') ?>">

    <!-- Search Input -->
    <div style="flex: 1; min-width: 220px; position: relative;">
      <input type="search" name="search" class="form-input" placeholder="Search by post title, author, explanation..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 38px; font-size: 0.88rem; padding-left: 34px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 10px; top: 11px; width: 16px; height: 16px; color: var(--text-muted);" aria-hidden="true">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
    </div>

    <!-- Status Filter -->
    <div style="min-width: 160px;">
      <select name="status" class="form-input" style="width: 100%; height: 38px; font-size: 0.88rem;">
        <option value="">All Statuses</option>
        <option value="pending" <?= ($statusFilter ?? '') === 'pending' ? 'selected' : '' ?>>Pending (<?= (int) ($stats['pending'] ?? 0) ?>)</option>
        <option value="under_review" <?= ($statusFilter ?? '') === 'under_review' ? 'selected' : '' ?>>Under Review</option>
        <option value="action_taken" <?= ($statusFilter ?? '') === 'action_taken' ? 'selected' : '' ?>>Action Taken</option>
        <option value="dismissed" <?= ($statusFilter ?? '') === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
      </select>
    </div>

    <?php if (($viewMode ?? '') === 'list'): ?>
      <!-- Reason Filter (Available in List View) -->
      <div style="min-width: 180px;">
        <select name="reason" class="form-input" style="width: 100%; height: 38px; font-size: 0.88rem;">
          <option value="">All Reasons</option>
          <?php foreach (($allowedReasons ?? []) as $r): ?>
            <option value="<?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?>" <?= ($reasonFilter ?? '') === $r ? 'selected' : '' ?>>
              <?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary btn-sm btn-pill" style="height: 38px; padding: 0 18px;">
      Filter
    </button>
    <?php if (!empty($statusFilter) || !empty($reasonFilter) || !empty($searchQuery)): ?>
      <a href="/admin/reported-posts-page?view=<?= urlencode($viewMode ?? 'grouped') ?>" class="btn btn-secondary btn-sm btn-pill" style="height: 38px; padding: 0 14px;">
        Reset
      </a>
    <?php endif; ?>
  </form>
</div>

<!-- Main Moderation Content Panel -->
<div class="glass-panel" style="padding: 24px; border-radius: var(--radius-lg);">

  <?php if (($viewMode ?? 'grouped') === 'grouped'): ?>
    <!-- ==================== VIEW 1: GROUPED BY POST ==================== -->
    <?php if (empty($groupedPosts)): ?>
      <div class="empty-state" style="padding: 48px 24px; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 12px;">🛡️</div>
        <h3 class="empty-title">No reported posts found</h3>
        <p class="empty-desc">
          <?= (!empty($statusFilter) || !empty($searchQuery)) ? 'No reported articles match your active filter criteria.' : 'Great news! There are currently no community reports awaiting moderation.' ?>
        </p>
      </div>
    <?php else: ?>
      <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
          Showing <?= count($groupedPosts) ?> of <?= (int) $total ?> reported posts
        </div>
      </div>

      <div class="table-responsive">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th style="width: 50px;">ID</th>
              <th>Article Title &amp; Target</th>
              <th style="width: 160px;">Author</th>
              <th style="width: 140px;">Report Count</th>
              <th style="width: 120px;">Article Status</th>
              <th style="width: 150px;">Latest Report</th>
              <th style="width: 220px; text-align: right;">Moderation Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groupedPosts as $p): ?>
              <tr>
                <td style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace; font-weight: 700;">
                  #<?= (int) $p['post_id'] ?>
                </td>
                <td>
                  <div style="font-weight: 700; color: var(--text-heading); font-size: 0.96rem; line-height: 1.35; margin-bottom: 4px;">
                    <a href="/article/<?= (int) $p['post_id'] ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;" title="Open article in new tab">
                      <?= htmlspecialchars($p['post_title'], ENT_QUOTES, 'UTF-8') ?> ↗
                    </a>
                  </div>
                  <div style="font-size: 0.78rem; color: var(--text-muted);">
                    <a href="/article/<?= (int) $p['post_id'] ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-color); text-decoration: none;">
                      Inspect Article
                    </a>
                    ·
                    <a href="/posts/<?= (int) $p['post_id'] ?>/edit" style="color: var(--text-subtle); text-decoration: none;">
                      Edit in Studio
                    </a>
                  </div>
                </td>
                <td>
                  <div style="font-weight: 600; color: var(--text-dark); font-size: 0.88rem;">
                    <?= htmlspecialchars($p['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <div style="font-size: 0.76rem; color: var(--text-muted);">
                    <a href="/authors/<?= rawurlencode($p['author_username'] ?? '') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-muted); text-decoration: none;">
                      @<?= htmlspecialchars($p['author_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  </div>
                </td>
                <td>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <span class="badge" style="font-size: 0.82rem; font-weight: 800; background: rgba(239, 68, 68, 0.15); color: #dc2626; padding: 2px 8px; border-radius: 999px;">
                      <?= (int) $p['total_reports'] ?> <?= (int) $p['total_reports'] === 1 ? 'report' : 'reports' ?>
                    </span>
                  </div>
                  <?php if ((int) $p['pending_count'] > 0): ?>
                    <div style="font-size: 0.74rem; color: #dc2626; font-weight: 600; margin-top: 2px;">
                      <?= (int) $p['pending_count'] ?> pending review
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($p['post_status'] === 'published'): ?>
                    <span class="badge" style="font-size: 0.75rem; font-weight: 700; background: rgba(34, 197, 94, 0.15); color: #15803d; padding: 2px 8px; border-radius: 999px;">
                      Published
                    </span>
                  <?php else: ?>
                    <span class="badge" style="font-size: 0.75rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #b45309; padding: 2px 8px; border-radius: 999px;">
                      <?= ucfirst($p['post_status']) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.82rem; color: var(--text-muted);">
                  <?= date('M d, Y H:i', strtotime($p['latest_report_at'])) ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm btn-pill" style="font-size: 0.78rem; padding: 4px 10px;" onclick="inspectPostReports(<?= (int) $p['post_id'] ?>, '<?= htmlspecialchars(addslashes($p['post_title']), ENT_QUOTES, 'UTF-8') ?>')">
                      View Reports
                    </button>

                    <?php if ($p['post_status'] === 'published'): ?>
                      <button type="button" class="btn btn-danger-subtle btn-sm btn-pill" style="font-size: 0.78rem; padding: 4px 10px;" onclick="confirmUnpublishPost(<?= (int) $p['post_id'] ?>, '<?= htmlspecialchars(addslashes($p['post_title']), ENT_QUOTES, 'UTF-8') ?>')">
                        Unpublish
                      </button>
                    <?php else: ?>
                      <form method="POST" action="/admin/reports/posts/<?= (int) $p['post_id'] ?>/restore" style="display: inline-block;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-primary btn-sm btn-pill" style="font-size: 0.78rem; padding: 4px 10px;" onclick="return confirm('Restore this article to published status?')">
                          Restore
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- ==================== VIEW 2: ALL REPORTS LIST ==================== -->
    <?php if (empty($reportsList)): ?>
      <div class="empty-state" style="padding: 48px 24px; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 12px;">📋</div>
        <h3 class="empty-title">No individual reports found</h3>
        <p class="empty-desc">There are no reports matching your active filters.</p>
      </div>
    <?php else: ?>
      <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
          Showing <?= count($reportsList) ?> of <?= (int) $total ?> individual reports
        </div>
      </div>

      <div class="table-responsive">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th style="width: 60px;">ID</th>
              <th>Reported Article</th>
              <th style="width: 180px;">Reason</th>
              <th>Explanation</th>
              <th style="width: 120px;">Status</th>
              <th style="width: 130px;">Submitted</th>
              <th style="width: 190px; text-align: right;">Moderation Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reportsList as $rep): ?>
              <tr>
                <td style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace; font-weight: 700;">
                  #<?= (int) $rep['id'] ?>
                </td>
                <td>
                  <div style="font-weight: 700; color: var(--text-heading); font-size: 0.92rem; line-height: 1.35; margin-bottom: 3px;">
                    <a href="/article/<?= (int) $rep['post_id'] ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;">
                      <?= htmlspecialchars($rep['post_title'], ENT_QUOTES, 'UTF-8') ?> ↗
                    </a>
                  </div>
                  <div style="font-size: 0.76rem; color: var(--text-muted);">
                    Author: <?= htmlspecialchars($rep['author_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($rep['post_status']) && $rep['post_status'] !== 'published'): ?>
                      · <span style="color: #b45309; font-weight: 600;">(<?= ucfirst($rep['post_status']) ?>)</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="badge" style="font-size: 0.76rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #dc2626; padding: 3px 8px; border-radius: 999px; display: inline-block; white-space: normal; line-height: 1.3;">
                    <?= htmlspecialchars($rep['reason'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td style="font-size: 0.86rem; color: var(--text-body); max-width: 300px; word-break: break-word;">
                  <?php if (!empty($rep['explanation'])): ?>
                    <?= nl2br(htmlspecialchars($rep['explanation'], ENT_QUOTES, 'UTF-8')) ?>
                  <?php else: ?>
                    <span style="color: var(--text-subtle); font-style: italic;">No additional comments provided.</span>
                  <?php endif; ?>
                  <?php if (!empty($rep['moderation_notes'])): ?>
                    <div style="margin-top: 6px; padding: 4px 8px; border-radius: 6px; background: rgba(0,0,0,0.04); font-size: 0.78rem; color: var(--text-muted);">
                      <strong>Mod Note:</strong> <?= htmlspecialchars($rep['moderation_notes'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                    $statusBadge = match ($rep['status']) {
                      'pending' => 'background: rgba(239, 68, 68, 0.15); color: #dc2626;',
                      'under_review' => 'background: rgba(59, 130, 246, 0.15); color: #2563eb;',
                      'action_taken' => 'background: rgba(16, 185, 129, 0.15); color: #059669;',
                      'dismissed' => 'background: rgba(148, 163, 184, 0.2); color: #475569;',
                      default => 'background: rgba(0,0,0,0.08); color: var(--text-muted);',
                    };
                  ?>
                  <span class="badge" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; <?= $statusBadge ?>">
                    <?= str_replace('_', ' ', htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8')) ?>
                  </span>
                  <?php if (!empty($rep['reviewer_name'])): ?>
                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
                      by <?= htmlspecialchars($rep['reviewer_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">
                  <?= date('M d, Y H:i', strtotime($rep['created_at'])) ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 4px; justify-content: flex-end; flex-wrap: wrap;">
                    <?php if ($rep['status'] === 'pending'): ?>
                      <form method="POST" action="/admin/reports/<?= (int) $rep['id'] ?>/status" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="status" value="under_review">
                        <button type="submit" class="btn btn-secondary btn-sm btn-pill" style="font-size: 0.75rem; padding: 3px 8px;" title="Mark Under Review">
                          Review
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($rep['status'] !== 'dismissed'): ?>
                      <button type="button" class="btn btn-secondary btn-sm btn-pill" style="font-size: 0.75rem; padding: 3px 8px;" onclick="promptDismissReport(<?= (int) $rep['id'] ?>)">
                        Dismiss
                      </button>
                    <?php endif; ?>

                    <?php if ($rep['status'] !== 'action_taken'): ?>
                      <button type="button" class="btn btn-primary btn-sm btn-pill" style="font-size: 0.75rem; padding: 3px 8px;" onclick="promptActionTaken(<?= (int) $rep['id'] ?>)">
                        Action
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Pagination Controls -->
  <?php if (($totalPages ?? 1) > 1): ?>
    <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="/admin/reported-posts-page?page=<?= $p ?>&view=<?= urlencode($viewMode ?? 'grouped') ?>&status=<?= urlencode($statusFilter ?? '') ?>&reason=<?= urlencode($reasonFilter ?? '') ?>&search=<?= urlencode($searchQuery ?? '') ?>"
           class="btn <?= $p === $currentPage ? 'btn-primary' : 'btn-secondary' ?> btn-sm btn-pill" style="min-width: 36px; padding: 4px 12px;">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</div>

<!-- ==================== MODALS ==================== -->

<!-- Modal 1: Post Reports Inspection Modal (Details on all reports for a post) -->
<div id="inspectReportsModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="glass-panel" style="width: 100%; max-width: 680px; max-height: 85vh; overflow-y: auto; background: #ffffff; border-radius: var(--radius-xl); box-shadow: var(--glass-shadow-dropdown); padding: 28px; position: relative;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 12px;">
      <div>
        <h3 id="inspectModalTitle" style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">Reports for Article</h3>
        <p id="inspectModalSubtitle" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;"></p>
      </div>
      <button type="button" onclick="closeInspectModal()" style="border: none; background: transparent; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 4px;">✕</button>
    </div>

    <div id="inspectModalBody" style="display: flex; flex-direction: column; gap: 14px; min-height: 120px;">
      <div style="text-align: center; padding: 24px; color: var(--text-muted);">Loading reports...</div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 14px; border-top: 1px solid rgba(0,0,0,0.08);">
      <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeInspectModal()">Close</button>
    </div>
  </div>
</div>

<!-- Modal 2: Moderate Action Prompt Modal (Dismiss / Action Taken with Notes) -->
<div id="moderateNoteModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="glass-panel" style="width: 100%; max-width: 480px; background: #ffffff; border-radius: var(--radius-lg); box-shadow: var(--glass-shadow-dropdown); padding: 24px; position: relative;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 id="moderateModalTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin: 0;">Update Report</h3>
      <button type="button" onclick="closeModerateModal()" style="border: none; background: transparent; font-size: 1.3rem; color: var(--text-muted); cursor: pointer;">✕</button>
    </div>

    <form id="moderateActionForm" method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="status" id="moderateFormStatus" value="">

      <div style="margin-bottom: 16px;">
        <label for="moderateNotesInput" style="display: block; font-size: 0.86rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">
          Moderator Notes (Optional):
        </label>
        <textarea name="notes" id="moderateNotesInput" class="form-input" rows="3" placeholder="Enter findings, justification, or reason for dismissal..." maxlength="500" style="width: 100%; resize: vertical;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeModerateModal()">Cancel</button>
        <button type="submit" id="moderateSubmitBtn" class="btn btn-primary btn-sm btn-pill">Confirm</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 3: Unpublish Confirmation Modal -->
<div id="unpublishConfirmModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="glass-panel" style="width: 100%; max-width: 520px; background: #ffffff; border-radius: var(--radius-lg); box-shadow: var(--glass-shadow-dropdown); padding: 26px; position: relative;">
    <div style="display: flex; gap: 14px; align-items: flex-start; margin-bottom: 16px;">
      <div style="width: 44px; height: 44px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
        ⚠️
      </div>
      <div>
        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-dark); margin: 0;">Confirm Unpublishing Article</h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;">
          This will change the article's status from <strong>Published</strong> to <strong>Draft</strong>, removing it from public view. All associated pending reports will be marked as <strong>Action Taken</strong>.
        </p>
      </div>
    </div>

    <form id="unpublishActionForm" method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div style="margin-bottom: 18px;">
        <label for="unpublishReasonInput" style="display: block; font-size: 0.86rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">
          Moderation Violation Reason:
        </label>
        <input type="text" name="reason" id="unpublishReasonInput" class="form-input" required value="Content moderation policy violation" maxlength="255" style="width: 100%;">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeUnpublishModal()">Cancel</button>
        <button type="submit" class="btn btn-danger btn-sm btn-pill">Confirm &amp; Unpublish</button>
      </div>
    </form>
  </div>
</div>

<script>
function inspectPostReports(postId, postTitle) {
  const modal = document.getElementById('inspectReportsModal');
  const title = document.getElementById('inspectModalTitle');
  const subtitle = document.getElementById('inspectModalSubtitle');
  const body = document.getElementById('inspectModalBody');

  title.textContent = 'Reports for: ' + postTitle;
  subtitle.textContent = 'Article ID #' + postId;
  body.innerHTML = '<div style="text-align: center; padding: 24px; color: var(--text-muted);">Fetching reports...</div>';
  modal.style.display = 'flex';

  fetch('/api/admin/reports/posts/' + postId)
    .then(r => r.json())
    .then(data => {
      const reports = (data.data && data.data.reports) ? data.data.reports : [];
      if (reports.length === 0) {
        body.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-muted);">No reports found.</div>';
        return;
      }
      let html = '';
      reports.forEach(r => {
        const badgeColor = r.status === 'pending' ? 'background: rgba(239, 68, 68, 0.15); color: #dc2626;' :
                           r.status === 'under_review' ? 'background: rgba(59, 130, 246, 0.15); color: #2563eb;' :
                           r.status === 'action_taken' ? 'background: rgba(16, 185, 129, 0.15); color: #059669;' :
                           'background: rgba(148, 163, 184, 0.2); color: #475569;';

        html += `
          <div style="padding: 14px 16px; border-radius: 12px; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.06);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-weight: 700; font-size: 0.88rem; color: #dc2626;">🚩 ${escapeHtml(r.reason)}</span>
              <span class="badge" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; padding: 2px 7px; border-radius: 999px; ${badgeColor}">${escapeHtml(r.status.replace('_', ' '))}</span>
            </div>
            <p style="font-size: 0.86rem; color: var(--text-body); margin: 0 0 8px; line-height: 1.45;">
              ${r.explanation ? escapeHtml(r.explanation) : '<em style="color: var(--text-subtle);">No comments provided.</em>'}
            </p>
            <div style="font-size: 0.76rem; color: var(--text-muted); display: flex; justify-content: space-between; border-top: 1px dashed rgba(0,0,0,0.08); padding-top: 6px;">
              <span>Submitted: ${escapeHtml(r.created_at)}</span>
              ${r.reviewer_name ? `<span>Reviewed by: ${escapeHtml(r.reviewer_name)}</span>` : ''}
            </div>
          </div>
        `;
      });
      body.innerHTML = html;
    })
    .catch(err => {
      body.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc2626;">Failed to load reports.</div>';
    });
}

function closeInspectModal() {
  document.getElementById('inspectReportsModal').style.display = 'none';
}

function promptDismissReport(reportId) {
  const modal = document.getElementById('moderateNoteModal');
  const title = document.getElementById('moderateModalTitle');
  const form = document.getElementById('moderateActionForm');
  const statusInput = document.getElementById('moderateFormStatus');
  const submitBtn = document.getElementById('moderateSubmitBtn');

  title.textContent = 'Dismiss Report #' + reportId;
  form.action = '/admin/reports/' + reportId + '/status';
  statusInput.value = 'dismissed';
  submitBtn.textContent = 'Confirm Dismissal';
  submitBtn.className = 'btn btn-secondary btn-sm btn-pill';

  modal.style.display = 'flex';
}

function promptActionTaken(reportId) {
  const modal = document.getElementById('moderateNoteModal');
  const title = document.getElementById('moderateModalTitle');
  const form = document.getElementById('moderateActionForm');
  const statusInput = document.getElementById('moderateFormStatus');
  const submitBtn = document.getElementById('moderateSubmitBtn');

  title.textContent = 'Mark Action Taken on Report #' + reportId;
  form.action = '/admin/reports/' + reportId + '/status';
  statusInput.value = 'action_taken';
  submitBtn.textContent = 'Mark Action Taken';
  submitBtn.className = 'btn btn-primary btn-sm btn-pill';

  modal.style.display = 'flex';
}

function closeModerateModal() {
  document.getElementById('moderateNoteModal').style.display = 'none';
}

function confirmUnpublishPost(postId, postTitle) {
  const modal = document.getElementById('unpublishConfirmModal');
  const form = document.getElementById('unpublishActionForm');
  form.action = '/admin/reports/posts/' + postId + '/unpublish';
  modal.style.display = 'flex';
}

function closeUnpublishModal() {
  document.getElementById('unpublishConfirmModal').style.display = 'none';
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Close modals when pressing Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeInspectModal();
    closeModerateModal();
    closeUnpublishModal();
  }
});
</script>
