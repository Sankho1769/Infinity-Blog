<?php
declare(strict_types=1);

namespace InfinityBlog\Core;

use InfinityBlog\Config\Database;

class SessionManager {
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        session_set_cookie_params([
            'lifetime' => 86400, // 24 hours
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('INFINITY_SESSID');
        session_start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function getCsrfToken(): string {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool {
        self::start();
        $stored = $_SESSION['csrf_token'] ?? '';
        if (!$token || !$stored) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public static function requireCsrf(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $key => $val) {
                    if (strtolower($key) === 'x-csrf-token') {
                        $token = $val;
                        break;
                    }
                }
            }
        }

        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['_csrf_token'] ?? $_POST['_csrf_token'] ?? null;
        }

        if (!self::validateCsrfToken($token)) {
            Response::error('CSRF_INVALID', 'A valid CSRF token is required for this action.', null, 403);
        }
    }

    public static function loginUser(array $user): void {
        self::start();
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'display_name' => (string)$user['display_name'],
            'email' => $user['email'] ?? null,
            'role' => (string)$user['role'],
        ];

        // Refresh CSRF token on session regeneration
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }

    public static function getCurrentUser(): ?array {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            Response::error('UNAUTHORIZED', 'Authentication required.', null, 401);
        }

        // Verify account is still active in database
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT `id`, `role`, `status` FROM `users` WHERE `id` = :id LIMIT 1");
            $stmt->execute([':id' => $user['id']]);
            $dbUser = $stmt->fetch();

            if (!$dbUser || $dbUser['status'] !== 'active') {
                self::logout();
                Response::error('ACCOUNT_INACTIVE', 'This account has been deactivated.', null, 403);
            }

            // Keep session role synchronized
            if ($dbUser['role'] !== $user['role']) {
                $_SESSION['user']['role'] = $dbUser['role'];
                $user['role'] = $dbUser['role'];
            }
        } catch (\Throwable $e) {
            error_log("SessionManager status check error: " . $e->getMessage());
        }

        return $user;
    }

    public static function requireRole(string ...$allowedRoles): array {
        $user = self::requireAuth();
        if (!in_array($user['role'], $allowedRoles, true)) {
            Response::error('FORBIDDEN', 'You do not have permission to access this resource.', null, 403);
        }
        return $user;
    }
}
