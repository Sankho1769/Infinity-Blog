<?php
declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use InfinityBlog\Ai\ArticleAssistantService;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Summaries\AiProviderConfig;
use InfinityBlog\Summaries\OpenAiResponsesProvider;
use InfinityBlog\Summaries\SummaryProvider;
use InfinityBlog\Summaries\SummaryProviderException;

echo "=================================================================\n";
echo "INFINITY BLOG — ARTICLE AI ASSISTANT AUDIT & VERIFICATION SUITE\n";
echo "=================================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function assertAudit(bool $condition, string $title, array $details = []): void {
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
// SECTION 1: Environment & Real Model Request Audit
// -------------------------------------------------------------
echo "--- Section 1: Live Environment & Model Request Path Audit ---\n";

$envConfig = AiProviderConfig::fromEnvironment();
$isConfigured = $envConfig !== null && $envConfig->apiKey !== '';

assertAudit(
    !$isConfigured,
    "Live application has NO active AI model provider configured in .env",
    [
        "AiProviderConfig::fromEnvironment() returned " . ($envConfig === null ? "null" : "non-null"),
        "Real generative AI model active in live runtime: NO",
        "Live request path: Browser -> /api/article/{id}/assistant -> WebController -> ArticleAssistantService (local_context fallback)"
    ]
);

// -------------------------------------------------------------
// SECTION 2: Database Setup for Article Context Isolation Tests
// -------------------------------------------------------------
echo "\n--- Section 2: Grounding and Multi-Article Isolation Audit ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    email TEXT,
    password_hash TEXT,
    display_name TEXT,
    role TEXT DEFAULT "reader",
    status TEXT DEFAULT "active",
    avatar_path TEXT,
    banner_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    slug TEXT UNIQUE,
    is_active INTEGER DEFAULT 1
)');

$pdo->exec('CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER,
    category_id INTEGER,
    title TEXT,
    slug TEXT,
    content TEXT,
    summary TEXT,
    summary_status TEXT DEFAULT "ready",
    summary_source_hash TEXT,
    content_type TEXT DEFAULT "article",
    status TEXT DEFAULT "published",
    cover_image_path TEXT,
    video_path TEXT,
    audio_path TEXT,
    audio_duration INTEGER,
    audio_status TEXT DEFAULT "idle",
    remixed_from_post_id INTEGER,
    summary_generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

$pdo->exec('INSERT INTO users (id, username, display_name) VALUES (1, "author_one", "Author One")');
$pdo->exec('INSERT INTO categories (id, name, slug) VALUES (1, "Cloud Architecture", "cloud-arch"), (2, "Culinary Arts", "culinary")');

// Article 1: Cloud Architecture
$titleA = "Mastering Kubernetes Cluster Autoscaling";
$contentA = "# Cluster Autoscaling in Production\n\n" .
    "Kubernetes Horizontal Pod Autoscaler (HPA) automatically adjusts replica counts based on observed CPU utilization and custom Prometheus metrics.\n\n" .
    "## Overprovisioning Strategies\n\n" .
    "Pause pods with negative priority pre-warm cloud nodes so sudden traffic spikes avoid node initialization latency.\n\n" .
    "## Spot Instance Pools\n\n" .
    "Combining diverse spot instance pools with graceful termination handlers reduces compute costs by up to 70 percent.";
$summaryA = "Comprehensive guide to Kubernetes autoscaling using HPA, pause pods, and spot instance pools.";

$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, category_id, title, slug, content, summary, status) VALUES (101, 1, 1, ?, "k8s-autoscaling", ?, ?, "published")');
$stmt->execute([$titleA, $contentA, $summaryA]);

// Article 2: Culinary Arts
$titleB = "The Science of Sourdough Fermentation";
$contentB = "# Sourdough Microbiology\n\n" .
    "Wild yeast Saccharomyces exiguus thrives alongside lactobacillus sanfranciscensis in a symbiotic low-pH matrix.\n\n" .
    "## Autolyse and Hydration\n\n" .
    "An initial 60-minute autolyse at 78 percent hydration promotes enzymatic gluten development before salt is incorporated.\n\n" .
    "## Cold Proofing Benefits\n\n" .
    "Retarding shaped batards at 4 degrees Celsius for 16 hours allows bacterial fermentation to generate lactic and acetic acids.";
$summaryB = "The biochemical dynamics of wild yeast and lactobacillus in artisanal sourdough bread making.";

$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, category_id, title, slug, content, summary, status) VALUES (102, 1, 2, ?, "sourdough-science", ?, ?, "published")');
$stmt->execute([$titleB, $contentB, $summaryB]);

