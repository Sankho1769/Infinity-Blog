<?php
declare(strict_types=1);

namespace InfinityBlog\Database;

use RuntimeException;

final class DatabaseUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Database service is unavailable.');
    }
}
