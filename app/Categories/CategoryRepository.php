<?php
declare(strict_types=1);

namespace InfinityBlog\Categories;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class CategoryRepository
{
    private const MAX_SLUG_ATTEMPTS = 1000;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return list<array{id: int, name: string, slug: string, description: ?string}> */
    public function listActive(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, slug, description FROM categories WHERE is_active = 1 ORDER BY name, id'
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{id: int, name: string, slug: string, description: ?string}|null */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, slug, description FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
        ] : null;
    }

    /** @return list<array{id: int, name: string, slug: string, description: ?string, post_count: int}> */
    public function listActiveWithCounts(): array
    {
        $statement = $this->pdo->query(
            "SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) AS post_count " .
            "FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status = 'published' " .
            "WHERE c.is_active = 1 GROUP BY c.id, c.name, c.slug, c.description ORDER BY c.name, c.id"
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
            'post_count' => (int) $row['post_count'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{id: int, name: string, slug: string, description: ?string, is_active: bool, post_count: int}> */
    public function listAdmin(): array
    {
        $statement = $this->pdo->query(
            'SELECT c.id, c.name, c.slug, c.description, c.is_active, COUNT(p.id) AS post_count ' .
            'FROM categories c LEFT JOIN posts p ON p.category_id = c.id ' .
            'GROUP BY c.id ORDER BY c.name, c.id'
        );

        return array_map(static fn (array $row): array => self::mapAdminRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{id: int, name: string, slug: string, description: ?string, is_active: bool, post_count: int}|null */
    public function findAdminCategory(int $categoryId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.name, c.slug, c.description, c.is_active, ' .
            '(SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS post_count ' .
            'FROM categories c WHERE c.id = :id'
        );
        $statement->execute(['id' => $categoryId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapAdminRow($row) : null;
    }

    /** @return array{id: int, name: string, slug: string, description: ?string, is_active: bool, post_count: int} */
    public function create(string $name, ?string $description, int $actorId): array
    {
        $this->begin();
        try {
            $name = self::normalizeName($name);
            if ($this->nameExists($name)) {
                throw new CategoryConflictException(CategoryConflictException::NAME_EXISTS);
            }

            $baseSlug = self::slugBase($name);
            $insert = $this->pdo->prepare(
                'INSERT INTO categories (name, slug, description, is_active, created_by) ' .
                'VALUES (:name, :slug, :description, 1, :created_by)'
            );
            for ($attempt = 1; $attempt <= self::MAX_SLUG_ATTEMPTS; $attempt++) {
                $slug = self::slugCandidate($baseSlug, $attempt);
                if ($this->slugExists($slug)) {
                    continue;
                }

                try {
                    $insert->execute([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'created_by' => $actorId,
                    ]);
                } catch (PDOException $exception) {
                    if (self::isNameUniqueViolation($exception)) {
                        throw new CategoryConflictException(CategoryConflictException::NAME_EXISTS);
                    }
                    if (self::isSlugUniqueViolation($exception)) {
                        continue;
                    }
                    throw $exception;
                }

                $categoryId = (int) $this->pdo->lastInsertId();
                $this->writeAudit('category.created', $categoryId, $actorId, ['name' => $name, 'slug' => $slug]);
                $category = $this->findAdminCategory($categoryId);
                if ($category === null) {
                    throw new RuntimeException('The new category could not be loaded.');
                }
                $this->pdo->commit();

                return $category;
            }

            throw new CategoryConflictException(CategoryConflictException::SLUG_UNAVAILABLE);
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array{id: int, name: string, slug: string, description: ?string, is_active: bool, post_count: int} */
    public function update(int $categoryId, string $name, ?string $description, int $actorId): array
    {
        $this->begin();
        try {
            $current = $this->findAdminCategory($categoryId);
            if ($current === null) {
                throw new CategoryNotFoundException();
            }

            $name = self::normalizeName($name);
            if ($this->nameExists($name, $categoryId)) {
                throw new CategoryConflictException(CategoryConflictException::NAME_EXISTS);
            }

            $nameChanged = $name !== $current['name'];
            $baseSlug = $nameChanged ? self::slugBase($name) : $current['slug'];
            $update = $this->pdo->prepare(
                'UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id'
            );
            $attempts = $nameChanged ? self::MAX_SLUG_ATTEMPTS : 1;
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $slug = $nameChanged ? self::slugCandidate($baseSlug, $attempt) : $current['slug'];
                if ($nameChanged && $this->slugExists($slug, $categoryId)) {
                    continue;
                }

                try {
                    $update->execute([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'id' => $categoryId,
                    ]);
                } catch (PDOException $exception) {
                    if (self::isNameUniqueViolation($exception)) {
                        throw new CategoryConflictException(CategoryConflictException::NAME_EXISTS);
                    }
                    if ($nameChanged && self::isSlugUniqueViolation($exception)) {
                        continue;
                    }
                    throw $exception;
                }

                $this->writeAudit('category.updated', $categoryId, $actorId, ['name' => $name, 'slug' => $slug]);
                $category = $this->findAdminCategory($categoryId);
                if ($category === null) {
                    throw new CategoryNotFoundException();
                }
                $this->pdo->commit();

                return $category;
            }

            throw new CategoryConflictException(CategoryConflictException::SLUG_UNAVAILABLE);
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array{id: int, name: string, slug: string, description: ?string, is_active: bool, post_count: int} */
    public function setActive(int $categoryId, bool $active, int $actorId): array
    {
        $this->begin();
        try {
            $current = $this->findAdminCategory($categoryId);
            if ($current === null) {
                throw new CategoryNotFoundException();
            }

            $statement = $this->pdo->prepare('UPDATE categories SET is_active = :active WHERE id = :id');
            $statement->execute(['active' => $active ? 1 : 0, 'id' => $categoryId]);
            $this->writeAudit(
                $active ? 'category.activated' : 'category.deactivated',
                $categoryId,
                $actorId,
                ['slug' => $current['slug']]
            );
            $category = $this->findAdminCategory($categoryId);
            if ($category === null) {
                throw new CategoryNotFoundException();
            }
            $this->pdo->commit();

            return $category;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function delete(int $categoryId, int $actorId): void
    {
        $this->begin();
        try {
            $category = $this->findAdminCategory($categoryId);
            if ($category === null) {
                throw new CategoryNotFoundException();
            }
            if ($category['post_count'] > 0) {
                throw new CategoryConflictException(CategoryConflictException::IN_USE);
            }

            try {
                $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
                $statement->execute(['id' => $categoryId]);
            } catch (PDOException $exception) {
                if (self::isForeignKeyViolation($exception)) {
                    throw new CategoryConflictException(CategoryConflictException::IN_USE);
                }
                throw $exception;
            }

            $this->writeAudit(
                'category.deleted',
                $categoryId,
                $actorId,
                ['name' => $category['name'], 'slug' => $category['slug']]
            );
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE name = :name';
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
        }
        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $parameters = ['name' => $name];
        if ($exceptId !== null) {
            $parameters['except_id'] = $exceptId;
        }
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE slug = :slug';
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
        }
        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $parameters = ['slug' => $slug];
        if ($exceptId !== null) {
            $parameters['except_id'] = $exceptId;
        }
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function writeAudit(string $action, int $categoryId, int $actorId, array $metadata): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata) ' .
            'VALUES (:actor, :action, :entity_type, :entity_id, :metadata)'
        );
        $statement->execute([
            'actor' => $actorId,
            'action' => $action,
            'entity_type' => 'category',
            'entity_id' => $categoryId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction() || !$this->pdo->beginTransaction()) {
            throw new RuntimeException('The category operation could not begin.');
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

    private static function normalizeName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name));
        if (!is_string($normalized)) {
            throw new RuntimeException('The category name could not be normalized.');
        }

        return $normalized;
    }

    private static function slugBase(string $name): string
    {
        $transliterated = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : false;
        $source = is_string($transliterated) ? strtolower($transliterated) : '';
        $slug = preg_replace('/[^a-z0-9]+/', '-', $source);
        $slug = is_string($slug) ? trim($slug, '-') : '';
        if ($slug === '') {
            $slug = 'category';
        }

        return substr($slug, 0, 140);
    }

    private static function slugCandidate(string $base, int $attempt): string
    {
        $suffix = $attempt === 1 ? '' : '-' . $attempt;
        $prefix = substr($base, 0, 140 - strlen($suffix));
        $prefix = rtrim($prefix, '-');
        if ($prefix === '') {
            $prefix = 'category';
        }

        return $prefix . $suffix;
    }

    private static function isNameUniqueViolation(PDOException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return ($driverCode === 1062 && str_contains($message, 'uq_categories_name'))
            || ($driverCode === 19 && str_contains($message, 'unique constraint failed: categories.name'));
    }

    private static function isSlugUniqueViolation(PDOException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return ($driverCode === 1062 && str_contains($message, 'uq_categories_slug'))
            || ($driverCode === 19 && str_contains($message, 'unique constraint failed: categories.slug'));
    }

    private static function isForeignKeyViolation(PDOException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return in_array($driverCode, [1451, 1452], true)
            || ($driverCode === 19 && str_contains($message, 'foreign key constraint failed'));
    }

    /** @param array<string, mixed> $row
     *  @return array{id: int, name: string, slug: string, description: ?string, is_active: bool, post_count: int}
     */
    private static function mapAdminRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
            'is_active' => (bool) $row['is_active'],
            'post_count' => (int) $row['post_count'],
        ];
    }
}
