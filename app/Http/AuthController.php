<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\LoginChallengeService;
use InfinityBlog\Auth\LoginResult;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;

final class AuthController
{
    private AuthenticationService $authentication;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private UserRepository $users;
    private ?LoginChallengeService $challenges;

    public function __construct(
        AuthenticationService $authentication,
        AuthGuard $guard,
        SessionManager $sessions,
        ?UserRepository $users = null,
        ?LoginChallengeService $challenges = null
    ) {
        $this->authentication = $authentication;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->users = $users ?? $authentication->users();
        $this->challenges = $challenges;
    }

    private function resolveChallenges(): ?LoginChallengeService
    {
        if ($this->challenges !== null) {
            return $this->challenges;
        }
        if (getenv('LOGIN_EMAIL_CHALLENGE') === '1' || getenv('LOGIN_EMAIL_CHALLENGE') === 'true') {
            if (method_exists($this->users, 'getPdo')) {
                $this->challenges = new LoginChallengeService($this->users->getPdo());
                return $this->challenges;
            }
        }
        return null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        $result = match ([$request->method, $request->path]) {
            ['POST', '/auth/login'], ['POST', '/login'] => $this->login($request),
            ['POST', '/auth/challenge/verify'], ['POST', '/challenge/verify'], ['POST', '/api/auth/challenge/verify'] => $this->verifyChallenge($request),
            ['POST', '/auth/challenge/resend'], ['POST', '/challenge/resend'], ['POST', '/api/auth/challenge/resend'] => $this->resendChallenge($request),
            ['POST', '/auth/register'], ['POST', '/register'] => $this->register($request),
            ['POST', '/auth/logout'], ['POST', '/logout'] => $this->logout($request),
            ['GET', '/auth/me'], ['GET', '/me'] => $this->me(),
            ['GET', '/auth/csrf'], ['GET', '/csrf'] => $this->csrf(),
            default => null,
        };
        if ($result !== null) {
            return $result;
        }

        $knownPath = in_array($request->path, [
            '/auth/login', '/login',
            '/auth/challenge/verify', '/challenge/verify', '/api/auth/challenge/verify',
            '/auth/challenge/resend', '/challenge/resend', '/api/auth/challenge/resend',
            '/auth/register', '/register',
            '/auth/logout', '/logout',
            '/auth/me', '/auth/csrf'
        ], true);
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
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        $input = $request->input();
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

        $account = $result->account;

        // Two-step verification challenge check
        $challengeService = $this->resolveChallenges();
        if ($challengeService !== null && $challengeService->shouldChallenge($account)) {
            $challengeData = $challengeService->createChallenge($account, $request->sourceAddress);
            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'challenge_required' => true,
                        'challenge_token' => $challengeData['challenge_token'],
                        'masked_email' => $challengeData['masked_email'],
                        'delivery_status' => $challengeData['delivery_status'],
                        'expires_in' => $challengeData['expires_in'],
                        'dev_code' => $challengeData['dev_code'] ?? null,
                        'csrf_token' => $this->sessions->csrfToken(),
                    ],
                ],
            ];
        }

        // Administrator Recovery Path: existing admins with null email bypass challenge safely
        $notice = null;
        if ($account->email === null && $account->isAdmin()) {
            $notice = 'Admin signed in via email recovery bypass. Please configure an administrator email address in Settings.';
            try {
                if (method_exists($this->users, 'getPdo')) {
                    $pdo = $this->users->getPdo();
                    $stmt = $pdo->prepare(
                        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                        'VALUES (:actor, :action, :type, :id, :meta, :created_at)'
                    );
                    $stmt->execute([
                        ':actor' => $account->id,
                        ':action' => 'admin.login_null_email_bypass',
                        ':type' => 'user',
                        ':id' => $account->id,
                        ':meta' => json_encode(['username' => $account->username, 'source_ip' => $request->sourceAddress]),
                        ':created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable) {
                // Tolerate missing audit table in test environments
            }
        }

        $this->sessions->establish($account);

        $responseData = [
            'user' => $account->toPublicArray(),
            'csrf_token' => $this->sessions->csrfToken(),
        ];
        if ($notice !== null) {
            $responseData['notice'] = $notice;
        }

        return [
            'status' => 200,
            'body' => [
                'data' => $responseData,
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function verifyChallenge(Request $request): array
    {
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        $input = $request->input();
        $challengeToken = $input['challenge_token'] ?? null;
        $otp = $input['code'] ?? $input['otp'] ?? null;

        $fields = [];
        if (!is_string($challengeToken) || trim($challengeToken) === '') {
            $fields['challenge_token'] = 'Challenge token is missing.';
        }
        if (!is_string($otp) || trim($otp) === '') {
            $fields['code'] = 'Enter the 6-digit verification code.';
        }

        if ($fields !== []) {
            return [
                'status' => 422,
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'One or more fields need attention.', $fields),
            ];
        }

        $challengeService = $this->resolveChallenges();
        if ($challengeService === null) {
            return $this->error(500, 'CHALLENGE_UNAVAILABLE', 'Verification challenge service is unavailable.');
        }

        $verification = $challengeService->verifyChallenge((string) $challengeToken, (string) $otp);
        if (!$verification['success']) {
            $errCode = $verification['error'] ?? 'INVALID_CODE';
            $statusCode = match ($errCode) {
                'MAX_ATTEMPTS_EXCEEDED', 'CHALLENGE_EXPIRED' => 429,
                'CHALLENGE_NOT_FOUND', 'CHALLENGE_ALREADY_USED' => 404,
                default => 401,
            };
            return $this->error($statusCode, $errCode, $verification['message'] ?? 'Verification failed.');
        }

        $userId = (int) $verification['user_id'];
        $account = $this->users->findById($userId);
        if ($account === null || !$account->isActive()) {
            return $this->error(401, 'ACCOUNT_INACTIVE', 'The account is inactive or not found.');
        }

        $this->users->recordSuccessfulLogin($account->id);
        $this->sessions->establish($account);

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'user' => $account->toPublicArray(),
                    'csrf_token' => $this->sessions->csrfToken(),
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function resendChallenge(Request $request): array
    {
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        $input = $request->input();
        $challengeToken = $input['challenge_token'] ?? null;
        if (!is_string($challengeToken) || trim($challengeToken) === '') {
            return $this->error(422, 'VALIDATION_ERROR', 'Challenge token is missing.');
        }

        $challengeService = $this->resolveChallenges();
        if ($challengeService === null) {
            return $this->error(500, 'CHALLENGE_UNAVAILABLE', 'Verification challenge service is unavailable.');
        }

        $account = null;
        if (method_exists($this->users, 'getPdo')) {
            $stmt = $this->users->getPdo()->prepare('SELECT user_id FROM auth_challenges WHERE challenge_token = :token LIMIT 1');
            $stmt->execute([':token' => $challengeToken]);
            $uid = $stmt->fetchColumn();
            if ($uid) {
                $account = $this->users->findById((int) $uid);
            }
        }

        if ($account === null) {
            return $this->error(404, 'CHALLENGE_NOT_FOUND', 'Original verification challenge was not found.');
        }

        $challengeData = $challengeService->createChallenge($account, $request->sourceAddress);

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'challenge_required' => true,
                    'challenge_token' => $challengeData['challenge_token'],
                    'masked_email' => $challengeData['masked_email'],
                    'delivery_status' => $challengeData['delivery_status'],
                    'expires_in' => $challengeData['expires_in'],
                    'dev_code' => $challengeData['dev_code'] ?? null,
                    'csrf_token' => $this->sessions->csrfToken(),
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function register(Request $request): array
    {
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        $input = $request->input();
        $displayName = $input['display_name'] ?? $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        $confirmPassword = $input['confirm_password'] ?? $input['password_confirmation'] ?? null;

        $fields = [];
        if (!is_string($displayName) || trim($displayName) === '' || strlen($displayName) > 190
            || preg_match('/[\x00-\x1F\x7F]/', $displayName) === 1) {
            $fields['display_name'] = 'Enter your full name (up to 190 characters).';
        }

        if (!is_string($email) || trim($email) === '' || strlen($email) > 190
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/[\x00-\x1F\x7F]/', $email) === 1) {
            $fields['email'] = 'Enter a valid email address.';
        } elseif ($this->users->emailExists($email)) {
            $fields['email'] = 'An account with this email address already exists.';
        }

        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 1024) {
            $fields['password'] = 'Password must be at least 8 characters long.';
        }

        if (!is_string($confirmPassword) || $confirmPassword !== $password) {
            $fields['confirm_password'] = 'Passwords do not match.';
        }

        if ($fields !== []) {
            return [
                'status' => 422,
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'One or more fields need attention.', $fields),
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $profile = [];
        foreach (['bio', 'location', 'interests', 'website', 'phone'] as $fieldKey) {
            if (isset($input[$fieldKey]) && is_string($input[$fieldKey]) && trim($input[$fieldKey]) !== '') {
                $profile[$fieldKey] = trim($input[$fieldKey]);
            }
        }
        $newUserId = $this->users->createUser(trim($displayName), trim($email), $passwordHash, $profile);

        return [
            'status' => 201,
            'body' => [
                'data' => [
                    'message' => 'Registration successful. You may now sign in.',
                    'user' => [
                        'id' => $newUserId,
                        'display_name' => trim($displayName),
                        'email' => UserRepository::normalizeIdentifier(trim($email)),
                        'role' => 'user',
                        'status' => 'active',
                    ],
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
