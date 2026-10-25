<?php
declare(strict_types=1);

$cleanName = \InfinityBlog\Community\CommunityNameFormatter::format($community['name'] ?? '');
$slug = htmlspecialchars($community['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$desc = htmlspecialchars($community['description'] ?? 'Community discussion and perspectives on Infinity Blog.', ENT_QUOTES, 'UTF-8');
$catName = !empty($community['category_name']) ? htmlspecialchars($community['category_name'], ENT_QUOTES, 'UTF-8') : null;
$catId = !empty($community['category_id']) ? (int) $community['category_id'] : null;
$renderer = new \InfinityBlog\Posts\ArticleContentRenderer();
?>

<!-- Community Header & Navigation Breadcrumb -->
<div class="community-detail-container" style="max-width: 960px; margin: 0 auto; padding: 20px 16px;">

  <!-- Breadcrumb -->
  <nav aria-label="Breadcrumb" style="margin-bottom: 16px; font-size: 0.86rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
    <a href="/" style="color: var(--text-muted); text-decoration: none;">Home</a>
    <span>/</span>
    <a href="/communities" style="color: var(--text-muted); text-decoration: none;">Communities</a>
    <span>/</span>
    <span style="color: var(--text-main); font-weight: 700;"><?= htmlspecialchars($cleanName, ENT_QUOTES, 'UTF-8') ?></span>
  </nav>

  <!-- Community Hero Card -->
  <div class="glass-panel" style="padding: 28px 24px; border-radius: var(--radius-xl); margin-bottom: 22px; position: relative; overflow: hidden;">
    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">

      <!-- Community Logo / Icon -->
      <div style="flex-shrink: 0;">
        <?= \InfinityBlog\Community\CommunityLogoHelper::renderLogoHtml($community, 58) ?>
      </div>

      <!-- Identity & Description -->
      <div style="flex: 1; min-width: 260px;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
          <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-main); margin: 0; letter-spacing: -0.02em;">
            <?= htmlspecialchars($cleanName, ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <?php if ($catName !== null): ?>
            <span style="background: rgba(37, 99, 235, 0.1); color: var(--primary-color); font-weight: 700; font-size: 0.78rem; padding: 3px 10px; border-radius: var(--radius-pill); text-transform: uppercase; letter-spacing: 0.04em;">
              <?= $catName ?>
            </span>
          <?php endif; ?>
        </div>

        <p style="font-size: 0.98rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 14px;">
          <?= $desc ?>
        </p>

        <!-- Stats Bar -->
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; font-size: 0.85rem; color: var(--text-muted);">
          <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <span class="community-member-count" data-slug="<?= $slug ?>"><?= (int) $memberCount ?> <?= (int) $memberCount === 1 ? 'member' : 'members' ?></span>
          </span>
          <span>•</span>
          <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span><?= (int) $totalPosts ?> <?= (int) $totalPosts === 1 ? 'story' : 'stories' ?></span>
          </span>
          <span>•</span>
          <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            <span><?= (int) $chatMessageCount ?> chat messages</span>
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; min-width: 170px;">
        <button type="button"
                class="btn btn-pill btn-community-join <?= !empty($isMember) ? 'btn-secondary joined' : 'btn-primary' ?>"
                data-slug="<?= $slug ?>"
                data-room-id="<?= (int) $community['id'] ?>"
                data-is-member="<?= !empty($isMember) ? '1' : '0' ?>"
                style="justify-content: center; width: 100%;">
          <?php if (!empty($isMember)): ?>
            <span>✓</span> Joined Member
          <?php else: ?>
            <span>+</span> Join Community
          <?php endif; ?>
        </button>

        <a href="/chat/<?= $slug ?>" class="btn btn-secondary btn-pill" style="justify-content: center; width: 100%; text-decoration: none;" title="Open live chat room for <?= htmlspecialchars($cleanName, ENT_QUOTES, 'UTF-8') ?>">
          <span>💬</span> Open Live Chat
        </a>

        <?php if ($catId !== null && !empty($currentUser)): ?>
          <a href="/posts/new?category=<?= $catId ?>" class="btn btn-ghost btn-pill btn-sm" style="justify-content: center; text-decoration: none; font-size: 0.82rem;" title="Publish an article in this community">
            <span>✍️</span> Share a Story
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Community Views Navigation Switcher -->
  <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); padding-bottom: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
    <div style="display: flex; gap: 8px;">
      <span class="chat-switch-pill active" style="background: var(--primary-color); color: #fff; cursor: default;">
        <span>📖</span> Discussions & Posts (<?= count($posts) ?>)
      </span>
      <a href="/chat/<?= $slug ?>" class="chat-switch-pill" title="Jump to real-time chat room">
        <span>💬</span> Live Chat Room
      </a>
    </div>

    <a href="/communities" style="font-size: 0.84rem; color: var(--text-muted); text-decoration: none; font-weight: 600;">
      ← Explore All Communities
    </a>
  </div>

  <!-- Discussion Posts Feed -->
  <div class="community-discussion-feed">
    <?php if (empty($posts)): ?>
      <div class="glass-panel empty-state" style="padding: 40px 24px; text-align: center; border-radius: var(--radius-xl);">
        <div style="font-size: 2.8rem; margin-bottom: 12px;">📝</div>
        <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No Discussion Posts Yet</h2>
        <p style="color: var(--text-muted); font-size: 0.94rem; max-width: 480px; margin: 0 auto 18px;">
          Be the first to share an idea, technical article, or topic discussion in <?= htmlspecialchars($cleanName, ENT_QUOTES, 'UTF-8') ?>!
        </p>
        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
          <?php if (!empty($currentUser)): ?>
            <a href="/posts/new?category=<?= (int) ($catId ?? 1) ?>" class="btn btn-primary btn-pill">Publish First Story</a>
          <?php else: ?>
            <a href="/login?redirect=<?= urlencode('/community/' . $community['slug']) ?>" class="btn btn-primary btn-pill">Sign In to Post</a>
          <?php endif; ?>
          <a href="/chat/<?= $slug ?>" class="btn btn-secondary btn-pill">Jump to Live Chat</a>
        </div>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <?php foreach ($posts as $post): ?>
          <?php
            $postId = (int) $post['id'];
            $authorId = (int) $post['author_id'];
            $authorName = htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8');
            $authorHandle = htmlspecialchars($post['author_handle'] ?? '@author', ENT_QUOTES, 'UTF-8');
            $authorAvatar = $post['author_avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($post['avatar_path'] ?? null);
            $authorInitial = strtoupper(mb_substr($post['author_name'] ?? 'A', 0, 1));
            $title = htmlspecialchars($post['title'] ?? 'Untitled Story', ENT_QUOTES, 'UTF-8');
            $relativeTime = htmlspecialchars($post['relative_time'] ?? 'recently', ENT_QUOTES, 'UTF-8');
            $commentCount = (int) ($post['comment_count'] ?? 0);
            $likeCount = (int) ($post['reader_score_count'] ?? 0);
            $thumb = $post['thumbnail_url'] ?? null;
            $rawContent = $post['content'] ?? '';
            $snippetData = $renderer->renderFeedSnippet(!empty($post['summary']) ? $post['summary'] : $rawContent, 200);
            $excerpt = !empty($post['summary']) ? $post['summary'] : $snippetData['text'];
            $recentComments = $post['recent_comments'] ?? [];
          ?>
          <article class="glass-panel feed-card" style="padding: 22px; border-radius: var(--radius-xl);" data-post-id="<?= $postId ?>">

            <!-- Card Header: Author Identity & Timestamp -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <a href="/author/<?= $authorId ?>" style="text-decoration: none;" title="View <?= $authorName ?>'s profile">
                  <?php if (!empty($authorAvatar)): ?>
                    <img src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $authorName ?>" class="feed-author-avatar-img" width="36" height="36" style="border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="user-avatar" style="display: none; width: 36px; height: 36px; font-size: 0.85rem; font-weight: 700; border-radius: 50%; background: var(--primary-gradient); color: #fff; align-items: center; justify-content: center;">
                      <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php else: ?>
                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 0.85rem; font-weight: 700; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center;">
                      <?= htmlspecialchars($authorInitial, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </a>

                <div>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <a href="/author/<?= $authorId ?>" style="font-weight: 700; font-size: 0.94rem; color: var(--text-main); text-decoration: none;">
                      <?= $authorName ?>
                    </a>
                    <?php if (!empty($post['author_has_badge'])): ?>
                      <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                    <?php endif; ?>
                    <span style="font-size: 0.78rem; color: var(--text-muted);"><?= $authorHandle ?></span>
                  </div>
                  <span style="font-size: 0.76rem; color: var(--text-muted);"><?= $relativeTime ?></span>
                </div>
              </div>

              <a href="/article/<?= $postId ?>" class="btn btn-ghost btn-sm btn-pill" style="font-size: 0.80rem;">
                Read Story →
              </a>
            </div>

            <!-- Story Title & Excerpt -->
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0 0 10px; line-height: 1.35;">
              <a href="/article/<?= $postId ?>" style="color: inherit; text-decoration: none;">
                <?= $title ?>
              </a>
            </h2>

            <?php if (!empty($excerpt)): ?>
              <p style="font-size: 0.93rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 16px;">
                <?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>

            <?php if (!empty($thumb)): ?>
              <div style="margin-bottom: 16px; border-radius: var(--radius-lg); overflow: hidden; max-height: 280px;">
                <a href="/article/<?= $postId ?>">
                  <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $title ?>" style="width: 100%; max-height: 280px; object-fit: cover; display: block;" loading="lazy">
                </a>
              </div>
            <?php endif; ?>

            <!-- Engagement & Discussion Actions -->
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 8px;">
              <div style="display: flex; align-items: center; gap: 12px;">
                <!-- Likes -->
                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                  <span><?= $likeCount ?> <?= $likeCount === 1 ? 'like' : 'likes' ?></span>
                </span>

                <!-- Discussion Trigger Button (Opens Drawer) -->
                <button type="button"
                        class="btn btn-ghost btn-sm btn-pill"
                        onclick="openCommentsDrawer(event, <?= $postId ?>)"
                        style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.84rem; font-weight: 700; color: var(--primary-color); cursor: pointer;"
                        title="View discussion comments and replies">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                  <span><?= $commentCount ?> <?= $commentCount === 1 ? 'comment' : 'comments' ?></span>
                </button>
              </div>

              <a href="/article/<?= $postId ?>#commentsSection" style="font-size: 0.82rem; color: var(--text-muted); text-decoration: none; font-weight: 600;">
                Join Discussion on Full Article ↗
              </a>
            </div>

            <!-- Discussion Comments Preview Tray -->
            <?php if (!empty($recentComments)): ?>
              <div style="margin-top: 14px; padding: 12px 14px; background: rgba(255, 255, 255, 0.04); border-radius: 12px; border: 1px solid var(--glass-border); display: flex; flex-direction: column; gap: 10px;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; display: flex; justify-content: space-between; align-items: center;">
                  <span>Recent Discussion</span>
                  <span onclick="openCommentsDrawer(event, <?= $postId ?>)" style="color: var(--primary-color); cursor: pointer; text-transform: none;">View all <?= $commentCount ?> →</span>
                </div>
                <?php foreach ($recentComments as $c): ?>
                  <div style="display: flex; gap: 8px; align-items: flex-start; font-size: 0.84rem;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; flex-shrink: 0;">
                      <?= htmlspecialchars(mb_substr($c['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                      <span style="font-weight: 700; color: var(--text-main); margin-right: 6px;"><?= htmlspecialchars($c['user_name'] ?? 'Member', ENT_QUOTES, 'UTF-8') ?>:</span>
                      <span style="color: var(--text-muted);"><?= htmlspecialchars(mb_substr($c['content'] ?? '', 0, 120), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($c['content'] ?? '') > 120 ? '…' : '' ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
