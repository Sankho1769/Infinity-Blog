<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use PDO;

final class DatabaseLoginAttemptLimiter implements LoginAttemptLimiter
{
    private const WINDOW_MINUTES = 15;
    private const IDENTIFIER_LIMIT = 5;
    private const SOURCE_LIMIT = 20;
    private const SYSTEM_LIMIT = 100000;

    private PDO $pdo;
    private string $key;

    public function __construct(PDO $pdo, string $applicationKey)
    {
        $this->pdo = $pdo;
        $this->key = $applicationKey;
    }

    public function isLimited(string $identifier, string $sourceAddress): bool
    {
        $this->removeExpired();
        if ($this->bucketIsLimited($this->bucketHash('global', 'all'), self::SYSTEM_LIMIT)) {
            return true;
        }

        return $this->bucketIsLimited($this->bucketHash('identifier', $identifier), self::IDENTIFIER_LIMIT)
            || $this->bucketIsLimited($this->bucketHash('source', $sourceAddress), self::SOURCE_LIMIT);
    }

    public function recordFailure(string $identifier, string $sourceAddress): void
    {
        $this->removeExpired();
        $this->incrementBucket('global', $this->bucketHash('global', 'all'));
        $this->incrementBucket('identifier', $this->bucketHash('identifier', $identifier));
        $this->incrementBucket('source', $this->bucketHash('source', $sourceAddress));
    }

    public function recordSuccess(string $identifier): void
    {
        $statement = $this->pdo->prepare('DELETE FROM login_rate_limits WHERE bucket_hash = :bucket_hash');
        $statement->execute(['bucket_hash' => $this->bucketHash('identifier', $identifier)]);
    }

    private function removeExpired(): void
    {
        $this->pdo->exec('DELETE FROM login_rate_limits WHERE expires_at <= UTC_TIMESTAMP()');
    }

    private function bucketIsLimited(string $hash, int $limit): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM login_rate_limits WHERE bucket_hash = :bucket_hash ' .
            'AND failed_attempts >= :attempt_limit AND expires_at > UTC_TIMESTAMP() LIMIT 1'
        );
        $statement->execute(['bucket_hash' => $hash, 'attempt_limit' => $limit]);

        return $statement->fetchColumn() !== false;
    }

    private function incrementBucket(string $type, string $hash): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_rate_limits ' .
            '(bucket_hash, bucket_type, failed_attempts, window_started_at, expires_at, updated_at) ' .
            'VALUES (:bucket_hash, :bucket_type, 1, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE), UTC_TIMESTAMP()) ' .
            'ON DUPLICATE KEY UPDATE ' .
            'failed_attempts = IF(expires_at <= UTC_TIMESTAMP(), 1, failed_attempts + 1), ' .
            'window_started_at = IF(expires_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP(), window_started_at), ' .
            'expires_at = IF(expires_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE), expires_at), ' .
            'updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute(['bucket_hash' => $hash, 'bucket_type' => $type]);
    }

    private function bucketHash(string $type, string $value): string
    {
        $normalized = $type === 'identifier'
            ? UserRepository::normalizeIdentifier($value)
            : trim($value);

        return hash_hmac('sha256', $type . "\0" . $normalized, $this->key);
    }
}
