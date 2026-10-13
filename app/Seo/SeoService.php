<?php
declare(strict_types=1);

namespace InfinityBlog\Seo;

final class SeoService
{
    private string $baseUrl;
    private ?string $gaMeasurementId;
    private ?string $gscVerificationCode;

    public function __construct(string $baseUrl, ?string $gaMeasurementId = null, ?string $gscVerificationCode = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->gaMeasurementId = $gaMeasurementId ?: (getenv('GA_MEASUREMENT_ID') ?: ($_ENV['GA_MEASUREMENT_ID'] ?? null));
        $this->gscVerificationCode = $gscVerificationCode ?: (getenv('GSC_VERIFICATION_CODE') ?: ($_ENV['GSC_VERIFICATION_CODE'] ?? null));
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getGaMeasurementId(): ?string
    {
        return $this->gaMeasurementId;
    }

    public function getGscVerificationCode(): ?string
    {
        return $this->gscVerificationCode;
    }

    /**
     * Build clean page title.
     */
    public function formatTitle(?string $pageTitle): string
    {
        if ($pageTitle === null || trim($pageTitle) === '') {
            return 'Infinity Blog — Ideas. Stories. Perspectives.';
        }
        $trimmed = trim($pageTitle);
        if (str_contains($trimmed, 'Infinity Blog')) {
            return $trimmed;
        }
        return $trimmed . ' — Infinity Blog';
    }

    /**
     * Clean and truncate meta description (150-160 characters ideal, max 190).
     */
    public function formatDescription(?string $description): string
    {
        if ($description === null || trim($description) === '') {
            return 'Infinity Blog — A modern publishing platform exploring web development, artificial intelligence, software engineering, and creative perspectives.';
        }

        $plain = strip_tags($description);
        $plain = (string) preg_replace('/\s+/', ' ', $plain);
        $plain = trim($plain);

        if (mb_strlen($plain) > 160) {
            return mb_substr($plain, 0, 157) . '...';
        }

        return $plain;
    }

    /**
     * Build canonical URL.
     */
    public function buildCanonicalUrl(string $path, array $allowedParams = []): string
    {
        $cleanPath = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? '/', '/');
        if ($cleanPath !== '/' && str_ends_with($cleanPath, '/')) {
            $cleanPath = rtrim($cleanPath, '/');
        }

        $queryString = '';
        if (!empty($allowedParams)) {
            $queryString = '?' . http_build_query($allowedParams);
        }

        return $this->baseUrl . $cleanPath . $queryString;
    }

    /**
     * Ensure an image URL is absolute for Open Graph and Twitter cards.
     */
    public function resolveAbsoluteImageUrl(?string $imageUrl): string
    {
        if ($imageUrl === null || trim($imageUrl) === '') {
            return $this->baseUrl . '/assets/images/floating-blog-documents.png';
        }

        $trimmed = trim($imageUrl);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return $this->baseUrl . '/' . ltrim($trimmed, '/');
    }

