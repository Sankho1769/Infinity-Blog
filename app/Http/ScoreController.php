<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Scores\ScoreRepository;
use InfinityBlog\Scores\ScoreValidationException;

final class ScoreController
{
    private ScoreRepository $scores;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(ScoreRepository $scores, AuthGuard $guard, SessionManager $sessions)
    {
        $this->scores = $scores;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if (preg_match('#^/posts/([0-9]+)/score$#D', $request->path, $matches) === 1) {
            $postId = (int) $matches[1];
            if ($request->method === 'GET') {
                return $this->getScore($request, $postId);
            }
            if ($request->method === 'POST') {
                return $this->submitScore($request, $postId);
            }
            return $this->methodNotAllowed();
        }

        return $this->error(404, 'NOT_FOUND', 'The requested scoring endpoint was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function getScore(Request $request, int $postId): array
    {
        $summary = $this->scores->getSummary($postId);
        $account = $this->guard->currentAccount();
        $userScore = $account !== null ? $this->scores->getUserScore($postId, $account->id) : null;

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'post_id' => $postId,
                    'average' => $summary['average'],
                    'count' => $summary['count'],
                    'user_score' => $userScore,
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function submitScore(Request $request, int $postId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHORIZED', 'You must be signed in to submit a Reader Score.');
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $input = $request->jsonObject(65536);
        $scoreVal = $input['score'] ?? null;

        if (!is_int($scoreVal)) {
            return $this->error(
                422,
                'VALIDATION_ERROR',
                'Reader score must be an integer between 1 and 5.',
                ['score' => 'Please select a star rating from 1 to 5.']
            );
        }

        try {
            $result = $this->scores->submitScore($postId, $account->id, $scoreVal);
            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'post_id' => $postId,
                        'user_score' => $result['user_score'],
                        'average' => $result['average'],
                        'count' => $result['count'],
                        'message' => 'Your Reader Score has been saved.',
                    ],
                ],
            ];
        } catch (ScoreValidationException $exception) {
            return $this->error(422, 'VALIDATION_ERROR', $exception->getMessage(), $exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function requireCsrf(Request $request): ?array
    {
        $token = $request->header('x-csrf-token') ?? ($request->formField('csrf_token') ?? null);
        if ($token === null || !is_string($token) || !$this->sessions->validateCsrfToken($token)) {
            return $this->error(403, 'CSRF_INVALID', 'The action could not be verified. Please refresh and try again.');
        }
        return null;
    }

    /** @param array<string, string> $fields
     *  @return array{status: int, body: array<string, mixed>}
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
