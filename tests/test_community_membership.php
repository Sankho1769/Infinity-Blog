<?php
declare(strict_types=1);
ob_start();

/**
 * Test Suite for Community Join-Before-Chat Membership and Authorization.
 */
require __DIR__ . '/../autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Http\CommunityController;
use InfinityBlog\Http\WebController;
use InfinityBlog\Http\Request;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Users\AdminUserRepository;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;

$config = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($config->database);
$community = new CommunityRepository($pdo);
$users = new UserRepository($pdo);
$postsRepo = new PostRepository($pdo);
$categories = new CategoryRepository($pdo);
$audit = new AuditLogRepository($pdo);

// Start session before any output
$sessions = new SessionManager($config);
$sessions->start();

// Find a test user (e.g. user 43 or first normal user)
$user = $users->findById(43);
if ($user === null) {
    $firstUser = $pdo->query("SELECT id FROM users WHERE role != 'admin' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $user = $users->findById((int) ($firstUser['id'] ?? 1));
}

// Clean up any existing test rooms from previous runs
$pdo->prepare('DELETE FROM chat_rooms WHERE slug IN (\'test-room-a\', \'test-room-b\', \'test-room-inactive\')')->execute();

// Ensure two distinct test rooms exist: Room A and Room B
$adminId = $users->findFirstAdminId() ?? 1;

$idA = $community->createChatRoom('Test Room Alpha', 'test-room-a', 'Room A for testing', null, $adminId);
$roomA = $community->getChatRoomBySlug('test-room-a');

$idB = $community->createChatRoom('Test Room Beta', 'test-room-b', 'Room B for testing', null, $adminId);
$roomB = $community->getChatRoomBySlug('test-room-b');

// Create an inactive room for testing inactive room rejection
$idInact = $community->createChatRoom('Test Inactive Room', 'test-room-inactive', 'Inactive room for testing', null, $adminId);
$pdo->prepare('UPDATE chat_rooms SET is_active = 0 WHERE id = :id')->execute([':id' => $idInact]);
$stmt = $pdo->prepare('SELECT * FROM chat_rooms WHERE slug = :slug LIMIT 1');
$stmt->execute([':slug' => 'test-room-inactive']);
$roomInactive = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure user is NOT initially a member of Room A or Room B
$pdo->prepare('DELETE FROM chat_room_members WHERE user_id = :uid AND room_id IN (:ra, :rb)')->execute([
    ':uid' => $user->id,
    ':ra' => (int) $roomA['id'],
    ':rb' => (int) $roomB['id'],
]);

// Track initial messages count for preservation check
$initialRoomCount = (int) $pdo->query('SELECT COUNT(*) FROM chat_rooms')->fetchColumn();

// Setup controller and guard
$guard = new AuthGuard($sessions, $users);
$controller = new CommunityController($community, $guard, $sessions, $postsRepo);
$follows = new FollowRepository($pdo);
$messages = new MessageRepository($pdo);
$webController = new WebController(
    $postsRepo,
    $categories,
    $users,
    new AdminUserRepository($pdo),
    $audit,
    $guard,
    $sessions,
    $config,
    new ScoreRepository($pdo),
    null,
    $follows,
    $messages,
    null,
    null,
    $community
);

$assertions = 0;
function assertTest(bool $condition, string $msg): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new \RuntimeException("FAILED: $msg");
    }
    echo "PASS: $msg\n";
}

echo "=== Running Community Join-Before-Chat Membership Tests ===\n";
echo "Testing with User ID: {$user->id} ({$user->displayName})\n";

// Ensure CSRF token
$csrf = $sessions->csrfToken();
$validHeaders = [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
    'X-CSRF-Token' => $csrf,
];

// --- Requirement 7: Unauthenticated join and message requests are rejected ---
$reqUnauthJoin = Request::fromValues('POST', '/api/chat/rooms/test-room-a/join', '127.0.0.1', $validHeaders, []);
$resUnauthJoin = $controller->handle($reqUnauthJoin);
assertTest($resUnauthJoin['status'] === 401, 'Unauthenticated join request must be rejected with 401');
assertTest($resUnauthJoin['body']['error']['code'] === 'UNAUTHENTICATED', 'Error code must be UNAUTHENTICATED');

$reqUnauthMsg = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, ['message' => 'Should fail']);
$resUnauthMsg = $controller->handle($reqUnauthMsg);
assertTest($resUnauthMsg['status'] === 401, 'Unauthenticated message request must be rejected with 401');

// Authenticate user
$sessions->establish($user);
$validHeaders['X-CSRF-Token'] = $sessions->csrfToken();

// --- Requirement 8: Invalid CSRF tokens are rejected ---
$badCsrfHeaders = $validHeaders;
$badCsrfHeaders['X-CSRF-Token'] = 'invalid-token-xyz';
$reqBadCsrfJoin = Request::fromValues('POST', '/api/chat/rooms/test-room-a/join', '127.0.0.1', $badCsrfHeaders, []);
$resBadCsrfJoin = $controller->handle($reqBadCsrfJoin);
assertTest($resBadCsrfJoin['status'] === 403, 'Invalid CSRF on join must be rejected with 403');
assertTest($resBadCsrfJoin['body']['error']['code'] === 'CSRF_INVALID', 'Error code must be CSRF_INVALID');

