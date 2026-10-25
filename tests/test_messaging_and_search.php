<?php
declare(strict_types=1);

/**
 * Infinity Blog — Messaging Integrity, Cross-Account Delivery, and People Search Tests
 */

require dirname(__DIR__) . '/autoload.php';

ob_start();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'infinity-msg-test-' . bin2hex(random_bytes(8));
@mkdir($sessionDir, 0700);
session_save_path($sessionDir);
ini_set('session.use_cookies', '1');
session_id('');

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\SocialController;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;

$passCount = 0;
$failCount = 0;

$assert = function (bool $condition, string $description) use (&$passCount, &$failCount): void {
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description}\n";
    }
};

echo "=== INFINITY BLOG MESSAGING & SEARCH VERIFICATION SUITE ===\n\n";

// Use SQLite in-memory database for rigorous reproducible isolation
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Set up schema
$pdo->exec('
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT "author",
    status VARCHAR(20) NOT NULL DEFAULT "active",
    avatar_path VARCHAR(255) NULL,
    bio TEXT NULL,
    website VARCHAR(255) NULL,
    twitter_url VARCHAR(255) NULL,
    github_url VARCHAR(255) NULL,
    instagram_url VARCHAR(255) NULL,
    facebook_url VARCHAR(255) NULL,
    linkedin_url VARCHAR(255) NULL,
    profile_visibility VARCHAR(20) NOT NULL DEFAULT "public",
    auth_version INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE direct_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL,
    client_msg_id VARCHAR(64) NULL,
    body TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    blocked_user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, blocked_user_id)
);

CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    actor_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL,
    entity_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_follows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    follower_id INTEGER NOT NULL,
    followed_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (follower_id, followed_id)
);
');

