<?php
declare(strict_types=1);

namespace InfinityBlog\Core;

use InfinityBlog\Config\Database;

class AuditLogger {
    /**
     * Record an audit event with recursive secret redaction and control character neutralization.
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $metadata = null,
        ?int $actorUserId = null
    ): void {
        try {
            $pdo = Database::getConnection();

            if ($actorUserId === null) {
                $user = SessionManager::getCurrentUser();
                $actorUserId = $user ? (int)$user['id'] : null;
            }

            // Recursively redact any sensitive credentials or secrets
            $cleanedMetadata = null;
            if ($metadata !== null) {
                $cleanedMetadata = self::redactRecursive($metadata);
            }

            // Normalize and bound action and entity strings
            $cleanAction = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $action) ?? $action, 0, 80);
            $cleanEntityType = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $entityType) ?? $entityType, 0, 50);

            $stmt = $pdo->prepare("
                INSERT INTO `audit_logs` (`actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata`)
                VALUES (:actor_id, :action, :entity_type, :entity_id, :metadata)
            ");

            $stmt->execute([
                ':actor_id' => $actorUserId,
                ':action' => $cleanAction,
                ':entity_type' => $cleanEntityType,
                ':entity_id' => $entityId,
                ':metadata' => $cleanedMetadata ? json_encode($cleanedMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log("AuditLogger failure: " . $e->getMessage());
        }
    }

    /**
     * Recursively scrubs passwords, tokens, API keys, cookies, and session IDs.
     */
    public static function redactRecursive(array $data): array {
        $sensitiveKeys = ['password', 'passwd', 'password_hash', 'csrf_token', 'token', 'secret', 'api_key', 'authorization', 'cookie', 'session_id', 'sessid', 'auth', 'key'];
        $cleaned = [];

        foreach ($data as $key => $value) {
            $strKey = strtolower((string)$key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sKey) {
                if (str_contains($strKey, $sKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $cleaned[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $cleaned[$key] = self::redactRecursive($value);
            } elseif (is_string($value)) {
                // Strip control characters to prevent log forgery and bound length
                $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
                $cleaned[$key] = mb_substr($sanitized, 0, 500);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
