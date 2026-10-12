<?php
declare(strict_types=1);

namespace InfinityBlog\Scores;

use RuntimeException;

final class ScoreValidationException extends RuntimeException
{
    /** @var array<string, string> */
    public array $fields;

    /** @param array<string, string> $fields */
    public function __construct(string $message = 'The score submission was invalid.', array $fields = [])
    {
        parent::__construct($message);
        $this->fields = $fields;
    }
}
