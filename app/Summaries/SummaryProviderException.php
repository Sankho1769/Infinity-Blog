<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

use RuntimeException;

final class SummaryProviderException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The configured summary provider could not complete the request.');
    }
}
