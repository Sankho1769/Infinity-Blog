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
use InfinityBlog\Http\SocialController;
use InfinityBlog\Http\CommunityController;
use InfinityBlog\Http\Request;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Users\AdminUserRepository;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Scores\ScoreRepository;

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
$sessions = new SessionManager($cfg);
$sessions->start();
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
    null,
    $community
);

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $msg): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$msg}\n";
    } else {
        $failed++;
        echo "FAIL: {$msg}\n";
    }
}

echo "=== 1. Testing Homepage HTML Structure (Guest Mode) ===\n";
$_GET = [];
$req = Request::fromValues('GET', '/', '127.0.0.1');
$res = $web->home($req);
assertTest($res['status'] === 200, 'Homepage returns HTTP 200');
$html = $res['body'];

assertTest(strpos($html, 'homepage-two-column-layout') !== false, 'HTML contains .homepage-two-column-layout');
assertTest(strpos($html, 'homepage-main-column') !== false, 'HTML contains .homepage-main-column (left column)');
assertTest(strpos($html, 'homepage-sidebar') !== false, 'HTML contains .homepage-sidebar (right sidebar)');

// Search field
assertTest(strpos($html, 'homepage-main-search-bar') !== false, 'HTML contains .homepage-main-search-bar');
assertTest(strpos($html, 'placeholder="Search articles, authors, topics, or communities…"') !== false, 'Search input has required placeholder');
assertTest(strpos($html, 'id="hero-search-input"') !== false, 'Search input preserves keyboard shortcut & sync ID');
assertTest(strpos($html, 'homepage-main-search-btn') !== false, 'Search bar includes submit button');

// Topic chips
assertTest(strpos($html, 'category-carousel-wrapper') !== false, 'HTML contains category filter chips');

// Latest articles
assertTest(strpos($html, 'Latest Articles') !== false, 'HTML contains Latest Articles heading');
assertTest(strpos($html, 'homepage-articles-grid') !== false, 'HTML contains .homepage-articles-grid');

// Sidebar cards
assertTest(strpos($html, 'Authors You May Know') !== false, 'Sidebar contains Authors You May Know card');
assertTest(strpos($html, 'sidebar-card-authors') !== false, 'Card has class .sidebar-card-authors');
assertTest(strpos($html, 'Discover Authors →') !== false, 'Authors card contains Discover Authors link');
assertTest(strpos($html, 'Popular Communities') !== false, 'Sidebar contains Popular Communities card');
assertTest(strpos($html, 'sidebar-card-communities') !== false, 'Card has class .sidebar-card-communities');
assertTest(strpos($html, 'Explore Communities →') !== false, 'Communities card contains Explore Communities link');

// Follow and Join buttons
assertTest(strpos($html, 'btn-follow-toggle') !== false, 'Sidebar contains .btn-follow-toggle buttons');
assertTest(strpos($html, 'btn-community-join') !== false, 'Sidebar contains .btn-community-join buttons');

echo "\n=== 2. Testing Author Recommendations (No Signed-In User Excluded When Guest) ===\n";
$eligibleAuthors = $users->listPublicAuthors();
$expectedAuthorCount = min(5, count($eligibleAuthors));
$authorRowMatches = preg_match_all('/class="[^"]*sidebar-author-row[^"]*"/', $html, $matches);
assertTest($authorRowMatches <= 5, "Author recommendations count ({$authorRowMatches}) is at most 5");
assertTest($authorRowMatches === $expectedAuthorCount, "Author recommendations count matches eligible authors ({$expectedAuthorCount})");

