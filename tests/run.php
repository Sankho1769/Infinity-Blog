<?php
declare(strict_types=1);

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Config\DatabaseConfig;
use InfinityBlog\Config\EnvironmentLoader;
use InfinityBlog\Auth\AdminProvisioner;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\AuthenticationService;
use InfinityBlog\Auth\LoginAttemptLimiter;
use InfinityBlog\Auth\LoginResult;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Database\DatabaseUnavailableException;
use InfinityBlog\Database\MigrationRunner;
use InfinityBlog\Http\ApiErrorResponder;
use InfinityBlog\Http\AuthController;
use InfinityBlog\Http\Request;

require dirname(__DIR__) . '/autoload.php';
ob_start();

final class TestLoginAttemptLimiter implements LoginAttemptLimiter
{
    /** @var array<string, int> */
    private array $identifiers = [];

    /** @var array<string, int> */
    private array $sources = [];

    public function isLimited(string $identifier, string $sourceAddress): bool
    {
        $identifier = UserRepository::normalizeIdentifier($identifier);
        return ($this->identifiers[$identifier] ?? 0) >= 5 || ($this->sources[$sourceAddress] ?? 0) >= 20;
    }

    public function recordFailure(string $identifier, string $sourceAddress): void
    {
        $identifier = UserRepository::normalizeIdentifier($identifier);
        $this->identifiers[$identifier] = ($this->identifiers[$identifier] ?? 0) + 1;
        $this->sources[$sourceAddress] = ($this->sources[$sourceAddress] ?? 0) + 1;
    }

    public function recordSuccess(string $identifier): void
    {
        unset($this->identifiers[UserRepository::normalizeIdentifier($identifier)]);
    }
}

$passed = 0;
$failed = 0;

