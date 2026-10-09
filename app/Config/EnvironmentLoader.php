<?php
declare(strict_types=1);

namespace InfinityBlog\Config;

use InvalidArgumentException;

final class EnvironmentLoader
{
    /**
     * Read a simple KEY=value file. Existing process environment values win.
     * Values are not interpolated or executed.
     *
     * @param array<string, string|false> $environment
     * @return array<string, string>
     */
    public static function loadFile(string $path, array $environment): array
    {
        if (!is_file($path)) {
            return self::normalizeEnvironment($environment);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new InvalidArgumentException('The environment file could not be read.');
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                throw new InvalidArgumentException('The environment file contains an invalid entry on line ' . ($lineNumber + 1) . '.');
            }

            $key = $matches[1];
            if (array_key_exists($key, $environment) && $environment[$key] !== false) {
                continue;
            }

            $value = trim($matches[2]);
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                } elseif ($first === '"' || $first === "'") {
                    throw new InvalidArgumentException('The environment file contains an unterminated quoted value on line ' . ($lineNumber + 1) . '.');
                }
            } elseif ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                throw new InvalidArgumentException('The environment file contains an unterminated quoted value on line ' . ($lineNumber + 1) . '.');
            }

            $environment[$key] = $value;
        }

        return self::normalizeEnvironment($environment);
    }

    /** @param array<string, string|false> $environment
     *  @return array<string, string>
     */
    private static function normalizeEnvironment(array $environment): array
    {
        $normalized = [];
        foreach ($environment as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
