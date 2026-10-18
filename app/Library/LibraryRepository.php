<?php
declare(strict_types=1);

namespace InfinityBlog\Library;

use PDO;
use Throwable;

final class LibraryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Bookmark or unbookmark an article (Toggle).
     * @return bool True if bookmarked, False if unbookmarked
     */
    public function toggleBookmark(int $userId, int $postId): bool
    {
        if ($this->isBookmarked($userId, $postId)) {
            $stmt = $this->pdo->prepare('DELETE FROM user_bookmarks WHERE user_id = :uid AND post_id = :pid');
            $stmt->execute([':uid' => $userId, ':pid' => $postId]);
            return false;
        }

        $stmt = $this->pdo->prepare('INSERT INTO user_bookmarks (user_id, post_id, created_at) VALUES (:uid, :pid, NOW())');
        $stmt->execute([':uid' => $userId, ':pid' => $postId]);
        return true;
    }

    public function isBookmarked(int $userId, int $postId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_bookmarks WHERE user_id = :uid AND post_id = :pid LIMIT 1');
        $stmt->execute([':uid' => $userId, ':pid' => $postId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get bookmarked posts for a user with optional keyword search.
     * @return array<int, array<string, mixed>>
     */
    public function getBookmarks(int $userId, ?string $search = null): array
    {
        $sql = 'SELECT p.id, p.title, p.content, p.summary, p.status, p.published_at, p.cover_image_path, ' .
            'c.id AS category_id, c.name AS category_name, c.slug AS category_slug, ' .
            'u.id AS author_id, u.display_name AS author_name, u.username AS author_username, u.avatar_path AS author_avatar, ' .
            'b.created_at AS bookmarked_at ' .
            'FROM user_bookmarks b ' .
            'JOIN posts p ON b.post_id = p.id ' .
            'JOIN categories c ON p.category_id = c.id ' .
            'JOIN users u ON p.author_id = u.id ' .
            'WHERE b.user_id = :uid AND p.status = \'published\' ';

        $params = [':uid' => $userId];
        if ($search !== null && trim($search) !== '') {
            $sql .= 'AND (p.title LIKE :s OR p.summary LIKE :s) ';
            $params[':s'] = '%' . trim($search) . '%';
        }

        $sql .= 'ORDER BY b.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save reading progress for a user.
     */
    public function saveReadingProgress(int $userId, int $postId, int $progressPercent, int $scrollPosition, bool $completed = false): void
    {
        $progressPercent = max(0, min(100, $progressPercent));
        if ($completed || $progressPercent >= 95) {
            $completed = true;
            $progressPercent = 100;
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_reading_history (user_id, post_id, progress_percent, scroll_position, completed, created_at, updated_at) ' .
                'VALUES (:uid, :pid, :pct, :pos, :comp, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ' .
                'ON CONFLICT(user_id, post_id) DO UPDATE SET ' .
                'progress_percent = :pct, scroll_position = :pos, completed = :comp, updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_reading_history (user_id, post_id, progress_percent, scroll_position, completed, created_at, updated_at) ' .
                'VALUES (:uid, :pid, :pct, :pos, :comp, NOW(), NOW()) ' .
                'ON DUPLICATE KEY UPDATE ' .
                'progress_percent = VALUES(progress_percent), ' .
                'scroll_position = VALUES(scroll_position), ' .
                'completed = VALUES(completed), ' .
                'updated_at = NOW()'
            );
        }

        $stmt->execute([
            ':uid' => $userId,
            ':pid' => $postId,
            ':pct' => $progressPercent,
            ':pos' => $scrollPosition,
            ':comp' => $completed ? 1 : 0,
        ]);
    }

    /**
     * Get reading progress for a specific article.
     * @return array<string, mixed>|null
     */
    public function getReadingProgress(int $userId, int $postId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT progress_percent, scroll_position, completed, updated_at ' .
            'FROM user_reading_history WHERE user_id = :uid AND post_id = :pid LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':pid' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Get reading history for a user (Continue Reading).
     * @return array<int, array<string, mixed>>
     */
    public function getReadingHistory(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT p.id, p.title, p.summary, p.status, p.published_at, p.cover_image_path, ' .
            'c.id AS category_id, c.name AS category_name, c.slug AS category_slug, ' .
            'u.id AS author_id, u.display_name AS author_name, u.username AS author_username, ' .
            'h.progress_percent, h.scroll_position, h.completed, h.updated_at AS last_read_at ' .
            'FROM user_reading_history h ' .
            'JOIN posts p ON h.post_id = p.id ' .
            'JOIN categories c ON p.category_id = c.id ' .
            'JOIN users u ON p.author_id = u.id ' .
            'WHERE h.user_id = :uid AND p.status = \'published\' ' .
            'ORDER BY h.updated_at DESC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Collections: Get user collections.
     * @return array<int, array<string, mixed>>
     */
    public function getCollections(int $userId, ?int $viewerId = null): array
    {
        $sql = 'SELECT c.id, c.user_id, c.title, c.description, c.is_private, c.created_at, ' .
            '(SELECT COUNT(*) FROM collection_items ci JOIN posts p ON ci.post_id = p.id WHERE ci.collection_id = c.id AND p.status = \'published\') AS item_count ' .
            'FROM user_collections c WHERE c.user_id = :uid ';

        if ($viewerId !== $userId) {
            $sql .= 'AND c.is_private = 0 ';
        }

        $sql .= 'ORDER BY c.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCollection(int $userId, string $title, ?string $description = null, bool $isPrivate = true): int
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Collection title cannot be empty.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_collections (user_id, title, description, is_private, created_at, updated_at) ' .
            'VALUES (:uid, :title, :desc, :priv, NOW(), NOW())'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':title' => $title,
            ':desc' => $description !== null ? trim($description) : null,
            ':priv' => $isPrivate ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteCollection(int $userId, int $collectionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_collections WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $collectionId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function addPostToCollection(int $userId, int $collectionId, int $postId): bool
    {
        // Verify ownership
        $stmt = $this->pdo->prepare('SELECT id FROM user_collections WHERE id = :cid AND user_id = :uid LIMIT 1');
        $stmt->execute([':cid' => $collectionId, ':uid' => $userId]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO collection_items (collection_id, post_id, created_at) VALUES (:cid, :pid, CURRENT_TIMESTAMP)'
            : 'INSERT IGNORE INTO collection_items (collection_id, post_id, created_at) VALUES (:cid, :pid, NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $collectionId, ':pid' => $postId]);
        return true;
    }

    public function removePostFromCollection(int $userId, int $collectionId, int $postId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE ci FROM collection_items ci ' .
            'JOIN user_collections c ON ci.collection_id = c.id ' .
            'WHERE ci.collection_id = :cid AND ci.post_id = :pid AND c.user_id = :uid'
        );
        $stmt->execute([':cid' => $collectionId, ':pid' => $postId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get posts in a collection.
     * @return array<string, mixed>|null
     */
    public function getCollectionDetails(int $collectionId, ?int $viewerId = null): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_collections WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $collectionId]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$collection) {
            return null;
        }

        if ((int) $collection['is_private'] === 1 && $viewerId !== (int) $collection['user_id']) {
            return null; // Private collection
        }

        $sql = 'SELECT p.id, p.title, p.summary, p.status, p.published_at, p.cover_image_path, ' .
            'c.id AS category_id, c.name AS category_name, c.slug AS category_slug, ' .
            'u.id AS author_id, u.display_name AS author_name, ' .
            'ci.created_at AS added_at ' .
            'FROM collection_items ci ' .
            'JOIN posts p ON ci.post_id = p.id ' .
            'JOIN categories c ON p.category_id = c.id ' .
            'JOIN users u ON p.author_id = u.id ' .
            'WHERE ci.collection_id = :cid AND p.status = \'published\' ' .
            'ORDER BY ci.created_at DESC';

        $itemStmt = $this->pdo->prepare($sql);
        $itemStmt->execute([':cid' => $collectionId]);
        $collection['posts'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        return $collection;
    }

    /**
     * Personal Highlights and Notes.
     */
    public function saveNote(int $userId, int $postId, string $noteText, ?string $highlightText = null, string $color = 'yellow', bool $isPrivate = true): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notes (user_id, post_id, highlight_text, note_text, color, is_private, created_at, updated_at) ' .
            'VALUES (:uid, :pid, :hl, :note, :color, :priv, NOW(), NOW())'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':pid' => $postId,
            ':hl' => $highlightText !== null ? trim($highlightText) : null,
            ':note' => trim($noteText),
            ':color' => $color,
            ':priv' => $isPrivate ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteNote(int $userId, int $noteId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_notes WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $noteId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get notes for a specific article for the current user.
     * @return array<int, array<string, mixed>>
     */
    public function getPostNotes(int $userId, int $postId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, post_id, highlight_text, note_text, color, is_private, created_at ' .
            'FROM user_notes WHERE user_id = :uid AND post_id = :pid ORDER BY created_at DESC'
        );
        $stmt->execute([':uid' => $userId, ':pid' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all notes for the current user across all articles.
     * @return array<int, array<string, mixed>>
     */
    public function getUserNotes(int $userId, ?string $search = null): array
    {
        $sql = 'SELECT n.id, n.post_id, n.highlight_text, n.note_text, n.color, n.is_private, n.created_at, ' .
            'p.title AS post_title ' .
            'FROM user_notes n ' .
            'JOIN posts p ON n.post_id = p.id ' .
            'WHERE n.user_id = :uid ';

        $params = [':uid' => $userId];
        if ($search !== null && trim($search) !== '') {
            $sql .= 'AND (n.note_text LIKE :s OR n.highlight_text LIKE :s OR p.title LIKE :s) ';
            $params[':s'] = '%' . trim($search) . '%';
        }

        $sql .= 'ORDER BY n.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Topic Preferences (Mute / Follow topics).
     */
    public function getTopicPreferences(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id AS category_id, c.name AS category_name, c.slug AS category_slug, ' .
            'COALESCE(p.is_muted, 0) AS is_muted, COALESCE(p.affinity_score, 0) AS affinity_score ' .
            'FROM categories c ' .
            'LEFT JOIN user_topic_preferences p ON c.id = p.category_id AND p.user_id = :uid ' .
            'WHERE c.is_active = 1 ' .
            'ORDER BY c.name ASC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setTopicPreference(int $userId, int $categoryId, bool $isMuted, int $affinityScore = 0): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_topic_preferences (user_id, category_id, is_muted, affinity_score, updated_at) ' .
                'VALUES (:uid, :cid, :muted, :aff, CURRENT_TIMESTAMP) ' .
                'ON CONFLICT(user_id, category_id) DO UPDATE SET ' .
                'is_muted = :muted, affinity_score = :aff, updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_topic_preferences (user_id, category_id, is_muted, affinity_score, updated_at) ' .
                'VALUES (:uid, :cid, :muted, :aff, NOW()) ' .
                'ON DUPLICATE KEY UPDATE ' .
                'is_muted = VALUES(is_muted), affinity_score = VALUES(affinity_score), updated_at = NOW()'
            );
        }

        $stmt->execute([
            ':uid' => $userId,
            ':cid' => $categoryId,
            ':muted' => $isMuted ? 1 : 0,
            ':aff' => $affinityScore,
        ]);
    }
}
