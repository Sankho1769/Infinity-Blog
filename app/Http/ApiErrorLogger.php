<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use Closure;
use Throwable;

final class ApiErrorLogger
{
    /** @var Closure(string): mixed */
    private Closure $writer;

    /** @param null|callable(string): mixed $writer Test seam; production writes to PHP's error log. */
    public function __construct(?callable $writer = null)
    {
        $this->writer = $writer === null
            ? static function (string $line): void {
                @error_log($line);
            }
            : Closure::fromCallable($writer);
    }

    public function log(Throwable $exception, string $requestId): void
    {
        if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $requestId) !== 1) {
            $requestId = 'unavailable';
        }

        $line = json_encode([
            'event' => 'api.unhandled_exception',
            'request_id' => $requestId,
            'exception_type' => get_class($exception),
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($line)) {
            return;
        }

        try {
            ($this->writer)($line);
        } catch (Throwable) {
            // A private log sink failure must not change the safe public response.
        }
    }
}
