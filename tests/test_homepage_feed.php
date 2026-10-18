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
$_SESSION = []; // Ensure fresh guest session
$guard = new AuthGuard($sessions, $users);

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

echo "=== 1. Testing Homepage Feed Tabs & Greetings (Guest Mode) ===\n";

// Guest visit: For You
$req = Request::fromValues('GET', '/', '127.0.0.1', [], [], ['tab' => 'for-you']);
$res = $web->home($req);
assertCondition($res['status'] === 200, "Guest visit / returns HTTP 200");
$html = $res['body'];
assertCondition(str_contains($html, 'feed-header-section'), "Contains .feed-header-section");
assertCondition(str_contains($html, 'Welcome to Infinity'), "Guest receives Welcome greeting");
assertCondition(str_contains($html, 'feed-tabs-bar'), "Contains .feed-tabs-bar");
assertCondition(str_contains($html, 'For You'), "Contains For You tab");
assertCondition(str_contains($html, 'Following'), "Contains Following tab");
assertCondition(str_contains($html, 'Latest'), "Contains Latest tab");
assertCondition(str_contains($html, 'Popular'), "Contains Popular tab");
assertCondition(!str_contains($html, '>News<'), "Does NOT contain fake News tab");

// Guest visit: Following tab -> prompts to sign in
$req = Request::fromValues('GET', '/', '127.0.0.1', [], [], ['tab' => 'following']);
$res = $web->home($req);
$html = $res['body'];
assertCondition(str_contains($html, 'Follow your favorite authors'), "Guest on Following tab sees follow prompt");
assertCondition(str_contains($html, '/login?redirect='), "Prompt contains redirect to sign in");

// Feed cards structure
$req = Request::fromValues('GET', '/', '127.0.0.1', [], [], ['tab' => 'for-you']);
$res = $web->home($req);
$html = $res['body'];
assertCondition(str_contains($html, 'feed-card') || str_contains($html, 'feed-cards-container'), "Contains feed card container");
assertCondition(str_contains($html, 'feed-card-header'), "Feed card contains .feed-card-header");
assertCondition(str_contains($html, 'feed-card-actions'), "Feed card contains .feed-card-actions");
assertCondition(str_contains($html, 'btn-feed-like'), "Feed card contains Like button");
assertCondition(str_contains($html, 'btn-feed-dislike'), "Feed card contains Dislike button");
assertCondition(str_contains($html, 'feed-comments-btn'), "Feed card contains Comments button");
assertCondition(str_contains($html, 'btn-feed-share'), "Feed card contains Share button");
assertCondition(str_contains($html, 'btn-feed-bookmark'), "Feed card contains Bookmark button");
assertCondition(str_contains($html, 'btn-feed-remix'), "Feed card contains Remix button");
assertCondition(str_contains($html, 'feed-card-media'), "Feed card contains right-side thumbnail media");

echo "\n=== 2. Testing Quick Post Feed Composer API ===\n";

// Unauthenticated quick-post returns 401
$req = Request::fromValues('POST', '/api/feed/quick-post', '127.0.0.1', [], ['content' => 'Hello from quick post', 'category_id' => 1]);
$res = $web->quickPost($req);
assertCondition($res['status'] === 401, "Unauthenticated quick-post returns 401");

// Create active session for test author
$eligibleAuthors = $users->listPublicAuthors();
if (!empty($eligibleAuthors)) {
    $authorId = (int) $eligibleAuthors[0]['id'];
    $authorAccount = $users->findById($authorId);
    $now = time();
    $_SESSION['auth'] = [
        'user_id' => $authorAccount->id,
        'auth_version' => $authorAccount->authVersion,
        'issued_at' => $now,
        'last_activity' => $now,
    ];
    $csrfToken = $sessions->csrfToken();

    // Missing CSRF returns 403
    $req = Request::fromValues('POST', '/api/feed/quick-post', '127.0.0.1', [], ['content' => 'Hello from quick post', 'category_id' => 1]);
    $res = $web->quickPost($req);
    assertCondition($res['status'] === 403, "Missing CSRF on quick-post returns 403");

    // Empty content returns 422
    $req = Request::fromValues('POST', '/api/feed/quick-post', '127.0.0.1', ['X-CSRF-Token' => $csrfToken], ['content' => '', 'category_id' => 1]);
    $res = $web->quickPost($req);
    assertCondition($res['status'] === 422, "Empty content on quick-post returns 422");

    // Valid quick-post creates published post
    $quickText = 'Testing quick publish from homepage feed with full persistence and markdown!';
    $req = Request::fromValues('POST', '/api/feed/quick-post', '127.0.0.1', ['X-CSRF-Token' => $csrfToken], [
        'content' => $quickText,
        'category_id' => 1
    ]);
    $res = $web->quickPost($req);
    assertCondition($res['status'] === 201, "Valid quick-post returns 201 Created");
    $resData = json_decode($res['body'], true);
    $createdId = (int) ($resData['data']['post_id'] ?? 0);
    assertCondition($createdId > 0, "Created post has valid ID ({$createdId})");

    // Verify created post in database
    $saved = $posts->findPublished($createdId);
    assertCondition($saved !== null, "Quick-posted article exists in database");
    assertCondition((int) $saved['author_id'] === $authorAccount->id, "Author matches authenticated account");
    assertCondition($saved['status'] === 'published', "Status is published immediately");
    assertCondition(str_contains($saved['content'], $quickText), "Content is persisted accurately");

    // Clean up test post
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$createdId]);
} else {
    echo "SKIP: author account not found for quick-post auth test\n";
}

echo "\n=== 3. Testing Authenticated Following Feed ===\n";

if (!empty($authorAccount)) {
    $req = Request::fromValues('GET', '/', '127.0.0.1', [], [], ['tab' => 'following']);
    $res = $web->home($req);
    $html = $res['body'];
    assertCondition(str_contains($html, "You're not following any authors yet") || str_contains($html, "Stories from Authors You Follow"), "Authenticated following feed handled gracefully");
}

echo "\n=== Summary: {$passed} passed, {$failed} failed. ===\n";
if ($failed > 0) exit(1);
