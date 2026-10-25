<?php
declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Config\EnvironmentLoader;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Support\UserIdentityHelper;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Posts\PostRepository;

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
    } else {
        $failed++;
        echo "FAIL: {$message}\n";
    }
};

echo "=== Running User Identity, Avatars, and Recommendations Test Suite ===\n\n";

// -------------------------------------------------------------
// PART 1: UserIdentityHelper Unit Tests
// -------------------------------------------------------------
echo "-- Testing UserIdentityHelper --\n";

// 1.1 formatHandle prevents email leaks
$h1 = UserIdentityHelper::formatHandle('cristine.palmer@example.com', 'Cristine Palmer');
$assert($h1 === '@cristine_palmer', "formatHandle converts email to display name slug ({$h1})");

$h2 = UserIdentityHelper::formatHandle('priya_sharma', 'Priya Sharma');
$assert($h2 === '@priya_sharma', "formatHandle prefixes @ to valid handle ({$h2})");

$h3 = UserIdentityHelper::formatHandle('@alex_author', 'Alex Author');
$assert($h3 === '@alex_author', "formatHandle does not double prefix @ ({$h3})");

$h4 = UserIdentityHelper::formatHandle(null, 'Shubham Banerjee');
$assert($h4 === '@shubham_banerjee', "formatHandle generates slug when username is null ({$h4})");

$h5 = UserIdentityHelper::formatHandle('test_user@domain.com', null);
$assert($h5 === '@user', "formatHandle returns @user and never leaks email local part when display name is null ({$h5})");
$assert(!str_contains($h5, 'domain.com') && !str_contains($h5, 'test_user'), "formatHandle never leaks email or local part");

// 1.2 cleanHandle validation
$assert(UserIdentityHelper::cleanHandle('priya_sharma') === 'priya_sharma', "cleanHandle accepts valid handle");
$assert(UserIdentityHelper::cleanHandle('user.name_123') === 'user.name_123', "cleanHandle accepts dots and underscores");
$assert(UserIdentityHelper::cleanHandle('user@example.com') === null, "cleanHandle rejects emails");
$assert(UserIdentityHelper::cleanHandle('user name') === 'username', "cleanHandle strips whitespace from simple names");
$assert(UserIdentityHelper::cleanHandle('a') === null, "cleanHandle rejects handles under 2 characters");

// 1.3 slugifyHandle
$assert(UserIdentityHelper::slugifyHandle('Cristine Palmer') === 'cristine_palmer', "slugifyHandle converts spaces to underscores");
$assert(UserIdentityHelper::slugifyHandle('Dr. Jane Doe-Smith') === 'dr_jane_doe_smith', "slugifyHandle removes special characters");
$assert(UserIdentityHelper::slugifyHandle('   Leading and Trailing   ') === 'leading_and_trailing', "slugifyHandle trims whitespace");

// 1.4 resolveAvatarUrl
$assert(UserIdentityHelper::resolveAvatarUrl(null) === null, "resolveAvatarUrl returns null for null input");
$assert(UserIdentityHelper::resolveAvatarUrl('') === null, "resolveAvatarUrl returns null for empty string");
$assert(UserIdentityHelper::resolveAvatarUrl('https://example.com/pic.jpg') === 'https://example.com/pic.jpg', "resolveAvatarUrl preserves external HTTP URL");
$assert(UserIdentityHelper::resolveAvatarUrl('/uploads/avatars/test.png') === '/uploads/avatars/test.png', "resolveAvatarUrl preserves leading slash upload");
$assert(UserIdentityHelper::resolveAvatarUrl('uploads/avatars/test.png') === '/uploads/avatars/test.png', "resolveAvatarUrl prepends leading slash to uploads");

$supabaseUrl = UserIdentityHelper::resolveAvatarUrl('avatars/u13_photo.jpg');
$assert(str_starts_with($supabaseUrl, 'https://'), "resolveAvatarUrl produces HTTPS URL for Supabase storage key");
$assert(str_contains($supabaseUrl, 'profile-avatars'), "resolveAvatarUrl targets profile-avatars bucket");
$assert(str_contains($supabaseUrl, 'avatars/u13_photo.jpg'), "resolveAvatarUrl preserves key path in public URL");

