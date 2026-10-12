<?php
declare(strict_types=1);

return [
    'id' => '202610120005_add_reader_role_and_email_to_users',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username, ' .
            'ADD CONSTRAINT uq_users_email UNIQUE (email)'
        );
        $pdo->exec(
            'ALTER TABLE users DROP CHECK chk_users_role, ' .
            "ADD CONSTRAINT chk_users_role CHECK (role IN ('reader', 'author', 'admin'))"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec(
            'ALTER TABLE users DROP CHECK chk_users_role, ' .
            "ADD CONSTRAINT chk_users_role CHECK (role IN ('author', 'admin'))"
        );
        $pdo->exec('ALTER TABLE users DROP COLUMN email');
    },
];
