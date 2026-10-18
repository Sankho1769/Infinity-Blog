<?php
declare(strict_types=1);

namespace InfinityBlog\Social;

use PDO;
use Throwable;

/**
 * Service for calculating and evaluating the earned "Author" recognition badge.
 *
 * Qualification Criteria:
 * - Must have at least 1 eligible, published article (posts.status = 'published').
 * - Account must be active (users.status = 'active').
 * - AND at least one of the following conditions must be met:
 *     1. 10 or more followers (user_follows).
 *     2. Average rating >= 4.5 across eligible published content, with at least 5 valid ratings (post_scores).
 *
 * Safeguards:
 * - Self-ratings are strictly excluded.
 * - Duplicate scores from the same user are not counted.
 * - Inactive or suspended accounts never qualify.
 * - The badge is a recognition label, NOT an RBAC authorization role.
 */
final class AuthorBadgeService
{
    public const FOLLOWER_THRESHOLD = 10;
    public const MIN_RATINGS_COUNT = 5;
    public const MIN_AVERAGE_RATING = 4.5;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if a single user qualifies for the earned Author badge.
     */
    public function qualifiesForAuthorBadge(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $status = $this->getAuthorBadgeStatus($userId);
        return $status['qualified'];
    }

    /**
     * Get a detailed breakdown of badge qualification for a user.
     *
     * @return array{
     *   user_id: int,
     *   qualified: bool,
     *   is_active: bool,
     *   published_posts_count: int,
     *   follower_count: int,
     *   total_ratings: int,
     *   average_rating: float,
     *   meets_published_req: bool,
     *   meets_follower_req: bool,
     *   meets_rating_req: bool,
     *   reasons: list<string>
     * }
     */
    public function getAuthorBadgeStatus(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptyStatus($userId);
        }

        try {
            // 1. Verify user account status
            $uStmt = $this->pdo->prepare('SELECT id, status FROM users WHERE id = :id LIMIT 1');
            $uStmt->execute([':id' => $userId]);
            $user = $uStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || ($user['status'] ?? '') !== 'active') {
                return $this->emptyStatus($userId, false);
            }

