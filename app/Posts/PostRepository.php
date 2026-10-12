<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

use PDO;
use RuntimeException;
use Throwable;

final class PostRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array{posts: list<array<string, mixed>>, total: int, dashboard_counts: array{total: int, draft: int, published: int}} */
    public function listForAuthor(int $authorId, array $filters, int $page, int $perPage): array
    {
        return $this->list($authorId, $filters, $page, $perPage, false);
    }

    /** @return array{posts: list<array<string, mixed>>, total: int, dashboard_counts: array{total: int, draft: int, published: int}} */
    public function listForAdmin(array $filters, int $page, int $perPage): array
    {
        return $this->list(null, $filters, $page, $perPage, true);
    }

    /** @param array<string, mixed> $filters
     *  @return array{posts: list<array<string, mixed>>, total: int}
     */
    public function listPublic(array $filters, int $page, int $perPage): array
    {
        [$where, $parameters] = self::where(null, $filters, 'p.status', true);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM posts p ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.title, p.content, p.category_id, c.name AS category_name, u.display_name AS author_name, ' .
            'p.summary, p.summary_status, p.summary_source_hash, p.summary_generated_at, p.status, p.published_at, p.created_at ' .
            'FROM posts p LEFT JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.author_id ' .
            $where . ' ORDER BY p.published_at DESC, p.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $posts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $content = (string) $row['content'];
            $post = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'excerpt' => self::excerpt($content),
                'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
                'category_name' => $row['category_name'] === null ? null : (string) $row['category_name'],
                'author_name' => (string) $row['author_name'],
                'summary' => self::isSummaryCurrent($row) ? (string) $row['summary'] : null,
                'published_at' => (string) $row['published_at'],
                'created_at' => (string) $row['created_at'],
            ];
            $posts[] = $post;
        }

        return ['posts' => $posts, 'total' => $total];
    }

    /** @return array{total: int, draft: int, published: int} */
    public function dashboardCounts(?int $authorId = null): array
    {
        $sql = 'SELECT COUNT(*) AS total, ' .
            "COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) AS draft, " .
            "COALESCE(SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END), 0) AS published FROM posts";
        $parameters = [];
        if ($authorId !== null) {
            $sql .= ' WHERE author_id = :author_id';
            $parameters['author_id'] = $authorId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'draft' => (int) ($row['draft'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findForAuthor(int $postId, int $authorId): ?array
    {
        return $this->find($postId, $authorId, false);
    }

    /** @return array<string, mixed>|null */
    public function findForAdmin(int $postId): ?array
    {
        return $this->find($postId, null, false);
    }

    /** @return array<string, mixed>|null */
    public function findPublished(int $postId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.author_id, p.title, p.content, p.category_id, p.summary, p.summary_status, ' .
            'p.summary_source_hash, p.summary_generated_at, p.status, p.published_at, p.created_at, p.updated_at, ' .
            'c.name AS category_name, u.display_name AS author_name ' .
            'FROM posts p LEFT JOIN categories c ON c.id = p.category_id ' .
            'JOIN users u ON u.id = p.author_id WHERE p.id = :id AND p.status = \'published\' LIMIT 1'
        );
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapDetail($row) : null;
    }

    public function isActiveCategory(int $categoryId): bool
    {
        $statement = $this->pdo->prepare('SELECT is_active FROM categories WHERE id = :id');
        $statement->execute(['id' => $categoryId]);
        return (int) $statement->fetchColumn() === 1;
    }

    /** @param array<string, mixed> $post */
    public static function isSummaryCurrent(array $post): bool
    {
        $summary = $post['summary'] ?? null;
        $sourceHash = $post['summary_source_hash'] ?? null;
        if (($post['summary_status'] ?? null) !== 'ready' || !is_string($summary) || !is_string($sourceHash)
            || !is_string($post['title'] ?? null) || !is_string($post['content'] ?? null)) {
            return false;
        }
        return hash_equals(self::sourceHash($post['title'], $post['content']), $sourceHash);
    }

    public static function sourceHash(string $title, string $content): string
    {
        return hash('sha256', $title . "\n" . $content);
    }

    /** @param array{title: string, content: string, category_id: ?int} $post */
    public function createDraft(int $authorId, array $post): array
    {
        $this->begin();
        try {
            if ($post['category_id'] !== null) {
                $this->requireActiveCategory($post['category_id'], true);
            }
            $insert = $this->pdo->prepare(
                "INSERT INTO posts (author_id, category_id, title, content, status) " .
                "VALUES (:author_id, :category_id, :title, :content, 'draft')"
            );
            $insert->execute([
                'author_id' => $authorId,
                'category_id' => $post['category_id'],
                'title' => $post['title'],
                'content' => $post['content'],
            ]);
            $postId = (int) $this->pdo->lastInsertId();
            $this->writeAudit('post.created', $postId, $authorId, ['fields' => ['title', 'content', 'category_id']]);
            $saved = $this->find($postId, $authorId, false);
            if ($saved === null) {
                throw new RuntimeException('The new post could not be loaded.');
            }
            $this->pdo->commit();

            return $saved;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @param array{title?: string, content?: string, category_id?: ?int} $changes
     *  @return array<string, mixed>
     */
    public function updateForAuthor(int $postId, int $authorId, array $changes): array
    {
        return $this->updateAuthorized($postId, $authorId, $authorId, $changes);
    }

    /** @param array{title?: string, content?: string, category_id?: ?int} $changes
     *  @return array<string, mixed>
     */
    public function updateForAdmin(int $postId, int $adminId, array $changes): array
    {
        return $this->updateAuthorized($postId, null, $adminId, $changes);
    }

    /** @return array<string, mixed> */
    public function publishForAuthor(int $postId, int $authorId): array
    {
        return $this->changePublication($postId, $authorId, $authorId, true);
    }

    /** @return array<string, mixed> */
    public function publishForAdmin(int $postId, int $adminId): array
    {
        return $this->changePublication($postId, null, $adminId, true);
    }

    /** @return array<string, mixed> */
    public function unpublishForAuthor(int $postId, int $authorId): array
    {
        return $this->changePublication($postId, $authorId, $authorId, false);
    }

    /** @return array<string, mixed> */
    public function unpublishForAdmin(int $postId, int $adminId): array
    {
        return $this->changePublication($postId, null, $adminId, false);
    }

    public function deleteForAuthor(int $postId, int $authorId): void
    {
        $this->deleteAuthorized($postId, $authorId, $authorId);
    }

    public function deleteForAdmin(int $postId, int $adminId): void
    {
        $this->deleteAuthorized($postId, null, $adminId);
    }

    /**
     * Store a generated summary only if the article source still matches the hash used by the provider.
     *
     * @return array{summary: string, summary_status: string, summary_generated_at: string}|null
     */
    public function saveSummaryIfCurrent(int $postId, ?int $ownerId, int $actorId, string $sourceHash, string $summary): ?array
    {
        $this->begin();
        try {
            $current = $this->find($postId, $ownerId, true);
            if ($current === null) {
                throw new PostNotFoundException();
            }
            $currentHash = self::sourceHash($current['title'], $current['content']);
            if (!hash_equals($sourceHash, $currentHash)) {
                $this->writeAudit('post.summary_superseded', $postId, $actorId, [
                    'requested_source_hash' => $sourceHash,
                    'current_source_hash' => $currentHash,
                ]);
                $this->pdo->commit();
                return null;
            }
            $generatedAt = gmdate('Y-m-d H:i:s');
            $updateSql =
                "UPDATE posts SET summary = :summary, summary_status = 'ready', summary_source_hash = :source_hash, " .
                'summary_generated_at = :generated_at, updated_at = :updated_at WHERE id = :id';
            $parameters = [
                'summary' => $summary,
                'source_hash' => $sourceHash,
                'generated_at' => $generatedAt,
                'updated_at' => $generatedAt,
                'id' => $postId,
            ];
            if ($ownerId !== null) {
                $updateSql .= ' AND author_id = :owner_id';
                $parameters['owner_id'] = $ownerId;
            }
            $update = $this->pdo->prepare($updateSql);
            $update->execute($parameters);
            $this->writeAudit('post.summary_generated', $postId, $actorId, [
                'source_hash' => $sourceHash,
                'summary_characters' => self::characterCount($summary),
            ]);
            $this->pdo->commit();
            return ['summary' => $summary, 'summary_status' => 'ready', 'summary_generated_at' => $generatedAt];
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** Mark a failed result only if the same source revision is still present. */
    public function markSummaryFailureIfCurrent(int $postId, ?int $ownerId, int $actorId, string $sourceHash): ?string
    {
        $this->begin();
        try {
            $current = $this->find($postId, $ownerId, true);
            if ($current === null) {
                throw new PostNotFoundException();
            }
            $currentHash = self::sourceHash($current['title'], $current['content']);
            if (!hash_equals($sourceHash, $currentHash)) {
                $this->writeAudit('post.summary_superseded', $postId, $actorId, [
                    'requested_source_hash' => $sourceHash,
                    'current_source_hash' => $currentHash,
                ]);
                $this->pdo->commit();
                return null;
            }
            $status = $current['summary'] === null ? 'failed' : 'stale';
            $updateSql = 'UPDATE posts SET summary_status = :status, updated_at = :updated_at WHERE id = :id';
            $parameters = ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $postId];
            if ($ownerId !== null) {
                $updateSql .= ' AND author_id = :owner_id';
                $parameters['owner_id'] = $ownerId;
            }
            $update = $this->pdo->prepare($updateSql);
            $update->execute($parameters);
            $this->writeAudit('post.summary_failed', $postId, $actorId, [
                'source_hash' => $sourceHash,
                'retained_prior_summary' => $current['summary'] !== null,
            ]);
            $this->pdo->commit();
            return $status;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @param array{title?: string, content?: string, category_id?: ?int} $changes
     *  @return array<string, mixed>
     */
    private function updateAuthorized(int $postId, ?int $ownerId, int $actorId, array $changes): array
    {
        $this->begin();
        try {
            $current = $this->find($postId, $ownerId, true);
            if ($current === null) {
                throw new PostNotFoundException();
            }
            $nextCategoryId = array_key_exists('category_id', $changes)
                ? $changes['category_id']
                : $current['category_id'];
            if ($nextCategoryId !== null && $nextCategoryId !== $current['category_id']) {
                $this->requireActiveCategory($nextCategoryId, true);
            }
            if ($current['status'] === 'published' && $nextCategoryId === null) {
                throw new PostValidationException(['category_id' => 'Published posts require a category.']);
            }
            $sets = [];
            $parameters = ['id' => $postId, 'updated_at' => gmdate('Y-m-d H:i:s')];
            if ($ownerId !== null) {
                $parameters['author_id'] = $ownerId;
            }
            foreach (['title', 'content', 'category_id'] as $field) {
                if (array_key_exists($field, $changes)) {
                    $sets[] = $field . ' = :' . $field;
                    $parameters[$field] = $changes[$field];
                }
            }
            if (array_key_exists('title', $changes) || array_key_exists('content', $changes)) {
                $sets[] = "summary_status = CASE WHEN summary IS NOT NULL THEN 'stale' ELSE summary_status END";
            }
            $sets[] = 'updated_at = :updated_at';
            $updateSql = 'UPDATE posts SET ' . implode(', ', $sets) . ' WHERE id = :id';
            if ($ownerId !== null) {
                $updateSql .= ' AND author_id = :author_id';
            }
            $update = $this->pdo->prepare($updateSql);
            $update->execute($parameters);
            $this->writeAudit('post.updated', $postId, $actorId, ['fields' => array_keys($changes)]);
            $saved = $this->find($postId, $ownerId, false);
            if ($saved === null) {
                throw new PostNotFoundException();
            }
            $this->pdo->commit();

            return $saved;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function changePublication(int $postId, ?int $ownerId, int $actorId, bool $publish): array
    {
        $this->begin();
        try {
            $current = $this->find($postId, $ownerId, true);
            if ($current === null) {
                throw new PostNotFoundException();
            }
            $updatedAt = gmdate('Y-m-d H:i:s');
            if ($publish) {
                if (trim((string) $current['title']) === '' || trim((string) $current['content']) === '') {
                    throw new PostValidationException(['post' => 'A title and article body are required before publishing.']);
                }
                if ($current['category_id'] === null) {
                    throw new PostValidationException(['category_id' => 'Choose an active category before publishing.']);
                }
                $this->requireActiveCategory((int) $current['category_id'], true);
                $updateSql = "UPDATE posts SET status = 'published', published_at = :published_at, updated_at = :updated_at WHERE id = :id";
                $parameters = ['published_at' => $updatedAt, 'updated_at' => $updatedAt, 'id' => $postId];
                if ($ownerId !== null) {
                    $updateSql .= ' AND author_id = :author_id';
                    $parameters['author_id'] = $ownerId;
                }
                $action = 'post.published';
            } else {
                $updateSql = "UPDATE posts SET status = 'draft', published_at = NULL, updated_at = :updated_at WHERE id = :id";
                $parameters = ['updated_at' => $updatedAt, 'id' => $postId];
                if ($ownerId !== null) {
                    $updateSql .= ' AND author_id = :author_id';
                    $parameters['author_id'] = $ownerId;
                }
                $action = 'post.unpublished';
            }
            $update = $this->pdo->prepare($updateSql);
            $update->execute($parameters);
            $this->writeAudit($action, $postId, $actorId, []);
            $saved = $this->find($postId, $ownerId, false);
            if ($saved === null) {
                throw new PostNotFoundException();
            }
            $this->pdo->commit();
            return $saved;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function deleteAuthorized(int $postId, ?int $ownerId, int $actorId): void
    {
        $this->begin();
        try {
            $current = $this->find($postId, $ownerId, true);
            if ($current === null) {
                throw new PostNotFoundException();
            }
            $sql = 'DELETE FROM posts WHERE id = :id';
            $parameters = ['id' => $postId];
            if ($ownerId !== null) {
                $sql .= ' AND author_id = :author_id';
                $parameters['author_id'] = $ownerId;
            }
            $delete = $this->pdo->prepare($sql);
            $delete->execute($parameters);
            $this->writeAudit('post.deleted', $postId, $actorId, ['status' => $current['status']]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $filters
     *  @return array{posts: list<array<string, mixed>>, total: int, dashboard_counts: array{total: int, draft: int, published: int}}
     */
    private function list(?int $authorId, array $filters, int $page, int $perPage, bool $includeSummary): array
    {
        [$where, $parameters] = self::where($authorId, $filters, 'p.status', false);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM posts p ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT p.id, p.author_id, p.title, SUBSTR(p.content, 1, 1200) AS preview, p.category_id, ' .
            'c.name AS category_name, p.status, p.published_at, p.created_at, p.updated_at, ' .
            'u.display_name AS author_name' . ($includeSummary ? ', p.summary, p.summary_status, p.summary_generated_at' : '') .
            ' FROM posts p LEFT JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.author_id ' .
            $where . ' ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $posts = array_map(static function (array $row) use ($includeSummary): array {
            $post = [
                'id' => (int) $row['id'],
                'author_id' => (int) $row['author_id'],
                'author_name' => (string) $row['author_name'],
                'title' => (string) $row['title'],
                'excerpt' => self::excerpt((string) $row['preview']),
                'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
                'category_name' => $row['category_name'] === null ? null : (string) $row['category_name'],
                'status' => (string) $row['status'],
                'published_at' => $row['published_at'] === null ? null : (string) $row['published_at'],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ];
            if ($includeSummary) {
                $post['summary'] = $row['summary'] === null ? null : (string) $row['summary'];
                $post['summary_status'] = (string) $row['summary_status'];
                $post['summary_generated_at'] = $row['summary_generated_at'] === null
                    ? null
                    : (string) $row['summary_generated_at'];
            }
            return $post;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'posts' => $posts,
            'total' => $total,
            'dashboard_counts' => $this->dashboardCounts($authorId),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array{string, array<string, mixed>}
     */
    private static function where(?int $authorId, array $filters, string $statusColumn, bool $public): array
    {
        $conditions = [];
        $parameters = [];
        if ($authorId !== null) {
            $conditions[] = 'p.author_id = :scope_author_id';
            $parameters['scope_author_id'] = $authorId;
        }
        if ($public) {
            $conditions[] = "p.status = 'published'";
        } elseif (isset($filters['status'])) {
            $conditions[] = $statusColumn . ' = :filter_status';
            $parameters['filter_status'] = $filters['status'];
        }
        if (isset($filters['category_id'])) {
            $conditions[] = $public
                ? 'p.category_id = :filter_category_id AND EXISTS (SELECT 1 FROM categories active_category WHERE active_category.id = p.category_id AND active_category.is_active = 1)'
                : 'p.category_id = :filter_category_id';
            $parameters['filter_category_id'] = $filters['category_id'];
        }
        if (isset($filters['author_id'])) {
            $conditions[] = 'p.author_id = :filter_author_id';
            $parameters['filter_author_id'] = (int) $filters['author_id'];
        }
        if (isset($filters['q'])) {
            $conditions[] = '(INSTR(LOWER(p.title), LOWER(:query_title)) > 0 OR INSTR(LOWER(p.content), LOWER(:query_content)) > 0)';
            $parameters['query_title'] = $filters['q'];
            $parameters['query_content'] = $filters['q'];
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @return array<string, mixed>|null */
    private function find(int $postId, ?int $authorId, bool $forUpdate): ?array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $forUpdate && $driver === 'mysql' ? ' FOR UPDATE' : '';
        $sql = 'SELECT p.id, p.author_id, p.title, p.content, p.category_id, p.summary, p.summary_status, ' .
            'p.summary_source_hash, p.summary_generated_at, p.status, p.published_at, p.created_at, p.updated_at, ' .
            'c.name AS category_name, u.display_name AS author_name FROM posts p ' .
            'LEFT JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.author_id WHERE p.id = :id';
        $parameters = ['id' => $postId];
        if ($authorId !== null) {
            $sql .= ' AND p.author_id = :author_id';
            $parameters['author_id'] = $authorId;
        }
        $sql .= ' LIMIT 1' . $lock;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapDetail($row) : null;
    }

    private function requireActiveCategory(int $categoryId, bool $forUpdate): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $forUpdate && $driver === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare('SELECT is_active FROM categories WHERE id = :id LIMIT 1' . $lock);
        $statement->execute(['id' => $categoryId]);
        $active = $statement->fetchColumn();
        if ($active === false || (int) $active !== 1) {
            throw new PostValidationException(['category_id' => 'Choose an existing active category.']);
        }
    }

    private function writeAudit(string $action, int $postId, int $actorId, array $metadata): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata) ' .
            'VALUES (:actor_id, :action, \'post\', :entity_id, :metadata)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'entity_id' => $postId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
            throw new RuntimeException('The post operation could not begin.');
        }
    }

    private function rollback(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }
        try {
            $this->pdo->rollBack();
        } catch (Throwable) {
            // Preserve the original failure; callers receive only a generic API error.
        }
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private static function mapDetail(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'author_id' => (int) $row['author_id'],
            'author_name' => (string) $row['author_name'],
            'title' => (string) $row['title'],
            'content' => (string) $row['content'],
            'category_id' => $row['category_id'] === null ? null : (int) $row['category_id'],
            'category_name' => $row['category_name'] === null ? null : (string) $row['category_name'],
            'summary' => $row['summary'] === null ? null : (string) $row['summary'],
            'summary_status' => (string) $row['summary_status'],
            'summary_source_hash' => $row['summary_source_hash'] === null ? null : (string) $row['summary_source_hash'],
            'summary_generated_at' => $row['summary_generated_at'] === null ? null : (string) $row['summary_generated_at'],
            'status' => (string) $row['status'],
            'published_at' => $row['published_at'] === null ? null : (string) $row['published_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private static function excerpt(string $preview): string
    {
        $preview = trim($preview);
        if (function_exists('mb_substr')) {
            return mb_substr($preview, 0, 220, 'UTF-8');
        }
        if (preg_match('/^.{0,220}/us', $preview, $matches) === 1) {
            return $matches[0];
        }
        return substr($preview, 0, 220);
    }

    private static function characterCount(string $value): int
    {
        $count = preg_match_all('/./us', $value);
        return $count === false ? strlen($value) : $count;
    }
}
