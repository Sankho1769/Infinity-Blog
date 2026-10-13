<?php
declare(strict_types=1);

/**
 * Migration 202610130019: Add audio_source_hash to posts table.
 */
return [
    'id' => '202610130019_add_audio_source_hash_to_posts',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $postCols = $pdo->query('PRAGMA table_info(posts)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($postCols, 'name');
            if (!in_array('audio_source_hash', $colNames, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN audio_source_hash VARCHAR(64) NULL');
            }
        } else {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (!in_array('audio_source_hash', $existing, true)) {
                $pdo->exec('ALTER TABLE posts ADD COLUMN audio_source_hash VARCHAR(64) NULL AFTER audio_generated_at');
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (in_array('audio_source_hash', $existing, true)) {
                $pdo->exec('ALTER TABLE posts DROP COLUMN audio_source_hash');
            }
        }
    },
];
