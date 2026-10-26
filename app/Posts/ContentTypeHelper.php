<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

final class ContentTypeHelper
{
    public const TYPE_ARTICLE = 'article';
    public const TYPE_BLOG = 'blog';
    public const TYPE_RESEARCH_PAPER = 'research_paper';
    public const TYPE_PHOTO = 'photo';

    public const ALL_TYPES = [
        self::TYPE_ARTICLE,
        self::TYPE_BLOG,
        self::TYPE_RESEARCH_PAPER,
        self::TYPE_PHOTO,
    ];

    public static function normalize(?string $type): string
    {
        if ($type === null) {
            return self::TYPE_ARTICLE;
        }
        $lower = strtolower(trim($type));
        return in_array($lower, self::ALL_TYPES, true) ? $lower : self::TYPE_ARTICLE;
    }

    public static function getLabel(string $type): string
    {
        return match (self::normalize($type)) {
            self::TYPE_BLOG => 'BLOG',
            self::TYPE_RESEARCH_PAPER => 'RESEARCH PAPER',
            self::TYPE_PHOTO => 'PHOTO',
            default => 'ARTICLE',
        };
    }

    public static function getIcon(string $type): string
    {
        return match (self::normalize($type)) {
            self::TYPE_BLOG => '✍️',
            self::TYPE_RESEARCH_PAPER => '📄',
            self::TYPE_PHOTO => '📷',
            default => '📰',
        };
    }

    public static function renderBadge(string $type, string $extraClass = ''): string
    {
        $normalized = self::normalize($type);
        $label = self::getLabel($normalized);
        $icon = self::getIcon($normalized);
        $cssType = htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8');
        $extra = !empty($extraClass) ? ' ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') : '';

        return sprintf(
            '<span class="badge badge-content-type badge-type-%s%s" title="Content Type: %s"><span class="badge-type-icon">%s</span> %s</span>',
            $cssType,
            $extra,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            $icon,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }
}
