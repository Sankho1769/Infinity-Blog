<?php
declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Http\WebController;
use InfinityBlog\Http\Request;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Users\AdminUserRepository;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Library\LibraryRepository;
use InfinityBlog\Posts\ArticleContentRenderer;

$cfg = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($cfg->database);

$users = new UserRepository($pdo);
$community = new CommunityRepository($pdo);
$follows = new FollowRepository($pdo);
$posts = new PostRepository($pdo);
$categories = new CategoryRepository($pdo);
$adminUsers = new AdminUserRepository($pdo);
$audit = new AuditLogRepository($pdo);
$scores = new ScoreRepository($pdo);
$library = new LibraryRepository($pdo);
$sessions = new SessionManager($cfg);
$sessions->start();
$_SESSION = [];
$guard = new AuthGuard($sessions, $users);

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo "PASS: {$msg}\n";
        $passed++;
    } else {
        echo "FAIL: {$msg}\n";
        $failed++;
    }
}

echo "=== 1. Testing ArticleContentRenderer::renderFeedSnippet & Image Extraction ===\n";

$renderer = new ArticleContentRenderer();

// Test extracting inline markdown image
$contentWithImage = "Check out this exciting architecture diagram!\n\n![System Architecture](/uploads/inline/inline_1_abc123.png)\n\nIt explains our message queue.";
$snippet = $renderer->renderFeedSnippet($contentWithImage, 200);

assertCondition($snippet['primary_image'] !== null, "Extracts primary inline image");
assertCondition($snippet['primary_image']['url'] === '/uploads/inline/inline_1_abc123.png', "Image URL correctly extracted");
assertCondition($snippet['primary_image']['alt'] === 'System Architecture', "Image alt correctly extracted");
assertCondition(!str_contains($snippet['text'], '!['), "Raw markdown image tag ![] is completely removed from text snippet");
assertCondition(!str_contains($snippet['text'], '/uploads/inline/'), "Image path is not printed as plain text in snippet");
assertCondition(str_contains($snippet['text'], 'Check out this exciting architecture diagram!'), "Text before image is preserved");
assertCondition(str_contains($snippet['text'], 'It explains our message queue.'), "Text after image is preserved");

// Test relative path normalization (uploads/ -> /uploads/)
$contentRel = "Hello ![Photo](uploads/inline/photo.jpg) world";
$snippetRel = $renderer->renderFeedSnippet($contentRel);
assertCondition($snippetRel['primary_image'] !== null, "Extracts image with uploads/ prefix");
assertCondition($snippetRel['primary_image']['url'] === '/uploads/inline/photo.jpg', "Normalizes uploads/ to /uploads/");

// Test unsafe protocol rejection (e.g. javascript:)
$contentUnsafe = "Bad ![XSS](javascript:alert(1))";
$snippetUnsafe = $renderer->renderFeedSnippet($contentUnsafe);
assertCondition($snippetUnsafe['primary_image'] === null, "Rejects javascript: URI in image extraction");

// Test markdown headings and formatting stripped
$contentFormatted = "### Heading 3\n\n**Bold words** and *italic words* with `code block`.\n\n---\nAnother line.";
$snippetFormatted = $renderer->renderFeedSnippet($contentFormatted);
assertCondition(!str_contains($snippetFormatted['text'], '###'), "Markdown ### headings stripped");
assertCondition(!str_contains($snippetFormatted['text'], '**'), "Markdown ** bold stripped");
assertCondition(!str_contains($snippetFormatted['text'], '---'), "Markdown horizontal rules stripped");
assertCondition(str_contains($snippetFormatted['text'], 'Bold words and italic words with code block. Another line.'), "Clean formatted text output");

echo "\n=== 2. Testing Single Unified Composer in Homepage HTML ===\n";



$web = new WebController(
    $posts,
    $categories,
    $users,
    $adminUsers,
    $audit,
    $guard,
    $sessions,
    $cfg,
    $scores,
    null,
    $follows,
    null,
    null,
    $library,
    $community
);

