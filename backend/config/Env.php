<?php
declare(strict_types=1);

namespace InfinityBlog\Config;

/**
 * Lightweight, zero-dependency environment configuration loader (.env).
 * Loads key-value pairs into $_ENV, $_SERVER, and putenv().
 */
class Env {
    private static bool $loaded = false;

    /**
     * Load environment variables from a .env file.
     */
    public static function load(?string $filePath = null): void {
        if (self::$loaded) {
            return;
        }

        if ($filePath === null) {
            $filePath = dirname(__DIR__, 2) . '/.env';
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            self::$loaded = true;
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip empty lines or full comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Split on the first '=' character
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Validate key name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                continue;
            }

            // Handle quoted values
            if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
                $value = str_replace(['\n', '\r', '\t', '\"'], ["\n", "\r", "\t", '"'], $value);
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            } else {
                // Strip inline comment if unquoted
                $commentPos = strpos($value, ' #');
                if ($commentPos !== false) {
                    $value = trim(substr($value, 0, $commentPos));
                }
            }

            // Set environment variable if not already explicitly set in server environment
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
            }
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }
            if (!isset($_SERVER[$name])) {
                $_SERVER[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Retrieve environment variable with fallback.
     */
    public static function get(string $key, mixed $default = null): mixed {
        self::load();
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }
}
