<?php
declare(strict_types=1);

namespace InfinityBlog\Core;

use InfinityBlog\Config\Database;
use PDO;

class RateLimiter {
    public static function check(string $action, string $identifier, int $maxHits, int $windowSeconds): bool {
        try {
            $pdo = Database::getConnection();
            $now = time();
            $keyHash = hash('sha256', "{$action}:{$identifier}");

            // Clean expired buckets periodically
            if (random_int(1, 100) === 1) {
                $cleanStmt = $pdo->prepare("DELETE FROM `rate_limit_buckets` WHERE `expires_at` < :now");
                $cleanStmt->execute([':now' => $now]);
            }

            $stmt = $pdo->prepare("SELECT `hit_count`, `window_start`, `expires_at` FROM `rate_limit_buckets` WHERE `key_hash` = :key");
            $stmt->execute([':key' => $keyHash]);
            $bucket = $stmt->fetch();

            if (!$bucket || $bucket['expires_at'] < $now) {
                // Initialize or reset bucket
                $insert = $pdo->prepare("
                    INSERT INTO `rate_limit_buckets` (`key_hash`, `hit_count`, `window_start`, `expires_at`)
                    VALUES (:key, 1, :win, :exp)
                    ON DUPLICATE KEY UPDATE `hit_count` = 1, `window_start` = VALUES(`window_start`), `expires_at` = VALUES(`expires_at`)
                ");
                $insert->execute([
                    ':key' => $keyHash,
                    ':win' => $now,
                    ':exp' => $now + $windowSeconds,
                ]);
                return true;
            }

            if ((int)$bucket['hit_count'] >= $maxHits) {
                return false;
            }

            $update = $pdo->prepare("UPDATE `rate_limit_buckets` SET `hit_count` = `hit_count` + 1 WHERE `key_hash` = :key");
            $update->execute([':key' => $keyHash]);
            return true;
        } catch (\Throwable $e) {
            // Fail open gracefully if rate limiter storage fails, but log it
            error_log("RateLimiter error: " . $e->getMessage());
            return true;
        }
    }
}
