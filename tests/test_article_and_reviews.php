<?php
declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\MigrationRunner;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\ReviewController;
use InfinityBlog\Http\ScoreController;
use InfinityBlog\Http\WebController;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Reviews\ReviewRepository;
use InfinityBlog\Reviews\ReviewValidationException;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Social\AuthorBadgeService;

ob_start();

echo "=== Running Article Detail and Reader Review Tests ===\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NULL,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'active\',
    role VARCHAR(20) NOT NULL DEFAULT \'author\',
    bio TEXT NULL,
    location VARCHAR(100) NULL,
    interests TEXT NULL,
    website VARCHAR(255) NULL,
    avatar_path VARCHAR(255) NULL,
    banner_path VARCHAR(255) NULL,
    profile_visibility VARCHAR(20) NOT NULL DEFAULT \'public\',
    auth_version INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon_name VARCHAR(50) NULL,
    custom_logo_path VARCHAR(255) NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec("INSERT INTO categories (id, name, slug, description) VALUES (1, 'Technology', 'technology', 'Tech stories')");

$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    summary TEXT NULL,
    summary_status VARCHAR(20) NOT NULL DEFAULT \'empty\',
    summary_source_hash VARCHAR(64) NULL,
    summary_generated_at DATETIME NULL,
    content TEXT NOT NULL,
    cover_image_path VARCHAR(255) NULL,
    video_path VARCHAR(255) NULL,
    remixed_from_post_id INTEGER NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'draft\',
    reading_time INTEGER NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER NOT NULL,
    metadata TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE post_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    score INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, user_id)
)');

$pdo->exec('CREATE TABLE post_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'published\',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, user_id)
)');

$pdo->exec('CREATE TABLE review_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    review_id INTEGER NOT NULL,
    reporter_id INTEGER NOT NULL,
    reason VARCHAR(100) NOT NULL,
    explanation VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT \'pending\',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE user_follows (
    follower_id INTEGER NOT NULL,
    following_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id)
)');

$pdo->exec('CREATE TABLE user_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    actor_id INTEGER NULL,
    type VARCHAR(50) NOT NULL,
    reference_id INTEGER NULL,
    message VARCHAR(255) NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE article_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    parent_id INTEGER NULL,
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'published\',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE post_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    reporter_id INTEGER NOT NULL,
    reason VARCHAR(100) NOT NULL,
    explanation VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT \'pending\',
    moderation_notes TEXT NULL,
    reviewed_by INTEGER NULL,
    reviewed_at TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// Seed test users
function insertTestUser(PDO $pdo, string $username, string $email, string $displayName, string $role): int {
    $stmt = $pdo->prepare('INSERT INTO users (username, email, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, $displayName, password_hash('password123', PASSWORD_DEFAULT), $role, 'active']);
    return (int) $pdo->lastInsertId();
}

$userRepo = new UserRepository($pdo);
$authorId = insertTestUser($pdo, 'author1', 'author1@example.com', 'Author One', 'author');
$reader1Id = insertTestUser($pdo, 'reader1', 'reader1@example.com', 'Reader One', 'reader');
$reader2Id = insertTestUser($pdo, 'reader2', 'reader2@example.com', 'Reader Two', 'reader');
$reader3Id = insertTestUser($pdo, 'reader3', 'reader3@example.com', 'Reader Three', 'reader');
$reader4Id = insertTestUser($pdo, 'reader4', 'reader4@example.com', 'Reader Four', 'reader');
$reader5Id = insertTestUser($pdo, 'reader5', 'reader5@example.com', 'Reader Five', 'reader');
$adminId = insertTestUser($pdo, 'admin1', 'admin1@example.com', 'Admin User', 'admin');

// Seed published post and draft post
function insertTestPost(PDO $pdo, int $authorId, string $title, string $slug, string $content, string $status, ?string $summary = null): int {
    $stmt = $pdo->prepare('INSERT INTO posts (author_id, category_id, title, slug, summary, content, status, published_at) VALUES (?, 1, ?, ?, ?, ?, ?, ?)');
    $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
    $stmt->execute([$authorId, $title, $slug, $summary, $content, $status, $publishedAt]);
    return (int) $pdo->lastInsertId();
}

$postRepo = new PostRepository($pdo);
$publishedPostId = insertTestPost(
    $pdo,
    $authorId,
    'Mastering Modern Architecture',
    'mastering-modern-architecture',
    "# Introduction\n\nHere is how modular architecture works.\n\n```python\nprint('hello world')\n```\n\n![Diagram](/assets/images/architecture.png)\n\n[Documentation](https://example.com/docs)",
    'published',
    'A comprehensive guide to distributed systems and modular design.'
);

$draftPostId = insertTestPost(
    $pdo,
    $authorId,
    'Draft In Progress',
    'draft-in-progress',
    'This is a secret draft.',
    'draft',
    'Unpublished draft article.'
);

$appConfig = AppConfig::fromEnvironment();
$sessionManager = new SessionManager($appConfig);
$authGuard = new AuthGuard($sessionManager, $userRepo);
$scoreRepo = new ScoreRepository($pdo);
$reviewRepo = new ReviewRepository($pdo, $scoreRepo);
$badgeService = new AuthorBadgeService($pdo);

$testCount = 0;
$passCount = 0;

$assert = function (bool $condition, string $msg) use (&$testCount, &$passCount) {
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg}\n";
    }
};

