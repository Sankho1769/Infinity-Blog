<?php
declare(strict_types=1);

namespace InfinityBlog\Community;

use PDO;

final class CommunityRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Add comment to post.
     */
    public function addComment(int $postId, int $userId, string $content, ?int $parentId = null): int
    {
        $content = trim($content);
        if ($content === '') {
            throw new \InvalidArgumentException('Comment content cannot be empty.');
        }

        // Validate parent_id belongs to same post if supplied
        if ($parentId !== null) {
            $check = $this->pdo->prepare('SELECT id FROM article_comments WHERE id = :pid AND post_id = :post_id LIMIT 1');
            $check->execute([':pid' => $parentId, ':post_id' => $postId]);
            if ($check->fetchColumn() === false) {
                $parentId = null;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO article_comments (post_id, user_id, parent_id, content, status, created_at, updated_at) ' .
            'VALUES (:pid, :uid, :parent, :content, \'published\', NOW(), NOW())'
        );
        $stmt->execute([
            ':pid' => $postId,
            ':uid' => $userId,
            ':parent' => $parentId,
            ':content' => $content,
        ]);
        $commentId = (int) $this->pdo->lastInsertId();

        try {
            $pStmt = $this->pdo->prepare('SELECT author_id, title FROM posts WHERE id = :id LIMIT 1');
            $pStmt->execute([':id' => $postId]);
            $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
            $authorId = (int) ($pRow['author_id'] ?? 0);
            $postTitle = (string) ($pRow['title'] ?? 'your article');
            if ($authorId > 0 && $authorId !== $userId) {
                $uStmt = $this->pdo->prepare('SELECT display_name, username FROM users WHERE id = :id LIMIT 1');
                $uStmt->execute([':id' => $userId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                $commenterName = $uRow['display_name'] ?? $uRow['username'] ?? 'Someone';
                $titlePreview = mb_substr($postTitle, 0, 40);

                $notifs = new \InfinityBlog\Social\NotificationRepository($this->pdo);
                $notifs->createNotification(
                    $authorId,
                    $userId,
                    'comment',
                    $postId,
                    "{$commenterName} commented on \"{$titlePreview}\"."
                );
            }

            // Process @mentions in comment
            $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);
            $mentionService->processMentionsAndNotify(
                'comment',
                $commentId,
                $userId,
                $content,
                $postTitle,
                "/article/{$postId}#comment-{$commentId}"
            );
        } catch (\Throwable) {
            // Ignore notification failure
        }

        return $commentId;
    }

    /**
     * Get threaded comments for a post.
     * @return array<int, array<string, mixed>>
     */
    public function getPostComments(int $postId, ?int $viewerId = null): array
    {
        $sql = 'SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content, c.status, c.created_at, c.updated_at, ' .
            'u.display_name AS user_name, u.username AS user_username, u.avatar_path AS user_avatar, u.role AS user_role ' .
            'FROM article_comments c ' .
            'JOIN users u ON c.user_id = u.id ' .
            'WHERE c.post_id = :pid AND c.status != \'deleted\' ';

        if ($viewerId !== null) {
            // Exclude comments from blocked users
            $sql .= 'AND c.user_id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = ' . (int) $viewerId . ') ';
        }

        $sql .= 'ORDER BY c.created_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $postId]);
        $allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($allComments)) {
            $userIds = array_map(static fn(array $c): int => (int) $c['user_id'], $allComments);
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->pdo);
            $badgeMap = $badgeService->getBadgeStatusesForUsers($userIds);
            $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);

            foreach ($allComments as &$comment) {
                $uid = (int) $comment['user_id'];
                $comment['has_author_badge'] = !empty($badgeMap[$uid]);
                $comment['rendered_content'] = $mentionService->renderMentionsToHtml((string) $comment['content']);
            }
            unset($comment);
        }

        // Build hierarchical tree
        $commentMap = [];
        $rootComments = [];

        foreach ($allComments as &$comment) {
            $comment['replies'] = [];
            $commentMap[$comment['id']] = &$comment;
        }
        unset($comment);

        foreach ($allComments as &$comment) {
            if ($comment['parent_id'] !== null && isset($commentMap[$comment['parent_id']])) {
                $commentMap[$comment['parent_id']]['replies'][] = &$comment;
            } else {
                $rootComments[] = &$comment;
            }
        }

        return $rootComments;
    }

    public function editComment(int $commentId, int $userId, string $content, bool $isAdmin = false): bool
    {
        $content = trim($content);
        if ($content === '') {
            throw new \InvalidArgumentException('Comment content cannot be empty.');
        }

        $sql = 'UPDATE article_comments SET content = :content, updated_at = NOW() WHERE id = :id ';
        $params = [':content' => $content, ':id' => $commentId];
        if (!$isAdmin) {
            $sql .= 'AND user_id = :uid';
            $params[':uid'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $updated = $stmt->rowCount() > 0;

        if ($updated) {
            try {
                $cStmt = $this->pdo->prepare('SELECT c.post_id, c.user_id, p.title FROM article_comments c JOIN posts p ON p.id = c.post_id WHERE c.id = :id LIMIT 1');
                $cStmt->execute([':id' => $commentId]);
                $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($cRow) {
                    $postId = (int) $cRow['post_id'];
                    $effectiveUserId = (int) $cRow['user_id'];
                    $postTitle = (string) ($cRow['title'] ?? 'your article');
                    $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);
                    $mentionService->processMentionsAndNotify(
                        'comment',
                        $commentId,
                        $effectiveUserId,
                        $content,
                        $postTitle,
                        "/article/{$postId}#comment-{$commentId}"
                    );
                }
            } catch (\Throwable) {
            }
        }

        return $updated;
    }

    public function deleteComment(int $commentId, int $userId, bool $isAdmin = false): bool
    {
        $sql = 'UPDATE article_comments SET status = \'deleted\', content = \'[This comment was deleted by user]\', updated_at = NOW() WHERE id = :id ';
        $params = [':id' => $commentId];
        if (!$isAdmin) {
            $sql .= 'AND user_id = :uid';
            $params[':uid'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function reportComment(int $commentId, int $reporterId, string $reason): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO comment_reports (comment_id, reporter_id, reason, status, created_at) ' .
            'VALUES (:cid, :rid, :reason, \'pending\', NOW())'
        );
        $stmt->execute([
            ':cid' => $commentId,
            ':rid' => $reporterId,
            ':reason' => trim($reason),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Chat Rooms.
     */
    public function getChatRooms(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT r.id, r.slug, r.name, r.description, r.category_id, r.is_active, r.created_at, ' .
                'c.name AS category_name, ' .
                '(SELECT COUNT(*) FROM chat_room_messages m WHERE m.room_id = r.id) AS message_count, ' .
                '(SELECT COUNT(*) FROM chat_room_members cm WHERE cm.room_id = r.id) AS member_count ' .
                'FROM chat_rooms r ' .
                'LEFT JOIN categories c ON r.category_id = c.id ' .
                'WHERE r.is_active = 1 ' .
                'ORDER BY r.created_at ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $stmt = $this->pdo->query(
                'SELECT r.id, r.slug, r.name, r.description, r.category_id, r.is_active, r.created_at, ' .
                'c.name AS category_name, ' .
                '(SELECT COUNT(*) FROM chat_room_messages m WHERE m.room_id = r.id) AS message_count, ' .
                '0 AS member_count ' .
                'FROM chat_rooms r ' .
                'LEFT JOIN categories c ON r.category_id = c.id ' .
                'WHERE r.is_active = 1 ' .
                'ORDER BY r.created_at ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getChatRoomBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chat_rooms WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($room) ? $room : null;
    }

    public function isMember(int $roomId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM chat_room_members WHERE room_id = :rid AND user_id = :uid LIMIT 1');
            $stmt->execute([':rid' => $roomId, ':uid' => $userId]);
            return $stmt->fetchColumn() !== false;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return false;
            }
            throw $e;
        }
    }

    public function joinRoom(int $roomId, int $userId): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timeExpr = $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
        $insertVerb = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        $stmt = $this->pdo->prepare(
            "{$insertVerb} INTO chat_room_members (room_id, user_id, joined_at) VALUES (:rid, :uid, {$timeExpr})"
        );
        $stmt->execute([':rid' => $roomId, ':uid' => $userId]);
        return true;
    }

    public function getRoomMemberCount(int $roomId): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chat_room_members WHERE room_id = :rid');
            $stmt->execute([':rid' => $roomId]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table') || str_contains($e->getMessage(), "doesn't exist")) {
                return 0;
            }
            throw $e;
        }
    }

    public function createChatRoom(string $name, string $slug, ?string $description, ?int $categoryId, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO chat_rooms (slug, name, description, category_id, created_by, is_active, created_at) ' .
            'VALUES (:slug, :name, :desc, :cat, :uid, 1, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':name' => $name,
            ':desc' => $description,
            ':cat' => $categoryId,
            ':uid' => $userId,
        ]);
        $roomId = (int) $this->pdo->lastInsertId();
        try {
            $this->joinRoom($roomId, $userId);
        } catch (\Throwable) {
        }
        return $roomId;
    }

    public function getChatMessages(int $roomId, int $limit = 50, ?int $afterId = null, ?int $viewerId = null): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT m.id, m.room_id, m.user_id, m.message, m.created_at, ' .
            'u.display_name AS user_name, u.username AS user_username, u.avatar_path AS user_avatar ' .
            'FROM chat_room_messages m ' .
            'JOIN users u ON m.user_id = u.id ' .
            'WHERE m.room_id = :rid ';

        $params = [':rid' => $roomId];
        if ($viewerId !== null && $viewerId > 0) {
            $sql .= 'AND m.user_id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = :viewerId) ';
            $params[':viewerId'] = $viewerId;
        }

        if ($afterId !== null && $afterId > 0) {
            $sql .= 'AND m.id > :afterId ';
            $params[':afterId'] = $afterId;
        }

        $sql .= 'ORDER BY m.id ASC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($messages)) {
            $userIds = array_map(static fn(array $m): int => (int) $m['user_id'], $messages);
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->pdo);
            $badgeMap = $badgeService->getBadgeStatusesForUsers($userIds);
            $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);

            foreach ($messages as &$msg) {
                $uid = (int) $msg['user_id'];
                $msg['has_author_badge'] = !empty($badgeMap[$uid]);
                $msg['rendered_message'] = $mentionService->renderMentionsToHtml((string) $msg['message']);
            }
            unset($msg);
        }

        return $messages;
    }

    public function postChatMessage(int $roomId, int $userId, string $message): int
    {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }

        if (mb_strlen($message) > 2000) {
            throw new \InvalidArgumentException('Chat message exceeds maximum length of 2000 characters.');
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Rate limit: maximum 20 messages per minute per user in chat
        $rateSql = $driver === 'sqlite'
            ? 'SELECT COUNT(*) FROM chat_room_messages WHERE user_id = :uid AND created_at >= datetime(\'now\', \'-1 minute\')'
            : 'SELECT COUNT(*) FROM chat_room_messages WHERE user_id = :uid AND created_at >= NOW() - INTERVAL 1 MINUTE';
        $rateStmt = $this->pdo->prepare($rateSql);
        $rateStmt->execute([':uid' => $userId]);
        if ((int) $rateStmt->fetchColumn() >= 20) {
            throw new \InvalidArgumentException('Rate limit exceeded: please wait a moment before sending more chat messages.');
        }

        $timeExpr = $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
        $stmt = $this->pdo->prepare(
            "INSERT INTO chat_room_messages (room_id, user_id, message, created_at) VALUES (:rid, :uid, :msg, {$timeExpr})"
        );
        $stmt->execute([
            ':rid' => $roomId,
            ':uid' => $userId,
            ':msg' => $message,
        ]);
        $chatId = (int) $this->pdo->lastInsertId();

        try {
            $rStmt = $this->pdo->prepare('SELECT name, slug FROM chat_rooms WHERE id = :id LIMIT 1');
            $rStmt->execute([':id' => $roomId]);
            $rRow = $rStmt->fetch(PDO::FETCH_ASSOC);
            $roomName = (string) ($rRow['name'] ?? 'Chat');
            $roomSlug = (string) ($rRow['slug'] ?? '');
            $contextUrl = '/chat' . ($roomSlug !== '' ? '/' . rawurlencode($roomSlug) : '');

            $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);
            $mentionService->processMentionsAndNotify(
                'chat',
                $chatId,
                $userId,
                $message,
                $roomName,
                $contextUrl
            );
        } catch (\Throwable) {
        }

        return $chatId;
    }

    /**
     * Safety & Notifications.
     */
    public function blockUser(int $userId, int $blockedUserId): bool
    {
        if ($userId === $blockedUserId) {
            return false;
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO user_blocks (user_id, blocked_user_id, created_at) VALUES (:uid, :bid, CURRENT_TIMESTAMP)'
            : 'INSERT IGNORE INTO user_blocks (user_id, blocked_user_id, created_at) VALUES (:uid, :bid, NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':bid' => $blockedUserId]);
        return true;
    }

    public function unblockUser(int $userId, int $blockedUserId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_blocks WHERE user_id = :uid AND blocked_user_id = :bid');
        $stmt->execute([':uid' => $userId, ':bid' => $blockedUserId]);
        return $stmt->rowCount() > 0;
    }

    public function reportUser(int $reporterId, int $reportedUserId, string $reason): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_reports (reporter_id, reported_user_id, reason, status, created_at) ' .
            'VALUES (:rid, :ruid, :reason, \'pending\', NOW())'
        );
        $stmt->execute([
            ':rid' => $reporterId,
            ':ruid' => $reportedUserId,
            ':reason' => trim($reason),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createNotification(int $userId, ?int $actorId, string $type, ?int $refId, string $message): int
    {
        if ($userId === $actorId) {
            return 0; // Don't notify yourself
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, actor_id, type, reference_id, message, is_read, created_at) ' .
            'VALUES (:uid, :aid, :type, :ref, :msg, 0, NOW())'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':aid' => $actorId,
            ':type' => $type,
            ':ref' => $refId,
            ':msg' => $message,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getUserNotifications(int $userId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.actor_id, n.type, n.reference_id, n.message, n.is_read, n.created_at, ' .
            'u.display_name AS actor_name, u.avatar_path AS actor_avatar ' .
            'FROM user_notifications n ' .
            'LEFT JOIN users u ON n.actor_id = u.id ' .
            'WHERE n.user_id = :uid ' .
            'ORDER BY n.created_at DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markNotificationsRead(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE user_notifications SET is_read = 1 WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
    }
}
