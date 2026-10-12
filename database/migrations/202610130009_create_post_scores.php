<?php
declare(strict_types=1);

return [
    'id' => '202610130009_create_post_scores',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS post_scores (' .
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
            'post_id BIGINT UNSIGNED NOT NULL, ' .
            'user_id BIGINT UNSIGNED NOT NULL, ' .
            'score TINYINT UNSIGNED NOT NULL, ' .
            'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
            'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
            'PRIMARY KEY (id), ' .
            'CONSTRAINT uq_post_scores_user UNIQUE (post_id, user_id), ' .
            'CONSTRAINT chk_post_scores_range CHECK (score >= 1 AND score <= 5), ' .
            'CONSTRAINT fk_post_scores_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
            'CONSTRAINT fk_post_scores_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
            'INDEX idx_post_scores_post (post_id), ' .
            'INDEX idx_post_scores_user (user_id)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS post_scores');
    },
];
