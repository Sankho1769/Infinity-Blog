<?php
declare(strict_types=1);

use InfinityBlog\Auth\AdminProvisioner;
use InfinityBlog\Auth\DatabaseLoginAttemptLimiter;
use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;

require dirname(__DIR__) . '/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$mode = $argv[1] ?? '';
if (!in_array($mode, ['provision', 'rotate'], true)) {
    fwrite(STDERR, "Usage: php bin/admin-credentials.php provision | rotate <admin-identifier>\n");
    exit(2);
}

try {
    $config = AppConfig::fromEnvironment();
    if ($config->environment === 'test') {
        throw new RuntimeException('Administrator provisioning is disabled in the test environment.');
    }
    $pdo = ConnectionFactory::create($config->database);
    $provisioner = new AdminProvisioner($pdo);

    if ($mode === 'provision') {
        $shubhamPassword = getenv('ADMIN_SHUBHAM_PASSWORD');
        $sounakPassword = getenv('ADMIN_SOUNAK_PASSWORD');
        if (!is_string($shubhamPassword) || !is_string($sounakPassword)) {
            throw new RuntimeException('Administrator passwords must be provided through the secure prompt wrapper.');
        }
        $provisioner->provision($shubhamPassword, $sounakPassword);
        unset($shubhamPassword, $sounakPassword);
        fwrite(STDOUT, "Initial administrator accounts provisioned.\n");
    } else {
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
} catch (Throwable) {
    unset($shubhamPassword, $sounakPassword, $newPassword);
    fwrite(STDERR, "Administrator credential operation failed. Check deployment configuration and account state.\n");
    exit(1);
} finally {
    putenv('ADMIN_SHUBHAM_PASSWORD');
    putenv('ADMIN_SOUNAK_PASSWORD');
    putenv('ADMIN_NEW_PASSWORD');
}
