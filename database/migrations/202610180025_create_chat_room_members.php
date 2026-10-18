<?php
declare(strict_types=1);

return [
    'id' => '202610180025_create_chat_room_members',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS chat_room_members (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                'room_id INTEGER NOT NULL, ' .
                'user_id INTEGER NOT NULL, ' .
                'joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'UNIQUE (room_id, user_id), ' .
                'FOREIGN KEY (room_id) REFERENCES chat_rooms (id) ON DELETE CASCADE, ' .
                'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_crmem_room ON chat_room_members (room_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_crmem_user ON chat_room_members (user_id)');
            $pdo->exec('INSERT OR IGNORE INTO chat_room_members (room_id, user_id, joined_at) SELECT id, created_by, created_at FROM chat_rooms WHERE created_by IS NOT NULL');
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS chat_room_members (' .
                'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
                'room_id BIGINT UNSIGNED NOT NULL, ' .
                'user_id BIGINT UNSIGNED NOT NULL, ' .
                'joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                'PRIMARY KEY (id), ' .
                'UNIQUE KEY uq_crmem_room_user (room_id, user_id), ' .
                'INDEX idx_crmem_room (room_id), ' .
                'INDEX idx_crmem_user (user_id), ' .
                'CONSTRAINT fk_crmem_room FOREIGN KEY (room_id) REFERENCES chat_rooms (id) ON DELETE CASCADE ON UPDATE CASCADE, ' .
                'CONSTRAINT fk_crmem_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $pdo->exec('INSERT IGNORE INTO chat_room_members (room_id, user_id, joined_at) SELECT id, created_by, created_at FROM chat_rooms WHERE created_by IS NOT NULL');
        }
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS chat_room_members');
    },
];
