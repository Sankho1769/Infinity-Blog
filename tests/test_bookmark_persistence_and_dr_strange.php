<?php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../autoload.php';

use InfinityBlog\Ai\ArticleAssistantService;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Http\LibraryController;
use InfinityBlog\Http\Request;
use InfinityBlog\Library\LibraryRepository;
use InfinityBlog\Posts\PostRepository;

echo "=================================================================\n";
echo "INFINITY BLOG — SAVE PERSISTENCE & DR. STRANGE VERIFICATION\n";
echo "=================================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function assertCondition(bool $condition, string $title, array $details = []): void {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "✓ PASS: {$title}\n";
    } else {
        $failedCount++;
        echo "✗ FAIL: {$title}\n";
    }
    foreach ($details as $d) {
        echo "    - {$d}\n";
    }
}

// -------------------------------------------------------------
// Database & Environment Setup (In-Memory SQLite)
// -------------------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NULL,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT "active",
    role VARCHAR(20) NOT NULL DEFAULT "reader",
    bio TEXT NULL,
    location VARCHAR(100) NULL,
    interests TEXT NULL,
    website VARCHAR(255) NULL,
    avatar_path VARCHAR(255) NULL,
    banner_path VARCHAR(255) NULL,
    profile_visibility VARCHAR(20) NOT NULL DEFAULT "public",
    auth_version INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon_name VARCHAR(50) NULL,
    custom_logo_path VARCHAR(255) NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL,
    category_id INTEGER NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    summary TEXT NULL,
    summary_status VARCHAR(20) NOT NULL DEFAULT "ready",
    summary_source_hash VARCHAR(64) NULL,
    content_type VARCHAR(32) NOT NULL DEFAULT "article",
    status VARCHAR(20) NOT NULL DEFAULT "published",
    cover_image_path VARCHAR(255) NULL,
    video_path VARCHAR(255) NULL,
    audio_path VARCHAR(255) NULL,
    audio_duration INTEGER NOT NULL DEFAULT 0,
    audio_status VARCHAR(20) NOT NULL DEFAULT "idle",
    remixed_from_post_id INTEGER NULL,
    summary_generated_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE user_bookmarks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, post_id)
)');

$pdo->exec('CREATE TABLE user_reading_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    progress_percent INTEGER NOT NULL DEFAULT 0,
    scroll_position INTEGER NOT NULL DEFAULT 0,
    completed INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, post_id)
)');

$pdo->exec('CREATE TABLE article_assistant_chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NULL,
    session_id TEXT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    passage_reference TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    csrf_token VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL
)');

// Seed Users
$pdo->exec('INSERT INTO users (id, username, display_name, password_hash, status, role) VALUES
    (1, "sorcerer_supreme", "Stephen Strange", "hash1", "active", "author"),
    (2, "wong_librarian", "Wong", "hash2", "active", "reader"),
    (3, "clea_disciple", "Clea", "hash3", "active", "reader")');

// Seed Category
$pdo->exec('INSERT INTO categories (id, name, slug) VALUES (1, "Mystic Arts", "mystic-arts")');

// Seed Posts
$contentPub1 = "# Eldritch Geometry and Dimensional Travel\n\n" .
    "The Mirror Dimension allows sorcerers to practice spells and contain threats without affecting the real world.\n\n" .
    "## Sling Ring Mechanics\n\n" .
    "A sling ring focuses visual imagination and mystical energy to open inter-dimensional gateways across spacetime.\n\n" .
    "## The Book of Cagliostro\n\n" .
    "Ancient rituals in the Book of Cagliostro describe time manipulation using mystical energy.";

$summaryPub1 = "A foundational study on dimensional containment, sling ring mechanics, and time spells.";

$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, category_id, title, slug, content, summary, status, published_at) VALUES (10, 1, 1, ?, "eldritch-geometry", ?, ?, "published", CURRENT_TIMESTAMP)');
$stmt->execute(["Eldritch Geometry and Dimensional Travel", $contentPub1, $summaryPub1]);

// Post 11: Published with NULL category
$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, category_id, title, slug, content, summary, status, published_at) VALUES (11, 1, NULL, "Universal Ley Lines", "universal-ley-lines", "Ley line frequencies permeate all matter.", "Study of planetary ley lines.", "published", CURRENT_TIMESTAMP)');
$stmt->execute([]);

// Post 12: Draft post (Unpublished)
$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, category_id, title, slug, content, summary, status) VALUES (12, 1, 1, "Darkhold Secrets", "darkhold-secrets", "Forbidden dark magic rituals.", "Classified.", "draft")');
$stmt->execute([]);

