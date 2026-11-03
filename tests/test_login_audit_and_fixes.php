<?php
declare(strict_types=1);

/**
 * Login System Audit, Password Reset, Identifier Canonicalization,
 * Rate Limiting, and Failure Boundary Test Suite.
 */

ob_start();

require __DIR__ . '/../autoload.php';

use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Auth\LoginChallengeService;
use InfinityBlog\Auth\PasswordResetService;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Http\AuthController;
use InfinityBlog\Http\Request;
use InfinityBlog\Mail\MailerService;
use InfinityBlog\Mail\MailTransportInterface;

$passedTests = 0;
$failedTests = 0;

function it(string $description, callable $test): void {
    global $passedTests, $failedTests;
    try {
        $test();
        fwrite(STDERR, "  [PASS] {$description}\n");
        $passedTests++;
    } catch (\Throwable $e) {
        fwrite(STDERR, "  [FAIL] {$description}\n");
        fwrite(STDERR, "         " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n");
        $failedTests++;
    }
}

function expectTrue(bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function expectEquals(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new \RuntimeException(($message ? $message . ': ' : '') . "Expected {$expStr}, got {$actStr}");
    }
}

function createTestDatabase(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        display_name TEXT NOT NULL,
        email TEXT UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'author',
        profile_visibility TEXT NOT NULL DEFAULT 'public',
        auth_version INTEGER NOT NULL DEFAULT 1,
        status TEXT NOT NULL DEFAULT 'active',
        must_change_password INTEGER NOT NULL DEFAULT 0,
        mobile_number_ciphertext TEXT NULL,
        mobile_number_hash TEXT NULL,
        mobile_number_verified_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE login_rate_limits (
        bucket_hash CHAR(64) PRIMARY KEY,
        bucket_type VARCHAR(16) NOT NULL,
        failed_attempts INT NOT NULL,
        window_started_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )");

    $pdo->exec("CREATE TABLE auth_challenges (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        challenge_token TEXT NOT NULL UNIQUE,
        otp_hash TEXT NOT NULL,
        destination_email TEXT NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        max_attempts INTEGER NOT NULL DEFAULT 5,
        expires_at TEXT NOT NULL,
        verified_at TEXT NULL,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        used_at TEXT NULL,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor_user_id INTEGER NULL,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        metadata TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    return $pdo;
}

const TEST_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

function createTestConfig(): AppConfig {
    return AppConfig::fromArray([
        'APP_ENV' => 'test',
        'APP_DEBUG' => 'true',
        'APP_KEY' => TEST_KEY,
        'APP_BASE_URL' => 'http://localhost:8086',
        'SESSION_SECURE_COOKIE' => 'false',
        'SESSION_LIFETIME' => 3600,
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => 3306,
        'DB_DATABASE' => 'test',
        'DB_USERNAME' => 'test',
        'DB_PASSWORD' => 'test',
    ]);
}

fwrite(STDERR, "\n--- 1. CANONICAL IDENTIFIER LOOKUP (UserRepository::findByIdentifier) ---\n");

it('prioritizes canonical username over any duplicate display name', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);

    // User A: username 'cristine', display_name 'Cristine Official'
    $repo->createUser('Cristine Official', 'cristine@example.com', password_hash('pass1', PASSWORD_DEFAULT), ['username' => 'cristine']);
    // User B: username 'other_user', display_name 'cristine' (matches User A's username)
    $repo->createUser('cristine', 'other@example.com', password_hash('pass2', PASSWORD_DEFAULT), ['username' => 'other_user']);

    $found = $repo->findByIdentifier('cristine');
    expectTrue($found !== null, 'Should find user');
    expectEquals('cristine', $found->username, 'Canonical username must take priority over display_name match');
});

it('prioritizes canonical email over any duplicate display name', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);

    // User A: email 'john@example.com', display_name 'John Doe'
    $repo->createUser('John Doe', 'john@example.com', password_hash('pass1', PASSWORD_DEFAULT), ['username' => 'johndoe']);
    // User B: email 'other@example.com', display_name 'john@example.com'
    $repo->createUser('john@example.com', 'other@example.com', password_hash('pass2', PASSWORD_DEFAULT), ['username' => 'other_john']);

    $found = $repo->findByIdentifier('john@example.com');
    expectTrue($found !== null, 'Should find user');
    expectEquals('johndoe', $found->username, 'Canonical email must take priority over display_name match');
});

