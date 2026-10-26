<!-- Screen 8: Single Post Page (Public View) -->
<div id="readingProgressBar" class="reading-progress-bar"></div>
<div id="exitFocusModePill" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 99999;">
  <button type="button" onclick="toggleFocusMode()" class="btn btn-primary" style="box-shadow: 0 4px 20px rgba(37, 99, 235, 0.45); border-radius: 30px; padding: 10px 20px;">
    Exit Focus Mode ✕
  </button>
</div>

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
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
        <?= \InfinityBlog\Posts\ContentTypeHelper::renderBadge($post['content_type'] ?? 'article') ?>
        <?php if (!empty($post['category_name'])): ?>
          <span class="badge badge-category" style="display: inline-flex; align-items: center; gap: 6px;">
            <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($post['category_slug'] ?? '', 14) ?>
            <span><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
          </span>
        <?php else: ?>
          <span class="badge badge-published">Published Article</span>
        <?php endif; ?>
      </div>

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
        <div class="article-author-info" style="display: flex; align-items: center; gap: 12px;">
          <?php
            $authorAvatar = $post['author_avatar_url'] ?? \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($post['author_avatar_path'] ?? ($post['avatar_path'] ?? null));
            $authorInitial = strtoupper(mb_substr($post['author_name'] ?? 'A', 0, 1));
            $authorHandle = $post['author_handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($post['author_username'] ?? null, $post['author_name'] ?? null);
          ?>
          <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" class="article-author-avatar-link" title="View <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>'s Profile" style="text-decoration: none; display: inline-flex;">
            <?php if (!empty($authorAvatar)): ?>
              <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>"
                   alt="<?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>"
                   class="article-author-avatar-img"
                   width="38" height="38" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255, 255, 255, 0.85); box-shadow: var(--clay-shadow-sm, 0 2px 8px rgba(0,0,0,0.08));"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="user-avatar article-avatar-fallback" style="display: none; width: 38px; height: 38px; font-size: 0.95rem; font-weight: 700; border-radius: 50%;">
                <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php else: ?>
              <div class="user-avatar article-avatar-fallback" style="width: 38px; height: 38px; font-size: 0.95rem; font-weight: 700; border-radius: 50%;">
                <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
          </a>
          <div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <a href="/author/<?= (int) ($post['author_id'] ?? 1) ?>" style="font-weight: 700; color: var(--text-dark); text-decoration: none;" title="View Author Profile">
                <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
              </a>
              <?php if (!empty($post['author_has_badge']) || !empty($authorHasBadge)): ?>
                <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
              <?php endif; ?>
              <span class="article-author-handle" style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">
                <?= htmlspecialchars($authorHandle, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
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

        <div class="article-meta-actions">
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

          <?php if (!empty($currentUser) && (int) $currentUser['id'] !== (int) ($post['author_id'] ?? 0)): ?>
            <button type="button" class="btn btn-secondary btn-sm btn-pill btn-report-article" onclick="openReportPostModal()" title="Report this article to moderators" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.35);">
              <span aria-hidden="true">🚩</span> Report
            </button>
          <?php elseif (empty($currentUser)): ?>
            <a href="/login?redirect=<?= urlencode('/article/' . (int) $post['id']) ?>" class="btn btn-secondary btn-sm btn-pill btn-report-article" title="Sign in to report this article" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.35);">
              <span aria-hidden="true">🚩</span> Report
            </a>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <!-- Featured Article Banner Image -->
    <?php
      $articleImage = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, 0);
    ?>
    <?php if (!empty($articleImage)): ?>
      <div class="article-featured-banner">
        <img src="<?= htmlspecialchars($articleImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high" onerror="this.onerror=null; this.closest('.article-featured-banner').style.display='none';">
      </div>
    <?php endif; ?>

    <!-- Reader Experience Toolbar (Reader Mode, Audio, Focus, Bookmark, AI) -->
    <div class="article-reader-toolbar" role="toolbar" aria-label="Reading Controls and Preferences">
      <!-- Cluster 1: Reading & Listening Modes -->
      <div class="reader-toolbar-cluster cluster-modes reader-mode-tabs" role="tablist" aria-label="Reading Experience Options">
        <button type="button" class="reader-tab-btn active" id="tab-read-full" data-mode="read-full" role="tab" aria-selected="true" title="Read Full Article">
          <span class="btn-icon">📖</span>
          <span class="btn-text">Read Full</span>
        </button>
        <?php if (!empty($post['summary'])): ?>
          <button type="button" class="reader-tab-btn" id="tab-read-summary" data-mode="read-summary" role="tab" aria-selected="false" title="Read Article Summary">
            <span class="btn-icon">📝</span>
            <span class="btn-text">Summary</span>
          </button>
        <?php endif; ?>
        <button type="button" class="reader-tab-btn" id="tab-listen-full" data-mode="listen-full" role="tab" aria-selected="false" title="Listen to Full Article Audio">
          <span class="btn-icon">🎧</span>
          <span class="btn-text">Listen Full</span>
        </button>
        <?php if (!empty($post['summary'])): ?>
          <button type="button" class="reader-tab-btn" id="tab-listen-summary" data-mode="listen-summary" role="tab" aria-selected="false" title="Listen to Summary Audio">
            <span class="btn-icon">⚡</span>
            <span class="btn-text">Listen Summary</span>
          </button>
        <?php endif; ?>
        <button type="button" class="reader-tab-btn btn-focus-mode" id="focusModeBtn" onclick="toggleFocusMode()" title="Toggle Distraction-Free Reading Mode (Esc)" aria-pressed="false">
          <span class="btn-icon">👁️</span>
          <span class="btn-text">Focus Mode</span>
        </button>
      </div>

      <!-- Cluster 2: Reader Actions (Save & Ask AI) -->
      <div class="reader-toolbar-cluster cluster-actions">
        <button type="button" class="reader-tab-btn btn-bookmark <?= !empty($isBookmarked) ? 'active is-saved' : '' ?>" id="bookmarkArticleBtn" onclick="toggleBookmarkArticle(<?= (int) $post['id'] ?>)" title="<?= !empty($isBookmarked) ? 'Saved to Personal Library' : 'Save to Personal Library' ?>" aria-label="<?= !empty($isBookmarked) ? 'Saved to Personal Library' : 'Save to Personal Library' ?>">
          <span class="btn-icon">🔖</span>
          <span class="btn-text" id="bookmarkBtnText"><?= !empty($isBookmarked) ? 'Saved' : 'Save' ?></span>
        </button>
        <button type="button" class="reader-tab-btn btn-ask-ai" id="openAssistantBtn" onclick="toggleAssistantDrawer()" title="Consult Dr. Strange about this article" aria-expanded="false" aria-label="Consult Dr. Strange about this article">
          <span class="btn-icon" aria-hidden="true">
            <svg class="btn-orb-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="9.5" stroke="url(#btnArcaneGlow)" stroke-width="1.8" stroke-dasharray="4 2.5" />
              <circle cx="12" cy="12" r="5.5" stroke="#45D9F5" stroke-width="1.4" />
              <circle cx="12" cy="12" r="2.2" fill="#FFB52E" />
              <defs>
                <linearGradient id="btnArcaneGlow" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#FFB52E" />
                  <stop offset="0.5" stop-color="#FF6A21" />
                  <stop offset="1" stop-color="#45D9F5" />
                </linearGradient>
              </defs>
            </svg>
          </span>
          <span class="btn-text">Ask AI</span>
        </button>
      </div>

      <!-- Cluster 3: Typography & Reading Time Meta -->
      <div class="reader-toolbar-cluster cluster-meta">
        <!-- Font Size Adjuster Controls -->
        <div class="font-size-adjuster" role="group" aria-label="Adjust font size">
          <button type="button" class="font-size-btn" onclick="adjustArticleFontSize(-1)" title="Decrease Text Size" aria-label="Decrease Text Size">A-</button>
          <button type="button" class="font-size-btn" onclick="adjustArticleFontSize(0)" title="Default Text Size" aria-label="Default Text Size">A</button>
          <button type="button" class="font-size-btn" onclick="adjustArticleFontSize(1)" title="Increase Text Size" aria-label="Increase Text Size">A+</button>
        </div>
        <span class="reading-time-pill" title="Estimated reading time">
          ⏱️ <?= (int) ($readingTime ?? 1) ?> min read
        </span>
      </div>
    </div>

    <!-- Integrated Audio Player (Hidden until Listen tab is selected or user clicks play) -->
    <div class="article-audio-player" id="articleAudioPlayer" style="display: none;">
      <audio id="html5AudioElement" preload="metadata" style="display: none;"></audio>
      <div class="audio-player-header">
        <div class="audio-player-title">
          <span>🎧</span>
          <span id="audioTrackTitle">Listening to: <?= htmlspecialchars(mb_substr($post['title'] ?? 'Full Article', 0, 48) . (mb_strlen($post['title'] ?? '') > 48 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div style="display: inline-flex; align-items: center; gap: 8px;">
          <span class="audio-state-badge state-idle" id="audioPlaybackStateBadge">Idle</span>
          <span class="audio-source-badge" id="audioSourceBadge">
            <?= !empty($speechData['has_server_audio']) ? '☁️ Cloud Audio' : '⚡ Browser TTS' ?>
          </span>
          <button type="button" class="audio-close-btn" id="audioCloseBtn" aria-label="Close audio player" title="Close Audio Player">✕</button>
        </div>
      </div>

      <!-- Waveform Visualizer (48 harmonic bars with dynamic pulse and seek) -->
      <?php
        $waveformBarsList = [
          35, 50, 70, 90, 60, 40, 65, 85, 95, 75, 45, 30, 55, 80, 100, 70, 45, 60, 85, 95, 70, 50, 35, 60,
          80, 90, 65, 40, 55, 75, 90, 100, 80, 60, 40, 50, 70, 85, 65, 45, 35, 60, 80, 95, 75, 55, 40, 30
        ];
      ?>
      <div class="audio-waveform-container" id="audioWaveformVisualizer" role="slider" aria-label="Audio Waveform Seek" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
        <?php foreach ($waveformBarsList as $wIdx => $wHeight): ?>
          <div class="waveform-bar" data-bar-index="<?= $wIdx ?>" style="--base-h: <?= $wHeight ?>%; --i: <?= $wIdx ?>; height: <?= $wHeight ?>%;"></div>
        <?php endforeach; ?>
      </div>

      <div class="audio-player-controls">
        <button type="button" class="audio-ctrl-btn" id="audioPlayPauseBtn" aria-label="Play audio">
          <svg id="audioPlayIcon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
          <svg id="audioPauseIcon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="display: none;"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>
        </button>

        <button type="button" class="btn btn-secondary btn-sm btn-pill" id="audioStopBtn" style="padding: 6px 12px; font-size: 0.78rem;" title="Stop Audio and Reset">
          ⏹ Stop
        </button>

        <div class="audio-progress-bar-container">
          <input type="range" class="audio-progress-range" id="audioTimelineRange" min="0" max="100" value="0" aria-label="Audio progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
          <div class="audio-time-row">
            <span id="audioCurrentTime">0:00</span>
            <span id="audioEstimatedNote" class="audio-estimate-note"><?= !empty($speechData['has_server_audio']) ? 'Cloud stream' : 'Spoken progress' ?></span>
            <span id="audioTotalDuration"><?= (!empty($speechData['has_server_audio']) ? '' : '~') . gmdate('i:s', (int) ($speechData['duration_seconds_est'] ?? 180)) ?></span>
          </div>
        </div>

        <div class="audio-speed-selector" role="group" aria-label="Audio playback speed">
          <button type="button" class="audio-speed-btn active" data-rate="1" aria-label="Normal speed">1x</button>
          <button type="button" class="audio-speed-btn" data-rate="1.25" aria-label="1.25 times speed">1.25x</button>
          <button type="button" class="audio-speed-btn" data-rate="1.5" aria-label="1.5 times speed">1.5x</button>
          <button type="button" class="audio-speed-btn" data-rate="2" aria-label="2 times speed">2x</button>
        </div>
      </div>
      <div id="audioPlayerMessage" class="audio-player-alert" style="display: none;" role="alert"></div>
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
    <div class="article-body-content article-body" id="articleBodyContent">
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
      <button type="button" class="btn btn-primary btn-sm btn-pill" id="shareArticleBtn" onclick="triggerNativeShare('<?= htmlspecialchars($canonicalUrl ?? (($baseUrl ?? '') . '/article/' . (int) $post['id']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($metaDescription ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
        🔗 Share Article
      </button>
      <?php if (!empty($currentUser) && (int) $currentUser['id'] !== (int) ($post['author_id'] ?? 0)): ?>
        <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="openReportPostModal()" title="Report inappropriate content" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.35);">
          🚩 Report Post
        </button>
      <?php elseif (empty($currentUser)): ?>
        <a href="/login?redirect=<?= urlencode('/article/' . (int) $post['id']) ?>" class="btn btn-secondary btn-sm btn-pill" title="Sign in to report this article" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.35);">
          🚩 Report Post
        </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($reviewsUnavailable)): ?>
      <section class="glass-panel" style="margin-top: 36px; padding: 24px 28px; border-radius: var(--radius-lg); text-align: center;">
        <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem;">
          Ratings and reviews are temporarily unavailable.
        </p>
      </section>
    <?php else: ?>
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
        <div id="reader-score-summary">
          <?php
            $avgScore = (float) ($scoreSummary['average'] ?? 0);
            $scoreCount = (int) ($scoreSummary['count'] ?? 0);
            echo \InfinityBlog\Support\RatingHelper::getRatingInfo($avgScore, $scoreCount)['badge_html'];
          ?>
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

    <!-- Reader Reviews Section (Formal Evaluative Reviews) -->
    <section id="reader-reviews" class="reader-reviews-section glass-panel">
      <div class="reader-reviews-header">
        <div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.3rem;">📝</span>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">
              Reader Reviews (<span id="reviewsCountBadge"><?= (int) ($reviewsTotal ?? count($reviews ?? [])) ?></span>)
            </h3>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; margin-bottom: 0;">
            Evaluations and critical perspectives from verified readers.
          </p>
        </div>

        <div>
          <?php if (!empty($currentUser) && (int) $currentUser['id'] !== (int) ($post['author_id'] ?? 0)): ?>
            <button type="button" class="btn btn-primary btn-sm btn-pill" id="toggleReviewComposerBtn" onclick="toggleReviewComposer()">
              ✍️ <?= !empty($userReview) ? 'Edit Your Review' : 'Write a Review' ?>
            </button>
          <?php elseif (!empty($currentUser) && (int) $currentUser['id'] === (int) ($post['author_id'] ?? 0)): ?>
            <span style="font-size: 0.82rem; color: var(--text-muted); font-style: italic;">Authors cannot review their own posts.</span>
          <?php elseif (empty($currentUser)): ?>
            <a href="/login?redirect=<?= urlencode('/article/' . (int) $post['id'] . '#reader-reviews') ?>" class="btn btn-secondary btn-sm btn-pill">
              Sign In to Review
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Review Composer Box (Hidden by default unless toggled or editing) -->
      <?php if (!empty($currentUser) && (int) $currentUser['id'] !== (int) ($post['author_id'] ?? 0)): ?>
        <div id="reviewComposerBox" class="review-composer-box" style="display: none;">
          <h4 style="margin: 0 0 12px; font-size: 1.05rem; font-weight: 700; color: var(--text-dark);" id="composerTitle">
            <?= !empty($userReview) ? 'Edit Your Review' : 'Write an Article Review' ?>
          </h4>
          <form id="readerReviewForm" onsubmit="handleReviewSubmit(event)">
            <!-- Star Rating Input in Composer -->
            <div style="margin-bottom: 14px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
              <span style="font-weight: 600; font-size: 0.88rem; color: var(--text-dark);">Your Score:</span>
              <div id="composerStarPicker" class="star-rating-controls" style="display: flex; gap: 4px;">
                <?php $initialScore = !empty($userReview['rating']) ? (int) $userReview['rating'] : (!empty($userScore) ? (int) $userScore : 5); ?>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button type="button" class="star-btn <?= $i <= $initialScore ? 'active' : '' ?>" data-score="<?= $i ?>" onclick="setComposerScore(<?= $i ?>)" aria-label="<?= $i ?> Star" style="font-size: 1.3rem; background: none; border: none; cursor: pointer; color: <?= $i <= $initialScore ? '#f59e0b' : '#cbd5e1' ?>;">
                    ★
                  </button>
                <?php endfor; ?>
              </div>
              <span id="composerScoreLabel" style="font-weight: 700; font-size: 0.88rem; color: var(--text-dark);"><?= $initialScore ?> / 5 Stars</span>
              <input type="hidden" id="composerScoreInput" value="<?= $initialScore ?>">
            </div>

            <!-- Review Textarea -->
            <div style="margin-bottom: 12px;">
              <textarea id="reviewContentInput" class="form-input" rows="4" placeholder="Share your assessment of the arguments, methodology, or insights in this story..." required minlength="10" maxlength="5000" style="width: 100%; resize: vertical;" oninput="updateReviewCharCount(this)"><?= !empty($userReview['content']) ? htmlspecialchars($userReview['content'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                <span style="font-size: 0.75rem; color: var(--text-muted);" id="reviewCharCounter"><?= !empty($userReview['content']) ? mb_strlen($userReview['content']) : 0 ?> / 5000</span>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Constructive evaluations help authors and fellow readers.</span>
              </div>
            </div>

            <div id="reviewFormFeedback" style="display: none; padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 12px;"></div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
              <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="toggleReviewComposer(false)">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary btn-sm btn-pill" id="submitReviewBtn">
                <?= !empty($userReview) ? 'Update Review' : 'Submit Review' ?>
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- Reviews Stream -->
      <div id="reviewsStream" class="reviews-stream">
        <?php if (empty($reviews)): ?>
          <div id="noReviewsNotice" class="empty-reviews-box" style="text-align: center; padding: 32px 20px; color: var(--text-muted); background: rgba(255,255,255,0.4); border-radius: 12px; border: 1px dashed var(--glass-border);">
            <span style="font-size: 2rem; display: block; margin-bottom: 6px;">📖</span>
            <strong style="color: var(--text-dark); font-size: 0.95rem;">No written reviews yet.</strong>
            <p style="margin: 4px 0 0; font-size: 0.85rem;">Be the first reader to share a formal evaluation of this story!</p>
          </div>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
            <?php
              $revId = (int) $rev['id'];
              $isOwn = !empty($currentUser) && ((int) $currentUser['id'] === (int) $rev['user_id']);
              $isAdmin = !empty($currentUser) && ($currentUser['role'] === 'admin');
              $authorInitial = strtoupper(mb_substr($rev['display_name'] ?? 'R', 0, 1));
            ?>
            <div class="review-card <?= $isOwn ? 'user-own-review' : '' ?>" id="review-card-<?= $revId ?>" data-review-id="<?= $revId ?>">
              <div class="review-card-header">
                <div class="review-author-info">
                  <?php if (!empty($rev['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($rev['avatar_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($rev['display_name'], ENT_QUOTES, 'UTF-8') ?>" class="review-avatar-img" width="38" height="38" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="review-avatar-fallback" style="display: none;"><?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php else: ?>
                    <div class="review-avatar-fallback"><?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>

                  <div class="review-meta-details">
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                      <a href="/author/<?= urlencode($rev['username']) ?>" class="review-author-name">
                        <?= htmlspecialchars($rev['display_name'], ENT_QUOTES, 'UTF-8') ?>
                      </a>
                      <?php if (!empty($rev['has_author_badge'])): ?>
                        <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                      <?php endif; ?>
                      <?php if ($isOwn): ?>
                        <span style="font-size: 0.72rem; background: rgba(59, 130, 246, 0.12); color: var(--primary-color); padding: 1px 6px; border-radius: 6px; font-weight: 700;">Your Review</span>
                      <?php endif; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <div class="review-stars-display" aria-label="Rating: <?= (int) $rev['rating'] ?> of 5 stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                          <span style="color: <?= $s <= (int) $rev['rating'] ? '#f59e0b' : '#cbd5e1' ?>;">★</span>
                        <?php endfor; ?>
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-dark); margin-left: 4px;"><?= (int) $rev['rating'] ?>/5</span>
                      </div>
                      <span style="color: var(--border-color);">·</span>
                      <span class="review-date-timestamp"><?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                    </div>
                  </div>
                </div>

                <!-- Overflow Menu for Review -->
                <div class="dropdown" style="position: relative;">
                  <button type="button" class="review-menu-btn" onclick="toggleReviewMenu(event, <?= $revId ?>)" aria-label="Review options">
                    •••
                  </button>
                  <div class="feed-menu-dropdown glass-panel" id="review-menu-<?= $revId ?>" style="display: none; position: absolute; right: 0; top: 100%; z-index: 10; min-width: 140px;">
                    <?php if ($isOwn): ?>
                      <button type="button" class="feed-menu-item" onclick="initEditReview(<?= $revId ?>, <?= (int) $rev['rating'] ?>, <?= htmlspecialchars(json_encode($rev['content']), ENT_QUOTES, 'UTF-8') ?>)">
                        <span>✏️</span> Edit Review
                      </button>
                      <button type="button" class="feed-menu-item" style="color: #ef4444;" onclick="deleteReviewItem(<?= $revId ?>)">
                        <span>🗑️</span> Delete Review
                      </button>
                    <?php elseif ($isAdmin): ?>
                      <button type="button" class="feed-menu-item" style="color: #ef4444;" onclick="deleteReviewItem(<?= $revId ?>)">
                        <span>🗑️</span> Delete Review (Admin)
                      </button>
                      <button type="button" class="feed-menu-item" onclick="openReportReviewModal(<?= $revId ?>)">
                        <span>🚩</span> Report Review
                      </button>
                    <?php else: ?>
                      <button type="button" class="feed-menu-item" onclick="openReportReviewModal(<?= $revId ?>)">
                        <span>🚩</span> Report Review
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Review Text -->
              <div class="review-content-body" id="review-text-<?= $revId ?>">
                <?= nl2br(htmlspecialchars($rev['content'], ENT_QUOTES, 'UTF-8')) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Load More Reviews Button (if pagination exceeds page 1) -->
      <?php if (!empty($reviewsTotal) && $reviewsTotal > count($reviews ?? [])): ?>
        <div id="loadMoreReviewsContainer" style="text-align: center; margin-top: 20px;">
          <button type="button" class="btn btn-secondary btn-sm btn-pill" id="loadMoreReviewsBtn" onclick="loadMoreReviews(<?= (int) $post['id'] ?>)">
            Load More Reviews ↓
          </button>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Remix CTA Banner -->
    <div class="remix-cta-box glass-panel" style="margin-top: 24px; padding: 20px 24px; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
      <div>
        <div class="remix-cta-title" style="font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">
          <span>🔀</span> Inspired by this story?
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">Start a Remix to adapt or expand these ideas with guaranteed original author attribution.</div>
      </div>
      <a href="<?= htmlspecialchars($remixUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm btn-pill">
        🔀 Remix This Article
      </a>
    </div>

    <!-- Threaded Comments & Community Discussions -->
    <section class="article-comments-section glass-panel" id="commentsSection" style="margin-top: 36px; padding: 28px; border-radius: var(--radius-lg); background: var(--glass-bg-card); border: 1px solid var(--glass-border);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="font-size: 1.3rem;">💬</span>
          <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">
            Discussion (<span id="commentsCount"><?= count($comments ?? []) ?></span>)
          </h3>
        </div>
      </div>

      <!-- New Comment Form -->
      <?php if (!empty($currentUser)): ?>
        <form id="commentPostForm" onsubmit="handlePostComment(event)" style="margin-bottom: 28px;">
          <input type="hidden" id="replyParentId" value="">
          <div id="replyingToBanner" style="display: none; padding: 6px 12px; background: rgba(37, 99, 235, 0.08); border-radius: 8px; font-size: 0.85rem; color: var(--primary-color); margin-bottom: 8px; justify-content: space-between; align-items: center;">
            <span>Replying to comment...</span>
            <button type="button" onclick="cancelReply()" style="border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-weight: 700;">✕ Cancel</button>
          </div>
          <div style="display: flex; gap: 12px; align-items: flex-start;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">
              <?= htmlspecialchars(mb_substr($currentUser['display_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="flex: 1;">
              <textarea id="commentContentInput" class="form-input" rows="3" placeholder="Share your constructive perspective or question on this story..." required maxlength="3000" style="resize: vertical;"></textarea>
              <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                <button type="submit" class="btn btn-primary btn-sm btn-pill" id="submitCommentBtn">
                  Post Comment
                </button>
              </div>
            </div>
          </div>
        </form>
      <?php else: ?>
        <div style="padding: 16px 20px; border-radius: 12px; background: rgba(255,255,255,0.5); text-align: center; margin-bottom: 24px; border: 1px dashed var(--glass-border);">
          <span style="font-size: 0.9rem; color: var(--text-muted);">
            Want to join the conversation? <a href="/login?redirect=<?= urlencode('/article/' . (int) $post['id'] . '#commentsSection') ?>" style="color: var(--primary-color); font-weight: 700; text-decoration: underline;">Sign in</a> to leave a comment.
          </span>
        </div>
      <?php endif; ?>

      <!-- Comments Stream List -->
      <div id="commentsStream" style="display: flex; flex-direction: column; gap: 14px;">
        <?php if (empty($comments)): ?>
          <div id="noCommentsNotice" style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 0.92rem;">
            No comments yet. Be the first to share your thoughts on this story!
          </div>
        <?php else: ?>
          <?php foreach ($comments as $comm): ?>
            <?php
              $commId = (int) $comm['id'];
              $commHandle = \InfinityBlog\Support\UserIdentityHelper::formatHandle($comm['user_username'] ?? '', $comm['user_name'] ?? '');
              $commAvatar = \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($comm['user_avatar'] ?? null, $comm['user_name'] ?? '');
              $commLikes = (int) ($comm['like_count'] ?? 0);
              $commDislikes = (int) ($comm['dislike_count'] ?? 0);
              $commReaction = $comm['viewer_reaction'] ?? null;
              $isPinned = !empty($comm['is_pinned']);
              $isAuthor = !empty($comm['is_author']);
            ?>
            <div class="comment-item <?= $isPinned ? 'is-pinned' : '' ?>" id="comment-<?= $commId ?>">
              <?php if ($isPinned): ?>
                <div class="comment-pinned-banner" style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--primary-color); margin-bottom: 6px; padding-left: 42px;">
                  <span>📌 Pinned by Author</span>
                </div>
              <?php endif; ?>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <a href="/author/<?= urlencode($comm['user_username'] ?? (string) $comm['user_id']) ?>" style="text-decoration: none;">
                    <?php if (!empty($commAvatar)): ?>
                      <img src="<?= htmlspecialchars($commAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($comm['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                      <div style="display: none; width: 32px; height: 32px; border-radius: 50%; background: var(--primary-gradient); color: #fff; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">
                        <?= htmlspecialchars(mb_substr($comm['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php else: ?>
                      <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">
                        <?= htmlspecialchars(mb_substr($comm['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                  </a>
                  <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <a href="/author/<?= urlencode($comm['user_username'] ?? (string) $comm['user_id']) ?>" style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark); text-decoration: none;">
                      <?= htmlspecialchars($commHandle, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php if ($isAuthor): ?>
                      <span class="comment-role-tag author-tag">Author</span>
                    <?php elseif (!empty($comm['has_author_badge'])): ?>
                      <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                    <?php endif; ?>
                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= \InfinityBlog\Community\CommunityRepository::formatRelativeTime((string) $comm['created_at']) ?></span>
                  </div>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                  <?php if (!empty($currentUser)): ?>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="initReply(<?= $commId ?>, '<?= htmlspecialchars(addslashes($commHandle), ENT_QUOTES, 'UTF-8') ?>')">Reply</button>
                    <?php if ((int) $currentUser['id'] === (int) $comm['user_id'] || $currentUser['role'] === 'admin'): ?>
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem; color: #ef4444;" onclick="deleteComment(<?= $commId ?>)">Delete</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="reportComment(<?= $commId ?>)">Report</button>
                  <?php endif; ?>
                </div>
              </div>
              <div style="font-size: 0.92rem; color: var(--text-main); line-height: 1.5; padding-left: 42px; word-break: break-word;">
                <?= !empty($comm['rendered_content']) ? $comm['rendered_content'] : \InfinityBlog\Social\MentionService::renderMentionsToHtml($comm['content']) ?>
              </div>

              <!-- Action Bar (Reactions) -->
              <div style="display: flex; align-items: center; gap: 14px; padding-left: 42px; margin-top: 6px;">
                <button type="button" class="comment-action-btn btn-like <?= $commReaction === 'like' ? 'active' : '' ?>" id="inline-like-<?= $commId ?>" onclick="toggleCommentReaction(event, <?= $commId ?>, 'like')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="<?= $commReaction === 'like' ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                  <span id="like-count-<?= $commId ?>"><?= $commLikes > 0 ? $commLikes : '' ?></span>
                </button>
                <button type="button" class="comment-action-btn btn-dislike <?= $commReaction === 'dislike' ? 'active' : '' ?>" id="inline-dislike-<?= $commId ?>" onclick="toggleCommentReaction(event, <?= $commId ?>, 'dislike')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="<?= $commReaction === 'dislike' ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path></svg>
                </button>
              </div>

              <!-- Nested Replies with Curved Thread Lines -->
              <?php if (!empty($comm['replies'])): ?>
                <div class="comment-replies-list" style="margin-top: 14px; display: flex; flex-direction: column; gap: 10px;">
                  <?php foreach ($comm['replies'] as $reply): ?>
                    <?php
                      $replyId = (int) $reply['id'];
                      $replyHandle = \InfinityBlog\Support\UserIdentityHelper::formatHandle($reply['user_username'] ?? '', $reply['user_name'] ?? '');
                      $replyAvatar = \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($reply['user_avatar'] ?? null, $reply['user_name'] ?? '');
                    ?>
                    <div class="comment-item reply comment-reply-item" id="comment-<?= $replyId ?>">
                      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                          <a href="/author/<?= urlencode($reply['user_username'] ?? (string) $reply['user_id']) ?>" style="text-decoration: none;">
                            <?php if (!empty($replyAvatar)): ?>
                              <img src="<?= htmlspecialchars($replyAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($reply['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                              <div style="display: none; width: 28px; height: 28px; border-radius: 50%; background: var(--primary-gradient); color: #fff; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem;">
                                <?= htmlspecialchars(mb_substr($reply['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                              </div>
                            <?php else: ?>
                              <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem;">
                                <?= htmlspecialchars(mb_substr($reply['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                              </div>
                            <?php endif; ?>
                          </a>
                          <a href="/author/<?= urlencode($reply['user_username'] ?? (string) $reply['user_id']) ?>" style="font-weight: 700; font-size: 0.85rem; color: var(--text-dark); text-decoration: none;">
                            <?= htmlspecialchars($replyHandle, ENT_QUOTES, 'UTF-8') ?>
                          </a>
                          <?php if (!empty($reply['is_author'])): ?>
                            <span class="comment-role-tag author-tag">Author</span>
                          <?php elseif (!empty($reply['has_author_badge'])): ?>
                            <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                          <?php endif; ?>
                          <span style="font-size: 0.72rem; color: var(--text-muted);"><?= \InfinityBlog\Community\CommunityRepository::formatRelativeTime((string) $reply['created_at']) ?></span>
                        </div>
                        <?php if (!empty($currentUser) && ((int) $currentUser['id'] === (int) $reply['user_id'] || $currentUser['role'] === 'admin')): ?>
                          <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem; color: #ef4444;" onclick="deleteComment(<?= $replyId ?>)">Delete</button>
                        <?php endif; ?>
                      </div>
                      <div style="font-size: 0.9rem; color: var(--text-main); line-height: 1.45; padding-left: 36px; word-break: break-word;">
                        <?= !empty($reply['rendered_content']) ? $reply['rendered_content'] : \InfinityBlog\Social\MentionService::renderMentionsToHtml($reply['content']) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

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
              if (window.setRatingBadgeUI) {
                window.setRatingBadgeUI('reader-score-summary', data.data.average, data.data.count);
              }
              feedbackMsg.innerHTML = `<span style="color: #16a34a; font-weight: 600;">You scored this article ${currentScore} / 5 ★ (updated!)</span>`;
              if (typeof setComposerScore === 'function') {
                setComposerScore(currentScore);
              }
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

  let currentReviewPage = <?= (int) ($reviewsPage ?? 1) ?>;
  const reviewPostId = <?= (int) $post['id'] ?>;
  let isEditingReview = <?= !empty($userReview) ? 'true' : 'false' ?>;

  function toggleReviewComposer(show) {
    const composer = document.getElementById('reviewComposerBox');
    if (!composer) return;
    if (typeof show === 'boolean') {
      composer.style.display = show ? 'block' : 'none';
    } else {
      composer.style.display = composer.style.display === 'none' ? 'block' : 'none';
    }
    if (composer.style.display === 'block') {
      composer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      document.getElementById('reviewContentInput')?.focus();
    }
  }

  function setComposerScore(score) {
    const input = document.getElementById('composerScoreInput');
    const label = document.getElementById('composerScoreLabel');
    const picker = document.getElementById('composerStarPicker');
    if (input) input.value = score;
    if (label) label.textContent = `${score} / 5 Stars`;
    if (picker) {
      picker.querySelectorAll('.star-btn').forEach(btn => {
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
  }

  function updateReviewCharCount(textarea) {
    const counter = document.getElementById('reviewCharCounter');
    if (counter && textarea) {
      const len = textarea.value.length;
      counter.textContent = `${len} / 5000`;
      if (len > 4800) {
        counter.style.color = '#ef4444';
      } else {
        counter.style.color = 'var(--text-muted)';
      }
    }
  }

  function initEditReview(reviewId, rating, content) {
    isEditingReview = true;
    const composer = document.getElementById('reviewComposerBox');
    const title = document.getElementById('composerTitle');
    const submitBtn = document.getElementById('submitReviewBtn');
    const contentInput = document.getElementById('reviewContentInput');

    if (title) title.textContent = 'Edit Your Review';
    if (submitBtn) submitBtn.textContent = 'Update Review';
    if (contentInput) {
      contentInput.value = content;
      updateReviewCharCount(contentInput);
    }
    setComposerScore(rating);
    toggleReviewComposer(true);
  }

  async function handleReviewSubmit(e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('submitReviewBtn');
    const feedback = document.getElementById('reviewFormFeedback');
    const contentInput = document.getElementById('reviewContentInput');
    const ratingInput = document.getElementById('composerScoreInput');

    const content = contentInput ? contentInput.value.trim() : '';
    const rating = ratingInput ? parseInt(ratingInput.value, 10) : 5;

    if (!content) {
      if (feedback) {
        feedback.style.display = 'block';
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#991b1b';
        feedback.style.border = '1px solid #fecaca';
        feedback.textContent = 'Please share your evaluation in the review text.';
      }
      contentInput?.focus();
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Submitting...';
    }

    try {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }

      const res = await fetch(`/api/posts/${reviewPostId}/reviews`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        },
        body: JSON.stringify({ rating, content })
      });

      const data = await res.json();
      if (res.ok && data.data) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#ecfdf5';
          feedback.style.color = '#065f46';
          feedback.style.border = '1px solid #a7f3d0';
          feedback.textContent = 'Your review has been successfully published!';
        }

        const avgNum = document.getElementById('score-average-num');
        const countLabel = document.getElementById('score-count-label');
        if (avgNum && data.data.score_summary?.average !== undefined) {
          avgNum.textContent = Number(data.data.score_summary.average).toFixed(1);
        }
        if (countLabel && data.data.score_summary?.count !== undefined) {
          const c = data.data.score_summary.count;
          countLabel.textContent = `${c} ${c === 1 ? 'score' : 'scores'}`;
        }

        setTimeout(() => {
          window.location.reload();
        }, 600);
      } else {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.style.border = '1px solid #fecaca';
          feedback.textContent = data.error?.message || 'Could not submit review.';
        }
        if (btn) {
          btn.disabled = false;
          btn.textContent = isEditingReview ? 'Update Review' : 'Submit Review';
        }
      }
    } catch (err) {
      if (feedback) {
        feedback.style.display = 'block';
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#991b1b';
        feedback.style.border = '1px solid #fecaca';
        feedback.textContent = 'Network error while submitting review.';
      }
      if (btn) {
        btn.disabled = false;
        btn.textContent = isEditingReview ? 'Update Review' : 'Submit Review';
      }
    }
  }

  async function deleteReviewItem(reviewId) {
    if (!confirm('Are you sure you want to delete this review?')) return;
    try {
      let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
        csrf = await window.BlogCMS.ensureCsrfToken();
      }

      const res = await fetch(`/api/reviews/${reviewId}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': csrf || ''
        }
      });

      const data = await res.json();
      if (res.ok && data.data?.deleted) {
        const card = document.getElementById(`review-card-${reviewId}`);
        if (card) {
          card.style.opacity = '0';
          card.style.transition = 'opacity 0.3s ease';
          setTimeout(() => {
            card.remove();
            const badge = document.getElementById('reviewsCountBadge');
            const newTotal = typeof data.data.total_reviews === 'number'
              ? data.data.total_reviews
              : Math.max(0, parseInt(badge?.textContent || '1', 10) - 1);
            if (badge) badge.textContent = newTotal;
            if (data.data.score_summary && window.setRatingBadgeUI) {
              window.setRatingBadgeUI('reader-score-summary', data.data.score_summary.average, data.data.score_summary.count);
            }
            if (newTotal === 0) {
              window.location.reload();
            }
          }, 300);
        } else {
          window.location.reload();
        }
      } else {
        alert(data.error?.message || 'Could not delete review.');
      }
    } catch (err) {
      alert('Network error while deleting review.');
    }
  }

  function toggleReviewMenu(e, reviewId) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    const menu = document.getElementById(`review-menu-${reviewId}`);
    if (!menu) return;
    const isShowing = menu.style.display === 'flex' || menu.style.display === 'block';
    document.querySelectorAll('.feed-menu-dropdown').forEach(m => m.style.display = 'none');
    if (!isShowing) {
      menu.style.display = 'block';
    }
  }

  async function loadMoreReviews(postId) {
    const btn = document.getElementById('loadMoreReviewsBtn');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Loading reviews...';
    }
    const nextPage = currentReviewPage + 1;
    try {
      const res = await fetch(`/api/posts/${postId}/reviews?page=${nextPage}&per_page=10`, {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (res.ok && data.data?.reviews) {
        currentReviewPage = nextPage;
        const stream = document.getElementById('reviewsStream');
        data.data.reviews.forEach(rev => {
          if (document.getElementById(`review-card-${rev.id}`)) return;
          const cardHtml = createReviewCardElement(rev);
          stream?.insertAdjacentHTML('beforeend', cardHtml);
        });
        if (currentReviewPage >= data.data.total_pages) {
          document.getElementById('loadMoreReviewsContainer')?.remove();
        } else if (btn) {
          btn.disabled = false;
          btn.textContent = 'Load More Reviews ↓';
        }
      } else {
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Retry Loading Reviews';
        }
      }
    } catch (e) {
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Retry Loading Reviews';
      }
    }
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function createReviewCardElement(rev) {
    const revId = rev.id;
    const authorInitial = (rev.display_name || rev.username || 'R').charAt(0).toUpperCase();
    let starsHtml = '';
    for (let s = 1; s <= 5; s++) {
      starsHtml += `<span style="color: ${s <= rev.rating ? '#f59e0b' : '#cbd5e1'};">★</span>`;
    }
    const badgeHtml = rev.has_author_badge ? '<span class="badge-author-earned" title="Verified Author">Author</span>' : '';
    const dateFormatted = rev.created_at ? rev.created_at.split(' ')[0] : '';
    const avatarHtml = rev.avatar_url
      ? `<img src="${escapeHtml(rev.avatar_url)}" alt="${escapeHtml(rev.display_name)}" class="review-avatar-img" width="38" height="38">`
      : `<div class="review-avatar-fallback">${escapeHtml(authorInitial)}</div>`;

    return `
      <div class="review-card" id="review-card-${revId}" data-review-id="${revId}">
        <div class="review-card-header">
          <div class="review-author-info">
            ${avatarHtml}
            <div class="review-meta-details">
              <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <a href="/author/${encodeURIComponent(rev.username)}" class="review-author-name">
                  ${escapeHtml(rev.display_name)}
                </a>
                ${badgeHtml}
              </div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <div class="review-stars-display" aria-label="Rating: ${rev.rating} of 5 stars">
                  ${starsHtml}
                  <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-dark); margin-left: 4px;">${rev.rating}/5</span>
                </div>
                <span style="color: var(--border-color);">·</span>
                <span class="review-date-timestamp">${escapeHtml(dateFormatted)}</span>
              </div>
            </div>
          </div>
          <div class="dropdown" style="position: relative;">
            <button type="button" class="review-menu-btn" onclick="toggleReviewMenu(event, ${revId})" aria-label="Review options">•••</button>
            <div class="feed-menu-dropdown glass-panel" id="review-menu-${revId}" style="display: none; position: absolute; right: 0; top: 100%; z-index: 10; min-width: 140px;">
              <button type="button" class="feed-menu-item" onclick="openReportReviewModal(${revId})">
                <span>🚩</span> Report Review
              </button>
            </div>
          </div>
        </div>
        <div class="review-content-body">
          ${escapeHtml(rev.content).replace(/\n/g, '<br>')}
        </div>
      </div>
    `;
  }
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

<!-- Resume Reading Toast Notification -->
<div id="resumeReadingToast" style="display: none; position: fixed; bottom: 24px; left: 24px; z-index: 9999; padding: 14px 20px; border-radius: 16px; background: rgba(255,255,255,0.96); backdrop-filter: blur(12px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); align-items: center; gap: 14px; border: 1px solid var(--primary-color);">
  <span style="font-size: 1.2rem;">⏱️</span>
  <div>
    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-dark);">Resume reading?</div>
    <div style="font-size: 0.78rem; color: var(--text-muted);">You previously left off at <span id="savedProgressPct">0%</span></div>
  </div>
  <button type="button" onclick="resumeSavedScroll()" class="btn btn-primary btn-sm btn-pill">Resume</button>
  <button type="button" onclick="dismissResumeToast()" style="border: none; background: transparent; cursor: pointer; color: var(--text-muted); font-weight: 700;">✕</button>
</div>

<!-- Article-Specific AI Assistant Drawer Backdrop & Aside -->
<div class="article-assistant-backdrop" id="assistantDrawerBackdrop" onclick="toggleAssistantDrawer()" aria-hidden="true"></div>

<aside class="article-assistant-drawer dr-strange-drawer" id="articleAssistantDrawer" role="dialog" aria-modal="true" aria-label="Dr. Strange — Mystical Reading Guide">
  <div class="assistant-drawer-header">
    <div class="assistant-header-info">
      <div class="assistant-bot-avatar mystical-portal-avatar" aria-hidden="true">
        <svg class="portal-ring-svg" width="36" height="36" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="20" r="18" stroke="url(#arcaneRingGlow)" stroke-width="2" stroke-dasharray="5 3" class="portal-outer-spin" />
          <circle cx="20" cy="20" r="13" stroke="#45D9F5" stroke-width="1.5" stroke-opacity="0.85" />
          <circle cx="20" cy="20" r="8" fill="url(#arcaneCoreFill)" />
          <path d="M20 5v4M20 31v4M5 20h4M31 20h4M9.5 9.5l3 3M27.5 27.5l3 3M30.5 9.5l-3 3M9.5 30.5l3-3" stroke="#FFB52E" stroke-width="1.2" stroke-linecap="round" />
          <defs>
            <linearGradient id="arcaneRingGlow" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
              <stop stop-color="#FFB52E" />
              <stop offset="0.35" stop-color="#FF6A21" />
              <stop offset="0.7" stop-color="#A61E36" />
              <stop offset="1" stop-color="#45D9F5" />
            </linearGradient>
            <radialGradient id="arcaneCoreFill" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(20 20) scale(8)">
              <stop stop-color="#542C83" />
              <stop offset="0.6" stop-color="#102F5C" />
              <stop offset="1" stop-color="#071426" />
            </radialGradient>
          </defs>
        </svg>
      </div>
      <div>
        <h4 class="assistant-title">Dr. Strange</h4>
        <span class="assistant-subtitle">Your mystical guide to knowledge.</span>
      </div>
    </div>
    <button type="button" class="assistant-close-btn" onclick="toggleAssistantDrawer()" aria-label="Close Dr. Strange Drawer" title="Close (Esc)">✕</button>
  </div>

  <div id="assistantChatStream" class="assistant-chat-stream" role="log" aria-live="polite">
    <div class="assistant-msg msg-intro">
      <span class="mystical-sparkle-icon" style="font-size: 1.25rem; flex-shrink: 0;" aria-hidden="true">✨</span>
      <div>
        Greetings, seeker. Ask me about this article, uncover its key ideas, or explore a concept in greater depth.
      </div>
    </div>
    <div class="assistant-quick-prompts">
      <button type="button" class="quick-prompt-chip" onclick="askQuickPrompt('Summarize this article.')">
        <span>📜</span> Summarize this article.
      </button>
      <button type="button" class="quick-prompt-chip" onclick="askQuickPrompt('Explain the main concepts.')">
        <span>🔮</span> Explain the main concepts.
      </button>
      <button type="button" class="quick-prompt-chip" onclick="askQuickPrompt('What are the key takeaways?')">
        <span>⚡</span> What are the key takeaways?
      </button>
      <button type="button" class="quick-prompt-chip" onclick="focusTopicSearchPrompt()">
        <span>🔍</span> Find the passage about a specific topic.
      </button>
    </div>
  </div>

  <div class="assistant-drawer-footer">
    <form id="assistantAskForm" onsubmit="handleAskAssistant(event)" class="assistant-input-form">
      <input type="text" id="assistantQuestionInput" class="assistant-input-field" placeholder="Consult Dr. Strange about this article..." required autocomplete="off" aria-label="Consult Dr. Strange about this article" maxlength="1000">
      <button type="submit" id="assistantAskBtn" class="assistant-send-btn" aria-label="Send Consultation Question" title="Send Question (Enter)">
        <span id="assistantAskBtnText">Consult</span>
        <svg class="send-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
        </svg>
      </button>
    </form>
  </div>
</aside>

<!-- Share Article Modal -->
<!-- Share Article Modal -->
<div class="modal-backdrop" id="share-article-modal" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle" style="display: none;">
  <div class="modal-card share-modal-card" style="max-width: 540px; width: 92%; max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <h3 id="shareModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
        <span>🔗</span> Share this Story
      </h3>
      <button type="button" class="alert-close-btn" onclick="closeShareModal()" style="font-size: 1.4rem;" aria-label="Close dialog">×</button>
    </div>

    <!-- Story Preview Snippet in Dialog -->
    <div class="share-dialog-story-preview" style="padding: 12px 14px; background: rgba(241, 245, 249, 0.7); border-radius: 12px; margin-bottom: 16px; border: 1px solid rgba(226, 232, 240, 0.8);">
      <div style="font-size: 0.78rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 2px;">Sharing Article</div>
      <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-dark); line-height: 1.35;"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></div>
      <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 3px;">by <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <!-- Section A: Send via Direct Message to Followed Contacts -->
    <div class="share-dialog-section share-dialog-section-dm" style="margin-bottom: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <label style="font-size: 0.86rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
          <span>✉️</span> Share with Followed Contacts
        </label>
        <span id="shareSelectedCount" style="font-size: 0.75rem; color: var(--primary-color); font-weight: 600;">0 selected</span>
      </div>

      <?php if (!empty($currentUser)): ?>
        <div style="position: relative; margin-bottom: 10px;">
          <input type="text" id="shareFollowingSearch" class="form-input" placeholder="Search contacts..." style="padding: 8px 12px 8px 32px; font-size: 0.85rem; border-radius: 20px; width: 100%;">
          <span style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 0.82rem; color: var(--text-muted); pointer-events: none;">🔍</span>
        </div>

        <div id="shareFollowingList" class="share-following-list" style="max-height: 160px; overflow-y: auto; border: 1px solid var(--glass-border); border-radius: 12px; padding: 6px; background: rgba(255,255,255,0.6); display: flex; flex-direction: column; gap: 4px;">
          <div id="shareFollowingLoading" style="text-align: center; padding: 16px; font-size: 0.85rem; color: var(--text-muted);">
            Loading contacts...
          </div>
        </div>

        <div style="margin-top: 10px;">
          <textarea id="shareDmNote" class="form-input" rows="2" placeholder="Add an optional personal note..." maxlength="500" style="font-size: 0.85rem; padding: 8px 12px; border-radius: 10px; resize: none;"></textarea>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
          <div id="shareDmFeedback" style="font-size: 0.8rem; font-weight: 600;"></div>
          <button type="button" id="shareSendDmBtn" class="btn btn-primary btn-sm btn-pill" onclick="sendArticleDirectMessage(<?= (int) $post['id'] ?>)" disabled>
            <span>✉️</span> Send Story
          </button>
        </div>
      <?php else: ?>
        <div style="padding: 14px; background: rgba(248, 250, 252, 0.9); border: 1px dashed var(--glass-border); border-radius: 12px; text-align: center;">
          <p style="margin: 0 0 8px; font-size: 0.85rem; color: var(--text-muted);">
            Sign in to share this story directly with writers and contacts you follow on Infinity Blog.
          </p>
          <a href="/login?redirect=<?= urlencode('/article/' . (int) $post['id']) ?>" class="btn btn-secondary btn-sm btn-pill">
            Sign In to Share Privately
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Divider -->
    <div style="display: flex; align-items: center; gap: 12px; margin: 18px 0 16px;">
      <div style="flex: 1; height: 1px; background: rgba(226, 232, 240, 0.9);"></div>
      <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.05em;">Or share externally</span>
      <div style="flex: 1; height: 1px; background: rgba(226, 232, 240, 0.9);"></div>
    </div>

    <!-- Section B: WhatsApp & External Social Platforms -->
    <?php
      $shareUrl = $canonicalUrl ?? (($baseUrl ?? '') . '/article/' . (int) $post['id']);
      $shareTitle = $post['title'];
      // WhatsApp sharing link format: https://wa.me/?text=... with title + space + canonical url
      $whatsappText = $shareTitle . "\n" . $shareUrl;
      $whatsappUrl = 'https://wa.me/?text=' . rawurlencode($whatsappText);
    ?>
    <div class="share-options-grid" style="margin: 0 0 16px;">
      <a href="<?= htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="share-option-btn share-option-whatsapp" id="shareWhatsAppBtn" style="background: rgba(37, 211, 102, 0.1); border-color: rgba(37, 211, 102, 0.3); color: #15803d;">
        <span style="font-size: 1.25rem;">💬</span> WhatsApp
      </a>
      <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($shareTitle) ?>" target="_blank" rel="noopener noreferrer" class="share-option-btn">
        <span style="font-size: 1.2rem;">🐦</span> Twitter / X
      </a>
      <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-option-btn">
        <span style="font-size: 1.2rem;">💼</span> LinkedIn
      </a>
      <a href="mailto:?subject=<?= urlencode('Thoughtful Article: ' . $shareTitle) ?>&body=<?= urlencode('Read this article on Infinity Blog: ' . $shareUrl) ?>" class="share-option-btn">
        <span style="font-size: 1.2rem;">✉️</span> Email
      </a>
    </div>

    <!-- Copy Link Bar -->
    <div style="margin-top: 14px;">
      <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Story Canonical Link</label>
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
window.__currentArticleId = <?= (int) $post['id'] ?>;
document.addEventListener('DOMContentLoaded', () => {
  const speechData = <?= json_encode($speechData ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const synth = ('speechSynthesis' in window) ? window.speechSynthesis : null;
  const bodyContent = document.getElementById('articleBodyContent');
  const summaryPane = document.getElementById('summaryModePane');
  const audioPlayer = document.getElementById('articleAudioPlayer');
  const audioEl = document.getElementById('html5AudioElement');
  const sourceBadgeEl = document.getElementById('audioSourceBadge');
  const stateBadgeEl = document.getElementById('audioPlaybackStateBadge');
  const closeBtn = document.getElementById('audioCloseBtn');
  const waveformContainer = document.getElementById('audioWaveformVisualizer');
  const waveformBars = document.querySelectorAll('#audioWaveformVisualizer .waveform-bar');
  const playPauseBtn = document.getElementById('audioPlayPauseBtn');
  const playIcon = document.getElementById('audioPlayIcon');
  const pauseIcon = document.getElementById('audioPauseIcon');
  const stopBtn = document.getElementById('audioStopBtn');
  const progressRange = document.getElementById('audioTimelineRange');
  const currentTimeEl = document.getElementById('audioCurrentTime');
  const totalDurationEl = document.getElementById('audioTotalDuration');
  const estimatedNoteEl = document.getElementById('audioEstimatedNote');
  const trackTitleEl = document.getElementById('audioTrackTitle');
  const alertEl = document.getElementById('audioPlayerMessage');
  const speedBtns = document.querySelectorAll('.audio-speed-btn');

  // Playback state variables
  let audioState = 'idle'; // 'idle' | 'preparing' | 'playing' | 'paused' | 'stopped' | 'completed' | 'error'
  let currentAudioMode = 'full'; // 'full' | 'summary'
  let playbackType = (!emptyServerAudio() ? 'html5' : 'synth');
  let speechRate = 1.0;
  let chunks = [];
  let chunkOffsets = [];
  let totalChars = 0;
  let chunkIdx = 0;
  let timerInterval = null;
  let elapsedSec = 0;
  let baseDurationSec = Math.max(10, parseInt(speechData?.duration_seconds_est, 10) || 180);
  let totalEstimatedSec = baseDurationSec;
  let lastBoundaryPct = 0;

  function emptyServerAudio() {
    return !(speechData && speechData.has_server_audio && speechData.audio_url);
  }

  function formatTime(s) {
    if (!s || isNaN(s) || s < 0) s = 0;
    const mins = Math.floor(s / 60);
    const secs = Math.floor(s % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }

  // State Machine Updater
  function setAudioState(newState, message = '') {
    audioState = newState;

    if (stateBadgeEl) {
      stateBadgeEl.className = 'audio-state-badge state-' + newState;
      stateBadgeEl.textContent = newState.charAt(0).toUpperCase() + newState.slice(1);
    }

    if (playPauseBtn && playIcon && pauseIcon) {
      if (newState === 'playing') {
        playIcon.style.display = 'none';
        pauseIcon.style.display = 'block';
        playPauseBtn.setAttribute('aria-label', 'Pause audio');
      } else {
        playIcon.style.display = 'block';
        pauseIcon.style.display = 'none';
        playPauseBtn.setAttribute('aria-label', 'Play audio');
      }
    }

    if (waveformContainer) {
      if (newState === 'playing') {
        waveformContainer.classList.add('is-playing');
        waveformContainer.classList.remove('is-paused');
      } else if (newState === 'paused') {
        waveformContainer.classList.remove('is-playing');
        waveformContainer.classList.add('is-paused');
      } else if (newState === 'stopped' || newState === 'idle') {
        waveformContainer.classList.remove('is-playing', 'is-paused');
        updateWaveformBars(0);
      } else if (newState === 'completed') {
        waveformContainer.classList.remove('is-playing', 'is-paused');
        updateWaveformBars(100);
      } else if (newState === 'error') {
        waveformContainer.classList.remove('is-playing', 'is-paused');
      }
    }

    if (alertEl) {
      if (newState === 'error' && message) {
        alertEl.textContent = message;
        alertEl.style.display = 'block';
      } else {
        alertEl.style.display = 'none';
      }
    }
  }

  function updateWaveformBars(pct) {
    pct = Math.min(100, Math.max(0, pct));
    const totalBars = waveformBars.length;
    const activeBars = Math.round((pct / 100) * totalBars);

    waveformBars.forEach((bar, idx) => {
      if (idx < activeBars) {
        bar.classList.add('played');
      } else {
        bar.classList.remove('played');
      }
    });

    if (waveformContainer) {
      waveformContainer.setAttribute('aria-valuenow', Math.round(pct));
    }
    if (progressRange) {
      progressRange.value = Math.round(pct);
      progressRange.setAttribute('aria-valuenow', Math.round(pct));
    }
  }

  function updateProgressUI(pct) {
    updateWaveformBars(pct);
    if (currentTimeEl) {
      currentTimeEl.textContent = formatTime(Math.floor(elapsedSec));
    }
  }

  function splitIntoSentenceChunks(text) {
    if (!text) return;
    chunks = [];
    chunkOffsets = [];
    let runningChars = 0;
    const sentences = text.replace(/([.?!])\s+/g, "$1\u0000").split("\u0000");
    let buffer = "";

    for (const s of sentences) {
      if ((buffer + " " + s).trim().length > 140) {
        if (buffer.trim()) {
          const chunkStr = buffer.trim();
          chunks.push(chunkStr);
          chunkOffsets.push(runningChars);
          runningChars += chunkStr.length;
        }
        buffer = s;
      } else {
        buffer += (buffer ? " " : "") + s;
      }
    }
    if (buffer.trim()) {
      const chunkStr = buffer.trim();
      chunks.push(chunkStr);
      chunkOffsets.push(runningChars);
      runningChars += chunkStr.length;
    }
    totalChars = runningChars;
  }

  function updateSourceBadge(type) {
    if (!sourceBadgeEl) return;
    if (type === 'html5') {
      sourceBadgeEl.textContent = '☁️ Cloud Audio';
      sourceBadgeEl.style.color = '#1d4ed8';
      if (estimatedNoteEl) estimatedNoteEl.textContent = 'Cloud stream';
      if (totalDurationEl) totalDurationEl.textContent = formatTime(totalEstimatedSec);
    } else {
      sourceBadgeEl.textContent = currentAudioMode === 'summary'
        ? '⚡ Browser TTS (Summary)'
        : '⚡ Browser TTS';
      sourceBadgeEl.style.color = '#d97706';
      if (estimatedNoteEl) estimatedNoteEl.textContent = 'Spoken progress';
      if (totalDurationEl) totalDurationEl.textContent = '~' + formatTime(totalEstimatedSec);
    }
  }

  // HTML5 audio event bindings
  if (audioEl) {
    audioEl.addEventListener('loadedmetadata', () => {
      if (playbackType === 'html5' && audioEl.duration && !isNaN(audioEl.duration)) {
        totalEstimatedSec = Math.round(audioEl.duration);
        if (totalDurationEl) totalDurationEl.textContent = formatTime(totalEstimatedSec);
      }
    });

    audioEl.addEventListener('timeupdate', () => {
      if (playbackType === 'html5' && audioState === 'playing' && audioEl.duration) {
        elapsedSec = audioEl.currentTime;
        const pct = (audioEl.currentTime / audioEl.duration) * 100;
        updateProgressUI(pct);
      }
    });

    audioEl.addEventListener('ended', () => {
      if (playbackType === 'html5') {
        finishPlayback();
      }
    });

    audioEl.addEventListener('error', (e) => {
      console.warn('Cloud audio stream unavailable, falling back to browser speech synthesis:', e);
      if (audioState === 'playing' || audioState === 'preparing') {
        playbackType = 'synth';
        updateSourceBadge('synth');
        startSpeechSynthesis(currentAudioMode);
      }
    });
  }

  function startTimer() {
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
      if (audioState !== 'playing') return;
      elapsedSec += 0.2;
      const expectedPct = totalEstimatedSec > 0 ? (elapsedSec / totalEstimatedSec) * 100 : 0;
      const blendedPct = Math.min(99.5, Math.max(lastBoundaryPct, expectedPct));
      updateProgressUI(blendedPct);
    }, 200);
  }

  function playNextChunk() {
    if (audioState !== 'playing' || playbackType !== 'synth') return;
    if (chunkIdx >= chunks.length) {
      finishPlayback();
      return;
    }

    const chunkText = chunks[chunkIdx];
    const utt = new SpeechSynthesisUtterance(chunkText);
    utt.rate = speechRate;

    utt.onboundary = (e) => {
      if (audioState !== 'playing') return;
      const charOffset = (chunkOffsets[chunkIdx] || 0) + (e.charIndex || 0);
      const pct = totalChars > 0 ? (charOffset / totalChars) * 100 : 0;
      lastBoundaryPct = pct;
      const blendedPct = Math.min(99.5, Math.max(lastBoundaryPct, (elapsedSec / totalEstimatedSec) * 100));
      updateProgressUI(blendedPct);
    };

    utt.onend = () => {
      if (audioState !== 'playing') return;
      chunkIdx++;
      if (chunkIdx < chunks.length) {
        const pct = totalChars > 0 ? (chunkOffsets[chunkIdx] / totalChars) * 100 : 0;
        lastBoundaryPct = pct;
        updateProgressUI(pct);
        playNextChunk();
      } else {
        finishPlayback();
      }
    };

    utt.onerror = (e) => {
      if (e.error === 'interrupted' || e.error === 'canceled') return;
      console.warn('Speech chunk warning:', e);
      chunkIdx++;
      if (chunkIdx < chunks.length) {
        playNextChunk();
      } else {
        finishPlayback();
      }
    };

    if (synth) synth.speak(utt);
  }

  function finishPlayback() {
    clearInterval(timerInterval);
    elapsedSec = totalEstimatedSec;
    setAudioState('completed');
    if (currentTimeEl) currentTimeEl.textContent = formatTime(totalEstimatedSec);
    updateWaveformBars(100);
  }

  function startSpeechSynthesis(mode) {
    if (!synth) {
      setAudioState('error', 'Browser speech synthesis is not supported in this browser.');
      return;
    }

    playbackType = 'synth';
    const textToSpeak = mode === 'summary' ? (speechData.summary_text || speechData.full_text) : speechData.full_text;
    if (!textToSpeak || !textToSpeak.trim()) {
      setAudioState('error', 'No text content available to read.');
      return;
    }

    splitIntoSentenceChunks(textToSpeak);
    if (chunks.length === 0) {
      setAudioState('error', 'Could not parse article sentences for audio.');
      return;
    }

    const words = textToSpeak.trim().split(/\s+/).length;
    baseDurationSec = mode === 'summary'
      ? Math.max(10, Math.round((words / 140) * 60))
      : Math.max(10, parseInt(speechData.duration_seconds_est, 10) || Math.round((words / 140) * 60));
    totalEstimatedSec = Math.max(10, Math.round(baseDurationSec / speechRate));

    updateSourceBadge('synth');

    if (trackTitleEl) {
      trackTitleEl.textContent = mode === 'summary'
        ? 'Listening to: Article Summary'
        : 'Listening to: Full Article (Browser TTS)';
    }

    setAudioState('preparing');
    if (synth.speaking || synth.pending) {
      synth.cancel();
    }

    chunkIdx = 0;
    elapsedSec = 0;
    lastBoundaryPct = 0;
    updateProgressUI(0);

    setAudioState('playing');
    startTimer();
    playNextChunk();
  }

  function startAudio(mode = 'full') {
    // Duplicate session prevention: if already playing the same mode, do not restart
    if (audioState === 'playing' && currentAudioMode === mode) {
      return;
    }
    // If paused in the same mode, resume smoothly
    if (audioState === 'paused' && currentAudioMode === mode) {
      resumeAudio();
      return;
    }

    stopAudio(false);
    currentAudioMode = mode;

    const hasServerAudio = (mode === 'full' && speechData && speechData.has_server_audio && speechData.audio_url);

    if (hasServerAudio && audioEl) {
      playbackType = 'html5';
      totalEstimatedSec = parseInt(speechData.duration_seconds_est, 10) || 180;
      updateSourceBadge('html5');

      if (trackTitleEl) {
        trackTitleEl.textContent = 'Listening to: Full Article (Cloud Audio)';
      }
      if (audioEl.src !== speechData.audio_url) {
        audioEl.src = speechData.audio_url;
      }
      audioEl.playbackRate = speechRate;
      setAudioState('preparing');

      audioEl.play().then(() => {
        setAudioState('playing');
      }).catch(err => {
        console.warn('HTML5 cloud playback failed; falling back to SpeechSynthesis:', err);
        startSpeechSynthesis(mode);
      });
    } else {
      startSpeechSynthesis(mode);
    }
  }

  function pauseAudio() {
    if (audioState !== 'playing') return;
    clearInterval(timerInterval);

    if (playbackType === 'html5' && audioEl) {
      audioEl.pause();
    } else if (synth) {
      synth.pause();
    }
    setAudioState('paused');
  }

  function resumeAudio() {
    if (audioState !== 'paused') return;
    setAudioState('playing');

    if (playbackType === 'html5' && audioEl) {
      audioEl.play().catch(() => {});
    } else if (synth) {
      synth.resume();
      // Workaround for Chromium pause stall: if synthesis is not speaking after 120ms, restart chunk
      setTimeout(() => {
        if (audioState === 'playing' && !synth.speaking) {
          playNextChunk();
        }
      }, 120);
      startTimer();
    }
  }

  function togglePlayPause() {
    if (audioState === 'idle' || audioState === 'stopped' || audioState === 'completed' || audioState === 'error') {
      startAudio(currentAudioMode);
    } else if (audioState === 'playing') {
      pauseAudio();
    } else if (audioState === 'paused') {
      resumeAudio();
    }
  }

  function stopAudio(resetUI = true) {
    if (audioEl) {
      audioEl.pause();
      audioEl.currentTime = 0;
    }
    if (synth) {
      synth.cancel();
    }
    clearInterval(timerInterval);
    chunkIdx = 0;
    elapsedSec = 0;
    lastBoundaryPct = 0;

    if (resetUI) {
      setAudioState('stopped');
      if (currentTimeEl) currentTimeEl.textContent = '0:00';
      updateWaveformBars(0);
    }
  }

  function seekToPct(pct) {
    pct = Math.min(100, Math.max(0, pct));
    if (playbackType === 'html5' && audioEl && audioEl.duration) {
      audioEl.currentTime = (pct / 100) * audioEl.duration;
      elapsedSec = audioEl.currentTime;
    } else if (playbackType === 'synth' && chunks.length > 0) {
      const targetChar = (pct / 100) * totalChars;
      let targetIdx = 0;
      for (let i = 0; i < chunkOffsets.length; i++) {
        if (chunkOffsets[i] <= targetChar) {
          targetIdx = i;
        } else {
          break;
        }
      }
      chunkIdx = targetIdx;
      elapsedSec = (pct / 100) * totalEstimatedSec;
      lastBoundaryPct = pct;

      if (audioState === 'playing') {
        synth.cancel();
        playNextChunk();
      } else if (audioState === 'paused') {
        synth.cancel();
      }
    }
    updateProgressUI(pct);
  }

  // Waveform click & keyboard seek bindings
  if (waveformContainer) {
    waveformContainer.addEventListener('click', (e) => {
      const rect = waveformContainer.getBoundingClientRect();
      if (rect.width > 0) {
        const clickX = e.clientX - rect.left;
        const pct = (clickX / rect.width) * 100;
        seekToPct(pct);
      }
    });

    waveformContainer.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') {
        e.preventDefault();
        const currentPct = parseFloat(waveformContainer.getAttribute('aria-valuenow')) || 0;
        seekToPct(Math.min(100, currentPct + 5));
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        const currentPct = parseFloat(waveformContainer.getAttribute('aria-valuenow')) || 0;
        seekToPct(Math.max(0, currentPct - 5));
      }
    });
  }

  if (progressRange) {
    progressRange.addEventListener('input', () => {
      seekToPct(parseInt(progressRange.value, 10));
    });
  }

  if (playPauseBtn) playPauseBtn.addEventListener('click', togglePlayPause);
  if (stopBtn) stopBtn.addEventListener('click', () => stopAudio(true));

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      stopAudio(true);
      if (audioPlayer) audioPlayer.style.display = 'none';
      if (tabReadFull) tabReadFull.click();
    });
  }

  // Speed selector
  speedBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      speedBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      speechRate = parseFloat(btn.getAttribute('data-rate')) || 1.0;
      totalEstimatedSec = Math.max(10, Math.round(baseDurationSec / speechRate));

      if (totalDurationEl) {
        totalDurationEl.textContent = (playbackType === 'synth' ? '~' : '') + formatTime(totalEstimatedSec);
      }
      if (audioEl) {
        audioEl.playbackRate = speechRate;
      }
      if (playbackType === 'synth' && audioState === 'playing') {
        synth.cancel();
        playNextChunk();
      }
    });
  });

  // Reading mode tabs
  const readerTabs = document.querySelectorAll('.reader-mode-tabs .reader-tab-btn[data-mode]');
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
      stopAudio(true);

      const target = document.getElementById('articleBodyContent') || document.querySelector('.article-main-card');
      target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (tabReadSummary) {
    tabReadSummary.addEventListener('click', () => {
      setActiveReaderTab(tabReadSummary);
      if (bodyContent) bodyContent.style.display = 'none';
      if (summaryPane) summaryPane.style.display = 'block';
      if (audioPlayer) audioPlayer.style.display = 'none';
      stopAudio(true);
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

  // Cleanup on page navigate or close
  window.addEventListener('beforeunload', () => {
    if (audioEl) audioEl.pause();
    if (synth) synth.cancel();
  });
  window.addEventListener('pagehide', () => {
    if (audioEl) audioEl.pause();
    if (synth) synth.cancel();
  });

  // Reading Progress Bar
  const progressBar = document.getElementById('readingProgressBar');
  let lastProgressSave = 0;
  window.addEventListener('scroll', () => {
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    if (docHeight > 0) {
      const pct = Math.min(100, Math.max(0, Math.round((window.scrollY / docHeight) * 100)));
      if (progressBar) progressBar.style.width = pct + '%';

      const now = Date.now();
      if (now - lastProgressSave > 4000) {
        lastProgressSave = now;
        localStorage.setItem('inf_read_' + <?= (int) $post['id'] ?>, JSON.stringify({ pct, scroll: window.scrollY }));
        fetch('/api/library/progress/' + <?= (int) $post['id'] ?>, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ progress_percent: pct, scroll_position: window.scrollY })
        }).catch(() => {});
      }
    }
  });

  // Resume Reading Check
  const savedDataStr = localStorage.getItem('inf_read_' + <?= (int) $post['id'] ?>);
  if (savedDataStr) {
    try {
      const savedData = JSON.parse(savedDataStr);
      if (savedData.pct > 15 && savedData.pct < 95 && savedData.scroll > 200) {
        const toast = document.getElementById('resumeReadingToast');
        const pctSpan = document.getElementById('savedProgressPct');
        if (toast && pctSpan) {
          pctSpan.textContent = savedData.pct + '%';
          toast.style.display = 'flex';
          window._savedScrollPos = savedData.scroll;
        }
      }
    } catch (e) {}
  }

  // Restore saved text size preference
  const savedFontSize = localStorage.getItem('inf_article_font_size');
  if (savedFontSize) {
    const bodyEl = document.querySelector('.article-body') || document.getElementById('articleBodyContent');
    if (bodyEl) bodyEl.style.fontSize = savedFontSize;
  }

  // Keyboard shortcut: Escape exits focus mode and closes assistant drawer
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (document.body.classList.contains('distraction-free-mode')) {
        toggleFocusMode();
      }
      const drawer = document.getElementById('articleAssistantDrawer');
      if (drawer && drawer.classList.contains('open')) {
        toggleAssistantDrawer();
      }
    }
  });

  // Expose audio functions for programmatic access and automated testing
  window.startAudio = startAudio;
  window.stopAudio = stopAudio;
  window.pauseAudio = pauseAudio;
  window.resumeAudio = resumeAudio;
  window.togglePlayPause = togglePlayPause;
  window.seekToPct = seekToPct;
});

// Text Size Adjuster
let currentFontSizeMultiplier = 1;
function adjustArticleFontSize(step) {
  const bodyEl = document.querySelector('.article-body') || document.getElementById('articleBodyContent');
  if (!bodyEl) return;
  if (step === 0) {
    currentFontSizeMultiplier = 1;
    bodyEl.style.fontSize = '';
    localStorage.removeItem('inf_article_font_size');
  } else {
    currentFontSizeMultiplier = Math.max(0.85, Math.min(1.45, currentFontSizeMultiplier + (step * 0.1)));
    const newSize = (1.05 * currentFontSizeMultiplier).toFixed(2) + 'rem';
    bodyEl.style.fontSize = newSize;
    localStorage.setItem('inf_article_font_size', newSize);
  }
}

// Distraction-Free Focus Mode
function toggleFocusMode() {
  document.body.classList.toggle('distraction-free-mode');
  const isFocus = document.body.classList.contains('distraction-free-mode');
  const exitPill = document.getElementById('exitFocusModePill');
  if (exitPill) exitPill.style.display = isFocus ? 'block' : 'none';
  const focusBtn = document.getElementById('focusModeBtn');
  if (focusBtn) {
    focusBtn.classList.toggle('active', isFocus);
    focusBtn.setAttribute('aria-pressed', isFocus ? 'true' : 'false');
  }
}

// Resume Reading Handlers
function resumeSavedScroll() {
  if (window._savedScrollPos) {
    window.scrollTo({ top: window._savedScrollPos, behavior: 'smooth' });
  }
  dismissResumeToast();
}
function dismissResumeToast() {
  const toast = document.getElementById('resumeReadingToast');
  if (toast) toast.style.display = 'none';
}

// Bookmark Article Toggle
async function toggleBookmarkArticle(postId) {
  const btn = document.getElementById('bookmarkArticleBtn');
  const txt = document.getElementById('bookmarkBtnText');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const isCurrentlySaved = btn ? (btn.classList.contains('active') || btn.classList.contains('is-saved')) : false;
  const intendedAction = isCurrentlySaved ? 'unsave' : 'save';

  if (btn) btn.disabled = true;

  try {
    const res = await fetch('/api/library/bookmark/' + postId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ action: intendedAction })
    });
    if (res.ok) {
      const data = await res.json();
      const isBookmarked = Boolean(data.data?.is_bookmarked ?? data.data?.bookmarked ?? data.data?.saved);
      if (btn) {
        btn.classList.toggle('active', isBookmarked);
        btn.classList.toggle('is-saved', isBookmarked);
        btn.setAttribute('title', isBookmarked ? 'Saved to Personal Library' : 'Save to Personal Library');
        btn.setAttribute('aria-label', isBookmarked ? 'Saved to Personal Library' : 'Save to Personal Library');
      }
      if (txt) txt.textContent = isBookmarked ? 'Saved' : 'Save';
    } else {
      if (res.status === 401) {
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
      } else {
        alert('Could not update library.');
      }
    }
  } catch (e) {
    alert('Connection error.');
  } finally {
    if (btn) btn.disabled = false;
  }
}

// AI Assistant Drawer
function toggleAssistantDrawer() {
  const drawer = document.getElementById('articleAssistantDrawer');
  const backdrop = document.getElementById('assistantDrawerBackdrop');
  const btn = document.getElementById('openAssistantBtn');
  if (drawer) {
    drawer.classList.toggle('open');
    const isOpen = drawer.classList.contains('open');
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) {
      setTimeout(() => {
        const input = document.getElementById('assistantQuestionInput');
        if (input) input.focus();
      }, 150);
    } else {
      if (btn) btn.focus();
    }
  }
}

function focusTopicSearchPrompt() {
  const input = document.getElementById('assistantQuestionInput');
  if (input) {
    input.value = 'Find the passage about ';
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
  }
}

function askQuickPrompt(promptText) {
  const input = document.getElementById('assistantQuestionInput');
  if (input) {
    input.value = promptText;
    const form = document.getElementById('assistantAskForm');
    if (form) {
      form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  }
}

async function handleAskAssistant(e) {
  if (e) e.preventDefault();
  const input = document.getElementById('assistantQuestionInput');
  const q = input ? input.value.trim() : '';
  if (!q) return;

  const btn = document.getElementById('assistantAskBtn');
  const btnText = document.getElementById('assistantAskBtnText');
  if (btn) btn.disabled = true;
  if (btnText) btnText.textContent = 'Divining...';
  if (input) input.disabled = true;

  const chatStream = document.getElementById('assistantChatStream');

  // Render question bubble
  const qDiv = document.createElement('div');
  qDiv.className = 'assistant-msg msg-user';
  qDiv.textContent = q;
  chatStream.appendChild(qDiv);
  if (input) input.value = '';
  chatStream.scrollTo({ top: chatStream.scrollHeight, behavior: 'smooth' });

  // Render typing indicator
  const typingDiv = document.createElement('div');
  typingDiv.className = 'assistant-typing-indicator';
  typingDiv.id = 'assistantTypingPlaceholder';
  typingDiv.innerHTML = `<div class="mystical-pulse-orb" aria-hidden="true"></div><span>Divining the arcane archives...</span><div class="typing-dots"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>`;
  chatStream.appendChild(typingDiv);
  chatStream.scrollTo({ top: chatStream.scrollHeight, behavior: 'smooth' });

  try {
    const res = await fetch('/api/article/' + <?= (int) $post['id'] ?> + '/assistant', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ question: q })
    });

    typingDiv.remove();

    const data = await res.json();
    if (!res.ok) {
      const errMsg = data?.error?.message || 'Could not consult the archives. Please try again.';
      renderAssistantError(chatStream, q, errMsg);
      return;
    }

    const ans = data.data?.answer || 'No response available.';
    const pref = data.data?.passage_reference;

    const aDiv = document.createElement('div');
    aDiv.className = 'assistant-msg msg-assistant';

    let formattedAns = escapeHtml(ans).replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>');
    let html = `<div>${formattedAns}</div>`;
    if (pref) {
      html += `<div class="assistant-passage-ref"><span style="color:#fbbf24; font-weight:700;">✦ Arcane Passage:</span> "${escapeHtml(pref)}"</div>`;
    }
    aDiv.innerHTML = html;
    chatStream.appendChild(aDiv);
    chatStream.scrollTo({ top: chatStream.scrollHeight, behavior: 'smooth' });
  } catch (err) {
    const placeholder = document.getElementById('assistantTypingPlaceholder');
    if (placeholder) placeholder.remove();
    renderAssistantError(chatStream, q, 'Mystical connection disrupted. Please try again.');
  } finally {
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = 'Consult';
    if (input) {
      input.disabled = false;
      input.focus();
    }
  }
}

function renderAssistantError(chatStream, questionText, errorMessage) {
  const errCard = document.createElement('div');
  errCard.className = 'assistant-error-card';
  errCard.innerHTML = `
    <div style="font-weight: 700; display: flex; align-items: center; gap: 6px;">
      <span>🛡️</span> <span>Mystical Ward Encountered</span>
    </div>
    <div>${escapeHtml(errorMessage)}</div>
  `;

  const retryBtn = document.createElement('button');
  retryBtn.type = 'button';
  retryBtn.className = 'assistant-retry-btn';
  retryBtn.innerHTML = '<span>⚡</span> Retry Consultation';
  retryBtn.onclick = () => {
    errCard.remove();
    const input = document.getElementById('assistantQuestionInput');
    if (input) input.value = questionText;
    handleAskAssistant(new Event('submit'));
  };

  errCard.appendChild(retryBtn);
  chatStream.appendChild(errCard);
  chatStream.scrollTo({ top: chatStream.scrollHeight, behavior: 'smooth' });
}

// Comments Handlers
function initReply(commentId, authorName) {
  document.getElementById('replyParentId').value = commentId;
  const banner = document.getElementById('replyingToBanner');
  banner.querySelector('span').textContent = 'Replying to ' + authorName;
  banner.style.display = 'flex';
  const input = document.getElementById('commentContentInput');
  input.focus();
}
function cancelReply() {
  document.getElementById('replyParentId').value = '';
  document.getElementById('replyingToBanner').style.display = 'none';
}

async function handlePostComment(e) {
  e.preventDefault();
  const input = document.getElementById('commentContentInput');
  const content = input.value.trim();
  if (!content) return;

  const parentId = document.getElementById('replyParentId').value;
  const btn = document.getElementById('submitCommentBtn');
  btn.disabled = true;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch('/api/posts/' + <?= (int) $post['id'] ?> + '/comments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ content, parent_id: parentId || null })
    });
    if (res.ok) {
      window.location.reload();
    } else {
      const d = await res.json();
      alert(d.error?.message || 'Failed to post comment.');
    }
  } catch (e) {
    alert('Network error.');
  } finally {
    btn.disabled = false;
  }
}

async function deleteComment(commentId) {
  if (!confirm('Are you sure you want to delete this comment?')) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  try {
    const res = await fetch('/api/comments/' + commentId + '/delete', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf || '', 'Accept': 'application/json' }
    });
    if (res.ok) window.location.reload();
  } catch (e) {
    alert('Error deleting comment.');
  }
}

async function reportComment(commentId) {
  const reason = prompt('Please describe why this comment is abusive or inappropriate:');
  if (!reason) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  try {
    const res = await fetch('/api/comments/' + commentId + '/report', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ reason })
    });
    if (res.ok) {
      alert('Thank you. The comment has been flagged for moderation.');
    }
  } catch (e) {
    alert('Error reporting comment.');
  }
}

// Post Reporting Handlers
function openReportPostModal() {
  const modal = document.getElementById('report-article-modal');
  if (!modal) return;
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  const reasonSelect = document.getElementById('reportReasonSelect');
  if (reasonSelect) {
    reasonSelect.focus();
  }
}

function closeReportPostModal() {
  const modal = document.getElementById('report-article-modal');
  if (!modal) return;
  modal.style.display = 'none';
  document.body.style.overflow = '';
  const form = document.getElementById('reportPostForm');
  if (form) form.reset();
  const counter = document.getElementById('reportCharCounter');
  if (counter) counter.textContent = '0 / 500';
  const feedback = document.getElementById('reportFormFeedback');
  if (feedback) {
    feedback.style.display = 'none';
    feedback.textContent = '';
  }
}

function updateReportCharCount(textarea) {
  const counter = document.getElementById('reportCharCounter');
  if (counter && textarea) {
    const len = textarea.value.length;
    counter.textContent = `${len} / 500`;
    if (len > 450) {
      counter.style.color = '#ef4444';
    } else {
      counter.style.color = 'var(--text-muted)';
    }
  }
}

async function handleReportPostSubmit(event) {
  event.preventDefault();
  const btn = document.getElementById('reportSubmitBtn');
  const feedback = document.getElementById('reportFormFeedback');
  const reason = document.getElementById('reportReasonSelect')?.value;
  const explanation = document.getElementById('reportExplanationTextarea')?.value || '';
  const postId = <?= (int) $post['id'] ?>;

  if (!reason) {
    if (feedback) {
      feedback.style.display = 'block';
      feedback.style.background = '#fef2f2';
      feedback.style.color = '#991b1b';
      feedback.style.border = '1px solid #fecaca';
      feedback.textContent = 'Please select a reason for reporting.';
    }
    return;
  }

  if (explanation.length > 500) {
    if (feedback) {
      feedback.style.display = 'block';
      feedback.style.background = '#fef2f2';
      feedback.style.color = '#991b1b';
      feedback.style.border = '1px solid #fecaca';
      feedback.textContent = 'Explanation must not exceed 500 characters.';
    }
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Submitting...';
  }

  try {
    let csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrf && window.BlogCMS && typeof window.BlogCMS.ensureCsrfToken === 'function') {
      csrf = await window.BlogCMS.ensureCsrfToken();
    }

    const res = await fetch(`/api/posts/${postId}/report`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({
        reason: reason,
        explanation: explanation
      })
    });

    const data = await res.json();

    if (feedback) {
      feedback.style.display = 'block';
      if (res.ok && data.success) {
        if (data.duplicate) {
          feedback.style.background = '#eff6ff';
          feedback.style.color = '#1e40af';
          feedback.style.border = '1px solid #bfdbfe';
        } else {
          feedback.style.background = '#f0fdf4';
          feedback.style.color = '#166534';
          feedback.style.border = '1px solid #bbf7d0';
        }
        feedback.textContent = data.message || 'Report received. Thank you for helping keep our community safe.';

        if (btn) {
          btn.textContent = 'Report Submitted';
          btn.disabled = true;
        }

        setTimeout(() => {
          closeReportPostModal();
        }, 2200);
      } else {
        feedback.style.background = '#fef2f2';
        feedback.style.color = '#991b1b';
        feedback.style.border = '1px solid #fecaca';
        feedback.textContent = data.error?.message || data.message || 'Unable to submit report. Please try again.';
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Submit Report';
        }
      }
    }
  } catch (err) {
    if (feedback) {
      feedback.style.display = 'block';
      feedback.style.background = '#fef2f2';
      feedback.style.color = '#991b1b';
      feedback.style.border = '1px solid #fecaca';
      feedback.textContent = 'A network error occurred. Please check your connection.';
    }
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Submit Report';
    }
  }
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modal = document.getElementById('report-article-modal');
    if (modal && modal.style.display === 'flex') {
      closeReportPostModal();
    }
  }
});

const reportModalBackdrop = document.getElementById('report-article-modal');
if (reportModalBackdrop) {
  reportModalBackdrop.addEventListener('click', (e) => {
    if (e.target === reportModalBackdrop) {
      closeReportPostModal();
    }
  });
}
</script>
