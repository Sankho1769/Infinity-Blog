<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use RuntimeException;

final class ProfileValidationException extends RuntimeException
{
    /** @var array<string, string> */
    public array $errors;

    /** @param array<string, string> $errors */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $first = reset($errors);
        parent::__construct(is_string($first) ? $first : 'The submitted profile data is invalid.');
    }
}
