<?php
declare(strict_types=1);

/**
 * Migration 202610270038: Create password_resets table.
 *
 * Implements secure password reset workflow:
 * - Stores SHA-256 hash of random token (never plaintext token)
 * - Records user_id, expires_at, used_at, created_at
 * - Supports single-use expiring password reset links
 */
return [
    'id' => '202610270038_create_password_resets',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS password_resets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token_hash TEXT NOT NULL UNIQUE,
                    expires_at TEXT NOT NULL,
                    used_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pw_reset_lookup ON password_resets (token_hash, expires_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pw_reset_user ON password_resets (user_id)");
        } else {
            // MySQL
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS password_resets (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    token_hash VARCHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pw_reset_lookup (token_hash, expires_at),
                    INDEX idx_pw_reset_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS password_resets");
    },
];
