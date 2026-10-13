<?php
declare(strict_types=1);

/**
 * Migration 202610130023: Create post_reports table for community content moderation.
 */
return [
    'id' => '202610130023_create_post_reports',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS post_reports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    reporter_id INTEGER NOT NULL,
                    reason VARCHAR(100) NOT NULL,
                    explanation VARCHAR(500) NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'pending',
                    moderation_notes TEXT NULL,
                    reviewed_by INTEGER NULL,
                    reviewed_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pr_post_status ON post_reports (post_id, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pr_reporter ON post_reports (reporter_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pr_status_created ON post_reports (status, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pr_active ON post_reports (post_id, reporter_id, status)");
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS post_reports (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    post_id BIGINT UNSIGNED NOT NULL,
                    reporter_id BIGINT UNSIGNED NOT NULL,
                    reason VARCHAR(100) NOT NULL,
                    explanation VARCHAR(500) NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'pending',
                    moderation_notes TEXT NULL,
                    reviewed_by BIGINT UNSIGNED NULL,
                    reviewed_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_pr_post_status (post_id, status),
                    INDEX idx_pr_reporter (reporter_id),
                    INDEX idx_pr_status_created (status, created_at),
                    INDEX idx_pr_active (post_id, reporter_id, status),
                    CONSTRAINT fk_pr_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_pr_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_pr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS post_reports");
    },
];
