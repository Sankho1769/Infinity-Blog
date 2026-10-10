<?php
/**
 * Infinity Blog - Database Setup & Administrator Provisioning CLI Script
 * 
 * Provisions database, schema, required initial administrators, initial categories,
 * and test posts for local development and verification.
 */

declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbName = getenv('DB_NAME') ?: 'infinity_blog';

echo "=== Infinity Blog Database Setup ===\n";
echo "Host: {$dbHost}:{$dbPort}\n";
echo "User: {$dbUser}\n";
echo "Database: {$dbName}\n";

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// 1. Run schema.sql
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Error: schema.sql not found at {$schemaFile}\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaFile);
echo "Executing schema.sql...\n";
$pdo->exec($schemaSql);
echo "Schema executed successfully.\n";

$pdo->exec("USE `{$dbName}`;");

// 2. Provision Initial Administrators (Shubham Banerjee & Sounak Sikdar)
$adminPassword = getenv('ADMIN_DEFAULT_PASSWORD') ?: 'Admin@Infinity2026!';
$authorPassword = getenv('AUTHOR_DEFAULT_PASSWORD') ?: 'Author@Infinity2026!';

$admins = [
    [
        'username' => 'Shubham Banerjee',
        'display_name' => 'Shubham Banerjee',
        'email' => 'shubham@infinityblog.local',
        'role' => 'admin',
        'password' => $adminPassword,
    ],
    [
        'username' => 'Sounak Sikdar',
        'display_name' => 'Sounak Sikdar',
        'email' => 'sounak@infinityblog.local',
        'role' => 'admin',
        'password' => $adminPassword,
    ],
    [
        'username' => 'john_doe',
        'display_name' => 'John Doe',
        'email' => 'john@infinityblog.local',
        'role' => 'author',
        'password' => $authorPassword,
    ]
];

$userStmt = $pdo->prepare("
    INSERT INTO `users` (`username`, `display_name`, `email`, `password_hash`, `role`, `status`)
    VALUES (:username, :display_name, :email, :password_hash, :role, 'active')
    ON DUPLICATE KEY UPDATE
        `display_name` = VALUES(`display_name`),
        `role` = VALUES(`role`),
        `status` = 'active'
");

foreach ($admins as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $userStmt->execute([
        ':username' => $u['username'],
        ':display_name' => $u['display_name'],
        ':email' => $u['email'],
        ':password_hash' => $hash,
        ':role' => $u['role'],
    ]);
    echo "User ensured: {$u['username']} ({$u['role']})\n";
}

// Get user IDs
$shubhamId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `username` = 'Shubham Banerjee'")->fetchColumn();
$sounakId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `username` = 'Sounak Sikdar'")->fetchColumn();
$johnId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `username` = 'john_doe'")->fetchColumn();

// 3. Provision Categories
$categories = [
    ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Articles about technology and development.', 'created_by' => $shubhamId],
    ['name' => 'Education', 'slug' => 'education', 'description' => 'Study tips, learning resources, and education.', 'created_by' => $shubhamId],
    ['name' => 'Lifestyle', 'slug' => 'lifestyle', 'description' => 'Lifestyle, health, productivity, and personal growth.', 'created_by' => $sounakId],
    ['name' => 'Photography', 'slug' => 'photography', 'description' => 'Photography tips, gears, and experiences.', 'created_by' => $sounakId],
];

$catStmt = $pdo->prepare("
    INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`, `created_by`)
    VALUES (:name, :slug, :description, 1, :created_by)
    ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `description` = VALUES(`description`),
        `is_active` = 1
");

foreach ($categories as $cat) {
    $catStmt->execute([
        ':name' => $cat['name'],
        ':slug' => $cat['slug'],
        ':description' => $cat['description'],
        ':created_by' => $cat['created_by'],
    ]);
}
echo "Default categories ensured.\n";

$techId = (int)$pdo->query("SELECT `id` FROM `categories` WHERE `slug` = 'technology'")->fetchColumn();
$lifeId = (int)$pdo->query("SELECT `id` FROM `categories` WHERE `slug` = 'lifestyle'")->fetchColumn();
$eduId = (int)$pdo->query("SELECT `id` FROM `categories` WHERE `slug` = 'education'")->fetchColumn();

// 4. Provision Sample Posts matching wireframe
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
    echo "Sample posts created.\n";
} else {
    echo "Posts already exist, skipping initial seed.\n";
}

// 5. Initial Audit Log
$auditStmt = $pdo->prepare("
    INSERT INTO `audit_logs` (`actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata`)
    VALUES (:actor_id, 'SYSTEM_INIT', 'system', NULL, :meta)
");
$auditStmt->execute([
    ':actor_id' => $shubhamId,
    ':meta' => json_encode(['event' => 'Initial database setup and provisioning completed']),
]);

echo "Setup completed successfully!\n";
echo "Initial administrators:\n";
echo " - 'Shubham Banerjee' (role: admin, default pwd: '{$adminPassword}')\n";
echo " - 'Sounak Sikdar' (role: admin, default pwd: '{$adminPassword}')\n";
echo "Initial author:\n";
echo " - 'john_doe' (role: author, default pwd: '{$authorPassword}')\n";
