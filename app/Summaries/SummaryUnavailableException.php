<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

use RuntimeException;
use Throwable;

final class SummaryUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Summary generation is temporarily unavailable.', 0, $previous);
    }
}
