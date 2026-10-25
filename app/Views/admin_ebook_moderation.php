<?php
declare(strict_types=1);
/**
 * Admin Moderation Dashboard for Community eBook Submissions
 */
?>
<div class="page-container" style="max-width: 1280px; margin: 0 auto; padding: 36px 20px;">

  <!-- Header -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
    <div>
      <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: #2563eb; background: rgba(37, 99, 235, 0.08); padding: 4px 12px; border-radius: 20px; margin-bottom: 8px;">
        <span>🛡️</span> ADMIN CONSOLE
      </div>
      <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin: 0;">eBook Moderation &amp; Review</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 6px;">
        Review community-submitted digital books, approve valid works for the public library, or reject improper uploads.
      </p>
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
      <a href="/admin/reported-ebooks-page" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.88rem;">
        🚩 Reported Books
      </a>
      <a href="/ebooks" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.88rem;">
        📚 Public Library
      </a>
      <a href="/ebooks/upload" class="btn btn-primary" style="border-radius: 10px; font-size: 0.88rem;">
        ➕ Upload Book
      </a>
    </div>
  </div>

  <?php if (!empty($approvedCount)): ?>
    <div class="glass-panel" style="padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.35); color: #047857; font-weight: 600; display: flex; align-items: center; gap: 8px;">
      <span>✅</span> eBook successfully approved and published to the public library catalog!
    </div>
  <?php endif; ?>

  <?php if (!empty($rejectedCount)): ?>
    <div class="glass-panel" style="padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.35); color: #b91c1c; font-weight: 600; display: flex; align-items: center; gap: 8px;">
      <span>ℹ️</span> eBook submission was rejected and excluded from the public catalog.
    </div>
  <?php endif; ?>

  <!-- Status Filter Pills -->
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div class="glass-panel" style="padding: 6px; border-radius: 14px; display: inline-flex; flex-wrap: wrap; gap: 4px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
      <a href="/admin/ebook-moderation?status=pending" class="tab-pill <?= ($statusFilter ?? 'pending') === 'pending' ? 'active' : '' ?>" style="padding: 8px 18px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($statusFilter ?? 'pending') === 'pending' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($statusFilter ?? 'pending') === 'pending' ? 'var(--primary-gradient)' : 'transparent' ?>;">
        Pending Review
      </a>
      <a href="/admin/ebook-moderation?status=published" class="tab-pill <?= ($statusFilter ?? '') === 'published' ? 'active' : '' ?>" style="padding: 8px 18px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($statusFilter ?? '') === 'published' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($statusFilter ?? '') === 'published' ? 'var(--primary-gradient)' : 'transparent' ?>;">
        Published
      </a>
      <a href="/admin/ebook-moderation?status=rejected" class="tab-pill <?= ($statusFilter ?? '') === 'rejected' ? 'active' : '' ?>" style="padding: 8px 18px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($statusFilter ?? '') === 'rejected' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($statusFilter ?? '') === 'rejected' ? 'var(--primary-gradient)' : 'transparent' ?>;">
        Rejected
      </a>
      <a href="/admin/ebook-moderation?status=hidden" class="tab-pill <?= ($statusFilter ?? '') === 'hidden' ? 'active' : '' ?>" style="padding: 8px 18px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($statusFilter ?? '') === 'hidden' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($statusFilter ?? '') === 'hidden' ? 'var(--primary-gradient)' : 'transparent' ?>;">
        Hidden
      </a>
      <a href="/admin/ebook-moderation?status=all" class="tab-pill <?= ($statusFilter ?? '') === 'all' ? 'active' : '' ?>" style="padding: 8px 18px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($statusFilter ?? '') === 'all' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($statusFilter ?? '') === 'all' ? 'var(--primary-gradient)' : 'transparent' ?>;">
        All Submissions
      </a>
    </div>

    <div style="font-size: 0.88rem; color: var(--text-muted);">
      Total submissions: <strong><?= (int) ($total ?? count($submissions)) ?></strong>
    </div>
  </div>

  <?php if (empty($submissions)): ?>
    <div class="glass-panel empty-state" style="padding: 56px 24px; text-align: center; border-radius: 20px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
      <div style="font-size: 3.5rem; margin-bottom: 12px;">🎉</div>
      <h3 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No Submissions in this Queue</h3>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto; line-height: 1.5;">
        There are currently no eBooks matching the <strong><?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?></strong> filter.
      </p>
    </div>
  <?php else: ?>
    <div class="glass-panel" style="border-radius: 20px; overflow: hidden; border: 1px solid var(--glass-border); background: var(--glass-bg); box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--glass-border); background: rgba(0,0,0,0.02);">
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">eBook Details</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Submitter &amp; Contact</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Category / Format</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Status</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Moderation Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($submissions as $b): ?>
              <?php
                $bId = (int) $b['id'];
                $bTitle = htmlspecialchars($b['title'] ?? 'Untitled eBook', ENT_QUOTES, 'UTF-8');
                $bAuthor = htmlspecialchars($b['author_name'] ?? 'Unknown Author', ENT_QUOTES, 'UTF-8');
                $format = strtoupper($b['file_format'] ?? 'PDF');
                $fileSize = !empty($b['file_size_bytes']) ? number_format($b['file_size_bytes'] / (1024 * 1024), 2) . ' MB' : 'Unknown';
                $status = strtolower($b['status'] ?? 'pending');
                $coverUrl = !empty($b['cover_image_path']) ? htmlspecialchars($b['cover_image_path'], ENT_QUOTES, 'UTF-8') : null;
                $category = htmlspecialchars($b['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8');
                $created = !empty($b['created_at']) ? date('M d, Y H:i', strtotime($b['created_at'])) : '';
                $isAnonymous = empty($b['user_id']);
                $submitterName = !empty($b['submitter_name']) ? htmlspecialchars($b['submitter_name'], ENT_QUOTES, 'UTF-8') : null;
                $submitterEmail = !empty($b['submitter_email']) ? htmlspecialchars($b['submitter_email'], ENT_QUOTES, 'UTF-8') : null;
                $uploaderName = !empty($b['uploader_name']) ? htmlspecialchars($b['uploader_name'], ENT_QUOTES, 'UTF-8') : null;
                $uploaderUsername = !empty($b['uploader_username']) ? htmlspecialchars($b['uploader_username'], ENT_QUOTES, 'UTF-8') : null;
                $rejectionReason = !empty($b['rejection_reason']) ? htmlspecialchars($b['rejection_reason'], ENT_QUOTES, 'UTF-8') : null;
              ?>
              <tr style="border-bottom: 1px solid var(--glass-border); vertical-align: top;">
                <!-- eBook Info -->
                <td style="padding: 18px 20px; min-width: 280px;">
                  <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 48px; height: 68px; border-radius: 6px; overflow: hidden; background: rgba(0,0,0,0.05); flex-shrink: 0; border: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: center;">
                      <?php if ($coverUrl): ?>
                        <img src="<?= $coverUrl ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                      <?php else: ?>
                        <span style="font-size: 1.5rem;"><?= $format === 'EPUB' ? '📘' : '📕' ?></span>
                      <?php endif; ?>
                    </div>
                    <div>
                      <a href="/ebooks/<?= $bId ?>" target="_blank" style="font-weight: 700; color: var(--text-main); text-decoration: none; font-size: 1rem; line-height: 1.3; display: block; margin-bottom: 4px;">
                        <?= $bTitle ?>
                      </a>
                      <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">
                        by <?= $bAuthor ?>
                      </div>
                      <div style="display: flex; gap: 8px; font-size: 0.78rem; color: var(--text-muted);">
                        <span>Submitted <?= $created ?></span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Submitter Info (Admin-exclusive contact visibility) -->
                <td style="padding: 18px 20px; min-width: 200px;">
                  <?php if ($isAnonymous): ?>
                    <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; font-weight: 700; background: rgba(100, 116, 139, 0.1); color: #475569; padding: 2px 8px; border-radius: 6px; margin-bottom: 6px;">
                      <span>🌐</span> Guest Contributor
                    </div>
                    <?php if ($submitterName): ?>
                      <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">
                        <?= $submitterName ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($submitterEmail): ?>
                      <div style="font-size: 0.82rem; color: var(--primary-color); word-break: break-all; margin-top: 2px;">
                        ✉️ <?= $submitterEmail ?>
                      </div>
                    <?php else: ?>
                      <div style="font-size: 0.78rem; color: var(--text-muted); font-style: italic;">
                        No contact email provided
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.82rem; font-weight: 700; background: rgba(37, 99, 235, 0.1); color: #2563eb; padding: 2px 8px; border-radius: 6px; margin-bottom: 6px;">
                      <span>👤</span> Registered Member
                    </div>
                    <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main);">
                      <?= $uploaderName ?? 'Member' ?>
                    </div>
                    <?php if ($uploaderUsername): ?>
                      <a href="/profile/<?= urlencode($uploaderUsername) ?>" target="_blank" style="font-size: 0.82rem; color: var(--text-muted); text-decoration: none;">
                        @<?= $uploaderUsername ?>
                      </a>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>

                <!-- Category / Format -->
                <td style="padding: 18px 20px; min-width: 170px;">
                  <div style="font-weight: 600; color: var(--text-main); font-size: 0.88rem; margin-bottom: 4px;">
                    <?= $category ?>
                  </div>
                  <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: <?= $format === 'EPUB' ? 'rgba(99, 102, 241, 0.12); color: #4f46e5;' : 'rgba(239, 68, 68, 0.12); color: #dc2626;' ?>">
                      <?= $format ?>
                    </span>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">
                      <?= $fileSize ?>
                    </span>
                  </div>
                  <div style="margin-top: 6px;">
                    <a href="/ebooks/<?= $bId ?>/download" class="btn-link" style="font-size: 0.8rem; color: var(--primary-color); text-decoration: underline;">
                      📥 Download File
                    </a>
                  </div>
                </td>

                <!-- Status Badge -->
                <td style="padding: 18px 20px; min-width: 140px;">
                  <?php if ($status === 'published'): ?>
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #059669;">
                      ● Published
                    </span>
                  <?php elseif ($status === 'pending'): ?>
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; background: rgba(245, 158, 11, 0.15); color: #d97706;">
                      ⏳ Pending Review
                    </span>
                  <?php elseif ($status === 'rejected'): ?>
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #dc2626;">
                      ✕ Rejected
                    </span>
                    <?php if ($rejectionReason): ?>
                      <div style="font-size: 0.78rem; color: #dc2626; margin-top: 6px; line-height: 1.3;">
                        Reason: <?= $rejectionReason ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; background: rgba(100, 116, 139, 0.15); color: #475569;">
                      👁️ Hidden
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Moderation Actions -->
                <td style="padding: 18px 20px; text-align: right; min-width: 220px;">
                  <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                    
                    <!-- View / Read button -->
                    <a href="/ebooks/<?= $bId ?>" target="_blank" class="btn btn-secondary btn-sm" style="border-radius: 8px; padding: 6px 12px; font-size: 0.82rem;" title="Inspect detail page">
                      Inspect
                    </a>

                    <!-- Approve Action -->
                    <?php if ($status !== 'published'): ?>
                      <form method="POST" action="/admin/ebooks/<?= $bId ?>/approve" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 8px; padding: 6px 12px; font-size: 0.82rem; background: #059669; border-color: #059669;" onclick="return confirm('Approve this eBook for public publication?');">
                          Approve
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- Reject Action (Prompt reason) -->
                    <?php if ($status !== 'rejected'): ?>
                      <button type="button" class="btn btn-danger btn-sm" style="border-radius: 8px; padding: 6px 12px; font-size: 0.82rem;" onclick="openRejectModal(<?= $bId ?>, '<?= addslashes($bTitle) ?>');">
                        Reject
                      </button>
                    <?php endif; ?>

                    <!-- Hide / Restore Status -->
                    <?php if ($status === 'published'): ?>
                      <form method="POST" action="/admin/ebooks/<?= $bId ?>/status" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="status" value="hidden">
                        <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px; padding: 6px 10px; font-size: 0.82rem;" onclick="return confirm('Hide this eBook from public catalog?');">
                          Hide
                        </button>
                      </form>
                    <?php elseif ($status === 'hidden'): ?>
                      <form method="POST" action="/admin/ebooks/<?= $bId ?>/status" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="status" value="published">
                        <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px; padding: 6px 10px; font-size: 0.82rem;">
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
    </div>

    <!-- Pagination -->
    <?php if (($totalPages ?? 1) > 1): ?>
      <div style="display: flex; justify-content: center; gap: 8px; margin-top: 28px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="/admin/ebook-moderation?status=<?= urlencode($statusFilter) ?>&page=<?= $p ?>"
             class="btn btn-sm <?= ((int) ($page ?? 1) === $p) ? 'btn-primary' : 'btn-secondary' ?>"
             style="border-radius: 8px; min-width: 38px; text-align: center;">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- REJECT SUBMISSION MODAL -->
