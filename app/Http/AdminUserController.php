<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Users\AccountConflictException;
use InfinityBlog\Users\AccountNotFoundException;
use InfinityBlog\Users\AdminUserRepository;

final class AdminUserController
{
    private AdminUserRepository $users;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(AdminUserRepository $users, AuthGuard $guard, SessionManager $sessions)
    {
        $this->users = $users;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path === '/admin/users') {
            if ($request->method === 'GET') {
                return $this->list($request);
            }
            if ($request->method === 'POST') {
                return $this->create($request);
            }
            return $this->methodNotAllowed();
        }
        if (preg_match('#^/admin/users/([0-9]+)$#D', $request->path, $matches) === 1) {
            if ($request->method === 'PATCH') {
                return $this->update($request, $matches[1]);
            }
            return $this->methodNotAllowed();
        }
        if (preg_match('#^/admin/users/([0-9]+)/(activate|deactivate)$#D', $request->path, $matches) === 1) {
            if ($request->method !== 'POST') {
                return $this->methodNotAllowed();
            }
            return $this->setActive($request, $matches[1], $matches[2] === 'activate');
        }
        return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function list(Request $request): array
    {
        $access = $this->requireAdmin($request, false);
        if (is_array($access)) {
            return $access;
        }
        $errors = [];
        foreach (array_keys($request->queryParameters()) as $key) {
            if (!in_array($key, ['page', 'per_page'], true)) {
                $errors['_query'] = 'Only page and per_page are supported.';
            }
        }
        $page = self::page($request->query('page'), 1, 'page', $errors);
        $perPage = self::page($request->query('per_page'), 10, 'per_page', $errors);
        if ($perPage > 50) {
            $errors['per_page'] = 'Choose between 1 and 50 items per page.';
        }
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $perPage = min(50, $perPage);
        $result = $this->users->listAuthors($page, $perPage);
        return [
            'status' => 200,
            'body' => [
                'data' => ['users' => $result['users']],
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $result['total'],
                    'total_pages' => (int) ceil($result['total'] / $perPage),
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function create(Request $request): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        [$fields, $errors] = self::validatedFields($request->jsonObject(), true);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $hash = password_hash($fields['password'], PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            return $this->error(500, 'INTERNAL_ERROR', 'The request could not be completed.');
        }
        try {
            $user = $this->users->createAuthor($fields['username'], $fields['display_name'], $hash, $admin->id);
            return ['status' => 201, 'body' => ['data' => ['user' => $user]]];
        } catch (AccountConflictException) {
            return $this->error(409, 'USERNAME_EXISTS', 'An account with that username already exists.');
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function update(Request $request, string $idText): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        $userId = self::parseId($idText);
        if ($userId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The account ID is invalid.', ['id' => 'Use a positive integer account ID.']);
        }
        [$fields, $errors] = self::validatedFields($request->jsonObject(), false);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $changes = [];
        if (array_key_exists('display_name', $fields)) {
            $changes['display_name'] = $fields['display_name'];
        }
        if (array_key_exists('password', $fields)) {
            $hash = password_hash($fields['password'], PASSWORD_DEFAULT);
            if (!is_string($hash)) {
                return $this->error(500, 'INTERNAL_ERROR', 'The request could not be completed.');
            }
            $changes['password_hash'] = $hash;
        }
        try {
            $user = $this->users->updateAuthor($userId, $changes, $admin->id);
            return ['status' => 200, 'body' => ['data' => ['user' => $user]]];
        } catch (AccountNotFoundException) {
            return $this->notFound();
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function setActive(Request $request, string $idText, bool $active): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        $userId = self::parseId($idText);
        if ($userId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The account ID is invalid.', ['id' => 'Use a positive integer account ID.']);
        }
        try {
            $user = $this->users->setActive($userId, $active, $admin->id);
            return ['status' => 200, 'body' => ['data' => ['user' => $user]]];
        } catch (AccountNotFoundException) {
            return $this->notFound();
        }
    }

    /** @return UserAccount|array{status: int, body: array<string, mixed>} */
    private function requireAdmin(Request $request, bool $stateChanging): UserAccount|array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if ($account->role !== 'admin') {
            return $this->error(403, 'FORBIDDEN', 'Administrator access is required.');
        }
        if ($stateChanging && !$this->sessions->validateCsrfToken($request->header('X-CSRF-Token'))) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }
        return $account;
    }

    /** @param array<string, mixed> $input
     *  @return array{array<string, string>, array<string, string>}
     */
    private static function validatedFields(array $input, bool $creating): array
    {
        $allowed = $creating ? ['username', 'display_name', 'password'] : ['display_name', 'password'];
        $fields = [];
        $errors = [];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[$key === 'role' ? 'role' : '_request'] = $key === 'role'
                    ? 'Role assignment is not allowed through this operation.'
                    : 'Unsupported account field.';
            }
        }
        if ($creating && !array_key_exists('username', $input)) {
            $errors['username'] = 'A username is required.';
        }
        if ($creating && !array_key_exists('display_name', $input)) {
            $errors['display_name'] = 'A display name is required.';
        }
        if ($creating && !array_key_exists('password', $input)) {
            $errors['password'] = 'A password is required.';
        }
        if (array_key_exists('username', $input)) {
            $username = $input['username'];
            if (!is_string($username) || preg_match('//u', $username) !== 1
                || preg_match('/\p{Cc}/u', $username) === 1) {
                $errors['username'] = 'Enter a valid username.';
            } else {
                $username = preg_replace('/\s+/u', ' ', trim($username));
                if (!is_string($username) || strlen($username) < 3 || strlen($username) > 190) {
                    $errors['username'] = 'Usernames must contain 3 to 190 bytes.';
                } else {
                    $fields['username'] = UserRepository::normalizeIdentifier($username);
                }
            }
        }
        if (array_key_exists('display_name', $input)) {
            $displayName = $input['display_name'];
            if (!is_string($displayName) || preg_match('//u', $displayName) !== 1
                || preg_match('/\p{Cc}/u', $displayName) === 1) {
                $errors['display_name'] = 'Enter a valid display name.';
            } else {
                $displayName = trim($displayName);
                $length = preg_match_all('/./us', $displayName);
                if ($displayName === '' || $length === false || $length > 190) {
                    $errors['display_name'] = 'Display names must contain 1 to 190 characters.';
                } else {
                    $fields['display_name'] = $displayName;
                }
            }
        }
        if (array_key_exists('password', $input)) {
            $password = $input['password'];
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
                $errors['password'] = 'Passwords must be between 12 and 72 bytes.';
            } else {
                $fields['password'] = $password;
            }
        }
        if (!$creating && $fields === [] && $errors === []) {
            $errors['_request'] = 'Provide a display name or replacement password.';
        }
        return [$fields, $errors];
    }

    /** @param array<string, string> $errors */
    private static function page(mixed $value, int $default, string $field, array &$errors): int
    {
        if ($value === null) {
            return $default;
        }
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($page === false) {
            $errors[$field] = 'Use a positive integer.';
            return $default;
        }
        return (int) $page;
    }

    private static function parseId(string $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }

    /** @param array<string, string> $fields
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function validationError(array $fields): array
    {
        return $this->error(422, 'VALIDATION_ERROR', 'One or more fields need attention.', $fields);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function notFound(): array
    {
        return $this->error(404, 'NOT_FOUND', 'The requested author account was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function methodNotAllowed(): array
    {
        return $this->error(405, 'METHOD_NOT_ALLOWED', 'The request method is not supported.');
    }

    /** @param array<string, string> $fields
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return ['status' => $status, 'body' => ApiErrorResponder::build($code, $message, $fields)];
    }
}
