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
     * Resolve the thumbnail image URL for an article record.
     * Checks explicit fields, embedded markdown/HTML images in content,
     * and falls back cleanly to the provided project visual assets.
     *
     * @param array<string, mixed> $post
     */
    public static function resolveThumbnail(array $post, int $index = 0): string
    {
        if (!empty($post['featured_image_url']) && is_string($post['featured_image_url'])) {
            return $post['featured_image_url'];
        }

        if (!empty($post['image_url']) && is_string($post['image_url'])) {
            return $post['image_url'];
        }

        if (!empty($post['content']) && is_string($post['content'])) {
            if (preg_match('/!\[.*?\]\((https?:\/\/[^\s\)]+)\)/i', $post['content'], $match)) {
                return $match[1];
            }
            if (preg_match('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/i', $post['content'], $match)) {
                return $match[1];
            }
        }

        $category = mb_strtolower((string) ($post['category_name'] ?? ''));
        $title = mb_strtolower((string) ($post['title'] ?? ''));

        if (str_contains($category, 'tech') || str_contains($category, 'code') || str_contains($category, 'software')
            || str_contains($title, 'code') || str_contains($title, 'web') || str_contains($title, 'tech')) {
            return ($index % 2 === 0)
                ? '/assets/images/technology-article-laptop.png'
                : '/assets/images/coding-coffee-workspace.png';
        }

        if (str_contains($category, 'life') || str_contains($category, 'productiv') || str_contains($category, 'mind')
            || str_contains($title, 'focus') || str_contains($title, 'habit') || str_contains($title, 'productiv')) {
            return '/assets/images/lifestyle-forest-road.png';
        }

        if (str_contains($category, 'edu') || str_contains($category, 'learn') || str_contains($category, 'study')
            || str_contains($title, 'learn') || str_contains($title, 'guide') || str_contains($title, 'notes')) {
            return '/assets/images/education-study-notes.png';
        }

        if (str_contains($category, 'nature') || str_contains($category, 'travel') || str_contains($category, 'outdoor')
            || str_contains($title, 'nature') || str_contains($title, 'trail') || str_contains($title, 'journey')) {
            return '/assets/images/nature-trail-scenery.png';
        }

        $seed = !empty($post['id']) ? (int) $post['id'] : $index;
        $count = count(self::FALLBACK_THUMBNAILS);
        return self::FALLBACK_THUMBNAILS[abs($seed) % $count];
    }
}
