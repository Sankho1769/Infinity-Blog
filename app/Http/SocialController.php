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
    private ?\InfinityBlog\Social\NotificationRepository $notifications;

    public function __construct(
        FollowRepository $follows,
        MessageRepository $messages,
        AuthGuard $guard,
        SessionManager $sessions,
        UserRepository $users,
        ?\InfinityBlog\Social\NotificationRepository $notifications = null
    ) {
        $this->follows = $follows;
        $this->messages = $messages;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->users = $users;
        $this->notifications = $notifications;
    }

    private function resolveNotificationRepo(): ?\InfinityBlog\Social\NotificationRepository
    {
        if ($this->notifications !== null) {
            return $this->notifications;
        }
        if (method_exists($this->users, 'getPdo')) {
            $this->notifications = new \InfinityBlog\Social\NotificationRepository($this->users->getPdo());
            return $this->notifications;
        }
        return null;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        // Notification endpoints
        if ($request->path === '/api/notifications' && $request->method === 'GET') {
            return $this->getNotifications($request);
        }
        if ($request->path === '/api/notifications/count' && $request->method === 'GET') {
            return $this->getNotificationsCount();
        }
        if (($request->path === '/api/notifications/read-all' || $request->path === '/api/notifications/read') && $request->method === 'POST') {
            return $this->markAllNotificationsRead($request);
        }
        if (preg_match('#^/api/notifications/([0-9]+)/read$#D', $request->path, $m) === 1 && $request->method === 'POST') {
            return $this->markNotificationRead($request, (int) $m[1]);
        }
        if (preg_match('#^/api/notifications/([0-9]+)$#D', $request->path, $m) === 1 && $request->method === 'DELETE') {
            return $this->deleteNotification($request, (int) $m[1]);
        }

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
        if (preg_match('#^/api/authors/([0-9]+)/badge$#D', $request->path, $m) === 1 && $request->method === 'GET') {
            return $this->getAuthorBadge((int) $m[1]);
        }

        // Mention autocomplete endpoint
        if ($request->path === '/api/users/mention-search' && $request->method === 'GET') {
            return $this->mentionSearch($request);
        }

        // Authenticated user's following list (for Share modal & social)
        if ($request->path === '/api/me/following' && $request->method === 'GET') {
            return $this->getMyFollowing($request);
        }

        // Direct message sharing endpoint
        if ($request->path === '/api/share/direct-message' && $request->method === 'POST') {
            return $this->shareDirectMessage($request);
        }

        // People search for New Chat (server-backed, secure)
        if (($request->path === '/api/users/search' || $request->path === '/api/messages/search-users') && $request->method === 'GET') {
            return $this->searchUsersForChat($request);
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
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to view messages.']]];
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

        if ($account->id === $otherUserId) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'SELF_MESSAGE', 'message' => 'You cannot message yourself.']]];
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

        if ($account->id === $otherUserId) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'SELF_MESSAGE', 'message' => 'You cannot send messages to yourself.']]];
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $input = $request->input();
        $body = isset($input['body']) && is_string($input['body']) ? $input['body'] : '';
        $clientMsgId = isset($input['client_msg_id']) && is_string($input['client_msg_id']) ? $input['client_msg_id'] : null;

        try {
            $msg = $this->messages->sendMessage($account->id, $otherUserId, $body, $clientMsgId);
            return ['status' => 201, 'body' => ['data' => ['message' => $msg]]];
        } catch (InvalidArgumentException $e) {
            $isRateLimit = str_contains($e->getMessage(), 'Rate limit') || str_contains($e->getMessage(), 'slow down');
            $status = $isRateLimit ? 429 : 422;
            $code = $isRateLimit ? 'RATE_LIMITED' : (str_contains($e->getMessage(), 'blocked') ? 'USER_BLOCKED' : 'VALIDATION_ERROR');
            return ['status' => $status, 'body' => ['error' => ['code' => $code, 'message' => $e->getMessage()]]];
        } catch (Throwable $e) {
            return ['status' => 500, 'body' => ['error' => ['code' => 'SEND_FAILED', 'message' => 'Failed to send message.']]];
        }
    }

    private function getNotifications(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to view notifications.']]];
        }
        $repo = $this->resolveNotificationRepo();
        if ($repo === null) {
            return ['status' => 200, 'body' => ['data' => ['notifications' => [], 'unread_count' => 0]]];
        }

        $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 25;
        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
        $notifications = $repo->getUserNotifications($account->id, $limit, $offset);
        $unreadCount = $repo->getUnreadCount($account->id);

        return ['status' => 200, 'body' => [
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]];
    }

    private function getNotificationsCount(): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 200, 'body' => ['data' => ['unread_count' => 0]]];
        }
        $repo = $this->resolveNotificationRepo();
        $unreadCount = $repo !== null ? $repo->getUnreadCount($account->id) : 0;

        return ['status' => 200, 'body' => [
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]];
    }

    private function markAllNotificationsRead(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in.']]];
        }
        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $repo = $this->resolveNotificationRepo();
        $affected = $repo !== null ? $repo->markAllAsRead($account->id) : 0;

        return ['status' => 200, 'body' => [
            'data' => [
                'success' => true,
                'affected' => $affected,
            ],
        ]];
    }

    private function markNotificationRead(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in.']]];
        }
        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $repo = $this->resolveNotificationRepo();
        $success = $repo !== null && $repo->markAsRead($id, $account->id);

        return ['status' => 200, 'body' => [
            'data' => [
                'success' => $success,
            ],
        ]];
    }

    private function deleteNotification(Request $request, int $id): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in.']]];
        }
        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $repo = $this->resolveNotificationRepo();
        $success = $repo !== null && $repo->deleteNotification($id, $account->id);

        return ['status' => 200, 'body' => [
            'data' => [
                'success' => $success,
            ],
        ]];
    }

    private function getAuthorBadge(int $authorId): array
    {
        $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
        $status = $badgeService->getAuthorBadgeStatus($authorId);

        return ['status' => 200, 'body' => ['data' => $status]];
    }

    private function searchUsersForChat(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to search users.']]];
        }

        $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
        $limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 15;

        $users = $this->users->searchUsersForChat($q, $account->id, $limit);

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'users' => $users,
                    'query' => $q,
                    'count' => count($users),
                ],
            ],
        ];
    }

    private function mentionSearch(Request $request): array
    {
        $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
        $viewer = $this->guard->currentAccount();
        $mentionService = new \InfinityBlog\Social\MentionService($this->users->getPdo());
        $users = $mentionService->searchUsers($q, 8, $viewer?->id);

        return ['status' => 200, 'body' => ['data' => ['users' => $users]]];
    }

    private function getMyFollowing(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to view your followed contacts.']]];
        }

        $following = $this->follows->getFollowing($account->id);
        if (!empty($following)) {
            $userIds = array_map(static fn(array $u): int => (int) $u['id'], $following);
            $badgeService = new \InfinityBlog\Social\AuthorBadgeService($this->users->getPdo());
            $badgeMap = $badgeService->getBadgeStatusesForUsers($userIds);
            foreach ($following as &$user) {
                $uid = (int) $user['id'];
                $user['has_author_badge'] = !empty($badgeMap[$uid]);
                $user['avatar_url'] = \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($user['avatar_path'] ?? null);
            }
            unset($user);
        }

        return ['status' => 200, 'body' => ['data' => ['following' => $following, 'count' => count($following)]]];
    }

    private function shareDirectMessage(Request $request): array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Please sign in to share stories with contacts.']]];
        }

        $csrfError = $this->requireCsrf($request);
        if ($csrfError !== null) {
            return $csrfError;
        }

        $input = $request->input();

        $recipientIds = [];
        if (isset($input['recipient_ids']) && is_array($input['recipient_ids'])) {
            foreach ($input['recipient_ids'] as $rid) {
                if (is_numeric($rid) && (int) $rid > 0) {
                    $recipientIds[] = (int) $rid;
                }
            }
        } elseif (isset($input['recipient_id']) && is_numeric($input['recipient_id'])) {
            $rid = (int) $input['recipient_id'];
            if ($rid > 0) {
                $recipientIds[] = $rid;
            }
        }

        // Exclude self and remove duplicates
        $recipientIds = array_values(array_unique(array_filter($recipientIds, static fn(int $id): bool => $id !== (int) $account->id)));

        if ($recipientIds === []) {
            return ['status' => 422, 'body' => ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Please select at least one contact to share with.']]];
        }

        $postId = isset($input['post_id']) && is_numeric($input['post_id']) ? (int) $input['post_id'] : null;
        $postTitle = '';
        $postUrl = isset($input['post_url']) && is_string($input['post_url']) ? trim($input['post_url']) : '';
        $customNote = isset($input['message']) && is_string($input['message']) ? trim($input['message']) : '';

        if ($postId !== null && $postId > 0) {
            $stmt = $this->users->getPdo()->prepare('SELECT id, title, status FROM posts WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $postId]);
            $postRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$postRow) {
                return ['status' => 404, 'body' => ['error' => ['code' => 'POST_NOT_FOUND', 'message' => 'The requested post was not found.']]];
            }

            if (($postRow['status'] ?? '') !== 'published') {
                return ['status' => 422, 'body' => ['error' => ['code' => 'INVALID_POST', 'message' => 'Only published articles can be shared.']]];
            }

            $postTitle = (string) $postRow['title'];
            if ($postUrl === '') {
                $postUrl = '/article/' . $postId;
            }
        }

        // Build share message body
        if ($postTitle !== '') {
            $shareBody = "I wanted to share this article with you: \"{$postTitle}\"\n{$postUrl}";
            if ($customNote !== '') {
                $shareBody .= "\n\n{$customNote}";
            }
        } elseif ($customNote !== '') {
            $shareBody = $customNote;
        } else {
            return ['status' => 422, 'body' => ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'A story or message is required to share.']]];
        }

        $sentCount = 0;
        $sentRecipients = [];
        $lastException = null;

        foreach ($recipientIds as $recipientId) {
            try {
                $msg = $this->messages->sendMessage($account->id, $recipientId, $shareBody);
                $sentCount++;
                $sentRecipients[] = [
                    'recipient_id' => $recipientId,
                    'message_id' => $msg['id'] ?? null,
                ];
            } catch (\InvalidArgumentException $e) {
                $lastException = $e;
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        if ($sentCount === 0 && $lastException !== null) {
            $isRateLimit = str_contains($lastException->getMessage(), 'Rate limit') || str_contains($lastException->getMessage(), 'slow down');
            $status = $isRateLimit ? 429 : 422;
            $code = $isRateLimit ? 'RATE_LIMITED' : (str_contains($lastException->getMessage(), 'blocked') ? 'USER_BLOCKED' : 'VALIDATION_ERROR');
            return ['status' => $status, 'body' => ['error' => ['code' => $code, 'message' => $lastException->getMessage()]]];
        }

        return ['status' => 200, 'body' => [
            'data' => [
                'success' => true,
                'sent_count' => $sentCount,
                'recipients' => $sentRecipients,
                'message' => "Successfully shared with {$sentCount} contact(s).",
            ],
        ]];
    }

    private function requireCsrf(Request $request): ?array
    {
        $token = $request->header('X-CSRF-Token');
        if (!is_string($token) || $token === '') {
            $inputData = $request->input();
            $token = $inputData['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null));
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
