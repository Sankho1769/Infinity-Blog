<?php
declare(strict_types=1);
/**
 * @var string $requestId
 * @var string $errorMessage
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 * @var string $basePath
 */
$errorMessage = $errorMessage ?? 'An unexpected error occurred while processing your request.';
$base = $basePath ?? '';
?>
<section class="not-found-hero server-error-hero" style="min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 48px 16px;">
  <div class="glass-card not-found-card server-error-card" style="max-width: 580px; width: 100%; text-align: center; padding: 40px 32px; border-radius: var(--radius-lg, 24px); border: 1px solid var(--glass-border); background: var(--glass-bg-card, rgba(255, 255, 255, 0.75)); backdrop-filter: var(--glass-blur, blur(16px)); box-shadow: var(--glass-shadow-lg, 0 20px 40px rgba(0,0,0,0.08));">
    <div class="not-found-badge server-error-badge" style="display: inline-block; padding: 4px 14px; border-radius: 9999px; font-size: 0.85rem; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); margin-bottom: 16px;">
      Error 500
    </div>
    <h1 class="not-found-title" style="font-size: 2rem; font-weight: 800; color: var(--text-dark, #0f172a); margin: 0 0 12px; letter-spacing: -0.02em;">
      Something Went Wrong
    </h1>
    <p class="not-found-desc" style="color: var(--text-muted, #64748b); font-size: 1rem; line-height: 1.6; margin: 0 auto 24px; max-width: 440px;">
      <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <?php if (!empty($requestId)): ?>
      <div class="server-error-meta" style="margin-bottom: 28px; padding: 10px 16px; border-radius: 12px; background: rgba(0, 0, 0, 0.04); display: inline-block; font-size: 0.82rem; color: var(--text-subtle, #94a3b8); border: 1px solid rgba(0, 0, 0, 0.06);">
        Reference ID: <code style="font-family: monospace; font-weight: 600; color: var(--text-dark, #334155);"><?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?></code>
      </div>
    <?php endif; ?>

    <div class="not-found-actions" style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <a href="<?= htmlspecialchars($base . '/') ?>" class="btn btn-primary btn-pill">Back to Home</a>
      <a href="<?= htmlspecialchars($base . '/explore') ?>" class="btn btn-secondary btn-pill">Explore Articles</a>
    </div>
  </div>
</section>
