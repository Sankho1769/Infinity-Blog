<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use PDO;
use RuntimeException;
use Throwable;

final class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByIdentifier(string $identifier): ?UserAccount
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, role, status, password_hash, auth_version FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => self::normalizeIdentifier($identifier)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::accountFromRow($row) : null;
    }

    public function findById(int $id): ?UserAccount
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, role, status, password_hash, auth_version FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::accountFromRow($row) : null;
    }

    public function recordSuccessfulLogin(int $id): void
    {
        if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
            throw new RuntimeException('Successful login details could not be recorded.');
        }

        try {
            $loggedInAt = gmdate('Y-m-d H:i:s');
            $statement = $this->pdo->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
            $statement->execute([
                'last_login_at' => $loggedInAt,
                'id' => $id,
            ]);

            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                'VALUES (:actor_user_id, :action, :entity_type, :entity_id, NULL, :created_at)'
            );
            $audit->execute([
                'actor_user_id' => $id,
                'action' => 'auth.login_succeeded',
                'entity_type' => 'user',
                'entity_id' => $id,
                'created_at' => $loggedInAt,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /**
     * Replace a stored password hash and revoke that account's existing sessions atomically.
     * Callers must complete server-side authorization before invoking this operation.
     */
    public function replacePasswordHashAndRevokeSessions(int $id, string $passwordHash): bool
    {
        if ($id < 1 || $passwordHash === '') {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, auth_version = auth_version + 1, ' .
            'updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['password_hash' => $passwordHash, 'id' => $id]);

        return $statement->rowCount() === 1;
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        return function_exists('mb_strtolower')
            ? mb_strtolower($identifier, 'UTF-8')
            : strtolower($identifier);
    }

    private function rollback(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }

        try {
            $this->pdo->rollBack();
        } catch (Throwable) {
            // Preserve the original audit or persistence failure.
        }
    }

    /** @param array<string, mixed> $row */
    private static function accountFromRow(array $row): ?UserAccount
    {
        $role = (string) ($row['role'] ?? '');
        $status = (string) ($row['status'] ?? '');
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
        $authVersion = filter_var($row['auth_version'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1 || !in_array($role, ['author', 'admin'], true)
            || !in_array($status, ['active', 'inactive'], true)
            || $authVersion === false || $authVersion < 1
            || !is_string($row['password_hash'] ?? null)) {
            return null;
        }

        return new UserAccount(
            (int) $id,
            (string) $row['username'],
            (string) $row['display_name'],
            $role,
            $status,
            $row['password_hash'],
            (int) $authVersion
        );
    }
}
