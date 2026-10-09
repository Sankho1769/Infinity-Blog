<?php
declare(strict_types=1);

namespace InfinityBlog\Users;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class AdminUserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array{users: list<array<string, mixed>>, total: int} */
    public function listAuthors(int $page, int $perPage): array
    {
        $total = (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'author'")->fetchColumn();
        $statement = $this->pdo->prepare(
            "SELECT id, username, display_name, role, status, must_change_password, last_login_at, created_at, updated_at " .
            "FROM users WHERE role = 'author' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return [
            'users' => array_map(static fn (array $row): array => self::mapSafeUser($row), $statement->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findAuthor(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, username, display_name, role, status, must_change_password, last_login_at, created_at, updated_at " .
            "FROM users WHERE id = :id AND role = 'author' LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::mapSafeUser($row) : null;
    }

    /** @return array<string, mixed> */
    public function createAuthor(string $username, string $displayName, string $passwordHash, int $actorId): array
    {
        $this->begin();
        try {
            $insert = $this->pdo->prepare(
                "INSERT INTO users (username, display_name, password_hash, role, status) " .
                "VALUES (:username, :display_name, :password_hash, 'author', 'active')"
            );
            try {
                $insert->execute([
                    'username' => $username,
                    'display_name' => $displayName,
                    'password_hash' => $passwordHash,
                ]);
            } catch (PDOException $exception) {
                if (self::isUsernameConflict($exception)) {
                    throw new AccountConflictException();
                }
                throw $exception;
            }
            $userId = (int) $this->pdo->lastInsertId();
            $this->writeAudit('user.created', $userId, $actorId, ['username' => $username, 'role' => 'author']);
            $user = $this->findAuthor($userId);
            if ($user === null) {
                throw new RuntimeException('The new author account could not be loaded.');
            }
            $this->pdo->commit();
            return $user;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @param array{display_name?: string, password_hash?: string} $changes
     *  @return array<string, mixed>
     */
    public function updateAuthor(int $userId, array $changes, int $actorId): array
    {
        $this->begin();
        try {
            $current = $this->findAuthorForUpdate($userId);
            if ($current === null) {
                throw new AccountNotFoundException();
            }
            $sets = [];
            $parameters = ['id' => $userId];
            if (array_key_exists('display_name', $changes)) {
                $sets[] = 'display_name = :display_name';
                $parameters['display_name'] = $changes['display_name'];
            }
            if (array_key_exists('password_hash', $changes)) {
                $sets[] = 'password_hash = :password_hash';
                $sets[] = 'auth_version = auth_version + 1';
                $parameters['password_hash'] = $changes['password_hash'];
            }
            $sets[] = 'updated_at = :updated_at';
            $parameters['updated_at'] = gmdate('Y-m-d H:i:s');
            $update = $this->pdo->prepare(
                "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id AND role = 'author'"
            );
            $update->execute($parameters);
            $this->writeAudit(
                array_key_exists('password_hash', $changes) ? 'user.password_reset' : 'user.updated',
                $userId,
                $actorId,
                ['fields' => array_map(static fn (string $field): string => $field === 'password_hash' ? 'password' : $field, array_keys($changes))]
            );
            $user = $this->findAuthor($userId);
            if ($user === null) {
                throw new AccountNotFoundException();
            }
            $this->pdo->commit();
            return $user;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function setActive(int $userId, bool $active, int $actorId): array
    {
        $this->begin();
        try {
            $current = $this->findAuthorForUpdate($userId);
            if ($current === null) {
                throw new AccountNotFoundException();
            }
            $status = $active ? 'active' : 'inactive';
            if ($current['status'] === $status) {
                $this->pdo->commit();
                return $current;
            }
            $update = $this->pdo->prepare(
                "UPDATE users SET status = :status, auth_version = auth_version + 1, updated_at = :updated_at " .
                "WHERE id = :id AND role = 'author'"
            );
            $update->execute([
                'status' => $status,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => $userId,
            ]);
            $this->writeAudit(
                $active ? 'user.activated' : 'user.deactivated',
                $userId,
                $actorId,
                ['username' => $current['username']]
            );
            $user = $this->findAuthor($userId);
            if ($user === null) {
                throw new AccountNotFoundException();
            }
            $this->pdo->commit();
            return $user;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function findAuthorForUpdate(int $userId): ?array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare(
            "SELECT id, username, display_name, role, status, must_change_password, last_login_at, created_at, updated_at " .
            "FROM users WHERE id = :id AND role = 'author' LIMIT 1" . $lock
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::mapSafeUser($row) : null;
    }

    private function writeAudit(string $action, int $userId, int $actorId, array $metadata): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata) ' .
            'VALUES (:actor_id, :action, \'user\', :entity_id, :metadata)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'entity_id' => $userId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
            throw new RuntimeException('The account operation could not begin.');
        }
    }

    private function rollback(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }
        try {
            $this->pdo->rollBack();
        } catch (Throwable) {
            // Preserve the original failure; callers receive only a generic API error.
        }
    }

    private static function isUsernameConflict(PDOException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));
        return ($driverCode === 1062 && str_contains($message, 'uq_users_username'))
            || ($driverCode === 19 && str_contains($message, 'unique constraint failed: users.username'));
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private static function mapSafeUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'display_name' => (string) $row['display_name'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'must_change_password' => (bool) $row['must_change_password'],
            'last_login_at' => $row['last_login_at'] === null ? null : (string) $row['last_login_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
