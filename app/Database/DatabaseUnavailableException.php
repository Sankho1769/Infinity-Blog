<?php
declare(strict_types=1);

namespace InfinityBlog\Database;

use RuntimeException;
use Throwable;

final class DatabaseUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Database service is unavailable.', 0, $previous);
    }
}