$run = static function (string $name, callable $test) use (&$passed, &$failed): void {
    try {
        $test();
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
};

$expect = static function (bool $condition, string $message = 'Assertion failed.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expectInvalid = static function (callable $operation) use ($expect): void {
    try {
        $operation();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    $expect(false, 'Expected invalid configuration to be rejected.');
};

$validConfiguration = [
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'true',
    'APP_BASE_URL' => 'http://localhost/Infinity_Blog',
    'SESSION_SECURE_COOKIE' => 'false',
    'APP_KEY' => str_repeat('a', 64),
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'infinity_blog',
    'DB_USERNAME' => 'blog_runtime',
    'DB_PASSWORD' => 'synthetic-test-placeholder',
];

$run('configuration accepts valid development values', static function () use ($validConfiguration, $expect): void {
    $config = AppConfig::fromArray($validConfiguration);
    $expect($config->environment === 'development' && $config->debug && !$config->secureSessionCookie);
    $expect(str_contains($config->database->dsn(), 'charset=utf8mb4'));
});

$run('configuration rejects missing database settings and invalid booleans', static function () use ($validConfiguration, $expectInvalid): void {
    $missingDatabase = $validConfiguration;
    unset($missingDatabase['DB_DATABASE']);
    $expectInvalid(static fn () => AppConfig::fromArray($missingDatabase));

    $invalidBoolean = $validConfiguration;
    $invalidBoolean['APP_DEBUG'] = 'sometimes';
    $expectInvalid(static fn () => AppConfig::fromArray($invalidBoolean));

    $invalidKey = $validConfiguration;
    $invalidKey['APP_KEY'] = 'too-short';
    $expectInvalid(static fn () => AppConfig::fromArray($invalidKey));
});

$run('database TLS requires a readable CA for remote hosts and cannot be disabled', static function () use ($expect, $expectInvalid): void {
    $caPath = tempnam(sys_get_temp_dir(), 'infinity-blog-ca-');
    if ($caPath === false) {
        throw new RuntimeException('Could not create a temporary CA test file.');
    }

    try {
        file_put_contents($caPath, 'synthetic certificate fixture');
        $expectInvalid(static fn () => new DatabaseConfig('db.example.invalid', 3306, 'infinity_blog_test', 'test_user', 'synthetic-password'));

        $remote = new DatabaseConfig('db.example.invalid', 3306, 'infinity_blog_test', 'test_user', 'synthetic-password', $caPath);
        $expect($remote->usesVerifiedTls() && $remote->sslCaPath === $caPath);

        $local = new DatabaseConfig('127.0.0.1', 3306, 'infinity_blog_test', 'test_user', 'synthetic-password');
        $expect(!$local->usesVerifiedTls());

        $expectInvalid(static fn () => new DatabaseConfig('db.example.invalid', 3306, 'infinity_blog_test', 'test_user', 'synthetic-password', $caPath . '.missing'));
    } finally {
        unlink($caPath);
    }
});

$run('production configuration requires safe debug, HTTPS, cookies, and database account', static function () use ($validConfiguration, $expectInvalid): void {
    $production = $validConfiguration;
    $production['APP_ENV'] = 'production';
    $production['APP_DEBUG'] = 'false';
    $production['APP_BASE_URL'] = 'https://blog.example.invalid';
    $production['SESSION_SECURE_COOKIE'] = 'true';
    $production['DB_USERNAME'] = 'blog_runtime';
    $production['DB_PASSWORD'] = 'synthetic-production-placeholder';
    $production['DB_SSL_CA'] = __FILE__;
    AppConfig::fromArray($production);

    $missingTls = $production;
    unset($missingTls['DB_SSL_CA']);
    $expectInvalid(static fn () => AppConfig::fromArray($missingTls));

    $unsafe = $production;
    $unsafe['SESSION_SECURE_COOKIE'] = 'false';
    $expectInvalid(static fn () => AppConfig::fromArray($unsafe));

    $rootAccount = $production;
    $rootAccount['DB_USERNAME'] = 'root';
    $expectInvalid(static fn () => AppConfig::fromArray($rootAccount));
});

$run('environment file parsing preserves process overrides and rejects malformed entries', static function () use ($expect): void {
    $path = tempnam(sys_get_temp_dir(), 'infinity-blog-env-');
    if ($path === false) {
        throw new RuntimeException('Could not create a temporary test file.');
    }

    try {
        file_put_contents($path, "APP_ENV=test\nDB_PASSWORD='test value'\n");
        $values = EnvironmentLoader::loadFile($path, ['APP_ENV' => 'development']);
        $expect($values['APP_ENV'] === 'development' && $values['DB_PASSWORD'] === 'test value');

        file_put_contents($path, "not-an-environment-entry\n");
        try {
            EnvironmentLoader::loadFile($path, []);
        } catch (InvalidArgumentException $exception) {
            return;
        }
        $expect(false, 'Malformed environment entries should be rejected.');
    } finally {
        unlink($path);
    }
});

$run('database connection failure has a generic message and no chained driver exception', static function () use ($expect): void {
    $config = new DatabaseConfig('127.0.0.1', 1, 'unavailable_test', 'synthetic-user', 'synthetic-password');
    try {
        ConnectionFactory::create($config, 1);
    } catch (DatabaseUnavailableException $exception) {
        $expect($exception->getMessage() === 'Database service is unavailable.');
        $expect($exception->getPrevious() === null);
        $expect(!str_contains($exception->getMessage(), 'synthetic'));
        return;
    }

    $expect(false, 'An unavailable database should fail safely.');
});

$run('environment example contains placeholders and local secrets are ignored', static function () use ($expect): void {
    $root = dirname(__DIR__);
    $example = file_get_contents($root . '/.env.example');
    $ignore = file_get_contents($root . '/.gitignore');
    $expect(is_string($example) && str_contains($example, 'DB_PASSWORD=replace-with-a-local-secret'));
    $expect(is_string($ignore) && preg_match('/^\.env\*?\s*$/m', $ignore) === 1 && str_contains($ignore, '!.env.example'));
    $expect(!is_file($root . '/.env'));
});

$run('migration rollback requires explicit confirmation before any database change', static function () use ($expect): void {
    $pdo = new PDO('sqlite::memory:');
    $runner = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');
    try {
        $runner->rollbackLast(false);
    } catch (InvalidArgumentException $exception) {
        $expect($exception->getMessage() === 'Rolling back a schema migration requires explicit data-loss confirmation.');
        return;
    }

    $expect(false, 'Rollback without confirmation must be rejected.');
});

$run('API error envelope includes a safe request ID and hides internal details', static function () use ($expect): void {
    $payload = ApiErrorResponder::internalFailure("bad\r\nheader");
    $expect($payload['error']['code'] === 'INTERNAL_ERROR');
    $expect($payload['error']['message'] === 'The request could not be completed.');
    $expect(preg_match('/^[a-f0-9]{32}$/', $payload['request_id']) === 1);
    $expect(!str_contains(json_encode($payload), 'bad'));

    $validation = ApiErrorResponder::build('VALIDATION_ERROR', 'One or more fields need attention.', ['title' => 'Title is required.'], 'req-12345678');
    $expect($validation['request_id'] === 'req-12345678');
    $expect($validation['error']['fields']['title'] === 'Title is required.');
});

$createUserDatabase = static function (): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE users (' .
        'id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE COLLATE NOCASE, ' .
        'display_name TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL, ' .
        "status TEXT NOT NULL DEFAULT 'active', must_change_password INTEGER NOT NULL DEFAULT 0, " .
        'last_login_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
    );
    $insert = $pdo->prepare(
        'INSERT INTO users (username, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ([
        ['author one', 'Author One', 'author-pass-1234', 'author', 'active'],
        ['admin one', 'Admin One', 'admin-pass-1234', 'admin', 'active'],
        ['inactive one', 'Inactive One', 'inactive-pass-1234', 'author', 'inactive'],
    ] as [$username, $displayName, $password, $role, $status]) {
        $insert->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
    }

    return $pdo;
};

