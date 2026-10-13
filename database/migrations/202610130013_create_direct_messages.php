<?php
declare(strict_types=1);

return [
    'id' => '202610130013_create_direct_messages',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS direct_messages (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'sender_id INTEGER NOT NULL, ' .
                'recipient_id INTEGER NOT NULL, ' .
                'body TEXT NOT NULL, ' .
                'read_at DATETIME NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
                ')'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS direct_messages (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'sender_id BIGINT UNSIGNED NOT NULL, ' .
                'recipient_id BIGINT UNSIGNED NOT NULL, ' .
                'body TEXT NOT NULL, ' .
                'read_at DATETIME NULL, ' .
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'INDEX idx_dm_sender (sender_id), ' .
                'INDEX idx_dm_recipient (recipient_id), ' .
                'INDEX idx_dm_conversation (sender_id, recipient_id, created_at), ' .
                'INDEX idx_dm_recipient_read (recipient_id, read_at), ' .
                'CONSTRAINT fk_dm_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_dm_recipient FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS direct_messages');
    },
];
