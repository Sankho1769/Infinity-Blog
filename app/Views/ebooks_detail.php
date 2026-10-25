<?php
declare(strict_types=1);
/**
 * Public eBook Detail View
 */

$id = (int) $ebook['id'];
$title = htmlspecialchars($ebook['title'], ENT_QUOTES, 'UTF-8');
$author = htmlspecialchars($ebook['author_name'], ENT_QUOTES, 'UTF-8');
$category = htmlspecialchars($ebook['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8');
$format = strtoupper(htmlspecialchars($ebook['format'], ENT_QUOTES, 'UTF-8'));
$readCount = (int) ($ebook['read_count'] ?? 0);
$downloads = (int) ($ebook['download_count'] ?? 0);
$allowDownload = !empty($ebook['allow_download']);
$description = nl2br(htmlspecialchars($ebook['description'], ENT_QUOTES, 'UTF-8'));

// Format file size
$fileSizeBytes = (int) ($ebook['file_size'] ?? 0);
$fileSizeFormatted = $fileSizeBytes >= 1048576 
    ? number_format($fileSizeBytes / 1048576, 2) . ' MB' 
    : number_format($fileSizeBytes / 1024, 0) . ' KB';

// Cover URL
$coverUrl = !empty($ebook['cover_path']) 
    ? htmlspecialchars($ebook['cover_path'], ENT_QUOTES, 'UTF-8') 
    : \InfinityBlog\Ebooks\EbookFileService::generateSvgCover($ebook['title'], $ebook['author_name'], $ebook['category_name'] ?? 'eBook');

// Uploader Identity — NEVER EXPOSE EMAIL!
$uploaderDisplayName = htmlspecialchars($ebook['uploader_name'] ?? 'Member', ENT_QUOTES, 'UTF-8');
$uploaderHandle = htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::formatHandle($ebook['uploader_username'] ?? null, $ebook['uploader_name'] ?? 'Member'), ENT_QUOTES, 'UTF-8');
$uploaderAvatar = !empty($ebook['uploader_avatar']) 
    ? htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($ebook['uploader_avatar']), ENT_QUOTES, 'UTF-8') 
    : null;
