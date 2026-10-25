<?php
declare(strict_types=1);
/**
 * In-Browser eBook Reader View (PDF & EPUB)
 */

$id = (int) $ebook['id'];
$title = htmlspecialchars($ebook['title'], ENT_QUOTES, 'UTF-8');
$author = htmlspecialchars($ebook['author_name'], ENT_QUOTES, 'UTF-8');
$format = strtolower($ebook['format']);
$initialPage = !empty($readingProgress['last_page']) ? (int) $readingProgress['last_page'] : 1;
$initialChapter = !empty($readingProgress['last_chapter']) ? (int) $readingProgress['last_chapter'] : 0;
?>
<!DOCTYPE html>
<html lang="en" data-reader-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Reading: <?= $title ?> — Infinity Blog Reader</title>
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <style>
    :root {
      --reader-bg: #f8fafc;
      --reader-surface: #ffffff;
      --reader-text: #0f172a;
      --reader-border: #e2e8f0;
      --reader-muted: #64748b;
      --reader-accent: #2563eb;
      --reader-font-size: 18px;
      --reader-line-height: 1.75;
      --reader-font-family: 'Source Serif 4', Georgia, serif;
    }

    [data-reader-theme="dark"] {
      --reader-bg: #090d16;
      --reader-surface: #111827;
      --reader-text: #f1f5f9;
      --reader-border: #1f2937;
      --reader-muted: #94a3b8;
      --reader-accent: #3b82f6;
    }

    [data-reader-theme="sepia"] {
      --reader-bg: #f4ecd8;
      --reader-surface: #fbf0d9;
      --reader-text: #433422;
      --reader-border: #e6dac3;
      --reader-muted: #7d6b53;
      --reader-accent: #92400e;
    }

    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--reader-bg);
      color: var(--reader-text);
      transition: background 0.25s ease, color 0.25s ease;
    }

    /* Fixed Top Control Bar */
    .reader-toolbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 60px;
      background: var(--reader-surface);
      border-bottom: 1px solid var(--reader-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 16px;
      z-index: 100;
      gap: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .toolbar-group {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .reader-btn {
      background: transparent;
      border: 1px solid var(--reader-border);
      color: var(--reader-text);
      border-radius: 8px;
      padding: 7px 12px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      transition: all 0.15s ease;
    }
    .reader-btn:hover:not(:disabled) {
      background: rgba(0,0,0,0.05);
      border-color: var(--reader-muted);
    }
    [data-reader-theme="dark"] .reader-btn:hover:not(:disabled) {
      background: rgba(255,255,255,0.08);
    }
    .reader-btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }

    .book-title-header {
      font-weight: 700;
      font-size: 0.95rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 320px;
    }
    .book-author-header {
      font-size: 0.8rem;
      color: var(--reader-muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 200px;
    }

    /* Reading Progress Top Bar */
    .reading-progress-track {
      position: fixed;
      top: 59px;
      left: 0;
      right: 0;
      height: 3px;
      background: rgba(0,0,0,0.08);
      z-index: 101;
    }
    .reading-progress-fill {
      height: 100%;
      width: 0%;
      background: var(--reader-accent);
      transition: width 0.3s ease;
    }

    /* Main Viewport Workspace */
    .reader-workspace {
      position: fixed;
      top: 62px;
      bottom: 0;
      left: 0;
      right: 0;
      overflow-y: auto;
      overflow-x: hidden;
      display: flex;
      justify-content: center;
      padding: 24px 16px;
      -webkit-overflow-scrolling: touch;
    }

    /* PDF Canvas View */
    .pdf-render-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      max-width: 100%;
    }
    #pdfCanvas {
      box-shadow: 0 8px 30px rgba(0,0,0,0.12);
      border-radius: 4px;
      background: #ffffff;
      max-width: 100%;
      height: auto !important;
      transition: filter 0.25s ease;
    }
    [data-reader-theme="dark"] #pdfCanvas {
      filter: invert(0.9) hue-rotate(180deg) contrast(0.95);
    }
    [data-reader-theme="sepia"] #pdfCanvas {
      filter: sepia(0.4) contrast(0.95);
    }

    /* EPUB Chapter Document View */
    .epub-render-container {
      width: 100%;
      max-width: 760px;
      background: var(--reader-surface);
      border: 1px solid var(--reader-border);
      border-radius: 16px;
      padding: 48px 56px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      font-family: var(--reader-font-family);
      font-size: var(--reader-font-size);
      line-height: var(--reader-line-height);
      color: var(--reader-text);
      min-height: 80vh;
      margin-bottom: 60px;
    }
    .epub-render-container img {
      max-width: 100%;
      height: auto;
      border-radius: 6px;
      display: block;
      margin: 20px auto;
    }
    .epub-render-container h1, .epub-render-container h2, .epub-render-container h3 {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--reader-text);
      margin-top: 1.5em;
      margin-bottom: 0.6em;
      line-height: 1.3;
    }
    .epub-render-container p {
      margin-top: 0;
      margin-bottom: 1.3em;
    }

    /* EPUB Navigation Drawer */
    .toc-drawer {
      position: fixed;
      top: 60px;
      bottom: 0;
      left: -320px;
      width: 300px;
      background: var(--reader-surface);
      border-right: 1px solid var(--reader-border);
      z-index: 150;
      transition: left 0.25s ease;
      overflow-y: auto;
      padding: 20px 16px;
      box-shadow: 4px 0 16px rgba(0,0,0,0.1);
    }
    .toc-drawer.open {
      left: 0;
    }
    .toc-item {
      display: block;
      padding: 10px 12px;
      border-radius: 8px;
      color: var(--reader-text);
      text-decoration: none;
      font-size: 0.88rem;
      font-weight: 500;
      margin-bottom: 4px;
      cursor: pointer;
    }
    .toc-item:hover, .toc-item.active {
      background: rgba(37,99,235,0.08);
      color: var(--reader-accent);
      font-weight: 700;
    }

    /* Mobile Adaptations */
    @media (max-width: 768px) {
      .reader-toolbar {
        height: 52px;
        padding: 0 10px;
      }
      .book-author-header { display: none; }
      .book-title-header { max-width: 160px; font-size: 0.85rem; }
      .epub-render-container {
        padding: 28px 20px;
        border-radius: 0;
        border-left: none;
        border-right: none;
      }
      .hide-mobile { display: none !important; }
    }
  </style>