it('matches unique display name when no canonical username or email matches', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);

    $repo->createUser('Unique Author', 'unique@example.com', password_hash('pass1', PASSWORD_DEFAULT), ['username' => 'uauthor']);

    $found = $repo->findByIdentifier('Unique Author');
    expectTrue($found !== null, 'Should find user by unique display name');
    expectEquals('uauthor', $found->username);

    // Case-insensitive check
    $foundLower = $repo->findByIdentifier('unique author');
    expectTrue($foundLower !== null, 'Should find user by lowercase display name');
    expectEquals('uauthor', $foundLower->username);
});

it('rejects ambiguous login when multiple users share the same display name', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);

    // Two users with the identical display name 'Cristine Palmer'
    $repo->createUser('Cristine Palmer', 'user1@example.com', password_hash('pass1', PASSWORD_DEFAULT), ['username' => 'cristine']);
    $repo->createUser('Cristine Palmer', 'user2@example.com', password_hash('pass2', PASSWORD_DEFAULT), ['username' => 'cristine_palmer']);

    $found = $repo->findByIdentifier('Cristine Palmer');
    expectTrue($found === null, 'Ambiguous display name must return null to prevent arbitrary account verification');

    // But each can still log in using their canonical username!
    $user1 = $repo->findByIdentifier('cristine');
    expectTrue($user1 !== null && $user1->username === 'cristine');
    $user2 = $repo->findByIdentifier('cristine_palmer');
    expectTrue($user2 !== null && $user2->username === 'cristine_palmer');
});

fwrite(STDERR, "\n--- 2. RATE LIMITER PARITY & INSTANT SUCCESS RESET (DatabaseLoginAttemptLimiter) ---\n");

it('enforces lockout after 5 attempts and resets immediately on success', function () {
    $pdo = createTestDatabase();
    $limiter = new DatabaseLoginAttemptLimiter($pdo, TEST_KEY);

    $identifier = 'testuser';
    $ip = '192.168.1.100';

    expectTrue(!$limiter->isLimited($identifier, $ip), 'Should not be limited initially');

    for ($i = 0; $i < 4; $i++) {
        $limiter->recordFailure($identifier, $ip);
        expectTrue(!$limiter->isLimited($identifier, $ip), "Attempt {$i} should not lock out yet");
    }

    $limiter->recordFailure($identifier, $ip); // 5th failure
    expectTrue($limiter->isLimited($identifier, $ip), '5th failure should trigger lockout');

    // Instant reset on success
    $limiter->recordSuccess($identifier);
    expectTrue(!$limiter->isLimited($identifier, $ip), 'Success should reset identifier bucket immediately');
});

fwrite(STDERR, "\n--- 3. PASSWORD RESET SECURITY WORKFLOW (PasswordResetService) ---\n");

it('provides enumeration-resistant reset request response', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $repo->createUser('Alice Real', 'alice@example.com', password_hash('pass1234', PASSWORD_DEFAULT), ['username' => 'alicereal']);

    $capturedMail = [];
    $mockTransport = new class($capturedMail) implements MailTransportInterface {
        private array $log;
        public function __construct(array &$log) { $this->log = &$log; }
        public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null, array $headers = []): array {
            $this->log[] = ['to' => $to, 'subject' => $subject, 'body' => $bodyText];
            return ['delivered' => true, 'status' => 'SENT', 'reason' => null];
        }
    };
    $mailer = new MailerService($mockTransport);
    $resetService = new PasswordResetService($pdo, $repo, $mailer, 'http://localhost:8086');

    // Existent user
    $resExist = $resetService->requestReset('alice@example.com', '127.0.0.1');
    expectTrue($resExist['success'] === true);
    expectEquals('If an account matches that email address, a password reset link has been sent.', $resExist['message']);
    expectEquals(1, count($capturedMail), 'Email must be sent for existent account');

    // Non-existent user
    $resNonExist = $resetService->requestReset('nobody@example.com', '127.0.0.1');
    expectTrue($resNonExist['success'] === true);
    expectEquals('If an account matches that email address, a password reset link has been sent.', $resNonExist['message']);
    expectEquals(1, count($capturedMail), 'No additional email sent for non-existent account');
});

