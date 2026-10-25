<?php
declare(strict_types=1);

namespace InfinityBlog\Storage;

use Throwable;

final class MediaService
{
    private StorageClientInterface $client;
    private SupabaseStorageConfig $config;

    public function __construct(StorageClientInterface $client, SupabaseStorageConfig $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    public function getClient(): StorageClientInterface
    {
        return $this->client;
    }

    public function getConfig(): SupabaseStorageConfig
    {
        return $this->config;
    }

    /**
     * Upload an avatar image for a user.
     * Enforces profile-avatars bucket (max 2MB, JPEG/PNG/WebP).
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @param ?string $oldPath Previous avatar path to delete upon replacement
     * @return string Stored object path
     */
    public function uploadAvatar(array $file, int $userId, ?string $oldPath = null): string
    {
        $bucket = $this->config->bucketAvatars;
        $path = $this->processAndUploadMedia(
            $file,
            $bucket,
            'avatars',
            $userId,
            SupabaseStorageConfig::LIMIT_AVATARS,
            SupabaseStorageConfig::ALLOWED_IMAGE_MIME_TYPES,
            true
        );

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $path) {
            $this->safeDelete($bucket, $oldPath);
        }

        return $path;
    }

    /**
     * Upload a profile banner image for a user.
     * Enforces profile-banners bucket (max 4MB, JPEG/PNG/WebP).
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @param ?string $oldPath Previous banner path to delete upon replacement
     * @return string Stored object path
     */
    public function uploadBanner(array $file, int $userId, ?string $oldPath = null): string
    {
        $bucket = $this->config->bucketBanners;
        $path = $this->processAndUploadMedia(
            $file,
            $bucket,
            'banners',
            $userId,
            SupabaseStorageConfig::LIMIT_BANNERS,
            SupabaseStorageConfig::ALLOWED_IMAGE_MIME_TYPES,
            true
        );

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $path) {
            $this->safeDelete($bucket, $oldPath);
        }

