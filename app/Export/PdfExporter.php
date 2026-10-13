<?php
declare(strict_types=1);

namespace InfinityBlog\Export;

use tFPDF;
use Throwable;

require_once __DIR__ . '/tfpdf.php';

final class PdfExporter
{
    /**
     * Generate a standards-compliant PDF document for a published article with full Unicode support.
     *
     * @param array{
     *     title: string,
     *     author_name: string,
     *     published_at?: ?string,
     *     category_name?: ?string,
     *     summary?: ?string,
     *     content: string,
     *     cover_image_path?: ?string
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
        $coverPath = !empty($article['cover_image_path']) ? (string) $article['cover_image_path'] : null;

        // Create PDF instance (A4: 210 x 297 mm, margins: 15mm left/right)
        $pdf = new class($title) extends tFPDF {
            private string $docTitle;

            public function __construct(string $docTitle)
            {
                parent::__construct('P', 'mm', 'A4');
                $this->docTitle = $docTitle;
            }

            public function Header(): void
            {
                if ($this->PageNo() > 1) {
                    $this->SetY(10);
                    $this->SetFont('NotoLatin', '', 8);
                    $this->SetTextColor(130, 140, 155);
                    $this->Cell(0, 5, 'Infinity Blog Perspectives  •  ' . mb_substr($this->docTitle, 0, 50), 0, 0, 'L');
                    $this->Ln(7);
                    $this->SetDrawColor(226, 232, 240);
                    $this->Line(15, 17, 195, 17);
                    $this->SetY(22);
                }
            }

            public function Footer(): void
            {
                $this->SetY(-15);
                $this->SetDrawColor(226, 232, 240);
                $this->Line(15, 282, 195, 282);
                $this->SetFont('NotoLatin', '', 8);
                $this->SetTextColor(140, 150, 165);
                $this->Cell(90, 8, 'Infinity Blog  •  Read & Share', 0, 0, 'L');
                $this->Cell(90, 8, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
            }
        };

        $pdf->AliasNbPages();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);

        // Register TrueType Unicode Fonts
        $pdf->AddFont('NotoLatin', '', 'latin.ttf', true);
        $pdf->AddFont('NotoLatin', 'B', 'latin-bold.ttf', true);
        $pdf->AddFont('NotoBengali', '', 'bengali.ttf', true);
        $pdf->AddFont('NotoDevanagari', '', 'devanagari.ttf', true);

        $pdf->AddPage();

        // Top decorative accent line (Indigo: #6366f1 -> RGB: 99, 102, 241)
        $pdf->SetDrawColor(99, 102, 241);
        $pdf->SetLineWidth(1.2);
        $pdf->Line(15, 15, 195, 15);
        $pdf->SetLineWidth(0.2);
        $pdf->SetY(18);

        // Publication Brand & Date Header
        $pdf->SetFont('NotoLatin', 'B', 8);
        $pdf->SetTextColor(99, 102, 241);
        $pdf->Cell(90, 6, 'INFINITY BLOG PERSPECTIVES', 0, 0, 'L');
        $pdf->SetFont('NotoLatin', '', 8);
        $pdf->SetTextColor(120, 130, 145);
        $pdf->Cell(90, 6, $dateStr, 0, 1, 'R');
        $pdf->Ln(2);

        // Article Title (Multilingual Unicode)
        $pdf->SetTextColor(15, 23, 42); // Slate-900
        self::writeMultilingual($pdf, $title, 18, true, 8);
        $pdf->Ln(3);

        // Metadata: Author & Category
        $pdf->SetFont('NotoLatin', '', 9.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Write(5, 'By ');
        self::writeMultilingual($pdf, $author, 9.5, true, 5);
        $pdf->SetFont('NotoLatin', '', 9.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Write(5, '  •  Category: ' . $category);
        $pdf->Ln(5);

        // Canonical Link
        $pdf->SetFont('NotoLatin', '', 8);
        $pdf->SetTextColor(99, 102, 241);
        $pdf->Write(4, $articleUrl, $articleUrl);
        $pdf->Ln(6);

        // Separator Rule
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        // Safe Cover Image embedding (if provided and accessible)
        if ($coverPath !== null) {
            self::embedImageSafely($pdf, $coverPath);
        }

        // Executive Summary Box (if present)
        if ($summary !== null && trim($summary) !== '') {
            $startY = $pdf->GetY();
            // Indigo pastel background fill
            $pdf->SetFillColor(245, 247, 255);
            $pdf->SetDrawColor(199, 210, 254);
            $pdf->Rect(15, $startY, 180, 2, 'DF'); // Initial measurement anchor

            $pdf->SetY($startY + 3);
            $pdf->SetX(18);
            $pdf->SetFont('NotoLatin', 'B', 8.5);
            $pdf->SetTextColor(67, 56, 202); // Indigo-700
            $pdf->Cell(0, 5, 'EXECUTIVE SUMMARY', 0, 1);
            $pdf->SetX(18);
            $pdf->SetTextColor(30, 41, 59);
            self::writeMultilingual($pdf, $summary, 9.5, false, 5.5);
            $pdf->Ln(4);
            $endY = $pdf->GetY();

            // Draw full background box around the summary
            $boxHeight = max(12, $endY - $startY + 2);
            $pdf->SetFillColor(245, 247, 255);
            $pdf->SetDrawColor(199, 210, 254);
            $pdf->Rect(15, $startY, 180, $boxHeight, 'D');
            // Left indigo accent border bar
            $pdf->SetFillColor(99, 102, 241);
            $pdf->Rect(15, $startY, 2, $boxHeight, 'F');

            $pdf->SetY($startY + $boxHeight + 6);
        }

        // Extract and render body paragraphs, headings, blockquotes, lists, and images
        $blocks = self::parseContentBlocks($body);
        foreach ($blocks as $block) {
            switch ($block['type']) {
                case 'heading1':
                    $pdf->Ln(4);
                    $pdf->SetTextColor(15, 23, 42);
                    self::writeMultilingual($pdf, $block['text'], 14, true, 7);
                    $pdf->Ln(3);
                    break;

                case 'heading2':
                    $pdf->Ln(3);
                    $pdf->SetTextColor(30, 41, 59);
                    self::writeMultilingual($pdf, $block['text'], 12, true, 6);
                    $pdf->Ln(2);
                    break;

                case 'heading3':
                    $pdf->Ln(2);
                    $pdf->SetTextColor(51, 65, 85);
                    self::writeMultilingual($pdf, $block['text'], 10.5, true, 5.5);
                    $pdf->Ln(2);
                    break;

                case 'blockquote':
                    $bStartY = $pdf->GetY();
                    $pdf->SetX(20);
                    $pdf->SetTextColor(71, 85, 105);
                    self::writeMultilingual($pdf, $block['text'], 10, false, 5.5);
                    $pdf->Ln(3);
                    $bEndY = $pdf->GetY();
                    // Draw accent left bar
                    $pdf->SetFillColor(147, 197, 253);
                    $pdf->Rect(16, $bStartY, 1.5, max(6, $bEndY - $bStartY), 'F');
                    break;

                case 'list_item':
                    $pdf->SetX(18);
                    $pdf->SetFont('NotoLatin', '', 9.5);
                    $pdf->SetTextColor(99, 102, 241);
                    $bullet = !empty($block['bullet']) ? $block['bullet'] : '• ';
                    $pdf->Write(5.5, $bullet);
                    $pdf->SetTextColor(30, 41, 59);
                    self::writeMultilingual($pdf, $block['text'], 9.5, false, 5.5);
                    $pdf->Ln(4);
                    break;

                case 'image':
                    self::embedImageSafely($pdf, $block['src'], $block['alt'] ?? '');
                    break;

                case 'paragraph':
                default:
                    $pdf->SetTextColor(30, 41, 59);
                    self::writeMultilingual($pdf, $block['text'], 10, false, 5.5);
                    $pdf->Ln(4);
                    break;
            }
        }

        return (string) $pdf->Output('', 'S');
    }

    /**
     * Render mixed multilingual text (Latin, Bengali, Devanagari) across script boundaries seamlessly.
     */
    public static function writeMultilingual(
        tFPDF $pdf,
        string $text,
        float $fontSize = 10,
        bool $bold = false,
        float $lineHeight = 5.5
    ): void {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Match runs of Bengali (U+0980-U+09FF), Devanagari (U+0900-U+097F), or other (Latin, digits, punctuation)
        preg_match_all('/([\x{0980}-\x{09FF}]+|[\x{0900}-\x{097F}]+|[^\x{0900}-\x{09FF}]+)/u', $text, $matches);

        foreach ($matches[0] as $chunk) {
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/[\x{0980}-\x{09FF}]/u', $chunk)) {
                $pdf->SetFont('NotoBengali', '', $fontSize);
            } elseif (preg_match('/[\x{0900}-\x{097F}]/u', $chunk)) {
                $pdf->SetFont('NotoDevanagari', '', $fontSize);
            } else {
                $pdf->SetFont('NotoLatin', $bold ? 'B' : '', $fontSize);
            }

            $pdf->Write($lineHeight, $chunk);
        }
    }

    /**
     * Safely embed an image without crashing if missing or invalid.
     */
    private static function embedImageSafely(tFPDF $pdf, string $pathOrUrl, string $alt = ''): void
    {
        $cleanPath = trim($pathOrUrl);
        if ($cleanPath === '') {
            return;
        }

        // Check if local file exists
        $localFile = null;
        if (file_exists($cleanPath)) {
            $localFile = $cleanPath;
        } elseif (file_exists(__DIR__ . '/../../public/' . ltrim($cleanPath, '/'))) {
            $localFile = __DIR__ . '/../../public/' . ltrim($cleanPath, '/');
        }

        if ($localFile !== null && is_file($localFile)) {
            $info = @getimagesize($localFile);
            if ($info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                try {
                    $maxWidth = 180; // Usable page width
                    $imgWidth = min($maxWidth, ($info[0] * 0.264)); // px to mm approx
                    $pdf->Ln(3);
                    $pdf->Image($localFile, 15, $pdf->GetY(), $imgWidth);
                    $pdf->Ln(4);
                    return;
                } catch (Throwable) {
                    // Fallback to text caption if rendering fails
                }
            }
        }

        // Render clean image placeholder caption if image cannot be embedded
        if ($alt !== '') {
            $pdf->SetFont('NotoLatin', '', 8.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 5, '[Figure: ' . $alt . ']', 0, 1, 'C');
            $pdf->Ln(2);
        }
    }

    /**
     * Parse markdown / HTML content into structured block elements.
     *
     * @return list<array{type: string, text: string, src?: string, alt?: string, bullet?: string}>
     */
    private static function parseContentBlocks(string $content): array
    {
        // Strip code blocks and script/style tags
        $cleaned = preg_replace('/```[\s\S]*?```/', '', $content);
        $cleaned = preg_replace('/<script[\s\S]*?<\/script>/i', '', (string) $cleaned);
        $cleaned = preg_replace('/<style[\s\S]*?<\/style>/i', '', (string) $cleaned);

        $lines = preg_split('/\r\n|\r|\n/', (string) $cleaned);
        $blocks = [];
        $buffer = [];

        $flushBuffer = function () use (&$blocks, &$buffer): void {
            if (!empty($buffer)) {
                $text = trim(implode(' ', $buffer));
                if ($text !== '') {
                    $blocks[] = ['type' => 'paragraph', 'text' => self::cleanInlineFormatting($text)];
                }
                $buffer = [];
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushBuffer();
                continue;
            }

            // Headings
            if (preg_match('/^###\s+(.+)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'heading3', 'text' => self::cleanInlineFormatting($m[1])];
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'heading2', 'text' => self::cleanInlineFormatting($m[1])];
                continue;
            }
            if (preg_match('/^#\s+(.+)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'heading1', 'text' => self::cleanInlineFormatting($m[1])];
                continue;
            }

            // Blockquotes
            if (preg_match('/^>\s+(.+)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'blockquote', 'text' => self::cleanInlineFormatting($m[1])];
                continue;
            }

            // Bullet list items
            if (preg_match('/^[-*•]\s+(.+)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'list_item', 'bullet' => '• ', 'text' => self::cleanInlineFormatting($m[1])];
                continue;
            }

            // Numbered list items
            if (preg_match('/^([0-9]+)\.\s+(.+)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'list_item', 'bullet' => $m[1] . '. ', 'text' => self::cleanInlineFormatting($m[2])];
                continue;
            }

            // Images ![alt](src)
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $trimmed, $m)) {
                $flushBuffer();
                $blocks[] = ['type' => 'image', 'alt' => $m[1], 'src' => $m[2], 'text' => ''];
                continue;
            }

            $buffer[] = $trimmed;
        }

        $flushBuffer();
        return $blocks;
    }

    private static function cleanInlineFormatting(string $text): string
    {
        // Convert markdown links [text](url) -> text (url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $text);
        // Strip bold, italic, code markdown symbols
        $text = preg_replace('/[*_~`]/', '', (string) $text);
        $text = strip_tags((string) $text);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Backward-compatible transliteration utility.
     */
    public static function transliterateUnicode(string $str): string
    {
        $replacements = [
            '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
            '—' => ' -- ', '–' => '-', '…' => '...',
            '•' => '*', '·' => '*', '। ' => '. ', '।' => '. ', '॥' => '. ',
        ];
        return strtr($str, $replacements);
    }
}
