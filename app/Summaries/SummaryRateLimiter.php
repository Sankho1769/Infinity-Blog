<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

interface SummaryRateLimiter
{
    public function consume(int $actorId, int $postId, string $sourceAddress): void;
}
