<?php
declare(strict_types=1);

namespace InfinityBlog\Config;

use InvalidArgumentException;

final class DatabaseConfig
{
    public string $host;
    public int $port;
    public string $database;
    public string $username;
    public ?string $sslCaPath;
    private string $password;

    public function __construct(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        ?string $sslCaPath = null
    )
    {
        if ($host === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $host)) {
            throw new InvalidArgumentException('DB_HOST is missing or invalid.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('DB_PORT must be between 1 and 65535.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new InvalidArgumentException('DB_DATABASE is missing or invalid.');
        }
        if ($username === '' || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            throw new InvalidArgumentException('DB_USERNAME is missing or invalid.');
        }

        $sslCaPath = $sslCaPath !== null ? trim($sslCaPath) : null;
        if ($sslCaPath === '') {
            $sslCaPath = null;
        }
        if ($sslCaPath !== null) {
            $caFile = is_file($sslCaPath) ? @fopen($sslCaPath, 'rb') : false;
            if ($caFile === false) {
                throw new InvalidArgumentException('DB_SSL_CA must point to a readable CA certificate file.');
            }
            fclose($caFile);
        }
        if (!self::isLoopbackHost($host) && $sslCaPath === null) {
            throw new InvalidArgumentException('DB_SSL_CA is required for remote database connections.');
        }

        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->sslCaPath = $sslCaPath;
    }

    public function password(): string
    {
        return $this->password;
    }

    /** @param array<string, string> $environment */
    public static function fromArray(array $environment, string $prefix = 'DB_'): self
    {
        $portValue = $environment[$prefix . 'PORT'] ?? '';
        $port = filter_var($portValue, FILTER_VALIDATE_INT);
        if ($port === false) {
            throw new InvalidArgumentException($prefix . 'PORT is missing or invalid.');
        }

        return new self(
            $environment[$prefix . 'HOST'] ?? '',
            $port,
            $environment[$prefix . 'DATABASE'] ?? '',
            $environment[$prefix . 'USERNAME'] ?? '',
            $environment[$prefix . 'PASSWORD'] ?? '',
            $environment[$prefix . 'SSL_CA'] ?? null
        );
    }

    public function usesVerifiedTls(): bool
    {
        return $this->sslCaPath !== null;
    }

    public function dsn(): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->database);
    }

    private static function isLoopbackHost(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1'], true);
    }
}
