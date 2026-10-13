<?php
declare(strict_types=1);

namespace InfinityBlog\Scores;

use PDO;
use RuntimeException;
use Throwable;

final class ScoreRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{average: float, count: int}
     */
    public function getSummary(int $postId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total_scores, COALESCE(AVG(score), 0) AS avg_score ' .
            'FROM post_scores WHERE post_id = :post_id'
        );
        $stmt->execute(['post_id' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['average' => 0.0, 'count' => 0];
        }

        return [
            'average' => round((float) $row['avg_score'], 1),
            'count' => (int) $row['total_scores'],
        ];
    }

    /**
     * Batch fetch score summaries for multiple post IDs.
     *
     * @param list<int> $postIds
     * @return array<int, array{average: float, count: int}>
     */
    public function getSummariesForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT post_id, COUNT(*) AS total_scores, COALESCE(AVG(score), 0) AS avg_score " .
            "FROM post_scores WHERE post_id IN ({$placeholders}) GROUP BY post_id"
        );
        $stmt->execute($postIds);

        $results = [];
        foreach ($postIds as $id) {
            $results[(int) $id] = [
                'average' => 0.0,
                'count' => 0,
            ];
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postId = (int) $row['post_id'];
            $results[$postId] = [
                'average' => round((float) $row['avg_score'], 1),
                'count' => (int) $row['total_scores'],
            ];
        }

        return $results;
    }

    public function getUserScore(int $postId, int $userId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT score FROM post_scores WHERE post_id = :post_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $score = $stmt->fetchColumn();

        return $score !== false ? (int) $score : null;
    }

    /**
     * Submit or update a 1-5 score for an article.
     *
     * @return array{user_score: int, average: float, count: int}
     */
    public function submitScore(int $postId, int $userId, int $score): array
    {
        if ($score < 1 || $score > 5) {
            throw new ScoreValidationException(
                'Reader score must be an integer between 1 and 5.',
                ['score' => 'Please provide a score between 1 and 5 stars.']
            );
        }

        // Validate that post exists and is published
        $postStmt = $this->pdo->prepare(
            'SELECT id, author_id, status FROM posts WHERE id = :id LIMIT 1'
        );
        $postStmt->execute(['id' => $postId]);
        $post = $postStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($post) || ($post['status'] ?? '') !== 'published') {
            throw new ScoreValidationException(
                'Scores can only be submitted for published articles.',
                ['post' => 'The article was not found or is not currently published.']
            );
        }

        // Disallow self-scoring: authors cannot score their own articles
        if ((int) $post['author_id'] === $userId) {
            throw new ScoreValidationException(
                'Authors cannot score their own articles.',
                ['author' => 'You cannot submit a Reader Score for your own article.']
            );
        }

        $this->begin();
        try {
            $existingScore = $this->getUserScore($postId, $userId);

            if ($existingScore !== null) {
                $updateStmt = $this->pdo->prepare(
                    'UPDATE post_scores SET score = :score, updated_at = CURRENT_TIMESTAMP ' .
                    'WHERE post_id = :post_id AND user_id = :user_id'
                );
                $updateStmt->execute([
                    'score' => $score,
                    'post_id' => $postId,
                    'user_id' => $userId,
                ]);
            } else {
                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO post_scores (post_id, user_id, score) ' .
                    'VALUES (:post_id, :user_id, :score)'
                );
                $insertStmt->execute([
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'score' => $score,
                ]);
            }

            // Write safe audit event
            $auditStmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata) ' .
                'VALUES (:actor_id, :action, \'post\', :entity_id, :metadata)'
            );
            $auditStmt->execute([
                'actor_id' => $userId,
                'action' => $existingScore !== null ? 'score.updated' : 'score.submitted',
                'entity_id' => $postId,
                'metadata' => json_encode(['score' => $score], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ]);

            $summary = $this->getSummary($postId);
            $this->pdo->commit();

            try {
                $authorId = (int) ($post['author_id'] ?? 0);
                if ($authorId > 0 && $authorId !== $userId) {
                    $notifs = new \InfinityBlog\Social\NotificationRepository($this->pdo);
                    $notifs->createNotification(
                        $authorId,
                        $userId,
                        'score',
                        $postId,
                        "A reader rated your article {$score}/5 stars."
                    );
                }
            } catch (\Throwable) {
                // Ignore notification failure
            }

            return [
                'user_score' => $score,
                'summary' => $summary,
                'average' => $summary['average'],
                'count' => $summary['count'],
            ];
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
            throw new RuntimeException('The database transaction could not begin.');
        }
    }

    private function rollback(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }
        try {
            $this->pdo->rollBack();
        } catch (Throwable) {
            // Ignore rollback exceptions during failure unwinding
        }
    }
}
