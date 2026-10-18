<?php
declare(strict_types=1);

namespace InfinityBlog\Social;

use PDO;
use Throwable;

/**
 * Service for @mention parsing, active user discovery, safe HTML rendering,
 * duplicate notification prevention, and notification dispatching.
 */
final class MentionService
{
    public const MAX_MENTIONS_PER_ITEM = 10;
    private const USERNAME_REGEX = '/(?:^|(?<=[\s,;:!?.(\[\{]))@([a-zA-Z0-9_\.]{2,50})\b/';

    private PDO $pdo;
    private ?NotificationRepository $notifications;
    private ?AuthorBadgeService $badgeService;

    public function __construct(PDO $pdo, ?NotificationRepository $notifications = null, ?AuthorBadgeService $badgeService = null)
    {
        $this->pdo = $pdo;
        $this->notifications = $notifications;
        $this->badgeService = $badgeService ?? new AuthorBadgeService($pdo);
        $this->ensureTable();
    }

    /**
     * Ensure the content_mentions tracking table exists.
     */
    public function ensureTable(): void
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->pdo->exec(
                    'CREATE TABLE IF NOT EXISTS content_mentions (' .
                    'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
                    'source_type VARCHAR(32) NOT NULL, ' .
                    'source_id INTEGER NOT NULL, ' .
                    'mentioned_user_id INTEGER NOT NULL, ' .
                    'author_id INTEGER NOT NULL, ' .
                    'context_url VARCHAR(500) NULL, ' .
                    'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                    'UNIQUE (source_type, source_id, mentioned_user_id)' .
                    ')'
                );
                $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_mentions_user ON content_mentions (mentioned_user_id)');
                $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_mentions_source ON content_mentions (source_type, source_id)');
            } else {
                $this->pdo->exec(
                    'CREATE TABLE IF NOT EXISTS content_mentions (' .
                    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, ' .
                    'source_type VARCHAR(32) NOT NULL, ' .
                    'source_id BIGINT UNSIGNED NOT NULL, ' .
                    'mentioned_user_id BIGINT UNSIGNED NOT NULL, ' .
                    'author_id BIGINT UNSIGNED NOT NULL, ' .
                    'context_url VARCHAR(500) NULL, ' .
                    'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ' .
                    'UNIQUE KEY uq_content_mentions (source_type, source_id, mentioned_user_id), ' .
                    'INDEX idx_mentions_user (mentioned_user_id), ' .
                    'INDEX idx_mentions_source (source_type, source_id)' .
                    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }
        } catch (Throwable) {
            // Ignore table creation error if already exists or permission restricted
        }
    }

    /**
     * Search discoverable active users for client-side @mention autocomplete.
     *
     * @return list<array{id: int, username: string, display_name: string, avatar_url: ?string, has_author_badge: bool}>
     */
    public function searchUsers(string $query, int $limit = 8, ?int $viewerId = null): array
    {
        $cleanQuery = trim(ltrim($query, '@'));
        $limit = max(1, min(20, $limit));

        if ($cleanQuery === '') {
            // Return top active authors when query is empty
            $sql = 'SELECT id, username, display_name, avatar_path FROM users WHERE status = \'active\' ';
            $params = [];
            if ($viewerId !== null && $viewerId > 0) {
                $sql .= 'AND id != :vid AND id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = :vid2) ';
                $params[':vid'] = $viewerId;
                $params[':vid2'] = $viewerId;
            }
            $sql .= 'ORDER BY id ASC LIMIT ' . $limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = 'SELECT id, username, display_name, avatar_path FROM users WHERE status = \'active\' AND (username LIKE :q1 OR display_name LIKE :q2) ';
            $params = [
                ':q1' => '%' . $cleanQuery . '%',
                ':q2' => '%' . $cleanQuery . '%',
            ];
            if ($viewerId !== null && $viewerId > 0) {
                $sql .= 'AND id != :vid AND id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = :vid2) ';
                $params[':vid'] = $viewerId;
                $params[':vid2'] = $viewerId;
            }
            $sql .= 'ORDER BY (CASE WHEN username LIKE :exact THEN 1 WHEN username LIKE :starts THEN 2 ELSE 3 END), username ASC LIMIT ' . $limit;
            $params[':exact'] = $cleanQuery;
            $params[':starts'] = $cleanQuery . '%';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $userIds = array_map(static fn(array $r): int => (int) $r['id'], $rows);
        $badgeMap = $this->badgeService->getBadgeStatusesForUsers($userIds);

        return array_map(static function (array $r) use ($badgeMap): array {
            $uid = (int) $r['id'];
            $avatarUrl = \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($r['avatar_path'] ?? null);

            return [
                'id' => $uid,
                'username' => (string) $r['username'],
                'display_name' => (string) ($r['display_name'] ?? $r['username']),
                'avatar_url' => $avatarUrl,
                'has_author_badge' => (bool) ($badgeMap[$uid] ?? false),
            ];
        }, $rows);
    }

    /**
     * Extract raw mentioned usernames from content.
     *
     * @return list<string> Normalized unique lowercase usernames
     */
    public function extractUsernames(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        if (preg_match_all(self::USERNAME_REGEX, $content, $matches)) {
            $usernames = array_map('mb_strtolower', $matches[1]);
            $unique = array_values(array_unique(array_filter($usernames, static fn(string $u): bool => mb_strlen($u) >= 2)));
            return array_slice($unique, 0, self::MAX_MENTIONS_PER_ITEM);
        }

        return [];
    }

    /**
     * Resolve mentioned usernames to active user records.
     *
     * @param list<string> $usernames
     * @return array<string, array{id: int, username: string, display_name: string}> Map of lowercase username => user
     */
    public function resolveUsersByUsernames(array $usernames): array
    {
        if ($usernames === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, username, display_name FROM users WHERE LOWER(username) IN ({$placeholders}) AND status = 'active'"
        );
        $stmt->execute($usernames);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resolved = [];
        foreach ($rows as $r) {
            $resolved[mb_strtolower((string) $r['username'])] = [
                'id' => (int) $r['id'],
                'username' => (string) $r['username'],
                'display_name' => (string) ($r['display_name'] ?? $r['username']),
            ];
        }

        return $resolved;
    }

    /**
     * Safely render user content to HTML with mentions converted to valid profile links.
     * Escaping is applied first to prevent stored XSS.
     */
    public function renderMentionsToHtml(string $rawContent): string
    {
        // 1. First escape all user input against XSS
        $escaped = htmlspecialchars($rawContent, ENT_QUOTES, 'UTF-8');

        // 2. Extract potential usernames
        $usernames = $this->extractUsernames($rawContent);
        if ($usernames === []) {
            return nl2br($escaped);
        }

        // 3. Resolve only real active users from database
        $resolved = $this->resolveUsersByUsernames($usernames);
        if ($resolved === []) {
            return nl2br($escaped);
        }

        // 4. Replace valid mentions with safe links
        $result = preg_replace_callback(self::USERNAME_REGEX, static function (array $m) use ($resolved): string {
            $lower = mb_strtolower($m[1]);
            if (isset($resolved[$lower])) {
                $user = $resolved[$lower];
                $cleanHandle = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
                $profileUrl = '/authors/' . rawurlencode($user['username']);
                return '<a href="' . $profileUrl . '" class="mention-link" data-user-id="' . $user['id'] . '">@' . $cleanHandle . '</a>';
            }
            return $m[0]; // Unknown or inactive account: keep as plain text
        }, $escaped);

        return nl2br((string) $result);
    }

    /**
     * Process mentions in user content, record them in content_mentions to prevent duplicates,
     * and trigger notifications via NotificationRepository.
     *
     * @param string $sourceType 'comment', 'chat', or 'post'
     * @param int $sourceId ID of the comment, chat message, etc.
     * @param int $authorId User ID of the sender/author
     * @param string $content Content containing the mentions
     * @param string $contextTitle Title for the notification (e.g. article title or room name)
     * @param string $contextUrl Direct target URL for the mention
     * @return list<int> List of newly notified user IDs
     */
    public function processMentionsAndNotify(
        string $sourceType,
        int $sourceId,
        int $authorId,
        string $content,
        string $contextTitle,
        string $contextUrl
    ): array {
        $usernames = $this->extractUsernames($content);
        if ($usernames === []) {
            return [];
        }

        $resolved = $this->resolveUsersByUsernames($usernames);
        if ($resolved === []) {
            return [];
        }

        // Retrieve author display name
        $authorStmt = $this->pdo->prepare('SELECT display_name, username FROM users WHERE id = :id LIMIT 1');
        $authorStmt->execute([':id' => $authorId]);
        $authorRow = $authorStmt->fetch(PDO::FETCH_ASSOC);
        $authorName = (string) ($authorRow['display_name'] ?? $authorRow['username'] ?? 'Someone');

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertVerb = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $timeExpr = $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';

        $notifiedUserIds = [];
        $notifs = $this->notifications ?? new NotificationRepository($this->pdo);

        $shortTitle = mb_substr($contextTitle, 0, 45);

        foreach ($resolved as $user) {
            $targetUserId = (int) $user['id'];

            // Safeguard 1: Do not notify self
            if ($targetUserId === $authorId) {
                continue;
            }

            // Safeguard 2: Check blocking between author and target user
            if ($this->isBlocked($authorId, $targetUserId)) {
                continue;
            }

            // Safeguard 3: Record in content_mentions. If already exists, rowCount will be 0 (no duplicate on edit).
            try {
                $insertStmt = $this->pdo->prepare(
                    "{$insertVerb} INTO content_mentions (source_type, source_id, mentioned_user_id, author_id, context_url, created_at) " .
                    "VALUES (:st, :sid, :muid, :aid, :curl, {$timeExpr})"
                );
                $insertStmt->execute([
                    ':st' => $sourceType,
                    ':sid' => $sourceId,
                    ':muid' => $targetUserId,
                    ':aid' => $authorId,
                    ':curl' => $contextUrl,
                ]);

                if ($insertStmt->rowCount() > 0) {
                    // Fresh mention: create notification
                    $msgText = match ($sourceType) {
                        'comment' => "{$authorName} mentioned you in a comment on \"{$shortTitle}\".",
                        'chat' => "{$authorName} mentioned you in #{$shortTitle}.",
                        default => "{$authorName} mentioned you in \"{$shortTitle}\".",
                    };

                    $notifId = $notifs->createNotification(
                        $targetUserId,
                        $authorId,
                        'mention',
                        $sourceId,
                        $msgText
                    );

                    if ($notifId !== null) {
                        $notifiedUserIds[] = $targetUserId;
                    }
                }
            } catch (Throwable) {
                // Ignore per-user notification failure
            }
        }

        return $notifiedUserIds;
    }

    /**
     * Check if communication between two users is blocked.
     */
    private function isBlocked(int $userA, int $userB): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM user_blocks WHERE (user_id = :u1 AND blocked_user_id = :u2) OR (user_id = :u2 AND blocked_user_id = :u1) LIMIT 1'
            );
            $stmt->execute([':u1' => $userA, ':u2' => $userB]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
