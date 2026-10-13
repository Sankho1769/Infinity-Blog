<?php
declare(strict_types=1);

namespace InfinityBlog\Social;

use InvalidArgumentException;
use PDO;

final class MessageRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Send a direct message between two users.
     *
     * @return array{id: int, sender_id: int, recipient_id: int, body: string, created_at: string}
     */
    public function sendMessage(int $senderId, int $recipientId, string $body): array
    {
        $cleanBody = trim($body);
        if ($cleanBody === '') {
            throw new InvalidArgumentException('Message body cannot be empty.');
        }

        if (mb_strlen($cleanBody) > 5000) {
            throw new InvalidArgumentException('Message exceeds maximum length of 5000 characters.');
        }

        if ($senderId === $recipientId) {
            throw new InvalidArgumentException('Cannot send direct messages to yourself.');
        }

        // Verify recipient exists and is active
        $stmt = $this->pdo->prepare('SELECT id, status FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $recipientId]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$recipient || $recipient['status'] !== 'active') {
            throw new InvalidArgumentException('Recipient is not found or is inactive.');
        }

        // Enforce user blocking: if either party blocked the other, refuse communication
        $blockStmt = $this->pdo->prepare(
            'SELECT id FROM user_blocks WHERE (user_id = :recip AND blocked_user_id = :sender) OR (user_id = :sender AND blocked_user_id = :recip) LIMIT 1'
        );
        $blockStmt->execute([':recip' => $recipientId, ':sender' => $senderId]);
        if ($blockStmt->fetch()) {
            throw new InvalidArgumentException('Cannot send direct message: communication between these users is blocked.');
        }

        // Rate limit: maximum 30 messages in the last 60 seconds from this sender
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $rateSql = $driver === 'sqlite'
            ? 'SELECT COUNT(*) FROM direct_messages WHERE sender_id = :sender AND created_at >= datetime(\'now\', \'-1 minute\')'
            : 'SELECT COUNT(*) FROM direct_messages WHERE sender_id = :sender AND created_at >= NOW() - INTERVAL 1 MINUTE';
        $rateStmt = $this->pdo->prepare($rateSql);
        $rateStmt->execute([':sender' => $senderId]);
        if ((int) $rateStmt->fetchColumn() >= 30) {
            throw new InvalidArgumentException('Rate limit exceeded: please slow down before sending more direct messages.');
        }

        $timeExpr = $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
        $insert = $this->pdo->prepare(
            "INSERT INTO direct_messages (sender_id, recipient_id, body, created_at) VALUES (:sender_id, :recipient_id, :body, {$timeExpr})"
        );
        $insert->execute([
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'body' => $cleanBody,
        ]);

        $messageId = (int) $this->pdo->lastInsertId();

        try {
            $senderStmt = $this->pdo->prepare('SELECT display_name, username FROM users WHERE id = :id LIMIT 1');
            $senderStmt->execute([':id' => $senderId]);
            $sRow = $senderStmt->fetch(PDO::FETCH_ASSOC);
            $sName = $sRow['display_name'] ?? $sRow['username'] ?? 'Someone';

            $notifs = new NotificationRepository($this->pdo);
            $notifs->createNotification(
                $recipientId,
                $senderId,
                'message',
                $messageId,
                "New direct message from {$sName}."
            );
        } catch (\Throwable) {
            // Ignore notification failure
        }

        $fetch = $this->pdo->prepare(
            'SELECT id, sender_id, recipient_id, body, created_at FROM direct_messages WHERE id = :id LIMIT 1'
        );
        $fetch->execute(['id' => $messageId]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);

        return [
            'id' => (int) $row['id'],
            'sender_id' => (int) $row['sender_id'],
            'recipient_id' => (int) $row['recipient_id'],
            'body' => (string) $row['body'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * List all conversation threads for a user.
     *
     * @return list<array{
     *     other_user: array{id: int, username: string, display_name: string, avatar_path: ?string},
     *     last_message: array{body: string, created_at: string, sender_id: int},
     *     unread_count: int
     * }>
     */
    public function listConversations(int $userId): array
    {
        // Find distinct contact IDs
        $contactsStmt = $this->pdo->prepare(
            'SELECT DISTINCT ' .
            'CASE WHEN sender_id = :u1 THEN recipient_id ELSE sender_id END AS contact_id ' .
            'FROM direct_messages ' .
            'WHERE sender_id = :u2 OR recipient_id = :u3'
        );
        $contactsStmt->execute(['u1' => $userId, 'u2' => $userId, 'u3' => $userId]);
        $contactIds = $contactsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($contactIds)) {
            return [];
        }

        $conversations = [];
        foreach ($contactIds as $contactId) {
            $cId = (int) $contactId;

            // Fetch user info
            $uStmt = $this->pdo->prepare(
                'SELECT id, username, display_name, avatar_path FROM users WHERE id = :cid LIMIT 1'
            );
            $uStmt->execute(['cid' => $cId]);
            $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) {
                continue;
            }

            // Fetch last message
            $lastMsgStmt = $this->pdo->prepare(
                'SELECT sender_id, body, created_at FROM direct_messages ' .
                'WHERE (sender_id = :me1 AND recipient_id = :them1) OR (sender_id = :them2 AND recipient_id = :me2) ' .
                'ORDER BY created_at DESC, id DESC LIMIT 1'
            );
            $lastMsgStmt->execute([
                'me1' => $userId, 'them1' => $cId,
                'them2' => $cId, 'me2' => $userId,
            ]);
            $lastMsg = $lastMsgStmt->fetch(PDO::FETCH_ASSOC);

            // Fetch unread count from this contact
            $unreadStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM direct_messages ' .
                'WHERE sender_id = :them AND recipient_id = :me AND read_at IS NULL'
            );
            $unreadStmt->execute(['them' => $cId, 'me' => $userId]);
            $unreadCount = (int) $unreadStmt->fetchColumn();

            $conversations[] = [
                'other_user' => [
                    'id' => (int) $userRow['id'],
                    'username' => (string) $userRow['username'],
                    'display_name' => (string) $userRow['display_name'],
                    'avatar_path' => $userRow['avatar_path'] !== null ? (string) $userRow['avatar_path'] : null,
                ],
                'last_message' => [
                    'body' => $lastMsg ? (string) $lastMsg['body'] : '',
                    'created_at' => $lastMsg ? (string) $lastMsg['created_at'] : '',
                    'sender_id' => $lastMsg ? (int) $lastMsg['sender_id'] : 0,
                ],
                'unread_count' => $unreadCount,
            ];
        }

        // Sort by last message date descending
        usort($conversations, static function (array $a, array $b): int {
            return strcmp($b['last_message']['created_at'], $a['last_message']['created_at']);
        });

        return $conversations;
    }

    /**
     * Get message thread between current user and another user.
     *
     * @return list<array{id: int, sender_id: int, recipient_id: int, body: string, read_at: ?string, created_at: string, is_mine: bool}>
     */
    public function getThread(int $currentUserId, int $otherUserId, int $limit = 50, ?int $afterId = null): array
    {
        $sql = 'SELECT id, sender_id, recipient_id, body, read_at, created_at ' .
            'FROM direct_messages ' .
            'WHERE ((sender_id = :me1 AND recipient_id = :other1) OR (sender_id = :other2 AND recipient_id = :me2)) ';

        if ($afterId !== null && $afterId > 0) {
            $sql .= 'AND id > :after_id ';
        }

        $sql .= 'ORDER BY id ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':me1', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':other1', $otherUserId, PDO::PARAM_INT);
        $stmt->bindValue(':other2', $otherUserId, PDO::PARAM_INT);
        $stmt->bindValue(':me2', $currentUserId, PDO::PARAM_INT);
        if ($afterId !== null && $afterId > 0) {
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'sender_id' => (int) $r['sender_id'],
            'recipient_id' => (int) $r['recipient_id'],
            'body' => (string) $r['body'],
            'read_at' => $r['read_at'] !== null ? (string) $r['read_at'] : null,
            'created_at' => (string) $r['created_at'],
            'is_mine' => ((int) $r['sender_id'] === $currentUserId),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Mark all incoming messages from another user as read.
     */
    public function markThreadAsRead(int $currentUserId, int $otherUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE direct_messages SET read_at = NOW() ' .
            'WHERE sender_id = :sender_id AND recipient_id = :recipient_id AND read_at IS NULL'
        );
        $stmt->execute([
            'sender_id' => $otherUserId,
            'recipient_id' => $currentUserId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Total unread messages count for a user across all contacts.
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM direct_messages WHERE recipient_id = :user_id AND read_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
