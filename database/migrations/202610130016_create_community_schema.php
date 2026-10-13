<?php
declare(strict_types=1);

return [
    'id' => '202610130016_create_community_schema',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS article_comments (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'post_id INTEGER NOT NULL, ' .
                'user_id INTEGER NOT NULL, ' .
                'parent_id INTEGER NULL, ' .
                'content TEXT NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'published\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS comment_reports (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'comment_id INTEGER NOT NULL, ' .
                'reporter_id INTEGER NOT NULL, ' .
                'reason VARCHAR(255) NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'pending\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS chat_rooms (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'slug VARCHAR(100) NOT NULL, ' .
                'name VARCHAR(100) NOT NULL, ' .
                'description TEXT NULL, ' .
                'category_id INTEGER NULL, ' .
                'created_by INTEGER NOT NULL, ' .
                'is_active INTEGER NOT NULL DEFAULT 1, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (slug)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS chat_room_messages (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'room_id INTEGER NOT NULL, ' .
                'user_id INTEGER NOT NULL, ' .
                'message TEXT NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_blocks (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'blocked_user_id INTEGER NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, blocked_user_id)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_reports (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'reporter_id INTEGER NOT NULL, ' .
                'reported_user_id INTEGER NOT NULL, ' .
                'reason VARCHAR(255) NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'pending\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_notifications (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'actor_id INTEGER NULL, ' .
                'type VARCHAR(50) NOT NULL, ' .
                'reference_id INTEGER NULL, ' .
                'message VARCHAR(255) NOT NULL, ' .
                'is_read INTEGER NOT NULL DEFAULT 0, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS article_comments (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'post_id BIGINT UNSIGNED NOT NULL, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'parent_id BIGINT UNSIGNED NULL, ' .
                'content TEXT NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'published\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_ac_post (post_id), ' .
                'INDEX idx_ac_user (user_id), ' .
                'INDEX idx_ac_parent (parent_id), ' .
                'CONSTRAINT fk_ac_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_ac_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_ac_parent FOREIGN KEY (parent_id) REFERENCES article_comments (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS comment_reports (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'comment_id BIGINT UNSIGNED NOT NULL, ' .
                'reporter_id BIGINT UNSIGNED NOT NULL, ' .
                'reason VARCHAR(255) NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'pending\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_cr_comment (comment_id), ' .
                'INDEX idx_cr_reporter (reporter_id), ' .
                'CONSTRAINT fk_cr_comment FOREIGN KEY (comment_id) REFERENCES article_comments (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_cr_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS chat_rooms (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'slug VARCHAR(100) NOT NULL, ' .
                'name VARCHAR(100) NOT NULL, ' .
                'description TEXT NULL, ' .
                'category_id BIGINT UNSIGNED NULL, ' .
                'created_by BIGINT UNSIGNED NOT NULL, ' .
                'is_active TINYINT(1) NOT NULL DEFAULT 1, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_chat_room_slug (slug), ' .
                'INDEX idx_cr_category (category_id), ' .
                'CONSTRAINT fk_cr_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_cr_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS chat_room_messages (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'room_id BIGINT UNSIGNED NOT NULL, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'message TEXT NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_crm_room (room_id), ' .
                'INDEX idx_crm_user (user_id), ' .
                'CONSTRAINT fk_crm_room FOREIGN KEY (room_id) REFERENCES chat_rooms (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_crm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_blocks (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'blocked_user_id BIGINT UNSIGNED NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_block (user_id, blocked_user_id), ' .
                'INDEX idx_ubl_user (user_id), ' .
                'INDEX idx_ubl_blocked (blocked_user_id), ' .
                'CONSTRAINT fk_ubl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_ubl_blocked FOREIGN KEY (blocked_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_reports (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'reporter_id BIGINT UNSIGNED NOT NULL, ' .
                'reported_user_id BIGINT UNSIGNED NOT NULL, ' .
                'reason VARCHAR(255) NOT NULL, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'pending\', ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_urp_reporter (reporter_id), ' .
                'INDEX idx_urp_reported (reported_user_id), ' .
                'CONSTRAINT fk_urp_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_urp_reported FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_notifications (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'actor_id BIGINT UNSIGNED NULL, ' .
                'type VARCHAR(50) NOT NULL, ' .
                'reference_id BIGINT UNSIGNED NULL, ' .
                'message VARCHAR(255) NOT NULL, ' .
                'is_read TINYINT(1) NOT NULL DEFAULT 0, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_unotif_user (user_id), ' .
                'INDEX idx_unotif_actor (actor_id), ' .
                'INDEX idx_unotif_read (user_id, is_read), ' .
                'CONSTRAINT fk_unotif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_unotif_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS user_notifications');
        $pdo->exec('DROP TABLE IF EXISTS user_reports');
        $pdo->exec('DROP TABLE IF EXISTS user_blocks');
        $pdo->exec('DROP TABLE IF EXISTS chat_room_messages');
        $pdo->exec('DROP TABLE IF EXISTS chat_rooms');
        $pdo->exec('DROP TABLE IF EXISTS comment_reports');
        $pdo->exec('DROP TABLE IF EXISTS article_comments');
    },
];
