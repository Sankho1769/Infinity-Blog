<?php
declare(strict_types=1);

namespace InfinityBlog\Export;

use RuntimeException;
use ZipArchive;

final class EpubExporter
{
    /**
     * Generate an EPUB 3 ebook file for a published article.
     *
     * @param array{
     *     title: string,
     *     author_name: string,
     *     published_at: ?string,
     *     category_name: ?string,
     *     summary: ?string,
     *     content: string
     * } $article
     * @param string $articleUrl
     */
    public static function generate(array $article, string $articleUrl): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP extension is required to export EPUB ebooks.');
        }

        $title = htmlspecialchars((string) ($article['title'] ?? 'Untitled Article'), ENT_XML1, 'UTF-8');
        $author = htmlspecialchars((string) ($article['author_name'] ?? 'Infinity Blog Author'), ENT_XML1, 'UTF-8');
        $dateStr = !empty($article['published_at']) ? date('Y-m-d', strtotime($article['published_at'])) : date('Y-m-d');
        $displayDate = !empty($article['published_at']) ? date('F j, Y', strtotime($article['published_at'])) : date('F j, Y');
        $category = !empty($article['category_name']) ? htmlspecialchars((string) $article['category_name'], ENT_XML1, 'UTF-8') : 'General';
        $summary = !empty($article['summary']) ? htmlspecialchars((string) $article['summary'], ENT_XML1, 'UTF-8') : null;
        $urlXml = htmlspecialchars($articleUrl, ENT_XML1, 'UTF-8');

        $articleBodyHtml = self::formatBodyToXhtml((string) ($article['content'] ?? ''));

        // Unique identifier for the book
        $bookId = 'urn:uuid:' . md5($articleUrl . $dateStr);

        // Temp file for ZIP creation
        $tempFile = tempnam(sys_get_temp_dir(), 'epub_');
        if ($tempFile === false) {
            throw new RuntimeException('Unable to create temporary file for EPUB.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to initialize EPUB archive.');
        }

        // 1. mimetype (MUST be first, uncompressed)
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        // 2. META-INF/container.xml
        $containerXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">' . "\n" .
            '  <rootfiles>' . "\n" .
            '    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>' . "\n" .
            '  </rootfiles>' . "\n" .
            '</container>';
        $zip->addFromString('META-INF/container.xml', $containerXml);

        // 3. OEBPS/style.css
        $css = "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; margin: 5%; color: #1e293b; background: #ffffff; }\n" .
            "h1 { font-size: 2em; line-height: 1.2; color: #0f172a; margin-bottom: 0.2em; }\n" .
            ".meta { font-size: 0.9em; color: #64748b; margin-bottom: 1.5em; border-bottom: 1px solid #e2e8f0; padding-bottom: 1em; }\n" .
            ".summary-box { background: #f8fafc; border-left: 4px solid #6366f1; padding: 1em; margin: 1.5em 0; border-radius: 4px; font-style: italic; }\n" .
            "h2 { font-size: 1.4em; color: #1e293b; margin-top: 1.5em; margin-bottom: 0.5em; }\n" .
            "h3 { font-size: 1.2em; color: #334155; margin-top: 1.2em; }\n" .
            "p { margin: 1em 0; text-align: justify; text-justify: inter-word; }\n" .
            "ul, ol { margin: 1em 0; padding-left: 2em; }\n" .
            "li { margin-bottom: 0.4em; }\n" .
            "blockquote { margin: 1.5em 0; padding: 0.5em 1em; border-left: 3px solid #cbd5e1; color: #475569; font-style: italic; }\n" .
            "a { color: #4f46e5; text-decoration: none; }\n" .
            "code { background: #f1f5f9; padding: 2px 5px; border-radius: 4px; font-family: monospace; font-size: 0.9em; }\n" .
            ".article-figure { margin: 1.5em 0; text-align: center; }\n" .
            "img { max-width: 100%; height: auto; border-radius: 6px; }\n" .
            "figcaption { font-size: 0.85em; color: #64748b; margin-top: 0.5em; font-style: italic; }\n" .
            ".footer { margin-top: 3em; padding-top: 1em; border-top: 1px solid #e2e8f0; font-size: 0.8em; color: #94a3b8; text-align: center; }";
        $zip->addFromString('OEBPS/style.css', $css);

        // 4. OEBPS/article.xhtml
        $articleXhtml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
            '<!DOCTYPE html>' . "\n" .
            '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en" lang="en">' . "\n" .
            '<head>' . "\n" .
            '  <title>' . $title . '</title>' . "\n" .
            '  <link rel="stylesheet" type="text/css" href="style.css"/>' . "\n" .
            '</head>' . "\n" .
            '<body>' . "\n" .
            '  <article epub:type="chapter">' . "\n" .
            '    <h1>' . $title . '</h1>' . "\n" .
            '    <div class="meta">' . "\n" .
            '      <p>By <strong>' . $author . '</strong>  |  Category: <strong>' . $category . '</strong>  |  Published: ' . $displayDate . '</p>' . "\n" .
            '      <p><a href="' . $urlXml . '">Read online on Infinity Blog</a></p>' . "\n" .
            '    </div>' . "\n";

        if ($summary !== null && trim($summary) !== '') {
            $articleXhtml .= '    <aside class="summary-box">' . "\n" .
                '      <strong>Executive Summary:</strong> ' . $summary . "\n" .
                '    </aside>' . "\n";
        }

        $articleXhtml .= '    <div class="article-content">' . "\n" .
            $articleBodyHtml . "\n" .
            '    </div>' . "\n" .
            '    <footer class="footer">' . "\n" .
            '      <p>Published on Infinity Blog  •  ' . $urlXml . '</p>' . "\n" .
            '    </footer>' . "\n" .
            '  </article>' . "\n" .
            '</body>' . "\n" .
            '</html>';
        $zip->addFromString('OEBPS/article.xhtml', $articleXhtml);

        // 5. OEBPS/nav.xhtml (EPUB 3 Navigation)
        $navXhtml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
            '<!DOCTYPE html>' . "\n" .
            '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en" lang="en">' . "\n" .
            '<head><title>Table of Contents</title><link rel="stylesheet" type="text/css" href="style.css"/></head>' . "\n" .
            '<body>' . "\n" .
            '  <nav epub:type="toc" id="toc">' . "\n" .
            '    <h1>Table of Contents</h1>' . "\n" .
            '    <ol>' . "\n" .
            '      <li><a href="article.xhtml">' . $title . '</a></li>' . "\n" .
            '    </ol>' . "\n" .
            '  </nav>' . "\n" .
            '</body>' . "\n" .
            '</html>';
        $zip->addFromString('OEBPS/nav.xhtml', $navXhtml);

        // 6. OEBPS/toc.ncx (EPUB 2 NCX Fallback)
        $tocNcx = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">' . "\n" .
            '  <head><meta name="dtb:uid" content="' . $bookId . '"/></head>' . "\n" .
            '  <docTitle><text>' . $title . '</text></docTitle>' . "\n" .
            '  <navMap>' . "\n" .
            '    <navPoint id="navpoint-1" playOrder="1">' . "\n" .
            '      <navLabel><text>' . $title . '</text></navLabel>' . "\n" .
            '      <content src="article.xhtml"/>' . "\n" .
            '    </navPoint>' . "\n" .
            '  </navMap>' . "\n" .
            '</ncx>';
        $zip->addFromString('OEBPS/toc.ncx', $tocNcx);

        // 7. OEBPS/content.opf
        $contentOpf = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="BookId">' . "\n" .
            '  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n" .
            '    <dc:identifier id="BookId">' . $bookId . '</dc:identifier>' . "\n" .
            '    <dc:title>' . $title . '</dc:title>' . "\n" .
            '    <dc:creator>' . $author . '</dc:creator>' . "\n" .
            '    <dc:language>en</dc:language>' . "\n" .
            '    <dc:date>' . $dateStr . '</dc:date>' . "\n" .
            '    <dc:publisher>Infinity Blog</dc:publisher>' . "\n" .
            '    <meta property="dcterms:modified">' . gmdate('Y-m-d\TH:i:s\Z') . '</meta>' . "\n" .
            '  </metadata>' . "\n" .
            '  <manifest>' . "\n" .
            '    <item id="style" href="style.css" media-type="text/css"/>' . "\n" .
            '    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>' . "\n" .
            '    <item id="article" href="article.xhtml" media-type="application/xhtml+xml"/>' . "\n" .
            '    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>' . "\n" .
            '  </manifest>' . "\n" .
            '  <spine toc="ncx">' . "\n" .
            '    <itemref idref="article"/>' . "\n" .
            '  </spine>' . "\n" .
            '</package>';
        $zip->addFromString('OEBPS/content.opf', $contentOpf);

        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        if ($content === false) {
            throw new RuntimeException('Failed to read generated EPUB binary.');
        }

        return $content;
    }

    private static function formatBodyToXhtml(string $content): string
    {
        // Strip unsafe scripts and styles
        $cleaned = preg_replace('/```[\s\S]*?```/', '', $content);
        $cleaned = preg_replace('/<script[\s\S]*?<\/script>/i', '', (string) $cleaned);
        $cleaned = preg_replace('/<style[\s\S]*?<\/style>/i', '', (string) $cleaned);

        $lines = preg_split('/\r\n|\r|\n/', (string) $cleaned);
        $xhtml = '';
        $inList = false;
        $inOrderedList = false;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                if ($inList) {
                    $xhtml .= "</ul>\n";
                    $inList = false;
                }
                if ($inOrderedList) {
                    $xhtml .= "</ol>\n";
                    $inOrderedList = false;
                }
                continue;
            }

            // Headings
            if (preg_match('/^#{1,2}\s+(.+)$/', $trimmed, $m)) {
                if ($inList) { $xhtml .= "</ul>\n"; $inList = false; }
                if ($inOrderedList) { $xhtml .= "</ol>\n"; $inOrderedList = false; }
                $xhtml .= '<h2>' . htmlspecialchars($m[1], ENT_XML1, 'UTF-8') . "</h2>\n";
                continue;
            }
            if (preg_match('/^#{3,4}\s+(.+)$/', $trimmed, $m)) {
                if ($inList) { $xhtml .= "</ul>\n"; $inList = false; }
                if ($inOrderedList) { $xhtml .= "</ol>\n"; $inOrderedList = false; }
                $xhtml .= '<h3>' . htmlspecialchars($m[1], ENT_XML1, 'UTF-8') . "</h3>\n";
                continue;
            }

            // Images on their own line: ![Alt](url)
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $trimmed, $m)) {
                if ($inList) { $xhtml .= "</ul>\n"; $inList = false; }
                if ($inOrderedList) { $xhtml .= "</ol>\n"; $inOrderedList = false; }
                $alt = htmlspecialchars($m[1], ENT_XML1, 'UTF-8');
                $src = htmlspecialchars($m[2], ENT_XML1, 'UTF-8');
                $xhtml .= '<figure class="article-figure"><img src="' . $src . '" alt="' . $alt . '" />' .
                    ($alt !== '' ? '<figcaption>' . $alt . '</figcaption>' : '') .
                    "</figure>\n";
                continue;
            }

            // Blockquotes: > quote
            if (preg_match('/^>\s+(.+)$/', $trimmed, $m)) {
                if ($inList) { $xhtml .= "</ul>\n"; $inList = false; }
                if ($inOrderedList) { $xhtml .= "</ol>\n"; $inOrderedList = false; }
                $xhtml .= '<blockquote><p>' . self::formatInlineXhtml($m[1]) . "</p></blockquote>\n";
                continue;
            }

            // Ordered list: 1. Item
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
                if ($inList) { $xhtml .= "</ul>\n"; $inList = false; }
                if (!$inOrderedList) {
                    $xhtml .= "<ol>\n";
                    $inOrderedList = true;
                }
                $xhtml .= '<li>' . self::formatInlineXhtml($m[1]) . "</li>\n";
                continue;
            }

            // Bullet points: - Item or * Item
            if (preg_match('/^[-*•]\s+(.+)$/', $trimmed, $m)) {
                if ($inOrderedList) { $xhtml .= "</ol>\n"; $inOrderedList = false; }
                if (!$inList) {
                    $xhtml .= "<ul>\n";
                    $inList = true;
                }
                $xhtml .= '<li>' . self::formatInlineXhtml($m[1]) . "</li>\n";
                continue;
            }

            // Normal paragraph
            if ($inList) {
                $xhtml .= "</ul>\n";
                $inList = false;
            }
            if ($inOrderedList) {
                $xhtml .= "</ol>\n";
                $inOrderedList = false;
            }

            $xhtml .= '<p>' . self::formatInlineXhtml($trimmed) . "</p>\n";
        }

        if ($inList) {
            $xhtml .= "</ul>\n";
        }
        if ($inOrderedList) {
            $xhtml .= "</ol>\n";
        }

        return $xhtml;
    }

    private static function formatInlineXhtml(string $text): string
    {
        // First encode XML special chars
        $text = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        // Images: ![Alt](url)
        $text = preg_replace('/!\[([^\]]*)\]\((https?:\/\/[^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;height:auto;"/>', $text);

        // Links: [Label](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function ($m) {
            $label = $m[1];
            $url = $m[2];
            if (preg_match('#^(https?://|/|mailto:)#i', $url)) {
                return '<a href="' . $url . '">' . $label . '</a>';
            }
            return $label;
        }, (string) $text);

        // Bold: **text**
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', (string) $text);

        // Italic: *text* or _text_
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', (string) $text);
        $text = preg_replace('/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', (string) $text);

        // Inline code: `code`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', (string) $text);

        return (string) $text;
    }
}
