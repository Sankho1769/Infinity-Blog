<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Auth\ProfileValidationException;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Config\EnvironmentLoader;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Support\UserIdentityHelper;

$environment = EnvironmentLoader::loadFile(dirname(__DIR__) . '/.env', getenv());
$config = AppConfig::fromArray($environment);
$pdo = ConnectionFactory::create($config->database);
$users = new UserRepository($pdo);

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $desc): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$desc}\n";
    }
}

echo "=== 1. Testing UserIdentityHelper::normalizeSocialUrl ===\n";

// Instagram
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('@cristine_palmer', 'instagram') === 'https://instagram.com/cristine_palmer',
    'Instagram handle normalization'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://www.instagram.com/cristine_palmer/', 'instagram') === 'https://www.instagram.com/cristine_palmer',
    'Instagram full URL normalization'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://evil-phishing.com/cristine_palmer', 'instagram') === null,
    'Instagram rejects untrusted host'
);

// GitHub
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('cristine-dev', 'github') === 'https://github.com/cristine-dev',
    'GitHub handle normalization'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://github.com/cristine-dev', 'github') === 'https://github.com/cristine-dev',
    'GitHub URL normalization'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://not-github.com/cristine', 'github') === null,
    'GitHub rejects invalid host'
);

// X / Twitter
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('@tech_lead', 'x') === 'https://x.com/tech_lead',
    'X handle normalization with @'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('tech_lead', 'twitter') === 'https://x.com/tech_lead',
    'Twitter handle normalization'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://twitter.com/tech_lead', 'twitter') === 'https://twitter.com/tech_lead',
    'Twitter URL accepted'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://x.com/tech_lead', 'x') === 'https://x.com/tech_lead',
    'X.com URL accepted'
);

// LinkedIn
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('in/techleader', 'linkedin') === 'https://linkedin.com/in/techleader',
    'LinkedIn handle with in/ prefix'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('techleader', 'linkedin') === 'https://linkedin.com/in/techleader',
    'LinkedIn plain handle prepends in/'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://www.linkedin.com/in/techleader', 'linkedin') === 'https://www.linkedin.com/in/techleader',
    'LinkedIn full URL accepted'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://malicious-site.com/in/techleader', 'linkedin') === null,
    'LinkedIn rejects untrusted host'
);

// Facebook
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('techlead', 'facebook') === 'https://facebook.com/techlead',
    'Facebook handle normalization'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('https://www.facebook.com/techlead', 'facebook') === 'https://www.facebook.com/techlead',
    'Facebook full URL accepted'
);

// Malicious / dangerous schemes
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('javascript:alert(1)', 'instagram') === null,
    'Rejects javascript: scheme'
);
assertCondition(
    UserIdentityHelper::normalizeSocialUrl('data:text/html,<html>', 'github') === null,
    'Rejects data: scheme'
);

echo "\n=== 2. Testing WhatsApp Normalization & Links ===\n";
assertCondition(
    UserIdentityHelper::normalizeWhatsAppNumber('+1 (415) 555-2671') === '+14155552671',
    'WhatsApp number E.164 normalization'
);
assertCondition(
    UserIdentityHelper::formatWhatsAppLink('+14155552671') === 'https://wa.me/14155552671',
    'WhatsApp wa.me link generation'
);
assertCondition(
    UserIdentityHelper::normalizeWhatsAppNumber('123') === null,
    'Rejects too short WhatsApp number'
);

echo "\n=== 3. Testing UserIdentityHelper::getSocialLinks ===\n";

// Account A: only Instagram & GitHub
$accountAData = [
    'display_name' => 'Cristine Palmer',
    'username' => 'cristine',
    'instagram_url' => 'https://instagram.com/cristine_palmer',
    'github_url' => 'https://github.com/cristine',
    'whatsapp_number' => null,
    'whatsapp_public' => 0,
    'twitter_url' => null,
    'linkedin_url' => null,
    'facebook_url' => null,
];
$linksA = UserIdentityHelper::getSocialLinks($accountAData, 'Cristine Palmer', true);
assertCondition(count($linksA) === 2, 'Account A has exactly 2 social links');
assertCondition($linksA[0]['platform'] === 'instagram' && $linksA[1]['platform'] === 'github', 'Account A has Instagram and GitHub');
assertCondition(str_contains($linksA[0]['aria_label'], "Cristine Palmer's Instagram"), 'Account A Instagram has accessible aria-label');
assertCondition(str_contains($linksA[1]['aria_label'], "Cristine Palmer's GitHub"), 'Account A GitHub has accessible aria-label');

