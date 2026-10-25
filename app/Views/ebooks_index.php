<?php
declare(strict_types=1);
/**
 * Public Community eBook Library Catalog
 */
?>
<div class="page-container ebook-library-page" style="max-width: 1240px; margin: 0 auto; padding: 32px 20px;">

  <!-- Hero / Header Section -->
  <div class="ebook-hero-banner glass-panel" style="padding: 36px 32px; border-radius: 24px; margin-bottom: 32px; position: relative; overflow: hidden; border: 1px solid var(--glass-border); background: var(--glass-bg);">
    <div style="position: relative; z-index: 2; max-width: 720px;">
      <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: var(--primary-color); background: rgba(37, 99, 235, 0.08); padding: 4px 14px; border-radius: 20px; margin-bottom: 12px; letter-spacing: 0.5px;">
        <span>📚</span> PUBLIC COMMUNITY REPOSITORY
      </div>
      <h1 style="font-size: 2.5rem; font-weight: 800; color: var(--text-main); margin: 0 0 10px; line-height: 1.2;">
        eBook Library
      </h1>
      <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.6; margin: 0 0 24px;">
        Discover, read, and share digital books across programming, system design, science, editorial essays, and creative literature. Open to all readers.
      </p>

      <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <a href="/ebooks/upload" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 12px 22px; border-radius: 12px; box-shadow: 0 4px 14px rgba(37,99,235,0.25);">
          <span>➕</span> Upload eBook
        </a>
        <?php if (!empty($currentUser)): ?>
          <a href="/ebooks/manage" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 12px 20px; border-radius: 12px;">
            <span>📖</span> My Uploads
          </a>
        <?php endif; ?>
        <a href="#browse-catalogue" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 12px 20px; border-radius: 12px;">
          <span>🔍</span> Browse Catalogue
        </a>
      </div>
    </div>

    <!-- Background Decorative Glyph -->
    <div style="position: absolute; right: 24px; bottom: -20px; font-size: 13rem; opacity: 0.04; user-select: none; pointer-events: none; font-family: sans-serif;">
      📖
    </div>
  </div>

  <!-- Search & Filter Controls -->
  <div id="browse-catalogue" class="glass-panel" style="padding: 20px 24px; border-radius: 18px; margin-bottom: 32px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
    <form method="GET" action="/ebooks" style="display: flex; flex-direction: column; gap: 16px;">
      <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <!-- Search input -->
        <div style="flex: 1; min-width: 260px; position: relative;">
          <input type="text" name="search" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="Search by title, author, description, or tags..."
                 class="form-input" style="width: 100%; padding-left: 40px; border-radius: 12px; height: 46px; font-size: 0.95rem;">
          <span style="position: absolute; left: 14px; top: 13px; font-size: 1.1rem; color: var(--text-muted); pointer-events: none;">🔍</span>
        </div>

        <!-- Category Dropdown -->
        <div style="min-width: 180px;">
          <select name="category" class="form-input" style="width: 100%; border-radius: 12px; height: 46px; font-size: 0.92rem;" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Format Dropdown -->
        <div style="min-width: 140px;">
          <select name="format" class="form-input" style="width: 100%; border-radius: 12px; height: 46px; font-size: 0.92rem;" onchange="this.form.submit()">
            <option value="">All Formats</option>
            <option value="pdf" <?= ($format ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
            <option value="epub" <?= ($format ?? '') === 'epub' ? 'selected' : '' ?>>EPUB</option>
          </select>
        </div>

        <!-- Sort Dropdown -->
        <div style="min-width: 140px;">
          <select name="sort" class="form-input" style="width: 100%; border-radius: 12px; height: 46px; font-size: 0.92rem;" onchange="this.form.submit()">
            <option value="latest" <?= ($sort ?? 'latest') === 'latest' ? 'selected' : '' ?>>Newest First</option>
            <option value="popular" <?= ($sort ?? '') === 'popular' ? 'selected' : '' ?>>Most Read</option>
            <option value="title" <?= ($sort ?? '') === 'title' ? 'selected' : '' ?>>Title (A-Z)</option>
          </select>
        </div>

        <!-- Submit & Clear -->
        <div style="display: flex; gap: 8px;">
          <button type="submit" class="btn btn-primary" style="padding: 0 20px; border-radius: 12px; font-weight: 600; height: 46px;">
            Filter
          </button>
          <?php if (!empty($search) || !empty($activeCategorySlug) || !empty($format) || ($sort ?? 'latest') !== 'latest'): ?>
            <a href="/ebooks" class="btn btn-secondary" style="padding: 0 16px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; height: 46px; text-decoration: none;" title="Reset filters">
              Clear
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Category Pills -->
      <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; padding-top: 4px; border-top: 1px solid var(--glass-border);">
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Categories:</span>
        <a href="/ebooks" class="category-pill <?= empty($activeCategorySlug) ? 'active' : '' ?>">
          All
        </a>
        <?php foreach (array_slice($categories, 0, 8) as $cat): ?>
          <a href="/ebooks?category=<?= urlencode($cat['slug']) ?><?= !empty($format) ? '&format=' . urlencode($format) : '' ?>"
             class="category-pill <?= ($activeCategorySlug ?? '') === $cat['slug'] ? 'active' : '' ?>">
            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </form>
  </div>

  <!-- CONTINUE READING SECTION (For Signed-in Members) -->
  <?php if (!empty($continueReading)): ?>
    <section class="ebook-section" style="margin-bottom: 40px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
        <h2 style="font-size: 1.4rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
          <span>⏱️</span> Continue Reading
        </h2>
        <a href="/library?tab=ebooks" style="font-size: 0.88rem; font-weight: 600; color: var(--primary-color); text-decoration: none;">
          View Saved Library &rarr;
        </a>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px;">
        <?php foreach ($continueReading as $cr): ?>
          <?php
            $crCover = !empty($cr['cover_path']) ? htmlspecialchars($cr['cover_path'], ENT_QUOTES, 'UTF-8') : \InfinityBlog\Ebooks\EbookFileService::generateSvgCover($cr['title'], $cr['author_name'], $cr['category_name'] ?? 'eBook');
            $pct = round((float) ($cr['percentage'] ?? 0));
          ?>
          <div class="glass-panel continue-reading-card" style="padding: 16px; border-radius: 16px; display: flex; gap: 14px; align-items: center; border: 1px solid var(--glass-border); transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <a href="/ebooks/<?= (int) $cr['id'] ?>/read" style="flex-shrink: 0; width: 64px; height: 90px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: block;">
              <img src="<?= $crCover ?>" alt="<?= htmlspecialchars($cr['title'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </a>
            <div style="flex: 1; min-width: 0;">
              <span style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; background: rgba(37,99,235,0.1); color: var(--primary-color); padding: 2px 6px; border-radius: 4px;">
                <?= strtoupper(htmlspecialchars($cr['format'], ENT_QUOTES, 'UTF-8')) ?>
              </span>
              <h4 style="font-size: 0.95rem; font-weight: 700; margin: 4px 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <a href="/ebooks/<?= (int) $cr['id'] ?>" style="color: var(--text-main); text-decoration: none;">
                  <?= htmlspecialchars($cr['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h4>
              <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 8px;">
                By <?= htmlspecialchars($cr['author_name'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); margin-bottom: 3px;">
                  <span><?= $cr['format'] === 'pdf' && !empty($cr['last_page']) ? 'Page ' . (int) $cr['last_page'] : 'Progress' ?></span>
                  <span><?= $pct ?>%</span>
                </div>
                <div style="height: 4px; width: 100%; background: rgba(0,0,0,0.06); border-radius: 2px; overflow: hidden;">
                  <div style="height: 100%; width: <?= min(100, max(0, $pct)) ?>%; background: var(--primary-gradient); border-radius: 2px;"></div>
                </div>
              </div>
              <a href="/ebooks/<?= (int) $cr['id'] ?>/read" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 0.78rem; border-radius: 6px;">
                Resume &rarr;
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- FEATURED BOOKS SECTION -->
  <?php if (!empty($featuredBooks)): ?>
    <section class="ebook-section" style="margin-bottom: 44px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
          <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0 0 4px; display: flex; align-items: center; gap: 8px;">
            <span>⭐</span> Featured Community eBooks
          </h2>
          <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Handpicked standout digital books and guides shared by members.</p>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 24px;">
        <?php foreach ($featuredBooks as $fb): ?>
          <?= renderEbookCard($fb) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- MAIN CATALOGUE GRID -->
  <section class="ebook-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
      <div>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0 0 4px;">
          <?php if (!empty($search)): ?>
            Search Results for "<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
          <?php elseif (!empty($activeCategorySlug)): ?>
            eBooks in <?= htmlspecialchars(array_reduce($categories, fn($c, $i) => $i['slug'] === $activeCategorySlug ? $i['name'] : $c, 'Category'), ENT_QUOTES, 'UTF-8') ?>
          <?php else: ?>
            All Published eBooks
          <?php endif; ?>
        </h2>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
          Showing <?= (int) ($pagination['total'] ?? count($books)) ?> community <?= (int) ($pagination['total'] ?? count($books)) === 1 ? 'eBook' : 'eBooks' ?>
        </p>
      </div>

      <?php if (!empty($books)): ?>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
          Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['total_pages'] ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($books)): ?>
      <div class="glass-panel empty-state" style="padding: 48px 24px; text-align: center; border-radius: 20px; border: 1px solid var(--glass-border); background: var(--glass-bg);">
        <div style="font-size: 4rem; margin-bottom: 16px;">📚</div>
        <h3 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">No eBooks Found</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 24px; line-height: 1.5;">
          We couldn't find any community eBooks matching your active search or filters. Try adjusting your query or upload the first eBook in this category!
        </p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
          <a href="/ebooks" class="btn btn-secondary">Clear All Filters</a>
          <a href="/ebooks/upload" class="btn btn-primary">Upload an eBook</a>
        </div>
      </div>
    <?php else: ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px;">
        <?php foreach ($books as $book): ?>
          <?= renderEbookCard($book) ?>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 40px; flex-wrap: wrap;">
          <?php
            $buildPageUrl = static function (int $p) use ($search, $activeCategorySlug, $format, $sort): string {
                $params = ['page' => $p];
                if (!empty($search)) $params['search'] = $search;
                if (!empty($activeCategorySlug)) $params['category'] = $activeCategorySlug;
                if (!empty($format)) $params['format'] = $format;
                if (!empty($sort) && $sort !== 'latest') $params['sort'] = $sort;
                return '/ebooks?' . http_build_query($params);
            };
            $cur = (int) $pagination['page'];
            $tot = (int) $pagination['total_pages'];
          ?>
          <?php if ($cur > 1): ?>
            <a href="<?= $buildPageUrl($cur - 1) ?>" class="btn btn-secondary btn-sm" style="border-radius: 8px;">&larr; Previous</a>
          <?php endif; ?>

          <?php for ($i = max(1, $cur - 2); $i <= min($tot, $cur + 2); $i++): ?>
            <a href="<?= $buildPageUrl($i) ?>" class="btn btn-sm <?= $i === $cur ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 8px; min-width: 36px; text-align: center;">
              <?= $i ?>
            </a>
          <?php endfor; ?>

          <?php if ($cur < $tot): ?>
            <a href="<?= $buildPageUrl($cur + 1) ?>" class="btn btn-secondary btn-sm" style="border-radius: 8px;">Next &rarr;</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

</div>

<?php
/**
 * Helper function to render an eBook card cleanly
 */
function renderEbookCard(array $book): string
{
    $id = (int) $book['id'];
    $title = htmlspecialchars($book['title'], ENT_QUOTES, 'UTF-8');
    $author = htmlspecialchars($book['author_name'], ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars($book['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8');
    $format = strtoupper(htmlspecialchars($book['format'], ENT_QUOTES, 'UTF-8'));
    $readCount = (int) ($book['read_count'] ?? 0);
    $downloads = (int) ($book['download_count'] ?? 0);
    $allowDownload = !empty($book['allow_download']);
    
    // Uploader handle - NEVER leak email!
    $uploaderHandle = htmlspecialchars(\InfinityBlog\Support\UserIdentityHelper::formatHandle($book['uploader_username'] ?? null, $book['uploader_name'] ?? 'member'), ENT_QUOTES, 'UTF-8');

    $coverUrl = !empty($book['cover_path']) 
        ? htmlspecialchars($book['cover_path'], ENT_QUOTES, 'UTF-8') 
        : \InfinityBlog\Ebooks\EbookFileService::generateSvgCover($book['title'], $book['author_name'], $book['category_name'] ?? 'eBook');

    $fileSizeFormatted = '0 KB';
    if (!empty($book['file_size'])) {
        $bytes = (int) $book['file_size'];
        $fileSizeFormatted = $bytes >= 1048576 
            ? number_format($bytes / 1048576, 1) . ' MB' 
            : number_format($bytes / 1024, 0) . ' KB';
    }

    return <<<HTML
    <article class="glass-panel ebook-card" style="border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--glass-border); background: var(--glass-bg); transition: transform 0.25s ease, box-shadow 0.25s ease;">
      <!-- Cover Container with Realistic Aspect Ratio -->
      <a href="/ebooks/{$id}" class="ebook-cover-wrap" style="position: relative; width: 100%; aspect-ratio: 1 / 1.35; overflow: hidden; background: rgba(0,0,0,0.04); display: block;">
        <img src="{$coverUrl}" alt="{$title}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.35s ease;">
        
        <!-- Badges Overlay -->
        <div style="position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap;">
          <span style="font-size: 0.72rem; font-weight: 800; background: rgba(15, 23, 42, 0.85); color: #fff; padding: 3px 8px; border-radius: 6px; backdrop-filter: blur(6px); letter-spacing: 0.5px;">
            {$format}
          </span>
          <span style="font-size: 0.72rem; font-weight: 700; background: rgba(255, 255, 255, 0.9); color: #1e293b; padding: 3px 8px; border-radius: 6px; backdrop-filter: blur(6px);">
            {$category}
          </span>
        </div>

        <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: #fff; font-size: 0.7rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; backdrop-filter: blur(4px);">
          {$fileSizeFormatted}
        </div>
      </a>

      <!-- Card Content -->
      <div style="padding: 18px; display: flex; flex-direction: column; flex: 1;">
        <h3 style="font-size: 1.08rem; font-weight: 700; margin: 0 0 6px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
          <a href="/ebooks/{$id}" style="color: var(--text-main); text-decoration: none;">
            {$title}
          </a>
        </h3>

        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px; font-weight: 500;">
          By <span style="color: var(--text-main); font-weight: 600;">{$author}</span>
        </div>

        <!-- Meta info: Uploader & Reads -->
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-muted); margin-top: auto; padding-top: 10px; border-top: 1px solid var(--glass-border);">
          <span>Shared by {$uploaderHandle}</span>
          <span title="Total reads">👁️ {$readCount}</span>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 8px; margin-top: 14px;">
          <a href="/ebooks/{$id}/read" class="btn btn-primary btn-sm" style="flex: 1; text-align: center; justify-content: center; font-weight: 600; border-radius: 10px; padding: 8px 12px; display: inline-flex; align-items: center; gap: 6px;">
            <span>📖</span> Read
          </a>
          <a href="/ebooks/{$id}" class="btn btn-secondary btn-sm" style="padding: 8px 12px; border-radius: 10px; font-weight: 600;" title="View Details">
            Details
          </a>
        </div>
      </div>
    </article>
HTML;
}
?>

<style>
/* Scoped eBook Library Styles */
.category-pill {
  font-size: 0.82rem;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 20px;
  text-decoration: none;
  color: var(--text-muted);
  background: rgba(0, 0, 0, 0.04);
  border: 1px solid var(--glass-border);
  transition: all 0.2s ease;
  white-space: nowrap;
}
[data-theme="dark"] .category-pill {
  background: rgba(255, 255, 255, 0.06);
}
.category-pill:hover,
.category-pill.active {
  color: #fff;
  background: var(--primary-gradient);
  border-color: transparent;
}
.ebook-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}
[data-theme="dark"] .ebook-card:hover {
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
}
.ebook-card:hover .ebook-cover-wrap img {
  transform: scale(1.03);
}
.continue-reading-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}
</style>
