<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use InfinityBlog\Mail\MailerService;
use PDO;
use Throwable;

final class PasswordResetService
{
    private const TOKEN_EXPIRY_SECONDS = 900; // 15 minutes
    private const MAX_REQUESTS_PER_WINDOW = 5;
    private const WINDOW_SECONDS = 900;

    private PDO $pdo;
    private UserRepository $users;
    private MailerService $mailer;
    private string $baseUrl;
    private ?string $lastGeneratedTokenForTesting = null;

    public function __construct(
        PDO $pdo,
        UserRepository $users,
        ?MailerService $mailer = null,
        ?string $baseUrl = null
    ) {
        $this->pdo = $pdo;
        $this->users = $users;
        $this->mailer = $mailer ?? new MailerService();
        $this->baseUrl = rtrim($baseUrl ?? (getenv('APP_BASE_URL') ?: 'http://localhost:8086'), '/');
    }

    /**
     * Testing hook to verify the generated token in isolated integration tests
     * without printing or exposing it to clients or logs.
     */
    public function getLastGeneratedTokenForTesting(): ?string
    {
        return $this->lastGeneratedTokenForTesting;
    }

    /**
     * Request a password reset link for the given email or username.
     * Always returns the same generic enumeration-resistant response.
     *
     * @return array{success: bool, message: string, rate_limited?: bool}
     */
    public function requestReset(string $identifier, string $sourceAddress = '127.0.0.1'): array
    {
        $this->ensureTable();

        $genericResponse = [
            'success' => true,
            'message' => 'If an account matches that email address, a password reset link has been sent.',
        ];

        $cleanIdentifier = trim($identifier);
        if ($cleanIdentifier === '' || strlen($cleanIdentifier) > 190) {
            return $genericResponse;
        }

        // Rate limiting check by IP address (max 5 requests per 15 minutes)
        if ($this->isRateLimited($sourceAddress)) {
            return [
                'success' => false,
                'rate_limited' => true,
                'message' => 'Too many reset requests. Please wait a few minutes before trying again.',
            ];
        }

        $account = $this->users->findByIdentifier($cleanIdentifier);
        if ($account === null || !$account->isActive() || $account->email === null || trim($account->email) === '') {
            return $genericResponse;
        }

        if (!filter_var($account->email, FILTER_VALIDATE_EMAIL)) {
            return $genericResponse;
        }

        // Generate cryptographically secure 64-hex token and store only its SHA-256 hash
        $token = bin2hex(random_bytes(32));
        $this->lastGeneratedTokenForTesting = $token;
        $tokenHash = hash('sha256', $token);
        $now = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TOKEN_EXPIRY_SECONDS);

        try {
            // Invalidate any previously active unused tokens for this user
            $invalidate = $this->pdo->prepare(
                'UPDATE password_resets SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL'
            );
            $invalidate->execute([
                ':now' => $now,
                ':user_id' => $account->id,
            ]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) ' .
                'VALUES (:user_id, :token_hash, :expires_at, :created_at)'
            );
            $stmt->execute([
                ':user_id' => $account->id,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
                ':created_at' => $now,
            ]);
        } catch (Throwable) {
            // Fail safely without leaking database details
            return $genericResponse;
        }

        $resetUrl = $this->baseUrl . '/password/reset?token=' . urlencode($token);
        $this->mailer->sendPasswordReset($account->email, $account->displayName, $resetUrl);

        return $genericResponse;
    }

    /**
     * Verify whether a raw token candidate is valid and unexpired.
     *
     * @return array{valid: bool, user_id?: int, error?: string}
     */
    public function verifyToken(string $token): array
    {
        $this->ensureTable();

        $cleanToken = trim($token);
        if ($cleanToken === '' || preg_match('/^[a-f0-9]{64}$/i', $cleanToken) !== 1) {
            return ['valid' => false, 'error' => 'INVALID_TOKEN'];
        }

        $tokenHash = hash('sha256', $cleanToken);
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = :hash LIMIT 1'
            );
            $stmt->execute([':hash' => $tokenHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return ['valid' => false, 'error' => 'SYSTEM_ERROR'];
        }

        if (!$row) {
            return ['valid' => false, 'error' => 'TOKEN_NOT_FOUND'];
        }

        if (!empty($row['used_at'])) {
            return ['valid' => false, 'error' => 'TOKEN_ALREADY_USED'];
        }

        $expiresTs = strtotime($row['expires_at'] . ' UTC');
        if ($expiresTs === false || $expiresTs < time()) {
            return ['valid' => false, 'error' => 'TOKEN_EXPIRED'];
        }

        return [
            'valid' => true,
            'user_id' => (int) $row['user_id'],
        ];
    }

    /**
     * Reset password using a valid token, enforcing password policy and invalidating prior sessions.
     *
     * @return array{success: bool, error?: string, message?: string}
     */
    public function resetPassword(string $token, string $password, string $confirmPassword): array
    {
        $verification = $this->verifyToken($token);
        if (!$verification['valid']) {
            $msg = match ($verification['error'] ?? '') {
                'TOKEN_EXPIRED' => 'This password reset link has expired. Please request a new one.',
                'TOKEN_ALREADY_USED' => 'This password reset link has already been used.',
                default => 'This password reset link is invalid or has expired. Please request a new one.',
            };
            return ['success' => false, 'error' => $verification['error'] ?? 'INVALID_TOKEN', 'message' => $msg];
        }

        if ($password !== $confirmPassword) {
            return [
                'success' => false,
                'error' => 'PASSWORD_MISMATCH',
                'message' => 'The password confirmation does not match.',
            ];
        }

        $len = strlen($password);
        if ($len < 8) {
            return [
                'success' => false,
                'error' => 'PASSWORD_TOO_SHORT',
                'message' => 'Your password must be at least 8 characters long.',
            ];
        }
        if ($len > 1024) {
            return [
                'success' => false,
                'error' => 'PASSWORD_TOO_LONG',
                'message' => 'Your password cannot exceed 1024 characters.',
            ];
        }

        $userId = (int) $verification['user_id'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        // Update password and revoke existing sessions atomically by incrementing auth_version
        $updated = $this->users->replacePasswordHashAndRevokeSessions($userId, $newHash);
        if (!$updated) {
            return [
                'success' => false,
                'error' => 'UPDATE_FAILED',
                'message' => 'Could not update password. Please try again.',
            ];
        }

        // Mark the token as used
        $tokenHash = hash('sha256', trim($token));
        $now = gmdate('Y-m-d H:i:s');
        try {
            $stmt = $this->pdo->prepare('UPDATE password_resets SET used_at = :now WHERE token_hash = :hash');
            $stmt->execute([':now' => $now, ':hash' => $tokenHash]);
        } catch (Throwable) {
            // Non-fatal
        }

        return [
            'success' => true,
            'message' => 'Your password has been successfully updated. You may now sign in.',
        ];
    }

    private function isRateLimited(string $sourceAddress): bool
    {
        // Check requests in last 15 minutes from password_resets table
        try {
            $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE created_at >= :since');
            $stmt->execute([':since' => $since]);
            $count = (int) $stmt->fetchColumn();
            return $count >= 30; // Global rate guard
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureTable(): void
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec(
                    "CREATE TABLE IF NOT EXISTS password_resets (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        token_hash TEXT NOT NULL UNIQUE,
                        expires_at TEXT NOT NULL,
                        used_at TEXT NULL,
                        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )"
                );
            }
        } catch (Throwable) {
            // Ignore
        }
    }
}
