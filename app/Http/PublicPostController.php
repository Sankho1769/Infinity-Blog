<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Posts\PostRepository;

final class PublicPostController
{
    private PostRepository $posts;

    public function __construct(PostRepository $posts)
    {
        $this->posts = $posts;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path === '/posts') {
            if ($request->method !== 'GET') {
                return $this->methodNotAllowed();
            }
            return $this->list($request);
        }
        if (preg_match('#^/posts/([0-9]+)$#D', $request->path, $matches) === 1) {
            if ($request->method !== 'GET') {
                return $this->methodNotAllowed();
            }
            return $this->show($matches[1]);
        }
        return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function list(Request $request): array
    {
        [$filters, $page, $perPage, $errors] = self::validatedQuery($request);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        if (isset($filters['category_id']) && !$this->posts->isActiveCategory($filters['category_id'])) {
            return $this->validationError(['category_id' => 'Choose an active category.']);
        }
        $result = $this->posts->listPublic($filters, $page, $perPage);
        return [
            'status' => 200,
            'body' => [
                'data' => ['posts' => $result['posts']],
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
    private function show(string $idText): array
    {
        $id = filter_var($idText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return $this->error(404, 'NOT_FOUND', 'The requested post was not found.');
        }
        $post = $this->posts->findPublished((int) $id);
        if ($post === null) {
            return $this->error(404, 'NOT_FOUND', 'The requested post was not found.');
        }
        $summaryIsCurrent = PostRepository::isSummaryCurrent($post);
        $body = [
            'id' => $post['id'],
            'title' => $post['title'],
            'content' => $post['content'],
            'category_id' => $post['category_id'],
            'category_name' => $post['category_name'],
            'author_name' => $post['author_name'],
            'summary' => $summaryIsCurrent ? $post['summary'] : null,
            'summary_generated_at' => $summaryIsCurrent ? $post['summary_generated_at'] : null,
            'published_at' => $post['published_at'],
            'created_at' => $post['created_at'],
        ];
        return ['status' => 200, 'body' => ['data' => ['post' => $body]]];
    }

    /** @return array{array<string, mixed>, int, int, array<string, string>} */
    private static function validatedQuery(Request $request): array
    {
        $allowed = ['page', 'per_page', 'category_id', 'q'];
        $filters = [];
        $errors = [];
        foreach (array_keys($request->queryParameters()) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors['_query'] = 'Unsupported query parameter.';
            }
        }
        $category = $request->query('category_id');
        if ($category !== null) {
            if (!is_string($category) && !is_int($category)) {
                $errors['category_id'] = 'Use a positive integer category ID.';
            } else {
                $id = filter_var($category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    $errors['category_id'] = 'Use a positive integer category ID.';
                } else {
                    $filters['category_id'] = (int) $id;
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

    /** @param array<string, string> $fields
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function validationError(array $fields): array
    {
        return $this->error(422, 'VALIDATION_ERROR', 'One or more query parameters are invalid.', $fields);
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