// Seed test users:
// User 1: Cristine (Alice)
// User 2: Shubham Banerjee (Bob)
// User 3: Charlie Reader (Charlie)
// User 4: Private User (David)
// User 5: Blocked User (Eve)
$pdo->exec("
INSERT INTO users (id, username, email, password_hash, display_name, role, status, profile_visibility) VALUES
(1, 'cristine', 'cristine@example.com', 'hash1', 'Cristine Banerjee', 'author', 'active', 'public'),
(2, 'shubham_banerjee', 'shubham@example.com', 'hash2', 'Shubham Banerjee', 'author', 'active', 'public'),
(3, 'charlie_reader', 'charlie@example.com', 'hash3', 'Charlie Reader', 'reader', 'active', 'public'),
(4, 'david_private', 'david@example.com', 'hash4', 'David Private', 'author', 'active', 'private'),
(5, 'eve_blocked', 'eve@example.com', 'hash5', 'Eve Blocked', 'author', 'active', 'public');
");

// User 1 blocks User 5
$pdo->exec("INSERT INTO user_blocks (user_id, blocked_user_id) VALUES (1, 5);");

$userRepo = new UserRepository($pdo);
$messageRepo = new MessageRepository($pdo);
$followRepo = new FollowRepository($pdo);

// -------------------------------------------------------------------------
// SUITE 1: People Search Logic (searchUsersForChat)
// -------------------------------------------------------------------------
echo "--- Suite 1: People Search Verification ---\n";

// 1.1 Partial Display Name Match
$results = $userRepo->searchUsersForChat('Shubham', 1, 10);
$assert(count($results) === 1, '1.1 Search by display name "Shubham" returns 1 result');
$assert($results[0]['id'] === 2, '1.1 Result is User 2');
$assert($results[0]['display_name'] === 'Shubham Banerjee', '1.1 Display name is Shubham Banerjee');
$assert($results[0]['handle'] === '@shubham_banerjee', '1.1 Handle is @shubham_banerjee');

// 1.2 Case-Insensitive Search
$resultsUpper = $userRepo->searchUsersForChat('SHUBHAM', 1, 10);
$assert(count($resultsUpper) === 1 && $resultsUpper[0]['id'] === 2, '1.2 Search is strictly case-insensitive (SHUBHAM)');

// 1.3 Username with @ prefix
$resultsAt = $userRepo->searchUsersForChat('@shubham', 1, 10);
$assert(count($resultsAt) === 1 && $resultsAt[0]['id'] === 2, '1.3 Strips leading @ from query (@shubham)');

// 1.4 Viewer Exclusion
$resultsSelf = $userRepo->searchUsersForChat('cristine', 1, 10);
$assert(count($resultsSelf) === 0, '1.4 Viewer is strictly excluded from own search results');

// 1.5 Private Profile Exclusion
$resultsPrivate = $userRepo->searchUsersForChat('david', 1, 10);
$assert(count($resultsPrivate) === 0, '1.5 Private profiles are excluded from search results');

// 1.6 Blocked User Exclusion
$resultsBlocked = $userRepo->searchUsersForChat('eve', 1, 10);
$assert(count($resultsBlocked) === 0, '1.6 Blocked users are excluded from search results');

// 1.7 Reverse Block Exclusion (User 2 blocks User 3)
$pdo->exec("INSERT INTO user_blocks (user_id, blocked_user_id) VALUES (2, 3);");
$resultsRevBlocked = $userRepo->searchUsersForChat('shubham', 3, 10);
$assert(count($resultsRevBlocked) === 0, '1.7 Users who blocked the viewer are excluded from viewer search');

// 1.8 Strict Privacy (No emails or passwords exposed)
$resultsAll = $userRepo->searchUsersForChat('', 1, 10);
$noSensitiveData = true;
foreach ($resultsAll as $u) {
    if (isset($u['email']) || isset($u['password_hash']) || isset($u['auth_version'])) {
        $noSensitiveData = false;
        break;
    }
}
$assert($noSensitiveData, '1.8 Email addresses and sensitive auth fields are NEVER exposed in search results');

// 1.9 Existing Conversation Indicator (has_conversation)
// Initially User 1 has no messages with User 2
$resultsBefore = $userRepo->searchUsersForChat('Shubham', 1, 10);
$assert($resultsBefore[0]['has_conversation'] === false, '1.9 has_conversation is false before any messages sent');

// User 1 sends message to User 2
$messageRepo->sendMessage(1, 2, 'Hello Shubham!');
$resultsAfter = $userRepo->searchUsersForChat('Shubham', 1, 10);
$assert($resultsAfter[0]['has_conversation'] === true, '1.9 has_conversation is true after messages exist');


// -------------------------------------------------------------------------
// SUITE 2: Message Content Integrity
// -------------------------------------------------------------------------
echo "\n--- Suite 2: Message Content Integrity ---\n";

// 2.1 Preserving Whitespace, Tabs, and Multiple Newlines
$multiLineBody = "Line 1\n\n  Indented Line 2 with   multiple   spaces\n\nLine 3 with\ttab\n\nEnd of message.";
$msg1 = $messageRepo->sendMessage(1, 2, $multiLineBody, 'client_multi_1');
$assert($msg1['body'] === $multiLineBody, '2.1 Multi-line message with indentation, tabs, and spaces preserved exactly');

// 2.2 Preserving Unicode and Emojis
$unicodeBody = "Hello world! 🚀✨\nBengali: ইনফিনিটি ব্লগ বাংলা টেস্ট\nHindi: नमस्ते दुनिया!\nJapanese: こんにちは\nPunctuation: «“‘—’”—» 100% #awesome";
$msg2 = $messageRepo->sendMessage(1, 2, $unicodeBody, 'client_unicode_1');
$assert($msg2['body'] === $unicodeBody, '2.2 Unicode characters, emojis, and diverse punctuation preserved exactly');

// 2.3 Long Message Support
$longBody = str_repeat("This is a detailed paragraph with information. ", 35); // ~2000 chars
$msg3 = $messageRepo->sendMessage(1, 2, $longBody, 'client_long_1');
$assert($msg3['body'] === $longBody, '2.3 Long message (~2,000 chars) preserved without truncation');

// 2.4 HTML Special Characters Not Double-Escaped in DB
$htmlBody = '<script>alert("XSS")</script> & <b>Bold</b>';
$msg4 = $messageRepo->sendMessage(1, 2, $htmlBody, 'client_html_1');
$assert($msg4['body'] === $htmlBody, '2.4 Raw text stored as input without destructive server-side alteration');


// -------------------------------------------------------------------------
// SUITE 3: Idempotency via client_msg_id vs Intentional Short Messages
// -------------------------------------------------------------------------
echo "\n--- Suite 3: Idempotency & Repeat Messages ---\n";

// 3.1 Idempotency: Retrying with the same client_msg_id returns existing record
$retryMsg = $messageRepo->sendMessage(1, 2, $multiLineBody, 'client_multi_1');
$assert($retryMsg['id'] === $msg1['id'], '3.1 Sending with same client_msg_id returns existing record without duplicate');

// Verify DB count has exactly 1 row for client_multi_1
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE client_msg_id = :cid');
$countStmt->execute([':cid' => 'client_multi_1']);
$assert((int) $countStmt->fetchColumn() === 1, '3.1 Exactly 1 row in database for client_multi_1');

// 3.2 Intentional Short Repeated Messages ("hi", "ok", "cool") are NOT blocked when client_msg_id differs
$msgShort1 = $messageRepo->sendMessage(1, 2, 'hi', 'cmsg_hi_1');
$msgShort2 = $messageRepo->sendMessage(1, 2, 'hi', 'cmsg_hi_2');
$assert($msgShort1['id'] !== $msgShort2['id'], '3.2 Consecutive identical messages with different client_msg_id both succeed');
$assert($msgShort2['body'] === 'hi', '3.2 Second message body matches');


// -------------------------------------------------------------------------
// SUITE 4: Thread History & Privacy Isolation
// -------------------------------------------------------------------------
echo "\n--- Suite 4: Thread History & Privacy Isolation ---\n";

// 4.1 Sender alignment: is_mine flag in getThread
$threadUser1 = $messageRepo->getThread(1, 2, 50);
$foundSent = false;
$foundReceived = false;

// Let User 2 reply to User 1
$messageRepo->sendMessage(2, 1, 'Got your message, Cristine!', 'reply_1');

$threadUser1Updated = $messageRepo->getThread(1, 2, 50);
foreach ($threadUser1Updated as $m) {
    if ($m['body'] === 'Got your message, Cristine!') {
        $foundReceived = ($m['is_mine'] === false);
    }
    if ($m['body'] === 'hi') {
        $foundSent = ($m['is_mine'] === true);
    }
}
$assert($foundSent, '4.1 Sent message is correctly marked is_mine = true for viewer (User 1)');
$assert($foundReceived, '4.1 Received message is correctly marked is_mine = false for viewer (User 1)');

// 4.2 User 2 perspective
$threadUser2 = $messageRepo->getThread(2, 1, 50);
$user2SawMine = false;
foreach ($threadUser2 as $m) {
    if ($m['body'] === 'Got your message, Cristine!') {
        $user2SawMine = ($m['is_mine'] === true);
    }
}
$assert($user2SawMine, '4.2 From User 2 perspective, their reply is correctly marked is_mine = true');

// 4.3 Third party (User 3) Privacy Isolation
$threadUser3With1 = $messageRepo->getThread(3, 1, 50);
$threadUser3With2 = $messageRepo->getThread(3, 2, 50);
$assert(empty($threadUser3With1), '4.3 User 3 has 0 messages with User 1');
$assert(empty($threadUser3With2), '4.3 User 3 has 0 messages with User 2');


// -------------------------------------------------------------------------
// SUITE 5: API Endpoints & Guard Enforcement
// -------------------------------------------------------------------------
echo "\n--- Suite 5: API Endpoints & Guard Enforcement ---\n";

$config = AppConfig::fromArray([
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'true',
    'APP_BASE_URL' => 'http://localhost:8086',
    'SESSION_SECURE_COOKIE' => 'false',
    'APP_KEY' => str_repeat('a', 64),
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'infinity_blog_test',
    'DB_USERNAME' => 'test_user',
    'DB_PASSWORD' => 'test_pass',
]);

$sessionManager = new SessionManager($config);
$guard = new AuthGuard($sessionManager, $userRepo);
$socialController = new SocialController($followRepo, $messageRepo, $guard, $sessionManager, $userRepo);

// 5.1 Unauthenticated People Search returns 401
$reqGuest = Request::fromValues('GET', '/api/users/search', '127.0.0.1');
$resGuest = $socialController->handle($reqGuest);
$assert($resGuest['status'] === 401, '5.1 Unauthenticated GET /api/users/search returns 401');

// 5.2 Authenticated People Search returns 200 with user list
$sessionManager->start();
$alice = $userRepo->findById(1);
$sessionManager->establish($alice);
$_GET['q'] = 'Shubham';

$reqAuth = Request::fromValues('GET', '/api/users/search', '127.0.0.1');
$resAuth = $socialController->handle($reqAuth);
$assert($resAuth['status'] === 200, '5.2 Authenticated GET /api/users/search returns 200');
$assert(!empty($resAuth['body']['data']['users']), '5.2 Search response includes user results');
$assert($resAuth['body']['data']['users'][0]['username'] === 'shubham_banerjee', '5.2 Found user is shubham_banerjee');

// 5.3 Sending message via API with client_msg_id
$csrfToken = $sessionManager->csrfToken();
$apiSendBody = "Direct API message from Cristine\nTesting newlines!";
$reqSend = Request::fromValues(
    'POST',
    '/api/messages',
    '127.0.0.1',
    ['X-CSRF-Token' => $csrfToken, 'Content-Type' => 'application/json'],
    ['recipient_id' => 2, 'body' => $apiSendBody, 'client_msg_id' => 'api_cmsg_999']
);

$resSend = $socialController->handle($reqSend);
$assert($resSend['status'] === 201, '5.3 POST /api/messages returns 201 Created');
$assert($resSend['body']['data']['message']['body'] === $apiSendBody, '5.3 Returned message body preserves newlines exactly');
$assert($resSend['body']['data']['message']['client_msg_id'] === 'api_cmsg_999', '5.3 Returned message includes client_msg_id');

// 5.4 Double-click API Send with same client_msg_id returns 201 with existing record
$resSendDup = $socialController->handle($reqSend);
$assert($resSendDup['status'] === 201, '5.4 Retried POST /api/messages with same client_msg_id succeeds idempotently');
$assert($resSendDup['body']['data']['message']['id'] === $resSend['body']['data']['message']['id'], '5.4 Idempotency confirms identical message ID');

// Clean up
$sessionManager->logout();

echo "\n=======================================================\n";
echo "MESSAGING & SEARCH VERIFICATION COMPLETE: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
