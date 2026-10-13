<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

use PDO;

final class StudioService
{
    private PDO $pdo;
    private PostRepository $posts;

    public function __construct(PDO $pdo, PostRepository $posts)
    {
        $this->pdo = $pdo;
        $this->posts = $posts;
    }

    /**
     * Save a version snapshot of a post.
     */
    public function saveVersion(int $postId, int $authorId, string $title, string $content, ?string $summary = null): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM post_versions WHERE post_id = :pid');
        $stmt->execute([':pid' => $postId]);
        $versionNum = (int) $stmt->fetchColumn();

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO post_versions (post_id, author_id, title, content, version_number, change_summary, created_at) ' .
            'VALUES (:pid, :aid, :title, :content, :vnum, :sum, :created_at)'
        );
        $stmt->execute([
            ':pid' => $postId,
            ':aid' => $authorId,
            ':title' => $title,
            ':content' => $content,
            ':vnum' => $versionNum,
            ':sum' => $summary,
            ':created_at' => $now,
        ]);
        return $versionNum;
    }

    /**
     * Get versions list for a post.
     * @return array<int, array<string, mixed>>
     */
    public function getVersions(int $postId, int $authorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, post_id, author_id, title, version_number, change_summary, created_at ' .
            'FROM post_versions WHERE post_id = :pid AND author_id = :aid ORDER BY version_number DESC'
        );
        $stmt->execute([':pid' => $postId, ':aid' => $authorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific version's full content.
     * @return array<string, mixed>|null
     */
    public function getVersion(int $postId, int $versionNumber, int $authorId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM post_versions WHERE post_id = :pid AND version_number = :vnum AND author_id = :aid LIMIT 1'
        );
        $stmt->execute([':pid' => $postId, ':vnum' => $versionNumber, ':aid' => $authorId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($res) ? $res : null;
    }

    /**
     * Increment views count on article view.
     */
    public function incrementViews(int $postId): void
    {
        $stmt = $this->pdo->prepare('UPDATE posts SET views_count = views_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $postId]);
    }

    /**
     * Get author analytics for the studio dashboard.
     * @return array{
     *     total_posts: int,
     *     published_posts: int,
     *     total_views: int,
     *     avg_reader_score: float,
     *     total_followers: int,
     *     total_comments: int
     * }
     */
    public function getAuthorAnalytics(int $authorId): array
    {
        // Total posts & published
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total, ' .
            'SUM(CASE WHEN status = \'published\' THEN 1 ELSE 0 END) AS published, ' .
            'COALESCE(SUM(views_count), 0) AS views ' .
            'FROM posts WHERE author_id = :aid'
        );
        $stmt->execute([':aid' => $authorId]);
        $postStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Avg score
        $stmt = $this->pdo->prepare(
            'SELECT AVG(s.score) AS avg_score ' .
            'FROM post_scores s ' .
            'JOIN posts p ON s.post_id = p.id ' .
            'WHERE p.author_id = :aid'
        );
        $stmt->execute([':aid' => $authorId]);
        $avgScore = (float) ($stmt->fetchColumn() ?: 0.0);

        // Followers
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE following_id = :aid');
        $stmt->execute([':aid' => $authorId]);
        $followers = (int) $stmt->fetchColumn();

        // Comments
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM article_comments c ' .
            'JOIN posts p ON c.post_id = p.id ' .
            'WHERE p.author_id = :aid AND c.status != \'deleted\''
        );
        $stmt->execute([':aid' => $authorId]);
        $comments = (int) $stmt->fetchColumn();

        return [
            'total_posts' => (int) ($postStats['total'] ?? 0),
            'published_posts' => (int) ($postStats['published'] ?? 0),
            'total_views' => (int) ($postStats['views'] ?? 0),
            'avg_reader_score' => round($avgScore, 1),
            'total_followers' => $followers,
            'total_comments' => $comments,
        ];
    }

    /**
     * Idempotent scheduled post publisher.
     * @return int Number of posts published
     */
    public function publishDuePosts(): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE posts SET status = \'published\', published_at = :now1, scheduled_at = NULL ' .
            'WHERE (status = \'scheduled\' OR (status = \'draft\' AND scheduled_at IS NOT NULL)) AND scheduled_at <= :now2'
        );
        $stmt->execute([':now1' => $now, ':now2' => $now]);
        return $stmt->rowCount();
    }
}
