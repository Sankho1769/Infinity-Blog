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
use InfinityBlog\Users\AdminUserRepository;

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

    public function __construct(
        PostRepository $posts,
        CategoryRepository $categories,
        UserRepository $users,
        AdminUserRepository $adminUsers,
        AuditLogRepository $auditLogs,
        AuthGuard $guard,
        SessionManager $sessions,
        AppConfig $config,
        ?ScoreRepository $scores = null
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

        return $this->render('article', [
            'pageTitle' => $post['title'],
            'activeNav' => 'home',
            'post' => $post,
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
                ];
            } else {
                return $this->notFound('The requested author was not found.');
            }
        }

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

        return $this->render('profile', [
            'pageTitle' => $user->displayName . ' — Profile',
            'activeNav' => 'profile',
            'user' => $user->toPrivateArray(),
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

        if ($request->method === 'POST') {
            $csrf = $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
            if (!$this->sessions->validateCsrfToken($csrf)) {
                return $this->render('profile_edit', [
                    'pageTitle' => 'Edit Profile',
                    'activeNav' => 'profile',
                    'user' => $user->toPrivateArray(),
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
                    'user' => $user->toPrivateArray(),
                    'errors' => $e->errors,
                ]);
            }
        }

        return $this->render('profile_edit', [
            'pageTitle' => 'Edit Profile',
            'activeNav' => 'profile',
            'user' => $user->toPrivateArray(),
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

        $csrf = $_POST['csrf_token'] ?? $request->header('X-CSRF-Token');
        if (!$this->sessions->validateCsrfToken($csrf)) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $user->toPrivateArray(),
                'errors' => ['avatar' => 'Security token invalid or expired.'],
            ]);
        }

        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $user->toPrivateArray(),
                'errors' => ['avatar' => 'Please select a valid image file to upload.'],
            ]);
        }

        $file = $_FILES['avatar'];
        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $maxBytes) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $user->toPrivateArray(),
                'errors' => ['avatar' => 'Image file size must not exceed 2MB.'],
            ]);
        }

        $tmpPath = (string) $file['tmp_name'];
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $user->toPrivateArray(),
                'errors' => ['avatar' => 'The uploaded file is not a valid image.'],
            ]);
        }

        $allowedMimes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $detectedMime = $imageInfo['mime'] ?? '';
        if (!isset($allowedMimes[$detectedMime])) {
            return $this->render('profile_edit', [
                'pageTitle' => 'Edit Profile',
                'activeNav' => 'profile',
                'user' => $user->toPrivateArray(),
                'errors' => ['avatar' => 'Only PNG, JPEG, WebP, and GIF images are permitted.'],
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
                'user' => $user->toPrivateArray(),
                'errors' => ['avatar' => 'Could not save the avatar image. Please try again.'],
            ]);
        }

        $avatarUrl = '/uploads/avatars/' . $filename;
        $this->users->updateAvatar($account->id, $avatarUrl);

        return $this->redirect('/profile?avatar_updated=1');
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
}
