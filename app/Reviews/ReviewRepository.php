<?php
declare(strict_types=1);

namespace InfinityBlog\Reviews;

use InfinityBlog\Posts\ArticleImageHelper;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Social\AuthorBadgeService;
use PDO;
use RuntimeException;
use Throwable;

final class ReviewRepository
{
    private PDO $pdo;
    private ?ScoreRepository $scores;

    public function __construct(PDO $pdo, ?ScoreRepository $scores = null)
    {
        $this->pdo = $pdo;
        $this->scores = $scores ?? new ScoreRepository($pdo);
    }

    /**
     * Get paginated reviews for a published article.
     *
     * @return array{
     *   reviews: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   total_pages: int
     * }
     */
    public function getReviewsForPost(int $postId, ?int $currentUserId = null, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        // Total count of published reviews for this post
        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM post_reviews WHERE post_id = :post_id AND status = \'published\''
        );
        $countStmt->execute(['post_id' => $postId]);
        $total = (int) $countStmt->fetchColumn();

        if ($total === 0) {
            return [
                'reviews' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0,
            ];
        }

        // Fetch reviews with user details
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.post_id, r.user_id, r.rating, r.content, r.status, r.created_at, r.updated_at, ' .
            'u.username, u.display_name, u.avatar_path ' .
            'FROM post_reviews r ' .
            'JOIN users u ON u.id = r.user_id ' .
            'WHERE r.post_id = :post_id AND r.status = \'published\' ' .
            'ORDER BY r.created_at DESC ' .
            'LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch author badges in batch
        $badgeService = new AuthorBadgeService($this->pdo);
        $reviewerIds = array_values(array_unique(array_map(static fn(array $r): int => (int) $r['user_id'], $rows)));
        $badgeMap = !empty($reviewerIds) ? $badgeService->getBadgeStatusesForUsers($reviewerIds) : [];

