<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

final class ArticleImageHelper
{
    /** @var list<string> */
    private const FALLBACK_THUMBNAILS = [
        '/assets/images/technology-article-laptop.png',
        '/assets/images/coding-coffee-workspace.png',
        '/assets/images/lifestyle-forest-road.png',
        '/assets/images/education-study-notes.png',
        '/assets/images/nature-trail-scenery.png',
    ];

    /**
     * Resolve public or local URL for an article cover image.
     * Supports Supabase Storage paths (covers/...), local uploads (/uploads/covers/...),
     * and external HTTP/HTTPS URLs. Returns null if empty or invalid.
     */
    public static function resolveCoverUrl(?string $path): ?string
    {
        if (empty($path) || !is_string($path)) {
            return null;
        }

        $cover = trim($path);
        if ($cover === '') {
            return null;
        }

        if (str_starts_with($cover, 'http://') || str_starts_with($cover, 'https://')) {
            return $cover;
        }

        if (str_starts_with($cover, '/')) {
            $publicPath = dirname(__DIR__, 2) . '/public' . $cover;
            if (file_exists($publicPath)) {
                return $cover;
            }
        }

        try {
            $storageConfig = \InfinityBlog\Storage\SupabaseStorageConfig::fromEnvironment();
            if ($storageConfig !== null && $storageConfig->isConfigured()) {
                return $storageConfig->supabaseUrl . '/storage/v1/object/public/' . rawurlencode($storageConfig->bucketArticles) . '/' . ltrim($cover, '/');
            }
        } catch (\Throwable) {
        }

        if (str_starts_with($cover, '/uploads/')) {
            return $cover;
        }

        return null;
    }

    /**
     * Resolve the thumbnail / cover image URL for an article record.
     * Only returns an image URL if the article has an explicit, valid cover image saved.
     * Returns null if no cover image was selected, preventing arbitrary or unrelated stock imagery.
     *
     * @param array<string, mixed> $post
     */
    public static function resolveThumbnail(array $post, int $index = 0): ?string
    {
        if (!empty($post['cover_image_path']) && is_string($post['cover_image_path'])) {
            $cover = self::resolveCoverUrl($post['cover_image_path']);
            if ($cover !== null) {
                return $cover;
            }
        }

        if (!empty($post['featured_image_url']) && is_string($post['featured_image_url'])) {
            $feat = self::resolveCoverUrl($post['featured_image_url']);
            if ($feat !== null) {
                return $feat;
            }
        }

        if (!empty($post['image_url']) && is_string($post['image_url'])) {
            $img = self::resolveCoverUrl($post['image_url']);
            if ($img !== null) {
                return $img;
            }
        }

        return null;
    }

    /**
     * Resolve public or local URL for a user avatar.
     * Supports Supabase Storage paths (avatars/...), local uploads (/uploads/avatars/...),
     * and external HTTP/HTTPS URLs. Returns null if empty.
     */
    public static function resolveAvatarUrl(?string $path): ?string
    {
        return \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($path);
    }

    /**
     * Resolve public or local URL for a user banner.
     * Supports Supabase Storage paths (banners/...), local uploads (/uploads/banners/...),
     * and external HTTP/HTTPS URLs. Returns null if empty.
     */
    public static function resolveBannerUrl(?string $path): ?string
    {
        return \InfinityBlog\Support\UserIdentityHelper::resolveBannerUrl($path);
    }
}
