<?php
declare(strict_types=1);

namespace InfinityBlog\Support;

use InfinityBlog\Storage\SupabaseStorageConfig;
use PDO;

/**
 * Authoritative helper for user identity formatting, handle normalization,
 * and profile picture / avatar resolution across Infinity Blog.
 */
final class UserIdentityHelper
{
    /**
     * Format a public Instagram-style handle (e.g., '@cristine_palmer').
     *
     * Guarantee: Email addresses are NEVER exposed as public usernames or handles.
     * If $username is an email or empty, derives a clean handle from $displayName.
     *
     * @param ?string $username Raw stored username or handle
     * @param ?string $displayName User's display name for fallback
     * @return string Formatted public handle prefixed with '@'
     */
    public static function formatHandle(?string $username, ?string $displayName = null): string
    {
        $clean = self::cleanHandle($username);
        if ($clean !== null) {
            return '@' . $clean;
        }

        if ($displayName !== null && trim($displayName) !== '') {
            $fromName = self::slugifyHandle($displayName);
            if ($fromName !== '') {
                return '@' . $fromName;
            }
        }

        return '@user';
    }

    /**
     * Return clean handle without leading '@'.
     * Returns null if empty, invalid, or if the value is an email address.
     */
    public static function cleanHandle(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        $raw = trim($username);
        if ($raw === '') {
            return null;
        }

        // Never permit email addresses to be treated as valid handles
        if (str_contains($raw, '@')) {
            return null;
        }

        // Only allow alphanumeric characters, dots, and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9._]/', '', $raw);
        if ($sanitized === null || $sanitized === '') {
            return null;
        }

        $trimmed = trim($sanitized, '._');
        if ($trimmed === '' || strlen($trimmed) < 2) {
            return null;
        }

        return strtolower($trimmed);
    }

    /**
     * Transform a display name into a clean, lowercased handle slug (e.g. 'Cristine Palmer' -> 'cristine_palmer').
     */
    public static function slugifyHandle(string $displayName): string
    {
        $cleaned = trim($displayName);
        if ($cleaned === '') {
            return 'user';
        }

        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($cleaned));
        $trimmed = trim((string) $slug, '_');
        if ($trimmed === '' || strlen($trimmed) < 2) {
            return 'user';
        }

