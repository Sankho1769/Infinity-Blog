<?php
declare(strict_types=1);

return [
    'id' => '202610180026_create_post_reviews',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS post_reviews (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'post_id INTEGER NOT NULL, ' .
                'user_id INTEGER NOT NULL, ' .
                'rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5), ' .
                'content TEXT NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'published\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (post_id, user_id), ' .
                'FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE, ' .
                'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_post_status ON post_reviews (post_id, status, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_user ON post_reviews (user_id)');

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS review_reports (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'review_id INTEGER NOT NULL, ' .
                'reporter_id INTEGER NOT NULL, ' .
                'reason VARCHAR(100) NOT NULL, ' .
                'explanation VARCHAR(500) NULL, ' .
                'status VARCHAR(30) NOT NULL DEFAULT \'pending\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'FOREIGN KEY (review_id) REFERENCES post_reviews (id) ON DELETE CASCADE, ' .
                'FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rr_review_status ON review_reports (review_id, status)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rr_reporter ON review_reports (reporter_id)');
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS post_reviews (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'rating TINYINT UNSIGNED NOT NULL, ' .
                'content TEXT NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'published\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_reviews_post_user (post_id, user_id), ' .
                'CONSTRAINT chk_reviews_rating CHECK (rating >= 1 AND rating <= 5), ' .
                'INDEX idx_reviews_post_status (post_id, status, created_at), ' .
                'INDEX idx_reviews_user (user_id), ' .
                'CONSTRAINT fk_reviews_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS review_reports (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'review_id BIGINT UNSIGNED NOT NULL, ' .
                'reporter_id BIGINT UNSIGNED NOT NULL, ' .
                'reason VARCHAR(100) NOT NULL, ' .
                'explanation VARCHAR(500) NULL, ' .
                'status VARCHAR(30) NOT NULL DEFAULT \'pending\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_rr_review_status (review_id, status), ' .
                'INDEX idx_rr_reporter (reporter_id), ' .
                'CONSTRAINT fk_rr_review FOREIGN KEY (review_id) REFERENCES post_reviews (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_rr_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS review_reports');
        $pdo->exec('DROP TABLE IF EXISTS post_reviews');
    },
];
