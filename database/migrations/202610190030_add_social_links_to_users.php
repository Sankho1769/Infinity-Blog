<?php
declare(strict_types=1);

/**
 * Migration 202610190030: Add dedicated social media links and WhatsApp contact to users table.
 *
 * Adds:
 * - instagram_url: VARCHAR(255) NULL
 * - facebook_url: VARCHAR(255) NULL
 * - github_url: VARCHAR(255) NULL
 * - whatsapp_number: VARCHAR(50) NULL (E.164 normalized format)
 * - whatsapp_public: TINYINT(1) NOT NULL DEFAULT 0 (privacy toggle: 0 = hidden, 1 = public)
 * - twitter_url: VARCHAR(255) NULL
 */
return [
    'id' => '202610190030_add_social_links_to_users',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($userCols, 'name');

            if (!in_array('instagram_url', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN instagram_url TEXT NULL');
            }
            if (!in_array('facebook_url', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN facebook_url TEXT NULL');
            }
            if (!in_array('github_url', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN github_url TEXT NULL');
            }
            if (!in_array('whatsapp_number', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN whatsapp_number TEXT NULL');
            }
            if (!in_array('whatsapp_public', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN whatsapp_public INTEGER NOT NULL DEFAULT 0');
            }
            if (!in_array('twitter_url', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN twitter_url TEXT NULL');
            }
        } else {
            // MySQL
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('instagram_url', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN instagram_url VARCHAR(255) NULL AFTER website');
            }
            if (!in_array('facebook_url', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN facebook_url VARCHAR(255) NULL AFTER instagram_url');
            }
            if (!in_array('github_url', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN github_url VARCHAR(255) NULL AFTER facebook_url');
            }
            if (!in_array('whatsapp_number', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN whatsapp_number VARCHAR(50) NULL AFTER github_url');
            }
            if (!in_array('whatsapp_public', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN whatsapp_public TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_number');
            }
            if (!in_array('twitter_url', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN twitter_url VARCHAR(255) NULL AFTER whatsapp_public');
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            try {
                $pdo->exec('ALTER TABLE users DROP COLUMN instagram_url');
                $pdo->exec('ALTER TABLE users DROP COLUMN facebook_url');
                $pdo->exec('ALTER TABLE users DROP COLUMN github_url');
                $pdo->exec('ALTER TABLE users DROP COLUMN whatsapp_number');
                $pdo->exec('ALTER TABLE users DROP COLUMN whatsapp_public');
                $pdo->exec('ALTER TABLE users DROP COLUMN twitter_url');
            } catch (Throwable) {
                // SQLite before 3.35 may not support DROP COLUMN
            }
        } else {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $drops = [];
            foreach (['twitter_url', 'whatsapp_public', 'whatsapp_number', 'github_url', 'facebook_url', 'instagram_url'] as $col) {
                if (in_array($col, $existing, true)) {
                    $drops[] = "DROP COLUMN `{$col}`";
                }
            }
            if (!empty($drops)) {
                $pdo->exec('ALTER TABLE users ' . implode(', ', $drops));
            }
        }
    },
];
