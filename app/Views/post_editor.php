<!-- Screens 4 & 5: Post Editor (Create & Edit) -->
<?php
  $isEdit = !empty($post['id']);
  $pageHeading = $isEdit ? 'Edit Post' : 'Create New Post';
  $pageSubhead = $isEdit ? 'Update your article content and publication settings.' : 'Write and publish your ideas to the world.';
?>

<div class="dashboard-header dashboard-page-bg">
  <div>
    <h1 class="dashboard-greeting"><?= $pageHeading ?></h1>
    <p class="dashboard-subhead"><?= $pageSubhead ?></p>
  </div>

  <a href="/posts/manage" class="btn btn-secondary btn-pill">
    ← Back to All Posts
  </a>
</div>

<?php
  $remixedFromId = !empty($post['remixed_from_post_id']) ? (int) $post['remixed_from_post_id'] : (!empty($remixSource['id']) ? (int) $remixSource['id'] : null);
  $remixedFromTitle = $post['remixed_from_title'] ?? ($remixSource['title'] ?? null);
  $remixedFromAuthor = $post['remixed_from_author_name'] ?? ($remixSource['author_name'] ?? null);
?>

<?php if ($remixedFromId !== null): ?>
  <div class="alert-banner" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(238, 242, 255, 0.7); border: 1px solid rgba(199, 210, 254, 0.85); color: #3730a3; border-radius: var(--radius-md);">
    <span style="font-size: 1.2rem;">🔀</span>
    <div>
      <strong>Remix Mode:</strong> This article is based on
      <em><?= htmlspecialchars($remixedFromTitle ?? 'Original Article', ENT_QUOTES, 'UTF-8') ?></em>
      <?php if (!empty($remixedFromAuthor)): ?>
        by <strong><?= htmlspecialchars($remixedFromAuthor, ENT_QUOTES, 'UTF-8') ?></strong>
      <?php endif; ?>.
      Original author attribution will be preserved upon publication.
    </div>
  </div>
<?php endif; ?>

<div id="editor-error-alert" class="alert-banner alert-error" style="display: none;"></div>