$run('authentication verifies known credentials and records only safe identity data', static function () use ($createUserDatabase, $expect): void {
    $pdo = $createUserDatabase();
    $limiter = new TestLoginAttemptLimiter();
    $service = new AuthenticationService(new UserRepository($pdo), $limiter);
    $result = $service->login(' AUTHOR ONE ', 'author-pass-1234', '192.0.2.10');
    $expect($result->status === LoginResult::SUCCESS && $result->account !== null);
    $expect($result->account->role === 'author' && $result->account->username === 'author one');
    $expect(!array_key_exists('password_hash', $result->account->toPublicArray()));
    $lastLogin = $pdo->query("SELECT last_login_at FROM users WHERE username = 'author one'")->fetchColumn();
    $expect(is_string($lastLogin) && $lastLogin !== '');
});

$run('incorrect, unknown, and inactive credentials have the same failure result', static function () use ($createUserDatabase, $expect): void {
    $pdo = $createUserDatabase();
    $service = new AuthenticationService(new UserRepository($pdo), new TestLoginAttemptLimiter());
    $wrong = $service->login('author one', 'wrong-password-123', '192.0.2.11');
    $unknown = $service->login('nobody here', 'wrong-password-123', '192.0.2.12');
    $inactive = $service->login('inactive one', 'inactive-pass-1234', '192.0.2.13');
    $expect($wrong->status === LoginResult::FAILURE && $unknown->status === LoginResult::FAILURE);
    $expect($inactive->status === LoginResult::FAILURE);
    $expect($wrong->account === null && $unknown->account === null && $inactive->account === null);
});

$run('login limiter counts failed attempts by normalized identifier and source address', static function () use ($expect): void {
    $limiter = new TestLoginAttemptLimiter();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $limiter->recordFailure('Shubham Banerjee', '192.0.2.20');
    }
    $expect($limiter->isLimited(' shubham banerjee ', '192.0.2.21'));

    $sourceLimiter = new TestLoginAttemptLimiter();
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $sourceLimiter->recordFailure('different-user-' . $attempt, '192.0.2.22');
    }
    $expect($sourceLimiter->isLimited('another-username', '192.0.2.22'));
});

$withSession = static function (callable $test): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $oldPath = session_save_path();
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'infinity-blog-session-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Could not create an isolated session test directory.');
    }
    session_save_path($directory);
    ini_set('session.use_cookies', '1');
    session_id('');

    try {
        $test($directory);
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        session_id('');
        session_save_path($oldPath);
        foreach (glob($directory . DIRECTORY_SEPARATOR . 'sess_*') ?: [] as $sessionFile) {
            if (is_file($sessionFile)) {
                unlink($sessionFile);
            }
        }
        rmdir($directory);
    }
};

$run('session ID regenerates on login, role guards read the account, and logout destroys access', static function () use ($withSession, $validConfiguration, $createUserDatabase, $expect): void {
    $withSession(static function () use ($validConfiguration, $createUserDatabase, $expect): void {
        $config = AppConfig::fromArray($validConfiguration);
        $pdo = $createUserDatabase();
        $users = new UserRepository($pdo);
        $sessions = new SessionManager($config);
        $sessions->start();
        $sessions->csrfToken();
        $cookieParams = session_get_cookie_params();
        $expect($cookieParams['httponly'] && strtolower((string) $cookieParams['samesite']) === 'lax' && !$cookieParams['secure'], json_encode($cookieParams));
        $expect((int) ini_get('session.gc_maxlifetime') >= 43200);
        $anonymousId = session_id();
        $account = $users->findByIdentifier('author one');
        if ($account === null) {
            throw new RuntimeException('Test account was not created.');
        }
        $sessions->establish($account);
        $authenticatedId = session_id();
        $expect($anonymousId !== $authenticatedId && $sessions->currentUserId() === $account->id);

        $guard = new AuthGuard($sessions, $users);
        $expect($guard->currentAccount()?->role === 'author');
        $expect(!$guard->isAdmin() && $guard->isAuthorOrAdmin());
        $sessions->logout();
        $expect($guard->currentAccount() === null);
    });
});

