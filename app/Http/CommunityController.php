<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Community\CommunityRepository;
use InfinityBlog\Posts\PostRepository;

final class CommunityController
{
    private CommunityRepository $community;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private PostRepository $posts;

    public function __construct(
        CommunityRepository $community,
        AuthGuard $guard,
        SessionManager $sessions,
        PostRepository $posts
    ) {
        $this->community = $community;
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

        // Get post comments: GET /api/posts/{id}/comments
        if ($method === 'GET' && preg_match('#^/api/posts/([0-9]+)/comments$#D', $path, $m)) {
            $user = $this->guard->user();
            $comments = $this->community->getPostComments((int) $m[1], $user?->id);
            return ['status' => 200, 'body' => ['data' => $comments]];
        }

        // Add comment: POST /api/posts/{id}/comments
        if ($method === 'POST' && preg_match('#^/api/posts/([0-9]+)/comments$#D', $path, $m)) {
            return $this->addComment($request, (int) $m[1]);
        }

        // Edit comment: PUT /api/comments/{id}
        if (($method === 'PUT' || $method === 'POST') && preg_match('#^/api/comments/([0-9]+)/edit$#D', $path, $m)) {
            return $this->editComment($request, (int) $m[1]);
        }

        // Delete comment: DELETE /api/comments/{id} or POST /api/comments/{id}/delete
        if (($method === 'DELETE' || $method === 'POST') && preg_match('#^/api/comments/([0-9]+)/delete$#D', $path, $m)) {
            return $this->deleteComment($request, (int) $m[1]);
        }

        // Report comment: POST /api/comments/{id}/report
        if ($method === 'POST' && preg_match('#^/api/comments/([0-9]+)/report$#D', $path, $m)) {
            return $this->reportComment($request, (int) $m[1]);
        }

        // Chat rooms list: GET /api/chat/rooms
        if ($method === 'GET' && $path === '/api/chat/rooms') {
            return ['status' => 200, 'body' => ['data' => $this->community->getChatRooms()]];
        }

        // Chat messages: GET /api/chat/rooms/{slug}/messages
        if ($method === 'GET' && preg_match('#^/api/chat/rooms/([a-zA-Z0-9_\-]+)/messages$#D', $path, $m)) {
            $room = $this->community->getChatRoomBySlug($m[1]);
            if ($room === null) {
                return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Chat room not found.')];
            }
            $afterId = isset($_GET['after']) ? (int) $_GET['after'] : null;
            $messages = $this->community->getChatMessages((int) $room['id'], 50, $afterId);
            return ['status' => 200, 'body' => ['data' => $messages, 'room' => $room]];
        }

        // Post chat message: POST /api/chat/rooms/{slug}/messages
        if ($method === 'POST' && preg_match('#^/api/chat/rooms/([a-zA-Z0-9_\-]+)/messages$#D', $path, $m)) {
            return $this->postChatMessage($request, $m[1]);
        }

        // Create chat room: POST /api/chat/rooms
        if ($method === 'POST' && $path === '/api/chat/rooms') {
            return $this->createChatRoom($request);
        }

        // Block user: POST /api/users/{id}/block
        if ($method === 'POST' && preg_match('#^/api/users/([0-9]+)/block$#D', $path, $m)) {
            return $this->blockUser($request, (int) $m[1]);
        }

        // Unblock user: POST /api/users/{id}/unblock
        if ($method === 'POST' && preg_match('#^/api/users/([0-9]+)/unblock$#D', $path, $m)) {
            return $this->unblockUser($request, (int) $m[1]);
        }

        // Report user: POST /api/users/{id}/report
        if ($method === 'POST' && preg_match('#^/api/users/([0-9]+)/report$#D', $path, $m)) {
            return $this->reportUser($request, (int) $m[1]);
        }

        // Notifications: GET /api/notifications
        if ($method === 'GET' && $path === '/api/notifications') {
            $user = $this->guard->user();
            if ($user === null) {
                return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
            }
            return ['status' => 200, 'body' => ['data' => $this->community->getUserNotifications($user->id)]];
        }

        // Mark notifications read: POST /api/notifications/read
        if ($method === 'POST' && $path === '/api/notifications/read') {
            $user = $this->guard->user();
            if ($user !== null) {
                $this->community->markNotificationsRead($user->id);
            }
            return ['status' => 200, 'body' => ['data' => ['success' => true]]];
        }

        return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Community endpoint not found.')];
    }

    private function validateCsrf(Request $request): bool
    {
        $token = $request->header('X-CSRF-Token') ?? $request->postParameter('csrf_token');
        return is_string($token) && $this->sessions->validateCsrfToken($token);
    }

    private function addComment(Request $request, int $postId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to post a comment.')];
        }

        $input = $request->input();
        $content = trim((string) ($input['content'] ?? ''));
        $parentId = !empty($input['parent_id']) ? (int) $input['parent_id'] : null;

