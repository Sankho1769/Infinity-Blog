<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\ProfileValidationException;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Scores\ScoreRepository;
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
        ?MediaService $media = null
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
    }

    /** @return array{status: int, type: string, body: string} */
    public function home(Request $request): array
    {
        $search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : null;
        if ($search === '') {
            $search = null;
        }

        $categorySlug = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : null;
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

        $postResult = $this->posts->listPublic($filters, 1, 24);

        return $this->render('home', [
            'pageTitle' => 'Ideas. Stories. Perspectives.',
            'activeNav' => 'home',
            'posts' => $postResult['posts'],
            'categories' => $activeCategories,
            'activeCategorySlug' => $categorySlug,
            'activeCategoryName' => $activeCatName,
            'searchQuery' => $search,
        ]);
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
        $scoreSummary = $this->scores !== null ? $this->scores->getSummary($id) : ['average' => 0.0, 'count' => 0];
        $userScore = ($this->scores !== null && $account !== null) ? $this->scores->getUserScore($id, $account->id) : null;

        $videoSignedUrl = null;
        if (!empty($post['video_path']) && $this->media !== null && $this->media->getConfig()->isConfigured()) {
            try {
                $videoSignedUrl = $this->media->resolveSignedVideoUrl((string) $post['video_path'], 3600);
            } catch (Throwable) {
            }
        }

        return $this->render('article', [
            'pageTitle' => $post['title'],
            'activeNav' => 'home',
            'post' => $post,
            'videoSignedUrl' => $videoSignedUrl,
            'recentPosts' => array_values(array_slice($recent, 0, 4)),
            'scoreSummary' => $scoreSummary,
            'userScore' => $userScore,
            'currentUser' => $account !== null ? [
                'id' => $account->id,
                'username' => $account->username,
                'display_name' => $account->displayName,
                'role' => $account->role,
            ] : null,
        ]);
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
            'pageTitle' => 'Categories',
            'activeNav' => 'categories',
            'categories' => $categories,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function publicAuthors(Request $request): array
    {
        $authors = $this->users->listPublicAuthors();
        $authors = array_map(function (array $a): array {
            $a['avatar_url'] = $this->resolveAvatarUrl($a['avatar_path'] ?? null);
            $a['banner_url'] = $this->resolveBannerUrl($a['banner_path'] ?? null);
            return $a;
        }, $authors);

        return $this->render('public_authors', [
            'pageTitle' => 'Authors',
            'activeNav' => 'authors',
            'authors' => $authors,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function authorProfile(Request $request, int $id): array
    {
        $author = $this->users->findPublicAuthor($id);
        if ($author === null) {
            $user = $this->users->findById($id);
            if ($user !== null && $user->role !== 'admin' && $user->status === 'active' && $user->profileVisibility !== 'private') {
                $author = [
                    'id' => $user->id,
                    'display_name' => $user->displayName,
                    'username' => $user->username,
                    'role' => $user->role,
                    'post_count' => 0,
                    'bio' => $user->bio,
                    'location' => $user->location,
                    'interests' => $user->interests,
                    'website' => $user->website,
                    'avatar_path' => $user->avatarPath,
                    'banner_path' => $user->bannerPath,
                ];
            } else {
                return $this->notFound('The requested author was not found.');
            }
        }

        $author['avatar_url'] = $this->resolveAvatarUrl($author['avatar_path'] ?? null);
        $author['banner_url'] = $this->resolveBannerUrl($author['banner_path'] ?? null);

        $postsResult = $this->posts->listPublic(['author_id' => $id], 1, 20);

        return $this->render('author_profile', [
            'pageTitle' => $author['display_name'] . ' — Author Profile',
            'activeNav' => 'authors',
            'author' => $author,
            'posts' => $postsResult['posts'],
            'totalPosts' => $postsResult['total'],
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
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $filters = [];
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

        $postsResult = $this->posts->listPublic($filters, $page, 12);
        $categories = $this->categories->listActiveWithCounts();

        return $this->render('explore', [
            'pageTitle' => 'Explore Articles',
            'activeNav' => 'explore',
            'posts' => $postsResult['posts'],
            'totalPosts' => $postsResult['total'],
            'page' => $page,
            'categories' => $categories,
            'activeCategorySlug' => $categorySlug,
            'activeCategoryName' => $activeCategory['name'] ?? null,
            'searchQuery' => $q,
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function about(Request $request): array
    {
        return $this->explore($request);
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

        return $this->render('dashboard', [
            'pageTitle' => 'Dashboard',
            'activeNav' => 'dashboard',
            'counts' => $counts,
            'recentPosts' => $recentPosts,
            'adminCounts' => $adminCounts,
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

        return $this->render('post_editor', [
            'pageTitle' => $remixPost !== null ? 'Remix: ' . $remixPost['title'] : 'Create New Post',
            'activeNav' => 'posts',
            'post' => $initialPost,
            'remixSource' => $remixPost,
            'categories' => $activeCategories,
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

        return $this->render('post_editor', [
            'pageTitle' => 'Edit: ' . $post['title'],
            'activeNav' => 'posts',
            'post' => $post,
            'categories' => $activeCategories,
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
            'pageTitle' => 'All Posts',
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
            'activeNav' => 'categories',
            'categories' => $allCategories,
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
            'activeNav' => 'audit',
            'logs' => $logsList['events'],
        ]);
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
            ],
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

        if ($request->method === 'POST') {
            $csrf = $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
            if (!$this->sessions->validateCsrfToken($csrf)) {
                return $this->render('profile_edit', [
                    'pageTitle' => 'Edit Profile',
                    'activeNav' => 'profile',
                    'user' => $userData,
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
                    'errors' => $e->errors,
                ]);
            }
        }

        return $this->render('profile_edit', [
            'pageTitle' => 'Edit Profile',
            'activeNav' => 'profile',
            'user' => $userData,
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
    public function contact(Request $request): array
    {
        return $this->render('contact', [
            'pageTitle' => 'Contact Us',
            'activeNav' => 'contact',
        ]);
    }

    /** @return array{status: int, type: string, body: string} */
    public function privacy(Request $request): array
    {
        return $this->render('privacy', [
            'pageTitle' => 'Privacy Policy',
            'activeNav' => 'privacy',
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

        $data['currentUser'] = $currentUser;
        $data['csrfToken'] = $csrfToken;
        $data['basePath'] = $basePath;

        extract($data);

        ob_start();
        require dirname(__DIR__) . '/Views/' . $template . '.php';
        $content = ob_get_clean();

        ob_start();
        require dirname(__DIR__) . '/Views/layout.php';
        $html = ob_get_clean();

        return [
            'status' => $status,
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
    private function redirect(string $url): array
    {
        header('Location: ' . $url);
        return [
            'status' => 302,
            'type' => 'text/html; charset=utf-8',
            'body' => '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></head><body>Redirecting...</body></html>',
        ];
    }

    public function resolveAvatarUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }
        if (str_starts_with($path, 'uploads/')) {
            return '/' . $path;
        }
        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            return $this->media->resolvePublicUrl($this->media->getConfig()->bucketAvatars, $path);
        }
        return ArticleImageHelper::resolveAvatarUrl($path);
    }

    public function resolveBannerUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }
        if (str_starts_with($path, 'uploads/')) {
            return '/' . $path;
        }
        if ($this->media !== null && $this->media->getConfig()->isConfigured()) {
            return $this->media->resolvePublicUrl($this->media->getConfig()->bucketBanners, $path);
        }
        return ArticleImageHelper::resolveBannerUrl($path);
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
}
