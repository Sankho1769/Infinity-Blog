<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;

$cfg = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($cfg->database);
$users = new UserRepository($pdo);
$sessions = new SessionManager($cfg);
$sessions->start();

$role = $argv[1] ?? null;
if ($role !== null) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? LIMIT 1");
    $stmt->execute([$role]);
} else {
    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
}
$uid = (int) $stmt->fetchColumn();
$user = $users->findById($uid);

if ($user) {
    $sessions->establish($user);
    $sid = session_id();
    session_write_close();
    echo $sid;
}
