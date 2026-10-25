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
            $normalizedIdentifier = self::normalizeIdentifier($email);
            $isEmail = str_contains($email, '@');

            if (!$isEmail) {
                $username = $normalizedIdentifier;
                $emailVal = isset($profile['email']) && is_string($profile['email']) ? self::normalizeIdentifier($profile['email']) : $normalizedIdentifier;
            } else {
                $emailVal = $normalizedIdentifier;
                if (!empty($profile['username']) && is_string($profile['username'])) {
                    $cleanCandidate = \InfinityBlog\Support\UserIdentityHelper::cleanHandle($profile['username']);
                } else {
                    $cleanCandidate = null;
                }

                if (empty($cleanCandidate)) {
                    $username = \InfinityBlog\Support\UserIdentityHelper::generateHandle($this->pdo, $displayName);
                } else {
                    $username = \InfinityBlog\Support\UserIdentityHelper::ensureUniqueHandle($this->pdo, $cleanCandidate);
                }
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO users (username, email, display_name, password_hash, role, status, auth_version, must_change_password) ' .
                "VALUES (:username, :email, :display_name, :password_hash, 'user', 'active', 1, 0)"
            );
            $statement->execute([
                'username' => $username,
                'email' => $emailVal,
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

        if (array_key_exists('instagram_url', $fields)) {
            $raw = is_string($fields['instagram_url']) ? trim($fields['instagram_url']) : null;
            if ($raw !== null && $raw !== '') {
                $norm = \InfinityBlog\Support\UserIdentityHelper::normalizeSocialUrl($raw, 'instagram');
                if ($norm === null || strlen($norm) > 255) {
                    $errors['instagram_url'] = 'Instagram must be a valid handle (e.g. @username) or URL.';
                } else {
                    $updates[] = 'instagram_url = :instagram_url';
                    $params['instagram_url'] = $norm;
                }
            } else {
                $updates[] = 'instagram_url = :instagram_url';
                $params['instagram_url'] = null;
            }
        }

        if (array_key_exists('facebook_url', $fields)) {
            $raw = is_string($fields['facebook_url']) ? trim($fields['facebook_url']) : null;
            if ($raw !== null && $raw !== '') {
                $norm = \InfinityBlog\Support\UserIdentityHelper::normalizeSocialUrl($raw, 'facebook');
                if ($norm === null || strlen($norm) > 255) {
                    $errors['facebook_url'] = 'Facebook must be a valid handle or URL.';
                } else {
                    $updates[] = 'facebook_url = :facebook_url';
                    $params['facebook_url'] = $norm;
                }
            } else {
                $updates[] = 'facebook_url = :facebook_url';
                $params['facebook_url'] = null;
            }
        }

        if (array_key_exists('github_url', $fields)) {
            $raw = is_string($fields['github_url']) ? trim($fields['github_url']) : null;
            if ($raw !== null && $raw !== '') {
                $norm = \InfinityBlog\Support\UserIdentityHelper::normalizeSocialUrl($raw, 'github');
                if ($norm === null || strlen($norm) > 255) {
                    $errors['github_url'] = 'GitHub must be a valid username or URL.';
                } else {
                    $updates[] = 'github_url = :github_url';
                    $params['github_url'] = $norm;
                }
            } else {
                $updates[] = 'github_url = :github_url';
                $params['github_url'] = null;
            }
        }

        if (!array_key_exists('twitter_url', $fields) && array_key_exists('x_url', $fields)) {
            $fields['twitter_url'] = $fields['x_url'];
        }

        if (array_key_exists('twitter_url', $fields)) {
            $raw = is_string($fields['twitter_url']) ? trim($fields['twitter_url']) : null;
            if ($raw !== null && $raw !== '') {
                $norm = \InfinityBlog\Support\UserIdentityHelper::normalizeSocialUrl($raw, 'twitter');
                if ($norm === null || strlen($norm) > 255) {
                    $errors['twitter_url'] = 'X / Twitter must be a valid handle (e.g. @username) or URL.';
                } else {
                    $updates[] = 'twitter_url = :twitter_url';
                    $params['twitter_url'] = $norm;
                }
            } else {
                $updates[] = 'twitter_url = :twitter_url';
                $params['twitter_url'] = null;
            }
        }

        if (array_key_exists('linkedin_url', $fields)) {
            $raw = is_string($fields['linkedin_url']) ? trim($fields['linkedin_url']) : null;
            if ($raw !== null && $raw !== '') {
                $norm = \InfinityBlog\Support\UserIdentityHelper::normalizeSocialUrl($raw, 'linkedin');
                if ($norm === null || strlen($norm) > 255) {
                    $errors['linkedin_url'] = 'LinkedIn must be a valid profile handle (e.g. in/username) or URL.';
                } else {
                    $updates[] = 'linkedin_url = :linkedin_url';
                    $params['linkedin_url'] = $norm;
                }
            } else {
                $updates[] = 'linkedin_url = :linkedin_url';
                $params['linkedin_url'] = null;
            }
        }

        if (array_key_exists('whatsapp_number', $fields)) {
            $raw = is_string($fields['whatsapp_number']) ? trim($fields['whatsapp_number']) : null;
            if ($raw !== null && $raw !== '') {
                $norm = \InfinityBlog\Support\UserIdentityHelper::normalizeWhatsAppNumber($raw);
                if ($norm === null) {
                    $errors['whatsapp_number'] = 'WhatsApp number must include country code in international format (e.g. +14155552671).';
                } else {
                    $updates[] = 'whatsapp_number = :whatsapp_number';
                    $params['whatsapp_number'] = $norm;
                }
            } else {
                $updates[] = 'whatsapp_number = :whatsapp_number';
                $params['whatsapp_number'] = null;
            }
        }

        if (array_key_exists('whatsapp_public', $fields)) {
            $isPublic = !empty($fields['whatsapp_public']) && ($fields['whatsapp_public'] === true || $fields['whatsapp_public'] === 1 || $fields['whatsapp_public'] === '1' || $fields['whatsapp_public'] === 'on');
            $updates[] = 'whatsapp_public = :whatsapp_public';
            $params['whatsapp_public'] = $isPublic ? 1 : 0;
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

        if (array_key_exists('show_in_recommendations', $fields)) {
            $showInRec = !empty($fields['show_in_recommendations']) && ($fields['show_in_recommendations'] === true || $fields['show_in_recommendations'] === 1 || $fields['show_in_recommendations'] === '1');
            $updates[] = 'show_in_recommendations = :show_in_recommendations';
            $params['show_in_recommendations'] = $showInRec ? 1 : 0;
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

            return array_map(static function (array $row): array {
                $displayName = (string) $row['display_name'];
                $username = (string) $row['username'];
                $avatarPath = isset($row['avatar_path']) && is_string($row['avatar_path']) ? $row['avatar_path'] : null;
                $bannerPath = isset($row['banner_path']) && is_string($row['banner_path']) ? $row['banner_path'] : null;

                return [
                    'id' => (int) $row['id'],
                    'display_name' => $displayName,
                    'username' => $username,
                    'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($username, $displayName),
                    'role' => (string) $row['role'],
                    'post_count' => (int) $row['post_count'],
                    'bio' => isset($row['bio']) && is_string($row['bio']) ? $row['bio'] : null,
                    'location' => isset($row['location']) && is_string($row['location']) ? $row['location'] : null,
                    'interests' => isset($row['interests']) && is_string($row['interests']) ? $row['interests'] : null,
                    'website' => isset($row['website']) && is_string($row['website']) ? $row['website'] : null,
                    'avatar_path' => $avatarPath,
                    'avatar_url' => \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($avatarPath),
                    'banner_path' => $bannerPath,
                    'banner_url' => \InfinityBlog\Support\UserIdentityHelper::resolveBannerUrl($bannerPath),
                ];
            }, $statement->fetchAll(PDO::FETCH_ASSOC));
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

            return array_map(static function (array $row): array {
                $displayName = (string) $row['display_name'];
                $username = (string) $row['username'];
                return [
                    'id' => (int) $row['id'],
                    'display_name' => $displayName,
                    'username' => $username,
                    'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($username, $displayName),
                    'role' => (string) $row['role'],
                    'post_count' => (int) $row['post_count'],
                    'bio' => null,
                    'location' => null,
                    'interests' => null,
                    'website' => null,
                    'avatar_path' => null,
                    'avatar_url' => null,
                    'banner_path' => null,
                    'banner_url' => null,
                ];
            }, $statement->fetchAll(PDO::FETCH_ASSOC));
        }
    }

    public function findPublicAuthor(int $id): ?array
    {
        $cols = $this->getUserColumnNames();
        $hasSocial = in_array('instagram_url', $cols, true);
        $hasLinkedin = in_array('linkedin_url', $cols, true);
        $hasProfile = in_array('bio', $cols, true);
        $hasVisibility = in_array('profile_visibility', $cols, true);

        $linkedinSelect = $hasLinkedin ? ', u.linkedin_url' : '';
        $socialSelect = $hasSocial
            ? "u.instagram_url, u.facebook_url, u.github_url, u.whatsapp_number, u.whatsapp_public, u.twitter_url{$linkedinSelect}, "
            : '';
        $profileSelect = $hasProfile
            ? 'u.bio, u.location, u.interests, u.website, u.avatar_path, u.banner_path, '
            : '';
        $visibilityWhere = $hasVisibility
            ? " AND (u.profile_visibility IS NULL OR u.profile_visibility <> 'private')"
            : '';

        $groupBy = 'u.id, u.display_name, u.username, u.role';
        if ($hasProfile) {
            $groupBy .= ', u.bio, u.location, u.interests, u.website, u.avatar_path, u.banner_path';
        }
        if ($hasSocial) {
            $groupBy .= ", u.instagram_url, u.facebook_url, u.github_url, u.whatsapp_number, u.whatsapp_public, u.twitter_url{$linkedinSelect}";
        }

        try {
            $statement = $this->pdo->prepare(
                "SELECT u.id, u.display_name, u.username, u.role, {$profileSelect}{$socialSelect}COUNT(p.id) AS post_count " .
                "FROM users u " .
                "LEFT JOIN posts p ON p.author_id = u.id AND p.status = 'published' " .
                "WHERE u.id = :id AND u.status = 'active'{$visibilityWhere} " .
                "GROUP BY {$groupBy} LIMIT 1"
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            $displayName = (string) $row['display_name'];
            $username = (string) $row['username'];
            $avatarPath = isset($row['avatar_path']) && is_string($row['avatar_path']) ? $row['avatar_path'] : null;
            $bannerPath = isset($row['banner_path']) && is_string($row['banner_path']) ? $row['banner_path'] : null;
            $whatsappPublic = !empty($row['whatsapp_public']);
            $whatsappNumber = isset($row['whatsapp_number']) && is_string($row['whatsapp_number']) ? $row['whatsapp_number'] : null;
            $whatsappLink = ($whatsappPublic && !empty($whatsappNumber))
                ? \InfinityBlog\Support\UserIdentityHelper::formatWhatsAppLink($whatsappNumber)
                : null;

            return [
                'id' => (int) $row['id'],
                'display_name' => $displayName,
                'username' => $username,
                'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($username, $displayName),
                'role' => (string) $row['role'],
                'post_count' => (int) $row['post_count'],
                'bio' => isset($row['bio']) && is_string($row['bio']) ? $row['bio'] : null,
                'location' => isset($row['location']) && is_string($row['location']) ? $row['location'] : null,
                'interests' => isset($row['interests']) && is_string($row['interests']) ? $row['interests'] : null,
                'website' => isset($row['website']) && is_string($row['website']) ? $row['website'] : null,
                'instagram_url' => isset($row['instagram_url']) && is_string($row['instagram_url']) ? $row['instagram_url'] : null,
                'facebook_url' => isset($row['facebook_url']) && is_string($row['facebook_url']) ? $row['facebook_url'] : null,
                'github_url' => isset($row['github_url']) && is_string($row['github_url']) ? $row['github_url'] : null,
                'whatsapp_number' => $whatsappPublic ? $whatsappNumber : null,
                'whatsapp_public' => $whatsappPublic,
                'whatsapp_link' => $whatsappLink,
                'twitter_url' => isset($row['twitter_url']) && is_string($row['twitter_url']) ? $row['twitter_url'] : null,
                'x_url' => isset($row['twitter_url']) && is_string($row['twitter_url']) ? $row['twitter_url'] : null,
                'linkedin_url' => isset($row['linkedin_url']) && is_string($row['linkedin_url']) ? $row['linkedin_url'] : null,
                'avatar_path' => $avatarPath,
                'avatar_url' => \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($avatarPath),
                'banner_path' => $bannerPath,
                'banner_url' => \InfinityBlog\Support\UserIdentityHelper::resolveBannerUrl($bannerPath),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get suggested users for discovery (e.g. "People You Know"), strictly excluding the viewer by ID.
     *
     * @param ?int $viewerId Current authenticated user ID (excluded if provided)
     * @param int $limit Max number of suggestions (default 5)
     * @return list<array{
     *     id: int,
     *     display_name: string,
     *     username: string,
     *     handle: string,
     *     role: string,
     *     bio: ?string,
     *     avatar_path: ?string,
     *     avatar_url: ?string,
     *     banner_path: ?string,
     *     post_count: int,
     *     follower_count: int,
     *     is_following: bool
     * }>
     */
    public function getSuggestedUsers(?int $viewerId, int $limit = 5): array
    {
        $followRepo = new \InfinityBlog\Social\FollowRepository($this->pdo);
        return $followRepo->getSuggestedUsers($viewerId, $limit);
    }

    /**
     * Search registered active users for starting or opening a private chat.
     *
     * Supports:
     * - Full display name and partial display name
     * - Exact username and partial username (with or without leading @)
     * - Case-insensitive partial matching
     * - Excluding the authenticated viewer
     * - Respecting profile visibility and user blocks
     * - Never returning emails or sensitive credentials
     *
     * @return list<array{
     *     id: int,
     *     username: string,
     *     display_name: string,
     *     handle: string,
     *     avatar_path: ?string,
     *     avatar_url: ?string,
     *     bio: ?string,
     *     role: string,
     *     has_conversation: bool
     * }>
     */
    public function searchUsersForChat(string $query, int $viewerUserId, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));
        $clean = trim($query);
        $cleanNoAt = ltrim($clean, '@');

        // Check if user_blocks table exists
        $hasBlocks = false;
        try {
            $test = $this->pdo->query('SELECT 1 FROM user_blocks LIMIT 0');
            $hasBlocks = ($test !== false);
        } catch (\Throwable) {
            $hasBlocks = false;
        }

        $blockClause = '';
        if ($hasBlocks) {
            $blockClause = ' AND NOT EXISTS (SELECT 1 FROM user_blocks ub WHERE (ub.user_id = :me_block1 AND ub.blocked_user_id = u.id) OR (ub.user_id = u.id AND ub.blocked_user_id = :me_block2))';
        }

        $cols = $this->getUserColumnNames();
        $hasBio = in_array('bio', $cols, true);
        $hasAvatar = in_array('avatar_path', $cols, true);
        $hasVisibility = in_array('profile_visibility', $cols, true);

        $bioCol = $hasBio ? 'u.bio' : 'NULL AS bio';
        $avatarCol = $hasAvatar ? 'u.avatar_path' : 'NULL AS avatar_path';
        $visClause = $hasVisibility ? " AND (u.profile_visibility IS NULL OR u.profile_visibility <> 'private')" : '';

        $sql = "SELECT u.id, u.username, u.display_name, u.role, {$bioCol}, {$avatarCol} " .
               "FROM users u " .
               "WHERE u.status = 'active' AND u.id <> :viewer_id{$visClause}{$blockClause}";

        $params = [
            ':viewer_id' => $viewerUserId,
        ];
        if ($hasBlocks) {
            $params[':me_block1'] = $viewerUserId;
            $params[':me_block2'] = $viewerUserId;
        }

        if ($cleanNoAt !== '') {
            $sql .= ' AND (LOWER(u.display_name) LIKE :q1 OR LOWER(u.username) LIKE :q2)';
            $like = '%' . strtolower($cleanNoAt) . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;

            // Prioritize: 1. Exact username match, 2. Exact display name match, 3. Starts with username, 4. Starts with display name, 5. Alphabetical
            $sql .= ' ORDER BY ' .
                    'CASE ' .
                    '  WHEN LOWER(u.username) = :exact1 THEN 0 ' .
                    '  WHEN LOWER(u.display_name) = :exact2 THEN 1 ' .
                    '  WHEN LOWER(u.username) LIKE :prefix1 THEN 2 ' .
                    '  WHEN LOWER(u.display_name) LIKE :prefix2 THEN 3 ' .
                    '  ELSE 4 ' .
                    'END, u.display_name ASC ';
            $params[':exact1'] = strtolower($cleanNoAt);
            $params[':exact2'] = strtolower($cleanNoAt);
            $params[':prefix1'] = strtolower($cleanNoAt) . '%';
            $params[':prefix2'] = strtolower($cleanNoAt) . '%';
        } else {
            $sql .= ' ORDER BY u.display_name ASC ';
        }

        $sql .= 'LIMIT :lim';

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch list of existing conversation partner IDs for viewer
            $convPartners = [];
            try {
                $cpStmt = $this->pdo->prepare(
                    'SELECT DISTINCT CASE WHEN sender_id = :u1 THEN recipient_id ELSE sender_id END AS partner_id ' .
                    'FROM direct_messages WHERE sender_id = :u2 OR recipient_id = :u3'
                );
                $cpStmt->execute([':u1' => $viewerUserId, ':u2' => $viewerUserId, ':u3' => $viewerUserId]);
                $convPartners = array_map('intval', $cpStmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (\Throwable) {
            }

            return array_map(function (array $r) use ($convPartners): array {
                $uId = (int) $r['id'];
                $dName = (string) $r['display_name'];
                $uName = (string) $r['username'];
                $avatarPath = isset($r['avatar_path']) && is_string($r['avatar_path']) ? $r['avatar_path'] : null;

                return [
                    'id' => $uId,
                    'username' => $uName,
                    'display_name' => $dName,
                    'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($uName, $dName),
                    'avatar_path' => $avatarPath,
                    'avatar_url' => \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($avatarPath),
                    'bio' => isset($r['bio']) && is_string($r['bio']) ? $r['bio'] : null,
                    'role' => (string) $r['role'],
                    'has_conversation' => in_array($uId, $convPartners, true),
                ];
            }, $rows);
        } catch (\Throwable) {
            return [];
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
        $instagramUrl = isset($row['instagram_url']) && is_string($row['instagram_url']) ? (string) $row['instagram_url'] : null;
        $facebookUrl = isset($row['facebook_url']) && is_string($row['facebook_url']) ? (string) $row['facebook_url'] : null;
        $githubUrl = isset($row['github_url']) && is_string($row['github_url']) ? (string) $row['github_url'] : null;
        $whatsappNumber = isset($row['whatsapp_number']) && is_string($row['whatsapp_number']) ? (string) $row['whatsapp_number'] : null;
        $whatsappPublic = !empty($row['whatsapp_public']);
        $twitterUrl = isset($row['twitter_url']) && is_string($row['twitter_url']) ? (string) $row['twitter_url'] : null;
        $linkedinUrl = isset($row['linkedin_url']) && is_string($row['linkedin_url']) ? (string) $row['linkedin_url'] : null;
        $showInRecommendations = !isset($row['show_in_recommendations']) || (int) $row['show_in_recommendations'] === 1;

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
            $mobileVerifiedAt,
            $instagramUrl,
            $facebookUrl,
            $githubUrl,
            $whatsappNumber,
            $whatsappPublic,
            $twitterUrl,
            $linkedinUrl,
            $showInRecommendations
        );
    }
}