        $reviews = [];
        foreach ($rows as $row) {
            $reviewerId = (int) $row['user_id'];
            $reviews[] = [
                'id' => (int) $row['id'],
                'post_id' => (int) $row['post_id'],
                'user_id' => $reviewerId,
                'rating' => (int) $row['rating'],
                'content' => (string) $row['content'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
                'username' => (string) ($row['username'] ?? 'reader'),
                'display_name' => (string) (!empty($row['display_name']) ? $row['display_name'] : $row['username']),
                'avatar_url' => ArticleImageHelper::resolveAvatarUrl($row['avatar_path'] ?? null),
                'has_author_badge' => !empty($badgeMap[$reviewerId]),
                'is_own_review' => $currentUserId !== null && $reviewerId === $currentUserId,
            ];
        }

        return [
            'reviews' => $reviews,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get a specific user's review for an article if it exists.
     *
     * @return array<string, mixed>|null
     */
    public function getUserReview(int $postId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.post_id, r.user_id, r.rating, r.content, r.status, r.created_at, r.updated_at, ' .
            'u.username, u.display_name, u.avatar_path ' .
            'FROM post_reviews r ' .
            'JOIN users u ON u.id = r.user_id ' .
            'WHERE r.post_id = :post_id AND r.user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $badgeService = new AuthorBadgeService($this->pdo);
        $hasBadge = $badgeService->qualifiesForAuthorBadge($userId);

        return [
            'id' => (int) $row['id'],
            'post_id' => (int) $row['post_id'],
            'user_id' => $userId,
            'rating' => (int) $row['rating'],
            'content' => (string) $row['content'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'username' => (string) ($row['username'] ?? 'reader'),
            'display_name' => (string) (!empty($row['display_name']) ? $row['display_name'] : $row['username']),
            'avatar_url' => ArticleImageHelper::resolveAvatarUrl($row['avatar_path'] ?? null),
            'has_author_badge' => $hasBadge,
            'is_own_review' => true,
        ];
    }

    /**
     * Submit or update a written review (and sync the user's reader score).
     *
     * @return array{
     *   review: array<string, mixed>,
     *   score_summary: array{average: float, count: int, user_score: int}
     * }
     */
    public function submitReview(int $postId, int $userId, int $rating, string $content): array
    {
        if ($rating < 1 || $rating > 5) {
            throw new ReviewValidationException(
                'Review rating must be an integer between 1 and 5 stars.',
                ['rating' => 'Please provide a valid rating between 1 and 5 stars.']
            );
        }

        $cleanContent = trim($content);
        if ($cleanContent === '') {
            throw new ReviewValidationException(
                'Review content cannot be empty.',
                ['content' => 'Please share your thoughts or evaluation in the review text.']
            );
        }

        if (mb_strlen($cleanContent) > 5000) {
            throw new ReviewValidationException(
                'Review content must not exceed 5,000 characters.',
                ['content' => 'Your review is too long (maximum 5,000 characters).']
            );
        }

        // Validate that post exists and is published
        $postStmt = $this->pdo->prepare(
            'SELECT id, author_id, status FROM posts WHERE id = :id LIMIT 1'
        );
        $postStmt->execute(['id' => $postId]);
        $post = $postStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($post) || ($post['status'] ?? '') !== 'published') {
            throw new ReviewValidationException(
                'Reviews can only be submitted for published articles.',
                ['post' => 'The article was not found or is not currently published.']
            );
        }

        // Prevent self-reviewing: authors cannot review their own articles
        if ((int) $post['author_id'] === $userId) {
            throw new ReviewValidationException(
                'Authors cannot submit reviews on their own articles.',
                ['author' => 'You cannot submit a review for your own article.']
            );
        }

        $this->begin();
        try {
            $existing = $this->getUserReview($postId, $userId);

            if ($existing !== null) {
                $updateStmt = $this->pdo->prepare(
                    'UPDATE post_reviews SET rating = :rating, content = :content, updated_at = CURRENT_TIMESTAMP ' .
                    'WHERE post_id = :post_id AND user_id = :user_id'
                );
                $updateStmt->execute([
                    'rating' => $rating,
                    'content' => $cleanContent,
                    'post_id' => $postId,
                    'user_id' => $userId,
                ]);
                $reviewId = (int) $existing['id'];
                $action = 'review.updated';
            } else {
                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO post_reviews (post_id, user_id, rating, content, status) ' .
                    'VALUES (:post_id, :user_id, :rating, :content, \'published\')'
                );
                $insertStmt->execute([
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'rating' => $rating,
                    'content' => $cleanContent,
                ]);
                $reviewId = (int) $this->pdo->lastInsertId();
                $action = 'review.submitted';
            }

            // Write safe audit event
            $auditStmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata) ' .
                'VALUES (:actor_id, :action, \'post_review\', :entity_id, :metadata)'
            );
            $auditStmt->execute([
                'actor_id' => $userId,
                'action' => $action,
                'entity_id' => $reviewId,
                'metadata' => json_encode(['post_id' => $postId, 'rating' => $rating], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ]);

            // Sync with post_scores
            $scoreExisting = $this->scores->getUserScore($postId, $userId);
            if ($scoreExisting !== null) {
                $updateScoreStmt = $this->pdo->prepare(
                    'UPDATE post_scores SET score = :score, updated_at = CURRENT_TIMESTAMP ' .
                    'WHERE post_id = :post_id AND user_id = :user_id'
                );
                $updateScoreStmt->execute([
                    'score' => $rating,
                    'post_id' => $postId,
                    'user_id' => $userId,
                ]);
            } else {
                $insertScoreStmt = $this->pdo->prepare(
                    'INSERT INTO post_scores (post_id, user_id, score) ' .
                    'VALUES (:post_id, :user_id, :score)'
                );
                $insertScoreStmt->execute([
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'score' => $rating,
                ]);
            }

            $scoreSummary = $this->scores->getSummary($postId);
            $this->pdo->commit();

            // Notify author if different
            try {
                $authorId = (int) ($post['author_id'] ?? 0);
                if ($authorId > 0 && $authorId !== $userId) {
                    $notifs = new \InfinityBlog\Social\NotificationRepository($this->pdo);
                    $notifs->createNotification(
                        $authorId,
                        $userId,
                        'review',
                        $postId,
                        "A reader posted a {$rating}/5 star written review on your article."
                    );
                }
            } catch (\Throwable) {
            }

            $savedReview = $this->getUserReview($postId, $userId);

            return [
                'review' => $savedReview ?? [
                    'id' => $reviewId,
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'rating' => $rating,
                    'content' => $cleanContent,
                    'status' => 'published',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'score_summary' => [
                    'average' => $scoreSummary['average'],
                    'count' => $scoreSummary['count'],
                    'user_score' => $rating,
                ],
            ];
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /**
     * Delete a review.
     */
    public function deleteReview(int $reviewId, int $userId, bool $isAdmin = false): bool
    {
        $res = $this->deleteReviewWithSummary($reviewId, $userId, $isAdmin);
        return $res !== null && !empty($res['deleted']);
    }

    /**
     * @return array{deleted: bool, post_id: int, total_reviews: int, score_summary: array{average: float, count: int}}|null
     */
    public function deleteReviewWithSummary(int $reviewId, int $userId, bool $isAdmin = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, post_id, user_id FROM post_reviews WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            return null;
        }

        if ((int) $review['user_id'] !== $userId && !$isAdmin) {
            throw new ReviewValidationException('You are not authorized to delete this review.');
        }

        $postId = (int) $review['post_id'];
        $this->begin();
        try {
            $delStmt = $this->pdo->prepare('DELETE FROM post_reviews WHERE id = :id');
            $delStmt->execute(['id' => $reviewId]);

            // Synchronize removal from post_scores
            try {
                $delScoreStmt = $this->pdo->prepare('DELETE FROM post_scores WHERE post_id = :post_id AND user_id = :user_id');
                $delScoreStmt->execute([
                    'post_id' => $postId,
                    'user_id' => (int) $review['user_id'],
                ]);
            } catch (\Throwable) {
            }

            // Safe audit log
            $auditStmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata) ' .
                'VALUES (:actor_id, \'review.deleted\', \'post_review\', :entity_id, :metadata)'
            );
            $auditStmt->execute([
                'actor_id' => $userId,
                'entity_id' => $reviewId,
                'metadata' => json_encode(['post_id' => $postId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            $totalReviews = $this->getReviewCountForPost($postId);
            $scoreSummary = $this->scores->getSummary($postId);

            return [
                'deleted' => true,
                'post_id' => $postId,
                'total_reviews' => $totalReviews,
                'score_summary' => $scoreSummary,
            ];
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getReviewCountForPost(int $postId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM post_reviews WHERE post_id = :post_id AND status = \'published\'');
        $stmt->execute(['post_id' => $postId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Report an inappropriate review.
     *
     * @return array{status: string, message: string}
     */
    public function reportReview(int $reviewId, int $reporterId, string $reason, ?string $explanation = null): array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_id, post_id FROM post_reviews WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            return [
                'status' => 'not_found',
                'message' => 'The review you are trying to report does not exist.',
            ];
        }

        if ((int) $review['user_id'] === $reporterId) {
            return [
                'status' => 'forbidden',
                'message' => 'You cannot report your own review.',
            ];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return [
                'status' => 'invalid_reason',
                'message' => 'Please select a reason for reporting.',
            ];
        }

        $cleanExplanation = null;
        if ($explanation !== null && trim($explanation) !== '') {
            $cleanExplanation = trim($explanation);
            if (mb_strlen($cleanExplanation) > 500) {
                return [
                    'status' => 'explanation_too_long',
                    'message' => 'Explanation must not exceed 500 characters.',
                ];
            }
        }

        // Check for duplicate active report
        $activeStmt = $this->pdo->prepare(
            'SELECT id FROM review_reports WHERE review_id = :rid AND reporter_id = :repid AND status IN (\'pending\', \'under_review\') LIMIT 1'
        );
        $activeStmt->execute(['rid' => $reviewId, 'repid' => $reporterId]);
        if ($activeStmt->fetch()) {
            return [
                'status' => 'duplicate',
                'message' => 'You have already submitted a report for this review. Our moderation team is reviewing it.',
            ];
        }

        $insStmt = $this->pdo->prepare(
            'INSERT INTO review_reports (review_id, reporter_id, reason, explanation, status) ' .
            'VALUES (:rid, :repid, :reason, :explanation, \'pending\')'
        );
        $insStmt->execute([
            'rid' => $reviewId,
            'repid' => $reporterId,
            'reason' => $reason,
            'explanation' => $cleanExplanation,
        ]);

        return [
            'status' => 'success',
            'message' => 'Thank you. Your report has been submitted for moderation review.',
        ];
    }

    /**
     * List review reports for moderation (admin).
     *
     * @return array{reports: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function listReports(int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'under_review', 'dismissed', 'action_taken'], true)) {
            $where = 'WHERE rr.status = :status';
            $params['status'] = $status;
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM review_reports rr {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT rr.*, " .
            "r.rating AS review_rating, r.content AS review_content, r.post_id, " .
            "p.title AS post_title, " .
            "u.username AS reviewer_username, u.display_name AS reviewer_display_name, " .
            "rep.username AS reporter_username, rep.display_name AS reporter_display_name " .
            "FROM review_reports rr " .
            "JOIN post_reviews r ON r.id = rr.review_id " .
            "JOIN posts p ON p.id = r.post_id " .
            "JOIN users u ON u.id = r.user_id " .
            "JOIN users rep ON rep.id = rr.reporter_id " .
            "{$where} ORDER BY rr.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'reports' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Update review report status (admin).
     */
    public function updateReportStatus(int $reportId, string $status): bool
    {
        if (!in_array($status, ['pending', 'under_review', 'dismissed', 'action_taken'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE review_reports SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $reportId]);

        return $stmt->rowCount() > 0;
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
        }
    }
}
