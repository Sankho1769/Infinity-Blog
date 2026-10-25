<!-- Personal Knowledge Library View -->
<div class="page-container" style="max-width: 1200px; margin: 0 auto; padding: 32px 20px;">

  <!-- Header Header -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
    <div>
      <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: var(--primary-color); background: rgba(37, 99, 235, 0.08); padding: 4px 12px; border-radius: 20px; margin-bottom: 8px;">
        <span>📚</span> PERSONAL KNOWLEDGE BASE
      </div>
      <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-main); margin: 0;">My Library</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 6px;">Manage your saved articles, reading history, highlights, collections, and topic preferences.</p>
    </div>

    <div>
      <a href="/explore" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <span>🔍</span> Discover Articles
      </a>
    </div>
  </div>

  <!-- Library Tabs Navigation -->
  <div class="glass-panel" style="padding: 6px; border-radius: 14px; margin-bottom: 28px; display: inline-flex; flex-wrap: wrap; gap: 4px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
    <a href="/library?tab=bookmarks" class="tab-pill <?= ($activeTab ?? 'bookmarks') === 'bookmarks' ? 'active' : '' ?>" style="padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: <?= ($activeTab ?? 'bookmarks') === 'bookmarks' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($activeTab ?? 'bookmarks') === 'bookmarks' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      <span>🔖</span> Saved Articles (<?= count($bookmarks ?? []) ?>)
    </a>
    <a href="/library?tab=ebooks" class="tab-pill <?= ($activeTab ?? '') === 'ebooks' ? 'active' : '' ?>" style="padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: <?= ($activeTab ?? '') === 'ebooks' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($activeTab ?? '') === 'ebooks' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      <span>📖</span> Saved eBooks (<?= count($savedEbooks ?? []) ?>)
    </a>
    <a href="/library?tab=history" class="tab-pill <?= ($activeTab ?? '') === 'history' ? 'active' : '' ?>" style="padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: <?= ($activeTab ?? '') === 'history' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($activeTab ?? '') === 'history' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      <span>⏱️</span> Continue Reading (<?= count($history ?? []) ?>)
    </a>
    <a href="/library?tab=collections" class="tab-pill <?= ($activeTab ?? '') === 'collections' ? 'active' : '' ?>" style="padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: <?= ($activeTab ?? '') === 'collections' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($activeTab ?? '') === 'collections' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      <span>📁</span> Collections (<?= count($collections ?? []) ?>)
    </a>
    <a href="/library?tab=notes" class="tab-pill <?= ($activeTab ?? '') === 'notes' ? 'active' : '' ?>" style="padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: <?= ($activeTab ?? '') === 'notes' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($activeTab ?? '') === 'notes' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      <span>✍️</span> Notes & Highlights (<?= count($notes ?? []) ?>)
    </a>
    <a href="/library?tab=preferences" class="tab-pill <?= ($activeTab ?? '') === 'preferences' ? 'active' : '' ?>" style="padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: <?= ($activeTab ?? '') === 'preferences' ? '#fff' : 'var(--text-muted)' ?>; background: <?= ($activeTab ?? '') === 'preferences' ? 'var(--primary-gradient)' : 'transparent' ?>;">
      <span>⚙️</span> Topic Preferences
    </a>
  </div>

  <!-- TAB 1: SAVED ARTICLES (BOOKMARKS) -->
  <?php if (($activeTab ?? 'bookmarks') === 'bookmarks'): ?>
    <div style="margin-bottom: 24px;">
      <form method="GET" action="/library" style="display: flex; gap: 12px; max-width: 500px;">
        <input type="hidden" name="tab" value="bookmarks">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Search within saved articles..." class="form-input" style="flex: 1;">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if (!empty($searchQuery)): ?>
          <a href="/library?tab=bookmarks" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (empty($bookmarks)): ?>
      <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center; border-radius: 16px;">
        <div style="font-size: 3rem; margin-bottom: 12px;">🔖</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No saved articles yet</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 20px;">When reading an article, click "Save to Library" to keep it organized here for quick future reference.</p>
        <a href="/explore" class="btn btn-primary">Browse Published Articles</a>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($bookmarks as $post): ?>
          <?php
            $postId = (int) $post['id'];
            $canonicalPostUrl = '/article/' . $postId;
            $authorId = (int) ($post['author_id'] ?? 1);
          ?>
          <article class="glass-panel article-card"
                   data-post-id="<?= $postId ?>"
                   data-article-url="<?= $canonicalPostUrl ?>"
                   tabindex="0"
                   role="article"
                   aria-label="Article: <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                   style="display: flex; flex-direction: column; border-radius: 16px; overflow: hidden; border: 1px solid var(--glass-border);">
            <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span class="badge" style="background: rgba(37, 99, 235, 0.1); color: var(--primary-color); font-weight: 600; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem;">
                  <?= htmlspecialchars($post['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span style="font-size: 0.75rem; color: var(--text-muted);">
                  Saved <?= date('M j, Y', strtotime($post['bookmarked_at'])) ?>
                </span>
              </div>

              <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 10px; line-height: 1.4;">
                <a href="/article/<?= $postId ?>" style="color: var(--text-main); text-decoration: none;">
                  <?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h3>

              <?php if (!empty($post['summary'])): ?>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; flex: 1;">
                  <?= htmlspecialchars(mb_substr($post['summary'], 0, 140) . (mb_strlen($post['summary']) > 140 ? '...' : ''), ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php else: ?>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; flex: 1;">
                  <?= htmlspecialchars(mb_substr(strip_tags((string)$post['content']), 0, 140) . '...', ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php endif; ?>

              <div style="display: flex; justify-content: space-between; align-items: center; pt: 14px; border-top: 1px solid var(--glass-border); margin-top: auto; padding-top: 14px;">
                <a href="/author/<?= $authorId ?>" class="feed-author-name" style="font-size: 0.82rem; color: var(--text-main); font-weight: 600; text-decoration: none;">
                  By <?= htmlspecialchars($post['author_name'] ?? 'Author', ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div style="display: flex; gap: 8px;">
                  <button type="button" class="btn btn-secondary btn-sm" onclick="removeBookmark(<?= $postId ?>, this)" title="Remove from Library">
                    ✕ Remove
                  </button>
                  <a href="/article/<?= $postId ?>" class="btn btn-primary btn-sm">Read</a>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- TAB 2: CONTINUE READING (HISTORY) -->
  <?php if (($activeTab ?? '') === 'history'): ?>
    <?php if (empty($history)): ?>
      <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center; border-radius: 16px;">
        <div style="font-size: 3rem; margin-bottom: 12px;">⏱️</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No reading history yet</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 20px;">As you read articles on Infinity Blog, your progress is tracked automatically so you can resume anytime.</p>
        <a href="/explore" class="btn btn-primary">Start Reading Articles</a>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($history as $item): ?>
          <div class="glass-panel" style="padding: 20px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; border: 1px solid var(--glass-border);">
            <div style="flex: 1; min-width: 280px;">
              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <span class="badge" style="background: rgba(37, 99, 235, 0.1); color: var(--primary-color); font-weight: 600; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem;">
                  <?= htmlspecialchars($item['category_name'] ?? 'Article', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span style="font-size: 0.78rem; color: var(--text-muted);">
                  Last read <?= date('M j, Y - g:i A', strtotime($item['last_read_at'])) ?>
                </span>
                <?php if (!empty($item['completed'])): ?>
                  <span style="font-size: 0.75rem; color: #10B981; font-weight: 700; background: rgba(16, 185, 129, 0.1); padding: 2px 8px; border-radius: 10px;">✓ Completed</span>
                <?php endif; ?>
              </div>

              <h4 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 10px;">
                <a href="/article/<?= (int) $item['id'] ?>" style="color: var(--text-main); text-decoration: none;">
                  <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h4>

              <!-- Progress Bar -->
              <div style="display: flex; align-items: center; gap: 12px;">
                <div style="flex: 1; height: 8px; background: rgba(0,0,0,0.06); border-radius: 4px; overflow: hidden; max-width: 320px;">
                  <div style="width: <?= (int) $item['progress_percent'] ?>%; height: 100%; background: var(--primary-gradient); border-radius: 4px;"></div>
                </div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary-color);">
                  <?= (int) $item['progress_percent'] ?>%
                </span>
              </div>
            </div>

            <div>
              <a href="/article/<?= (int) $item['id'] ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>📖</span> Resume Reading
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- TAB 3: CUSTOM COLLECTIONS -->
  <?php if (($activeTab ?? '') === 'collections'): ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0;">My Reading Lists</h3>
      <button type="button" class="btn btn-primary" onclick="openNewCollectionModal()">
        <span>+</span> Create Collection
      </button>
    </div>

    <?php if (empty($collections)): ?>
      <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center; border-radius: 16px;">
        <div style="font-size: 3rem; margin-bottom: 12px;">📁</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No collections yet</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 20px;">Organize related articles into thematic reading lists (e.g. "AI Research", "Web Architecture").</p>
        <button type="button" class="btn btn-primary" onclick="openNewCollectionModal()">Create Your First Collection</button>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($collections as $col): ?>
          <div class="glass-panel" style="padding: 24px; border-radius: 16px; border: 1px solid var(--glass-border); display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
              <span style="font-size: 0.8rem; font-weight: 600; color: <?= !empty($col['is_private']) ? '#6B7280' : '#10B981' ?>; background: rgba(0,0,0,0.04); padding: 2px 8px; border-radius: 10px;">
                <?= !empty($col['is_private']) ? '🔒 Private' : '🌐 Public' ?>
              </span>
              <span style="font-size: 0.82rem; color: var(--text-muted);">
                <?= (int) ($col['item_count'] ?? 0) ?> articles
              </span>
            </div>

            <h4 style="font-size: 1.2rem; font-weight: 700; margin: 0 0 8px; color: var(--text-main);">
              <?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?>
            </h4>

            <?php if (!empty($col['description'])): ?>
              <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0 0 16px; flex: 1;">
                <?= htmlspecialchars($col['description'], ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 14px; margin-top: auto;">
              <span style="font-size: 0.75rem; color: var(--text-muted);">
                Created <?= date('M j, Y', strtotime($col['created_at'])) ?>
              </span>
              <button type="button" class="btn btn-secondary btn-sm" onclick="deleteCollection(<?= (int) $col['id'] ?>, this)">
                Delete
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- TAB 4: NOTES & HIGHLIGHTS -->
  <?php if (($activeTab ?? '') === 'notes'): ?>
    <?php if (empty($notes)): ?>
      <div class="glass-panel empty-state" style="padding: 28px 20px; text-align: center; border-radius: 16px;">
        <div style="font-size: 3rem; margin-bottom: 12px;">✍️</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No notes or highlights yet</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 20px;">Highlight text and capture personal reflections while reading published articles.</p>
        <a href="/explore" class="btn btn-primary">Browse Articles</a>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($notes as $note): ?>
          <div class="glass-panel" style="padding: 20px; border-radius: 16px; border: 1px solid var(--glass-border);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary-color);">
                From: <a href="/article/<?= (int) $note['post_id'] ?>" style="color: inherit; text-decoration: underline;"><?= htmlspecialchars($note['post_title'], ENT_QUOTES, 'UTF-8') ?></a>
              </span>
              <div style="display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 0.78rem; color: var(--text-muted);"><?= date('M j, Y', strtotime($note['created_at'])) ?></span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="deleteNote(<?= (int) $note['id'] ?>, this)">Delete</button>
              </div>
            </div>

            <?php if (!empty($note['highlight_text'])): ?>
              <blockquote style="margin: 0 0 12px; padding: 8px 14px; border-left: 4px solid #F59E0B; background: rgba(245, 158, 11, 0.08); border-radius: 0 8px 8px 0; font-size: 0.92rem; font-style: italic; color: var(--text-main);">
                "<?= htmlspecialchars($note['highlight_text'], ENT_QUOTES, 'UTF-8') ?>"
              </blockquote>
            <?php endif; ?>

            <div style="font-size: 0.95rem; color: var(--text-main); line-height: 1.5;">
              <?= nl2br(htmlspecialchars($note['note_text'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- TAB 5: TOPIC PREFERENCES -->
  <?php if (($activeTab ?? '') === 'preferences'): ?>
    <div class="glass-panel" style="padding: 28px; border-radius: 16px; max-width: 800px; border: 1px solid var(--glass-border);">
      <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 8px;">Personalized Discovery Preferences</h3>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0 0 24px;">Control which topics appear in your feed. Muting a topic hides related articles from your personalized recommendations.</p>

      <div style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($topicPreferences ?? [] as $topic): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: rgba(255,255,255,0.4); border-radius: 12px; border: 1px solid var(--glass-border);">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);"><?= htmlspecialchars($topic['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (!empty($topic['is_muted'])): ?>
                <span style="font-size: 0.75rem; background: rgba(239, 68, 68, 0.1); color: #EF4444; padding: 2px 6px; border-radius: 6px; font-weight: 600;">Muted</span>
              <?php endif; ?>
            </div>

            <div>
              <button type="button" class="btn btn-sm <?= !empty($topic['is_muted']) ? 'btn-secondary' : 'btn-primary' ?>" onclick="toggleTopicMute(<?= (int) $topic['category_id'] ?>, <?= !empty($topic['is_muted']) ? 'false' : 'true' ?>, this)">
                <?= !empty($topic['is_muted']) ? 'Unmute' : 'Mute Topic' ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- TAB 6: SAVED EBOOKS -->
  <?php if (($activeTab ?? '') === 'ebooks'): ?>
    <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <form method="GET" action="/library" style="display: flex; gap: 12px; max-width: 500px; flex: 1;">
        <input type="hidden" name="tab" value="ebooks">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Search saved eBooks by title or author..." class="form-input" style="flex: 1;">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if (!empty($searchQuery)): ?>
          <a href="/library?tab=ebooks" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
      <a href="/ebooks" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
        <span>📚</span> Browse eBook Library
      </a>
    </div>

    <?php if (empty($savedEbooks)): ?>
      <div class="glass-panel empty-state" style="padding: 36px 20px; text-align: center; border-radius: 16px;">
        <div style="font-size: 3.5rem; margin-bottom: 12px;">📖</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No saved eBooks yet</h3>
        <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">Discover community eBooks across programming, design, fiction, and more. Click "Save to Library" on any book to read it anytime.</p>
        <a href="/ebooks" class="btn btn-primary">Discover eBooks</a>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
        <?php foreach ($savedEbooks as $book): ?>
          <?php
            $coverUrl = !empty($book['cover_path']) ? htmlspecialchars($book['cover_path'], ENT_QUOTES, 'UTF-8') : \InfinityBlog\Ebooks\EbookFileService::generateSvgCover($book['title'], $book['author_name'], $book['category_name'] ?? 'eBook');
          ?>
          <div class="glass-panel saved-ebook-card" data-ebook-id="<?= (int) $book['id'] ?>" style="padding: 20px; border-radius: 16px; display: flex; flex-direction: column; gap: 14px; position: relative;">
            <div style="display: flex; gap: 16px;">
              <a href="/ebooks/<?= (int) $book['id'] ?>" style="flex-shrink: 0; width: 80px; height: 112px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.12); display: block;">
                <img src="<?= $coverUrl ?>" alt="<?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;">
              </a>
              <div style="flex: 1; min-width: 0;">
                <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px;">
                  <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; background: rgba(37,99,235,0.1); color: var(--primary-color); padding: 2px 8px; border-radius: 6px;">
                    <?= strtoupper(htmlspecialchars($book['format'], ENT_QUOTES, 'UTF-8')) ?>
                  </span>
                  <?php if (!empty($book['category_name'])): ?>
                    <span style="font-size: 0.72rem; font-weight: 600; color: var(--text-muted);">
                      <?= htmlspecialchars($book['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                </div>
                <h4 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 4px; line-height: 1.3;">
                  <a href="/ebooks/<?= (int) $book['id'] ?>" style="color: var(--text-main); text-decoration: none;">
                    <?= htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                </h4>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">
                  By <?= htmlspecialchars($book['author_name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if (isset($book['percentage'])): ?>
                  <div style="margin-top: 6px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.72rem; font-weight: 600; color: var(--text-muted); margin-bottom: 2px;">
                      <span>Progress</span>
                      <span><?= round((float) $book['percentage']) ?>%</span>
                    </div>
                    <div style="height: 5px; width: 100%; background: rgba(0,0,0,0.06); border-radius: 3px; overflow: hidden;">
                      <div style="height: 100%; width: <?= min(100, max(0, (float) $book['percentage'])) ?>%; background: var(--primary-gradient); border-radius: 3px;"></div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--glass-border);">
              <a href="/ebooks/<?= (int) $book['id'] ?>/read" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>📖</span> Read Now
              </a>
              <button type="button" class="btn btn-secondary btn-sm" onclick="removeSavedEbook(<?= (int) $book['id'] ?>, this)" title="Remove from saved eBooks">
                <span>✕</span> Remove
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<!-- Modal: New Collection -->
<div id="newCollectionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div class="glass-panel" style="width: 100%; max-width: 480px; padding: 28px; border-radius: 20px; background: #fff; margin: 20px;">
    <h3 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 16px; color: var(--text-main);">Create Collection</h3>
    <form id="createCollectionForm" onsubmit="handleCreateCollection(event)">
      <div class="form-group" style="margin-bottom: 16px;">
        <label class="form-label">Collection Title *</label>
        <input type="text" id="colTitle" class="form-input" placeholder="e.g. Modern Web Development" required maxlength="150">
      </div>
      <div class="form-group" style="margin-bottom: 16px;">
        <label class="form-label">Description (Optional)</label>
        <textarea id="colDesc" class="form-input" rows="3" placeholder="Brief summary of what this reading list is about..."></textarea>
      </div>
      <div class="form-group" style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
        <input type="checkbox" id="colPrivate" checked style="width: 18px; height: 18px;">
        <label for="colPrivate" style="font-size: 0.9rem; font-weight: 600; cursor: pointer; color: var(--text-main);">Keep collection private</label>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary" onclick="closeNewCollectionModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="createColBtn">Create Collection</button>
      </div>
    </form>
  </div>
</div>

<script>
async function removeBookmark(postId, btn) {
  if (!confirm('Remove this article from your library?')) return;
  btn.disabled = true;
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const res = await fetch('/api/library/bookmark/' + postId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ action: 'unsave' })
    });
    if (res.ok) {
      window.location.reload();
    } else {
      const data = await res.json();
      alert(data.error?.message || 'Could not remove article from library.');
    }
  } catch (e) {
    alert('Error updating library.');
  } finally {
    btn.disabled = false;
  }
}

async function deleteNote(noteId, btn) {
  if (!confirm('Delete this note?')) return;
  btn.disabled = true;
  try {
    const res = await fetch('/api/library/notes/' + noteId, { method: 'DELETE' });
    if (res.ok) window.location.reload();
  } catch (e) {
    alert('Error deleting note.');
  } finally {
    btn.disabled = false;
  }
}

async function deleteCollection(colId, btn) {
  if (!confirm('Delete this collection?')) return;
  btn.disabled = true;
  try {
    const res = await fetch('/api/library/collections/' + colId, { method: 'DELETE' });
    if (res.ok) window.location.reload();
  } catch (e) {
    alert('Error deleting collection.');
  } finally {
    btn.disabled = false;
  }
}

function openNewCollectionModal() {
  document.getElementById('newCollectionModal').style.display = 'flex';
}
function closeNewCollectionModal() {
  document.getElementById('newCollectionModal').style.display = 'none';
}

async function handleCreateCollection(e) {
  e.preventDefault();
  const btn = document.getElementById('createColBtn');
  btn.disabled = true;
  btn.textContent = 'Creating...';
  const title = document.getElementById('colTitle').value.trim();
  const description = document.getElementById('colDesc').value.trim();
  const is_private = document.getElementById('colPrivate').checked;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch('/api/library/collections', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ title, description, is_private })
    });
    if (res.ok) {
      window.location.reload();
    } else {
      const d = await res.json();
      alert(d.error?.message || 'Failed to create collection.');
    }
  } catch (e) {
    alert('Network error.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Create Collection';
  }
}

async function toggleTopicMute(catId, shouldMute, btn) {
  btn.disabled = true;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  try {
    const res = await fetch('/api/library/topic-preferences', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ category_id: catId, is_muted: shouldMute })
    });
    if (res.ok) window.location.reload();
  } catch (e) {
    alert('Error updating preference.');
  } finally {
    btn.disabled = false;
  }
}
async function removeSavedEbook(ebookId, btn) {
  if (!confirm('Remove this eBook from your saved library?')) return;
  btn.disabled = true;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  try {
    const res = await fetch('/api/ebooks/' + ebookId + '/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      }
    });
    if (res.ok) {
      window.location.reload();
    } else {
      alert('Failed to remove eBook.');
    }
  } catch (e) {
    alert('Network error.');
  } finally {
    btn.disabled = false;
  }
}
</script>
