<?php
declare(strict_types=1);
/**
 * @var string $errorMessage
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 */
$errorMessage = $errorMessage ?? 'The page or article you requested was not found or is no longer available.';
?>
<section class="not-found-hero">
  <div class="glass-card not-found-card">
    <div class="not-found-badge">Error 404</div>
    <h1 class="not-found-title">Page Not Found</h1>
    <p class="not-found-desc">
      <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div class="not-found-search">
      <form action="/explore" method="GET" class="not-found-search-form" role="search">
        <input type="search" name="search" class="form-input not-found-search-input" placeholder="Search articles or topics..." aria-label="Search articles">
        <button type="submit" class="btn btn-primary btn-pill">Search</button>
      </form>
    </div>

    <div class="not-found-actions">
      <a href="/" class="btn btn-primary btn-pill">Back to Home</a>
      <a href="/explore" class="btn btn-secondary btn-pill">Explore Articles</a>
      <a href="/categories" class="btn btn-secondary btn-pill">Browse Categories</a>
    </div>
  </div>
</section>
