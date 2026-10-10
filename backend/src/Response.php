<?php
declare(strict_types=1);

namespace InfinityBlog\Core;

class Response {
    private static ?string $requestId = null;

    public static function getRequestId(): string {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(16));
        }
        return self::$requestId;
    }

    public static function json(mixed $payload, int $status = 200, array $headers = []): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Request-ID: ' . self::getRequestId());
        
        foreach ($headers as $key => $val) {
            header("{$key}: {$val}");
        }

        if ($status === 204) {
            exit;
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): void {
        $body = ['data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        self::json($body, $status);
    }

    public static function error(string $code, string $message, ?array $fields = null, int $status = 400): void {
        $err = [
            'code' => $code,
            'message' => $message,
        ];
        if (!empty($fields)) {
            $err['fields'] = $fields;
        }

        self::json([
            'error' => $err,
            'request_id' => self::getRequestId(),
        ], $status);
    }
}
