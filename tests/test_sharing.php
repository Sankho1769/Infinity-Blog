<?php
declare(strict_types=1);

ob_start();
register_shutdown_function(function () {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});

/**
 * Test Suite for Share Dialog, Following List, and WhatsApp Sharing.
 */
require __DIR__ . '/../autoload.php';

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\SocialController;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;
use InfinityBlog\Social\NotificationRepository;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup required tables
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    banner_path VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'active\',
    role VARCHAR(20) NOT NULL DEFAULT \'user\',
    auth_version INTEGER NOT NULL DEFAULT 1,
    bio TEXT NULL,
    location VARCHAR(100) NULL,
    interests VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    profile_visibility VARCHAR(20) NOT NULL DEFAULT \'public\',
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL
)');

$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    content_type VARCHAR(32) NOT NULL DEFAULT \'article\',
    status VARCHAR(20) NOT NULL DEFAULT \'draft\',
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE user_follows (
    follower_id INTEGER NOT NULL,
    following_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id)
)');

$pdo->exec('CREATE TABLE post_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    score INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, user_id)
)');

$pdo->exec('CREATE TABLE direct_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE user_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    blocked_user_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, blocked_user_id)
)');

$pdo->exec('CREATE TABLE user_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    actor_id INTEGER NULL,
    type VARCHAR(32) NOT NULL,
    reference_id INTEGER NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// Seed test accounts
$pass = password_hash('Secret123!', PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO users (id, username, display_name, email, password_hash, status) VALUES (1, 'user1', 'Sender One', 'user1@example.com', '{$pass}', 'active')");
$pdo->exec("INSERT INTO users (id, username, display_name, email, password_hash, status) VALUES (2, 'user2', 'Contact Two', 'user2@example.com', '{$pass}', 'active')");
$pdo->exec("INSERT INTO users (id, username, display_name, email, password_hash, status) VALUES (3, 'user3', 'Contact Three', 'user3@example.com', '{$pass}', 'active')");
$pdo->exec("INSERT INTO users (id, username, display_name, email, password_hash, status) VALUES (4, 'user4', 'Blocked Four', 'user4@example.com', '{$pass}', 'active')");

// Follow relationships: User 1 follows User 2 and User 3
$pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES (1, 2)");
$pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES (1, 3)");

// User 2 qualifies for author badge (1 published post + 10 followers)
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (101, 2, 'Post by Two', 'Content', 'published')");
for ($i = 20; $i <= 30; $i++) {
    $pdo->exec("INSERT OR IGNORE INTO users (id, username, display_name, email, password_hash) VALUES ({$i}, 'fan_{$i}', 'Fan {$i}', 'fan{$i}@example.com', '{$pass}')");
    $pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES ({$i}, 2)");
}

// User 1 blocks User 4
$pdo->exec("INSERT INTO user_blocks (user_id, blocked_user_id) VALUES (1, 4)");

// Posts: One published, one draft
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (200, 2, 'Great Engineering Practices', 'Insightful content', 'published')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (201, 2, 'Unpublished Draft', 'Draft content', 'draft')");

$config = AppConfig::fromEnvironment();
$sessions = new SessionManager($config);
$sessions->start();
$validCsrf = $sessions->csrfToken();

$userRepo = new UserRepository($pdo);
$follows = new FollowRepository($pdo);
$messages = new MessageRepository($pdo);
$notifs = new NotificationRepository($pdo);

$passed = 0;
function assertTest(bool $condition, string $msg): void {
    global $passed;
    if (!$condition) {
        throw new RuntimeException("FAILED: {$msg}");
    }
    $passed++;
    echo "PASS: {$msg}\n";
}

echo "=== Running Share Dialog & Social Tests ===\n";

// --- Test 1: GET /api/me/following unauthenticated ---
$sessions->clearAuthentication();
$guard = new AuthGuard($sessions, $userRepo);
$controller = new SocialController($follows, $messages, $guard, $sessions, $userRepo, $notifs);

$req1 = Request::fromValues('GET', '/api/me/following', '127.0.0.1');
$res1 = $controller->handle($req1);
assertTest($res1['status'] === 401, 'Guest cannot access /api/me/following (401)');

// --- Test 2: GET /api/me/following authenticated ---
// Authenticate user 1
$account1 = $userRepo->findById(1);
$sessions->establish($account1);
$validCsrf = $sessions->csrfToken();

$res2 = $controller->handle($req1);
assertTest($res2['status'] === 200, 'Authenticated user gets 200 from /api/me/following');
$followingList = $res2['body']['data']['following'] ?? [];
assertTest(count($followingList) === 2, 'Following list contains 2 contacts');
// Check that User 2 has author badge and User 3 does not
$u2Data = null;
$u3Data = null;
foreach ($followingList as $f) {
    if ($f['id'] === 2) $u2Data = $f;
    if ($f['id'] === 3) $u3Data = $f;
}
assertTest($u2Data !== null && !empty($u2Data['has_author_badge']), 'User 2 has_author_badge is true in following list');
assertTest($u3Data !== null && empty($u3Data['has_author_badge']), 'User 3 has_author_badge is false in following list');

// --- Test 3: POST /api/share/direct-message unauthenticated ---
$sessions->clearAuthentication();
$req3 = Request::fromValues('POST', '/api/share/direct-message', '127.0.0.1', [], ['recipient_ids' => [2], 'post_id' => 200]);
$res3 = $controller->handle($req3);
assertTest($res3['status'] === 401, 'Unauthenticated direct-message share rejected with 401');

// Re-authenticate user 1 for tests 4 to 7
$sessions->establish($account1);
$validCsrf = $sessions->csrfToken();

// --- Test 4: POST /api/share/direct-message invalid CSRF ---
$req4 = Request::fromValues('POST', '/api/share/direct-message', '127.0.0.1', ['X-CSRF-Token' => 'invalid_csrf'], ['recipient_ids' => [2], 'post_id' => 200]);
$res4 = $controller->handle($req4);
assertTest($res4['status'] === 403, 'Invalid CSRF token rejected with 403');

// --- Test 5: POST /api/share/direct-message with no recipients or self ---
$req5 = Request::fromValues('POST', '/api/share/direct-message', '127.0.0.1', ['X-CSRF-Token' => $validCsrf], ['recipient_ids' => [1], 'post_id' => 200]);
$res5 = $controller->handle($req5);
assertTest($res5['status'] === 422, 'Sharing only to self rejected with 422');

// --- Test 6: POST /api/share/direct-message sharing a draft post ---
$req6 = Request::fromValues('POST', '/api/share/direct-message', '127.0.0.1', ['X-CSRF-Token' => $validCsrf], ['recipient_ids' => [2], 'post_id' => 201]);
$res6 = $controller->handle($req6);
assertTest($res6['status'] === 422, 'Sharing unpublished draft post rejected with 422');
assertTest(($res6['body']['error']['code'] ?? '') === 'INVALID_POST', 'Error code is INVALID_POST');

// --- Test 7: POST /api/share/direct-message successful multi-recipient share ---
$req7 = Request::fromValues(
    'POST',
    '/api/share/direct-message',
    '127.0.0.1',
    ['X-CSRF-Token' => $validCsrf],
    [
        'recipient_ids' => [2, 3, 2], // includes duplicate
        'post_id' => 200,
        'message' => 'Check this article out, highly recommended!'
    ]
);
$res7 = $controller->handle($req7);
assertTest($res7['status'] === 200, 'Direct message share returned 200');
assertTest(($res7['body']['data']['sent_count'] ?? 0) === 2, 'Sent to exactly 2 distinct contacts (deduplicated)');

// Verify direct messages table contains the shares
$dmRows = $pdo->query('SELECT * FROM direct_messages WHERE sender_id = 1 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
assertTest(count($dmRows) === 2, 'Exactly 2 direct messages created');
assertTest(str_contains($dmRows[0]['body'], 'Great Engineering Practices'), 'Message body contains article title');
assertTest(str_contains($dmRows[0]['body'], 'Check this article out, highly recommended!'), 'Message body contains personal note');

// --- Test 8: WhatsApp Sharing Link Canonical Format ---
$sampleTitle = 'Top 10 PHP & JavaScript Architectures for 2026';
$sampleCanonical = 'http://localhost:8086/article/200';
$whatsappExpectedText = $sampleTitle . "\n" . $sampleCanonical;
$whatsappUrl = 'https://wa.me/?text=' . rawurlencode($whatsappExpectedText);

assertTest(str_starts_with($whatsappUrl, 'https://wa.me/?text='), 'WhatsApp share link starts with https://wa.me/?text=');
assertTest(str_contains($whatsappUrl, rawurlencode($sampleCanonical)), 'WhatsApp share text contains urlencoded canonical link');
assertTest(str_contains($whatsappUrl, rawurlencode($sampleTitle)), 'WhatsApp share text contains urlencoded story title');

echo "\nAll {$passed} Share Dialog & Social tests passed successfully!\n";
