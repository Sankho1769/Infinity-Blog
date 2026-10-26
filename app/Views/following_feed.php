<!-- Screen: Followed Authors Feed -->
<div class="page-container" style="max-width: 1200px; margin: 0 auto; padding: 32px 20px;">

  <!-- Header -->
  <div style="margin-bottom: 28px;">
    <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: var(--primary-color); background: rgba(37, 99, 235, 0.08); padding: 4px 12px; border-radius: 20px; margin-bottom: 8px;">
      <span>👥</span> COMMUNITY NETWORK
    </div>
    <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin: 0;">Following Feed</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 6px;">Latest published articles from writers and creators you follow.</p>
  </div>

  <?php if (empty($posts)): ?>
    <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center; border-radius: 16px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">👥</div>
      <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Your following feed is quiet</h3>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">
        Follow insightful authors to get their latest stories delivered directly to this personalized stream.
      </p>
      <a href="/authors" class="btn btn-primary">Discover Authors to Follow</a>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
      <?php foreach ($posts as $post): ?>
        <?php
          $postId = (int) $post['id'];
          $canonicalPostUrl = ($basePath ?? '') . '/article/' . $postId;
          $thumb = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($post, 0);
          $authorId = (int) ($post['author_id'] ?? 1);
        ?>
        <article class="glass-panel article-card"
                 data-post-id="<?= $postId ?>"
                 data-article-url="<?= $canonicalPostUrl ?>"
                 tabindex="0"
                 role="article"
                 aria-label="Article: <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                 style="display: flex; flex-direction: column; border-radius: 18px; overflow: hidden; border: 1px solid var(--glass-border);">
          <?php if (!empty($thumb)): ?>
            <div style="height: 180px; overflow: hidden; position: relative;">
              <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . $postId) ?>" class="feed-media-link" aria-label="Read <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" style="display: block; width: 100%; height: 100%;">
                <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.parentElement.parentElement.style.display='none';">
              </a>
              <div style="position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; align-items: center; z-index: 2;">
                <?= \InfinityBlog\Posts\ContentTypeHelper::renderBadge($post['content_type'] ?? 'article') ?>
                <?php if (!empty($post['category_name'])): ?>
                  <a href="/explore?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="badge badge-category" style="background: rgba(255,255,255,0.9); color: var(--primary-color); font-weight: 700; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; text-decoration: none;">
                    <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap;">
              <?php
                $authorAvatar = $post['author_avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($post['avatar_path'] ?? ($post['author_avatar_path'] ?? null));
                $authorInitial = strtoupper(mb_substr($post['author_name'] ?? 'A', 0, 1));
              ?>
              <a href="/author/<?= $authorId ?>" class="feed-author-avatar-link" title="View <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>'s profile" style="text-decoration: none; display: inline-flex; align-items: center;">
                <?php if (!empty($authorAvatar)): ?>
                  <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>"
                       alt="<?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>"
                       class="feed-author-avatar-img"
                       width="28" height="28" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;"
                       onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                  <div class="user-avatar" style="display: none; width: 28px; height: 28px; font-size: 0.75rem; text-decoration: none;">
                    <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php else: ?>
                  <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem; text-decoration: none;">
                    <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
              </a>
              <a href="/author/<?= $authorId ?>" class="feed-author-name" style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); text-decoration: none;">
                <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
              </a>
              <span style="font-size: 0.75rem; color: var(--text-muted);">● <?= date('M j, Y', strtotime($post['published_at'])) ?></span>
              <?php if (empty($thumb)): ?>
                <?= \InfinityBlog\Posts\ContentTypeHelper::renderBadge($post['content_type'] ?? 'article') ?>
              <?php endif; ?>
              <?php if (empty($thumb) && !empty($post['category_name'])): ?>
                <a href="/explore?category=<?= urlencode($post['category_slug'] ?? '') ?>" class="badge badge-category" style="margin-left: auto; font-size: 0.78rem; text-decoration: none;">
                  <?= htmlspecialchars($post['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php endif; ?>
            </div>

            <h3 style="font-size: 1.18rem; font-weight: 700; margin: 0 0 10px; line-height: 1.35;">
              <a href="<?= htmlspecialchars(($basePath ?? '') . '/article/' . $postId) ?>" style="color: var(--text-main); text-decoration: none;">
                <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </h3>

            <?php if (!empty($post['summary'])): ?>
              <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 16px; flex: 1;">
                <?= htmlspecialchars(mb_substr($post['summary'], 0, 130) . '...', ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php else: ?>
              <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 16px; flex: 1;">
                <?= htmlspecialchars(mb_substr(strip_tags((string)$post['content']), 0, 130) . '...', ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 14px; margin-top: auto;">
              <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">
                ⏱️ <?= (int) ($post['reading_time'] ?? 1) ?> min read
              </span>
              <a href="/article/<?= $postId ?>" class="btn btn-primary btn-sm">Read Article</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