it('hashes token with SHA-256 and never stores plaintext token', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $repo->createUser('Bob Real', 'bob@example.com', password_hash('pass1234', PASSWORD_DEFAULT), ['username' => 'bobreal']);

    $plainToken = '';
    $mockTransport = new class($plainToken) implements MailTransportInterface {
        private string $token;
        public function __construct(string &$token) { $this->token = &$token; }
        public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null, array $headers = []): array {
            if (preg_match('/token=([a-f0-9]{64})/', $bodyText, $m)) {
                $this->token = $m[1];
            }
            return ['delivered' => true, 'status' => 'SENT', 'reason' => null];
        }
    };
    $mailer = new MailerService($mockTransport);
    $resetService = new PasswordResetService($pdo, $repo, $mailer, 'http://localhost:8086');

    $resetService->requestReset('bob@example.com', '127.0.0.1');
    expectTrue(strlen($plainToken) === 64, 'Token must be 64-character hex');

    // Verify DB does NOT contain plainToken, but DOES contain hash('sha256', plainToken)
    $stmt = $pdo->query('SELECT token_hash FROM password_resets LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    expectTrue($row !== false, 'Record must exist in DB');
    expectEquals(hash('sha256', $plainToken), $row['token_hash'], 'DB must store SHA-256 hash of token');
});

it('rejects expired, already used, or invalid reset tokens', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $uId = $repo->createUser('Charlie Real', 'charlie@example.com', password_hash('pass1234', PASSWORD_DEFAULT), ['username' => 'charliereal']);

    $mailer = new MailerService(new class implements MailTransportInterface {
        public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null, array $headers = []): array {
            return ['delivered' => true, 'status' => 'SENT', 'reason' => null];
        }
    });
    $resetService = new PasswordResetService($pdo, $repo, $mailer, 'http://localhost:8086');

    // 1. Invalid token format
    $resInvalid = $resetService->verifyToken('not-a-valid-hex-token');
    expectTrue($resInvalid['valid'] === false);
    expectEquals('INVALID_TOKEN', $resInvalid['error']);

    // 2. Token not found
    $resNotFound = $resetService->verifyToken(bin2hex(random_bytes(32)));
    expectTrue($resNotFound['valid'] === false);
    expectEquals('TOKEN_NOT_FOUND', $resNotFound['error']);

    // 3. Expired token
    $expiredToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:u, :h, :exp, :cre)');
    $stmt->execute([
        ':u' => $uId,
        ':h' => hash('sha256', $expiredToken),
        ':exp' => gmdate('Y-m-d H:i:s', time() - 60), // 1 min ago
        ':cre' => gmdate('Y-m-d H:i:s', time() - 3600),
    ]);
    $resExpired = $resetService->verifyToken($expiredToken);
    expectTrue($resExpired['valid'] === false, 'Expired token must not be valid');
    expectEquals('TOKEN_EXPIRED', $resExpired['error']);

    // 4. Already used token
    $usedToken = bin2hex(random_bytes(32));
    $stmt->execute([
        ':u' => $uId,
        ':h' => hash('sha256', $usedToken),
        ':exp' => gmdate('Y-m-d H:i:s', time() + 3600),
        ':cre' => gmdate('Y-m-d H:i:s'),
    ]);
    $pdo->prepare('UPDATE password_resets SET used_at = :now WHERE token_hash = :h')->execute([
        ':now' => gmdate('Y-m-d H:i:s'),
        ':h' => hash('sha256', $usedToken),
    ]);
    $resUsed = $resetService->verifyToken($usedToken);
    expectTrue($resUsed['valid'] === false);
    expectEquals('TOKEN_ALREADY_USED', $resUsed['error']);
});