// 1. Article Access & Visibility Tests
echo "\n--- 1. Article Content & Visibility ---\n";
$published = $postRepo->findPublished($publishedPostId);
$assert($published !== null && $published['title'] === 'Mastering Modern Architecture', 'Published article is retrievable');

$draftPublished = $postRepo->findPublished($draftPostId);
$assert($draftPublished === null, 'Draft post is not accessible via findPublished');

// Test Markdown rendering
$renderer = new \InfinityBlog\Posts\ArticleContentRenderer();
$rendered = $renderer->render($published['content']);
$assert(str_contains($rendered, '<h1>Introduction</h1>'), 'Markdown headings rendered properly');
$assert(str_contains($rendered, '<pre class="article-code-block">'), 'Markdown code blocks rendered properly');
$assert(str_contains($rendered, '<img'), 'Markdown embedded images rendered properly');

// 2. Reader Rating System (Scores) Tests
echo "\n--- 2. Reader Rating System (Scores) ---\n";

// Author self-rating must be prevented
try {
    $scoreRepo->submitScore($publishedPostId, $authorId, 5);
    $assert(false, 'Author should not be able to rate own article');
} catch (\InfinityBlog\Scores\ScoreValidationException $e) {
    $assert(str_contains($e->getMessage(), 'own'), 'Prevented author self-rating on article');
}

// Reader 1 submits score 5
$res1 = $scoreRepo->submitScore($publishedPostId, $reader1Id, 5);
$assert($res1['user_score'] === 5, 'Reader 1 submitted score 5');
$assert($res1['count'] === 1 && (float)$res1['average'] === 5.0, 'Aggregate count 1, avg 5.0');

// Reader 1 updates score to 4
$res1Update = $scoreRepo->submitScore($publishedPostId, $reader1Id, 4);
$assert($res1Update['user_score'] === 4, 'Reader 1 updated score to 4');
$assert($res1Update['count'] === 1 && (float)$res1Update['average'] === 4.0, 'Aggregate count still 1 after update, avg 4.0');

// Reader 2 submits score 5
$res2 = $scoreRepo->submitScore($publishedPostId, $reader2Id, 5);
$assert($res2['count'] === 2 && (float)$res2['average'] === 4.5, 'Reader 2 submitted, count 2, avg 4.5');

// Invalid score rejection (< 1 or > 5)
try {
    $scoreRepo->submitScore($publishedPostId, $reader3Id, 6);
    $assert(false, 'Score > 5 should be rejected');
} catch (\InfinityBlog\Scores\ScoreValidationException $e) {
    $assert(true, 'Score > 5 rejected with validation error');
}

// 3. Written Reviews System Tests
echo "\n--- 3. Written Reviews System ---\n";

// Author self-review must be prevented
try {
    $reviewRepo->submitReview($publishedPostId, $authorId, 5, 'Great story written by me!');
    $assert(false, 'Author should not be able to review own article');
} catch (ReviewValidationException $e) {
    $assert(str_contains($e->getMessage(), 'own'), 'Prevented author self-review');
}

