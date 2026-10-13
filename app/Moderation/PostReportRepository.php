<?php
declare(strict_types=1);

namespace InfinityBlog\Moderation;

use InvalidArgumentException;
use PDO;
use Throwable;

final class PostReportRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACTION_TAKEN = 'action_taken';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_DISMISSED,
        self::STATUS_ACTION_TAKEN,
    ];

    public const ALLOWED_REASONS = [
        'Spam or misleading content',
        'Harassment or bullying',
        'Hate or abusive content',
        'Sexual or inappropriate content',
        'Violence or dangerous content',
        'Copyright or intellectual-property concern',
        'Other',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Submit a report for a post.
     *
     * @return array{status: string, report_id?: int, message: string}
     */
    public function createReport(int $postId, int $reporterId, string $reason, ?string $explanation = null): array
    {
        // 1. Check if target post exists
        $postStmt = $this->pdo->prepare('SELECT id, author_id, title FROM posts WHERE id = :id');
        $postStmt->execute(['id' => $postId]);
        $post = $postStmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            return [
                'status' => 'not_found',
                'message' => 'The article you are trying to report does not exist.',
            ];
        }

        // 2. Prevent self-reporting
        if ((int) $post['author_id'] === $reporterId) {
            return [
                'status' => 'forbidden',
                'message' => 'You cannot report your own article.',
            ];
        }

        // 3. Validate reason
        $reason = trim($reason);
        if (!in_array($reason, self::ALLOWED_REASONS, true)) {
            return [
                'status' => 'invalid_reason',
                'message' => 'Please select a valid report reason from the provided options.',
            ];
        }

        // 4. Validate explanation (optional, max 500 chars)
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

        // 5. Check for existing active report (pending or under_review) by this user for this post
        $activeStmt = $this->pdo->prepare(
            'SELECT id, status FROM post_reports WHERE post_id = :pid AND reporter_id = :rid AND status IN (\'pending\', \'under_review\') LIMIT 1'
        );
        $activeStmt->execute(['pid' => $postId, 'rid' => $reporterId]);
        $existing = $activeStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return [
                'status' => 'duplicate',
                'report_id' => (int) $existing['id'],
                'message' => 'You have already submitted an active report for this post. Our moderation team is currently reviewing it.',
            ];
        }

        // 6. Rate limiting: max 10 reports per user in the last hour
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $rateSql = $driver === 'sqlite'
            ? 'SELECT COUNT(*) FROM post_reports WHERE reporter_id = :rid AND created_at >= datetime(\'now\', \'-1 hour\')'
            : 'SELECT COUNT(*) FROM post_reports WHERE reporter_id = :rid AND created_at >= NOW() - INTERVAL 1 HOUR';

        $rateStmt = $this->pdo->prepare($rateSql);
        $rateStmt->execute(['rid' => $reporterId]);
        $recentCount = (int) $rateStmt->fetchColumn();

        if ($recentCount >= 10) {
            return [
                'status' => 'rate_limited',
                'message' => 'You have submitted too many reports recently. Please wait before submitting another report.',
            ];
        }

        // 7. Insert the report
        $now = date('Y-m-d H:i:s');
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO post_reports (post_id, reporter_id, reason, explanation, status, created_at, updated_at) ' .
            'VALUES (:pid, :rid, :reason, :explanation, :status, :created_at, :updated_at)'
        );
        $insertStmt->execute([
            'pid' => $postId,
            'rid' => $reporterId,
            'reason' => $reason,
            'explanation' => $cleanExplanation,
            'status' => self::STATUS_PENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $reportId = (int) $this->pdo->lastInsertId();

        // 8. Log report creation to audit log if available
        $this->writeAudit($reporterId, 'post_report_created', 'post_report', $reportId, [
            'post_id' => $postId,
            'reason' => $reason,
        ]);

        return [
            'status' => 'created',
            'report_id' => $reportId,
            'message' => 'Thank you. Your report has been submitted to our moderation team for review.',
        ];
    }

    /**
     * Get a single report by ID with post, reporter, and reviewer info.
     *
     * @return array<string, mixed>|null
     */
    public function getReportById(int $reportId): ?array
    {
        $sql = 'SELECT pr.*, ' .
            'p.title AS post_title, p.status AS post_status, p.author_id AS post_author_id, ' .
            'author.display_name AS author_name, author.username AS author_username, ' .
            'reporter.display_name AS reporter_name, reporter.username AS reporter_username, ' .
            'reviewer.display_name AS reviewer_name, reviewer.username AS reviewer_username ' .
            'FROM post_reports pr ' .
            'JOIN posts p ON p.id = pr.post_id ' .
            'LEFT JOIN users author ON author.id = p.author_id ' .
            'LEFT JOIN users reporter ON reporter.id = pr.reporter_id ' .
            'LEFT JOIN users reviewer ON reviewer.id = pr.reviewed_by ' .
            'WHERE pr.id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $reportId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * List all reports with search, filtering, and pagination.
     *
     * @param array{status?: string, reason?: string, post_id?: int, search?: string, from_date?: string, to_date?: string} $filters
     * @return array{reports: list<array<string, mixed>>, total: int, total_pages: int, current_page: int}
     */
    public function listReports(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::ALLOWED_STATUSES, true)) {
            $conditions[] = 'pr.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['reason']) && in_array($filters['reason'], self::ALLOWED_REASONS, true)) {
            $conditions[] = 'pr.reason = :reason';
            $params['reason'] = $filters['reason'];
        }

        if (!empty($filters['post_id'])) {
            $conditions[] = 'pr.post_id = :post_id';
            $params['post_id'] = (int) $filters['post_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(p.title LIKE :search OR pr.explanation LIKE :search OR author.display_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['from_date'])) {
            $conditions[] = 'pr.created_at >= :from_date';
            $params['from_date'] = $filters['from_date'] . ' 00:00:00';
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 'pr.created_at <= :to_date';
            $params['to_date'] = $filters['to_date'] . ' 23:59:59';
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Total count
        $countSql = 'SELECT COUNT(*) FROM post_reports pr ' .
            'JOIN posts p ON p.id = pr.post_id ' .
            'LEFT JOIN users author ON author.id = p.author_id ' .
            $whereClause;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Paginated results
        $sql = 'SELECT pr.*, ' .
            'p.title AS post_title, p.status AS post_status, p.author_id AS post_author_id, ' .
            'author.display_name AS author_name, author.username AS author_username, ' .
            'reporter.display_name AS reporter_name, reporter.username AS reporter_username, ' .
            'reviewer.display_name AS reviewer_name, reviewer.username AS reviewer_username ' .
            'FROM post_reports pr ' .
            'JOIN posts p ON p.id = pr.post_id ' .
            'LEFT JOIN users author ON author.id = p.author_id ' .
            'LEFT JOIN users reporter ON reporter.id = pr.reporter_id ' .
            'LEFT JOIN users reviewer ON reviewer.id = pr.reviewed_by ' .
            $whereClause .
            ' ORDER BY pr.created_at DESC, pr.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'reports' => $reports,
            'total' => $total,
            'total_pages' => $totalPages,
            'current_page' => $page,
        ];
    }

    /**
     * Get reported posts grouped by post ID.
     *
     * @param array{status?: string, search?: string} $filters
     * @return array{posts: list<array<string, mixed>>, total: int, total_pages: int, current_page: int}
     */
    public function getReportsGroupedByPost(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::ALLOWED_STATUSES, true)) {
            $conditions[] = 'pr.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(p.title LIKE :search OR author.display_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Distinct post count
        $countSql = 'SELECT COUNT(DISTINCT pr.post_id) FROM post_reports pr ' .
            'JOIN posts p ON p.id = pr.post_id ' .
            'LEFT JOIN users author ON author.id = p.author_id ' .
            $whereClause;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Grouped query
        $sql = 'SELECT p.id AS post_id, p.title AS post_title, p.status AS post_status, p.author_id, ' .
            'author.display_name AS author_name, author.username AS author_username, ' .
            'COUNT(pr.id) AS total_reports, ' .
            'SUM(CASE WHEN pr.status = \'pending\' THEN 1 ELSE 0 END) AS pending_count, ' .
            'SUM(CASE WHEN pr.status = \'under_review\' THEN 1 ELSE 0 END) AS under_review_count, ' .
            'SUM(CASE WHEN pr.status = \'dismissed\' THEN 1 ELSE 0 END) AS dismissed_count, ' .
            'SUM(CASE WHEN pr.status = \'action_taken\' THEN 1 ELSE 0 END) AS action_taken_count, ' .
            'MAX(pr.created_at) AS latest_report_at ' .
            'FROM post_reports pr ' .
            'JOIN posts p ON p.id = pr.post_id ' .
            'LEFT JOIN users author ON author.id = p.author_id ' .
            $whereClause .
            ' GROUP BY p.id, p.title, p.status, p.author_id, author.display_name, author.username ' .
            ' ORDER BY latest_report_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'posts' => $posts,
            'total' => $total,
            'total_pages' => $totalPages,
            'current_page' => $page,
        ];
    }

    /**
     * Get all individual reports for a specific post.
     *
     * @return list<array<string, mixed>>
     */
    public function getReportsForPost(int $postId): array
    {
        $sql = 'SELECT pr.*, ' .
            'reporter.display_name AS reporter_name, reporter.username AS reporter_username, ' .
            'reviewer.display_name AS reviewer_name, reviewer.username AS reviewer_username ' .
            'FROM post_reports pr ' .
            'LEFT JOIN users reporter ON reporter.id = pr.reporter_id ' .
            'LEFT JOIN users reviewer ON reviewer.id = pr.reviewed_by ' .
            'WHERE pr.post_id = :pid ' .
            'ORDER BY pr.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['pid' => $postId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update status of an individual report.
     */
    public function updateReportStatus(int $reportId, string $newStatus, int $adminId, ?string $notes = null): bool
    {
        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid report status.');
        }

        $now = date('Y-m-d H:i:s');
        $cleanNotes = $notes !== null ? trim($notes) : null;

        $stmt = $this->pdo->prepare(
            'UPDATE post_reports SET status = :status, reviewed_by = :aid, reviewed_at = :now, ' .
            'moderation_notes = :notes, updated_at = :now WHERE id = :id'
        );
        $stmt->execute([
            'status' => $newStatus,
            'aid' => $adminId,
            'now' => $now,
            'notes' => $cleanNotes,
            'id' => $reportId,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->writeAudit($adminId, 'moderation_report_status_update', 'post_report', $reportId, [
                'new_status' => $newStatus,
                'notes' => $cleanNotes,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Update all active reports for a post (e.g. when unpublishing or dismissing).
     */
    public function updateAllReportsForPost(int $postId, string $newStatus, int $adminId, ?string $notes = null): int
    {
        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid report status.');
        }

        $now = date('Y-m-d H:i:s');
        $cleanNotes = $notes !== null ? trim($notes) : null;

        $stmt = $this->pdo->prepare(
            'UPDATE post_reports SET status = :status, reviewed_by = :aid, reviewed_at = :now, ' .
            'moderation_notes = COALESCE(:notes, moderation_notes), updated_at = :now ' .
            'WHERE post_id = :pid AND status IN (\'pending\', \'under_review\')'
        );
        $stmt->execute([
            'status' => $newStatus,
            'aid' => $adminId,
            'now' => $now,
            'notes' => $cleanNotes,
            'pid' => $postId,
        ]);

        $count = $stmt->rowCount();

        if ($count > 0) {
            $this->writeAudit($adminId, 'moderation_post_reports_batch_update', 'post', $postId, [
                'new_status' => $newStatus,
                'affected_reports' => $count,
                'notes' => $cleanNotes,
            ]);
        }

        return $count;
    }

    /**
     * Hide/unpublish a reported post via explicit admin moderation action.
     */
    public function hideOrUnpublishPost(int $postId, int $adminId, string $reason = 'Content moderation violation'): bool
    {
        $stmt = $this->pdo->prepare('UPDATE posts SET status = \'draft\', updated_at = :now WHERE id = :pid');
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['now' => $now, 'pid' => $postId]);

        if ($stmt->rowCount() > 0) {
            // Update active reports to action_taken
            $this->updateAllReportsForPost($postId, self::STATUS_ACTION_TAKEN, $adminId, 'Post unpublished by administrator: ' . $reason);

            $this->writeAudit($adminId, 'post_moderated_unpublish', 'post', $postId, [
                'reason' => $reason,
                'action' => 'unpublish',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Restore a previously hidden/drafted post to published status.
     */
    public function restorePost(int $postId, int $adminId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE posts SET status = \'published\', updated_at = :now WHERE id = :pid');
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['now' => $now, 'pid' => $postId]);

        if ($stmt->rowCount() > 0) {
            $this->writeAudit($adminId, 'post_moderated_restore', 'post', $postId, [
                'action' => 'restore',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Count total pending reports for navbar indicator.
     */
    public function getPendingCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM post_reports WHERE status = \'pending\'');
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get aggregate statistics across all reports.
     *
     * @return array{pending: int, under_review: int, dismissed: int, action_taken: int, total: int}
     */
    public function getModerationStats(): array
    {
        $stmt = $this->pdo->query(
            'SELECT ' .
            'SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending, ' .
            'SUM(CASE WHEN status = \'under_review\' THEN 1 ELSE 0 END) AS under_review, ' .
            'SUM(CASE WHEN status = \'dismissed\' THEN 1 ELSE 0 END) AS dismissed, ' .
            'SUM(CASE WHEN status = \'action_taken\' THEN 1 ELSE 0 END) AS action_taken, ' .
            'COUNT(DISTINCT post_id) AS reported_posts_count, ' .
            'COUNT(*) AS total ' .
            'FROM post_reports'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'under_review' => (int) ($row['under_review'] ?? 0),
            'dismissed' => (int) ($row['dismissed'] ?? 0),
            'action_taken' => (int) ($row['action_taken'] ?? 0),
            'reported_posts_count' => (int) ($row['reported_posts_count'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    private function writeAudit(?int $actorId, string $action, string $entityType, ?int $entityId, array $metadata): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, created_at) ' .
                'VALUES (:actor_id, :action, :entity_type, :entity_id, :metadata, :created_at)'
            );
            $stmt->execute([
                'actor_id' => $actorId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Tolerate test environments where audit_logs table might not be present
        }
    }
}