// Draft Article 3: Unpublished Post
$stmt = $pdo->prepare('INSERT INTO posts (id, author_id, category_id, title, slug, content, summary, status) VALUES (103, 1, 1, "Unpublished Draft", "draft-post", "Secret draft content", "Secret", "draft")');
$stmt->execute([]);

$postRepo = new PostRepository($pdo);
$assistant = new ArticleAssistantService($pdo, $postRepo, null);

// 2.1 Test Draft Article Isolation
$draftBlocked = false;
try {
    $assistant->ask(103, 1, 'sess1', 'What is this?');
} catch (InvalidArgumentException $e) {
    $draftBlocked = ($e->getMessage() === 'Article not found.');
}
assertAudit($draftBlocked, "Unpublished draft articles are strictly inaccessible to the assistant");

// 2.2 Test Context Isolation: Question on Article A strictly uses Article A context
$resA = $assistant->ask(101, 1, 'sessA', 'How do spot instances help?');
$hasSpot = str_contains($resA['answer'], '70 percent');
$hasNoBaking = !str_contains($resA['answer'], 'sourdough') && !str_contains($resA['answer'], 'yeast');
assertAudit($hasSpot && $hasNoBaking, "Article A query answers using Article A content only and never leaks Article B context");

// 2.3 Test Context Isolation: Question on Article B strictly uses Article B context
$resB = $assistant->ask(102, 1, 'sessB', 'What temperature is used for cold proofing?');
$hasTemp = str_contains($resB['answer'], '4 degrees Celsius');
$hasNoK8s = !str_contains($resB['answer'], 'Kubernetes') && !str_contains($resB['answer'], 'HPA');
assertAudit($hasTemp && $hasNoK8s, "Article B query answers using Article B content only and never leaks Article A context");

// -------------------------------------------------------------
// SECTION 3: Local Intent Classification & Retrieval Audit
// -------------------------------------------------------------
echo "\n--- Section 3: Local Intent Classification & Deterministic Grounding ---\n";

// 3.1 Greeting
$resGreeting = $assistant->ask(101, 1, 'sessA', 'hello');
assertAudit(
    (str_contains($resGreeting['answer'], 'Greetings') || str_contains($resGreeting['answer'], 'Hello!')) && str_contains($resGreeting['answer'], $titleA) && $resGreeting['passage_reference'] === null,
    "Greeting prompt returns natural greeting citing article title with null passage citation",
    ["Answer preview: " . substr($resGreeting['answer'], 0, 90) . "..."]
);

// 3.2 Overview
$resOverview = $assistant->ask(101, 1, 'sessA', 'What is the article about?');
assertAudit(
    str_contains($resOverview['answer'], 'provides an overview') && str_contains($resOverview['answer'], 'Kubernetes') && !empty($resOverview['passage_reference']),
    "Overview prompt returns grounded summary of article with passage citation",
    ["Passage citation: " . substr($resOverview['passage_reference'] ?? '', 0, 70) . "..."]
);

// 3.3 Summarization
$resSummary = $assistant->ask(102, 1, 'sessB', 'Summarize it');
assertAudit(
    str_contains($resSummary['answer'], 'Here is a summary') && str_contains($resSummary['answer'], 'sourdough') && !empty($resSummary['passage_reference']),
    "Summarization prompt returns structured summary of article with passage citation",
    ["Answer preview: " . substr($resSummary['answer'], 0, 80) . "..."]
);

// 3.4 Factual grounded question with passage citation
$resFactual = $assistant->ask(101, 1, 'sessA', 'What do pause pods do?');
assertAudit(
    str_contains($resFactual['answer'], 'Pause pods with negative priority') && !empty($resFactual['passage_reference']),
    "Factual grounded question locates target section and cites relevant passage",
    ["Passage citation: " . substr($resFactual['passage_reference'] ?? '', 0, 80) . "..."]
);

// 3.5 Unsupported / Out-of-scope question
$resUnsupported = $assistant->ask(101, 1, 'sessA', 'How do I change car engine oil?');
assertAudit(
    str_contains($resUnsupported['answer'], 'does not appear to be covered') && str_contains($resUnsupported['answer'], 'Overprovisioning Strategies') && $resUnsupported['passage_reference'] === null,
    "Unsupported question transparently explains what the article does cover (sections/topics) rather than generic error",
    ["Answer preview: " . substr($resUnsupported['answer'], 0, 95) . "..."]
);

// -------------------------------------------------------------
// SECTION 4: Provider Integration, Timeout, and Error Handling
// -------------------------------------------------------------
echo "\n--- Section 4: Generative Provider Pipeline & Error Safety ---\n";