$run('administrator role guard permits an active admin account from the database', static function () use ($withSession, $validConfiguration, $createUserDatabase, $expect): void {
    $withSession(static function () use ($validConfiguration, $createUserDatabase, $expect): void {
        $sessions = new SessionManager(AppConfig::fromArray($validConfiguration));
        $sessions->start();
        $pdo = $createUserDatabase();
        $users = new UserRepository($pdo);
        $admin = $users->findByIdentifier('admin one');
        if ($admin === null) {
            throw new RuntimeException('Test admin account was not created.');
        }
        $sessions->establish($admin);
        $guard = new AuthGuard($sessions, $users);
        $expect($guard->isAdmin() && $guard->isAuthorOrAdmin());
    });
});

$run('HTTPS session cookies are Secure in production configuration', static function () use ($withSession, $validConfiguration, $expect): void {
    $withSession(static function () use ($validConfiguration, $expect): void {
        $production = $validConfiguration;
        $production['APP_ENV'] = 'production';
        $production['APP_DEBUG'] = 'false';
        $production['APP_BASE_URL'] = 'https://blog.example.invalid';
        $production['SESSION_SECURE_COOKIE'] = 'true';
        $production['DB_SSL_CA'] = __FILE__;
        $sessions = new SessionManager(AppConfig::fromArray($production));
        $sessions->start();
        $cookieParams = session_get_cookie_params();
        $expect($cookieParams['secure'] && $cookieParams['httponly']);
        $expect(strtolower((string) $cookieParams['samesite']) === 'lax');
    });
});

$run('expired sessions lose authorization and CSRF rejects missing or invalid tokens', static function () use ($withSession, $validConfiguration, $createUserDatabase, $expect): void {
    $withSession(static function () use ($validConfiguration, $createUserDatabase, $expect): void {
        $config = AppConfig::fromArray($validConfiguration);
        $pdo = $createUserDatabase();
        $users = new UserRepository($pdo);
        $sessions = new SessionManager($config);
        $sessions->start();
        $token = $sessions->csrfToken();
        $expect($sessions->validateCsrfToken($token));
        $expect(!$sessions->validateCsrfToken(null) && !$sessions->validateCsrfToken('invalid'));

        $account = $users->findByIdentifier('admin one');
        if ($account === null) {
            throw new RuntimeException('Test account was not created.');
        }
        $sessions->establish($account);
        $_SESSION['auth']['last_activity'] = time() - 1900;
        $guard = new AuthGuard($sessions, $users);
        $expect($guard->currentAccount() === null);
        $expect($sessions->currentUserId() === null);

        $activeAdmin = $users->findByIdentifier('admin one');
        if ($activeAdmin === null) {
            throw new RuntimeException('Test admin account was not created.');
        }
        $sessions->start();
        $sessions->establish($activeAdmin);
        $_SESSION['auth']['issued_at'] = time() - 43201;
        $expect($guard->currentAccount() === null);

        $inactive = $users->findByIdentifier('inactive one');
        if ($inactive === null) {
            throw new RuntimeException('Test inactive account was not created.');
        }
        $sessions->start();
        $sessions->establish($inactive);
        $expect($guard->currentAccount() === null);
    });
});

$run('auth controller rejects unauthenticated role escalation and public admin registration', static function () use ($withSession, $validConfiguration, $createUserDatabase, $expect): void {
    $withSession(static function () use ($validConfiguration, $createUserDatabase, $expect): void {
        $config = AppConfig::fromArray($validConfiguration);
        $pdo = $createUserDatabase();
        $users = new UserRepository($pdo);
        $sessions = new SessionManager($config);
        $sessions->start();
        $csrf = $sessions->csrfToken();
        $controller = new AuthController(
            new AuthenticationService($users, new TestLoginAttemptLimiter()),
            new AuthGuard($sessions, $users),
            $sessions
        );

        $missing = $controller->login(Request::fromValues('POST', '/auth/login', '192.0.2.40', ['X-CSRF-Token' => $csrf]));
        $expect($missing['status'] === 422);
        $badToken = $controller->login(Request::fromValues('POST', '/auth/login', '192.0.2.40', [], ['identifier' => 'author one', 'password' => 'author-pass-1234']));
        $expect($badToken['status'] === 403);
        $malformed = $controller->login(Request::fromValues('POST', '/auth/login', '192.0.2.40', ['X-CSRF-Token' => $csrf], ['identifier' => ['admin'], 'password' => 'author-pass-1234']));
        $expect($malformed['status'] === 422);

        $failedLogin = $controller->login(Request::fromValues(
            'POST',
            '/auth/login',
            '192.0.2.41',
            ['X-CSRF-Token' => $csrf],
            ['identifier' => 'author one', 'password' => 'wrong-password-123']
        ));
        $expect($failedLogin['status'] === 401 && $sessions->currentUserId() === null);

        $login = $controller->login(Request::fromValues(
            'POST',
            '/auth/login',
            '192.0.2.40',
            ['X-CSRF-Token' => $csrf],
            ['identifier' => 'author one', 'password' => 'author-pass-1234', 'role' => 'admin', 'user_id' => 2]
        ));
        $expect($login['status'] === 200 && $login['body']['data']['user']['role'] === 'author');

        $registration = $controller->handle(Request::fromValues('POST', '/auth/register', '192.0.2.40', [], ['role' => 'admin']));
        $expect($registration['status'] === 404);
        $expect($controller->logout(Request::fromValues('POST', '/auth/logout', '192.0.2.40'))['status'] === 403);
        $sessions->start();
        $expect($controller->logout(Request::fromValues('POST', '/auth/logout', '192.0.2.40', ['X-CSRF-Token' => 'not-a-token']))['status'] === 403);
        $logout = $controller->logout(Request::fromValues('POST', '/auth/logout', '192.0.2.40', ['X-CSRF-Token' => $sessions->csrfToken()]));
        $expect($logout['status'] === 200);
        $expect($controller->me()['status'] === 401);
    });
});

