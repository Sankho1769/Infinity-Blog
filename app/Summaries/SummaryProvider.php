<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

interface SummaryProvider
{
    public function summarize(string $title, string $content): string;
}
