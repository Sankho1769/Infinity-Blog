<?php
declare(strict_types=1);

/**
 * Migration 202610130021: Add secure, unique mobile-number fields to users table.
 *
 * Adds:
 * - mobile_number_ciphertext: authenticated AES-256-GCM encrypted E.164 number.
 * - mobile_number_hash: deterministic keyed HMAC-SHA-256 for database uniqueness.
 * - mobile_number_verified_at: nullable verification timestamp.
 * - UNIQUE INDEX on mobile_number_hash (NULL values allowed for accounts without mobile).
 */
return [
    'id' => '202610130021_add_secure_mobile_fields_to_users',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $userCols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($userCols, 'name');
            if (!in_array('mobile_number_ciphertext', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN mobile_number_ciphertext TEXT NULL');
            }
            if (!in_array('mobile_number_hash', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN mobile_number_hash TEXT NULL');
            }
            if (!in_array('mobile_number_verified_at', $colNames, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN mobile_number_verified_at DATETIME NULL');
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_users_mobile_hash ON users (mobile_number_hash)');
        } else {
            // MySQL 8+ syntax
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('mobile_number_ciphertext', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN mobile_number_ciphertext VARCHAR(512) NULL AFTER phone');
            }
            if (!in_array('mobile_number_hash', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN mobile_number_hash VARCHAR(64) NULL AFTER mobile_number_ciphertext');
            }
            if (!in_array('mobile_number_verified_at', $existing, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN mobile_number_verified_at DATETIME NULL AFTER mobile_number_hash');
            }

            // Check if unique key exists
            $indexStmt = $pdo->query("SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'uq_users_mobile_hash'");
            if ($indexStmt->fetchColumn() === false) {
                $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_mobile_hash (mobile_number_hash)');
            }
        }

        // Migrate any pre-existing plain phone records into secure ciphertext + HMAC hash
        $service = new \InfinityBlog\Security\MobileNumberService();
        $stmt = $pdo->query('SELECT id, phone FROM users WHERE phone IS NOT NULL AND phone != "" AND (mobile_number_hash IS NULL OR mobile_number_hash = "")');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $update = $pdo->prepare('UPDATE users SET mobile_number_ciphertext = :cipher, mobile_number_hash = :hash WHERE id = :id');
        foreach ($rows as $row) {
            try {
                $norm = $service->normalize((string) $row['phone']);
                $cipher = $service->encrypt($norm);
                $hash = $service->hash($norm);
                $update->execute([
                    ':cipher' => $cipher,
                    ':hash' => $hash,
                    ':id' => $row['id'],
                ]);
            } catch (Throwable) {
                // If existing legacy string cannot be normalized, leave null rather than failing migration
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec('DROP INDEX IF EXISTS uq_users_mobile_hash');
            // SQLite 3.35+ support DROP COLUMN
            try {
                $pdo->exec('ALTER TABLE users DROP COLUMN mobile_number_verified_at');
                $pdo->exec('ALTER TABLE users DROP COLUMN mobile_number_hash');
                $pdo->exec('ALTER TABLE users DROP COLUMN mobile_number_ciphertext');
            } catch (Throwable) {}
        } else {
            // Drop MySQL unique key and columns
            try {
                $pdo->exec('ALTER TABLE users DROP KEY uq_users_mobile_hash');
            } catch (Throwable) {}

            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $drops = [];
            if (in_array('mobile_number_verified_at', $existing, true)) {
                $drops[] = 'DROP COLUMN mobile_number_verified_at';
            }
            if (in_array('mobile_number_hash', $existing, true)) {
                $drops[] = 'DROP COLUMN mobile_number_hash';
            }
            if (in_array('mobile_number_ciphertext', $existing, true)) {
                $drops[] = 'DROP COLUMN mobile_number_ciphertext';
            }

            if ($drops !== []) {
                $pdo->exec('ALTER TABLE users ' . implode(', ', $drops));
            }
        }
    },
];
