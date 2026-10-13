<?php
declare(strict_types=1);

$requestUriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    && is_string($requestUriPath)
    && strlen($requestUriPath) > 1
    && str_ends_with($requestUriPath, '/')) {
    $canonical = rtrim($requestUriPath, '/');
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    http_response_code(301);
    header('Location: ' . $canonical . $qs, true, 301);
    exit;
}

if (PHP_SAPI === 'cli-server') {
    if ($requestUriPath !== '/' && is_file(__DIR__ . $requestUriPath)) {
        return false;
    }
}

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Audio\TtsService;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Contact\ContactRepository;
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
use InfinityBlog\Http\LibraryController;
use InfinityBlog\Http\CommunityController;
use InfinityBlog\Http\SummaryController;
use InfinityBlog\Http\WebController;
use InfinityBlog\Library\LibraryRepository;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Ai\ArticleAssistantService;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Posts\StudioService;
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
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; media-src 'self' https: data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");
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
    $library = new LibraryRepository($pdo);
    $community = new CommunityRepository($pdo);
    $studio = new StudioService($pdo, $postsRepo);
    $contactRepo = new ContactRepository($pdo);

    $aiConfig = null;
    try {
        $aiConfig = AiProviderConfig::fromEnvironment();
    } catch (Throwable) {
    }
    $assistant = new ArticleAssistantService($pdo, $postsRepo, $aiConfig);

    $storageConfig = null;
    $storageClient = null;
    $mediaService = null;
    try {
        $storageConfig = SupabaseStorageConfig::fromEnvironment();
        if ($storageConfig !== null && $storageConfig->isConfigured()) {
            $storageClient = new \InfinityBlog\Storage\SupabaseStorageClient($storageConfig);
            $mediaService = new \InfinityBlog\Storage\MediaService($storageClient, $storageConfig);
        }
    } catch (Throwable) {
    }

    $ttsProvider = $aiConfig !== null ? new \InfinityBlog\Audio\OpenAiTtsProvider($aiConfig->apiKey) : null;
    $tts = new TtsService($postsRepo, $ttsProvider, $aiConfig, $storageConfig, $mediaService, $storageClient);
    $postReports = new \InfinityBlog\Moderation\PostReportRepository($pdo);

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
        $tts,
        $library,
        $community,
        $assistant,
        $studio,
        null,
        $contactRepo,
        $postReports
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
        if ($request->path === '/contact') {
            $webResult = $webController->submitContact($request);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if (preg_match('#^/admin/contact-messages/([0-9]+)/status$#D', $request->path, $m)) {
            $webResult = $webController->updateContactMessageStatus($request, (int) $m[1]);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if (preg_match('#^/api/article/([0-9]+)/assistant$#D', $request->path, $m)) {
            $webResult = $webController->assistant($request, (int) $m[1]);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if (preg_match('#^/admin/reports/([0-9]+)/status$#D', $request->path, $m)) {
            $webResult = $webController->adminUpdateReportStatus($request, (int) $m[1]);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if (preg_match('#^/admin/reports/posts/([0-9]+)/unpublish$#D', $request->path, $m)) {
            $webResult = $webController->adminUnpublishReportedPost($request, (int) $m[1]);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
        if (preg_match('#^/admin/reports/posts/([0-9]+)/restore$#D', $request->path, $m)) {
            $webResult = $webController->adminRestoreReportedPost($request, (int) $m[1]);
            http_response_code($webResult['status']);
            header('Content-Type: ' . $webResult['type']);
            echo $webResult['body'];
            return;
        }
    }

    // Web Browser Views (HTML)
    if ($request->method === 'GET' || $request->method === 'HEAD') {
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
            $request->path === '/terms' => $webController->terms($request),
            $request->path === '/categories' && !$wantsJson => $webController->publicCategories($request),
            $request->path === '/authors' => $webController->publicAuthors($request),
            (bool) preg_match('#^/(?:author|authors)/([^/]+)$#D', $request->path, $m) => $webController->authorProfile($request, $m[1]),
            $request->path === '/explore' => $webController->explore($request),
            $request->path === '/library' => $webController->library($request),
            $request->path === '/following' => $webController->followingFeed($request),
            $request->path === '/community' || $request->path === '/chat' => $webController->chat($request),
            (bool) preg_match('#^/(?:chat|community)/([a-zA-Z0-9_\-]+)$#D', $request->path, $m) => $webController->chat($request, $m[1]),
            $request->path === '/sitemap.xml' => $webController->sitemap($request),
            $request->path === '/robots.txt' => $webController->robots($request),
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
            $request->path === '/admin/contact-messages-page' => $webController->adminContactMessages($request),
            $request->path === '/admin/reported-posts-page' => $webController->adminReportedPosts($request),
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
            if ($request->method !== 'HEAD') {
                echo $webResult['body'];
            }
            return;
        }
    }

    if (str_starts_with($request->path, '/api/library')) {
        $controller = new LibraryController($library, $guard, $sessions, $postsRepo);
    } elseif (preg_match('#^/api/posts/[0-9]+/report$#D', $request->path) === 1 || str_starts_with($request->path, '/api/admin/reports')) {
        $controller = new \InfinityBlog\Http\ModerationController($postReports, $guard, $sessions);
    } elseif (str_starts_with($request->path, '/api/posts/') && str_ends_with($request->path, '/comments')) {
        $controller = new CommunityController($community, $guard, $sessions, $postsRepo);
    } elseif (str_starts_with($request->path, '/api/comments') || str_starts_with($request->path, '/api/chat') || (str_starts_with($request->path, '/api/users/') && (str_ends_with($request->path, '/block') || str_ends_with($request->path, '/unblock') || str_ends_with($request->path, '/report')))) {
        $controller = new CommunityController($community, $guard, $sessions, $postsRepo);
    } elseif ($request->path === '/categories' || str_starts_with($request->path, '/admin/categories')) {
        $controller = new CategoryController(new CategoryRepository($pdo), $guard, $sessions, $mediaService);
    } elseif ($request->path === '/admin/users' || str_starts_with($request->path, '/admin/users/')) {
        $controller = new AdminUserController(new AdminUserRepository($pdo), $guard, $sessions);
    } elseif (str_starts_with($request->path, '/api/messages') || str_starts_with($request->path, '/api/authors/') || str_starts_with($request->path, '/api/notifications')) {
        $controller = new SocialController($follows, $messages, $guard, $sessions, $users, new \InfinityBlog\Social\NotificationRepository($pdo));
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
        $summaryService = new SummaryService(new PostRepository($pdo), $limiter, $aiConfig, $provider);
        $controller = new PostController(new PostRepository($pdo), $guard, $sessions, $mediaService, $studio, $summaryService, $tts);
    } elseif (preg_match('#^/posts/[0-9]+/score$#D', $request->path) === 1) {
        $controller = new ScoreController(new ScoreRepository($pdo), $guard, $sessions);
    } elseif ($request->path === '/posts' || str_starts_with($request->path, '/posts/')) {
        $controller = new PublicPostController(new PostRepository($pdo));
    } else {
        $controller = new AuthController(
            new AuthenticationService($users, new DatabaseLoginAttemptLimiter($pdo, $config->applicationKey())),
            $guard,
            $sessions,
            $users,
            new \InfinityBlog\Auth\LoginChallengeService($pdo)
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
        if ($result['status'] !== 200 && ($request->path === '/login' || $request->path === '/auth/login')) {
            $errCode = $result['body']['error']['code'] ?? 'invalid';
            $errType = match($errCode) {
                'CSRF_INVALID' => 'csrf',
                'RATE_LIMITED' => 'rate_limited',
                default => 'invalid',
            };
            header('Location: /login?error=' . $errType);
            return;
        }
        if ($result['status'] !== 201 && ($request->path === '/register' || $request->path === '/auth/register')) {
            $errMsg = urlencode($result['body']['error']['message'] ?? 'Registration failed.');
            header('Location: /register?error=' . $errMsg);
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
