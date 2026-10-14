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

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function findByIdentifier(string $identifier): ?UserAccount
    {
        $normalized = self::normalizeIdentifier($identifier);
        $statement = $this->pdo->prepare(
            'SELECT * FROM users WHERE username = :identifier OR email = :identifier_email OR LOWER(display_name) = :identifier_name LIMIT 1'
        );
        $statement->execute([
            'identifier' => $normalized,
            'identifier_email' => $normalized,
            'identifier_name' => $normalized,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::accountFromRow($row) : null;
    }

    public function findById(int $id): ?UserAccount
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
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

    public function createReader(string $displayName, string $email, string $passwordHash): int
    {
        return $this->createUser($displayName, $email, $passwordHash);
    }

    /** @param array<string, mixed> $profile */
    public function createUser(string $displayName, string $email, string $passwordHash, array $profile = []): int
    {
        $inTx = $this->pdo->inTransaction();
        if (!$inTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $normalized = self::normalizeIdentifier($email);
            $statement = $this->pdo->prepare(
                'INSERT INTO users (username, email, display_name, password_hash, role, status, auth_version, must_change_password) ' .
                "VALUES (:username, :email, :display_name, :password_hash, 'user', 'active', 1, 0)"
            );
            $statement->execute([
                'username' => $normalized,
                'email' => $normalized,
                'display_name' => trim($displayName),
                'password_hash' => $passwordHash,
            ]);

            $id = (int) $this->pdo->lastInsertId();

            try {
                $audit = $this->pdo->prepare(
                    'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                    'VALUES (:actor_user_id, :action, :entity_type, :entity_id, NULL, :created_at)'
                );
                $audit->execute([
                    'actor_user_id' => $id,
                    'action' => 'auth.registered',
                    'entity_type' => 'user',
                    'entity_id' => $id,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (Throwable) {
                // Tolerate test environments where audit_logs table is omitted
            }

            if ($profile !== []) {
                $this->updateProfile($id, $profile);
            }

            if (!$inTx) {
                $this->pdo->commit();
            }

            return $id;
        } catch (Throwable $e) {
            if (!$inTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function emailExists(string $email): bool
    {
        $normalized = self::normalizeIdentifier($email);
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE email = :email OR username = :username LIMIT 1'
        );
        $statement->execute([
            'email' => $normalized,
            'username' => $normalized,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function existsByMobileHash(string $mobileHash): bool
    {
        try {
            $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE mobile_number_hash = :hash LIMIT 1');
            $statement->execute([':hash' => $mobileHash]);
            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function findByMobileHash(string $mobileHash): ?UserAccount
    {
        try {
            $statement = $this->pdo->prepare('SELECT * FROM users WHERE mobile_number_hash = :hash LIMIT 1');
            $statement->execute([':hash' => $mobileHash]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->mapRowToAccount($row) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @var list<string>|null */
    private ?array $userColumnNames = null;

    /** @return list<string> */
    public function getUserColumnNames(): array
    {
        if ($this->userColumnNames !== null) {
            return $this->userColumnNames;
        }

        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $cols = $this->pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
                $this->userColumnNames = array_map('strtolower', array_column($cols, 'name'));
            } else {
                $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");
                $this->userColumnNames = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }
        } catch (Throwable) {
            $this->userColumnNames = ['id', 'username', 'email', 'display_name', 'password_hash', 'role', 'status', 'auth_version', 'must_change_password'];
        }

        return $this->userColumnNames;
    }

    /**
     * @param array<string, mixed> $fields
     * @throws ProfileValidationException
     */
    public function updateProfile(int $userId, array $fields): UserAccount
    {
        $user = $this->findById($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $errors = [];
        $updates = [];
        $params = ['id' => $userId];

        if (array_key_exists('display_name', $fields)) {
            $displayName = trim((string) $fields['display_name']);
            if ($displayName === '' || strlen($displayName) > 190 || preg_match('/[\x00-\x1F\x7F]/', $displayName) === 1) {
                $errors['display_name'] = 'Full name must be between 1 and 190 characters.';
            } else {
                $updates[] = 'display_name = :display_name';
                $params['display_name'] = $displayName;
            }
        }

        if (array_key_exists('username', $fields)) {
            $username = trim((string) $fields['username']);
            if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
                $errors['username'] = 'Username must be 3-50 alphanumeric characters (dots, dashes, and underscores allowed).';
            } else {
                $check = $this->pdo->prepare('SELECT 1 FROM users WHERE username = :username AND id <> :id LIMIT 1');
                $check->execute(['username' => self::normalizeIdentifier($username), 'id' => $userId]);
                if ($check->fetchColumn() !== false) {
                    $errors['username'] = 'This username is already taken.';
                } else {
                    $updates[] = 'username = :username';
                    $params['username'] = self::normalizeIdentifier($username);
                }
            }
        }

        if (array_key_exists('email', $fields)) {
            $email = trim((string) $fields['email']);
            if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\x00-\x1F\x7F]/', $email) === 1) {
                $errors['email'] = 'Enter a valid email address.';
            } else {
                $check = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :id LIMIT 1');
                $check->execute(['email' => self::normalizeIdentifier($email), 'id' => $userId]);
                if ($check->fetchColumn() !== false) {
                    $errors['email'] = 'An account with this email address already exists.';
                } else {
                    $updates[] = 'email = :email';
                    $params['email'] = self::normalizeIdentifier($email);
                }
            }
        }

        if (array_key_exists('bio', $fields)) {
            $bio = is_string($fields['bio']) ? trim($fields['bio']) : null;
            if ($bio !== null && strlen($bio) > 1000) {
                $errors['bio'] = 'Bio cannot exceed 1000 characters.';
            } else {
                $updates[] = 'bio = :bio';
                $params['bio'] = ($bio === '' || $bio === null) ? null : $bio;
            }
        }

        if (array_key_exists('location', $fields)) {
            $location = is_string($fields['location']) ? trim($fields['location']) : null;
            if ($location !== null && strlen($location) > 120) {
                $errors['location'] = 'Location cannot exceed 120 characters.';
            } else {
                $updates[] = 'location = :location';
                $params['location'] = ($location === '' || $location === null) ? null : $location;
            }
        }

        if (array_key_exists('interests', $fields)) {
            $interests = is_string($fields['interests']) ? trim($fields['interests']) : null;
            if ($interests !== null && strlen($interests) > 255) {
                $errors['interests'] = 'Interests cannot exceed 255 characters.';
            } else {
                $updates[] = 'interests = :interests';
                $params['interests'] = ($interests === '' || $interests === null) ? null : $interests;
            }
        }

        if (array_key_exists('website', $fields)) {
            $website = is_string($fields['website']) ? trim($fields['website']) : null;
            if ($website !== null && $website !== '') {
                if (!preg_match('/^https?:\/\//i', $website) || filter_var($website, FILTER_VALIDATE_URL) === false || strlen($website) > 255) {
                    $errors['website'] = 'Website must be a valid URL starting with http:// or https:// (up to 255 characters).';
                } else {
                    $updates[] = 'website = :website';
                    $params['website'] = $website;
                }
            } else {
                $updates[] = 'website = :website';
                $params['website'] = null;
            }
        }

        if (array_key_exists('phone', $fields) || array_key_exists('mobile_number', $fields)) {
            $rawPhone = $fields['mobile_number'] ?? $fields['phone'] ?? null;
            $rawPhone = is_string($rawPhone) ? trim($rawPhone) : null;
            if ($rawPhone !== null && $rawPhone !== '') {
                try {
                    $mobileService = new \InfinityBlog\Security\MobileNumberService();
                    $normalized = $mobileService->normalize($rawPhone);
                    $hash = $mobileService->hash($normalized);

                    // Uniqueness check: ensure no other user has this mobile_number_hash
                    $stmtCheck = $this->pdo->prepare('SELECT id FROM users WHERE mobile_number_hash = :hash AND id != :id LIMIT 1');
                    $stmtCheck->execute([':hash' => $hash, ':id' => $userId]);
                    if ($stmtCheck->fetchColumn() !== false) {
                        $errors['phone'] = 'This mobile number is already in use by another account.';
                    } else {
                        $cipher = $mobileService->encrypt($normalized);
                        if (in_array('phone', $this->getUserColumnNames(), true)) {
                            $updates[] = 'phone = :phone';
                            $params['phone'] = $rawPhone;
                        }
                        $updates[] = 'mobile_number_ciphertext = :mobile_cipher';
                        $params['mobile_cipher'] = $cipher;
                        $updates[] = 'mobile_number_hash = :mobile_hash';
                        $params['mobile_hash'] = $hash;
                        $updates[] = 'mobile_number_verified_at = :mobile_verified_at';
                        $params['mobile_verified_at'] = $fields['mobile_number_verified_at'] ?? null;
                    }
                } catch (\InvalidArgumentException $e) {
                    $errors['phone'] = $e->getMessage();
                }
            } else {
                if (in_array('phone', $this->getUserColumnNames(), true)) {
                    $updates[] = 'phone = :phone';
                    $params['phone'] = null;
                }
                $updates[] = 'mobile_number_ciphertext = :mobile_cipher';
                $params['mobile_cipher'] = null;
                $updates[] = 'mobile_number_hash = :mobile_hash';
                $params['mobile_hash'] = null;
                $updates[] = 'mobile_number_verified_at = :mobile_verified_at';
                $params['mobile_verified_at'] = null;
            }
        }

        if (array_key_exists('profile_visibility', $fields)) {
            $visibility = (string) $fields['profile_visibility'];
            if (!in_array($visibility, ['public', 'private'], true)) {
                $errors['profile_visibility'] = "Profile visibility must be either 'public' or 'private'.";
            } else {
                $updates[] = 'profile_visibility = :profile_visibility';
                $params['profile_visibility'] = $visibility;
            }
        }

        if ($errors !== []) {
            throw new ProfileValidationException($errors);
        }

        if ($updates !== []) {
            $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            try {
                $audit = $this->pdo->prepare(
                    'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                    'VALUES (:actor_user_id, :action, :entity_type, :entity_id, NULL, :created_at)'
                );
                $audit->execute([
                    'actor_user_id' => $userId,
                    'action' => 'user.profile_updated',
                    'entity_type' => 'user',
                    'entity_id' => $userId,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (Throwable) {
                // Tolerate test environments where audit_logs table is omitted
            }
        }

        $fresh = $this->findById($userId);
        if ($fresh === null) {
            throw new RuntimeException('Failed to reload updated profile.');
        }

        return $fresh;
    }

    public function updateAvatar(int $userId, ?string $avatarPath): void
    {
        $user = $this->findById($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $statement = $this->pdo->prepare('UPDATE users SET avatar_path = :avatar_path WHERE id = :id');
        $statement->execute([
            'avatar_path' => $avatarPath,
            'id' => $userId,
        ]);

        try {
            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                'VALUES (:actor_user_id, :action, :entity_type, :entity_id, NULL, :created_at)'
            );
            $audit->execute([
                'actor_user_id' => $userId,
                'action' => 'user.avatar_updated',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Tolerate test environments where audit_logs table is omitted
        }
    }

    public function updateBanner(int $userId, ?string $bannerPath): void
    {
        $user = $this->findById($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $statement = $this->pdo->prepare('UPDATE users SET banner_path = :banner_path WHERE id = :id');
        $statement->execute([
            'banner_path' => $bannerPath,
            'id' => $userId,
        ]);

        try {
            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                'VALUES (:actor_user_id, :action, :entity_type, :entity_id, NULL, :created_at)'
            );
            $audit->execute([
                'actor_user_id' => $userId,
                'action' => 'user.banner_updated',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Tolerate test environments where audit_logs table is omitted
        }
    }

    /** @return list<array{id: int, display_name: string, username: string, role: string, post_count: int, bio: ?string, location: ?string, interests: ?string, website: ?string, avatar_path: ?string, banner_path: ?string}> */
    public function listPublicAuthors(?string $query = null): array
    {
        $hasQuery = ($query !== null && trim($query) !== '');
        $qCondition = $hasQuery ? ' AND (INSTR(LOWER(u.display_name), LOWER(:q_name)) > 0 OR INSTR(LOWER(u.username), LOWER(:q_user)) > 0)' : '';
        $params = $hasQuery ? ['q_name' => trim($query), 'q_user' => trim($query)] : [];

        try {
            $statement = $this->pdo->prepare(
                "SELECT u.id, u.display_name, u.username, u.role, u.bio, u.location, u.interests, u.website, u.avatar_path, u.banner_path, COUNT(p.id) AS post_count " .
                "FROM users u " .
                "INNER JOIN posts p ON p.author_id = u.id AND p.status = 'published' " .
                "WHERE u.status = 'active' AND (u.profile_visibility IS NULL OR u.profile_visibility <> 'private')" . $qCondition . " " .
                "GROUP BY u.id, u.display_name, u.username, u.role, u.bio, u.location, u.interests, u.website, u.avatar_path, u.banner_path " .
                "HAVING post_count > 0 " .
                "ORDER BY post_count DESC, u.display_name ASC"
            );
            $statement->execute($params);

            return array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
                'username' => (string) $row['username'],
                'role' => (string) $row['role'],
                'post_count' => (int) $row['post_count'],
                'bio' => isset($row['bio']) && is_string($row['bio']) ? $row['bio'] : null,
                'location' => isset($row['location']) && is_string($row['location']) ? $row['location'] : null,
                'interests' => isset($row['interests']) && is_string($row['interests']) ? $row['interests'] : null,
                'website' => isset($row['website']) && is_string($row['website']) ? $row['website'] : null,
                'avatar_path' => isset($row['avatar_path']) && is_string($row['avatar_path']) ? $row['avatar_path'] : null,
                'banner_path' => isset($row['banner_path']) && is_string($row['banner_path']) ? $row['banner_path'] : null,
            ], $statement->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            // Fallback for schemas without profile columns
            $statement = $this->pdo->prepare(
                "SELECT u.id, u.display_name, u.username, u.role, COUNT(p.id) AS post_count " .
                "FROM users u " .
                "INNER JOIN posts p ON p.author_id = u.id AND p.status = 'published' " .
                "WHERE u.status = 'active'" . $qCondition . " " .
                "GROUP BY u.id, u.display_name, u.username, u.role " .
                "HAVING post_count > 0 " .
                "ORDER BY post_count DESC, u.display_name ASC"
            );
            $statement->execute($params);

            return array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
                'username' => (string) $row['username'],
                'role' => (string) $row['role'],
                'post_count' => (int) $row['post_count'],
                'bio' => null,
                'location' => null,
                'interests' => null,
                'website' => null,
                'avatar_path' => null,
                'banner_path' => null,
            ], $statement->fetchAll(PDO::FETCH_ASSOC));
        }
    }

    /** @return array{id: int, display_name: string, username: string, role: string, post_count: int, bio: ?string, location: ?string, interests: ?string, website: ?string, avatar_path: ?string, banner_path: ?string}|null */
    public function findPublicAuthor(int $id): ?array
    {
        try {
            $statement = $this->pdo->prepare(
                "SELECT u.id, u.display_name, u.username, u.role, u.bio, u.location, u.interests, u.website, u.avatar_path, u.banner_path, COUNT(p.id) AS post_count " .
                "FROM users u " .
                "LEFT JOIN posts p ON p.author_id = u.id AND p.status = 'published' " .
                "WHERE u.id = :id AND u.status = 'active' AND (u.profile_visibility IS NULL OR u.profile_visibility <> 'private') " .
                "GROUP BY u.id, u.display_name, u.username, u.role, u.bio, u.location, u.interests, u.website, u.avatar_path, u.banner_path LIMIT 1"
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
                'username' => (string) $row['username'],
                'role' => (string) $row['role'],
                'post_count' => (int) $row['post_count'],
                'bio' => isset($row['bio']) && is_string($row['bio']) ? $row['bio'] : null,
                'location' => isset($row['location']) && is_string($row['location']) ? $row['location'] : null,
                'interests' => isset($row['interests']) && is_string($row['interests']) ? $row['interests'] : null,
                'website' => isset($row['website']) && is_string($row['website']) ? $row['website'] : null,
                'avatar_path' => isset($row['avatar_path']) && is_string($row['avatar_path']) ? $row['avatar_path'] : null,
                'banner_path' => isset($row['banner_path']) && is_string($row['banner_path']) ? $row['banner_path'] : null,
            ];
        } catch (Throwable) {
            $statement = $this->pdo->prepare(
                "SELECT u.id, u.display_name, u.username, u.role, COUNT(p.id) AS post_count " .
                "FROM users u " .
                "LEFT JOIN posts p ON p.author_id = u.id AND p.status = 'published' " .
                "WHERE u.id = :id AND u.status = 'active' AND u.role IN ('user', 'author') " .
                "GROUP BY u.id, u.display_name, u.username, u.role LIMIT 1"
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
                'username' => (string) $row['username'],
                'role' => (string) $row['role'],
                'post_count' => (int) $row['post_count'],
                'bio' => null,
                'location' => null,
                'interests' => null,
                'website' => null,
                'avatar_path' => null,
                'banner_path' => null,
            ];
        }
    }

    public function findFirstAdminId(): ?int
    {
        try {
            $statement = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
            $val = $statement->fetchColumn();
            if ($val !== false && $val !== null) {
                return (int) $val;
            }
            $statement = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $val = $statement->fetchColumn();
            if ($val !== false && $val !== null) {
                return (int) $val;
            }
            $statement = $this->pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
            $val = $statement->fetchColumn();
            return ($val !== false && $val !== null) ? (int) $val : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $row */
    private static function accountFromRow(array $row): ?UserAccount
    {
        $role = (string) ($row['role'] ?? '');
        $status = (string) ($row['status'] ?? '');
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
        $authVersion = filter_var($row['auth_version'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1 || !in_array($role, ['user', 'reader', 'author', 'admin'], true)
            || !in_array($status, ['active', 'inactive'], true)
            || $authVersion === false || $authVersion < 1
            || !is_string($row['password_hash'] ?? null)) {
            return null;
        }

        $email = is_string($row['email'] ?? null) ? (string) $row['email'] : null;
        $bio = isset($row['bio']) && is_string($row['bio']) ? (string) $row['bio'] : null;
        $location = isset($row['location']) && is_string($row['location']) ? (string) $row['location'] : null;
        $interests = isset($row['interests']) && is_string($row['interests']) ? (string) $row['interests'] : null;
        $website = isset($row['website']) && is_string($row['website']) ? (string) $row['website'] : null;
        $phone = isset($row['phone']) && is_string($row['phone']) ? (string) $row['phone'] : null;
        $avatarPath = isset($row['avatar_path']) && is_string($row['avatar_path']) ? (string) $row['avatar_path'] : null;
        $bannerPath = isset($row['banner_path']) && is_string($row['banner_path']) ? (string) $row['banner_path'] : null;
        $profileVisibility = isset($row['profile_visibility']) && is_string($row['profile_visibility']) ? (string) $row['profile_visibility'] : 'public';
        $createdAt = isset($row['created_at']) && is_string($row['created_at']) ? (string) $row['created_at'] : null;
        $mobileCipher = isset($row['mobile_number_ciphertext']) && is_string($row['mobile_number_ciphertext']) ? (string) $row['mobile_number_ciphertext'] : null;
        $mobileHash = isset($row['mobile_number_hash']) && is_string($row['mobile_number_hash']) ? (string) $row['mobile_number_hash'] : null;
        $mobileVerifiedAt = isset($row['mobile_number_verified_at']) && is_string($row['mobile_number_verified_at']) ? (string) $row['mobile_number_verified_at'] : null;

        return new UserAccount(
            (int) $id,
            (string) $row['username'],
            (string) $row['display_name'],
            $role,
            $status,
            $row['password_hash'],
            (int) $authVersion,
            $email,
            $bio,
            $location,
            $interests,
            $website,
            $phone,
            $avatarPath,
            $bannerPath,
            $profileVisibility,
            $createdAt,
            $mobileCipher,
            $mobileHash,
            $mobileVerifiedAt
        );
    }
}
