<?php
declare(strict_types=1);

namespace InfinityBlog\Database;

use InfinityBlog\Config\DatabaseConfig;
use PDO;
use PDOException;

final class ConnectionFactory
{
    public static function create(DatabaseConfig $config, int $timeoutSeconds = 3): PDO
    {
        return self::connect($config, $timeoutSeconds, false);
    }

    public static function createForCliDiagnostics(DatabaseConfig $config, int $timeoutSeconds = 3): PDO
    {
        return self::connect($config, $timeoutSeconds, true);
    }

    private static function connect(DatabaseConfig $config, int $timeoutSeconds, bool $preserveCause): PDO
    {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $timeoutSeconds,
            ];

            if ($config->usesVerifiedTls()) {
                if (!defined('PDO::MYSQL_ATTR_SSL_CA') || !defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    throw new DatabaseUnavailableException();
                }

                $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $config->sslCaPath;
                $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
            }

            return new PDO(
                $config->dsn(),
                $config->username,
                $config->password(),
                $options
            );
        } catch (PDOException $exception) {
            // General callers get no driver details; the local credential CLI can opt into safe-code diagnostics.
            throw new DatabaseUnavailableException($preserveCause ? $exception : null);
        }
    }
}
