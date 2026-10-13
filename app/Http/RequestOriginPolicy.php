<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

/** Adds a same-origin check to state-changing browser requests. CSRF remains required. */
final class RequestOriginPolicy
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const ACCEPTED_FETCH_SITES = ['same-origin', 'same-site', 'none'];

    public static function allows(Request $request, string $applicationBaseUrl): bool
    {
        if (in_array($request->method, self::SAFE_METHODS, true)) {
            return true;
        }

        $fetchSite = $request->header('Sec-Fetch-Site');
        if ($fetchSite !== null) {
            $fetchSite = strtolower($fetchSite);
            if (!in_array($fetchSite, self::ACCEPTED_FETCH_SITES, true)) {
                return false;
            }
        }

        $providedOrigin = $request->header('Origin');
        if ($providedOrigin === null) {
            // Non-browser clients may omit Origin; implemented mutations still require CSRF.
            return true;
        }

        $origin = self::normalizeOrigin($providedOrigin, false);
        $expectedOrigin = self::normalizeOrigin($applicationBaseUrl, true);
        if ($origin !== null && $expectedOrigin !== null && hash_equals($expectedOrigin, $origin)) {
            return true;
        }

        $host = $request->header('Host');
        if ($host !== null && $origin !== null && preg_match('/^[a-z0-9.:\-_]+$/i', $host) === 1) {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $hostOrigin = self::normalizeOrigin($scheme . '://' . $host, false);
            if ($hostOrigin !== null && hash_equals($hostOrigin, $origin)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeOrigin(string $url, bool $allowPath): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        foreach (['user', 'pass', 'query', 'fragment'] as $forbiddenPart) {
            if (array_key_exists($forbiddenPart, $parts)) {
                return null;
            }
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (!$allowPath && isset($parts['path']) && $parts['path'] !== '') {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!is_int($port) || $port < 1 || $port > 65535) {
            return null;
        }
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . ($port === $defaultPort ? '' : ':' . $port);
    }
}
