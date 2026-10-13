<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

/**
 * High-performance, secure Markdown & Media Content Renderer for Infinity Blog.
 * Converts article Markdown into semantic, glassmorphism-friendly HTML.
 * Supports inline YouTube/Vimeo embeds and uploaded private/local MP4/WebM videos.
 * Strictly prevents XSS by escaping raw HTML before parsing.
 */
final class ArticleContentRenderer
{
    /** @var array<string, string> Optional map of raw video storage paths to resolved signed URLs */
    private array $signedVideoUrls = [];

    /**
     * @param array<string, string> $signedVideoUrls
     */
    public function __construct(array $signedVideoUrls = [])
    {
        $this->signedVideoUrls = $signedVideoUrls;
    }

    /**
     * Set or merge signed video URLs.
     *
     * @param array<string, string> $urls
     */
    public function setSignedVideoUrls(array $urls): self
    {
        $this->signedVideoUrls = array_merge($this->signedVideoUrls, $urls);
        return $this;
    }

    /**
     * Render raw article Markdown content to secure HTML.
     */
    public function render(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        // Normalize line endings to \n
        $text = str_replace(["\r\n", "\r"], "\n", $trimmed);
        $lines = explode("\n", $text);

        $output = [];
        $inCodeBlock = false;
        $codeBlockLang = '';
        $codeBlockLines = [];

        $inQuote = false;
        $quoteLines = [];

        $listType = null; // 'ul' or 'ol'
        $listItems = [];

        $paraLines = [];

        $flushParagraph = function () use (&$paraLines, &$output) {
            if (!empty($paraLines)) {
                $joined = implode("\n", $paraLines);
                $escaped = htmlspecialchars($joined, ENT_QUOTES, 'UTF-8');
                $formatted = $this->renderInlineElements($escaped);
                $output[] = '<p>' . nl2br($formatted) . '</p>';
                $paraLines = [];
            }
        };

        $flushQuote = function () use (&$inQuote, &$quoteLines, &$output) {
            if ($inQuote && !empty($quoteLines)) {
                $joined = implode("\n", $quoteLines);
                $escaped = htmlspecialchars($joined, ENT_QUOTES, 'UTF-8');
                $formatted = $this->renderInlineElements($escaped);
                $output[] = '<blockquote><p>' . nl2br($formatted) . '</p></blockquote>';
                $quoteLines = [];
                $inQuote = false;
            }
        };

        $flushList = function () use (&$listType, &$listItems, &$output) {
            if ($listType !== null && !empty($listItems)) {
                $itemsHtml = '';
                foreach ($listItems as $item) {
                    $escaped = htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
                    $itemsHtml .= '<li>' . $this->renderInlineElements($escaped) . '</li>';
                }
                $output[] = "<{$listType}>{$itemsHtml}</{$listType}>";
                $listType = null;
                $listItems = [];
            }
        };

        $flushAll = function () use (&$flushParagraph, &$flushQuote, &$flushList) {
            $flushParagraph();
            $flushQuote();
            $flushList();
        };

        foreach ($lines as $line) {
            // Check code block fence ```lang
            if (preg_match('/^```([a-zA-Z0-9_\-\+]*)\s*$/', $line, $fenceM)) {
                if ($inCodeBlock) {
                    // Close code block
                    $code = htmlspecialchars(implode("\n", $codeBlockLines), ENT_QUOTES, 'UTF-8');
                    $classAttr = $codeBlockLang !== '' ? ' class="language-' . htmlspecialchars($codeBlockLang, ENT_QUOTES, 'UTF-8') . '"' : '';
                    $output[] = '<pre class="article-code-block"><code' . $classAttr . '>' . $code . '</code></pre>';
                    $inCodeBlock = false;
                    $codeBlockLines = [];
                    $codeBlockLang = '';
                } else {
                    // Open code block
                    $flushAll();
                    $inCodeBlock = true;
                    $codeBlockLang = trim($fenceM[1]);
                    $codeBlockLines = [];
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeBlockLines[] = $line;
                continue;
            }

            $trimmedLine = trim($line);

            // Empty line: flush paragraph and lists/quotes
            if ($trimmedLine === '') {
                $flushAll();
                continue;
            }

            // Headings: # H1 through ###### H6
            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmedLine, $hM)) {
                $flushAll();
                $level = strlen($hM[1]);
                $headingText = $this->renderInlineElements(htmlspecialchars(trim($hM[2]), ENT_QUOTES, 'UTF-8'));
                $output[] = "<h{$level}>{$headingText}</h{$level}>";
                continue;
            }

            // Standalone video: [video](...) or [video:...] or standalone YouTube/Vimeo/uploaded URL
            $videoHtml = $this->renderStandaloneVideo($trimmedLine);
            if ($videoHtml !== null) {
                $flushAll();
                $output[] = $videoHtml;
                continue;
            }

            // Standalone image: ![alt](url)
            if (preg_match('/^!\[(.*?)\]\((.*?)\)$/', $trimmedLine, $imgM)) {
                $flushAll();
                $output[] = $this->renderImage($imgM[1], $imgM[2]);
                continue;
            }

            // Horizontal rule: --- or *** or ___
            if (preg_match('/^(---|___|\*\*\*)$/', $trimmedLine)) {
                $flushAll();
                $output[] = '<hr class="article-divider">';
                continue;
            }

            // Blockquote: > text
            if (str_starts_with($trimmedLine, '>')) {
                $flushParagraph();
                $flushList();
                $inQuote = true;
                $quoteLines[] = preg_replace('/^>\s?/', '', $line);
                continue;
            } elseif ($inQuote) {
                $flushQuote();
            }

            // Unordered list item: - or *
            if (preg_match('/^[\*\-]\s+(.+)$/', $trimmedLine, $ulM)) {
                $flushParagraph();
                $flushQuote();
                if ($listType !== 'ul') {
                    $flushList();
                    $listType = 'ul';
                }
                $listItems[] = $ulM[1];
                continue;
            }

            // Ordered list item: 1. 2. etc.
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmedLine, $olM)) {
                $flushParagraph();
                $flushQuote();
                if ($listType !== 'ol') {
                    $flushList();
                    $listType = 'ol';
                }
                $listItems[] = $olM[1];
                continue;
            }

            // If we are currently inside a list and hit normal text, flush the list
            if ($listType !== null) {
                $flushList();
            }

            // Standard line inside paragraph
            $paraLines[] = $line;
        }

        // Flush remaining buffers
        if ($inCodeBlock) {
            $code = htmlspecialchars(implode("\n", $codeBlockLines), ENT_QUOTES, 'UTF-8');
            $classAttr = $codeBlockLang !== '' ? ' class="language-' . htmlspecialchars($codeBlockLang, ENT_QUOTES, 'UTF-8') . '"' : '';
            $output[] = '<pre class="article-code-block"><code' . $classAttr . '>' . $code . '</code></pre>';
        }
        $flushAll();

        return implode("\n\n", $output);
    }

    /**
     * Render inline Markdown elements: inline code, bold, italic, strikethrough, images, links.
     */
    private function renderInlineElements(string $text): string
    {
        // 1. Inline code: `code`
        $inlineCodes = [];
        $text = preg_replace_callback('/`([^`\n]+)`/', function ($matches) use (&$inlineCodes) {
            $code = $matches[1];
            $placeholder = '###INLINECODE_' . count($inlineCodes) . '###';
            $inlineCodes[$placeholder] = '<code class="article-inline-code">' . $code . '</code>';
            return $placeholder;
        }, $text);

        // 2. Inline images: ![alt](url)
        $text = preg_replace_callback('/!\[(.*?)\]\((.*?)\)/', function ($m) {
            return $this->renderImage($m[1], $m[2]);
        }, $text);

        // 3. Inline video: [video](url) or [video:url]
        $text = preg_replace_callback('/\[video\]\((.*?)\)|\[video:(.*?)\]/i', function ($m) {
            $rawUrl = !empty($m[1]) ? $m[1] : ($m[2] ?? '');
            $rendered = $this->renderStandaloneVideo($rawUrl);
            return $rendered ?? '';
        }, $text);

        // 4. Links: [text](url)
        $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function ($m) {
            $label = $m[1];
            $url = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            $safeUrl = $this->sanitizeUrl($url);
            if ($safeUrl === null) {
                return $label;
            }
            $isExternal = str_starts_with($safeUrl, 'http://') || str_starts_with($safeUrl, 'https://');
            $targetAttr = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
            return '<a href="' . htmlspecialchars($safeUrl, ENT_QUOTES, 'UTF-8') . '"' . $targetAttr . ' class="article-content-link">' . $label . '</a>';
        }, $text);

        // 5. Bold: **text** or __text__
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/s', '<strong>$1</strong>', $text);

        // 6. Italic: *text* or _text_
        $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!_)_(?!_)(.*?)(?<!_)_(?!_)/s', '<em>$1</em>', $text);

        // 7. Strikethrough: ~~text~~
        $text = preg_replace('/~~(.*?)~~/s', '<del>$1</del>', $text);

        // 8. Restore inline codes
        foreach ($inlineCodes as $placeholder => $codeHtml) {
            $text = str_replace($placeholder, $codeHtml, $text);
        }

        return $text;
    }

    /**
     * Render an image figure with caption.
     */
    private function renderImage(string $alt, string $url): string
    {
        $decodedUrl = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
        $safeUrl = $this->sanitizeUrl($decodedUrl);
        if ($safeUrl === null) {
            return '';
        }

        $altClean = htmlspecialchars(trim($alt), ENT_QUOTES, 'UTF-8');
        $captionHtml = $altClean !== '' ? '<figcaption class="article-image-caption">' . $altClean . '</figcaption>' : '';

        return '<figure class="article-inline-figure">'
            . '<img src="' . htmlspecialchars($safeUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $altClean . '" class="article-inline-image" loading="lazy">'
            . $captionHtml
            . '</figure>';
    }

    /**
     * Parse and render a standalone video URL or directive.
     * Supports YouTube, Vimeo, and MP4/WebM uploads.
     */
    public function renderStandaloneVideo(string $block): ?string
    {
        $trimmed = html_entity_decode(trim($block), ENT_QUOTES, 'UTF-8');

        // Match [video](url) or [video:url]
        if (preg_match('/^\[video\]\((.*?)\)$/i', $trimmed, $m) || preg_match('/^\[video:(.*?)\]$/i', $trimmed, $m)) {
            $trimmed = trim($m[1]);
        }

        // Check YouTube
        $youtubeId = self::extractYouTubeId($trimmed);
        if ($youtubeId !== null) {
            return '<div class="article-video-container responsive-video-wrapper">'
                . '<iframe src="https://www.youtube-nocookie.com/embed/' . htmlspecialchars($youtubeId, ENT_QUOTES, 'UTF-8') . '" '
                . 'title="YouTube Video Player" frameborder="0" '
                . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" '
                . 'allowfullscreen loading="lazy"></iframe>'
                . '</div>';
        }

        // Check Vimeo
        $vimeoId = self::extractVimeoId($trimmed);
        if ($vimeoId !== null) {
            return '<div class="article-video-container responsive-video-wrapper">'
                . '<iframe src="https://player.vimeo.com/video/' . htmlspecialchars($vimeoId, ENT_QUOTES, 'UTF-8') . '" '
                . 'title="Vimeo Video Player" frameborder="0" '
                . 'allow="autoplay; fullscreen; picture-in-picture" '
                . 'allowfullscreen loading="lazy"></iframe>'
                . '</div>';
        }

        // Check Uploaded Video (MP4 / WebM / Supabase storage path)
        if ($this->isUploadedVideoPath($trimmed)) {
            $playbackUrl = $this->resolveVideoPlaybackUrl($trimmed);
            if ($playbackUrl !== null) {
                $ext = strtolower(pathinfo($playbackUrl, PATHINFO_EXTENSION));
                $mimeType = ($ext === 'webm') ? 'video/webm' : 'video/mp4';

                return '<div class="article-video-container responsive-video-wrapper">'
                    . '<video controls playsinline preload="metadata" class="article-inline-video" style="width: 100%; border-radius: var(--radius-lg); background: #000;">'
                    . '<source src="' . htmlspecialchars($playbackUrl, ENT_QUOTES, 'UTF-8') . '" type="' . $mimeType . '">'
                    . 'Your browser does not support HTML5 video playback.'
                    . '</video>'
                    . '</div>';
            }
        }

        return null;
    }

    /**
     * Extract video ID from common YouTube URL patterns.
     */
    public static function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_\-]{11})#i',
            '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_\-]{11})#i',
            '#(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_\-]{11})#i',
            '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_\-]{11})#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Extract numeric ID from common Vimeo URL patterns.
     */
    public static function extractVimeoId(string $url): ?string
    {
        if (preg_match('#(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/[^\/]*\/videos\/|album\/(?:\d+\/)?video\/|video\/|)(\d+)#i', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#(?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Determine if a string looks like an uploaded video path or direct MP4/WebM URL.
     */
    private function isUploadedVideoPath(string $path): bool
    {
        $clean = trim($path);
        if ($clean === '') {
            return false;
        }

        if (str_starts_with($clean, 'videos/') || str_starts_with($clean, '/uploads/videos/') || str_starts_with($clean, 'uploads/videos/')) {
            return true;
        }

        $parsed = parse_url($clean, PHP_URL_PATH);
        if (is_string($parsed)) {
            $ext = strtolower(pathinfo($parsed, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve actual playable URL for an uploaded video.
     */
    private function resolveVideoPlaybackUrl(string $path): ?string
    {
        $clean = trim($path);

        // Check if pre-resolved signed URL is in map
        if (isset($this->signedVideoUrls[$clean])) {
            return $this->signedVideoUrls[$clean];
        }

        // Direct local or external URL
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://') || str_starts_with($clean, '/uploads/')) {
            return $clean;
        }

        if (str_starts_with($clean, 'uploads/')) {
            return '/' . $clean;
        }

        // If it's a Supabase path like videos/u20_....mp4, attempt on-demand signed URL
        try {
            $storageConfig = \InfinityBlog\Storage\SupabaseStorageConfig::fromEnvironment();
            if ($storageConfig !== null && $storageConfig->isConfigured()) {
                $client = new \InfinityBlog\Storage\SupabaseStorageClient($storageConfig);
                return $client->createSignedUrl($storageConfig->bucketVideos, $clean, 3600);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Sanitize and validate URL for security.
     */
    private function sanitizeUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        // Allow relative URLs starting with /
        if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
            return $trimmed;
        }

        // Allow mailto:
        if (str_starts_with($trimmed, 'mailto:')) {
            return $trimmed;
        }

        // Allow http:// and https://
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $parsed = parse_url($trimmed);
            if (is_array($parsed) && isset($parsed['scheme'], $parsed['host']) && in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Extract all video paths from article content so caller can batch-sign them.
     *
     * @return list<string>
     */
    public static function extractVideoPaths(string $content): array
    {
        $paths = [];

        // Check [video](...) and [video:...]
        if (preg_match_all('/\[video\]\((.*?)\)|\[video:(.*?)\]/i', $content, $matches)) {
            foreach ($matches[1] as $idx => $m1) {
                $p = trim($m1 !== '' ? $m1 : ($matches[2][$idx] ?? ''));
                if ($p !== '' && (str_starts_with($p, 'videos/') || str_starts_with($p, 'uploads/videos/'))) {
                    $paths[] = $p;
                }
            }
        }

        // Check standalone lines
        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, 'videos/') || str_starts_with($trimmed, 'uploads/videos/')) {
                $paths[] = $trimmed;
            }
        }

        return array_values(array_unique($paths));
    }
}
