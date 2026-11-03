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
        $now = gmdate('Y-m-d H:i:s');
        $this->removeExpired($now);
        if ($this->bucketIsLimited($this->bucketHash('global', 'all'), self::SYSTEM_LIMIT, $now)) {
            return true;
        }

        return $this->bucketIsLimited($this->bucketHash('identifier', $identifier), self::IDENTIFIER_LIMIT, $now)
            || $this->bucketIsLimited($this->bucketHash('source', $sourceAddress), self::SOURCE_LIMIT, $now);
    }

    public function recordFailure(string $identifier, string $sourceAddress): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + (self::WINDOW_MINUTES * 60));
        $this->removeExpired($now);
        $this->incrementBucket('global', $this->bucketHash('global', 'all'), $now, $expires);
        $this->incrementBucket('identifier', $this->bucketHash('identifier', $identifier), $now, $expires);
        $this->incrementBucket('source', $this->bucketHash('source', $sourceAddress), $now, $expires);
    }

    public function recordSuccess(string $identifier): void
    {
        $statement = $this->pdo->prepare('DELETE FROM login_rate_limits WHERE bucket_hash = :bucket_hash');
        $statement->execute(['bucket_hash' => $this->bucketHash('identifier', $identifier)]);
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private function removeExpired(string $now): void
    {
        if ($this->isMysql()) {
            $this->pdo->exec('DELETE FROM login_rate_limits WHERE expires_at <= UTC_TIMESTAMP()');
            return;
        }

        $statement = $this->pdo->prepare('DELETE FROM login_rate_limits WHERE expires_at <= :now');
        $statement->execute(['now' => $now]);
    }

    private function bucketIsLimited(string $hash, int $limit, string $now): bool
    {
        if ($this->isMysql()) {
            $statement = $this->pdo->prepare(
                'SELECT 1 FROM login_rate_limits WHERE bucket_hash = :bucket_hash ' .
                'AND failed_attempts >= :attempt_limit AND expires_at > UTC_TIMESTAMP() LIMIT 1'
            );
            $statement->execute(['bucket_hash' => $hash, 'attempt_limit' => $limit]);

            return $statement->fetchColumn() !== false;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1 FROM login_rate_limits WHERE bucket_hash = :bucket_hash ' .
            'AND failed_attempts >= :attempt_limit AND expires_at > :now LIMIT 1'
        );
        $statement->execute(['bucket_hash' => $hash, 'attempt_limit' => $limit, 'now' => $now]);

        return $statement->fetchColumn() !== false;
    }

    private function incrementBucket(string $type, string $hash, string $now, string $expires): void
    {
        if ($this->isMysql()) {
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
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO login_rate_limits ' .
            '(bucket_hash, bucket_type, failed_attempts, window_started_at, expires_at, updated_at) ' .
            'VALUES (:bucket_hash, :bucket_type, 1, :now, :expires, :now) ' .
            'ON CONFLICT(bucket_hash) DO UPDATE SET ' .
            'failed_attempts = CASE WHEN expires_at <= excluded.window_started_at THEN 1 ELSE failed_attempts + 1 END, ' .
            'window_started_at = CASE WHEN expires_at <= excluded.window_started_at THEN excluded.window_started_at ELSE window_started_at END, ' .
            'expires_at = CASE WHEN expires_at <= excluded.window_started_at THEN excluded.expires_at ELSE expires_at END, ' .
            'updated_at = excluded.updated_at'
        );
        $statement->execute([
            'bucket_hash' => $hash,
            'bucket_type' => $type,
            'now' => $now,
            'expires' => $expires,
        ]);
    }

    private function bucketHash(string $type, string $value): string
    {
        $normalized = $type === 'identifier'
            ? UserRepository::normalizeIdentifier($value)
            : trim($value);

        return hash_hmac('sha256', $type . "\0" . $normalized, $this->key);
    }
}
