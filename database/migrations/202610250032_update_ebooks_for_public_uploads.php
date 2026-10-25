<?php
declare(strict_types=1);

/**
 * Migration: 202610250032_update_ebooks_for_public_uploads
 * Description: Support anonymous eBook contributions, pending moderation approval workflow,
 *              submitter contact details, and search performance indexes.
 */
return [
    'id' => '202610250032_update_ebooks_for_public_uploads',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // 1. Make user_id nullable for anonymous guest contributions
            $pdo->exec("ALTER TABLE ebooks MODIFY user_id BIGINT UNSIGNED NULL");

            // 2. Add submitter contact info and moderation columns if not already present
            $existingCols = [];
            $colStmt = $pdo->query("SHOW COLUMNS FROM ebooks");
            if ($colStmt) {
                while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingCols[] = $col['Field'];
                }
            }

            if (!in_array('submitter_name', $existingCols, true)) {
                $pdo->exec("ALTER TABLE ebooks ADD COLUMN submitter_name VARCHAR(255) NULL AFTER user_id");
            }
            if (!in_array('submitter_email', $existingCols, true)) {
                $pdo->exec("ALTER TABLE ebooks ADD COLUMN submitter_email VARCHAR(255) NULL AFTER submitter_name");
            }
            if (!in_array('rejection_reason', $existingCols, true)) {
                $pdo->exec("ALTER TABLE ebooks ADD COLUMN rejection_reason TEXT NULL AFTER status");
            }
            if (!in_array('reviewed_by', $existingCols, true)) {
                $pdo->exec("ALTER TABLE ebooks ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER rejection_reason");
            }
            if (!in_array('reviewed_at', $existingCols, true)) {
                $pdo->exec("ALTER TABLE ebooks ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by");
            }

            // 3. Set default status to 'pending' for all new submissions
            $pdo->exec("ALTER TABLE ebooks ALTER COLUMN status SET DEFAULT 'pending'");

            // 4. Performance indexes for catalog search & filtering
            $indexes = [];
            $idxStmt = $pdo->query("SHOW INDEX FROM ebooks");
            if ($idxStmt) {
                while ($idx = $idxStmt->fetch(PDO::FETCH_ASSOC)) {
                    $indexes[] = $idx['Key_name'];
                }
            }

            if (!in_array('idx_ebooks_status_created', $indexes, true)) {
                $pdo->exec("CREATE INDEX idx_ebooks_status_created ON ebooks (status, created_at DESC)");
            }
            if (!in_array('idx_ebooks_status_reads', $indexes, true)) {
                $pdo->exec("CREATE INDEX idx_ebooks_status_reads ON ebooks (status, read_count DESC)");
            }
            if (!in_array('idx_ebooks_format', $indexes, true)) {
                $pdo->exec("CREATE INDEX idx_ebooks_format ON ebooks (file_format)");
            }
        } elseif ($driver === 'sqlite') {
            // SQLite support for automated tests
            $pdo->exec("ALTER TABLE ebooks ADD COLUMN submitter_name TEXT NULL");
            $pdo->exec("ALTER TABLE ebooks ADD COLUMN submitter_email TEXT NULL");
            $pdo->exec("ALTER TABLE ebooks ADD COLUMN rejection_reason TEXT NULL");
            $pdo->exec("ALTER TABLE ebooks ADD COLUMN reviewed_by INTEGER NULL");
            $pdo->exec("ALTER TABLE ebooks ADD COLUMN reviewed_at TEXT NULL");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ebooks_status_created ON ebooks (status, created_at DESC)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ebooks_status_reads ON ebooks (status, read_count DESC)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ebooks_format ON ebooks (file_format)");
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE ebooks ALTER COLUMN status SET DEFAULT 'published'");
            $pdo->exec("ALTER TABLE ebooks MODIFY user_id BIGINT UNSIGNED NOT NULL");
            try { $pdo->exec("DROP INDEX idx_ebooks_status_created ON ebooks"); } catch (\Throwable) {}
            try { $pdo->exec("DROP INDEX idx_ebooks_status_reads ON ebooks"); } catch (\Throwable) {}
            try { $pdo->exec("DROP INDEX idx_ebooks_format ON ebooks"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE ebooks DROP COLUMN submitter_name"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE ebooks DROP COLUMN submitter_email"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE ebooks DROP COLUMN rejection_reason"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE ebooks DROP COLUMN reviewed_by"); } catch (\Throwable) {}
            try { $pdo->exec("ALTER TABLE ebooks DROP COLUMN reviewed_at"); } catch (\Throwable) {}
        }
    }
];
