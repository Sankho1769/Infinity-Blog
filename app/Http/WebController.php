<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Audio\TtsService;
use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\ProfileValidationException;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Contact\ContactRepository;
use InfinityBlog\Export\EpubExporter;
use InfinityBlog\Export\PdfExporter;
use InfinityBlog\Library\LibraryRepository;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Ai\ArticleAssistantService;
use InfinityBlog\Mail\MailerService;
use InfinityBlog\Moderation\PostReportRepository;
use InfinityBlog\Posts\ArticleContentRenderer;
use InfinityBlog\Posts\ArticleImageHelper;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Posts\StudioService;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Seo\SeoService;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;
use InfinityBlog\Storage\MediaService;
use InfinityBlog\Storage\StorageException;
use InfinityBlog\Storage\SupabaseStorageClient;
use InfinityBlog\Storage\SupabaseStorageConfig;
use InfinityBlog\Users\AdminUserRepository;
use Throwable;

final class WebController
{
    private PostRepository $posts;
    private CategoryRepository $categories;
    private UserRepository $users;
    private AdminUserRepository $adminUsers;
    private AuditLogRepository $auditLogs;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private AppConfig $config;
    private ?ScoreRepository $scores;
    private ?MediaService $media;
    private ?FollowRepository $follows;
    private ?MessageRepository $messages;
    private ?TtsService $tts;
    private ?LibraryRepository $library;
    private ?CommunityRepository $community;
    private ?ArticleAssistantService $assistant;
    private ?StudioService $studio;
    private SeoService $seo;
    private ?ContactRepository $contactRepo;
    private ?PostReportRepository $postReports;
    private ?MailerService $mailer;
    private ?\InfinityBlog\Reviews\ReviewRepository $reviews;
    private ?\InfinityBlog\Ebooks\EbookRepository $ebooks;
    private ?\InfinityBlog\Ebooks\EbookFileService $ebookFileService;

    public function __construct(
        PostRepository $posts,
        CategoryRepository $categories,
        UserRepository $users,
        AdminUserRepository $adminUsers,
        AuditLogRepository $auditLogs,
        AuthGuard $guard,
        SessionManager $sessions,
        AppConfig $config,
        ?ScoreRepository $scores = null,
        ?MediaService $media = null,
        ?FollowRepository $follows = null,
        ?MessageRepository $messages = null,
        ?TtsService $tts = null,
        ?LibraryRepository $library = null,
        ?CommunityRepository $community = null,
        ?ArticleAssistantService $assistant = null,
        ?StudioService $studio = null,
        ?SeoService $seo = null,
        ?ContactRepository $contactRepo = null,
        ?PostReportRepository $postReports = null,
        ?MailerService $mailer = null,
        ?\InfinityBlog\Reviews\ReviewRepository $reviews = null,
        ?\InfinityBlog\Ebooks\EbookRepository $ebooks = null,
        ?\InfinityBlog\Ebooks\EbookFileService $ebookFileService = null
    ) {
        $this->posts = $posts;
        $this->categories = $categories;
        $this->users = $users;
        $this->adminUsers = $adminUsers;
        $this->auditLogs = $auditLogs;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->config = $config;
        $this->scores = $scores;

        if ($media === null) {
            try {
                $storageConfig = SupabaseStorageConfig::fromEnvironment();
                if ($storageConfig !== null) {
                    $client = new SupabaseStorageClient($storageConfig);
                    $media = new MediaService($client, $storageConfig);
                }
            } catch (Throwable) {
            }
        }
        $this->media = $media;
        $this->follows = $follows;
        $this->messages = $messages;
        $this->tts = $tts;
        $this->library = $library;
        $this->community = $community;
        $this->assistant = $assistant;
        $this->studio = $studio;
        $this->seo = $seo ?? new SeoService($config->baseUrl);
        $this->contactRepo = $contactRepo;
        $this->postReports = $postReports;
        $this->mailer = $mailer ?? new MailerService();
        $this->reviews = $reviews ?? new \InfinityBlog\Reviews\ReviewRepository($this->users->getPdo(), $this->scores);
        $this->ebooks = $ebooks ?? new \InfinityBlog\Ebooks\EbookRepository($this->users->getPdo());
        $this->ebookFileService = $ebookFileService ?? new \InfinityBlog\Ebooks\EbookFileService();
    }

    /** @return array{status: int, type: string, body: string} */
    public function home(Request $request): array
    {
        $searchParam = $request->query('search') ?? ($_GET['search'] ?? null);
        $search = is_string($searchParam) ? trim($searchParam) : null;
        if ($search === '') {
            $search = null;
        }

        $catParam = $request->query('category') ?? ($_GET['category'] ?? null);
        $categorySlug = is_string($catParam) ? trim($catParam) : null;
        if ($categorySlug === '') {
            $categorySlug = null;
        }

        $activeCategories = $this->categories->listActive();
        $activeCatName = null;
        $activeCatId = null;
        if ($categorySlug !== null) {
            foreach ($activeCategories as $cat) {
                if ($cat['slug'] === $categorySlug) {
                    $activeCatName = $cat['name'];
                    $activeCatId = (int) $cat['id'];
                    break;
                }
            }
        }

        $filters = [];
        if ($activeCatId !== null) {
            $filters['category_id'] = $activeCatId;
        }
        if ($search !== null) {
            $filters['q'] = $search;
        }

        $account = $this->guard->currentAccount();

        // Feed Tabs: for-you, following, latest, trending, popular
        $tabParam = $request->query('tab') ?? ($_GET['tab'] ?? null);
        $activeTab = is_string($tabParam) ? strtolower(trim($tabParam)) : 'for-you';
        if ($activeTab === 'popular') {
            $activeTab = 'trending';
        }
        if (!in_array($activeTab, ['for-you', 'following', 'latest', 'trending'], true)) {
            $activeTab = 'for-you';
        }

        $emptyFollowing = false;
        $guestFollowing = false;

        if ($activeTab === 'following') {
            if ($account === null) {
                $guestFollowing = true;
                $postResult = ['posts' => [], 'total' => 0];
            } else {
                $followingList = $this->follows !== null ? $this->follows->getFollowing($account->id) : [];
                $followingIds = array_map(static fn(array $u): int => (int) $u['id'], $followingList);
                if (empty($followingIds)) {
                    $emptyFollowing = true;
                    $postResult = ['posts' => [], 'total' => 0];
                } else {
                    $filters['author_ids'] = $followingIds;
                    $postResult = $this->posts->listPublic($filters, 1, 24);
                }
            }
        } elseif ($activeTab === 'latest') {
            $filters['sort'] = 'newest';
            $postResult = $this->posts->listPublic($filters, 1, 24);
        } elseif ($activeTab === 'trending') {
            $filters['sort'] = 'popular';
            $postResult = $this->posts->listPublic($filters, 1, 24);
        } else {
            // 'for-you' default
            $postResult = $this->posts->listPublic($filters, 1, 24);
        }

        $feedPosts = $postResult['posts'] ?? [];

        // Enrich feed posts with author badge, avatar, bookmark status, user score, and relative time
        if (!empty($feedPosts)) {
            $feedAuthorIds = array_values(array_unique(array_map(static fn(array $p): int => (int) $p['author_id'], $feedPosts)));
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
            $authorBadgeMap = $badgeService->getBadgeStatusesForUsers($feedAuthorIds);

            $authorDetails = [];
            foreach ($feedAuthorIds as $aid) {
                $u = $this->users->findById($aid);
                if ($u !== null) {
                    $authorDetails[$aid] = [
                        'avatar_url' => $this->resolveAvatarUrl($u->avatarPath),
                        'username' => $u->username,
                        'display_name' => $u->displayName,
                    ];
                }
            }

            $bookmarkedPostIds = [];
            if ($account !== null && $this->library !== null) {
                try {
                    $bookmarks = $this->library->getBookmarks($account->id);
                    foreach ($bookmarks as $bm) {
                        $bookmarkedPostIds[(int) $bm['id']] = true;
                    }
                } catch (\Throwable) {}
            }

            $userScores = [];
            if ($account !== null && $this->scores !== null) {
                try {
                    foreach ($feedPosts as $p) {
                        $pid = (int) $p['id'];
                        $score = $this->scores->getUserScore($pid, $account->id);
                        if ($score !== null) {
                            $userScores[$pid] = $score;
                        }
                    }
                } catch (\Throwable) {}
            }

            foreach ($feedPosts as &$fp) {
                $aid = (int) $fp['author_id'];
                $pid = (int) $fp['id'];
                $fp['author_has_badge'] = !empty($authorBadgeMap[$aid]);
                $fp['author_avatar_url'] = $authorDetails[$aid]['avatar_url'] ?? ($fp['author_avatar_url'] ?? null);
                $fp['author_username'] = $authorDetails[$aid]['username'] ?? ($fp['author_username'] ?? 'author');
                $fp['author_handle'] = \InfinityBlog\Support\UserIdentityHelper::formatHandle(
                    $authorDetails[$aid]['username'] ?? ($fp['author_username'] ?? null),
                    $authorDetails[$aid]['display_name'] ?? ($fp['author_name'] ?? null)
                );
                $fp['thumbnail_url'] = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($fp);
                $fp['is_bookmarked'] = !empty($bookmarkedPostIds[$pid]);
                $fp['user_score'] = $userScores[$pid] ?? null;
                $fp['relative_time'] = self::formatRelativeTime($fp['published_at'] ?? $fp['created_at'] ?? null);
            }
            unset($fp);
        }

        // Time-based greeting
        $hour = (int) date('G');
        if ($hour < 12) {
            $timeGreeting = 'morning';
        } elseif ($hour < 17) {
            $timeGreeting = 'afternoon';
        } else {
            $timeGreeting = 'evening';
        }

        $trendingResult = $this->posts->listPublic(['sort' => 'popular'], 1, 3);
        $trendingPosts = $trendingResult['posts'] ?? [];

        // 1. Sidebar: Authors You May Know (up to 5 eligible authors, strictly excluding current signed-in user)
        $sidebarAuthors = [];
        $rawSessionUserId = $this->sessions->currentUserId();

        // Fail-safe: if session contains an authenticated user ID but account cannot be resolved safely, do not return an unfiltered list
        if ($account === null && $rawSessionUserId !== null && $rawSessionUserId > 0) {
            $sidebarAuthors = [];
        } else {
            try {
                if ($this->follows !== null) {
                    $sidebarAuthors = $this->follows->getSuggestedUsers($account?->id, 5);
                } else {
                    $sidebarAuthors = $this->users->getSuggestedUsers($account?->id, 5);
                }

                if (!empty($sidebarAuthors)) {
                    // Strict server-side invariant: Exclude authenticated user by canonical database ID
                    if ($account !== null) {
                        $canonicalViewerId = (int) $account->id;
                        $sidebarAuthors = array_values(array_filter(
                            $sidebarAuthors,
                            static fn(array $a): bool => (int) ($a['id'] ?? 0) !== $canonicalViewerId
                        ));
                    }

                    // Strict server-side invariant: Exclude non-discoverable accounts
                    $sidebarAuthors = array_values(array_filter(
                        $sidebarAuthors,
                        static fn(array $a): bool => (int) ($a['show_in_recommendations'] ?? 1) === 1
                    ));

                    $authorIds = array_map(static fn(array $a): int => (int) $a['id'], $sidebarAuthors);
                    $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
                    $badgeMap = $badgeService->getBadgeStatusesForUsers($authorIds);
                    foreach ($sidebarAuthors as &$author) {
                        $author['has_author_badge'] = !empty($badgeMap[(int) $author['id']]);
                    }
                    unset($author);
                }
            } catch (\Throwable) {
                $sidebarAuthors = [];
            }
        }

        // 2. Sidebar: Popular Communities (up to 5 active communities with member count & membership state)
        $sidebarCommunities = [];
        if ($this->community !== null) {
            try {
                $allRooms = $this->community->getChatRooms();
                foreach ($allRooms as $room) {
                    if (empty($room['is_active'])) {
                        continue;
                    }
                    $room['is_member'] = ($account !== null) ? $this->community->isMember((int) $room['id'], $account->id) : false;
                    $sidebarCommunities[] = $room;
                    if (count($sidebarCommunities) >= 5) {
                        break;
                    }
                }
            } catch (\Throwable) {
            }
        }

        // 3. Trending Topics (Top 5 active categories by post_count DESC)
        $trendingTopics = [];
        try {
            $categoriesWithCounts = $this->categories->listActiveWithCounts();
            usort($categoriesWithCounts, static fn(array $a, array $b): int => $b['post_count'] <=> $a['post_count']);
            $trendingTopics = array_slice($categoriesWithCounts, 0, 5);
        } catch (\Throwable) {
        }

        $featuredCommunities = array_slice($sidebarCommunities, 0, 4);
        $featuredAuthors = array_slice($sidebarAuthors, 0, 4);

        $res = $this->render('home', [
            'pageTitle' => 'Ideas. Stories. Perspectives.',
            'metaDescription' => 'Discover insightful articles on web development, artificial intelligence, software engineering, and creative perspectives on Infinity Blog.',
            'activeNav' => 'home',
            'posts' => $feedPosts,
            'totalPosts' => (int) ($postResult['total'] ?? count($feedPosts)),
            'trendingPosts' => $trendingPosts,
            'featuredCommunities' => $featuredCommunities,
            'featuredAuthors' => $featuredAuthors,
            'sidebarAuthors' => $sidebarAuthors,
            'sidebarCommunities' => $sidebarCommunities,
            'trendingTopics' => $trendingTopics,
            'categories' => $activeCategories,
            'activeCategorySlug' => $categorySlug,
            'activeCategoryName' => $activeCatName,
            'searchQuery' => $search,
            'activeTab' => $activeTab,
            'emptyFollowing' => $emptyFollowing,
            'guestFollowing' => $guestFollowing,
            'timeGreeting' => $timeGreeting,
        ]);
        $res['headers'] = [
            'Cache-Control' => 'no-cache, private, must-revalidate',
            'Pragma' => 'no-cache',
        ];
        return $res;
    }

    /** @return array{status: int, type: string, body: string} */
    public function article(Request $request, int $id): array
    {
        $post = $this->posts->findPublished($id);
        if ($post === null) {
            return $this->notFound('The requested article was not found or is not published.');
        }

        $recentResult = $this->posts->listPublic([], 1, 5);
        $recent = array_filter($recentResult['posts'], fn($p) => (int) $p['id'] !== $id);

        $account = $this->guard->currentAccount();
        $scoresUnavailable = false;
        $scoreSummary = ['average' => 0.0, 'count' => 0];
        $userScore = null;
        if ($this->scores !== null) {
            try {
                $scoreSummary = $this->scores->getSummary($id);
                $userScore = $account !== null ? $this->scores->getUserScore($id, $account->id) : null;
            } catch (Throwable) {
                $scoresUnavailable = true;
            }
        }

        $videoSignedUrl = null;
        if (!empty($post['video_path']) && $this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $videoSignedUrl = $this->media->resolveSignedVideoUrl((string) $post['video_path'], 3600);
            } catch (Throwable) {
            }
        }