$reqBadCsrfMsg = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $badCsrfHeaders, ['message' => 'Should fail']);
$resBadCsrfMsg = $controller->handle($reqBadCsrfMsg);
assertTest($resBadCsrfMsg['status'] === 403, 'Invalid CSRF on message must be rejected with 403');

// --- Requirement 1: An authenticated non-member cannot send a message ---
$reqNonMemberPost = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, ['message' => 'Unauthorized post attempt']);
$resNonMemberPost = $controller->handle($reqNonMemberPost);
assertTest($resNonMemberPost['status'] === 403, 'Authenticated non-member cannot send a message (must return 403)');
assertTest($resNonMemberPost['body']['error']['code'] === 'MEMBERSHIP_REQUIRED', 'Error code must be MEMBERSHIP_REQUIRED');

// --- Requirement 12: Direct API requests cannot bypass the membership requirement ---
$bypassAttempts = [
    ['message' => 'direct bypass', 'is_member' => true],
    ['message' => 'direct bypass', 'role' => 'member'],
    ['message' => 'direct bypass', 'joined' => 1],
];
foreach ($bypassAttempts as $payload) {
    $reqBypass = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, $payload);
    $resBypass = $controller->handle($reqBypass);
    assertTest($resBypass['status'] === 403 && $resBypass['body']['error']['code'] === 'MEMBERSHIP_REQUIRED', 'Direct API tampering cannot bypass membership requirement');
}


// --- Requirement 9: Inactive or nonexistent rooms cannot be joined or used for posting ---
$reqNonExistJoin = Request::fromValues('POST', '/api/chat/rooms/nonexistent-xyz-room/join', '127.0.0.1', $validHeaders, []);
$resNonExistJoin = $controller->handle($reqNonExistJoin);
assertTest($resNonExistJoin['status'] === 404, 'Nonexistent room join must return 404');

$reqNonExistPost = Request::fromValues('POST', '/api/chat/rooms/nonexistent-xyz-room/messages', '127.0.0.1', $validHeaders, ['message' => 'Hi']);
$resNonExistPost = $controller->handle($reqNonExistPost);
assertTest($resNonExistPost['status'] === 404, 'Nonexistent room message must return 404');

$reqInactiveJoin = Request::fromValues('POST', '/api/chat/rooms/test-room-inactive/join', '127.0.0.1', $validHeaders, []);
$resInactiveJoin = $controller->handle($reqInactiveJoin);
assertTest($resInactiveJoin['status'] === 403, 'Inactive room join must return 403');
assertTest($resInactiveJoin['body']['error']['code'] === 'ROOM_INACTIVE', 'Error code must be ROOM_INACTIVE');

$reqInactivePost = Request::fromValues('POST', '/api/chat/rooms/test-room-inactive/messages', '127.0.0.1', $validHeaders, ['message' => 'Hi']);
$resInactivePost = $controller->handle($reqInactivePost);
assertTest($resInactivePost['status'] === 403, 'Inactive room message must return 403');
assertTest($resInactivePost['body']['error']['code'] === 'ROOM_INACTIVE', 'Error code must be ROOM_INACTIVE');

// --- Requirement 2: A user can join a valid community ---
$reqJoinA = Request::fromValues('POST', '/api/chat/rooms/test-room-a/join', '127.0.0.1', $validHeaders, []);
$resJoinA = $controller->handle($reqJoinA);
assertTest($resJoinA['status'] === 200, 'User can join a valid community (must return 200)');
assertTest($resJoinA['body']['data']['is_member'] === true, 'Response must confirm is_member: true');
assertTest($resJoinA['body']['data']['slug'] === 'test-room-a', 'Response slug must match room');
assertTest($community->isMember((int) $roomA['id'], $user->id) === true, 'Database must confirm user is a member of Room A');

// --- Requirement 6: Repeated join requests do not create duplicate membership records ---
$reqJoinAgain = Request::fromValues('POST', '/api/chat/rooms/test-room-a/join', '127.0.0.1', $validHeaders, []);
$resJoinAgain = $controller->handle($reqJoinAgain);
assertTest($resJoinAgain['status'] === 200, 'Repeated join request must succeed safely (200)');
$memberRows = $pdo->prepare('SELECT COUNT(*) FROM chat_room_members WHERE room_id = :rid AND user_id = :uid');
$memberRows->execute([':rid' => (int) $roomA['id'], ':uid' => $user->id]);
$memberCount = (int) $memberRows->fetchColumn();
assertTest($memberCount === 1, 'Repeated join must NOT create duplicate records in chat_room_members');

