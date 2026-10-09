<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Categories\CategoryConflictException;
use InfinityBlog\Categories\CategoryNotFoundException;
use InfinityBlog\Categories\CategoryRepository;

final class CategoryController
{
    private CategoryRepository $categories;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(CategoryRepository $categories, AuthGuard $guard, SessionManager $sessions)
    {
        $this->categories = $categories;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path === '/categories') {
            if ($request->method !== 'GET') {
                return $this->methodNotAllowed();
            }

            $categories = $this->categories->listActive();
            return [
                'status' => 200,
                'body' => ['data' => ['categories' => $categories], 'meta' => ['total' => count($categories)]],
            ];
        }

        if ($request->path === '/admin/categories') {
            if ($request->method === 'GET') {
                $access = $this->requireAdmin($request, false);
                if (is_array($access)) {
                    return $access;
                }
                $categories = $this->categories->listAdmin();
                return [
                    'status' => 200,
                    'body' => ['data' => ['categories' => $categories], 'meta' => ['total' => count($categories)]],
                ];
            }
            if ($request->method === 'POST') {
                return $this->create($request);
            }

            return $this->methodNotAllowed();
        }

        if (preg_match('#^/admin/categories/([0-9]+)$#D', $request->path, $matches) === 1) {
            if ($request->method === 'PATCH') {
                return $this->update($request, $matches[1]);
            }
            if ($request->method === 'DELETE') {
                return $this->delete($request, $matches[1]);
            }

            return $this->methodNotAllowed();
        }

        if (preg_match('#^/admin/categories/([0-9]+)/(activate|deactivate)$#D', $request->path, $matches) === 1) {
            if ($request->method !== 'POST') {
                return $this->methodNotAllowed();
            }
            return $this->setActive($request, $matches[1], $matches[2] === 'activate');
        }

        return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function create(Request $request): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }

        [$fields, $errors] = $this->validatedFields($request->jsonObject(), true);
        if ($errors !== []) {
            return $this->validationError($errors);
        }

        try {
            $category = $this->categories->create($fields['name'], $fields['description'] ?? null, $access->id);
            return ['status' => 201, 'body' => ['data' => ['category' => $category]]];
        } catch (CategoryConflictException $exception) {
            return $this->conflictError($exception);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function update(Request $request, string $idText): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }
        $categoryId = self::parseId($idText);
        if ($categoryId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The category ID is invalid.', ['id' => 'Use a positive integer category ID.']);
        }

        [$fields, $errors] = $this->validatedFields($request->jsonObject(), false);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $current = $this->categories->findAdminCategory($categoryId);
        if ($current === null) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        }
        $name = $fields['name'] ?? $current['name'];
        $description = array_key_exists('description', $fields) ? $fields['description'] : $current['description'];

        try {
            $category = $this->categories->update($categoryId, $name, $description, $access->id);
            return ['status' => 200, 'body' => ['data' => ['category' => $category]]];
        } catch (CategoryNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        } catch (CategoryConflictException $exception) {
            return $this->conflictError($exception);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function setActive(Request $request, string $idText, bool $active): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }
        $categoryId = self::parseId($idText);
        if ($categoryId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The category ID is invalid.', ['id' => 'Use a positive integer category ID.']);
        }

        try {
            $category = $this->categories->setActive($categoryId, $active, $access->id);
            return ['status' => 200, 'body' => ['data' => ['category' => $category]]];
        } catch (CategoryNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function delete(Request $request, string $idText): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }
        $categoryId = self::parseId($idText);
        if ($categoryId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The category ID is invalid.', ['id' => 'Use a positive integer category ID.']);
        }

        try {
            $this->categories->delete($categoryId, $access->id);
            return ['status' => 200, 'body' => ['data' => ['message' => 'Category deleted.']]];
        } catch (CategoryNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        } catch (CategoryConflictException $exception) {
            return $this->conflictError($exception);
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
     *  @return array{array<string, mixed>, array<string, string>}
     */
    private function validatedFields(array $input, bool $creating): array
    {
        $fields = [];
        $errors = [];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, ['name', 'description'], true)) {
                $errors[$key === 'slug' ? 'slug' : '_request'] = $key === 'slug'
                    ? 'The slug is generated by the server.'
                    : 'Only name and description are supported.';
            }
        }

        if ($creating && !array_key_exists('name', $input)) {
            $errors['name'] = 'A category name is required.';
        } elseif (array_key_exists('name', $input)) {
            if (!is_string($input['name']) || preg_match('//u', $input['name']) !== 1
                || preg_match('/\p{Cc}/u', $input['name']) === 1) {
                $errors['name'] = 'Enter a valid category name.';
            } else {
                $name = preg_replace('/\s+/u', ' ', trim($input['name']));
                $length = is_string($name) ? self::characterLength($name) : null;
                if (!is_string($name) || $name === '' || $length === null || $length > 120) {
                    $errors['name'] = 'Category names must contain 1 to 120 characters.';
                } else {
                    $fields['name'] = $name;
                }
            }
        }

        if (array_key_exists('description', $input)) {
            $description = $input['description'];
            if ($description !== null && (!is_string($description) || preg_match('//u', $description) !== 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $description) === 1)) {
                $errors['description'] = 'Enter a valid category description or null.';
            } elseif (is_string($description)) {
                $description = trim($description);
                $length = self::characterLength($description);
                if ($length === null || $length > 500) {
                    $errors['description'] = 'Descriptions may contain at most 500 characters.';
                } else {
                    $fields['description'] = $description === '' ? null : $description;
                }
            } else {
                $fields['description'] = null;
            }
        }

        if (!$creating && $fields === [] && $errors === []) {
            $errors['_request'] = 'Provide a name or description to update.';
        }

        return [$fields, $errors];
    }

    private static function characterLength(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);

        return $length === false ? null : $length;
    }

    private static function parseId(string $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    /** @param array<string, string> $errors
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function validationError(array $errors): array
    {
        return $this->error(422, 'VALIDATION_ERROR', 'One or more fields need attention.', $errors);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function conflictError(CategoryConflictException $exception): array
    {
        if ($exception->reason === CategoryConflictException::NAME_EXISTS) {
            return $this->error(409, 'CATEGORY_NAME_EXISTS', 'A category with that name already exists.');
        }
        if ($exception->reason === CategoryConflictException::IN_USE) {
            return $this->error(409, 'CATEGORY_IN_USE', 'A category assigned to posts cannot be deleted.');
        }

        return $this->error(409, 'CATEGORY_SLUG_UNAVAILABLE', 'A unique category slug could not be generated.');
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
