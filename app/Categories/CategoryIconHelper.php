<?php
declare(strict_types=1);

namespace InfinityBlog\Categories;

final class CategoryIconHelper
{
    /**
     * Complete allowlist of supported icon identifiers and their display metadata.
     *
     * @var list<array{id: string, name: string, category: string, keywords: string}>
     */
    public const ALLOWLIST = [
        ['id' => 'globe-code', 'name' => 'Globe & Web Code', 'category' => 'Web & Tech', 'keywords' => 'web development frontend backend api internet browser'],
        ['id' => 'terminal-code', 'name' => 'Terminal & Code', 'category' => 'Programming', 'keywords' => 'programming software engineering coding console bash cli syntax'],
        ['id' => 'ai-brain', 'name' => 'Brain & Neural Network', 'category' => 'AI & ML', 'keywords' => 'artificial intelligence machine learning deep llm neural algorithms'],
        ['id' => 'bar-chart', 'name' => 'Bar Chart & Analytics', 'category' => 'Data Science', 'keywords' => 'data science analytics metrics statistics charts graphs reporting'],
        ['id' => 'shield-lock', 'name' => 'Shield & Security', 'category' => 'Cybersecurity', 'keywords' => 'cybersecurity privacy security cryptography encryption auth protection'],
        ['id' => 'briefcase-check', 'name' => 'Briefcase & Career', 'category' => 'Career', 'keywords' => 'career productivity jobs professional work resume workflow time'],
        ['id' => 'graduation-cap', 'name' => 'Graduation Cap', 'category' => 'Education', 'keywords' => 'education learning courses university school tutorials students academy'],
        ['id' => 'palette', 'name' => 'Palette & Art', 'category' => 'Design', 'keywords' => 'design creativity ui ux interface colors typography vector art'],
        ['id' => 'rocket', 'name' => 'Rocket & Startup', 'category' => 'Business', 'keywords' => 'business startups launch growth ventures entrepreneurship marketing scale'],
        ['id' => 'heart-sun', 'name' => 'Heart & Wellness', 'category' => 'Lifestyle', 'keywords' => 'lifestyle wellness health balance mindfulness fitness living'],
        ['id' => 'atom', 'name' => 'Atom & Science', 'category' => 'Science', 'keywords' => 'science innovation physics research experiments discovery biology lab'],
        ['id' => 'compass', 'name' => 'Compass & Travel', 'category' => 'Travel', 'keywords' => 'travel nature exploration geography world outdoors maps adventure'],
        ['id' => 'cpu', 'name' => 'CPU Processor', 'category' => 'Hardware', 'keywords' => 'cpu tech insights hardware chip electronics computing semiconductor'],
        ['id' => 'database', 'name' => 'Database Storage', 'category' => 'Infrastructure', 'keywords' => 'database sql storage redis mysql postgres cache tables backend'],
        ['id' => 'server', 'name' => 'Cloud Server', 'category' => 'Infrastructure', 'keywords' => 'server cloud devops docker kubernetes infrastructure hosting network'],
        ['id' => 'book-open', 'name' => 'Open Book & Story', 'category' => 'Writing', 'keywords' => 'book reading stories literature novel fiction guide documentation'],
        ['id' => 'feather', 'name' => 'Feather Quill', 'category' => 'Writing', 'keywords' => 'feather writing blogging articles journalism editorial essays author'],
        ['id' => 'zap', 'name' => 'Lightning Zap', 'category' => 'Performance', 'keywords' => 'zap lightning power energy fast speed performance optimization burst'],
        ['id' => 'target', 'name' => 'Bullseye Target', 'category' => 'Strategy', 'keywords' => 'target strategy goals focus marketing conversion aim direct'],
        ['id' => 'camera', 'name' => 'Camera & Media', 'category' => 'Media', 'keywords' => 'camera photography video photo visual images media creator studio'],
        ['id' => 'music', 'name' => 'Music Note', 'category' => 'Audio', 'keywords' => 'music audio sound podcasts acoustics melody track radio listen'],
        ['id' => 'math-pi', 'name' => 'Math Pi & Logic', 'category' => 'Mathematics', 'keywords' => 'math pi mathematics calculus logic theory formulas proof'],
        ['id' => 'grid-all', 'name' => 'Grid All Topics', 'category' => 'General', 'keywords' => 'grid all topics general miscellaneous categories browse view'],
    ];

