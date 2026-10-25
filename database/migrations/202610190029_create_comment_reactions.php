<?php
declare(strict_types=1);

return [
    'id' => '202610190029_create_comment_reactions',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(article_comments)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($cols, 'name');
            if (!in_array('is_pinned', $colNames, true)) {
                $pdo->exec('ALTER TABLE article_comments ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0');
            }
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS article_comment_reactions (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'comment_id INTEGER NOT NULL, ' .
                'user_id INTEGER NOT NULL, ' .
                'reaction VARCHAR(10) NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (comment_id, user_id), ' .
                'FOREIGN KEY (comment_id) REFERENCES article_comments (id) ON DELETE CASCADE, ' .
                'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comment_reaction_comment ON article_comment_reactions (comment_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comment_reaction_user ON article_comment_reactions (user_id)');
        } else {
            $colCheck = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'article_comments' AND column_name = 'is_pinned'"
            )->fetchColumn();
            if ((int) $colCheck === 0) {
                $pdo->exec('ALTER TABLE article_comments ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
            }
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS article_comment_reactions (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'comment_id BIGINT UNSIGNED NOT NULL, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'reaction VARCHAR(10) NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_comment_user_reaction (comment_id, user_id), ' .
                'INDEX idx_acr_comment (comment_id), ' .
                'INDEX idx_acr_user (user_id), ' .
                'CONSTRAINT fk_acr_comment FOREIGN KEY (comment_id) REFERENCES article_comments (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_acr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->exec('DROP TABLE IF EXISTS article_comment_reactions');
        if ($driver !== 'sqlite') {
            $colCheck = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'article_comments' AND column_name = 'is_pinned'"
            )->fetchColumn();
            if ((int) $colCheck > 0) {
                $pdo->exec('ALTER TABLE article_comments DROP COLUMN is_pinned');
            }
        }
    },
];