// Authenticated session for composer check
$eligibleAuthors = $users->listPublicAuthors();
if (!empty($eligibleAuthors)) {
    $authorAccount = $users->findById((int) $eligibleAuthors[0]['id']);
    $now = time();
    $_SESSION['auth'] = [
        'user_id' => $authorAccount->id,
        'auth_version' => $authorAccount->authVersion,
        'issued_at' => $now,
        'last_activity' => $now,
    ];
}

$req = Request::fromValues('GET', '/', '127.0.0.1');
$res = $web->home($req);
$html = $res['body'];

assertCondition(str_contains($html, 'feed-composer-card'), "Contains .feed-composer-card");
assertCondition(str_contains($html, 'id="composer-text-input"'), "Contains single #composer-text-input textarea");
assertCondition(!str_contains($html, 'id="composer-quick-input"'), "Eliminated legacy duplicate #composer-quick-input");
assertCondition(!str_contains($html, 'id="composer-content-input"'), "Eliminated legacy duplicate #composer-content-input");
assertCondition(str_contains($html, 'composer-attach-btn'), "Contains image attachment button");
assertCondition(str_contains($html, 'id="composer-image-file-input"'), "Contains hidden image file input");
assertCondition(str_contains($html, 'id="composer-image-preview-tray"'), "Contains image preview tray");
assertCondition(str_contains($html, 'id="composer-category-select"'), "Contains category selector in composer tray");
assertCondition(str_contains($html, 'id="composer-submit-btn"'), "Contains submit button in composer tray");

echo "\n=== 3. Testing Floating Sidebars & Feed Cards HTML ===\n";

assertCondition(str_contains($html, 'app-left-sidebar'), "Contains .app-left-sidebar");
assertCondition(str_contains($html, 'homepage-sidebar'), "Contains .homepage-sidebar");
assertCondition(str_contains($html, 'sidebar-card-authors'), "Contains authors recommendation card");
assertCondition(str_contains($html, 'sidebar-card-communities'), "Contains communities recommendation card");
assertCondition(str_contains($html, 'btn-feed-report'), "Feed cards contain .btn-feed-report button");
assertCondition(str_contains($html, 'report-article-modal'), "Layout contains global #report-article-modal");

echo "\n=== 4. Testing CSS Floating Sidebar Rules in style.css ===\n";

$cssPath = __DIR__ . '/../public/assets/css/style.css';
$css = file_get_contents($cssPath);

assertCondition(preg_match('/\.app-left-sidebar\s*\{[^}]*position:\s*sticky/s', $css) === 1, ".app-left-sidebar has position: sticky");
assertCondition(preg_match('/\.app-left-sidebar\s*\{[^}]*top:\s*16px/s', $css) === 1, ".app-left-sidebar has top: 16px");
assertCondition(preg_match('/\.app-left-sidebar\s*\{[^}]*margin:\s*16px/s', $css) === 1, ".app-left-sidebar has margin: 16px detached panel spacing");
assertCondition(preg_match('/\.app-left-sidebar\s*\{[^}]*border-radius:\s*var\(--radius-xl\)/s', $css) === 1, ".app-left-sidebar has border-radius: var(--radius-xl)");
assertCondition(preg_match('/\.homepage-sidebar\s*\{[^}]*position:\s*sticky/s', $css) === 1, ".homepage-sidebar has position: sticky");
assertCondition(preg_match('/\.homepage-sidebar\s*\{[^}]*max-height:\s*calc\(100vh\s*-\s*100px\)/s', $css) === 1, ".homepage-sidebar has max-height for comfortable scrolling");
assertCondition(preg_match('/\.sidebar-card\s*\{[^}]*border-radius:\s*var\(--radius-xl\)/s', $css) === 1, ".sidebar-card has border-radius: var(--radius-xl)");
assertCondition(preg_match('/\.feed-card-inline-media\s*\{/s', $css) === 1, "Contains .feed-card-inline-media rules");
assertCondition(preg_match('/\.feed-card-inline-image\s*\{/s', $css) === 1, "Contains .feed-card-inline-image rules");

echo "\n=== Floating Sidebars & Feed Verification: {$passed} passed, {$failed} failed. ===\n";
if ($failed > 0) exit(1);
