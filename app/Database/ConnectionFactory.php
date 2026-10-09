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
            // Do not chain PDO's error: drivers may include connection details in it.
            throw new DatabaseUnavailableException();
        }
    }
}
