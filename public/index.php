<?php
declare(strict_types=1);

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Http\ApiErrorResponder;
use InfinityBlog\Http\AuthController;
use InfinityBlog\Http\CategoryController;
use InfinityBlog\Http\JsonResponse;
use InfinityBlog\Http\Request;
use InfinityBlog\Http\RequestException;

require dirname(__DIR__) . '/autoload.php';

try {
    $config = AppConfig::fromEnvironment();
    $sessions = new SessionManager($config);
    $sessions->start();
    $request = Request::fromGlobals($config->baseUrl);

    if ($request->path === '/auth/csrf' && $request->method === 'GET') {
        JsonResponse::send(200, ['data' => ['csrf_token' => $sessions->csrfToken()]]);
        return;
    }

    $pdo = ConnectionFactory::create($config->database);
    $users = new UserRepository($pdo);
    $guard = new AuthGuard($sessions, $users);
    if ($request->path === '/categories' || str_starts_with($request->path, '/admin/categories')) {
        $controller = new CategoryController(new CategoryRepository($pdo), $guard, $sessions);
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