$uploaderInitial = strtoupper(mb_substr($ebook['uploader_name'] ?? 'M', 0, 1));
?>
<div class="page-container ebook-detail-page" style="max-width: 1100px; margin: 0 auto; padding: 32px 20px;">

  <!-- Success notification banner after upload -->
  <?php if (!empty($uploadedSuccess)): ?>
    <div class="glass-panel" style="padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px; color: #059669; font-weight: 600;">
        <span style="font-size: 1.3rem;">🎉</span>
        <span>Your eBook was uploaded and published to the community library!</span>
      </div>
      <a href="/ebooks/<?= $id ?>/read" class="btn btn-primary btn-sm" style="background: #059669; border-color: #059669;">
        Open Reader
      </a>
    </div>
  <?php endif; ?>

  <!-- Breadcrumbs -->
  <nav aria-label="Breadcrumb" style="margin-bottom: 24px;">
    <ol style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; font-size: 0.88rem; color: var(--text-muted); align-items: center; flex-wrap: wrap;">
      <li><a href="/" style="color: var(--text-muted); text-decoration: none;">Home</a></li>
      <li>/</li>
      <li><a href="/ebooks" style="color: var(--text-muted); text-decoration: none;">eBook Library</a></li>
      <li>/</li>
      <?php if (!empty($ebook['category_slug'])): ?>
        <li><a href="/ebooks?category=<?= urlencode($ebook['category_slug']) ?>" style="color: var(--text-muted); text-decoration: none;"><?= $category ?></a></li>
        <li>/</li>
      <?php endif; ?>
      <li style="color: var(--text-main); font-weight: 600; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= $title ?></li>
    </ol>
  </nav>

  <!-- Main Showcase Container -->
  <div class="glass-panel" style="padding: 36px 32px; border-radius: 24px; border: 1px solid var(--glass-border); background: var(--glass-bg); margin-bottom: 40px;">
    <div style="display: flex; gap: 36px; flex-wrap: wrap; align-items: flex-start;">
      
      <!-- Book Cover (Left Column) -->
      <div class="detail-cover-column" style="flex: 0 0 280px; max-width: 100%;">
        <div class="book-cover-3d" style="position: relative; width: 100%; aspect-ratio: 1 / 1.42; border-radius: 14px; overflow: hidden; box-shadow: 0 16px 38px rgba(0, 0, 0, 0.16), 0 4px 12px rgba(0,0,0,0.08); background: #f8fafc;">
          <img src="<?= $coverUrl ?>" alt="<?= $title ?>" style="width: 100%; height: 100%; object-fit: cover;">
          <div style="position: absolute; top: 12px; left: 12px; display: flex; gap: 6px;">
            <span style="font-size: 0.75rem; font-weight: 800; background: rgba(15, 23, 42, 0.9); color: #fff; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px;">
              <?= $format ?>
            </span>
          </div>
        </div>

        <!-- Quick Stats Pill Under Cover -->
        <div style="display: flex; justify-content: space-around; background: rgba(0,0,0,0.02); border-radius: 12px; padding: 12px; margin-top: 20px; border: 1px solid var(--glass-border); font-size: 0.82rem; text-align: center;">
          <div>
            <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Reads</div>
            <div style="font-weight: 800; color: var(--text-main); font-size: 1.1rem;"><?= $readCount ?></div>
          </div>
          <div style="border-left: 1px solid var(--glass-border);"></div>
          <div>
            <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Downloads</div>
            <div style="font-weight: 800; color: var(--text-main); font-size: 1.1rem;"><?= $downloads ?></div>
          </div>
          <div style="border-left: 1px solid var(--glass-border);"></div>
          <div>
            <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; font-weight: 700;">Size</div>
            <div style="font-weight: 800; color: var(--text-main); font-size: 1rem;"><?= $fileSizeFormatted ?></div>
          </div>
        </div>
      </div>

      <!-- Book Info & Actions (Right Column) -->
      <div style="flex: 1; min-width: 280px;">
        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
          <a href="/ebooks?category=<?= urlencode($ebook['category_slug'] ?? '') ?>" style="font-size: 0.8rem; font-weight: 700; background: rgba(37,99,235,0.1); color: var(--primary-color); padding: 4px 12px; border-radius: 20px; text-decoration: none;">
            <?= $category ?>
          </a>
          <?php if (!empty($ebook['community_name'])): ?>
            <a href="/chat/<?= urlencode($ebook['community_slug'] ?? (string) $ebook['community_id']) ?>" style="font-size: 0.8rem; font-weight: 700; background: rgba(16,185,129,0.1); color: #059669; padding: 4px 12px; border-radius: 20px; text-decoration: none;">
              💬 <?= htmlspecialchars($ebook['community_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          <?php endif; ?>
          <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); background: rgba(0,0,0,0.04); padding: 4px 10px; border-radius: 20px;">
            <?= strtoupper(htmlspecialchars($ebook['language'] ?? 'en', ENT_QUOTES, 'UTF-8')) ?>
          </span>
        </div>

        <h1 style="font-size: 2.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 10px; line-height: 1.25;">
          <?= $title ?>
        </h1>

        <div style="font-size: 1.15rem; color: var(--text-muted); margin-bottom: 20px; font-weight: 500;">
          Written by <span style="color: var(--text-main); font-weight: 700;"><?= $author ?></span>
        </div>

        <!-- Reading Progress Alert (if available) -->
        <?php if (!empty($readingProgress)): ?>
          <?php $pct = round((float) ($readingProgress['percentage'] ?? 0)); ?>
          <div style="background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 12px; padding: 14px 18px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 6px;">
              <span>You previously read <?= $pct ?>% of this book</span>
              <span><?= $ebook['format'] === 'pdf' && !empty($readingProgress['last_page']) ? 'Page ' . (int) $readingProgress['last_page'] : '' ?></span>
            </div>
            <div style="height: 6px; width: 100%; background: rgba(0,0,0,0.08); border-radius: 3px; overflow: hidden;">
              <div style="height: 100%; width: <?= min(100, max(0, $pct)) ?>%; background: var(--primary-gradient); border-radius: 3px;"></div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Primary Action Toolbar -->
        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px;">
          <!-- In-Browser Read Button -->
          <a href="/ebooks/<?= $id ?>/read" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-size: 1.05rem; font-weight: 700; padding: 14px 28px; border-radius: 12px; box-shadow: 0 4px 16px rgba(37,99,235,0.3);">
            <span>📖</span> Read Online
          </a>

          <!-- Save to Library Toggle -->
          <button type="button" id="saveLibraryBtn" class="btn btn-secondary" onclick="toggleSaveEbook(<?= $id ?>)" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 600; padding: 14px 20px; border-radius: 12px;">
            <span id="saveBtnIcon"><?= !empty($isSaved) ? '🔖' : '📑' ?></span>
            <span id="saveBtnText"><?= !empty($isSaved) ? 'Saved in Library' : 'Save to Library' ?></span>
          </button>

          <!-- Download Button -->
          <?php if ($allowDownload): ?>
            <a href="/ebooks/<?= $id ?>/download" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 600; padding: 14px 20px; border-radius: 12px;" title="Download original file">
              <span>⬇️</span> Download (<?= $fileSizeFormatted ?>)
            </a>
          <?php else: ?>
            <button type="button" class="btn btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed; font-size: 0.88rem; padding: 14px 18px; border-radius: 12px;" title="Author disabled downloads for this eBook">
              <span>🔒</span> Online Reading Only
            </button>
          <?php endif; ?>
        </div>

        <!-- Uploader Card (Author / Contributor Profile — No Email Leak) -->
        <div style="background: rgba(0,0,0,0.02); border: 1px solid var(--glass-border); border-radius: 16px; padding: 16px 20px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
          <div style="display: flex; align-items: center; gap: 14px;">
            <?php if (!empty($uploaderAvatar)): ?>
              <img src="<?= $uploaderAvatar ?>" alt="<?= $uploaderDisplayName ?>" style="width: 46px; height: 46px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color);">
            <?php else: ?>
              <div style="width: 46px; height: 46px; border-radius: 50%; background: var(--primary-gradient); color: #fff; font-weight: 700; font-size: 1.2rem; display: flex; align-items: center; justify-content: center;">
                <?= $uploaderInitial ?>
              </div>
            <?php endif; ?>
            <div>
              <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Shared by</div>
              <div style="font-weight: 700; color: var(--text-main); font-size: 1rem;"><?= $uploaderDisplayName ?></div>
              <div style="font-size: 0.82rem; color: var(--primary-color); font-weight: 600;"><?= $uploaderHandle ?></div>
            </div>
          </div>

          <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openReportModal()" style="font-size: 0.82rem; border-radius: 8px; color: var(--text-muted);">
              <span>🚩</span> Report
            </button>
            <?php if (!empty($canManage)): ?>
              <form method="POST" action="/ebooks/<?= $id ?>/delete" onsubmit="return confirm('Are you sure you want to delete this eBook? This action cannot be undone.');" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 0.82rem; border-radius: 8px; color: #dc2626;">
                  <span>🗑️</span> Delete
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Description / Synopsis -->
        <div>
          <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0 0 12px;">
            About this eBook
          </h3>
          <div style="color: var(--text-main); font-size: 0.98rem; line-height: 1.7; word-break: break-word;">
            <?= $description ?>
          </div>
        </div>

        <!-- Tags List -->
        <?php
          $tagList = is_array($ebook['tags'] ?? null) 
              ? $ebook['tags'] 
              : (!empty($ebook['tags']) ? explode(',', (string) $ebook['tags']) : []);
        ?>
        <?php if (!empty($tagList)): ?>
          <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--glass-border);">
            <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">
              Tags
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
              <?php foreach ($tagList as $t): ?>
                <?php $trimmed = trim((string) $t); if ($trimmed === '') continue; ?>
                <a href="/ebooks?search=<?= urlencode($trimmed) ?>" style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); background: rgba(0,0,0,0.04); padding: 4px 10px; border-radius: 12px; text-decoration: none;">
                  #<?= htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- Related eBooks in same category -->
  <?php if (!empty($relatedBooks)): ?>
    <section style="margin-top: 40px;">
      <h3 style="font-size: 1.4rem; font-weight: 700; color: var(--text-main); margin: 0 0 20px;">
        More in <?= $category ?>
      </h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;">
        <?php foreach ($relatedBooks as $rb): ?>
          <?php
            $rbId = (int) $rb['id'];
            $rbTitle = htmlspecialchars($rb['title'], ENT_QUOTES, 'UTF-8');
            $rbAuthor = htmlspecialchars($rb['author_name'], ENT_QUOTES, 'UTF-8');
            $rbCover = !empty($rb['cover_path']) ? htmlspecialchars($rb['cover_path'], ENT_QUOTES, 'UTF-8') : \InfinityBlog\Ebooks\EbookFileService::generateSvgCover($rb['title'], $rb['author_name'], $rb['category_name'] ?? 'eBook');
          ?>
          <a href="/ebooks/<?= $rbId ?>" class="glass-panel" style="padding: 14px; border-radius: 14px; display: flex; gap: 12px; text-decoration: none; color: inherit; border: 1px solid var(--glass-border); transition: transform 0.2s ease;">
            <div style="width: 54px; height: 76px; flex-shrink: 0; border-radius: 6px; overflow: hidden;">
              <img src="<?= $rbCover ?>" alt="<?= $rbTitle ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-main); line-height: 1.3; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= $rbTitle ?>
              </div>
              <div style="font-size: 0.8rem; color: var(--text-muted);">
                By <?= $rbAuthor ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</div>