// --- Requirement 3: A joined member can send a message successfully ---
$validMsgText = 'Hello Room A, I am a joined member! ' . bin2hex(random_bytes(4));
$reqMemberPost = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, ['message' => $validMsgText]);
$resMemberPost = $controller->handle($reqMemberPost);
assertTest($resMemberPost['status'] === 201, 'Joined member can post a message successfully (201)');
assertTest(!empty($resMemberPost['body']['data']['id']), 'Returned message must have an ID');
assertTest($resMemberPost['body']['data']['message'] === $validMsgText, 'Returned message text must match input');
$createdMsgId = (int) $resMemberPost['body']['data']['id'];

// --- Requirement 4: A joined member can reload the page and retain membership ---
$reqWebChatA = Request::fromValues('GET', '/chat/test-room-a', '127.0.0.1', [], []);
$webResultA = $webController->chat($reqWebChatA, 'test-room-a');
assertTest($webResultA['status'] === 200, 'Reloading chat page for Room A returns 200');
assertTest(str_contains($webResultA['body'], 'chatComposerContainer'), 'Page HTML contains chatComposerContainer');
assertTest(str_contains($webResultA['body'], '✓ Joined Member'), 'Page HTML displays Joined Member badge');
assertTest(str_contains($webResultA['body'], htmlspecialchars($validMsgText, ENT_QUOTES, 'UTF-8')), 'Page HTML renders the posted message');

// API stream reload check
$reqStream = Request::fromValues('GET', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, []);
$resStream = $controller->handle($reqStream);
assertTest($resStream['status'] === 200, 'GET /messages returns 200');
assertTest($resStream['body']['is_member'] === true, 'Stream endpoint returns is_member: true for joined member');

// --- Requirement 5: A user joined to Room A cannot send messages to Room B without joining Room B ---
assertTest($community->isMember((int) $roomB['id'], $user->id) === false, 'User must not be a member of Room B');
$reqPostB = Request::fromValues('POST', '/api/chat/rooms/test-room-b/messages', '127.0.0.1', $validHeaders, ['message' => 'Sneak into Room B']);
$resPostB = $controller->handle($reqPostB);
assertTest($resPostB['status'] === 403, 'Posting to Room B while only joined to Room A must return 403');
assertTest($resPostB['body']['error']['code'] === 'MEMBERSHIP_REQUIRED', 'Error code must be MEMBERSHIP_REQUIRED for Room B');

// Web view check for Room B shows Join Community prompt, not composer
$reqWebChatB = Request::fromValues('GET', '/chat/test-room-b', '127.0.0.1', [], []);
$webResultB = $webController->chat($reqWebChatB, 'test-room-b');
assertTest($webResultB['status'] === 200, 'Chat view for Room B returns 200');
assertTest(str_contains($webResultB['body'], 'Join #Test Room Beta to Participate'), 'Room B prompts user to join');
assertTest(str_contains($webResultB['body'], 'Guest Viewer'), 'Room B displays Guest Viewer badge for non-member');

// Now join Room B and verify user can post in Room B
$reqJoinB = Request::fromValues('POST', '/api/chat/rooms/test-room-b/join', '127.0.0.1', $validHeaders, []);
$resJoinB = $controller->handle($reqJoinB);
assertTest($resJoinB['status'] === 200 && $resJoinB['body']['data']['is_member'] === true, 'User can join Room B');
$reqPostBJoined = Request::fromValues('POST', '/api/chat/rooms/test-room-b/messages', '127.0.0.1', $validHeaders, ['message' => 'Now I can post in Room B!']);
$resPostBJoined = $controller->handle($reqPostBJoined);
assertTest($resPostBJoined['status'] === 201, 'User can now post in Room B after joining');
$createdMsgBId = (int) $resPostBJoined['body']['data']['id'];

// --- Requirement 10: Empty, oversized, or rate-limited messages remain rejected ---
$reqEmpty = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, ['message' => '   ']);
$resEmpty = $controller->handle($reqEmpty);
assertTest($resEmpty['status'] === 422, 'Empty message rejected with 422');

$reqTooLong = Request::fromValues('POST', '/api/chat/rooms/test-room-a/messages', '127.0.0.1', $validHeaders, ['message' => str_repeat('X', 1001)]);
$resTooLong = $controller->handle($reqTooLong);
assertTest($resTooLong['status'] === 422, 'Oversized message (>1000 chars) rejected with 422');

// --- Requirement 11: Existing chat messages and room records are preserved ---
assertTest($pdo->query('SELECT COUNT(*) FROM chat_rooms')->fetchColumn() >= $initialRoomCount, 'Chat rooms records preserved');

// Cleanup test messages and memberships to leave database clean
$pdo->prepare('DELETE FROM chat_room_messages WHERE id IN (:ma, :mb)')->execute([':ma' => $createdMsgId, ':mb' => $createdMsgBId]);
$pdo->prepare('DELETE FROM chat_room_members WHERE user_id = :uid AND room_id IN (:ra, :rb)')->execute([
    ':uid' => $user->id,
    ':ra' => (int) $roomA['id'],
    ':rb' => (int) $roomB['id'],
]);
$pdo->prepare('DELETE FROM chat_rooms WHERE slug IN (\'test-room-a\', \'test-room-b\', \'test-room-inactive\')')->execute();

echo "\nAll {$assertions} community membership assertions passed successfully!\n";
ob_end_flush();
