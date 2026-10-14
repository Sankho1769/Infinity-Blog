<?php
declare(strict_types=1);

/**
 * Migration 202610140024: Create registration_challenges table for registration-only phone OTP verification.
 */
return [
    'id' => '202610140024_create_registration_challenges',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec(
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
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS registration_challenges (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    challenge_token VARCHAR(64) NOT NULL UNIQUE,
                    otp_hash VARCHAR(64) NOT NULL,
                    phone_hash VARCHAR(64) NOT NULL,
                    phone_ciphertext TEXT NOT NULL,
                    normalized_phone VARCHAR(32) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    display_name VARCHAR(190) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    profile_data TEXT NULL,
                    attempts INT UNSIGNED NOT NULL DEFAULT 0,
                    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
                    expires_at DATETIME NOT NULL,
                    verified_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_reg_challenges_token (challenge_token),
                    INDEX idx_reg_challenges_phone_hash (phone_hash),
                    INDEX idx_reg_challenges_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS registration_challenges");
    },
];