    /**
     * Generate Schema.org JSON-LD data structure.
     *
     * @param array<string, mixed> $contextData
     * @return array<string, mixed>
     */
    public function generateSchema(string $pageType, array $contextData = []): array
    {
        $schemas = [];

        // Base Organization
        $orgSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Infinity Blog',
            'url' => $this->baseUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $this->baseUrl . '/favicon.png',
            ],
            'description' => 'A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.',
        ];

        // WebSite Schema with SearchAction
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Infinity Blog',
            'url' => $this->baseUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->baseUrl . '/explore?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        if ($pageType === 'home') {
            $schemas[] = $websiteSchema;
            $schemas[] = $orgSchema;
        } elseif ($pageType === 'article') {
            $post = $contextData['post'] ?? [];
            $canonicalUrl = $contextData['canonicalUrl'] ?? ($this->baseUrl . '/article/' . ($post['id'] ?? ''));
            $authorName = $post['author_name'] ?? 'Author';
            $authorUrl = $this->baseUrl . '/author/' . ($post['author_id'] ?? '');
            $title = $post['title'] ?? 'Article';
            $description = $this->formatDescription($post['summary'] ?? ($post['excerpt'] ?? $post['content'] ?? ''));
            $image = $this->resolveAbsoluteImageUrl($contextData['ogImage'] ?? null);
            $publishedAt = !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c');
            $updatedAt = !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : $publishedAt;

            $articleSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $canonicalUrl,
                ],
                'headline' => $title,
                'description' => $description,
                'image' => $image,
                'datePublished' => $publishedAt,
                'dateModified' => $updatedAt,
                'author' => [
                    '@type' => 'Person',
                    'name' => $authorName,
                    'url' => $authorUrl,
                ],
                'publisher' => $orgSchema,
            ];

            // Breadcrumbs
            $breadcrumbSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $this->baseUrl . '/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $post['category_name'] ?? 'Explore',
                        'item' => !empty($post['category_slug'])
                            ? $this->baseUrl . '/explore?category=' . urlencode((string) $post['category_slug'])
                            : $this->baseUrl . '/explore',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $title,
                        'item' => $canonicalUrl,
                    ],
                ],
            ];

            $schemas[] = $articleSchema;
            $schemas[] = $breadcrumbSchema;
        } elseif ($pageType === 'author') {
            $author = $contextData['author'] ?? [];
            $authorUrl = $this->baseUrl . '/author/' . ($author['id'] ?? '');
            $authorName = $author['display_name'] ?? 'Author';
            $bio = $author['bio'] ?? 'Contributing author on Infinity Blog.';
            $avatarUrl = $this->resolveAbsoluteImageUrl($contextData['ogImage'] ?? null);

            $profileSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'mainEntity' => [
                    '@type' => 'Person',
                    'name' => $authorName,
                    'description' => $bio,
                    'url' => $authorUrl,
                    'image' => $avatarUrl,
                ],
            ];

            $breadcrumbSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $this->baseUrl . '/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Authors',
                        'item' => $this->baseUrl . '/authors',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $authorName,
                        'item' => $authorUrl,
                    ],
                ],
            ];

            $schemas[] = $profileSchema;
            $schemas[] = $breadcrumbSchema;
        } elseif ($pageType === 'categories' || $pageType === 'explore') {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $contextData['pageTitle'] ?? 'Articles — Infinity Blog',
                'url' => $contextData['canonicalUrl'] ?? ($this->baseUrl . '/explore'),
                'description' => $this->formatDescription($contextData['metaDescription'] ?? null),
                'publisher' => $orgSchema,
            ];
        } else {
            // General WebPage / ContactPage / AboutPage
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => ($pageType === 'contact' ? 'ContactPage' : 'WebPage'),
                'name' => $contextData['pageTitle'] ?? 'Infinity Blog',
                'url' => $contextData['canonicalUrl'] ?? $this->baseUrl,
                'description' => $this->formatDescription($contextData['metaDescription'] ?? null),
                'publisher' => $orgSchema,
            ];
        }

        return $schemas;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOrganizationSchema(): array
    {
        return [
            '@type' => 'Organization',
            'name' => 'Infinity Blog',
            'url' => $this->baseUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $this->baseUrl . '/favicon.png',
            ],
            'description' => 'A modern publishing platform exploring web development, AI, software engineering, and creative perspectives.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildWebSiteSchema(): array
    {
        return [
            '@type' => 'WebSite',
            'name' => 'Infinity Blog',
            'url' => $this->baseUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->baseUrl . '/explore?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param array<int, array{name: string, url: string}> $crumbs
     * @return array<string, mixed>
     */
    public function buildBreadcrumbSchema(array $crumbs): array
    {
        $itemList = [];
        $pos = 1;
        foreach ($crumbs as $crumb) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $crumb['name'] ?? '',
                'item' => $crumb['url'] ?? '',
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function buildArticleSchema(array $post, string $canonicalUrl, string $imageUrl): array
    {
        $publishedAt = !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c');
        $updatedAt = !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : $publishedAt;
        $authorName = $post['author_name'] ?? ($post['author']['display_name'] ?? ($post['author']['username'] ?? 'Cristine Palmer'));
        $authorUrl = !empty($post['author_id']) ? $this->baseUrl . '/author/' . $post['author_id'] : $this->baseUrl . '/authors';

        return [
            '@type' => 'BlogPosting',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl,
            ],
            'headline' => $post['title'] ?? 'Article',
            'description' => $this->formatDescription($post['summary'] ?? ($post['excerpt'] ?? ($post['content'] ?? ''))),
            'image' => $this->resolveAbsoluteImageUrl($imageUrl),
            'datePublished' => $publishedAt,
            'dateModified' => $updatedAt,
            'author' => [
                '@type' => 'Person',
                'name' => $authorName,
                'url' => $authorUrl,
            ],
            'publisher' => $this->buildOrganizationSchema(),
        ];
    }

    /**
     * @param array<string, mixed> $author
     * @return array<string, mixed>
     */
    public function buildAuthorSchema(array $author, string $canonicalUrl): array
    {
        $authorName = $author['display_name'] ?? ($author['username'] ?? 'Author');
        $bio = $author['bio'] ?? 'Contributing author on Infinity Blog.';
        $avatarUrl = !empty($author['avatar_url']) ? $this->resolveAbsoluteImageUrl($author['avatar_url']) : null;

        $schema = [
            '@type' => 'ProfilePage',
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $authorName,
                'description' => $bio,
                'url' => $canonicalUrl,
            ],
        ];

        if ($avatarUrl !== null) {
            $schema['mainEntity']['image'] = $avatarUrl;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContactSchema(): array
    {
        return [
            '@type' => 'ContactPage',
            'name' => 'Contact Infinity Blog',
            'url' => $this->baseUrl . '/contact',
            'description' => 'Get in touch with Infinity Blog editorial, security, and technical support.',
            'publisher' => $this->buildOrganizationSchema(),
        ];
    }

    /**
     * Render graph-wrapped JSON-LD script string.
     *
     * @param array<int, array<string, mixed>> $schemas
     */
    public function renderJsonLd(array $schemas): string
    {
        if (empty($schemas)) {
            return '';
        }

        $cleaned = array_map(function ($s) {
            unset($s['@context']);
            return $s;
        }, $schemas);

        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => array_values($cleaned),
        ];

        return (string) json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
