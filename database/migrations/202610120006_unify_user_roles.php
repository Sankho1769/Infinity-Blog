<?php
declare(strict_types=1);

return [
    'id' => '202610120006_unify_user_roles',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'ALTER TABLE users DROP CHECK chk_users_role, ' .
            "ADD CONSTRAINT chk_users_role CHECK (role IN ('user', 'author', 'reader', 'admin'))"
        );
        $pdo->exec(
            "UPDATE users SET role = 'user' WHERE role IN ('reader', 'author')"
        );
        $pdo->exec(
            'ALTER TABLE users DROP CHECK chk_users_role, ' .
            "ADD CONSTRAINT chk_users_role CHECK (role IN ('user', 'admin'))"
        );
        $pdo->exec(
            "ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user'"
        );
    },
    'down' => static function (PDO $pdo): void {
        $pdo->exec(
            'ALTER TABLE users DROP CHECK chk_users_role, ' .
            "ADD CONSTRAINT chk_users_role CHECK (role IN ('user', 'author', 'reader', 'admin'))"
        );
        $pdo->exec(
            "UPDATE users SET role = 'author' WHERE role = 'user'"
        );
        $pdo->exec(
            'ALTER TABLE users DROP CHECK chk_users_role, ' .
            "ADD CONSTRAINT chk_users_role CHECK (role IN ('reader', 'author', 'admin'))"
        );
        $pdo->exec(
            "ALTER TABLE users ALTER COLUMN role SET DEFAULT 'author'"
        );
    },
];