</head>
<body>

  <!-- Top Fixed Toolbar -->
  <header class="reader-toolbar" role="banner">
    <!-- Left: Back & Title -->
    <div class="toolbar-group">
      <a href="/ebooks/<?= $id ?>" class="reader-btn" title="Back to Book Details">
        <span>&larr;</span> <span class="hide-mobile">Details</span>
      </a>
      
      <?php if ($format === 'epub'): ?>
        <button type="button" id="tocToggleBtn" class="reader-btn" title="Table of Contents">
          <span>📑</span> <span class="hide-mobile">Contents</span>
        </button>
      <?php endif; ?>

      <div style="margin-left: 6px;">
        <div class="book-title-header"><?= $title ?></div>
        <div class="book-author-header">By <?= $author ?></div>
      </div>
    </div>

    <!-- Center: Page / Chapter Navigation -->
    <div class="toolbar-group">
      <?php if ($format === 'pdf'): ?>
        <button type="button" id="pdfPrevPage" class="reader-btn" title="Previous Page">◀</button>
        <span style="font-size: 0.88rem; font-weight: 600;">
          <input type="number" id="pdfPageInput" value="<?= $initialPage ?>" min="1" style="width: 50px; text-align: center; border-radius: 6px; border: 1px solid var(--reader-border); background: var(--reader-surface); color: var(--reader-text); padding: 4px;">
          / <span id="pdfPageCount">--</span>
        </span>
        <button type="button" id="pdfNextPage" class="reader-btn" title="Next Page">▶</button>
      <?php else: ?>
        <button type="button" id="epubPrevChapter" class="reader-btn" title="Previous Chapter">◀</button>
        <span style="font-size: 0.88rem; font-weight: 600;" id="epubChapterIndicator">
          Chapter <span id="curChapterNum">1</span> / <span id="totalChapterNum">--</span>
        </span>
        <button type="button" id="epubNextChapter" class="reader-btn" title="Next Chapter">▶</button>
      <?php endif; ?>
    </div>

    <!-- Right: Theme, Font, Zoom, Fullscreen -->
    <div class="toolbar-group">
      <!-- Zoom Controls for PDF -->
      <?php if ($format === 'pdf'): ?>
        <button type="button" id="pdfZoomOut" class="reader-btn hide-mobile" title="Zoom Out">−</button>
        <span id="pdfZoomLevel" class="hide-mobile" style="font-size: 0.82rem; font-weight: 600; min-width: 42px; text-align: center;">100%</span>
        <button type="button" id="pdfZoomIn" class="reader-btn hide-mobile" title="Zoom In">+</button>
      <?php else: ?>
        <!-- Font Size Controls for EPUB -->
        <button type="button" id="epubFontSmaller" class="reader-btn" title="Smaller text">A−</button>
        <button type="button" id="epubFontLarger" class="reader-btn" title="Larger text">A+</button>
      <?php endif; ?>

      <!-- Theme Selector Dropdown / Cycle -->
      <select id="readerThemeSelect" class="reader-btn" style="padding: 6px 10px;" title="Switch reading theme">
        <option value="light">☀️ Light</option>
        <option value="sepia">📜 Sepia</option>
        <option value="dark">🌙 Dark</option>
      </select>

      <!-- Fullscreen Toggle -->
      <button type="button" id="fullscreenToggleBtn" class="reader-btn hide-mobile" title="Toggle Fullscreen">
        ⛶
      </button>
    </div>
  </header>

  <!-- Progress Bar Line -->
  <div class="reading-progress-track">
    <div id="readingProgressFill" class="reading-progress-fill"></div>
  </div>

  <!-- TOC Drawer for EPUB -->
  <?php if ($format === 'epub'): ?>
    <aside id="tocDrawer" class="toc-drawer" aria-label="Table of Contents">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--reader-border); padding-bottom: 10px;">
        <strong style="font-size: 1rem;">Table of Contents</strong>
        <button type="button" id="tocCloseBtn" class="reader-btn" style="padding: 4px 8px;">✕</button>
      </div>
      <div id="tocItemsList">
        <!-- Dynamically populated -->
      </div>
    </aside>
  <?php endif; ?>

  <!-- Main Viewport Workspace -->
  <main class="reader-workspace" id="readerWorkspace">
    <?php if ($format === 'pdf'): ?>
      <div class="pdf-render-container">
        <div id="pdfLoadingNotice" style="padding: 40px; font-weight: 600; color: var(--reader-muted); text-align: center;">
          <div style="font-size: 2rem; margin-bottom: 10px;">📖</div>
          Loading document pages...
        </div>
        <canvas id="pdfCanvas" style="display: none;"></canvas>
      </div>
    <?php else: ?>
      <article class="epub-render-container" id="epubContentArea">
        <div id="epubLoadingNotice" style="padding: 60px 20px; font-weight: 600; color: var(--reader-muted); text-align: center;">
          <div style="font-size: 2.5rem; margin-bottom: 12px;">📚</div>
          Loading eBook chapters...
        </div>
      </article>
    <?php endif; ?>
  </main>

  <!-- Include PDF.js Vendor Script (if PDF) -->
  <?php if ($format === 'pdf'): ?>
    <script src="/assets/js/vendor/pdf.min.js"></script>
  <?php endif; ?>

  <script>
  (() => {
    const ebookId = <?= $id ?>;
    const format = '<?= $format ?>';
    const csrfToken = '<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>';

    // Theme Switcher
    const themeSelect = document.getElementById('readerThemeSelect');
    const storedTheme = localStorage.getItem('infinity_reader_theme') || 'light';
    document.documentElement.setAttribute('data-reader-theme', storedTheme);
    themeSelect.value = storedTheme;

    themeSelect.addEventListener('change', () => {
      const theme = themeSelect.value;
      document.documentElement.setAttribute('data-reader-theme', theme);
      localStorage.setItem('infinity_reader_theme', theme);
    });

    // Fullscreen Toggle
    const fsBtn = document.getElementById('fullscreenToggleBtn');
    if (fsBtn) {
      fsBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen().catch(() => {});
        } else {
          document.exitFullscreen().catch(() => {});
        }
      });
    }

    // Reading Progress Saver
    let progressTimer = null;
    function scheduleProgressSave(lastPage, lastChapter, percentage) {
      clearTimeout(progressTimer);
      progressTimer = setTimeout(() => {
        fetch('/api/ebooks/' + ebookId + '/progress', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            last_page: lastPage,
            last_chapter: lastChapter,
            percentage: percentage
          })
        }).catch(() => {});
      }, 1500);
    }

    // ==========================================
    // PDF ENGINE (Canvas + PDF.js)
    // ==========================================
    if (format === 'pdf') {
      let pdfDoc = null;
      let pageNum = <?= $initialPage ?>;
      let pageRendering = false;
      let pageNumPending = null;
      let scale = 1.25;

      const canvas = document.getElementById('pdfCanvas');
      const ctx = canvas.getContext('2d');
      const loadingNotice = document.getElementById('pdfLoadingNotice');
      const pageInput = document.getElementById('pdfPageInput');
      const pageCountEl = document.getElementById('pdfPageCount');
      const prevBtn = document.getElementById('pdfPrevPage');
      const nextBtn = document.getElementById('pdfNextPage');
      const zoomInBtn = document.getElementById('pdfZoomIn');
      const zoomOutBtn = document.getElementById('pdfZoomOut');
      const zoomLevelEl = document.getElementById('pdfZoomLevel');
      const progressFill = document.getElementById('readingProgressFill');

      if (window.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/js/vendor/pdf.worker.min.js';

        pdfjsLib.getDocument('/ebooks/' + ebookId + '/read-file').promise.then((pdf) => {
          pdfDoc = pdf;
          pageCountEl.textContent = pdf.numPages;
          pageInput.max = pdf.numPages;
          loadingNotice.style.display = 'none';
          canvas.style.display = 'block';

          if (pageNum > pdf.numPages) pageNum = 1;
          renderPage(pageNum);
        }).catch((err) => {
          console.error('PDF loading error:', err);
          loadingNotice.innerHTML = `
            <div style="color: #dc2626; font-size: 1.1rem; margin-bottom: 12px;">Failed to render PDF using canvas viewer.</div>
            <a href="/ebooks/${ebookId}/read-file" target="_blank" class="reader-btn" style="display: inline-block;">
              Open in Native PDF Viewer &rarr;
            </a>
          `;
        });
      }

      function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then((page) => {
          const viewport = page.getViewport({ scale });
          canvas.height = viewport.height;
          canvas.width = viewport.width;

          const renderContext = {
            canvasContext: ctx,
            viewport: viewport
          };
          const renderTask = page.render(renderContext);

          renderTask.promise.then(() => {
            pageRendering = false;
            if (pageNumPending !== null) {
              renderPage(pageNumPending);
              pageNumPending = null;
            }
          });
        });

        pageInput.value = num;
        prevBtn.disabled = (num <= 1);
        nextBtn.disabled = (num >= pdfDoc?.numPages);

        const pct = Math.round((num / (pdfDoc?.numPages || 1)) * 100);
        progressFill.style.width = pct + '%';
        scheduleProgressSave(num, null, pct);
      }

      function queueRenderPage(num) {
        if (pageRendering) {
          pageNumPending = num;
        } else {
          renderPage(num);
        }
      }

      prevBtn.addEventListener('click', () => {
        if (pageNum <= 1) return;
        pageNum--;
        queueRenderPage(pageNum);
      });

      nextBtn.addEventListener('click', () => {
        if (pageNum >= pdfDoc.numPages) return;
        pageNum++;
        queueRenderPage(pageNum);
      });

      pageInput.addEventListener('change', () => {
        const val = parseInt(pageInput.value, 10);
        if (val >= 1 && val <= pdfDoc.numPages) {
          pageNum = val;
          queueRenderPage(pageNum);
        } else {
          pageInput.value = pageNum;
        }
      });

      zoomInBtn?.addEventListener('click', () => {
        if (scale < 3.0) {
          scale += 0.25;
          zoomLevelEl.textContent = Math.round(scale * 80) + '%';
          queueRenderPage(pageNum);
        }
      });

      zoomOutBtn?.addEventListener('click', () => {
        if (scale > 0.6) {
          scale -= 0.25;
          zoomLevelEl.textContent = Math.round(scale * 80) + '%';
          queueRenderPage(pageNum);
        }
      });

      // Keyboard arrow shortcuts
      window.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT') return;
        if (e.key === 'ArrowRight' || e.key === 'PageDown') {
          if (pdfDoc && pageNum < pdfDoc.numPages) { pageNum++; queueRenderPage(pageNum); }
        } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
          if (pdfDoc && pageNum > 1) { pageNum--; queueRenderPage(pageNum); }
        }
      });
    }

    // ==========================================
    // EPUB ENGINE (Chapter Streamer)
    // ==========================================
    if (format === 'epub') {
      let currentChapter = <?= $initialChapter ?>;
      let totalChapters = 1;
      let toc = [];

      const contentArea = document.getElementById('epubContentArea');
      const prevChapBtn = document.getElementById('epubPrevChapter');
      const nextChapBtn = document.getElementById('epubNextChapter');
      const curChapEl = document.getElementById('curChapterNum');
      const totChapEl = document.getElementById('totalChapterNum');
      const tocDrawer = document.getElementById('tocDrawer');
      const tocToggle = document.getElementById('tocToggleBtn');
      const tocClose = document.getElementById('tocCloseBtn');
      const tocList = document.getElementById('tocItemsList');
      const progressFill = document.getElementById('readingProgressFill');

      // Font size adjustment for EPUB
      let currentFontSize = 18;
      document.getElementById('epubFontLarger')?.addEventListener('click', () => {
        if (currentFontSize < 32) {
          currentFontSize += 2;
          contentArea.style.fontSize = currentFontSize + 'px';
        }
      });
      document.getElementById('epubFontSmaller')?.addEventListener('click', () => {
        if (currentFontSize > 14) {
          currentFontSize -= 2;
          contentArea.style.fontSize = currentFontSize + 'px';
        }
      });

      // TOC Toggle
      tocToggle?.addEventListener('click', () => tocDrawer.classList.toggle('open'));
      tocClose?.addEventListener('click', () => tocDrawer.classList.remove('open'));

      // Fetch EPUB Manifest
      fetch('/ebooks/' + ebookId + '/manifest')
        .then(r => r.json())
        .then(data => {
          totalChapters = data.spine ? data.spine.length : 1;
          totChapEl.textContent = totalChapters;
          toc = data.toc || [];

          // Render TOC drawer items
          if (toc.length > 0) {
            tocList.innerHTML = toc.map((item, idx) => `
              <div class="toc-item" data-chapter="${idx}">
                ${item.label || 'Chapter ' + (idx + 1)}
              </div>
            `).join('');

            tocList.querySelectorAll('.toc-item').forEach(el => {
              el.addEventListener('click', () => {
                const chap = parseInt(el.getAttribute('data-chapter'), 10);
                loadChapter(chap);
                tocDrawer.classList.remove('open');
              });
            });
          }

          loadChapter(currentChapter);
        })
        .catch(() => {
          contentArea.innerHTML = '<div style="color: #dc2626; padding: 40px; text-align: center;">Failed to load EPUB manifest.</div>';
        });

      function loadChapter(index) {
        if (index < 0) index = 0;
        if (index >= totalChapters) index = totalChapters - 1;
        currentChapter = index;

        contentArea.innerHTML = '<div style="padding: 60px 20px; text-align: center; color: var(--reader-muted);">Loading chapter...</div>';

        fetch('/ebooks/' + ebookId + '/chapter/' + index)
          .then(r => r.json())
          .then(data => {
            if (data.html) {
              contentArea.innerHTML = data.html;
              document.getElementById('readerWorkspace').scrollTop = 0;
            } else {
              contentArea.innerHTML = '<div style="color: #dc2626; padding: 40px;">Chapter content could not be rendered.</div>';
            }

            curChapEl.textContent = (currentChapter + 1);
            prevChapBtn.disabled = (currentChapter <= 0);
            nextChapBtn.disabled = (currentChapter >= totalChapters - 1);

            // Update TOC active state
            tocList.querySelectorAll('.toc-item').forEach((el, idx) => {
              el.classList.toggle('active', idx === currentChapter);
            });

            // Update Progress
            const pct = Math.round(((currentChapter + 1) / totalChapters) * 100);
            progressFill.style.width = pct + '%';
            scheduleProgressSave(null, currentChapter, pct);
          })
          .catch(() => {
            contentArea.innerHTML = '<div style="color: #dc2626; padding: 40px;">Network error loading chapter.</div>';
          });
      }

      prevChapBtn.addEventListener('click', () => {
        if (currentChapter > 0) loadChapter(currentChapter - 1);
      });

      nextChapBtn.addEventListener('click', () => {
        if (currentChapter < totalChapters - 1) loadChapter(currentChapter + 1);
      });
    }
  })();
  </script>
</body>
</html>
