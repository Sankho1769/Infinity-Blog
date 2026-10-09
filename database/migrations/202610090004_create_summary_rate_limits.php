<?php
declare(strict_types=1);

return [
    'id' => '202610090004_create_summary_rate_limits',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS summary_rate_limits (
                bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                bucket_type VARCHAR(16) NOT NULL,
                request_count INT UNSIGNED NOT NULL,
                window_started_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (bucket_hash),
                CONSTRAINT chk_summary_rate_limit_type CHECK (bucket_type IN ('user', 'post', 'source', 'global')),
                CONSTRAINT chk_summary_rate_limit_count CHECK (request_count > 0),
                INDEX idx_summary_rate_limits_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
    'down' => static function (PDO $pdo): void {
        // Contains only transient throttling counters; the migration runner still requires explicit rollback confirmation.
        $pdo->exec('DROP TABLE IF EXISTS summary_rate_limits');
    },
];