echo "\n-- Connecting to Database for Integration Tests --\n";

$environment = EnvironmentLoader::loadFile(dirname(__DIR__) . '/.env', getenv());
$config = AppConfig::fromArray($environment);
$pdo = ConnectionFactory::create($config->database);

$follows = new FollowRepository($pdo);
$users = new UserRepository($pdo);
$posts = new PostRepository($pdo);

// -------------------------------------------------------------
// PART 2: Database Users Handle Verification
// -------------------------------------------------------------
echo "\n-- Verifying Normalized User Handles in Database --\n";

$stmt = $pdo->query('SELECT id, username, display_name, email FROM users');
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allClean = true;
$emailsPreserved = true;
foreach ($allUsers as $u) {
    if (str_contains($u['username'], '@') || str_contains($u['username'], ' ')) {
        $allClean = false;
        echo "Found invalid handle: ID {$u['id']} has username '{$u['username']}'\n";
    }
    if (!empty($u['email']) && !str_contains($u['email'], '@')) {
        $emailsPreserved = false;
        echo "Found corrupted email: ID {$u['id']} has email '{$u['email']}'\n";
    }
}
$assert($allClean, "All users in database have clean Instagram-style handles (no @ or spaces in username)");
$assert($emailsPreserved, "All user emails remain intact and valid");

// -------------------------------------------------------------
// PART 3: Suggested Users ("People You Know") Excludes Current User
// -------------------------------------------------------------
echo "\n-- Testing Suggested Users Exclusions and Ranking --\n";

// Pick user 2 ("shubham_banerjee") as viewer
$viewerId = 2;
$suggestions = $follows->getSuggestedUsers($viewerId, 5);

$assert(!empty($suggestions), "getSuggestedUsers returns recommendations");
$assert(count($suggestions) <= 5, "getSuggestedUsers respects the limit of 5");

$selfFound = false;
foreach ($suggestions as $s) {
    if ((int) $s['id'] === $viewerId) {
        $selfFound = true;
    }
    $assert(!empty($s['handle']), "Suggested user ID {$s['id']} has populated handle ({$s['handle']})");
    $assert(str_starts_with($s['handle'], '@'), "Suggested user handle starts with @");
    $assert(!str_contains($s['handle'], '@example.com') && !str_contains($s['handle'], '@gmail.com'), "Suggested user handle never contains email domain");
    $assert(isset($s['is_following']), "Suggested user ID {$s['id']} has is_following field");
    $assert(isset($s['follower_count']), "Suggested user ID {$s['id']} has follower_count field");
}
$assert(!$selfFound, "getSuggestedUsers strictly excludes viewer (ID {$viewerId}) from results");

// Test with another user (ID 13, "cristine")
$viewerId2 = 13;
$suggestions2 = $follows->getSuggestedUsers($viewerId2, 5);
$selfFound2 = false;
foreach ($suggestions2 as $s) {
    if ((int) $s['id'] === $viewerId2) {
        $selfFound2 = true;
    }
}
$assert(!$selfFound2, "getSuggestedUsers strictly excludes viewer ID 13 from results");

// Test that user cannot follow self (FollowRepository throws InvalidArgumentException)
$selfFollowBlocked = false;
try {
    $follows->follow($viewerId, $viewerId);
} catch (InvalidArgumentException) {
    $selfFollowBlocked = true;
}
$assert($selfFollowBlocked, "FollowRepository::follow rejects self-following with InvalidArgumentException");

$isFollowingSelf = $follows->isFollowing($viewerId, $viewerId);
$assert($isFollowingSelf === false, "isFollowing returns false for self");

// -------------------------------------------------------------
// PART 4: Authentication Compatibility with Emails & Handles
// -------------------------------------------------------------
echo "\n-- Testing Authentication by Email and Handle --\n";

