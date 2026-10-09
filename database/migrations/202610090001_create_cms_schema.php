<?php
declare(strict_types=1);

return [
    'id' => '202610090001_create_cms_schema',
    'up' => static function (PDO $pdo): void {
        $statements = [
            "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(190) COLLATE utf8mb4_unicode_ci NOT NULL,
                display_name VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT uq_users_username UNIQUE (username),
                CONSTRAINT chk_users_role CHECK (role IN ('author', 'admin')),
                CONSTRAINT chk_users_status CHECK (status IN ('active', 'inactive')),
                CONSTRAINT chk_users_must_change_password CHECK (must_change_password IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
                slug VARCHAR(140) COLLATE utf8mb4_unicode_ci NOT NULL,
                description VARCHAR(500) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT uq_categories_name UNIQUE (name),
                CONSTRAINT uq_categories_slug UNIQUE (slug),
                CONSTRAINT chk_categories_active CHECK (is_active IN (0, 1)),
                CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS posts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                author_id BIGINT UNSIGNED NOT NULL,
                category_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                summary TEXT NULL,
                summary_status VARCHAR(20) NOT NULL DEFAULT 'none',
                summary_source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                summary_generated_at DATETIME NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT chk_posts_status CHECK (status IN ('draft', 'published')),
                CONSTRAINT chk_posts_summary_status CHECK (summary_status IN ('none', 'generating', 'ready', 'failed', 'stale')),
                CONSTRAINT chk_posts_published_fields CHECK (status <> 'published' OR (category_id IS NOT NULL AND published_at IS NOT NULL)),
                CONSTRAINT chk_posts_ready_summary CHECK (summary_status <> 'ready' OR (summary IS NOT NULL AND summary_source_hash IS NOT NULL AND summary_generated_at IS NOT NULL)),
                CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                INDEX idx_posts_author_status (author_id, status),
                INDEX idx_posts_status_published (status, published_at),
                INDEX idx_posts_category_status (category_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id BIGINT UNSIGNED NULL,
                action VARCHAR(80) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NULL,
                metadata JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE RESTRICT,
                INDEX idx_audit_logs_actor_created (actor_user_id, created_at),
                INDEX idx_audit_logs_entity (entity_type, entity_id),
                INDEX idx_audit_logs_action_created (action, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    },
    'down' => static function (PDO $pdo): void {
        // This initial migration is reversible only by deleting all CMS data.
        $pdo->exec('DROP TABLE IF EXISTS audit_logs');
        $pdo->exec('DROP TABLE IF EXISTS posts');
        $pdo->exec('DROP TABLE IF EXISTS categories');
        $pdo->exec('DROP TABLE IF EXISTS users');
    },
];
