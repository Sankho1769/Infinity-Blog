<?php
declare(strict_types=1);

ob_start();
require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\PostController;
use InfinityBlog\Http\AdminPostController;

$cfg = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($cfg->database);

$passed = 0;
$failed = 0;

function assertTest(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

$users = new UserRepository($pdo);
$sessions = new SessionManager($cfg);
$sessions->start();
$posts = new PostRepository($pdo);
$guard = new AuthGuard($sessions, $users);

echo "=== WRITING STUDIO FULL EDITOR PUBLISH VERIFICATION ===\n\n";

// 1. Setup Admin Account Test
echo "--- 1. ADMIN USER IN WRITING STUDIO --- \n";
$adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
$adminId = (int) $adminStmt->fetchColumn();
$adminUser = $users->findById($adminId);
assertTest($adminUser !== null && $adminUser->role === 'admin', 'Active administrator account found');

$sessions->establish($adminUser);
$token = $sessions->csrfToken();

$adminController = new AdminPostController($posts, $guard, $sessions);

// Admin creates draft via /admin/posts (used by studio when role is admin)
$createReq = Request::fromValues('POST', '/admin/posts', '127.0.0.1', ['X-CSRF-Token' => $token], [
    'title' => 'Admin Studio Test Article ' . time(),
    'content' => '## Admin Article\n\nComposed in full editor mode.',
    'category_id' => 4,
    'content_type' => 'article'
]);
$createRes = $adminController->handle($createReq);
assertTest($createRes['status'] === 201, 'Admin draft created successfully via /admin/posts (HTTP 201)');
$adminPostId = (int) ($createRes['body']['data']['post']['id'] ?? 0);
assertTest($adminPostId > 0, "Admin post assigned positive ID: {$adminPostId}");

// Admin publishes post via /admin/posts/{id}/publish
$pubReq = Request::fromValues('POST', "/admin/posts/{$adminPostId}/publish", '127.0.0.1', ['X-CSRF-Token' => $token]);
$pubRes = $adminController->handle($pubReq);
assertTest($pubRes['status'] === 200, "Admin post published successfully via /admin/posts/{$adminPostId}/publish (HTTP 200)");
assertTest(($pubRes['body']['data']['post']['status'] ?? '') === 'published', 'Admin post status is confirmed published');

// Admin updates published post via /admin/posts/{id}
$updateReq = Request::fromValues('PATCH', "/admin/posts/{$adminPostId}", '127.0.0.1', ['X-CSRF-Token' => $token], [
    'content' => '## Admin Article Updated\n\nContent revised in full editor.'
]);
$updateRes = $adminController->handle($updateReq);
assertTest($updateRes['status'] === 200, "Admin post updated successfully via PATCH /admin/posts/{$adminPostId} (HTTP 200)");

$sessions->clearAuthentication();

// 2. Setup Normal Author/User Account Test
echo "\n--- 2. AUTHOR/USER IN WRITING STUDIO --- \n";
$userStmt = $pdo->query("SELECT id FROM users WHERE role = 'user' AND status = 'active' ORDER BY id ASC LIMIT 1");
$userId = (int) $userStmt->fetchColumn();
$normalUser = $users->findById($userId);
assertTest($normalUser !== null && $normalUser->role === 'user', 'Active author/user account found');

$sessions->establish($normalUser);
$userToken = $sessions->csrfToken();

$authorController = new PostController($posts, $guard, $sessions);

// User creates draft via /author/posts
$userCreateReq = Request::fromValues('POST', '/author/posts', '127.0.0.1', ['X-CSRF-Token' => $userToken], [
    'title' => 'Author Studio Test Article ' . time(),
    'content' => '## Author Article\n\nComposed in full editor mode.',
    'category_id' => 4,
    'content_type' => 'article'
]);
$userCreateRes = $authorController->handle($userCreateReq);
assertTest($userCreateRes['status'] === 201, 'Author draft created successfully via /author/posts (HTTP 201)');
$userPostId = (int) ($userCreateRes['body']['data']['post']['id'] ?? 0);
assertTest($userPostId > 0, "Author post assigned positive ID: {$userPostId}");

// User publishes post via /author/posts/{id}/publish
$userPubReq = Request::fromValues('POST', "/author/posts/{$userPostId}/publish", '127.0.0.1', ['X-CSRF-Token' => $userToken]);
$userPubRes = $authorController->handle($userPubReq);
assertTest($userPubRes['status'] === 200, "Author post published successfully via /author/posts/{$userPostId}/publish (HTTP 200)");
assertTest(($userPubRes['body']['data']['post']['status'] ?? '') === 'published', 'Author post status is confirmed published');

// User updates published post via /author/posts/{id}
$userUpdateReq = Request::fromValues('PATCH', "/author/posts/{$userPostId}", '127.0.0.1', ['X-CSRF-Token' => $userToken], [
    'content' => '## Author Article Updated\n\nContent revised in full editor.'
]);
$userUpdateRes = $authorController->handle($userUpdateReq);
assertTest($userUpdateRes['status'] === 200, "Author post updated successfully via PATCH /author/posts/{$userPostId} (HTTP 200)");

// Clean up test posts from database
$pdo->exec("DELETE FROM posts WHERE id IN ({$adminPostId}, {$userPostId})");
echo "Cleaned up temporary test posts ({$adminPostId}, {$userPostId})\n";

echo "\n============================================\n";
echo "SUMMARY: {$passed} passed, {$failed} failed.\n";
echo "============================================\n";

ob_end_flush();
if ($failed > 0) {
    exit(1);
}
exit(0);