<!-- Modal: Report eBook -->
<div id="reportModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div class="glass-panel" style="width: 100%; max-width: 480px; padding: 28px; border-radius: 20px; background: #fff; margin: 20px; border: 1px solid var(--glass-border);">
    <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 10px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
      <span>🚩</span> Report this eBook
    </h3>
    <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0 0 18px;">
      Help maintain community safety and copyright integrity. Our moderation team reviews all reports.
    </p>

    <form id="reportForm" onsubmit="submitEbookReport(event)">
      <div style="margin-bottom: 16px;">
        <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
          Reason for reporting *
        </label>
        <select id="reportReason" required class="form-input" style="width: 100%; border-radius: 12px; height: 42px;">
          <option value="">Select a reason</option>
          <option value="copyright">Copyright Infringement / Unauthorized Upload</option>
          <option value="inappropriate">Inappropriate, Explicit, or Hate Content</option>
          <option value="spam">Spam, Promotional, or Malicious Content</option>
          <option value="broken">Corrupted File or False Information</option>
          <option value="other">Other Violation</option>
        </select>
      </div>

      <div style="margin-bottom: 24px;">
        <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
          Explanation &amp; Details <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(Optional)</span>
        </label>
        <textarea id="reportExplanation" rows="3" class="form-input" placeholder="Provide additional context or links to original work..." style="width: 100%; border-radius: 12px;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary" onclick="closeReportModal()" style="border-radius: 10px;">Cancel</button>
        <button type="submit" id="reportSubmitBtn" class="btn btn-primary" style="border-radius: 10px; font-weight: 700;">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<script>
