<?php
declare(strict_types=1);

namespace InfinityBlog\Community;

final class CommunityNameFormatter
{
    /**
     * Format a community or chat room name for clean user-facing presentation
     * by stripping any decorative leading hash and whitespace, while preserving
     * the underlying room identifier and database values.
     *
     * Example: "#Web Development" or "Web Development" -> "Web Development"
     */
    public static function format(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        // Strip any leading '#' symbols and following whitespace
        $trimmed = ltrim($name, "# \t\n\r\0\x0B");

        return $trimmed !== '' ? $trimmed : $name;
    }
}
