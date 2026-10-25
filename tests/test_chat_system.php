<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

ob_start();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'infinity-chat-test-' . bin2hex(random_bytes(8));
@mkdir($sessionDir, 0700);
session_save_path($sessionDir);
ini_set('session.use_cookies', '1');
session_id('');

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Http\CommunityController;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\SocialController;
use InfinityBlog\Http\WebController;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;
use InfinityBlog\Social\NotificationRepository;
use InfinityBlog\Users\AdminUserRepository;

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
};

echo "=== INFINITY BLOG CHAT SYSTEM VERIFICATION SUITE ===\n\n";

// Setup an in-memory SQLite database matching the application schema
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 1. Users schema
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NULL UNIQUE,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT \'reader\',
    status TEXT NOT NULL DEFAULT \'active\',
    auth_version INTEGER NOT NULL DEFAULT 1,
    avatar_path TEXT NULL,
    banner_path TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 2. Categories schema
$pdo->exec('CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NULL,
    icon_name TEXT NULL,
    custom_logo_path TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 3. Posts schema
$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL,
    category_id INTEGER NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT \'published\',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 4. Chat rooms schema
$pdo->exec('CREATE TABLE chat_rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NULL,
    category_id INTEGER NULL UNIQUE,
    created_by INTEGER NOT NULL DEFAULT 1,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 5. Chat room messages schema
$pdo->exec('CREATE TABLE chat_room_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 6. Chat room members schema
$pdo->exec('CREATE TABLE chat_room_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(room_id, user_id)
)');

// 7. Direct messages schema
$pdo->exec('CREATE TABLE direct_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    read_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 8. User blocks schema
$pdo->exec('CREATE TABLE user_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    blocked_user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, blocked_user_id)
)');

// 9. Notifications schema
$pdo->exec('CREATE TABLE user_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    actor_id INTEGER NULL,
    type TEXT NOT NULL,
    reference_id INTEGER NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// 10. Audit logs schema
$pdo->exec('CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NULL,
    metadata TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// Seed Test Users: User A (id 1), User B (id 2), User C (id 3)
$hash = password_hash('Pass1234!', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, username, email, display_name, password_hash, role, status) VALUES
    (1, 'alice', 'alice@test.local', 'Alice Walker', '{$hash}', 'author', 'active'),
    (2, 'bob', 'bob@test.local', 'Bob Smith', '{$hash}', 'author', 'active'),
    (3, 'carol', 'carol@test.local', 'Carol Danvers', '{$hash}', 'reader', 'active')");

// Seed 3 Categories
$pdo->exec("INSERT INTO categories (id, name, slug, description, icon_name, is_active) VALUES
    (1, 'Technology', 'technology', 'Tech discussions', 'cpu', 1),
    (2, 'Design', 'design', 'Design & UI/UX', 'palette', 1),
    (3, 'Science', 'science', 'Science and innovation', 'flask', 1)");

// Initialize Repositories and Services
$users = new UserRepository($pdo);
$community = new CommunityRepository($pdo);
$messages = new MessageRepository($pdo);
$follows = new FollowRepository($pdo);
$notifications = new NotificationRepository($pdo);
$posts = new PostRepository($pdo);
$config = AppConfig::fromArray([
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'true',
    'APP_BASE_URL' => 'http://localhost:8086',
    'SESSION_SECURE_COOKIE' => 'false',
    'APP_KEY' => str_repeat('a', 64),
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'infinity_blog',
    'DB_USERNAME' => 'blog_runtime',
    'DB_PASSWORD' => 'synthetic-test-placeholder',
]);

$sessions = new SessionManager($config);
$sessions->start();
$guard = new AuthGuard($sessions, $users);

$webController = new WebController(
    $posts,
    new \InfinityBlog\Categories\CategoryRepository($pdo),
    $users,
    new AdminUserRepository($pdo),
    new AuditLogRepository($pdo),
    $guard,
    $sessions,
    $config,
    null,
    null,
    null,
    $messages,
    null,
    null,
    $community
);

$communityController = new CommunityController($community, $guard, $sessions, $posts);
$socialController = new SocialController($follows, $messages, $guard, $sessions, $users, $notifications);

// =========================================================================
// SUITE 1: AUTHENTICATION IS MANDATORY (7 CASES)
// =========================================================================
echo "--- Suite 1: Authentication Enforcement (7 Cases) ---\n";

// Case 1.1: Logged-out visitor visiting /chat is redirected to /login?redirect=%2Fchat
$req = Request::fromValues('GET', '/chat', '127.0.0.1');
$res = $webController->chat($req);
$assert($res['status'] === 302, '1.1 Logged-out /chat returns HTTP 302 redirect');
$assert(str_contains($res['headers']['Location'] ?? $res['body'], '/login?redirect=%2Fchat'), '1.1 Redirect Location points to /login?redirect=%2Fchat');

// Case 1.2: Logged-out visitor visiting /chat/technology is redirected to /login?redirect=%2Fchat%2Ftechnology
$req = Request::fromValues('GET', '/chat/technology', '127.0.0.1');
$res = $webController->chat($req, 'technology');
$assert($res['status'] === 302, '1.2 Logged-out /chat/technology returns HTTP 302 redirect');
$assert(str_contains($res['headers']['Location'] ?? $res['body'], '/login?redirect=%2Fchat%2Ftechnology'), '1.2 Redirect Location preserves target slug');

// Case 1.3: Logged-out visitor visiting /messages is redirected to /login?redirect=%2Fmessages
$req = Request::fromValues('GET', '/messages', '127.0.0.1');
$res = $webController->messages($req);
$assert($res['status'] === 302, '1.3 Logged-out /messages returns HTTP 302 redirect');
$assert(str_contains($res['headers']['Location'] ?? $res['body'], '/login?redirect=%2Fmessages'), '1.3 Redirect Location points to /login?redirect=%2Fmessages');

// Case 1.4: Logged-out visitor visiting /messages/2 is redirected to /login?redirect=%2Fmessages%2F2
$req = Request::fromValues('GET', '/messages/2', '127.0.0.1');
$res = $webController->messages($req, 2);
$assert($res['status'] === 302, '1.4 Logged-out /messages/2 returns HTTP 302 redirect');
$assert(str_contains($res['headers']['Location'] ?? $res['body'], '/login?redirect=%2Fmessages%2F2'), '1.4 Redirect Location preserves active recipient ID');

// Case 1.5: Logged-out API call GET /api/chat/rooms returns HTTP 401
$req = Request::fromValues('GET', '/api/chat/rooms', '127.0.0.1');
$res = $communityController->handle($req);
$assert($res['status'] === 401, '1.5 Logged-out GET /api/chat/rooms returns HTTP 401');
$assert(($res['body']['error']['code'] ?? '') === 'UNAUTHENTICATED', '1.5 Error code is UNAUTHENTICATED');

// Case 1.6: Logged-out API call GET /api/chat/rooms/technology/messages returns HTTP 401
$req = Request::fromValues('GET', '/api/chat/rooms/technology/messages', '127.0.0.1');
$res = $communityController->handle($req);
$assert($res['status'] === 401, '1.6 Logged-out GET /api/chat/rooms/technology/messages returns HTTP 401');

// Case 1.7: Logged-out API call GET /api/messages & /api/messages/2 returns HTTP 401
$req1 = Request::fromValues('GET', '/api/messages', '127.0.0.1');
$res1 = $socialController->handle($req1);
$req2 = Request::fromValues('GET', '/api/messages/2', '127.0.0.1');
$res2 = $socialController->handle($req2);
$assert($res1['status'] === 401 && $res2['status'] === 401, '1.7 Logged-out GET /api/messages returns HTTP 401');

// Verify strict Cache-Control headers returned by authenticated views
$alice = $users->findById(1);
$sessions->establish($alice);
$chatAuthed = $webController->chat(Request::fromValues('GET', '/chat', '127.0.0.1'));
$msgAuthed = $webController->messages(Request::fromValues('GET', '/messages', '127.0.0.1'));
$assert(!empty($chatAuthed['headers']['Cache-Control']) && str_contains($chatAuthed['headers']['Cache-Control'], 'no-store'), '1.8 /chat sends strict Cache-Control: no-store');
$assert(!empty($msgAuthed['headers']['Cache-Control']) && str_contains($msgAuthed['headers']['Cache-Control'], 'no-store'), '1.8 /messages sends strict Cache-Control: no-store');


// =========================================================================
// SUITE 2: PRIVATE DIRECT MESSAGING (10 CASES)
// =========================================================================
echo "\n--- Suite 2: Private Direct Messaging (10 Cases) ---\n";

// Case 2.1: User A sends private message to User B
$msg1 = $messages->sendMessage(1, 2, 'Hello Bob, this is Alice!');
$assert($msg1['id'] > 0 && $msg1['sender_id'] === 1 && $msg1['recipient_id'] === 2, '2.1 User A sends DM to User B successfully');

// Case 2.2: User B can read User A\'s message in their thread with User A
$bThread = $messages->getThread(2, 1);
$assert(count($bThread) === 1 && $bThread[0]['body'] === 'Hello Bob, this is Alice!', '2.2 User B sees Alice\'s message in their thread');

// Case 2.3: User B replies to User A, history preserves chronological order
$msg2 = $messages->sendMessage(2, 1, 'Hi Alice! Great to hear from you.');
$aThread = $messages->getThread(1, 2);
$assert(count($aThread) === 2, '2.3 Thread history contains both messages');
$assert($aThread[0]['body'] === 'Hello Bob, this is Alice!' && $aThread[1]['body'] === 'Hi Alice! Great to hear from you.', '2.3 Chronological order strictly preserved');

// Case 2.4: Single idempotent conversation thread for User A and User B
$aConvs = $messages->listConversations(1);
$bConvs = $messages->listConversations(2);
$assert(count($aConvs) === 1 && (int)$aConvs[0]['other_user']['id'] === 2, '2.4 User A has exactly 1 conversation thread with User B');
$assert(count($bConvs) === 1 && (int)$bConvs[0]['other_user']['id'] === 1, '2.4 User B has exactly 1 conversation thread with User A');

// Case 2.5: User cannot send direct message to themselves (HTTP 422 rejected)
$selfMsgThrown = false;
try {
    $messages->sendMessage(1, 1, 'Talking to myself');
} catch (\InvalidArgumentException $e) {
    $selfMsgThrown = true;
}
$assert($selfMsgThrown, '2.5 MessageRepository rejects self-messaging with InvalidArgumentException');

// Case 2.6: SocialController rejects self-messaging API call
$selfReq = Request::fromValues('POST', '/api/messages/1', '127.0.0.1', ['X-CSRF-Token' => $sessions->csrfToken()], ['body' => 'Self msg']);
$selfRes = $socialController->handle($selfReq);
$assert($selfRes['status'] === 422, '2.6 API POST /api/messages/{self_id} returns HTTP 422');
$assert(($selfRes['body']['error']['code'] ?? '') === 'SELF_MESSAGE', '2.6 Error code is SELF_MESSAGE');

// Case 2.7: WebController /messages/{self_id} redirects away to /messages
$selfWebReq = Request::fromValues('GET', '/messages/1', '127.0.0.1');
$selfWebRes = $webController->messages($selfWebReq, 1);
$assert($selfWebRes['status'] === 302 && str_contains($selfWebRes['headers']['Location'] ?? $selfWebRes['body'], '/messages'), '2.7 Navigating to own ID redirects to /messages');

// Case 2.8: User C cannot see messages between User A and User B (strict isolation)
$cThreadWithA = $messages->getThread(3, 1);
$cThreadWithB = $messages->getThread(3, 2);
$cConvs = $messages->listConversations(3);
$assert(empty($cThreadWithA) && empty($cThreadWithB) && empty($cConvs), '2.8 User C thread with A and B is completely empty (strict privacy isolation)');

// Case 2.9: Double-click duplicate protection: identical message to same recipient within 2 seconds
$dupMsg1 = $messages->sendMessage(1, 2, 'Rapid click test message');
$dupMsg2 = $messages->sendMessage(1, 2, 'Rapid click test message');
$assert($dupMsg1['id'] === $dupMsg2['id'], '2.9 Double-click returns existing message ID without creating duplicate');
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE body = :b');
$countStmt->execute([':b' => 'Rapid click test message']);
$assert((int)$countStmt->fetchColumn() === 1, '2.9 Exactly 1 message row exists in database');

// Case 2.10: Blocklist enforcement: blocked user cannot send direct messages
$pdo->exec('INSERT INTO user_blocks (user_id, blocked_user_id) VALUES (2, 1)'); // Bob blocks Alice
$blockRejected = false;
try {
    $messages->sendMessage(1, 2, 'Should not deliver');
} catch (\InvalidArgumentException $e) {
    $blockRejected = str_contains($e->getMessage(), 'blocked');
}
$assert($blockRejected, '2.10 MessageRepository rejects messaging when recipient has blocked sender');
$pdo->exec('DELETE FROM user_blocks WHERE user_id = 2 AND blocked_user_id = 1'); // cleanup block


// =========================================================================
// SUITE 3: CATEGORY COMMUNITIES GROUP CHAT (10 CASES)
// =========================================================================
echo "\n--- Suite 3: Category Communities Group Chat (10 Cases) ---\n";

// Case 3.1: Provisioning generates exactly 1 room per category
$community->ensureCategoryChatRooms();
$rooms = $community->getChatRooms();
$categoryRooms = array_filter($rooms, static fn($r) => !empty($r['category_id']));
$assert(count($categoryRooms) === 3, '3.1 Exactly 3 category rooms provisioned for 3 categories');

// Case 3.2: Room provisioning is idempotent
$community->ensureCategoryChatRooms();
$roomsAfter = $community->getChatRooms();
$categoryRoomsAfter = array_filter($roomsAfter, static fn($r) => !empty($r['category_id']));
$assert(count($categoryRoomsAfter) === 3, '3.2 Calling ensureCategoryChatRooms again is strictly idempotent');

// Case 3.3: Database UNIQUE constraint on category_id
$uniqueBlocked = false;
try {
    $pdo->exec("INSERT INTO chat_rooms (slug, name, category_id, created_by, is_active) VALUES ('dup-cat-room', 'Duplicate', 1, 1, 1)");
} catch (\PDOException $e) {
    $uniqueBlocked = true;
}
$assert($uniqueBlocked, '3.3 Database enforces UNIQUE constraint on category_id');

// Case 3.4: Category group chats display category metadata
$techRoom = $community->getChatRoomBySlug('technology');
$assert($techRoom !== null, '3.4 Room by slug "technology" exists');
$assert($techRoom['category_name'] === 'Technology' && $techRoom['icon_name'] === 'cpu', '3.4 Room retrieves category_name and icon_name');

// Case 3.5: Multiple users join and chat in the same room
$community->joinRoom((int)$techRoom['id'], 1); // Alice joins
$community->joinRoom((int)$techRoom['id'], 2); // Bob joins
$m1Id = $community->postChatMessage((int)$techRoom['id'], 1, 'Hello tech room from Alice!');
$m2Id = $community->postChatMessage((int)$techRoom['id'], 2, 'Hi Alice, Bob is here too!');
$techMsgs = $community->getChatMessages((int)$techRoom['id'], 50);
$assert(count($techMsgs) === 2, '3.5 Both Alice and Bob\'s messages are present in Technology room');

// Case 3.6: Messages in Category Room 1 never leak to Category Room 2
$designRoom = $community->getChatRoomBySlug('design');
$designMsgs = $community->getChatMessages((int)$designRoom['id'], 50);
$assert(empty($designMsgs), '3.6 Design room contains 0 messages (no cross-category leakage)');

// Case 3.7: Private DMs never appear in any Category room
$dmTextsInRoom = array_filter($techMsgs, static fn($m) => str_contains($m['message'], 'Hello Bob, this is Alice!'));
$assert(empty($dmTextsInRoom), '3.7 Private DM does not appear in Category chat room');

// Case 3.8: Category room messages never appear in private DMs
$dms = $messages->getThread(1, 2);
$roomTextsInDm = array_filter($dms, static fn($m) => str_contains($m['body'], 'Hello tech room from Alice!'));
$assert(empty($roomTextsInDm), '3.8 Category chat message does not appear in private DMs');

// Case 3.9: Double-click duplicate protection in Category rooms within 2 seconds
$dupRoom1 = $community->postChatMessage((int)$techRoom['id'], 1, 'Quick tap duplicate test');
$dupRoom2 = $community->postChatMessage((int)$techRoom['id'], 1, 'Quick tap duplicate test');
$assert($dupRoom1 === $dupRoom2, '3.9 Rapid duplicate message returns existing ID');
$countRoomStmt = $pdo->prepare('SELECT COUNT(*) FROM chat_room_messages WHERE message = :m');
$countRoomStmt->execute([':m' => 'Quick tap duplicate test']);
$assert((int)$countRoomStmt->fetchColumn() === 1, '3.9 Exactly 1 message row exists in chat_room_messages');

// Case 3.10: Incremental polling via after / after_id
$latestBefore = $community->getChatMessages((int)$techRoom['id'], 50);
$lastId = (int)end($latestBefore)['id'];
$newMsgId = $community->postChatMessage((int)$techRoom['id'], 2, 'Polled new message');
$polledMsgs = $community->getChatMessages((int)$techRoom['id'], 50, $lastId);
$assert(count($polledMsgs) === 1 && (int)$polledMsgs[0]['id'] === $newMsgId, '3.10 Incremental polling after_id retrieves only newly added message');


// =========================================================================
// SUMMARY
// =========================================================================
echo "\n=======================================================\n";
echo "CHAT VERIFICATION COMPLETE: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

ob_end_flush();
exit($failed === 0 ? 0 : 1);
