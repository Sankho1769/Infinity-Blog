<?php
declare(strict_types=1);

/**
 * Migration 202610130018: Create contact_messages table.
 */
return [
    'id' => '202610130018_create_contact_messages_schema',
    'up' => static function (PDO $pdo): void {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS contact_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    message TEXT NOT NULL,
                    ip_address TEXT NULL,
                    status TEXT NOT NULL DEFAULT 'received',
                    created_at TEXT NOT NULL
                )"
            );
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS contact_messages (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    subject VARCHAR(150) NOT NULL,
                    message TEXT NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'received',
                    created_at DATETIME NOT NULL,
                    INDEX idx_contact_created (created_at),
                    INDEX idx_contact_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS contact_messages");
    },
];
