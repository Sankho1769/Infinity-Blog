<?php
declare(strict_types=1);

/**
 * Test Suite for @Mention System.
 */
require __DIR__ . '/../autoload.php';

use InfinityBlog\Social\AuthorBadgeService;
use InfinityBlog\Social\MentionService;
use InfinityBlog\Social\NotificationRepository;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup test tables
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    avatar_path VARCHAR(255) NULL,
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

$notifs = new NotificationRepository($pdo);
$badges = new AuthorBadgeService($pdo);
$mentionService = new MentionService($pdo, $notifs, $badges);

$passed = 0;
function assertTest(bool $condition, string $msg): void {
    global $passed;
    if (!$condition) {
        throw new RuntimeException("FAILED: {$msg}");
    }
    $passed++;
    echo "PASS: {$msg}\n";
}

echo "=== Running @Mention System Tests ===\n";

// Seed users
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (1, 'alice', 'Alice Developer', 'active')");
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (2, 'bob_smith', 'Bob Smith', 'active')");
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (3, 'charlie.ai', 'Charlie Tech', 'active')");
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (4, 'inactive_user', 'Inactive User', 'inactive')");
$pdo->exec("INSERT INTO users (id, username, display_name, status) VALUES (5, 'blocked_dan', 'Dan Danger', 'active')");

// Block relation: Alice blocks Dan (User 1 blocks User 5)
$pdo->exec("INSERT INTO user_blocks (user_id, blocked_user_id) VALUES (1, 5)");

// Section 1: Mention Extraction
echo "\n--- 1. Extraction Rules ---\n";
$ext1 = $mentionService->extractUsernames('Hello @alice, have you seen this?');
assertTest($ext1 === ['alice'], 'Single mention extracted');

$ext2 = $mentionService->extractUsernames('Pinging @Bob_Smith and @charlie.ai on this topic.');
assertTest($ext2 === ['bob_smith', 'charlie.ai'], 'Normalized lowercase mentions extracted');

$ext3 = $mentionService->extractUsernames('Email me at contact@example.com please, do not mention @alice twice @Alice');
assertTest($ext3 === ['alice'], 'Email addresses ignored, duplicate mentions deduplicated');

$ext4 = $mentionService->extractUsernames('Special punctuation: (@alice)! Is @bob_smith? Yes, [@charlie.ai].');
assertTest(count($ext4) === 3 && in_array('alice', $ext4, true) && in_array('bob_smith', $ext4, true) && in_array('charlie.ai', $ext4, true), 'Punctuation surrounding handles respected');

// Anti-spam 10 mention cap
$spamText = '';
for ($i = 1; $i <= 15; $i++) {
    $spamText .= " @user{$i}";
}
$extSpam = $mentionService->extractUsernames($spamText);
assertTest(count($extSpam) === MentionService::MAX_MENTIONS_PER_ITEM, 'Mentions capped at 10 anti-spam limit');

// Section 2: Discoverable Active User Search
echo "\n--- 2. Autocomplete Search ---\n";
$searchAlice = $mentionService->searchUsers('ali');
assertTest(count($searchAlice) === 1 && $searchAlice[0]['username'] === 'alice', 'Search found alice');

$searchInactive = $mentionService->searchUsers('inactive');
assertTest(count($searchInactive) === 0, 'Inactive accounts are not discoverable in autocomplete');

$searchBlocked = $mentionService->searchUsers('dan', 8, 1);
assertTest(count($searchBlocked) === 0, 'Blocked users are excluded from autocomplete for viewer');

// Section 3: Safe HTML Rendering
echo "\n--- 3. Safe HTML Rendering & XSS Protection ---\n";
$safeHtml = $mentionService->renderMentionsToHtml('Hello @alice and @bob_smith!');
assertTest(str_contains($safeHtml, '<a href="/authors/alice" class="mention-link" data-user-id="1">@alice</a>'), 'Alice replaced with safe link');
assertTest(str_contains($safeHtml, '<a href="/authors/bob_smith" class="mention-link" data-user-id="2">@bob_smith</a>'), 'Bob replaced with safe link');

