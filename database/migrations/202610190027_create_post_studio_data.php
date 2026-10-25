<?php
declare(strict_types=1);

return [
    'id' => '202610190027_create_post_studio_data',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS post_studio_data (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'post_id INTEGER NULL, ' .
                'draft_key VARCHAR(64) NOT NULL, ' .
                'subtitle TEXT NULL, ' .
                'research_notes TEXT NULL, ' .
                'quick_notes TEXT NULL, ' .
                'target_words INTEGER NOT NULL DEFAULT 2000, ' .
                'settings TEXT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, draft_key), ' .
                'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE, ' .
                'FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_psd_post ON post_studio_data (post_id)');
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS post_studio_data (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'post_id BIGINT UNSIGNED DEFAULT NULL, ' .
                'draft_key VARCHAR(64) NOT NULL, ' .
                'subtitle TEXT NULL, ' .
                'research_notes MEDIUMTEXT NULL, ' .
                'quick_notes MEDIUMTEXT NULL, ' .
                'target_words INT UNSIGNED NOT NULL DEFAULT 2000, ' .
                'settings TEXT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uk_user_draft (user_id, draft_key), ' .
                'INDEX idx_post (post_id), ' .
                'CONSTRAINT fk_psd_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_psd_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS post_studio_data');
    },
];
