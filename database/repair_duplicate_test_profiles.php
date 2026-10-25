<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;

$config = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($config->database);

echo "=== REPAIRING TEST PROFILES AVATARS ===\n";

// 1. Check User 9
$u9 = $pdo->query('SELECT id, username, display_name, email, avatar_path FROM users WHERE id = 9')->fetch(PDO::FETCH_ASSOC);
echo "User 9 before: " . json_encode($u9) . "\n";

if (!empty($u9['avatar_path']) && str_starts_with($u9['avatar_path'], 'avatars/u13_')) {
    $stmt = $pdo->prepare('UPDATE users SET avatar_path = NULL WHERE id = 9');
    $stmt->execute();
    echo "Cleared User 9 copied avatar path (u13_...). Rows affected: " . $stmt->rowCount() . "\n";
}

// 2. Check User 35 (which had u43_ copied)
$u35 = $pdo->query('SELECT id, username, display_name, email, avatar_path FROM users WHERE id = 35')->fetch(PDO::FETCH_ASSOC);
echo "User 35 before: " . json_encode($u35) . "\n";
if (!empty($u35['avatar_path']) && str_starts_with($u35['avatar_path'], 'avatars/u43_')) {
    $stmt = $pdo->prepare('UPDATE users SET avatar_path = NULL WHERE id = 35');
    $stmt->execute();
    echo "Cleared User 35 copied avatar path (u43_...). Rows affected: " . $stmt->rowCount() . "\n";
}

// Verify User 13 (owner of u13_ avatar) remains intact
$u13 = $pdo->query('SELECT id, username, display_name, email, avatar_path FROM users WHERE id = 13')->fetch(PDO::FETCH_ASSOC);
echo "User 13 verified intact: " . json_encode($u13) . "\n";

// Verify User 43 (owner of u43_ avatar) remains intact
$u43 = $pdo->query('SELECT id, username, display_name, email, avatar_path FROM users WHERE id = 43')->fetch(PDO::FETCH_ASSOC);
echo "User 43 verified intact: " . json_encode($u43) . "\n";

echo "Repair complete.\n";
