<?php
declare(strict_types=1);
/**
 * Member eBook Management Dashboard
 */
?>
<div class="page-container" style="max-width: 1100px; margin: 0 auto; padding: 36px 20px;">

  <!-- Header -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
    <div>
      <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: var(--primary-color); background: rgba(37, 99, 235, 0.08); padding: 4px 12px; border-radius: 20px; margin-bottom: 8px;">
        <span>📖</span> AUTHOR WORKSPACE
      </div>
      <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin: 0;">My Uploaded eBooks</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 6px;">Manage your published digital books, track genuine reader engagement, and update files.</p>
    </div>

    <div style="display: flex; gap: 10px;">
      <a href="/ebooks" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-radius: 10px;">
        <span>🔍</span> eBook Library
      </a>
      <a href="/ebooks/upload" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; border-radius: 10px; font-weight: 600;">
        <span>➕</span> Upload New eBook
      </a>
    </div>
  </div>

  <?php if (!empty($deletedSuccess)): ?>
    <div class="glass-panel" style="padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; font-weight: 600;">
      eBook was successfully removed from the library.
    </div>
  <?php endif; ?>

  <?php if (empty($books)): ?>
    <div class="glass-panel empty-state" style="padding: 48px 24px; text-align: center; border-radius: 20px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
      <div style="font-size: 3.5rem; margin-bottom: 12px;">📚</div>
      <h3 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">You haven't uploaded any eBooks yet</h3>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 24px; line-height: 1.5;">
        Share valuable reference materials, technical manuals, or creative stories with thousands of readers in the community.
      </p>
      <a href="/ebooks/upload" class="btn btn-primary" style="padding: 12px 28px; border-radius: 12px; font-weight: 700;">
        Upload Your First eBook
      </a>
    </div>
  <?php else: ?>
    <div class="glass-panel" style="border-radius: 20px; overflow: hidden; border: 1px solid var(--glass-border); background: var(--glass-bg);">
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.92rem;">
          <thead>
            <tr style="border-bottom: 1px solid var(--glass-border); background: rgba(0,0,0,0.02);">
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">eBook</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Format</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Category</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Reads</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Downloads</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Status</th>
              <th style="padding: 16px 20px; font-weight: 700; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($books as $b): ?>
              <?php
                $bId = (int) $b['id'];
                $bTitle = htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8');
                $bAuthor = htmlspecialchars($b['author_name'], ENT_QUOTES, 'UTF-8');
                $bFormat = strtoupper(htmlspecialchars($b['format'], ENT_QUOTES, 'UTF-8'));
                $bCat = htmlspecialchars($b['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8');
                $bReads = (int) ($b['read_count'] ?? 0);
                $bDownloads = (int) ($b['download_count'] ?? 0);
                $bStatus = htmlspecialchars($b['status'] ?? 'published', ENT_QUOTES, 'UTF-8');
                $bCover = !empty($b['cover_path']) ? htmlspecialchars($b['cover_path'], ENT_QUOTES, 'UTF-8') : \InfinityBlog\Ebooks\EbookFileService::generateSvgCover($b['title'], $b['author_name'], $b['category_name'] ?? 'eBook');
              ?>
              <tr style="border-bottom: 1px solid var(--glass-border);">
                <!-- eBook Info -->
                <td style="padding: 16px 20px;">
                  <div style="display: flex; gap: 14px; align-items: center;">
                    <a href="/ebooks/<?= $bId ?>" style="width: 44px; height: 60px; border-radius: 6px; overflow: hidden; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1); display: block;">
                      <img src="<?= $bCover ?>" alt="<?= $bTitle ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </a>
                    <div style="min-width: 0;">
                      <a href="/ebooks/<?= $bId ?>" style="font-weight: 700; color: var(--text-main); text-decoration: none; display: block; line-height: 1.3; margin-bottom: 2px;">
                        <?= $bTitle ?>
                      </a>
                      <span style="font-size: 0.8rem; color: var(--text-muted);">By <?= $bAuthor ?></span>
                    </div>
                  </div>
                </td>

                <!-- Format -->
                <td style="padding: 16px 20px;">
                  <span style="font-size: 0.72rem; font-weight: 700; background: rgba(37,99,235,0.1); color: var(--primary-color); padding: 3px 8px; border-radius: 6px;">
                    <?= $bFormat ?>
                  </span>
                </td>

                <!-- Category -->
                <td style="padding: 16px 20px; color: var(--text-muted); font-size: 0.88rem;">
                  <?= $bCat ?>
                </td>

                <!-- Reads -->
                <td style="padding: 16px 20px; font-weight: 700; color: var(--text-main);">
                  👁️ <?= $bReads ?>
                </td>

                <!-- Downloads -->
                <td style="padding: 16px 20px; font-weight: 700; color: var(--text-main);">
                  ⬇️ <?= $bDownloads ?>
                </td>

                <!-- Status -->
                <td style="padding: 16px 20px;">
                  <?php if ($bStatus === 'published'): ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: rgba(16,185,129,0.1); padding: 3px 8px; border-radius: 6px;">Published</span>
                  <?php elseif ($bStatus === 'hidden'): ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #dc2626; background: rgba(239,68,68,0.1); padding: 3px 8px; border-radius: 6px;">Hidden</span>
                  <?php else: ?>
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); background: rgba(0,0,0,0.06); padding: 3px 8px; border-radius: 6px;"><?= ucfirst($bStatus) ?></span>
                  <?php endif; ?>
                </td>

                <!-- Actions -->
                <td style="padding: 16px 20px; text-align: right;">
                  <div style="display: inline-flex; gap: 8px; align-items: center;">
                    <a href="/ebooks/<?= $bId ?>/read" class="btn btn-primary btn-sm" style="border-radius: 8px;" title="Read Online">
                      Read
                    </a>
                    <a href="/ebooks/<?= $bId ?>" class="btn btn-secondary btn-sm" style="border-radius: 8px;" title="View Details">
                      Details
                    </a>
                    <form method="POST" action="/ebooks/<?= $bId ?>/delete" onsubmit="return confirm('Delete this eBook completely from Infinity Blog?');" style="margin: 0;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px; color: #dc2626;" title="Delete eBook">
                        🗑️
                      </button>
                    </form>
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