// Account B: WhatsApp, X, LinkedIn, Facebook
$accountBData = [
    'display_name' => 'Tech Lead',
    'username' => 'tech_lead',
    'instagram_url' => null,
    'github_url' => null,
    'whatsapp_number' => '+14155552671',
    'whatsapp_public' => 1,
    'twitter_url' => 'https://x.com/tech_lead',
    'linkedin_url' => 'https://linkedin.com/in/techleader',
    'facebook_url' => 'https://facebook.com/techlead',
];
$linksB = UserIdentityHelper::getSocialLinks($accountBData, 'Tech Lead', true);
assertCondition(count($linksB) === 4, 'Account B has exactly 4 social links');
$platformsB = array_column($linksB, 'platform');
assertCondition($platformsB === ['whatsapp', 'x', 'linkedin', 'facebook'], 'Account B platforms are WhatsApp, X, LinkedIn, Facebook');
assertCondition(!in_array('instagram', $platformsB, true) && !in_array('github', $platformsB, true), 'Account B does NOT show Instagram or GitHub');

// Account C: No social links
$accountCData = [
    'display_name' => 'Shubham Banerjee',
    'username' => 'shubham_banerjee',
    'instagram_url' => null,
    'github_url' => null,
    'whatsapp_number' => null,
    'whatsapp_public' => 0,
    'twitter_url' => null,
    'linkedin_url' => null,
    'facebook_url' => null,
];
$linksC = UserIdentityHelper::getSocialLinks($accountCData, 'Shubham Banerjee', true);
assertCondition(count($linksC) === 0, 'Account C has 0 social links (empty array, no fake buttons)');

// WhatsApp privacy test
$privateWAData = [
    'display_name' => 'Private User',
    'whatsapp_number' => '+14155552671',
    'whatsapp_public' => 0,
];
$publicLinks = UserIdentityHelper::getSocialLinks($privateWAData, 'Private User', true);
assertCondition(count($publicLinks) === 0, 'Private WhatsApp number is NEVER exposed in public links');

echo "\n=== 4. Testing UserRepository Social Field Persistence ===\n";

// Test updating User 13 with LinkedIn and X
$users->updateProfile(13, [
    'display_name' => 'Cristine Palmer',
    'instagram_url' => 'https://instagram.com/cristine_palmer',
    'github_url' => 'https://github.com/cristine',
    'linkedin_url' => 'in/cristinepalmer',
    'x_url' => '@cristine_palmer',
]);
$updatedUser = $users->findById(13);
assertCondition($updatedUser->linkedinUrl === 'https://linkedin.com/in/cristinepalmer', 'LinkedIn URL saved and normalized');
assertCondition($updatedUser->twitterUrl === 'https://x.com/cristine_palmer', 'X URL saved via x_url alias');

// Test findPublicAuthor returns linkedin_url and x_url
$authorData = $users->findPublicAuthor(13);
assertCondition(isset($authorData['linkedin_url']) && $authorData['linkedin_url'] === 'https://linkedin.com/in/cristinepalmer', 'findPublicAuthor includes linkedin_url');
assertCondition(isset($authorData['x_url']) && $authorData['x_url'] === 'https://x.com/cristine_palmer', 'findPublicAuthor includes x_url');

// Test clearing social fields
$users->updateProfile(13, [
    'display_name' => 'Cristine Palmer',
    'instagram_url' => 'https://instagram.com/cristine_palmer',
    'github_url' => 'https://github.com/cristine',
    'linkedin_url' => '',
    'twitter_url' => '',
]);
$clearedUser = $users->findById(13);
assertCondition($clearedUser->linkedinUrl === null, 'LinkedIn URL cleared to null');
assertCondition($clearedUser->twitterUrl === null, 'Twitter URL cleared to null');

// Test validation error rejection
try {
    $users->updateProfile(13, [
        'display_name' => 'Cristine Palmer',
        'linkedin_url' => 'https://evil-phishing.com/user',
    ]);
    assertCondition(false, 'Should have thrown ProfileValidationException for invalid LinkedIn host');
} catch (ProfileValidationException $e) {
    assertCondition(isset($e->errors['linkedin_url']), 'Rejected invalid LinkedIn URL with validation error');
}

echo "\n============================================\n";
echo "Summary: {$passed} passed, {$failed} failed\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