$run('initial administrators are provisioned with distinct hashes and duplicates are refused', static function () use ($expect): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE COLLATE NOCASE, ' .
        'display_name TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'active\', ' .
        'must_change_password INTEGER NOT NULL DEFAULT 0, last_login_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, ' .
        'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
    );
    $provisioner = new AdminProvisioner($pdo);
    $provisioner->provision('shubham-strong-password', 'sounak-different-password');
    $rows = $pdo->query('SELECT username, display_name, password_hash, role FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $expect(count($rows) === 2);
    $expect($rows[0]['username'] === 'shubham banerjee' && $rows[1]['username'] === 'sounak sikdar');
    $expect($rows[0]['role'] === 'admin' && $rows[1]['role'] === 'admin');
    $expect(password_verify('shubham-strong-password', $rows[0]['password_hash']));
    $expect($rows[0]['password_hash'] !== 'shubham-strong-password');
    $expect(!str_contains(json_encode($rows), 'shubham-strong-password'));

    $provisioner->rotatePassword('Shubham Banerjee', 'rotated-shubham-password');
    $rotatedHash = $pdo->query("SELECT password_hash FROM users WHERE username = 'shubham banerjee'")->fetchColumn();
    $expect(is_string($rotatedHash) && password_verify('rotated-shubham-password', $rotatedHash));
    $expect(!password_verify('shubham-strong-password', $rotatedHash));

    $duplicateWasRejected = false;
    try {
        $provisioner->provision('another-strong-password', 'another-distinct-password');
    } catch (RuntimeException $exception) {
        $duplicateWasRejected = true;
        $expect($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() == 2);
    }
    $expect($duplicateWasRejected, 'Duplicate administrator accounts must be refused.');
});

