<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\LoginChallengeService;
use InfinityBlog\Auth\LoginResult;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Security\RegistrationChallengeService;
use PDO;

final class AuthController
{
    private AuthenticationService $authentication;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private UserRepository $users;
    private ?LoginChallengeService $challenges;
    private ?RegistrationChallengeService $registrationChallenges;

    public function __construct(
        AuthenticationService $authentication,
        AuthGuard $guard,
        SessionManager $sessions,
        ?UserRepository $users = null,
        ?LoginChallengeService $challenges = null,
        ?RegistrationChallengeService $registrationChallenges = null
    ) {
        $this->authentication = $authentication;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->users = $users ?? $authentication->users();
        $this->challenges = $challenges;
        $this->registrationChallenges = $registrationChallenges;
    }

    private function resolveRegistrationChallenges(): ?RegistrationChallengeService
    {
        if ($this->registrationChallenges !== null) {
            return $this->registrationChallenges;
        }
        $toggle = getenv('REGISTRATION_EMAIL_VERIFICATION') ?: getenv('REGISTRATION_PHONE_VERIFICATION') ?: getenv('REGISTRATION_OTP_VERIFICATION');
        if ($toggle === '1' || $toggle === 'true') {
            if (method_exists($this->users, 'getPdo')) {
                $this->registrationChallenges = new RegistrationChallengeService($this->users->getPdo(), null, null, $this->users);
                return $this->registrationChallenges;
            }
        }
        return null;
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
            ['POST', '/auth/register/verify'], ['POST', '/register/verify'], ['POST', '/api/auth/register/verify'] => $this->verifyRegistration($request),
            ['POST', '/auth/register/resend'], ['POST', '/register/resend'], ['POST', '/api/auth/register/resend'] => $this->resendRegistration($request),
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
            '/auth/register/verify', '/register/verify', '/api/auth/register/verify',
            '/auth/register/resend', '/register/resend', '/api/auth/register/resend',
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
        $input = $request->input();
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($input['csrf_token'] ?? null) ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'Your session or security token has expired. Please refresh the page and try again.');
        }

        $displayName = $input['display_name'] ?? $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        $confirmPassword = $input['confirm_password'] ?? $input['password_confirmation'] ?? null;
        $phone = $input['phone'] ?? $input['mobile_number'] ?? null;

        $fields = [];
        if (!is_string($displayName) || trim($displayName) === '' || strlen($displayName) > 190
            || preg_match('/[\x00-\x1F\x7F]/', $displayName) === 1) {
            $fields['display_name'] = 'Please enter your full name (up to 190 characters).';
        }

        if (!is_string($email) || trim($email) === '' || strlen($email) > 190
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/[\x00-\x1F\x7F]/', $email) === 1) {
            $fields['email'] = 'Please enter a valid email address.';
        } elseif ($this->users->emailExists($email)) {
            $fields['email'] = 'An account with this email address already exists. Please sign in or use a different email.';
        }

        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 1024) {
            $fields['password'] = 'Password must be at least 8 characters long.';
        }

        if (!is_string($confirmPassword) || $confirmPassword === '') {
            $fields['confirm_password'] = 'Please confirm your password.';
        } elseif ($confirmPassword !== $password) {
            $fields['confirm_password'] = 'Passwords do not match.';
        }

        $regService = $this->resolveRegistrationChallenges();
        if ($regService !== null) {
            if (!is_string($phone) || trim($phone) === '') {
                $fields['phone'] = 'A mobile phone number is required for verification.';
            } else {
                try {
                    $mobileService = new \InfinityBlog\Security\MobileNumberService();
                    $normalizedPhone = $mobileService->normalize((string) $phone);
                    $phoneHash = $mobileService->hash($normalizedPhone);
                    if ($this->users->existsByMobileHash($phoneHash)) {
                        $fields['phone'] = 'This mobile phone number is already registered to an account.';
                    }
                } catch (\InvalidArgumentException $e) {
                    $fields['phone'] = 'Please enter a valid mobile phone number in international format (e.g. +91 98765 43210).';
                }
            }
        }

        if ($fields !== []) {
            return [
                'status' => 422,
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Please correct the highlighted errors and try again.', $fields),
            ];
        }

        $profile = [];
        foreach (['bio', 'location', 'interests', 'website'] as $fieldKey) {
            if (isset($input[$fieldKey]) && is_string($input[$fieldKey]) && trim($input[$fieldKey]) !== '') {
                $profile[$fieldKey] = trim($input[$fieldKey]);
            }
        }

        if ($regService !== null) {
            $challengeResult = $regService->createChallenge([
                'display_name' => trim((string) $displayName),
                'email' => trim((string) $email),
                'password' => (string) $password,
                'phone' => trim((string) $phone),
                'profile' => $profile,
            ], $request->sourceAddress);

            if (!$challengeResult['success']) {
                $errCode = $challengeResult['error'] ?? 'REGISTRATION_FAILED';
                return $this->error(422, $errCode, $challengeResult['message'] ?? 'Registration could not be started.');
            }

            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'challenge_required' => true,
                        'challenge_token' => $challengeResult['challenge_token'],
                        'masked_email' => $challengeResult['masked_email'] ?? null,
                        'masked_phone' => $challengeResult['masked_phone'] ?? null,
                        'expires_in' => $challengeResult['expires_in'] ?? 600,
                        'delivery_status' => $challengeResult['delivery_status'],
                        'delivery_reason' => $challengeResult['delivery_reason'] ?? null,
                        'dev_code' => $challengeResult['dev_code'] ?? null,
                        'csrf_token' => $this->sessions->csrfToken(),
                        'message' => 'Verification code sent to your email address.',
                    ],
                ],
            ];
        }

        // Direct registration fallback when registration challenges are not active
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (is_string($phone) && trim($phone) !== '') {
            $profile['phone'] = trim($phone);
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
    public function verifyRegistration(Request $request): array
    {
        $input = $request->input();
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($input['csrf_token'] ?? null) ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'Your session or security token has expired. Please refresh the page and try again.');
        }

        $challengeToken = $input['challenge_token'] ?? $request->postParameter('challenge_token') ?? null;
        $otp = $input['code'] ?? $input['otp'] ?? $request->postParameter('code') ?? null;

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
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Please correct the highlighted errors and try again.', $fields),
            ];
        }

        $regService = $this->resolveRegistrationChallenges();
        if ($regService === null) {
            return $this->error(500, 'CHALLENGE_UNAVAILABLE', 'Registration challenge service is unavailable.');
        }

        $verification = $regService->verifyChallenge((string) $challengeToken, (string) $otp);
        if (!$verification['success']) {
            $errCode = $verification['error'] ?? 'INVALID_CODE';
            $statusCode = match ($errCode) {
                'MAX_ATTEMPTS_EXCEEDED', 'CHALLENGE_EXPIRED' => 429,
                'CHALLENGE_NOT_FOUND', 'CHALLENGE_ALREADY_USED' => 404,
                'EMAIL_ALREADY_EXISTS', 'PHONE_ALREADY_REGISTERED' => 409,
                default => 401,
            };
            return $this->error($statusCode, $errCode, $verification['message'] ?? 'Verification failed.');
        }

        $userId = (int) $verification['user_id'];
        $user = $this->users->findById($userId);

        return [
            'status' => 201,
            'body' => [
                'data' => [
                    'message' => 'Registration and email verification successful. You may now sign in.',
                    'user' => $user ? $user->toPublicArray() : ['id' => $userId],
                    'csrf_token' => $this->sessions->csrfToken(),
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function resendRegistration(Request $request): array
    {
        $input = $request->input();
        $csrfToken = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token') ?? ($input['csrf_token'] ?? null) ?? ($_POST['csrf_token'] ?? null);
        if (!is_string($csrfToken) || !$this->sessions->validateCsrfToken($csrfToken)) {
            return $this->error(403, 'CSRF_INVALID', 'Your session or security token has expired. Please refresh the page and try again.');
        }

        $challengeToken = $input['challenge_token'] ?? $request->postParameter('challenge_token') ?? null;
        if (!is_string($challengeToken) || trim($challengeToken) === '') {
            return $this->error(422, 'VALIDATION_ERROR', 'Challenge token is missing.');
        }

        $regService = $this->resolveRegistrationChallenges();
        if ($regService === null) {
            return $this->error(500, 'CHALLENGE_UNAVAILABLE', 'Registration challenge service is unavailable.');
        }

        $resend = $regService->resendChallenge((string) $challengeToken, $request->sourceAddress);
        if (!$resend['success']) {
            $code = $resend['error'] ?? 'RESEND_FAILED';
            return $this->error(400, $code, $resend['message'] ?? 'Could not resend verification code.');
        }

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'challenge_token' => $resend['challenge_token'],
                    'masked_email' => $resend['masked_email'] ?? null,
                    'masked_phone' => $resend['masked_phone'] ?? null,
                    'expires_in' => $resend['expires_in'] ?? 600,
                    'delivery_status' => $resend['delivery_status'],
                    'delivery_reason' => $resend['delivery_reason'] ?? null,
                    'dev_code' => $resend['dev_code'] ?? null,
                    'csrf_token' => $this->sessions->csrfToken(),
                    'message' => $resend['message'],
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
