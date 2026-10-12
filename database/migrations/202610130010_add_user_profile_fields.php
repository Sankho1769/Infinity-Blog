<?php
declare(strict_types=1);

return [
    'id' => '202610130010_add_user_profile_fields',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('ALTER TABLE users ADD COLUMN bio TEXT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN location TEXT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN interests TEXT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN website TEXT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN phone TEXT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN avatar_path TEXT NULL');
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_visibility TEXT NOT NULL DEFAULT 'public'");
        } else {
            // MySQL 8+ syntax
            $pdo->exec(
                'ALTER TABLE users ' .
                'ADD COLUMN bio TEXT NULL AFTER display_name, ' .
                'ADD COLUMN location VARCHAR(120) NULL AFTER bio, ' .
                'ADD COLUMN interests VARCHAR(255) NULL AFTER location, ' .
                'ADD COLUMN website VARCHAR(255) NULL AFTER interests, ' .
                'ADD COLUMN phone VARCHAR(30) NULL AFTER website, ' .
                'ADD COLUMN avatar_path VARCHAR(255) NULL AFTER phone, ' .
                "ADD COLUMN profile_visibility VARCHAR(20) NOT NULL DEFAULT 'public' AFTER avatar_path"
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            // SQLite does not support dropping columns directly in older versions;
            // modern SQLite (3.35+) supports DROP COLUMN.
            try {
                $pdo->exec('ALTER TABLE users DROP COLUMN bio');
                $pdo->exec('ALTER TABLE users DROP COLUMN location');
                $pdo->exec('ALTER TABLE users DROP COLUMN interests');
                $pdo->exec('ALTER TABLE users DROP COLUMN website');
                $pdo->exec('ALTER TABLE users DROP COLUMN phone');
                $pdo->exec('ALTER TABLE users DROP COLUMN avatar_path');
                $pdo->exec('ALTER TABLE users DROP COLUMN profile_visibility');
            } catch (Throwable) {
                // Ignore if SQLite version doesn't support DROP COLUMN
            }
        } else {
            $pdo->exec(
                'ALTER TABLE users ' .
                'DROP COLUMN bio, ' .
                'DROP COLUMN location, ' .
                'DROP COLUMN interests, ' .
                'DROP COLUMN website, ' .
                'DROP COLUMN phone, ' .
                'DROP COLUMN avatar_path, ' .
                'DROP COLUMN profile_visibility'
            );
        }
    },
];