it('executes password reset, enforces policy, revokes active sessions, and updates password hash', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $uId = $repo->createUser('Dan Real', 'dan@example.com', password_hash('old_pass_123', PASSWORD_DEFAULT), ['username' => 'danreal']);
    $userBefore = $repo->findById($uId);
    $initialAuthVersion = $userBefore->authVersion;

    $plainToken = '';
    $mockTransport = new class($plainToken) implements MailTransportInterface {
        private string $token;
        public function __construct(string &$token) { $this->token = &$token; }
        public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null, array $headers = []): array {
            if (preg_match('/token=([a-f0-9]{64})/', $bodyText, $m)) {
                $this->token = $m[1];
            }
            return ['delivered' => true, 'status' => 'SENT', 'reason' => null];
        }
    };
    $resetService = new PasswordResetService($pdo, $repo, new MailerService($mockTransport), 'http://localhost:8086');

    $resetService->requestReset('dan@example.com', '127.0.0.1');

    // 1. Password too short (< 8 chars)
    $shortRes = $resetService->resetPassword($plainToken, 'short', 'short');
    expectTrue($shortRes['success'] === false);
    expectEquals('PASSWORD_TOO_SHORT', $shortRes['error']);

    // 2. Valid password reset
    $goodRes = $resetService->resetPassword($plainToken, 'new_secure_password_2026', 'new_secure_password_2026');
    expectTrue($goodRes['success'] === true);

    // Verify token marked used
    $reuseRes = $resetService->resetPassword($plainToken, 'another_new_password_2026', 'another_new_password_2026');
    expectTrue($reuseRes['success'] === false);
    expectEquals('TOKEN_ALREADY_USED', $reuseRes['error']);

    // Verify user record updated: auth_version incremented
    $userAfter = $repo->findById($uId);
    expectEquals($initialAuthVersion + 1, $userAfter->authVersion, 'auth_version must be incremented to revoke active sessions');

    // Verify old password fails, new password succeeds
    expectTrue(!$userAfter->verifyPassword('old_pass_123'), 'Old password must no longer verify');
    expectTrue($userAfter->verifyPassword('new_secure_password_2026'), 'New password must verify');
});

fwrite(STDERR, "\n--- 4. TWO-STEP CHALLENGE & FAIL-CLOSED GUARDS (LoginChallengeService) ---\n");

it('invalidates prior active challenges when a new challenge is created to prevent token replay', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $uId = $repo->createUser('Eve Real', 'eve@example.com', password_hash('pass1234', PASSWORD_DEFAULT), ['username' => 'evereal']);
    $eve = $repo->findById($uId);

    $service = new LoginChallengeService($pdo);

    $ch1 = $service->createChallenge($eve);
    $otp1 = $service->getLastGeneratedOtpForTesting();
    expectTrue(is_string($otp1) && strlen($otp1) === 6);

    // Create a 2nd challenge (e.g. user requested resend)
    $ch2 = $service->createChallenge($eve);
    $otp2 = $service->getLastGeneratedOtpForTesting();

    // Verify the first challenge token was superseded and can no longer be used
    $res1 = $service->verifyChallenge($ch1['challenge_token'], $otp1);
    expectTrue($res1['success'] === false, 'Superseded challenge must not succeed');
    expectEquals('CHALLENGE_ALREADY_USED', $res1['error']);

    // The second challenge succeeds
    $res2 = $service->verifyChallenge($ch2['challenge_token'], $otp2);
    expectTrue($res2['success'] === true, 'Latest challenge must succeed');
    expectEquals($uId, $res2['user_id']);
});

it('locks out challenge after 5 failed verification attempts', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $uId = $repo->createUser('Frank Real', 'frank@example.com', password_hash('pass1234', PASSWORD_DEFAULT), ['username' => 'frankreal']);
    $frank = $repo->findById($uId);

    $service = new LoginChallengeService($pdo);
    $ch = $service->createChallenge($frank);
    $token = $ch['challenge_token'];
    $validOtp = $service->getLastGeneratedOtpForTesting();

    for ($i = 1; $i <= 4; $i++) {
        $res = $service->verifyChallenge($token, '999999');
        expectTrue($res['success'] === false);
        expectEquals(5 - $i, $res['remaining_attempts']);
    }

    // 5th failed attempt exhausts limit
    $res5 = $service->verifyChallenge($token, '999999');
    expectTrue($res5['success'] === false);
    expectEquals(0, $res5['remaining_attempts']);

    // Even with correct OTP, verification is rejected after lockout
    $resLocked = $service->verifyChallenge($token, $validOtp);
    expectTrue($resLocked['success'] === false);
    expectEquals('MAX_ATTEMPTS_EXCEEDED', $resLocked['error']);
});

