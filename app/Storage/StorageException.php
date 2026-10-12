<?php
declare(strict_types=1);

namespace InfinityBlog\Storage;

use RuntimeException;

final class StorageException extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(string $message, string $errorCode = 'STORAGE_ERROR', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
