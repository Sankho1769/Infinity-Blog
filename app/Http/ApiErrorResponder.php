<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

final class ApiErrorResponder
{
    /**
     * Build the documented error envelope. Callers must provide a safe public message.
     * Internal failures should use internalFailure() instead of passing exception text.
     *
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    public static function build(
        string $code,
        string $message,
        array $fields = [],
        ?string $requestId = null
    ): array {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return [
            'error' => $error,
            'request_id' => self::requestId($requestId),
        ];
    }

    /** @return array<string, mixed> */
    public static function internalFailure(?string $requestId = null): array
    {
        return self::build('INTERNAL_ERROR', 'The request could not be completed.', [], $requestId);
    }

    private static function requestId(?string $candidate): string
    {
        if ($candidate !== null && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return bin2hex(random_bytes(16));
    }
}