        return $path;
    }

    /**
     * Upload an article cover image.
     * Enforces article-images bucket (max 5MB, JPEG/PNG/WebP).
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @param ?string $oldPath Previous cover image path to delete upon replacement
     * @return string Stored object path
     */
    public function uploadCoverImage(array $file, int $userId, ?string $oldPath = null): string
    {
        $bucket = $this->config->bucketArticles;
        $path = $this->processAndUploadMedia(
            $file,
            $bucket,
            'covers',
            $userId,
            SupabaseStorageConfig::LIMIT_ARTICLES,
            SupabaseStorageConfig::ALLOWED_IMAGE_MIME_TYPES,
            true
        );

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $path) {
            $this->safeDelete($bucket, $oldPath);
        }

        return $path;
    }

    /**
     * Upload a custom category logo.
     * Enforces category-icons bucket (max 1MB, JPEG/PNG/WebP).
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @param ?string $oldPath Previous logo path to delete upon replacement
     * @return string Stored object path
     */
    public function uploadCategoryLogo(array $file, int $actorId, ?string $oldPath = null): string
    {
        $bucket = $this->config->bucketCategoryIcons;
        $path = $this->processAndUploadMedia(
            $file,
            $bucket,
            'category-logos',
            $actorId,
            SupabaseStorageConfig::LIMIT_CATEGORY_ICONS,
            SupabaseStorageConfig::ALLOWED_IMAGE_MIME_TYPES,
            true
        );

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $path) {
            $this->safeDelete($bucket, $oldPath);
        }

        return $path;
    }

    /**
     * Upload an inline article image.
     * Enforces article-images bucket (max 5MB, JPEG/PNG/WebP).
     * Returns both stored object path and public URL for inserting into content.
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @return array{path: string, url: string}
     */
    public function uploadInlineArticleImage(array $file, int $userId): array
    {
        $bucket = $this->config->bucketArticles;
        $path = $this->processAndUploadMedia(
            $file,
            $bucket,
            'inline',
            $userId,
            SupabaseStorageConfig::LIMIT_ARTICLES,
            SupabaseStorageConfig::ALLOWED_IMAGE_MIME_TYPES,
            true
        );

        $url = $this->client->getPublicUrl($bucket, $path);
        return ['path' => $path, 'url' => $url];
    }

    /**
     * Upload a private article video.
     * Enforces article-videos bucket (max 50MB, MP4/WebM).
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @param ?string $oldPath Previous video path to delete upon replacement
     * @return string Stored object path
     */
    public function uploadVideo(array $file, int $userId, ?string $oldPath = null): string
    {
        $bucket = $this->config->bucketVideos;
        $path = $this->processAndUploadMedia(
            $file,
            $bucket,
            'videos',
            $userId,
            SupabaseStorageConfig::LIMIT_VIDEOS,
            SupabaseStorageConfig::ALLOWED_VIDEO_MIME_TYPES,
            false
        );

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $path) {
            $this->safeDelete($bucket, $oldPath);
        }

        return $path;
    }

    /**
     * Resolve public URL for an object in a public bucket.
     */
    public function resolvePublicUrl(string $bucket, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->client->getPublicUrl($bucket, $path);
    }

    /**
     * Create an authorized, short-lived signed URL for a private video object.
     */
    public function resolveSignedVideoUrl(string $path, int $expiresInSeconds = 3600): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->client->createSignedUrl($this->config->bucketVideos, $path, $expiresInSeconds);
    }

    /**
     * Safely delete an object without throwing if it fails or does not exist.
     */
    public function safeDelete(string $bucket, string $path): bool
    {
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return false;
        }

        // Clean up local mirror file if present
        $projectRoot = dirname(__DIR__, 2);
        $cleanPath = ltrim($path, '/');
        $cleanSub = preg_replace('#^(avatars|banners|covers|category-logos|inline|videos)/#', '', $cleanPath);
        @unlink($projectRoot . '/public/uploads/' . $cleanPath);
        if ($cleanSub !== $cleanPath) {
            @unlink($projectRoot . '/public/uploads/avatars/' . $cleanSub);
            @unlink($projectRoot . '/public/uploads/banners/' . $cleanSub);
            @unlink($projectRoot . '/public/uploads/covers/' . $cleanSub);
        }

        try {
            return $this->client->delete($bucket, $path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Common validation, key generation, and upload routine.
     *
     * @param array{tmp_name: string, name?: string, size: int, error: int} $file
     * @param list<string> $allowedMimes
     */
    private function processAndUploadMedia(
        array $file,
        string $bucket,
        string $folderPrefix,
        int $userId,
        int $maxSizeBytes,
        array $allowedMimes,
        bool $isImage
    ): string {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new StorageException(self::fileUploadErrorMessage($errorCode), 'UPLOAD_ERR');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new StorageException('The uploaded file could not be read from disk.', 'INVALID_FILE');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $size = (int) filesize($tmpPath);
        }
        if ($size > $maxSizeBytes) {
            $limitMb = (int) round($maxSizeBytes / (1024 * 1024));
            throw new StorageException("File size exceeds the {$limitMb}MB limit for this category.", 'FILE_TOO_LARGE');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!is_string($detectedMime) || !in_array($detectedMime, $allowedMimes, true)) {
            $allowedList = implode(', ', $allowedMimes);
            throw new StorageException("Invalid file type ({$detectedMime}). Allowed formats: {$allowedList}.", 'INVALID_MIME_TYPE');
        }

        // Additional deep content verification
        if ($isImage) {
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
                throw new StorageException('The uploaded file is not a valid image.', 'CORRUPT_IMAGE');
            }
        } else {
            // Video verification (MP4/WebM magic bytes)
            $handle = @fopen($tmpPath, 'rb');
            if ($handle !== false) {
                $headerBytes = fread($handle, 64);
                fclose($handle);
                if (!self::isValidVideoHeader($detectedMime, (string) $headerBytes)) {
                    throw new StorageException('The uploaded video container is malformed or invalid.', 'CORRUPT_VIDEO');
                }
            }
        }

        // Generate clean extension and cryptographically random object key
        $ext = self::extensionForMime($detectedMime);
        $randomHex = bin2hex(random_bytes(16));
        $timestamp = gmdate('Ymd_His');
        $objectPath = "{$folderPrefix}/u{$userId}_{$timestamp}_{$randomHex}.{$ext}";

        $binaryData = file_get_contents($tmpPath);
        if ($binaryData === false) {
            throw new StorageException('Could not read temporary file content.', 'READ_ERROR');
        }

        // Always mirror uploaded file to public/uploads directory for robust local availability
        $projectRoot = dirname(__DIR__, 2);
        $localDest = $projectRoot . '/public/uploads/' . $objectPath;
        $localDir = dirname($localDest);
        if (!is_dir($localDir)) {
            @mkdir($localDir, 0755, true);
        }
        @file_put_contents($localDest, $binaryData);

        return $this->client->upload($bucket, $objectPath, $binaryData, $detectedMime, true);
    }

    private static function isValidVideoHeader(string $mime, string $header): bool
    {
        if ($mime === 'video/mp4') {
            // MP4 files contain 'ftyp' box within the first 16 bytes
            return str_contains($header, 'ftyp');
        }
        if ($mime === 'video/webm') {
            // WebM starts with EBML ID: 0x1A 0x45 0xDF 0xA3
            return str_starts_with($header, "\x1A\x45\xDF\xA3");
        }
        return false;
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => 'bin',
        };
    }

    private static function fileUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size allowed by server.',
            UPLOAD_ERR_PARTIAL => 'File upload was only partially received.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder for upload on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write upload to server disk.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked the file upload.',
            default => 'Unknown file upload error.',
        };
    }
}
