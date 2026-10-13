<?php
declare(strict_types=1);

namespace InfinityBlog\Social;

use InvalidArgumentException;
use PDO;
use Throwable;

final class FollowRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Follow a user. Prevents self-following and duplicate follows.
     */
    public function follow(int $followerId, int $followingId): bool
    {
        if ($followerId === $followingId) {
            throw new InvalidArgumentException('Users cannot follow themselves.');
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertSql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO user_follows (follower_id, following_id, created_at) VALUES (:follower_id, :following_id, CURRENT_TIMESTAMP)'
            : 'INSERT IGNORE INTO user_follows (follower_id, following_id, created_at) VALUES (:follower_id, :following_id, NOW())';
        $stmt = $this->pdo->prepare($insertSql);
        $stmt->execute([
            'follower_id' => $followerId,
            'following_id' => $followingId,
        ]);

        if ($stmt->rowCount() > 0) {
            try {
                $uStmt = $this->pdo->prepare('SELECT display_name, username FROM users WHERE id = :id LIMIT 1');
                $uStmt->execute([':id' => $followerId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                $name = $uRow['display_name'] ?? $uRow['username'] ?? 'Someone';

                $notifs = new NotificationRepository($this->pdo);
                $notifs->createNotification(
                    $followingId,
                    $followerId,
                    'follow',
                    $followerId,
                    "{$name} started following you."
                );
            } catch (Throwable) {
                // Ignore notification failure
            }

            return true;
        }

        return false;
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(int $followerId, int $followingId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_follows WHERE follower_id = :follower_id AND following_id = :following_id'
        );
        $stmt->execute([
            'follower_id' => $followerId,
            'following_id' => $followingId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a user is following another user.
     */
    public function isFollowing(int $followerId, int $followingId): bool
    {
        if ($followerId === $followingId) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM user_follows WHERE follower_id = :follower_id AND following_id = :following_id LIMIT 1'
        );
        $stmt->execute([
            'follower_id' => $followerId,
            'following_id' => $followingId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get follower count for a user.
     */
    public function getFollowerCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE following_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get following count for a user.
     */
    public function getFollowingCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE follower_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * List followers of a user with public profile information.
     *
     * @return list<array{id: int, username: string, display_name: string, avatar_path: ?string, bio: ?string}>
     */
    public function getFollowers(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_path, u.bio ' .
            'FROM user_follows uf ' .
            'JOIN users u ON u.id = uf.follower_id ' .
            'WHERE uf.following_id = :user_id AND u.status = \'active\' ' .
            'ORDER BY uf.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'username' => (string) $r['username'],
            'display_name' => (string) $r['display_name'],
            'avatar_path' => $r['avatar_path'] !== null ? (string) $r['avatar_path'] : null,
            'bio' => $r['bio'] !== null ? (string) $r['bio'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * List users that a given user is following.
     *
     * @return list<array{id: int, username: string, display_name: string, avatar_path: ?string, bio: ?string}>
     */
    public function getFollowing(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.display_name, u.avatar_path, u.bio ' .
            'FROM user_follows uf ' .
            'JOIN users u ON u.id = uf.following_id ' .
            'WHERE uf.follower_id = :user_id AND u.status = \'active\' ' .
            'ORDER BY uf.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'username' => (string) $r['username'],
            'display_name' => (string) $r['display_name'],
            'avatar_path' => $r['avatar_path'] !== null ? (string) $r['avatar_path'] : null,
            'bio' => $r['bio'] !== null ? (string) $r['bio'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
