<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

final class JsonResponse
{
    /** @param array<string, mixed> $body */
    public static function send(int $status, array $body): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: same-origin');
        }

        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo is_string($encoded) ? $encoded : '{"error":{"code":"INTERNAL_ERROR","message":"The request could not be completed."}}';
    }
}
