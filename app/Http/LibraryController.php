<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Library\LibraryRepository;
use InfinityBlog\Posts\PostRepository;
use InvalidArgumentException;

final class LibraryController
{
    private LibraryRepository $library;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private PostRepository $posts;

    public function __construct(
        LibraryRepository $library,
        AuthGuard $guard,
        SessionManager $sessions,
        PostRepository $posts
    ) {
        $this->library = $library;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->posts = $posts;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(Request $request): array
    {
        $path = $request->path;
        $method = $request->method;

        // Toggle bookmark: POST /api/library/bookmark/{id}
        if ($method === 'POST' && preg_match('#^/api/library/bookmark/([0-9]+)$#D', $path, $m)) {
            return $this->toggleBookmark($request, (int) $m[1]);
        }

        // Bookmark and reading status for post: GET /api/library/status/{id}
        if ($method === 'GET' && preg_match('#^/api/library/status/([0-9]+)$#D', $path, $m)) {
            return $this->getArticleLibraryStatus((int) $m[1]);
        }

        // Save reading progress: POST /api/library/progress/{id}
        if ($method === 'POST' && preg_match('#^/api/library/progress/([0-9]+)$#D', $path, $m)) {
            return $this->saveProgress($request, (int) $m[1]);
        }

        // Collections list: GET /api/library/collections
        if ($method === 'GET' && $path === '/api/library/collections') {
            return $this->getCollections();
        }

        // Create collection: POST /api/library/collections
        if ($method === 'POST' && $path === '/api/library/collections') {
            return $this->createCollection($request);
        }

        // Add to collection: POST /api/library/collections/{id}/items
        if ($method === 'POST' && preg_match('#^/api/library/collections/([0-9]+)/items$#D', $path, $m)) {
            return $this->addPostToCollection($request, (int) $m[1]);
        }

        // Remove from collection: DELETE /api/library/collections/{id}/items/{postId}
        if ($method === 'DELETE' && preg_match('#^/api/library/collections/([0-9]+)/items/([0-9]+)$#D', $path, $m)) {
            return $this->removePostFromCollection((int) $m[1], (int) $m[2]);
        }

        // Delete collection: DELETE /api/library/collections/{id}
        if ($method === 'DELETE' && preg_match('#^/api/library/collections/([0-9]+)$#D', $path, $m)) {
            return $this->deleteCollection((int) $m[1]);
        }

        // Notes for post: GET /api/library/notes/{postId}
        if ($method === 'GET' && preg_match('#^/api/library/notes/([0-9]+)$#D', $path, $m)) {
            return $this->getNotes((int) $m[1]);
        }

        // Create note: POST /api/library/notes/{postId}
        if ($method === 'POST' && preg_match('#^/api/library/notes/([0-9]+)$#D', $path, $m)) {
            return $this->saveNote($request, (int) $m[1]);
        }

        // Delete note: DELETE /api/library/notes/{id}
        if ($method === 'DELETE' && preg_match('#^/api/library/notes/([0-9]+)$#D', $path, $m)) {
            return $this->deleteNote((int) $m[1]);
        }

        // Topic preferences: POST /api/library/topic-preferences
        if ($method === 'POST' && $path === '/api/library/topic-preferences') {
            return $this->setTopicPreference($request);
        }

        return [
            'status' => 404,
            'body' => ApiErrorResponder::build('NOT_FOUND', 'Library endpoint not found.'),
        ];
    }

    private function validateCsrf(Request $request): bool
    {
        $token = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token');
        return is_string($token) && $this->sessions->validateCsrfToken($token);
    }

    private function toggleBookmark(Request $request, int $postId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to save articles.')];
        }

        $isBookmarked = $this->library->toggleBookmark($user->id, $postId);
        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'is_bookmarked' => $isBookmarked,
                    'message' => $isBookmarked ? 'Article saved to your library.' : 'Article removed from your library.',
                ],
            ],
        ];
    }

    private function getArticleLibraryStatus(int $postId): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return [
                'status' => 200,
                'body' => ['data' => ['is_bookmarked' => false, 'progress' => null, 'notes' => []]],
            ];
        }

        $isBookmarked = $this->library->isBookmarked($user->id, $postId);
        $progress = $this->library->getReadingProgress($user->id, $postId);
        $notes = $this->library->getPostNotes($user->id, $postId);

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'is_bookmarked' => $isBookmarked,
                    'progress' => $progress,
                    'notes' => $notes,
                ],
            ],
        ];
    }

    private function saveProgress(Request $request, int $postId): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 200, 'body' => ['data' => ['saved' => false, 'guest' => true]]];
        }

        $input = $request->input();
        $pct = (int) ($input['progress_percent'] ?? 0);
        $pos = (int) ($input['scroll_position'] ?? 0);
        $completed = !empty($input['completed']);

        $this->library->saveReadingProgress($user->id, $postId, $pct, $pos, $completed);
        return [
            'status' => 200,
            'body' => ['data' => ['saved' => true]],
        ];
    }

    private function getCollections(): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to view collections.')];
        }

        $collections = $this->library->getCollections($user->id, $user->id);
        return [
            'status' => 200,
            'body' => ['data' => $collections],
        ];
    }

    private function createCollection(Request $request): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to create collections.')];
        }

        $input = $request->input();
        $title = trim((string) ($input['title'] ?? ''));
        $desc = isset($input['description']) ? trim((string) $input['description']) : null;
        $isPrivate = !isset($input['is_private']) || (bool) $input['is_private'];

        if ($title === '' || strlen($title) > 150) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Please provide a valid collection title (max 150 characters).')];
        }

        $id = $this->library->createCollection($user->id, $title, $desc, $isPrivate);
        return [
            'status' => 201,
            'body' => ['data' => ['id' => $id, 'title' => $title, 'message' => 'Collection created successfully.']],
        ];
    }

    private function addPostToCollection(Request $request, int $collectionId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $input = $request->input();
        $postId = (int) ($input['post_id'] ?? 0);
        if ($postId <= 0) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Invalid post ID.')];
        }

        $success = $this->library->addPostToCollection($user->id, $collectionId, $postId);
        if (!$success) {
            return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Collection not found or access denied.')];
        }

        return ['status' => 200, 'body' => ['data' => ['success' => true, 'message' => 'Added to collection.']]];
    }

    private function removePostFromCollection(int $collectionId, int $postId): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $this->library->removePostFromCollection($user->id, $collectionId, $postId);
        return ['status' => 200, 'body' => ['data' => ['success' => true]]];
    }

    private function deleteCollection(int $collectionId): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $this->library->deleteCollection($user->id, $collectionId);
        return ['status' => 200, 'body' => ['data' => ['success' => true]]];
    }

    private function getNotes(int $postId): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 200, 'body' => ['data' => []]];
        }

        $notes = $this->library->getPostNotes($user->id, $postId);
        return ['status' => 200, 'body' => ['data' => $notes]];
    }

    private function saveNote(Request $request, int $postId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to save notes.')];
        }

        $input = $request->input();
        $noteText = trim((string) ($input['note_text'] ?? ''));
        $highlightText = isset($input['highlight_text']) ? trim((string) $input['highlight_text']) : null;
        $color = trim((string) ($input['color'] ?? 'yellow'));
        $isPrivate = !isset($input['is_private']) || (bool) $input['is_private'];

        if ($noteText === '') {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Note content cannot be empty.')];
        }

        $noteId = $this->library->saveNote($user->id, $postId, $noteText, $highlightText, $color, $isPrivate);
        return [
            'status' => 201,
            'body' => [
                'data' => [
                    'id' => $noteId,
                    'note_text' => $noteText,
                    'highlight_text' => $highlightText,
                    'color' => $color,
                    'message' => 'Note saved.',
                ],
            ],
        ];
    }

    private function deleteNote(int $noteId): array
    {
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $this->library->deleteNote($user->id, $noteId);
        return ['status' => 200, 'body' => ['data' => ['success' => true]]];
    }

    private function setTopicPreference(Request $request): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $input = $request->input();
        $catId = (int) ($input['category_id'] ?? 0);
        $isMuted = !empty($input['is_muted']);

        if ($catId <= 0) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Invalid category ID.')];
        }

        $this->library->setTopicPreference($user->id, $catId, $isMuted);
        return ['status' => 200, 'body' => ['data' => ['success' => true, 'is_muted' => $isMuted]]];
    }
}
