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
      <img src="<?= htmlspecialchars($articleImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
    </div>

    <!-- Stored AI Summary Box (if present and current) -->
    <?php if (!empty($post['summary']) && \InfinityBlog\Posts\PostRepository::isSummaryCurrent($post)): ?>
      <aside class="ai-summary-card">
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

    <!-- Full Article Content (safely entity-escaped) -->
    <div class="article-body-content">
      <?= nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')) ?>
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