// 4.1 Mock Generative Model Provider (Simulating successful external LLM call)
$mockModelTransport = static function (string $url, array $headers, string $body, int $timeout): array {
    // Assert authorization header contains Bearer
    if (!isset($headers['Authorization']) || !str_starts_with($headers['Authorization'], 'Bearer ')) {
        throw new \RuntimeException('Missing Bearer authorization header');
    }

    $payload = json_decode($body, true);
    return [
        'status' => 200,
        'body' => json_encode([
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Synthesized generative response: Kubernetes autoscaling dynamically adapts compute resources.'
                        ]
                    ]
                ]
            ]
        ], JSON_THROW_ON_ERROR)
    ];
};

$syntheticEnv = [
    'AI_PROVIDER' => 'openai',
    'AI_MODEL' => 'gpt-4o-mini',
    'AI_API_KEY' => 'sk-synthetic-audit-key-test-not-a-real-secret',
    'AI_TIMEOUT_SECONDS' => '15',
    'AI_MAX_INPUT_CHARS' => '10000',
    'AI_MAX_OUTPUT_CHARS' => '800',
    'AI_SUMMARY_RATE_LIMIT' => '20'
];

$validConfig = AiProviderConfig::fromArray($syntheticEnv);
$mockProvider = new OpenAiResponsesProvider($validConfig, $mockModelTransport);
$assistantWithProvider = new ArticleAssistantService($pdo, $postRepo, $validConfig, $mockProvider);

$providerRes = $assistantWithProvider->ask(101, 1, 'sessP', 'Explain autoscaling benefits');
assertAudit(
    $providerRes['source'] === 'ai_provider' && str_contains($providerRes['answer'], 'Synthesized generative response'),
    "When a working generative provider is supplied, response source is 'ai_provider' with model text",
    ["Source: " . $providerRes['source'], "Model output: " . $providerRes['answer']]
);

// 4.2 Provider Upstream Error Handling (No Secret Leaking)
$errorTransport = static function (): array {
    return [
        'status' => 500,
        'body' => '{"error": {"message": "OpenAI internal upstream failure with secret sk-synthetic-audit-key-test-not-a-real-secret"}}'
    ];
};
$errorProvider = new OpenAiResponsesProvider($validConfig, $errorTransport);
$assistantWithErrorProvider = new ArticleAssistantService($pdo, $postRepo, $validConfig, $errorProvider);

$errorSafe = false;
$errorMessage = '';
try {
    $assistantWithErrorProvider->ask(101, 1, 'sessErr', 'Explain autoscaling');
} catch (\RuntimeException $e) {
    $errorMessage = $e->getMessage();
    $errorSafe = !str_contains($errorMessage, 'sk-synthetic') && !str_contains($errorMessage, 'internal upstream failure');
}
assertAudit(
    $errorSafe,
    "Upstream provider failure throws clean user-friendly error without leaking internal error bodies or credentials",
    ["User-facing error message: " . $errorMessage]
);

// 4.3 Provider Timeout Handling
$timeoutTransport = static function (): array {
    throw new \RuntimeException('cURL error 28: Operation timed out after 15000 milliseconds with 0 bytes received');
};
$timeoutProvider = new OpenAiResponsesProvider($validConfig, $timeoutTransport);
$assistantWithTimeoutProvider = new ArticleAssistantService($pdo, $postRepo, $validConfig, $timeoutProvider);

$timeoutSafe = false;
$timeoutMessage = '';
try {
    $assistantWithTimeoutProvider->ask(101, 1, 'sessTimeout', 'Explain autoscaling');
} catch (\RuntimeException $e) {
    $timeoutMessage = $e->getMessage();
    $timeoutSafe = !str_contains($timeoutMessage, 'cURL error 28');
}
assertAudit(
    $timeoutSafe,
    "Provider network timeout throws safe user-facing error without leaking raw socket diagnostics",
    ["User-facing timeout message: " . $timeoutMessage]
);

// 4.4 Invalid AI Environment Configuration
$invalidConfigRejected = false;
try {
    AiProviderConfig::fromArray([
        'AI_PROVIDER' => 'unsupported_llm_service',
        'AI_MODEL' => 'bad-model',
        'AI_API_KEY' => 'key'
    ]);
} catch (\InvalidArgumentException $e) {
    $invalidConfigRejected = str_contains($e->getMessage(), 'supported provider');
}
assertAudit(
    $invalidConfigRejected,
    "Invalid AI_PROVIDER setting is strictly validated and rejected at startup"
);

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n=================================================================\n";
echo "AUDIT RESULTS: {$passedCount} passed, {$failedCount} failed.\n";
echo "=================================================================\n";

exit($failedCount === 0 ? 0 : 1);
