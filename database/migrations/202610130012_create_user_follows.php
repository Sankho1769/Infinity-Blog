<?php
declare(strict_types=1);

return [
    'id' => '202610130012_create_user_follows',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_follows (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'follower_id INTEGER NOT NULL, ' .
                'following_id INTEGER NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (follower_id, following_id)' .
                ')'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_follows (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'follower_id BIGINT UNSIGNED NOT NULL, ' .
                'following_id BIGINT UNSIGNED NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_follows (follower_id, following_id), ' .
                'INDEX idx_uf_follower (follower_id), ' .
                'INDEX idx_uf_following (following_id), ' .
                'CONSTRAINT fk_uf_follower FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_uf_following FOREIGN KEY (following_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS user_follows');
    },
];
