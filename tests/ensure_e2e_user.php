<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Auth\UserRepository;

$cfg = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($cfg->database);
$users = new UserRepository($pdo);

$action = $argv[1] ?? 'ensure';

if ($action === 'cleanup') {
    $u = $users->findByIdentifier('login_audit_e2e_user');
    if ($u) {
        $pdo->prepare('DELETE FROM posts WHERE author_id = ?')->execute([(int) $u->id]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int) $u->id]);
    }
    $pdo->exec('DELETE FROM login_rate_limits');
    echo "Cleaned up\n";
    exit(0);
}

// Clear rate limits before test run so 127.0.0.1 is not blocked
$pdo->exec('DELETE FROM login_rate_limits');

$existing = $users->findByIdentifier('login_audit_e2e_user');
if (!$existing) {
    // Note: set email to empty string/null so login challenge (OTP) is bypassed in local dev unless configured
    $users->createUser('Login Audit E2E User', 'login_audit_e2e_user', password_hash('SecurePass123!', PASSWORD_DEFAULT), [
        'username' => 'login_audit_e2e_user',
        'email' => 'login_audit_e2e@example.com'
    ]);
    echo "Created\n";
} else {
    // Reset password to known
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?')->execute([
        password_hash('SecurePass123!', PASSWORD_DEFAULT),
        'login_audit_e2e_user'
    ]);
    echo "Updated\n";
}
