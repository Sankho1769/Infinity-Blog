<?php
declare(strict_types=1);

namespace InfinityBlog\Security;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MobileNumberService
{
    private string $encryptionKey;
    private string $hmacSecret;

    public function __construct(?string $encryptionKey = null, ?string $hmacSecret = null)
    {
        $this->encryptionKey = $encryptionKey ?? self::resolveEncryptionKey();
        $this->hmacSecret = $hmacSecret ?? self::resolveHmacSecret();
    }

    /**
     * Resolve 32-byte encryption key from environment or derive via HKDF from APP_KEY.
     */
    private static function resolveEncryptionKey(): string
    {
        $envKey = getenv('MOBILE_ENCRYPTION_KEY');
        if (is_string($envKey) && preg_match('/^[a-f0-9]{64}$/i', $envKey) === 1) {
            return (string) hex2bin($envKey);
        }

        $appKeyHex = getenv('APP_KEY') ?: '';
        if (preg_match('/^[a-f0-9]{64}$/i', $appKeyHex) === 1) {
            return hash_hkdf('sha256', (string) hex2bin($appKeyHex), 32, 'mobile-encryption', 'infinity-blog-mobile-enc-v1');
        }

        // Fallback deterministic derivation if APP_KEY not set in process environment
        return hash('sha256', 'infinity-blog-mobile-enc-fallback-salt-2026', true);
    }

    /**
     * Resolve 32-byte HMAC secret from environment or derive via HKDF from APP_KEY.
     */
    private static function resolveHmacSecret(): string
    {
        $envSecret = getenv('MOBILE_HMAC_SECRET');
        if (is_string($envSecret) && preg_match('/^[a-f0-9]{64}$/i', $envSecret) === 1) {
            return (string) hex2bin($envSecret);
        }

        $appKeyHex = getenv('APP_KEY') ?: '';
        if (preg_match('/^[a-f0-9]{64}$/i', $appKeyHex) === 1) {
            return hash_hkdf('sha256', (string) hex2bin($appKeyHex), 32, 'mobile-hmac', 'infinity-blog-mobile-hmac-v1');
        }

        // Fallback deterministic derivation if APP_KEY not set in process environment
        return hash('sha256', 'infinity-blog-mobile-hmac-fallback-salt-2026', true);
    }

    /**
     * Validate and normalize a mobile number into strict international E.164 format (+<country><digits>).
     *
     * Supports:
     * - Indian numbers: 10 digits starting with [6-9], with optional +91, 91, or leading 0.
     * - International numbers: E.164 starting with '+' followed by 8 to 15 digits (ITU-T E.164 standard).
     *
     * Rejects ambiguous, non-digit, or malformed numbers.
     *
     * @throws InvalidArgumentException
     */
    public function normalize(string $rawNumber): string
    {
        $trimmed = trim($rawNumber);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Mobile number cannot be empty.');
        }

        // Check for disallowable characters (only digits, +, spaces, hyphens, parentheses, dots allowed)
        if (preg_match('/[^0-9+\s\-().]/', $trimmed) === 1) {
            throw new InvalidArgumentException('Mobile number contains invalid characters.');
        }

        $hasLeadingPlus = str_starts_with($trimmed, '+');
        $digitsOnly = preg_replace('/\D/', '', $trimmed);

        if (!is_string($digitsOnly) || $digitsOnly === '') {
            throw new InvalidArgumentException('Mobile number must contain digits.');
        }

        // Handle Indian numbers:
        // Case 1: Exactly 10 digits starting with 6, 7, 8, 9 (e.g. 9876543210 or 8676767666)
        if (!$hasLeadingPlus && strlen($digitsOnly) === 10 && preg_match('/^[6-9]\d{9}$/', $digitsOnly) === 1) {
            return '+91' . $digitsOnly;
        }

        // Case 2: 11 digits starting with 0 followed by 6, 7, 8, 9 (e.g. 09876543210)
        if (!$hasLeadingPlus && strlen($digitsOnly) === 11 && str_starts_with($digitsOnly, '0') && preg_match('/^0[6-9]\d{9}$/', $digitsOnly) === 1) {
            return '+91' . substr($digitsOnly, 1);
        }

        // Case 3: 12 digits starting with 91 followed by 6, 7, 8, 9
        if (strlen($digitsOnly) === 12 && str_starts_with($digitsOnly, '91') && preg_match('/^91[6-9]\d{9}$/', $digitsOnly) === 1) {
            return '+91' . substr($digitsOnly, 2);
        }

        // International numbers: must have had an explicit '+' or international prefix
        if ($hasLeadingPlus) {
            // E.164 specifies max 15 digits, min 8 digits for subscriber numbers
            if (strlen($digitsOnly) < 8 || strlen($digitsOnly) > 15) {
                throw new InvalidArgumentException('International mobile number must be between 8 and 15 digits.');
            }
            if (str_starts_with($digitsOnly, '0')) {
                throw new InvalidArgumentException('Country calling code cannot start with 0.');
            }

            return '+' . $digitsOnly;
        }

        throw new InvalidArgumentException('Please provide a valid 10-digit Indian mobile number or international number starting with +.');
    }

    /**
     * Compute deterministic HMAC-SHA-256 hash for unique database index.
     */
    public function hash(string $normalizedNumber): string
    {
        return hash_hmac('sha256', $normalizedNumber, $this->hmacSecret);
    }

    /**
     * Encrypt mobile number using authenticated AES-256-GCM.
     */
    public function encrypt(string $normalizedNumber): string
    {
        $iv = random_bytes(12); // Standard 96-bit IV for GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $normalizedNumber,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Authenticated encryption of mobile number failed.');
        }

        // Format: base64(iv[12] . tag[16] . ciphertext)
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt authenticated AES-256-GCM mobile number ciphertext.
     * Returns null if decryption fails or tag does not verify.
     */
    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        try {
            $raw = base64_decode($payload, true);
            if ($raw === false || strlen($raw) < 28) {
                return null;
            }

            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $ciphertext = substr($raw, 28);

            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $decrypted !== false ? $decrypted : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Format a mobile number for safe display to account owner (masked).
     * Example: +91 ••••• •7666 or +1 ••••• •2671
     */
    public function mask(?string $normalizedNumber): ?string
    {
        if ($normalizedNumber === null || trim($normalizedNumber) === '') {
            return null;
        }

        $length = strlen($normalizedNumber);
        if ($length < 7) {
            return '••••••';
        }

        // Extract country code if +91
        if (str_starts_with($normalizedNumber, '+91') && $length === 13) {
            $lastFour = substr($normalizedNumber, -4);
            return '+91 ••••• •' . $lastFour;
        }

        // Generic international: keep first 3 chars and last 4 chars
        $prefix = substr($normalizedNumber, 0, 3);
        $suffix = substr($normalizedNumber, -4);
        return $prefix . ' ••••• •' . $suffix;
    }

    /**
     * SMS provider configuration status.
     * As per specification: If no SMS provider configured, mark phone verification BLOCKED.
     */
    public function isSmsConfigured(): bool
    {
        $provider = getenv('SMS_PROVIDER');
        $apiKey = getenv('SMS_API_KEY');
        return is_string($provider) && trim($provider) !== '' && is_string($apiKey) && trim($apiKey) !== '';
    }

    public function verificationStatus(): string
    {
        return $this->isSmsConfigured() ? 'CONFIGURED' : 'BLOCKED';
    }
}
