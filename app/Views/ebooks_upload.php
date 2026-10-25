<?php
declare(strict_types=1);
/**
 * Member & Community Guest eBook Upload View
 */
?>
<div class="page-container" style="max-width: 860px; margin: 0 auto; padding: 36px 20px;">

  <!-- Header Breadcrumb & Title -->
  <div style="margin-bottom: 28px;">
    <a href="/ebooks" style="font-size: 0.88rem; font-weight: 600; color: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;">
      <span>&larr;</span> Back to eBook Library
    </a>
    <h1 style="font-size: 2.3rem; font-weight: 800; color: var(--text-main); margin: 0 0 8px;">
      Upload an eBook
    </h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin: 0; line-height: 1.5;">
      Share digital books, guides, tutorials, or open-source manuals with the Infinity Blog reading community. Supported formats: <strong>PDF</strong> and <strong>EPUB</strong> (up to 30MB).
    </p>
  </div>

  <?php if (!empty($submitted)): ?>
    <!-- SUCCESS CONFIRMATION BANNER -->
    <div class="glass-panel" style="padding: 28px 32px; border-radius: 18px; margin-bottom: 32px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.35); text-align: center;">
      <div style="font-size: 3rem; margin-bottom: 10px;">🎉</div>
      <h2 style="font-size: 1.5rem; font-weight: 800; color: #059669; margin: 0 0 10px;">
        eBook Submitted Successfully!
      </h2>
      <p style="color: var(--text-main); font-size: 0.98rem; margin: 0 auto 16px; max-width: 600px; line-height: 1.5;">
        Thank you for contributing to the public library! Your book has been securely uploaded and queued for community moderation review. Once verified, it will be published to the public catalog for everyone to discover and read.
      </p>
      <?php if (!empty($submissionRef)): ?>
        <div style="display: inline-block; padding: 6px 16px; border-radius: 20px; background: rgba(16, 185, 129, 0.15); color: #047857; font-weight: 700; font-size: 0.88rem; margin-bottom: 22px; font-family: monospace;">
          Reference ID: <?= htmlspecialchars($submissionRef, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>
      <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
        <a href="/ebooks" class="btn btn-primary" style="border-radius: 12px; padding: 10px 24px; font-weight: 700;">
          Browse eBook Library
        </a>
        <a href="/ebooks/upload" class="btn btn-secondary" style="border-radius: 12px; padding: 10px 24px; font-weight: 600;">
          Submit Another Book
        </a>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($account)): ?>
    <!-- GUEST CONTRIBUTOR NOTICE -->
    <div class="glass-panel" style="padding: 16px 22px; border-radius: 14px; margin-bottom: 26px; background: rgba(99, 102, 241, 0.07); border: 1px solid rgba(99, 102, 241, 0.25); display: flex; align-items: flex-start; gap: 14px;">
      <span style="font-size: 1.4rem; line-height: 1;">💡</span>
      <div style="font-size: 0.92rem; color: var(--text-main); line-height: 1.5;">
        <strong>Guest Contributor Welcome:</strong> Anyone can share open-access and community eBooks without logging in. All submissions are moderated before going live.
        <a href="/login?redirect=<?= urlencode('/ebooks/upload') ?>" style="color: var(--primary-color); font-weight: 600; text-decoration: underline; margin-left: 4px;">Sign in</a> if you wish to track your uploaded books and reading stats under your personal profile.
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="glass-panel" style="padding: 18px 24px; border-radius: 14px; margin-bottom: 28px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3);">
      <div style="font-weight: 700; color: #dc2626; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
        <span>⚠️</span> Please correct the following errors:
      </div>
      <ul style="margin: 0; padding-left: 20px; color: #dc2626; font-size: 0.92rem;">
        <?php foreach ($errors as $field => $err): ?>
          <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-panel" style="padding: 36px 32px; border-radius: 20px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
    <form method="POST" action="/ebooks/upload" enctype="multipart/form-data" id="ebookUploadForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <!-- Anti-abuse Honeypot (hidden from human visitors) -->
      <div style="display: none !important;" aria-hidden="true">
        <label for="website_hp">Leave this field blank</label>
        <input type="text" id="website_hp" name="website_hp" value="" tabindex="-1" autocomplete="off">
      </div>

      <!-- SECTION 1: EBOOK FILE SELECTION (PDF / EPUB) -->
      <div style="margin-bottom: 30px;">
        <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
          eBook Document File * <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(PDF or EPUB, Max 30MB)</span>
        </label>
        
        <div id="dropZoneFile" class="upload-dropzone" style="border: 2px dashed var(--glass-border); border-radius: 16px; padding: 32px 20px; text-align: center; cursor: pointer; transition: all 0.2s ease; background: rgba(0,0,0,0.02);">
          <div id="fileUploadPrompt">
            <div style="font-size: 3rem; margin-bottom: 10px;">📄</div>
            <div style="font-weight: 700; font-size: 1.05rem; color: var(--text-main); margin-bottom: 4px;">
              Choose or drag &amp; drop your eBook file here
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
              Supports .pdf and .epub formats
            </div>
          </div>

          <div id="fileSelectedDisplay" style="display: none; align-items: center; justify-content: center; gap: 14px;">
            <div style="font-size: 2.2rem;" id="selectedFileIcon">📑</div>
            <div style="text-align: left;">
              <div id="selectedFileName" style="font-weight: 700; color: var(--text-main); font-size: 1rem;"></div>
              <div id="selectedFileSize" style="font-size: 0.85rem; color: var(--text-muted);"></div>
            </div>
            <button type="button" id="removeFileBtn" class="btn btn-secondary btn-sm" style="margin-left: 12px; border-radius: 8px;">
              Change File
            </button>
          </div>

          <input type="file" name="ebook_file" id="ebookFileInput" accept=".pdf,.epub,application/pdf,application/epub+zip" style="display: none;" required>
        </div>
      </div>

      <!-- SECTION 2: BOOK DETAILS -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <!-- Title -->
        <div>
          <label for="bookTitle" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
            Book Title *
          </label>
          <input type="text" id="bookTitle" name="title" value="<?= htmlspecialchars($formData['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="e.g. Designing Data-Intensive Applications" required maxlength="255" class="form-input" style="width: 100%; border-radius: 12px; height: 44px;">
        </div>

        <!-- Author Name -->
        <div>
          <label for="authorName" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
            Author / Creator *
          </label>
          <input type="text" id="authorName" name="author_name" value="<?= htmlspecialchars($formData['author_name'] ?? ($currentUser['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="Author name or organization" required maxlength="255" class="form-input" style="width: 100%; border-radius: 12px; height: 44px;">
        </div>
      </div>

      <!-- Category & Community -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <!-- Category -->
        <div>
          <label for="bookCategory" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
            Category *
          </label>
          <select id="bookCategory" name="category_id" required class="form-input" style="width: 100%; border-radius: 12px; height: 44px;">
            <option value="">Select a Category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($formData['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Associated Community (Optional) -->
        <div>
          <label for="bookCommunity" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
            Associated Community <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(Optional)</span>
          </label>
          <select id="bookCommunity" name="community_id" class="form-input" style="width: 100%; border-radius: 12px; height: 44px;">
            <option value="">None (General Library)</option>
            <?php foreach ($communities as $room): ?>
              <option value="<?= (int) $room['id'] ?>" <?= ((int) ($formData['community_id'] ?? 0) === (int) $room['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($room['name'] ?? $room['title'] ?? 'Community #' . $room['id'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Description / Synopsis -->
      <div style="margin-bottom: 24px;">
        <label for="bookDescription" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
          Synopsis &amp; Description *
        </label>
        <textarea id="bookDescription" name="description" rows="5" required minlength="10"
                  placeholder="Provide an overview of the book, key chapters, target audience, and why readers should explore it..."
                  class="form-input" style="width: 100%; border-radius: 12px; line-height: 1.5;"><?= htmlspecialchars($formData['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <!-- Cover Image Selection (Optional) -->
      <div style="margin-bottom: 28px;">
        <label class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
          Cover Image <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(Optional — JPG, PNG, or WebP up to 4MB)</span>
        </label>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0; margin-bottom: 10px;">
          If left blank, an embedded cover will be extracted automatically from EPUB files, or a custom gradient SVG cover will be created.
        </p>

        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
          <div id="coverPreviewWrap" style="width: 90px; height: 125px; border-radius: 10px; background: rgba(0,0,0,0.06); border: 1px dashed var(--glass-border); overflow: hidden; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
            <img id="coverPreviewImg" src="" alt="Cover preview" style="display: none; width: 100%; height: 100%; object-fit: cover;">
            <span id="coverPreviewPlaceholder" style="font-size: 1.8rem; color: var(--text-muted);">🖼️</span>
          </div>

          <div style="flex: 1; min-width: 200px;">
            <input type="file" name="cover_image" id="coverImageInput" accept="image/jpeg,image/png,image/webp" class="form-input" style="width: 100%; border-radius: 12px; height: 44px; padding: 8px 12px;">
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Recommended aspect ratio: 1:1.4 (portrait book orientation, min 50x50px)</div>
          </div>
        </div>
      </div>

      <!-- Tags & Language -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 28px;">
        <!-- Tags -->
        <div>
          <label for="bookTags" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
            Tags <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(Comma separated)</span>
          </label>
          <input type="text" id="bookTags" name="tags" value="<?= htmlspecialchars($formData['tags'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="e.g. algorithms, python, beginner, cloud" class="form-input" style="width: 100%; border-radius: 12px; height: 44px;">
        </div>

        <!-- Language -->
        <div>
          <label for="bookLanguage" class="form-label" style="display: block; font-weight: 700; margin-bottom: 6px; color: var(--text-main);">
            Language
          </label>
          <select id="bookLanguage" name="language" class="form-input" style="width: 100%; border-radius: 12px; height: 44px;">
            <option value="en" <?= ($formData['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
            <option value="es" <?= ($formData['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish (Español)</option>
            <option value="fr" <?= ($formData['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French (Français)</option>
            <option value="de" <?= ($formData['language'] ?? '') === 'de' ? 'selected' : '' ?>>German (Deutsch)</option>
            <option value="zh" <?= ($formData['language'] ?? '') === 'zh' ? 'selected' : '' ?>>Chinese (中文)</option>
            <option value="ja" <?= ($formData['language'] ?? '') === 'ja' ? 'selected' : '' ?>>Japanese (日本語)</option>
            <option value="other" <?= ($formData['language'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      </div>

      <!-- SECTION 3: CONTRIBUTOR CONTACT DETAILS (Optional, for guest submitters) -->
      <?php if (empty($account)): ?>
        <div style="background: rgba(0,0,0,0.02); border-radius: 14px; padding: 20px; margin-bottom: 28px; border: 1px solid var(--glass-border);">
          <div style="font-weight: 700; color: var(--text-main); font-size: 1rem; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
            <span>👤</span> Contributor Information <span style="font-weight: 400; font-size: 0.85rem; color: var(--text-muted);">(Optional)</span>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 16px; line-height: 1.4;">
            As a guest submitter, you may optionally provide your name and email.
          </p>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
            <div>
              <label for="submitterName" class="form-label" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; color: var(--text-main);">
                Your Name / Alias
              </label>
              <input type="text" id="submitterName" name="submitter_name" value="<?= htmlspecialchars($formData['submitter_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="e.g. Alex M." maxlength="255" class="form-input" style="width: 100%; border-radius: 12px; height: 42px;">
            </div>

            <div>
              <label for="submitterEmail" class="form-label" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; color: var(--text-main);">
                Notification Email
              </label>
              <input type="email" id="submitterEmail" name="submitter_email" value="<?= htmlspecialchars($formData['submitter_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="e.g. alex@example.com" maxlength="255" class="form-input" style="width: 100%; border-radius: 12px; height: 42px;">
            </div>
          </div>

          <div style="margin-top: 10px; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
            <span>🔒</span> <em>Privacy Guarantee: Your email is stored securely for moderation and copyright notices only. It is NEVER shown publicly or exposed in any API.</em>
          </div>
        </div>
      <?php endif; ?>

      <!-- Permissions & Compliance Checkboxes -->
      <div style="background: rgba(0,0,0,0.02); border-radius: 14px; padding: 20px; margin-bottom: 30px; border: 1px solid var(--glass-border); display: flex; flex-direction: column; gap: 14px;">
        <!-- Allow Downloads -->
        <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; user-select: none;">
          <input type="checkbox" name="allow_download" value="1" checked style="width: 18px; height: 18px; margin-top: 2px;">
          <div>
            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
              Allow readers to download the original file
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
              When enabled, anyone can download the original PDF/EPUB to read offline.
            </div>
          </div>
        </label>

        <!-- Copyright Confirmation -->
        <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; user-select: none;">
          <input type="checkbox" name="copyright_confirmed" value="1" required style="width: 18px; height: 18px; margin-top: 2px;">
          <div>
            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
              Copyright &amp; Licensing Confirmation *
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">
              I confirm that I have the legal right or permission to share this eBook, or that it is in the public domain or distributed under an open/creative commons license. Uploads violating copyrights are subject to takedown.
            </div>
          </div>
        </label>
      </div>

      <!-- Submission Actions -->
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;">
        <span style="font-size: 0.82rem; color: var(--text-muted);">
          🛡️ All uploads undergo automated file verification and moderation.
        </span>
        <div style="display: flex; gap: 12px;">
          <a href="/ebooks" class="btn btn-secondary" style="border-radius: 12px; padding: 12px 24px; font-weight: 600;">
            Cancel
          </a>
          <button type="submit" id="submitBtn" class="btn btn-primary" style="border-radius: 12px; padding: 12px 32px; font-weight: 700; box-shadow: 0 4px 14px rgba(37,99,235,0.25);">
            Submit for Moderation
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const dropZone = document.getElementById('dropZoneFile');
  const fileInput = document.getElementById('ebookFileInput');
  const promptWrap = document.getElementById('fileUploadPrompt');
  const displayWrap = document.getElementById('fileSelectedDisplay');
  const nameEl = document.getElementById('selectedFileName');
  const sizeEl = document.getElementById('selectedFileSize');
  const iconEl = document.getElementById('selectedFileIcon');
  const removeBtn = document.getElementById('removeFileBtn');

  const coverInput = document.getElementById('coverImageInput');
  const coverImg = document.getElementById('coverPreviewImg');
  const coverPlaceholder = document.getElementById('coverPreviewPlaceholder');

  // Trigger file selection on dropzone click
  dropZone.addEventListener('click', (e) => {
    if (e.target !== removeBtn) {
      fileInput.click();
    }
  });

  // Drag and drop events
  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.style.borderColor = 'var(--primary-color)';
      dropZone.style.background = 'rgba(37,99,235,0.06)';
    });
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.style.borderColor = 'var(--glass-border)';
      dropZone.style.background = 'rgba(0,0,0,0.02)';
    });
  });

  dropZone.addEventListener('drop', (e) => {
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
      fileInput.files = files;
      handleFileSelected(files[0]);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files[0]) {
      handleFileSelected(fileInput.files[0]);
    }
  });

  function handleFileSelected(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    iconEl.textContent = ext === 'pdf' ? '📕' : '📘';
    nameEl.textContent = file.name;
    const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
    sizeEl.textContent = `${sizeMb} MB (${ext.toUpperCase()})`;

    promptWrap.style.display = 'none';
    displayWrap.style.display = 'flex';

    // Auto-fill title if empty
    const titleInput = document.getElementById('bookTitle');
    if (!titleInput.value) {
      const cleanName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
      titleInput.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
    }
  }

  removeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    fileInput.value = '';
    promptWrap.style.display = 'block';
    displayWrap.style.display = 'none';
  });

  // Live cover image preview
  coverInput.addEventListener('change', () => {
    if (coverInput.files && coverInput.files[0]) {
      const file = coverInput.files[0];
      const reader = new FileReader();
      reader.onload = (e) => {
        coverImg.src = e.target.result;
        coverImg.style.display = 'block';
        coverPlaceholder.style.display = 'none';
      };
      reader.readAsDataURL(file);
    } else {
      coverImg.style.display = 'none';
      coverPlaceholder.style.display = 'block';
    }
  });

  // Form submit state
  const form = document.getElementById('ebookUploadForm');
  const submitBtn = document.getElementById('submitBtn');
  form.addEventListener('submit', () => {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading & Verifying...';
  });
})();
</script>
