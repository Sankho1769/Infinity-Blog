<?php
declare(strict_types=1);

namespace InfinityBlog\Users;

use RuntimeException;

final class AccountNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The author account was not found.');
    }
}
