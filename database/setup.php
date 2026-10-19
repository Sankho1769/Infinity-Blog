<?php
/**
 * Infinity Blog - Database Setup & Administrator Provisioning CLI Script
 * 
 * Provisions database schema, default categories, and initial administrators.
 * Security safeguards:
 * - Enforces CLI-only execution (rejects public web/HTTP access with HTTP 403)
 * - Safe idempotent user provisioning (preserves existing credentials, roles, and statuses)
 * - Restricts sample/demo data to non-production environments
 * - Never prints passwords or secrets to console or web responses
 */

declare(strict_types=1);

// 1. Enforce CLI-only execution safeguard
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Access Denied: Database setup script can only be executed via the command-line interface (CLI).\n";
    exit(1);
}

require_once __DIR__ . '/../backend/config/Env.php';
use InfinityBlog\Config\Env;

Env::load();

$appEnv = strtolower((string)Env::get('APP_ENV', 'development'));
$dbHost = (string)Env::get('DB_HOST', '127.0.0.1');
$dbPort = (string)Env::get('DB_PORT', '3306');
$dbUser = (string)Env::get('DB_USER', 'root');
$dbPass = (string)Env::get('DB_PASS', '');
$dbName = (string)Env::get('DB_NAME', 'infinity_blog');

echo "=== Infinity Blog Database Provisioning ===\n";
echo "Environment: {$appEnv}\n";
echo "Host: {$dbHost}:{$dbPort}\n";
echo "User: {$dbUser}\n";
echo "Database: {$dbName}\n";

// 2. Connect to MySQL server
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// 3. Execute schema.sql
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Error: schema.sql not found at {$schemaFile}\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaFile);
echo "Executing schema.sql...\n";
$pdo->exec($schemaSql);
echo "Schema verified and executed successfully.\n";

$pdo->exec("USE `{$dbName}`;");

// 3.1 Apply non-destructive column migrations
$userColumns = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('gender', $userColumns, true)) {
    echo "Migrating users table: Adding 'gender' column...\n";
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL AFTER `email`;");
}
if (!in_array('date_of_birth', $userColumns, true)) {
    echo "Migrating users table: Adding 'date_of_birth' column...\n";
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `date_of_birth` DATE NULL AFTER `gender`;");
}
if (!in_array('bio', $userColumns, true)) {
    echo "Migrating users table: Adding 'bio' column...\n";
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL AFTER `date_of_birth`;");
}

$postColumns = $pdo->query("SHOW COLUMNS FROM `posts`")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('featured_image', $postColumns, true)) {
    echo "Migrating posts table: Adding 'featured_image' column...\n";
    $pdo->exec("ALTER TABLE `posts` ADD COLUMN `featured_image` VARCHAR(255) NULL AFTER `content`;");
}
if (!in_array('tags', $postColumns, true)) {
    echo "Migrating posts table: Adding 'tags' column...\n";
    $pdo->exec("ALTER TABLE `posts` ADD COLUMN `tags` VARCHAR(255) NULL AFTER `featured_image`;");
}
if (!in_array('views', $postColumns, true)) {
    echo "Migrating posts table: Adding 'views' column...\n";
    $pdo->exec("ALTER TABLE `posts` ADD COLUMN `views` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `tags`;");
}

// 4. Resolve administrator and author credentials
$adminPassword = Env::get('ADMIN_DEFAULT_PASSWORD');
$authorPassword = Env::get('AUTHOR_DEFAULT_PASSWORD');

if ($appEnv === 'production') {
    if (empty($adminPassword) || strlen((string)$adminPassword) < 12 || $adminPassword === 'Admin@Infinity2026!') {
        fwrite(STDERR, "Error: In production environments, ADMIN_DEFAULT_PASSWORD environment variable must be set with a unique passphrase (minimum 12 characters, not the development default).\n");
        exit(1);
    }
} else {
    if (empty($adminPassword)) {
        $adminPassword = 'Admin@Infinity2026!';
        echo "[Notice] Using standard development administrator credentials. Set ADMIN_DEFAULT_PASSWORD in production.\n";
    }
    if (empty($authorPassword)) {
        $authorPassword = 'Author@Infinity2026!';
    }
}

