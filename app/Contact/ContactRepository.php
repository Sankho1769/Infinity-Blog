<?php
declare(strict_types=1);

namespace InfinityBlog\Contact;

use PDO;

final class ContactRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Store a verified contact message in the queue.
     */
    public function createMessage(string $name, string $email, string $subject, string $message, ?string $ipAddress = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO contact_messages (name, email, subject, message, ip_address, status, created_at) ' .
            'VALUES (:name, :email, :subject, :message, :ip, :status, :created_at)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message,
            ':ip' => $ipAddress,
            ':status' => 'received',
            ':created_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Count messages submitted from an IP address within the given window in minutes.
     */
    public function countRecentFromIp(string $ipAddress, int $minutes = 60): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($minutes * 60));
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM contact_messages WHERE ip_address = :ip AND created_at >= :cutoff'
        );
        $stmt->execute([
            ':ip' => $ipAddress,
            ':cutoff' => $cutoff,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * List contact messages for administrative inbox.
     *
     * @return list<array<string, mixed>>
     */
    public function listMessages(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, subject, message, ip_address, status, created_at ' .
            'FROM contact_messages ' .
            'ORDER BY id DESC ' .
            'LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total contact messages for inbox pagination.
     */
    public function countMessages(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
    }

    /**
     * Update message status (e.g. 'received', 'read', 'resolved').
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['received', 'read', 'resolved'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE contact_messages SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
