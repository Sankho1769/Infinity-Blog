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
      <?php if (!empty($post['category_name'])): ?>
        <span class="badge badge-category" style="display: inline-flex; align-items: center; gap: 6px;">
          <?= \InfinityBlog\Categories\CategoryIconHelper::getIcon($post['category_slug'] ?? '', 14) ?>
          <span><?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </span>
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

    <!-- Reader Experience Toolbar (Reader Mode, Audio, Focus, Bookmark, AI) -->
    <div class="article-reader-toolbar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
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
        <button type="button" class="reader-tab-btn" id="focusModeBtn" onclick="toggleFocusMode()" title="Toggle Distraction-Free Reading Mode">
          <span>👁️</span> Focus Mode
        </button>
        <button type="button" class="reader-tab-btn <?= !empty($isBookmarked) ? 'active' : '' ?>" id="bookmarkArticleBtn" onclick="toggleBookmarkArticle(<?= (int) $post['id'] ?>)" title="Save to Personal Library">
          <span>🔖</span> <span id="bookmarkBtnText"><?= !empty($isBookmarked) ? 'Saved' : 'Save' ?></span>
        </button>
        <button type="button" class="reader-tab-btn" id="openAssistantBtn" onclick="toggleAssistantDrawer()" title="Ask Questions About Article">
          <span>🤖</span> Ask AI
        </button>
      </div>

      <div style="display: flex; gap: 10px; align-items: center;">
        <!-- Font Size Adjuster Controls -->
        <div style="display: inline-flex; align-items: center; gap: 4px; background: rgba(0,0,0,0.05); padding: 2px 8px; border-radius: 8px;">
          <button type="button" onclick="adjustArticleFontSize(-1)" style="border: none; background: transparent; font-size: 0.8rem; font-weight: 700; cursor: pointer; padding: 2px 6px; color: var(--text-dark);" title="Decrease Text Size">A-</button>
          <button type="button" onclick="adjustArticleFontSize(0)" style="border: none; background: transparent; font-size: 0.85rem; font-weight: 700; cursor: pointer; padding: 2px 4px; color: var(--text-dark);" title="Default Text Size">A</button>
          <button type="button" onclick="adjustArticleFontSize(1)" style="border: none; background: transparent; font-size: 0.95rem; font-weight: 700; cursor: pointer; padding: 2px 6px; color: var(--text-dark);" title="Increase Text Size">A+</button>
        </div>
        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
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
          <span id="audioTrackTitle">Listening to: Full Article</span>
        </div>
        <span class="audio-source-badge" id="audioSourceBadge">
          <?= !empty($speechData['has_server_audio']) ? '☁️ Stored Cloud Audio Stream (article-audio)' : '⚡ Browser Speech Synthesis (Local Fallback)' ?>
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
      <button type="button" class="btn btn-primary btn-sm btn-pill" id="shareArticleBtn" onclick="triggerNativeShare('<?= htmlspecialchars($canonicalUrl ?? (($baseUrl ?? '') . '/article/' . (int) $post['id']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($post['title']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($metaDescription ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
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
            <div class="comment-item" id="comment-<?= (int) $comm['id'] ?>">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">
                    <?= htmlspecialchars(mb_substr($comm['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <span style="font-weight: 700; font-size: 0.9rem; color: var(--text-dark);"><?= htmlspecialchars($comm['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
                  <span style="font-size: 0.75rem; color: var(--text-muted);"><?= date('M j, Y - g:i A', strtotime($comm['created_at'])) ?></span>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                  <?php if (!empty($currentUser)): ?>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="initReply(<?= (int) $comm['id'] ?>, '<?= htmlspecialchars(addslashes($comm['user_name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?>')">Reply</button>
                    <?php if ((int) $currentUser['id'] === (int) $comm['user_id'] || $currentUser['role'] === 'admin'): ?>
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem; color: #ef4444;" onclick="deleteComment(<?= (int) $comm['id'] ?>)">Delete</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="reportComment(<?= (int) $comm['id'] ?>)">Report</button>
                  <?php endif; ?>
                </div>
              </div>
              <div style="font-size: 0.92rem; color: var(--text-main); line-height: 1.5; padding-left: 42px;">
                <?= nl2br(htmlspecialchars($comm['content'], ENT_QUOTES, 'UTF-8')) ?>
              </div>

              <!-- Nested Replies -->
              <?php if (!empty($comm['replies'])): ?>
                <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 10px;">
                  <?php foreach ($comm['replies'] as $reply): ?>
                    <div class="comment-item reply" id="comment-<?= (int) $reply['id'] ?>">
                      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                          <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem;">
                            <?= htmlspecialchars(mb_substr($reply['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                          </div>
                          <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-dark);"><?= htmlspecialchars($reply['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
                          <span style="font-size: 0.72rem; color: var(--text-muted);"><?= date('M j, Y - g:i A', strtotime($reply['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($currentUser) && ((int) $currentUser['id'] === (int) $reply['user_id'] || $currentUser['role'] === 'admin')): ?>
                          <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem; color: #ef4444;" onclick="deleteComment(<?= (int) $reply['id'] ?>)">Delete</button>
                        <?php endif; ?>
                      </div>
                      <div style="font-size: 0.9rem; color: var(--text-main); line-height: 1.45; padding-left: 36px;">
                        <?= nl2br(htmlspecialchars($reply['content'], ENT_QUOTES, 'UTF-8')) ?>
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

    <!-- Article-Specific AI Assistant Drawer -->
    <aside class="article-assistant-drawer" id="articleAssistantDrawer">
      <div style="padding: 18px 20px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.5);">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="font-size: 1.3rem;">🤖</span>
          <div>
            <h4 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-dark);">Article AI Assistant</h4>
            <span style="font-size: 0.72rem; color: var(--text-muted);">Contextual Q&A strictly from this article</span>
          </div>
        </div>
        <button type="button" onclick="toggleAssistantDrawer()" style="border: none; background: transparent; font-size: 1.3rem; cursor: pointer; color: var(--text-dark);">✕</button>
      </div>

      <div id="assistantChatStream" style="flex: 1; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 14px;">
        <div style="padding: 12px 14px; border-radius: 12px; background: rgba(37, 99, 235, 0.08); font-size: 0.85rem; color: var(--text-dark); line-height: 1.45;">
          👋 Hi! Ask any question about <strong>"<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"</strong>. I will answer concisely using only facts from this article and provide passage citations.
        </div>
      </div>

      <div style="padding: 14px 18px; border-top: 1px solid var(--glass-border); background: rgba(255,255,255,0.85);">
        <form id="assistantAskForm" onsubmit="handleAskAssistant(event)" style="display: flex; gap: 8px;">
          <input type="text" id="assistantQuestionInput" class="form-input" placeholder="Ask about this article..." required autocomplete="off" style="flex: 1; font-size: 0.88rem; border-radius: 20px; padding: 8px 14px;">
          <button type="submit" id="assistantAskBtn" class="btn btn-primary btn-sm btn-pill" style="padding: 8px 16px;">
            Ask
          </button>
        </form>
      </div>
    </aside>

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
      $shareUrl = $canonicalUrl ?? (($baseUrl ?? '') . '/article/' . (int) $post['id']);
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
  const audioEl = document.getElementById('html5AudioElement');
  const badgeEl = document.getElementById('audioSourceBadge');
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
  let playbackType = 'synth'; // 'html5' or 'synth'
  let isPlaying = false;
  let isPaused = false;
  let speechRate = 1.0;
  let chunks = [];
  let chunkIdx = 0;
  let timerInterval = null;
  let elapsedSec = 0;
  let totalEstimatedSec = speechData?.duration_seconds_est || 180;

  function formatTime(s) {
    if (!s || isNaN(s) || s < 0) s = 0;
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

  function updateBadge(type) {
    if (!badgeEl) return;
    if (type === 'html5') {
      badgeEl.textContent = '☁️ Stored Cloud Audio Stream (article-audio)';
      badgeEl.style.color = '#1d4ed8';
    } else {
      badgeEl.textContent = currentAudioMode === 'summary'
        ? '⚡ Browser Speech Synthesis (Summary)'
        : '⚡ Browser Speech Synthesis (Local Fallback)';
      badgeEl.style.color = '#d97706';
    }
  }

  // HTML5 audio element event bindings
  if (audioEl) {
    audioEl.addEventListener('loadedmetadata', () => {
      if (playbackType === 'html5' && audioEl.duration && totalDurationEl) {
        totalDurationEl.textContent = formatTime(audioEl.duration);
      }
    });

    audioEl.addEventListener('timeupdate', () => {
      if (playbackType === 'html5' && !isPaused && isPlaying) {
        if (currentTimeEl) currentTimeEl.textContent = formatTime(audioEl.currentTime);
        if (progressRange && audioEl.duration) {
          progressRange.value = Math.min(100, Math.round((audioEl.currentTime / audioEl.duration) * 100));
        }
      }
    });

    audioEl.addEventListener('ended', () => {
      stopAudio();
    });

    audioEl.addEventListener('error', (e) => {
      console.warn('Stored audio stream error, falling back to browser speech synthesis:', e);
      if (isPlaying) {
        playbackType = 'synth';
        updateBadge('synth');
        startSpeechSynthesis(currentAudioMode);
      }
    });
  }

  function stopAudio() {
    if (audioEl) {
      audioEl.pause();
      audioEl.currentTime = 0;
    }
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
    if (!isPlaying || playbackType !== 'synth') return;
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

  function startSpeechSynthesis(mode) {
    if (!synth) {
      alert('Speech synthesis is not supported in this browser.');
      return;
    }
    playbackType = 'synth';
    updateBadge('synth');

    const textToSpeak = mode === 'summary' ? (speechData.summary_text || speechData.full_text) : speechData.full_text;
    chunks = splitIntoChunks(textToSpeak);
    if (chunks.length === 0) return;

    totalEstimatedSec = mode === 'summary'
      ? Math.max(10, Math.round(((speechData.summary_text || '').split(/\s+/).length / 140) * 60))
      : (speechData.duration_seconds_est || 180);

    if (totalDurationEl) totalDurationEl.textContent = formatTime(totalEstimatedSec);
    if (trackTitleEl) {
      trackTitleEl.textContent = mode === 'summary'
        ? 'Listening to: Article Summary'
        : 'Listening to: Full Article (Browser TTS)';
    }

    isPlaying = true;
    isPaused = false;
    if (playIcon) playIcon.style.display = 'none';
    if (pauseIcon) pauseIcon.style.display = 'block';

    timerInterval = setInterval(() => {
      if (isPlaying && !isPaused && playbackType === 'synth') {
        elapsedSec += 1;
        if (currentTimeEl) currentTimeEl.textContent = formatTime(elapsedSec);
      }
    }, 1000);

    playNextChunk();
  }

  function startAudio(mode = 'full') {
    stopAudio();
    currentAudioMode = mode;

    // Check if persistent server-generated audio is available for full-article
    const hasServerAudio = (mode === 'full' && speechData && speechData.has_server_audio && speechData.audio_url);

    if (hasServerAudio && audioEl) {
      playbackType = 'html5';
      updateBadge('html5');
      if (trackTitleEl) trackTitleEl.textContent = 'Listening to: Full Article (Cloud Audio)';
      if (audioEl.src !== speechData.audio_url) {
        audioEl.src = speechData.audio_url;
      }
      audioEl.playbackRate = speechRate;
      if (totalDurationEl && speechData.duration_seconds_est) {
        totalDurationEl.textContent = formatTime(speechData.duration_seconds_est);
      }

      isPlaying = true;
      isPaused = false;
      if (playIcon) playIcon.style.display = 'none';
      if (pauseIcon) pauseIcon.style.display = 'block';

      audioEl.play().catch(err => {
        console.warn('HTML5 cloud playback failed; falling back to SpeechSynthesis:', err);
        startSpeechSynthesis(mode);
      });
    } else {
      startSpeechSynthesis(mode);
    }
  }

  function togglePlayPause() {
    if (!isPlaying) {
      startAudio(currentAudioMode);
      return;
    }

    if (playbackType === 'html5' && audioEl) {
      if (isPaused) {
        audioEl.play();
        isPaused = false;
        if (playIcon) playIcon.style.display = 'none';
        if (pauseIcon) pauseIcon.style.display = 'block';
      } else {
        audioEl.pause();
        isPaused = true;
        if (playIcon) playIcon.style.display = 'block';
        if (pauseIcon) pauseIcon.style.display = 'none';
      }
    } else {
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
  }

  if (playPauseBtn) playPauseBtn.addEventListener('click', togglePlayPause);
  if (stopBtn) stopBtn.addEventListener('click', stopAudio);

  speedBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      speedBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      speechRate = parseFloat(btn.getAttribute('data-rate')) || 1.0;
      if (audioEl) {
        audioEl.playbackRate = speechRate;
      }
      if (playbackType === 'synth' && isPlaying && !isPaused) {
        synth.cancel();
        playNextChunk();
      }
    });
  });

  if (progressRange) {
    progressRange.addEventListener('input', () => {
      if (playbackType === 'html5' && audioEl && audioEl.duration) {
        audioEl.currentTime = (parseInt(progressRange.value, 10) / 100) * audioEl.duration;
      } else if (playbackType === 'synth' && chunks.length > 0) {
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

      // Save reading progress every 5 seconds or on major scroll
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
    const bodyEl = document.querySelector('.article-body');
    if (bodyEl) bodyEl.style.fontSize = savedFontSize;
  }
});

// Text Size Adjuster
let currentFontSizeMultiplier = 1;
function adjustArticleFontSize(step) {
  const bodyEl = document.querySelector('.article-body');
  if (!bodyEl) return;
  if (step === 0) {
    currentFontSizeMultiplier = 1;
    bodyEl.style.fontSize = '';
    localStorage.removeItem('inf_article_font_size');
  } else {
    currentFontSizeMultiplier = Math.max(0.85, Math.min(1.4, currentFontSizeMultiplier + (step * 0.1)));
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
  if (btn) btn.disabled = true;

  try {
    const res = await fetch('/api/library/bookmark/' + postId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      }
    });
    if (res.ok) {
      const data = await res.json();
      const isBookmarked = data.data?.is_bookmarked;
      if (btn) btn.classList.toggle('active', isBookmarked);
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
  if (drawer) drawer.classList.toggle('open');
}

async function handleAskAssistant(e) {
  e.preventDefault();
  const input = document.getElementById('assistantQuestionInput');
  const q = input.value.trim();
  if (!q) return;

  const btn = document.getElementById('assistantAskBtn');
  btn.disabled = true;
  btn.textContent = 'Thinking...';
  const chatStream = document.getElementById('assistantChatStream');

  // Render question
  const qDiv = document.createElement('div');
  qDiv.style.alignSelf = 'flex-end';
  qDiv.style.background = 'var(--primary-gradient)';
  qDiv.style.color = '#fff';
  qDiv.style.padding = '10px 14px';
  qDiv.style.borderRadius = '14px';
  qDiv.style.fontSize = '0.88rem';
  qDiv.style.maxWidth = '85%';
  qDiv.textContent = q;
  chatStream.appendChild(qDiv);
  input.value = '';
  chatStream.scrollTop = chatStream.scrollHeight;

  try {
    const res = await fetch('/api/article/' + <?= (int) $post['id'] ?> + '/assistant', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ question: q })
    });
    const data = await res.json();
    const ans = data.data?.answer || 'I could not find an answer in this article.';
    const pref = data.data?.passage_reference;

    const aDiv = document.createElement('div');
    aDiv.style.background = '#fff';
    aDiv.style.padding = '12px 14px';
    aDiv.style.borderRadius = '14px';
    aDiv.style.border = '1px solid var(--glass-border)';
    aDiv.style.fontSize = '0.88rem';
    aDiv.style.color = 'var(--text-dark)';
    aDiv.style.lineHeight = '1.45';
    aDiv.style.maxWidth = '90%';

    let html = `<div>${escapeHtml(ans)}</div>`;
    if (pref) {
      html += `<div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(0,0,0,0.1); font-size: 0.78rem; color: var(--text-muted); font-style: italic;">Reference: "${escapeHtml(pref)}"</div>`;
    }
    aDiv.innerHTML = html;
    chatStream.appendChild(aDiv);
    chatStream.scrollTop = chatStream.scrollHeight;
  } catch (e) {
    const errDiv = document.createElement('div');
    errDiv.style.color = '#ef4444';
    errDiv.style.fontSize = '0.82rem';
    errDiv.textContent = 'Error connecting to assistant.';
    chatStream.appendChild(errDiv);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Ask';
  }
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
</script>