// Prepare administrator and demo users
$users = [
    [
        'username' => 'Shubham Banerjee',
        'display_name' => 'Shubham Banerjee',
        'email' => 'shubham@infinityblog.local',
        'role' => 'admin',
        'password' => (string)$adminPassword,
    ],
    [
        'username' => 'Sounak Sikdar',
        'display_name' => 'Sounak Sikdar',
        'email' => 'sounak@infinityblog.local',
        'role' => 'admin',
        'password' => (string)$adminPassword,
    ],
];

// Sample author only in development or testing
if ($appEnv !== 'production') {
    $users[] = [
        'username' => 'john_doe',
        'display_name' => 'John Doe',
        'email' => 'john@infinityblog.local',
        'role' => 'author',
        'password' => (string)$authorPassword,
    ];
}

$userCheck = $pdo->prepare("SELECT `id`, `role`, `status` FROM `users` WHERE `username` = :u OR `email` = :e LIMIT 1");
$userInsert = $pdo->prepare("
    INSERT INTO `users` (`username`, `display_name`, `email`, `password_hash`, `role`, `status`)
    VALUES (:username, :display_name, :email, :password_hash, :role, 'active')
");

foreach ($users as $u) {
    $userCheck->execute([':u' => $u['username'], ':e' => $u['email']]);
    $existing = $userCheck->fetch();

    if ($existing) {
        echo "User exists: {$u['username']} (role: {$existing['role']}, status: {$existing['status']}) - preserving existing state.\n";
    } else {
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $userInsert->execute([
            ':username' => $u['username'],
            ':display_name' => $u['display_name'],
            ':email' => $u['email'],
            ':password_hash' => $hash,
            ':role' => $u['role'],
        ]);
        echo "User provisioned: {$u['username']} ({$u['role']})\n";
    }
}

// Resolve user IDs for category mapping
$shubhamId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `username` = 'Shubham Banerjee'")->fetchColumn();
$sounakId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `username` = 'Sounak Sikdar'")->fetchColumn();
$johnIdRow = $pdo->query("SELECT `id` FROM `users` WHERE `username` = 'john_doe'")->fetch();
$johnId = $johnIdRow ? (int)$johnIdRow['id'] : $shubhamId;

// 5. Provision Default Categories
$categories = [
    ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Articles about technology and development.', 'created_by' => $shubhamId],
    ['name' => 'Education', 'slug' => 'education', 'description' => 'Study tips, learning resources, and education.', 'created_by' => $shubhamId],
    ['name' => 'Lifestyle', 'slug' => 'lifestyle', 'description' => 'Lifestyle, health, productivity, and personal growth.', 'created_by' => $sounakId],
    ['name' => 'Photography', 'slug' => 'photography', 'description' => 'Photography tips, gears, and experiences.', 'created_by' => $sounakId],
];

$catCheck = $pdo->prepare("SELECT `id` FROM `categories` WHERE `slug` = :slug OR `name` = :name LIMIT 1");
$catInsert = $pdo->prepare("
    INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`, `created_by`)
    VALUES (:name, :slug, :description, 1, :created_by)
");

foreach ($categories as $cat) {
    $catCheck->execute([':slug' => $cat['slug'], ':name' => $cat['name']]);
    if (!$catCheck->fetch()) {
        $catInsert->execute([
            ':name' => $cat['name'],
            ':slug' => $cat['slug'],
            ':description' => $cat['description'],
            ':created_by' => $cat['created_by'],
        ]);
        echo "Category created: {$cat['name']}\n";
    }
}
echo "Default categories verified.\n";

$techId = (int)$pdo->query("SELECT `id` FROM `categories` WHERE `slug` = 'technology'")->fetchColumn();
$lifeId = (int)$pdo->query("SELECT `id` FROM `categories` WHERE `slug` = 'lifestyle'")->fetchColumn();
$eduId = (int)$pdo->query("SELECT `id` FROM `categories` WHERE `slug` = 'education'")->fetchColumn();

// 6. Provision Sample Posts (non-production environments only)
if ($appEnv !== 'production') {
    $existingPostCount = (int)$pdo->query("SELECT COUNT(*) FROM `posts`")->fetchColumn();
    if ($existingPostCount === 0) {
        $postStmt = $pdo->prepare("
            INSERT INTO `posts` (`author_id`, `category_id`, `title`, `content`, `summary`, `summary_status`, `status`, `published_at`)
            VALUES (:author_id, :category_id, :title, :content, :summary, :summary_status, :status, :published_at)
        ");

        $posts = [
            [
                'author_id' => $sounakId,
                'category_id' => $techId,
                'title' => 'Getting Started with Web Development in 2026',
                'content' => "Modern web development continues to evolve at a blistering pace. From native browser primitives to performant server runtimes, building accessible and resilient applications requires understanding fundamental design patterns and separation of concerns.\n\nIn this article, we explore foundational principles for creating responsive web applications with semantic HTML5, modern CSS variables, and vanilla JavaScript without unnecessary runtime bloat.",
                'summary' => 'An introduction to modern web development principles in 2026, focusing on semantic HTML5, lightweight design patterns, and native browser APIs.',
                'summary_status' => 'ready',
                'status' => 'published',
                'published_at' => '2026-10-05 10:00:00',
            ],
            [
                'author_id' => $shubhamId,
                'category_id' => $lifeId,
                'title' => 'The Importance of Taking Regular Breaks',
                'content' => "Sustained intellectual focus demands intentional periods of rest. Research in ergonomics and cognitive psychology confirms that short, periodic pauses improve problem-solving agility and prevent burnout over long development cycles.\n\nIncorporate micro-breaks every hour to maintain clarity, refresh perspective, and protect long-term physical well-being.",
                'summary' => 'An insightful overview of why intentional pauses enhance focus, prevent cognitive fatigue, and improve creative engineering output.',
                'summary_status' => 'ready',
                'status' => 'published',
                'published_at' => '2026-10-03 14:30:00',
            ],
            [
                'author_id' => $johnId,
                'category_id' => $eduId,
                'title' => 'Effective Study Techniques for Computer Science',
                'content' => "Active recall and spaced repetition form the cornerstone of effective algorithmic mastery. Rather than passively reading reference specifications, practicing real code implementation reinforces conceptual clarity and deepens retention.\n\nPair peer review with incremental project milestones for optimal mastery.",
                'summary' => 'A practical guide detailing active recall, spaced repetition, and project-based milestones for computer science studies.',
                'summary_status' => 'ready',
                'status' => 'published',
                'published_at' => '2026-09-28 09:15:00',
            ],
            [
                'author_id' => $johnId,
                'category_id' => $techId,
                'title' => 'Draft: Exploring AI Integration Boundaries',
                'content' => "AI summarization should always operate as an asynchronous auxiliary service rather than an unconstrained browser dependency. In this upcoming guide, we review strict security boundaries, rate limits, and output sanitization.",
                'summary' => null,
                'summary_status' => 'none',
                'status' => 'draft',
                'published_at' => null,
            ]
        ];

        foreach ($posts as $p) {
            $postStmt->execute([
                ':author_id' => $p['author_id'],
                ':category_id' => $p['category_id'],
                ':title' => $p['title'],
                ':content' => $p['content'],
                ':summary' => $p['summary'],
                ':summary_status' => $p['summary_status'],
                ':status' => $p['status'],
                ':published_at' => $p['published_at'],
            ]);
        }
        echo "Sample development posts seeded.\n";
    }
}

// 7. Audit log initialization
$auditStmt = $pdo->prepare("
    INSERT INTO `audit_logs` (`actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata`)
    VALUES (:actor_id, 'SYSTEM_INIT', 'system', NULL, :meta)
");
$auditStmt->execute([
    ':actor_id' => $shubhamId,
    ':meta' => json_encode(['event' => 'Database setup and provisioning verified']),
]);

echo "Setup completed successfully.\n";
echo "Initial accounts verified:\n";
echo " - 'Shubham Banerjee' (role: admin)\n";
echo " - 'Sounak Sikdar' (role: admin)\n";
if ($appEnv !== 'production') {
    echo " - 'john_doe' (role: author)\n";
}
