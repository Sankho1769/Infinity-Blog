<?php
declare(strict_types=1);

// Infinity Blog REST JSON API Router (/api/v1)
// Follows API_SPEC.md contract

require_once __DIR__ . '/../../backend/config/Database.php';
require_once __DIR__ . '/../../backend/src/Response.php';
require_once __DIR__ . '/../../backend/src/SessionManager.php';
require_once __DIR__ . '/../../backend/src/RateLimiter.php';
require_once __DIR__ . '/../../backend/src/AuditLogger.php';
require_once __DIR__ . '/../../backend/src/AISummarizer.php';

use InfinityBlog\Config\Database;
use InfinityBlog\Core\Response;
use InfinityBlog\Core\SessionManager;
use InfinityBlog\Core\RateLimiter;
use InfinityBlog\Core\AuditLogger;
use InfinityBlog\Services\AISummarizer;

// Set error handler to output clean JSON without exposing stack traces
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log("PHP Warning/Notice [{$severity}]: {$message} in {$file}:{$line}");
    return true;
});

set_exception_handler(function (\Throwable $e) {
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    Response::error('SERVER_ERROR', 'An unexpected server error occurred.', null, 500);
});

// Start session
SessionManager::start();

// Handle CORS if needed for local cross-origin development
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('ALLOWED_ORIGINS') ?: 'http://localhost,http://127.0.0.1,http://localhost:80,http://localhost:3000')));

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-CSRF-Token, X-Request-ID');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Resolve Route Path
// Supports mod_rewrite (/api/v1/posts), PATH_INFO (/api/v1/index.php/posts), or ?route=/posts
$route = $_GET['route'] ?? '';
if (!$route && !empty($_SERVER['PATH_INFO'])) {
    $route = $_SERVER['PATH_INFO'];
}
if (!$route) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $basePath = '/api/v1';
    $pos = strpos($uri, $basePath);
    if ($pos !== false) {
        $route = substr($uri, $pos + strlen($basePath));
    }
}
$route = '/' . ltrim($route, '/');
// Strip query strings or trailing slashes if present
if (($qPos = strpos($route, '?')) !== false) {
    $route = substr($route, 0, $qPos);
}
$route = rtrim($route, '/') ?: '/';

// Parse JSON Body
$rawInput = file_get_contents('php://input');
$inputData = [];
if (!empty($rawInput)) {
    $inputData = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
        Response::error('MALFORMED_JSON', 'Invalid JSON payload received.', null, 400);
    }
}
if (!is_array($inputData)) {
    $inputData = [];
}