            // 2. Count published articles
            $postStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM posts WHERE author_id = :uid AND status = \'published\''
            );
            $postStmt->execute([':uid' => $userId]);
            $publishedCount = (int) $postStmt->fetchColumn();

            $meetsPublishedReq = ($publishedCount >= 1);

            // 3. Count followers
            $followStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM user_follows WHERE following_id = :uid'
            );
            $followStmt->execute([':uid' => $userId]);
            $followerCount = (int) $followStmt->fetchColumn();

            $meetsFollowerReq = ($followerCount >= self::FOLLOWER_THRESHOLD);

            // 4. Calculate total valid ratings and average score for published content (exclude self-ratings and inactive raters)
            $scoreStmt = $this->pdo->prepare(
                'SELECT COUNT(s.id) AS total_ratings, COALESCE(AVG(s.score), 0) AS avg_score ' .
                'FROM post_scores s ' .
                'JOIN posts p ON p.id = s.post_id ' .
                'JOIN users ru ON ru.id = s.user_id AND ru.status = \'active\' ' .
                'WHERE p.author_id = :uid AND p.status = \'published\' AND s.user_id != :uid'
            );
            $scoreStmt->execute([':uid' => $userId]);
            $scoreRow = $scoreStmt->fetch(PDO::FETCH_ASSOC);

            $totalRatings = (int) ($scoreRow['total_ratings'] ?? 0);
            $avgScore = round((float) ($scoreRow['avg_score'] ?? 0.0), 2);

            $meetsRatingReq = ($totalRatings >= self::MIN_RATINGS_COUNT && $avgScore >= self::MIN_AVERAGE_RATING);

            $reasons = [];
            if ($meetsFollowerReq) {
                $reasons[] = "Has {$followerCount} followers (threshold: " . self::FOLLOWER_THRESHOLD . ')';
            }
            if ($meetsRatingReq) {
                $reasons[] = "Has an average rating of {$avgScore}/5 across {$totalRatings} reader ratings (threshold: " . self::MIN_AVERAGE_RATING . ' avg with ' . self::MIN_RATINGS_COUNT . ' ratings)';
            }

            $qualified = $meetsPublishedReq && ($meetsFollowerReq || $meetsRatingReq);

            return [
                'user_id' => $userId,
                'qualified' => $qualified,
                'is_active' => true,
                'published_posts_count' => $publishedCount,
                'follower_count' => $followerCount,
                'total_ratings' => $totalRatings,
                'average_rating' => $avgScore,
                'meets_published_req' => $meetsPublishedReq,
                'meets_follower_req' => $meetsFollowerReq,
                'meets_rating_req' => $meetsRatingReq,
                'reasons' => $reasons,
            ];
        } catch (Throwable) {
            return $this->emptyStatus($userId);
        }
    }

    /**
     * Efficient batch evaluation of badge qualification for multiple user IDs.
     *
     * @param list<int> $userIds
     * @return array<int, bool> Map of user_id => bool (whether they qualify)
     */
    public function getBadgeStatusesForUsers(array $userIds): array
    {
        $uniqueIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($uniqueIds === []) {
            return [];
        }

        $results = [];
        foreach ($uniqueIds as $id) {
            $results[$id] = false;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));

            // 1. Filter active users
            $uStmt = $this->pdo->prepare(
                "SELECT id FROM users WHERE id IN ({$placeholders}) AND status = 'active'"
            );
            $uStmt->execute($uniqueIds);
            $activeIds = array_map('intval', $uStmt->fetchAll(PDO::FETCH_COLUMN));

            if ($activeIds === []) {
                return $results;
            }

            $activePlaceholders = implode(',', array_fill(0, count($activeIds), '?'));

            // 2. Count published posts per active user
            $postStmt = $this->pdo->prepare(
                "SELECT author_id, COUNT(*) AS cnt FROM posts " .
                "WHERE author_id IN ({$activePlaceholders}) AND status = 'published' " .
                "GROUP BY author_id"
            );
            $postStmt->execute($activeIds);
            $postCounts = [];
            foreach ($postStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $postCounts[(int) $row['author_id']] = (int) $row['cnt'];
            }

            // Filter users who meet the published content requirement (>= 1 post)
            $eligibleAuthors = array_values(array_filter($activeIds, static fn(int $id): bool => ($postCounts[$id] ?? 0) >= 1));
            if ($eligibleAuthors === []) {
                return $results;
            }

            $eligiblePlaceholders = implode(',', array_fill(0, count($eligibleAuthors), '?'));

            // 3. Count followers
            $followStmt = $this->pdo->prepare(
                "SELECT following_id, COUNT(*) AS cnt FROM user_follows " .
                "WHERE following_id IN ({$eligiblePlaceholders}) " .
                "GROUP BY following_id"
            );
            $followStmt->execute($eligibleAuthors);
            $followerCounts = [];
            foreach ($followStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $followerCounts[(int) $row['following_id']] = (int) $row['cnt'];
            }

            // 4. Calculate ratings per author (excluding self-ratings and inactive raters)
            $scoreStmt = $this->pdo->prepare(
                "SELECT p.author_id, COUNT(s.id) AS total_ratings, COALESCE(AVG(s.score), 0) AS avg_score " .
                "FROM post_scores s " .
                "JOIN posts p ON p.id = s.post_id " .
                "JOIN users ru ON ru.id = s.user_id AND ru.status = 'active' " .
                "WHERE p.author_id IN ({$eligiblePlaceholders}) AND p.status = 'published' AND s.user_id != p.author_id " .
                "GROUP BY p.author_id"
            );
            $scoreStmt->execute($eligibleAuthors);
            $ratingsData = [];
            foreach ($scoreStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ratingsData[(int) $row['author_id']] = [
                    'count' => (int) $row['total_ratings'],
                    'avg' => round((float) $row['avg_score'], 2),
                ];
            }

            // Determine qualification
            foreach ($eligibleAuthors as $authorId) {
                $fCount = $followerCounts[$authorId] ?? 0;
                $meetsFollower = ($fCount >= self::FOLLOWER_THRESHOLD);

                $rCount = $ratingsData[$authorId]['count'] ?? 0;
                $rAvg = $ratingsData[$authorId]['avg'] ?? 0.0;
                $meetsRating = ($rCount >= self::MIN_RATINGS_COUNT && $rAvg >= self::MIN_AVERAGE_RATING);

                $results[$authorId] = ($meetsFollower || $meetsRating);
            }

            return $results;
        } catch (Throwable) {
            return $results;
        }
    }

    /**
     * Render the tasteful HTML badge snippet for qualifying users.
     */
    public static function renderBadgeHtml(bool $qualified): string
    {
        if (!$qualified) {
            return '';
        }

        return '<span class="badge-author-earned" title="Recognized Author" aria-label="Recognized Author">' .
               '<span class="badge-author-icon" aria-hidden="true">✍</span> Author</span>';
    }

    /**
     * @return array{
     *   user_id: int,
     *   qualified: bool,
     *   is_active: bool,
     *   published_posts_count: int,
     *   follower_count: int,
     *   total_ratings: int,
     *   average_rating: float,
     *   meets_published_req: bool,
     *   meets_follower_req: bool,
     *   meets_rating_req: bool,
     *   reasons: list<string>
     * }
     */
    private function emptyStatus(int $userId, bool $isActive = false): array
    {
        return [
            'user_id' => $userId,
            'qualified' => false,
            'is_active' => $isActive,
            'published_posts_count' => 0,
            'follower_count' => 0,
            'total_ratings' => 0,
            'average_rating' => 0.0,
            'meets_published_req' => false,
            'meets_follower_req' => false,
            'meets_rating_req' => false,
            'reasons' => [],
        ];
    }
}