// Save to Library Toggle
async function toggleSaveEbook(ebookId) {
  const btn = document.getElementById('saveLibraryBtn');
  const icon = document.getElementById('saveBtnIcon');
  const text = document.getElementById('saveBtnText');
  btn.disabled = true;

  try {
    const res = await fetch('/api/ebooks/' + ebookId + '/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    });

    if (res.status === 401) {
      window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
      return;
    }

    if (res.ok) {
      const data = await res.json();
      if (data.saved) {
        icon.textContent = '🔖';
        text.textContent = 'Saved in Library';
      } else {
        icon.textContent = '📑';
        text.textContent = 'Save to Library';
      }
    }
  } catch (e) {
    alert('Failed to update library.');
  } finally {
    btn.disabled = false;
  }
}

// Reporting Modal
function openReportModal() {
  document.getElementById('reportModal').style.display = 'flex';
}
function closeReportModal() {
  document.getElementById('reportModal').style.display = 'none';
}

async function submitEbookReport(e) {
  e.preventDefault();
  const btn = document.getElementById('reportSubmitBtn');
  btn.disabled = true;
  btn.textContent = 'Submitting...';

  const reason = document.getElementById('reportReason').value;
  const explanation = document.getElementById('reportExplanation').value;

  try {
    const res = await fetch('/api/ebooks/<?= $id ?>/report', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ reason, explanation })
    });

    if (res.status === 401) {
      window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
      return;
    }

    const data = await res.json();
    if (res.ok) {
      alert(data.message || 'Report received. Thank you.');
      closeReportModal();
    } else {
      alert(data.error || 'Failed to submit report.');
    }
  } catch (err) {
    alert('Network error submitting report.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Submit Report';
  }
}
</script>
