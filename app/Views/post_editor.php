<!-- Screens 4 & 5: Post Editor (Create & Edit) -->
<?php
  $isEdit = !empty($post['id']);
  $pageHeading = $isEdit ? 'Edit Post' : 'Create New Post';
  $pageSubhead = $isEdit ? 'Update your article content and publication settings.' : 'Write and publish your ideas to the world.';
?>

<div class="dashboard-header">
  <div>
    <h1 class="dashboard-greeting"><?= $pageHeading ?></h1>
    <p class="dashboard-subhead"><?= $pageSubhead ?></p>
  </div>

  <a href="/posts/manage" class="btn btn-secondary btn-pill">
    ← Back to All Posts
  </a>
</div>

<div id="editor-error-alert" class="alert-banner alert-error" style="display: none;"></div>

<form id="post-editor-form" method="POST" action="<?= $isEdit ? '/author/posts/' . (int) $post['id'] : '/author/posts' ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" id="post-id" value="<?= (int) $post['id'] ?>">
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

      <div class="form-group">
        <label class="form-label">Featured Image Thumbnail <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Optional)</span></label>
        <div class="featured-image-placeholder-box">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color); margin-bottom: 6px;">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <polyline points="21 15 16 10 5 21"></polyline>
          </svg>
          <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-dark);">Editorial Visual Card</div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Standard pastel claymorphic banner will be paired automatically with this topic.</div>
        </div>
      </div>

      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
          <label for="post-content" class="form-label" style="margin-bottom: 0;">Article Content <span style="color: var(--danger-color);">*</span></label>
          <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('**', '**')" title="Bold">B</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('*', '*')" title="Italic"><i>I</i></button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('## ', '')" title="Heading">H2</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('> ', '')" title="Quote">”</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('```\n', '\n```')" title="Code">Code</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('- ', '')" title="Bullet List">• List</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="insertMarkdown('[', '](https://)')" title="Link">🔗 Link</button>
          </div>
        </div>
        <textarea id="post-content" name="content" class="form-textarea" placeholder="Write your article here..." required><?= htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
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

<script>
function insertMarkdown(prefix, suffix) {
  const textarea = document.getElementById('post-content');
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = textarea.value;
  const selection = text.substring(start, end);
  const replacement = prefix + selection + suffix;
  textarea.value = text.substring(0, start) + replacement + text.substring(end);
  textarea.focus();
  textarea.setSelectionRange(start + prefix.length, end + prefix.length);
}

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

    const payload = {
      title,
      content,
      category_id: categoryId
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

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    submitPost('published');
  });
  document.getElementById('save-draft-btn')?.addEventListener('click', () => submitPost('draft'));
  document.getElementById('publish-btn')?.addEventListener('click', () => submitPost('published'));
});
</script>
