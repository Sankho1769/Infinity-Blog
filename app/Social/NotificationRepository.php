<?php
declare(strict_types=1);

namespace InfinityBlog\Social;

use PDO;
use Throwable;

final class NotificationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a notification. Avoids self-notification if actor_id === user_id.
     */
    public function createNotification(
        int $userId,
        ?int $actorId,
        string $type,
        ?int $referenceId,
        string $message
    ): ?int {
        if ($actorId !== null && $actorId === $userId) {
            return null; // Don't notify self
        }

        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $timeSql = $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
            $stmt = $this->pdo->prepare(
                "INSERT INTO user_notifications (user_id, actor_id, type, reference_id, message, is_read, created_at) " .
                "VALUES (:user_id, :actor_id, :type, :reference_id, :message, 0, {$timeSql})"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':actor_id' => $actorId,
                ':type' => $type,
                ':reference_id' => $referenceId,
                ':message' => mb_substr($message, 0, 255),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get unread notifications count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id = :uid AND is_read = 0');
            $stmt->execute([':uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Get recent notifications for a user with actor info.
     *
     * @return list<array<string, mixed>>
     */
    public function getUserNotifications(int $userId, int $limit = 20, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT n.id, n.user_id, n.actor_id, n.type, n.reference_id, n.message, n.is_read, n.created_at, ' .
                'u.display_name AS actor_name, u.username AS actor_username, u.avatar_path AS actor_avatar ' .
                'FROM user_notifications n ' .
                'LEFT JOIN users u ON u.id = n.actor_id ' .
                'WHERE n.user_id = :uid ' .
                'ORDER BY n.created_at DESC, n.id DESC ' .
                'LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch any custom context URLs from content_mentions for mention-type notifications
            $mentionUrls = [];
            $mentionRefIds = [];
            foreach ($rows as $r) {
                if (($r['type'] ?? '') === 'mention' && !empty($r['reference_id'])) {
                    $mentionRefIds[] = (int) $r['reference_id'];
                }
            }
            if ($mentionRefIds !== []) {
                try {
                    $placeholders = implode(',', array_fill(0, count($mentionRefIds), '?'));
                    $cmStmt = $this->pdo->prepare(
                        "SELECT source_id, context_url FROM content_mentions WHERE mentioned_user_id = ? AND source_id IN ({$placeholders})"
                    );
                    $cmStmt->execute(array_merge([$userId], $mentionRefIds));
                    foreach ($cmStmt->fetchAll(PDO::FETCH_ASSOC) as $cmRow) {
                        if (!empty($cmRow['context_url'])) {
                            $mentionUrls[(int) $cmRow['source_id']] = (string) $cmRow['context_url'];
                        }
                    }
                } catch (Throwable) {
                    // Ignore fallback if content_mentions does not exist
                }
            }

            return array_map(function (array $r) use ($mentionUrls): array {
                $type = (string) $r['type'];
                $refId = $r['reference_id'] !== null ? (int) $r['reference_id'] : null;
                $actorId = $r['actor_id'] !== null ? (int) $r['actor_id'] : null;
                $actorUsername = $r['actor_username'] ?? null;

                $targetUrl = ($type === 'mention' && $refId !== null && isset($mentionUrls[$refId]))
                    ? $mentionUrls[$refId]
                    : self::resolveTargetUrl($type, $refId, $actorId, $actorUsername);

                return [
                    'id' => (int) $r['id'],
                    'user_id' => (int) $r['user_id'],
                    'actor_id' => $actorId,
                    'actor_name' => $r['actor_name'] ?? 'Someone',
                    'actor_username' => $actorUsername,
                    'actor_avatar' => $r['actor_avatar'] ?? null,
                    'type' => $type,
                    'reference_id' => $refId,
                    'message' => (string) $r['message'],
                    'target_url' => $targetUrl,
                    'is_read' => (bool) $r['is_read'],
                    'created_at' => (string) $r['created_at'],
                ];
            }, $rows);
        } catch (Throwable) {
            return [];
        }
    }

    public static function resolveTargetUrl(string $type, ?int $refId, ?int $actorId, ?string $actorUsername): string
    {
        return match ($type) {
            'follow' => $actorUsername !== null && $actorUsername !== '' ? '/authors/' . rawurlencode($actorUsername) : ($actorId !== null ? '/authors/' . $actorId : '/'),
            'score', 'comment', 'mention' => $refId !== null ? '/article/' . $refId : '/',
            'message' => $actorId !== null ? '/messages?user=' . $actorId : '/messages',
            default => '/',
        };
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
            $stmt->execute([':uid' => $userId]);
            return $stmt->rowCount();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Delete a single notification.
     */
    public function deleteNotification(int $notificationId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM user_notifications WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
