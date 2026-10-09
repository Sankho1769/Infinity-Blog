<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

interface LoginAttemptLimiter
{
    public function isLimited(string $identifier, string $sourceAddress): bool;

    public function recordFailure(string $identifier, string $sourceAddress): void;

    public function recordSuccess(string $identifier): void;
}
