<?php
declare(strict_types=1);

/**
 * Infinity Blog - End-to-End API Integration & Acceptance Test Suite
 * Tests actual HTTP requests against http://localhost/infinity-blog/api/v1
 */

class ApiIntegrationTest {
    private string $baseUrl;
    private ?string $cookieFile = null;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct(string $baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'inf_cookie_');
    }

    public function __destruct() {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    private function request(string $method, string $path, ?array $body = null, array $headers = []): array {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

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

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("cURL failed: {$err}");
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
        echo " INFINITY BLOG - HTTP API INTEGRATION TEST SUITE\n";
        echo " Base URL: {$this->baseUrl}\n";
        echo "=======================================================\n\n";

        $this->testAuthentication();
        $this->testPublicBlog();
        $this->testAuthorPostOperations();
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

    private function testAuthentication(): void {
        echo "1. Testing Authentication & Session Management:\n";
        $this->clearCookies();

        // 1.1 Invalid login
        $res = $this->request('POST', '/auth/login', [
            'identifier' => 'non_existent_user',
            'password' => 'WrongPassword123',
        ]);
        $this->assert($res['status'] === 401, 'Invalid credentials return HTTP 401');
        $this->assert(($res['body']['error']['code'] ?? '') === 'INVALID_CREDENTIALS', 'Error code is INVALID_CREDENTIALS');

        // 1.2 Valid admin login
        $res = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $this->assert($res['status'] === 200, 'Valid admin login returns HTTP 200');
        $this->assert(($res['body']['data']['user']['role'] ?? '') === 'admin', 'Returned role is admin');
        $csrfToken = $res['body']['data']['csrf_token'] ?? '';
        $this->assert(!empty($csrfToken), 'Login response contains session CSRF token');

        // 1.3 Session check /auth/me with session cookie
        $res = $this->request('GET', '/auth/me');
        $this->assert($res['status'] === 200, 'GET /auth/me returns HTTP 200 with active session');
        $this->assert(($res['body']['data']['user']['username'] ?? '') === 'Sounak Sikdar', 'Current user matches authenticated identity');

        // 1.4 CSRF token endpoint
        $res = $this->request('GET', '/auth/csrf');
        $this->assert($res['status'] === 200, 'GET /auth/csrf returns HTTP 200');
        $this->assert(!empty($res['body']['data']['csrf_token']), 'Returns valid CSRF token');

        // 1.5 Logout
        $res = $this->request('POST', '/auth/logout', null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($res['status'] === 200, 'POST /auth/logout invalidates session and returns HTTP 200');

        // 1.6 Confirm unauthenticated after logout
        $res = $this->request('GET', '/auth/me');
        $this->assert($res['status'] === 401, 'GET /auth/me returns HTTP 401 after logout');
    }

    private function testPublicBlog(): void {
        echo "\n2. Testing Public Blog Endpoints (No Authentication Required):\n";
        $this->clearCookies();

        // 2.1 Public Categories
        $res = $this->request('GET', '/categories');
        $this->assert($res['status'] === 200, 'GET /categories returns HTTP 200');
        $this->assert(is_array($res['body']['data'] ?? null) && count($res['body']['data']) > 0, 'Categories list is populated');

        // 2.2 Public Published Posts
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

        // 2.3 Single Published Post
        $firstId = $posts[0]['id'] ?? 1;
        $res = $this->request('GET', "/posts/{$firstId}");
        $this->assert($res['status'] === 200, "GET /posts/{$firstId} returns published article details");
        $this->assert(!empty($res['body']['data']['content']), 'Article content is returned');
        $this->assert(!empty($res['body']['data']['author_name']), 'Author display name is included');

        // 2.4 Search Filter
        $res = $this->request('GET', '/posts?q=Web');
        $this->assert($res['status'] === 200, 'GET /posts?q=Web succeeds');
        $this->assert(count($res['body']['data'] ?? []) > 0, 'Search keyword returns matching posts');

        // 2.5 Category Filter
        $res = $this->request('GET', '/posts?category_id=1');
        $this->assert($res['status'] === 200, 'GET /posts?category_id=1 succeeds');
    }

    private function testAuthorPostOperations(): void {
        echo "\n3. Testing Author Post CRUD, Publishing & Deletion Lifecycle:\n";
        $this->clearCookies();

        // 3.1 Log in as author John Doe
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'john_doe',
            'password' => 'Author@Infinity2026!',
        ]);
        $this->assert($loginRes['status'] === 200, 'Author login succeeds');
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 3.2 Create draft post
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

        // 3.3 Verify draft is NOT public
        $publicCheck = $this->request('GET', "/posts/{$createdId}");
        $this->assert($publicCheck['status'] === 404, 'Draft post is NOT accessible via public GET /posts/{id}');

        // 3.4 Attempt to publish without category (Must fail validation)
        $pubFail = $this->request('POST', "/author/posts/{$createdId}/publish", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($pubFail['status'] === 422, 'Publishing without active category rejected with validation error (HTTP 422)');

        // 3.5 Update post with category
        $updateRes = $this->request('PATCH', "/author/posts/{$createdId}", [
            'title' => 'Automated Integration Testing in PHP & Vanilla JS (Updated)',
            'category_id' => 1,
            'status' => 'draft',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($updateRes['status'] === 200, 'Author updates post with active category');

        // 3.6 Publish post
        $pubSuccess = $this->request('POST', "/author/posts/{$createdId}/publish", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($pubSuccess['status'] === 200, 'Author publishes post with active category (HTTP 200)');

        // 3.7 Verify post is now public
        $publicCheck2 = $this->request('GET', "/posts/{$createdId}");
        $this->assert($publicCheck2['status'] === 200, 'Published post is now accessible via public GET /posts/{id}');

        // 3.8 Unpublish post
        $unpubRes = $this->request('POST', "/author/posts/{$createdId}/unpublish", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($unpubRes['status'] === 200, 'Author unpublishes post back to draft (HTTP 200)');

        // 3.9 Delete post
        $delRes = $this->request('DELETE', "/author/posts/{$createdId}", null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($delRes['status'] === 200, 'Author deletes own post (HTTP 200)');

        // 3.10 Confirm deleted
        $getDeleted = $this->request('GET', "/author/posts/{$createdId}");
        $this->assert($getDeleted['status'] === 404, 'Deleted post returns HTTP 404');
    }

    private function testAuthorizationAndIDOR(): void {
        echo "\n4. Testing Role Authorization, IDOR Protection & CSRF Enforcement:\n";
        $this->clearCookies();

        // 4.1 Unauthenticated requests to protected author routes
        $res = $this->request('GET', '/author/posts');
        $this->assert($res['status'] === 401, 'Unauthenticated GET /author/posts denied with HTTP 401');

        $res = $this->request('POST', '/author/posts', ['title' => 'hack', 'content' => 'hack']);
        $this->assert($res['status'] === 401, 'Unauthenticated POST /author/posts denied with HTTP 401');

        // 4.2 Login as author John Doe
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'john_doe',
            'password' => 'Author@Infinity2026!',
        ]);
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 4.3 Author attempts to create an admin category (Role escalation check)
        $catAttempt = $this->request('POST', '/admin/categories', [
            'name' => 'Illegal Category',
            'slug' => 'illegal-category',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($catAttempt['status'] === 403, 'Author attempting admin category creation denied with HTTP 403 FORBIDDEN');

        // 4.4 Author attempts to edit another author\'s post (IDOR check on Post #1 owned by Sounak)
        $idorAttempt = $this->request('PATCH', '/author/posts/1', [
            'title' => 'Tampered Title',
            'content' => 'Tampered Content',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($idorAttempt['status'] === 403, 'Author attempting to edit another user\'s post denied with HTTP 403 IDOR');

        // 4.5 Author attempts to delete another author\'s post
        $idorDel = $this->request('DELETE', '/author/posts/1', null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($idorDel['status'] === 403, 'Author attempting to delete another user\'s post denied with HTTP 403 IDOR');

        // 4.6 Missing CSRF token on mutation
        $noCsrf = $this->request('POST', '/author/posts', [
            'title' => 'Post without CSRF',
            'content' => 'Content without CSRF token',
        ]);
        $this->assert($noCsrf['status'] === 403, 'State-changing mutation without CSRF token denied with HTTP 403');
    }

    private function testAdminOperations(): void {
        echo "\n5. Testing Administrator Capabilities:\n";
        $this->clearCookies();

        // 5.1 Admin login
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $this->assert($loginRes['status'] === 200, 'Admin login succeeds');
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 5.2 Admin System Stats
        $statsRes = $this->request('GET', '/admin/stats');
        $this->assert($statsRes['status'] === 200, 'Admin GET /admin/stats returns HTTP 200');
        $this->assert(isset($statsRes['body']['data']['posts']['total']), 'Stats include total posts');
        $this->assert(isset($statsRes['body']['data']['users']['authors']), 'Stats include author accounts');

        // 5.3 Admin Categories Management
        $newCat = [
            'name' => 'Data Science & Analytics',
            'slug' => 'data-science-' . time(),
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

        // 5.4 Admin User Management
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

        // 5.5 Audit Logs
        $auditRes = $this->request('GET', '/admin/audit-logs');
        $this->assert($auditRes['status'] === 200, 'Admin GET /admin/audit-logs returns HTTP 200');
        $this->assert(count($auditRes['body']['data'] ?? []) > 0, 'Audit logs contain recorded events');
    }

    private function testAISummarization(): void {
        echo "\n6. Testing AI Summarization Workflow:\n";
        $this->clearCookies();

        // 6.1 Log in as author Sounak Sikdar
        $loginRes = $this->request('POST', '/auth/login', [
            'identifier' => 'Sounak Sikdar',
            'password' => 'Admin@Infinity2026!',
        ]);
        $csrfToken = $loginRes['body']['data']['csrf_token'] ?? '';

        // 6.2 Request summary for Post #1
        $sumRes = $this->request('POST', '/author/posts/1/summary', null, ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($sumRes['status'] === 200, 'POST /author/posts/1/summary returns HTTP 200');
        $this->assert(!empty($sumRes['body']['data']['summary']), 'Generated summary contains plain-text summary');
        $this->assert(($sumRes['body']['data']['summary_status'] ?? '') === 'ready', 'Summary status is ready');

        // 6.3 Update post body and verify summary becomes stale
        $updateRes = $this->request('PATCH', '/author/posts/1', [
            'title' => 'Getting Started with Web Development in 2026 (Revised Edition)',
            'content' => 'Updated content body with revisions to trigger stale summary status.',
            'category_id' => 1,
            'status' => 'published',
        ], ["X-CSRF-Token: {$csrfToken}"]);
        $this->assert($updateRes['status'] === 200, 'Post update succeeds');
        $this->assert(($updateRes['body']['data']['summary_status'] ?? '') === 'stale', 'Post update marks summary status as stale');

        // 6.4 Public view should NOT display stale summary as current
        $this->clearCookies();
        $pubRes = $this->request('GET', '/posts/1');
        $this->assert($pubRes['status'] === 200, 'Public GET /posts/1 returns post');
        $this->assert(empty($pubRes['body']['data']['summary']), 'Public view omits stale summary from output');
    }
}

// Run test against local server
$baseUrl = getenv('TEST_API_BASE') ?: 'http://localhost/infinity-blog/api/v1';
$suite = new ApiIntegrationTest($baseUrl);
$suite->runAll();
