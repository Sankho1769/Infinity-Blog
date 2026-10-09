<?php
declare(strict_types=1);

namespace InfinityBlog\Categories;

use RuntimeException;

final class CategoryConflictException extends RuntimeException
{
    public const NAME_EXISTS = 'name_exists';
    public const IN_USE = 'in_use';
    public const SLUG_UNAVAILABLE = 'slug_unavailable';

    public string $reason;

    public function __construct(string $reason)
    {
        parent::__construct('The category change conflicts with existing data.');
        $this->reason = $reason;
    }
}
