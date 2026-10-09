<?php
declare(strict_types=1);

namespace InfinityBlog\Categories;

use RuntimeException;

final class CategoryNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The category was not found.');
    }
}
