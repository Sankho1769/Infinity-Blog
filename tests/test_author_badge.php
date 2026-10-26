<?php
declare(strict_types=1);

/**
 * Test Suite for Earned Author Badge System.
 */
require __DIR__ . '/../autoload.php';

use InfinityBlog\Social\AuthorBadgeService;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup test tables
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'active\',
    role VARCHAR(20) NOT NULL DEFAULT \'user\'
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

$badgeService = new AuthorBadgeService($pdo);
$passed = 0;

function assertTest(bool $condition, string $msg): void {
    global $passed;
    if (!$condition) {
        throw new RuntimeException("FAILED: {$msg}");
    }
    $passed++;
    echo "PASS: {$msg}\n";
}

echo "=== Running Author Badge Tests ===\n";

// Seed authors and raters
// Author 1: Base author with no content
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (1, 'no_articles', 'No Articles User', 'active')");
// Author 2: Author with 1 published article, 9 followers (just below threshold)
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (2, 'author_9_followers', 'Nine Followers User', 'active')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (101, 2, 'Post 1', 'Content', 'published')");
// Author 3: Author with 1 published article, 10 followers (meets follower threshold)
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (3, 'author_10_followers', 'Ten Followers User', 'active')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (102, 3, 'Post 2', 'Content', 'published')");
// Author 4: Author with 1 published article, 4 ratings of 5.0 (below rating count threshold of 5)
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (4, 'author_4_ratings', 'Four Ratings User', 'active')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (103, 4, 'Post 3', 'Content', 'published')");
// Author 5: Author with 1 published article, 5 ratings averaging 4.6 (meets rating threshold)
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (5, 'author_high_rated', 'High Rated User', 'active')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (104, 5, 'Post 4', 'Content', 'published')");
// Author 6: Author with 1 published article, 5 ratings averaging 4.2 (below 4.5 avg threshold)
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (6, 'author_low_rated', 'Low Rated User', 'active')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (105, 6, 'Post 5', 'Content', 'published')");
// Author 7: Suspended/inactive author who met criteria before suspension
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (7, 'suspended_author', 'Suspended User', 'suspended')");
$pdo->exec("INSERT INTO posts (id, author_id, title, content, status) VALUES (106, 7, 'Post 6', 'Content', 'published')");

// Seed 20 raters/followers (Users 100 to 119)
for ($i = 100; $i <= 119; $i++) {
    $pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES ({$i}, 'user_{$i}', 'User {$i}', 'active')");
}

// Inactive rater (User 150)
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (150, 'inactive_rater', 'Inactive Rater', 'inactive')");

// Add 9 followers to Author 2
for ($i = 100; $i <= 108; $i++) {
    $pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES ({$i}, 2)");
}

// Add 10 followers to Author 3
for ($i = 100; $i <= 109; $i++) {
    $pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES ({$i}, 3)");
}

// Add 15 followers to Author 1 (who has 0 published articles)
for ($i = 100; $i <= 114; $i++) {
    $pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES ({$i}, 1)");
}

// Add 15 followers to suspended Author 7
for ($i = 100; $i <= 114; $i++) {
    $pdo->exec("INSERT INTO user_follows (follower_id, following_id) VALUES ({$i}, 7)");
}

// Add 4 valid ratings of 5 to Author 4 (post 103)
for ($i = 100; $i <= 103; $i++) {
    $pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (103, {$i}, 5)");
}

// Add 5 valid ratings (5, 5, 5, 4, 4 -> avg 4.6) to Author 5 (post 104)
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (104, 100, 5)");
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (104, 101, 5)");
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (104, 102, 5)");
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (104, 103, 4)");
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (104, 104, 4)");

// Add 5 valid ratings (4, 4, 4, 4, 5 -> avg 4.2) to Author 6 (post 105)
for ($i = 100; $i <= 103; $i++) {
    $pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (105, {$i}, 4)");
}
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (105, 104, 5)");

// Test 1: User with no published articles doesn't qualify (even with 15 followers)
$st1 = $badgeService->getAuthorBadgeStatus(1);
assertTest(!$st1['qualified'], 'User with 0 published articles does not qualify despite 15 followers');
assertTest(!$st1['meets_published_req'], 'User 1 meets_published_req is false');
assertTest($st1['meets_follower_req'], 'User 1 meets_follower_req is true');

