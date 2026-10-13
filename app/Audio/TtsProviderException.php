<?php
declare(strict_types=1);

namespace InfinityBlog\Audio;

use RuntimeException;
use Throwable;

final class TtsProviderException extends RuntimeException
{
    public function __construct(string $message = 'The text-to-speech provider request failed.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
