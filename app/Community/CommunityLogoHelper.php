<?php
declare(strict_types=1);

namespace InfinityBlog\Community;

final class CommunityLogoHelper
{
    /**
     * Pre-defined SVG icons and palettes for recognizable community categories.
     *
     * @var array<string, array{gradient: string, color: string, shadow: string, svg: string}>
     */
    private static array $presets = [
        'code' => [
            'gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(37, 99, 235, 0.35)',
            'svg' => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        'ai' => [
            'gradient' => 'linear-gradient(135deg, #8b5cf6 0%, #6366f1 50%, #3b82f6 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(99, 102, 241, 0.35)',
            'svg' => '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="2.2"/>',
        ],
        'lounge' => [
            'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #ec4899 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(236, 72, 153, 0.35)',
            'svg' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="10" r="1" fill="currentColor"/><circle cx="15" cy="10" r="1" fill="currentColor"/>',
        ],
        'design' => [
            'gradient' => 'linear-gradient(135deg, #ec4899 0%, #f43f5e 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(244, 63, 94, 0.35)',
            'svg' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.563C22 6.5 17.5 2 12 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        'business' => [
            'gradient' => 'linear-gradient(135deg, #10b981 0%, #0d9488 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(16, 185, 129, 0.35)',
            'svg' => '<path d="M22 7 13.5 15.5 8.5 10.5 2 17M16 7h6v6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        'writing' => [
            'gradient' => 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(168, 85, 247, 0.35)',
            'svg' => '<path d="m18 2 4 4-10 10H8v-4L18 2zM12 20h8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        'science' => [
            'gradient' => 'linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(6, 182, 212, 0.35)',
            'svg' => '<circle cx="12" cy="12" r="3" fill="currentColor"/><path d="M12 2a10 5 0 0 1 0 20 10 5 0 0 1 0-20z" fill="none" stroke="currentColor" stroke-width="1.8" transform="rotate(45 12 12)"/><path d="M12 2a10 5 0 0 1 0 20 10 5 0 0 1 0-20z" fill="none" stroke="currentColor" stroke-width="1.8" transform="rotate(-45 12 12)"/>',
        ],
        'security' => [
            'gradient' => 'linear-gradient(135deg, #3b82f6 0%, #1e40af 100%)',
            'color' => '#ffffff',
            'shadow' => 'rgba(30, 64, 175, 0.35)',
            'svg' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
    ];

    /**
     * Map a room to an icon preset key based on slug, category, and name.
     */
    public static function resolvePresetKey(array $room): string
    {
        $slug = strtolower($room['slug'] ?? '');
        $name = strtolower($room['name'] ?? '');
        $category = strtolower($room['category_name'] ?? '');
        $combined = "{$slug} {$name} {$category}";

        if (str_contains($combined, 'ai') || str_contains($combined, 'artificial') || str_contains($combined, 'machine-learning') || str_contains($combined, 'neural') || str_contains($combined, 'llm')) {
            return 'ai';
        }
        if (str_contains($combined, 'web') || str_contains($combined, 'dev') || str_contains($combined, 'code') || str_contains($combined, 'software') || str_contains($combined, 'tech') || str_contains($combined, 'frontend') || str_contains($combined, 'backend') || str_contains($combined, 'api')) {
            return 'code';
        }
        if (str_contains($combined, 'general') || str_contains($combined, 'lounge') || str_contains($combined, 'hangout') || str_contains($combined, 'chat') || str_contains($combined, 'random') || str_contains($combined, 'community')) {
            return 'lounge';
        }
        if (str_contains($combined, 'design') || str_contains($combined, 'ui') || str_contains($combined, 'ux') || str_contains($combined, 'art') || str_contains($combined, 'creative')) {
            return 'design';
        }
        if (str_contains($combined, 'business') || str_contains($combined, 'growth') || str_contains($combined, 'startup') || str_contains($combined, 'finance') || str_contains($combined, 'market')) {
            return 'business';
        }
        if (str_contains($combined, 'writing') || str_contains($combined, 'story') || str_contains($combined, 'author') || str_contains($combined, 'book') || str_contains($combined, 'edit')) {
            return 'writing';
        }
        if (str_contains($combined, 'science') || str_contains($combined, 'data') || str_contains($combined, 'research') || str_contains($combined, 'math')) {
            return 'science';
        }
        if (str_contains($combined, 'security') || str_contains($combined, 'crypto') || str_contains($combined, 'privacy') || str_contains($combined, 'safe')) {
            return 'security';
        }

        // Deterministic fallback based on room ID or slug
        $keys = array_keys(self::$presets);
        $seed = !empty($room['id']) ? (int) $room['id'] : crc32($slug);
        return $keys[abs($seed) % count($keys)];
    }

    /**
     * Render a complete HTML logo badge for a community.
     *
     * @param array{id?: int, slug?: string, name?: string, description?: string, logo_url?: string, icon_url?: string, category_name?: string} $room
     * @param int $size Width & height in pixels (default 42)
     * @param string $extraClass Optional CSS class names
     * @return string Valid HTML
     */
    public static function renderLogoHtml(array $room, int $size = 42, string $extraClass = ''): string
    {
        $customUrl = $room['logo_url'] ?? ($room['icon_url'] ?? null);
        $cleanName = CommunityNameFormatter::format($room['name'] ?? '');
        $safeName = htmlspecialchars($cleanName, ENT_QUOTES, 'UTF-8');
        $radius = max(8, (int) round($size * 0.28));

        if (!empty($customUrl)) {
            $safeUrl = htmlspecialchars($customUrl, ENT_QUOTES, 'UTF-8');
            return sprintf(
                '<img src="%s" alt="%s logo" class="community-brand-logo %s" style="width: %dpx; height: %dpx; border-radius: %dpx; object-fit: cover; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.12);" />',
                $safeUrl,
                $safeName,
                htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8'),
                $size,
                $size,
                $radius
            );
        }

        $presetKey = self::resolvePresetKey($room);
        $preset = self::$presets[$presetKey];
        $iconSize = max(16, (int) round($size * 0.52));

        return sprintf(
            '<div class="community-brand-logo community-preset-%s %s" title="%s" style="width: %dpx; height: %dpx; min-width: %dpx; border-radius: %dpx; background: %s; color: %s; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px %s; flex-shrink: 0; transition: transform 0.2s ease, box-shadow 0.2s ease;">' .
            '<svg width="%d" height="%d" viewBox="0 0 24 24" style="display: block;">%s</svg>' .
            '</div>',
            $presetKey,
            htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8'),
            $safeName,
            $size,
            $size,
            $size,
            $radius,
            $preset['gradient'],
            $preset['color'],
            $preset['shadow'],
            $iconSize,
            $iconSize,
            $preset['svg']
        );
    }
}
