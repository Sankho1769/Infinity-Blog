<?php
declare(strict_types=1);

use InfinityBlog\Auth\AdminProvisioner;
use InfinityBlog\Auth\AdminProvisioningDiagnostic;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;

require dirname(__DIR__) . '/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$mode = $argv[1] ?? '';
if (!in_array($mode, ['provision', 'rotate', 'diagnose'], true)) {
    fwrite(STDERR, "Usage: php bin/admin-credentials.php provision | diagnose | rotate <admin-identifier>\n");
    exit(2);
}

try {
    $config = AppConfig::fromEnvironment();
    if ($config->environment === 'test') {
        throw new RuntimeException('Administrator provisioning is disabled in the test environment.');
    }
    if ($mode === 'diagnose') {
        if ($config->environment !== 'development'
            || $config->database->database !== 'infinity_blog'
            || $config->database->username !== 'blog_runtime') {
            throw new AdminProvisioningException('Diagnostic mode only permits the development runtime database.');
        }
        $shubhamPassword = getenv('ADMIN_SHUBHAM_PASSWORD');
        $sounakPassword = getenv('ADMIN_SOUNAK_PASSWORD');
        $hasPasswords = is_string($shubhamPassword) && is_string($sounakPassword);
        if ($hasPasswords) {
            AdminProvisioner::validateProvisioningPasswords($shubhamPassword, $sounakPassword);
        }
        $pdo = ConnectionFactory::createForCliDiagnostics($config->database);
        $identity = $pdo->query('SELECT DATABASE() AS database_name, CURRENT_USER() AS database_account')->fetch();
        if (!is_array($identity)
            || ($identity['database_name'] ?? null) !== 'infinity_blog'
            || !str_starts_with((string) ($identity['database_account'] ?? ''), 'blog_runtime@')) {
            throw new AdminProvisioningException('Diagnostic mode only permits the development runtime database.');
        }
        $engine = $pdo->query(
            "SELECT ENGINE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"
        )->fetchColumn();
        if ($engine !== 'InnoDB') {
            throw new AdminProvisioningException('The users table must use InnoDB for atomic provisioning.');
        }
        $statement = $pdo->prepare('SELECT username, role, status FROM users WHERE username IN (?, ?)');
        $statement->execute(['shubham banerjee', 'sounak sikdar']);
        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[strtolower((string) $row['username'])] = $row;
        }
        if ($hasPasswords) {
            fwrite(STDOUT, "Preflight passed: password policy accepted; passwords are distinct; target is development/infinity_blog via blog_runtime; writes=none.\n");
        } else {
            fwrite(STDOUT, "Diagnostic passed: target is development/infinity_blog via blog_runtime; writes=none.\n");
        }
        foreach (['shubham banerjee' => 'Shubham Banerjee', 'sounak sikdar' => 'Sounak Sikdar'] as $username => $label) {
            $row = $rows[$username] ?? null;
            if ($row === null) {
                fwrite(STDOUT, $label . ": exists=no\n");
            } else {
                fwrite(STDOUT, $label . ': exists=yes; role=' . $row['role'] . '; status=' . $row['status'] . "\n");
            }
        }
    } elseif ($mode === 'provision') {
        $pdo = ConnectionFactory::createForCliDiagnostics($config->database);
        $provisioner = new AdminProvisioner($pdo);
        $shubhamPassword = getenv('ADMIN_SHUBHAM_PASSWORD');
        $sounakPassword = getenv('ADMIN_SOUNAK_PASSWORD');
        if (!is_string($shubhamPassword) || !is_string($sounakPassword)) {
            throw new RuntimeException('Administrator passwords must be provided through the secure prompt wrapper.');
        }
        $provisioner->provision($shubhamPassword, $sounakPassword);
        unset($shubhamPassword, $sounakPassword);
        fwrite(STDOUT, "Initial administrator accounts provisioned.\n");
    } else {
        $pdo = ConnectionFactory::createForCliDiagnostics($config->database);
        $provisioner = new AdminProvisioner($pdo);
        $identifier = (string) ($argv[2] ?? '');
        $newPassword = getenv('ADMIN_NEW_PASSWORD');
        if ($identifier === '' || !is_string($newPassword)) {
            throw new RuntimeException('Choose an administrator and provide a new password through the secure prompt wrapper.');
        }
        $provisioner->rotatePassword($identifier, $newPassword);
        (new DatabaseLoginAttemptLimiter($pdo, $config->applicationKey()))->recordSuccess($identifier);
        unset($newPassword);
        fwrite(STDOUT, "Administrator password rotated.\n");
    }
} catch (Throwable $exception) {
    unset($shubhamPassword, $sounakPassword, $newPassword);
    fwrite(STDERR, "Administrator credential operation failed. Check deployment configuration and account state.\n");
    fwrite(STDERR, 'Safe diagnostic: ' . AdminProvisioningDiagnostic::format($exception) . "\n");
    exit(1);
} finally {
    putenv('ADMIN_SHUBHAM_PASSWORD');
    putenv('ADMIN_SOUNAK_PASSWORD');
    putenv('ADMIN_NEW_PASSWORD');
}