    /**
     * Map from category slug to allowlist icon identifier.
     *
     * @var array<string, string>
     */
    private const SLUG_TO_ICON = [
        'web-development' => 'globe-code',
        'programming-software-engineering' => 'terminal-code',
        'artificial-intelligence-machine-learning' => 'ai-brain',
        'data-science-analytics' => 'bar-chart',
        'cybersecurity-privacy' => 'shield-lock',
        'career-productivity' => 'briefcase-check',
        'education-learning' => 'graduation-cap',
        'design-creativity' => 'palette',
        'business-startups' => 'rocket',
        'lifestyle-wellness' => 'heart-sun',
        'science-innovation' => 'atom',
        'travel-nature' => 'compass',
        'tech-insights' => 'cpu',
        'math' => 'math-pi',
    ];

    /**
     * Return list of all allowlist icons with SVG previews.
     *
     * @return list<array{id: string, name: string, category: string, keywords: string, svg: string}>
     */
    public static function getAllowlist(): array
    {
        $result = [];
        foreach (self::ALLOWLIST as $item) {
            $result[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'category' => $item['category'],
                'keywords' => $item['keywords'],
                'svg' => self::getIcon($item['id'], 20),
            ];
        }
        return $result;
    }

    /**
     * Check if a given icon name is present in the allowlist.
     */
    public static function isValidIcon(?string $iconName): bool
    {
        if ($iconName === null || trim($iconName) === '') {
            return false;
        }
        $normalized = strtolower(trim($iconName));
        foreach (self::ALLOWLIST as $item) {
            if ($item['id'] === $normalized) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return an inline SVG icon for a given icon identifier or category slug.
     */
    public static function getIcon(?string $iconOrSlug, int $size = 16, string $cssClass = 'category-icon'): string
    {
        $normalized = strtolower(trim((string) $iconOrSlug));

        // If a category slug is passed, resolve to mapped icon id
        if (isset(self::SLUG_TO_ICON[$normalized])) {
            $normalized = self::SLUG_TO_ICON[$normalized];
        }

        return match ($normalized) {
            'globe-code' => self::renderGlobeCodeIcon($size, $cssClass),
            'terminal-code' => self::renderTerminalCodeIcon($size, $cssClass),
            'ai-brain' => self::renderAiBrainIcon($size, $cssClass),
            'bar-chart' => self::renderBarChartIcon($size, $cssClass),
            'shield-lock' => self::renderShieldLockIcon($size, $cssClass),
            'briefcase-check' => self::renderBriefcaseCheckIcon($size, $cssClass),
            'graduation-cap' => self::renderGraduationCapIcon($size, $cssClass),
            'palette' => self::renderPaletteIcon($size, $cssClass),
            'rocket' => self::renderRocketIcon($size, $cssClass),
            'heart-sun' => self::renderHeartSunIcon($size, $cssClass),
            'atom' => self::renderAtomIcon($size, $cssClass),
            'compass' => self::renderCompassIcon($size, $cssClass),
            'cpu' => self::renderCpuIcon($size, $cssClass),
            'database' => self::renderDatabaseIcon($size, $cssClass),
            'server' => self::renderServerIcon($size, $cssClass),
            'book-open' => self::renderBookOpenIcon($size, $cssClass),
            'feather' => self::renderFeatherIcon($size, $cssClass),
            'zap' => self::renderZapIcon($size, $cssClass),
            'target' => self::renderTargetIcon($size, $cssClass),
            'camera' => self::renderCameraIcon($size, $cssClass),
            'music' => self::renderMusicIcon($size, $cssClass),
            'math-pi' => self::renderMathPiIcon($size, $cssClass),
            default => self::renderGridAllIcon($size, $cssClass),
        };
    }

    /**
     * Resolve a public URL for a custom category logo image.
     */
    public static function resolveLogoUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        $trimmed = trim($path);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '/')) {
            return $trimmed;
        }
        if (str_starts_with($trimmed, 'uploads/')) {
            return '/' . $trimmed;
        }

