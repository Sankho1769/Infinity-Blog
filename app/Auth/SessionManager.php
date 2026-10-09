<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use InfinityBlog\Config\AppConfig;
use RuntimeException;

final class SessionManager
{
    private const IDLE_TIMEOUT_SECONDS = 1800;
    private const ABSOLUTE_TIMEOUT_SECONDS = 43200;
    private const SESSION_NAME = 'INFINITYBLOGSESSID';

    private AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            throw new RuntimeException('Session could not be started.');
        }

        foreach ([
            'session.use_strict_mode' => '1',
            'session.use_only_cookies' => '1',
            'session.use_trans_sid' => '0',
            'session.cookie_httponly' => '1',
            'session.gc_maxlifetime' => (string) self::ABSOLUTE_TIMEOUT_SECONDS,
        ] as $setting => $value) {
            if (ini_set($setting, $value) === false || ini_get($setting) !== $value) {
                throw new RuntimeException('Secure session settings are unavailable.');
            }
        }
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->cookiePath(),
            'secure' => $this->usesHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Session could not be started.');
        }
    }

    public function establish(UserAccount $account): void
    {
        $this->requireStarted();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Authenticated session could not be established.');
        }

        $now = time();
        $_SESSION = [
            'auth' => [
                'user_id' => $account->id,
                'auth_version' => $account->authVersion,
                'issued_at' => $now,
                'last_activity' => $now,
            ],
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
    }

    public function currentUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth)) {
            return null;
        }
        $id = $auth['user_id'] ?? null;

        return is_int($id) && $id > 0 ? $id : null;
    }

    public function currentAuthVersion(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth)) {
            return null;
        }
        $version = $auth['auth_version'] ?? null;

        return is_int($version) && $version > 0 ? $version : null;
    }

    public function isExpired(): bool
    {
        $this->requireStarted();
        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth)) {
            return false;
        }

        $issuedAt = $auth['issued_at'] ?? null;
        $lastActivity = $auth['last_activity'] ?? null;
        if (!is_int($issuedAt) || !is_int($lastActivity)) {
            return true;
        }
        $now = time();

        return $issuedAt > $now || $lastActivity > $now
            || $now - $lastActivity > self::IDLE_TIMEOUT_SECONDS
            || $now - $issuedAt > self::ABSOLUTE_TIMEOUT_SECONDS;
    }

    public function touch(): void
    {
        $this->requireStarted();
        if (isset($_SESSION['auth']) && is_array($_SESSION['auth'])) {
            $_SESSION['auth']['last_activity'] = time();
        }
    }

    public function clearAuthentication(): void
    {
        $this->requireStarted();
        unset($_SESSION['auth']);
        $this->rotateCsrfToken();
    }

    public function csrfToken(): string
    {
        $this->requireStarted();
        $token = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }

    public function validateCsrfToken(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '' || strlen($candidate) > 128) {
            return false;
        }

        return hash_equals($this->csrfToken(), $candidate);
    }

    public function logout(): void
    {
        $this->requireStarted();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        if (!session_destroy()) {
            throw new RuntimeException('Session could not be destroyed.');
        }
    }

    private function rotateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private function cookiePath(): string
    {
        $path = parse_url($this->config->baseUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/') . '/';
    }

    private function usesHttps(): bool
    {
        $configuredScheme = parse_url($this->config->baseUrl, PHP_URL_SCHEME);
        $requestHttps = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        return $this->config->secureSessionCookie || $configuredScheme === 'https' || $requestHttps;
    }

    private function requireStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session is not active.');
        }
    }
}
