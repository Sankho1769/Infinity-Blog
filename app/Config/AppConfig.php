<?php
declare(strict_types=1);

namespace InfinityBlog\Config;

use InvalidArgumentException;

final class AppConfig
{
    public string $environment;
    public bool $debug;
    public string $baseUrl;
    public bool $secureSessionCookie;
    public DatabaseConfig $database;
    private string $applicationKey;

    private function __construct(
        string $environment,
        bool $debug,
        string $baseUrl,
        bool $secureSessionCookie,
        DatabaseConfig $database,
        string $applicationKey
    ) {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->baseUrl = $baseUrl;
        $this->secureSessionCookie = $secureSessionCookie;
        $this->database = $database;
        $this->applicationKey = $applicationKey;
    }

    /** @param array<string, string> $environment */
    public static function fromArray(array $environment): self
    {
        $appEnvironment = $environment['APP_ENV'] ?? '';
        if (!in_array($appEnvironment, ['development', 'test', 'production'], true)) {
            throw new InvalidArgumentException('APP_ENV must be development, test, or production.');
        }

        $debug = self::booleanValue($environment['APP_DEBUG'] ?? null, 'APP_DEBUG');
        $secureCookie = self::booleanValue($environment['SESSION_SECURE_COOKIE'] ?? null, 'SESSION_SECURE_COOKIE');
        $applicationKey = $environment['APP_KEY'] ?? '';
        if (preg_match('/^[a-f0-9]{64}$/i', $applicationKey) !== 1) {
            throw new InvalidArgumentException('APP_KEY must contain 64 hexadecimal characters.');
        }
        $baseUrl = $environment['APP_BASE_URL'] ?? '';
        $parsedUrl = filter_var($baseUrl, FILTER_VALIDATE_URL) ? parse_url($baseUrl) : false;
        if (!is_array($parsedUrl) || !isset($parsedUrl['scheme'], $parsedUrl['host']) || !in_array($parsedUrl['scheme'], ['http', 'https'], true)) {
            throw new InvalidArgumentException('APP_BASE_URL must be an absolute HTTP or HTTPS URL.');
        }

        $database = DatabaseConfig::fromArray($environment);
        if ($appEnvironment === 'production') {
            if ($debug) {
                throw new InvalidArgumentException('APP_DEBUG must be false in production.');
            }
            if (!$secureCookie || $parsedUrl['scheme'] !== 'https') {
                throw new InvalidArgumentException('Production requires HTTPS and secure session cookies.');
            }
            $host = strtolower($parsedUrl['host']);
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
                throw new InvalidArgumentException('Production APP_BASE_URL cannot use localhost, loopback, or local domains.');
            }
            if ($database->username === 'root' || $database->password() === '') {
                throw new InvalidArgumentException('Production requires a dedicated database account with a password.');
            }
            if (!$database->usesVerifiedTls()) {
                throw new InvalidArgumentException('Production requires a trusted database CA certificate and server certificate verification.');
            }
        }

        return new self($appEnvironment, $debug, $baseUrl, $secureCookie, $database, strtolower($applicationKey));
    }

    /** @param array<string, string|false>|null $environment */
    public static function fromEnvironment(?array $environment = null, ?string $envFile = null): self
    {
        $environment = $environment ?? getenv();
        $environment = EnvironmentLoader::loadFile($envFile ?? dirname(__DIR__, 2) . '/.env', $environment);

        return self::fromArray($environment);
    }

    private static function booleanValue(?string $value, string $name): bool
    {
        if ($value === null) {
            throw new InvalidArgumentException($name . ' is required.');
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new InvalidArgumentException($name . ' must be true or false.');
        }

        return $parsed;
    }

    public function applicationKey(): string
    {
        return $this->applicationKey;
    }
}
