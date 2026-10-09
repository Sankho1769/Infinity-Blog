<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Audit\AuditLogRepository;
use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\UserAccount;

final class AdminAuditLogController
{
    private AuditLogRepository $logs;
    private AuthGuard $guard;

    public function __construct(AuditLogRepository $logs, AuthGuard $guard)
    {
        $this->logs = $logs;
        $this->guard = $guard;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path !== '/admin/audit-logs') {
            return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
        }
        if ($request->method !== 'GET') {
            return $this->error(405, 'METHOD_NOT_ALLOWED', 'The request method is not supported.');
        }
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if ($account->role !== 'admin') {
            return $this->error(403, 'FORBIDDEN', 'Administrator access is required.');
        }

        [$filters, $page, $perPage, $errors] = self::validatedQuery($request);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'One or more query parameters are invalid.', $errors),
            ];
        }
        $result = $this->logs->listEvents($filters, $page, $perPage);
        return [
            'status' => 200,
            'body' => [
                'data' => ['events' => $result['events']],
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $result['total'],
                    'total_pages' => (int) ceil($result['total'] / $perPage),
                ],
            ],
        ];
    }

    /** @return array{array<string, mixed>, int, int, array<string, string>} */
    private static function validatedQuery(Request $request): array
    {
        $allowed = ['actor_id', 'action', 'entity_type', 'from', 'to', 'page', 'per_page'];
        $errors = [];
        $filters = [];
        foreach (array_keys($request->queryParameters()) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors['_query'] = 'Unsupported query parameter.';
            }
        }

        $actorId = $request->query('actor_id');
        if ($actorId !== null) {
            $parsed = is_string($actorId) || is_int($actorId)
                ? filter_var($actorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($parsed === false) {
                $errors['actor_id'] = 'Use a positive integer account ID.';
            } else {
                $filters['actor_id'] = (int) $parsed;
            }
        }

        foreach (['action' => 80, 'entity_type' => 50] as $field => $maxLength) {
            $value = $request->query($field);
            if ($value !== null) {
                $pattern = $field === 'action' ? '/^[a-z][a-z0-9_.-]*$/' : '/^[a-z][a-z0-9_]*$/';
                if (!is_string($value) || strlen($value) > $maxLength || preg_match($pattern, $value) !== 1) {
                    $errors[$field] = 'Enter a valid ' . str_replace('_', ' ', $field) . ' filter.';
                } else {
                    $filters[$field] = $value;
                }
            }
        }

        foreach (['from', 'to'] as $field) {
            $date = $request->query($field);
            if ($date !== null) {
                if (!is_string($date) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1
                    || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                    $errors[$field] = 'Use a valid calendar date in YYYY-MM-DD format.';
                } else {
                    $filters[$field] = $date;
                }
            }
        }
        if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
            $errors['from'] = 'The start date must not be later than the end date.';
        }

        $page = self::positiveInteger($request->query('page'), 1, 'page', $errors);
        $perPage = self::positiveInteger($request->query('per_page'), 20, 'per_page', $errors);
        if ($perPage > 50) {
            $errors['per_page'] = 'Choose between 1 and 50 items per page.';
        }
        return [$filters, $page, min(50, $perPage), $errors];
    }

    /** @param array<string, string> $errors */
    private static function positiveInteger(mixed $value, int $default, string $field, array &$errors): int
    {
        if ($value === null) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($parsed === false) {
            $errors[$field] = 'Use a positive integer no greater than 100000.';
            return $default;
        }
        return (int) $parsed;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ApiErrorResponder::build($code, $message)];
    }
}
