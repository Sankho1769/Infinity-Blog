<?php
declare(strict_types=1);

namespace InfinityBlog\Config;

require_once __DIR__ . '/Env.php';

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        Env::load();

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $name = Env::get('DB_NAME', 'infinity_blog');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return self::$instance;
        } catch (PDOException $e) {
            // Do not disclose DB password or raw path
            error_log("Database connection error: " . $e->getMessage());
            throw new \RuntimeException("Database service is currently unavailable.");
        }
    }

    public static function setConnection(PDO $pdo): void {
        self::$instance = $pdo;
    }
}
