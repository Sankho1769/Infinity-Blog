<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use InfinityBlog\Mail\MailerService;
use PDO;
use RuntimeException;
use Throwable;

final class LoginChallengeService
{
    private PDO $pdo;
    private MailerService $mailer;
    private string $secretKey;

    public function __construct(PDO $pdo, ?MailerService $mailer = null, ?string $secretKey = null)
    {
        $this->pdo = $pdo;
        $this->mailer = $mailer ?? new MailerService();
        $this->secretKey = $secretKey ?? self::resolveSecretKey();
    }

    private static function resolveSecretKey(): string
    {
        $appKey = getenv('APP_KEY') ?: '';
        if (preg_match('/^[a-f0-9]{64}$/i', $appKey) === 1) {
            return hash_hkdf('sha256', (string) hex2bin($appKey), 32, 'auth-challenge-secret', 'infinity-blog-challenge-v1');
        }
        return hash('sha256', 'infinity-blog-challenge-fallback-secret-2026', true);
    }

    /**
     * Determine if an account requires an email challenge.
     * Accounts with null email (such as initial admins or unconfigured users)
     * cannot receive email OTPs and follow the recovery path.
     */
    public function shouldChallenge(UserAccount $account): bool
    {
        if ($account->email === null || trim($account->email) === '') {
            return false;
        }

        // Feature toggle (defaults to true)
        $toggle = getenv('LOGIN_EMAIL_CHALLENGE');
        if ($toggle === '0' || $toggle === 'false') {
            return false;
        }

        return true;
    }

    /**
     * Ensure the auth_challenges table exists (idempotent for SQLite and dynamic environments).
     */
    private function ensureTable(): void
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec(
                    "CREATE TABLE IF NOT EXISTS auth_challenges (
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
                    )"
                );
            }
        } catch (Throwable) {
            // Tolerate errors
        }
    }

    /**
     * Create and dispatch a 6-digit numeric login verification challenge.
     *
     * @return array{
     *     challenge_required: bool,
     *     challenge_token: string,
     *     masked_email: string,
     *     expires_in: int,
     *     delivery_status: string,
     *     dev_code?: string|null
     * }
     */
    public function createChallenge(UserAccount $account, string $sourceAddress = '127.0.0.1'): array
    {
        $this->ensureTable();

        if ($account->email === null || trim($account->email) === '') {
            throw new RuntimeException('Cannot create email challenge for account with null email.');
        }

        // Generate cryptographically secure 6-digit numeric OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $otpHash = hash_hmac('sha256', $otp, $this->secretKey);
        $challengeToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        $createdAt = date('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auth_challenges (user_id, challenge_token, otp_hash, destination_email, attempts, max_attempts, expires_at, created_at) ' .
                'VALUES (:user_id, :challenge_token, :otp_hash, :destination_email, 0, 5, :expires_at, :created_at)'
            );
            $stmt->execute([
                ':user_id' => $account->id,
                ':challenge_token' => $challengeToken,
                ':otp_hash' => $otpHash,
                ':destination_email' => $account->email,
                ':expires_at' => $expiresAt,
                ':created_at' => $createdAt,
            ]);
        } catch (Throwable $e) {
            // If table insertion fails, store in session
            $_SESSION['pending_auth_challenge'] = [
                'user_id' => $account->id,
                'challenge_token' => $challengeToken,
                'otp_hash' => $otpHash,
                'destination_email' => $account->email,
                'attempts' => 0,
                'max_attempts' => 5,
                'expires_at' => $expiresAt,
            ];
        }

        // Send notification email
        $maskedEmail = MailerService::maskEmail($account->email);
        $mailResult = $this->mailer->sendOtp($account->email, $account->displayName, $otp);

        $isDev = getenv('APP_ENV') !== 'production';

        return [
            'challenge_required' => true,
            'challenge_token' => $challengeToken,
            'masked_email' => $maskedEmail,
            'expires_in' => 600,
            'delivery_status' => $mailResult['status'],
            'dev_code' => $isDev ? $otp : null,
        ];
    }

    /**
     * Verify submitted 6-digit OTP code against the challenge token.
     *
     * @return array{success: bool, user_id?: int, error?: string, message?: string, remaining_attempts?: int}
     */
    public function verifyChallenge(string $challengeToken, string $otp): array
    {
        $this->ensureTable();

        $otpClean = trim($otp);
        if ($otpClean === '' || preg_match('/^\d{6}$/', $otpClean) !== 1) {
            return [
                'success' => false,
                'error' => 'INVALID_FORMAT',
                'message' => 'Please enter a valid 6-digit numeric verification code.',
            ];
        }

        $row = null;
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM auth_challenges WHERE challenge_token = :token LIMIT 1');
            $stmt->execute([':token' => $challengeToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $row = $_SESSION['pending_auth_challenge'] ?? null;
        }

        if (!$row && isset($_SESSION['pending_auth_challenge']) && $_SESSION['pending_auth_challenge']['challenge_token'] === $challengeToken) {
            $row = $_SESSION['pending_auth_challenge'];
        }

        if (!$row) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_NOT_FOUND',
                'message' => 'The verification challenge was not found or has expired. Please sign in again.',
            ];
        }

        if (!empty($row['verified_at'])) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_ALREADY_USED',
                'message' => 'This verification challenge has already been completed.',
            ];
        }

        $expiresTs = strtotime($row['expires_at'] ?? '');
        if ($expiresTs === false || $expiresTs < time()) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_EXPIRED',
                'message' => 'This verification code has expired. Please request a new code or sign in again.',
            ];
        }

        $attempts = (int) ($row['attempts'] ?? 0);
        $maxAttempts = (int) ($row['max_attempts'] ?? 5);

        if ($attempts >= $maxAttempts) {
            return [
                'success' => false,
                'error' => 'MAX_ATTEMPTS_EXCEEDED',
                'message' => 'Too many failed verification attempts. Please sign in again.',
                'remaining_attempts' => 0,
            ];
        }

        // Increment attempts count
        $newAttempts = $attempts + 1;
        try {
            $updateAttempts = $this->pdo->prepare('UPDATE auth_challenges SET attempts = :attempts WHERE challenge_token = :token');
            $updateAttempts->execute([':attempts' => $newAttempts, ':token' => $challengeToken]);
        } catch (Throwable) {
            if (isset($_SESSION['pending_auth_challenge'])) {
                $_SESSION['pending_auth_challenge']['attempts'] = $newAttempts;
            }
        }

        $computedHash = hash_hmac('sha256', $otpClean, $this->secretKey);
        if (!hash_equals((string) $row['otp_hash'], $computedHash)) {
            $remaining = max(0, $maxAttempts - $newAttempts);
            return [
                'success' => false,
                'error' => 'INVALID_CODE',
                'message' => $remaining > 0
                    ? "Incorrect verification code. {$remaining} attempts remaining."
                    : 'Incorrect code. Maximum attempts exceeded. Please sign in again.',
                'remaining_attempts' => $remaining,
            ];
        }

        // Successfully verified: mark in database and clear session challenge
        $now = date('Y-m-d H:i:s');
        try {
            $markVerified = $this->pdo->prepare('UPDATE auth_challenges SET verified_at = :now WHERE challenge_token = :token');
            $markVerified->execute([':now' => $now, ':token' => $challengeToken]);
        } catch (Throwable) {
            // Ignore
        }
        unset($_SESSION['pending_auth_challenge']);

        return [
            'success' => true,
            'user_id' => (int) $row['user_id'],
        ];
    }
}
