<?php
declare(strict_types=1);

namespace InfinityBlog\Export;

final class PdfExporter
{
    /**
     * Generate a standards-compliant PDF document for a published article.
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
        $title = html_entity_decode(strip_tags((string) ($article['title'] ?? 'Untitled Article')), ENT_QUOTES, 'UTF-8');
        $author = html_entity_decode(strip_tags((string) ($article['author_name'] ?? 'Infinity Blog Author')), ENT_QUOTES, 'UTF-8');
        $dateStr = !empty($article['published_at']) ? date('F j, Y', strtotime($article['published_at'])) : date('F j, Y');
        $category = !empty($article['category_name']) ? html_entity_decode(strip_tags((string) $article['category_name']), ENT_QUOTES, 'UTF-8') : 'General';
        $summary = !empty($article['summary']) ? html_entity_decode(strip_tags((string) $article['summary']), ENT_QUOTES, 'UTF-8') : null;
        $body = (string) ($article['content'] ?? '');

        // Prepare plain text paragraphs from markdown / html content
        $paragraphs = self::extractParagraphs($body);

        // Page layout: A4 = 595 x 842 points. Margins = 50 pt each side. Usable width = 495 pt.
        // Printable Y: from 760 down to 70. Usable height = 690 pt.
        $pages = [];
        $currentPageOps = [];
        $y = 750;

        // Helper to push a new page
        $startNewPage = function () use (&$pages, &$currentPageOps, &$y, $title): void {
            if (!empty($currentPageOps)) {
                $pages[] = $currentPageOps;
            }
            $currentPageOps = [];
            $y = 750;
        };

        // --- PAGE 1: HEADER & TITLE ---
        // Top banner bar
        $currentPageOps[] = "0.388 0.4 0.945 rg\n"; // Primary Indigo
        $currentPageOps[] = "50 780 495 2 re f\n"; // Accent bar
        $currentPageOps[] = "0 0 0 rg\n";

        // Brand tag
        $currentPageOps[] = "BT /F1 9 Tf 50 788 Td (" . self::pdfEscape("INFINITY BLOG PERSPECTIVES") . ") Tj ET\n";
        $currentPageOps[] = "BT /F1 9 Tf 400 788 Td (" . self::pdfEscape($dateStr) . ") Tj ET\n";

        // Title (wrapped)
        $titleLines = self::wrapText($title, 55);
        $y = 740;
        foreach ($titleLines as $tLine) {
            $currentPageOps[] = "BT /F2 18 Tf 50 {$y} Td (" . self::pdfEscape($tLine) . ") Tj ET\n";
            $y -= 24;
        }

        // Meta info (Author & Category)
        $y -= 4;
        $currentPageOps[] = "0.4 0.45 0.5 rg\n";
        $metaText = "By {$author}  •  Category: {$category}";
        $currentPageOps[] = "BT /F1 10 Tf 50 {$y} Td (" . self::pdfEscape($metaText) . ") Tj ET\n";
        $y -= 14;
        $currentPageOps[] = "BT /F1 8 Tf 50 {$y} Td (" . self::pdfEscape("Canonical URL: " . $articleUrl) . ") Tj ET\n";
        $y -= 18;

        // Separator line
        $currentPageOps[] = "0.85 0.88 0.92 RG\n";
        $currentPageOps[] = "50 {$y} m 545 {$y} l S\n";
        $currentPageOps[] = "0 0 0 rg\n";
        $y -= 22;

        // Executive Summary box (if available)
        if ($summary !== null && trim($summary) !== '') {
            $summaryLines = self::wrapText("EXECUTIVE SUMMARY: " . $summary, 72);
            $boxHeight = count($summaryLines) * 14 + 18;
            if ($y - $boxHeight < 90) {
                $startNewPage();
            }
            $boxY = $y - $boxHeight;

            // Box background & border
            $currentPageOps[] = "0.96 0.97 1.0 rg 0.79 0.82 0.98 RG\n";
            $currentPageOps[] = "50 {$boxY} 495 {$boxHeight} re B\n";
            $currentPageOps[] = "0.15 0.2 0.4 rg\n";

            $sY = $y - 14;
            foreach ($summaryLines as $sLine) {
                $currentPageOps[] = "BT /F3 9.5 Tf 60 {$sY} Td (" . self::pdfEscape($sLine) . ") Tj ET\n";
                $sY -= 14;
            }
            $currentPageOps[] = "0 0 0 rg\n";
            $y = $boxY - 24;
        }

        // Body Paragraphs
        foreach ($paragraphs as $para) {
            $type = $para['type'];
            $text = $para['text'];

            if ($type === 'heading') {
                $hLines = self::wrapText($text, 60);
                $needed = count($hLines) * 18 + 20;
                if ($y - $needed < 80) {
                    $startNewPage();
                } else {
                    $y -= 12;
                }

                $currentPageOps[] = "0.1 0.15 0.3 rg\n";
                foreach ($hLines as $hLine) {
                    $currentPageOps[] = "BT /F2 13 Tf 50 {$y} Td (" . self::pdfEscape($hLine) . ") Tj ET\n";
                    $y -= 18;
                }
                $currentPageOps[] = "0 0 0 rg\n";
                $y -= 6;
            } else {
                $pLines = self::wrapText($text, 78);
                foreach ($pLines as $pLine) {
                    if ($y < 80) {
                        $startNewPage();
                    }
                    $currentPageOps[] = "BT /F1 10 Tf 50 {$y} Td (" . self::pdfEscape($pLine) . ") Tj ET\n";
                    $y -= 15;
                }
                $y -= 8; // spacing between paragraphs
            }
        }

        // Add last page ops
        if (!empty($currentPageOps)) {
            $pages[] = $currentPageOps;
        }

        // Now render the PDF object graph
        $totalPages = count($pages);
        $objects = [];
        $objIndex = 1;

        // Obj 1: Catalog
        $catalogObj = $objIndex++;
        // Obj 2: Pages container
        $pagesObj = $objIndex++;

        // Page objects and content stream objects
        $pageObjectIds = [];
        $streamObjectIds = [];
        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjectIds[] = $objIndex++;
            $streamObjectIds[] = $objIndex++;
        }

        // Font objects: F1 (Helvetica), F2 (Helvetica-Bold), F3 (Helvetica-Oblique)
        $fontF1 = $objIndex++;
        $fontF2 = $objIndex++;
        $fontF3 = $objIndex++;

        // Build Streams with Footer ("Page X of Y")
        $streamContents = [];
        for ($i = 0; $i < $totalPages; $i++) {
            $pageNum = $i + 1;
            $footerY = 45;
            $footerOps = "0.5 0.5 0.5 rg\n";
            $footerOps .= "50 58 495 0.5 re f\n"; // Thin line
            $footerOps .= "BT /F1 8 Tf 50 {$footerY} Td (" . self::pdfEscape("Infinity Blog  •  " . mb_substr($title, 0, 40)) . ") Tj ET\n";
            $footerOps .= "BT /F1 8 Tf 480 {$footerY} Td (" . self::pdfEscape("Page {$pageNum} of {$totalPages}") . ") Tj ET\n";

            $fullContent = implode('', $pages[$i]) . $footerOps;
            $streamContents[$i] = $fullContent;
        }

        // Construct PDF output string and calculate offsets
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        // Obj 1: Catalog
        $offsets[$catalogObj] = strlen($pdf);
        $pdf .= "{$catalogObj} 0 obj\n<< /Type /Catalog /Pages {$pagesObj} 0 R >>\nendobj\n";

        // Obj 2: Pages
        $kidsStr = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjectIds));
        $offsets[$pagesObj] = strlen($pdf);
        $pdf .= "{$pagesObj} 0 obj\n<< /Type /Pages /Kids [{$kidsStr}] /Count {$totalPages} >>\nendobj\n";

        // Pages and Content Streams
        for ($i = 0; $i < $totalPages; $i++) {
            $pId = $pageObjectIds[$i];
            $sId = $streamObjectIds[$i];

            // Page Obj
            $offsets[$pId] = strlen($pdf);
            $pdf .= "{$pId} 0 obj\n<< /Type /Page /Parent {$pagesObj} 0 R /MediaBox [0 0 595 842] " .
                "/Resources << /Font << /F1 {$fontF1} 0 R /F2 {$fontF2} 0 R /F3 {$fontF3} 0 R >> >> " .
                "/Contents {$sId} 0 R >>\nendobj\n";

            // Stream Obj
            $sContent = $streamContents[$i];
            $sLen = strlen($sContent);
            $offsets[$sId] = strlen($pdf);
            $pdf .= "{$sId} 0 obj\n<< /Length {$sLen} >>\nstream\n{$sContent}\nendstream\nendobj\n";
        }

        // Fonts
        $offsets[$fontF1] = strlen($pdf);
        $pdf .= "{$fontF1} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $offsets[$fontF2] = strlen($pdf);
        $pdf .= "{$fontF2} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $offsets[$fontF3] = strlen($pdf);
        $pdf .= "{$fontF3} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Cross-reference table
        $xrefOffset = strlen($pdf);
        $totalObjs = $objIndex;
        $pdf .= "xref\n0 {$totalObjs}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        // Trailer
        $pdf .= "trailer\n<< /Size {$totalObjs} /Root {$catalogObj} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /**
     * Parse markdown / HTML into structured paragraphs and headings.
     *
     * @return list<array{type: 'heading'|'paragraph', text: string}>
     */
    private static function extractParagraphs(string $content): array
    {
        // Strip code blocks and script tags safely
        $cleaned = preg_replace('/```[\s\S]*?```/', '', $content);
        $cleaned = preg_replace('/<script[\s\S]*?<\/script>/i', '', (string) $cleaned);
        $cleaned = preg_replace('/<style[\s\S]*?<\/style>/i', '', (string) $cleaned);

        $lines = preg_split('/\r\n|\r|\n/', (string) $cleaned);
        $paragraphs = [];
        $currentBuffer = [];

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                if (!empty($currentBuffer)) {
                    $text = implode(' ', $currentBuffer);
                    $paragraphs[] = ['type' => 'paragraph', 'text' => self::cleanInline($text)];
                    $currentBuffer = [];
                }
                continue;
            }

