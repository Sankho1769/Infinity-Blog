<!-- Public Communities Discovery Page -->
<div class="content-header content-page-hero">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Connect & Collaborate</span>
  <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Discover Communities</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 620px; margin: 10px auto 0;">
    Find and join topic-based community channels, share perspectives with creators, and participate in real-time text discussions.
  </p>

  <!-- Community Search Form -->
  <form action="/communities" method="GET" class="hero-search-bar" role="search" style="max-width: 520px; margin: 24px auto 0;">
    <input type="search" name="search" class="hero-search-input" placeholder="Search communities by name, topic, or keyword..." value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search communities">
    <button type="submit" class="btn btn-primary btn-pill btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      Search
    </button>
  </form>
</div>

<?php if (!empty($searchQuery)): ?>
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; margin-top: 10px;">
    <span style="font-size: 0.9rem; color: var(--text-muted);">
      Search results for "<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" (<?= count($communities) ?> found)
    </span>
    <a href="/communities" class="section-link">Clear search ×</a>
  </div>
<?php endif; ?>

<?php if (empty($communities)): ?>
  <div class="glass-panel empty-state" style="margin-top: 20px; padding: 32px 20px; text-align: center;">
    <div style="font-size: 2.5rem; margin-bottom: 12px;">💬</div>
    <h2 class="empty-title">No Communities Found</h2>
    <p class="empty-desc">
      <?= !empty($searchQuery) ? 'No communities matched your search criteria.' : 'Active discussion communities will appear here.' ?>
    </p>
    <a href="/communities" class="btn btn-primary btn-pill">Browse All Communities</a>
  </div>
<?php else: ?>
  <div class="communities-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); gap: 22px; margin-top: 16px;">
    <?php foreach ($communities as $comm): ?>
      <?php
        $slug = htmlspecialchars($comm['slug'] ?? '', ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars(\InfinityBlog\Community\CommunityNameFormatter::format($comm['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($comm['description'] ?? 'Active discussion space for creators and readers.', ENT_QUOTES, 'UTF-8');
        $categoryName = !empty($comm['category_name']) ? htmlspecialchars($comm['category_name'], ENT_QUOTES, 'UTF-8') : 'General';
        $memberCount = (int) ($comm['member_count'] ?? 0);
        $isMember = !empty($comm['is_member']);
      ?>
      <div class="glass-panel glass-panel-hover community-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 24px; border-radius: var(--radius-xl); transition: all 0.2s ease;">
        <div>
          <!-- Header: Category Tag & Member Badge -->
          <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 14px;">
            <span style="background: rgba(224, 231, 255, 0.75); color: #4338ca; font-weight: 700; padding: 4px 10px; border-radius: var(--radius-pill); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
              <?= $categoryName ?>
            </span>
            <span class="community-member-count" data-slug="<?= $slug ?>" style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted); display: inline-flex; align-items: center; gap: 4px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
              <?= $memberCount ?> <?= $memberCount === 1 ? 'member' : 'members' ?>
            </span>
          </div>

          <!-- Community Icon & Title -->
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
            <a href="/community/<?= $slug ?>" style="text-decoration: none; display: inline-flex;" aria-label="Open <?= $name ?> community discussions">
              <?= \InfinityBlog\Community\CommunityLogoHelper::renderLogoHtml($comm, 44) ?>
            </a>
            <div>
              <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin: 0; line-height: 1.3;">
                <a href="/community/<?= $slug ?>" style="color: inherit; text-decoration: none;" class="community-title-link">
                  <?= $name ?>
                </a>
              </h3>
              <span style="font-size: 0.78rem; color: var(--text-muted);">slug: <?= $slug ?></span>
            </div>
          </div>

          <!-- Description -->
          <p style="font-size: 0.92rem; color: var(--text-muted); line-height: 1.5; margin: 12px 0 20px;">
            <?= $desc ?>
          </p>
        </div>

        <!-- Action Row -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; padding-top: 16px; border-top: 1px solid var(--glass-border);">
          <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($isMember): ?>
              <button type="button"
                      class="btn btn-secondary btn-sm btn-pill btn-community-join joined"
                      data-slug="<?= $slug ?>"
                      data-room-id="<?= (int) $comm['id'] ?>"
                      data-is-member="1"
                      title="You are a member of this community. Click to open discussions.">
                ✓ Joined
              </button>
            <?php else: ?>
              <button type="button"
                      class="btn btn-primary btn-sm btn-pill btn-community-join"
                      data-slug="<?= $slug ?>"
                      data-room-id="<?= (int) $comm['id'] ?>"
                      data-is-member="0"
                      title="Join this community and open discussion">
                + Join
              </button>
            <?php endif; ?>
            <a href="/community/<?= $slug ?>" class="btn btn-primary btn-sm btn-pill" title="View <?= $name ?> discussion posts and comments">
              Discussion Feed →
            </a>
          </div>
          <a href="/chat/<?= $slug ?>" class="btn btn-secondary btn-sm btn-pill community-open-chat-link" title="Open <?= $name ?> live text chat room">
            💬 Live Chat
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
