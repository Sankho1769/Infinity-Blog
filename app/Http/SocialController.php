<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserRepository;
use InfinityBlog\Social\FollowRepository;
use InfinityBlog\Social\MessageRepository;
use InvalidArgumentException;
use Throwable;

final class SocialController
{
    private FollowRepository $follows;
    private MessageRepository $messages;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private UserRepository $users;

    public function __construct(
        FollowRepository $follows,
        MessageRepository $messages,
        AuthGuard $guard,
        SessionManager $sessions,
        UserRepository $users
    ) {
        $this->follows = $follows;
        $this->messages = $messages;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->users = $users;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        // Follow / Unfollow endpoints
        if (preg_match('#^/api/authors/([0-9]+)/follow$#D', $request->path, $m) === 1) {
            return $this->follow($request, (int) $m[1]);
        }
        if (preg_match('#^/api/authors/([0-9]+)/unfollow$#D', $request->path, $m) === 1) {
            return $this->unfollow($request, (int) $m[1]);
        }
        if (preg_match('#^/api/authors/([0-9]+)/followers$#D', $request->path, $m) === 1) {
            return $this->followers((int) $m[1]);
        }
        if (preg_match('#^/api/authors/([0-9]+)/following$#D', $request->path, $m) === 1) {
            return $this->following((int) $m[1]);
        }

        // Direct message endpoints
        if ($request->path === '/api/messages/unread-count' && $request->method === 'GET') {
            return $this->unreadCount();
        }
        if ($request->path === '/api/messages' && $request->method === 'GET') {
            return $this->listConversations();
        }
        if ($request->path === '/api/messages' && $request->method === 'POST') {
            $input = $request->input();
            $recipientId = isset($input['recipient_id']) ? (int) $input['recipient_id'] : 0;
            return $this->sendMessage($request, $recipientId);
        }
        if (preg_match('#^/api/messages/([0-9]+)$#D', $request->path, $m) === 1) {
            $otherUserId = (int) $m[1];
            if ($request->method === 'GET') {
                return $this->getThread($request, $otherUserId);
            }
            if ($request->method === 'POST') {
                return $this->sendMessage($request, $otherUserId);
            }
        }

        return ['status' => 404, 'body' => ['error' => ['code' => 'NOT_FOUND', 'message' => 'Resource not found.']]];
    }

    private function follow(Request $request, int $targetAuthorId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to follow authors.']]];
        }
        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        if ($account->id === $targetAuthorId) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'SELF_FOLLOW', 'message' => 'You cannot follow yourself.']]];
        }

        try {
            $this->follows->follow($account->id, $targetAuthorId);
            $count = $this->follows->getFollowerCount($targetAuthorId);

            return ['status' => 200, 'body' => [
                'data' => [
                    'is_following' => true,
                    'follower_count' => $count,
                    'message' => 'Successfully followed.',
                ],
            ]];
        } catch (Throwable $e) {
            return ['status' => 400, 'body' => ['error' => ['code' => 'FOLLOW_FAILED', 'message' => $e->getMessage()]]];
        }
    }

    private function unfollow(Request $request, int $targetAuthorId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to unfollow authors.']]];
        }
        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        try {
            $this->follows->unfollow($account->id, $targetAuthorId);
            $count = $this->follows->getFollowerCount($targetAuthorId);

            return ['status' => 200, 'body' => [
                'data' => [
                    'is_following' => false,
                    'follower_count' => $count,
                    'message' => 'Successfully unfollowed.',
                ],
            ]];
        } catch (Throwable $e) {
            return ['status' => 400, 'body' => ['error' => ['code' => 'UNFOLLOW_FAILED', 'message' => $e->getMessage()]]];
        }
    }

    private function followers(int $authorId): array
    {
        $list = $this->follows->getFollowers($authorId);
        return ['status' => 200, 'body' => ['data' => ['followers' => $list, 'count' => count($list)]]];
    }

    private function following(int $authorId): array
    {
        $list = $this->follows->getFollowing($authorId);
        return ['status' => 200, 'body' => ['data' => ['following' => $list, 'count' => count($list)]]];
    }

    private function unreadCount(): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 200, 'body' => ['data' => ['unread_count' => 0]]];
        }

        $count = $this->messages->getUnreadCount($account->id);
        return ['status' => 200, 'body' => ['data' => ['unread_count' => $count]]];
    }

    private function listConversations(): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to view messages.']]];
        }

        $conversations = $this->messages->listConversations($account->id);
        return ['status' => 200, 'body' => ['data' => ['conversations' => $conversations]]];
    }

    private function getThread(Request $request, int $otherUserId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to view messages.']]];
        }

        $afterId = isset($_GET['after_id']) ? max(0, (int) $_GET['after_id']) : null;
        $messages = $this->messages->getThread($account->id, $otherUserId, 100, $afterId);
        // Mark as read
        $this->messages->markThreadAsRead($account->id, $otherUserId);

        $otherUser = $this->users->findById($otherUserId);
        $otherUserData = $otherUser ? [
            'id' => $otherUser->id,
            'username' => $otherUser->username,
            'display_name' => $otherUser->displayName,
            'avatar_path' => $otherUser->avatarPath,
        ] : null;

        return ['status' => 200, 'body' => [
            'data' => [
                'messages' => $messages,
                'other_user' => $otherUserData,
            ],
        ]];
    }

    private function sendMessage(Request $request, int $otherUserId): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to send messages.']]];
        }
        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $body = '';
        if ($request->isJson()) {
            $jsonData = $request->json();
            $body = isset($jsonData['body']) && is_string($jsonData['body']) ? $jsonData['body'] : '';
        } else {
            $body = isset($_POST['body']) && is_string($_POST['body']) ? $_POST['body'] : '';
        }

        try {
            $msg = $this->messages->sendMessage($account->id, $otherUserId, $body);
            return ['status' => 201, 'body' => ['data' => ['message' => $msg]]];
        } catch (InvalidArgumentException $e) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]]];
        } catch (Throwable $e) {
            return ['status' => 500, 'body' => ['error' => ['code' => 'SEND_FAILED', 'message' => 'Failed to send message.']]];
        }
    }

    private function requireCsrf(Request $request): ?array
    {
        $token = $request->header('X-CSRF-Token');
        if (!is_string($token) || $token === '') {
            $jsonData = $request->isJson() ? $request->json() : [];
            $token = $jsonData['csrf_token'] ?? ($_POST['csrf_token'] ?? null);
        }

        if (!is_string($token) || !$this->sessions->validateCsrfToken($token)) {
            return [
                'status' => 403,
                'body' => [
                    'error' => [
                        'code' => 'CSRF_INVALID',
                        'message' => 'The CSRF token is missing or invalid.',
                    ],
                ],
            ];
        }

        return null;
    }
}
