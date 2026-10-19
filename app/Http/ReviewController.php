<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Reviews\ReviewRepository;
use InfinityBlog\Reviews\ReviewValidationException;
use Throwable;

final class ReviewController
{
    private ReviewRepository $reviews;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(ReviewRepository $reviews, AuthGuard $guard, SessionManager $sessions)
    {
        $this->reviews = $reviews;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if (preg_match('#^/api/posts/([0-9]+)/reviews$#D', $request->path, $matches) === 1) {
            $postId = (int) $matches[1];
            if ($request->method === 'GET') {
                return $this->getReviews($request, $postId);
            }
            if ($request->method === 'POST') {
                return $this->submitReview($request, $postId);
            }
            return $this->methodNotAllowed();
        }

        if (preg_match('#^/api/reviews/([0-9]+)$#D', $request->path, $matches) === 1) {
            $reviewId = (int) $matches[1];
            if ($request->method === 'DELETE' || ($request->method === 'POST' && $request->postParameter('_method') === 'DELETE')) {
                return $this->deleteReview($request, $reviewId);
            }
            return $this->methodNotAllowed();
        }

        if (preg_match('#^/api/reviews/([0-9]+)/report$#D', $request->path, $matches) === 1) {
            $reviewId = (int) $matches[1];
            if ($request->method === 'POST') {
                return $this->reportReview($request, $reviewId);
            }
            return $this->methodNotAllowed();
        }

        if ($request->path === '/api/reviews/reports') {
            if ($request->method === 'GET') {
                return $this->listReports($request);
            }
            return $this->methodNotAllowed();
        }

        if (preg_match('#^/api/reviews/reports/([0-9]+)/status$#D', $request->path, $matches) === 1) {
            $reportId = (int) $matches[1];
            if ($request->method === 'POST') {
                return $this->updateReportStatus($request, $reportId);
            }
            return $this->methodNotAllowed();
        }

        return $this->error(404, 'NOT_FOUND', 'The requested review endpoint was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function getReviews(Request $request, int $postId): array
    {
        $page = max(1, (int) ($request->query('page') ?? 1));
        $perPage = max(1, min(50, (int) ($request->query('per_page') ?? 10)));
        $account = $this->guard->currentAccount();

        $result = $this->reviews->getReviewsForPost($postId, $account?->id, $page, $perPage);
        $userReview = $account !== null ? $this->reviews->getUserReview($postId, $account->id) : null;

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'post_id' => $postId,
                    'reviews' => $result['reviews'],
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                    'user_review' => $userReview,
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function submitReview(Request $request, int $postId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHORIZED', 'You must be signed in to submit a review.');
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $input = $request->jsonObject(65536);
        $rating = $input['rating'] ?? null;
        $content = $input['content'] ?? null;

        if (!is_int($rating)) {
            return $this->error(
                422,
                'VALIDATION_ERROR',
                'Rating must be an integer between 1 and 5 stars.',
                ['rating' => 'Please select a valid star rating (1 to 5).']
            );
        }

        if (!is_string($content) || trim($content) === '') {
            return $this->error(
                422,
                'VALIDATION_ERROR',
                'Review content cannot be empty.',
                ['content' => 'Please share your evaluation in the review text.']
            );
        }

        try {
            $result = $this->reviews->submitReview($postId, $account->id, $rating, $content);

            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'post_id' => $postId,
                        'review' => $result['review'],
                        'score_summary' => $result['score_summary'],
                        'message' => 'Your review has been submitted.',
                    ],
                ],
            ];
        } catch (ReviewValidationException $exception) {
            return $this->error(422, 'VALIDATION_ERROR', $exception->getMessage(), $exception->fieldErrors());
        } catch (Throwable $e) {
            return $this->error(500, 'SERVER_ERROR', 'Failed to save review.');
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function deleteReview(Request $request, int $reviewId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHORIZED', 'You must be signed in to delete a review.');
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        try {
            $isAdmin = ($account->role === 'admin');
            $deleted = $this->reviews->deleteReview($reviewId, $account->id, $isAdmin);

            if (!$deleted) {
                return $this->error(404, 'NOT_FOUND', 'Review not found or already deleted.');
            }

            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'review_id' => $reviewId,
                        'deleted' => true,
                        'message' => 'Review successfully removed.',
                    ],
                ],
            ];
        } catch (ReviewValidationException $e) {
            return $this->error(403, 'FORBIDDEN', $e->getMessage());
        } catch (Throwable $e) {
            return $this->error(500, 'SERVER_ERROR', 'Failed to delete review.');
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function reportReview(Request $request, int $reviewId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHORIZED', 'You must be signed in to report a review.');
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $input = $request->jsonObject(65536);
        $reason = $input['reason'] ?? '';
        $explanation = $input['explanation'] ?? null;

        if (!is_string($reason) || trim($reason) === '') {
            return $this->error(
                422,
                'VALIDATION_ERROR',
                'Please select a reason for reporting.',
                ['reason' => 'Please select a reason for reporting.']
            );
        }

        try {
            $result = $this->reviews->reportReview(
                $reviewId,
                $account->id,
                $reason,
                is_string($explanation) ? $explanation : null
            );

            if ($result['status'] === 'not_found') {
                return $this->error(404, 'NOT_FOUND', $result['message']);
            }
            if ($result['status'] === 'forbidden') {
                return $this->error(403, 'FORBIDDEN', $result['message']);
            }
            if ($result['status'] === 'duplicate') {
                return $this->error(409, 'DUPLICATE_REPORT', $result['message']);
            }
            if ($result['status'] !== 'success') {
                return $this->error(422, 'VALIDATION_ERROR', $result['message']);
            }

            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'review_id' => $reviewId,
                        'message' => $result['message'],
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return $this->error(500, 'SERVER_ERROR', 'Failed to submit report.');
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function listReports(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->error(403, 'FORBIDDEN', 'Administrator access required.');
        }

        $page = max(1, (int) ($request->query('page') ?? 1));
        $perPage = max(1, min(100, (int) ($request->query('per_page') ?? 20)));
        $status = $request->query('status');
        $statusStr = is_string($status) ? trim($status) : null;

        $result = $this->reviews->listReports($page, $perPage, $statusStr);

        return [
            'status' => 200,
            'body' => [
                'data' => $result,
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function updateReportStatus(Request $request, int $reportId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null || $account->role !== 'admin') {
            return $this->error(403, 'FORBIDDEN', 'Administrator access required.');
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $input = $request->jsonObject(16384);
        $status = $input['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['pending', 'under_review', 'dismissed', 'action_taken'], true)) {
            return $this->error(422, 'VALIDATION_ERROR', 'Invalid report status.');
        }

        $updated = $this->reviews->updateReportStatus($reportId, $status);
        if (!$updated) {
            return $this->error(404, 'NOT_FOUND', 'Report not found.');
        }

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'report_id' => $reportId,
                    'status' => $status,
                    'message' => 'Report status updated.',
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function requireCsrf(Request $request): ?array
    {
        $token = $request->header('x-csrf-token') ?? ($request->postParameter('csrf_token') ?? null);
        if ($token === null || !is_string($token) || !$this->sessions->validateCsrfToken($token)) {
            return $this->error(403, 'CSRF_INVALID', 'The action could not be verified. Please refresh and try again.');
        }
        return null;
    }

    /**
     * @param array<string, string> $fields
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        $payload = [
            'code' => $code,
            'message' => $message,
        ];
        if ($fields !== []) {
            $payload['fields'] = $fields;
        }
        return [
            'status' => $status,
            'body' => [
                'error' => $payload,
                'request_id' => bin2hex(random_bytes(16)),
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function methodNotAllowed(): array
    {
        return $this->error(405, 'METHOD_NOT_ALLOWED', 'The requested HTTP method is not allowed.');
    }
}