$unknownHtml = $mentionService->renderMentionsToHtml('Hey @nonexistent_user and @inactive_user!');
assertTest(!str_contains($unknownHtml, '<a href="/authors/nonexistent_user"'), 'Unknown user left as plain text');
assertTest(!str_contains($unknownHtml, '<a href="/authors/inactive_user"'), 'Inactive user left as plain text');

// Stored XSS attempt
$xssPayload = '<script>alert("XSS")</script> Hey @alice <img src=x onerror=alert(1)>';
$xssRendered = $mentionService->renderMentionsToHtml($xssPayload);
assertTest(!str_contains($xssRendered, '<script>'), 'Script tag escaped');
assertTest(!str_contains($xssRendered, '<img'), 'Img tag escaped');
assertTest(str_contains($xssRendered, '&lt;script&gt;'), 'Script tag converted to entity');
assertTest(str_contains($xssRendered, 'class="mention-link"'), 'Mention link is still safely formed');

// Section 4: Notifications & Deduplication on Edit
echo "\n--- 4. Mention Notifications & Deduplication ---\n";

// Alice creates a comment mentioning Bob and Charlie
$commentId = 501;
$notified = $mentionService->processMentionsAndNotify(
    'comment',
    $commentId,
    1, // Alice authorId
    'Hey @bob_smith and @charlie.ai, take a look at this!',
    'Architectural Guide',
    '/article/10#comment-501'
);

assertTest(count($notified) === 2, 'Two users notified on first comment creation');
assertTest(in_array(2, $notified, true) && in_array(3, $notified, true), 'Bob and Charlie notified');

// Verify notification records in DB
$notifBob = $pdo->query("SELECT * FROM user_notifications WHERE user_id = 2 AND type = 'mention'")->fetch(PDO::FETCH_ASSOC);
assertTest($notifBob !== false, 'Notification record exists for Bob');
assertTest(str_contains($notifBob['message'], 'Alice Developer mentioned you'), 'Notification message contains Alice Developer mentioned you');

// Self-mention safeguard: Alice mentions herself and Bob in same comment
$notifiedSelf = $mentionService->processMentionsAndNotify(
    'comment',
    502,
    1, // Alice authorId
    'Note to self @alice, and pinging @bob_smith',
    'Self Note',
    '/article/10#comment-502'
);
assertTest(!in_array(1, $notifiedSelf, true), 'Alice was NOT notified for self-mention');

// Blocked user safeguard: Alice mentions Dan (who is blocked)
$notifiedBlocked = $mentionService->processMentionsAndNotify(
    'comment',
    503,
    1, // Alice authorId
    'Pinging @blocked_dan',
    'Some Topic',
    '/article/10#comment-503'
);
assertTest(count($notifiedBlocked) === 0, 'Blocked Dan was NOT notified');

// Deduplication on edit: Alice edits comment 501, keeping @bob_smith and @charlie.ai, and adds no new mentions
$edited1 = $mentionService->processMentionsAndNotify(
    'comment',
    $commentId,
    1,
    'Updated: Hey @bob_smith and @charlie.ai, take a look at this edited comment!',
    'Architectural Guide',
    '/article/10#comment-501'
);
assertTest(count($edited1) === 0, 'No duplicate notifications sent on edit with existing mentions');

// Check notifications count for Bob (must still be 2 from comment 501 and 502, not 3)
$bobCount = (int) $pdo->query("SELECT COUNT(*) FROM user_notifications WHERE user_id = 2 AND type = 'mention'")->fetchColumn();
assertTest($bobCount === 2, "Bob has exactly 2 notifications (no duplicates on edit)");

// Target URL resolution via NotificationRepository
$userNotifs = $notifs->getUserNotifications(2);
assertTest(count($userNotifs) >= 1, 'Bob received notifications via NotificationRepository');
$foundCommentUrl = false;
foreach ($userNotifs as $un) {
    if ($un['target_url'] === '/article/10#comment-501') {
        $foundCommentUrl = true;
        break;
    }
}
assertTest($foundCommentUrl, 'Notification target_url correctly resolved custom context URL (/article/10#comment-501)');

echo "\nAll {$passed} @Mention System tests passed successfully!\n";
