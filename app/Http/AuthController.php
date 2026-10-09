<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\LoginResult;
use InfinityBlog\Auth\SessionManager;

final class AuthController
{
    private AuthenticationService $authentication;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(AuthenticationService $authentication, AuthGuard $guard, SessionManager $sessions)
    {
        $this->authentication = $authentication;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        $result = match ([$request->method, $request->path]) {
            ['POST', '/auth/login'] => $this->login($request),
            ['POST', '/auth/logout'] => $this->logout($request),
            ['GET', '/auth/me'] => $this->me(),
            ['GET', '/auth/csrf'] => $this->csrf(),
            default => null,
        };
        if ($result !== null) {
            return $result;
        }

        $knownPath = in_array($request->path, ['/auth/login', '/auth/logout', '/auth/me', '/auth/csrf'], true);
        return $this->error(
            $knownPath ? 405 : 404,
            $knownPath ? 'METHOD_NOT_ALLOWED' : 'NOT_FOUND',
            $knownPath ? 'The request method is not supported.' : 'The requested resource was not found.'
        );
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function csrf(): array
    {
        return [
            'status' => 200,
            'body' => ['data' => ['csrf_token' => $this->sessions->csrfToken()]],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function login(Request $request): array
    {
        if (!$this->sessions->validateCsrfToken($request->header('X-CSRF-Token'))) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        $input = $request->jsonObject();
        $identifier = $input['identifier'] ?? null;
        $password = $input['password'] ?? null;
        $fields = [];
        if (!is_string($identifier) || trim($identifier) === '' || strlen($identifier) > 190
            || preg_match('/[\x00-\x1F\x7F]/', $identifier) === 1) {
            $fields['identifier'] = 'Enter a valid login identifier.';
        }
        if (!is_string($password) || $password === '' || strlen($password) > 1024) {
            $fields['password'] = 'Enter a valid password.';
        }
        if ($fields !== []) {
            return [
                'status' => 422,
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'One or more fields need attention.', $fields),
            ];
        }

        $result = $this->authentication->login($identifier, $password, $request->sourceAddress);
        if ($result->status === LoginResult::LIMITED) {
            return $this->error(429, 'RATE_LIMITED', 'The login request could not be completed. Try again later.');
        }
        if ($result->status !== LoginResult::SUCCESS || $result->account === null) {
            return $this->error(401, 'AUTHENTICATION_FAILED', 'The login identifier or password is invalid.');
        }

        $this->sessions->establish($result->account);

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'user' => $result->account->toPublicArray(),
                    'csrf_token' => $this->sessions->csrfToken(),
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function me(): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }

        return ['status' => 200, 'body' => ['data' => ['user' => $account->toPublicArray()]]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function logout(Request $request): array
    {
        if ($this->guard->currentAccount() === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if (!$this->sessions->validateCsrfToken($request->header('X-CSRF-Token'))) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        $this->sessions->logout();

        return ['status' => 200, 'body' => ['data' => ['message' => 'Logged out.']]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ApiErrorResponder::build($code, $message)];
    }
}
