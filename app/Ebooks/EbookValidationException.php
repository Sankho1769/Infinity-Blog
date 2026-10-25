<?php
declare(strict_types=1);

namespace InfinityBlog\Ebooks;

use Exception;

final class EbookValidationException extends Exception
{
    /** @var array<string, string> */
    public array $errors;

    /**
     * @param array<string, string> $errors
     */
    public function __construct(array $errors, string $message = 'eBook validation failed.')
    {
        $this->errors = $errors;
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
