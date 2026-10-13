<?php
declare(strict_types=1);

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Audio\TtsService;
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
use InfinityBlog\Http\SocialController;
use InfinityBlog\Http\SummaryController;
use InfinityBlog\Http\WebController;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Http\ScoreController;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;
use InfinityBlog\Storage\SupabaseStorageConfig;
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

    if (!headers_sent()) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; media-src 'self' https: data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }

    $pdo = ConnectionFactory::create($config->database);
    $users = new UserRepository($pdo);
    $guard = new AuthGuard($sessions, $users);
    $follows = new FollowRepository($pdo);
    $messages = new MessageRepository($pdo);
    $postsRepo = new PostRepository($pdo);

    $storageConfig = null;
    try {
        $storageConfig = SupabaseStorageConfig::fromEnvironment();
    } catch (Throwable) {
    }

    $tts = new TtsService($postsRepo, null, $storageConfig, null);

    $webController = new WebController(
        $postsRepo,
        new CategoryRepository($pdo),
        $users,
        new AdminUserRepository($pdo),
        new AuditLogRepository($pdo),
        $guard,
        $sessions,
        $config,
        new ScoreRepository($pdo),
        null,
        $follows,
        $messages,
        $tts
    );

    // Browser POST form submissions for user profile & media uploads
    if ($request->method === 'POST') {
        if ($request->path === '/profile/edit') {
            $webResult = $webController->editProfile($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if ($request->path === '/profile/avatar') {
            $webResult = $webController->uploadAvatar($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if ($request->path === '/profile/banner') {
            $webResult = $webController->uploadBanner($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if ($request->path === '/posts/upload/cover') {
            $webResult = $webController->uploadCover($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if ($request->path === '/posts/upload/inline') {
            $webResult = $webController->uploadInline($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if ($request->path === '/posts/upload/video') {
            $webResult = $webController->uploadVideo($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if ($request->path === '/posts/preview') {
            $webResult = $webController->previewPost($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
    }

    // Web Browser Views (HTML)
    if ($request->method === 'GET') {
        $wantsJson = str_contains((string) $request->header('Accept'), 'application/json');

        $webResult = match (true) {
            $request->path === '/' => $webController->home($request),
            (bool) preg_match('#^/article/([0-9]+)/download/(pdf|epub)$#D', $request->path, $m) => $m[2] === 'pdf' ? $webController->downloadPdf($request, (int) $m[1]) : $webController->downloadEpub($request, (int) $m[1]),
            (bool) preg_match('#^/article/([0-9]+)/audio-speech$#D', $request->path, $m) => $webController->articleSpeechData($request, (int) $m[1]),
            (bool) preg_match('#^/article/([0-9]+)$#D', $request->path, $m) => $webController->article($request, (int) $m[1]),
            $request->path === '/video/signed-url' => $webController->signedVideoUrl($request),
            $request->path === '/login' => $webController->login($request),
            $request->path === '/register' => $webController->register($request),
            $request->path === '/profile' => $webController->profile($request),
            $request->path === '/profile/edit' => $webController->editProfile($request),
            $request->path === '/contact' => $webController->contact($request),
            $request->path === '/privacy' => $webController->privacy($request),
            $request->path === '/categories' && !$wantsJson => $webController->publicCategories($request),
            $request->path === '/authors' => $webController->publicAuthors($request),
            (bool) preg_match('#^/author/([0-9]+)$#D', $request->path, $m) => $webController->authorProfile($request, (int) $m[1]),
            $request->path === '/explore' => $webController->explore($request),
            $request->path === '/messages' => $webController->messages($request),
            (bool) preg_match('#^/messages/([0-9]+)$#D', $request->path, $m) => $webController->messages($request, (int) $m[1]),
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

        if ($webResult === null && !$wantsJson && !$request->isJson()
            && !str_starts_with($request->path, '/api/')
            && !str_starts_with($request->path, '/auth/')
            && !str_starts_with($request->path, '/admin/')
            && !str_starts_with($request->path, '/author/')
            && !str_starts_with($request->path, '/posts/')
            && $request->path !== '/categories'
            && $request->path !== '/posts') {
            $webResult = $webController->notFound('The page or resource you requested could not be found.');
        }

        if ($webResult !== null) {
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            if (!empty($webResult['headers']) && is_array($webResult['headers'])) {
                foreach ($webResult['headers'] as $headerName => $headerValue) {
                    header($headerName . ': ' . $headerValue);
                }
            }
            echo $webResult['body'];
            return;
        }
    }

    if ($request->path === '/categories' || str_starts_with($request->path, '/admin/categories')) {
        $controller = new CategoryController(new CategoryRepository($pdo), $guard, $sessions);
    } elseif ($request->path === '/admin/users' || str_starts_with($request->path, '/admin/users/')) {
        $controller = new AdminUserController(new AdminUserRepository($pdo), $guard, $sessions);
    } elseif (str_starts_with($request->path, '/api/messages') || str_starts_with($request->path, '/api/authors/')) {
        $controller = new SocialController($follows, $messages, $guard, $sessions, $users);
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
    } elseif (preg_match('#^/posts/[0-9]+/score$#D', $request->path) === 1) {
        $controller = new ScoreController(new ScoreRepository($pdo), $guard, $sessions);
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
            $redirectParam = $_GET['redirect'] ?? ($_POST['redirect'] ?? null);
            if (is_string($redirectParam) && str_starts_with($redirectParam, '/') && !str_starts_with($redirectParam, '//')) {
                header('Location: ' . $redirectParam);
                return;
            }
            header('Location: /dashboard');
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
