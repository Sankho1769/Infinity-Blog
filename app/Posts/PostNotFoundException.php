<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

use RuntimeException;

final class PostNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The post was not found.');
    }
}