        if ($content === '' || strlen($content) > 3000) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Comment must be between 1 and 3000 characters.')];
        }

        $commentId = $this->community->addComment($postId, $user->id, $content, $parentId);

        // Notify article author
        $post = $this->posts->findPublic($postId);
        if ($post && (int) $post['author_id'] !== $user->id) {
            $this->community->createNotification(
                (int) $post['author_id'],
                $user->id,
                'comment',
                $postId,
                $user->displayName . ' commented on your article "' . mb_substr($post['title'], 0, 40) . '..."'
            );
        }

        return [
            'status' => 201,
            'body' => [
                'data' => [
                    'id' => $commentId,
                    'post_id' => $postId,
                    'user_id' => $user->id,
                    'user_name' => $user->displayName,
                    'content' => $content,
                    'created_at' => date('Y-m-d H:i:s'),
                    'message' => 'Comment posted successfully.',
                ],
            ],
        ];
    }

    private function editComment(Request $request, int $commentId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $input = $request->input();
        $content = trim((string) ($input['content'] ?? ''));
        if ($content === '' || strlen($content) > 3000) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Comment must be between 1 and 3000 characters.')];
        }

        $success = $this->community->editComment($commentId, $user->id, $content, $user->role === 'admin');
        if (!$success) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('FORBIDDEN', 'Permission denied.')];
        }

        return ['status' => 200, 'body' => ['data' => ['success' => true, 'content' => $content]]];
    }

    private function deleteComment(Request $request, int $commentId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $success = $this->community->deleteComment($commentId, $user->id, $user->role === 'admin');
        if (!$success) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('FORBIDDEN', 'Permission denied.')];
        }

        return ['status' => 200, 'body' => ['data' => ['success' => true]]];
    }

    private function reportComment(Request $request, int $commentId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to report content.')];
        }

        $input = $request->input();
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Please provide a reason for the report.')];
        }

        $this->community->reportComment($commentId, $user->id, $reason);
        return ['status' => 200, 'body' => ['data' => ['success' => true, 'message' => 'Thank you. The comment has been flagged for moderation.']]];
    }

    private function postChatMessage(Request $request, string $slug): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in to chat.')];
        }

        $room = $this->community->getChatRoomBySlug($slug);
        if ($room === null) {
            return ['status' => 404, 'body' => ApiErrorResponder::build('NOT_FOUND', 'Room not found.')];
        }

        $input = $request->input();
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '' || strlen($message) > 1000) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Message cannot be empty and max 1000 characters.')];
        }

        try {
            $msgId = $this->community->postChatMessage((int) $room['id'], $user->id, $message);
        } catch (\InvalidArgumentException $e) {
            $isRateLimit = str_contains($e->getMessage(), 'Rate limit') || str_contains($e->getMessage(), 'wait a moment');
            $status = $isRateLimit ? 429 : 422;
            $code = $isRateLimit ? 'RATE_LIMITED' : 'VALIDATION_ERROR';
            return ['status' => $status, 'body' => ApiErrorResponder::build($code, $e->getMessage())];
        }
        return [
            'status' => 201,
            'body' => [
                'data' => [
                    'id' => $msgId,
                    'room_id' => $room['id'],
                    'user_id' => $user->id,
                    'user_name' => $user->displayName,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
            ],
        ];
    }

    private function createChatRoom(Request $request): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }

        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $input = $request->input();
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $desc = isset($input['description']) ? trim((string) $input['description']) : null;
        $catId = !empty($input['category_id']) ? (int) $input['category_id'] : null;

        if ($name === '' || strlen($name) > 100) {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Room name must be between 1 and 100 characters.')];
        }

        $existing = $this->community->getChatRoomBySlug($slug);
        if ($existing !== null) {
            $slug .= '-' . substr(md5((string) microtime(true)), 0, 4);
        }

        $roomId = $this->community->createChatRoom($name, $slug, $desc, $catId, $user->id);
        return ['status' => 201, 'body' => ['data' => ['id' => $roomId, 'slug' => $slug, 'name' => $name]]];
    }

    private function blockUser(Request $request, int $targetUserId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $this->community->blockUser($user->id, $targetUserId);
        return ['status' => 200, 'body' => ['data' => ['success' => true, 'message' => 'User blocked.']]];
    }

    private function unblockUser(Request $request, int $targetUserId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $this->community->unblockUser($user->id, $targetUserId);
        return ['status' => 200, 'body' => ['data' => ['success' => true, 'message' => 'User unblocked.']]];
    }

    private function reportUser(Request $request, int $targetUserId): array
    {
        if (!$this->validateCsrf($request)) {
            return ['status' => 403, 'body' => ApiErrorResponder::build('CSRF_INVALID', 'Security verification failed.')];
        }
        $user = $this->guard->user();
        if ($user === null) {
            return ['status' => 401, 'body' => ApiErrorResponder::build('UNAUTHENTICATED', 'Please sign in.')];
        }

        $input = $request->input();
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ['status' => 422, 'body' => ApiErrorResponder::build('VALIDATION_ERROR', 'Please provide a reason.')];
        }

        $this->community->reportUser($user->id, $targetUserId, $reason);
        return ['status' => 200, 'body' => ['data' => ['success' => true, 'message' => 'Report submitted for review.']]];
    }
}