        // Batch resolve any inline uploaded videos in the content
        $signedUrlsMap = [];
        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            $contentVideoPaths = ArticleContentRenderer::extractVideoPaths($post['content']);
            foreach ($contentVideoPaths as $vp) {
                try {
                    $signedUrlsMap[$vp] = $this->media->resolveSignedVideoUrl($vp, 3600);
                } catch (Throwable) {
                }
            }
        }

        $renderer = new ArticleContentRenderer($signedUrlsMap);
        $renderedBody = $renderer->render($post['content']);

        $plainSnippet = trim((string) preg_replace('/\s+/', ' ', strip_tags($renderedBody)));
        $metaDescription = !empty($post['summary'])
            ? $post['summary']
            : (mb_substr($plainSnippet, 0, 160) . (mb_strlen($plainSnippet) > 160 ? '...' : ''));

        $articleThumbnail = ArticleImageHelper::resolveThumbnail($post);
        $canonicalPath = '/article/' . $id;

        $canonicalUrl = $this->config->baseUrl . $canonicalPath;
        $speechData = $this->tts !== null ? $this->tts->getArticleSpeechData($post) : [
            'has_server_audio' => false,
            'audio_url' => null,
            'audio_status' => 'unconfigured',
            'full_text' => \InfinityBlog\Audio\TextCleaner::cleanForSpeech($post['title'], $post['content']),
            'summary_text' => !empty($post['summary']) ? \InfinityBlog\Audio\TextCleaner::cleanForSpeech($post['title'], (string) $post['summary']) : null,
            'word_count' => str_word_count(strip_tags($post['content'])),
            'duration_seconds_est' => max(10, (int) ceil((str_word_count(strip_tags($post['content'])) / 140) * 60)),
        ];

        if ($this->studio !== null) {
            $this->studio->incrementViews($id);
        }

        $isBookmarked = false;
        if ($account !== null && $this->library !== null) {
            try {
                $isBookmarked = $this->library->isBookmarked($account->id, $id);
            } catch (Throwable) {
                $isBookmarked = false;
            }
        }

        $comments = [];
        if ($this->community !== null) {
            try {
                $comments = $this->community->getPostComments($id, $account?->id);
            } catch (Throwable) {
                $comments = [];
            }
        }

        $authorId = (int) ($post['author_id'] ?? 0);
        $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
        $authorHasBadge = $authorId > 0 && $badgeService->qualifiesForAuthorBadge($authorId);
        $post['author_has_badge'] = $authorHasBadge;
        if ($authorId > 0 && (empty($post['author_avatar_url']) || empty($post['author_handle']))) {
            $authorObj = $this->users->findById($authorId);
            if ($authorObj !== null) {
                $post['author_avatar_url'] = $post['author_avatar_url'] ?? \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($authorObj->avatarPath);
                $post['author_handle'] = $post['author_handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($authorObj->username, $authorObj->displayName);
            }
        }

        $reviewsUnavailable = false;
        $reviewsData = ['reviews' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 0];
        $userReview = null;
        if ($this->reviews !== null) {
            try {
                $reviewsData = $this->reviews->getReviewsForPost($id, $account?->id, 1, 10);
                $userReview = $account !== null ? $this->reviews->getUserReview($id, $account->id) : null;
            } catch (Throwable) {
                $reviewsUnavailable = true;
            }
        }

        return $this->render('article', [
            'pageTitle' => $post['title'],
            'metaDescription' => $metaDescription,
            'canonicalPath' => $canonicalPath,
            'canonicalUrl' => $canonicalUrl,
            'articleUrl' => $canonicalUrl,
            'authorHasBadge' => $authorHasBadge,
            'ogImage' => $articleThumbnail,
            'ogType' => 'article',
            'activeNav' => 'home',
            'post' => $post,
            'renderedBody' => $renderedBody,
            'videoSignedUrl' => $videoSignedUrl,
            'recentPosts' => array_values(array_slice($recent, 0, 4)),
            'scoreSummary' => $scoreSummary,
            'userScore' => $userScore,
            'scoresUnavailable' => $scoresUnavailable,
            'reviews' => $reviewsData['reviews'],
            'reviewsTotal' => $reviewsData['total'],
            'reviewsPage' => $reviewsData['page'],
            'reviewsTotalPages' => $reviewsData['total_pages'],
            'reviewsUnavailable' => $reviewsUnavailable,
            'userReview' => $userReview,
            'speechData' => $speechData,
            'isBookmarked' => $isBookmarked,
            'comments' => $comments,
            'readingTime' => $post['reading_time'] ?? max(1, (int) ceil(str_word_count(strip_tags($post['content'])) / 200)),
            'currentUser' => $account !== null ? [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'role' => $account->role,
            ] : null,
        ]);
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function downloadPdf(Request $request, int $id): array
    {
        $post = $this->posts->findPublished($id);
        if ($post === null) {
            return $this->notFound('Article not found or not published.');
        }

        $canonicalUrl = $this->config->baseUrl . '/article/' . $id;
        $pdfBinary = PdfExporter::generate($post, $canonicalUrl);
        $filename = 'infinity-blog-' . $this->slugify($post['title']) . '.pdf';

        return [
            'status' => 200,
            'type' => 'application/pdf',
            'body' => $pdfBinary,
            'headers' => [
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($pdfBinary),
                'Cache-Control' => 'no-cache, must-revalidate',
            ],
        ];
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function downloadEpub(Request $request, int $id): array
    {
        $post = $this->posts->findPublished($id);
        if ($post === null) {
            return $this->notFound('Article not found or not published.');
        }

        $canonicalUrl = $this->config->baseUrl . '/article/' . $id;
        try {
            $epubBinary = EpubExporter::generate($post, $canonicalUrl);
        } catch (Throwable $e) {
            return $this->notFound('EPUB generation is unavailable: ' . $e->getMessage());
        }
        $filename = 'infinity-blog-' . $this->slugify($post['title']) . '.epub';

        return [
            'status' => 200,
            'type' => 'application/epub+zip',
            'body' => $epubBinary,
            'headers' => [
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($epubBinary),
                'Cache-Control' => 'no-cache, must-revalidate',
            ],
        ];
    }

    /** @return array{status: int, type: string, body: string} */
    public function articleSpeechData(Request $request, int $id): array
    {
        $post = $this->posts->findPublished($id);
        if ($post === null) {
            return [
                'status' => 404,
                'type' => 'application/json',
                'body' => json_encode(['error' => ['code' => 'NOT_FOUND', 'message' => 'Article not found.']]),
            ];
        }

        $speechData = $this->tts !== null
            ? $this->tts->getArticleSpeechData($post)
            : [
                'has_server_audio' => false,
                'audio_url' => null,
                'audio_status' => 'unconfigured',
                'full_text' => \InfinityBlog\Audio\TextCleaner::cleanForSpeech($post['title'], $post['content']),
                'summary_text' => !empty($post['summary']) ? \InfinityBlog\Audio\TextCleaner::cleanForSpeech($post['title'], (string) $post['summary']) : null,
                'word_count' => str_word_count(strip_tags($post['content'])),
                'duration_seconds_est' => max(10, (int) ceil((str_word_count(strip_tags($post['content'])) / 140) * 60)),
            ];

        return [
            'status' => 200,
            'type' => 'application/json',
            'body' => json_encode(['data' => $speechData]),
        ];
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function messages(Request $request, ?int $activeUserId = null): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode($request->path));
        }

        // Prevent self-messaging
        if ($activeUserId !== null && $activeUserId === $account->id) {
            return $this->redirect('/messages');
        }

        $conversations = $this->messages !== null ? $this->messages->listConversations($account->id) : [];
        $activeUser = null;
        $threadMessages = [];

        if ($activeUserId !== null) {
            $userAcc = $this->users->findById($activeUserId);
            if ($userAcc !== null) {
                $activeUser = [
                    'id' => $userAcc->id,
                    'username' => $userAcc->username,
                    'display_name' => $userAcc->displayName,
                    'avatar_path' => $userAcc->avatarPath,
                    'avatar_url' => $this->resolveAvatarUrl($userAcc->avatarPath),
                ];
                if ($this->messages !== null) {
                    $threadMessages = $this->messages->getThread($account->id, $activeUserId, 100);
                    $this->messages->markThreadAsRead($account->id, $activeUserId);
                }
            }
        } elseif (!empty($conversations)) {
            $firstContactId = (int) $conversations[0]['other_user']['id'];
            $userAcc = $this->users->findById($firstContactId);
            if ($userAcc !== null) {
                $activeUser = [
                    'id' => $userAcc->id,
                    'username' => $userAcc->username,
                    'display_name' => $userAcc->displayName,
                    'avatar_path' => $userAcc->avatarPath,
                    'avatar_url' => $this->resolveAvatarUrl($userAcc->avatarPath),
                ];
                if ($this->messages !== null) {
                    $threadMessages = $this->messages->getThread($account->id, $firstContactId, 100);
                    $this->messages->markThreadAsRead($account->id, $firstContactId);
                }
            }
        }

        $allAuthors = array_values(array_filter(
            $this->users->listPublicAuthors(),
            static fn(array $author): bool => (int) ($author['id'] ?? 0) !== (int) $account->id
        ));

        $res = $this->render('messages', [
            'pageTitle' => 'Direct Messages',
            'metaDescription' => 'Private user-to-user messaging and conversations on Infinity Blog.',
            'activeNav' => 'messages',
            'conversations' => $conversations,
            'activeUser' => $activeUser,
            'threadMessages' => $threadMessages,
            'allAuthors' => $allAuthors,
            'currentUserId' => $account->id,
        ]);
        $res['headers'] = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];
        return $res;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim((string) $text, '-') ?: 'article';
    }

    /** @return array{status: int, type: string, body: string} */
    public function login(Request $request): array
    {
        $redirectParam = $_GET['redirect'] ?? null;
        $dest = (is_string($redirectParam) && str_starts_with($redirectParam, '/') && !str_starts_with($redirectParam, '//'))
            ? $redirectParam
            : '/dashboard';

        $account = $this->guard->currentAccount();
        if ($account !== null) {
            return $this->redirect($dest);
        }

        return $this->render('login', [
            'pageTitle' => 'Sign In',
            'activeNav' => 'login',
            'redirectUrl' => is_string($redirectParam) ? $redirectParam : null,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function register(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account !== null) {
            return $this->redirect('/dashboard');
        }

        return $this->render('register', [
            'pageTitle' => 'Create Account',
            'activeNav' => 'register',
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function publicCategories(Request $request): array
    {
        $categories = $this->categories->listActiveWithCounts();

        return $this->render('public_categories', [
            'pageTitle' => 'Article Categories',
            'metaDescription' => 'Browse all topics and disciplines across web development, AI, software engineering, business, and creative perspectives.',
            'activeNav' => 'categories',
            'categories' => $categories,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function publicAuthors(Request $request): array
    {
        $search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
        if ($search === '' && isset($_GET['q']) && is_string($_GET['q'])) {
            $search = trim($_GET['q']);
        }
        $authors = $this->users->listPublicAuthors($search !== '' ? $search : null);
        $account = $this->guard->currentAccount();
        $authors = array_map(function (array $a) use ($account): array {
            $a['avatar_url'] = $this->resolveAvatarUrl($a['avatar_path'] ?? null);
            $a['banner_url'] = $this->resolveBannerUrl($a['banner_path'] ?? null);
            $a['follower_count'] = $this->follows !== null ? $this->follows->getFollowerCount((int) $a['id']) : 0;
            $a['is_following'] = ($this->follows !== null && $account !== null) ? $this->follows->isFollowing($account->id, (int) $a['id']) : false;
            $a['is_self'] = $account !== null && $account->id === (int) $a['id'];
            return $a;
        }, $authors);

        if (!empty($authors)) {
            $authorIds = array_map(static fn(array $a): int => (int) $a['id'], $authors);
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
            $badgeMap = $badgeService->getBadgeStatusesForUsers($authorIds);
            foreach ($authors as &$author) {
                $author['has_author_badge'] = !empty($badgeMap[(int) $author['id']]);
            }
            unset($author);
        }

        return $this->render('public_authors', [
            'pageTitle' => 'Meet Our Authors',
            'metaDescription' => 'Discover the talented writers, engineers, and researchers publishing stories and tutorials on Infinity Blog.',
            'activeNav' => 'authors',
            'authors' => $authors,
            'searchQuery' => $search,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function publicCommunities(Request $request): array
    {
        $rooms = $this->community !== null ? $this->community->getChatRooms() : [];
        if (empty($rooms) && $this->community !== null) {
            $adminId = $this->users->findFirstAdminId();
            if ($adminId !== null) {
                try {
                    $this->community->createChatRoom('Web Development', 'web-development', 'Discussions about frontend, backend, APIs, and modern architecture.', null, $adminId);
                    $this->community->createChatRoom('Artificial Intelligence', 'artificial-intelligence', 'Exchange thoughts on LLMs, neural networks, ethics, and AI workflows.', null, $adminId);
                    $this->community->createChatRoom('General Lounge', 'general', 'Casual hangouts, writing inspiration, and community introductions.', null, $adminId);
                    $rooms = $this->community->getChatRooms();
                } catch (\Throwable) {
                    $rooms = [];
                }
            }
        }

        $activeRooms = array_values(array_filter($rooms, fn($r) => !empty($r['is_active'])));

        $search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
        if ($search === '' && isset($_GET['q']) && is_string($_GET['q'])) {
            $search = trim($_GET['q']);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $activeRooms = array_values(array_filter($activeRooms, function (array $r) use ($needle): bool {
                $name = mb_strtolower((string) ($r['name'] ?? ''));
                $desc = mb_strtolower((string) ($r['description'] ?? ''));
                $cat = mb_strtolower((string) ($r['category_name'] ?? ''));
                return str_contains($name, $needle) || str_contains($desc, $needle) || str_contains($cat, $needle);
            }));
        }

        $account = $this->guard->currentAccount();
        $enrichedRooms = [];
        foreach ($activeRooms as $room) {
            $roomId = (int) $room['id'];
            $isMember = false;
            if ($account !== null && $this->community !== null) {
                $isMember = $this->community->isMember($roomId, $account->id);
            }
            $memberCount = isset($room['member_count']) ? (int) $room['member_count'] : ($this->community !== null ? $this->community->getRoomMemberCount($roomId) : 0);
            $room['is_member'] = $isMember;
            $room['member_count'] = $memberCount;
            $enrichedRooms[] = $room;
        }

        return $this->render('public_communities', [
            'pageTitle' => 'Discover Communities',
            'metaDescription' => 'Explore topic-based community channels, connect with creators, and join live text discussions across Infinity Blog.',
            'activeNav' => 'communities',
            'communities' => $enrichedRooms,
            'searchQuery' => $search,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function joinCommunityWeb(Request $request, string $slug): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode('/community/' . $slug));
        }

        $room = $this->community?->getChatRoomBySlug(trim($slug));
        if ($room !== null && !empty($room['is_active'])) {
            try {
                $this->community->joinRoom((int) $room['id'], $account->id);
            } catch (\Throwable) {
            }
        }

        return $this->redirect('/community/' . urlencode(trim($slug)));
    }

    /** @return array{status: int, type: string, body: string} */
    public function communityDetail(Request $request, string $slug): array
    {
        $cleanSlug = trim($slug);
        $room = $this->community?->getChatRoomBySlug($cleanSlug);
        if ($room === null || empty($room['is_active'])) {
            return $this->notFound('The requested community could not be found.');
        }

        $account = $this->guard->currentAccount();
        $roomId = (int) $room['id'];
        $isMember = false;
        if ($account !== null && $this->community !== null) {
            $isMember = $this->community->isMember($roomId, $account->id);
        }

        $memberCount = $this->community !== null ? $this->community->getRoomMemberCount($roomId) : 0;

        // Fetch posts for this community (by category_id if associated)
        $posts = [];
        $totalPosts = 0;
        $categoryId = !empty($room['category_id']) ? (int) $room['category_id'] : null;

        if ($categoryId !== null) {
            $postsResult = $this->posts->listPublic(['category_id' => $categoryId], 1, 20);
            $posts = $postsResult['posts'] ?? [];
            $totalPosts = (int) ($postsResult['total'] ?? count($posts));
        }

        // Enrich posts with comments, scores, authors
        if (!empty($posts)) {
            $authorIds = array_values(array_unique(array_map(fn($p) => (int) $p['author_id'], $posts)));
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
            $authorBadgeMap = $badgeService->getBadgeStatusesForUsers($authorIds);

            $authorDetails = [];
            foreach ($authorIds as $aid) {
                $u = $this->users->findById($aid);
                if ($u !== null) {
                    $authorDetails[$aid] = [
                        'username' => $u->username,
                        'display_name' => $u->displayName,
                        'avatar_url' => !empty($u->avatarPath) ? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($u->avatarPath) : null,
                    ];
                }
            }

            $userScores = [];
            if ($account !== null && $this->scores !== null) {
                try {
                    foreach ($posts as $p) {
                        $pid = (int) $p['id'];
                        $score = $this->scores->getUserScore($pid, $account->id);
                        if ($score !== null) {
                            $userScores[$pid] = $score;
                        }
                    }
                } catch (\Throwable) {}
            }

            foreach ($posts as &$fp) {
                $aid = (int) $fp['author_id'];
                $pid = (int) $fp['id'];
                $fp['author_has_badge'] = !empty($authorBadgeMap[$aid]);
                $fp['author_avatar_url'] = $authorDetails[$aid]['avatar_url'] ?? ($fp['author_avatar_url'] ?? null);
                $fp['author_username'] = $authorDetails[$aid]['username'] ?? ($fp['author_username'] ?? 'author');
                $fp['author_handle'] = \InfinityBlog\Support\UserIdentityHelper::formatHandle(
                    $authorDetails[$aid]['username'] ?? ($fp['author_username'] ?? null),
                    $authorDetails[$aid]['display_name'] ?? ($fp['author_name'] ?? null)
                );
                $fp['thumbnail_url'] = \InfinityBlog\Posts\ArticleImageHelper::resolveThumbnail($fp);
                $fp['user_score'] = $userScores[$pid] ?? null;
                $fp['relative_time'] = self::formatRelativeTime($fp['published_at'] ?? $fp['created_at'] ?? null);
                $fp['comment_count'] = $this->community !== null ? $this->community->getCommentCount($pid) : 0;
                $fp['reader_score_count'] = $this->scores !== null ? (int) ($this->scores->getSummary($pid)['count'] ?? 0) : 0;

                // Fetch top 3 latest published comments for discussion preview
                $fp['recent_comments'] = [];
                if ($this->community !== null && $fp['comment_count'] > 0) {
                    try {
                        $cList = $this->community->getPostComments($pid, $account?->id);
                        $fp['recent_comments'] = array_slice($cList, 0, 3);
                    } catch (\Throwable) {}
                }
            }
            unset($fp);
        }

        // Live chat message count for this room
        $chatMessageCount = 0;
        try {
            $cStmt = $this->users->getPdo()->prepare('SELECT COUNT(*) FROM chat_room_messages WHERE room_id = :rid');
            $cStmt->execute([':rid' => $roomId]);
            $chatMessageCount = (int) $cStmt->fetchColumn();
        } catch (\Throwable) {}

        $cleanName = \InfinityBlog\Community\CommunityNameFormatter::format($room['name'] ?? '');

        $res = $this->render('community_detail', [
            'pageTitle' => "{$cleanName} — Community Discussions & Feed",
            'metaDescription' => !empty($room['description']) ? $room['description'] : "Explore {$cleanName} community posts, discussions, and conversations on Infinity Blog.",
            'activeNav' => 'communities',
            'community' => $room,
            'isMember' => $isMember,
            'memberCount' => $memberCount,
            'posts' => $posts,
            'totalPosts' => $totalPosts,
            'chatMessageCount' => $chatMessageCount,
            'currentUser' => $account !== null ? [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'avatar_path' => $account->avatarPath,
                'avatar_url' => \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($account->avatarPath),
            ] : null,
        ]);
        $res['headers'] = [
            'Cache-Control' => 'no-cache, private, must-revalidate',
            'Pragma' => 'no-cache',
        ];
        return $res;
    }

    /** @return array{status: int, type: string, body: string} */
    public function authorProfile(Request $request, int|string $idOrUsername): array
    {
        $id = is_numeric($idOrUsername) ? (int) $idOrUsername : null;
        if ($id === null) {
            $userByUsername = $this->users->findByIdentifier((string) $idOrUsername);
            if ($userByUsername !== null) {
                $id = $userByUsername->id;
            }
        }
        if ($id === null) {
            return $this->notFound('The requested author was not found.');
        }

        $author = $this->users->findPublicAuthor($id);
        if ($author === null) {
            $user = $this->users->findById($id);
            if ($user !== null && $user->role !== 'admin' && $user->status === 'active' && $user->profileVisibility !== 'private') {
                $author = array_merge($user->toPublicArray(), [
                    'post_count' => 0,
                ]);
            } else {
                return $this->notFound('The requested author was not found.');
            }
        }

        $author['avatar_url'] = $this->resolveAvatarUrl($author['avatar_path'] ?? null);
        $author['banner_url'] = $this->resolveBannerUrl($author['banner_path'] ?? null);
        $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
        $author['has_author_badge'] = $badgeService->qualifiesForAuthorBadge($id);

        $postsResult = $this->posts->listPublic(['author_id' => $id], 1, 20);

        $followerCount = $this->follows !== null ? $this->follows->getFollowerCount($id) : 0;
        $followingCount = $this->follows !== null ? $this->follows->getFollowingCount($id) : 0;
        $account = $this->guard->currentAccount();
        $isFollowing = ($this->follows !== null && $account !== null) ? $this->follows->isFollowing($account->id, $id) : false;
        $followers = $this->follows !== null ? $this->follows->getFollowers($id, 20) : [];
        $following = $this->follows !== null ? $this->follows->getFollowing($id, 20) : [];

        return $this->render('author_profile', [
            'pageTitle' => $author['display_name'] . ' — Author Profile',
            'metaDescription' => 'Read published articles, insights, and background by ' . $author['display_name'] . ' on Infinity Blog.',
            'activeNav' => 'authors',
            'author' => $author,
            'posts' => $postsResult['posts'],
            'totalPosts' => $postsResult['total'],
            'followerCount' => $followerCount,
            'followingCount' => $followingCount,
            'isFollowing' => $isFollowing,
            'followers' => $followers,
            'following' => $following,
            'isSelf' => $account !== null && $account->id === $id,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function explore(Request $request): array
    {
        $q = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
        if ($q === '' && isset($_GET['q']) && is_string($_GET['q'])) {
            $q = trim($_GET['q']);
        }
        $categorySlug = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
        $authorId = isset($_GET['author']) && is_numeric($_GET['author']) ? (int) $_GET['author'] : null;
        $dateFrom = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date_from']) ? (string) $_GET['date_from'] : null;
        $dateTo = isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['date_to']) ? (string) $_GET['date_to'] : null;
        $readingTime = isset($_GET['reading_time']) && in_array($_GET['reading_time'], ['quick', 'medium', 'deep'], true) ? (string) $_GET['reading_time'] : null;
        $sort = isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'oldest', 'popular', 'highest_rated', 'relevance'], true) ? (string) $_GET['sort'] : 'newest';
        $account = $this->guard->currentAccount();
        $forYou = (isset($_GET['for_you']) && $_GET['for_you'] === '1' && $account !== null);

        $filters = [];
        if ($forYou) {
            $filters['exclude_muted_for_user_id'] = $account->id;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $activeCategory = null;
        if ($categorySlug !== '') {
            $activeCategory = $this->categories->findBySlug($categorySlug);
            if ($activeCategory !== null) {
                $filters['category_id'] = $activeCategory['id'];
            }
        }

        $activeAuthor = null;
        if ($authorId !== null) {
            $userAcc = $this->users->findById($authorId);
            if ($userAcc !== null) {
                $filters['author_id'] = $authorId;
                $activeAuthor = [
                    'id' => $userAcc->id,
                    'name' => $userAcc->displayName,
                ];
            }
        }

        if ($dateFrom !== null) {
            $filters['date_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $filters['date_to'] = $dateTo;
        }
        if ($readingTime !== null) {
            $filters['reading_time'] = $readingTime;
        }
        $filters['sort'] = $sort;

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = 12;
        $postsResult = $this->posts->listPublic($filters, $page, $perPage);
        $categories = $this->categories->listActiveWithCounts();
        $authors = $this->users->listPublicAuthors();

        // Build active filter chips
        $activeFilterChips = [];
        $currentQuery = $_GET;

        if ($q !== '') {
            $chipQuery = $currentQuery;
            unset($chipQuery['search'], $chipQuery['q'], $chipQuery['page']);
            $activeFilterChips[] = [
                'type' => 'search',
                'label' => 'Search: "' . $q . '"',
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($activeCategory !== null) {
            $chipQuery = $currentQuery;
            unset($chipQuery['category'], $chipQuery['page']);
            $activeFilterChips[] = [
                'type' => 'category',
                'label' => 'Category: ' . $activeCategory['name'],
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($activeAuthor !== null) {
            $chipQuery = $currentQuery;
            unset($chipQuery['author'], $chipQuery['page']);
            $activeFilterChips[] = [
                'type' => 'author',
                'label' => 'Author: ' . $activeAuthor['name'],
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($dateFrom !== null) {
            $chipQuery = $currentQuery;
            unset($chipQuery['date_from'], $chipQuery['page']);
            $activeFilterChips[] = [
                'type' => 'date_from',
                'label' => 'From: ' . $dateFrom,
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($dateTo !== null) {
            $chipQuery = $currentQuery;
            unset($chipQuery['date_to'], $chipQuery['page']);
            $activeFilterChips[] = [
                'type' => 'date_to',
                'label' => 'To: ' . $dateTo,
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($readingTime !== null) {
            $chipQuery = $currentQuery;
            unset($chipQuery['reading_time'], $chipQuery['page']);
            $labels = ['quick' => 'Quick (< 4 min)', 'medium' => 'Medium (4-8 min)', 'deep' => 'Deep (> 8 min)'];
            $activeFilterChips[] = [
                'type' => 'reading_time',
                'label' => 'Read Time: ' . ($labels[$readingTime] ?? $readingTime),
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($sort !== 'newest') {
            $chipQuery = $currentQuery;
            unset($chipQuery['sort'], $chipQuery['page']);
            $sortLabels = ['oldest' => 'Oldest First', 'popular' => 'Most Popular', 'highest_rated' => 'Highest Rated', 'relevance' => 'Most Relevant'];
            $activeFilterChips[] = [
                'type' => 'sort',
                'label' => 'Sort: ' . ($sortLabels[$sort] ?? $sort),
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        if ($forYou) {
            $chipQuery = $currentQuery;
            unset($chipQuery['for_you'], $chipQuery['page']);
            $activeFilterChips[] = [
                'type' => 'for_you',
                'label' => '✨ For You (Muted topics excluded)',
                'remove_url' => '/explore' . (!empty($chipQuery) ? '?' . http_build_query($chipQuery) : ''),
            ];
        }

        $totalPages = max(1, (int) ceil($postsResult['total'] / $perPage));

        return $this->render('explore', [
            'pageTitle' => 'Explore Articles',
            'metaDescription' => 'Search, filter, and discover insightful articles across all topics on Infinity Blog.',
            'activeNav' => 'explore',
            'posts' => $postsResult['posts'],
            'totalPosts' => $postsResult['total'],
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'categories' => $categories,
            'authors' => $authors,
            'activeCategorySlug' => $categorySlug,
            'activeCategoryName' => $activeCategory['name'] ?? null,
            'activeAuthorId' => $authorId,
            'activeAuthorName' => $activeAuthor['name'] ?? null,
            'activeDateFrom' => $dateFrom,
            'activeDateTo' => $dateTo,
            'activeReadingTime' => $readingTime,
            'activeSort' => $sort,
            'searchQuery' => $q,
            'activeFilterChips' => $activeFilterChips,
            'currentQueryParams' => $currentQuery,
            'forYou' => $forYou,
            'currentUser' => $account !== null ? [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'role' => $account->role,
            ] : null,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function about(Request $request): array
    {
        return $this->render('about', [
            'pageTitle' => 'About Infinity Blog',
            'metaDescription' => 'Learn about the mission, architecture, and technology behind Infinity Blog — a modern, glassmorphism CMS built with PHP, PDO and MySQL.',
            'canonicalPath' => '/about',
            'activeNav' => 'about',
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function dashboard(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login');
        }

        $counts = [];
        $recentPosts = [];
        $adminCounts = [];

        if ($account->role === 'admin') {
            $postResult = $this->posts->listForAdmin([], 1, 6);
            $recentPosts = $postResult['posts'];
            $counts = $postResult['dashboard_counts'];
            $userList = $this->adminUsers->listAuthors(1, 1);
            $adminCounts['users_total'] = $userList['total'];
        } else {
            $postResult = $this->posts->listForAuthor($account->id, [], 1, 6);
            $recentPosts = $postResult['posts'];
            $counts = $postResult['dashboard_counts'];
        }

        $analytics = $this->studio !== null ? $this->studio->getAuthorAnalytics($account->id) : null;

        return $this->render('dashboard', [
            'pageTitle' => 'Author Dashboard',
            'metaDescription' => 'Track your publishing stats, recent drafts, reader engagement, and manage your articles on Infinity Blog.',
            'activeNav' => 'dashboard',
            'counts' => $counts,
            'recentPosts' => $recentPosts,
            'adminCounts' => $adminCounts,
            'analytics' => $analytics,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function newPost(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            $currentUri = $_SERVER['REQUEST_URI'] ?? '/posts/new';
            return $this->redirect('/login?redirect=' . urlencode($currentUri));
        }

        $activeCategories = $this->categories->listActive();

        $remixId = isset($_GET['remix']) ? (int) $_GET['remix'] : null;
        $remixPost = null;
        $initialPost = [];

        if ($remixId !== null && $remixId > 0) {
            $source = $this->posts->findPublished($remixId);
            if ($source !== null) {
                $remixPost = $source;
                $initialPost = [
                    'title' => 'Remix: ' . $source['title'],
                    'content' => $source['content'],
                    'category_id' => $source['category_id'],
                    'remixed_from_post_id' => $source['id'],
                    'remixed_from_title' => $source['title'],
                    'remixed_from_author_name' => $source['author_name'],
                    'remixed_from_author_id' => $source['author_id'],
                ];
            }
        }

        $studioService = new \InfinityBlog\Posts\StudioDataService($this->users->getPdo());
        $studioData = $studioService->getStudioData($account->id, 'new');
        $authorPostsRes = $this->posts->listForAuthor($account->id, [], 1, 15);

        return $this->render('post_editor', [
            'pageTitle' => $remixPost !== null ? 'Remix: ' . $remixPost['title'] : 'Writing Studio — Create New Post',
            'metaDescription' => 'Professional Writing Studio for composing, formatting, and publishing articles on Infinity Blog.',
            'activeNav' => 'posts_studio',
            'isStudioPage' => true,
            'post' => $initialPost,
            'remixSource' => $remixPost,
            'categories' => $activeCategories,
            'studioData' => $studioData,
            'authorPosts' => $authorPostsRes['posts'] ?? [],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function editPost(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login');
        }

        $post = $account->role === 'admin'
            ? $this->posts->findForAdmin($id)
            : $this->posts->findForAuthor($id, $account->id);

        if ($post === null) {
            return $this->notFound('Post not found or you are not authorized to edit it.');
        }

        $activeCategories = $this->categories->listActive();
        $studioService = new \InfinityBlog\Posts\StudioDataService($this->users->getPdo());
        $studioData = $studioService->getStudioData($account->id, (string) $id, (int) $id);
        $authorPostsRes = $this->posts->listForAuthor($account->id, [], 1, 15);

        return $this->render('post_editor', [
            'pageTitle' => 'Writing Studio — ' . $post['title'],
            'metaDescription' => 'Update article content, category, cover artwork, and publication status in Writing Studio.',
            'activeNav' => 'posts_studio',
            'isStudioPage' => true,
            'post' => $post,
            'categories' => $activeCategories,
            'studioData' => $studioData,
            'authorPosts' => $authorPostsRes['posts'] ?? [],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function managePosts(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login');
        }

        $status = isset($_GET['status']) && in_array($_GET['status'], ['draft', 'published'], true)
            ? $_GET['status']
            : null;

        $search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : null;
        if ($search === '') {
            $search = null;
        }

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $filters = [];
        if ($status !== null) {
            $filters['status'] = $status;
        }
        if ($search !== null) {
            $filters['q'] = $search;
        }

        if ($account->role === 'admin') {
            $result = $this->posts->listForAdmin($filters, $page, 15);
        } else {
            $result = $this->posts->listForAuthor($account->id, $filters, $page, 15);
        }

        return $this->render('manage_posts', [
            'pageTitle' => 'Manage Articles',
            'metaDescription' => 'Review, filter, publish, and manage all your written articles on Infinity Blog.',
            'activeNav' => 'posts',
            'posts' => $result['posts'],
            'counts' => $result['dashboard_counts'],
            'currentStatusFilter' => $status,
            'searchQuery' => $search,
            'currentPage' => $page,
            'totalPages' => (int) ceil(($result['total'] ?? 0) / 15),
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminCategories(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $allCategories = $this->categories->listAdmin();

        return $this->render('admin_categories', [
            'pageTitle' => 'Manage Categories',
            'metaDescription' => 'Administer article categories, update slugs, and toggle category visibility.',
            'activeNav' => 'admin_categories',
            'categories' => $allCategories,
            'iconAllowlist' => \InfinityBlog\Categories\CategoryIconHelper::getAllowlist(),
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminUsers(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $usersList = $this->adminUsers->listAuthors(1, 50);

        return $this->render('admin_users', [
            'pageTitle' => 'Author Accounts',
            'metaDescription' => 'Manage author accounts, permissions, and administrative access on Infinity Blog.',
            'activeNav' => 'users',
            'users' => $usersList['users'],
            'totalUsers' => $usersList['total'],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminAuditLogs(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $logsList = $this->auditLogs->listEvents([], 1, 50);

        return $this->render('admin_audit', [
            'pageTitle' => 'Audit Logs',
            'metaDescription' => 'Review system administrative events, security changes, and content moderation records.',
            'activeNav' => 'audit',
            'logs' => $logsList['events'],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminContactMessages(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login?redirect=' . urlencode('/admin/contact-messages-page'));
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $messages = $this->contactRepo !== null ? $this->contactRepo->listMessages($limit, $offset) : [];
        $total = $this->contactRepo !== null ? $this->contactRepo->countMessages() : 0;

        return $this->render('admin_contact_messages', [
            'pageTitle' => 'Contact Inquiries Inbox',
            'metaDescription' => 'Review reader feedback, inquiries, and copyright notices.',
            'activeNav' => 'contact_messages',
            'messages' => $messages,
            'total' => $total,
            'currentPage' => $page,
            'totalPages' => max(1, (int) ceil($total / $limit)),
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function updateContactMessageStatus(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->sessions->validateCsrfToken(is_string($token) ? $token : null)) {
            return $this->redirect('/admin/contact-messages-page');
        }

        $status = trim((string) ($_POST['status'] ?? 'resolved'));
        if ($this->contactRepo !== null) {
            $this->contactRepo->updateStatus($id, $status);
        }

        return $this->redirect('/admin/contact-messages-page');
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminReportedPosts(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login?redirect=' . urlencode('/admin/reported-posts-page'));
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : null;
        $reason = isset($_GET['reason']) && $_GET['reason'] !== '' ? (string) $_GET['reason'] : null;
        $search = isset($_GET['search']) && trim((string) $_GET['search']) !== '' ? trim((string) $_GET['search']) : null;
        $viewMode = ($_GET['view'] ?? 'grouped') === 'list' ? 'list' : 'grouped';

        $filters = [];
        if ($status !== null) $filters['status'] = $status;
        if ($reason !== null) $filters['reason'] = $reason;
        if ($search !== null) $filters['search'] = $search;

        $reportsData = [];
        $groupedData = [];
        $stats = ['pending' => 0, 'under_review' => 0, 'dismissed' => 0, 'action_taken' => 0, 'total' => 0];

        if ($this->postReports !== null) {
            $stats = $this->postReports->getModerationStats();
            if ($viewMode === 'list') {
                $reportsData = $this->postReports->listReports($filters, $page, 20);
            } else {
                $groupedData = $this->postReports->getReportsGroupedByPost($filters, $page, 15);
            }
        }

        return $this->render('admin_reported_posts', [
            'pageTitle' => 'Reported Posts Moderation',
            'metaDescription' => 'Review user reports, take moderation action, and enforce community content policies.',
            'activeNav' => 'reported_posts',
            'viewMode' => $viewMode,
            'statusFilter' => $status ?? '',
            'reasonFilter' => $reason ?? '',
            'searchQuery' => $search ?? '',
            'stats' => $stats,
            'groupedPosts' => $groupedData['posts'] ?? [],
            'reportsList' => $reportsData['reports'] ?? [],
            'total' => $viewMode === 'list' ? ($reportsData['total'] ?? 0) : ($groupedData['total'] ?? 0),
            'currentPage' => $page,
            'totalPages' => $viewMode === 'list' ? ($reportsData['total_pages'] ?? 1) : ($groupedData['total_pages'] ?? 1),
            'allowedReasons' => PostReportRepository::ALLOWED_REASONS,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminUpdateReportStatus(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->sessions->validateCsrfToken(is_string($token) ? $token : null)) {
            return $this->redirect('/admin/reported-posts-page');
        }

        $status = trim((string) ($_POST['status'] ?? 'under_review'));
        $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;

        if ($this->postReports !== null) {
            $this->postReports->updateReportStatus($id, $status, $account->id, $notes);
        }

        return $this->redirect('/admin/reported-posts-page');
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminUnpublishReportedPost(Request $request, int $postId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->sessions->validateCsrfToken(is_string($token) ? $token : null)) {
            return $this->redirect('/admin/reported-posts-page');
        }

        $reason = trim((string) ($_POST['reason'] ?? 'Content moderation violation'));

        if ($this->postReports !== null) {
            $this->postReports->hideOrUnpublishPost($postId, $account->id, $reason);
        }

        return $this->redirect('/admin/reported-posts-page');
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminRestoreReportedPost(Request $request, int $postId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->redirect('/login');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!$this->sessions->validateCsrfToken(is_string($token) ? $token : null)) {
            return $this->redirect('/admin/reported-posts-page');
        }

        if ($this->postReports !== null) {
            $this->postReports->restorePost($postId, $account->id);
        }

        return $this->redirect('/admin/reported-posts-page');
    }

    /** @return array{status: int, type: string, body: string} */
    public function logout(Request $request): array
    {
        if ($this->guard->currentAccount() !== null) {
            $this->sessions->logout();
        }

        return $this->redirect('/?logged_out=1');
    }

    /** @return array{status: int, type: string, body: string} */
    public function profile(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode('/profile'));
        }

        $user = $this->users->findById($account->id);
        if ($user === null) {
            return $this->redirect('/login');
        }

        $published = $this->posts->listForAuthor($account->id, ['status' => 'published'], 1, 1);
        $drafts = $this->posts->listForAuthor($account->id, ['status' => 'draft'], 1, 1);
        $allUserPosts = $this->posts->listForAuthor($account->id, [], 1, 50);
        $remixCount = count(array_filter($allUserPosts['posts'], static fn($p) => !empty($p['remixed_from_post_id'])));
        $recent = array_slice($allUserPosts['posts'], 0, 6);

        $followerCount = $this->follows !== null ? $this->follows->getFollowerCount($account->id) : 0;
        $followingCount = $this->follows !== null ? $this->follows->getFollowingCount($account->id) : 0;
        $followers = $this->follows !== null ? $this->follows->getFollowers($account->id, 50) : [];
        $following = $this->follows !== null ? $this->follows->getFollowing($account->id, 50) : [];

        $userData = $user->toPrivateArray();
        $userData['avatar_url'] = $this->resolveAvatarUrl($user->avatarPath);
        $userData['banner_url'] = $this->resolveBannerUrl($user->bannerPath);

        return $this->render('profile', [
            'pageTitle' => $user->displayName . ' — Profile',
            'activeNav' => 'profile',
            'user' => $userData,
            'stats' => [
                'published_count' => $published['total'],
                'draft_count' => $drafts['total'],
                'remix_count' => $remixCount,
                'follower_count' => $followerCount,
                'following_count' => $followingCount,
            ],
            'followers' => $followers,
            'following' => $following,
            'userPosts' => $recent,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function editProfile(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode('/profile/edit'));
        }

        $user = $this->users->findById($account->id);
        if ($user === null) {
            return $this->redirect('/login');
        }

        $userData = $user->toPrivateArray();
        $userData['avatar_url'] = $this->resolveAvatarUrl($user->avatarPath);
        $userData['banner_url'] = $this->resolveBannerUrl($user->bannerPath);

        $activeCategories = $this->categories->listActive();

        if ($request->method === 'POST') {
            $csrf = $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
            if (!$this->sessions->validateCsrfToken($csrf)) {
                return $this->render('profile_edit', [
                    'pageTitle' => 'Edit Profile',
                    'activeNav' => 'profile',
                    'user' => $userData,
                    'categories' => $activeCategories,
                    'errors' => ['general' => 'Security token invalid or expired. Please refresh and try again.'],
                ]);
            }

            try {
                $this->users->updateProfile($account->id, $_POST);
                return $this->redirect('/profile?updated=1');
            } catch (ProfileValidationException $e) {
                return $this->render('profile_edit', [
                    'pageTitle' => 'Edit Profile',
                    'activeNav' => 'profile',
                    'user' => $userData,
                    'categories' => $activeCategories,
                    'errors' => $e->errors,
                ]);
            }
        }

        return $this->render('profile_edit', [
            'pageTitle' => 'Edit Profile',
            'activeNav' => 'profile',
            'user' => $userData,
            'categories' => $activeCategories,
            'errors' => [],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function uploadAvatar(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login');
        }

        $user = $this->users->findById($account->id);
        if ($user === null) {
            return $this->redirect('/login');
        }

        $userData = $user->toPrivateArray();
        $userData['avatar_url'] = $this->resolveAvatarUrl($user->avatarPath);
        $userData['banner_url'] = $this->resolveBannerUrl($user->bannerPath);

        $csrf = $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['avatar' => 'Security token invalid or expired.'],
            ]);
        }

        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['avatar' => 'Please select a valid image file to upload.'],
            ]);
        }

        $file = $_FILES['avatar'];

        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $storedPath = $this->media->uploadAvatar($file, $account->id, $user->avatarPath);
                $this->users->updateAvatar($account->id, $storedPath);
                return $this->redirect('/profile?avatar_updated=1');
            } catch (StorageException $e) {
                return $this->render('profile_edit', [
                    'pageTitle' => 'Edit Profile',
                    'activeNav' => 'profile',
                    'user' => $userData,
                    'errors' => ['avatar' => $e->getMessage()],
                ]);
            }
        }

        $maxBytes = SupabaseStorageConfig::LIMIT_AVATARS; // 2MB
        if ($file['size'] > $maxBytes) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['avatar' => 'Image file size must not exceed 2MB.'],
            ]);
        }

        $tmpPath = (string) $file['tmp_name'];
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['avatar' => 'The uploaded file is not a valid image.'],
            ]);
        }

        $allowedMimes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/webp' => 'webp',
        ];

        $detectedMime = $imageInfo['mime'] ?? '';
        if (!isset($allowedMimes[$detectedMime])) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['avatar' => 'Only PNG, JPEG, and WebP images are permitted.'],
            ]);
        }

        $ext = $allowedMimes[$detectedMime];
        $filename = 'avatar_' . $account->id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destDir = dirname(__DIR__, 2) . '/public/uploads/avatars';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['avatar' => 'Could not save the avatar image. Please try again.'],
            ]);
        }

        $avatarUrl = '/uploads/avatars/' . $filename;
        $this->users->updateAvatar($account->id, $avatarUrl);

        return $this->redirect('/profile?avatar_updated=1');
    }

    /** @return array{status: int, type: string, body: string} */
    public function uploadBanner(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login');
        }

        $user = $this->users->findById($account->id);
        if ($user === null) {
            return $this->redirect('/login');
        }

        $userData = $user->toPrivateArray();
        $userData['avatar_url'] = $this->resolveAvatarUrl($user->avatarPath);
        $userData['banner_url'] = $this->resolveBannerUrl($user->bannerPath);

        $csrf = $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['banner' => 'Security token invalid or expired.'],
            ]);
        }

        if (!isset($_FILES['banner']) || !is_array($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['banner' => 'Please select a valid banner image file to upload.'],
            ]);
        }

        $file = $_FILES['banner'];

        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $storedPath = $this->media->uploadBanner($file, $account->id, $user->bannerPath);
                $this->users->updateBanner($account->id, $storedPath);
                return $this->redirect('/profile?banner_updated=1');
            } catch (StorageException $e) {
                return $this->render('profile_edit', [
                    'pageTitle' => 'Edit Profile',
                    'activeNav' => 'profile',
                    'user' => $userData,
                    'errors' => ['banner' => $e->getMessage()],
                ]);
            }
        }

        // Local fallback
        $maxBytes = SupabaseStorageConfig::LIMIT_BANNERS; // 4MB
        if ($file['size'] > $maxBytes) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['banner' => 'Banner image file size must not exceed 4MB.'],
            ]);
        }

        $tmpPath = (string) $file['tmp_name'];
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['banner' => 'The uploaded file is not a valid image.'],
            ]);
        }

        $allowedMimes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/webp' => 'webp',
        ];

        $detectedMime = $imageInfo['mime'] ?? '';
        if (!isset($allowedMimes[$detectedMime])) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['banner' => 'Only PNG, JPEG, and WebP images are permitted for profile banners.'],
            ]);
        }

        $ext = $allowedMimes[$detectedMime];
        $filename = 'banner_' . $account->id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destDir = dirname(__DIR__, 2) . '/public/uploads/banners';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $userData,
                'errors' => ['banner' => 'Could not save the banner image. Please try again.'],
            ]);
        }

        $bannerUrl = '/uploads/banners/' . $filename;
        $this->users->updateBanner($account->id, $bannerUrl);

        return $this->redirect('/profile?banner_updated=1');
    }

    /** @return array{status: int, type: string, body: string} */
    public function uploadCover(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Sign in to upload cover images.']]);
        }

        $csrf = $request->header('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
        }

        if (!isset($_FILES['cover']) || !is_array($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Please select a valid image file.']]);
        }

        $file = $_FILES['cover'];
        $oldPath = isset($_POST['old_path']) && is_string($_POST['old_path']) ? trim($_POST['old_path']) : null;

        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $path = $this->media->uploadCoverImage($file, $account->id, $oldPath);
                $url = $this->media->resolvePublicUrl($this->media->getConfig()->bucketArticles, $path);
                return $this->jsonResponse(200, ['data' => ['path' => $path, 'url' => $url]]);
            } catch (StorageException $e) {
                return $this->jsonResponse(422, ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]]);
            }
        }

        // Local fallback
        $maxBytes = SupabaseStorageConfig::LIMIT_ARTICLES; // 5MB
        if ($file['size'] > $maxBytes) {
            return $this->jsonResponse(422, ['error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'Cover image must not exceed 5MB.']]);
        }

        $tmpPath = (string) $file['tmp_name'];
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return $this->jsonResponse(422, ['error' => ['code' => 'INVALID_IMAGE', 'message' => 'The uploaded file is not a valid image.']]);
        }

        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $detectedMime = $imageInfo['mime'] ?? '';
        if (!isset($allowedMimes[$detectedMime])) {
            return $this->jsonResponse(422, ['error' => ['code' => 'UNSUPPORTED_TYPE', 'message' => 'Only JPEG, PNG, and WebP images are allowed.']]);
        }

        $ext = $allowedMimes[$detectedMime];
        $filename = 'cover_' . $account->id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destDir = dirname(__DIR__, 2) . '/public/uploads/covers';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            return $this->jsonResponse(500, ['error' => ['code' => 'SAVE_FAILED', 'message' => 'Could not save uploaded cover image.']]);
        }

        $url = '/uploads/covers/' . $filename;
        return $this->jsonResponse(200, ['data' => ['path' => $url, 'url' => $url]]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function uploadInline(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Sign in to upload images.']]);
        }

        $csrf = $request->header('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
        }

        $file = $_FILES['image'] ?? ($_FILES['file'] ?? null);
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Please select a valid image file.']]);
        }

        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $result = $this->media->uploadInlineArticleImage($file, $account->id);
                return $this->jsonResponse(200, ['data' => ['url' => $result['url'], 'path' => $result['path']]]);
            } catch (StorageException $e) {
                return $this->jsonResponse(422, ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]]);
            }
        }

        // Local fallback
        $maxBytes = SupabaseStorageConfig::LIMIT_ARTICLES; // 5MB
        if ($file['size'] > $maxBytes) {
            return $this->jsonResponse(422, ['error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'Article image must not exceed 5MB.']]);
        }

        $tmpPath = (string) $file['tmp_name'];
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return $this->jsonResponse(422, ['error' => ['code' => 'INVALID_IMAGE', 'message' => 'The uploaded file is not a valid image.']]);
        }

        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $detectedMime = $imageInfo['mime'] ?? '';
        if (!isset($allowedMimes[$detectedMime])) {
            return $this->jsonResponse(422, ['error' => ['code' => 'UNSUPPORTED_TYPE', 'message' => 'Only JPEG, PNG, and WebP images are allowed.']]);
        }

        $ext = $allowedMimes[$detectedMime];
        $filename = 'inline_' . $account->id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destDir = dirname(__DIR__, 2) . '/public/uploads/inline';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            return $this->jsonResponse(500, ['error' => ['code' => 'SAVE_FAILED', 'message' => 'Could not save inline image.']]);
        }

        $url = '/uploads/inline/' . $filename;
        return $this->jsonResponse(200, ['data' => ['url' => $url, 'path' => $url]]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function uploadVideo(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Sign in to upload videos.']]);
        }

        $csrf = $request->header('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
        }

        if (!isset($_FILES['video']) || !is_array($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Please select a valid video file.']]);
        }

        $file = $_FILES['video'];
        $oldPath = isset($_POST['old_path']) && is_string($_POST['old_path']) ? trim($_POST['old_path']) : null;

        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $path = $this->media->uploadVideo($file, $account->id, $oldPath);
                $signedUrl = $this->media->resolveSignedVideoUrl($path, 3600);
                return $this->jsonResponse(200, ['data' => ['path' => $path, 'signed_url' => $signedUrl]]);
            } catch (StorageException $e) {
                return $this->jsonResponse(422, ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]]);
            }
        }

        // Local fallback
        $maxBytes = SupabaseStorageConfig::LIMIT_VIDEOS; // 50MB
        if ($file['size'] > $maxBytes) {
            return $this->jsonResponse(422, ['error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'Video must not exceed 50MB.']]);
        }

        $tmpPath = (string) $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $allowedMimes = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!is_string($mime) || !isset($allowedMimes[$mime])) {
            return $this->jsonResponse(422, ['error' => ['code' => 'UNSUPPORTED_TYPE', 'message' => 'Only MP4 and WebM videos are allowed.']]);
        }

        $ext = $allowedMimes[$mime];
        $filename = 'video_' . $account->id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destDir = dirname(__DIR__, 2) . '/public/uploads/videos';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            return $this->jsonResponse(500, ['error' => ['code' => 'SAVE_FAILED', 'message' => 'Could not save video file.']]);
        }

        $url = '/uploads/videos/' . $filename;
        return $this->jsonResponse(200, ['data' => ['path' => $url, 'signed_url' => $url]]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function signedVideoUrl(Request $request): array
    {
        $path = isset($_GET['path']) && is_string($_GET['path']) ? trim($_GET['path']) : '';
        if ($path === '') {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Missing path parameter.']]);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/uploads/')) {
            return $this->jsonResponse(200, ['data' => ['signed_url' => $path]]);
        }

        if ($this->media === null || !$this->media->getConfig()->isConfigured()) {
            return $this->jsonResponse(503, ['error' => ['code' => 'STORAGE_UNAVAILABLE', 'message' => 'Storage service is not configured.']]);
        }

        $account = $this->guard->currentAccount();
        $isAuthorized = false;

        if ($account !== null && $account->role === 'admin') {
            $isAuthorized = true;
        } else {
            $post = $this->posts->findByVideoPath($path);
            if ($post !== null) {
                if ($post['status'] === 'published') {
                    $isAuthorized = true;
                } elseif ($account !== null && (int) $post['author_id'] === $account->id) {
                    $isAuthorized = true;
                }
            }
        }

        if (!$isAuthorized) {
            return $this->jsonResponse(403, ['error' => ['code' => 'FORBIDDEN', 'message' => 'You are not authorized to access this private video.']]);
        }

        try {
            $signedUrl = $this->media->resolveSignedVideoUrl($path, 3600);
            return $this->jsonResponse(200, ['data' => ['signed_url' => $signedUrl]]);
        } catch (StorageException $e) {
            return $this->jsonResponse(500, ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]]);
        }
    }

    /** @return array{status: int, type: string, body: string} */
    public function previewPost(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Sign in to preview articles.']]);
        }

        $csrf = $request->header('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
        }

        $body = $request->json();
        $content = isset($body['content']) && is_string($body['content']) ? $body['content'] : '';
        $title = isset($body['title']) && is_string($body['title']) && trim($body['title']) !== '' ? trim($body['title']) : 'Untitled Article';
        $category = isset($body['category']) && is_string($body['category']) && trim($body['category']) !== '' ? trim($body['category']) : 'General';
        $coverPath = isset($body['cover_image']) && is_string($body['cover_image']) && trim($body['cover_image']) !== '' ? trim($body['cover_image']) : null;

        $signedUrlsMap = [];
        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            $contentVideoPaths = ArticleContentRenderer::extractVideoPaths($content);
            foreach ($contentVideoPaths as $vp) {
                try {
                    $signedUrlsMap[$vp] = $this->media->resolveSignedVideoUrl($vp, 3600);
                } catch (Throwable) {
                }
            }
        }

        $renderer = new ArticleContentRenderer($signedUrlsMap);
        $renderedBody = trim($content) !== '' ? $renderer->render($content) : '';

        $wordCount = str_word_count(strip_tags($content));
        $readTime = max(1, (int) ceil($wordCount / 200));
        $avatarUrl = $this->resolveAvatarUrl($account->avatarPath);
        $handle = \InfinityBlog\Support\UserIdentityHelper::formatHandle($account->username, $account->displayName);

        $html = '<div class="studio-live-preview-sheet" style="max-width: 780px; margin: 0 auto; padding: 24px 0;">';
        
        if (!empty($coverPath)) {
            $coverUrl = \InfinityBlog\Posts\ArticleImageHelper::resolveCoverUrl($coverPath);
            $html .= '<div style="margin-bottom: 28px; border-radius: 16px; overflow: hidden; max-height: 420px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<img src="' . htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') . '" alt="Article Cover" style="width: 100%; height: 100%; object-fit: cover;">'
                . '</div>';
        }

        $html .= '<div style="margin-bottom: 14px;"><span class="category-pill" style="font-size: 0.8rem; padding: 4px 12px;">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</span></div>';
        $html .= '<h1 style="font-size: 2.3rem; font-weight: 800; color: var(--text-heading); margin-bottom: 18px; line-height: 1.25; letter-spacing: -0.02em;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';

        // Author Bar
        $html .= '<div style="display: flex; align-items: center; gap: 14px; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--studio-border);">';
        if (!empty($avatarUrl)) {
            $html .= '<img src="' . htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($account->displayName, ENT_QUOTES, 'UTF-8') . '" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
        } else {
            $initial = strtoupper(substr($account->displayName, 0, 1));
            $html .= '<div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">' . $initial . '</div>';
        }
        $html .= '<div>'
            . '<div style="font-size: 0.95rem; font-weight: 700; color: var(--text-heading);">' . htmlspecialchars($account->displayName, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="font-size: 0.82rem; color: var(--text-muted); display: flex; gap: 8px;">'
            . '<span>' . htmlspecialchars($handle, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span>·</span>'
            . '<span>' . date('M d, Y') . '</span>'
            . '<span>·</span>'
            . '<span>' . $readTime . ' min read (' . $wordCount . ' words)</span>'
            . '</div>'
            . '</div>'
            . '</div>';

        if ($renderedBody !== '') {
            $html .= '<div class="article-content" style="font-size: 1.05rem; line-height: 1.8; color: var(--text-body);">' . $renderedBody . '</div>';
        } else {
            $html .= '<div style="padding: 56px 24px; text-align: center; border: 2px dashed var(--studio-border); border-radius: 20px; background: rgba(99, 102, 241, 0.03);">'
                . '<div style="font-size: 2.5rem; margin-bottom: 12px;">✍️</div>'
                . '<h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-heading); margin-bottom: 8px;">Draft has no content yet</h3>'
                . '<p style="font-size: 0.92rem; color: var(--text-muted); max-width: 460px; margin: 0 auto; line-height: 1.55;">Write paragraphs in the editor, insert images or code snippets, or use the AI Writing Assistant. Your live formatted article will render here seamlessly.</p>'
                . '</div>';
        }

        $html .= '</div>';

        return $this->jsonResponse(200, ['data' => ['html' => $html]]);
    }

    public function studioAiAssist(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to use the AI Writing Assistant.']]);
        }

        $input = $request->input();
        $csrf = $request->header('X-CSRF-Token') ?? ($input['csrf_token'] ?? ($_POST['csrf_token'] ?? null));
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
        }

        $action = trim((string) ($input['action'] ?? ''));
        $text = (string) ($input['text'] ?? '');
        $options = isset($input['options']) && is_array($input['options']) ? $input['options'] : [];

        if ($action === '' || trim($text) === '') {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Both action and text are required.']]);
        }

        try {
            $aiConfig = null;
            try {
                $aiConfig = \InfinityBlog\Summaries\AiProviderConfig::fromEnvironment();
            } catch (Throwable) {}

            $service = new \InfinityBlog\Posts\StudioAiService($aiConfig);
            $res = $service->assist($action, $text, $options);
            return $this->jsonResponse(200, ['data' => $res]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(422, ['error' => ['code' => 'INVALID_ARGUMENT', 'message' => $e->getMessage()]]);
        } catch (Throwable $e) {
            return $this->jsonResponse(500, ['error' => ['code' => 'STUDIO_AI_ERROR', 'message' => $e->getMessage() ?: 'Could not process AI request.']]);
        }
    }

    public function studioData(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in.']]);
        }

        $service = new \InfinityBlog\Posts\StudioDataService($this->users->getPdo());

        if ($request->method === 'POST') {
            $input = $request->input();
            $csrf = $request->header('X-CSRF-Token') ?? ($input['csrf_token'] ?? ($_POST['csrf_token'] ?? null));
            if (!$this->sessions->validateCsrfToken($csrf)) {
                return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
            }

            $draftKey = trim((string) ($input['draft_key'] ?? 'default'));
            $postId = !empty($input['post_id']) && is_numeric($input['post_id']) ? (int) $input['post_id'] : null;
            $subtitle = isset($input['subtitle']) ? (string) $input['subtitle'] : null;
            $researchNotes = isset($input['research_notes']) ? (string) $input['research_notes'] : null;
            $quickNotes = isset($input['quick_notes']) && is_array($input['quick_notes']) ? $input['quick_notes'] : null;
            $targetWords = !empty($input['target_words']) && is_numeric($input['target_words']) ? (int) $input['target_words'] : null;
            $settings = isset($input['settings']) && is_array($input['settings']) ? $input['settings'] : null;

            $service->saveStudioData($account->id, $draftKey, $postId, $subtitle, $researchNotes, $quickNotes, $targetWords, $settings);
            return $this->jsonResponse(200, ['data' => ['saved' => true]]);
        }

        // GET request
        $draftKey = trim((string) ($_GET['draft_key'] ?? 'default'));
        $postId = !empty($_GET['post_id']) && is_numeric($_GET['post_id']) ? (int) $_GET['post_id'] : null;

        $data = $service->getStudioData($account->id, $draftKey, $postId);
        return $this->jsonResponse(200, ['data' => $data]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function contact(Request $request): array
    {
        return $this->render('contact', [
            'pageTitle' => 'Contact Us',
            'metaDescription' => 'Get in touch with the Infinity Blog editorial, security, or support team.',
            'activeNav' => 'contact',
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function submitContact(Request $request): array
    {
        $csrf = $request->header('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->render('contact', [
                'pageTitle' => 'Contact Us',
                'errorMessage' => 'Security token invalid or expired. Please reload the page and try again.',
                'formData' => $_POST,
                'activeNav' => 'contact',
            ], 403);
        }

        // Anti-spam honeypot check
        $honeypot = trim((string) ($_POST['website_hp'] ?? ''));
        if ($honeypot !== '') {
            return $this->redirect('/contact?submitted=1&ticket=' . rand(1000, 9999));
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Please enter your name (2–100 characters).';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Please provide a valid email address.';
        }
        if ($subject === '' || mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
            $errors['subject'] = 'Please provide a clear subject (3–150 characters).';
        }
        if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
            $errors['message'] = 'Message must be between 10 and 3,000 characters.';
        }

        if (!empty($errors)) {
            return $this->render('contact', [
                'pageTitle' => 'Contact Us',
                'errorMessage' => 'Please correct the highlighted errors before submitting.',
                'errors' => $errors,
                'formData' => ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message],
                'activeNav' => 'contact',
            ], 422);
        }

        $ip = $request->sourceAddress;
        if ($this->contactRepo !== null && $this->contactRepo->countRecentFromIp($ip, 60) >= 5) {
            return $this->render('contact', [
                'pageTitle' => 'Contact Us',
                'errorMessage' => 'Rate limit exceeded. You have sent 5 inquiries recently. Please wait an hour before submitting again.',
                'formData' => ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message],
                'activeNav' => 'contact',
            ], 429);
        }

        $ticketId = 1001;
        if ($this->contactRepo !== null) {
            $ticketId = $this->contactRepo->createMessage($name, $email, $subject, $message, $ip);
        }

        // Deliver contact notification via MailerService to configured CONTACT_TO_EMAIL
        if ($this->mailer !== null) {
            try {
                $this->mailer->sendContactNotification([
                    'ticket_id' => $ticketId,
                    'name' => $name,
                    'email' => $email,
                    'subject' => $subject,
                    'message' => $message,
                    'timestamp' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $e) {
                // Graceful failure: do not crash the contact page or leak errors to visitors
                error_log('Contact form email notification failed: ' . $e->getMessage());
            }
        }

        return $this->redirect('/contact?submitted=1&ticket=' . $ticketId);
    }

    /** @return array{status: int, type: string, body: string} */
    public function privacy(Request $request): array
    {
        return $this->render('privacy', [
            'pageTitle' => 'Privacy Policy',
            'metaDescription' => 'Review the Infinity Blog privacy statement, data protection guidelines, cookie policies, and visitor rights.',
            'activeNav' => 'privacy',
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function terms(Request $request): array
    {
        return $this->render('terms', [
            'pageTitle' => 'Terms and Conditions',
            'metaDescription' => 'Review the Infinity Blog Terms of Service, creator rights, code remix licensing, and content policies.',
            'activeNav' => 'terms',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, type: string, body: string}
     */
    private function render(string $template, array $data = [], int $status = 200): array
    {
        $currentUser = $this->guard->currentAccount()?->toPublicArray();
        if ($currentUser !== null) {
            $currentUser['avatar_url'] = $this->resolveAvatarUrl($currentUser['avatar_path'] ?? null);
            $currentUser['banner_url'] = $this->resolveBannerUrl($currentUser['banner_path'] ?? null);
        }
        $csrfToken = $this->sessions->csrfToken();
        $basePath = Request::detectBasePath($this->config->baseUrl);

        // SEO Formatting & Metadata Injection
        $pageTitleFormatted = $this->seo->formatTitle($data['pageTitle'] ?? null);
        $metaDescriptionFormatted = $this->seo->formatDescription($data['metaDescription'] ?? null);
        $canonicalUrl = $data['canonicalUrl'] ?? $this->seo->buildCanonicalUrl($_SERVER['REQUEST_URI'] ?? '/');
        $ogImageAbsolute = $this->seo->resolveAbsoluteImageUrl($data['ogImage'] ?? null);

        // Schema.org Graph Construction
        $schemas = [
            $this->seo->buildOrganizationSchema(),
            $this->seo->buildWebSiteSchema(),
        ];

        if ($template === 'article' && !empty($data['post'])) {
            $schemas[] = $this->seo->buildArticleSchema($data['post'], $canonicalUrl, $ogImageAbsolute);
            $crumbs = [
                ['name' => 'Home', 'url' => $this->config->baseUrl . '/'],
                ['name' => $data['post']['category_name'] ?? 'Articles', 'url' => $this->config->baseUrl . '/explore' . (!empty($data['post']['category_slug']) ? '?category=' . urlencode($data['post']['category_slug']) : '')],
                ['name' => $data['post']['title'], 'url' => $canonicalUrl],
            ];
            $schemas[] = $this->seo->buildBreadcrumbSchema($crumbs);
        } elseif ($template === 'author_profile' && !empty($data['author'])) {
            $schemas[] = $this->seo->buildAuthorSchema($data['author'], $canonicalUrl);
            $crumbs = [
                ['name' => 'Home', 'url' => $this->config->baseUrl . '/'],
                ['name' => 'Authors', 'url' => $this->config->baseUrl . '/authors'],
                ['name' => $data['author']['display_name'] ?? $data['author']['username'], 'url' => $canonicalUrl],
            ];
            $schemas[] = $this->seo->buildBreadcrumbSchema($crumbs);
        } elseif ($template === 'contact') {
            $schemas[] = $this->seo->buildContactSchema();
        } elseif ($template === 'public_categories') {
            $crumbs = [
                ['name' => 'Home', 'url' => $this->config->baseUrl . '/'],
                ['name' => 'Categories', 'url' => $this->config->baseUrl . '/categories'],
            ];
            $schemas[] = $this->seo->buildBreadcrumbSchema($crumbs);
        } elseif ($template === 'explore') {
            $crumbs = [
                ['name' => 'Home', 'url' => $this->config->baseUrl . '/'],
                ['name' => 'Explore Articles', 'url' => $this->config->baseUrl . '/explore'],
            ];
            $schemas[] = $this->seo->buildBreadcrumbSchema($crumbs);
        }

        $jsonLd = $this->seo->renderJsonLd($schemas);

        $data['currentUser'] = $currentUser;
        $data['csrfToken'] = $csrfToken;
        $data['basePath'] = $basePath;
        $data['pageTitleFormatted'] = $pageTitleFormatted;
        $data['metaDescriptionFormatted'] = $metaDescriptionFormatted;
        $data['canonicalUrl'] = $canonicalUrl;
        $data['ogImageAbsolute'] = $ogImageAbsolute;
        $data['jsonLd'] = $jsonLd;
        $data['baseUrl'] = $this->config->baseUrl;
        $data['supportEmail'] = filter_var(getenv('CONTACT_SUPPORT_EMAIL') ?: null, FILTER_VALIDATE_EMAIL) ?: null;
        $data['editorialEmail'] = filter_var(getenv('CONTACT_EDITORIAL_EMAIL') ?: null, FILTER_VALIDATE_EMAIL) ?: null;
        $data['gaMeasurementId'] = $this->seo->getGaMeasurementId();
        $data['gscVerificationCode'] = $this->seo->getGscVerificationCode();

        $httpStatusCode = $status;
        extract($data);

        ob_start();
        require dirname(__DIR__) . '/Views/' . $template . '.php';
        $content = ob_get_clean();

        ob_start();
        require dirname(__DIR__) . '/Views/layout.php';
        $html = ob_get_clean();

        return [
            'status' => $httpStatusCode,
            'type' => 'text/html; charset=utf-8',
            'body' => (string) $html,
        ];
    }

    /** @return array{status: int, type: string, body: string} */
    public function notFound(string|Request $param = 'The requested page was not found.'): array
    {
        $message = is_string($param) ? $param : 'The requested page was not found.';

        return $this->render('404', [
            'pageTitle' => 'Page Not Found',
            'activeNav' => '',
            'errorMessage' => $message,
        ], 404);
    }

    /** @return array{status: int, type: string, body: string} */
    public function serverError(string $requestId = '', string $message = 'An unexpected error occurred while processing your request.'): array
    {
        return $this->render('500', [
            'pageTitle' => 'Something Went Wrong',
            'activeNav' => '',
            'errorMessage' => $message,
            'requestId' => $requestId,
        ], 500);
    }

    /** @return array{status: int, type: string, body: string} */
    private function redirect(string $url): array
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        }
        return [
            'status' => 302,
            'type' => 'text/html; charset=utf-8',
            'headers' => ['Location' => $url],
            'body' => '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></head><body>Redirecting...</body></html>',
        ];
    }

    public function resolveAvatarUrl(?string $path): ?string
    {
        return \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($path);
    }

    public function resolveBannerUrl(?string $path): ?string
    {
        return \InfinityBlog\Support\UserIdentityHelper::resolveBannerUrl($path);
    }

    /** @return array{status: int, type: string, body: string} */
    public function library(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode('/library'));
        }

        $tab = isset($_GET['tab']) && in_array($_GET['tab'], ['bookmarks', 'history', 'collections', 'notes', 'preferences', 'ebooks'], true)
            ? $_GET['tab']
            : 'bookmarks';
        $searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : null;

        $bookmarks = $this->library !== null ? $this->library->getBookmarks($account->id, $searchQuery) : [];
        $history = $this->library !== null ? $this->library->getReadingHistory($account->id, 20) : [];
        $collections = $this->library !== null ? $this->library->getCollections($account->id, $account->id) : [];
        $notes = $this->library !== null ? $this->library->getUserNotes($account->id, $searchQuery) : [];
        $topicPreferences = $this->library !== null ? $this->library->getTopicPreferences($account->id) : [];
        $savedEbooks = $this->ebooks !== null ? $this->ebooks->getSavedBooks($account->id, $searchQuery) : [];
        $ebookReadingHistory = $this->ebooks !== null ? $this->ebooks->getContinueReading($account->id, 20) : [];

        return $this->render('library', [
            'pageTitle' => 'My Library — Personal Knowledge Base',
            'metaDescription' => 'Your personal saved articles, reading history, collections, and passage notes.',
            'activeNav' => 'library',
            'activeTab' => $tab,
            'searchQuery' => $searchQuery,
            'bookmarks' => $bookmarks,
            'history' => $history,
            'collections' => $collections,
            'notes' => $notes,
            'topicPreferences' => $topicPreferences,
            'savedEbooks' => $savedEbooks,
            'ebookReadingHistory' => $ebookReadingHistory,
            'currentUser' => [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'role' => $account->role,
            ],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function followingFeed(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode('/following'));
        }

        $feedResult = $this->posts->listPublic(['following_user_id' => $account->id], 1, 24);

        return $this->render('following_feed', [
            'pageTitle' => 'Following Feed — Stories from Followed Writers',
            'metaDescription' => 'The latest published stories and updates from writers you follow on Infinity Blog.',
            'activeNav' => 'following',
            'posts' => $feedResult['posts'],
            'currentUser' => [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'role' => $account->role,
            ],
        ]);
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function chat(Request $request, ?string $slug = null): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode($request->path));
        }

        if ($this->community !== null) {
            $this->community->ensureCategoryChatRooms();
        }

        $rooms = $this->community !== null ? $this->community->getChatRooms() : [];
        if (empty($rooms) && $this->community !== null) {
            $adminId = $this->users->findFirstAdminId();
            if ($adminId !== null) {
                try {
                    $this->community->createChatRoom('Web Development', 'web-development', 'Discussions about frontend, backend, APIs, and modern architecture.', null, $adminId);
                    $this->community->createChatRoom('Artificial Intelligence', 'artificial-intelligence', 'Exchange thoughts on LLMs, neural networks, ethics, and AI workflows.', null, $adminId);
                    $this->community->createChatRoom('General Lounge', 'general', 'Casual hangouts, writing inspiration, and community introductions.', null, $adminId);
                    $rooms = $this->community->getChatRooms();
                } catch (\Throwable) {
                    $rooms = [];
                }
            }
        }

        $activeRoom = null;
        if ($slug !== null && trim($slug) !== '') {
            $activeRoom = $this->community?->getChatRoomBySlug(trim($slug));
            if ($activeRoom !== null && empty($activeRoom['is_active'])) {
                $activeRoom = null;
            }
        }
        if ($activeRoom === null && !empty($rooms)) {
            $activeRoom = $rooms[0];
        }

        $messages = [];
        $isMember = false;
        $memberCount = 0;
        if ($activeRoom !== null && $this->community !== null) {
            $messages = $this->community->getChatMessages((int) $activeRoom['id'], 50, null, $account->id);
            $memberCount = $this->community->getRoomMemberCount((int) $activeRoom['id']);
            $isMember = $this->community->isMember((int) $activeRoom['id'], $account->id);
        }

        $res = $this->render('chat', [
            'pageTitle' => 'Category Communities & Chat Rooms',
            'metaDescription' => 'Join topic-based text channels to exchange ideas with writers and readers in real-time.',
            'activeNav' => 'chat',
            'rooms' => $rooms,
            'activeRoom' => $activeRoom,
            'messages' => $messages,
            'isMember' => $isMember,
            'memberCount' => $memberCount,
            'currentUser' => [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'role' => $account->role,
            ],
        ]);
        $res['headers'] = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];
        return $res;
    }

    /** @return array{status: int, type: string, body: string} */
    public function sitemap(Request $request): array
    {
        $postsResult = $this->posts->listPublic([], 1, 500);
        $categories = $this->categories->listActive();
        $authors = $this->users->listPublicAuthors();
        $baseUrl = rtrim($this->config->baseUrl, '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Main Navigation Index Pages
        $xml .= "  <url>\n    <loc>{$baseUrl}/</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/explore</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.9</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/categories</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/authors</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/communities</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.8</priority>\n  </url>\n";

        // Informational, Contact & Legal Pages
        $xml .= "  <url>\n    <loc>{$baseUrl}/about</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/contact</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.5</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/privacy</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.3</priority>\n  </url>\n";
        $xml .= "  <url>\n    <loc>{$baseUrl}/terms</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.3</priority>\n  </url>\n";

        // Categories
        foreach ($categories as $cat) {
            $slug = htmlspecialchars($cat['slug'] ?? '', ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n    <loc>{$baseUrl}/explore?category={$slug}</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.75</priority>\n  </url>\n";
        }

        // Articles with W3C Datetime Lastmod
        foreach ($postsResult['posts'] as $post) {
            $loc = htmlspecialchars("{$baseUrl}/article/{$post['id']}", ENT_XML1, 'UTF-8');
            $rawDate = !empty($post['updated_at']) ? $post['updated_at'] : (!empty($post['published_at']) ? $post['published_at'] : 'now');
            $lastmod = date('Y-m-d\TH:i:sP', strtotime($rawDate));
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.85</priority>\n  </url>\n";
        }

        // Public Authors
        foreach ($authors as $author) {
            $loc = htmlspecialchars("{$baseUrl}/author/{$author['id']}", ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
        }

        $xml .= '</urlset>';

        return [
            'status' => 200,
            'type' => 'application/xml; charset=utf-8',
            'body' => $xml,
        ];
    }

    /** @return array{status: int, type: string, body: string} */
    public function robots(Request $request): array
    {
        $baseUrl = rtrim($this->config->baseUrl, '/');
        $robots = "User-agent: *\n" .
            "Allow: /\n" .
            "Allow: /assets/\n" .
            "Disallow: /admin/\n" .
            "Disallow: /auth/\n" .
            "Disallow: /api/\n" .
            "Disallow: /messages\n" .
            "Disallow: /library\n" .
            "Disallow: /following\n" .
            "Disallow: /profile/edit\n\n" .
            "Sitemap: {$baseUrl}/sitemap.xml\n";

        return [
            'status' => 200,
            'type' => 'text/plain; charset=utf-8',
            'body' => $robots,
        ];
    }

    /** @return array{status: int, type: string, body: string} */
    public function assistant(Request $request, int $postId): array
    {
        $account = $this->guard->currentAccount();
        $input = $request->input();
        $question = trim((string) ($input['question'] ?? ''));

        if ($question === '') {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Please provide a question.']]);
        }

        $sessionId = session_id() ?: 'guest_' . substr(md5($request->sourceAddress), 0, 16);

        if ($this->assistant === null) {
            return $this->jsonResponse(200, [
                'data' => [
                    'answer' => 'Assistant service is not available in current configuration.',
                    'passage_reference' => null,
                ],
            ]);
        }

        try {
            $res = $this->assistant->ask($postId, $account?->id, $sessionId, $question);
            return $this->jsonResponse(200, ['data' => $res]);
        } catch (\InvalidArgumentException $e) {
            $status = $e->getMessage() === 'Article not found.' ? 404 : 422;
            return $this->jsonResponse($status, ['error' => ['code' => 'INVALID_REQUEST', 'message' => $e->getMessage()]]);
        } catch (Throwable $e) {
            return $this->jsonResponse(500, ['error' => ['code' => 'ASSISTANT_ERROR', 'message' => $e->getMessage() ?: 'Could not process question.']]);
        }
    }

    public function quickPost(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to share a post.']]);
        }

        $input = $request->input();
        $csrf = $request->header('X-CSRF-Token') ?? ($input['csrf_token'] ?? ($_POST['csrf_token'] ?? null));
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => ['code' => 'CSRF_INVALID', 'message' => 'Security token invalid or expired.']]);
        }

        $rawContent = isset($input['content']) && is_string($input['content']) ? trim($input['content']) : '';
        $rawTitle = isset($input['title']) && is_string($input['title']) ? trim($input['title']) : '';

        if ($rawContent === '' && $rawTitle === '') {
            return $this->jsonResponse(422, ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Post content cannot be empty.']]);
        }

        if ($rawTitle === '') {
            $clean = strip_tags($rawContent);
            $firstSentence = preg_split('/[.!?\n]/u', $clean)[0] ?? $clean;
            $rawTitle = mb_substr(trim($firstSentence), 0, 80);
            if ($rawTitle === '') {
                $rawTitle = 'Community Discussion';
            }
        }

        if ($rawContent === '') {
            $rawContent = $rawTitle;
        }

        $categoryId = isset($input['category_id']) && is_numeric($input['category_id']) ? (int) $input['category_id'] : null;
        if ($categoryId === null || $categoryId <= 0) {
            $activeCats = $this->categories->listActive();
            $categoryId = !empty($activeCats) ? (int) $activeCats[0]['id'] : null;
        }

        $contentType = isset($input['content_type']) && is_string($input['content_type'])
            ? \InfinityBlog\Posts\ContentTypeHelper::normalize($input['content_type'])
            : \InfinityBlog\Posts\ContentTypeHelper::TYPE_ARTICLE;
        $coverImagePath = isset($input['cover_image_path']) && is_string($input['cover_image_path']) && trim($input['cover_image_path']) !== ''
            ? trim($input['cover_image_path'])
            : null;

        try {
            $draft = $this->posts->createDraft($account->id, [
                'title' => $rawTitle,
                'content' => $rawContent,
                'category_id' => $categoryId,
                'content_type' => $contentType,
                'cover_image_path' => $coverImagePath,
            ]);

            $published = $this->posts->publishForAuthor((int) $draft['id'], $account->id);

            return $this->jsonResponse(201, [
                'data' => [
                    'post' => $published,
                    'post_id' => (int) ($published['id'] ?? $draft['id']),
                    'message' => 'Your post was successfully shared!',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(400, ['error' => ['code' => 'POST_FAILED', 'message' => $e->getMessage()]]);
        }
    }

    private static function formatRelativeTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return 'recently';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return 'recently';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $mins = max(1, (int) floor($diff / 60));
            return $mins . 'm ago';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . 'h ago';
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days === 1 ? 'yesterday' : $days . 'd ago';
        }
        return date('M j, Y', $timestamp);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, type: string, body: string}
     */
    private function jsonResponse(int $status, array $data): array
    {
        return [
            'status' => $status,
            'type' => 'application/json; charset=utf-8',
            'body' => (string) json_encode($data, JSON_UNESCAPED_SLASHES),
        ];
    }

    // ==========================================
    // eBook Library Web & API Endpoints
    // ==========================================

    /** @return array{status: int, type: string, body: string} */
    public function ebooksIndex(Request $request): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;

        $searchParam = $request->query('search') ?? ($_GET['search'] ?? null);
        $search = is_string($searchParam) ? trim($searchParam) : null;
        if ($search === '') {
            $search = null;
        }

        $categorySlug = $request->query('category') ?? ($_GET['category'] ?? null);
        $categorySlug = is_string($categorySlug) ? trim($categorySlug) : null;
        if ($categorySlug === '') {
            $categorySlug = null;
        }

        $format = $request->query('format') ?? ($_GET['format'] ?? null);
        $format = is_string($format) ? strtolower(trim($format)) : null;
        if (!in_array($format, ['pdf', 'epub'], true)) {
            $format = null;
        }

        $sort = $request->query('sort') ?? ($_GET['sort'] ?? 'latest');
        $sort = is_string($sort) && in_array($sort, ['latest', 'popular', 'title'], true) ? $sort : 'latest';

        $page = max(1, (int) ($request->query('page') ?? ($_GET['page'] ?? 1)));

        $activeCategories = $this->categories->listActive();
        $activeCategoryId = null;
        if ($categorySlug !== null) {
            foreach ($activeCategories as $cat) {
                if ($cat['slug'] === $categorySlug || (string) $cat['id'] === $categorySlug) {
                    $activeCategoryId = (int) $cat['id'];
                    break;
                }
            }
        }

        $filters = [];
        if ($search !== null) {
            $filters['search'] = $search;
        }
        if ($activeCategoryId !== null) {
            $filters['category_id'] = $activeCategoryId;
        }
        if ($format !== null) {
            $filters['format'] = $format;
        }
        $filters['sort'] = $sort;

        $ebooksResult = $this->ebooks->listPublic($filters, $page, 12);
        $continueReading = $viewerId !== null ? $this->ebooks->getContinueReading($viewerId, 6) : [];
        $featuredBooks = ($page === 1 && $search === null && $activeCategoryId === null && $format === null) ? $this->ebooks->getFeatured(4) : [];
        $popularBooks = ($page === 1 && $search === null && $activeCategoryId === null && $format === null) ? $this->ebooks->getPopular(4) : [];

        return $this->render('ebooks_index', [
            'pageTitle' => 'eBook Library — Public Community Books',
            'metaDescription' => 'Discover, read, and share community electronic books across programming, design, science, fiction, and more.',
            'activeNav' => 'ebooks',
            'categories' => $activeCategories,
            'activeCategorySlug' => $categorySlug,
            'activeCategoryId' => $activeCategoryId,
            'books' => $ebooksResult['items'],
            'pagination' => $ebooksResult['pagination'],
            'continueReading' => $continueReading,
            'featuredBooks' => $featuredBooks,
            'popularBooks' => $popularBooks,
            'search' => $search,
            'format' => $format,
            'sort' => $sort,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebooksUpload(Request $request): array
    {
        $account = $this->guard->currentAccount();
        $categories = $this->categories->listActive();
        $rooms = $this->community !== null ? $this->community->getChatRooms() : [];

        $submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';
        $submissionRef = isset($_GET['ref']) ? trim(strip_tags((string) $_GET['ref'])) : null;

        return $this->render('ebooks_upload', [
            'pageTitle' => 'Upload an eBook — Community Library',
            'metaDescription' => 'Share a digital book with the Infinity Blog reading community.',
            'activeNav' => 'ebooks',
            'categories' => $categories,
            'communities' => $rooms,
            'account' => $account,
            'isAnonymous' => $account === null,
            'submitted' => $submitted,
            'submissionRef' => $submissionRef,
            'errors' => [],
            'formData' => [],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function processEbookUpload(Request $request): array
    {
        $account = $this->guard->currentAccount();
        $isAjax = $request->isJson() || str_contains((string) $request->header('Accept'), 'application/json');

        // Anti-abuse Honeypot verification (empty field for humans, filled by bots)
        $honeypot = trim((string) ($request->post('website_hp') ?? $_POST['website_hp'] ?? ''));
        if ($honeypot !== '') {
            if ($isAjax) {
                return $this->jsonResponse(200, ['success' => true, 'message' => 'Submission received.']);
            }
            return $this->redirect('/ebooks/upload?submitted=1&ref=EB-VERIFIED');
        }

        // Anti-abuse sliding-window rate limiting for anonymous guests
        $clientIp = $request->clientIp() ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($account === null && !\InfinityBlog\Ebooks\EbookFileService::checkUploadRateLimit($this->ebooks->getPdo(), $clientIp)) {
            $limitErr = ['general' => 'Too many submissions received from your connection recently. Please wait a bit before uploading again.'];
            if ($isAjax) {
                return $this->jsonResponse(429, ['errors' => $limitErr]);
            }
            return $this->render('ebooks_upload', [
                'pageTitle' => 'Upload an eBook — Community Library',
                'metaDescription' => 'Share a digital book with the Infinity Blog reading community.',
                'activeNav' => 'ebooks',
                'categories' => $this->categories->listActive(),
                'communities' => $this->community !== null ? $this->community->getChatRooms() : [],
                'account' => $account,
                'isAnonymous' => true,
                'submitted' => false,
                'submissionRef' => null,
                'errors' => $limitErr,
                'formData' => $_POST,
            ], 429);
        }

        $csrfToken = (string) ($request->post('csrf_token') ?? $_POST['csrf_token'] ?? $request->header('X-CSRF-Token') ?? '');
        if (!$this->sessions->validateCsrfToken($csrfToken)) {
            if ($isAjax) {
                return $this->jsonResponse(403, ['error' => 'Invalid or expired CSRF token. Please refresh the page and try again.']);
            }
            $categories = $this->categories->listActive();
            $rooms = $this->community !== null ? $this->community->getChatRooms() : [];
            return $this->render('ebooks_upload', [
                'pageTitle' => 'Upload an eBook — Community Library',
                'metaDescription' => 'Share a digital book with the Infinity Blog community.',
                'activeNav' => 'ebooks',
                'categories' => $categories,
                'communities' => $rooms,
                'account' => $account,
                'isAnonymous' => $account === null,
                'submitted' => false,
                'submissionRef' => null,
                'errors' => ['csrf' => 'Invalid or expired security token.'],
                'formData' => $_POST,
            ], 403);
        }

        $errors = [];
        $title = trim((string) ($request->post('title') ?? $_POST['title'] ?? ''));
        $authorName = trim((string) ($request->post('author_name') ?? $_POST['author_name'] ?? ''));
        $description = trim((string) ($request->post('description') ?? $_POST['description'] ?? ''));
        $categoryId = (int) ($request->post('category_id') ?? $_POST['category_id'] ?? 0);
        $communityId = !empty($_POST['community_id']) ? (int) $_POST['community_id'] : null;
        $tags = trim((string) ($request->post('tags') ?? $_POST['tags'] ?? ''));
        $language = trim((string) ($request->post('language') ?? $_POST['language'] ?? 'en'));
        $allowDownload = isset($_POST['allow_download']) ? 1 : 0;
        $copyrightConfirmed = !empty($_POST['copyright_confirmed']);

        // Optional anonymous contact details (protected from public display)
        $submitterName = trim((string) ($request->post('submitter_name') ?? $_POST['submitter_name'] ?? ''));
        $submitterEmail = trim((string) ($request->post('submitter_email') ?? $_POST['submitter_email'] ?? ''));

        if ($account === null && $submitterEmail !== '' && !filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['submitter_email'] = 'Please enter a valid email address or leave the field blank.';
        }

        if ($title === '') {
            $errors['title'] = 'Please enter a book title.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title cannot exceed 255 characters.';
        }

        if ($authorName === '') {
            $errors['author_name'] = 'Please specify the author name.';
        } elseif (mb_strlen($authorName) > 255) {
            $errors['author_name'] = 'Author name cannot exceed 255 characters.';
        }

        if ($description === '') {
            $errors['description'] = 'Please provide a synopsis or description for the book.';
        } elseif (mb_strlen($description) < 10) {
            $errors['description'] = 'Description must be at least 10 characters long.';
        }

        if ($categoryId <= 0) {
            $errors['category_id'] = 'Please select a valid category.';
        }

        if (!$copyrightConfirmed) {
            $errors['copyright_confirmed'] = 'You must confirm that you have rights to share this work or it is public domain.';
        }

        if (empty($_FILES['ebook_file']) || (int) ($_FILES['ebook_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['ebook_file'] = 'Please select a PDF or EPUB file to upload.';
        }

        if (!empty($errors)) {
            if ($isAjax) {
                return $this->jsonResponse(422, ['errors' => $errors]);
            }
            $categories = $this->categories->listActive();
            $rooms = $this->community !== null ? $this->community->getChatRooms() : [];
            return $this->render('ebooks_upload', [
                'pageTitle' => 'Upload an eBook — Community Library',
                'metaDescription' => 'Share a digital book with the Infinity Blog community.',
                'activeNav' => 'ebooks',
                'categories' => $categories,
                'communities' => $rooms,
                'account' => $account,
                'isAnonymous' => $account === null,
                'submitted' => false,
                'submissionRef' => null,
                'errors' => $errors,
                'formData' => $_POST,
            ], 422);
        }

        try {
            // Process the eBook binary (supports null user for anonymous uploads)
            $bookMeta = $this->ebookFileService->processUploadedBook($_FILES['ebook_file'], $account?->id);

            // Handle cover image
            $coverPath = null;
            if (!empty($_FILES['cover_image']) && (int) ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $coverPath = $this->ebookFileService->processUploadedCover($_FILES['cover_image'], $account?->id);
            } elseif (!empty($bookMeta['extracted_cover'])) {
                $coverPath = $this->ebookFileService->saveExtractedCover(
                    $bookMeta['extracted_cover'],
                    $bookMeta['extracted_cover_mime'] ?? 'image/jpeg',
                    $account?->id
                );
            }

            // All public & member submissions enter 'pending' status for moderation
            $ebookId = $this->ebooks->create($account?->id, [
                'title' => $title,
                'author_name' => $authorName,
                'description' => $description,
                'submitter_name' => $account !== null ? $account->displayName : ($submitterName !== '' ? $submitterName : null),
                'submitter_email' => $account !== null ? $account->email : ($submitterEmail !== '' ? $submitterEmail : null),
                'category_id' => $categoryId,
                'community_id' => $communityId,
                'file_path' => $bookMeta['file_path'],
                'format' => $bookMeta['format'],
                'file_size' => $bookMeta['file_size'],
                'cover_path' => $coverPath,
                'mime_type' => $bookMeta['mime_type'],
                'language' => $language !== '' ? $language : 'en',
                'tags' => $tags !== '' ? $tags : null,
                'allow_download' => $allowDownload,
                'status' => 'pending',
            ]);

            $submissionRef = 'EB-' . gmdate('Y') . '-' . str_pad((string) $ebookId, 5, '0', STR_PAD_LEFT);

            if ($isAjax) {
                return $this->jsonResponse(201, [
                    'success' => true,
                    'ebook_id' => $ebookId,
                    'reference' => $submissionRef,
                    'message' => 'Thank you! Your eBook has been submitted and is pending moderation review before public listing.',
                    'redirect' => '/ebooks/upload?submitted=1&ref=' . urlencode($submissionRef),
                ]);
            }

            return $this->redirect('/ebooks/upload?submitted=1&ref=' . urlencode($submissionRef));
        } catch (\InfinityBlog\Ebooks\EbookValidationException $e) {
            if ($isAjax) {
                return $this->jsonResponse(422, ['errors' => $e->getErrors()]);
            }
            $categories = $this->categories->listActive();
            $rooms = $this->community !== null ? $this->community->getChatRooms() : [];
            return $this->render('ebooks_upload', [
                'pageTitle' => 'Upload an eBook — Community Library',
                'metaDescription' => 'Share a digital book with the Infinity Blog community.',
                'activeNav' => 'ebooks',
                'categories' => $categories,
                'communities' => $rooms,
                'account' => $account,
                'isAnonymous' => $account === null,
                'submitted' => false,
                'submissionRef' => null,
                'errors' => $e->getErrors(),
                'formData' => $_POST,
            ], 422);
        } catch (Throwable $e) {
            if ($isAjax) {
                return $this->jsonResponse(500, ['error' => 'An error occurred while saving your eBook: ' . $e->getMessage()]);
            }
            $categories = $this->categories->listActive();
            $rooms = $this->community !== null ? $this->community->getChatRooms() : [];
            return $this->render('ebooks_upload', [
                'pageTitle' => 'Upload an eBook — Community Library',
                'metaDescription' => 'Share a digital book with the Infinity Blog community.',
                'activeNav' => 'ebooks',
                'categories' => $categories,
                'communities' => $rooms,
                'account' => $account,
                'isAnonymous' => $account === null,
                'submitted' => false,
                'submissionRef' => null,
                'errors' => ['general' => 'Upload failed. Please try again.'],
                'formData' => $_POST,
            ], 500);
        }
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookDetail(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null) {
            return $this->notFound('The requested eBook could not be found or has been restricted.');
        }

        $isSaved = $viewerId !== null ? $this->ebooks->isBookSaved($viewerId, $id) : false;
        $readingProgress = $viewerId !== null ? $this->ebooks->getReadingProgress($viewerId, $id) : null;
        $relatedBooksResult = $this->ebooks->listPublic([
            'category_id' => $ebook['category_id'],
            'exclude_id' => $id,
        ], 1, 4);

        return $this->render('ebooks_detail', [
            'pageTitle' => $ebook['title'] . ' by ' . $ebook['author_name'] . ' — eBook Library',
            'metaDescription' => mb_substr(strip_tags($ebook['description']), 0, 160),
            'activeNav' => 'ebooks',
            'ebook' => $ebook,
            'isSaved' => $isSaved,
            'readingProgress' => $readingProgress,
            'relatedBooks' => $relatedBooksResult['items'],
            'canManage' => $viewerId !== null && ($viewerId === (int) $ebook['user_id'] || $isAdmin),
            'uploadedSuccess' => isset($_GET['uploaded']),
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookReader(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null) {
            return $this->notFound('The requested eBook could not be found or has been restricted.');
        }

        // Increment genuine read count
        $this->ebooks->incrementReadCount($id);

        $readingProgress = $viewerId !== null ? $this->ebooks->getReadingProgress($viewerId, $id) : null;

        $manifest = null;
        if ($ebook['format'] === 'epub') {
            $diskPath = $this->ebookFileService->resolveDiskPath($ebook['file_path']);
            if ($diskPath !== null && is_file($diskPath)) {
                $manifest = \InfinityBlog\Ebooks\EpubParser::getManifest($diskPath);
            }
        }

        return $this->render('ebooks_reader', [
            'pageTitle' => 'Reading: ' . $ebook['title'] . ' — Infinity Blog Reader',
            'metaDescription' => 'Reading ' . $ebook['title'] . ' online in browser.',
            'activeNav' => 'ebooks',
            'ebook' => $ebook,
            'readingProgress' => $readingProgress,
            'manifest' => $manifest,
        ]);
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function ebookReadFile(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null) {
            return $this->notFound('File not found or access restricted.');
        }

        $diskPath = $this->ebookFileService->resolveDiskPath($ebook['file_path']);
        if ($diskPath === null || !is_file($diskPath)) {
            return $this->notFound('eBook file is not present on storage.');
        }

        if (ob_get_level() > 0) {
            @ob_clean();
        }

        $fileSize = filesize($diskPath);
        $mime = !empty($ebook['mime_type']) ? (string) $ebook['mime_type'] : ($ebook['format'] === 'pdf' ? 'application/pdf' : 'application/epub+zip');
        $content = file_get_contents($diskPath);

        return [
            'status' => 200,
            'type' => $mime,
            'headers' => [
                'Content-Disposition' => 'inline; filename="book_' . $id . '.' . $ebook['format'] . '"',
                'Content-Length' => (string) $fileSize,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=86400',
            ],
            'body' => $content !== false ? $content : '',
        ];
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function ebookDownload(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null) {
            return $this->notFound('eBook not found.');
        }

        if (!$ebook['allow_download'] && !$isAdmin && ($viewerId === null || $viewerId !== (int) $ebook['user_id'])) {
            return [
                'status' => 403,
                'type' => 'text/html; charset=utf-8',
                'body' => '<!DOCTYPE html><html><body><h1>403 Forbidden</h1><p>Downloads have been restricted by the author for this eBook.</p><a href="/ebooks/' . $id . '">Back to Book</a></body></html>',
            ];
        }

        $diskPath = $this->ebookFileService->resolveDiskPath($ebook['file_path']);
        if ($diskPath === null || !is_file($diskPath)) {
            return $this->notFound('File missing from storage.');
        }

        if (ob_get_level() > 0) {
            @ob_clean();
        }

        // Increment genuine download count
        $this->ebooks->incrementDownloadCount($id);

        $fileSize = filesize($diskPath);
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $ebook['title']);
        $safeTitle = trim(preg_replace('/\s+/', '_', $safeTitle));
        if ($safeTitle === '') {
            $safeTitle = 'book_' . $id;
        }
        $filename = $safeTitle . '.' . $ebook['format'];

        $content = file_get_contents($diskPath);

        $mime = !empty($ebook['mime_type']) ? (string) $ebook['mime_type'] : ($ebook['format'] === 'pdf' ? 'application/pdf' : 'application/epub+zip');

        return [
            'status' => 200,
            'type' => $mime,
            'headers' => [
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) $fileSize,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-transform, no-store',
            ],
            'body' => $content !== false ? $content : '',
        ];
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookManifest(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null || $ebook['format'] !== 'epub') {
            return $this->jsonResponse(404, ['error' => 'EPUB manifest not found.']);
        }

        $diskPath = $this->ebookFileService->resolveDiskPath($ebook['file_path']);
        if ($diskPath === null || !is_file($diskPath)) {
            return $this->jsonResponse(404, ['error' => 'EPUB file not found on storage.']);
        }

        $manifest = \InfinityBlog\Ebooks\EpubParser::getManifest($diskPath);
        return $this->jsonResponse(200, $manifest);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookChapter(Request $request, int $id, int $chapterIndex): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null || $ebook['format'] !== 'epub') {
            return $this->jsonResponse(404, ['error' => 'EPUB not found.']);
        }

        $diskPath = $this->ebookFileService->resolveDiskPath($ebook['file_path']);
        if ($diskPath === null || !is_file($diskPath)) {
            return $this->jsonResponse(404, ['error' => 'EPUB file not found on storage.']);
        }

        $chapter = \InfinityBlog\Ebooks\EpubParser::getChapterHtml($diskPath, $chapterIndex, $id);
        if (isset($chapter['error'])) {
            return $this->jsonResponse(404, $chapter);
        }

        return $this->jsonResponse(200, $chapter);
    }

    /** @return array{status: int, type: string, body: string, headers?: array<string, string>} */
    public function ebookEpubAsset(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        $viewerId = $account?->id;
        $isAdmin = ($account?->role ?? '') === 'admin';

        $ebook = $this->ebooks->findPublished($id, $viewerId, $isAdmin);
        if ($ebook === null || $ebook['format'] !== 'epub') {
            return $this->notFound('EPUB not found.');
        }

        $diskPath = $this->ebookFileService->resolveDiskPath($ebook['file_path']);
        if ($diskPath === null || !is_file($diskPath)) {
            return $this->notFound('File not found.');
        }

        $assetPath = (string) ($request->query('path') ?? ($_GET['path'] ?? ''));
        if ($assetPath === '') {
            return $this->notFound('Asset path required.');
        }

        $asset = \InfinityBlog\Ebooks\EpubParser::getAsset($diskPath, $assetPath);
        if ($asset === null) {
            return $this->notFound('Asset not found inside EPUB.');
        }

        return [
            'status' => 200,
            'type' => $asset['mime'],
            'headers' => [
                'Cache-Control' => 'public, max-age=604800, immutable',
                'Content-Length' => (string) strlen($asset['data']),
            ],
            'body' => $asset['data'],
        ];
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookSaveProgress(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => 'Authentication required to save reading progress.']);
        }

        $input = $request->body();
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }

        $lastPage = isset($input['last_page']) ? (int) $input['last_page'] : null;
        $lastChapter = isset($input['last_chapter']) ? (int) $input['last_chapter'] : null;
        $percentage = isset($input['percentage']) ? (float) $input['percentage'] : 0.0;

        $progress = $this->ebooks->saveReadingProgress($account->id, $id, $lastPage, $lastChapter, $percentage);

        return $this->jsonResponse(200, [
            'success' => true,
            'progress' => $progress,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookSaveToggle(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => 'Authentication required to bookmark eBooks.']);
        }

        if ($this->ebooks->isBookSaved($account->id, $id)) {
            $this->ebooks->unsaveBook($account->id, $id);
            $saved = false;
            $msg = 'Removed from your reading library.';
        } else {
            $this->ebooks->saveBook($account->id, $id);
            $saved = true;
            $msg = 'Saved to your reading library.';
        }

        return $this->jsonResponse(200, [
            'saved' => $saved,
            'message' => $msg,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookSubmitReport(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->jsonResponse(401, ['error' => 'Authentication required to report an eBook.']);
        }

        $input = $request->body();
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        $explanation = trim((string) ($input['explanation'] ?? ''));

        if ($reason === '') {
            return $this->jsonResponse(422, ['error' => 'Please select a reason for reporting this eBook.']);
        }

        $report = $this->ebooks->createReport($id, $account->id, $reason, $explanation !== '' ? $explanation : null);

        return $this->jsonResponse(201, [
            'success' => true,
            'message' => 'Thank you for your report. Our moderation team has been notified.',
            'report_id' => $report['id'],
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebooksManage(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login?redirect=' . urlencode('/ebooks/manage'));
        }

        $myBooks = $this->ebooks->listForUser($account->id);

        return $this->render('ebooks_manage', [
            'pageTitle' => 'My Uploaded eBooks — Author Dashboard',
            'metaDescription' => 'Manage your uploaded community eBooks, track reads, and edit visibility.',
            'activeNav' => 'ebooks',
            'books' => $myBooks,
            'deletedSuccess' => isset($_GET['deleted']),
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function ebookDelete(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->redirect('/login');
        }

        $isAdmin = ($account->role ?? '') === 'admin';
        $ebook = $this->ebooks->findById($id);

        if ($ebook === null) {
            return $this->notFound('eBook not found.');
        }

        if (!$isAdmin && (int) $ebook['user_id'] !== $account->id) {
            return $this->jsonResponse(403, ['error' => 'You do not have permission to delete this eBook.']);
        }

        // Clean up files
        $this->ebookFileService->deleteFile($ebook['file_path']);
        if (!empty($ebook['cover_path'])) {
            $this->ebookFileService->deleteCover($ebook['cover_path']);
        }

        $this->ebooks->delete($id, $account->id, $isAdmin);

        return $this->redirect('/ebooks/manage?deleted=1');
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminReportedEbooks(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || ($account->role ?? '') !== 'admin') {
            return $this->redirect('/login?redirect=' . urlencode('/admin/reported-ebooks-page'));
        }

        $status = $request->query('status') ?? ($_GET['status'] ?? null);
        $status = is_string($status) && in_array($status, ['pending', 'reviewed', 'dismissed'], true) ? $status : null;
        $page = max(1, (int) ($request->query('page') ?? ($_GET['page'] ?? 1)));

        $reportsResult = $this->ebooks->listReports($status, $page, 20);

        return $this->render('admin_reported_ebooks', [
            'pageTitle' => 'Reported eBooks Moderation — Admin',
            'activeNav' => 'reported_ebooks',
            'reports' => $reportsResult['items'],
            'pagination' => $reportsResult['pagination'],
            'currentStatus' => $status,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminUpdateEbookReportStatus(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || ($account->role ?? '') !== 'admin') {
            return $this->jsonResponse(403, ['error' => 'Admin authorization required.']);
        }

        $input = $request->body();
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }

        $status = (string) ($input['status'] ?? 'reviewed');
        $action = isset($input['action']) ? (string) $input['action'] : null;

        $updated = $this->ebooks->updateReportStatus($id, $status, $account->id, $action);

        return $this->jsonResponse(200, [
            'success' => $updated,
            'message' => 'Report status updated successfully.',
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminEbookModeration(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || ($account->role ?? '') !== 'admin') {
            return $this->redirect('/login?redirect=' . urlencode('/admin/ebook-moderation'));
        }

        $status = $request->query('status') ?? ($_GET['status'] ?? 'pending');
        $status = is_string($status) && in_array($status, ['pending', 'published', 'rejected', 'hidden', 'all'], true) ? $status : 'pending';
        $page = max(1, (int) ($request->query('page') ?? ($_GET['page'] ?? 1)));

        $submissionsData = $this->ebooks->listSubmissions($status, $page, 15);

        return $this->render('admin_ebook_moderation', [
            'pageTitle' => 'eBook Moderation & Review — Admin Portal',
            'metaDescription' => 'Review community-submitted eBooks, approve for public catalogue, or reject submissions.',
            'activeNav' => 'ebook_moderation',
            'statusFilter' => $status,
            'submissions' => $submissionsData['items'],
            'total' => $submissionsData['total'],
            'page' => $submissionsData['page'],
            'totalPages' => $submissionsData['total_pages'],
            'approvedCount' => isset($_GET['approved']) ? 1 : 0,
            'rejectedCount' => isset($_GET['rejected']) ? 1 : 0,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminApproveEbook(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || ($account->role ?? '') !== 'admin') {
            return $this->jsonResponse(403, ['error' => 'Admin authorization required.']);
        }

        $csrf = $request->post('csrf_token') ?? $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => 'Invalid or expired CSRF token.']);
        }

        $success = $this->ebooks->approveSubmission($id, $account->id);

        if ($request->isJson() || str_contains((string) $request->header('Accept'), 'application/json')) {
            return $this->jsonResponse(200, ['success' => $success, 'message' => 'eBook approved and published to public catalog.']);
        }

        return $this->redirect('/admin/ebook-moderation?status=pending&approved=1');
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminRejectEbook(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || ($account->role ?? '') !== 'admin') {
            return $this->jsonResponse(403, ['error' => 'Admin authorization required.']);
        }

        $csrf = $request->post('csrf_token') ?? $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => 'Invalid or expired CSRF token.']);
        }

        $reason = trim((string) ($request->post('reason') ?? $_POST['reason'] ?? ''));
        $success = $this->ebooks->rejectSubmission($id, $account->id, $reason !== '' ? $reason : null);

        if ($request->isJson() || str_contains((string) $request->header('Accept'), 'application/json')) {
            return $this->jsonResponse(200, ['success' => $success, 'message' => 'eBook submission rejected.']);
        }

        return $this->redirect('/admin/ebook-moderation?status=pending&rejected=1');
    }

    /** @return array{status: int, type: string, body: string} */
    public function adminUpdateEbookStatus(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || ($account->role ?? '') !== 'admin') {
            return $this->jsonResponse(403, ['error' => 'Admin authorization required.']);
        }

        $csrf = $request->post('csrf_token') ?? $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->jsonResponse(403, ['error' => 'Invalid or expired CSRF token.']);
        }

        $status = trim((string) ($request->post('status') ?? $_POST['status'] ?? 'hidden'));
        $success = $this->ebooks->updateStatus($id, $status);

        if ($request->isJson() || str_contains((string) $request->header('Accept'), 'application/json')) {
            return $this->jsonResponse(200, ['success' => $success, 'status' => $status]);
        }

        return $this->redirect('/admin/ebook-moderation');
    }
}
