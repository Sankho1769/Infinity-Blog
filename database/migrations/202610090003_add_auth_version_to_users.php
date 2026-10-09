<?php
declare(strict_types=1);

return [
    'id' => '202610090003_add_auth_version_to_users',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN auth_version BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER password_hash'
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE users DROP COLUMN auth_version');
    },
];
