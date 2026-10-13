<?php
declare(strict_types=1);

return [
    'id' => '202610130017_create_assistant_and_studio_schema',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS article_assistant_chats (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'post_id INTEGER NOT NULL, ' .
                'user_id INTEGER NULL, ' .
                'session_id VARCHAR(100) NOT NULL, ' .
                'question TEXT NOT NULL, ' .
                'answer TEXT NOT NULL, ' .
                'passage_reference TEXT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS post_versions (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'post_id INTEGER NOT NULL, ' .
                'author_id INTEGER NOT NULL, ' .
                'title VARCHAR(255) NOT NULL, ' .
                'content TEXT NOT NULL, ' .
                'version_number INTEGER NOT NULL DEFAULT 1, ' .
                'change_summary VARCHAR(255) NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            // Add columns to posts in SQLite if not present
            $postCols = $pdo->query('PRAGMA table_info(posts)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($postCols, 'name');

            if (!in_array('scheduled_at', $colNames, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN scheduled_at DATETIME NULL');
            }
            if (!in_array('views_count', $colNames, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN views_count INTEGER NOT NULL DEFAULT 0');
            }
            if (!in_array('summary_audio_path', $colNames, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN summary_audio_path VARCHAR(255) NULL');
            }
            if (!in_array('audio_language', $colNames, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN audio_language VARCHAR(10) NOT NULL DEFAULT \'en\'');
            }
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS article_assistant_chats (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'user_id BIGINT UNSIGNED NULL, ' .
                'session_id VARCHAR(100) NOT NULL, ' .
                'question TEXT NOT NULL, ' .
                'answer TEXT NOT NULL, ' .
                'passage_reference TEXT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_aac_post (post_id), ' .
                'INDEX idx_aac_session (session_id), ' .
                'CONSTRAINT fk_aac_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_aac_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS post_versions (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'author_id BIGINT UNSIGNED NOT NULL, ' .
                'title VARCHAR(255) NOT NULL, ' .
                'content MEDIUMTEXT NOT NULL, ' .
                'version_number INT UNSIGNED NOT NULL DEFAULT 1, ' .
                'change_summary VARCHAR(255) NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_pv_post (post_id), ' .
                'INDEX idx_pv_author (author_id), ' .
                'CONSTRAINT fk_pv_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_pv_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            // Add columns to posts in MySQL if not present
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('scheduled_at', $existing, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN scheduled_at DATETIME NULL AFTER published_at');
            }
            if (!in_array('views_count', $existing, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN views_count BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER scheduled_at');
            }
            if (!in_array('summary_audio_path', $existing, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN summary_audio_path VARCHAR(255) NULL AFTER audio_path');
            }
            if (!in_array('audio_language', $existing, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN audio_language VARCHAR(10) NOT NULL DEFAULT \'en\' AFTER audio_duration');
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS post_versions');
        $pdo->exec('DROP TABLE IF EXISTS article_assistant_chats');
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (in_array('audio_language', $existing, true)) {
                $pdo->exec('ALTER TABLE posts DROP COLUMN audio_language');
            }
            if (in_array('summary_audio_path', $existing, true)) {
                $pdo->exec('ALTER TABLE posts DROP COLUMN summary_audio_path');
            }
            if (in_array('views_count', $existing, true)) {
                $pdo->exec('ALTER TABLE posts DROP COLUMN views_count');
            }
            if (in_array('scheduled_at', $existing, true)) {
                $pdo->exec('ALTER TABLE posts DROP COLUMN scheduled_at');
            }
        }
    },
];
