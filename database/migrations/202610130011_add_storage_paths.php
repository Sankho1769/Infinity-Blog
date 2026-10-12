<?php
declare(strict_types=1);

return [
    'id' => '202610130011_add_storage_paths',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('ALTER TABLE users ADD COLUMN banner_path TEXT NULL');
            $pdo->exec('ALTER TABLE posts ADD COLUMN cover_image_path TEXT NULL');
            $pdo->exec('ALTER TABLE posts ADD COLUMN video_path TEXT NULL');
        } else {
            // MySQL 8+ syntax
            $pdo->exec(
                'ALTER TABLE users ' .
                'ADD COLUMN banner_path VARCHAR(255) NULL AFTER avatar_path'
            );
            $pdo->exec(
                'ALTER TABLE posts ' .
                'ADD COLUMN cover_image_path VARCHAR(255) NULL AFTER category_id, ' .
                'ADD COLUMN video_path VARCHAR(255) NULL AFTER cover_image_path'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            try {
                $pdo->exec('ALTER TABLE users DROP COLUMN banner_path');
                $pdo->exec('ALTER TABLE posts DROP COLUMN cover_image_path');
                $pdo->exec('ALTER TABLE posts DROP COLUMN video_path');
            } catch (Throwable) {
                // Ignore if SQLite version doesn't support DROP COLUMN
            }
        } else {
            $pdo->exec('ALTER TABLE users DROP COLUMN banner_path');
            $pdo->exec('ALTER TABLE posts DROP COLUMN cover_image_path, DROP COLUMN video_path');
        }
    },
];
