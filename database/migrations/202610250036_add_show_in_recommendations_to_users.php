<?php
declare(strict_types=1);

/**
 * Migration 202610250036: Add show_in_recommendations to users table.
 *
 * Adds:
 * - show_in_recommendations: TINYINT(1) NOT NULL DEFAULT 1
 *
 * Controls whether an account is discoverable in recommendations such as "People You Know".
 * Authentic accounts default to 1 (visible), while automated test fixtures can be explicitly set to 0 (hidden).
 */
return [
    'id' => '202610250036_add_show_in_recommendations_to_users',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_map('strtolower', array_column($userCols, 'name'));

            if (!in_array('show_in_recommendations', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN show_in_recommendations INTEGER NOT NULL DEFAULT 1');
            }
        } else {
            // MySQL
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('show_in_recommendations', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN show_in_recommendations TINYINT(1) NOT NULL DEFAULT 1 AFTER profile_visibility');
            }

            // Check if index exists before creating
            $indexStmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_recommendations'");
            if ($indexStmt->fetch() === false) {
                $pdo->exec('ALTER TABLE users ADD INDEX idx_users_recommendations (status, show_in_recommendations)');
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            try {
                $pdo->exec('ALTER TABLE users DROP COLUMN show_in_recommendations');
            } catch (Throwable) {
                // SQLite before 3.35 may not support DROP COLUMN
            }
        } else {
            $indexStmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_recommendations'");
            if ($indexStmt->fetch() !== false) {
                $pdo->exec('ALTER TABLE users DROP INDEX idx_users_recommendations');
            }

            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (in_array('show_in_recommendations', $existing, true)) {
                $pdo->exec('ALTER TABLE users DROP COLUMN `show_in_recommendations`');
            }
        }
    },
];