$run('administrator provisioning rejects duplicate passwords and unsafe configuration', static function () use ($validConfiguration, $expect, $expectInvalid): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec(
        'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, ' .
        'password_hash TEXT NOT NULL, role TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'active\', updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
    );
    try {
        (new AdminProvisioner($pdo))->provision('same-password-123', 'same-password-123');
    } catch (RuntimeException) {
        $expect((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
    }
    $oversizedRejected = false;
    try {
        (new AdminProvisioner($pdo))->provision(str_repeat('x', 73), 'another-strong-password');
    } catch (RuntimeException) {
        $oversizedRejected = true;
        $expect((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
    }
    $expect($oversizedRejected, 'Oversized passwords must be rejected before database changes.');

    $unconfiguredDatabase = new PDO('sqlite::memory:');
    $databaseFailureWasHidden = false;
    try {
        (new AdminProvisioner($unconfiguredDatabase))->provision('valid-shubham-password', 'valid-sounak-password');
    } catch (RuntimeException $exception) {
        $databaseFailureWasHidden = true;
        $expect($exception->getMessage() === 'Administrator provisioning failed.');
        $expect(!str_contains($exception->getMessage(), 'no such table'));
    }
    $expect($databaseFailureWasHidden, 'Provisioning must fail safely when the schema is unavailable.');

    $invalid = $validConfiguration;
    $invalid['APP_KEY'] = '';
    $expectInvalid(static fn () => AppConfig::fromArray($invalid));
});

$databaseTestFlag = getenv('INFINITY_BLOG_RUN_DB_TESTS');
$runDatabaseTests = $databaseTestFlag === '1';
$testDatabaseValues = [
    'TEST_DB_HOST' => getenv('TEST_DB_HOST'),
    'TEST_DB_PORT' => getenv('TEST_DB_PORT'),
    'TEST_DB_DATABASE' => getenv('TEST_DB_DATABASE'),
    'TEST_DB_USERNAME' => getenv('TEST_DB_USERNAME'),
    'TEST_DB_PASSWORD' => getenv('TEST_DB_PASSWORD'),
    'TEST_DB_SSL_CA' => getenv('TEST_DB_SSL_CA'),
];
$testConfig = null;
$testDatabaseError = null;
if ($databaseTestFlag !== false && !in_array($databaseTestFlag, ['', '0', '1'], true)) {
    $testDatabaseError = 'INFINITY_BLOG_RUN_DB_TESTS must be 1, 0, or unset.';
}
if ($runDatabaseTests && $testDatabaseError === null) {
    if (getenv('APP_ENV') !== 'test') {
        $testDatabaseError = 'APP_ENV must be test when MySQL integration tests are enabled.';
    }

    foreach (['TEST_DB_HOST', 'TEST_DB_PORT', 'TEST_DB_DATABASE', 'TEST_DB_USERNAME', 'TEST_DB_PASSWORD'] as $requiredKey) {
        if (!is_string($testDatabaseValues[$requiredKey]) || trim($testDatabaseValues[$requiredKey]) === '') {
            $testDatabaseError = 'All required TEST_DB_* settings must be explicitly configured.';
            break;
        }
    }

    if ($testDatabaseError === null) {
        $testDatabaseName = (string) $testDatabaseValues['TEST_DB_DATABASE'];
        if (preg_match('/^[A-Za-z0-9_]+_test$/i', $testDatabaseName) !== 1) {
            $testDatabaseError = 'TEST_DB_DATABASE must be a valid database name ending in _test.';
        } elseif (strcasecmp((string) $testDatabaseValues['TEST_DB_USERNAME'], 'root') === 0) {
            $testDatabaseError = 'MySQL integration tests require a dedicated non-root test account.';
        } elseif (is_string(getenv('DB_DATABASE')) && strcasecmp($testDatabaseName, (string) getenv('DB_DATABASE')) === 0) {
            $testDatabaseError = 'The test database name must differ from DB_DATABASE.';
        }
    }

    if ($testDatabaseError === null) {
        try {
            $testConfig = DatabaseConfig::fromArray(array_map('strval', $testDatabaseValues), 'TEST_DB_');
        } catch (Throwable) {
            $testDatabaseError = 'TEST_DB_* settings are invalid or remote TLS certificate configuration is missing.';
        }
    }
}

if (!$runDatabaseTests && $testDatabaseError === null) {
    echo "SKIP MySQL integration tests (set APP_ENV=test, INFINITY_BLOG_RUN_DB_TESTS=1, and dedicated TEST_DB_* settings to run).\n";
} elseif ($testDatabaseError !== null || !($testConfig instanceof DatabaseConfig)) {
    $failed++;
    echo 'FAIL MySQL integration test configuration: ' . ($testDatabaseError ?? 'configuration is invalid.') . "\n";
} else {
    $pdo = null;
    $migrationRunner = null;
    $migrationReady = false;

    $run('MySQL clean test database accepts connection and versioned migrations rerun safely', static function () use ($testConfig, &$pdo, &$migrationRunner, &$migrationReady, $expect): void {
        $pdo = ConnectionFactory::create($testConfig);
        $tableCount = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $expect($tableCount === 0, 'The dedicated *_test database must be empty before destructive rollback integration tests.');

        $migrationRunner = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');
        $migrationRunner->migrate();
        $migrationRunner->migrate();
        $expect(in_array('202610090001_create_cms_schema', $migrationRunner->appliedMigrations(), true));
        $expect(in_array('202610090002_create_login_rate_limits', $migrationRunner->appliedMigrations(), true));
        $indexes = $pdo->query(
            "SELECT CONCAT(table_name, ':', index_name) FROM information_schema.statistics WHERE table_schema = DATABASE()"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ([
            'posts:idx_posts_author_status',
            'posts:idx_posts_status_published',
            'posts:idx_posts_category_status',
            'audit_logs:idx_audit_logs_actor_created',
            'audit_logs:idx_audit_logs_entity',
            'audit_logs:idx_audit_logs_action_created',
            'login_rate_limits:idx_login_rate_limits_expiry',
        ] as $expectedIndex) {
            $expect(in_array($expectedIndex, $indexes, true), 'A documented index is missing.');
        }
        $migrationReady = true;
    });

    if ($pdo instanceof PDO && $migrationReady) {
        $run('MySQL constraints enforce keys, roles, statuses, and category deactivation policy', static function () use ($pdo, $expect): void {
        $token = bin2hex(random_bytes(6));
        $username = 'phase1-' . $token;
        $userId = null;
        $categoryId = null;
        $postId = null;

        $expectFailure = static function (callable $operation) use ($expect): void {
            try {
                $operation();
            } catch (PDOException $exception) {
                return;
            }
            $expect(false, 'Expected a database constraint to reject the operation.');
        };

        try {
            $insertUser = $pdo->prepare('INSERT INTO users (username, display_name, password_hash, role) VALUES (:username, :display_name, :password_hash, :role)');
            $insertUser->execute([
                'username' => $username,
                'display_name' => 'Phase 1 Integration Test',
                'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                'role' => 'admin',
            ]);
            $userId = (int) $pdo->lastInsertId();
            $expect($userId > 0);

            $expectFailure(static function () use ($pdo, $username, $userId): void {
                $statement = $pdo->prepare('INSERT INTO users (id, username, display_name, password_hash, role) VALUES (:id, :username, :display_name, :password_hash, :role)');
                $statement->execute([
                    'id' => $userId,
                    'username' => 'duplicate-primary-' . $username,
                    'display_name' => 'Duplicate Primary Key',
                    'password_hash' => password_hash('synthetic-only', PASSWORD_DEFAULT),
                    'role' => 'admin',
                ]);
            });

            $expectFailure(static function () use ($insertUser, $username): void {
                $insertUser->execute([
                    'username' => strtoupper($username),
                    'display_name' => 'Duplicate Login',
                    'password_hash' => password_hash('synthetic-only', PASSWORD_DEFAULT),
                    'role' => 'author',
                ]);
            });
            $expectFailure(static function () use ($insertUser, $token): void {
                $insertUser->execute([
                    'username' => 'invalid-role-' . $token,
                    'display_name' => 'Invalid Role',
                    'password_hash' => password_hash('synthetic-only', PASSWORD_DEFAULT),
                    'role' => 'superuser',
                ]);
            });
            $expectFailure(static function () use ($pdo, $token): void {
                $statement = $pdo->prepare('INSERT INTO users (username, display_name, password_hash, role) VALUES (:username, :display_name, :password_hash, :role)');
                $statement->execute([
                    'username' => 'null-password-' . $token,
                    'display_name' => 'Missing Password Hash',
                    'password_hash' => null,
                    'role' => 'author',
                ]);
            });
            $expectFailure(static function () use ($pdo, $username): void {
                $statement = $pdo->prepare("UPDATE users SET status = 'locked' WHERE username = :username");
                $statement->execute(['username' => $username]);
            });

            $insertCategory = $pdo->prepare('INSERT INTO categories (name, slug, created_by) VALUES (:name, :slug, :created_by)');
            $insertCategory->execute([
                'name' => 'Integration ' . $token,
                'slug' => 'integration-' . $token,
                'created_by' => $userId,
            ]);
            $categoryId = (int) $pdo->lastInsertId();
            $expectFailure(static function () use ($insertCategory, $token, $userId): void {
                $insertCategory->execute([
                    'name' => 'integration ' . $token,
                    'slug' => 'duplicate-name-' . $token,
                    'created_by' => $userId,
                ]);
            });
            $expectFailure(static function () use ($pdo, $categoryId): void {
                $statement = $pdo->prepare('UPDATE categories SET is_active = 2 WHERE id = :id');
                $statement->execute(['id' => $categoryId]);
            });
            $expectFailure(static function () use ($insertCategory, $token, $userId): void {
                $insertCategory->execute([
                    'name' => 'Duplicate Category ' . $token,
                    'slug' => strtoupper('integration-' . $token),
                    'created_by' => $userId,
                ]);
            });

            $insertPost = $pdo->prepare('INSERT INTO posts (author_id, category_id, title, content) VALUES (:author_id, :category_id, :title, :content)');
            $insertPost->execute([
                'author_id' => $userId,
                'category_id' => $categoryId,
                'title' => 'Synthetic migration test',
                'content' => 'Test content only.',
            ]);
            $postId = (int) $pdo->lastInsertId();
            $expectFailure(static function () use ($insertPost, $userId): void {
                $insertPost->execute([
                    'author_id' => $userId,
                    'category_id' => 0,
                    'title' => 'Invalid category reference',
                    'content' => 'Test content only.',
                ]);
            });
            $expectFailure(static function () use ($insertPost, $categoryId): void {
                $insertPost->execute([
                    'author_id' => 0,
                    'category_id' => $categoryId,
                    'title' => 'Invalid author reference',
                    'content' => 'Test content only.',
                ]);
            });
            $expectFailure(static function () use ($pdo, $postId): void {
                $statement = $pdo->prepare("UPDATE posts SET status = 'scheduled' WHERE id = :id");
                $statement->execute(['id' => $postId]);
            });
            $expectFailure(static function () use ($pdo, $postId): void {
                $statement = $pdo->prepare("UPDATE posts SET summary_status = 'queued' WHERE id = :id");
                $statement->execute(['id' => $postId]);
            });

            $deactivate = $pdo->prepare('UPDATE categories SET is_active = 0 WHERE id = :id');
            $deactivate->execute(['id' => $categoryId]);
            $postCategory = $pdo->prepare('SELECT category_id FROM posts WHERE id = :id');
            $postCategory->execute(['id' => $postId]);
            $expect((int) $postCategory->fetchColumn() === $categoryId);

            $expectFailure(static function () use ($pdo, $categoryId): void {
                $statement = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                $statement->execute(['id' => $categoryId]);
            });
        } finally {
            if ($postId !== null) {
                $statement = $pdo->prepare('DELETE FROM posts WHERE id = :id');
                $statement->execute(['id' => $postId]);
            }
            if ($categoryId !== null) {
                $statement = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                $statement->execute(['id' => $categoryId]);
            }
            if ($userId !== null) {
                $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $statement->execute(['id' => $userId]);
            }
        }
        });

        $run('MySQL-backed login limiter enforces account and source limits and clears successful accounts', static function () use ($pdo, $expect): void {
            $key = str_repeat('b', 64);
            $limiter = new \InfinityBlog\Auth\DatabaseLoginAttemptLimiter($pdo, $key);
            $identifier = 'integration-user-' . bin2hex(random_bytes(6));
            $source = '198.51.100.41';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $limiter->recordFailure($identifier, $source);
            }
            $expect($limiter->isLimited(strtoupper($identifier), '198.51.100.42'));
            $limiter->recordSuccess($identifier);
            $expect(!$limiter->isLimited($identifier, '198.51.100.42'));

            $sourceLimited = '198.51.100.43';
            for ($attempt = 0; $attempt < 20; $attempt++) {
                $limiter->recordFailure('other-' . $attempt . '-' . bin2hex(random_bytes(2)), $sourceLimited);
            }
            $expect($limiter->isLimited('brand-new-identifier', $sourceLimited));
            $sourceHash = hash_hmac('sha256', "source\0" . $sourceLimited, $key);
            $expire = $pdo->prepare('UPDATE login_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE bucket_hash = :hash');
            $expire->execute(['hash' => $sourceHash]);
            $expect(!$limiter->isLimited('brand-new-identifier', $sourceLimited));
            $left = $pdo->prepare('SELECT COUNT(*) FROM login_rate_limits WHERE bucket_hash = :hash');
            $left->execute(['hash' => $sourceHash]);
            $expect((int) $left->fetchColumn() === 0, 'Expired MySQL limiter state should be pruned.');

            $globalHash = hash_hmac('sha256', "global\0all", $key);
            $global = $pdo->prepare(
                'INSERT INTO login_rate_limits (bucket_hash, bucket_type, failed_attempts, window_started_at, expires_at, updated_at) ' .
                'VALUES (:hash, \'global\', 100000, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), UTC_TIMESTAMP())'
            );
            $global->execute(['hash' => $globalHash]);
            $expect($limiter->isLimited('any-user', '198.51.100.44'));
            $expire->execute(['hash' => $globalHash]);
            $expect(!$limiter->isLimited('any-user', '198.51.100.44'));
        });

        $run('MySQL rollback requires confirmation and restores only the empty disposable test schema', static function () use ($pdo, $migrationRunner, $expect): void {
            $cmsTables = ['users', 'categories', 'posts', 'audit_logs'];
            $countCmsTables = static function () use ($pdo, $cmsTables): int {
                $statement = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (?, ?, ?, ?)'
                );
                $statement->execute($cmsTables);
                return (int) $statement->fetchColumn();
            };

            $expect($countCmsTables() === 4, 'Expected the initial migration tables before rollback verification.');
            try {
                $migrationRunner->rollbackLast(false);
            } catch (InvalidArgumentException $exception) {
                $expect($countCmsTables() === 4, 'Unconfirmed rollback must leave all schema tables intact.');
                $rolledBack = $migrationRunner->rollbackLast(true);
                $expect($rolledBack === '202610090002_create_login_rate_limits');
                $rateLimitTables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'login_rate_limits'")->fetchColumn();
                $expect($rateLimitTables === 0, 'The rate-limit table should be removed by its guarded rollback.');
                $rolledBack = $migrationRunner->rollbackLast(true);
                $expect($rolledBack === '202610090001_create_cms_schema');
                $expect($countCmsTables() === 0, 'Confirmed rollback should remove the initial migration tables.');

                $migrationRunner->migrate();
                $expect($countCmsTables() === 4, 'The schema must be reproducible after rollback.');
                $expect(in_array('202610090001_create_cms_schema', $migrationRunner->appliedMigrations(), true));
                $expect(in_array('202610090002_create_login_rate_limits', $migrationRunner->appliedMigrations(), true));
                return;
            }

            $expect(false, 'Rollback without confirmation must be rejected.');
        });
    }
}

echo "\n{$passed} passed, {$failed} failed.\n";
ob_end_flush();
exit($failed === 0 ? 0 : 1);
