<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Posts\PostNotFoundException;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Posts\PostValidationException;

final class PostController
{
    private PostRepository $posts;
    private AuthGuard $guard;
    private SessionManager $sessions;

    public function __construct(PostRepository $posts, AuthGuard $guard, SessionManager $sessions)
    {
        $this->posts = $posts;
        $this->guard = $guard;
        $this->sessions = $sessions;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path === '/author/posts') {
            if ($request->method === 'GET') {
                return $this->list($request);
            }
            if ($request->method === 'POST') {
                return $this->create($request);
            }
            return $this->methodNotAllowed();
        }

        if (preg_match('#^/author/posts/([0-9]+)$#D', $request->path, $matches) === 1) {
            if ($request->method === 'GET') {
                return $this->show($request, $matches[1]);
            }
            if ($request->method === 'PATCH') {
                return $this->update($request, $matches[1]);
            }
            if ($request->method === 'DELETE') {
                return $this->delete($request, $matches[1]);
            }
            return $this->methodNotAllowed();
        }

        if (preg_match('#^/author/posts/([0-9]+)/(publish|unpublish)$#D', $request->path, $matches) === 1) {
            if ($request->method !== 'POST') {
                return $this->methodNotAllowed();
            }
            return $this->changePublication($request, $matches[1], $matches[2] === 'publish');
        }

