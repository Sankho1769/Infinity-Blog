<?php
declare(strict_types=1);

namespace InfinityBlog\Ebooks;

use SimpleXMLElement;
use Throwable;
use ZipArchive;

final class EpubParser
{
    /**
     * Inspect and validate an EPUB file.
     * Enforces format integrity, Zip Slip prevention, entry limits, and Zip Bomb defenses.
     * Returns true if valid EPUB, false otherwise.
     */
    public static function isValidEpub(string $filePath): bool
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        try {
            // 1. Guard against excessive entry count (entry exhaustion)
            if ($zip->numFiles > 1000 || $zip->numFiles < 2) {
                $zip->close();
                return false;
            }

            // 2. Guard against Zip Bomb / unreasonable expansion size (100MB max uncompressed)
            $totalUncompressed = 0;
            $maxUncompressed = 104857600; // 100MB
            $dangerousExtensions = [
                'php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'py', 'pl',
                'jar', 'vbs', 'ps1', 'cgi', 'asp', 'aspx', 'jsp', 'shtml', 'dll', 'so'
            ];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }

                $totalUncompressed += (int) ($stat['size'] ?? 0);
                if ($totalUncompressed > $maxUncompressed) {
                    $zip->close();
                    return false;
                }

                $filename = $stat['name'] ?? '';
                // 3. Zip Slip & Path Traversal guard
                if (
                    str_contains($filename, '..') ||
                    str_starts_with($filename, '/') ||
                    str_starts_with($filename, '\\') ||
                    str_contains($filename, "\0") ||
                    preg_match('#^[a-zA-Z]:#', $filename) === 1
                ) {
                    $zip->close();
                    return false;
                }

