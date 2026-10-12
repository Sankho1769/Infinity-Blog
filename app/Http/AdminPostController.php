<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Posts\PostNotFoundException;
use InfinityBlog\Posts\PostInputValidator;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Posts\PostValidationException;

final class AdminPostController
{
    private PostRepository $posts;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private ?\InfinityBlog\Storage\MediaService $media;

    public function __construct(PostRepository $posts, AuthGuard $guard, SessionManager $sessions, ?\InfinityBlog\Storage\MediaService $media = null)
    {
        $this->posts = $posts;
        $this->guard = $guard;
        $this->sessions = $sessions;
        if ($media === null) {
            try {
                $storageConfig = \InfinityBlog\Storage\SupabaseStorageConfig::fromEnvironment();
                if ($storageConfig !== null) {
                    $client = new \InfinityBlog\Storage\SupabaseStorageClient($storageConfig);
                    $media = new \InfinityBlog\Storage\MediaService($client, $storageConfig);
                }
            } catch (\Throwable) {
            }
        }
        $this->media = $media;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path === '/admin/posts') {
            if ($request->method === 'GET') {
                return $this->list($request);
            }
            if ($request->method === 'POST') {
                return $this->create($request);
            }
            return $this->methodNotAllowed();
        }
        if (preg_match('#^/admin/posts/([0-9]+)$#D', $request->path, $matches) === 1) {
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
        if (preg_match('#^/admin/posts/([0-9]+)/(publish|unpublish)$#D', $request->path, $matches) === 1) {
            if ($request->method !== 'POST') {
                return $this->methodNotAllowed();
            }
            return $this->changePublication($request, $matches[1], $matches[2] === 'publish');
        }
        return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function create(Request $request): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        [$fields, $errors] = PostInputValidator::validate($request->jsonObject(1048576), true);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        try {
            $post = $this->posts->createDraft($admin->id, $fields);
            unset($post['summary_source_hash']);
            return ['status' => 201, 'body' => ['data' => ['post' => $post]]];
        } catch (PostValidationException $exception) {
            return $this->validationError($exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function list(Request $request): array
    {
        $admin = $this->requireAdmin($request, false);
        if (is_array($admin)) {
            return $admin;
        }
        [$filters, $page, $perPage, $errors] = self::validatedQuery($request);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $result = $this->posts->listForAdmin($filters, $page, $perPage);
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
        $admin = $this->requireAdmin($request, false);
        if (is_array($admin)) {
            return $admin;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        $post = $this->posts->findForAdmin($postId);
        if ($post === null) {
            return $this->notFound();
        }
        unset($post['summary_source_hash']);
        return ['status' => 200, 'body' => ['data' => ['post' => $post]]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function update(Request $request, string $idText): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        [$fields, $errors] = PostInputValidator::validate($request->jsonObject(1048576), false);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        try {
            $post = $this->posts->updateForAdmin($postId, $admin->id, $fields);
            unset($post['summary_source_hash']);
            return ['status' => 200, 'body' => ['data' => ['post' => $post]]];
        } catch (PostNotFoundException) {
            return $this->notFound();
        } catch (PostValidationException $exception) {
            return $this->validationError($exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function changePublication(Request $request, string $idText, bool $publish): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        try {
            $post = $publish
                ? $this->posts->publishForAdmin($postId, $admin->id)
                : $this->posts->unpublishForAdmin($postId, $admin->id);
            unset($post['summary_source_hash']);
            return ['status' => 200, 'body' => ['data' => ['post' => $post]]];
        } catch (PostNotFoundException) {
            return $this->notFound();
        } catch (PostValidationException $exception) {
            return $this->validationError($exception->fields);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function delete(Request $request, string $idText): array
    {
        $admin = $this->requireAdmin($request, true);
        if (is_array($admin)) {
            return $admin;
        }
        $postId = self::parseId($idText);
        if ($postId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The post ID is invalid.', ['id' => 'Use a positive integer post ID.']);
        }
        if ($request->jsonObject() !== ['confirm' => true]) {
            return $this->validationError(['confirm' => 'Explicit deletion confirmation is required.']);
        }
        try {
            $deleted = $this->posts->deleteForAdmin($postId, $admin->id);
            if ($this->media !== null && is_array($deleted)) {
                if (!empty($deleted['cover_image_path'])) {
                    $this->media->safeDelete($this->media->getConfig()->bucketArticles, $deleted['cover_image_path']);
                }
                if (!empty($deleted['video_path'])) {
                    $this->media->safeDelete($this->media->getConfig()->bucketVideos, $deleted['video_path']);
                }
            }
            return ['status' => 200, 'body' => ['data' => ['message' => 'Post deleted.']]];
        } catch (PostNotFoundException) {
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

    /** @return array{array<string, mixed>, int, int, array<string, string>} */
    private static function validatedQuery(Request $request): array
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
            if (!is_string($categoryId) && !is_int($categoryId)) {
                $errors['category_id'] = 'Use a positive integer category ID.';
            } else {
                $parsed = filter_var($categoryId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsed === false) {
                    $errors['category_id'] = 'Use a positive integer category ID.';
                } else {
                    $filters['category_id'] = (int) $parsed;
                }
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
        $page = self::page($request->query('page'), 1, 'page', $errors);
        $perPage = self::page($request->query('per_page'), 10, 'per_page', $errors);
        if ($perPage > 50) {
            $errors['per_page'] = 'Choose between 1 and 50 items per page.';
        }
        return [$filters, $page, min(50, $perPage), $errors];
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
