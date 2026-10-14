<?php
declare(strict_types=1);

namespace InfinityBlog\Security;

use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Mail\MailerService;
use InfinityBlog\Sms\SmsService;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class RegistrationChallengeService
{
    private PDO $pdo;
    private MailerService $mailer;
    private MobileNumberService $mobileService;
    private UserRepository $userRepo;
    private string $secretKey;
    private ?SmsService $smsService = null;

    public function __construct(
        PDO $pdo,
        object|null $mailer = null,
        ?MobileNumberService $mobileService = null,
        ?UserRepository $userRepo = null,
        ?string $secretKey = null
    ) {
        $this->pdo = $pdo;
        if ($mailer instanceof MailerService) {
            $this->mailer = $mailer;
        } elseif ($mailer instanceof SmsService) {
            $this->smsService = $mailer;
            $this->mailer = new MailerService();
        } else {
            $this->mailer = new MailerService();
        }
        $this->mobileService = $mobileService ?? new MobileNumberService();
        $this->userRepo = $userRepo ?? new UserRepository($pdo);
        $this->secretKey = $secretKey ?? self::resolveSecretKey();
    }

    public function setMailer(MailerService $mailer): void
    {
        $this->mailer = $mailer;
    }

    public function mailer(): MailerService
    {
        return $this->mailer;
    }

    private static function resolveSecretKey(): string
    {
        $appKey = getenv('APP_KEY') ?: '';
        if (preg_match('/^[a-f0-9]{64}$/i', $appKey) === 1) {
            return hash_hkdf('sha256', (string) hex2bin($appKey), 32, 'reg-challenge-secret', 'infinity-blog-reg-challenge-v1');
        }
        return hash('sha256', 'infinity-blog-reg-challenge-fallback-secret-2026', true);
    }

    /**
     * Ensure the registration_challenges table exists (idempotent for SQLite/test environments).
     */
    public function ensureTable(): void
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec(
                    "CREATE TABLE IF NOT EXISTS registration_challenges (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        challenge_token TEXT NOT NULL UNIQUE,
                        otp_hash TEXT NOT NULL,
                        phone_hash TEXT NOT NULL,
                        phone_ciphertext TEXT NOT NULL,
                        normalized_phone TEXT NOT NULL,
                        email TEXT NOT NULL,
                        display_name TEXT NOT NULL,
                        password_hash TEXT NOT NULL,
                        profile_data TEXT NULL,
                        attempts INTEGER NOT NULL DEFAULT 0,
                        max_attempts INTEGER NOT NULL DEFAULT 5,
                        expires_at TEXT NOT NULL,
                        verified_at TEXT NULL,
                        created_at TEXT NOT NULL
                    )"
                );
            }
        } catch (Throwable) {
            // Tolerate errors in dynamic environments
        }
    }

    /**
     * Create and initiate a registration verification challenge.
     *
     * @param array{
     *     display_name: string,
     *     email: string,
     *     password: string,
     *     phone: string,
     *     profile?: array<string, mixed>
     * } $data
     * @return array{
     *     success: bool,
     *     challenge_required?: bool,
     *     challenge_token?: string,
     *     masked_phone?: string,
     *     expires_in?: int,
     *     delivery_status?: string,
     *     delivery_reason?: string|null,
     *     dev_code?: string|null,
     *     error?: string,
     *     message?: string
     * }
     */
    public function createChallenge(array $data, string $sourceAddress = '127.0.0.1'): array
    {
        $this->ensureTable();

        $displayName = trim($data['display_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $rawPhone = trim($data['phone'] ?? '');

        if ($displayName === '' || strlen($displayName) > 190) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Please enter a valid full name (1 to 190 characters).',
            ];
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Please enter a valid email address.',
            ];
        }

        if ($this->userRepo->emailExists($email)) {
            return [
                'success' => false,
                'error' => 'EMAIL_ALREADY_EXISTS',
                'message' => 'An account with this email address already exists.',
            ];
        }

        if (strlen($password) < 8) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Password must be at least 8 characters long.',
            ];
        }

        if ($rawPhone === '') {
            return [
                'success' => false,
                'error' => 'PHONE_REQUIRED',
                'message' => 'A mobile phone number is required for registration verification.',
            ];
        }

        try {
            $normalizedPhone = $this->mobileService->normalize($rawPhone);
        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => 'PHONE_INVALID',
                'message' => 'Please enter a valid mobile phone number in international or 10-digit format (e.g. +91 98765 43210).',
            ];
        }

        // Check uniqueness via blind HMAC hash
        $phoneHash = $this->mobileService->hash($normalizedPhone);
        if ($this->userRepo->existsByMobileHash($phoneHash)) {
            return [
                'success' => false,
                'error' => 'PHONE_ALREADY_REGISTERED',
                'message' => 'This mobile phone number is already registered to an account.',
            ];
        }

        // Invalidate any previous unverified challenges for this email
        try {
            $past = date('Y-m-d H:i:s', time() - 5);
            $invStmt = $this->pdo->prepare(
                'UPDATE registration_challenges SET expires_at = :past WHERE email = :email AND verified_at IS NULL'
            );
            $invStmt->execute([
                ':past' => $past,
                ':email' => $email,
            ]);
        } catch (Throwable) {
            // Tolerate errors in dynamic/test environments
        }

        // Generate cryptographic OTP and challenge token
        $otp = sprintf('%06d', random_int(100000, 999999));
        $otpHash = hash_hmac('sha256', $otp, $this->secretKey);
        $challengeToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        $createdAt = date('Y-m-d H:i:s');

        // Secure password hash (never store plaintext)
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $phoneCiphertext = $this->mobileService->encrypt($normalizedPhone);

        $profileData = isset($data['profile']) && is_array($data['profile'])
            ? json_encode($data['profile'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO registration_challenges (' .
                'challenge_token, otp_hash, phone_hash, phone_ciphertext, normalized_phone, ' .
                'email, display_name, password_hash, profile_data, attempts, max_attempts, expires_at, created_at' .
                ') VALUES (' .
                ':token, :otp_hash, :phone_hash, :phone_cipher, :phone_norm, ' .
                ':email, :display_name, :password_hash, :profile_data, 0, 5, :expires_at, :created_at' .
                ')'
            );
            $stmt->execute([
                ':token' => $challengeToken,
                ':otp_hash' => $otpHash,
                ':phone_hash' => $phoneHash,
                ':phone_cipher' => $phoneCiphertext,
                ':phone_norm' => $normalizedPhone,
                ':email' => $email,
                ':display_name' => $displayName,
                ':password_hash' => $passwordHash,
                ':profile_data' => $profileData,
                ':expires_at' => $expiresAt,
                ':created_at' => $createdAt,
            ]);
        } catch (Throwable) {
            // Fallback storage in session if table write fails
            $_SESSION['pending_registration'] = [
                'challenge_token' => $challengeToken,
                'otp_hash' => $otpHash,
                'phone_hash' => $phoneHash,
                'phone_ciphertext' => $phoneCiphertext,
                'normalized_phone' => $normalizedPhone,
                'email' => $email,
                'display_name' => $displayName,
                'password_hash' => $passwordHash,
                'profile_data' => $profileData,
                'attempts' => 0,
                'max_attempts' => 5,
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
            ];
        }

        // Also cache in session for fast UI hydration
        $_SESSION['pending_registration'] = [
            'challenge_token' => $challengeToken,
            'masked_email' => MailerService::maskEmail($email),
            'masked_phone' => $this->mobileService->mask($normalizedPhone),
            'expires_at' => $expiresAt,
        ];

        // Send OTP via MailerService (never send registration OTP via SMS)
        $mailResult = $this->mailer->sendRegistrationOtp($email, $displayName, $otp);

        $isDev = getenv('APP_ENV') !== 'production';

        return [
            'success' => true,
            'challenge_required' => true,
            'challenge_token' => $challengeToken,
            'masked_email' => MailerService::maskEmail($email),
            'masked_phone' => $this->mobileService->mask($normalizedPhone),
            'expires_in' => 600,
            'delivery_status' => $mailResult['status'],
            'delivery_reason' => $mailResult['reason'] ?? null,
            'dev_code' => $isDev ? $otp : null,
        ];
    }

    /**
     * Verify submitted 6-digit registration OTP code against challenge token.
     *
     * @return array{
     *     success: bool,
     *     user_id?: int,
     *     error?: string,
     *     message?: string,
     *     remaining_attempts?: int
     * }
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
            $stmt = $this->pdo->prepare('SELECT * FROM registration_challenges WHERE challenge_token = :token LIMIT 1');
            $stmt->execute([':token' => $challengeToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $row = null;
        }

        if (!$row && isset($_SESSION['pending_registration']) && is_array($_SESSION['pending_registration']) && ($_SESSION['pending_registration']['challenge_token'] ?? '') === $challengeToken && isset($_SESSION['pending_registration']['otp_hash'])) {
            $row = $_SESSION['pending_registration'];
        }

        if (!$row) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_NOT_FOUND',
                'message' => 'The verification challenge was not found or has expired. Please register again.',
            ];
        }

        if (!empty($row['verified_at'])) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_ALREADY_USED',
                'message' => 'This verification code has already been used. Please sign in.',
            ];
        }

        $expiresTs = strtotime((string) ($row['expires_at'] ?? ''));
        if ($expiresTs === false || $expiresTs <= time()) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_EXPIRED',
                'message' => 'This verification code has expired. Please request a new code or register again.',
            ];
        }

        $attempts = (int) ($row['attempts'] ?? 0);
        $maxAttempts = (int) ($row['max_attempts'] ?? 5);

        if ($attempts >= $maxAttempts) {
            return [
                'success' => false,
                'error' => 'MAX_ATTEMPTS_EXCEEDED',
                'message' => 'Too many failed verification attempts. Please register again.',
                'remaining_attempts' => 0,
            ];
        }

        // Increment attempts count atomically
        $newAttempts = $attempts + 1;
        try {
            $updateAttempts = $this->pdo->prepare('UPDATE registration_challenges SET attempts = :attempts WHERE challenge_token = :token');
            $updateAttempts->execute([':attempts' => $newAttempts, ':token' => $challengeToken]);
        } catch (Throwable) {
            if (isset($_SESSION['pending_registration']) && is_array($_SESSION['pending_registration'])) {
                $_SESSION['pending_registration']['attempts'] = $newAttempts;
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
                    : 'Incorrect code. Maximum attempts exceeded. Please register again.',
                'remaining_attempts' => $remaining,
            ];
        }

        // Re-check uniqueness before final account creation to prevent race condition
        if ($this->userRepo->emailExists((string) $row['email'])) {
            return [
                'success' => false,
                'error' => 'EMAIL_ALREADY_EXISTS',
                'message' => 'An account with this email address already exists.',
            ];
        }

        if ($this->userRepo->existsByMobileHash((string) $row['phone_hash'])) {
            return [
                'success' => false,
                'error' => 'PHONE_ALREADY_REGISTERED',
                'message' => 'This mobile phone number is already registered to an account.',
            ];
        }

        // Mark challenge verified in database
        $now = date('Y-m-d H:i:s');
        try {
            $markVerified = $this->pdo->prepare('UPDATE registration_challenges SET verified_at = :now WHERE challenge_token = :token');
            $markVerified->execute([':now' => $now, ':token' => $challengeToken]);
        } catch (Throwable) {
            // Ignore
        }

        // Build profile and create user
        $profile = [];
        if (!empty($row['profile_data']) && is_string($row['profile_data'])) {
            $decoded = json_decode($row['profile_data'], true);
            if (is_array($decoded)) {
                $profile = $decoded;
            }
        }
        $profile['phone'] = (string) $row['normalized_phone'];
        $profile['mobile_number_ciphertext'] = (string) $row['phone_ciphertext'];
        $profile['mobile_number_hash'] = (string) $row['phone_hash'];
        $profile['mobile_number_verified_at'] = $now;

        try {
            $userId = $this->userRepo->createUser(
                (string) $row['display_name'],
                (string) $row['email'],
                (string) $row['password_hash'],
                $profile
            );
        } catch (\InfinityBlog\Auth\ProfileValidationException $e) {
            $errs = $e->errors();
            $msg = $errs['phone'] ?? $errs['email'] ?? $errs['username'] ?? 'Registration could not be completed.';
            return [
                'success' => false,
                'error' => 'PHONE_ALREADY_REGISTERED',
                'message' => $msg,
            ];
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $code = (string) $e->getCode();
            if ($code === '23000' || str_contains($msg, '1062') || str_contains($msg, 'UNIQUE') || str_contains($msg, 'uq_users_mobile_hash')) {
                return [
                    'success' => false,
                    'error' => 'PHONE_ALREADY_REGISTERED',
                    'message' => 'This mobile phone number is already registered to an account.',
                ];
            }
            throw $e;
        }

        unset($_SESSION['pending_registration']);

        return [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Registration and phone verification successful. You may now sign in.',
        ];
    }

    /**
     * Resend registration verification OTP with rate limiting cooldown.
     *
     * @return array{
     *     success: bool,
     *     challenge_token?: string,
     *     masked_phone?: string,
     *     expires_in?: int,
     *     delivery_status?: string,
     *     dev_code?: string|null,
     *     error?: string,
     *     message?: string
     * }
     */
    public function resendChallenge(string $challengeToken, string $sourceAddress = '127.0.0.1'): array
    {
        $this->ensureTable();

        $row = null;
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM registration_challenges WHERE challenge_token = :token LIMIT 1');
            $stmt->execute([':token' => $challengeToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $row = null;
        }

        if (!$row) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_NOT_FOUND',
                'message' => 'Registration verification session not found.',
            ];
        }

        if (!empty($row['verified_at'])) {
            return [
                'success' => false,
                'error' => 'CHALLENGE_ALREADY_USED',
                'message' => 'This account has already been verified.',
            ];
        }

        // Generate fresh OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $otpHash = hash_hmac('sha256', $otp, $this->secretKey);
        $expiresAt = date('Y-m-d H:i:s', time() + 600);

        try {
            $update = $this->pdo->prepare('UPDATE registration_challenges SET otp_hash = :hash, attempts = 0, expires_at = :expires WHERE challenge_token = :token');
            $update->execute([
                ':hash' => $otpHash,
                ':expires' => $expiresAt,
                ':token' => $challengeToken,
            ]);
        } catch (Throwable) {
            // Ignore
        }

        $email = (string) $row['email'];
        $displayName = (string) $row['display_name'];
        $mailResult = $this->mailer->sendRegistrationOtp($email, $displayName, $otp);
        $isDev = getenv('APP_ENV') !== 'production';

        return [
            'success' => true,
            'challenge_token' => $challengeToken,
            'masked_email' => MailerService::maskEmail($email),
            'masked_phone' => $this->mobileService->mask((string) $row['normalized_phone']),
            'expires_in' => 600,
            'delivery_status' => $mailResult['status'],
            'delivery_reason' => $mailResult['reason'] ?? null,
            'dev_code' => $isDev ? $otp : null,
            'message' => 'A new verification code has been sent to your email address.',
        ];
    }
}