                // 4. Reject dangerous executable extensions
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $dangerousExtensions, true)) {
                    $zip->close();
                    return false;
                }
            }

            // 5. Must contain mimetype or META-INF/container.xml
            $mimetype = $zip->getFromName('mimetype');
            $hasValidMime = is_string($mimetype) && str_starts_with(trim($mimetype), 'application/epub+zip');
            $hasContainer = $zip->locateName('META-INF/container.xml') !== false;

            if (!$hasValidMime && !$hasContainer) {
                $zip->close();
                return false;
            }

            $zip->close();
            return true;
        } catch (Throwable) {
            $zip->close();
            return false;
        }
    }

    /**
     * Extract metadata (title, author, description, language, cover) from an EPUB file.
     *
     * @return array{title: ?string, author: ?string, description: ?string, language: ?string, cover_data: ?string, cover_mime: ?string}
     */
    public static function extractMetadata(string $filePath): array
    {
        $result = [
            'title' => null,
            'author' => null,
            'description' => null,
            'language' => 'en',
            'cover_data' => null,
            'cover_mime' => null,
        ];

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return $result;
        }

        try {
            $containerXml = $zip->getFromName('META-INF/container.xml');
            if ($containerXml === false) {
                $zip->close();
                return $result;
            }

            $opfPath = self::getOpfPathFromContainer($containerXml);
            if ($opfPath === null) {
                $zip->close();
                return $result;
            }

            $opfXml = $zip->getFromName($opfPath);
            if ($opfXml === false) {
                $zip->close();
                return $result;
            }

            $opfDir = dirname($opfPath);
            if ($opfDir === '.' || $opfDir === '/') {
                $opfDir = '';
            } else {
                $opfDir = rtrim($opfDir, '/') . '/';
            }

            $xml = @simplexml_load_string($opfXml);
            if ($xml === false) {
                $zip->close();
                return $result;
            }

            // Namespaces
            $namespaces = $xml->getNamespaces(true);
            $dc = $xml->metadata->children($namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/');

            if (isset($dc->title)) {
                $result['title'] = trim((string) $dc->title);
            }
            if (isset($dc->creator)) {
                $result['author'] = trim((string) $dc->creator);
            }
            if (isset($dc->description)) {
                $result['description'] = trim(strip_tags((string) $dc->description));
            }
            if (isset($dc->language)) {
                $lang = trim((string) $dc->language);
                if (strlen($lang) >= 2) {
                    $result['language'] = strtolower(substr($lang, 0, 5));
                }
            }

            // Find cover image if present
            $coverHref = self::findCoverHref($xml, $namespaces);
            if ($coverHref !== null) {
                $coverZipPath = $opfDir . ltrim($coverHref, '/');
                $coverBytes = $zip->getFromName($coverZipPath);
                if ($coverBytes !== false && strlen($coverBytes) > 100) {
                    $result['cover_data'] = $coverBytes;
                    $ext = strtolower(pathinfo($coverZipPath, PATHINFO_EXTENSION));
                    $result['cover_mime'] = match ($ext) {
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif',
                        default => 'image/jpeg',
                    };
                }
            }

            $zip->close();
            return $result;
        } catch (Throwable) {
            $zip->close();
            return $result;
        }
    }

    /**
     * Get manifest of chapters / spine and Table of Contents.
     *
     * @return array{title: string, author: string, toc: list<array{title: string, chapter_index: int}>, total_chapters: int}
     */
    public static function getManifest(string $filePath): array
    {
        $default = [
            'title' => 'eBook',
            'author' => 'Unknown Author',
            'toc' => [],
            'total_chapters' => 0,
        ];

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return $default;
        }

        try {
            $containerXml = $zip->getFromName('META-INF/container.xml');
            if ($containerXml === false) {
                $zip->close();
                return $default;
            }

            $opfPath = self::getOpfPathFromContainer($containerXml);
            if ($opfPath === null) {
                $zip->close();
                return $default;
            }

            $opfXml = $zip->getFromName($opfPath);
            if ($opfXml === false) {
                $zip->close();
                return $default;
            }

            $opfDir = dirname($opfPath);
            $opfDir = ($opfDir === '.' || $opfDir === '/') ? '' : rtrim($opfDir, '/') . '/';

            $xml = @simplexml_load_string($opfXml);
            if ($xml === false) {
                $zip->close();
                return $default;
            }

            $namespaces = $xml->getNamespaces(true);
            $dc = $xml->metadata->children($namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/');

            $title = isset($dc->title) ? trim((string) $dc->title) : 'eBook';
            $author = isset($dc->creator) ? trim((string) $dc->creator) : 'Unknown Author';

            // Manifest map: id => href
            $manifestMap = [];
            if (isset($xml->manifest->item)) {
                foreach ($xml->manifest->item as $item) {
                    $id = (string) $item['id'];
                    $href = (string) $item['href'];
                    $manifestMap[$id] = $href;
                }
            }

            // Spine reading order
            $chapters = [];
            if (isset($xml->spine->itemref)) {
                foreach ($xml->spine->itemref as $itemref) {
                    $idref = (string) $itemref['idref'];
                    if (isset($manifestMap[$idref])) {
                        $chapters[] = $opfDir . ltrim($manifestMap[$idref], '/');
                    }
                }
            }

            // Build TOC
            $toc = [];
            // Try to read NCX or nav.xhtml if possible
            $ncxItem = null;
            if (isset($xml->manifest->item)) {
                foreach ($xml->manifest->item as $item) {
                    $mediaType = (string) $item['media-type'];
                    if ($mediaType === 'application/x-dtbncx+xml') {
                        $ncxItem = (string) $item['href'];
                        break;
                    }
                }
            }

            if ($ncxItem !== null) {
                $ncxXml = $zip->getFromName($opfDir . ltrim($ncxItem, '/'));
                if ($ncxXml !== false) {
                    $ncx = @simplexml_load_string($ncxXml);
                    if ($ncx !== false && isset($ncx->navMap->navPoint)) {
                        foreach ($ncx->navMap->navPoint as $point) {
                            $pointTitle = trim((string) ($point->navLabel->text ?? ''));
                            $src = (string) ($point->content['src'] ?? '');
                            $cleanSrc = $opfDir . ltrim(explode('#', $src)[0], '/');
                            $chapterIdx = array_search($cleanSrc, $chapters, true);
                            if ($chapterIdx !== false && $pointTitle !== '') {
                                $toc[] = [
                                    'title' => $pointTitle,
                                    'chapter_index' => (int) $chapterIdx,
                                ];
                            }
                        }
                    }
                }
            }

            // If TOC is empty, create generic Chapter entries
            if ($toc === []) {
                foreach ($chapters as $idx => $chap) {
                    $toc[] = [
                        'title' => 'Section ' . ($idx + 1),
                        'chapter_index' => $idx,
                    ];
                }
            }

            $zip->close();
            return [
                'title' => $title,
                'author' => $author,
                'toc' => $toc,
                'spine' => $chapters,
                'total_chapters' => count($chapters),
            ];
        } catch (Throwable) {
            $zip->close();
            return $default;
        }
    }

    /**
     * Get clean, sanitized HTML content for a specific chapter index.
     *
     * @return array{content: string, title: string, chapter_index: int, total_chapters: int}
     */
    public static function getChapterHtml(string $filePath, int $chapterIndex, int $ebookId): array
    {
        $default = [
            'content' => '<p class="reader-empty">Chapter content could not be loaded.</p>',
            'title' => 'Chapter ' . ($chapterIndex + 1),
            'chapter_index' => $chapterIndex,
            'total_chapters' => 1,
        ];

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return $default;
        }

        try {
            $containerXml = $zip->getFromName('META-INF/container.xml');
            if ($containerXml === false) {
                $zip->close();
                return $default;
            }

            $opfPath = self::getOpfPathFromContainer($containerXml);
            if ($opfPath === null) {
                $zip->close();
                return $default;
            }

            $opfXml = $zip->getFromName($opfPath);
            if ($opfXml === false) {
                $zip->close();
                return $default;
            }

            $opfDir = dirname($opfPath);
            $opfDir = ($opfDir === '.' || $opfDir === '/') ? '' : rtrim($opfDir, '/') . '/';

            $xml = @simplexml_load_string($opfXml);
            if ($xml === false) {
                $zip->close();
                return $default;
            }

            // Manifest map
            $manifestMap = [];
            if (isset($xml->manifest->item)) {
                foreach ($xml->manifest->item as $item) {
                    $id = (string) $item['id'];
                    $href = (string) $item['href'];
                    $manifestMap[$id] = $href;
                }
            }

            // Spine
            $chapters = [];
            if (isset($xml->spine->itemref)) {
                foreach ($xml->spine->itemref as $itemref) {
                    $idref = (string) $itemref['idref'];
                    if (isset($manifestMap[$idref])) {
                        $chapters[] = $opfDir . ltrim($manifestMap[$idref], '/');
                    }
                }
            }

            $totalChapters = count($chapters);
            if ($chapterIndex < 0 || $chapterIndex >= $totalChapters) {
                $zip->close();
                return $default;
            }

            $chapterFile = $chapters[$chapterIndex];
            $rawHtml = $zip->getFromName($chapterFile);
            if ($rawHtml === false) {
                $zip->close();
                return $default;
            }

            $chapterDir = dirname($chapterFile);
            $chapterDir = ($chapterDir === '.' || $chapterDir === '/') ? '' : rtrim($chapterDir, '/') . '/';

            // Extract body content and sanitize
            $bodyContent = self::extractAndSanitizeBody($rawHtml, $chapterDir, $ebookId, $zip);

            $zip->close();
            return [
                'content' => $bodyContent,
                'html' => $bodyContent,
                'title' => 'Section ' . ($chapterIndex + 1),
                'chapter_index' => $chapterIndex,
                'total_chapters' => $totalChapters,
            ];
        } catch (Throwable) {
            $zip->close();
            return $default;
        }
    }

    /**
     * Get an asset (image, font, css) from the EPUB archive.
     *
     * @return array{data: string, mime: string}|null
     */
    public static function getAsset(string $filePath, string $assetPath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return null;
        }

        try {
            // Normalize path, prevent directory traversal
            $cleanPath = ltrim(str_replace('\\', '/', $assetPath), '/');
            if (str_contains($cleanPath, '..')) {
                $zip->close();
                return null;
            }

            $bytes = $zip->getFromName($cleanPath);
            if ($bytes === false) {
                $zip->close();
                return null;
            }

            $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'css' => 'text/css',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                default => 'application/octet-stream',
            };

            $zip->close();
            return ['data' => $bytes, 'mime' => $mime];
        } catch (Throwable) {
            $zip->close();
            return null;
        }
    }

    private static function getOpfPathFromContainer(string $containerXml): ?string
    {
        $xml = @simplexml_load_string($containerXml);
        if ($xml === false || !isset($xml->rootfiles->rootfile)) {
            return null;
        }

        foreach ($xml->rootfiles->rootfile as $rootfile) {
            $fullPath = (string) $rootfile['full-path'];
            if ($fullPath !== '') {
                return $fullPath;
            }
        }

        return null;
    }

    private static function findCoverHref(SimpleXMLElement $opfXml, array $namespaces): ?string
    {
        // Check EPUB 3 properties="cover-image"
        if (isset($opfXml->manifest->item)) {
            foreach ($opfXml->manifest->item as $item) {
                $props = (string) $item['properties'];
                if (str_contains($props, 'cover-image')) {
                    return (string) $item['href'];
                }
            }
        }

        // Check EPUB 2 <meta name="cover" content="item_id"/>
        if (isset($opfXml->metadata->meta)) {
            foreach ($opfXml->metadata->meta as $meta) {
                if ((string) $meta['name'] === 'cover') {
                    $coverId = (string) $meta['content'];
                    if (isset($opfXml->manifest->item)) {
                        foreach ($opfXml->manifest->item as $item) {
                            if ((string) $item['id'] === $coverId) {
                                return (string) $item['href'];
                            }
                        }
                    }
                }
            }
        }

        // Fallback: search manifest for id="cover" or href containing "cover"
        if (isset($opfXml->manifest->item)) {
            foreach ($opfXml->manifest->item as $item) {
                $id = strtolower((string) $item['id']);
                $href = strtolower((string) $item['href']);
                $mime = (string) $item['media-type'];
                if (str_starts_with($mime, 'image/') && (str_contains($id, 'cover') || str_contains($href, 'cover'))) {
                    return (string) $item['href'];
                }
            }
        }

        return null;
    }

    private static function extractAndSanitizeBody(string $rawHtml, string $chapterDir, int $ebookId, ZipArchive $zip): string
    {
        // Extract <body> content if present
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $rawHtml, $match)) {
            $content = $match[1];
        } else {
            $content = $rawHtml;
        }

        // Remove <script>, <style>, <link>, <object>, <embed>, <iframe>
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content) ?? $content;
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content) ?? $content;
        $content = preg_replace('/<link\b[^>]*>/is', '', $content) ?? $content;
        $content = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $content) ?? $content;
        $content = preg_replace('/<embed\b[^>]*>/is', '', $content) ?? $content;
        $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content) ?? $content;

        // Remove on* inline event handlers (onload, onerror, onclick, etc.)
        $content = preg_replace('/\s+on[a-zA-Z]+\s*=\s*(["\']).*?\1/is', '', $content) ?? $content;
        $content = preg_replace('/\s+on[a-zA-Z]+\s*=\s*[^ >]+/is', '', $content) ?? $content;

        // Rewrite <img> src references to /ebooks/{id}/epub-asset?path=...
        $content = preg_replace_callback('/<img\b([^>]*?)src=(["\'])(.*?)\2([^>]*?)>/is', static function (array $m) use ($chapterDir, $ebookId, $zip): string {
            $src = trim($m[3]);
            if (str_starts_with($src, 'data:') || str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
                return "<img {$m[1]}src=\"{$src}\"{$m[4]}>";
            }

            // Resolve relative path against chapterDir
            $resolvedPath = self::resolveRelativePath($chapterDir . $src);
            $assetUrl = '/ebooks/' . $ebookId . '/epub-asset?path=' . rawurlencode($resolvedPath);

            return "<img {$m[1]}src=\"{$assetUrl}\" loading=\"lazy\"{$m[4]}>";
        }, $content) ?? $content;

        // Rewrite <image> SVG references in EPUB
        $content = preg_replace_callback('/<image\b([^>]*?)xlink:href=(["\'])(.*?)\2([^>]*?)>/is', static function (array $m) use ($chapterDir, $ebookId): string {
            $src = trim($m[3]);
            if (str_starts_with($src, 'data:') || str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
                return "<image {$m[1]}xlink:href=\"{$src}\"{$m[4]}>";
            }
            $resolvedPath = self::resolveRelativePath($chapterDir . $src);
            $assetUrl = '/ebooks/' . $ebookId . '/epub-asset?path=' . rawurlencode($resolvedPath);
            return "<image {$m[1]}xlink:href=\"{$assetUrl}\"{$m[4]}>";
        }, $content) ?? $content;

        return trim($content);
    }

    private static function resolveRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $safe = [];

        foreach ($parts as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                array_pop($safe);
            } else {
                $safe[] = $p;
            }
        }

        return implode('/', $safe);
    }
}