<form id="post-editor-form" method="POST" action="<?= $isEdit ? '/author/posts/' . (int) $post['id'] : '/author/posts' ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" id="post-id" value="<?= (int) $post['id'] ?>">
  <?php endif; ?>
  <?php if ($remixedFromId !== null): ?>
    <input type="hidden" id="remixed-from-post-id" value="<?= (int) $remixedFromId ?>">
  <?php endif; ?>

  <div class="editor-layout">
    <!-- Main Content Column -->
    <div class="glass-panel editor-main-card">
      <div class="form-group">
        <label for="post-title" class="form-label">Article Title <span style="color: var(--danger-color);">*</span></label>
        <input type="text" id="post-title" name="title" class="form-input" style="font-size: 1.15rem; font-weight: 700;" placeholder="Enter an engaging title..." value="<?= htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="190">
      </div>

      <div class="form-group">
        <label for="post-category" class="form-label">Category</label>
        <select id="post-category" name="category_id" class="form-select">
          <option value="">Select a category (Optional for draft, required for publish)</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= (!empty($post['category_id']) && (int) $post['category_id'] === (int) $cat['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Article Cover Image (Supabase Storage: article-images) -->
      <?php
        $initialCoverPath = $post['cover_image_path'] ?? '';
        $initialCoverUrl = !empty($initialCoverPath) ? \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post) : '';
      ?>
      <div class="form-group">
        <label class="form-label">Article Cover Image <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Optional · Max 5MB · JPEG, PNG, WebP)</span></label>
        <input type="hidden" id="post-cover-image-path" name="cover_image_path" value="<?= htmlspecialchars($initialCoverPath, ENT_QUOTES, 'UTF-8') ?>">

        <div id="cover-preview-container" style="<?= empty($initialCoverUrl) ? 'display: none;' : '' ?> margin-bottom: 12px; position: relative; border-radius: var(--radius-md); overflow: hidden; max-height: 220px; border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow-sm);">
          <img id="cover-preview-img" src="<?= htmlspecialchars($initialCoverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cover Image Preview" style="width: 100%; height: 220px; object-fit: cover; display: block;">
          <div style="position: absolute; top: 10px; right: 10px; display: flex; gap: 8px;">
            <button type="button" id="change-cover-btn" class="btn btn-secondary btn-sm btn-pill" style="background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);">Change</button>
            <button type="button" id="remove-cover-btn" class="btn btn-danger btn-sm btn-pill" style="background: rgba(239,68,68,0.9);">Remove</button>
          </div>
        </div>

        <div id="cover-upload-box" class="featured-image-placeholder-box" style="<?= !empty($initialCoverUrl) ? 'display: none;' : '' ?> cursor: pointer;" onclick="document.getElementById('cover-file-input').click()">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color); margin-bottom: 6px;">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <polyline points="21 15 16 10 5 21"></polyline>
          </svg>
          <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-dark);">Click to Upload Cover Image</div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Stored securely in Supabase Storage. Automatic fallback artwork if omitted.</div>
        </div>
        <input type="file" id="cover-file-input" accept="image/png,image/jpeg,image/webp" style="display: none;">
        <div id="cover-upload-status" style="font-size: 0.82rem; margin-top: 6px; display: none;"></div>
      </div>

      <!-- Article Video (Supabase Storage: article-videos) -->
      <?php
        $initialVideoPath = $post['video_path'] ?? '';
      ?>
      <div class="form-group" style="margin-top: 20px; padding-top: 18px; border-top: 1px solid rgba(226, 232, 240, 0.6);">
        <label class="form-label">Article Video <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Optional · Max 50MB · MP4, WebM · Supabase Private Bucket)</span></label>
        <input type="hidden" id="post-video-path" name="video_path" value="<?= htmlspecialchars($initialVideoPath, ENT_QUOTES, 'UTF-8') ?>">

        <div id="video-preview-container" style="<?= empty($initialVideoPath) ? 'display: none;' : '' ?> margin-bottom: 10px; padding: 12px 16px; background: rgba(255, 255, 255, 0.6); border-radius: var(--radius-md); border: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: space-between; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
            <span style="font-size: 1.3rem;">🎬</span>
            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <span id="video-preview-label" style="font-size: 0.88rem; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars(basename($initialVideoPath), ENT_QUOTES, 'UTF-8') ?></span>
              <div style="font-size: 0.75rem; color: var(--text-muted);">Protected video playable via authorized signed URLs</div>
            </div>
          </div>
          <div style="display: flex; gap: 6px; flex-shrink: 0;">
            <button type="button" id="change-video-btn" class="btn btn-secondary btn-sm btn-pill">Change</button>
            <button type="button" id="remove-video-btn" class="btn btn-danger btn-sm btn-pill">Remove</button>
          </div>
        </div>

        <div id="video-upload-box" class="featured-image-placeholder-box" style="<?= !empty($initialVideoPath) ? 'display: none;' : '' ?> cursor: pointer; padding: 16px;" onclick="document.getElementById('video-file-input').click()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color); margin-bottom: 4px;">
            <polygon points="5 3 19 12 5 21 5 3"></polygon>
          </svg>
          <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-dark);">Click to Upload Video</div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">MP4 or WebM (up to 50MB). Stored in private bucket with short-lived playback signatures.</div>
        </div>
        <input type="file" id="video-file-input" accept="video/mp4,video/webm" style="display: none;">
        <div id="video-upload-status" style="font-size: 0.82rem; margin-top: 6px; display: none;"></div>
      </div>

      <div class="form-group" style="margin-top: 20px; padding-top: 18px; border-top: 1px solid rgba(226, 232, 240, 0.6);">
        <div class="editor-tabs-bar">
          <div class="editor-tabs">
            <button type="button" class="editor-tab-btn active" id="tab-write-btn" onclick="switchEditorTab('write')">
              ✏️ Write
            </button>
            <button type="button" class="editor-tab-btn" id="tab-preview-btn" onclick="switchEditorTab('preview')">
              👁️ Preview
            </button>
          </div>
          <div id="editor-write-toolbar" style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('**', '**', 'bold text')" title="Bold"><strong>B</strong></button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('*', '*', 'italic text')" title="Italic"><em>I</em></button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('## ', '', 'Heading 2')" title="Heading 2">H2</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('### ', '', 'Heading 3')" title="Heading 3">H3</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('> ', '', 'Quote text')" title="Quote">”</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('```\n', '\n```', 'code block')" title="Code">Code</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('- ', '', 'List item')" title="Bullet List">• List</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('1. ', '', 'Numbered item')" title="Numbered List">1. List</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('[', '](https://example.com)', 'Link text')" title="Insert Link">🔗 Link</button>
            <button type="button" class="btn btn-secondary btn-sm" id="insert-image-btn" title="Upload and Insert Image">🖼️ Image</button>
            <button type="button" class="btn btn-secondary btn-sm" id="open-video-modal-btn" title="Insert YouTube, Vimeo, or Video Upload">🎥 Video</button>
            <input type="file" id="inline-image-file-input" accept="image/png,image/jpeg,image/webp" style="display: none;">
          </div>
        </div>

        <div id="editor-write-panel">
          <textarea id="post-content" name="content" class="form-textarea" placeholder="Write your article in Markdown..." required><?= htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 6px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
            <span>Supports standard Markdown: <code>## Headings</code>, <code>**bold**</code>, <code>*italic*</code>, <code>[links](url)</code>, <code>![alt](image-url)</code>, and YouTube/Vimeo embeds.</span>
            <span id="word-count-display">0 words · 0 characters</span>
          </div>
        </div>

        <div id="editor-preview-panel" style="display: none;">
          <div id="editor-preview-loading" style="text-align: center; padding: 40px; color: var(--text-muted); display: none;">
            <span>Rendering live preview...</span>
          </div>
          <div id="editor-preview-content" class="article-body-content editor-preview-container" style="display: none;"></div>
        </div>
      </div>

      <!-- AI Summary Section (Edit Mode) -->
      <?php if ($isEdit): ?>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(226, 232, 240, 0.7);">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <span style="font-weight: 700; color: #4338ca; display: flex; align-items: center; gap: 6px;">
              <span>✨</span> AI Article Summary
            </span>
            <button type="button" id="generate-summary-btn" data-post-id="<?= (int) $post['id'] ?>" class="btn btn-secondary btn-sm">
              Generate AI Summary
            </button>
          </div>
          <?php if (!empty($post['summary'])): ?>
            <div class="ai-summary-card" style="margin-bottom: 0;">
              <p class="ai-summary-text"><?= nl2br(htmlspecialchars($post['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
              <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px;">
                Status: <strong><?= htmlspecialchars($post['summary_status'] ?? 'unknown', ENT_QUOTES, 'UTF-8') ?></strong>
              </div>
            </div>
          <?php else: ?>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
              Click "Generate AI Summary" to create a concise plain-text summary via the configured AI provider.
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar Settings Column -->
    <aside>
      <div class="glass-panel editor-sidebar-card">
        <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin-bottom: 16px;">Post Settings</h3>

        <div class="form-group">
          <label class="form-label">Publication Status</label>
          <div class="radio-group" style="flex-direction: column; gap: 10px;">
            <label class="radio-label">
              <input type="radio" name="status" value="draft" <?= (empty($post['status']) || $post['status'] === 'draft') ? 'checked' : '' ?>>
              <span>Draft (Not visible publicly)</span>
            </label>
            <label class="radio-label">
              <input type="radio" name="status" value="published" <?= (!empty($post['status']) && $post['status'] === 'published') ? 'checked' : '' ?>>
              <span>Published (Visible on blog)</span>
            </label>
          </div>
        </div>

        <?php if ($isEdit && !empty($post['published_at'])): ?>
          <div class="form-group" style="font-size: 0.85rem; color: var(--text-muted);">
            <div><strong>Published on:</strong> <?= date('M j, Y H:i', strtotime($post['published_at'])) ?></div>
            <?php if (!empty($post['updated_at'])): ?>
              <div style="margin-top: 4px;"><strong>Last edited:</strong> <?= date('M j, Y H:i', strtotime($post['updated_at'])) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 24px;">
          <button type="button" id="save-draft-btn" class="btn btn-secondary btn-full">
            Save as Draft
          </button>
          <button type="button" id="publish-btn" class="btn btn-primary btn-full">
            <?= $isEdit && ($post['status'] ?? '') === 'published' ? 'Update Post' : 'Publish Article' ?>
          </button>

          <?php if ($isEdit): ?>
            <button type="button" class="btn btn-danger btn-full" style="margin-top: 8px;"
                    onclick="openDeleteModal('<?= (($currentUser['role'] ?? '') === 'admin') ? '/admin/posts' : '/author/posts' ?>/<?= (int) $post['id'] ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>')">
              Delete Post
            </button>
          <?php endif; ?>

          <a href="/posts/manage" class="btn btn-secondary btn-full" style="text-align: center; justify-content: center; margin-top: 4px;">
            Cancel
          </a>
        </div>
      </div>
    </aside>
  </div>
</form>

<!-- Modal: Insert Video -->
<div id="insert-video-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="insert-video-modal-title">
  <div class="modal-card">
    <h3 id="insert-video-modal-title" class="modal-title">Insert Video into Article</h3>
    <p class="modal-desc">Embed a YouTube or Vimeo video, or upload an MP4/WebM video file directly to Supabase private storage.</p>

    <div style="display: flex; gap: 8px; margin-bottom: 18px; border-bottom: 1px solid rgba(226, 232, 240, 0.8); padding-bottom: 8px;">
      <button type="button" class="btn btn-secondary btn-sm active" id="video-mode-embed-btn" onclick="switchVideoModalTab('embed')">Embed Web Video</button>
      <button type="button" class="btn btn-secondary btn-sm" id="video-mode-upload-btn" onclick="switchVideoModalTab('upload')">Upload Video File</button>
    </div>

    <!-- Embed Mode -->
    <div id="video-tab-embed">
      <div class="form-group">
        <label for="video-embed-url" class="form-label">Video URL (YouTube or Vimeo)</label>
        <input type="url" id="video-embed-url" class="form-input" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/...">
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Supports standard watch, embed, and short URLs.</div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeInsertVideoModal()">Cancel</button>
        <button type="button" id="confirm-embed-video-btn" class="btn btn-primary">Insert Embed</button>
      </div>
    </div>

    <!-- Upload Mode -->
    <div id="video-tab-upload" style="display: none;">
      <div class="form-group">
        <label class="form-label">Upload Video (MP4 or WebM · Max 50MB)</label>
        <div id="modal-video-upload-box" class="featured-image-placeholder-box" style="cursor: pointer; padding: 18px;" onclick="document.getElementById('modal-video-file-input').click()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color); margin-bottom: 4px;">
            <polygon points="5 3 19 12 5 21 5 3"></polygon>
          </svg>
          <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-dark);">Click to select video file</div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Stored in private bucket with short-lived playback signatures.</div>
        </div>
        <input type="file" id="modal-video-file-input" accept="video/mp4,video/webm" style="display: none;">
        <div id="modal-video-upload-status" style="font-size: 0.82rem; margin-top: 8px; display: none;"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeInsertVideoModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script>
function updateWordCount() {
  const textarea = document.getElementById('post-content');
  const display = document.getElementById('word-count-display');
  if (!textarea || !display) return;
  const val = textarea.value.trim();
  const words = val ? val.split(/\s+/).length : 0;
  const chars = textarea.value.length;
  display.textContent = `${words} word${words === 1 ? '' : 's'} · ${chars} character${chars === 1 ? '' : 's'}`;
}

function insertMarkdown(prefix, suffix, defaultText = '') {
  const textarea = document.getElementById('post-content');
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = textarea.value;
  const selection = text.substring(start, end);
  const content = selection || defaultText;
  const replacement = prefix + content + suffix;
  textarea.value = text.substring(0, start) + replacement + text.substring(end);
  textarea.focus();
  if (selection) {
    textarea.setSelectionRange(start + prefix.length, start + prefix.length + selection.length);
  } else {
    textarea.setSelectionRange(start + prefix.length, start + prefix.length + defaultText.length);
  }
  updateWordCount();
}

async function switchEditorTab(tab) {
  const writeBtn = document.getElementById('tab-write-btn');
  const previewBtn = document.getElementById('tab-preview-btn');
  const writePanel = document.getElementById('editor-write-panel');
  const writeToolbar = document.getElementById('editor-write-toolbar');
  const previewPanel = document.getElementById('editor-preview-panel');
  const previewLoading = document.getElementById('editor-preview-loading');
  const previewContent = document.getElementById('editor-preview-content');

  if (tab === 'write') {
    writeBtn.classList.add('active');
    previewBtn.classList.remove('active');
    writePanel.style.display = 'block';
    writeToolbar.style.display = 'flex';
    previewPanel.style.display = 'none';
  } else {
    writeBtn.classList.remove('active');
    previewBtn.classList.add('active');
    writePanel.style.display = 'none';
    writeToolbar.style.display = 'none';
    previewPanel.style.display = 'block';
    previewLoading.style.display = 'block';
    previewContent.style.display = 'none';

    const content = document.getElementById('post-content').value;
    try {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      const res = await fetch('/posts/preview', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        },
        body: JSON.stringify({ content })
      });
      const data = await res.json();
      previewLoading.style.display = 'none';
      if (res.ok && data.data) {
        previewContent.innerHTML = data.data.html || '<p style="color: var(--text-muted); font-style: italic;">Nothing to preview yet. Write some markdown content!</p>';
        previewContent.style.display = 'block';
      } else {
        previewContent.innerHTML = '<p style="color: var(--danger-color);">Failed to load preview.</p>';
        previewContent.style.display = 'block';
      }
    } catch (err) {
      previewLoading.style.display = 'none';
      previewContent.innerHTML = '<p style="color: var(--danger-color);">Network error while loading preview.</p>';
      previewContent.style.display = 'block';
    }
  }
}

