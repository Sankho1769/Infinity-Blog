<?php
declare(strict_types=1);

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Http\ApiErrorResponder;
use InfinityBlog\Http\AdminPostController;
use InfinityBlog\Http\AdminAuditLogController;
use InfinityBlog\Http\AdminUserController;
use InfinityBlog\Http\AuthController;
use InfinityBlog\Http\CategoryController;
use InfinityBlog\Http\JsonResponse;
use InfinityBlog\Http\PostController;
use InfinityBlog\Http\PublicPostController;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\RequestOriginPolicy;
use InfinityBlog\Http\RequestException;
use InfinityBlog\Http\SummaryController;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Summaries\AiProviderConfig;
use InfinityBlog\Summaries\DatabaseSummaryRateLimiter;
use InfinityBlog\Summaries\OpenAiResponsesProvider;
use InfinityBlog\Summaries\SummaryService;
use InfinityBlog\Users\AdminUserRepository;

require dirname(__DIR__) . '/autoload.php';

try {
    $config = AppConfig::fromEnvironment();
    $request = Request::fromGlobals($config->baseUrl);
    if (!RequestOriginPolicy::allows($request, $config->baseUrl)) {
        JsonResponse::send(403, ApiErrorResponder::build('ORIGIN_FORBIDDEN', 'The request origin is not permitted.'));
        return;
    }

    $sessions = new SessionManager($config);
    $sessions->start();

    if ($request->path === '/auth/csrf' && $request->method === 'GET') {
        JsonResponse::send(200, ['data' => ['csrf_token' => $sessions->csrfToken()]]);
        return;
    }

    $pdo = ConnectionFactory::create($config->database);
    $users = new UserRepository($pdo);
    $guard = new AuthGuard($sessions, $users);
    if ($request->path === '/categories' || str_starts_with($request->path, '/admin/categories')) {
        $controller = new CategoryController(new CategoryRepository($pdo), $guard, $sessions);
    } elseif ($request->path === '/admin/users' || str_starts_with($request->path, '/admin/users/')) {
        $controller = new AdminUserController(new AdminUserRepository($pdo), $guard, $sessions);
    } elseif (preg_match('#^/(author|admin)/posts/[0-9]+/summary$#D', $request->path) === 1) {
        try {
            $aiConfig = AiProviderConfig::fromEnvironment();
        } catch (Throwable) {
            $aiConfig = null;
        }
        $provider = $aiConfig === null ? null : new OpenAiResponsesProvider($aiConfig);
        $limiter = new DatabaseSummaryRateLimiter(
            $pdo,
            $config->applicationKey(),
            $aiConfig?->userRateLimit ?? 10
        );
        $controller = new SummaryController(
            new SummaryService(new PostRepository($pdo), $limiter, $aiConfig, $provider),
            $guard,
            $sessions
        );
    } elseif ($request->path === '/admin/posts' || str_starts_with($request->path, '/admin/posts/')) {
        $controller = new AdminPostController(new PostRepository($pdo), $guard, $sessions);
    } elseif ($request->path === '/admin/audit-logs') {
        $controller = new AdminAuditLogController(new AuditLogRepository($pdo), $guard);
    } elseif ($request->path === '/author/posts' || str_starts_with($request->path, '/author/posts/')) {
        $controller = new PostController(new PostRepository($pdo), $guard, $sessions);
    } elseif ($request->path === '/posts' || str_starts_with($request->path, '/posts/')) {
        $controller = new PublicPostController(new PostRepository($pdo));
    } else {
        $controller = new AuthController(
            new AuthenticationService($users, new DatabaseLoginAttemptLimiter($pdo, $config->applicationKey())),
            $guard,
            $sessions
        );
    }

    $result = $controller->handle($request);
    JsonResponse::send($result['status'], $result['body']);
} catch (RequestException $exception) {
    JsonResponse::send($exception->httpStatus, ApiErrorResponder::build($exception->errorCode, $exception->getMessage()));
} catch (Throwable) {
    JsonResponse::send(500, ApiErrorResponder::internalFailure());
}
