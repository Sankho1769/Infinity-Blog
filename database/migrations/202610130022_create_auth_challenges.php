<?php
declare(strict_types=1);

/**
 * Migration 202610130022: Create auth_challenges table for two-step email verification during login.
 */
return [
    'id' => '202610130022_create_auth_challenges',
    'up' => static function (PDO $pdo): void {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec(
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
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS auth_challenges (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    challenge_token VARCHAR(64) NOT NULL UNIQUE,
                    otp_hash VARCHAR(64) NOT NULL,
                    destination_email VARCHAR(190) NOT NULL,
                    attempts INT UNSIGNED NOT NULL DEFAULT 0,
                    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
                    expires_at DATETIME NOT NULL,
                    verified_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_auth_challenges_token (challenge_token),
                    INDEX idx_auth_challenges_user (user_id),
                    INDEX idx_auth_challenges_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS auth_challenges");
    },
];