        return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function list(Request $request): array
    {
        $author = $this->requireAuthor();
        if (is_array($author)) {
            return $author;
        }
        [$filters, $page, $perPage, $errors] = $this->validatedQuery($request);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $result = $this->posts->listForAuthor($author->id, $filters, $page, $perPage);

        return [
            'status' => 200,
            'body' => [
                'data' => ['posts' => $result['posts']],
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $result['total'],
                    'total_pages' => (int) ceil($result['total'] / $perPage),
                    'dashboard_counts' => $result['dashboard_counts'],
                ],
            ],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function show(Request $request, string $idText): array
    {
        $reader = $this->requirePostReader();
        if (is_array($reader)) {
            return $reader;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        $post = $reader->role === 'admin'
            ? $this->posts->findForAdmin($postId)
            : $this->posts->findForAuthor($postId, $reader->id);
        if ($post === null) {
            return $this->notFound();
        }

        return ['status' => 200, 'body' => ['data' => ['post' => self::publicPostFields($post)]]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function create(Request $request): array
    {
        $author = $this->requireAuthor();
        if (is_array($author)) {
            return $author;
        }
        $csrf = $this->requireCsrf($request);
        if ($csrf !== null) {
            return $csrf;
        }
        [$fields, $errors] = self::validatedPostFields($request->jsonObject(1048576), true);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        try {
            $post = $this->posts->createDraft($author->id, $fields);
            return ['status' => 201, 'body' => ['data' => ['post' => self::publicPostFields($post)]]];
        } catch (PostValidationException $exception) {
            return $this->validationError($exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function update(Request $request, string $idText): array
    {
        $author = $this->requireAuthor();
        if (is_array($author)) {
            return $author;
        }
        $csrf = $this->requireCsrf($request);
        if ($csrf !== null) {
            return $csrf;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        [$fields, $errors] = self::validatedPostFields($request->jsonObject(1048576), false);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        try {
            $post = $this->posts->updateForAuthor($postId, $author->id, $fields);
            return ['status' => 200, 'body' => ['data' => ['post' => self::publicPostFields($post)]]];
        } catch (PostNotFoundException) {
            return $this->notFound();
        } catch (PostValidationException $exception) {
            return $this->validationError($exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function changePublication(Request $request, string $idText, bool $publish): array
    {
        $author = $this->requireAuthor();
        if (is_array($author)) {
            return $author;
        }
        $csrf = $this->requireCsrf($request);
        if ($csrf !== null) {
            return $csrf;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        try {
            $post = $publish
                ? $this->posts->publishForAuthor($postId, $author->id)
                : $this->posts->unpublishForAuthor($postId, $author->id);
            return ['status' => 200, 'body' => ['data' => ['post' => self::publicPostFields($post)]]];
        } catch (PostNotFoundException) {
            return $this->notFound();
        } catch (PostValidationException $exception) {
            return $this->validationError($exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function delete(Request $request, string $idText): array
    {
        $author = $this->requireAuthor();
        if (is_array($author)) {
            return $author;
        }
        $csrf = $this->requireCsrf($request);
        if ($csrf !== null) {
            return $csrf;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        $input = $request->jsonObject();
        if ($input !== ['confirm' => true]) {
            return $this->validationError(['confirm' => 'Explicit deletion confirmation is required.']);
        }
        try {
            $this->posts->deleteForAuthor($postId, $author->id);
            return ['status' => 200, 'body' => ['data' => ['message' => 'Post deleted.']]];
        } catch (PostNotFoundException) {
            return $this->notFound();
        }
    }

    /** @return UserAccount|array{status: int, body: array<string, mixed>} */
    private function requireAuthor(): UserAccount|array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if ($account->role !== 'author') {
            return $this->error(403, 'FORBIDDEN', 'Author access is required.');
        }
        return $account;
    }

    /** @return UserAccount|array{status: int, body: array<string, mixed>} */
    private function requirePostReader(): UserAccount|array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if (!in_array($account->role, ['author', 'admin'], true)) {
            return $this->error(403, 'FORBIDDEN', 'Post access is not permitted.');
        }
        return $account;
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function requireCsrf(Request $request): ?array
    {
        return $this->sessions->validateCsrfToken($request->header('X-CSRF-Token'))
            ? null
            : $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
    }

    /** @return array{array<string, mixed>, int, int, array<string, string>} */
    private function validatedQuery(Request $request): array
    {
        $allowed = ['page', 'per_page', 'status', 'category_id', 'q'];
        $errors = [];
        $filters = [];
        foreach (array_keys($request->queryParameters()) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors['_query'] = 'Unsupported query parameter.';
            }
        }
        $status = $request->query('status');
        if ($status !== null && (!is_string($status) || !in_array($status, ['draft', 'published'], true))) {
            $errors['status'] = 'Choose draft or published.';
        } elseif (is_string($status)) {
            $filters['status'] = $status;
        }
        $categoryId = $request->query('category_id');
        if ($categoryId !== null) {
            $parsed = self::parseScalarId($categoryId);
            if ($parsed === null) {
                $errors['category_id'] = 'Use a positive integer category ID.';
            } else {
                $filters['category_id'] = $parsed;
            }
        }
        $query = $request->query('q');
        if ($query !== null) {
            if (!is_string($query) || preg_match('//u', $query) !== 1 || strlen($query) > 100) {
                $errors['q'] = 'Search text must be valid UTF-8 and at most 100 bytes.';
            } elseif (trim($query) !== '') {
                $filters['q'] = trim($query);
            }
        }
        $page = self::validatedPage($request->query('page'), 1, 'page', $errors);
        $perPage = self::validatedPage($request->query('per_page'), 10, 'per_page', $errors);
        if ($perPage > 50) {
            $errors['per_page'] = 'Choose between 1 and 50 items per page.';
        }
        return [$filters, $page, min($perPage, 50), $errors];
    }

    /** @param array<string, string> $errors */
    private static function validatedPage(mixed $value, int $default, string $field, array &$errors): int
    {
        if ($value === null) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($parsed === false) {
            $errors[$field] = 'Use a positive integer.';
            return $default;
        }
        return (int) $parsed;
    }

    /** @param array<string, mixed> $input
     *  @return array{array{title: string, content: string, category_id: ?int}, array<string, string>}
     */
    private static function validatedPostFields(array $input, bool $creating): array
    {
        $fields = [];
        $errors = [];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, ['title', 'content', 'category_id', 'status'], true)) {
                $errors[$key === 'author_id' ? 'author_id' : '_request'] = $key === 'author_id'
                    ? 'The author is assigned by the authenticated session.'
                    : 'Unsupported post field.';
            }
        }
        if ($creating && array_key_exists('status', $input) && $input['status'] !== 'draft') {
            $errors['status'] = 'Create posts as drafts; publish them through the publish operation.';
        }
        if (array_key_exists('status', $input) && $input['status'] !== 'draft') {
            $errors['status'] = 'Use the publish or unpublish operation to change status.';
        }
        if ($creating && !array_key_exists('title', $input)) {
            $errors['title'] = 'A post title is required.';
        }
        if ($creating && !array_key_exists('content', $input)) {
            $errors['content'] = 'Article content is required.';
        }
        if (array_key_exists('title', $input)) {
            $title = $input['title'];
            if (!is_string($title) || preg_match('//u', $title) !== 1 || preg_match('/\p{Cc}/u', $title) === 1) {
                $errors['title'] = 'Enter a valid post title.';
            } else {
                $title = trim($title);
                $length = preg_match_all('/./us', $title);
                if ($title === '' || $length === false || $length > 255) {
                    $errors['title'] = 'Titles must contain 1 to 255 characters.';
                } else {
                    $fields['title'] = $title;
                }
            }
        }
        if (array_key_exists('content', $input)) {
            $content = $input['content'];
            if (!is_string($content) || preg_match('//u', $content) !== 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $content) === 1) {
                $errors['content'] = 'Enter valid UTF-8 article content.';
            } elseif (trim($content) === '') {
                $errors['content'] = 'Article content cannot be empty.';
            } elseif (strlen($content) > 524288) {
                $errors['content'] = 'Article content may not exceed 512 KiB.';
            } else {
                $fields['content'] = $content;
            }
        }
        if (array_key_exists('category_id', $input)) {
            $category = $input['category_id'];
            if ($category !== null && (!is_int($category) || $category < 1)) {
                $errors['category_id'] = 'Use a positive integer category ID or null.';
            } else {
                $fields['category_id'] = $category;
            }
        }
        if (!$creating && $fields === [] && $errors === []) {
            $errors['_request'] = 'Provide a title, content, or category_id to update.';
        }
        return [$fields, $errors];
    }

    private static function parseId(string $value): ?int
    {
        return self::parseScalarId($value);
    }

    private static function parseScalarId(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }

    /** @param array<string, mixed> $post
     *  @return array<string, mixed>
     */
    private static function publicPostFields(array $post): array
    {
        unset($post['summary_source_hash']);
        return $post;
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
        return $this->error(404, 'NOT_FOUND', 'The requested post was not found.');
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
