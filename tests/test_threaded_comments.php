<?php
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/../autoload.php';

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Http\CommunityController;
use InfinityBlog\Http\Request;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Support\UserIdentityHelper;

$sessionConfig = AppConfig::fromEnvironment();
$sessions = new SessionManager($sessionConfig);
$sessions->start();

echo "=== Running Threaded Comments & Discussion System Tests ===\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup SQLite in-memory tables
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
    slug VARCHAR(100) NOT NULL
)');
$pdo->exec("INSERT INTO categories (id, name, slug) VALUES (1, 'General', 'general')");

$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL DEFAULT 1,
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
    status VARCHAR(20) NOT NULL DEFAULT \'published\',
    reading_time INTEGER NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE article_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    parent_id INTEGER NULL,
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'published\',
    is_pinned INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE article_comment_reactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    comment_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    reaction VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (comment_id, user_id)
)');

$pdo->exec('CREATE TABLE comment_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    comment_id INTEGER NOT NULL,
    reporter_id INTEGER NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT \'pending\',
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
    type VARCHAR(50) NOT NULL,
    reference_id INTEGER NULL,
    message VARCHAR(255) NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

// Seed test users
$now = date('Y-m-d H:i:s');
$pdo->exec("INSERT INTO users (id, username, email, display_name, password_hash, role) VALUES
    (1, 'tenderherbs', 'tenderherbs@example.com', 'Tender Herbs', 'hash1', 'author'),
    (2, 'al_dante', 'aldante@example.com', 'Al Dante', 'hash2', 'author'),
    (3, 'ratatooey', 'ratatooey@example.com', 'Ratatooey', 'hash3', 'author'),
    (4, 'cherrygarcia', 'cherry@example.com', 'Cherry Garcia', 'hash4', 'author'),
    (5, 'admin_user', 'admin@example.com', 'System Admin', 'hash5', 'admin')");

// Seed test post
$pdo->exec("INSERT INTO posts (id, author_id, title, slug, content, status) VALUES
    (100, 1, 'Delicious Olive Oil Pancakes Recipe', 'olive-oil-pancakes', 'Recipe content here', 'published')");

$repo = new CommunityRepository($pdo);
$pass = 0;
$fail = 0;

function assertCondition(bool $cond, string $msg, &$pass, &$fail): void {
    if ($cond) {
        echo "PASS: {$msg}\n";
        $pass++;
    } else {
        echo "FAIL: {$msg}\n";
        $fail++;
    }
}

// 1. Thread Hierarchy and Comments Creation
echo "\n--- 1. Thread Hierarchy and Nested Replies ---\n";
$comment1Id = $repo->addComment(100, 1, 'Great recipe! I also made the batter with olive oil instead of melted butter.');
assertCondition($comment1Id > 0, "Top-level comment created with ID {$comment1Id}", $pass, $fail);

$comment2Id = $repo->addComment(100, 2, 'wow great tip for people like me who are too lazy to melt the butter', $comment1Id);
assertCondition($comment2Id > 0, "Child reply created targeting parent {$comment1Id}", $pass, $fail);

$comment3Id = $repo->addComment(100, 3, '+1 for being too lazy to melt the butter lol. and olive oil is just so tasty', $comment2Id);
assertCondition($comment3Id > 0, "Nested reply created targeting child {$comment2Id}", $pass, $fail);

$comment4Id = $repo->addComment(100, 4, 'I love that the olive oil gives the pancakes a more fruity taste!', $comment1Id);
assertCondition($comment4Id > 0, "Second sibling reply created targeting parent {$comment1Id}", $pass, $fail);

// Retrieve comments
$tree = $repo->getPostComments(100, 2);
assertCondition(count($tree) === 1, "Root comments count is 1", $pass, $fail);
$root = $tree[0];
assertCondition(count($root['replies']) === 2, "Parent comment has 2 direct replies", $pass, $fail);
assertCondition($root['replies'][0]['id'] === $comment2Id, "First reply ID matches", $pass, $fail);
assertCondition(count($root['replies'][0]['replies']) === 1, "Nested reply belongs to first reply", $pass, $fail);
assertCondition($root['replies'][0]['replies'][0]['id'] === $comment3Id, "Nested reply ID matches", $pass, $fail);
assertCondition($repo->getCommentCount(100) === 4, "Total comments count is 4", $pass, $fail);

// 2. Identity Safety & Public Handle Normalization
echo "\n--- 2. Public Identity Safety & Handle Normalization ---\n";
assertCondition(!str_contains($root['handle'], '@example.com'), "Root comment handle does not contain email domain", $pass, $fail);
assertCondition($root['handle'] === '@tenderherbs', "Author handle properly formatted as @tenderherbs", $pass, $fail);
assertCondition($root['is_author'] === true, "Author identified correctly as post creator", $pass, $fail);
assertCondition($root['replies'][0]['is_author'] === false, "Commenter 2 correctly not marked as post author", $pass, $fail);
assertCondition($root['avatar_url'] === null || str_starts_with((string)$root['avatar_url'], '/'), "Avatar URL resolves to a safe path or null", $pass, $fail);

// 3. Reactions: Like / Dislike Toggle
echo "\n--- 3. Reactions: Like & Dislike System ---\n";
$r1 = $repo->reactToComment($comment1Id, 2, 'like');
assertCondition($r1['likes'] === 1, "Likes count incremented to 1", $pass, $fail);
assertCondition($r1['viewer_reaction'] === 'like', "Viewer reaction confirmed as 'like'", $pass, $fail);

$r2 = $repo->reactToComment($comment1Id, 3, 'like');
assertCondition($r2['likes'] === 2, "Likes count incremented to 2 with second user", $pass, $fail);

// Toggle off user 2's like
$r3 = $repo->reactToComment($comment1Id, 2, 'like');
assertCondition($r3['likes'] === 1, "Re-clicking like toggled reaction off (count back to 1)", $pass, $fail);
assertCondition($r3['viewer_reaction'] === null, "Viewer reaction now null after toggle off", $pass, $fail);

// User 2 reacts dislike
$r4 = $repo->reactToComment($comment1Id, 2, 'dislike');
assertCondition($r4['dislikes'] === 1, "Dislike count incremented to 1", $pass, $fail);
assertCondition($r4['viewer_reaction'] === 'dislike', "Viewer reaction confirmed as 'dislike'", $pass, $fail);

// User 2 switches from dislike to like
$r5 = $repo->reactToComment($comment1Id, 2, 'like');
assertCondition($r5['likes'] === 2, "Likes incremented to 2 when switching from dislike", $pass, $fail);
assertCondition($r5['dislikes'] === 0, "Dislikes decremented to 0 when switching to like", $pass, $fail);
assertCondition($r5['viewer_reaction'] === 'like', "Viewer reaction updated to 'like'", $pass, $fail);

// 4. Pinning Comments
echo "\n--- 4. Pinned Comments by Author / Admin ---\n";
$pinnedOk = $repo->pinComment($comment1Id, 1);
assertCondition($pinnedOk === true, "Post author successfully pinned comment 1", $pass, $fail);

$treePinned = $repo->getPostComments(100);
assertCondition($treePinned[0]['is_pinned'] === true, "Comment 1 is flagged as is_pinned: true", $pass, $fail);

$unauthorizedPin = $repo->pinComment($comment1Id, 2);
assertCondition($unauthorizedPin === false, "Unauthorized user 2 blocked from pinning", $pass, $fail);

// 5. Editing and Deleting
echo "\n--- 5. Edit and Delete Comments ---\n";
$editOk = $repo->editComment($comment1Id, 1, 'Updated pancake batter review with fresh olive oil notes.');
assertCondition($editOk === true, "Comment author successfully edited comment", $pass, $fail);

$updatedComm = $repo->getCommentById($comment1Id);
assertCondition(str_contains($updatedComm['content'], 'Updated pancake batter'), "Content updated in database", $pass, $fail);

$deleteOk = $repo->deleteComment($comment4Id, 4);
assertCondition($deleteOk === true, "Author deleted comment 4", $pass, $fail);
assertCondition($repo->getCommentCount(100) === 3, "Active comments count decremented to 3", $pass, $fail);

// 6. Controller API Tests
echo "\n--- 6. CommunityController API Endpoints ---\n";
$usersRepo = new UserRepository($pdo);
$guard = new AuthGuard($sessions, $usersRepo);
$postsRepo = new PostRepository($pdo);
$controller = new CommunityController($repo, $guard, $sessions, $postsRepo);

$headers = ['Host' => 'localhost', 'Accept' => 'application/json'];
$csrfToken = $sessions->csrfToken();
$authHeaders = array_merge($headers, ['X-CSRF-Token' => $csrfToken]);

// Test GET /api/posts/100/comments
$getReq = Request::fromValues('GET', '/api/posts/100/comments', '127.0.0.1', $headers, []);
$getRes = $controller->handle($getReq);
assertCondition($getRes['status'] === 200, "GET /api/posts/100/comments returns 200", $pass, $fail);
assertCondition(isset($getRes['body']['data']), "Response includes data array", $pass, $fail);
assertCondition(isset($getRes['body']['total_count']), "Response includes total_count", $pass, $fail);
assertCondition(isset($getRes['body']['post']), "Response includes post metadata", $pass, $fail);

// Test POST without CSRF
$postNoCsrf = Request::fromValues('POST', '/api/posts/100/comments', '127.0.0.1', $headers, ['content' => 'Hello']);
$resNoCsrf = $controller->handle($postNoCsrf);
assertCondition($resNoCsrf['status'] === 403, "Unverified POST rejected with 403 CSRF_INVALID", $pass, $fail);

// Login as user 2
$user2 = $usersRepo->findById(2);
$sessions->establish($user2);
$authHeaders['X-CSRF-Token'] = $sessions->csrfToken();

$postWithAuth = Request::fromValues('POST', '/api/posts/100/comments', '127.0.0.1', $authHeaders, [
    'content' => 'Testing API comment posting'
]);
$resWithAuth = $controller->handle($postWithAuth);
assertCondition($resWithAuth['status'] === 201, "Authenticated comment POST returns 201", $pass, $fail);
assertCondition(isset($resWithAuth['body']['data']['id']), "Returned comment includes generated ID", $pass, $fail);

$newId = (int) $resWithAuth['body']['data']['id'];

// Test POST /api/comments/{id}/react
$reactReq = Request::fromValues('POST', "/api/comments/{$newId}/react", '127.0.0.1', $authHeaders, [
    'reaction' => 'like'
]);
$reactRes = $controller->handle($reactReq);
assertCondition($reactRes['status'] === 200, "POST /api/comments/{id}/react returns 200", $pass, $fail);
assertCondition($reactRes['body']['data']['likes'] === 1, "Reaction confirmed with likes: 1", $pass, $fail);

// Test Report Comment
$reportReq = Request::fromValues('POST', "/api/comments/{$newId}/report", '127.0.0.1', $authHeaders, [
    'reason' => 'Inappropriate content'
]);
$reportRes = $controller->handle($reportReq);
assertCondition($reportRes['status'] === 200, "POST /api/comments/{id}/report returns 200", $pass, $fail);

echo "\n=============================================\n";
echo "Results: {$pass}/" . ($pass + $fail) . " tests passed.\n";
if ($fail === 0) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
