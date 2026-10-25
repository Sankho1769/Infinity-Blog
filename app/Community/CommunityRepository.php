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
    public static function formatRelativeTime(string $datetime): string
    {
        $time = strtotime($datetime);
        if ($time === false) {
            return 'just now';
        }
        $diff = time() - $time;
        if ($diff < 45) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = max(1, (int) floor($diff / 60));
            return $m . 'm ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . 'h ago';
        }
        if ($diff < 2592000) {
            $d = (int) floor($diff / 86400);
            return $d . 'd ago';
        }
        if ($diff < 31536000) {
            $mo = (int) floor($diff / 2592000);
            return $mo . 'mo ago';
        }
        $y = (int) floor($diff / 31536000);
        return $y . 'y ago';
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

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_comments (post_id, user_id, parent_id, content, status, created_at, updated_at) ' .
            'VALUES (:pid, :uid, :parent, :content, \'published\', :created_at, :updated_at)'
        );
        $stmt->execute([
            ':pid' => $postId,
            ':uid' => $userId,
            ':parent' => $parentId,
            ':content' => $content,
            ':created_at' => $now,
            ':updated_at' => $now,
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
     * Get count of active comments for a post.
     */
    public function getCommentCount(int $postId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM article_comments WHERE post_id = :pid AND status != \'deleted\'');
        $stmt->execute([':pid' => $postId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get a single comment by ID with author metadata.
     */
    public function getCommentById(int $commentId, ?int $viewerId = null): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content, c.status, c.created_at, c.updated_at, ' .
            'u.display_name AS user_name, u.username AS user_username, u.email AS user_email, u.avatar_path AS user_avatar, u.role AS user_role ' .
            'FROM article_comments c ' .
            'JOIN users u ON c.user_id = u.id ' .
            'WHERE c.id = :id AND c.status != \'deleted\' LIMIT 1'
        );
        $stmt->execute([':id' => $commentId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            return null;
        }

        $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);
        $postAuthorId = 0;
        try {
            $pStmt = $this->pdo->prepare('SELECT author_id FROM posts WHERE id = :pid LIMIT 1');
            $pStmt->execute([':pid' => (int) $c['post_id']]);
            $postAuthorId = (int) $pStmt->fetchColumn();
        } catch (\Throwable) {
        }

        $badgeMap = [];
        try {
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->pdo);
            $badgeMap = $badgeService->getBadgeStatusesForUsers([(int) $c['user_id']]);
        } catch (\Throwable) {
        }

        $likes = 0;
        $dislikes = 0;
        $viewerReaction = null;
        try {
            $rStmt = $this->pdo->prepare(
                'SELECT ' .
                'SUM(CASE WHEN reaction = \'like\' THEN 1 ELSE 0 END) AS likes, ' .
                'SUM(CASE WHEN reaction = \'dislike\' THEN 1 ELSE 0 END) AS dislikes ' .
                'FROM article_comment_reactions WHERE comment_id = :cid'
            );
            $rStmt->execute([':cid' => $commentId]);
            $counts = $rStmt->fetch(PDO::FETCH_ASSOC);
            $likes = (int) ($counts['likes'] ?? 0);
            $dislikes = (int) ($counts['dislikes'] ?? 0);

            if ($viewerId !== null) {
                $vStmt = $this->pdo->prepare('SELECT reaction FROM article_comment_reactions WHERE comment_id = :cid AND user_id = :uid LIMIT 1');
                $vStmt->execute([':cid' => $commentId, ':uid' => $viewerId]);
                $viewerReaction = $vStmt->fetchColumn() ?: null;
            }
        } catch (\Throwable) {
        }

        $c['handle'] = \InfinityBlog\Support\UserIdentityHelper::formatHandle($c['user_username'] ?? '', $c['user_name'] ?? '');
        $c['avatar_url'] = \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($c['user_avatar'] ?? null, $c['user_name'] ?? '');
        $c['formatted_time'] = self::formatRelativeTime((string) $c['created_at']);
        $c['rendered_content'] = $mentionService->renderMentionsToHtml((string) $c['content']);
        $c['like_count'] = $likes;
        $c['dislike_count'] = $dislikes;
        $c['viewer_reaction'] = $viewerReaction;
        $c['is_pinned'] = !empty($c['is_pinned']);
        $c['is_author'] = ($postAuthorId > 0 && (int) $c['user_id'] === $postAuthorId);
        $c['has_author_badge'] = !empty($badgeMap[(int) $c['user_id']]);
        $c['reply_count'] = 0;
        $c['replies'] = [];
        $c['is_own'] = ($viewerId !== null && (int) $c['user_id'] === (int) $viewerId);

        return $c;
    }

    /**
     * Get threaded comments for a post.
     * @return array<int, array<string, mixed>>
     */
    public function getPostComments(int $postId, ?int $viewerId = null): array
    {
        // Check if is_pinned column exists
        $hasPinnedColumn = false;
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $cols = $this->pdo->query('PRAGMA table_info(article_comments)')->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                $hasPinnedColumn = in_array('is_pinned', $colNames, true);
            } else {
                $hasPinnedColumn = (bool) $this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'article_comments' AND column_name = 'is_pinned'"
                )->fetchColumn();
            }
        } catch (\Throwable) {
            $hasPinnedColumn = false;
        }

        // Check if article_comment_reactions table exists
        $hasReactionsTable = false;
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $hasReactionsTable = (bool) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='article_comment_reactions'")->fetchColumn();
            } else {
                $hasReactionsTable = (bool) $this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'article_comment_reactions'"
                )->fetchColumn();
            }
        } catch (\Throwable) {
            $hasReactionsTable = false;
        }

        // Post author ID
        $postAuthorId = 0;
        try {
            $pStmt = $this->pdo->prepare('SELECT author_id FROM posts WHERE id = :pid LIMIT 1');
            $pStmt->execute([':pid' => $postId]);
            $postAuthorId = (int) $pStmt->fetchColumn();
        } catch (\Throwable) {
        }

        $pinnedSelect = $hasPinnedColumn ? 'c.is_pinned' : '0 AS is_pinned';

        $reactionsSelect = '0 AS like_count, 0 AS dislike_count, NULL AS viewer_reaction';
        if ($hasReactionsTable) {
            $viewerSql = $viewerId !== null
                ? '(SELECT r.reaction FROM article_comment_reactions r WHERE r.comment_id = c.id AND r.user_id = ' . (int) $viewerId . ' LIMIT 1) AS viewer_reaction'
                : 'NULL AS viewer_reaction';
            $reactionsSelect = '(SELECT COUNT(*) FROM article_comment_reactions r WHERE r.comment_id = c.id AND r.reaction = \'like\') AS like_count, ' .
                '(SELECT COUNT(*) FROM article_comment_reactions r WHERE r.comment_id = c.id AND r.reaction = \'dislike\') AS dislike_count, ' .
                $viewerSql;
        }

        $sql = 'SELECT c.id, c.post_id, c.user_id, c.parent_id, c.content, c.status, c.created_at, c.updated_at, ' .
            $pinnedSelect . ', ' .
            'u.display_name AS user_name, u.username AS user_username, u.email AS user_email, u.avatar_path AS user_avatar, u.role AS user_role, ' .
            $reactionsSelect . ' ' .
            'FROM article_comments c ' .
            'JOIN users u ON c.user_id = u.id ' .
            'WHERE c.post_id = :pid AND c.status != \'deleted\' ';

        if ($viewerId !== null) {
            try {
                // Exclude comments from blocked users
                $sql .= 'AND c.user_id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = ' . (int) $viewerId . ') ';
            } catch (\Throwable) {
            }
        }

        $sql .= 'ORDER BY ' . ($hasPinnedColumn ? 'c.is_pinned DESC, ' : '') . 'c.created_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $postId]);
        $allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($allComments)) {
            $userIds = array_map(static fn(array $c): int => (int) $c['user_id'], $allComments);
            $badgeMap = [];
            try {
                $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->pdo);
                $badgeMap = $badgeService->getBadgeStatusesForUsers($userIds);
            } catch (\Throwable) {
            }
            $mentionService = new \InfinityBlog\Social\MentionService($this->pdo);

            foreach ($allComments as &$comment) {
                $uid = (int) $comment['user_id'];
                $comment['has_author_badge'] = !empty($badgeMap[$uid]);
                $comment['is_author'] = ($postAuthorId > 0 && $uid === $postAuthorId);
                $comment['is_pinned'] = !empty($comment['is_pinned']);
                $comment['like_count'] = (int) ($comment['like_count'] ?? 0);
                $comment['dislike_count'] = (int) ($comment['dislike_count'] ?? 0);
                $comment['is_own'] = ($viewerId !== null && $uid === (int) $viewerId);
                $comment['handle'] = \InfinityBlog\Support\UserIdentityHelper::formatHandle($comment['user_username'] ?? '', $comment['user_name'] ?? '');
                $comment['avatar_url'] = \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($comment['user_avatar'] ?? null, $comment['user_name'] ?? '');
                $comment['formatted_time'] = self::formatRelativeTime((string) $comment['created_at']);
                $comment['rendered_content'] = $mentionService->renderMentionsToHtml((string) $comment['content']);
                $comment['reply_count'] = 0;
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
                $commentMap[$comment['parent_id']]['reply_count'] = count($commentMap[$comment['parent_id']]['replies']);
            } else {
                $rootComments[] = &$comment;
            }
        }

        return $rootComments;
    }

    /**
     * React to a comment with 'like' or 'dislike' (toggles if matching).
     * @return array{likes: int, dislikes: int, viewer_reaction: ?string}
     */
    public function reactToComment(int $commentId, int $userId, string $reaction): array
    {
        if (!in_array($reaction, ['like', 'dislike'], true)) {
            throw new \InvalidArgumentException('Reaction must be "like" or "dislike".');
        }

        $check = $this->pdo->prepare('SELECT id, post_id, user_id FROM article_comments WHERE id = :id AND status != \'deleted\' LIMIT 1');
        $check->execute([':id' => $commentId]);
        $comment = $check->fetch(PDO::FETCH_ASSOC);
        if (!$comment) {
            throw new \RuntimeException('Comment not found.');
        }

        $stmt = $this->pdo->prepare('SELECT id, reaction FROM article_comment_reactions WHERE comment_id = :cid AND user_id = :uid LIMIT 1');
        $stmt->execute([':cid' => $commentId, ':uid' => $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = date('Y-m-d H:i:s');
        $viewerReaction = null;

        if ($existing) {
            if ($existing['reaction'] === $reaction) {
                // Toggle off
                $del = $this->pdo->prepare('DELETE FROM article_comment_reactions WHERE id = :id');
                $del->execute([':id' => $existing['id']]);
                $viewerReaction = null;
            } else {
                // Switch reaction
                $upd = $this->pdo->prepare('UPDATE article_comment_reactions SET reaction = :r, updated_at = :upd WHERE id = :id');
                $upd->execute([':r' => $reaction, ':upd' => $now, ':id' => $existing['id']]);
                $viewerReaction = $reaction;
            }
        } else {
            // Insert
            $ins = $this->pdo->prepare(
                'INSERT INTO article_comment_reactions (comment_id, user_id, reaction, created_at, updated_at) ' .
                'VALUES (:cid, :uid, :r, :created, :updated)'
            );
            $ins->execute([
                ':cid' => $commentId,
                ':uid' => $userId,
                ':r' => $reaction,
                ':created' => $now,
                ':updated' => $now,
            ]);
            $viewerReaction = $reaction;

            // Notification on like
            if ($reaction === 'like' && (int) $comment['user_id'] !== $userId) {
                try {
                    $uStmt = $this->pdo->prepare('SELECT display_name, username FROM users WHERE id = :id LIMIT 1');
                    $uStmt->execute([':id' => $userId]);
                    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                    $likerName = $uRow['display_name'] ?? $uRow['username'] ?? 'Someone';

                    $notifs = new \InfinityBlog\Social\NotificationRepository($this->pdo);
                    $notifs->createNotification(
                        (int) $comment['user_id'],
                        $userId,
                        'like',
                        (int) $comment['post_id'],
                        "{$likerName} liked your comment."
                    );
                } catch (\Throwable) {
                }
            }
        }

        $cStmt = $this->pdo->prepare(
            'SELECT ' .
            'SUM(CASE WHEN reaction = \'like\' THEN 1 ELSE 0 END) AS likes, ' .
            'SUM(CASE WHEN reaction = \'dislike\' THEN 1 ELSE 0 END) AS dislikes ' .
            'FROM article_comment_reactions WHERE comment_id = :cid'
        );
        $cStmt->execute([':cid' => $commentId]);
        $counts = $cStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'likes' => (int) ($counts['likes'] ?? 0),
            'dislikes' => (int) ($counts['dislikes'] ?? 0),
            'viewer_reaction' => $viewerReaction,
        ];
    }

    /**
     * Pin or unpin a comment (allowed for post author or admin).
     */
    public function pinComment(int $commentId, int $userId, bool $isAdmin = false): bool
    {
        $cStmt = $this->pdo->prepare(
            'SELECT c.id, c.post_id, c.is_pinned, p.author_id ' .
            'FROM article_comments c ' .
            'JOIN posts p ON p.id = c.post_id ' .
            'WHERE c.id = :id AND c.status != \'deleted\' LIMIT 1'
        );
        $cStmt->execute([':id' => $commentId]);
        $row = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $isPostAuthor = ((int) $row['author_id']) === $userId;
        if (!$isPostAuthor && !$isAdmin) {
            return false;
        }

        $newPinned = empty($row['is_pinned']) ? 1 : 0;
        if ($newPinned === 1) {
            $uStmt = $this->pdo->prepare('UPDATE article_comments SET is_pinned = 0 WHERE post_id = :pid');
            $uStmt->execute([':pid' => (int) $row['post_id']]);
        }

        $stmt = $this->pdo->prepare('UPDATE article_comments SET is_pinned = :pinned WHERE id = :id');
        $stmt->execute([':pinned' => $newPinned, ':id' => $commentId]);
        return true;
    }

    public function editComment(int $commentId, int $userId, string $content, bool $isAdmin = false): bool
    {
        $content = trim($content);
        if ($content === '') {
            throw new \InvalidArgumentException('Comment content cannot be empty.');
        }

        $now = date('Y-m-d H:i:s');
        $sql = 'UPDATE article_comments SET content = :content, updated_at = :now WHERE id = :id ';
        $params = [':content' => $content, ':now' => $now, ':id' => $commentId];
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
        $now = date('Y-m-d H:i:s');
        $sql = 'UPDATE article_comments SET status = \'deleted\', content = \'[This comment was deleted by user]\', updated_at = :now WHERE id = :id ';
        $params = [':now' => $now, ':id' => $commentId];
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
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO comment_reports (comment_id, reporter_id, reason, status, created_at) ' .
            'VALUES (:cid, :rid, :reason, \'pending\', :now)'
        );
        $stmt->execute([
            ':cid' => $commentId,
            ':rid' => $reporterId,
            ':reason' => trim($reason),
            ':now' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ensure each active category in the database has an associated unique chat room.
     */
    public function ensureCategoryChatRooms(): void
    {
        try {
            $stmt = $this->pdo->query('SELECT c.id, c.name, c.slug, c.description, c.created_by FROM categories c WHERE c.is_active = 1 AND c.id NOT IN (SELECT category_id FROM chat_rooms WHERE category_id IS NOT NULL)');
            if ($stmt) {
                $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($missing as $cat) {
                    $cId = (int) $cat['id'];
                    $cName = (string) $cat['name'];
                    $cSlug = (string) $cat['slug'];
                    $cDesc = !empty($cat['description'])
                        ? (string) $cat['description']
                        : "Community discussions, resources, and live chat for {$cName}.";
                    $cCreator = !empty($cat['created_by']) ? (int) $cat['created_by'] : 1;

                    // Ensure unique slug
                    $roomSlug = $cSlug;
                    $slugCheck = $this->pdo->prepare('SELECT id FROM chat_rooms WHERE slug = :slug LIMIT 1');
                    $slugCheck->execute([':slug' => $roomSlug]);
                    if ($slugCheck->fetchColumn()) {
                        $roomSlug = 'category-' . $cSlug;
                    }

                    $insertStmt = $this->pdo->prepare(
                        'INSERT INTO chat_rooms (slug, name, description, category_id, created_by, is_active, created_at) ' .
                        'VALUES (:slug, :name, :desc, :cid, :uid, 1, CURRENT_TIMESTAMP)'
                    );
                    $insertStmt->execute([
                        ':slug' => $roomSlug,
                        ':name' => $cName,
                        ':desc' => $cDesc,
                        ':cid' => $cId,
                        ':uid' => $cCreator,
                    ]);
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Chat Rooms.
     */
    public function getChatRooms(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT r.id, r.slug, r.name, r.description, r.category_id, r.is_active, r.created_at, ' .
                'c.name AS category_name, c.slug AS category_slug, c.icon_name, c.custom_logo_path, ' .
                '(SELECT COUNT(*) FROM chat_room_messages m WHERE m.room_id = r.id) AS message_count, ' .
                '(SELECT COUNT(*) FROM chat_room_members cm WHERE cm.room_id = r.id) AS member_count, ' .
                '(SELECT m.message FROM chat_room_messages m WHERE m.room_id = r.id ORDER BY m.id DESC LIMIT 1) AS latest_message, ' .
                '(SELECT m.created_at FROM chat_room_messages m WHERE m.room_id = r.id ORDER BY m.id DESC LIMIT 1) AS latest_message_at ' .
                'FROM chat_rooms r ' .
                'LEFT JOIN categories c ON r.category_id = c.id ' .
                'WHERE r.is_active = 1 ' .
                'ORDER BY CASE WHEN r.category_id IS NOT NULL THEN 0 ELSE 1 END ASC, r.created_at ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $stmt = $this->pdo->query(
                'SELECT r.id, r.slug, r.name, r.description, r.category_id, r.is_active, r.created_at, ' .
                'c.name AS category_name, c.slug AS category_slug, c.icon_name, c.custom_logo_path, ' .
                '(SELECT COUNT(*) FROM chat_room_messages m WHERE m.room_id = r.id) AS message_count, ' .
                '0 AS member_count, ' .
                'NULL AS latest_message, ' .
                'NULL AS latest_message_at ' .
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
        $stmt = $this->pdo->prepare('SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.icon_name, c.custom_logo_path FROM chat_rooms r LEFT JOIN categories c ON r.category_id = c.id WHERE r.slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($room) ? $room : null;
    }

    public function getChatRoomByCategoryId(int $categoryId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.icon_name, c.custom_logo_path FROM chat_rooms r LEFT JOIN categories c ON r.category_id = c.id WHERE r.category_id = :cid LIMIT 1');
        $stmt->execute([':cid' => $categoryId]);
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
                $msg['avatar_url'] = \InfinityBlog\Support\UserIdentityHelper::resolveAvatarUrl($msg['user_avatar'] ?? null);
                $msg['user_avatar'] = $msg['avatar_url'];
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

        // Double-click duplicate protection: check recent duplicate from same sender in same room within 2 seconds
        $dupSql = $driver === 'sqlite'
            ? "SELECT id FROM chat_room_messages WHERE room_id = :rid AND user_id = :uid AND message = :msg AND created_at >= datetime('now', '-2 seconds') ORDER BY id DESC LIMIT 1"
            : "SELECT id FROM chat_room_messages WHERE room_id = :rid AND user_id = :uid AND message = :msg AND created_at >= NOW() - INTERVAL 2 SECOND ORDER BY id DESC LIMIT 1";
        $dupStmt = $this->pdo->prepare($dupSql);
        $dupStmt->execute([':rid' => $roomId, ':uid' => $userId, ':msg' => $message]);
        $existingId = $dupStmt->fetchColumn();
        if ($existingId) {
            return (int) $existingId;
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
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_reports (reporter_id, reported_user_id, reason, status, created_at) ' .
            'VALUES (:rid, :ruid, :reason, \'pending\', :created_at)'
        );
        $stmt->execute([
            ':rid' => $reporterId,
            ':ruid' => $reportedUserId,
            ':reason' => trim($reason),
            ':created_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createNotification(int $userId, ?int $actorId, string $type, ?int $refId, string $message): int
    {
        if ($userId === $actorId) {
            return 0; // Don't notify yourself
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, actor_id, type, reference_id, message, is_read, created_at) ' .
            'VALUES (:uid, :aid, :type, :ref, :msg, 0, :created_at)'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':aid' => $actorId,
            ':type' => $type,
            ':ref' => $refId,
            ':msg' => $message,
            ':created_at' => $now,
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