fwrite(STDERR, "\n--- 5. AUTH CONTROLLER ERROR BOUNDARIES & HTTP CODES ---\n");

it('returns 500 INTERNAL_ERROR on database exception during login and never masquerades as 401', function () {
    // Database without tables so query throws PDOException
    $failingPdo = new PDO('sqlite::memory:');
    $failingPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $failingRepo = new UserRepository($failingPdo);
    $failingAuth = new AuthenticationService($failingRepo, new DatabaseLoginAttemptLimiter(createTestDatabase(), TEST_KEY));

    $config = createTestConfig();
    $sessions = new SessionManager($config);
    $sessions->start();
    $csrf = $sessions->csrfToken();
    $guard = new AuthGuard($sessions, $failingRepo);

    $controller = new AuthController($failingAuth, $guard, $sessions, $failingRepo);
    $req = Request::fromValues('POST', '/auth/login', '127.0.0.1', ['X-CSRF-Token' => $csrf], [
        'identifier' => 'grace@example.com',
        'password' => 'pass1234',
    ]);

    $res = $controller->login($req);
    expectEquals(500, $res['status'], 'Database failure must return 500 status code');
    expectEquals('INTERNAL_ERROR', $res['body']['error']['code']);
    expectTrue(!empty($res['body']['request_id']), 'Response must provide request ID correlation');
});

it('returns 403 CSRF_INVALID on missing or invalid CSRF token', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $config = createTestConfig();
    $sessions = new SessionManager($config);
    $sessions->start();
    $guard = new AuthGuard($sessions, $repo);
    $auth = new AuthenticationService($repo, new DatabaseLoginAttemptLimiter($pdo, TEST_KEY));
    $controller = new AuthController($auth, $guard, $sessions, $repo);

    $req = Request::fromValues('POST', '/auth/login', '127.0.0.1', ['X-CSRF-Token' => 'tampered_csrf_token'], [
        'identifier' => 'grace@example.com',
        'password' => 'pass1234',
    ]);

    $res = $controller->login($req);
    expectEquals(403, $res['status']);
    expectEquals('CSRF_INVALID', $res['body']['error']['code']);
});

it('returns 429 RATE_LIMITED when attempts are exhausted', function () {
    $pdo = createTestDatabase();
    $repo = new UserRepository($pdo);
    $config = createTestConfig();
    $sessions = new SessionManager($config);
    $sessions->start();
    $csrf = $sessions->csrfToken();
    $guard = new AuthGuard($sessions, $repo);

    $limiter = new DatabaseLoginAttemptLimiter($pdo, TEST_KEY);
    $auth = new AuthenticationService($repo, $limiter);
    $controller = new AuthController($auth, $guard, $sessions, $repo);

    // Trigger 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $controller->login(Request::fromValues('POST', '/auth/login', '127.0.0.1', ['X-CSRF-Token' => $csrf], [
            'identifier' => 'unknown_target',
            'password' => 'wrong',
        ]));
    }

    // 6th attempt should be blocked with 429
    $res = $controller->login(Request::fromValues('POST', '/auth/login', '127.0.0.1', ['X-CSRF-Token' => $csrf], [
        'identifier' => 'unknown_target',
        'password' => 'wrong',
    ]));

    expectEquals(429, $res['status']);
    expectEquals('RATE_LIMITED', $res['body']['error']['code']);
});

fwrite(STDERR, "\n============================================\n");
fwrite(STDERR, "SUMMARY: {$passedTests} passed, {$failedTests} failed.\n");
fwrite(STDERR, "============================================\n");

ob_end_flush();

if ($failedTests > 0) {
    exit(1);
}
