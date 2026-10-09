<?php
declare(strict_types=1);

namespace InfinityBlog\Summaries;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseSummaryRateLimiter implements SummaryRateLimiter
{
    private const WINDOW_SECONDS = 3600;
    private const POST_LIMIT = 3;
    private const SOURCE_LIMIT = 30;
    private const GLOBAL_LIMIT = 10000;

    private PDO $pdo;
    private string $key;
    private int $userLimit;

    public function __construct(PDO $pdo, string $applicationKey, int $userLimit = 10)
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $applicationKey) !== 1 || $userLimit < 1 || $userLimit > 1000) {
            throw new InvalidArgumentException('The summary limiter configuration is invalid.');
        }
        $this->pdo = $pdo;
        $this->key = strtolower($applicationKey);
        $this->userLimit = $userLimit;
    }

    public function consume(int $actorId, int $postId, string $sourceAddress): void
    {
        if ($actorId < 1 || $postId < 1) {
            throw new InvalidArgumentException('Summary limiter identifiers must be positive.');
        }
        if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
            throw new RuntimeException('The summary rate limit could not be checked.');
        }

        try {
            $now = gmdate('Y-m-d H:i:s');
            $expires = gmdate('Y-m-d H:i:s', time() + self::WINDOW_SECONDS);
            $this->removeExpired($now);
            $buckets = [
                ['user', (string) $actorId, $this->userLimit],
                ['post', (string) $postId, self::POST_LIMIT],
                ['source', $sourceAddress, self::SOURCE_LIMIT],
                ['global', 'all', self::GLOBAL_LIMIT],
            ];
            foreach ($buckets as [$type, $value, $limit]) {
                $hash = hash_hmac('sha256', 'summary:' . $type . "\0" . $value, $this->key);
                $this->incrementBucket($type, $hash, $now, $expires);
                $count = $this->bucketCount($hash);
                if ($count > $limit) {
                    throw new SummaryRateLimitException();
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                try {
                    $this->pdo->rollBack();
                } catch (Throwable) {
                    // Preserve the safe original failure.
                }
            }
            throw $exception;
        }
    }

    private function removeExpired(string $now): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $this->pdo->exec('DELETE FROM summary_rate_limits WHERE expires_at <= UTC_TIMESTAMP()');
            return;
        }
        $statement = $this->pdo->prepare('DELETE FROM summary_rate_limits WHERE expires_at <= :now');
        $statement->execute(['now' => $now]);
    }

    private function incrementBucket(string $type, string $hash, string $now, string $expires): void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $statement = $this->pdo->prepare(
                'INSERT INTO summary_rate_limits ' .
                '(bucket_hash, bucket_type, request_count, window_started_at, expires_at, updated_at) ' .
                'VALUES (:bucket_hash, :bucket_type, 1, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), UTC_TIMESTAMP()) ' .
                'ON DUPLICATE KEY UPDATE ' .
                'request_count = IF(expires_at <= UTC_TIMESTAMP(), 1, request_count + 1), ' .
                'window_started_at = IF(expires_at <= UTC_TIMESTAMP(), UTC_TIMESTAMP(), window_started_at), ' .
                'expires_at = IF(expires_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), expires_at), ' .
                'updated_at = UTC_TIMESTAMP()'
            );
            $statement->execute(['bucket_hash' => $hash, 'bucket_type' => $type]);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO summary_rate_limits ' .
            '(bucket_hash, bucket_type, request_count, window_started_at, expires_at, updated_at) ' .
            'VALUES (:bucket_hash, :bucket_type, 1, :now, :expires, :now) ' .
            'ON CONFLICT(bucket_hash) DO UPDATE SET ' .
            'request_count = CASE WHEN expires_at <= excluded.window_started_at THEN 1 ELSE request_count + 1 END, ' .
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

    private function bucketCount(string $hash): int
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare('SELECT request_count FROM summary_rate_limits WHERE bucket_hash = :hash' . $lock);
        $statement->execute(['hash' => $hash]);
        return (int) $statement->fetchColumn();
    }
}
