<?php
declare(strict_types=1);

/**
 * Infinity Blog - End-to-End API Integration & Security Verification Suite
 * Tests actual HTTP requests against http://localhost/infinity-blog/api/v1
 * Tests security protections: rate limiting, CSRF, IDOR, session lifecycle,
 * account deactivation rejection, and web file protections.
 */

require_once __DIR__ . '/../backend/config/Env.php';
require_once __DIR__ . '/../backend/config/Database.php';
require_once __DIR__ . '/../backend/src/RateLimiter.php';
require_once __DIR__ . '/../backend/src/AISummarizer.php';

use InfinityBlog\Config\Env;
use InfinityBlog\Config\Database;
use InfinityBlog\Core\RateLimiter;
use InfinityBlog\Services\AISummarizer;

Env::load();

class ApiIntegrationTest {
    private string $baseUrl;
    private string $rootUrl;
    private ?string $cookieFile = null;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct(string $baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
        // Derive root URL, e.g. http://localhost/infinity-blog
        $this->rootUrl = preg_replace('#/api/v1/?$#', '', $this->baseUrl);
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'inf_cookie_');
    }

    public function __destruct() {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    private function request(string $method, string $path, ?array $body = null, array $headers = []): array {
        $url = $this->baseUrl . $path;
        return $this->executeCurl($method, $url, $body, $headers);
    }

    private function rawRequest(string $method, string $fullUrl, ?array $body = null, array $headers = []): array {
        return $this->executeCurl($method, $fullUrl, $body, $headers);
    }

    private function executeCurl(string $method, string $url, ?array $body = null, array $headers = []): array {
        $ch = curl_init($url);

        // Standard production client headers (NO security bypass headers)
        $defaultHeaders = ['Accept: application/json'];
        if ($body !== null) {
            $defaultHeaders[] = 'Content-Type: application/json';
        }
        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("cURL failed for {$url}: {$err}");
        }

        $json = json_decode($response, true);
        return [
            'status' => $status,
            'body' => $json,
            'raw' => $response,
        ];
    }

    private function clearCookies(): void {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            file_put_contents($this->cookieFile, '');
        }
    }

    private function assert(bool $condition, string $message): void {
        if ($condition) {
            echo "  [PASS] {$message}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$message}\n";
            $this->failed++;
        }
    }

    public function runAll(): void {
        echo "=======================================================\n";
        echo " INFINITY BLOG - FULL-STACK INTEGRATION TEST SUITE\n";
        echo " API Base: {$this->baseUrl}\n";
        echo " Root URL: {$this->rootUrl}\n";
        echo "=======================================================\n\n";

        $this->testFileAndDirectoryProtections();
        $this->testRateLimiterEngine();
        $this->testAuthentication();
        $this->testInactiveAccountLifecycle();
        $this->testPublicBlog();
        $this->testAuthorPostOperations();
        $this->testPatchPreservesCategory();
        $this->testAuthorizationAndIDOR();
        $this->testAdminOperations();
        $this->testAISummarization();

        echo "\n=======================================================\n";
        echo " TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "=======================================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testFileAndDirectoryProtections(): void {
        echo "1. Testing Web Protections & Sensitive File Access Safeguards:\n";

        // 1.1 Web access to database/setup.php must be 403 Forbidden
        $res = $this->rawRequest('GET', "{$this->rootUrl}/database/setup.php");
        $this->assert($res['status'] === 403, 'Web access to database/setup.php denied with HTTP 403 Forbidden');

        // 1.2 Web access to backend/config/Database.php must be 403 Forbidden
        $res = $this->rawRequest('GET', "{$this->rootUrl}/backend/config/Database.php");
        $this->assert($res['status'] === 403, 'Web access to backend directory denied with HTTP 403 Forbidden');

        // 1.3 Web access to tests/integration_test.php must be 403 Forbidden
        $res = $this->rawRequest('GET', "{$this->rootUrl}/tests/integration_test.php");
        $this->assert($res['status'] === 403, 'Web access to tests directory denied with HTTP 403 Forbidden');

        // 1.4 Web access to .env must be 403 Forbidden
        $res = $this->rawRequest('GET', "{$this->rootUrl}/.env");
        $this->assert($res['status'] === 403, 'Web access to .env configuration file denied with HTTP 403 Forbidden');
    }

    private function testRateLimiterEngine(): void {
        echo "\n2. Testing Rate Limiter Engine & Enforcement:\n";

        $testAction = 'test_unit_rate_limit';
        $testKey = 'client_' . time() . '_' . random_int(1000, 9999);
        $maxLimit = 3;
        $window = 60;

        // Hit 1, 2, 3 should succeed
        $h1 = RateLimiter::check($testAction, $testKey, $maxLimit, $window);
        $h2 = RateLimiter::check($testAction, $testKey, $maxLimit, $window);
        $h3 = RateLimiter::check($testAction, $testKey, $maxLimit, $window);
        $this->assert($h1 && $h2 && $h3, 'Hits 1-3 within max limit succeed');

        // Hit 4 must be rate-limited
        $h4 = RateLimiter::check($testAction, $testKey, $maxLimit, $window);
        $this->assert(!$h4, 'Hit 4 exceeding max limit is strictly rate-limited (returns false)');
    }

    private function testAuthentication(): void {
        echo "\n3. Testing Authentication & Session Management:\n";
        $this->clearCookies();

        // 3.1 Invalid login
        $res = $this->request('POST', '/auth/login', [
            'identifier' => 'non_existent_user',
            'password' => 'WrongPassword123',
        ]);
        $this->assert($res['status'] === 401, 'Invalid credentials return HTTP 401');
        $this->assert(($res['body']['error']['code'] ?? '') === 'INVALID_CREDENTIALS', 'Error code is INVALID_CREDENTIALS');

        // 3.2 Empty identifier/password validation
        $valRes = $this->request('POST', '/auth/login', [
            'identifier' => '',
            'password' => '',
        ]);
        $this->assert($valRes['status'] === 422, 'Empty credentials rejected with HTTP 422 VALIDATION_ERROR');

        // 3.3 Valid admin login
        $res = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $this->assert($res['status'] === 200, 'Valid admin login returns HTTP 200');
        $this->assert(($res['body']['data']['user']['role'] ?? '') === 'admin', 'Returned role is admin');
        $csrfToken = $res['body']['data']['csrf_token'] ?? '';
        $this->assert(!empty($csrfToken), 'Login response contains session CSRF token');

        // 3.4 Session check /auth/me with session cookie
        $res = $this->request('GET', '/auth/me');
        $this->assert($res['status'] === 200, 'GET /auth/me returns HTTP 200 with active session');
        $this->assert(($res['body']['data']['user']['username'] ?? '') === 'Sounak Sikdar', 'Current user matches authenticated identity');

        // 3.5 CSRF token endpoint
        $res = $this->request('GET', '/auth/csrf');
        $this->assert($res['status'] === 200, 'GET /auth/csrf returns HTTP 200');
        $this->assert(!empty($res['body']['data']['csrf_token']), 'Returns valid CSRF token');

        // 3.6 Logout
        $res = $this->request('POST', '/auth/logout', null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($res['status'] === 200, 'POST /auth/logout invalidates session and returns HTTP 200');

        // 3.7 Confirm unauthenticated after logout
        $res = $this->request('GET', '/auth/me');
        $this->assert($res['status'] === 401, 'GET /auth/me returns HTTP 401 after logout');
    }

    private function testInactiveAccountLifecycle(): void {
        echo "\n4. Testing Account Deactivation & Session Invalidation:\n";
        $this->clearCookies();

        // 4.1 Admin login
        $adminLogin = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $adminCsrf = $adminLogin['body']['data']['csrf_token'] ?? '';

        // 4.2 Create user for deactivation test
        $tempUsername = 'temp_deact_user_' . time();
        $createRes = $this->request('POST', '/admin/users', [
            'username' => $tempUsername,
            'display_name' => 'Temp Deactivate QA',
            'email' => "{$tempUsername}@infinityblog.local",
            'role' => 'author',
            'password' => 'SecurePass2026!',
        ], ["X-CSRF-Token: {$adminCsrf}"]);
        $this->assert($createRes['status'] === 201, 'Admin creates author account for lifecycle test');
        $tempUserId = (int)($createRes['body']['data']['id'] ?? 0);

        // 4.3 Switch session: Log in as the new user
        $this->clearCookies();
        $userLogin = $this->request('POST', '/auth/login', [
            'identifier' => $tempUsername,
            'password' => 'SecurePass2026!',
        ]);
        $this->assert($userLogin['status'] === 200, 'New author logs in successfully');
        $userCsrf = $userLogin['body']['data']['csrf_token'] ?? '';

        // 4.4 Switch back to admin: Deactivate the user
        $this->clearCookies();
        $adminLogin = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $adminCsrf = $adminLogin['body']['data']['csrf_token'] ?? '';
        $deactRes = $this->request('POST', "/admin/users/{$tempUserId}/deactivate", null, ["X-CSRF-Token: {$adminCsrf}"]);
        $this->assert($deactRes['status'] === 200, 'Admin deactivates the author account');

        // 4.5 Attempt to log in with deactivated account
        $this->clearCookies();
        $blockedLogin = $this->request('POST', '/auth/login', [
            'identifier' => $tempUsername,
            'password' => 'SecurePass2026!',
        ]);
        $this->assert($blockedLogin['status'] === 403, 'Login attempt by deactivated account is rejected with HTTP 403');
        $this->assert(($blockedLogin['body']['error']['code'] ?? '') === 'ACCOUNT_INACTIVE', 'Error code is ACCOUNT_INACTIVE');

        // 4.6 Reactivate account
        $this->clearCookies();
        $adminLogin = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $adminCsrf = $adminLogin['body']['data']['csrf_token'] ?? '';
        $actRes = $this->request('POST', "/admin/users/{$tempUserId}/activate", null, ["X-CSRF-Token: {$adminCsrf}"]);
        $this->assert($actRes['status'] === 200, 'Admin reactivates the author account');

        // 4.7 Login succeeds after reactivation
        $this->clearCookies();
        $reLogin = $this->request('POST', '/auth/login', [
            'identifier' => $tempUsername,
            'password' => 'SecurePass2026!',
        ]);
        $this->assert($reLogin['status'] === 200, 'Author login succeeds after reactivation');
    }

    private function testPublicBlog(): void {
        echo "\n5. Testing Public Blog Endpoints:\n";
        $this->clearCookies();

        // 5.1 Public Categories
        $res = $this->request('GET', '/categories');
        $this->assert($res['status'] === 200, 'GET /categories returns HTTP 200');
        $this->assert(is_array($res['body']['data'] ?? null) && count($res['body']['data']) > 0, 'Categories list is populated');

        // 5.2 Public Published Posts
        $res = $this->request('GET', '/posts');
        $this->assert($res['status'] === 200, 'GET /posts returns HTTP 200');
        $posts = $res['body']['data'] ?? [];
        $this->assert(!empty($posts), 'Public posts returned');
        
        // Confirm no drafts are exposed
        $hasDraft = false;
        foreach ($posts as $p) {
            if (($p['status'] ?? '') === 'draft') $hasDraft = true;
        }
        $this->assert(!$hasDraft, 'Draft articles are strictly excluded from public listing');

        // 5.3 Single Published Post
        $firstId = $posts[0]['id'] ?? 1;
        $res = $this->request('GET', "/posts/{$firstId}");
        $this->assert($res['status'] === 200, "GET /posts/{$firstId} returns published article details");
        $this->assert(!empty($res['body']['data']['content']), 'Article content is returned');
        $this->assert(!empty($res['body']['data']['author_name']), 'Author display name is included');

        // 5.4 Search Filter
        $res = $this->request('GET', '/posts?q=Web');
        $this->assert($res['status'] === 200, 'GET /posts?q=Web succeeds');
        $this->assert(count($res['body']['data'] ?? []) > 0, 'Search keyword returns matching posts');

        // 5.5 Category Filter
        $res = $this->request('GET', '/posts?category_id=1');
        $this->assert($res['status'] === 200, 'GET /posts?category_id=1 succeeds');
    }

    private function testAuthorPostOperations(): void {
        echo "\n6. Testing Author Post CRUD, Publishing & Deletion Lifecycle:\n";
        $this->clearCookies();

        // 6.1 Log in as author John Doe
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'john_doe',
            'password' => 'Author@Infinity2026!',
        ]);
        $this->assert($loginRes['status'] === 200, 'Author login succeeds');
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 6.2 Create draft post
        $newPost = [
            'title' => 'Automated Integration Testing in PHP & Vanilla JS',
            'content' => 'Comprehensive integration tests ensure that the API contract matches frontend expectations perfectly.',
            'status' => 'draft',
            'category_id' => null,
        ];
        $res = $this->request('POST', '/author/posts', $newPost, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($res['status'] === 201, 'Author creates draft post (HTTP 201)');
        $createdId = (int)($res['body']['data']['id'] ?? 0);
        $this->assert($createdId > 0, "Created post assigned valid ID #{$createdId}");

        // 6.3 Verify draft is NOT public
        $publicCheck = $this->request('GET', "/posts/{$createdId}");
        $this->assert($publicCheck['status'] === 404, 'Draft post is NOT accessible via public GET /posts/{id}');

        // 6.4 Attempt to publish without category (Must fail validation)
        $pubFail = $this->request('POST', "/author/posts/{$createdId}/publish", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($pubFail['status'] === 422, 'Publishing without active category rejected with validation error (HTTP 422)');

        // 6.5 Update post with category
        $updateRes = $this->request('PATCH', "/author/posts/{$createdId}", [
            'title' => 'Automated Integration Testing in PHP & Vanilla JS (Updated)',
            'category_id' => 1,
            'status' => 'draft',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($updateRes['status'] === 200, 'Author updates post with active category');

        // 6.6 Publish post
        $pubSuccess = $this->request('POST', "/author/posts/{$createdId}/publish", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($pubSuccess['status'] === 200, 'Author publishes post with active category (HTTP 200)');

        // 6.7 Verify post is now public
        $publicCheck2 = $this->request('GET', "/posts/{$createdId}");
        $this->assert($publicCheck2['status'] === 200, 'Published post is now accessible via public GET /posts/{id}');

        // 6.8 Unpublish post
        $unpubRes = $this->request('POST', "/author/posts/{$createdId}/unpublish", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($unpubRes['status'] === 200, 'Author unpublishes post back to draft (HTTP 200)');

        // 6.9 Delete post
        $delRes = $this->request('DELETE', "/author/posts/{$createdId}", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($delRes['status'] === 200, 'Author deletes own post (HTTP 200)');

        // 6.10 Confirm deleted
        $getDeleted = $this->request('GET', "/author/posts/{$createdId}");
        $this->assert($getDeleted['status'] === 404, 'Deleted post returns HTTP 404');
    }

    private function testPatchPreservesCategory(): void {
        echo "\n7. Testing PATCH Partial Updates Category Preservation:\n";
        $this->clearCookies();

        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'john_doe',
            'password' => 'Author@Infinity2026!',
        ]);
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // Create post with category 1
        $postRes = $this->request('POST', '/author/posts', [
            'title' => 'Category Preservation Test Article',
            'content' => 'Verifying that omitting category_id in PATCH does not wipe out the category.',
            'category_id' => 1,
            'status' => 'draft',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $postId = (int)($postRes['body']['data']['id'] ?? 0);
        $this->assert($postId > 0, 'Created initial post with Category #1');

        // PATCH updating only title
        $patchRes = $this->request('PATCH', "/author/posts/{$postId}", [
            'title' => 'Category Preservation Test Article (Updated Title Only)',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($patchRes['status'] === 200, 'PATCH request updating only title succeeds');

        // Fetch post and confirm category is still 1
        $getRes = $this->request('GET', "/author/posts/{$postId}");
        $this->assert((int)($getRes['body']['data']['category_id'] ?? 0) === 1, 'Existing category_id is properly preserved');

        // Clean up
        $this->request('DELETE', "/author/posts/{$postId}", null, ["X-CSRF-Token: {$csrfToken}"]);
    }

    private function testAuthorizationAndIDOR(): void {
        echo "\n8. Testing Role Authorization, IDOR Protection & CSRF Enforcement:\n";
        $this->clearCookies();

        // 8.1 Unauthenticated requests to protected author routes
        $res = $this->request('GET', '/author/posts');
        $this->assert($res['status'] === 401, 'Unauthenticated GET /author/posts denied with HTTP 401');

        $res = $this->request('POST', '/author/posts', ['title' => 'hack', 'content' => 'hack']);
        $this->assert($res['status'] === 401, 'Unauthenticated POST /author/posts denied with HTTP 401');

        // 8.2 Login as author John Doe
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'john_doe',
            'password' => 'Author@Infinity2026!',
        ]);
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 8.3 Author attempts to create an admin category (Role escalation check)
        $catAttempt = $this->request('POST', '/admin/categories', [
            'name' => 'Illegal Category',
            'slug' => 'illegal-category',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($catAttempt['status'] === 403, 'Author attempting admin category creation denied with HTTP 403 FORBIDDEN');

        // 8.4 Author attempts to edit another author\'s post (IDOR check on Post #1 owned by Sounak)
        $idorAttempt = $this->request('PATCH', '/author/posts/1', [
            'title' => 'Tampered Title',
            'content' => 'Tampered Content',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($idorAttempt['status'] === 403, 'Author attempting to edit another user\'s post denied with HTTP 403 IDOR');

        // 8.5 Author attempts to delete another author\'s post
        $idorDel = $this->request('DELETE', '/author/posts/1', null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($idorDel['status'] === 403, 'Author attempting to delete another user\'s post denied with HTTP 403 IDOR');

        // 8.6 Missing CSRF token on mutation
        $noCsrf = $this->request('POST', '/author/posts', [
            'title' => 'Post without CSRF',
            'content' => 'Content without CSRF token',
        ]);
        $this->assert($noCsrf['status'] === 403, 'State-changing mutation without CSRF token denied with HTTP 403');
    }

    private function testAdminOperations(): void {
        echo "\n9. Testing Administrator Capabilities:\n";
        $this->clearCookies();

        // 9.1 Admin login
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $this->assert($loginRes['status'] === 200, 'Admin login succeeds');
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 9.2 Admin System Stats
        $statsRes = $this->request('GET', '/admin/stats');
        $this->assert($statsRes['status'] === 200, 'Admin GET /admin/stats returns HTTP 200');
        $this->assert(isset($statsRes['body']['data']['posts']['total']), 'Stats include total posts');
        $this->assert(isset($statsRes['body']['data']['users']['authors']), 'Stats include author accounts');

        // 9.3 Admin Categories Management
        $uniqueSuffix = time() . '_' . random_int(100, 999);
        $newCat = [
            'name' => 'Data Science ' . $uniqueSuffix,
            'slug' => 'data-science-' . $uniqueSuffix,
            'description' => 'Statistical modeling and big data.',
        ];
        $catRes = $this->request('POST', '/admin/categories', $newCat, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($catRes['status'] === 201, 'Admin creates category (HTTP 201)');
        $catId = (int)($catRes['body']['data']['id'] ?? 0);

        // Deactivate category
        $deactRes = $this->request('POST', "/admin/categories/{$catId}/deactivate", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($deactRes['status'] === 200, 'Admin deactivates category (HTTP 200)');

        // Activate category
        $actRes = $this->request('POST', "/admin/categories/{$catId}/activate", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($actRes['status'] === 200, 'Admin reactivates category (HTTP 200)');

        // 9.4 Admin User Management
        $newUser = [
            'username' => 'test_author_' . time(),
            'display_name' => 'Test Author QA',
            'email' => 'qa_' . time() . '@infinityblog.local',
            'role' => 'author',
            'password' => 'SecurePass2026!',
        ];
        $userRes = $this->request('POST', '/admin/users', $newUser, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($userRes['status'] === 201, 'Admin provisions new author account (HTTP 201)');
        $newUserId = (int)($userRes['body']['data']['id'] ?? 0);

        // Deactivate user
        $deactUser = $this->request('POST', "/admin/users/{$newUserId}/deactivate", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($deactUser['status'] === 200, 'Admin deactivates user account (HTTP 200)');

        // 9.5 Audit Logs
        $auditRes = $this->request('GET', '/admin/audit-logs');
        $this->assert($auditRes['status'] === 200, 'Admin GET /admin/audit-logs returns HTTP 200');
        $this->assert(count($auditRes['body']['data'] ?? []) > 0, 'Audit logs contain recorded events');
    }

    private function testAISummarization(): void {
        echo "\n10. Testing AI Summarization Workflow & Content Protection:\n";
        $this->clearCookies();

        // 10.1 Short content rejection
        $shortRes = AISummarizer::generateSummary('Short Title', 'Too short');
        $this->assert(!$shortRes['success'] && ($shortRes['code'] ?? '') === 'CONTENT_TOO_SHORT', 'Content under 20 chars rejected by AISummarizer');

        // 10.2 Log in as author Sounak Sikdar
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 10.3 Request summary for Post #1
        $sumRes = $this->request('POST', '/author/posts/1/summary', null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($sumRes['status'] === 200, 'POST /author/posts/1/summary returns HTTP 200');
        $this->assert(!empty($sumRes['body']['data']['summary']), 'Generated summary contains plain-text summary');
        $this->assert(($sumRes['body']['data']['summary_status'] ?? '') === 'ready', 'Summary status is ready');

        // 10.4 Update post body and verify summary becomes stale
        $updateRes = $this->request('PATCH', '/author/posts/1', [
            'title' => 'Getting Started with Web Development in 2026',
            'content' => 'Modern web development continues to evolve at a blistering pace. From native browser primitives to performant server runtimes, building accessible and resilient applications requires understanding fundamental design patterns and separation of concerns. (Updated ' . time() . ')',
            'category_id' => 1,
            'status' => 'published',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($updateRes['status'] === 200, 'Post update succeeds');
        $this->assert(($updateRes['body']['data']['summary_status'] ?? '') === 'stale', 'Post update marks summary status as stale');

        // 10.5 Public view should NOT display stale summary as current
        $this->clearCookies();
        $pubRes = $this->request('GET', '/posts/1');
        $this->assert($pubRes['status'] === 200, 'Public GET /posts/1 returns post');
        $this->assert(empty($pubRes['body']['data']['summary']), 'Public view omits stale summary from output');
    }
}

// Run test suite against local server
$baseUrl = getenv('TEST_API_BASE') ?: 'http://localhost/infinity-blog/api/v1';
$suite = new ApiIntegrationTest($baseUrl);
$suite->runAll();
