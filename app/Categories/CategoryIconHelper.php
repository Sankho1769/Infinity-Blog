<?php
declare(strict_types=1);

namespace InfinityBlog\Categories;

final class CategoryIconHelper
{
    /**
     * Return an inline SVG icon for a given category slug.
     */
    public static function getIcon(?string $slug, int $size = 16): string
    {
        $normalized = strtolower(trim((string) $slug));

        return match ($normalized) {
            'tech-insights' => self::renderCpuIcon($size),
            'web-development' => self::renderGlobeCodeIcon($size),
            'programming-software-engineering' => self::renderTerminalCodeIcon($size),
            'artificial-intelligence-machine-learning' => self::renderAiBrainIcon($size),
            'data-science-analytics' => self::renderBarChartIcon($size),
            'cybersecurity-privacy' => self::renderShieldLockIcon($size),
            'career-productivity' => self::renderBriefcaseCheckIcon($size),
            'education-learning' => self::renderGraduationCapIcon($size),
            'design-creativity' => self::renderPaletteIcon($size),
            'business-startups' => self::renderRocketIcon($size),
            'lifestyle-wellness' => self::renderHeartSunIcon($size),
            'science-innovation' => self::renderAtomIcon($size),
            'travel-nature' => self::renderCompassIcon($size),
            'math' => self::renderMathPiIcon($size),
            default => self::renderGridAllIcon($size),
        };
    }

    private static function renderGridAllIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect>' .
            '<rect x="14" y="3" width="7" height="7" rx="1.5"></rect>' .
            '<rect x="14" y="14" width="7" height="7" rx="1.5"></rect>' .
            '<rect x="3" y="14" width="7" height="7" rx="1.5"></rect>' .
            '</svg>';
    }

    private static function renderCpuIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<rect x="4" y="4" width="16" height="16" rx="2"></rect>' .
            '<rect x="9" y="9" width="6" height="6"></rect>' .
            '<line x1="9" y1="1" x2="9" y2="4"></line>' .
            '<line x1="15" y1="1" x2="15" y2="4"></line>' .
            '<line x1="9" y1="20" x2="9" y2="23"></line>' .
            '<line x1="15" y1="20" x2="15" y2="23"></line>' .
            '<line x1="20" y1="9" x2="23" y2="9"></line>' .
            '<line x1="20" y1="14" x2="23" y2="14"></line>' .
            '<line x1="1" y1="9" x2="4" y2="9"></line>' .
            '<line x1="1" y1="14" x2="4" y2="14"></line>' .
            '</svg>';
    }

    private static function renderGlobeCodeIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="10"></circle>' .
            '<line x1="2" y1="12" x2="22" y2="12"></line>' .
            '<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>' .
            '</svg>';
    }

    private static function renderTerminalCodeIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<polyline points="4 17 10 11 4 5"></polyline>' .
            '<line x1="12" y1="19" x2="20" y2="19"></line>' .
            '</svg>';
    }

    private static function renderAiBrainIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>' .
            '<circle cx="12" cy="12" r="4"></circle>' .
            '</svg>';
    }

    private static function renderBarChartIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<line x1="18" y1="20" x2="18" y2="10"></line>' .
            '<line x1="12" y1="20" x2="12" y2="4"></line>' .
            '<line x1="6" y1="20" x2="6" y2="14"></line>' .
            '<line x1="3" y1="20" x2="21" y2="20"></line>' .
            '</svg>';
    }

    private static function renderShieldLockIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>' .
            '</svg>';
    }

    private static function renderBriefcaseCheckIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>' .
            '<path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>' .
            '</svg>';
    }

    private static function renderGraduationCapIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>' .
            '<path d="M6 12v5c3 3 9 3 12 0v-5"></path>' .
            '</svg>';
    }

    private static function renderPaletteIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>' .
            '<circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>' .
            '<circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>' .
            '<circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>' .
            '<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.562C22 6.5 17.5 2 12 2z"></path>' .
            '</svg>';
    }

    private static function renderRocketIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>' .
            '<path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>' .
            '<path d="M9 12H4s.55-3.03 2-4.5c1.45-1.47 4.5-2 4.5-2"></path>' .
            '<path d="M15 6v5s3.03-.55 4.5-2c1.47-1.45 2-4.5 2-4.5"></path>' .
            '</svg>';
    }

    private static function renderHeartSunIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>' .
            '</svg>';
    }

    private static function renderAtomIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="2"></circle>' .
            '<path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z" stroke-dasharray="2 3"></path>' .
            '<ellipse cx="12" cy="12" rx="10" ry="4.5" transform="rotate(30 12 12)"></ellipse>' .
            '<ellipse cx="12" cy="12" rx="10" ry="4.5" transform="rotate(-30 12 12)"></ellipse>' .
            '</svg>';
    }

    private static function renderCompassIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="10"></circle>' .
            '<polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>' .
            '</svg>';
    }

    private static function renderMathPiIcon(int $s): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="category-icon" aria-hidden="true">' .
            '<line x1="4" y1="7" x2="20" y2="7"></line>' .
            '<path d="M9 7v11a2 2 0 0 0 2 2"></path>' .
            '<path d="M16 7v13"></path>' .
            '</svg>';
    }
}