// Test user with email 'cristine@gmail.com' (ID 13, handle 'cristine')
$foundByEmail = $users->findByIdentifier('cristine@gmail.com');
$assert($foundByEmail !== null && $foundByEmail->id === 13, "findByIdentifier succeeds with email (cristine@gmail.com)");

$foundByHandle = $users->findByIdentifier('cristine');
$assert($foundByHandle !== null && $foundByHandle->id === 13, "findByIdentifier succeeds with handle (cristine)");

// Test user with email 'priya.sharma@example.com' (ID 8, handle 'priya_sharma')
$foundPriyaByEmail = $users->findByIdentifier('priya.sharma@example.com');
$assert($foundPriyaByEmail !== null && $foundPriyaByEmail->id === 8, "findByIdentifier succeeds with email for Priya Sharma");

$foundPriyaByHandle = $users->findByIdentifier('priya_sharma');
$assert($foundPriyaByHandle !== null && $foundPriyaByHandle->id === 8, "findByIdentifier succeeds with normalized handle 'priya_sharma'");

// -------------------------------------------------------------
// PART 5: PostRepository Handle and Avatar Resolution
// -------------------------------------------------------------
echo "\n-- Testing PostRepository Author Identity and Avatars --\n";

$publicPosts = $posts->listPublic([], 1, 10);
$assert(!empty($publicPosts['posts']), "listPublic returns published posts");

foreach ($publicPosts['posts'] as $p) {
    $assert(!empty($p['author_handle']), "Post ID {$p['id']} has non-empty author_handle");
    $assert(str_starts_with($p['author_handle'], '@'), "Post ID {$p['id']} author_handle starts with @ ({$p['author_handle']})");
    $assert(!str_contains($p['author_handle'], '.com'), "Post ID {$p['id']} author_handle does not contain email domain");
    if (!empty($p['avatar_path'])) {
        $assert(!empty($p['author_avatar_url']), "Post ID {$p['id']} has resolved author_avatar_url when avatar_path exists");
    }
}

// Check first published post detail
$firstPostId = (int) $publicPosts['posts'][0]['id'];
$detail = $posts->findPublished($firstPostId);
$assert($detail !== null, "findPublished succeeds for post ID {$firstPostId}");
$assert(!empty($detail['author_handle']), "findPublished maps author_handle ({$detail['author_handle']})");
$assert(str_starts_with($detail['author_handle'], '@'), "Detail author_handle starts with @");

// -------------------------------------------------------------
// PART 6: Follow / Following Lists with Handles & Avatars
// -------------------------------------------------------------
echo "\n-- Testing Follow / Following Lists Data Structures --\n";

$followersList = $follows->getFollowers(2, 5);
foreach ($followersList as $fl) {
    $assert(!empty($fl['handle']), "Follower user ID {$fl['id']} has handle ({$fl['handle']})");
    $assert(str_starts_with($fl['handle'], '@'), "Follower handle starts with @");
    $assert(array_key_exists('avatar_url', $fl), "Follower user ID {$fl['id']} has avatar_url key");
}

$followingList = $follows->getFollowing(2, 5);
foreach ($followingList as $fl) {
    $assert(!empty($fl['handle']), "Following user ID {$fl['id']} has handle ({$fl['handle']})");
    $assert(str_starts_with($fl['handle'], '@'), "Following handle starts with @");
    $assert(array_key_exists('avatar_url', $fl), "Following user ID {$fl['id']} has avatar_url key");
}

// -------------------------------------------------------------
// PART 7: Social Links and WhatsApp Normalization & Privacy
// -------------------------------------------------------------
echo "\n-- Testing Social Links and WhatsApp Normalization & Privacy --\n";

// 7.1 normalizeSocialUrl handles handles, full URLs, and strips domains correctly
$ig1 = UserIdentityHelper::normalizeSocialUrl('cristine_p', 'instagram');
$assert($ig1 === 'https://instagram.com/cristine_p', "normalizeSocialUrl converts handle to Instagram URL ({$ig1})");

