<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use RuntimeException;

final class RequestException extends RuntimeException
{
    public int $httpStatus;
    public string $errorCode;

    public function __construct(int $httpStatus, string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
    }
}
