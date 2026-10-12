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
use InfinityBlog\Http\ApiErrorLogger;
use InfinityBlog\Http\AuthController;
use InfinityBlog\Http\CategoryController;
use InfinityBlog\Http\JsonResponse;
use InfinityBlog\Http\PostController;
use InfinityBlog\Http\PublicPostController;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\RequestOriginPolicy;
use InfinityBlog\Http\RequestException;
use InfinityBlog\Http\SummaryController;
use InfinityBlog\Http\WebController;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Summaries\AiProviderConfig;
use InfinityBlog\Summaries\DatabaseSummaryRateLimiter;
use InfinityBlog\Summaries\OpenAiResponsesProvider;
use InfinityBlog\Summaries\SummaryService;
use InfinityBlog\Users\AdminUserRepository;

require dirname(__DIR__) . '/autoload.php';

$requestId = bin2hex(random_bytes(16));
$errorLogger = new ApiErrorLogger();

try {
    $config = AppConfig::fromEnvironment();
    $request = Request::fromGlobals($config->baseUrl);
    if (!RequestOriginPolicy::allows($request, $config->baseUrl)) {
        JsonResponse::send(403, ApiErrorResponder::build(
            'ORIGIN_FORBIDDEN',
            'The request origin is not permitted.',
            [],
            $requestId
        ));
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

    // Web Browser Views (HTML)
    if ($request->method === 'GET') {
        $webController = new WebController(
            new PostRepository($pdo),
            new CategoryRepository($pdo),
            $users,
            new AdminUserRepository($pdo),
            new AuditLogRepository($pdo),
            $guard,
            $sessions,
            $config
        );

        $wantsJson = str_contains((string) $request->header('Accept'), 'application/json');

        $webResult = match (true) {
            $request->path === '/' => $webController->home($request),
            (bool) preg_match('#^/article/([0-9]+)$#D', $request->path, $m) => $webController->article($request, (int) $m[1]),
            $request->path === '/login' => $webController->login($request),
            $request->path === '/register' => $webController->register($request),
            $request->path === '/categories' && !$wantsJson => $webController->publicCategories($request),
            $request->path === '/authors' => $webController->publicAuthors($request),
            (bool) preg_match('#^/author/([0-9]+)$#D', $request->path, $m) => $webController->authorProfile($request, (int) $m[1]),
            $request->path === '/about' => $webController->about($request),
            $request->path === '/dashboard' => $webController->dashboard($request),
            $request->path === '/posts/new' => $webController->newPost($request),
            (bool) preg_match('#^/posts/([0-9]+)/edit$#D', $request->path, $m) => $webController->editPost($request, (int) $m[1]),
            $request->path === '/posts/manage' => $webController->managePosts($request),
            $request->path === '/admin/categories-page' => $webController->adminCategories($request),
            $request->path === '/admin/users-page' => $webController->adminUsers($request),
            $request->path === '/admin/audit-logs-page' => $webController->adminAuditLogs($request),
            $request->path === '/logout' => $webController->logout($request),
            default => null,
        };

        if ($webResult !== null) {
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
    }

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
            $sessions,
            $users
        );
    }

    $result = $controller->handle($request);
    $isBrowserForm = !str_contains((string) $request->header('Accept'), 'application/json') && !$request->isJson();
    if ($isBrowserForm) {
        if ($result['status'] === 201 && ($request->path === '/register' || $request->path === '/auth/register')) {
            header('Location: /login?registered=1');
            return;
        }
        if ($result['status'] === 200 && ($request->path === '/login' || $request->path === '/auth/login')) {
            $userRole = $result['body']['data']['user']['role'] ?? 'reader';
            header('Location: ' . ($userRole === 'reader' ? '/' : '/dashboard'));
            return;
        }
    }
    JsonResponse::send($result['status'], $result['body']);
} catch (RequestException $exception) {
    JsonResponse::send($exception->httpStatus, ApiErrorResponder::build(
        $exception->errorCode,
        $exception->getMessage(),
        [],
        $requestId
    ));
} catch (Throwable $exception) {
    $errorLogger->log($exception, $requestId);
    JsonResponse::send(500, ApiErrorResponder::internalFailure($requestId));
}
