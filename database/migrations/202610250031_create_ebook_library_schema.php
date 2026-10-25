<?php
declare(strict_types=1);

/**
 * Migration 202610250031: Create public community eBook library schema.
 *
 * Creates:
 * - ebooks: Public catalogue of eBooks uploaded by members
 * - ebook_reading_progress: User reading position and completion tracking
 * - user_saved_ebooks: Personal reading library saves distinct from article bookmarks
 * - ebook_reports: Moderation and reporting system for eBooks
 */
return [
    'id' => '202610250031_create_ebook_library_schema',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ebooks (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'title VARCHAR(255) NOT NULL, ' .
                'slug VARCHAR(255) NOT NULL, ' .
                'author_name VARCHAR(255) NOT NULL, ' .
                'description TEXT NOT NULL, ' .
                'category_id INTEGER NULL, ' .
                'community_id INTEGER NULL, ' .
                'file_path VARCHAR(255) NOT NULL, ' .
                'file_format VARCHAR(10) NOT NULL, ' .
                'file_size INTEGER NOT NULL DEFAULT 0, ' .
                'cover_image_path VARCHAR(255) NULL, ' .
                'language VARCHAR(20) NOT NULL DEFAULT \'en\', ' .
                'tags VARCHAR(255) NULL, ' .
                'allow_download INTEGER NOT NULL DEFAULT 1, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'published\', ' .
                'read_count INTEGER NOT NULL DEFAULT 0, ' .
                'download_count INTEGER NOT NULL DEFAULT 0, ' .
                'featured INTEGER NOT NULL DEFAULT 0, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );

            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ebooks_user ON ebooks (user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ebooks_cat ON ebooks (category_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ebooks_comm ON ebooks (community_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ebooks_status ON ebooks (status)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ebooks_reads ON ebooks (read_count)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ebooks_created ON ebooks (created_at)');

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ebook_reading_progress (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'ebook_id INTEGER NOT NULL, ' .
                'current_page INTEGER NOT NULL DEFAULT 1, ' .
                'total_pages INTEGER NOT NULL DEFAULT 1, ' .
                'progress_percent INTEGER NOT NULL DEFAULT 0, ' .
                'epub_cfi VARCHAR(255) NULL, ' .
                'completed INTEGER NOT NULL DEFAULT 0, ' .
                'last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, ebook_id)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_saved_ebooks (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'user_id INTEGER NOT NULL, ' .
                'ebook_id INTEGER NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (user_id, ebook_id)' .
                ')'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ebook_reports (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'ebook_id INTEGER NOT NULL, ' .
                'reporter_id INTEGER NOT NULL, ' .
                'reason VARCHAR(100) NOT NULL, ' .
                'explanation TEXT NULL, ' .
                'status VARCHAR(30) NOT NULL DEFAULT \'pending\', ' .
                'reviewed_by INTEGER NULL, ' .
                'reviewed_at DATETIME NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_er_ebook ON ebook_reports (ebook_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_er_status ON ebook_reports (status)');
        } else {
            // MySQL / MariaDB
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ebooks (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'title VARCHAR(255) NOT NULL, ' .
                'slug VARCHAR(255) NOT NULL, ' .
                'author_name VARCHAR(255) NOT NULL, ' .
                'description TEXT NOT NULL, ' .
                'category_id BIGINT UNSIGNED NULL, ' .
                'community_id BIGINT UNSIGNED NULL, ' .
                'file_path VARCHAR(255) NOT NULL, ' .
                'file_format VARCHAR(10) NOT NULL, ' .
                'file_size BIGINT UNSIGNED NOT NULL DEFAULT 0, ' .
                'cover_image_path VARCHAR(255) NULL, ' .
                'language VARCHAR(20) NOT NULL DEFAULT \'en\', ' .
                'tags VARCHAR(255) NULL, ' .
                'allow_download TINYINT(1) NOT NULL DEFAULT 1, ' .
                'status VARCHAR(20) NOT NULL DEFAULT \'published\', ' .
                'read_count BIGINT UNSIGNED NOT NULL DEFAULT 0, ' .
                'download_count BIGINT UNSIGNED NOT NULL DEFAULT 0, ' .
                'featured TINYINT(1) NOT NULL DEFAULT 0, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'KEY idx_ebooks_user (user_id), ' .
                'KEY idx_ebooks_category (category_id), ' .
                'KEY idx_ebooks_community (community_id), ' .
                'KEY idx_ebooks_status (status), ' .
                'KEY idx_ebooks_reads (read_count), ' .
                'KEY idx_ebooks_created (created_at), ' .
                'KEY idx_ebooks_slug (slug), ' .
                'CONSTRAINT fk_ebooks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE, ' .
                'CONSTRAINT fk_ebooks_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL, ' .
                'CONSTRAINT fk_ebooks_community FOREIGN KEY (community_id) REFERENCES chat_rooms (id) ON DELETE SET NULL' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ebook_reading_progress (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'ebook_id BIGINT UNSIGNED NOT NULL, ' .
                'current_page INT UNSIGNED NOT NULL DEFAULT 1, ' .
                'total_pages INT UNSIGNED NOT NULL DEFAULT 1, ' .
                'progress_percent INT UNSIGNED NOT NULL DEFAULT 0, ' .
                'epub_cfi VARCHAR(255) NULL, ' .
                'completed TINYINT(1) NOT NULL DEFAULT 0, ' .
                'last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_ebook_progress (user_id, ebook_id), ' .
                'KEY idx_erp_user (user_id), ' .
                'KEY idx_erp_ebook (ebook_id), ' .
                'CONSTRAINT fk_erp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE, ' .
                'CONSTRAINT fk_erp_ebook FOREIGN KEY (ebook_id) REFERENCES ebooks (id) ON DELETE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_saved_ebooks (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'ebook_id BIGINT UNSIGNED NOT NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_user_ebook_save (user_id, ebook_id), ' .
                'KEY idx_use_user (user_id), ' .
                'KEY idx_use_ebook (ebook_id), ' .
                'CONSTRAINT fk_use_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE, ' .
                'CONSTRAINT fk_use_ebook FOREIGN KEY (ebook_id) REFERENCES ebooks (id) ON DELETE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ebook_reports (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'ebook_id BIGINT UNSIGNED NOT NULL, ' .
                'reporter_id BIGINT UNSIGNED NOT NULL, ' .
                'reason VARCHAR(100) NOT NULL, ' .
                'explanation TEXT NULL, ' .
                'status VARCHAR(30) NOT NULL DEFAULT \'pending\', ' .
                'reviewed_by BIGINT UNSIGNED NULL, ' .
                'reviewed_at DATETIME NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'KEY idx_er_ebook (ebook_id), ' .
                'KEY idx_er_reporter (reporter_id), ' .
                'KEY idx_er_status (status), ' .
                'CONSTRAINT fk_er_ebook FOREIGN KEY (ebook_id) REFERENCES ebooks (id) ON DELETE CASCADE, ' .
                'CONSTRAINT fk_er_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE, ' .
                'CONSTRAINT fk_er_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS ebook_reports');
        $pdo->exec('DROP TABLE IF EXISTS user_saved_ebooks');
        $pdo->exec('DROP TABLE IF EXISTS ebook_reading_progress');
        $pdo->exec('DROP TABLE IF EXISTS ebooks');
    },
];
