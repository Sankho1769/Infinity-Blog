<?php
declare(strict_types=1);

namespace InfinityBlog\Ebooks;

use Throwable;

final class EbookFileService
{
    public const MAX_BOOK_SIZE = 31457280; // 30 MB
    public const MAX_COVER_SIZE = 4194304;  // 4 MB

    public const ALLOWED_BOOK_MIMES = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'epub' => ['application/epub+zip', 'application/zip', 'application/octet-stream'],
    ];

    public const ALLOWED_COVER_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private string $storageDir;
    private string $coversDir;

    public function __construct(?string $storageDir = null, ?string $coversDir = null)
    {
        $projectRoot = dirname(__DIR__, 2);
        $this->storageDir = $storageDir ?? ($projectRoot . '/storage/ebooks/files');
        $this->coversDir = $coversDir ?? ($projectRoot . '/public/uploads/ebooks/covers');

        $this->ensureDirectories();
    }

    public function getStorageDir(): string
    {
        return $this->storageDir;
    }

    public function getCoversDir(): string
    {
        return $this->coversDir;
    }

    private function ensureDirectories(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
        // Protect raw eBook files directory from direct web execution
        $htaccess = $this->storageDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }

        if (!is_dir($this->coversDir)) {
            @mkdir($this->coversDir, 0755, true);
        }
    }

    /**
     * Validate and process an uploaded eBook file.
     * Supports both authenticated members and anonymous public contributions.
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @return array{format: string, size: int, path: string, original_name: string}
     * @throws EbookValidationException
     */
    public function processUploadedBook(array $file, ?int $userId = null): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new EbookValidationException([
                'book_file' => self::fileUploadErrorMessage($errorCode),
            ]);
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new EbookValidationException([
                'book_file' => 'The uploaded eBook file could not be read.',
            ]);
        }

        $size = (int) ($file['size'] ?? filesize($tmpPath));
        if ($size <= 0) {
            throw new EbookValidationException([
                'book_file' => 'The uploaded file is empty.',
            ]);
        }
        if ($size > self::MAX_BOOK_SIZE) {
            throw new EbookValidationException([
                'book_file' => 'eBook file size exceeds the 30MB maximum limit.',
            ]);
        }

        $origName = (string) ($file['name'] ?? 'ebook');
        $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($origExt, ['pdf', 'epub'], true)) {
            throw new EbookValidationException([
                'book_file' => 'Only PDF (.pdf) and EPUB (.epub) files are supported.',
            ]);
        }

        // Deep Content Verification
        if ($origExt === 'pdf') {
            $handle = @fopen($tmpPath, 'rb');
            if ($handle === false) {
                throw new EbookValidationException(['book_file' => 'Could not read PDF file header.']);
            }
            $header = (string) fread($handle, 1024);
            fclose($handle);

            if (!str_contains($header, '%PDF-')) {
                throw new EbookValidationException([
                    'book_file' => 'The uploaded file is not a valid PDF document (missing %PDF- header).',
                ]);
            }

            $format = 'pdf';
        } else {
            // EPUB verification
            if (!EpubParser::isValidEpub($tmpPath)) {
                throw new EbookValidationException([
                    'book_file' => 'The uploaded file is not a valid or safe EPUB container (failed structural and security verification).',
                ]);
            }
            $format = 'epub';
        }

        // Generate safe cryptographically randomized storage name
        $userPrefix = $userId !== null ? "u{$userId}" : 'anon';
        $randomHex = bin2hex(random_bytes(16));
        $timestamp = gmdate('Ymd_His');
        $filename = "{$userPrefix}_{$timestamp}_{$randomHex}.{$format}";
        $destPath = $this->storageDir . '/' . $filename;

        if (!@move_uploaded_file($tmpPath, $destPath)) {
            // Fallback for copy/move in CLI or test environments
            if (!@copy($tmpPath, $destPath)) {
                throw new EbookValidationException([
                    'book_file' => 'Failed to store the eBook on the server.',
                ]);
            }
            @unlink($tmpPath);
        }

        return [
            'format' => $format,
            'file_format' => $format,
            'size' => $size,
            'file_size' => $size,
            'path' => 'ebooks/files/' . $filename,
            'file_path' => 'ebooks/files/' . $filename,
            'mime_type' => $format === 'pdf' ? 'application/pdf' : 'application/epub+zip',
            'original_name' => $origName,
            'extracted_cover' => $extractedCover ?? null,
            'extracted_cover_mime' => $extractedCoverMime ?? null,
        ];
    }

    /**
     * Validate and process an uploaded cover image.
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @return string Relative URL path e.g. /uploads/ebooks/covers/...
     * @throws EbookValidationException
     */
    public function processUploadedCover(array $file, ?int $userId = null): string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new EbookValidationException([
                'cover_image' => self::fileUploadErrorMessage($errorCode),
            ]);
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new EbookValidationException([
                'cover_image' => 'The uploaded cover image could not be read.',
            ]);
        }

        $size = (int) ($file['size'] ?? filesize($tmpPath));
        if ($size > self::MAX_COVER_SIZE) {
            throw new EbookValidationException([
                'cover_image' => 'Cover image exceeds the 4MB maximum limit.',
            ]);
        }

        $imgInfo = @getimagesize($tmpPath);
        if ($imgInfo === false || empty($imgInfo['mime'])) {
            throw new EbookValidationException([
                'cover_image' => 'The uploaded file is not a valid image.',
            ]);
        }

        $mime = $imgInfo['mime'];
        if (!isset(self::ALLOWED_COVER_MIMES[$mime])) {
            throw new EbookValidationException([
                'cover_image' => 'Only JPEG, PNG, and WebP cover images are permitted.',
            ]);
        }

        $width = (int) ($imgInfo[0] ?? 0);
        $height = (int) ($imgInfo[1] ?? 0);
        if ($width < 50 || $height < 50) {
            throw new EbookValidationException([
                'cover_image' => 'Cover image is too small (minimum 50x50 pixels required).',
            ]);
        }
        if ($width > 4096 || $height > 4096) {
            throw new EbookValidationException([
                'cover_image' => 'Cover image dimensions exceed the 4096x4096px maximum limit.',
            ]);
        }

        $ext = self::ALLOWED_COVER_MIMES[$mime];
        $userPrefix = $userId !== null ? "u{$userId}" : 'anon';
        $randomHex = bin2hex(random_bytes(12));
        $timestamp = gmdate('Ymd_His');
        $filename = "cover_{$userPrefix}_{$timestamp}_{$randomHex}.{$ext}";
        $destPath = $this->coversDir . '/' . $filename;

        if (!@move_uploaded_file($tmpPath, $destPath)) {
            if (!@copy($tmpPath, $destPath)) {
                throw new EbookValidationException([
                    'cover_image' => 'Failed to store cover image.',
                ]);
            }
            @unlink($tmpPath);
        }

        return '/uploads/ebooks/covers/' . $filename;
    }

    /**
     * Save raw cover bytes (e.g. extracted from EPUB).
     */
    public function saveExtractedCover(string $binaryData, string $mime, ?int $userId = null): ?string
    {
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $userPrefix = $userId !== null ? "u{$userId}" : 'anon';
        $randomHex = bin2hex(random_bytes(12));
        $filename = "cover_extracted_{$userPrefix}_{$randomHex}.{$ext}";
        $destPath = $this->coversDir . '/' . $filename;

        if (@file_put_contents($destPath, $binaryData) !== false) {
            return '/uploads/ebooks/covers/' . $filename;
        }

        return null;
    }

    /**
     * Probe whether an external malware scanner (e.g. ClamAV / clamscan) is available.
     * Documented limitation: ClamAV is not installed or configured in standard Windows XAMPP environments.
     */
    public static function isMalwareScannerAvailable(): bool
    {
        $disabled = explode(',', (string) ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        if (in_array('exec', $disabled, true)) {
            return false;
        }

        try {
            $output = [];
            $code = 0;
            @exec('where clamscan 2>nul', $output, $code);
            return $code === 0 && !empty($output);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sliding-window rate limiter for guest eBook submissions.
     * Prevents automated spam/DoS uploads from single IP addresses.
     */
    public static function checkUploadRateLimit(\PDO $pdo, string $ip, int $maxPerWindow = 10, int $windowSeconds = 3600): bool
    {
        $cacheDir = sys_get_temp_dir() . '/infinity_rl';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . '/' . md5('ebook_upload_' . $ip) . '.json';
        $now = time();
        $timestamps = [];
        if (is_file($cacheFile)) {
            $content = (string) @file_get_contents($cacheFile);
            $data = json_decode($content, true);
            if (is_array($data)) {
                $timestamps = array_filter($data, fn($ts) => is_int($ts) && ($now - $ts) < $windowSeconds);
            }
        }
        if (count($timestamps) >= $maxPerWindow) {
            return false;
        }
        $timestamps[] = $now;
        @file_put_contents($cacheFile, json_encode(array_values($timestamps)));
        return true;
    }

    /**
     * Resolve absolute disk path from stored relative path.
     */
    public function resolveDiskPath(string $storedPath): ?string
    {
        $clean = ltrim(str_replace('\\', '/', $storedPath), '/');

        // If path starts with ebooks/files/
        if (str_starts_with($clean, 'ebooks/files/')) {
            $filename = basename($clean);
            $full = $this->storageDir . '/' . $filename;
            return is_file($full) ? $full : null;
        }

        // Direct filename in storageDir
        $full = $this->storageDir . '/' . basename($clean);
        return is_file($full) ? $full : null;
    }

    /**
     * Delete stored file from disk.
     */
    public function deleteFile(?string $storedPath): void
    {
        if ($storedPath === null || $storedPath === '') {
            return;
        }
        $diskPath = $this->resolveDiskPath($storedPath);
        if ($diskPath !== null && is_file($diskPath)) {
            @unlink($diskPath);
        }
    }

    /**
     * Delete cover file from disk.
     */
    public function deleteCover(?string $coverPath): void
    {
        if ($coverPath === null || $coverPath === '') {
            return;
        }
        $clean = ltrim(str_replace('\\', '/', $coverPath), '/');
        if (str_starts_with($clean, 'uploads/ebooks/covers/')) {
            $filename = basename($clean);
            $full = $this->coversDir . '/' . $filename;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    /**
     * Generate an SVG data URI fallback cover with gradient and initials.
     */
    public static function generateSvgCover(string $title, string $author, ?string $category = null): string
    {
        $safeTitle = htmlspecialchars(mb_substr($title, 0, 40), ENT_QUOTES, 'UTF-8');
        $safeAuthor = htmlspecialchars(mb_substr($author, 0, 30), ENT_QUOTES, 'UTF-8');
        $safeCat = htmlspecialchars(mb_substr($category ?? 'eBook', 0, 20), ENT_QUOTES, 'UTF-8');

        // Choose gradient hues based on title string hash
        $hash = crc32($title);
        $gradients = [
            ['#4f46e5', '#7c3aed'], // Indigo -> Violet
            ['#2563eb', '#06b6d4'], // Blue -> Cyan
            ['#059669', '#10b981'], // Emerald -> Teal
            ['#d97706', '#f59e0b'], // Amber -> Orange
            ['#db2777', '#f43f5e'], // Pink -> Rose
            ['#7c2d12', '#ea580c'], // Warm Brown -> Red
        ];
        $grad = $gradients[abs($hash) % count($gradients)];

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="600" viewBox="0 0 400 600">
  <defs>
    <linearGradient id="coverGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$grad[0]}" />
      <stop offset="100%" stop-color="{$grad[1]}" />
    </linearGradient>
    <pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse">
      <path d="M 30 0 L 0 0 0 30" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
    </pattern>
  </defs>
  <rect width="400" height="600" fill="url(#coverGrad)" rx="12" />
  <rect width="400" height="600" fill="url(#grid)" rx="12" />
  <rect x="0" y="0" width="16" height="600" fill="rgba(0,0,0,0.18)" rx="4"/>
  <rect x="18" y="0" width="4" height="600" fill="rgba(255,255,255,0.15)"/>
  
  <!-- Category Badge -->
  <rect x="42" y="46" width="120" height="26" rx="13" fill="rgba(255,255,255,0.2)" />
  <text x="102" y="63" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="11" font-weight="700" fill="#ffffff" text-anchor="middle" letter-spacing="1">{$safeCat}</text>
  
  <!-- Title -->
  <text x="42" y="160" font-family="'Source Serif 4', Georgia, serif" font-size="28" font-weight="700" fill="#ffffff" width="316">
    <tspan x="42" dy="0">{$safeTitle}</tspan>
  </text>
  
  <!-- Divider -->
  <line x1="42" y1="210" x2="100" y2="210" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
  
  <!-- Author -->
  <text x="42" y="246" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="16" font-weight="600" fill="rgba(255,255,255,0.88)">
    By {$safeAuthor}
  </text>
  
  <!-- Bottom Brand -->
  <g transform="translate(42, 530)">
    <circle cx="14" cy="14" r="14" fill="rgba(255,255,255,0.2)"/>
    <text x="14" y="19" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" font-weight="800" fill="#ffffff" text-anchor="middle">∞</text>
    <text x="38" y="19" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" font-weight="700" fill="rgba(255,255,255,0.75)" letter-spacing="0.5">INFINITY BLOG</text>
  </g>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private static function fileUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the file upload.',
            default => 'An unknown file upload error occurred.',
        };
    }
}