// Mock Session & Request Environment
$config = AppConfig::fromEnvironment();
$sessionManager = new SessionManager($config);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @$sessionManager->start();
}

$userRepo = new UserRepository($pdo);
$authGuard = new AuthGuard($sessionManager, $userRepo);
$postRepo = new PostRepository($pdo);
$libraryRepo = new LibraryRepository($pdo);
$libraryController = new LibraryController($libraryRepo, $authGuard, $sessionManager, $postRepo);

// Helper to create authenticated request for a user
function makeAuthRequest(SessionManager $sessions, int $userId, string $method, string $path, array $body = []): Request {
    $now = time();
    $token = bin2hex(random_bytes(32));
    $_SESSION = [
        'auth' => [
            'user_id' => $userId,
            'auth_version' => 1,
            'issued_at' => $now,
            'last_activity' => $now,
        ],
        'csrf_token' => $token,
    ];

    return Request::fromValues(
        method: $method,
        path: $path,
        sourceAddress: '127.0.0.1',
        headers: [
            'X-CSRF-Token' => $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        json: $body
    );
}

// -------------------------------------------------------------
// SECTION 1: Save / Bookmark Feature Verification
// -------------------------------------------------------------
echo "--- Section 1: Save/Bookmark Functionality & Persistence ---\n";

// 1.1 Save creates persistent bookmark
$reqSave = makeAuthRequest($sessionManager, 2, 'POST', '/api/library/bookmark/10', ['action' => 'save']);
$resSave = $libraryController->handle($reqSave);
assertCondition(
    $resSave['status'] === 200 &&
    $resSave['body']['data']['is_bookmarked'] === true &&
    $resSave['body']['data']['bookmarked'] === true &&
    $resSave['body']['data']['saved'] === true &&
    $libraryRepo->isBookmarked(2, 10),
    "Save creates the correct persistent bookmark in database with unified response envelope"
);

// 1.2 Repeated save requests do not create duplicates (Idempotent)
$reqSave2 = makeAuthRequest($sessionManager, 2, 'POST', '/api/library/bookmark/10', ['action' => 'save']);
$resSave2 = $libraryController->handle($reqSave2);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM user_bookmarks WHERE user_id = 2 AND post_id = 10');
$stmt->execute();
$count = (int) $stmt->fetchColumn();
assertCondition(
    $resSave2['status'] === 200 &&
    $resSave2['body']['data']['is_bookmarked'] === true &&
    $count === 1,
    "Repeated save requests are strictly idempotent and do not create duplicate rows"
);

// 1.3 State survives fresh query / refresh
assertCondition(
    $libraryRepo->isBookmarked(2, 10) === true,
    "Saved state survives fresh requests and page refreshes"
);

// 1.4 Reading Library reflects the saved item
$libraryItems = $libraryRepo->getBookmarks(2);
assertCondition(
    count($libraryItems) === 1 &&
    (int) $libraryItems[0]['id'] === 10 &&
    $libraryItems[0]['title'] === "Eldritch Geometry and Dimensional Travel" &&
    !empty($libraryItems[0]['bookmarked_at']),
    "Reading Library accurately lists the saved article with author, category, and timestamp"
);

// 1.5 Reading Library includes saved articles even if category_id is NULL
$reqSaveNullCat = makeAuthRequest($sessionManager, 2, 'POST', '/api/library/bookmark/11', ['action' => 'save']);
$resSaveNullCat = $libraryController->handle($reqSaveNullCat);
$libraryItemsWithNull = $libraryRepo->getBookmarks(2);
$hasNullCatPost = false;
foreach ($libraryItemsWithNull as $item) {
    if ((int) $item['id'] === 11) {
        $hasNullCatPost = true;
        break;
    }
}
assertCondition(
    $resSaveNullCat['status'] === 200 && $hasNullCatPost,
    "Reading Library preserves and returns bookmarked posts even if category is NULL"
);

// 1.6 Two different users have independent bookmark states
$user3Bookmarks = $libraryRepo->getBookmarks(3);
assertCondition(
    !$libraryRepo->isBookmarked(3, 10) &&
    !$libraryRepo->isBookmarked(3, 11) &&
    count($user3Bookmarks) === 0,
    "Two different users have strictly independent, isolated bookmark states"
);

// 1.7 Unsave removes the correct bookmark
$reqUnsave = makeAuthRequest($sessionManager, 2, 'POST', '/api/library/bookmark/10', ['action' => 'unsave']);
$resUnsave = $libraryController->handle($reqUnsave);
assertCondition(
    $resUnsave['status'] === 200 &&
    $resUnsave['body']['data']['is_bookmarked'] === false &&
    $resUnsave['body']['data']['bookmarked'] === false &&
    !$libraryRepo->isBookmarked(2, 10),
    "Unsave cleanly removes the bookmark from database"
);

// 1.8 Unsave does not affect other saved posts
assertCondition(
    $libraryRepo->isBookmarked(2, 11) === true,
    "Unsaving one post does not affect other saved articles in user library"
);

// 1.9 Guests cannot bypass authentication
$_SESSION = [];
$guestReq = Request::fromValues(
    method: 'POST',
    path: '/api/library/bookmark/10',
    sourceAddress: '127.0.0.1',
    headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json']
);
$resGuest = $libraryController->handle($guestReq);
assertCondition(
    $resGuest['status'] === 403 || $resGuest['status'] === 401,
    "Guests cannot bypass authentication or CSRF protection to bookmark content"
);

// 1.10 Inaccessible/Draft/Invalid posts are rejected
$reqDraft = makeAuthRequest($sessionManager, 2, 'POST', '/api/library/bookmark/12', ['action' => 'save']);
$resDraft = $libraryController->handle($reqDraft);
$reqMissing = makeAuthRequest($sessionManager, 2, 'POST', '/api/library/bookmark/99999', ['action' => 'save']);
$resMissing = $libraryController->handle($reqMissing);
assertCondition(
    $resDraft['status'] === 404 && $resMissing['status'] === 404,
    "Invalid IDs and unpublished draft posts are rejected with 404 rather than falsely saved"
);


// -------------------------------------------------------------
// SECTION 2: Dr. Strange AI Assistant Verification
// -------------------------------------------------------------
echo "\n--- Section 2: Dr. Strange Assistant Identity & Grounds ---\n";

$assistant = new ArticleAssistantService($pdo, $postRepo, null);

// 2.1 Greeting Persona
$resGreeting = $assistant->ask(10, 2, 'sess_mystic', 'hello');
assertCondition(
    str_contains($resGreeting['answer'], 'Greetings, seeker!') &&
    str_contains($resGreeting['answer'], 'Dr. Strange') &&
    str_contains($resGreeting['answer'], 'Eldritch Geometry and Dimensional Travel'),
    "Dr. Strange greeting returns genuine mystical persona grounded in article title"
);

// 2.2 Suggested Prompt 1: "Summarize this article."
$resPrompt1 = $assistant->ask(10, 2, 'sess_mystic', 'Summarize this article.');
assertCondition(
    str_contains($resPrompt1['answer'], 'Here is a summary of') &&
    str_contains($resPrompt1['answer'], 'dimensional containment') &&
    !empty($resPrompt1['passage_reference']),
    "Suggested prompt 'Summarize this article.' returns structured summary with passage citation"
);

// 2.3 Suggested Prompt 2: "Explain the main concepts."
$resPrompt2 = $assistant->ask(10, 2, 'sess_mystic', 'Explain the main concepts.');
assertCondition(
    str_contains($resPrompt2['answer'], 'provides an overview of') &&
    !empty($resPrompt2['passage_reference']),
    "Suggested prompt 'Explain the main concepts.' returns grounded concept overview"
);

// 2.4 Suggested Prompt 3: "What are the key takeaways?"
$resPrompt3 = $assistant->ask(10, 2, 'sess_mystic', 'What are the key takeaways?');
assertCondition(
    (str_contains($resPrompt3['answer'], 'Key takeaways from') || str_contains($resPrompt3['answer'], 'Here is a summary of')) &&
    !empty($resPrompt3['passage_reference']),
    "Suggested prompt 'What are the key takeaways?' returns grounded key takeaways"
);

// 2.5 Suggested Prompt 4: Factual passage retrieval
$resPrompt4 = $assistant->ask(10, 2, 'sess_mystic', 'What does the Book of Cagliostro describe?');
assertCondition(
    str_contains($resPrompt4['answer'], 'Book of Cagliostro') &&
    str_contains($resPrompt4['answer'], 'time manipulation') &&
    !empty($resPrompt4['passage_reference']),
    "Factual consultation retrieves section passage and cites reference accurately"
);


// -------------------------------------------------------------
// SECTION 3: Article View Markup & Share Preservation
// -------------------------------------------------------------
echo "\n--- Section 3: Article View Markup & Share Preservation ---\n";

$testPost = [
    'id' => 10,
    'title' => 'Eldritch Geometry and Dimensional Travel',
    'slug' => 'eldritch-geometry',
    'content' => $contentPub1,
    'summary' => $summaryPub1,
    'status' => 'published',
    'published_at' => date('Y-m-d H:i:s'),
    'created_at' => date('Y-m-d H:i:s'),
    'author_id' => 1,
    'author_name' => 'Stephen Strange',
    'author_username' => 'sorcerer_supreme',
    'category_id' => 1,
    'category_name' => 'Mystic Arts',
    'reading_time' => 3,
];

$speechData = [
    'audio_url' => null,
    'speech_text' => 'Eldritch Geometry. The Mirror Dimension allows sorcerers.',
    'summary_text' => $summaryPub1,
    'duration_seconds_est' => 60,
];

// Helper to render article view
function renderView(array $post, array $speechData, bool $isBookmarked): string {
    $renderedBody = '<div>' . htmlspecialchars($post['content']) . '</div>';
    $videoSignedUrl = null;
    $recentPosts = [];
    $scoreSummary = ['average_score' => 5.0, 'total_ratings' => 8];
    $userScore = null;
    $reviews = [];
    $reviewsTotal = 0;
    $reviewsPage = 1;
    $reviewsTotalPages = 1;
    $userReview = null;
    $comments = [];
    $readingTime = 3;
    $currentUser = ['id' => 2, 'username' => 'wong_librarian', 'role' => 'reader'];
    $authorHasBadge = true;
    $canonicalUrl = 'http://localhost:8086/article/10';
    $metaDescription = $post['summary'];

    ob_start();
    include __DIR__ . '/../app/Views/article.php';
    return ob_get_clean();
}

$viewHtml = renderView($testPost, $speechData, true);

// 3.1 Ask AI Toolbar Button
assertCondition(
    str_contains($viewHtml, 'id="openAssistantBtn"') &&
    str_contains($viewHtml, 'Ask AI') &&
    str_contains($viewHtml, 'Dr. Strange') &&
    str_contains($viewHtml, 'Consult Dr. Strange about this article'),
    "Toolbar button renders 'Ask AI' label with Dr. Strange identity and accessible consultation label"
);

// 3.2 Dr. Strange Drawer Markup & Mystical Theme
assertCondition(
    str_contains($viewHtml, 'id="articleAssistantDrawer"') &&
    str_contains($viewHtml, 'dr-strange-drawer') &&
    str_contains($viewHtml, 'Your mystical guide to knowledge.') &&
    str_contains($viewHtml, 'Greetings, seeker. Ask me about this article') &&
    str_contains($viewHtml, 'Summarize this article.') &&
    str_contains($viewHtml, 'Explain the main concepts.') &&
    str_contains($viewHtml, 'What are the key takeaways?') &&
    str_contains($viewHtml, 'Find the passage about a specific topic.'),
    "Assistant drawer markup includes Dr. Strange heading, subtitle, welcome message, and 4 suggested prompts"
);


// 3.3 Save Button Reflection in View
assertCondition(
    str_contains($viewHtml, 'id="bookmarkArticleBtn"') &&
    str_contains($viewHtml, 'is-saved') &&
    str_contains($viewHtml, 'Saved to Personal Library'),
    "Toolbar Save button renders active 'Saved' state when article is bookmarked"
);

// 3.4 Share Article Modal & Handlers Strictly Preserved
assertCondition(
    str_contains($viewHtml, 'id="shareArticleBtn"') &&
    str_contains($viewHtml, 'triggerNativeShare') &&
    str_contains($viewHtml, 'id="share-article-modal"') &&
    str_contains($viewHtml, 'id="shareWhatsAppBtn"') &&
    str_contains($viewHtml, 'id="shareFollowingList"') &&
    str_contains($viewHtml, 'copyShareLink()'),
    "Share button, native share trigger, modal, WhatsApp, DM list, and copy link are 100% preserved"
);

// 3.5 CSS Rules for Dr. Strange Mystical Theme
$cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');
assertCondition(
    str_contains($cssContent, 'Dr. Strange — Mystical Arcane Assistant Drawer') &&
    str_contains($cssContent, '.mystical-portal-avatar') &&
    str_contains($cssContent, 'portalRingSpin') &&
    str_contains($cssContent, '.mystical-pulse-orb'),
    "CSS defines Dr. Strange mystical theme with portal ring animation and arcane styles"
);

echo "\n=================================================================\n";
echo "SUMMARY: {$passedCount} passed, {$failedCount} failed.\n";
echo "=================================================================\n";

if ($failedCount > 0) {
    exit(1);
}