// Empty review content must be rejected
try {
    $reviewRepo->submitReview($publishedPostId, $reader3Id, 5, '   ');
    $assert(false, 'Empty review content should be rejected');
} catch (ReviewValidationException $e) {
    $assert(str_contains($e->getMessage(), 'empty'), 'Empty review content correctly rejected');
}

// Reader 3 submits written review with 5 stars
$reviewRes3 = $reviewRepo->submitReview(
    $publishedPostId,
    $reader3Id,
    5,
    'Outstanding breakdown of modular design patterns. Really enjoyed the architectural depth.'
);
$assert($reviewRes3['review']['rating'] === 5, 'Reader 3 submitted 5-star review');
$assert(str_contains($reviewRes3['review']['content'], 'Outstanding breakdown'), 'Review content stored accurately');

// Verify score synchronization: post_scores should now have reader 3's rating
$reader3Score = $scoreRepo->getUserScore($publishedPostId, $reader3Id);
$assert($reader3Score === 5, 'Review submission synced score to post_scores');

// Reader 3 updates their written review
$reviewRes3Update = $reviewRepo->submitReview(
    $publishedPostId,
    $reader3Id,
    4,
    'Updated review: thoroughly enjoyed, though a few more code examples would help.'
);
$assert($reviewRes3Update['review']['rating'] === 4, 'Review rating updated to 4');
$assert(str_contains($reviewRes3Update['review']['content'], 'Updated review'), 'Review text updated');
$reader3ScoreUpdated = $scoreRepo->getUserScore($publishedPostId, $reader3Id);
$assert($reader3ScoreUpdated === 4, 'Review update synced score in post_scores');

// Check user review lookup
$userRev = $reviewRepo->getUserReview($publishedPostId, $reader3Id);
$assert($userRev !== null && $userRev['rating'] === 4, 'getUserReview returns user review');

// Reader 4 and 5 submit reviews
$reviewRepo->submitReview($publishedPostId, $reader4Id, 5, 'Super clear explanations and diagrams.');
$reviewRepo->submitReview($publishedPostId, $reader5Id, 5, 'Best article on the platform this month.');

// Check paginated reviews list
$reviewsList = $reviewRepo->getReviewsForPost($publishedPostId, $reader3Id, 1, 10);
$assert($reviewsList['total'] === 3, 'Found 3 total written reviews');
$assert(count($reviewsList['reviews']) === 3, 'Returned 3 reviews for page 1');
$firstRev = $reviewsList['reviews'][0];
$assert(!empty($firstRev['username']), 'Review includes reviewer username');
$assert(isset($firstRev['is_own_review']), 'Review includes is_own_review flag');

// Test review deletion authorization
// Non-author non-admin trying to delete Reader 3's review
try {
    $reviewRepo->deleteReview((int)$reviewRes3['review']['id'], $reader1Id, false);
    $assert(false, 'Unauthorized user should not delete other review');
} catch (ReviewValidationException $e) {
    $assert(str_contains($e->getMessage(), 'authorized'), 'Blocked unauthorized review deletion');
}

// 4. Review Reporting Tests
echo "\n--- 4. Review Reporting ---\n";
$reviewId = (int) $reviewRes3['review']['id'];

// Self-reporting prevention
$selfReport = $reviewRepo->reportReview($reviewId, $reader3Id, 'Spam or misleading content');
$assert($selfReport['status'] === 'forbidden', 'Prevented self-reporting on review');

// Reader 1 reports Reader 3's review
$reportRes = $reviewRepo->reportReview($reviewId, $reader1Id, 'Harassment or bullying', 'Contains offensive language.');
$assert($reportRes['status'] === 'success', 'Report successfully filed');

// Duplicate report prevention
$dupReport = $reviewRepo->reportReview($reviewId, $reader1Id, 'Harassment or bullying');
$assert($dupReport['status'] === 'duplicate', 'Duplicate active report blocked');

// Test report listing and status update
$reportsList = $reviewRepo->listReports(1, 10);
$assert($reportsList['total'] >= 1, 'Review reports listed successfully');
$firstReport = $reportsList['reports'][0];
$assert((int)$firstReport['review_id'] === $reviewId, 'Report matches reported review ID');
$assert($firstReport['reviewer_username'] === 'reader3', 'Report includes reviewer username');
$assert($firstReport['reporter_username'] === 'reader1', 'Report includes reporter username');
$assert(!empty($firstReport['post_title']), 'Report includes post title');

