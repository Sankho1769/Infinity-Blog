<?php
declare(strict_types=1);

/**
 * Migration 202610260037: Add content_type to posts table.
 *
 * Adds:
 * - content_type: VARCHAR(32) NOT NULL DEFAULT 'article'
 *
 * Allowed values: 'article', 'blog', 'research_paper', 'photo'
 * Existing posts are cleanly backfilled to 'article'.
 */
return [
    'id' => '202610260037_add_content_type_to_posts',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(posts)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_map('strtolower', array_column($cols, 'name'));

            if (!in_array('content_type', $colNames, true)) {
                $pdo->exec("ALTER TABLE posts ADD COLUMN content_type TEXT NOT NULL DEFAULT 'article'");
            }
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_content_type ON posts (content_type, status)");
            $pdo->exec("UPDATE posts SET content_type = 'article' WHERE content_type IS NULL OR content_type = ''");
        } else {
            // MySQL
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('content_type', $existing, true)) {
                $pdo->exec("ALTER TABLE posts ADD COLUMN content_type VARCHAR(32) NOT NULL DEFAULT 'article' AFTER status");
            }

            $indexStmt = $pdo->query("SHOW INDEX FROM posts WHERE Key_name = 'idx_posts_content_type'");
            if ($indexStmt->fetch() === false) {
                $pdo->exec("ALTER TABLE posts ADD INDEX idx_posts_content_type (content_type, status)");
            }

            $pdo->exec("UPDATE posts SET content_type = 'article' WHERE content_type IS NULL OR content_type = ''");
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            try {
                $pdo->exec("DROP INDEX IF EXISTS idx_posts_content_type");
                $pdo->exec("ALTER TABLE posts DROP COLUMN content_type");
            } catch (Throwable) {
                // SQLite older versions do not support DROP COLUMN
            }
        } else {
            $indexStmt = $pdo->query("SHOW INDEX FROM posts WHERE Key_name = 'idx_posts_content_type'");
            if ($indexStmt->fetch() !== false) {
                $pdo->exec("ALTER TABLE posts DROP INDEX idx_posts_content_type");
            }

            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (in_array('content_type', $existing, true)) {
                $pdo->exec("ALTER TABLE posts DROP COLUMN `content_type`");
            }
        }
    },
];
