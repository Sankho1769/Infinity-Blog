<?php
declare(strict_types=1);

/**
 * Migration 202610250034: Add linkedin_url to users table for dynamic social profile links.
 *
 * Adds:
 * - linkedin_url: VARCHAR(255) NULL
 */
return [
    'id' => '202610250034_add_linkedin_to_users',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_map('strtolower', array_column($userCols, 'name'));

            if (!in_array('linkedin_url', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN linkedin_url TEXT NULL');
            }
        } else {
            // MySQL
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('linkedin_url', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER twitter_url');
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            try {
                $pdo->exec('ALTER TABLE users DROP COLUMN linkedin_url');
            } catch (Throwable) {
                // SQLite before 3.35 may not support DROP COLUMN
            }
        } else {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (in_array('linkedin_url', $existing, true)) {
                $pdo->exec('ALTER TABLE users DROP COLUMN `linkedin_url`');
            }
        }
    },
];
