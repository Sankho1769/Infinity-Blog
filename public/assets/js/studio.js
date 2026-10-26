/**
 * Infinity Blog — Professional Writing Studio Controller
 * Matches media_1792406624845_52fa8e85.jpg reference architecture
 */
(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const studioContainer = document.getElementById('infinity-writing-studio');
    if (!studioContainer) return;

    // Elements
    const form = document.getElementById('post-editor-form');
    const titleInput = document.getElementById('post-title');
    const subtitleInput = document.getElementById('post-subtitle');
    const contentTextarea = document.getElementById('post-content');
    const categorySelect = document.getElementById('post-category');
    const scheduledAtInput = document.getElementById('scheduled-at');
    const errorAlert = document.getElementById('editor-error-alert');

    const saveDot = document.getElementById('studio-save-dot');
    const saveText = document.getElementById('studio-save-text');

    const postIdInput = document.getElementById('post-id');
    const isEdit = !!postIdInput;
    const postId = isEdit ? parseInt(postIdInput.value, 10) : null;
    const draftStorageKey = 'infinity_blog_draft_' + (postId ? postId : 'new');

    const apiBase = window.BlogCMS?.apiBase || '/author/posts';
    let isDirty = false;
    let autosaveTimer = null;
    let lastSavedTime = new Date();

    // Goal and Settings State
    let targetWords = parseInt(document.getElementById('goal-words-target')?.textContent || '2000', 10);
    let selectedTone = 'formal';

    // Helper: get CSRF token
    async function getCsrfToken() {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="csrf_token"]')?.value;
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }
      return csrf || '';
    }

    // =========================================================================
    // 1. DYNAMIC OUTLINE GENERATOR
    // =========================================================================
    const outlineContainer = document.getElementById('studio-outline-container');

    function updateOutline() {
      if (!outlineContainer || !contentTextarea) return;
      const text = contentTextarea.value;
      const lines = text.split('\n');
      const headings = [];

      lines.forEach((line, index) => {
        const match = line.match(/^(#{1,3})\s+(.+)$/);
        if (match) {
          headings.push({
            level: match[1].length,
            title: match[2].trim(),
            lineIndex: index
          });
        }
      });

      if (headings.length === 0) {
        outlineContainer.innerHTML = '<span style="font-size: 0.78rem; color: var(--studio-muted); font-style: italic;">Add headings (## Section Title) to generate your live outline.</span>';
        return;
      }

      outlineContainer.innerHTML = '';
      headings.forEach((h, i) => {
        const item = document.createElement('div');
        item.className = `studio-outline-item ${h.level === 3 ? 'level-3' : ''} ${i === 0 ? 'active' : ''}`;
        item.textContent = `${i + 1}. ${h.title}`;
        item.title = h.title;
        item.addEventListener('click', () => {
          document.querySelectorAll('.studio-outline-item').forEach(el => el.classList.remove('active'));
          item.classList.add('active');

          // Scroll and select heading in textarea
          const linesBefore = lines.slice(0, h.lineIndex).join('\n');
          const charPos = linesBefore.length + (h.lineIndex > 0 ? 1 : 0);
          contentTextarea.focus();
          contentTextarea.setSelectionRange(charPos, charPos + lines[h.lineIndex].length);

          const lineHeight = 28;
          contentTextarea.scrollTop = Math.max(0, h.lineIndex * lineHeight - 100);
        });
        outlineContainer.appendChild(item);
      });
    }

    // "+ Add section" Button
    document.getElementById('outline-add-heading-btn')?.addEventListener('click', addSectionHeading);
    document.getElementById('btn-add-section-action')?.addEventListener('click', addSectionHeading);

    function addSectionHeading() {
      insertMarkdown('\n\n## ', '\n\n', 'New Section Title');
      updateOutline();
    }

    // =========================================================================
    // 2. WORD COUNT, READING TIME & WRITING GOAL RING
    // =========================================================================
    function updateMetrics() {
      const val = contentTextarea?.value.trim() || '';
      const words = val ? val.split(/\s+/).length : 0;
      const chars = contentTextarea?.value.length || 0;
      const readTime = Math.max(1, Math.ceil(words / 200));

      const statWords = document.getElementById('studio-stat-words');
      const statReadtime = document.getElementById('studio-stat-readtime');
      const statChars = document.getElementById('studio-stat-chars');
      if (statWords) statWords.textContent = `${words} word${words === 1 ? '' : 's'}`;
      if (statReadtime) statReadtime.textContent = `${readTime} min read`;
      if (statChars) statChars.textContent = `${chars} character${chars === 1 ? '' : 's'}`;

      // Goal progress
      const goalCurrent = document.getElementById('goal-words-current');
      const goalPercent = document.getElementById('goal-percent-text');
      const goalArc = document.getElementById('goal-ring-arc');

      if (goalCurrent) goalCurrent.textContent = words.toLocaleString();
      const pct = Math.min(100, Math.round((words / targetWords) * 100));
      if (goalPercent) goalPercent.textContent = `${pct}%`;
      if (goalArc) goalArc.setAttribute('stroke-dasharray', `${pct}, 100`);

      updateSeoReadiness(words);
    }

    // Custom Goal Modal Dialog (PART 8)
    const wordCountModal = document.getElementById('word-count-modal');
    const wordCountInput = document.getElementById('modal-target-words-input');
    const wordCountSaveBtn = document.getElementById('modal-save-goal-btn') || document.getElementById('word-count-modal-save');
    const wordCountCancelBtn = document.getElementById('modal-cancel-goal-btn') || document.getElementById('word-count-modal-cancel');
    const wordCountXBtn = document.getElementById('word-count-modal-x-btn');
    const wordCountPresets = document.querySelectorAll('.word-goal-preset, .preset-pill');

    function openWordCountModal() {
      if (!wordCountModal || !wordCountInput) return;
      wordCountInput.value = targetWords.toString();
      wordCountModal.style.display = 'flex';
      setTimeout(() => wordCountInput.focus(), 60);
    }
    window.openWordCountModal = openWordCountModal;

    function closeWordCountModal() {
      if (!wordCountModal) return;
      wordCountModal.style.display = 'none';
    }

    function saveWordCountGoal() {
      if (!wordCountInput) return;
      const parsed = parseInt(wordCountInput.value, 10);
      if (!isNaN(parsed) && parsed >= 50 && parsed <= 50000) {
        targetWords = parsed;
        const targetEl = document.getElementById('goal-words-target');
        if (targetEl) targetEl.textContent = targetWords.toLocaleString();
        updateMetrics();
        persistStudioData();
        closeWordCountModal();
      } else {
        alert('Please enter a target word count between 50 and 50,000.');
        wordCountInput.focus();
      }
    }

    document.getElementById('studio-writing-goal-card')?.addEventListener('click', openWordCountModal);
    wordCountCancelBtn?.addEventListener('click', closeWordCountModal);
    wordCountXBtn?.addEventListener('click', closeWordCountModal);
    wordCountSaveBtn?.addEventListener('click', saveWordCountGoal);

    wordCountPresets.forEach(btn => {
      btn.addEventListener('click', () => {
        const words = btn.getAttribute('data-words');
        if (words && wordCountInput) {
          wordCountInput.value = words;
          wordCountInput.focus();
        }
      });
    });

    wordCountInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        saveWordCountGoal();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeWordCountModal();
      }
    });

    wordCountModal?.addEventListener('click', (e) => {
      if (e.target === wordCountModal) {
        closeWordCountModal();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && wordCountModal && wordCountModal.style.display === 'flex') {
        closeWordCountModal();
      }
    });

    // =========================================================================
    // 3. SEO & PUBLISH READINESS METER
    // =========================================================================
    function updateSeoReadiness(words) {
      const hasSubtitle = (subtitleInput?.value.trim().length || 0) > 0;
      const hasCategory = !!categorySelect?.value;
      const hasCover = !!document.getElementById('post-cover-image-path')?.value;
      const hasContent = words >= 50;
      const hasTags = (titleInput?.value.length || 0) > 10;

      let score = 0;
      function setCheck(id, pass) {
        const row = document.getElementById(id);
        if (!row) return;
        const icon = row.querySelector('.chk-icon');
        if (pass) {
          row.classList.add('is-complete');
          if (icon) icon.textContent = '✓';
          score++;
        } else {
          row.classList.remove('is-complete');
          if (icon) icon.textContent = '☐';
        }
      }

      setCheck('chk-meta-desc', hasSubtitle);
      setCheck('chk-category', hasCategory);
      setCheck('chk-tags', hasTags);
      setCheck('chk-featured-image', hasCover);
      setCheck('chk-readability', hasContent);

      const fractionEl = document.getElementById('seo-score-fraction');
      const arcEl = document.getElementById('seo-gauge-arc');
      if (fractionEl) fractionEl.textContent = `${score}/5`;
      if (arcEl) arcEl.setAttribute('stroke-dasharray', `${(score / 5) * 100}, 100`);
    }

    // SEO Item Click Shortcuts
    document.getElementById('chk-meta-desc')?.addEventListener('click', () => subtitleInput?.focus());
    document.getElementById('chk-category')?.addEventListener('click', () => categorySelect?.focus());
    document.getElementById('chk-featured-image')?.addEventListener('click', () => document.getElementById('cover-file-input')?.click());

    // =========================================================================
    // 4. TRUTHFUL SAVE STATUS INDICATOR & AUTOSAVE
    // =========================================================================
    function setSaveStatus(state, message) {
      if (!saveDot || !saveText) return;
      saveDot.className = 'studio-save-dot';

      if (state === 'saving') {
        saveDot.classList.add('is-saving');
        saveText.textContent = message || 'Saving...';
      } else if (state === 'saved') {
        saveText.textContent = message || 'Saved just now';
        lastSavedTime = new Date();
      } else if (state === 'dirty') {
        saveDot.classList.add('is-dirty');
        saveText.textContent = message || 'Unsaved changes';
      } else if (state === 'error') {
        saveDot.classList.add('is-error');
        saveText.textContent = message || 'Save failed';
      }
    }

    function markDirty() {
      isDirty = true;
      setSaveStatus('dirty');
      clearTimeout(autosaveTimer);
      autosaveTimer = setTimeout(performAutosave, 2500);

      // Save local backup immediately
      try {
        const backup = {
          title: titleInput?.value || '',
          subtitle: subtitleInput?.value || '',
          content: contentTextarea?.value || '',
          category_id: categorySelect?.value || '',
          timestamp: new Date().toISOString()
        };
        localStorage.setItem(draftStorageKey, JSON.stringify(backup));
      } catch (e) {}
    }

    async function performAutosave() {
      if (!isDirty) return;
      const title = titleInput?.value.trim() || '';
      const content = contentTextarea?.value.trim() || '';
      if (!title && !content) return;

      setSaveStatus('saving');
      const csrf = await getCsrfToken();

      const payload = {
        title: title || 'Untitled Draft',
        content: content || 'Draft content in progress...',
        category_id: categorySelect?.value ? parseInt(categorySelect.value, 10) : null,
        remixed_from_post_id: document.getElementById('remixed-from-post-id')?.value ? parseInt(document.getElementById('remixed-from-post-id').value, 10) : null,
        cover_image_path: document.getElementById('post-cover-image-path')?.value || null,
        video_path: document.getElementById('post-video-path')?.value || null,
        scheduled_at: scheduledAtInput?.value || null,
        content_type: document.getElementById('post-content-type')?.value || 'article'
      };

      try {
        let url = isEdit ? `${apiBase}/${postId}` : apiBase;
        let method = isEdit ? 'PATCH' : 'POST';

        const res = await fetch(url, {
          method,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify(payload)
        });

        if (res.ok) {
          const data = await res.json();
          if (!isEdit && data.data?.post?.id) {
            // Update URL and postId without reloading page
            const newId = data.data.post.id;
            window.history.replaceState({}, '', `/posts/${newId}/edit`);
            const hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.id = 'post-id';
            hiddenId.value = newId;
            form.appendChild(hiddenId);
          }
          isDirty = false;
          setSaveStatus('saved');
          persistStudioData();
        } else {
          setSaveStatus('error', 'Autosave error');
        }
      } catch (err) {
        setSaveStatus('error', 'Offline (Saved locally)');
      }
    }

    // Relative save time updater (every 30s)
    setInterval(() => {
      if (!isDirty && saveDot && !saveDot.classList.contains('is-saving') && !saveDot.classList.contains('is-error')) {
        const diffSec = Math.floor((new Date() - lastSavedTime) / 1000);
        if (diffSec < 45) {
          saveText.textContent = 'Saved just now';
        } else if (diffSec < 120) {
          saveText.textContent = 'Saved 1 minute ago';
        } else {
          saveText.textContent = `Saved ${Math.floor(diffSec / 60)} minutes ago`;
        }
      }
    }, 30000);

    // =========================================================================
    // 5. LOCAL DRAFT RECOVERY
    // =========================================================================
    try {
      const stored = localStorage.getItem(draftStorageKey);
      if (stored) {
        const parsed = JSON.parse(stored);
        const serverContent = contentTextarea?.value || '';
        if (parsed.content && parsed.content.length > serverContent.length + 10) {
          const banner = document.getElementById('draft-recovery-banner');
          const timeSpan = document.getElementById('draft-saved-time');
          if (banner && timeSpan) {
            timeSpan.textContent = new Date(parsed.timestamp).toLocaleTimeString();
            banner.style.display = 'flex';

            document.getElementById('restore-draft-btn')?.addEventListener('click', () => {
              if (parsed.title && titleInput) titleInput.value = parsed.title;
              if (parsed.subtitle && subtitleInput) subtitleInput.value = parsed.subtitle;
              if (parsed.content && contentTextarea) contentTextarea.value = parsed.content;
              if (parsed.category_id && categorySelect) categorySelect.value = parsed.category_id;
              banner.style.display = 'none';
              updateMetrics();
              updateOutline();
              markDirty();
            });

            document.getElementById('discard-draft-btn')?.addEventListener('click', () => {
              localStorage.removeItem(draftStorageKey);
              banner.style.display = 'none';
            });
          }
        }
      }
    } catch (e) {}

    // Input listeners for live updates
    [titleInput, subtitleInput, contentTextarea, categorySelect].forEach(el => {
      el?.addEventListener('input', () => {
        updateMetrics();
        updateOutline();
        markDirty();
      });
    });

    // Warn on unload if dirty
    window.addEventListener('beforeunload', (e) => {
      if (isDirty) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes in your Writing Studio draft.';
      }
    });

    // =========================================================================
    // 6. FORMATTING TOOLBAR ENGINE
    // =========================================================================
    function insertMarkdown(prefix, suffix, defaultText = '') {
      if (!contentTextarea) return;
      const start = contentTextarea.selectionStart;
      const end = contentTextarea.selectionEnd;
      const text = contentTextarea.value;
      const selection = text.substring(start, end);
      const content = selection || defaultText;
      const replacement = prefix + content + suffix;

      contentTextarea.value = text.substring(0, start) + replacement + text.substring(end);
      contentTextarea.focus();
      const newCursor = start + prefix.length + content.length;
      contentTextarea.setSelectionRange(newCursor, newCursor);

      updateMetrics();
      updateOutline();
      markDirty();
    }

    document.getElementById('format-bold-btn')?.addEventListener('click', () => insertMarkdown('**', '**', 'bold text'));
    document.getElementById('format-italic-btn')?.addEventListener('click', () => insertMarkdown('*', '*', 'italic text'));
    document.getElementById('format-underline-btn')?.addEventListener('click', () => insertMarkdown('<u>', '</u>', 'underlined text'));
    document.getElementById('format-strike-btn')?.addEventListener('click', () => insertMarkdown('~~', '~~', 'strikethrough text'));
    document.getElementById('format-bullet-btn')?.addEventListener('click', () => insertMarkdown('\n- ', '', 'Bullet item'));
    document.getElementById('format-number-btn')?.addEventListener('click', () => insertMarkdown('\n1. ', '', 'Numbered item'));
    document.getElementById('format-quote-btn')?.addEventListener('click', () => insertMarkdown('\n> ', '', 'Inspiring quote...'));
    document.getElementById('format-code-btn')?.addEventListener('click', () => insertMarkdown('\n```\n', '\n```\n', '// code here'));
    document.getElementById('format-link-btn')?.addEventListener('click', () => {
      const url = prompt('Enter link URL (e.g. https://...):', 'https://');
      if (url) insertMarkdown('[', `](${url})`, 'Link text');
    });

    // Block type selector
    document.getElementById('studio-block-type-select')?.addEventListener('change', (e) => {
      const type = e.target.value;
      if (type === 'h1') insertMarkdown('\n# ', '\n', 'Heading 1');
      else if (type === 'h2') insertMarkdown('\n## ', '\n', 'Heading 2');
      else if (type === 'h3') insertMarkdown('\n### ', '\n', 'Heading 3');
      else if (type === 'quote') insertMarkdown('\n> ', '\n', 'Blockquote');
      else if (type === 'code') insertMarkdown('\n```\n', '\n```\n', 'Code block');
      e.target.value = 'p';
    });

    // Inline Image upload button
    const inlineImageBtn = document.getElementById('format-image-btn');
    const inlineImageInput = document.getElementById('inline-image-file-input');
    // Indent / Outdent Text Handlers
    function indentText() {
      if (!contentTextarea) return;
      const start = contentTextarea.selectionStart;
      const end = contentTextarea.selectionEnd;
      const text = contentTextarea.value;

      const lineStart = text.lastIndexOf('\n', start - 1) + 1;
      let lineEnd = text.indexOf('\n', end);
      if (lineEnd === -1) lineEnd = text.length;

      const selectedBlock = text.substring(lineStart, lineEnd);
      const lines = selectedBlock.split('\n');
      const indented = lines.map(line => '    ' + line).join('\n');

      contentTextarea.value = text.substring(0, lineStart) + indented + text.substring(lineEnd);
      contentTextarea.focus();
      const addedChars = 4 * lines.length;
      contentTextarea.setSelectionRange(start + 4, end + addedChars);

      updateMetrics();
      updateOutline();
      markDirty();
    }

    function outdentText() {
      if (!contentTextarea) return;
      const start = contentTextarea.selectionStart;
      const end = contentTextarea.selectionEnd;
      const text = contentTextarea.value;

      const lineStart = text.lastIndexOf('\n', start - 1) + 1;
      let lineEnd = text.indexOf('\n', end);
      if (lineEnd === -1) lineEnd = text.length;

      const selectedBlock = text.substring(lineStart, lineEnd);
      const lines = selectedBlock.split('\n');
      let totalRemoved = 0;
      let firstLineRemoved = 0;

      const outdented = lines.map((line, idx) => {
        let spacesToRemove = 0;
        if (line.startsWith('\t')) {
          spacesToRemove = 1;
        } else {
          while (spacesToRemove < 4 && line[spacesToRemove] === ' ') {
            spacesToRemove++;
          }
        }
        if (idx === 0) firstLineRemoved = spacesToRemove;
        totalRemoved += spacesToRemove;
        return line.substring(spacesToRemove);
      }).join('\n');

      contentTextarea.value = text.substring(0, lineStart) + outdented + text.substring(lineEnd);
      contentTextarea.focus();
      const newStart = Math.max(lineStart, start - firstLineRemoved);
      const newEnd = Math.max(newStart, end - totalRemoved);
      contentTextarea.setSelectionRange(newStart, newEnd);

      updateMetrics();
      updateOutline();
      markDirty();
    }

    document.getElementById('format-align-center-btn')?.addEventListener('click', indentText);
    document.getElementById('format-align-left-btn')?.addEventListener('click', outdentText);

    // Video Toolbar Trigger & Modal Wiring
    document.getElementById('format-video-btn')?.addEventListener('click', () => {
      window.openInsertVideoModal();
    });

    document.getElementById('confirm-embed-video-btn')?.addEventListener('click', () => {
      const urlInput = document.getElementById('video-embed-url');
      const url = urlInput?.value.trim();
      if (!url) {
        alert('Please enter a YouTube or Vimeo URL.');
        urlInput?.focus();
        return;
      }
      insertMarkdown(`\n[video](${url})\n`, '');
      urlInput.value = '';
      window.closeInsertVideoModal();
    });

    const modalVideoInput = document.getElementById('modal-video-file-input');
    const modalVideoStatus = document.getElementById('modal-video-upload-status');
    modalVideoInput?.addEventListener('change', async (e) => {
      if (!e.target.files || !e.target.files[0]) return;
      const file = e.target.files[0];
      if (file.size > 50 * 1024 * 1024) {
        alert('Video file size must not exceed 50MB.');
        return;
      }
      if (modalVideoStatus) {
        modalVideoStatus.style.display = 'block';
        modalVideoStatus.style.color = '#4f46e5';
        modalVideoStatus.textContent = 'Uploading video file (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB)...';
      }
      setSaveStatus('saving', 'Uploading video...');

      const formData = new FormData();
      formData.append('video', file);
      const oldVideo = document.getElementById('post-video-path')?.value;
      if (oldVideo) formData.append('old_path', oldVideo);
      const csrf = await getCsrfToken();
      formData.append('csrf_token', csrf);

      try {
        const res = await fetch('/posts/upload/video', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: formData
        });
        const data = await res.json();
        if (res.ok && data.data?.path) {
          const videoPath = data.data.path;
          const postVideoInput = document.getElementById('post-video-path');
          if (postVideoInput) postVideoInput.value = videoPath;

          const videoBanner = document.getElementById('studio-video-banner');
          const videoLabel = document.getElementById('studio-video-label');
          if (videoLabel) videoLabel.textContent = file.name;
          if (videoBanner) videoBanner.style.display = 'flex';

          insertMarkdown(`\n[video](${videoPath})\n`, '');
          setSaveStatus('saved', 'Video attached');
          if (modalVideoStatus) {
            modalVideoStatus.textContent = 'Upload complete!';
            modalVideoStatus.style.color = '#10b981';
          }
          setTimeout(() => {
            window.closeInsertVideoModal();
            if (modalVideoStatus) modalVideoStatus.style.display = 'none';
          }, 600);
          markDirty();
        } else {
          const errMsg = data.error?.message || 'Failed to upload video.';
          alert(errMsg);
          if (modalVideoStatus) {
            modalVideoStatus.textContent = errMsg;
            modalVideoStatus.style.color = '#ef4444';
          }
          setSaveStatus('error', 'Upload failed');
        }
      } catch (err) {
        alert('Network error uploading video.');
        setSaveStatus('error', 'Upload failed');
        if (modalVideoStatus) {
          modalVideoStatus.textContent = 'Network error.';
          modalVideoStatus.style.color = '#ef4444';
        }
      } finally {
        modalVideoInput.value = '';
      }
    });

    inlineImageBtn?.addEventListener('click', () => inlineImageInput?.click());

    inlineImageInput?.addEventListener('change', async (e) => {
      if (!e.target.files || !e.target.files[0]) return;
      const file = e.target.files[0];
      if (file.size > 5 * 1024 * 1024) {
        alert('Image must be under 5MB.');
        return;
      }
      setSaveStatus('saving', 'Uploading image...');
      const formData = new FormData();
      formData.append('image', file);
      const csrf = await getCsrfToken();
      formData.append('csrf_token', csrf);

      try {
        const res = await fetch('/posts/upload/inline', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: formData
        });
        const data = await res.json();
        if (res.ok && data.data?.url) {
          insertMarkdown(`\n![${file.name.replace(/[\[\]]/g, '')}](${data.data.url})\n`, '');
          setSaveStatus('saved', 'Image inserted');
        } else {
          alert(data.error?.message || 'Failed to upload image.');
          setSaveStatus('error', 'Upload failed');
        }
      } catch (err) {
        alert('Network error while uploading image.');
        setSaveStatus('error', 'Upload failed');
      } finally {
        inlineImageInput.value = '';
      }
    });

    // =========================================================================
    // 7. CONTEXTUAL AI WRITING ASSISTANT
    // =========================================================================
    const aiPanel = document.getElementById('studio-ai-panel');
    const openAiBtn = document.getElementById('open-ai-panel-btn');
    const closeAiBtn = document.getElementById('close-ai-panel-btn');
    const aiResultCard = document.getElementById('studio-ai-result-card');
    const aiResultContent = document.getElementById('ai-result-content');
    const aiResultSource = document.getElementById('ai-result-source');

    openAiBtn?.addEventListener('click', () => {
      aiPanel.classList.toggle('is-open');
      if (aiPanel.classList.contains('is-open')) {
        document.getElementById('studio-settings-drawer')?.classList.remove('is-open');
      }
    });
    closeAiBtn?.addEventListener('click', () => {
      aiPanel.classList.remove('is-open');
    });

    // Tone Pills
    document.querySelectorAll('.ai-tone-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        document.querySelectorAll('.ai-tone-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        selectedTone = pill.dataset.tone || 'formal';
      });
    });

    // AI Action Cards Click
    document.querySelectorAll('.studio-ai-action-card').forEach(card => {
      card.addEventListener('click', async () => {
        const action = card.dataset.action;
        if (!action) return;

        // Context text: selected text or current paragraph or full content
        const start = contentTextarea.selectionStart;
        const end = contentTextarea.selectionEnd;
        let selectedText = contentTextarea.value.substring(start, end).trim();

        if (!selectedText) {
          selectedText = contentTextarea.value.trim();
        }

        if (!selectedText) {
          alert('Please write or select text in your draft for the AI Assistant to analyze.');
          contentTextarea.focus();
          return;
        }

        const origCardHtml = card.innerHTML;
        card.innerHTML = `<span style="font-size: 0.85rem; color: #6366f1;">✨ Analyzing with AI...</span>`;
        card.style.pointerEvents = 'none';

        const csrf = await getCsrfToken();

        try {
          const res = await fetch('/api/studio/ai-assist', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
              action,
              text: selectedText,
              options: {
                tone: selectedTone,
                title: titleInput?.value || 'Untitled'
              }
            })
          });

          const data = await res.json();
          if (res.ok && data.data?.result) {
            aiResultCard.style.display = 'flex';
            aiResultContent.textContent = data.data.result;
            aiResultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            if (data.data.provider === 'ai_provider') {
              aiResultSource.textContent = '✨ OpenAI (Model)';
              aiResultSource.title = 'Model-generated suggestion';
              aiResultSource.style.color = '#4f46e5';
            } else if (data.data.provider_error) {
              aiResultSource.textContent = '⚡ Local Fallback (Upstream Offline)';
              aiResultSource.title = 'AI provider error: ' + data.data.provider_error;
              aiResultSource.style.color = '#b45309';
            } else {
              aiResultSource.textContent = '⚡ Local Editorial Engine';
              aiResultSource.title = 'Contextual rule-based transformation';
              aiResultSource.style.color = 'var(--studio-muted)';
            }

            // Wire Apply Replace
            document.getElementById('ai-apply-replace-btn').onclick = () => {
              if (start !== end) {
                insertMarkdown('', '', data.data.result);
              } else {
                contentTextarea.value = data.data.result;
                updateMetrics();
                updateOutline();
                markDirty();
              }
              aiResultCard.style.display = 'none';
            };

            // Wire Apply Insert
            document.getElementById('ai-apply-insert-btn').onclick = () => {
              insertMarkdown('\n', '\n', data.data.result);
              aiResultCard.style.display = 'none';
            };

            // Discard
            document.getElementById('ai-discard-btn').onclick = () => {
              aiResultCard.style.display = 'none';
            };
          } else {
            alert(data.error?.message || 'Could not process AI writing request.');
          }
        } catch (err) {
          alert('Network error communicating with AI Assistant.');
        } finally {
          card.innerHTML = origCardHtml;
          card.style.pointerEvents = 'auto';
        }
      });
    });

    // =========================================================================
    // 8. RIGHT SIDEBAR TABS & PERSISTENCE
    // =========================================================================
    const tabOutline = document.getElementById('tab-nav-outline');
    const tabResearch = document.getElementById('tab-nav-research');
    const tabMedia = document.getElementById('tab-nav-media');

    const panelOutline = document.getElementById('tab-panel-outline');
    const panelResearch = document.getElementById('tab-panel-research');
    const panelMedia = document.getElementById('tab-panel-media');

    function switchRightTab(activeTab) {
      [tabOutline, tabResearch, tabMedia].forEach(t => t?.classList.remove('active'));
      [panelOutline, panelResearch, panelMedia].forEach(p => { if (p) p.style.display = 'none'; });

      if (activeTab === 'outline') {
        tabOutline?.classList.add('active');
        if (panelOutline) panelOutline.style.display = 'block';
      } else if (activeTab === 'research') {
        tabResearch?.classList.add('active');
        if (panelResearch) panelResearch.style.display = 'block';
      } else if (activeTab === 'media') {
        tabMedia?.classList.add('active');
        if (panelMedia) panelMedia.style.display = 'block';
      }
    }

    tabOutline?.addEventListener('click', () => switchRightTab('outline'));
    tabResearch?.addEventListener('click', () => switchRightTab('research'));
    tabMedia?.addEventListener('click', () => switchRightTab('media'));

    // Quick Notes checklist interactivity
    const quickNotesList = document.getElementById('studio-quick-notes-list');
    const addNoteBtn = document.getElementById('add-quick-note-btn');
    const noteAddRow = document.getElementById('quick-note-add-row');
    const newNoteInput = document.getElementById('new-quick-note-input');
    const saveNoteBtn = document.getElementById('save-new-quick-note-btn');

    addNoteBtn?.addEventListener('click', () => {
      noteAddRow.style.display = noteAddRow.style.display === 'none' ? 'flex' : 'none';
      newNoteInput?.focus();
    });

    saveNoteBtn?.addEventListener('click', () => {
      const text = newNoteInput?.value.trim();
      if (!text) return;
      const id = 'qn-' + Date.now();
      const item = document.createElement('div');
      item.className = 'studio-note-item';
      item.dataset.id = id;
      item.innerHTML = `
        <input type="checkbox" class="studio-note-checkbox">
        <span class="studio-note-label" style="flex: 1;">${escapeHtml(text)}</span>
        <button type="button" class="studio-note-del-btn" style="background: none; border: none; color: var(--studio-muted); cursor: pointer; font-size: 0.8rem;" title="Delete">×</button>
      `;
      quickNotesList.appendChild(item);
      newNoteInput.value = '';
      noteAddRow.style.display = 'none';
      bindQuickNoteEvents(item);
      persistStudioData();
    });

    function bindQuickNoteEvents(row) {
      const cb = row.querySelector('.studio-note-checkbox');
      const del = row.querySelector('.studio-note-del-btn');
      cb?.addEventListener('change', () => {
        row.classList.toggle('is-done', cb.checked);
        persistStudioData();
      });
      del?.addEventListener('click', () => {
        row.remove();
        persistStudioData();
      });
    }

    document.querySelectorAll('.studio-note-item').forEach(bindQuickNoteEvents);

    // Save Studio Data (Research Notes, Quick Notes, Target Words, Settings)
    async function persistStudioData() {
      const researchNotes = document.getElementById('studio-research-notes')?.value || '';
      const notes = [];
      document.querySelectorAll('.studio-note-item').forEach(row => {
        notes.push({
          id: row.dataset.id || 'qn-' + Math.random(),
          text: row.querySelector('.studio-note-label')?.textContent || '',
          done: !!row.querySelector('.studio-note-checkbox')?.checked
        });
      });

      const settings = {
        theme: studioContainer.dataset.activeTheme || 'lavender',
        font: studioContainer.dataset.activeFont || 'serif',
        textSize: parseInt(document.getElementById('settings-text-size-slider')?.value || '18', 10),
        editorWidth: document.getElementById('settings-width-select')?.value || 'comfortable',
        lineSpacing: document.getElementById('settings-line-spacing-select')?.value || 'relaxed',
        focusDefault: !!document.getElementById('settings-focus-default-toggle')?.checked
      };

      const csrf = await getCsrfToken();
      setSaveStatus('saving');
      try {
        const res = await fetch('/api/studio/data', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({
            draft_key: (postId ? String(postId) : 'new'),
            post_id: postId,
            subtitle: subtitleInput?.value || '',
            research_notes: researchNotes,
            quick_notes: notes,
            target_words: targetWords,
            settings
          })
        });
        if (res.ok) {
          if (!isDirty) {
            setSaveStatus('saved');
          }
        } else {
          setSaveStatus('error', 'Sync failed');
        }
      } catch (e) {
        setSaveStatus('error', 'Offline');
      }
    }

    document.getElementById('studio-research-notes')?.addEventListener('input', () => {
      clearTimeout(window._researchDebounce);
      window._researchDebounce = setTimeout(persistStudioData, 1500);
    });

    // =========================================================================
    // 9. FOCUS MODE — ZEN DISTRACTION FREE
    // =========================================================================
    const focusToggle = document.getElementById('studio-focus-mode-toggle');
    focusToggle?.addEventListener('change', (e) => {
      studioContainer.classList.toggle('is-focus-mode', e.target.checked);
    });

    // ESC shortcut exits focus mode or drawers
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (studioContainer.classList.contains('is-focus-mode')) {
          studioContainer.classList.remove('is-focus-mode');
          if (focusToggle) focusToggle.checked = false;
        }
        document.getElementById('studio-settings-drawer')?.classList.remove('is-open');
        document.getElementById('studio-ai-panel')?.classList.remove('is-open');
        document.getElementById('studio-publish-menu')?.classList.remove('is-open');
        if (typeof closeAllMobileDrawers === 'function') {
          closeAllMobileDrawers();
        } else {
          document.getElementById('studio-left-sidebar')?.classList.remove('is-mobile-open');
          document.getElementById('studio-right-sidebar')?.classList.remove('is-mobile-open');
          document.getElementById('studio-drawer-backdrop')?.classList.remove('is-visible');
        }
      }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        document.getElementById('studio-search-input')?.focus();
      }
    });

    // Fullscreen Toggle
    document.getElementById('studio-fullscreen-btn')?.addEventListener('click', () => {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
      } else {
        document.exitFullscreen().catch(() => {});
      }
    });

    // =========================================================================
    // 10. APPEARANCE & EDITOR SETTINGS
    // =========================================================================
    const settingsDrawer = document.getElementById('studio-settings-drawer');
    document.getElementById('open-settings-drawer-btn')?.addEventListener('click', () => {
      settingsDrawer.classList.toggle('is-open');
      if (settingsDrawer.classList.contains('is-open')) {
        document.getElementById('studio-ai-panel')?.classList.remove('is-open');
      }
    });
    document.getElementById('close-settings-drawer-btn')?.addEventListener('click', () => {
      settingsDrawer.classList.remove('is-open');
    });

    // Theme Swatches
    document.querySelectorAll('.studio-theme-swatch').forEach(swatch => {
      swatch.addEventListener('click', () => {
        const theme = swatch.dataset.theme;
        document.querySelectorAll('.studio-theme-swatch').forEach(s => s.classList.remove('active'));
        swatch.classList.add('active');

        studioContainer.className = studioContainer.className.replace(/studio-theme-\w+/g, '');
        studioContainer.classList.add(`studio-theme-${theme}`);
        studioContainer.dataset.activeTheme = theme;

        const tm = window.ThemeManager || window.BlogCMS?.ThemeManager;
        if (theme === 'midnight') {
          if (tm) {
            tm.setPreference('dark');
          } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.classList.add('theme-dark');
            try { localStorage.setItem('ib_theme_preference', 'dark'); } catch (e) {}
          }
        } else {
          if (tm) {
            tm.setPreference('light');
          } else {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.classList.remove('theme-dark');
            try { localStorage.setItem('ib_theme_preference', 'light'); } catch (e) {}
          }
        }
        syncThemeIcons();
        persistStudioData();
      });
    });

    // Font Select
    document.getElementById('settings-font-select')?.addEventListener('change', (e) => {
      const font = e.target.value;
      studioContainer.className = studioContainer.className.replace(/studio-font-\w+/g, '');
      studioContainer.classList.add(`studio-font-${font}`);
      studioContainer.dataset.activeFont = font;
      persistStudioData();
    });

    // Text Size Slider
    const textSizeSlider = document.getElementById('settings-text-size-slider');
    const textSizeVal = document.getElementById('settings-text-size-val');
    textSizeSlider?.addEventListener('input', (e) => {
      const sz = e.target.value;
      if (textSizeVal) textSizeVal.textContent = `${sz}px`;
      document.getElementById('studio-canvas-inner')?.style.setProperty('--studio-font-size', `${sz}px`);
      contentTextarea.style.fontSize = `${sz}px`;
      persistStudioData();
    });

    // Editor Width
    document.getElementById('settings-width-select')?.addEventListener('change', (e) => {
      const val = e.target.value;
      const widths = { compact: '640px', comfortable: '780px', wide: '980px', full: '100%' };
      document.getElementById('studio-canvas-inner')?.style.setProperty('--studio-editor-max-width', widths[val] || '780px');
      persistStudioData();
    });

    // Line Spacing
    document.getElementById('settings-line-spacing-select')?.addEventListener('change', (e) => {
      const val = e.target.value;
      const heights = { normal: '1.6', relaxed: '1.85', spacious: '2.1' };
      contentTextarea.style.lineHeight = heights[val] || '1.85';
      persistStudioData();
    });

    // =========================================================================
    // 11. THEME TOGGLE BUTTON SYNC
    // =========================================================================
    const themeBtn = document.getElementById('studio-theme-toggle-btn');
    const sunIcon = document.getElementById('studio-theme-icon-sun');
    const moonIcon = document.getElementById('studio-theme-icon-moon');

    function syncThemeIcons() {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      if (sunIcon && moonIcon) {
        sunIcon.style.display = isDark ? 'block' : 'none';
        moonIcon.style.display = isDark ? 'none' : 'block';
      }
    }
    syncThemeIcons();
    window.addEventListener('ib:themechange', syncThemeIcons);

    themeBtn?.addEventListener('click', () => {
      const tm = window.ThemeManager || window.BlogCMS?.ThemeManager;
      if (tm) {
        tm.toggle();
      } else {
        const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', cur);
        if (cur === 'dark') {
          document.documentElement.classList.add('theme-dark');
        } else {
          document.documentElement.classList.remove('theme-dark');
        }
      }
      syncThemeIcons();
    });

    // =========================================================================
    // 12. PUBLISH & UPDATE ACTIONS
    // =========================================================================
    const publishBtn = document.getElementById('studio-main-publish-btn');
    const publishMenuBtn = document.getElementById('studio-publish-dropdown-btn');
    const publishMenu = document.getElementById('studio-publish-menu');

    publishMenuBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      publishMenu.classList.toggle('is-open');
    });

    document.addEventListener('click', () => publishMenu?.classList.remove('is-open'));

    async function submitPost(targetStatus) {
      if (errorAlert) errorAlert.style.display = 'none';

      const title = titleInput?.value.trim();
      const content = contentTextarea?.value.trim();
      const categoryVal = categorySelect?.value;
      const categoryId = categoryVal ? parseInt(categoryVal, 10) : null;

      if (!title) {
        if (errorAlert) {
          errorAlert.textContent = 'Please enter an article title.';
          errorAlert.style.display = 'flex';
        }
        titleInput?.focus();
        return;
      }
      const contentType = document.getElementById('post-content-type')?.value || 'article';
      const coverImagePath = document.getElementById('post-cover-image-path')?.value || null;

      if (!content && contentType !== 'photo') {
        if (errorAlert) {
          errorAlert.textContent = 'Please write article content.';
          errorAlert.style.display = 'flex';
        }
        contentTextarea?.focus();
        return;
      }
      if (contentType === 'photo' && !coverImagePath && !content) {
        if (errorAlert) {
          errorAlert.textContent = 'Please upload a photo/cover image for your photo post.';
          errorAlert.style.display = 'flex';
        }
        return;
      }
      if (targetStatus === 'published' && !categoryId) {
        if (errorAlert) {
          errorAlert.textContent = 'A valid category is required before publishing your article.';
          errorAlert.style.display = 'flex';
        }
        categorySelect?.focus();
        return;
      }

      setSaveStatus('saving');
      const csrf = await getCsrfToken();

      const payload = {
        title,
        content,
        category_id: categoryId,
        remixed_from_post_id: document.getElementById('remixed-from-post-id')?.value ? parseInt(document.getElementById('remixed-from-post-id').value, 10) : null,
        cover_image_path: document.getElementById('post-cover-image-path')?.value || null,
        video_path: document.getElementById('post-video-path')?.value || null,
        scheduled_at: scheduledAtInput?.value || null,
        content_type: document.getElementById('post-content-type')?.value || 'article'
      };

      try {
        let url = isEdit ? `${apiBase}/${postId}` : apiBase;
        let method = isEdit ? 'PATCH' : 'POST';

        const res = await fetch(url, {
          method,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (res.ok) {
          isDirty = false;
          try { localStorage.removeItem(draftStorageKey); } catch (e) {}

          const effectiveId = isEdit ? postId : data.data?.post?.id;
          if (targetStatus === 'published') {
            await fetch(`${apiBase}/${effectiveId}/publish`, {
              method: 'POST',
              headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
            });
            window.location.href = '/posts/manage?published=1';
          } else {
            setSaveStatus('saved', 'Draft saved');
            persistStudioData();
            window.location.href = '/posts/manage?saved=1';
          }
        } else {
          setSaveStatus('error');
          if (errorAlert) {
            errorAlert.textContent = data.error?.message || 'Failed to save post.';
            errorAlert.style.display = 'flex';
          }
        }
      } catch (err) {
        setSaveStatus('error', 'Network error');
      }
    }

    publishBtn?.addEventListener('click', () => submitPost('published'));
    document.getElementById('menu-publish-now-btn')?.addEventListener('click', () => submitPost('published'));
    document.getElementById('menu-save-draft-btn')?.addEventListener('click', () => submitPost('draft'));

    // Schedule Modal
    document.getElementById('menu-schedule-btn')?.addEventListener('click', () => {
      document.getElementById('schedule-modal').style.display = 'flex';
    });
    document.getElementById('confirm-schedule-btn')?.addEventListener('click', () => {
      const dateVal = document.getElementById('modal-scheduled-at')?.value;
      if (dateVal && scheduledAtInput) {
        scheduledAtInput.value = dateVal;
      }
      document.getElementById('schedule-modal').style.display = 'none';
      submitPost('draft');
    });

    // Unpublish
    document.getElementById('menu-unpublish-btn')?.addEventListener('click', async () => {
      if (!confirm('Revert this published article back to draft status?')) return;
      const csrf = await getCsrfToken();
      await fetch(`${apiBase}/${postId}/unpublish`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
      });
      window.location.href = '/posts/manage?unpublished=1';
    });

    // =========================================================================
    // 13. PREVIEW TOGGLE
    // =========================================================================
    const previewToggleBtn = document.getElementById('studio-preview-toggle-btn');
    const previewContainer = document.getElementById('studio-preview-container');
    const previewLoading = document.getElementById('studio-preview-loading');
    const previewRendered = document.getElementById('studio-preview-rendered');
    let isPreviewOpen = false;

    function renderLocalFallbackPreview() {
      const raw = contentTextarea?.value || '';
      const title = titleInput?.value.trim() || 'Untitled Article';
      const cat = categorySelect?.options[categorySelect.selectedIndex]?.text || 'General';
      const coverVal = postCoverInput?.value || '';

      if (!raw.trim()) {
        return '<div style="max-width: 780px; margin: 0 auto; padding: 48px 24px; text-align: center; border: 2px dashed var(--studio-border); border-radius: 20px; background: rgba(99, 102, 241, 0.03);">' +
          '<div style="font-size: 2.5rem; margin-bottom: 12px;">✍️</div>' +
          '<h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-heading); margin-bottom: 8px;">Draft has no content yet</h3>' +
          '<p style="font-size: 0.92rem; color: var(--text-muted); max-width: 440px; margin: 0 auto; line-height: 1.55;">Write your paragraphs in the editor to see your live formatted article preview.</p>' +
          '</div>';
      }

      const paras = raw.split(/\n\n+/).map(p => {
        if (p.startsWith('## ')) {
          return '<h2 style="font-size: 1.5rem; font-weight: 700; margin: 1.5em 0 0.5em; color: var(--text-heading);">' + escapeHtml(p.replace(/^##\s+/, '')) + '</h2>';
        }
        if (p.startsWith('# ')) {
          return '<h1 style="font-size: 1.85rem; font-weight: 800; margin: 1.5em 0 0.5em; color: var(--text-heading);">' + escapeHtml(p.replace(/^#\s+/, '')) + '</h1>';
        }
        return '<p style="margin-bottom: 1.2em; line-height: 1.8; color: var(--text-body);">' + escapeHtml(p).replace(/\n/g, '<br>') + '</p>';
      }).join('');

      let coverHtml = '';
      if (coverVal) {
        coverHtml = '<div style="margin-bottom: 24px; border-radius: 16px; overflow: hidden; max-height: 400px;"><img src="' + escapeHtml(coverVal) + '" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;"></div>';
      }

      return '<div class="studio-live-preview-sheet" style="max-width: 780px; margin: 0 auto; padding: 24px 0;">' +
        coverHtml +
        '<div style="margin-bottom: 12px;"><span class="category-pill" style="font-size: 0.8rem; padding: 4px 12px;">' + escapeHtml(cat) + '</span></div>' +
        '<h1 style="font-size: 2.3rem; font-weight: 800; color: var(--text-heading); margin-bottom: 18px; line-height: 1.25;">' + escapeHtml(title) + '</h1>' +
        '<div class="article-content" style="font-size: 1.05rem; line-height: 1.8;">' + paras + '</div>' +
        '</div>';
    }

    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    previewToggleBtn?.addEventListener('click', async () => {
      isPreviewOpen = !isPreviewOpen;
      const icon = document.getElementById('studio-preview-btn-icon');
      const label = document.getElementById('studio-preview-btn-text');

      if (isPreviewOpen) {
        if (icon) icon.textContent = '✏️';
        if (label) label.textContent = 'Edit';
        if (contentTextarea) contentTextarea.style.display = 'none';
        if (previewContainer) previewContainer.style.display = 'block';
        if (previewLoading) previewLoading.style.display = 'block';
        if (previewRendered) previewRendered.style.display = 'none';

        const csrf = await getCsrfToken();
        try {
          const res = await fetch('/posts/preview', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
              title: titleInput?.value.trim() || 'Untitled Article',
              content: contentTextarea?.value || '',
              category: categorySelect?.options[categorySelect.selectedIndex]?.text || 'General',
              cover_image: postCoverInput?.value || null
            })
          });

          const data = await res.json();
          if (previewLoading) previewLoading.style.display = 'none';
          if (res.ok && data.data && typeof data.data.html === 'string') {
            previewRendered.innerHTML = data.data.html;
            previewRendered.style.display = 'block';
          } else {
            previewRendered.innerHTML = renderLocalFallbackPreview();
            previewRendered.style.display = 'block';
          }
        } catch (e) {
          if (previewLoading) previewLoading.style.display = 'none';
          previewRendered.innerHTML = renderLocalFallbackPreview();
          previewRendered.style.display = 'block';
        }
      } else {
        if (icon) icon.textContent = '👁️';
        if (label) label.textContent = 'Preview';
        if (contentTextarea) {
          contentTextarea.style.display = 'block';
          contentTextarea.focus();
        }
        if (previewContainer) previewContainer.style.display = 'none';
      }
    });

    // =========================================================================
    // 14. COVER & VIDEO UPLOAD HANDLERS
    // =========================================================================
    const coverFileInput = document.getElementById('cover-file-input');
    const postCoverInput = document.getElementById('post-cover-image-path');
    const coverBanner = document.getElementById('studio-cover-banner');
    const coverImg = document.getElementById('studio-cover-img');

    coverFileInput?.addEventListener('change', async (e) => {
      if (!e.target.files || !e.target.files[0]) return;
      const file = e.target.files[0];
      if (file.size > 5 * 1024 * 1024) {
        alert('Cover image size must not exceed 5MB.');
        return;
      }
      setSaveStatus('saving', 'Uploading cover artwork...');
      const formData = new FormData();
      formData.append('cover', file);
      if (postCoverInput.value) formData.append('old_path', postCoverInput.value);
      const csrf = await getCsrfToken();
      formData.append('csrf_token', csrf);

      try {
        const res = await fetch('/posts/upload/cover', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: formData
        });
        const data = await res.json();
        if (res.ok && data.data) {
          postCoverInput.value = data.data.path;
          coverImg.src = data.data.url;
          coverBanner.style.display = 'block';
          setSaveStatus('saved', 'Cover image updated');
          updateMetrics();
          markDirty();
        } else {
          alert(data.error?.message || 'Failed to upload cover image.');
          setSaveStatus('error');
        }
      } catch (err) {
        alert('Network error during cover upload.');
        setSaveStatus('error');
      }
    });

    document.getElementById('remove-cover-banner-btn')?.addEventListener('click', () => {
      postCoverInput.value = '';
      coverBanner.style.display = 'none';
      coverFileInput.value = '';
      updateMetrics();
      markDirty();
    });

    // Remove video banner
    document.getElementById('remove-video-banner-btn')?.addEventListener('click', () => {
      document.getElementById('post-video-path').value = '';
      document.getElementById('studio-video-banner').style.display = 'none';
      markDirty();
    });

    // =========================================================================
    // 12. INDEPENDENT SIDEBAR CONTROLLERS & COLLAPSE LOGIC
    // =========================================================================
    const toggleLeftBtn = document.getElementById('toggle-left-sidebar-btn') || document.getElementById('toggle-mobile-left-btn');
    const toggleRightBtn = document.getElementById('toggle-right-sidebar-btn') || document.getElementById('toggle-mobile-right-btn');
    const leftSidebar = document.getElementById('studio-left-sidebar');
    const rightSidebar = document.getElementById('studio-right-sidebar');
    const drawerBackdrop = document.getElementById('studio-drawer-backdrop');

    function isLeftDrawerMode() {
      return window.innerWidth <= 768;
    }

    function isRightDrawerMode() {
      return window.innerWidth <= 900;
    }

    function updateLeftToggleState() {
      if (!toggleLeftBtn) return;
      if (isLeftDrawerMode()) {
        const isOpen = leftSidebar?.classList.contains('is-mobile-open');
        toggleLeftBtn.classList.toggle('active', !!isOpen);
        toggleLeftBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleLeftBtn.title = isOpen ? 'Close left navigation' : 'Open left navigation';
      } else {
        const isCollapsed = studioContainer.classList.contains('is-left-collapsed');
        toggleLeftBtn.classList.toggle('active', !isCollapsed);
        toggleLeftBtn.setAttribute('aria-expanded', !isCollapsed ? 'true' : 'false');
        toggleLeftBtn.title = isCollapsed ? 'Expand left sidebar (Workspaces & Navigation)' : 'Collapse left sidebar';
      }
    }

    function updateRightToggleState() {
      if (!toggleRightBtn) return;
      if (isRightDrawerMode()) {
        const isOpen = rightSidebar?.classList.contains('is-mobile-open');
        toggleRightBtn.classList.toggle('active', !!isOpen);
        toggleRightBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleRightBtn.title = isOpen ? 'Close outline & tools' : 'Open outline & tools';
      } else {
        const isCollapsed = studioContainer.classList.contains('is-right-collapsed');
        toggleRightBtn.classList.toggle('active', !isCollapsed);
        toggleRightBtn.setAttribute('aria-expanded', !isCollapsed ? 'true' : 'false');
        toggleRightBtn.title = isCollapsed ? 'Expand right sidebar (Outline & Tools)' : 'Collapse right sidebar';
      }
    }

    function toggleLeftSidebar() {
      if (isLeftDrawerMode()) {
        // Drawer Mode (Mobile / Tablet)
        document.getElementById('studio-settings-drawer')?.classList.remove('is-open');
        document.getElementById('studio-ai-panel')?.classList.remove('is-open');
        rightSidebar?.classList.remove('is-mobile-open');

        const willOpen = !leftSidebar?.classList.contains('is-mobile-open');
        leftSidebar?.classList.toggle('is-mobile-open', willOpen);
        if (drawerBackdrop) {
          drawerBackdrop.classList.toggle('is-visible', willOpen);
        }
        updateLeftToggleState();
        updateRightToggleState();
      } else {
        // In-Flow Desktop Mode
        const willCollapse = !studioContainer.classList.contains('is-left-collapsed');
        studioContainer.classList.toggle('is-left-collapsed', willCollapse);
        try {
          localStorage.setItem('infinity_studio_sidebar_left', willCollapse ? 'collapsed' : 'open');
        } catch (e) {}
        updateLeftToggleState();
      }
    }

    function toggleRightSidebar() {
      if (isRightDrawerMode()) {
        // Drawer Mode (Mobile / Tablet)
        document.getElementById('studio-settings-drawer')?.classList.remove('is-open');
        document.getElementById('studio-ai-panel')?.classList.remove('is-open');
        leftSidebar?.classList.remove('is-mobile-open');

        const willOpen = !rightSidebar?.classList.contains('is-mobile-open');
        rightSidebar?.classList.toggle('is-mobile-open', willOpen);
        if (drawerBackdrop) {
          drawerBackdrop.classList.toggle('is-visible', willOpen);
        }
        updateRightToggleState();
        updateLeftToggleState();
      } else {
        // In-Flow Desktop Mode
        const willCollapse = !studioContainer.classList.contains('is-right-collapsed');
        studioContainer.classList.toggle('is-right-collapsed', willCollapse);
        try {
          localStorage.setItem('infinity_studio_sidebar_right', willCollapse ? 'collapsed' : 'open');
        } catch (e) {}
        updateRightToggleState();
      }
    }

    function closeAllMobileDrawers() {
      leftSidebar?.classList.remove('is-mobile-open');
      rightSidebar?.classList.remove('is-mobile-open');
      if (drawerBackdrop) {
        drawerBackdrop.classList.remove('is-visible');
      }
      updateLeftToggleState();
      updateRightToggleState();
    }

    // Attach listeners
    toggleLeftBtn?.addEventListener('click', toggleLeftSidebar);
    toggleRightBtn?.addEventListener('click', toggleRightSidebar);
    drawerBackdrop?.addEventListener('click', closeAllMobileDrawers);

    // Restore desktop sidebar layout preferences
    try {
      if (window.innerWidth > 768) {
        const savedLeft = localStorage.getItem('infinity_studio_sidebar_left');
        if (savedLeft === 'collapsed') {
          studioContainer.classList.add('is-left-collapsed');
        }
      }
      if (window.innerWidth > 900) {
        const savedRight = localStorage.getItem('infinity_studio_sidebar_right');
        if (savedRight === 'collapsed') {
          studioContainer.classList.add('is-right-collapsed');
        }
      }
    } catch (e) {}

    updateLeftToggleState();
    updateRightToggleState();

    // Responsive resize synchronizer
    window.addEventListener('resize', () => {
      if (!isLeftDrawerMode() && leftSidebar?.classList.contains('is-mobile-open')) {
        leftSidebar.classList.remove('is-mobile-open');
      }
      if (!isRightDrawerMode() && rightSidebar?.classList.contains('is-mobile-open')) {
        rightSidebar.classList.remove('is-mobile-open');
      }
      if (!isLeftDrawerMode() && !isRightDrawerMode() && drawerBackdrop?.classList.contains('is-visible')) {
        drawerBackdrop.classList.remove('is-visible');
      }
      updateLeftToggleState();
      updateRightToggleState();
    });

    // Global Search Focus
    document.getElementById('studio-search-input')?.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      document.querySelectorAll('.studio-outline-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? 'flex' : 'none';
      });
    });

    // Escape helper
    function escapeHtml(str) {
      return str.replace(/[&<>'"]/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
      }[tag] || tag));
    }

    // =========================================================================
    // 15. LEFT SIDEBAR NAVIGATION TOOLS & WORKSPACES
    // =========================================================================
    const navItemWrite = document.getElementById('nav-item-write');
    const navItemIdeas = document.getElementById('nav-item-ideas');
    const navItemDrafts = document.getElementById('nav-item-drafts');
    const navItemMedia = document.getElementById('nav-item-media');
    const navItemPlanner = document.getElementById('nav-item-planner');
    const navItemInsights = document.getElementById('nav-item-insights');

    function setActiveNav(btn) {
      document.querySelectorAll('.studio-nav-item').forEach(item => item.classList.remove('active'));
      btn?.classList.add('active');
    }

    navItemWrite?.addEventListener('click', () => {
      setActiveNav(navItemWrite);
      contentTextarea?.focus();
    });

    navItemIdeas?.addEventListener('click', () => {
      setActiveNav(navItemIdeas);
      studioContainer.classList.remove('is-right-collapsed');
      updateRightToggleState();
      switchRightTab('research');
      document.getElementById('studio-research-notes')?.focus();
    });

    navItemDrafts?.addEventListener('click', () => {
      setActiveNav(navItemDrafts);
      if (isDirty) {
        if (confirm('You have unsaved changes. Save draft before leaving?')) {
          submitPost('draft');
          return;
        }
      }
      window.location.href = '/posts/manage?status=draft';
    });

    navItemMedia?.addEventListener('click', () => {
      setActiveNav(navItemMedia);
      studioContainer.classList.remove('is-right-collapsed');
      updateRightToggleState();
      switchRightTab('media');
    });

    navItemPlanner?.addEventListener('click', () => {
      setActiveNav(navItemPlanner);
      document.getElementById('schedule-modal').style.display = 'flex';
    });

    navItemInsights?.addEventListener('click', () => {
      setActiveNav(navItemInsights);
      studioContainer.classList.remove('is-right-collapsed');
      updateRightToggleState();
      switchRightTab('outline');
      const seoCard = document.getElementById('seo-score-fraction');
      if (seoCard) {
        seoCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });

    // Workspaces Interactivity
    const addWorkspaceBtn = document.getElementById('add-workspace-btn');
    const workspacesList = document.querySelector('.studio-workspaces-list');

    function bindWorkspaceItem(item) {
      item.addEventListener('click', (e) => {
        if (item.tagName.toLowerCase() === 'a') return;
        document.querySelectorAll('.studio-workspace-item').forEach(w => w.classList.remove('active'));
        item.classList.add('active');
        const name = item.querySelector('span:last-child')?.textContent || 'Workspace';
        setSaveStatus('saved', `Workspace: ${name}`);
      });
    }

    document.querySelectorAll('.studio-workspace-item').forEach(bindWorkspaceItem);

    const workspaceColors = ['#ec4899', '#06b6d4', '#84cc16', '#eab308', '#f97316', '#a855f7'];
    addWorkspaceBtn?.addEventListener('click', () => {
      const name = prompt('Enter new workspace name:');
      if (!name || !name.trim()) return;
      const color = workspaceColors[Math.floor(Math.random() * workspaceColors.length)];
      const div = document.createElement('div');
      div.className = 'studio-workspace-item active';
      div.dataset.workspace = name.toLowerCase().replace(/[^a-z0-9]/g, '-');
      div.innerHTML = `<span class="studio-workspace-dot" style="background: ${color};"></span><span>${escapeHtml(name.trim())}</span>`;

      document.querySelectorAll('.studio-workspace-item').forEach(w => w.classList.remove('active'));
      const allLink = workspacesList?.querySelector('a.studio-workspace-item');
      if (allLink && workspacesList) {
        workspacesList.insertBefore(div, allLink);
      } else if (workspacesList) {
        workspacesList.appendChild(div);
      }
      bindWorkspaceItem(div);
      setSaveStatus('saved', `Created workspace: ${name.trim()}`);
    });

    // Initial metrics & outline pass
    updateMetrics();
    updateOutline();
  });

  // Global video modal helpers
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
})();
