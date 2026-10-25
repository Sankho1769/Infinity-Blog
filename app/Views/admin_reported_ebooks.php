<?php
declare(strict_types=1);
/**
 * Admin Moderation Dashboard for Reported eBooks
 */
?>
<div class="page-container" style="max-width: 1200px; margin: 0 auto; padding: 36px 20px;">

  <!-- Header -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
    <div>
      <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: #dc2626; background: rgba(220, 38, 38, 0.08); padding: 4px 12px; border-radius: 20px; margin-bottom: 8px;">
        <span>🛡️</span> MODERATION SYSTEM
      </div>
      <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin: 0;">Reported eBooks</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 6px;">
        Review community reports for copyright violations, inappropriate materials, or corrupted uploads.
      </p>
    </div>

    <div style="display: flex; gap: 10px;">
      <a href="/admin/reported-posts-page" class="btn btn-secondary" style="border-radius: 10px;">
        🚩 Reported Articles
      </a>
      <a href="/ebooks" class="btn btn-secondary" style="border-radius: 10px;">
        📚 eBook Library
      </a>
    </div>
  </div>

  <!-- Status Filter Pills -->
  <div class="glass-panel" style="padding: 6px; border-radius: 14px; margin-bottom: 28px; display: inline-flex; flex-wrap: wrap; gap: 4px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
    <a href="/admin/reported-ebooks-page" class="tab-pill <?= empty($currentStatus) ? 'active' : '' ?>" style="padding: 8px 16px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= empty($currentStatus) ? '#fff' : 'var(--text-muted)' ?>; background: <?= empty($currentStatus) ? 'var(--primary-gradient)' : 'transparent' ?>;">
      All Reports
    </a>
    <a href="/admin/reported-ebooks-page?status=pending" class="tab-pill <?= ($currentStatus ?? '') === 'pending' ? 'active' : '' ?>" style="padding: 8px 16px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($currentStatus ?? '') === 'pending' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($currentStatus ?? '') === 'pending' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      Pending Review
    </a>
    <a href="/admin/reported-ebooks-page?status=reviewed" class="tab-pill <?= ($currentStatus ?? '') === 'reviewed' ? 'active' : '' ?>" style="padding: 8px 16px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($currentStatus ?? '') === 'reviewed' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($currentStatus ?? '') === 'reviewed' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      Reviewed
    </a>
    <a href="/admin/reported-ebooks-page?status=dismissed" class="tab-pill <?= ($currentStatus ?? '') === 'dismissed' ? 'active' : '' ?>" style="padding: 8px 16px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; color: <?= ($currentStatus ?? '') === 'dismissed' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($currentStatus ?? '') === 'dismissed' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      Dismissed
    </a>
  </div>

  <?php if (empty($reports)): ?>
    <div class="glass-panel empty-state" style="padding: 48px 24px; text-align: center; border-radius: 20px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
      <div style="font-size: 3.5rem; margin-bottom: 12px;">✅</div>
      <h3 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No Reported eBooks</h3>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto; line-height: 1.5;">
        There are currently no community reports matching the selected status filter. The eBook library is clean.
      </p>
    </div>
  <?php else: ?>
    <div class="glass-panel" style="border-radius: 20px; overflow: hidden; border: 1px solid var(--glass-border); background: var(--glass-bg);">
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--glass-border); background: rgba(0,0,0,0.02);">
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">eBook</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Reporter</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Reason &amp; Explanation</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Report Status</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Book Visibility</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Moderation Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reports as $r): ?>
              <?php
                $rId = (int) $r['id'];
                $ebId = (int) $r['ebook_id'];
                $ebTitle = htmlspecialchars($r['ebook_title'] ?? ('eBook #' . $ebId), ENT_QUOTES, 'UTF-8');
                $reporterHandle = htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::formatHandle($r['reporter_username'] ?? null, $r['reporter_name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
                $reason = htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8');
                $explanation = !empty($r['explanation']) ? htmlspecialchars($r['explanation'], ENT_QUOTES, 'UTF-8') : null;
                $status = htmlspecialchars($r['status'] ?? 'pending', ENT_QUOTES, 'UTF-8');
                $ebookStatus = htmlspecialchars($r['ebook_status'] ?? 'published', ENT_QUOTES, 'UTF-8');
                $created = htmlspecialchars($r['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
              ?>
              <tr style="border-bottom: 1px solid var(--glass-border);">
                <!-- eBook Info -->
                <td style="padding: 16px 20px; max-width: 240px;">
                  <a href="/ebooks/<?= $ebId ?>" target="_blank" style="font-weight: 700; color: var(--text-main); text-decoration: none; display: block; line-height: 1.3;">
                    <?= $ebTitle ?>
                  </a>
                  <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                    ID: <?= $ebId ?> &bull; <a href="/ebooks/<?= $ebId ?>/read" target="_blank" style="color: var(--primary-color);">Inspect Reader</a>
                  </div>
                </td>

                <!-- Reporter -->
                <td style="padding: 16px 20px; font-size: 0.88rem; color: var(--text-main);">
                  <?= $reporterHandle ?>
                </td>

                <!-- Reason & Explanation -->
                <td style="padding: 16px 20px; max-width: 300px;">
                  <div style="font-weight: 700; color: #dc2626; font-size: 0.88rem; margin-bottom: 4px;">
                    <?= ucfirst($reason) ?>
                  </div>
                  <?php if (!empty($explanation)): ?>
                    <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
                      <?= $explanation ?>
                    </div>
                  <?php endif; ?>
                  <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">
                    Reported on <?= $created ?>
                  </div>
                </td>

                <!-- Report Status -->
                <td style="padding: 16px 20px;">
                  <?php if ($status === 'pending'): ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #d97706; background: rgba(217, 119, 6, 0.1); padding: 3px 8px; border-radius: 6px;">Pending</span>
                  <?php elseif ($status === 'reviewed'): ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: rgba(16, 185, 129, 0.1); padding: 3px 8px; border-radius: 6px;">Reviewed</span>
                  <?php else: ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); background: rgba(0,0,0,0.06); padding: 3px 8px; border-radius: 6px;"><?= ucfirst($status) ?></span>
                  <?php endif; ?>
                </td>

                <!-- eBook Visibility -->
                <td style="padding: 16px 20px;">
                  <?php if ($ebookStatus === 'published'): ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: rgba(16, 185, 129, 0.1); padding: 3px 8px; border-radius: 6px;">Live (Public)</span>
                  <?php else: ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #dc2626; background: rgba(220, 38, 38, 0.1); padding: 3px 8px; border-radius: 6px;">Hidden (Restricted)</span>
                  <?php endif; ?>
                </td>

                <!-- Actions -->
                <td style="padding: 16px 20px; text-align: right;">
                  <div style="display: inline-flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;">
                    <?php if ($ebookStatus === 'published'): ?>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="moderateEbookReport(<?= $rId ?>, 'reviewed', 'hide', this)" style="border-radius: 8px; color: #dc2626;" title="Hide this book from public library">
                        Hide Book
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="moderateEbookReport(<?= $rId ?>, 'reviewed', 'restore', this)" style="border-radius: 8px; color: #059669;" title="Restore book to public catalogue">
                        Restore Book
                      </button>
                    <?php endif; ?>

                    <?php if ($status !== 'dismissed'): ?>
                      <button type="button" class="btn btn-secondary btn-sm" onclick="moderateEbookReport(<?= $rId ?>, 'dismissed', null, this)" style="border-radius: 8px;" title="Dismiss report as invalid">
                        Dismiss
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
async function moderateEbookReport(reportId, status, action, btn) {
  if (action === 'hide' && !confirm('Are you sure you want to hide this eBook from the public library?')) {
    return;
  }
  btn.disabled = true;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch('/admin/reports/ebooks/' + reportId + '/status', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ status, action })
    });

    if (res.ok) {
      window.location.reload();
    } else {
      const d = await res.json();
      alert(d.error || 'Failed to update report.');
    }
  } catch (e) {
    alert('Network error.');
  } finally {
    btn.disabled = false;
  }
}
</script>
