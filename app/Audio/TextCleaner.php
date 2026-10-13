<?php
declare(strict_types=1);

namespace InfinityBlog\Audio;

final class TextCleaner
{
    /**
     * Clean markdown and HTML content into natural, fluid spoken text for TTS.
     */
    public static function cleanForSpeech(string $title, string $content): string
    {
        // 1. Remove code blocks entirely (fenced with ```)
        $text = preg_replace('/```[\s\S]*?```/', '', $content);

        // 2. Remove inline code
        $text = preg_replace('/`[^`]+`/', '', (string) $text);

        // 3. Remove raw HTML tags (video players, scripts, etc.)
        $text = preg_replace('/<script[\s\S]*?<\/script>/i', '', (string) $text);
        $text = preg_replace('/<style[\s\S]*?<\/style>/i', '', (string) $text);
        $text = strip_tags((string) $text);

        // 4. Convert markdown links [text](url) -> text
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', (string) $text);

        // 5. Remove images ![alt](url)
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', (string) $text);

        // 6. Convert headings # Title -> Title.
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '$1. ', (string) $text);

        // 7. Convert bullet points - Item -> Item,
        $text = preg_replace('/^[-*•]\s+(.+)$/m', '$1, ', (string) $text);

        // 8. Clean up symbols & markdown styling
        $text = preg_replace('/[*_~]/', '', (string) $text);

        // 9. Decode HTML entities
        $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');

        // 10. Normalize whitespace
        $text = preg_replace('/\s+/', ' ', (string) $text);

        $cleanTitle = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
        $cleanBody = trim((string) $text);

        return $cleanTitle . '. ' . $cleanBody;
    }
}