window.openInsertVideoModal = function() {
  document.getElementById('insert-video-modal')?.classList.add('open');
};

window.closeInsertVideoModal = function() {
  document.getElementById('insert-video-modal')?.classList.remove('open');
};

window.switchVideoModalTab = function(tab) {
  const embedTab = document.getElementById('video-tab-embed');
  const uploadTab = document.getElementById('video-tab-upload');
  const embedBtn = document.getElementById('video-mode-embed-btn');
  const uploadBtn = document.getElementById('video-mode-upload-btn');
  if (tab === 'embed') {
    embedTab.style.display = 'block';
    uploadTab.style.display = 'none';
    embedBtn.classList.add('active');
    uploadBtn.classList.remove('active');
  } else {
    embedTab.style.display = 'none';
    uploadTab.style.display = 'block';
    embedBtn.classList.remove('active');
    uploadBtn.classList.add('active');
  }
};

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('post-editor-form');
  const errorBox = document.getElementById('editor-error-alert');
  const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
  const postId = <?= $isEdit ? (int) $post['id'] : 'null' ?>;
  const apiBase = <?= json_encode((($currentUser['role'] ?? '') === 'admin') ? '/admin/posts' : '/author/posts') ?>;
  const currentPostStatus = <?= json_encode($post['status'] ?? 'draft') ?>;

  async function submitPost(targetStatus) {
    errorBox.style.display = 'none';

    const title = document.getElementById('post-title').value.trim();
    const content = document.getElementById('post-content').value.trim();
    const categoryVal = document.getElementById('post-category').value;
    const categoryId = categoryVal ? parseInt(categoryVal, 10) : null;

    if (!title) {
      errorBox.textContent = 'Please enter an article title.';
      errorBox.style.display = 'flex';
      return;
    }
    if (!content) {
      errorBox.textContent = 'Please enter article content.';
      errorBox.style.display = 'flex';
      return;
    }
    if (targetStatus === 'published' && !categoryId) {
      errorBox.textContent = 'A valid active category is required before publishing.';
      errorBox.style.display = 'flex';
      return;
    }

    const remixedFromVal = document.getElementById('remixed-from-post-id')?.value;
    const remixedFromId = remixedFromVal && parseInt(remixedFromVal, 10) > 0 ? parseInt(remixedFromVal, 10) : null;
    const coverPath = document.getElementById('post-cover-image-path')?.value || null;
    const videoPath = document.getElementById('post-video-path')?.value || null;

    const payload = {
      title,
      content,
      category_id: categoryId,
      remixed_from_post_id: remixedFromId,
      cover_image_path: coverPath,
      video_path: videoPath
    };

    const saveDraftBtn = document.getElementById('save-draft-btn');
    const publishBtn = document.getElementById('publish-btn');
    if (saveDraftBtn) saveDraftBtn.disabled = true;
    if (publishBtn) publishBtn.disabled = true;

    try {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      let url = isEdit ? `${apiBase}/${postId}` : apiBase;
      let method = isEdit ? 'PATCH' : 'POST';

      const res = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (res.ok) {
        const effectiveId = isEdit ? postId : data.data?.post?.id;
        if (targetStatus === 'published' && (currentPostStatus !== 'published' || !isEdit)) {
          const pubRes = await fetch(`${apiBase}/${effectiveId}/publish`, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-Token': csrf || ''
            }
          });
          if (!pubRes.ok) {
            const pubData = await pubRes.json();
            errorBox.textContent = pubData.error?.message || 'Post saved, but publishing failed.';
            errorBox.style.display = 'flex';
            return;
          }
        } else if (targetStatus === 'draft' && isEdit && currentPostStatus === 'published') {
          await fetch(`${apiBase}/${effectiveId}/unpublish`, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-Token': csrf || ''
            }
          });
        }
        window.location.href = isEdit ? '/posts/manage?updated=1' : '/posts/manage?created=1';
      } else {
        errorBox.textContent = data.error?.message || (data.error?.fields ? Object.values(data.error.fields).join(' ') : 'Could not save post.');
        errorBox.style.display = 'flex';
      }
    } catch (err) {
      errorBox.textContent = 'Network error while saving post.';
      errorBox.style.display = 'flex';
    } finally {
      if (saveDraftBtn) saveDraftBtn.disabled = false;
      if (publishBtn) publishBtn.disabled = false;
    }
  }

  // --- Cover Image Upload Handler ---
  const coverFileInput = document.getElementById('cover-file-input');
  const coverUploadStatus = document.getElementById('cover-upload-status');
  const coverPreviewContainer = document.getElementById('cover-preview-container');
  const coverPreviewImg = document.getElementById('cover-preview-img');
  const coverUploadBox = document.getElementById('cover-upload-box');
  const postCoverInput = document.getElementById('post-cover-image-path');

  async function uploadCoverFile(file) {
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      alert('Cover image size must not exceed 5MB.');
      return;
    }
    coverUploadStatus.textContent = 'Uploading cover image...';
    coverUploadStatus.style.color = 'var(--text-muted)';
    coverUploadStatus.style.display = 'block';

    const formData = new FormData();
    formData.append('cover', file);
    if (postCoverInput.value) {
      formData.append('old_path', postCoverInput.value);
    }
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || document.querySelector('input[name="csrf_token"]')?.value;
    formData.append('csrf_token', csrf || '');

    try {
      const res = await fetch('/posts/upload/cover', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf || '' },
        body: formData
      });
      const data = await res.json();
      if (res.ok && data.data) {
        postCoverInput.value = data.data.path;
        coverPreviewImg.src = data.data.url;
        coverPreviewContainer.style.display = 'block';
        coverUploadBox.style.display = 'none';
        coverUploadStatus.textContent = 'Cover image uploaded!';
        coverUploadStatus.style.color = '#16a34a';
        setTimeout(() => { coverUploadStatus.style.display = 'none'; }, 3000);
      } else {
        coverUploadStatus.textContent = data.error?.message || 'Failed to upload cover image.';
        coverUploadStatus.style.color = '#dc2626';
      }
    } catch (err) {
      coverUploadStatus.textContent = 'Network error during cover upload.';
      coverUploadStatus.style.color = '#dc2626';
    }
  }

  coverFileInput?.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0]) {
      uploadCoverFile(e.target.files[0]);
    }
  });
  document.getElementById('change-cover-btn')?.addEventListener('click', () => coverFileInput?.click());
  document.getElementById('remove-cover-btn')?.addEventListener('click', () => {
    postCoverInput.value = '';
    coverPreviewImg.src = '';
    coverPreviewContainer.style.display = 'none';
    coverUploadBox.style.display = 'block';
    coverFileInput.value = '';
  });

  // --- Video Upload Handler ---
  const videoFileInput = document.getElementById('video-file-input');
  const videoUploadStatus = document.getElementById('video-upload-status');
  const videoPreviewContainer = document.getElementById('video-preview-container');
  const videoPreviewLabel = document.getElementById('video-preview-label');
  const videoUploadBox = document.getElementById('video-upload-box');
  const postVideoInput = document.getElementById('post-video-path');

  async function uploadVideoFile(file) {
    if (!file) return;
    if (file.size > 50 * 1024 * 1024) {
      alert('Video file size must not exceed 50MB.');
      return;
    }
    videoUploadStatus.textContent = 'Uploading video (up to 50MB, please wait)...';
    videoUploadStatus.style.color = 'var(--text-muted)';
    videoUploadStatus.style.display = 'block';

    const formData = new FormData();
    formData.append('video', file);
    if (postVideoInput.value) {
      formData.append('old_path', postVideoInput.value);
    }
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || document.querySelector('input[name="csrf_token"]')?.value;
    formData.append('csrf_token', csrf || '');

    try {
      const res = await fetch('/posts/upload/video', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf || '' },
        body: formData
      });
      const data = await res.json();
      if (res.ok && data.data) {
        postVideoInput.value = data.data.path;
        if (videoPreviewLabel) videoPreviewLabel.textContent = file.name;
        videoPreviewContainer.style.display = 'flex';
        videoUploadBox.style.display = 'none';
        videoUploadStatus.textContent = 'Video uploaded successfully to private storage!';
        videoUploadStatus.style.color = '#16a34a';
        setTimeout(() => { videoUploadStatus.style.display = 'none'; }, 3000);
      } else {
        videoUploadStatus.textContent = data.error?.message || 'Failed to upload video.';
        videoUploadStatus.style.color = '#dc2626';
      }
    } catch (err) {
      videoUploadStatus.textContent = 'Network error during video upload.';
      videoUploadStatus.style.color = '#dc2626';
    }
  }

  videoFileInput?.addEventListener('change', (e) => {
    if (e.target.files && e.target.files[0]) {
      uploadVideoFile(e.target.files[0]);
    }
  });
  document.getElementById('change-video-btn')?.addEventListener('click', () => videoFileInput?.click());
  document.getElementById('remove-video-btn')?.addEventListener('click', () => {
    postVideoInput.value = '';
    videoPreviewContainer.style.display = 'none';
    videoUploadBox.style.display = 'block';
    videoFileInput.value = '';
  });

  // --- Inline Image Toolbar Handler ---
  const inlineImageBtn = document.getElementById('insert-image-btn');
  const inlineImageInput = document.getElementById('inline-image-file-input');

  inlineImageBtn?.addEventListener('click', () => inlineImageInput?.click());
  inlineImageInput?.addEventListener('change', async (e) => {
    if (!e.target.files || !e.target.files[0]) return;
    const file = e.target.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('Article image must not exceed 5MB.');
      return;
    }
    const origText = inlineImageBtn.textContent;
    inlineImageBtn.textContent = 'Uploading...';
    inlineImageBtn.disabled = true;

    const formData = new FormData();
    formData.append('image', file);
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || document.querySelector('input[name="csrf_token"]')?.value;
    formData.append('csrf_token', csrf || '');

    try {
      const res = await fetch('/posts/upload/inline', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf || '' },
        body: formData
      });
      const data = await res.json();
      if (res.ok && data.data && data.data.url) {
        insertMarkdown(`![${file.name.replace(/[\[\]]/g, '')}](${data.data.url})`, '');
      } else {
        alert(data.error?.message || 'Failed to upload image.');
      }
    } catch (err) {
      alert('Network error while uploading image.');
    } finally {
      inlineImageBtn.textContent = origText;
      inlineImageBtn.disabled = false;
      inlineImageInput.value = '';
    }
  });

  // --- Word Count & Typing Listener ---
  const contentTextarea = document.getElementById('post-content');
  if (contentTextarea) {
    contentTextarea.addEventListener('input', updateWordCount);
    updateWordCount();
  }

  // --- Video Insert Modal Event Listeners ---
  document.getElementById('open-video-modal-btn')?.addEventListener('click', () => {
    window.openInsertVideoModal();
  });

  document.getElementById('confirm-embed-video-btn')?.addEventListener('click', () => {
    const urlInput = document.getElementById('video-embed-url');
    const url = urlInput ? urlInput.value.trim() : '';
    if (!url) {
      alert('Please enter a YouTube or Vimeo video link.');
      return;
    }
    insertMarkdown(`\n[video](${url})\n`, '');
    if (urlInput) urlInput.value = '';
    window.closeInsertVideoModal();
  });

  const modalVideoFileInput = document.getElementById('modal-video-file-input');
  const modalVideoStatus = document.getElementById('modal-video-upload-status');
  modalVideoFileInput?.addEventListener('change', async (e) => {
    if (!e.target.files || !e.target.files[0]) return;
    const file = e.target.files[0];
    if (file.size > 50 * 1024 * 1024) {
      alert('Video file size must not exceed 50MB.');
      return;
    }
    if (modalVideoStatus) {
      modalVideoStatus.textContent = 'Uploading video to private storage...';
      modalVideoStatus.style.color = 'var(--text-muted)';
      modalVideoStatus.style.display = 'block';
    }

    const formData = new FormData();
    formData.append('video', file);
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || document.querySelector('input[name="csrf_token"]')?.value;
    formData.append('csrf_token', csrf || '');

    try {
      const res = await fetch('/posts/upload/video', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf || '' },
        body: formData
      });
      const data = await res.json();
      if (res.ok && data.data && data.data.path) {
        insertMarkdown(`\n[video](${data.data.path})\n`, '');
        if (modalVideoStatus) {
          modalVideoStatus.textContent = 'Uploaded and inserted into article!';
          modalVideoStatus.style.color = '#16a34a';
        }
        setTimeout(() => {
          if (modalVideoStatus) modalVideoStatus.style.display = 'none';
          window.closeInsertVideoModal();
        }, 1200);
      } else {
        if (modalVideoStatus) {
          modalVideoStatus.textContent = data.error?.message || 'Video upload failed.';
          modalVideoStatus.style.color = '#dc2626';
        }
      }
    } catch (err) {
      if (modalVideoStatus) {
        modalVideoStatus.textContent = 'Network error during upload.';
        modalVideoStatus.style.color = '#dc2626';
      }
    } finally {
      modalVideoFileInput.value = '';
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    submitPost('published');
  });
  document.getElementById('save-draft-btn')?.addEventListener('click', () => submitPost('draft'));
  document.getElementById('publish-btn')?.addEventListener('click', () => submitPost('published'));
});
</script>