$reportUpdateOk = $reviewRepo->updateReportStatus((int)$firstReport['id'], 'under_review');
$assert($reportUpdateOk === true, 'Review report status updated to under_review');

$reportUpdateBad = $reviewRepo->updateReportStatus((int)$firstReport['id'], 'invalid_status');
$assert($reportUpdateBad === false, 'Invalid report status rejected');

// 5. Author Badge Integration with Scores and Reviews
echo "\n--- 5. Author Badge Integration ---\n";
// Currently author has:
// reader 1: 4
// reader 2: 5
// reader 3: 4
// reader 4: 5
// reader 5: 5
// total valid ratings = 5, sum = 23, avg = 4.6 (>= 4.5 and >= 5 ratings!)
$badgeStatus = $badgeService->getAuthorBadgeStatus($authorId);
$assert($badgeStatus['meets_rating_req'] === true, 'Author meets rating requirement (>= 4.5 avg with >= 5 ratings)');
$assert($badgeStatus['qualified'] === true, 'Author qualifies for Author recognition badge');

// 6. Review Controller API Endpoints Tests
echo "\n--- 6. Review Controller API Endpoints ---\n";
$reviewCtrl = new ReviewController($reviewRepo, $authGuard, $sessionManager);

// Session login as reader 1
$reader1Account = $userRepo->findById($reader1Id);
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionManager->start();
}
$sessionManager->establish($reader1Account);
$csrfToken = $sessionManager->csrfToken();

// GET /api/posts/{id}/reviews
$getReq = Request::fromValues(
    'GET',
    "/api/posts/{$publishedPostId}/reviews",
    '127.0.0.1',
    ['x-csrf-token' => $csrfToken],
    [],
    ['page' => '1', 'per_page' => '10']
);
$getRes = $reviewCtrl->handle($getReq);
$assert($getRes['status'] === 200, 'GET reviews returns status 200');
$assert(isset($getRes['body']['data']['reviews']), 'GET reviews returns reviews array');

// POST /api/posts/{id}/reviews without CSRF -> 403
$unauthPostReq = Request::fromValues(
    'POST',
    "/api/posts/{$publishedPostId}/reviews",
    '127.0.0.1',
    [],
    ['rating' => 5, 'content' => 'Great!']
);
$unauthPostRes = $reviewCtrl->handle($unauthPostReq);
$assert($unauthPostRes['status'] === 403, 'POST review without CSRF rejected with 403');

// POST /api/posts/{id}/reviews with valid CSRF
$authPostReq = Request::fromValues(
    'POST',
    "/api/posts/{$publishedPostId}/reviews",
    '127.0.0.1',
    [
        'x-csrf-token' => $csrfToken,
        'content-type' => 'application/json'
    ],
    ['rating' => 5, 'content' => 'Super insightful read from start to finish.']
);
$authPostRes = $reviewCtrl->handle($authPostReq);
$assert($authPostRes['status'] === 200, 'POST review with CSRF succeeds with 200');
$assert(isset($authPostRes['body']['data']['review']['rating']) && $authPostRes['body']['data']['review']['rating'] === 5, 'POST review created 5-star review');

// DELETE /api/reviews/{id} with valid CSRF as reviewer
$reader1ReviewId = (int) $authPostRes['body']['data']['review']['id'];
$delReq = Request::fromValues(
    'DELETE',
    "/api/reviews/{$reader1ReviewId}",
    '127.0.0.1',
    [
        'x-csrf-token' => $csrfToken,
    ]
);
$delRes = $reviewCtrl->handle($delReq);
$assert($delRes['status'] === 200, 'DELETE review returns 200');
$assert(isset($delRes['body']['data']['deleted']) && $delRes['body']['data']['deleted'] === true, 'DELETE review confirmed deleted');

$scoreAfterDelete = $scoreRepo->getUserScore($publishedPostId, $reader1Id);
$assert($scoreAfterDelete === null, 'Deleted review also purged user score from post_scores');

