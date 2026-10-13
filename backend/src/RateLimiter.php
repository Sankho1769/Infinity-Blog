<?php
declare(strict_types=1);

namespace InfinityBlog\Core;

use InfinityBlog\Config\Database;
use PDO;

class RateLimiterException extends \RuntimeException {}

/**
 * Enterprise Rate Limiter with Atomic Database Enforcement
 * Fail-Closed: Never allows security-critical bypasses when storage fails.
 */
class RateLimiter {
    /**
     * Atomically check and increment hit count.
     *
     * @param string $action Action name (e.g. 'login:ip', 'login:account', 'ai_summary')
     * @param string $identifier Client identifier (e.g. IP address or username)
     * @param int $maxHits Maximum allowed requests in window
     * @param int $windowSeconds Window duration in seconds
     * @param bool $failClosed If true, throws RateLimiterException on storage failure; otherwise returns false
     * @return bool True if request is within limit, false if limit exceeded
     * @throws RateLimiterException When database is unavailable and failClosed is true
     */
    public static function check(
        string $action,
        string $identifier,
        int $maxHits,
        int $windowSeconds,
        bool $failClosed = true
    ): bool {
        try {
            $pdo = Database::getConnection();
            $now = time();
            $expiresAt = $now + $windowSeconds;
            $keyHash = hash('sha256', "{$action}:{$identifier}");

            // Clean expired buckets periodically (1% chance)
            if (random_int(1, 100) === 1) {
                $cleanStmt = $pdo->prepare("DELETE FROM `rate_limit_buckets` WHERE `expires_at` < :now");
                $cleanStmt->execute([':now' => $now]);
            }

            // Atomic UPSERT prevents race conditions under high concurrency
            $upsert = $pdo->prepare("
                INSERT INTO `rate_limit_buckets` (`key_hash`, `hit_count`, `window_start`, `expires_at`)
                VALUES (:key, 1, :win, :exp)
                ON DUPLICATE KEY UPDATE
                    `hit_count` = IF(`expires_at` < :now1, 1, `hit_count` + 1),
                    `window_start` = IF(`expires_at` < :now2, :win2, `window_start`),
                    `expires_at` = IF(`expires_at` < :now3, :exp2, `expires_at`)
            ");
            $upsert->execute([
                ':key' => $keyHash,
                ':win' => $now,
                ':exp' => $expiresAt,
                ':now1' => $now,
                ':now2' => $now,
                ':win2' => $now,
                ':now3' => $now,
                ':exp2' => $expiresAt,
            ]);

            // Retrieve current count
            $query = $pdo->prepare("SELECT `hit_count` FROM `rate_limit_buckets` WHERE `key_hash` = :key");
            $query->execute([':key' => $keyHash]);
            $hitCount = (int)$query->fetchColumn();

            return $hitCount <= $maxHits;
        } catch (\Throwable $e) {
            // Log failure safely without exposing SQL or credentials
            error_log("RateLimiter storage error for action '{$action}': " . $e->getMessage());

            if ($failClosed) {
                throw new RateLimiterException("Rate limiting service unavailable.", 0, $e);
            }

            // Even when not throwing, fail closed (deny request)
            return false;
        }
    }

    /**
     * Clear a specific rate limit bucket (for testing or administrative unlock).
     */
    public static function reset(string $action, string $identifier): void {
        try {
            $pdo = Database::getConnection();
            $keyHash = hash('sha256', "{$action}:{$identifier}");
            $stmt = $pdo->prepare("DELETE FROM `rate_limit_buckets` WHERE `key_hash` = :key");
            $stmt->execute([':key' => $keyHash]);
        } catch (\Throwable $e) {
            error_log("RateLimiter reset error: " . $e->getMessage());
        }
    }
}
