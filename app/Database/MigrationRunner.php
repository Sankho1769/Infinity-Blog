<?php
declare(strict_types=1);

namespace InfinityBlog\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private PDO $pdo;
    private string $directory;

    public function __construct(PDO $pdo, string $directory)
    {
        $this->pdo = $pdo;
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->assertSupportedMySqlVersion();
        $this->ensureMigrationTable();
        $applied = $this->appliedMigrations();
        $newlyApplied = [];

        foreach ($this->migrationFiles() as $migration) {
            if (in_array($migration['id'], $applied, true)) {
                continue;
            }

            try {
                ($migration['up'])($this->pdo);
                $statement = $this->pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
                $statement->execute(['migration' => $migration['id']]);
            } catch (Throwable) {
                throw new RuntimeException('A database migration failed. No migration details were exposed.', 0);
            }

            $newlyApplied[] = $migration['id'];
        }

        return $newlyApplied;
    }

    /** @return list<string> */
    public function appliedMigrations(): array
    {
        $exists = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        $exists->execute(['table_name' => 'schema_migrations']);
        if ($exists->fetchColumn() === false) {
            return [];
        }

        $statement = $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration');

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function rollbackLast(bool $confirmDataLoss): ?string
    {
        if (!$confirmDataLoss) {
            throw new InvalidArgumentException('Rolling back a schema migration requires explicit data-loss confirmation.');
        }

        $applied = $this->appliedMigrations();
        if ($applied === []) {
            return null;
        }

        $migrationId = end($applied);
        foreach ($this->migrationFiles() as $migration) {
            if ($migration['id'] !== $migrationId) {
                continue;
            }

            try {
                ($migration['down'])($this->pdo);
                $statement = $this->pdo->prepare('DELETE FROM schema_migrations WHERE migration = :migration');
                $statement->execute(['migration' => $migrationId]);
            } catch (Throwable) {
                throw new RuntimeException('The database migration rollback failed. No migration details were exposed.', 0);
            }

            return $migrationId;
        }

        throw new RuntimeException('An applied migration file is missing.');
    }

    private function ensureMigrationTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'migration VARCHAR(120) NOT NULL PRIMARY KEY, ' .
            'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function assertSupportedMySqlVersion(): void
    {
        $version = $this->pdo->query('SELECT VERSION()')->fetchColumn();
        if (!is_string($version)
            || stripos($version, 'MariaDB') !== false
            || preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches) !== 1
            || version_compare($matches[1], '8.0.16', '<')) {
            throw new RuntimeException('Database migrations require MySQL 8.0.16 or newer.');
        }
    }

    /** @return list<array{id: string, up: callable, down: callable}> */
    private function migrationFiles(): array
    {
        $paths = glob($this->directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($paths, SORT_STRING);
        $migrations = [];

        foreach ($paths as $path) {
            $migration = require $path;
            if (!is_array($migration)
                || !isset($migration['id'], $migration['up'], $migration['down'])
                || !is_string($migration['id'])
                || !is_callable($migration['up'])
                || !is_callable($migration['down'])) {
                throw new RuntimeException('A migration file has an invalid format.');
            }

            $migrations[] = $migration;
        }

        return $migrations;
    }
}