// Test 2: User with 9 followers does not qualify via followers
$st2 = $badgeService->getAuthorBadgeStatus(2);
assertTest(!$st2['qualified'], 'Author with 9 followers does not qualify');
assertTest($st2['meets_published_req'], 'Author 2 meets published requirement');
assertTest(!$st2['meets_follower_req'], 'Author 2 does not meet follower threshold (has 9)');

// Test 3: User with 10 followers qualifies
$st3 = $badgeService->getAuthorBadgeStatus(3);
assertTest($st3['qualified'], 'Author with 10 followers qualifies for badge');
assertTest($st3['meets_follower_req'], 'Author 3 meets follower requirement');

// Test 4: Author with 4 ratings of 5.0 does not qualify (minimum ratings = 5)
$st4 = $badgeService->getAuthorBadgeStatus(4);
assertTest(!$st4['qualified'], 'Author with only 4 ratings does not qualify even with 5.0 avg');
assertTest(!$st4['meets_rating_req'], 'Author 4 does not meet rating count threshold');

// Test 5: Author with 5 ratings averaging 4.6 qualifies via ratings
$st5 = $badgeService->getAuthorBadgeStatus(5);
assertTest($st5['qualified'], 'Author with 5 ratings averaging 4.6 qualifies');
assertTest($st5['meets_rating_req'], 'Author 5 meets rating threshold');

// Test 6: Author with 5 ratings averaging 4.2 does not qualify
$st6 = $badgeService->getAuthorBadgeStatus(6);
assertTest(!$st6['qualified'], 'Author with 5 ratings averaging 4.2 does not qualify');
assertTest(!$st6['meets_rating_req'], 'Author 6 average 4.2 is below 4.5');

// Test 7: Suspended/inactive account never qualifies
$st7 = $badgeService->getAuthorBadgeStatus(7);
assertTest(!$st7['qualified'], 'Suspended author does not qualify');
assertTest(!$st7['is_active'], 'Suspended author is marked not active');

// Test 8: Self-ratings are excluded
// Author 4 gives themselves a score of 5 on their own post 103 -> total becomes 5, but self-score must be ignored
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (103, 4, 5)");
$st4_after_self = $badgeService->getAuthorBadgeStatus(4);
assertTest($st4_after_self['total_ratings'] === 4, 'Self-score is excluded from rating count (still 4 ratings)');
assertTest(!$st4_after_self['qualified'], 'Self-score does not cause author to qualify');

// Test 9: Ratings from inactive users are excluded
// Add score from inactive user 150 to post 103
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (103, 150, 5)");
$st4_after_inactive = $badgeService->getAuthorBadgeStatus(4);
assertTest($st4_after_inactive['total_ratings'] === 4, 'Score from inactive user is excluded (still 4 ratings)');

// Test 10: Now add 5th rating from active user 105 to post 103 -> Author 4 now qualifies!
$pdo->exec("INSERT INTO post_scores (post_id, user_id, score) VALUES (103, 105, 5)");
$st4_now_qualified = $badgeService->getAuthorBadgeStatus(4);
assertTest($st4_now_qualified['total_ratings'] === 5, 'Now has 5 valid ratings');
assertTest($st4_now_qualified['qualified'], 'Author 4 now qualifies with 5 valid 5.0 ratings');

// Test 11: Batch lookup for multiple users
$batch = $badgeService->getBadgeStatusesForUsers([1, 2, 3, 4, 5, 6, 7]);
assertTest($batch[1] === false, 'Batch: User 1 false');
assertTest($batch[2] === false, 'Batch: User 2 false');
assertTest($batch[3] === true, 'Batch: User 3 true');
assertTest($batch[4] === true, 'Batch: User 4 true');
assertTest($batch[5] === true, 'Batch: User 5 true');
assertTest($batch[6] === false, 'Batch: User 6 false');
assertTest($batch[7] === false, 'Batch: User 7 false');

// Test 12: HTML badge rendering
$renderedTrue = AuthorBadgeService::renderBadgeHtml(true);
assertTest(str_contains($renderedTrue, 'badge-author-earned'), 'HTML badge contains badge-author-earned');
assertTest(str_contains($renderedTrue, 'Author'), 'HTML badge contains text Author');
$renderedFalse = AuthorBadgeService::renderBadgeHtml(false);
assertTest($renderedFalse === '', 'HTML badge is empty string for non-qualifying user');

echo "\nAll {$passed} Author Badge tests passed successfully!\n";
