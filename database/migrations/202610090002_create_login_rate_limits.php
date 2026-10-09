<?php
declare(strict_types=1);

return [
    'id' => '202610090002_create_login_rate_limits',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS login_rate_limits (
                bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                bucket_type VARCHAR(16) NOT NULL,
                failed_attempts INT UNSIGNED NOT NULL,
                window_started_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (bucket_hash),
                CONSTRAINT chk_login_rate_limits_type CHECK (bucket_type IN ('identifier', 'source', 'global')),
                CONSTRAINT chk_login_rate_limits_attempts CHECK (failed_attempts > 0),
                INDEX idx_login_rate_limits_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS login_rate_limits');
    },
];