$pdo = Database::getConnection();
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Route Dispatcher
try {
    // ==========================================
    // 1. AUTHENTICATION & CSRF ROUTES
    // ==========================================

    // POST /auth/login
    if ($method === 'POST' && $route === '/auth/login') {
        if (!RateLimiter::check('login', $clientIp, 8, 300)) {
            Response::error('RATE_LIMITED', 'Too many failed login attempts. Please wait 5 minutes.', null, 429);
        }

        $identifier = trim((string)($inputData['identifier'] ?? ''));
        $password = (string)($inputData['password'] ?? '');

        if ($identifier === '' || $password === '') {
            Response::error('VALIDATION_ERROR', 'Identifier and password are required.', [
                'identifier' => $identifier === '' ? 'Identifier is required.' : null,
                'password' => $password === '' ? 'Password is required.' : null,
            ], 422);
        }

        $stmt = $pdo->prepare("
            SELECT `id`, `username`, `display_name`, `email`, `password_hash`, `role`, `status`
            FROM `users`
            WHERE `username` = :id1 OR `email` = :id2
            LIMIT 1
        ");
        $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            AuditLogger::log('AUTH_LOGIN_FAILED', 'user', null, ['identifier' => $identifier]);
            Response::error('INVALID_CREDENTIALS', 'Invalid username or password.', null, 401);
        }

        if ($user['status'] !== 'active') {
            AuditLogger::log('AUTH_LOGIN_INACTIVE', 'user', (int)$user['id']);
            Response::error('ACCOUNT_INACTIVE', 'This account has been deactivated. Please contact an administrator.', null, 403);
        }

        SessionManager::loginUser($user);

        // Update last login
        $pdo->prepare("UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = :id")->execute([':id' => $user['id']]);

        AuditLogger::log('AUTH_LOGIN_SUCCESS', 'user', (int)$user['id']);

        Response::success([
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
            'csrf_token' => SessionManager::getCsrfToken(),
        ]);
    }

    // POST /auth/logout
    if ($method === 'POST' && $route === '/auth/logout') {
        SessionManager::requireCsrf();
        $user = SessionManager::getCurrentUser();
        if ($user) {
            AuditLogger::log('AUTH_LOGOUT', 'user', (int)$user['id']);
        }
        SessionManager::logout();
        Response::success(['message' => 'Logged out successfully.']);
    }

    // GET /auth/me
    if ($method === 'GET' && $route === '/auth/me') {
        $user = SessionManager::requireAuth();
        // Fetch fresh status from DB in case account was deactivated
        $stmt = $pdo->prepare("SELECT `id`, `username`, `display_name`, `email`, `role`, `status` FROM `users` WHERE `id` = :id");
        $stmt->execute([':id' => $user['id']]);
        $fresh = $stmt->fetch();

        if (!$fresh || $fresh['status'] !== 'active') {
            SessionManager::logout();
            Response::error('SESSION_EXPIRED', 'Your session has expired or account is inactive.', null, 401);
        }

        Response::success([
            'user' => [
                'id' => (int)$fresh['id'],
                'username' => $fresh['username'],
                'display_name' => $fresh['display_name'],
                'email' => $fresh['email'],
                'role' => $fresh['role'],
            ],
            'csrf_token' => SessionManager::getCsrfToken(),
        ]);
    }

    // GET /auth/csrf
    if ($method === 'GET' && $route === '/auth/csrf') {
        Response::success([
            'csrf_token' => SessionManager::getCsrfToken(),
        ]);
    }

    // ==========================================
    // 2. PUBLIC BLOG ROUTES
    // ==========================================

    // GET /categories (Public active categories)
    if ($method === 'GET' && $route === '/categories') {
        $stmt = $pdo->query("
            SELECT `id`, `name`, `slug`, `description`
            FROM `categories`
            WHERE `is_active` = 1
            ORDER BY `name` ASC
        ");
        Response::success($stmt->fetchAll());
    }

    // GET /posts (Public published post list)
    if ($method === 'GET' && $route === '/posts') {
        $q = trim((string)($_GET['q'] ?? ''));
        $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $conditions = ["p.`status` = 'published'"];
        $params = [];

        if ($q !== '') {
            $conditions[] = "(p.`title` LIKE :q1 OR p.`content` LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }

        if ($categoryId !== null) {
            $conditions[] = "p.`category_id` = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        $whereClause = implode(' AND ', $conditions);

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `posts` p WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch records
        $sql = "
            SELECT 
                p.`id`, p.`title`, p.`summary`, p.`summary_status`,
                p.`published_at`, p.`created_at`, p.`updated_at`,
                SUBSTRING(REGEXP_REPLACE(p.`content`, '<[^>]+>', ''), 1, 220) AS `excerpt`,
                u.`display_name` AS `author_name`,
                c.`id` AS `category_id`, c.`name` AS `category_name`, c.`slug` AS `category_slug`
            FROM `posts` p
            JOIN `users` u ON p.`author_id` = u.`id`
            LEFT JOIN `categories` c ON p.`category_id` = c.`id`
            WHERE {$whereClause}
            ORDER BY p.`published_at` DESC
            LIMIT :offset, :limit
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        // Format summary only if current
        foreach ($posts as &$post) {
            if ($post['summary_status'] !== 'ready') {
                $post['summary'] = null;
            }
        }

        Response::success($posts, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ]);
    }

    // GET /posts/{id} (Public single published article)
    if ($method === 'GET' && preg_match('#^/posts/(\d+)$#', $route, $matches)) {
        $postId = (int)$matches[1];

        $stmt = $pdo->prepare("
            SELECT 
                p.`id`, p.`title`, p.`content`, p.`summary`, p.`summary_status`,
                p.`published_at`, p.`created_at`, p.`updated_at`,
                u.`id` AS `author_id`, u.`display_name` AS `author_name`,
                c.`id` AS `category_id`, c.`name` AS `category_name`, c.`slug` AS `category_slug`
            FROM `posts` p
            JOIN `users` u ON p.`author_id` = u.`id`
            LEFT JOIN `categories` c ON p.`category_id` = c.`id`
            WHERE p.`id` = :id AND p.`status` = 'published'
            LIMIT 1
        ");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('NOT_FOUND', 'Article not found or not currently published.', null, 404);
        }

        if ($post['summary_status'] !== 'ready') {
            $post['summary'] = null;
        }

        Response::success($post);
    }

    // ==========================================
    // 3. AUTHOR POST ROUTES
    // ==========================================

    // GET /author/posts (Author posts listing)
    if ($method === 'GET' && $route === '/author/posts') {
        $user = SessionManager::requireRole('author', 'admin');
        
        $status = trim((string)($_GET['status'] ?? ''));
        $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $q = trim((string)($_GET['q'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        // If author, show only own posts; if admin, show all posts unless specific author requested
        if ($user['role'] === 'author') {
            $conditions[] = "p.`author_id` = :author_id";
            $params[':author_id'] = $user['id'];
        }

        if ($status !== '' && in_array($status, ['draft', 'published'], true)) {
            $conditions[] = "p.`status` = :status";
            $params[':status'] = $status;
        }

        if ($categoryId !== null) {
            $conditions[] = "p.`category_id` = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        if ($q !== '') {
            $conditions[] = "p.`title` LIKE :q";
            $params[':q'] = "%{$q}%";
        }

        $whereClause = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        // Counts summary for author dashboard
        $countsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS `total`,
                SUM(CASE WHEN `status` = 'published' THEN 1 ELSE 0 END) AS `published`,
                SUM(CASE WHEN `status` = 'draft' THEN 1 ELSE 0 END) AS `drafts`
            FROM `posts`
            WHERE `author_id` = :uid
        ");
        $countsStmt->execute([':uid' => $user['id']]);
        $stats = $countsStmt->fetch();

        // Total matching filter
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `posts` p {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT 
                p.`id`, p.`author_id`, p.`title`, p.`summary`, p.`summary_status`,
                p.`status`, p.`published_at`, p.`created_at`, p.`updated_at`,
                u.`display_name` AS `author_name`,
                c.`id` AS `category_id`, c.`name` AS `category_name`, c.`slug` AS `category_slug`
            FROM `posts` p
            JOIN `users` u ON p.`author_id` = u.`id`
            LEFT JOIN `categories` c ON p.`category_id` = c.`id`
            {$whereClause}
            ORDER BY p.`created_at` DESC
            LIMIT :offset, :limit
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        Response::success($posts, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
            'stats' => [
                'total_posts' => (int)($stats['total'] ?? 0),
                'published_posts' => (int)($stats['published'] ?? 0),
                'draft_posts' => (int)($stats['drafts'] ?? 0),
            ]
        ]);
    }

    // POST /author/posts (Create post)
    if ($method === 'POST' && $route === '/author/posts') {
        $user = SessionManager::requireRole('author', 'admin');
        SessionManager::requireCsrf();

        $title = trim((string)($inputData['title'] ?? ''));
        $content = trim((string)($inputData['content'] ?? ''));
        $status = strtolower(trim((string)($inputData['status'] ?? 'draft')));
        $categoryId = !empty($inputData['category_id']) ? (int)$inputData['category_id'] : null;

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title cannot exceed 255 characters.';
        }

        if ($content === '') {
            $errors['content'] = 'Article content is required.';
        }

        if (!in_array($status, ['draft', 'published'], true)) {
            $errors['status'] = 'Status must be draft or published.';
        }

        // If published, category is required and must be active
        if ($status === 'published') {
            if (!$categoryId) {
                $errors['category_id'] = 'An active category is required to publish an article.';
            } else {
                $catStmt = $pdo->prepare("SELECT `id`, `is_active` FROM `categories` WHERE `id` = :id");
                $catStmt->execute([':id' => $categoryId]);
                $cat = $catStmt->fetch();
                if (!$cat || !(int)$cat['is_active']) {
                    $errors['category_id'] = 'The selected category is inactive or does not exist.';
                }
            }
        } elseif ($categoryId !== null) {
            // Draft category check
            $catStmt = $pdo->prepare("SELECT `id` FROM `categories` WHERE `id` = :id");
            $catStmt->execute([':id' => $categoryId]);
            if (!$catStmt->fetch()) {
                $errors['category_id'] = 'Selected category does not exist.';
            }
        }

        if (!empty($errors)) {
            Response::error('VALIDATION_ERROR', 'One or more fields need attention.', $errors, 422);
        }

        $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            INSERT INTO `posts` (`author_id`, `category_id`, `title`, `content`, `status`, `published_at`)
            VALUES (:author_id, :cat_id, :title, :content, :status, :pub_at)
        ");
        $stmt->execute([
            ':author_id' => $user['id'],
            ':cat_id' => $categoryId,
            ':title' => $title,
            ':content' => $content,
            ':status' => $status,
            ':pub_at' => $publishedAt,
        ]);

        $newId = (int)$pdo->lastInsertId();
        AuditLogger::log('POST_CREATE', 'post', $newId, ['status' => $status, 'title' => $title]);

        Response::success([
            'id' => $newId,
            'title' => $title,
            'status' => $status,
            'category_id' => $categoryId,
            'published_at' => $publishedAt,
        ], null, 201);
    }

    // GET /author/posts/{id} (Author get post for editing)
    if ($method === 'GET' && preg_match('#^/author/posts/(\d+)$#', $route, $matches)) {
        $user = SessionManager::requireRole('author', 'admin');
        $postId = (int)$matches[1];

        $stmt = $pdo->prepare("
            SELECT 
                p.`id`, p.`author_id`, p.`title`, p.`content`, p.`summary`, p.`summary_status`,
                p.`status`, p.`published_at`, p.`created_at`, p.`updated_at`,
                c.`id` AS `category_id`, c.`name` AS `category_name`, c.`slug` AS `category_slug`, c.`is_active` AS `category_is_active`
            FROM `posts` p
            LEFT JOIN `categories` c ON p.`category_id` = c.`id`
            WHERE p.`id` = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('NOT_FOUND', 'Article not found.', null, 404);
        }

        // Ownership check: authors can only view their own; admins can view any
        if ($user['role'] === 'author' && (int)$post['author_id'] !== $user['id']) {
            Response::error('FORBIDDEN', 'You are not authorized to view or edit this article.', null, 403);
        }

        Response::success($post);
    }

    // PATCH /author/posts/{id} (Update post)
    if ($method === 'PATCH' && preg_match('#^/author/posts/(\d+)$#', $route, $matches)) {
        $user = SessionManager::requireRole('author', 'admin');
        SessionManager::requireCsrf();
        $postId = (int)$matches[1];

        $stmt = $pdo->prepare("SELECT `id`, `author_id`, `title`, `content`, `status`, `summary_status` FROM `posts` WHERE `id` = :id");
        $stmt->execute([':id' => $postId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            Response::error('NOT_FOUND', 'Article not found.', null, 404);
        }

        if ($user['role'] === 'author' && (int)$existing['author_id'] !== $user['id']) {
            Response::error('FORBIDDEN', 'You are not authorized to edit this article.', null, 403);
        }

        $title = isset($inputData['title']) ? trim((string)$inputData['title']) : $existing['title'];
        $content = isset($inputData['content']) ? trim((string)$inputData['content']) : $existing['content'];
        $status = isset($inputData['status']) ? strtolower(trim((string)$inputData['status'])) : $existing['status'];
        $categoryId = array_key_exists('category_id', $inputData) ? ($inputData['category_id'] ? (int)$inputData['category_id'] : null) : null;

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title cannot be empty.';
        }
        if ($content === '') {
            $errors['content'] = 'Content cannot be empty.';
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            $errors['status'] = 'Invalid status value.';
        }

        if ($status === 'published') {
            if (!$categoryId) {
                $errors['category_id'] = 'An active category is required to publish.';
            } else {
                $catStmt = $pdo->prepare("SELECT `is_active` FROM `categories` WHERE `id` = :id");
                $catStmt->execute([':id' => $categoryId]);
                $c = $catStmt->fetch();
                if (!$c || !(int)$c['is_active']) {
                    $errors['category_id'] = 'The selected category is inactive or does not exist.';
                }
            }
        }

        if (!empty($errors)) {
            Response::error('VALIDATION_ERROR', 'Validation failed.', $errors, 422);
        }

        // If title or content changed, mark existing summary stale
        $summaryStatus = $existing['summary_status'];
        if (($title !== $existing['title'] || $content !== $existing['content']) && $summaryStatus === 'ready') {
            $summaryStatus = 'stale';
        }

        $publishedAtSql = '';
        $params = [
            ':title' => $title,
            ':content' => $content,
            ':status' => $status,
            ':cat_id' => $categoryId,
            ':sum_stat' => $summaryStatus,
            ':id' => $postId,
        ];

        if ($status === 'published' && $existing['status'] !== 'published') {
            $publishedAtSql = ', `published_at` = NOW()';
        }

        $updateSql = "
            UPDATE `posts`
            SET `title` = :title,
                `content` = :content,
                `status` = :status,
                `category_id` = :cat_id,
                `summary_status` = :sum_stat
                {$publishedAtSql}
            WHERE `id` = :id
        ";

        $pdo->prepare($updateSql)->execute($params);
        AuditLogger::log('POST_UPDATE', 'post', $postId, ['status' => $status]);

        Response::success([
            'id' => $postId,
            'title' => $title,
            'status' => $status,
            'summary_status' => $summaryStatus,
            'message' => 'Post updated successfully.',
        ]);
    }

    // POST /author/posts/{id}/publish
    if ($method === 'POST' && preg_match('#^/author/posts/(\d+)/publish$#', $route, $matches)) {
        $user = SessionManager::requireRole('author', 'admin');
        SessionManager::requireCsrf();
        $postId = (int)$matches[1];

        $stmt = $pdo->prepare("SELECT `id`, `author_id`, `category_id`, `title`, `content` FROM `posts` WHERE `id` = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('NOT_FOUND', 'Article not found.', null, 404);
        }

        if ($user['role'] === 'author' && (int)$post['author_id'] !== $user['id']) {
            Response::error('FORBIDDEN', 'You are not authorized to publish this article.', null, 403);
        }

        if (!$post['category_id']) {
            Response::error('VALIDATION_ERROR', 'An active category is required to publish.', ['category_id' => 'Please select an active category.'], 422);
        }

        $catStmt = $pdo->prepare("SELECT `is_active` FROM `categories` WHERE `id` = :id");
        $catStmt->execute([':id' => $post['category_id']]);
        $cat = $catStmt->fetch();
        if (!$cat || !(int)$cat['is_active']) {
            Response::error('VALIDATION_ERROR', 'The assigned category is inactive. Please choose an active category.', ['category_id' => 'Category is inactive.'], 422);
        }

        $pdo->prepare("UPDATE `posts` SET `status` = 'published', `published_at` = NOW() WHERE `id` = :id")->execute([':id' => $postId]);
        AuditLogger::log('POST_PUBLISH', 'post', $postId);

        Response::success(['id' => $postId, 'status' => 'published', 'message' => 'Article published successfully.']);
    }

    // POST /author/posts/{id}/unpublish
    if ($method === 'POST' && preg_match('#^/author/posts/(\d+)/unpublish$#', $route, $matches)) {
        $user = SessionManager::requireRole('author', 'admin');
        SessionManager::requireCsrf();
        $postId = (int)$matches[1];

        $stmt = $pdo->prepare("SELECT `id`, `author_id` FROM `posts` WHERE `id` = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('NOT_FOUND', 'Article not found.', null, 404);
        }

        if ($user['role'] === 'author' && (int)$post['author_id'] !== $user['id']) {
            Response::error('FORBIDDEN', 'You are not authorized to unpublish this article.', null, 403);
        }

        $pdo->prepare("UPDATE `posts` SET `status` = 'draft' WHERE `id` = :id")->execute([':id' => $postId]);
        AuditLogger::log('POST_UNPUBLISH', 'post', $postId);

        Response::success(['id' => $postId, 'status' => 'draft', 'message' => 'Article moved to drafts.']);
    }

    // DELETE /author/posts/{id}
    if ($method === 'DELETE' && preg_match('#^/author/posts/(\d+)$#', $route, $matches)) {
        $user = SessionManager::requireRole('author', 'admin');
        SessionManager::requireCsrf();
        $postId = (int)$matches[1];

        $stmt = $pdo->prepare("SELECT `id`, `author_id`, `title` FROM `posts` WHERE `id` = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('NOT_FOUND', 'Article not found.', null, 404);
        }

        if ($user['role'] === 'author' && (int)$post['author_id'] !== $user['id']) {
            Response::error('FORBIDDEN', 'You are not authorized to delete this article.', null, 403);
        }

        $pdo->prepare("DELETE FROM `posts` WHERE `id` = :id")->execute([':id' => $postId]);
        AuditLogger::log('POST_DELETE', 'post', $postId, ['title' => $post['title']]);

        Response::success(['id' => $postId, 'message' => 'Article deleted successfully.']);
    }

    // POST /author/posts/{id}/summary (Generate/Regenerate AI summary)
    if ($method === 'POST' && preg_match('#^/author/posts/(\d+)/summary$#', $route, $matches)) {
        $user = SessionManager::requireRole('author', 'admin');
        SessionManager::requireCsrf();
        $postId = (int)$matches[1];

        if (!RateLimiter::check('ai_summary', "user:{$user['id']}", 6, 60)) {
            Response::error('RATE_LIMITED', 'AI summary request limit exceeded. Please wait a minute before retrying.', null, 429);
        }

        $stmt = $pdo->prepare("SELECT `id`, `author_id`, `title`, `content` FROM `posts` WHERE `id` = :id");
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();

        if (!$post) {
            Response::error('NOT_FOUND', 'Article not found.', null, 404);
        }

        if ($user['role'] === 'author' && (int)$post['author_id'] !== $user['id']) {
            Response::error('FORBIDDEN', 'You are not authorized to summarize this article.', null, 403);
        }

        // Canonical content from DB
        $res = AISummarizer::generateSummary($post['title'], $post['content']);

        if (!$res['success']) {
            $pdo->prepare("UPDATE `posts` SET `summary_status` = 'failed' WHERE `id` = :id")->execute([':id' => $postId]);
            Response::error($res['code'] ?? 'AI_ERROR', $res['error'] ?? 'Summary generation failed.', null, 502);
        }

        $summaryText = $res['summary'];
        $hash = hash('sha256', $post['title'] . $post['content']);

        $update = $pdo->prepare("
            UPDATE `posts`
            SET `summary` = :summary,
                `summary_status` = 'ready',
                `summary_source_hash` = :hash,
                `summary_generated_at` = NOW()
            WHERE `id` = :id
        ");
        $update->execute([
            ':summary' => $summaryText,
            ':hash' => $hash,
            ':id' => $postId,
        ]);

        AuditLogger::log('POST_SUMMARY_GENERATED', 'post', $postId);

        Response::success([
            'id' => $postId,
            'summary' => $summaryText,
            'summary_status' => 'ready',
            'message' => 'Article summary generated successfully.',
        ]);
    }

    // ==========================================
    // 4. ADMIN MANAGEMENT ROUTES (Admin role required)
    // ==========================================

    // Admin Posts
    if ($method === 'GET' && $route === '/admin/posts') {
        SessionManager::requireRole('admin');

        $q = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if ($status !== '' && in_array($status, ['draft', 'published'], true)) {
            $conditions[] = "p.`status` = :status";
            $params[':status'] = $status;
        }
        if ($categoryId !== null) {
            $conditions[] = "p.`category_id` = :cat_id";
            $params[':cat_id'] = $categoryId;
        }
        if ($q !== '') {
            $conditions[] = "(p.`title` LIKE :q1 OR u.`display_name` LIKE :q2)";
            $params[':q1'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }

        $whereClause = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `posts` p JOIN `users` u ON p.`author_id` = u.`id` {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT 
                p.`id`, p.`author_id`, p.`title`, p.`status`, p.`summary_status`,
                p.`published_at`, p.`created_at`, p.`updated_at`,
                u.`display_name` AS `author_name`, u.`username` AS `author_username`,
                c.`id` AS `category_id`, c.`name` AS `category_name`, c.`slug` AS `category_slug`
            FROM `posts` p
            JOIN `users` u ON p.`author_id` = u.`id`
            LEFT JOIN `categories` c ON p.`category_id` = c.`id`
            {$whereClause}
            ORDER BY p.`created_at` DESC
            LIMIT :offset, :limit
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        Response::success($stmt->fetchAll(), [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ]);
    }

    // Admin Categories
    if ($method === 'GET' && $route === '/admin/categories') {
        SessionManager::requireRole('admin');

        $stmt = $pdo->query("
            SELECT 
                c.`id`, c.`name`, c.`slug`, c.`description`, c.`is_active`, c.`created_at`, c.`updated_at`,
                u.`display_name` AS `created_by_name`,
                COUNT(p.`id`) AS `post_count`
            FROM `categories` c
            LEFT JOIN `users` u ON c.`created_by` = u.`id`
            LEFT JOIN `posts` p ON c.`id` = p.`category_id`
            GROUP BY c.`id`
            ORDER BY c.`name` ASC
        ");

        Response::success($stmt->fetchAll());
    }

    if ($method === 'POST' && $route === '/admin/categories') {
        $admin = SessionManager::requireRole('admin');
        SessionManager::requireCsrf();

        $name = trim((string)($inputData['name'] ?? ''));
        $slug = trim((string)($inputData['slug'] ?? ''));
        $description = trim((string)($inputData['description'] ?? ''));

        if ($slug === '' && $name !== '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Category name is required.';
        }
        if ($slug === '') {
            $errors['slug'] = 'Category slug is required.';
        }

        // Check uniqueness
        if ($slug !== '') {
            $check = $pdo->prepare("SELECT `id` FROM `categories` WHERE `slug` = :slug OR `name` = :name");
            $check->execute([':slug' => $slug, ':name' => $name]);
            if ($check->fetch()) {
                $errors['slug'] = 'A category with this name or slug already exists.';
            }
        }

        if (!empty($errors)) {
            Response::error('VALIDATION_ERROR', 'Validation failed.', $errors, 422);
        }

        $insert = $pdo->prepare("
            INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`, `created_by`)
            VALUES (:name, :slug, :description, 1, :created_by)
        ");
        $insert->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description ?: null,
            ':created_by' => $admin['id'],
        ]);

        $newId = (int)$pdo->lastInsertId();
        AuditLogger::log('CATEGORY_CREATE', 'category', $newId, ['name' => $name, 'slug' => $slug]);

        Response::success([
            'id' => $newId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => 1,
            'message' => 'Category created successfully.',
        ], null, 201);
    }

    if ($method === 'PATCH' && preg_match('#^/admin/categories/(\d+)$#', $route, $matches)) {
        SessionManager::requireRole('admin');
        SessionManager::requireCsrf();
        $catId = (int)$matches[1];

        $stmt = $pdo->prepare("SELECT `id`, `name`, `slug`, `description`, `is_active` FROM `categories` WHERE `id` = :id");
        $stmt->execute([':id' => $catId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            Response::error('NOT_FOUND', 'Category not found.', null, 404);
        }

        $name = isset($inputData['name']) ? trim((string)$inputData['name']) : $existing['name'];
        $slug = isset($inputData['slug']) ? trim((string)$inputData['slug']) : $existing['slug'];
        $description = array_key_exists('description', $inputData) ? trim((string)$inputData['description']) : $existing['description'];

        if ($slug !== $existing['slug'] || $name !== $existing['name']) {
            $check = $pdo->prepare("SELECT `id` FROM `categories` WHERE (`slug` = :slug OR `name` = :name) AND `id` != :id");
            $check->execute([':slug' => $slug, ':name' => $name, ':id' => $catId]);
            if ($check->fetch()) {
                Response::error('CONFLICT', 'A category with this name or slug already exists.', ['slug' => 'Slug or name in use.'], 409);
            }
        }

        $pdo->prepare("
            UPDATE `categories`
            SET `name` = :name, `slug` = :slug, `description` = :desc
            WHERE `id` = :id
        ")->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $description ?: null,
            ':id' => $catId,
        ]);

        AuditLogger::log('CATEGORY_UPDATE', 'category', $catId);
        Response::success(['id' => $catId, 'name' => $name, 'slug' => $slug, 'message' => 'Category updated.']);
    }

    if ($method === 'POST' && preg_match('#^/admin/categories/(\d+)/activate$#', $route, $matches)) {
        SessionManager::requireRole('admin');
        SessionManager::requireCsrf();
        $catId = (int)$matches[1];

        $pdo->prepare("UPDATE `categories` SET `is_active` = 1 WHERE `id` = :id")->execute([':id' => $catId]);
        AuditLogger::log('CATEGORY_ACTIVATE', 'category', $catId);
        Response::success(['id' => $catId, 'is_active' => 1, 'message' => 'Category activated.']);
    }

    if ($method === 'POST' && preg_match('#^/admin/categories/(\d+)/deactivate$#', $route, $matches)) {
        SessionManager::requireRole('admin');
        SessionManager::requireCsrf();
        $catId = (int)$matches[1];

        $pdo->prepare("UPDATE `categories` SET `is_active` = 0 WHERE `id` = :id")->execute([':id' => $catId]);
        AuditLogger::log('CATEGORY_DEACTIVATE', 'category', $catId);
        Response::success(['id' => $catId, 'is_active' => 0, 'message' => 'Category deactivated.']);
    }

    // Admin Users
    if ($method === 'GET' && $route === '/admin/users') {
        SessionManager::requireRole('admin');

        $stmt = $pdo->query("
            SELECT `id`, `username`, `display_name`, `email`, `role`, `status`, `created_at`, `last_login_at`
            FROM `users`
            ORDER BY `created_at` DESC
        ");
        Response::success($stmt->fetchAll());
    }

    if ($method === 'POST' && $route === '/admin/users') {
        SessionManager::requireRole('admin');
        SessionManager::requireCsrf();

        $username = trim((string)($inputData['username'] ?? ''));
        $displayName = trim((string)($inputData['display_name'] ?? ''));
        $email = trim((string)($inputData['email'] ?? ''));
        $role = strtolower(trim((string)($inputData['role'] ?? 'author')));
        $password = (string)($inputData['password'] ?? '');

        $errors = [];
        if ($username === '') $errors['username'] = 'Username is required.';
        if ($displayName === '') $errors['display_name'] = 'Display name is required.';
        if (mb_strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
        if (!in_array($role, ['admin', 'author'], true)) $errors['role'] = 'Role must be admin or author.';

        if ($username !== '') {
            $check = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = :u OR (`email` = :e AND `email` != '')");
            $check->execute([':u' => $username, ':e' => $email]);
            if ($check->fetch()) {
                $errors['username'] = 'Username or email is already taken.';
            }
        }

        if (!empty($errors)) {
            Response::error('VALIDATION_ERROR', 'Validation failed.', $errors, 422);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare("
            INSERT INTO `users` (`username`, `display_name`, `email`, `password_hash`, `role`, `status`)
            VALUES (:u, :dn, :em, :hash, :role, 'active')
        ");
        $insert->execute([
            ':u' => $username,
            ':dn' => $displayName,
            ':em' => $email ?: null,
            ':hash' => $hash,
            ':role' => $role,
        ]);

        $newId = (int)$pdo->lastInsertId();
        AuditLogger::log('USER_CREATE', 'user', $newId, ['username' => $username, 'role' => $role]);

        Response::success([
            'id' => $newId,
            'username' => $username,
            'display_name' => $displayName,
            'role' => $role,
            'status' => 'active',
            'message' => 'User created successfully.',
        ], null, 201);
    }

    if ($method === 'POST' && preg_match('#^/admin/users/(\d+)/deactivate$#', $route, $matches)) {
        $admin = SessionManager::requireRole('admin');
        SessionManager::requireCsrf();
        $targetId = (int)$matches[1];

        if ($targetId === (int)$admin['id']) {
            Response::error('FORBIDDEN', 'You cannot deactivate your own administrative account.', null, 403);
        }

        $pdo->prepare("UPDATE `users` SET `status` = 'inactive' WHERE `id` = :id")->execute([':id' => $targetId]);
        AuditLogger::log('USER_DEACTIVATE', 'user', $targetId);
        Response::success(['id' => $targetId, 'status' => 'inactive', 'message' => 'Account deactivated.']);
    }

    if ($method === 'POST' && preg_match('#^/admin/users/(\d+)/activate$#', $route, $matches)) {
        SessionManager::requireRole('admin');
        SessionManager::requireCsrf();
        $targetId = (int)$matches[1];

        $pdo->prepare("UPDATE `users` SET `status` = 'active' WHERE `id` = :id")->execute([':id' => $targetId]);
        AuditLogger::log('USER_ACTIVATE', 'user', $targetId);
        Response::success(['id' => $targetId, 'status' => 'active', 'message' => 'Account activated.']);
    }

    // Admin Audit Logs
    if ($method === 'GET' && $route === '/admin/audit-logs') {
        SessionManager::requireRole('admin');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $total = (int)$pdo->query("SELECT COUNT(*) FROM `audit_logs`")->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT 
                a.`id`, a.`action`, a.`entity_type`, a.`entity_id`, a.`metadata`, a.`created_at`,
                u.`display_name` AS `actor_name`, u.`username` AS `actor_username`
            FROM `audit_logs` a
            LEFT JOIN `users` u ON a.`actor_user_id` = u.`id`
            ORDER BY a.`created_at` DESC
            LIMIT :offset, :limit
        ");
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            if ($log['metadata']) {
                $log['metadata'] = json_decode($log['metadata'], true);
            }
        }

        Response::success($logs, [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ]);
    }

    // Admin Dashboard Summary
    if ($method === 'GET' && $route === '/admin/stats') {
        SessionManager::requireRole('admin');

        $postStats = $pdo->query("
            SELECT 
                COUNT(*) AS `total_posts`,
                SUM(CASE WHEN `status` = 'published' THEN 1 ELSE 0 END) AS `published_posts`,
                SUM(CASE WHEN `status` = 'draft' THEN 1 ELSE 0 END) AS `draft_posts`
            FROM `posts`
        ")->fetch();

        $userStats = $pdo->query("
            SELECT 
                COUNT(*) AS `total_users`,
                SUM(CASE WHEN `role` = 'author' THEN 1 ELSE 0 END) AS `authors`,
                SUM(CASE WHEN `role` = 'admin' THEN 1 ELSE 0 END) AS `admins`
            FROM `users`
        ")->fetch();

        $categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();

        Response::success([
            'posts' => [
                'total' => (int)($postStats['total_posts'] ?? 0),
                'published' => (int)($postStats['published_posts'] ?? 0),
                'drafts' => (int)($postStats['draft_posts'] ?? 0),
            ],
            'users' => [
                'total' => (int)($userStats['total_users'] ?? 0),
                'authors' => (int)($userStats['authors'] ?? 0),
                'admins' => (int)($userStats['admins'] ?? 0),
            ],
            'categories' => [
                'total' => $categoryCount,
            ]
        ]);
    }

    // Unmatched Route
    Response::error('ROUTE_NOT_FOUND', "The requested endpoint '{$method} {$route}' is not implemented.", null, 404);

} catch (\Throwable $e) {
    error_log("Router Exception: " . $e->getMessage());
    Response::error('INTERNAL_SERVER_ERROR', 'A server error occurred while processing the request.', null, 500);
}
