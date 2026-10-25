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
            'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle((string) $r['username'], (string) $r['display_name']),
            'display_name' => (string) $r['display_name'],
            'avatar_path' => $r['avatar_path'] !== null ? (string) $r['avatar_path'] : null,
            'avatar_url' => \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($r['avatar_path'] !== null ? (string) $r['avatar_path'] : null),
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
            'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle((string) $r['username'], (string) $r['display_name']),
            'display_name' => (string) $r['display_name'],
            'avatar_path' => $r['avatar_path'] !== null ? (string) $r['avatar_path'] : null,
            'avatar_url' => \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($r['avatar_path'] !== null ? (string) $r['avatar_path'] : null),
            'bio' => $r['bio'] !== null ? (string) $r['bio'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get suggested users for discovery (e.g. "People You Know"), strictly excluding the viewer.
     *
     * @param ?int $viewerId Current authenticated user ID (excluded from results if provided)
     * @param int $limit Max number of suggestions to return (default 5)
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
        $params = [];
        $where = [
            "u.status = 'active'",
            "(u.profile_visibility IS NULL OR u.profile_visibility <> 'private')",
            "(u.show_in_recommendations IS NULL OR u.show_in_recommendations = 1)",
        ];

        // Canonical database ID self-exclusion
        if ($viewerId !== null && $viewerId > 0) {
            $where[] = "u.id != :viewer_id";
            $params['viewer_id'] = $viewerId;
        }

        $whereSql = implode(' AND ', $where);

        if ($viewerId !== null && $viewerId > 0) {
            $followingSql = "(SELECT COUNT(*) FROM user_follows uf_rel WHERE uf_rel.follower_id = :viewer_rel AND uf_rel.following_id = u.id) > 0";
            $params['viewer_rel'] = $viewerId;
        } else {
            $followingSql = "0";
        }

        $fetchLimit = max(20, $limit * 4);
        $sql = "SELECT u.id, u.display_name, u.username, u.role, u.bio, u.avatar_path, u.banner_path, u.show_in_recommendations, " .
            "(SELECT COUNT(*) FROM posts p WHERE p.author_id = u.id AND p.status = 'published') AS post_count, " .
            "(SELECT COUNT(*) FROM user_follows uf_cnt WHERE uf_cnt.following_id = u.id) AS follower_count, " .
            "CASE WHEN {$followingSql} THEN 1 ELSE 0 END AS is_following " .
            "FROM users u " .
            "WHERE {$whereSql} " .
            "ORDER BY is_following ASC, (u.avatar_path IS NOT NULL AND u.avatar_path != '') DESC, (post_count > 0) DESC, follower_count DESC, post_count DESC, u.id DESC " .
            "LIMIT :fetch_limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':fetch_limit', $fetchLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $seenIds = [];

        foreach ($rows as $r) {
            $uid = (int) $r['id'];

            // Strictly exclude the viewer by canonical database ID
            if ($viewerId !== null && $uid === (int) $viewerId) {
                continue;
            }

            // Exclude accounts marked non-discoverable in recommendations
            if (isset($r['show_in_recommendations']) && (int) $r['show_in_recommendations'] === 0) {
                continue;
            }

            // Deduplicate strictly by canonical user ID
            if (isset($seenIds[$uid])) {
                continue;
            }
            $seenIds[$uid] = true;

            $displayName = (string) $r['display_name'];
            $username = (string) $r['username'];
            $avatarPath = $r['avatar_path'] !== null && $r['avatar_path'] !== '' ? (string) $r['avatar_path'] : null;

            $results[] = [
                'id' => $uid,
                'display_name' => $displayName,
                'username' => $username,
                'handle' => \InfinityBlog\Support\UserIdentityHelper::formatHandle($username, $displayName),
                'role' => (string) $r['role'],
                'bio' => $r['bio'] !== null ? (string) $r['bio'] : null,
                'avatar_path' => $avatarPath,
                'avatar_url' => \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($avatarPath),
                'banner_path' => $r['banner_path'] !== null ? (string) $r['banner_path'] : null,
                'show_in_recommendations' => isset($r['show_in_recommendations']) ? (int) $r['show_in_recommendations'] : 1,
                'post_count' => (int) $r['post_count'],
                'follower_count' => (int) $r['follower_count'],
                'is_following' => (bool) $r['is_following'],
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        // Final server-side invariant: strictly exclude viewer canonical ID
        if ($viewerId !== null && $viewerId > 0) {
            $canonicalViewer = (int) $viewerId;
            $results = array_values(array_filter(
                $results,
                static fn(array $u): bool => (int) ($u['id'] ?? 0) !== $canonicalViewer
            ));
        }

        // Final server-side invariant: strictly exclude non-discoverable accounts
        $results = array_values(array_filter(
            $results,
            static fn(array $u): bool => (int) ($u['show_in_recommendations'] ?? 1) === 1
        ));

        return $results;
    }
}