echo "\n=== 3. Testing Author Recommendations (Signed-In User Excluded) ===\n";
// Pick an author from eligible authors
if (!empty($eligibleAuthors)) {
    $firstAuthor = $eligibleAuthors[0];
    $authorId = (int) $firstAuthor['id'];
    
    // Simulate logged-in user in CLI environment
    $authorAccount = $users->findById($authorId);
    $now = time();
    $_SESSION['auth'] = [
        'user_id' => $authorAccount->id,
        'auth_version' => $authorAccount->authVersion,
        'issued_at' => $now,
        'last_activity' => $now,
    ];
    
    $resAuth = $web->home($req);
    $htmlAuth = $resAuth['body'];
    
    // Ensure the signed-in author is NOT in the recommendations
    $selfInSidebar = (strpos($htmlAuth, 'sidebar-author-row" data-author-id="' . $authorId . '"') !== false);
    assertTest(!$selfInSidebar, "Signed-in user (ID {$authorId}) is strictly excluded from sidebar recommendations");
    
    unset($_SESSION['auth']);
}

echo "\n=== 4. Testing Community Recommendations & Join Persistence ===\n";
$rooms = $community->getChatRooms();
$activeRooms = array_filter($rooms, fn($r) => !empty($r['is_active']));
$expectedRoomCount = min(5, count($activeRooms));
$commRowMatches = preg_match_all('/class="[^"]*sidebar-community-row[^"]*"/', $html, $matches);
assertTest($commRowMatches <= 5, "Community recommendations count ({$commRowMatches}) is at most 5");
assertTest($commRowMatches === $expectedRoomCount, "Community recommendations count matches active rooms ({$expectedRoomCount})");

echo "\n=== 5. Testing Follow / Unfollow API Functionality ===\n";
// Test user 43 following author 9 (Cristine Palmer)
$followerId = 43;
$targetAuthorId = 9;

// Ensure clean initial state
$follows->unfollow($followerId, $targetAuthorId);
assertTest(!$follows->isFollowing($followerId, $targetAuthorId), 'Initially user is not following author 9');

// Follow author 9
$followSuccess = $follows->follow($followerId, $targetAuthorId);
assertTest($followSuccess, 'Follow operation returns true');
assertTest($follows->isFollowing($followerId, $targetAuthorId), 'User is now following author 9');
$fCount = $follows->getFollowerCount($targetAuthorId);
assertTest($fCount >= 1, "Author 9 follower count ({$fCount}) incremented");

// Unfollow author 9
$unfollowSuccess = $follows->unfollow($followerId, $targetAuthorId);
assertTest($unfollowSuccess, 'Unfollow operation returns true');
assertTest(!$follows->isFollowing($followerId, $targetAuthorId), 'User is now not following author 9');

echo "\n=== 6. Testing Community Join Functionality ===\n";
// Test user 43 joining room 'web-development'
$room = $community->getChatRoomBySlug('web-development');
assertTest($room !== null, 'Room web-development exists');
$roomId = (int) $room['id'];

// Check isMember
$isMemberBefore = $community->isMember($roomId, $followerId);
$community->joinRoom($roomId, $followerId);
$isMemberAfter = $community->isMember($roomId, $followerId);
assertTest($isMemberAfter === true, 'User is confirmed member of web-development room');

// Repeated join must not duplicate
$community->joinRoom($roomId, $followerId);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_room_members WHERE room_id = :rid AND user_id = :uid');
$stmt->execute([':rid' => $roomId, ':uid' => $followerId]);
$memberRows = (int) $stmt->fetchColumn();
assertTest($memberRows === 1, 'Repeated join does not duplicate rows in chat_room_members');

echo "\n=== 7. Testing Search Integration ===\n";
$_GET = ['search' => 'Web'];
$searchReq = Request::fromValues('GET', '/', '127.0.0.1', [], [], ['search' => 'Web']);
$searchRes = $web->home($searchReq);
assertTest($searchRes['status'] === 200, 'Search query returns HTTP 200');
assertTest(strpos($searchRes['body'], 'Search Results for "Web"') !== false, 'Search results heading displayed');
assertTest(strpos($searchRes['body'], 'Clear filters ×') !== false, 'Clear filters option present');

echo "\n=== Summary: {$passed} passed, {$failed} failed. ===\n";
exit($failed === 0 ? 0 : 1);