        try {
            $storageConfig = \InfinityBlog\Storage\SupabaseStorageConfig::fromEnvironment();
            if ($storageConfig !== null && $storageConfig->isConfigured()) {
                $bucket = $storageConfig->bucketCategoryIcons ?? 'category-icons';
                return $storageConfig->supabaseUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . ltrim($trimmed, '/');
            }
        } catch (\Throwable) {
        }

        // Check local uploads fallback
        $localPath = dirname(__DIR__, 2) . '/public/uploads/category-logos/' . ltrim($trimmed, '/');
        if (file_exists($localPath)) {
            return '/uploads/category-logos/' . ltrim($trimmed, '/');
        }

        return '/uploads/category-logos/' . ltrim($trimmed, '/');
    }

    /**
     * Render the icon for a category record or string identifier.
     * Prefers custom uploaded logo if present, then allowlist icon, then slug fallback.
     *
     * @param array<string, mixed>|string|null $category
     */
    public static function renderIcon(array|string|null $category, int $size = 20, string $cssClass = 'category-icon'): string
    {
        if (is_string($category)) {
            return self::getIcon($category, $size, $cssClass);
        }
        if (!is_array($category)) {
            return self::getIcon('grid-all', $size, $cssClass);
        }

        // Option B: Custom logo
        $customLogo = $category['custom_logo_path'] ?? null;
        if (is_string($customLogo) && trim($customLogo) !== '') {
            $logoUrl = self::resolveLogoUrl($customLogo);
            $alt = htmlspecialchars($category['name'] ?? 'Category', ENT_QUOTES, 'UTF-8');
            return '<img src="' . htmlspecialchars((string) $logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '" style="width:' . $size . 'px; height:' . $size . 'px; object-fit: contain; border-radius: 6px; display: inline-block; vertical-align: middle; flex-shrink: 0;" loading="lazy">';
        }

        // Option A: Distinct icon from allowlist
        $iconName = $category['icon_name'] ?? null;
        if (is_string($iconName) && trim($iconName) !== '') {
            return self::getIcon($iconName, $size, $cssClass);
        }

        // Fallback to category slug
        $slug = $category['slug'] ?? null;
        return self::getIcon($slug, $size, $cssClass);
    }

    /**
     * Render category card visual container (e.g. for homepage cards or category list).
     *
     * @param array<string, mixed> $category
     */
    public static function renderCardBadge(array $category, int $iconSize = 22): string
    {
        $iconHtml = self::renderIcon($category, $iconSize);
        return '<div class="category-card-badge" style="width: 44px; height: 44px; border-radius: 12px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: #ffffff; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28); flex-shrink: 0;">' .
            $iconHtml .
            '</div>';
    }

    /* -------------------------------------------------------------
     * SVG Icons Implementations
     * ------------------------------------------------------------- */

    private static function renderGridAllIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect>' .
            '<rect x="14" y="3" width="7" height="7" rx="1.5"></rect>' .
            '<rect x="14" y="14" width="7" height="7" rx="1.5"></rect>' .
            '<rect x="3" y="14" width="7" height="7" rx="1.5"></rect>' .
            '</svg>';
    }

    private static function renderCpuIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
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

    private static function renderGlobeCodeIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="10"></circle>' .
            '<line x1="2" y1="12" x2="22" y2="12"></line>' .
            '<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>' .
            '</svg>';
    }

    private static function renderTerminalCodeIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<polyline points="4 17 10 11 4 5"></polyline>' .
            '<line x1="12" y1="19" x2="20" y2="19"></line>' .
            '</svg>';
    }

    private static function renderAiBrainIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>' .
            '<circle cx="12" cy="12" r="4"></circle>' .
            '</svg>';
    }

    private static function renderBarChartIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<line x1="18" y1="20" x2="18" y2="10"></line>' .
            '<line x1="12" y1="20" x2="12" y2="4"></line>' .
            '<line x1="6" y1="20" x2="6" y2="14"></line>' .
            '<line x1="3" y1="20" x2="21" y2="20"></line>' .
            '</svg>';
    }

    private static function renderShieldLockIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>' .
            '</svg>';
    }

    private static function renderBriefcaseCheckIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>' .
            '<path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>' .
            '</svg>';
    }

    private static function renderGraduationCapIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>' .
            '<path d="M6 12v5c3 3 9 3 12 0v-5"></path>' .
            '</svg>';
    }

    private static function renderPaletteIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>' .
            '<circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>' .
            '<circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>' .
            '<circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>' .
            '<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.562C22 6.5 17.5 2 12 2z"></path>' .
            '</svg>';
    }

    private static function renderRocketIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>' .
            '<path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>' .
            '<path d="M9 12H4s.55-3.03 2-4.5c1.45-1.47 4.5-2 4.5-2"></path>' .
            '<path d="M15 6v5s3.03-.55 4.5-2c1.47-1.45 2-4.5 2-4.5"></path>' .
            '</svg>';
    }

    private static function renderHeartSunIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>' .
            '</svg>';
    }

    private static function renderAtomIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="2"></circle>' .
            '<path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z" stroke-dasharray="2 3"></path>' .
            '<ellipse cx="12" cy="12" rx="10" ry="4.5" transform="rotate(30 12 12)"></ellipse>' .
            '<ellipse cx="12" cy="12" rx="10" ry="4.5" transform="rotate(-30 12 12)"></ellipse>' .
            '</svg>';
    }

    private static function renderCompassIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="10"></circle>' .
            '<polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>' .
            '</svg>';
    }

    private static function renderDatabaseIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>' .
            '<path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>' .
            '<path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>' .
            '</svg>';
    }

    private static function renderServerIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>' .
            '<rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>' .
            '<line x1="6" y1="6" x2="6.01" y2="6"></line>' .
            '<line x1="6" y1="18" x2="6.01" y2="18"></line>' .
            '</svg>';
    }

    private static function renderBookOpenIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>' .
            '<path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>' .
            '</svg>';
    }

    private static function renderFeatherIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path>' .
            '<line x1="16" y1="8" x2="2" y2="22"></line>' .
            '<line x1="17.5" y1="15" x2="9" y2="15"></line>' .
            '</svg>';
    }

    private static function renderZapIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>' .
            '</svg>';
    }

    private static function renderTargetIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<circle cx="12" cy="12" r="10"></circle>' .
            '<circle cx="12" cy="12" r="6"></circle>' .
            '<circle cx="12" cy="12" r="2"></circle>' .
            '</svg>';
    }

    private static function renderCameraIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>' .
            '<circle cx="12" cy="13" r="4"></circle>' .
            '</svg>';
    }

    private static function renderMusicIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<path d="M9 18V5l12-2v13"></path>' .
            '<circle cx="6" cy="18" r="3"></circle>' .
            '<circle cx="18" cy="16" r="3"></circle>' .
            '</svg>';
    }

    private static function renderMathPiIcon(int $s, string $c): string
    {
        return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $c . '" aria-hidden="true">' .
            '<line x1="4" y1="7" x2="20" y2="7"></line>' .
            '<path d="M9 7v11a2 2 0 0 0 2 2"></path>' .
            '<path d="M16 7v13"></path>' .
            '</svg>';
    }
}
