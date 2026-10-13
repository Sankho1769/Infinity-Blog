<!-- Screen 8: Single Post Page (Public View) -->
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
  <a href="/">Home</a>
  <span>›</span>
  <?php if (!empty($post['category_name'])): ?>
    <a href="/?category=<?= urlencode($post['category_slug'] ?? '') ?>">
      <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
    </a>
    <span>›</span>
  <?php endif; ?>
  <span style="color: var(--text-dark); font-weight: 500;">
    <?= htmlspecialchars(mb_substr($post['title'], 0, 30) . (mb_strlen($post['title']) > 30 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?>
  </span>
</nav>

<div class="article-detail-layout">
  <!-- Main Article Card -->
  <article class="glass-panel article-main-card">
    <header class="article-header">
      <?php if (!empty($post['category_name'])): ?>
        <span class="badge badge-category"><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
        <span class="badge badge-published">Published Article</span>
      <?php endif; ?>

      <h1 class="article-title-large">
        <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
      </h1>

      <?php if (!empty($post['remixed_from_post_id'])): ?>
        <div class="remix-attribution-banner" style="margin: 12px 0 16px; padding: 10px 16px; background: rgba(238, 242, 255, 0.7); border: 1px solid rgba(199, 210, 254, 0.85); border-radius: var(--radius-md); backdrop-filter: var(--glass-blur-sm); display: flex; align-items: center; gap: 8px; font-size: 0.88rem; color: #3730a3;">
          <span style="font-size: 1.1rem;">🔀</span>
          <div>
            <span>Remixed from </span>
            <?php if (!empty($post['remixed_from_is_published'])): ?>
              <a href="/article/<?= (int) $post['remixed_from_post_id'] ?>" style="color: #4338ca; font-weight: 700; text-decoration: underline;">
                <?= htmlspecialchars($post['remixed_from_title'] ?? 'Original Article', ENT_QUOTES, 'UTF-8') ?>
              </a>
              <span> by </span>
              <?php if (!empty($post['remixed_from_author_id'])): ?>
                <a href="/author/<?= (int) $post['remixed_from_author_id'] ?>" style="color: #4338ca; font-weight: 600; text-decoration: underline;">
                  <?= htmlspecialchars($post['remixed_from_author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php else: ?>
                <strong><?= htmlspecialchars($post['remixed_from_author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?></strong>
              <?php endif; ?>
            <?php else: ?>
              <em>an earlier published article (source unavailable or private)</em>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="article-meta-row">
        <div class="article-author-info">
          <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" class="user-avatar" style="width: 36px; height: 36px; font-size: 0.95rem; text-decoration: none;" title="View Author Profile">
            <?= strtoupper(substr($post['author_name'] ?? 'Cristine Palmer', 0, 1)) ?>
          </a>
          <div>
            <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" style="font-weight: 700; color: var(--text-dark); text-decoration: none;" title="View Author Profile">
              <?= htmlspecialchars($post['author_name'] ?? 'Cristine Palmer', ENT_QUOTES, 'UTF-8') ?>
            </a>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
              <?= date('F j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?> ·
              <?php
                $wordCount = str_word_count(strip_tags($post['content'] ?? ''));
                $minutes = max(1, (int) ceil($wordCount / 200));
                echo $minutes . ' min read';
              ?>
            </div>
          </div>
        </div>

        <div style="margin-left: auto; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
          <?php
            $remixUrl = !empty($currentUser)
              ? '/posts/new?remix=' . (int) $post['id']
              : '/login?redirect=' . urlencode('/posts/new?remix=' . (int) $post['id']);
          ?>
          <a href="<?= htmlspecialchars($remixUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-sm btn-pill" title="Start a new article based on this post">
            <span>🔀</span> Remix Article
          </a>

          <?php if (!empty($currentUser) && ($currentUser['id'] === $post['author_id'] || $currentUser['role'] === 'admin')): ?>
            <a href="/posts/<?= (int) $post['id'] ?>/edit" class="btn btn-secondary btn-sm btn-pill">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
              </svg>
              Edit Post
            </a>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <!-- Featured Article Banner Image -->
    <?php
      $articleImage = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, 0);
    ?>
    <div class="article-featured-banner">
      <img src="<?= htmlspecialchars($articleImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    </div>

    <!-- Reader Experience Toolbar (Reader Mode & Audio Experience) -->
    <div class="article-reader-toolbar">
      <div class="reader-mode-tabs" role="tablist" aria-label="Reading Experience Options">
        <button type="button" class="reader-tab-btn active" id="tab-read-full" data-mode="read-full" role="tab" aria-selected="true">
          <span>📖</span> Read Full
        </button>
        <?php if (!empty($post['summary'])): ?>
          <button type="button" class="reader-tab-btn" id="tab-read-summary" data-mode="read-summary" role="tab" aria-selected="false">
            <span>📝</span> Read Summary
          </button>
        <?php endif; ?>
        <button type="button" class="reader-tab-btn" id="tab-listen-full" data-mode="listen-full" role="tab" aria-selected="false">
          <span>🎧</span> Listen Full
        </button>
        <?php if (!empty($post['summary'])): ?>
          <button type="button" class="reader-tab-btn" id="tab-listen-summary" data-mode="listen-summary" role="tab" aria-selected="false">
            <span>⚡</span> Listen Summary
          </button>
        <?php endif; ?>
      </div>

      <div style="display: flex; gap: 8px; align-items: center;">
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
          ⏱️ <?= (int) ($readingTime ?? 1) ?> min read
        </span>
      </div>
    </div>

    <!-- Integrated Audio Player (Hidden until Listen tab is selected or user clicks play) -->
    <div class="article-audio-player" id="articleAudioPlayer" style="display: none;">
      <div class="audio-player-header">
        <div class="audio-player-title">
          <span>🎧</span>
          <span id="audioTrackTitle">Listening to: Full Article</span>
        </div>
        <span class="audio-source-badge" id="audioSourceBadge">
          <?= !empty($speechData['has_server_audio']) ? 'Cloud Audio Stream' : '⚡ Browser Speech Synthesis' ?>
        </span>
      </div>

      <div class="audio-player-controls">
        <button type="button" class="audio-ctrl-btn" id="audioPlayPauseBtn" aria-label="Play or pause audio">
          <svg id="audioPlayIcon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
          <svg id="audioPauseIcon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="display: none;"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
        </button>

        <button type="button" class="btn btn-secondary btn-sm btn-pill" id="audioStopBtn" style="padding: 6px 12px; font-size: 0.78rem;">
          ⏹ Stop
        </button>

        <div class="audio-progress-bar-container">
          <input type="range" class="audio-progress-range" id="audioTimelineRange" min="0" max="100" value="0" aria-label="Audio progress">
          <div class="audio-time-row">
            <span id="audioCurrentTime">0:00</span>
            <span id="audioTotalDuration"><?= gmdate('i:s', (int) ($speechData['duration_seconds_est'] ?? 180)) ?></span>
          </div>
        </div>

        <div class="audio-speed-selector">
          <button type="button" class="audio-speed-btn active" data-rate="1">1x</button>
          <button type="button" class="audio-speed-btn" data-rate="1.25">1.25x</button>
          <button type="button" class="audio-speed-btn" data-rate="1.5">1.5x</button>
          <button type="button" class="audio-speed-btn" data-rate="2">2x</button>
        </div>
      </div>
    </div>

    <!-- Summary Mode Pane (Shown when user selects Read Summary) -->
    <?php if (!empty($post['summary'])): ?>
      <div id="summaryModePane" class="ai-summary-card" style="margin: 20px 0; display: none;">
        <div class="ai-summary-header">
          <span>✨</span>
          <span>Article Key Takeaways & Summary</span>
        </div>
        <p class="ai-summary-text" style="font-size: 1.05rem; line-height: 1.7;"><?= nl2br(htmlspecialchars($post['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
      </div>
    <?php endif; ?>

    <!-- Full Article Content (safely rendered from Markdown with inline media) -->
    <div class="article-body-content" id="articleBodyContent">
      <!-- Stored AI Summary Box inline (if present and current) -->
      <?php if (!empty($post['summary']) && \InfinityBlog\Posts\PostRepository::isSummaryCurrent($post)): ?>
        <aside class="ai-summary-card" style="margin-bottom: 24px;">
          <div class="ai-summary-header">
            <span>✨</span>
            <span>Article Summary</span>
          </div>
          <p class="ai-summary-text"><?= nl2br(htmlspecialchars($post['summary'], ENT_QUOTES, 'UTF-8')) ?></p>
        </aside>
      <?php endif; ?>

      <!-- Video Player (authorized signed URL from private bucket or local) -->
      <?php if (!empty($videoSignedUrl)): ?>
        <div class="article-video-player" style="margin: 20px 0 28px; border-radius: var(--radius-lg); overflow: hidden; background: #0f172a; box-shadow: var(--glass-shadow-sm); border: 1px solid rgba(255, 255, 255, 0.4);">
          <video controls preload="metadata" style="width: 100%; max-height: 540px; display: block;" playsinline>
            <source src="<?= htmlspecialchars($videoSignedUrl, ENT_QUOTES, 'UTF-8') ?>">
            Your browser does not support the video tag.
          </video>
        </div>
      <?php endif; ?>

      <?= $renderedBody ?? (new \InfinityBlog\Posts\ArticleContentRenderer())->render($post['content']) ?>
    </div>

    <!-- Article Actions Bar: PDF, EPUB, Share -->
    <div class="article-actions-bar">
      <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-dark); margin-right: 4px;">Story Actions:</span>
      <a href="/article/<?= (int) $post['id'] ?>/download/pdf" class="btn btn-secondary btn-sm btn-pill" title="Download formatted PDF">
        📄 Download PDF
      </a>
      <a href="/article/<?= (int) $post['id'] ?>/download/epub" class="btn btn-secondary btn-sm btn-pill" title="Download EPUB eBook">
        📱 Download EPUB
      </a>
      <button type="button" class="btn btn-primary btn-sm btn-pill" id="shareArticleBtn" onclick="triggerNativeShare('<?= htmlspecialchars($canonicalUrl ?? ('http://localhost:8086/article/' . (int) $post['id']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($metaDescription ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
        🔗 Share Article
      </button>
    </div>

    <!-- Reader Score Section -->
    <section id="reader-score" class="reader-score-card glass-panel" style="margin-top: 36px; padding: 24px 28px; border-radius: var(--radius-lg); background: var(--glass-bg-card); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
        <div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.25rem;">⭐</span>
            <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-dark); margin: 0;">Reader Score</h3>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">
            Community rating based on 1 to 5 stars from authenticated readers.
          </p>
        </div>

        <!-- Aggregate Score Summary Badge -->
        <div id="reader-score-summary" style="display: flex; align-items: baseline; gap: 8px; background: rgba(255, 255, 255, 0.55); padding: 8px 16px; border-radius: var(--radius-pill); border: 1px solid var(--glass-border);">
          <?php
            $avgScore = (float) ($scoreSummary['average'] ?? 0);
            $scoreCount = (int) ($scoreSummary['count'] ?? 0);
          ?>
          <span id="score-average-num" style="font-size: 1.35rem; font-weight: 800; color: var(--text-dark);"><?= $scoreCount > 0 ? number_format($avgScore, 1) : '—' ?></span>
          <span style="color: var(--text-subtle); font-size: 0.85rem;">/ 5</span>
          <span style="color: var(--border-color);">·</span>
          <span id="score-count-label" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);"><?= $scoreCount ?> <?= $scoreCount === 1 ? 'score' : 'scores' ?></span>
        </div>
      </div>

      <!-- Star Rating Interaction -->
      <div class="reader-score-interaction" style="padding-top: 14px; border-top: 1px solid rgba(226, 232, 240, 0.6);">
        <?php
          $isAuthor = !empty($currentUser) && ((int) $currentUser['id'] === (int) $post['author_id']);
          $isLoggedIn = !empty($currentUser);
        ?>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-main);">Your Rating:</span>
            <div id="star-picker" class="star-rating-controls <?= $isAuthor ? 'disabled-author' : '' ?>" data-current-score="<?= (int) ($userScore ?? 0) ?>" style="display: flex; gap: 4px;">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="star-btn <?= (!empty($userScore) && $i <= $userScore) ? 'active' : '' ?>" data-score="<?= $i ?>" aria-label="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>" title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>">
                  ★
                </button>
              <?php endfor; ?>
            </div>
          </div>

          <div id="score-feedback-message" style="font-size: 0.88rem; color: var(--text-muted);">
            <?php if ($isAuthor): ?>
              <span style="color: var(--text-subtle);">Authors cannot score their own articles.</span>
            <?php elseif (!$isLoggedIn): ?>
              <a href="/login?redirect=<?= urlencode('/article/' . (int) $post['id'] . '#reader-score') ?>" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Sign in to submit a Reader Score →</a>
            <?php elseif (!empty($userScore)): ?>
              <span style="color: #16a34a; font-weight: 600;">You scored this article <?= $userScore ?> / 5 ★ (click any star to update)</span>
            <?php else: ?>
              <span>Click a star above to rate this article.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- Remix CTA Banner -->
    <div class="remix-cta-box glass-panel" style="margin-top: 24px; padding: 20px 24px; border-radius: var(--radius-md); background: rgba(240, 249, 255, 0.65); border: 1px solid rgba(186, 230, 253, 0.85); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
      <div>
        <div style="font-weight: 700; color: #0369a1; font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">
          <span>🔀</span> Inspired by this story?
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">Start a Remix to adapt or expand these ideas with guaranteed original author attribution.</div>
      </div>
      <a href="<?= htmlspecialchars($remixUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm btn-pill">
        🔀 Remix This Article
      </a>
    </div>

    <div style="margin-top: 48px; padding-top: 24px; border-top: 1px solid rgba(226, 232, 240, 0.7); display: flex; justify-content: space-between; align-items: center;">
      <a href="/" class="btn btn-secondary btn-pill">
        ← Back to All Articles
      </a>
      <span style="font-size: 0.85rem; color: var(--text-subtle);">
        Infinity Blog
      </span>
    </div>
  </article>

  <!-- Script for Reader Score interaction -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const starPicker = document.getElementById('star-picker');
    const feedbackMsg = document.getElementById('score-feedback-message');
    const avgNum = document.getElementById('score-average-num');
    const countLabel = document.getElementById('score-count-label');
    const isAuthor = <?= $isAuthor ? 'true' : 'false' ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const postId = <?= (int) $post['id'] ?>;

    if (!starPicker) return;

    const starBtns = starPicker.querySelectorAll('.star-btn');

    function renderStars(score) {
      starBtns.forEach(btn => {
        const val = parseInt(btn.getAttribute('data-score'), 10);
        if (val <= score) {
          btn.classList.add('active');
          btn.style.color = '#f59e0b';
        } else {
          btn.classList.remove('active');
          btn.style.color = '#cbd5e1';
        }
      });
    }

    let currentScore = parseInt(starPicker.getAttribute('data-current-score') || '0', 10);

    if (!isAuthor && isLoggedIn) {
      starBtns.forEach(btn => {
        btn.addEventListener('mouseenter', () => {
          const hoverVal = parseInt(btn.getAttribute('data-score'), 10);
          renderStars(hoverVal);
        });

        btn.addEventListener('mouseleave', () => {
          renderStars(currentScore);
        });

        btn.addEventListener('click', async () => {
          const selectedVal = parseInt(btn.getAttribute('data-score'), 10);
          feedbackMsg.textContent = 'Submitting your Reader Score...';

          try {
            let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
              csrf = await window.BlogCMS.ensureCsrfToken();
            }

            const res = await fetch(`/posts/${postId}/score`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf || ''
              },
              body: JSON.stringify({ score: selectedVal })
            });

            const data = await res.json();
            if (res.ok) {
              currentScore = data.data.user_score;
              starPicker.setAttribute('data-current-score', currentScore);
              renderStars(currentScore);
              avgNum.textContent = Number(data.data.average).toFixed(1);
              countLabel.textContent = `${data.data.count} ${data.data.count === 1 ? 'score' : 'scores'}`;
              feedbackMsg.innerHTML = `<span style="color: #16a34a; font-weight: 600;">You scored this article ${currentScore} / 5 ★ (updated!)</span>`;
            } else {
              feedbackMsg.innerHTML = `<span style="color: var(--danger-color);">${data.error?.message || 'Could not submit score.'}</span>`;
              renderStars(currentScore);
            }
          } catch (err) {
            feedbackMsg.innerHTML = `<span style="color: var(--danger-color);">Network error while submitting score.</span>`;
            renderStars(currentScore);
          }
        });
      });
    } else if (!isLoggedIn) {
      starBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + '#reader-score')}`;
        });
      });
    }
  });
  </script>

  <!-- Sidebar: Recent Articles -->
  <aside>
    <div class="glass-panel sidebar-card">
      <h3 class="sidebar-title">Recent Stories</h3>
      <?php if (empty($recentPosts)): ?>
        <p style="font-size: 0.85rem; color: var(--text-muted);">No other articles yet.</p>
      <?php else: ?>
        <div class="mini-article-list">
          <?php foreach ($recentPosts as $item): ?>
            <a href="/article/<?= (int) $item['id'] ?>" class="mini-article-item">
              <div class="mini-article-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="mini-article-date">
                <?= date('M j, Y', strtotime($item['published_at'] ?? $item['created_at'])) ?>
                <?php if (!empty($item['category_name'])): ?>
                  · <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>
</div>

<!-- Share Article Modal -->
<div class="modal-backdrop" id="share-article-modal">
  <div class="modal-card share-modal-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark);">Share this Story</h3>
      <button type="button" class="alert-close-btn" onclick="closeShareModal()" style="font-size: 1.4rem;">×</button>
    </div>
    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
      Spread the word with readers, colleagues, and your network.
    </p>

    <?php
      $shareUrl = $canonicalUrl ?? ('http://localhost:8086/article/' . (int) $post['id']);
      $shareTitle = $post['title'];
    ?>
    <div class="share-options-grid">
      <a href="https://api.whatsapp.com/send?text=<?= urlencode($shareTitle . ' ' . $shareUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-option-btn">
        <span style="font-size: 1.2rem;">💬</span> WhatsApp
      </a>
      <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-option-btn">
        <span style="font-size: 1.2rem;">💼</span> LinkedIn
      </a>
      <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($shareTitle) ?>" target="_blank" rel="noopener noreferrer" class="share-option-btn">
        <span style="font-size: 1.2rem;">🐦</span> Twitter / X
      </a>
      <a href="mailto:?subject=<?= urlencode('Thoughtful Article: ' . $shareTitle) ?>&body=<?= urlencode('Read this article on Infinity Blog: ' . $shareUrl) ?>" class="share-option-btn">
        <span style="font-size: 1.2rem;">✉️</span> Email
      </a>
    </div>

    <div style="margin-top: 16px;">
      <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Story Link</label>
      <div class="share-copy-link-wrapper">
        <input type="text" id="share-modal-url-input" class="share-copy-input" value="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" readonly>
        <button type="button" class="btn btn-primary btn-sm btn-pill" onclick="copyShareLink()">
          Copy Link
        </button>
      </div>
      <span id="share-copy-tooltip" style="display: none; font-size: 0.8rem; color: #16a34a; font-weight: 600; margin-top: 6px;">✓ Link copied to clipboard!</span>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const speechData = <?= json_encode($speechData ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const synth = window.speechSynthesis;
  const bodyContent = document.getElementById('articleBodyContent');
  const summaryPane = document.getElementById('summaryModePane');
  const audioPlayer = document.getElementById('articleAudioPlayer');
  const playPauseBtn = document.getElementById('audioPlayPauseBtn');
  const playIcon = document.getElementById('audioPlayIcon');
  const pauseIcon = document.getElementById('audioPauseIcon');
  const stopBtn = document.getElementById('audioStopBtn');
  const progressRange = document.getElementById('audioTimelineRange');
  const currentTimeEl = document.getElementById('audioCurrentTime');
  const totalDurationEl = document.getElementById('audioTotalDuration');
  const trackTitleEl = document.getElementById('audioTrackTitle');
  const speedBtns = document.querySelectorAll('.audio-speed-btn');

  let currentAudioMode = 'full';
  let isPlaying = false;
  let isPaused = false;
  let speechRate = 1.0;
  let chunks = [];
  let chunkIdx = 0;
  let timerInterval = null;
  let elapsedSec = 0;
  let totalEstimatedSec = speechData?.duration_seconds_est || 180;

  function formatTime(s) {
    const mins = Math.floor(s / 60);
    const secs = Math.floor(s % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }

  function splitIntoChunks(text) {
    if (!text) return [];
    const sentences = text.replace(/([.?!])\s+/g, "$1|").split("|");
    const result = [];
    let cur = "";
    for (const s of sentences) {
      if ((cur + " " + s).length > 150) {
        if (cur.trim()) result.push(cur.trim());
        cur = s;
      } else {
        cur += (cur ? " " : "") + s;
      }
    }
    if (cur.trim()) result.push(cur.trim());
    return result;
  }

  function stopAudio() {
    if (synth) synth.cancel();
    isPlaying = false;
    isPaused = false;
    chunkIdx = 0;
    elapsedSec = 0;
    if (timerInterval) clearInterval(timerInterval);
    if (playIcon) playIcon.style.display = 'block';
    if (pauseIcon) pauseIcon.style.display = 'none';
    if (progressRange) progressRange.value = 0;
    if (currentTimeEl) currentTimeEl.textContent = '0:00';
  }

  function playNextChunk() {
    if (!isPlaying) return;
    if (chunkIdx >= chunks.length) {
      stopAudio();
      return;
    }
    const chunkText = chunks[chunkIdx];
    const utt = new SpeechSynthesisUtterance(chunkText);
    utt.rate = speechRate;
    utt.onend = () => {
      chunkIdx++;
      if (progressRange && chunks.length > 0) {
        progressRange.value = Math.min(100, Math.round((chunkIdx / chunks.length) * 100));
      }
      playNextChunk();
    };
    utt.onerror = () => {
      chunkIdx++;
      playNextChunk();
    };
    synth.speak(utt);
  }

  function startAudio(mode = 'full') {
    if (!synth) {
      alert('Speech synthesis is not supported in this browser.');
      return;
    }
    stopAudio();
    currentAudioMode = mode;
    const textToSpeak = mode === 'summary' ? (speechData.summary_text || speechData.full_text) : speechData.full_text;
    chunks = splitIntoChunks(textToSpeak);
    if (chunks.length === 0) return;

    totalEstimatedSec = mode === 'summary'
      ? Math.max(10, Math.round(((speechData.summary_text || '').split(/\s+/).length / 140) * 60))
      : (speechData.duration_seconds_est || 180);

    if (totalDurationEl) totalDurationEl.textContent = formatTime(totalEstimatedSec);
    if (trackTitleEl) trackTitleEl.textContent = mode === 'summary' ? 'Listening to: Article Summary' : 'Listening to: Full Article';

    isPlaying = true;
    isPaused = false;
    if (playIcon) playIcon.style.display = 'none';
    if (pauseIcon) pauseIcon.style.display = 'block';

    timerInterval = setInterval(() => {
      if (isPlaying && !isPaused) {
        elapsedSec += 1;
        if (currentTimeEl) currentTimeEl.textContent = formatTime(elapsedSec);
      }
    }, 1000);

    playNextChunk();
  }

  function togglePlayPause() {
    if (!isPlaying) {
      startAudio(currentAudioMode);
      return;
    }
    if (isPaused) {
      synth.resume();
      isPaused = false;
      if (playIcon) playIcon.style.display = 'none';
      if (pauseIcon) pauseIcon.style.display = 'block';
    } else {
      synth.pause();
      isPaused = true;
      if (playIcon) playIcon.style.display = 'block';
      if (pauseIcon) pauseIcon.style.display = 'none';
    }
  }

  if (playPauseBtn) playPauseBtn.addEventListener('click', togglePlayPause);
  if (stopBtn) stopBtn.addEventListener('click', stopAudio);

  speedBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      speedBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      speechRate = parseFloat(btn.getAttribute('data-rate')) || 1.0;
      if (isPlaying && !isPaused) {
        synth.cancel();
        playNextChunk();
      }
    });
  });

  if (progressRange) {
    progressRange.addEventListener('input', () => {
      if (chunks.length > 0) {
        chunkIdx = Math.floor((parseInt(progressRange.value, 10) / 100) * chunks.length);
        if (isPlaying && !isPaused) {
          synth.cancel();
          playNextChunk();
        }
      }
    });
  }

  const readerTabs = document.querySelectorAll('.reader-tab-btn');
  function setActiveReaderTab(activeTab) {
    readerTabs.forEach(t => {
      t.classList.remove('active');
      t.setAttribute('aria-selected', 'false');
    });
    if (activeTab) {
      activeTab.classList.add('active');
      activeTab.setAttribute('aria-selected', 'true');
    }
  }

  const tabReadFull = document.getElementById('tab-read-full');
  const tabReadSummary = document.getElementById('tab-read-summary');
  const tabListenFull = document.getElementById('tab-listen-full');
  const tabListenSummary = document.getElementById('tab-listen-summary');

  if (tabReadFull) {
    tabReadFull.addEventListener('click', () => {
      setActiveReaderTab(tabReadFull);
      if (bodyContent) bodyContent.style.display = 'block';
      if (summaryPane) summaryPane.style.display = 'none';
      if (audioPlayer) audioPlayer.style.display = 'none';
      stopAudio();
    });
  }

  if (tabReadSummary) {
    tabReadSummary.addEventListener('click', () => {
      setActiveReaderTab(tabReadSummary);
      if (bodyContent) bodyContent.style.display = 'none';
      if (summaryPane) summaryPane.style.display = 'block';
      if (audioPlayer) audioPlayer.style.display = 'none';
      stopAudio();
    });
  }

  if (tabListenFull) {
    tabListenFull.addEventListener('click', () => {
      setActiveReaderTab(tabListenFull);
      if (bodyContent) bodyContent.style.display = 'block';
      if (summaryPane) summaryPane.style.display = 'none';
      if (audioPlayer) audioPlayer.style.display = 'block';
      startAudio('full');
    });
  }

  if (tabListenSummary) {
    tabListenSummary.addEventListener('click', () => {
      setActiveReaderTab(tabListenSummary);
      if (bodyContent) bodyContent.style.display = 'none';
      if (summaryPane) summaryPane.style.display = 'block';
      if (audioPlayer) audioPlayer.style.display = 'block';
      startAudio('summary');
    });
  }

  window.addEventListener('beforeunload', () => {
    if (synth) synth.cancel();
  });
});
</script>
