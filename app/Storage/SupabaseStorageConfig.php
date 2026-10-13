<?php
declare(strict_types=1);

namespace InfinityBlog\Storage;

use InfinityBlog\Config\EnvironmentLoader;
use InvalidArgumentException;

final class SupabaseStorageConfig
{
    public const DEFAULT_BUCKET_ARTICLES = 'article-images';
    public const DEFAULT_BUCKET_AVATARS = 'profile-avatars';
    public const DEFAULT_BUCKET_BANNERS = 'profile-banners';
    public const DEFAULT_BUCKET_VIDEOS = 'article-videos';
    public const DEFAULT_BUCKET_AUDIO = 'article-audio';
    public const DEFAULT_BUCKET_CATEGORY_ICONS = 'category-icons';

    public const LIMIT_ARTICLES = 5 * 1024 * 1024; // 5 MB
    public const LIMIT_AVATARS = 2 * 1024 * 1024;  // 2 MB
    public const LIMIT_BANNERS = 4 * 1024 * 1024;  // 4 MB
    public const LIMIT_VIDEOS = 50 * 1024 * 1024;  // 50 MB
    public const LIMIT_AUDIO = 20 * 1024 * 1024;   // 20 MB
    public const LIMIT_CATEGORY_ICONS = 1 * 1024 * 1024; // 1 MB

    /** @var list<string> */
    public const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    public const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4', 'video/webm'];

    /** @var list<string> */
    public const ALLOWED_AUDIO_MIME_TYPES = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/aac'];

    public string $supabaseUrl;
    public string $secretKey;
    public string $bucketArticles;
    public string $bucketAvatars;
    public string $bucketBanners;
    public string $bucketVideos;
    public string $bucketAudio;
    public string $bucketCategoryIcons;

    public function __construct(
        string $supabaseUrl,
        string $secretKey,
        string $bucketArticles = self::DEFAULT_BUCKET_ARTICLES,
        string $bucketAvatars = self::DEFAULT_BUCKET_AVATARS,
        string $bucketBanners = self::DEFAULT_BUCKET_BANNERS,
        string $bucketVideos = self::DEFAULT_BUCKET_VIDEOS,
        string $bucketAudio = self::DEFAULT_BUCKET_AUDIO,
        string $bucketCategoryIcons = self::DEFAULT_BUCKET_CATEGORY_ICONS
    ) {
        $this->supabaseUrl = rtrim($supabaseUrl, '/');
        $this->secretKey = $secretKey;
        $this->bucketArticles = $bucketArticles !== '' ? $bucketArticles : self::DEFAULT_BUCKET_ARTICLES;
        $this->bucketAvatars = $bucketAvatars !== '' ? $bucketAvatars : self::DEFAULT_BUCKET_AVATARS;
        $this->bucketBanners = $bucketBanners !== '' ? $bucketBanners : self::DEFAULT_BUCKET_BANNERS;
        $this->bucketVideos = $bucketVideos !== '' ? $bucketVideos : self::DEFAULT_BUCKET_VIDEOS;
        $this->bucketAudio = $bucketAudio !== '' ? $bucketAudio : self::DEFAULT_BUCKET_AUDIO;
        $this->bucketCategoryIcons = $bucketCategoryIcons !== '' ? $bucketCategoryIcons : self::DEFAULT_BUCKET_CATEGORY_ICONS;
    }

    /**
     * Build configuration from array (e.g. from loaded environment).
     *
     * @param array<string, mixed> $environment
     */
    public static function fromArray(array $environment): ?self
    {
        $url = trim((string) ($environment['SUPABASE_URL'] ?? ''));
        $key = trim((string) ($environment['SUPABASE_SECRET_KEY'] ?? ''));

        if ($url === '' && $key === '') {
            return null;
        }

        $bucketArticles = trim((string) ($environment['SUPABASE_BUCKET_ARTICLES'] ?? self::DEFAULT_BUCKET_ARTICLES));
        $bucketAvatars = trim((string) ($environment['SUPABASE_BUCKET_AVATARS'] ?? self::DEFAULT_BUCKET_AVATARS));
        $bucketBanners = trim((string) ($environment['SUPABASE_BUCKET_BANNERS'] ?? self::DEFAULT_BUCKET_BANNERS));
        $bucketVideos = trim((string) ($environment['SUPABASE_BUCKET_VIDEOS'] ?? self::DEFAULT_BUCKET_VIDEOS));
        $bucketAudio = trim((string) ($environment['SUPABASE_BUCKET_AUDIO'] ?? self::DEFAULT_BUCKET_AUDIO));
        $bucketCategoryIcons = trim((string) ($environment['SUPABASE_BUCKET_CATEGORY_ICONS'] ?? self::DEFAULT_BUCKET_CATEGORY_ICONS));

        return new self($url, $key, $bucketArticles, $bucketAvatars, $bucketBanners, $bucketVideos, $bucketAudio, $bucketCategoryIcons);
    }

    /**
     * Build configuration by reading .env file with the established environment loader.
     */
    public static function fromEnvironment(?string $envPath = null): ?self
    {
        $path = $envPath ?? (dirname(__DIR__, 2) . '/.env');
        $environment = EnvironmentLoader::loadFile($path, getenv());
        return self::fromArray($environment);
    }

    /**
     * Checks if the configuration has non-placeholder and well-formed credentials.
     */
    public function isConfigured(): bool
    {
        if ($this->supabaseUrl === '' || $this->secretKey === '') {
            return false;
        }

        if (str_contains($this->supabaseUrl, 'YOUR_PROJECT_REF') || str_contains($this->secretKey, 'YOUR_SECRET_KEY')) {
            return false;
        }

        $parsed = parse_url($this->supabaseUrl);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        return in_array($parsed['scheme'], ['http', 'https'], true);
    }

    /**
     * Get maximum size limit for a given bucket.
     */
    public static function getBucketLimit(string $bucket): int
    {
        return match ($bucket) {
            self::DEFAULT_BUCKET_AVATARS => self::LIMIT_AVATARS,
            self::DEFAULT_BUCKET_BANNERS => self::LIMIT_BANNERS,
            self::DEFAULT_BUCKET_VIDEOS => self::LIMIT_VIDEOS,
            self::DEFAULT_BUCKET_AUDIO => self::LIMIT_AUDIO,
            self::DEFAULT_BUCKET_CATEGORY_ICONS => self::LIMIT_CATEGORY_ICONS,
            default => self::LIMIT_ARTICLES,
        };
    }

    /**
     * Get allowed MIME types for a given bucket.
     *
     * @return list<string>
     */
    public static function getBucketMimeTypes(string $bucket): array
    {
        return match ($bucket) {
            self::DEFAULT_BUCKET_VIDEOS => self::ALLOWED_VIDEO_MIME_TYPES,
            self::DEFAULT_BUCKET_AUDIO => self::ALLOWED_AUDIO_MIME_TYPES,
            default => self::ALLOWED_IMAGE_MIME_TYPES,
        };
    }
}