$ig2 = UserIdentityHelper::normalizeSocialUrl('@cristine_p', 'instagram');
$assert($ig2 === 'https://instagram.com/cristine_p', "normalizeSocialUrl strips leading @ ({$ig2})");

$ig3 = UserIdentityHelper::normalizeSocialUrl('https://instagram.com/cristine_p', 'instagram');
$assert($ig3 === 'https://instagram.com/cristine_p', "normalizeSocialUrl preserves full Instagram URL");

$gh = UserIdentityHelper::normalizeSocialUrl('octocat', 'github');
$assert($gh === 'https://github.com/octocat', "normalizeSocialUrl converts handle to GitHub URL");

$x = UserIdentityHelper::normalizeSocialUrl('elonmusk', 'x');
$assert($x === 'https://x.com/elonmusk', "normalizeSocialUrl converts handle to X URL");

$fb = UserIdentityHelper::normalizeSocialUrl('zuck', 'facebook');
$assert($fb === 'https://facebook.com/zuck', "normalizeSocialUrl converts handle to Facebook URL");

// 7.2 normalizeWhatsAppNumber enforces E.164
$w1 = UserIdentityHelper::normalizeWhatsAppNumber('+1 (555) 234-5678');
$assert($w1 === '+15552345678', "normalizeWhatsAppNumber strips formatting and preserves country code ({$w1})");

$w2 = UserIdentityHelper::normalizeWhatsAppNumber('919876543210');
$assert($w2 === '+919876543210', "normalizeWhatsAppNumber adds leading + ({$w2})");

$wInvalid = UserIdentityHelper::normalizeWhatsAppNumber('123');
$assert($wInvalid === null, "normalizeWhatsAppNumber rejects numbers shorter than 7 digits");

$wLink = UserIdentityHelper::formatWhatsAppLink('+15552345678');
$assert($wLink === 'https://wa.me/15552345678', "formatWhatsAppLink builds correct wa.me link ({$wLink})");

// 7.3 Profile Update and Privacy Toggle
echo "-- Testing Profile Update with Social and WhatsApp Privacy --\n";
$testAuthor = $users->findById(13); // cristine
if ($testAuthor !== null) {
    // Update with whatsapp_public = 0 (private by default)
    $users->updateProfile(13, [
        'instagram_url' => 'cristine_official',
        'github_url' => 'cristine-dev',
        'whatsapp_number' => '+15551234567',
        'whatsapp_public' => 0,
    ]);

    $publicAuthorPrivate = $users->findPublicAuthor(13);
    $assert($publicAuthorPrivate !== null, "findPublicAuthor found user 13");
    $assert($publicAuthorPrivate['instagram_url'] === 'https://instagram.com/cristine_official', "Instagram URL normalized on author profile");
    $assert($publicAuthorPrivate['github_url'] === 'https://github.com/cristine-dev', "GitHub URL normalized on author profile");
    $assert($publicAuthorPrivate['whatsapp_public'] === false, "whatsapp_public is false");
    $assert($publicAuthorPrivate['whatsapp_number'] === null, "whatsapp_number is hidden when whatsapp_public is false");
    $assert($publicAuthorPrivate['whatsapp_link'] === null, "whatsapp_link is null when whatsapp_public is false");

    // Toggle whatsapp_public = 1
    $users->updateProfile(13, [
        'whatsapp_public' => 1,
    ]);

    $publicAuthorPublic = $users->findPublicAuthor(13);
    $assert($publicAuthorPublic['whatsapp_public'] === true, "whatsapp_public is true");
    $assert($publicAuthorPublic['whatsapp_number'] === '+15551234567', "whatsapp_number is visible when whatsapp_public is true");
    $assert($publicAuthorPublic['whatsapp_link'] === 'https://wa.me/15551234567', "whatsapp_link is generated when whatsapp_public is true");
}

echo "\n======================================================\n";
echo "Total tests passed: {$passed}, failed: {$failed}\n";
echo "======================================================\n";

exit($failed === 0 ? 0 : 1);

