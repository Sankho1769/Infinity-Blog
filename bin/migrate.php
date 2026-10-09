<?php
declare(strict_types=1);

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Config\EnvironmentLoader;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Database\MigrationRunner;

require dirname(__DIR__) . '/autoload.php';

$arguments = array_slice($argv, 1);
$command = $arguments[0] ?? 'up';
if (!in_array($command, ['up', 'status', 'down'], true)) {
    fwrite(STDERR, "Usage: php bin/migrate.php [up|status|down --confirm-data-loss]\n");
    exit(2);
}

$environment = EnvironmentLoader::loadFile(dirname(__DIR__) . '/.env', getenv());
foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD', 'SSL_CA'] as $suffix) {
    if (isset($environment['MIGRATION_DB_' . $suffix])) {
        $environment['DB_' . $suffix] = $environment['MIGRATION_DB_' . $suffix];
    }
}

try {
    $config = AppConfig::fromArray($environment);
    $pdo = ConnectionFactory::create($config->database);
    $runner = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');

    if ($command === 'status') {
        $applied = $runner->appliedMigrations();
        echo $applied === [] ? "No migrations applied.\n" : implode("\n", $applied) . "\n";
        exit(0);
    }

    if ($command === 'down') {
        if ($config->environment === 'production') {
            throw new RuntimeException('Schema rollback is disabled in production.');
        }
        $rolledBack = $runner->rollbackLast(in_array('--confirm-data-loss', $arguments, true));
        echo $rolledBack === null ? "No applied migration to roll back.\n" : "Rolled back {$rolledBack}.\n";
        exit(0);
    }

    $applied = $runner->migrate();
    echo $applied === [] ? "Database schema is up to date.\n" : "Applied: " . implode(', ', $applied) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Database migration command failed. Check the private server configuration and database logs.\n");
    exit(1);
}
