<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Moderation\PostReportRepository;

final class ModerationController
{
    private PostReportRepository $reports;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(
        PostReportRepository $reports,
        AuthGuard $guard,
        SessionManager $sessions
    ) {
        $this->reports = $reports;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /**
     * Dispatch moderation API requests.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(Request $request): array
    {
        $path = $request->path;
        $method = $request->method;

        // 1. Submit Post Report: POST /api/posts/{id}/report
        if ($method === 'POST' && preg_match('#^/api/posts/([0-9]+)/report$#D', $path, $m)) {
            return $this->reportPost($request, (int) $m[1]);
        }

        // 2. Admin: Get pending report count: GET /api/admin/reports/pending-count
        if ($method === 'GET' && $path === '/api/admin/reports/pending-count') {
            return $this->getPendingCount($request);
        }

        // 3. Admin: List all reports: GET /api/admin/reports
        if ($method === 'GET' && $path === '/api/admin/reports') {
            return $this->listReports($request);
        }

        // 4. Admin: Get single report: GET /api/admin/reports/{id}
        if ($method === 'GET' && preg_match('#^/api/admin/reports/([0-9]+)$#D', $path, $m)) {
            return $this->getReport($request, (int) $m[1]);
        }

        // 5. Admin: Get all reports for a post: GET /api/admin/reports/posts/{postId}
        if ($method === 'GET' && preg_match('#^/api/admin/reports/posts/([0-9]+)$#D', $path, $m)) {
            return $this->getReportsForPost($request, (int) $m[1]);
        }

        // 6. Admin: Update report status: POST /api/admin/reports/{id}/status
        if ($method === 'POST' && preg_match('#^/api/admin/reports/([0-9]+)/status$#D', $path, $m)) {
            return $this->updateReportStatus($request, (int) $m[1]);
        }

        // 7. Admin: Unpublish/hide reported post: POST /api/admin/reports/posts/{postId}/unpublish
        if ($method === 'POST' && preg_match('#^/api/admin/reports/posts/([0-9]+)/unpublish$#D', $path, $m)) {
            return $this->unpublishPost($request, (int) $m[1]);
        }

        // 8. Admin: Restore post: POST /api/admin/reports/posts/{postId}/restore
        if ($method === 'POST' && preg_match('#^/api/admin/reports/posts/([0-9]+)/restore$#D', $path, $m)) {
            return $this->restorePost($request, (int) $m[1]);
        }

        return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Moderation endpoint not found.')];
    }

    /**
     * Submit a report for a post (Authenticated Users).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function reportPost(Request $request, int $postId): array
    {
        // 1. Authentication check
        $user = $this->guard->currentAccount();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'You must be signed in to report an article.')];
        }

        // 2. CSRF verification
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed. Please refresh and try again.')];
        }

        // 3. Input extraction
        $input = $request->input();
        $reason = trim((string) ($input['reason'] ?? ''));
        $explanation = isset($input['explanation']) ? trim((string) $input['explanation']) : null;

        if ($reason === '') {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Please select a reason for reporting this article.')];
        }

        // 4. Delegate to repository
        $result = $this->reports->createReport($postId, $user->id, $reason, $explanation);

        return match ($result['status']) {
            'created' => [
                'status' => 201,
                'body' => [
                    'data' => [
                        'success' => true,
                        'report_id' => $result['report_id'],
                        'message' => $result['message'],
                    ],
                ],
            ],
            'duplicate' => [
                'status' => 200,
                'body' => [
                    'data' => [
                        'success' => true,
                        'duplicate' => true,
                        'message' => $result['message'],
                    ],
                ],
            ],
            'forbidden' => [
                'status' => 403,
                'body' => ApiErrorResponder::build('FORBIDDEN', $result['message']),
            ],
            'not_found' => [
                'status' => 404,
                'body' => ApiErrorResponder::build('NOT_FOUND', $result['message']),
            ],
            'explanation_too_long', 'invalid_reason' => [
                'status' => 422,
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', $result['message']),
            ],
            'rate_limited' => [
                'status' => 429,
                'body' => ApiErrorResponder::build('RATE_LIMITED', $result['message']),
            ],
            default => [
                'status' => 500,
                'body' => ApiErrorResponder::build('INTERNAL_ERROR', 'Unable to submit report.'),
            ],
        };
    }

    /**
     * Admin: Get count of pending reports.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function getPendingCount(Request $request): array
    {
        $admin = $this->requireAdmin($request, false);
        if (is_array($admin)) {
            return $admin;
        }

        $count = $this->reports->getPendingCount();
        return ['status' => 200, 'body' => ['data' => ['pending_count' => $count]]];
    }

    /**
     * Admin: List reports with filters and pagination.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function listReports(Request $request): array
    {
        $admin = $this->requireAdmin($request, false);
        if (is_array($admin)) {
            return $admin;
        }

        $filters = [];
        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $filters['status'] = $status;
        }

        $reason = $request->query('reason');
        if ($reason !== null && $reason !== '') {
            $filters['reason'] = $reason;
        }

        $postId = $request->query('post_id');
        if ($postId !== null && $postId !== '') {
            $filters['post_id'] = (int) $postId;
        }

        $search = $request->query('search');
        if ($search !== null && $search !== '') {
            $filters['search'] = $search;
        }

        $page = max(1, (int) ($request->query('page') ?? 1));
        $perPage = max(1, min(50, (int) ($request->query('per_page') ?? 20)));

        $result = $this->reports->listReports($filters, $page, $perPage);

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'reports' => $result['reports'],
                    'stats' => $this->reports->getModerationStats(),
                ],
                'meta' => [
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                    'current_page' => $result['current_page'],
                    'per_page' => $perPage,
                ],
            ],
        ];
    }

    /**
     * Admin: Get a specific report.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function getReport(Request $request, int $reportId): array
    {
        $admin = $this->requireAdmin($request, false);
        if (is_array($admin)) {
            return $admin;
        }

        $report = $this->reports->getReportById($reportId);
        if ($report === null) {
            return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Report not found.')];
        }

        return ['status' => 200, 'body' => ['data' => ['report' => $report]]];
    }

    /**
     * Admin: Get all reports for a specific post.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function getReportsForPost(Request $request, int $postId): array
    {
        $admin = $this->requireAdmin($request, false);
        if (is_array($admin)) {
            return $admin;
        }

        $reports = $this->reports->getReportsForPost($postId);
        return ['status' => 200, 'body' => ['data' => ['reports' => $reports]]];
    }

    /**
     * Admin: Update report status (under_review, dismissed, action_taken).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function updateReportStatus(Request $request, int $reportId): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }

        $input = $request->input();
        $status = (string) ($input['status'] ?? '');
        $notes = isset($input['notes']) ? (string) $input['notes'] : null;

        if (!in_array($status, PostReportRepository::ALLOWED_STATUSES, true)) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Invalid report status.')];
        }

        $updated = $this->reports->updateReportStatus($reportId, $status, $admin->id, $notes);
        if (!$updated) {
            return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Report not found or not modified.')];
        }

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'success' => true,
                    'message' => 'Report status updated successfully.',
                    'new_status' => $status,
                ],
            ],
        ];
    }

    /**
     * Admin: Unpublish/hide reported post via moderation action.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function unpublishPost(Request $request, int $postId): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }

        $input = $request->input();
        $reason = trim((string) ($input['reason'] ?? 'Content moderation violation'));

        $success = $this->reports->hideOrUnpublishPost($postId, $admin->id, $reason);
        if (!$success) {
            return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Post not found or could not be unpublished.')];
        }

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'success' => true,
                    'message' => 'Post has been unpublished and related reports marked as Action Taken.',
                ],
            ],
        ];
    }

    /**
     * Admin: Restore post to published status.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function restorePost(Request $request, int $postId): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }

        $success = $this->reports->restorePost($postId, $admin->id);
        if (!$success) {
            return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Post not found or could not be restored.')];
        }

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'success' => true,
                    'message' => 'Post has been restored to published status.',
                ],
            ],
        ];
    }

    /**
     * Verify administrator permissions and CSRF if state-changing.
     *
     * @return UserAccount|array{status: int, body: array<string, mixed>}
     */
    private function requireAdmin(Request $request, bool $stateChanging): UserAccount|array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Authentication is required.')];
        }

        if ($account->role !== 'admin') {
            return ['status' => 403, 'body' => ApiErrorResponder::build('FORBIDDEN', 'Administrator access is required.')];
        }

        if ($stateChanging && !$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        return $account;
    }

    /**
     * Validate CSRF token from header, post parameter, or JSON body.
     */
    private function validateCsrf(Request $request): bool
    {
        $token = $request->header('X-CSRF-Token')
            ?? $request->postParameter('csrf_token')
            ?? ($request->input()['csrf_token'] ?? null);

        return is_string($token) && $this->sessions->validateCsrfToken($token);
    }
}
