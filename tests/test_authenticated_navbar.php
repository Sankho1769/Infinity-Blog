<?php
/**
 * Test script for Navbar Behavior, Active States, and Role Guards.
 */
require __DIR__ . '/../autoload.php';

$cfg = \InfinityBlog\Config\AppConfig::fromEnvironment();
$pdo = \InfinityBlog\Database\ConnectionFactory::create($cfg->database);

$guestUser = null;

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => 8]);
$normalUser = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt->execute([':id' => 2]);
$adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

function renderNavbarTest(?array $user, string $activeNav = 'home'): string {
    $currentUser = $user;
    $title = 'Test Navbar';
    $metaDescription = 'Test meta description';
    $metaKeywords = 'test, navbar';
    $canonicalUrl = 'http://localhost:8086/';
    $ogImage = 'http://localhost:8086/assets/images/og.png';
    $content = '<div>Page Content</div>';

    ob_start();
    include __DIR__ . '/../app/Views/layout.php';
    return ob_get_clean();
}

$assertions = 0;
function assertNavbar(bool $condition, string $msg): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new \RuntimeException("FAILED: $msg");
    }
    echo "PASS: $msg\n";
}

echo "=== Testing Guest Navbar ===\n";
$guestHtml = renderNavbarTest($guestUser, 'home');
assertNavbar(strpos($guestHtml, 'Sign In') !== false, 'Guest navbar contains Sign In');
assertNavbar(strpos($guestHtml, 'Create Account') !== false, 'Guest navbar contains Create Account');
assertNavbar(strpos($guestHtml, 'user-menu-pill') === false, 'Guest navbar does NOT contain user-menu-pill');
assertNavbar(strpos($guestHtml, 'more-nav-dropdown') === false, 'Guest navbar does NOT contain more-nav-dropdown');
assertNavbar(strpos($guestHtml, 'admin-nav-dropdown') === false, 'Guest navbar does NOT contain admin-nav-dropdown');
assertNavbar(strpos($guestHtml, 'mobile-guest-actions') !== false, 'Guest mobile drawer has guest actions');

echo "\n=== Testing Normal User Navbar ===\n";
$userHtml = renderNavbarTest($normalUser, 'library');
assertNavbar(strpos($userHtml, 'user-menu-pill') !== false, 'User navbar contains user-menu-pill');
assertNavbar(strpos($userHtml, 'more-nav-dropdown') !== false, 'User navbar contains more-nav-dropdown');
assertNavbar(strpos($userHtml, 'admin-nav-dropdown') === false, 'Normal user navbar does NOT contain admin-nav-dropdown');
assertNavbar(strpos($userHtml, 'nav-create-btn') !== false, 'User navbar contains + Create Post button');
assertNavbar(strpos($userHtml, 'mobile-create-link') !== false, 'User mobile drawer contains Create New Post link');
assertNavbar(strpos($userHtml, 'Logout') !== false, 'User navbar contains Logout');
assertNavbar(strpos($userHtml, 'more-nav-dropdown-btn') !== false && strpos($userHtml, 'nav-dropdown-btn active') !== false, 'Active state: more-nav-dropdown-btn has active class when activeNav is library');

echo "\n=== Testing Admin User Navbar ===\n";
$adminHtml = renderNavbarTest($adminUser, 'admin_categories');
assertNavbar(strpos($adminHtml, 'admin-nav-dropdown') !== false, 'Admin navbar contains admin-nav-dropdown');
assertNavbar(strpos($adminHtml, 'Categories Admin') !== false, 'Admin navbar contains Categories Admin link');
assertNavbar(strpos($adminHtml, 'Users Management') !== false, 'Admin navbar contains Users Management link');
assertNavbar(strpos($adminHtml, 'Audit Logs') !== false, 'Admin navbar contains Audit Logs link');
assertNavbar(strpos($adminHtml, 'Contact Inquiries') !== false, 'Admin navbar contains Contact Inquiries link');
assertNavbar(strpos($adminHtml, 'user-role-badge admin') !== false, 'Admin navbar user pill displays Admin badge');
assertNavbar(strpos($adminHtml, 'admin-nav-dropdown-btn') !== false && strpos($adminHtml, 'nav-dropdown-btn active') !== false, 'Active state: admin-nav-dropdown-btn has active class when activeNav is admin_categories');

echo "\n=== Testing Active Nav on Primary Links ===\n";
$categoriesHtml = renderNavbarTest($normalUser, 'categories');
assertNavbar(strpos($categoriesHtml, 'href="/categories" class="nav-link active"') !== false, 'Desktop Categories link is active');
assertNavbar(strpos($categoriesHtml, 'href="/categories" class="mobile-nav-link active"') !== false, 'Mobile Categories link is active');

$chatHtml = renderNavbarTest($normalUser, 'chat');
assertNavbar(strpos($chatHtml, 'href="/chat" class="nav-link active"') !== false, 'Desktop Chat link is active');
assertNavbar(strpos($chatHtml, 'href="/chat" class="mobile-nav-link active"') !== false, 'Mobile Chat link is active');

echo "\nAll $assertions assertions passed!\n";