            if (preg_match('/^#{1,4}\s+(.+)$/', $trimmed, $m)) {
                if (!empty($currentBuffer)) {
                    $paragraphs[] = ['type' => 'paragraph', 'text' => self::cleanInline(implode(' ', $currentBuffer))];
                    $currentBuffer = [];
                }
                $paragraphs[] = ['type' => 'heading', 'text' => self::cleanInline($m[1])];
                continue;
            }

            // Bullet points
            if (preg_match('/^[-*•]\s+(.+)$/', $trimmed, $m)) {
                if (!empty($currentBuffer)) {
                    $paragraphs[] = ['type' => 'paragraph', 'text' => self::cleanInline(implode(' ', $currentBuffer))];
                    $currentBuffer = [];
                }
                $paragraphs[] = ['type' => 'paragraph', 'text' => '• ' . self::cleanInline($m[1])];
                continue;
            }

            $currentBuffer[] = $trimmed;
        }

        if (!empty($currentBuffer)) {
            $paragraphs[] = ['type' => 'paragraph', 'text' => self::cleanInline(implode(' ', $currentBuffer))];
        }

        return $paragraphs;
    }

    private static function cleanInline(string $text): string
    {
        // Remove markdown formatting: [text](url) -> text, **bold** -> bold, etc.
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/[*_~`]/', '', (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Wrap text by approximate character width.
     *
     * @return list<string>
     */
    private static function wrapText(string $text, int $maxChars): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $w) {
            if ($current === '') {
                $current = $w;
            } elseif (mb_strlen($current . ' ' . $w) <= $maxChars) {
                $current .= ' ' . $w;
            } else {
                $lines[] = $current;
                $current = $w;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private static function pdfEscape(string $str): string
    {
        // Convert to ISO-8859-1 / WinAnsi for standard Type 1 fonts
        $encoded = @iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
        if ($encoded === false) {
            $encoded = utf8_decode($str);
        }

        // Escape parenthesis and backslashes for PDF syntax
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
        return $escaped;
    }
}
