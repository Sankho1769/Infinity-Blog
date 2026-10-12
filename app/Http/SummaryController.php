<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Posts\PostNotFoundException;
use InfinityBlog\Summaries\SummaryInputTooLargeException;
use InfinityBlog\Summaries\SummaryRateLimitException;
use InfinityBlog\Summaries\SummaryService;
use InfinityBlog\Summaries\SummarySourceChangedException;
use InfinityBlog\Summaries\SummaryUnavailableException;

final class SummaryController
{
    private SummaryService $summaries;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(SummaryService $summaries, AuthGuard $guard, SessionManager $sessions)
    {
        $this->summaries = $summaries;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if (preg_match('#^/(author|admin)/posts/([0-9]+)/summary$#D', $request->path, $matches) !== 1) {
            return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
        }
        if ($request->method !== 'POST') {
            return $this->error(405, 'METHOD_NOT_ALLOWED', 'The request method is not supported.');
        }
        $actor = $this->guard->currentAccount();
        if ($actor === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if ($matches[1] === 'admin') {
            if ($actor->role !== 'admin') {
                return $this->error(403, 'FORBIDDEN', 'The requested operation is not permitted.');
            }
        } else {
            if (!in_array($actor->role, ['user', 'author'], true)) {
                return $this->error(403, 'FORBIDDEN', 'The requested operation is not permitted.');
            }
        }
        if (!$this->sessions->validateCsrfToken($request->header('X-CSRF-Token'))) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }
        $postId = filter_var($matches[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($postId === false) {
            return $this->validationError(['id' => 'Use a positive integer post ID.']);
        }
        if ($request->jsonObject(1024) !== []) {
            return $this->validationError(['_request' => 'The server loads article text; no request fields are accepted.']);
        }

        try {
            $summary = $this->summaries->generate((int) $postId, $actor, $request->sourceAddress);
            return ['status' => 200, 'body' => ['data' => $summary]];
        } catch (PostNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested post was not found.');
        } catch (SummaryInputTooLargeException) {
            return $this->validationError(['content' => 'This article exceeds the configured summary input limit.']);
        } catch (SummaryRateLimitException) {
            return $this->error(429, 'RATE_LIMITED', 'Summary requests are temporarily limited.');
        } catch (SummarySourceChangedException) {
            return $this->error(409, 'SUMMARY_SOURCE_CHANGED', 'The article changed during summary generation. Please retry.');
        } catch (SummaryUnavailableException) {
            return $this->error(503, 'SUMMARY_UNAVAILABLE', 'Summary generation is temporarily unavailable.');
        }
    }

    /** @param array<string, string> $fields
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function validationError(array $fields): array
    {
        return [
            'status' => 422,
            'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'One or more fields need attention.', $fields),
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ApiErrorResponder::build($code, $message)];
    }
}