// Non-admin calling GET /api/reviews/reports -> 403
$unauthReportsReq = Request::fromValues('GET', '/api/reviews/reports', '127.0.0.1');
$unauthReportsRes = $reviewCtrl->handle($unauthReportsReq);
$assert($unauthReportsRes['status'] === 403, 'Non-admin blocked from listing review reports');

// Login as admin
$adminAccount = $userRepo->findById($adminId);
$sessionManager->establish($adminAccount);
$adminCsrfToken = $sessionManager->csrfToken();

// Admin calling GET /api/reviews/reports -> 200
$adminReportsReq = Request::fromValues('GET', '/api/reviews/reports', '127.0.0.1', ['x-csrf-token' => $adminCsrfToken]);
$adminReportsRes = $reviewCtrl->handle($adminReportsReq);
$assert($adminReportsRes['status'] === 200, 'Admin can list review reports');
$assert(isset($adminReportsRes['body']['data']['reports']), 'Admin review reports response contains reports array');

// Admin updating report status via POST /api/reviews/reports/{id}/status -> 200
$repId = (int) $firstReport['id'];
$adminStatusReq = Request::fromValues(
    'POST',
    "/api/reviews/reports/{$repId}/status",
    '127.0.0.1',
    [
        'x-csrf-token' => $adminCsrfToken,
        'content-type' => 'application/json',
    ],
    ['status' => 'action_taken']
);
$adminStatusRes = $reviewCtrl->handle($adminStatusReq);
$assert($adminStatusRes['status'] === 200, 'Admin can update review report status');
$assert($adminStatusRes['body']['data']['status'] === 'action_taken', 'Report status set to action_taken');

$sessionManager->clearAuthentication();

// 7. Feed Card click isolation verification in HTML
echo "\n--- 7. Feed Card Markup & Isolation ---\n";
$appConfig = AppConfig::fromEnvironment();
$webController = new WebController(
    $postRepo,
    new \InfinityBlog\Categories\CategoryRepository($pdo),
    $userRepo,
    new \InfinityBlog\Users\AdminUserRepository($pdo),
    new \InfinityBlog\Audit\AuditLogRepository($pdo),
    $authGuard,
    $sessionManager,
    $appConfig,
    $scoreRepo,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    $reviewRepo
);

$homeReq = Request::fromValues('GET', '/', '127.0.0.1');
$homeRes = $webController->home($homeReq);
$assert($homeRes['status'] === 200, 'Homepage renders with status 200');
$assert(str_contains($homeRes['body'], 'data-post-id'), 'Homepage feed cards contain data-post-id attribute');
$assert(str_contains($homeRes['body'], 'href="/article/'), 'Feed card titles link to /article/{id}');
$assert(str_contains($homeRes['body'], 'btn-feed-like'), 'Feed cards include Like button');
$assert(str_contains($homeRes['body'], 'btn-feed-dislike'), 'Feed cards include Dislike button');
$assert(str_contains($homeRes['body'], 'btn-feed-share'), 'Feed cards include Share button');

// 8. Article Detail View Rendering
echo "\n--- 8. Article Detail View Rendering ---\n";
$articleReq = Request::fromValues('GET', "/article/{$publishedPostId}", '127.0.0.1');
$articleRes = $webController->article($articleReq, $publishedPostId);
$assert($articleRes['status'] === 200, 'Article detail renders with status 200');
$assert(str_contains($articleRes['body'], 'id="reader-score"'), 'Article detail contains #reader-score section');
$assert(str_contains($articleRes['body'], 'id="reader-reviews"'), 'Article detail contains #reader-reviews section');
$assert(str_contains($articleRes['body'], 'id="commentsSection"'), 'Article detail contains distinct #commentsSection');
$assert(str_contains($articleRes['body'], 'Reader Reviews ('), 'Article detail displays reviews header and count');
$assert(str_contains($articleRes['body'], 'id="report-review-modal"'), 'Article detail has report review modal');

// 404 on Draft Post for guest
$draftReq = Request::fromValues('GET', "/article/{$draftPostId}", '127.0.0.1');
$draftRes = $webController->article($draftReq, $draftPostId);
$assert($draftRes['status'] === 404, 'Draft post returns 404 for guest visitors');

echo "\n=============================================\n";
echo "Results: {$passCount}/{$testCount} tests passed.\n";
ob_end_flush();
if ($passCount === $testCount) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
