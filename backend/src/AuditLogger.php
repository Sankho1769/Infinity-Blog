<?php
declare(strict_types=1);

namespace InfinityBlog\Core;

use InfinityBlog\Config\Database;

class AuditLogger {
    public static function log(string $action, string $entityType, ?int $entityId = null, ?array $metadata = null, ?int $actorUserId = null): void {
        try {
            $pdo = Database::getConnection();

            if ($actorUserId === null) {
                $user = SessionManager::getCurrentUser();
                $actorUserId = $user ? (int)$user['id'] : null;
            }

            // Redact any potential sensitive fields in metadata
            if ($metadata !== null) {
                unset($metadata['password'], $metadata['password_hash'], $metadata['csrf_token'], $metadata['api_key']);
            }

            $stmt = $pdo->prepare("
                INSERT INTO `audit_logs` (`actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata`)
                VALUES (:actor_id, :action, :entity_type, :entity_id, :metadata)
            ");

            $stmt->execute([
                ':actor_id' => $actorUserId,
                ':action' => substr($action, 0, 80),
                ':entity_type' => substr($entityType, 0, 50),
                ':entity_id' => $entityId,
                ':metadata' => $metadata ? json_encode($metadata) : null,
            ]);
        } catch (\Throwable $e) {
            error_log("AuditLogger failure: " . $e->getMessage());
        }
    }
}
