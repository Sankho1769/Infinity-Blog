<?php
declare(strict_types=1);

namespace InfinityBlog\Users;

use RuntimeException;

final class AccountConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The account conflicts with existing data.');
    }
}
