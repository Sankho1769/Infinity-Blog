<?php
declare(strict_types=1);

/**
 * Migration: 202610250035_add_client_msg_id_to_direct_messages
 * Description: Add client_msg_id to direct_messages for safe idempotency, duplicate protection, and retry handling.
 */
return [
    'id' => '202610250035_add_client_msg_id_to_direct_messages',
    'up' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $cols = $pdo->query('PRAGMA table_info(direct_messages)')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_map('strtolower', array_column($cols, 'name'));
            if (!in_array('client_msg_id', $colNames, true)) {
                $pdo->exec('ALTER TABLE direct_messages ADD COLUMN client_msg_id TEXT NULL');
            }
        } else {
            // MySQL
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'direct_messages'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!in_array('client_msg_id', $existing, true)) {
                $pdo->exec('ALTER TABLE direct_messages ADD COLUMN client_msg_id VARCHAR(64) NULL AFTER recipient_id');
                try {
                    $pdo->exec('ALTER TABLE direct_messages ADD INDEX idx_dm_client_msg (sender_id, client_msg_id)');
                } catch (\Throwable) {
                    // Index may already exist
                }
            }
        }
    },
    'down' => static function (PDO $pdo): void {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            try {
                $pdo->exec('ALTER TABLE direct_messages DROP COLUMN client_msg_id');
            } catch (\Throwable) {
            }
        } else {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'direct_messages'");
            $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (in_array('client_msg_id', $existing, true)) {
                try {
                    $pdo->exec('ALTER TABLE direct_messages DROP INDEX idx_dm_client_msg');
                } catch (\Throwable) {
                }
                $pdo->exec('ALTER TABLE direct_messages DROP COLUMN client_msg_id');
            }
        }
    },
];
