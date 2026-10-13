<?php
declare(strict_types=1);

return [
    'id' => '202610130014_add_audio_to_posts',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('ALTER TABLE posts ADD COLUMN audio_path TEXT NULL');
            $pdo->exec("ALTER TABLE posts ADD COLUMN audio_status TEXT NOT NULL DEFAULT 'idle'");
            $pdo->exec('ALTER TABLE posts ADD COLUMN audio_duration INTEGER NULL');
            $pdo->exec('ALTER TABLE posts ADD COLUMN audio_generated_at DATETIME NULL');
        } else {
            $pdo->exec(
                'ALTER TABLE posts ' .
                'ADD COLUMN audio_path VARCHAR(255) NULL AFTER video_path, ' .
                "ADD COLUMN audio_status VARCHAR(20) NOT NULL DEFAULT 'idle' AFTER audio_path, " .
                'ADD COLUMN audio_duration INT UNSIGNED NULL AFTER audio_status, ' .
                'ADD COLUMN audio_generated_at DATETIME NULL AFTER audio_duration'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            try {
                $pdo->exec('ALTER TABLE posts DROP COLUMN audio_path');
                $pdo->exec('ALTER TABLE posts DROP COLUMN audio_status');
                $pdo->exec('ALTER TABLE posts DROP COLUMN audio_duration');
                $pdo->exec('ALTER TABLE posts DROP COLUMN audio_generated_at');
            } catch (Throwable) {
            }
        } else {
            $pdo->exec(
                'ALTER TABLE posts ' .
                'DROP COLUMN audio_path, ' .
                'DROP COLUMN audio_status, ' .
                'DROP COLUMN audio_duration, ' .
                'DROP COLUMN audio_generated_at'
            );
        }
    },
];