<div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="glass-panel" style="max-width: 500px; width: 100%; border-radius: 18px; padding: 28px; background: var(--bg-surface, #ffffff); border: 1px solid var(--glass-border); box-shadow: 0 10px 40px rgba(0,0,0,0.25);">
    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 8px;">
      Reject eBook Submission
    </h3>
    <p id="rejectModalBookTitle" style="color: var(--text-muted); font-size: 0.9rem; margin: 0 0 18px; font-weight: 600;">
    </p>

    <form id="rejectForm" method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
      
      <div style="margin-bottom: 20px;">
        <label for="rejectReason" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 0.88rem; color: var(--text-main);">
          Rejection Reason (Internal &amp; Notification note)
        </label>
        <textarea id="rejectReason" name="reason" rows="4" required class="form-input"
                  placeholder="e.g. Incomplete content, copyright violation, unreadable formatting, or spam upload..."
                  style="width: 100%; border-radius: 10px; line-height: 1.4;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 12px;">
        <button type="button" class="btn btn-secondary" onclick="closeRejectModal();" style="border-radius: 10px;">
          Cancel
        </button>
        <button type="submit" class="btn btn-danger" style="border-radius: 10px; font-weight: 700;">
          Confirm Rejection
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(bookId, title) {
  const modal = document.getElementById('rejectModal');
  const titleEl = document.getElementById('rejectModalBookTitle');
  const form = document.getElementById('rejectForm');
  titleEl.textContent = 'Book: ' + title;
  form.action = '/admin/ebooks/' + bookId + '/reject';
  modal.style.display = 'flex';
}

function closeRejectModal() {
  const modal = document.getElementById('rejectModal');
  modal.style.display = 'none';
  document.getElementById('rejectReason').value = '';
}

// Close on backdrop click
document.getElementById('rejectModal').addEventListener('click', (e) => {
  if (e.target.id === 'rejectModal') {
    closeRejectModal();
  }
});
</script>