        return substr($trimmed, 0, 30);
    }

    /**
     * Generate a guaranteed unique handle from a display name against the database.
     */
    public static function generateHandle(PDO $pdo, string $displayName, ?int $excludeUserId = null): string
    {
        $base = self::slugifyHandle($displayName);
        return self::ensureUniqueHandle($pdo, $base, $excludeUserId);
    }

    /**
     * Ensure a candidate handle is unique in the users table.
     * Appends _2, _3, etc., if collision occurs.
     */
    public static function ensureUniqueHandle(PDO $pdo, string $candidate, ?int $excludeUserId = null): string
    {
        $clean = self::cleanHandle($candidate) ?? self::slugifyHandle($candidate);
        if ($clean === '') {
            $clean = 'user';
        }

        $handle = $clean;
        $counter = 1;

        while (true) {
            $sql = 'SELECT id FROM users WHERE LOWER(username) = LOWER(:username)';
            if ($excludeUserId !== null && $excludeUserId > 0) {
                $sql .= ' AND id != :exclude_id';
            }
            $sql .= ' LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':username', $handle, PDO::PARAM_STR);
            if ($excludeUserId !== null && $excludeUserId > 0) {
                $stmt->bindValue(':exclude_id', $excludeUserId, PDO::PARAM_INT);
            }
            $stmt->execute();

            if ($stmt->fetchColumn() === false) {
                return $handle;
            }

            $counter++;
            $handle = $clean . '_' . $counter;
        }
    }

    /**
     * Resolve a valid, public URL for a user's avatar.
     * Supports:
     *  - Full HTTP / HTTPS URLs (returned as-is).
     *  - Local uploads in /uploads/avatars/ or /uploads/.
     *  - Supabase Storage object paths in the profile-avatars bucket.
     *
     * Prevents duplicate path concatenation or invalid prefixes.
     *
     * @param ?string $path Avatar path from database or storage
     * @return ?string Resolved absolute or root-relative URL, or null if empty
     */
    public static function resolveAvatarUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        // External URLs
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $projectRoot = dirname(__DIR__, 2);

        // 1. Check already root-relative upload path
        if (str_starts_with($trimmed, '/uploads/')) {
            $localRoot = $projectRoot . '/public' . $trimmed;
            if (file_exists($localRoot)) {
                $sz = @filesize($localRoot);
                if ($sz !== false && $sz >= 128) {
                    return $trimmed;
                }
                return null;
            }
        }

        // 2. Check unslashed upload path
        if (str_starts_with($trimmed, 'uploads/')) {
            $localRoot = $projectRoot . '/public/' . $trimmed;
            if (file_exists($localRoot)) {
                $sz = @filesize($localRoot);
                if ($sz !== false && $sz >= 128) {
                    return '/' . $trimmed;
                }
                return null;
            }
        }

        $cleanRel = ltrim($trimmed, '/');
        $subName = preg_replace('#^avatars/#', '', $cleanRel);

        // 3. Check local public/uploads/avatars directory first
        $localAvatarsPath = $projectRoot . '/public/uploads/avatars/' . $subName;
        if (file_exists($localAvatarsPath)) {
            $sz = @filesize($localAvatarsPath);
            if ($sz !== false && $sz >= 128) {
                return '/uploads/avatars/' . $subName;
            }
            return null;
        }

        // 4. Check local public/uploads directory
        $localUploadsPath = $projectRoot . '/public/uploads/' . $cleanRel;
        if (file_exists($localUploadsPath)) {
            $sz = @filesize($localUploadsPath);
            if ($sz !== false && $sz >= 128) {
                return '/uploads/' . $cleanRel;
            }
            return null;
        }

        // 5. Resolve via Supabase Storage if configured
        try {
            $storageConfig = SupabaseStorageConfig::fromEnvironment();
            if ($storageConfig !== null && $storageConfig->isConfigured()) {
                $encodedPath = implode('/', array_map('rawurlencode', explode('/', $cleanRel)));
                return $storageConfig->supabaseUrl . '/storage/v1/object/public/'
                    . rawurlencode($storageConfig->bucketAvatars) . '/' . $encodedPath;
            }
        } catch (\Throwable) {
        }

        // Do NOT return broken non-existent file URLs when not found on local disk and not in remote storage
        return null;
    }

    /**
     * Resolve a valid, public URL for a user's profile banner.
     *
     * @param ?string $path Banner path from database or storage
     * @return ?string Resolved URL, or null if empty
     */
    public static function resolveBannerUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $projectRoot = dirname(__DIR__, 2);

        if (str_starts_with($trimmed, '/uploads/')) {
            $localRoot = $projectRoot . '/public' . $trimmed;
            if (file_exists($localRoot)) {
                $sz = @filesize($localRoot);
                if ($sz !== false && $sz >= 128) {
                    return $trimmed;
                }
                return null;
            }
        }

        if (str_starts_with($trimmed, 'uploads/')) {
            $localRoot = $projectRoot . '/public/' . $trimmed;
            if (file_exists($localRoot)) {
                $sz = @filesize($localRoot);
                if ($sz !== false && $sz >= 128) {
                    return '/' . $trimmed;
                }
                return null;
            }
        }

        $cleanRel = ltrim($trimmed, '/');
        $subName = preg_replace('#^banners/#', '', $cleanRel);

        $localBannersPath = $projectRoot . '/public/uploads/banners/' . $subName;
        if (file_exists($localBannersPath)) {
            $sz = @filesize($localBannersPath);
            if ($sz !== false && $sz >= 128) {
                return '/uploads/banners/' . $subName;
            }
            return null;
        }

        $localUploadsPath = $projectRoot . '/public/uploads/' . $cleanRel;
        if (file_exists($localUploadsPath)) {
            $sz = @filesize($localUploadsPath);
            if ($sz !== false && $sz >= 128) {
                return '/uploads/' . $cleanRel;
            }
            return null;
        }

        try {
            $storageConfig = SupabaseStorageConfig::fromEnvironment();
            if ($storageConfig !== null && $storageConfig->isConfigured()) {
                $encodedPath = implode('/', array_map('rawurlencode', explode('/', $cleanRel)));
                return $storageConfig->supabaseUrl . '/storage/v1/object/public/'
                    . rawurlencode($storageConfig->bucketBanners) . '/' . $encodedPath;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Normalize social media URL or handle into canonical, safe HTTPS URL.
     * Supported platforms: instagram, facebook, github, twitter, x, linkedin.
     *
     * Validates host against strict platform allowlist and rejects unsafe schemes
     * like javascript: or data:.
     */
    public static function normalizeSocialUrl(?string $input, string $platform): ?string
    {
        if ($input === null) {
            return null;
        }
        $raw = trim($input);
        if ($raw === '') {
            return null;
        }

        // Reject dangerous schemes early
        if (preg_match('/^(javascript|data|file|vbscript):/i', $raw)) {
            return null;
        }

        $plat = strtolower(trim($platform));
        if ($plat === 'x') {
            $plat = 'twitter';
        }

        // Remove leading @ if present
        $clean = ltrim($raw, '@');

        // Check if full URL
        if (preg_match('#^https?://#i', $clean)) {
            $parsed = parse_url($clean);
            if ($parsed === false || empty($parsed['host'])) {
                return null;
            }

            $host = strtolower($parsed['host']);
            $isHostValid = match ($plat) {
                'instagram' => (bool) preg_match('/(?:^|\.)instagram\.com$/i', $host),
                'facebook' => (bool) preg_match('/(?:^|\.)(?:facebook\.com|fb\.com)$/i', $host),
                'github' => (bool) preg_match('/(?:^|\.)github\.com$/i', $host),
                'twitter' => (bool) preg_match('/(?:^|\.)(?:x\.com|twitter\.com)$/i', $host),
                'linkedin' => (bool) preg_match('/(?:^|\.)linkedin\.com$/i', $host),
                default => false,
            };

            if (!$isHostValid) {
                return null;
            }

            $path = trim($parsed['path'] ?? '', '/');
            if ($path === '') {
                return null;
            }

            return 'https://' . $host . '/' . $path;
        }

        // If it's a handle or path without protocol
        $domainRegex = match ($plat) {
            'instagram' => '#^(?:https?://)?(?:www\.)?instagram\.com/#i',
            'facebook' => '#^(?:https?://)?(?:www\.)?(?:facebook\.com|fb\.com)/#i',
            'github' => '#^(?:https?://)?(?:www\.)?github\.com/#i',
            'twitter' => '#^(?:https?://)?(?:www\.)?(?:twitter\.com|x\.com)/#i',
            'linkedin' => '#^(?:https?://)?(?:www\.)?linkedin\.com/#i',
            default => null,
        };

        if ($domainRegex !== null) {
            $clean = preg_replace($domainRegex, '', $clean);
        }

        $clean = trim($clean, '/@');
        if ($clean === '' || preg_match('/[^a-zA-Z0-9._\-\/]/', $clean) || str_contains($clean, '..')) {
            return null;
        }

        return match ($plat) {
            'instagram' => 'https://instagram.com/' . $clean,
            'facebook' => 'https://facebook.com/' . $clean,
            'github' => 'https://github.com/' . $clean,
            'twitter' => 'https://x.com/' . $clean,
            'linkedin' => str_starts_with($clean, 'in/') || str_starts_with($clean, 'company/') || str_starts_with($clean, 'school/')
                ? 'https://linkedin.com/' . $clean
                : 'https://linkedin.com/in/' . $clean,
            default => null,
        };
    }

    /**
     * Normalize WhatsApp number to E.164 format (+[country code][digits]).
     */
    public static function normalizeWhatsAppNumber(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $raw = trim($input);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * Build public wa.me link for WhatsApp.
     */
    public static function formatWhatsAppLink(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $number);
        if ($digits === '' || strlen($digits) < 7) {
            return null;
        }
        return 'https://wa.me/' . $digits;
    }

    /**
     * Format a clean, human-readable handle for sidebar and badge display.
     */
    public static function formatDisplayHandle(?string $url, string $platform): string
    {
        if ($url === null || trim($url) === '') {
            return '';
        }
        $plat = strtolower(trim($platform));
        if ($plat === 'x') {
            $plat = 'twitter';
        }

        return match ($plat) {
            'instagram' => preg_replace('#^https?://(?:www\.)?instagram\.com/#i', '@', $url),
            'twitter' => preg_replace('#^https?://(?:www\.)?(?:twitter\.com|x\.com)/#i', '@', $url),
            'github' => preg_replace('#^https?://(?:www\.)?github\.com/#i', '', $url),
            'linkedin' => preg_replace('#^https?://(?:www\.)?linkedin\.com/(?:in/)?#i', '', $url),
            'facebook' => preg_replace('#^https?://(?:www\.)?(?:facebook\.com|fb\.com)/#i', '', $url),
            'whatsapp' => $url,
            default => $url,
        };
    }

    /**
     * Render SVG icon for a supported social platform with consistent styling.
     * Supported platforms: instagram, github, whatsapp, x / twitter, linkedin, facebook.
     */
    public static function renderPlatformSvg(string $platform, int $size = 14): string
    {
        $plat = strtolower(trim($platform));
        if ($plat === 'x') {
            $plat = 'twitter';
        }

        return match ($plat) {
            'instagram' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="#E1306C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>',
            'github' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>',
            'whatsapp' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="#25D366" aria-hidden="true"><path d="M17.472 14.382c-.301-.15-1.782-.879-2.057-.98-.276-.1-.476-.15-.677.15-.2.3-.777.98-.952 1.18-.175.2-.351.226-.652.075-.3-.15-1.267-.467-2.414-1.489-.893-.796-1.496-1.78-1.671-2.08-.175-.3-.019-.462.131-.612.135-.135.301-.35.452-.525.15-.176.2-.3.301-.5.1-.2.05-.376-.025-.526-.075-.15-.677-1.631-.927-2.233-.244-.587-.492-.507-.677-.516-.175-.01-.376-.01-.577-.01-.2 0-.526.075-.802.376-.276.3-1.053 1.028-1.053 2.508 0 1.48 1.078 2.909 1.229 3.109.15.2 2.122 3.24 5.141 4.544.718.31 1.279.496 1.716.634.721.23 1.378.197 1.9.12.58-.088 1.782-.728 2.032-1.431.25-.702.25-1.304.175-1.43-.075-.125-.276-.201-.577-.351z"/><path d="M12 2C6.48 2 2 6.48 2 12c0 1.83.49 3.55 1.34 5.03L2 22l5.12-1.33A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.2c-1.57 0-3.04-.43-4.32-1.18l-.31-.18-3.04.79.81-2.96-.2-.32A8.17 8.17 0 013.8 12c0-4.52 3.68-8.2 8.2-8.2s8.2 3.68 8.2 8.2-3.68 8.2-8.2 8.2z"/></svg>',
            'twitter' => '<svg width="' . ($size - 1) . '" height="' . ($size - 1) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'linkedin' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="#0A66C2" aria-hidden="true"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.62 1.62 0 1 0 0-3.24 1.62 1.62 0 0 0 0 3.24M7.85 18.5V10.13H5.07V18.5h2.78z"/></svg>',
            'facebook' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            default => '',
        };
    }

    /**
     * Get verified social profile links for a user array.
     * Strictly includes only platforms the user has provided valid data for.
     * Never generates default, placeholder, or fallback links.
     *
     * @param array<string, mixed> $userData User record or author array
     * @param ?string $displayName Optional override for label formatting
     * @param bool $onlyPublic If true, only include WhatsApp when whatsapp_public is true
     * @return list<array{platform: string, name: string, url: string, aria_label: string, title: string, display_handle: string, svg_icon: string, text_color?: string}>
     */
    public static function getSocialLinks(array $userData, ?string $displayName = null, bool $onlyPublic = true): array
    {
        $name = trim((string) ($displayName ?? $userData['display_name'] ?? $userData['username'] ?? 'User'));
        if ($name === '') {
            $name = 'User';
        }

        $links = [];

        // 1. Instagram
        $instagram = trim((string) ($userData['instagram_url'] ?? ''));
        if ($instagram !== '' && ($normInsta = self::normalizeSocialUrl($instagram, 'instagram')) !== null) {
            $links[] = [
                'platform' => 'instagram',
                'name' => 'Instagram',
                'url' => $normInsta,
                'aria_label' => "Visit {$name}'s Instagram profile",
                'title' => "Instagram — {$name}",
                'display_handle' => self::formatDisplayHandle($normInsta, 'instagram'),
                'svg_icon' => self::renderPlatformSvg('instagram', 14),
                'text_color' => null,
            ];
        }

        // 2. GitHub
        $github = trim((string) ($userData['github_url'] ?? ''));
        if ($github !== '' && ($normGithub = self::normalizeSocialUrl($github, 'github')) !== null) {
            $links[] = [
                'platform' => 'github',
                'name' => 'GitHub',
                'url' => $normGithub,
                'aria_label' => "Visit {$name}'s GitHub profile",
                'title' => "GitHub — {$name}",
                'display_handle' => self::formatDisplayHandle($normGithub, 'github'),
                'svg_icon' => self::renderPlatformSvg('github', 14),
                'text_color' => null,
            ];
        }

        // 3. WhatsApp
        $isWhatsAppPublic = !empty($userData['whatsapp_public']);
        $whatsappNumber = trim((string) ($userData['whatsapp_number'] ?? ''));
        $whatsappLink = trim((string) ($userData['whatsapp_link'] ?? ''));
        if ($whatsappLink === '' && $whatsappNumber !== '') {
            $whatsappLink = (string) self::formatWhatsAppLink($whatsappNumber);
        }

        if ($whatsappLink !== '' && (!$onlyPublic || $isWhatsAppPublic)) {
            $links[] = [
                'platform' => 'whatsapp',
                'name' => 'WhatsApp',
                'url' => $whatsappLink,
                'aria_label' => "Chat with {$name} on WhatsApp",
                'title' => "WhatsApp — {$name}",
                'display_handle' => $whatsappNumber !== '' ? $whatsappNumber : 'WhatsApp',
                'svg_icon' => self::renderPlatformSvg('whatsapp', 14),
                'text_color' => '#16a34a',
            ];
        }

        // 4. X (Twitter)
        $twitter = trim((string) ($userData['twitter_url'] ?? $userData['x_url'] ?? ''));
        if ($twitter !== '' && ($normTwitter = self::normalizeSocialUrl($twitter, 'twitter')) !== null) {
            $links[] = [
                'platform' => 'x',
                'name' => 'X',
                'url' => $normTwitter,
                'aria_label' => "Visit {$name}'s X profile",
                'title' => "X (Twitter) — {$name}",
                'display_handle' => self::formatDisplayHandle($normTwitter, 'twitter'),
                'svg_icon' => self::renderPlatformSvg('twitter', 13),
                'text_color' => null,
            ];
        }

        // 5. LinkedIn
        $linkedin = trim((string) ($userData['linkedin_url'] ?? ''));
        if ($linkedin !== '' && ($normLinkedin = self::normalizeSocialUrl($linkedin, 'linkedin')) !== null) {
            $links[] = [
                'platform' => 'linkedin',
                'name' => 'LinkedIn',
                'url' => $normLinkedin,
                'aria_label' => "Visit {$name}'s LinkedIn profile",
                'title' => "LinkedIn — {$name}",
                'display_handle' => self::formatDisplayHandle($normLinkedin, 'linkedin'),
                'svg_icon' => self::renderPlatformSvg('linkedin', 14),
                'text_color' => null,
            ];
        }

        // 6. Facebook
        $facebook = trim((string) ($userData['facebook_url'] ?? ''));
        if ($facebook !== '' && ($normFacebook = self::normalizeSocialUrl($facebook, 'facebook')) !== null) {
            $links[] = [
                'platform' => 'facebook',
                'name' => 'Facebook',
                'url' => $normFacebook,
                'aria_label' => "Visit {$name}'s Facebook profile",
                'title' => "Facebook — {$name}",
                'display_handle' => self::formatDisplayHandle($normFacebook, 'facebook'),
                'svg_icon' => self::renderPlatformSvg('facebook', 14),
                'text_color' => null,
            ];
        }

        return $links;
    }
}
