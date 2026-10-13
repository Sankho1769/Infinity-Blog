<?php
declare(strict_types=1);

return [
    'id' => '202610130015_create_library_schema',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_bookmarks (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'post_id INTEGER NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, post_id)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_reading_history (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'post_id INTEGER NOT NULL, ' .
                'progress_percent INTEGER NOT NULL DEFAULT 0, ' .
                'scroll_position INTEGER NOT NULL DEFAULT 0, ' .
                'completed INTEGER NOT NULL DEFAULT 0, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, post_id)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_collections (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'title VARCHAR(150) NOT NULL, ' .
                'description TEXT NULL, ' .
                'is_private INTEGER NOT NULL DEFAULT 1, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS collection_items (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'collection_id INTEGER NOT NULL, ' .
                'post_id INTEGER NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (collection_id, post_id)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_notes (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'post_id INTEGER NOT NULL, ' .
                'highlight_text TEXT NULL, ' .
                'note_text TEXT NOT NULL, ' .
                'color VARCHAR(30) NOT NULL DEFAULT \'yellow\', ' .
                'is_private INTEGER NOT NULL DEFAULT 1, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_topic_preferences (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'category_id INTEGER NOT NULL, ' .
                'is_muted INTEGER NOT NULL DEFAULT 0, ' .
                'affinity_score INTEGER NOT NULL DEFAULT 0, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, category_id)' .
                ')'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_bookmarks (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_post_bookmark (user_id, post_id), ' .
                'INDEX idx_ub_user (user_id), ' .
                'INDEX idx_ub_post (post_id), ' .
                'CONSTRAINT fk_ub_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_ub_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_reading_history (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0, ' .
                'scroll_position INT UNSIGNED NOT NULL DEFAULT 0, ' .
                'completed TINYINT(1) NOT NULL DEFAULT 0, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_post_history (user_id, post_id), ' .
                'INDEX idx_urh_user (user_id), ' .
                'INDEX idx_urh_post (post_id), ' .
                'CONSTRAINT fk_urh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_urh_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_collections (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'title VARCHAR(150) NOT NULL, ' .
                'description TEXT NULL, ' .
                'is_private TINYINT(1) NOT NULL DEFAULT 1, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_uc_user (user_id), ' .
                'CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS collection_items (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'collection_id BIGINT UNSIGNED NOT NULL, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_collection_post (collection_id, post_id), ' .
                'INDEX idx_ci_collection (collection_id), ' .
                'INDEX idx_ci_post (post_id), ' .
                'CONSTRAINT fk_ci_collection FOREIGN KEY (collection_id) REFERENCES user_collections (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_ci_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_notes (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'highlight_text TEXT NULL, ' .
                'note_text TEXT NOT NULL, ' .
                'color VARCHAR(30) NOT NULL DEFAULT \'yellow\', ' .
                'is_private TINYINT(1) NOT NULL DEFAULT 1, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_un_user (user_id), ' .
                'INDEX idx_un_post (post_id), ' .
                'CONSTRAINT fk_un_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_un_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_topic_preferences (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'category_id BIGINT UNSIGNED NOT NULL, ' .
                'is_muted TINYINT(1) NOT NULL DEFAULT 0, ' .
                'affinity_score INT NOT NULL DEFAULT 0, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_category_pref (user_id, category_id), ' .
                'INDEX idx_utp_user (user_id), ' .
                'INDEX idx_utp_category (category_id), ' .
                'CONSTRAINT fk_utp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_utp_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS user_topic_preferences');
        $pdo->exec('DROP TABLE IF EXISTS user_notes');
        $pdo->exec('DROP TABLE IF EXISTS collection_items');
        $pdo->exec('DROP TABLE IF EXISTS user_collections');
        $pdo->exec('DROP TABLE IF EXISTS user_reading_history');
        $pdo->exec('DROP TABLE IF EXISTS user_bookmarks');
    },
];
