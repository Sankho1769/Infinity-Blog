<?php
declare(strict_types=1);

namespace InfinityBlog\Support;

/**
 * BrandHelper — Centralized Official Brand Identity & Logo Helper
 * Manages official Infinity Blog brand assets across viewports and themes.
 */
class BrandHelper
{
    public const LOGO_MASTER = '/assets/images/infinity-blog-logo.png';
    public const LOGO_TRANSPARENT = '/assets/images/infinity-blog-logo-transparent.png';
    public const LOGO_HORIZONTAL = '/assets/images/infinity-blog-logo-horizontal.png';
    public const LOGO_SYMBOL = '/assets/images/infinity-blog-symbol.png';

    /**
     * Render the official brand logo responsive HTML markup.
     *
     * @param string $variant 'horizontal' | 'symbol' | 'full' | 'responsive'
     * @param array $options ['class' => '', 'height' => 36, 'width' => 0, 'link' => true, 'href' => '/', 'alt' => 'Infinity Blog']
     * @return string
     */
    public static function render(string $variant = 'responsive', array $options = []): string
    {
        $link = $options['link'] ?? false;
        $href = htmlspecialchars($options['href'] ?? '/', ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($options['alt'] ?? 'Infinity Blog', ENT_QUOTES, 'UTF-8');
        $extraClass = !empty($options['class']) ? ' ' . htmlspecialchars($options['class'], ENT_QUOTES, 'UTF-8') : '';
        $height = isset($options['height']) ? (int)$options['height'] : 36;

        $html = '';

        if ($variant === 'symbol') {
            $width = isset($options['width']) ? (int)$options['width'] : (int)round($height * (269 / 167));
            $html = sprintf(
                '<img src="%s" alt="%s" class="brand-symbol-img%s" width="%d" height="%d" loading="eager" decoding="async">',
                self::LOGO_SYMBOL,
                $alt,
                $extraClass,
                $width,
                $height
            );
        } elseif ($variant === 'full') {
            $width = isset($options['width']) ? (int)$options['width'] : (int)round($height * (435 / 237));
            $html = sprintf(
                '<img src="%s" alt="%s" class="brand-logo-full-img%s" width="%d" height="%d" loading="eager" decoding="async">',
                self::LOGO_TRANSPARENT,
                $alt,
                $extraClass,
                $width,
                $height
            );
        } elseif ($variant === 'horizontal') {
            $width = isset($options['width']) ? (int)$options['width'] : (int)round($height * (324 / 64));
            $html = sprintf(
                '<img src="%s" alt="%s" class="brand-logo-img%s" width="%d" height="%d" loading="eager" decoding="async">',
                self::LOGO_HORIZONTAL,
                $alt,
                $extraClass,
                $width,
                $height
            );
        } else {
            // Responsive default: symbol on narrow screens (<= 540px), horizontal lockup on desktop
            $horizW = (int)round($height * (324 / 64));
            $html = sprintf(
                '<picture class="brand-logo-picture%s">
  <source media="(max-width: 540px)" srcset="%s">
  <img src="%s" alt="%s" class="brand-logo-img" width="%d" height="%d" loading="eager" decoding="async">
</picture>',
                $extraClass,
                self::LOGO_SYMBOL,
                self::LOGO_HORIZONTAL,
                $alt,
                $horizW,
                $height
            );
        }

        if ($link) {
            $ariaLabel = htmlspecialchars($options['aria_label'] ?? 'Infinity Blog Home', ENT_QUOTES, 'UTF-8');
            return sprintf(
                '<a href="%s" class="brand-logo%s" aria-label="%s">%s</a>',
                $href,
                $extraClass,
                $ariaLabel,
                $html
            );
        }

        return $html;
    }
}
