<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

use InvalidArgumentException;

final class PostValidationException extends InvalidArgumentException
{
    /** @var array<string, string> */
    public array $fields;

    /** @param array<string, string> $fields */
    public function __construct(array $fields)
    {
        parent::__construct('One or more post fields are invalid.');
        $this->fields = $fields;
    }
}
