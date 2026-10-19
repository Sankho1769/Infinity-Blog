<?php
declare(strict_types=1);

namespace InfinityBlog\Reviews;

use RuntimeException;

final class ReviewValidationException extends RuntimeException
{
    /** @var array<string, string> */
    private array $fieldErrors;

    /**
     * @param array<string, string> $fieldErrors
     */
    public function __construct(string $message, array $fieldErrors = [])
    {
        parent::__construct($message);
        $this->fieldErrors = $fieldErrors;
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
