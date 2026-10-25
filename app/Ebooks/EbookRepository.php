<?php
declare(strict_types=1);

namespace InfinityBlog\Ebooks;

use InfinityBlog\Support\UserIdentityHelper;
use InvalidArgumentException;
use PDO;
use Throwable;

final class EbookRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * List published eBooks with search, category, community, and format filters.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, total_pages: int}
     */
    public function listPublic(array $filters = [], int $page = 1, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["b.status = 'published'"];
        $params = [];

        if (!empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $where[] = '(b.title LIKE :s_title OR b.author_name LIKE :s_author OR b.description LIKE :s_desc OR b.tags LIKE :s_tags)';
            $params['s_title'] = '%' . $s . '%';
            $params['s_author'] = '%' . $s . '%';
            $params['s_desc'] = '%' . $s . '%';
            $params['s_tags'] = '%' . $s . '%';
        }

        if (!empty($filters['category_id']) && (int) $filters['category_id'] > 0) {
            $where[] = 'b.category_id = :cat_id';
            $params['cat_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['community_id']) && (int) $filters['community_id'] > 0) {
            $where[] = 'b.community_id = :comm_id';
            $params['comm_id'] = (int) $filters['community_id'];
        }

        if (!empty($filters['format']) && in_array(strtolower((string) $filters['format']), ['pdf', 'epub'], true)) {
            $where[] = 'b.file_format = :fmt';
            $params['fmt'] = strtolower((string) $filters['format']);
        }

        if (!empty($filters['language'])) {
            $where[] = 'b.language = :lang';
            $params['lang'] = strtolower((string) $filters['language']);
        }

        $whereSql = implode(' AND ', $where);

        // Count query
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ebooks b WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Sort order
        $sort = $filters['sort'] ?? 'recent';
        $orderBy = match ($sort) {
            'popular' => 'b.read_count DESC, b.created_at DESC',
            'title' => 'b.title ASC',
            'downloads' => 'b.download_count DESC, b.created_at DESC',
            default => 'b.created_at DESC',
        };

        $sql = "SELECT b.*, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug, " .
            "cr.name AS community_name, cr.slug AS community_slug " .
            "FROM ebooks b " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "LEFT JOIN chat_rooms cr ON b.community_id = cr.id " .
            "WHERE {$whereSql} " .
            "ORDER BY {$orderBy} " .
            "LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(fn($row) => $this->formatBookRow($row), $rows);

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Get featured eBooks.
     *
     * @return list<array<string, mixed>>
     */
    public function getFeatured(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug, " .
            "cr.name AS community_name, cr.slug AS community_slug " .
            "FROM ebooks b " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "LEFT JOIN chat_rooms cr ON b.community_id = cr.id " .
            "WHERE b.status = 'published' AND b.featured = 1 " .
            "ORDER BY b.read_count DESC, b.created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn($row) => $this->formatBookRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get recently added eBooks.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentlyAdded(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug, " .
            "cr.name AS community_name, cr.slug AS community_slug " .
            "FROM ebooks b " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "LEFT JOIN chat_rooms cr ON b.community_id = cr.id " .
            "WHERE b.status = 'published' " .
            "ORDER BY b.created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn($row) => $this->formatBookRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get popular reads based on genuine read count tracking.
     *
     * @return list<array<string, mixed>>
     */
    public function getPopular(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug, " .
            "cr.name AS community_name, cr.slug AS community_slug " .
            "FROM ebooks b " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "LEFT JOIN chat_rooms cr ON b.community_id = cr.id " .
            "WHERE b.status = 'published' " .
            "ORDER BY b.read_count DESC, b.created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn($row) => $this->formatBookRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find book by ID regardless of status (internal/admin lookup).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug, " .
            "cr.name AS community_name, cr.slug AS community_slug " .
            "FROM ebooks b " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "LEFT JOIN chat_rooms cr ON b.community_id = cr.id " .
            "WHERE b.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatBookRow($row) : null;
    }

    /**
     * Find published book, or allow author/admin access.
     */
    public function findPublished(int $id, ?int $viewerId = null, bool $isAdmin = false): ?array
    {
        $book = $this->findById($id);
        if ($book === null) {
            return null;
        }

        if ($book['status'] === 'published') {
            return $book;
        }

        // Non-published book (pending, rejected, hidden): visible only to verified owner or admin
        if ($isAdmin || ($viewerId !== null && !empty($book['user_id']) && (int) $book['user_id'] === $viewerId)) {
            return $book;
        }

        return null;
    }

    /**
     * Create a new eBook record.
     * Supports both authenticated members and anonymous contributions.
     *
     * @param array<string, mixed> $data
     * @throws EbookValidationException
     */
    public function create(?int $userId, array $data): int
    {
        $errors = [];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Please enter a title for the eBook.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Title cannot exceed 255 characters.';
        }

        $author = trim((string) ($data['author_name'] ?? ''));
        if ($author === '') {
            $errors['author_name'] = 'Please provide the author name.';
        } elseif (mb_strlen($author) > 255) {
            $errors['author_name'] = 'Author name cannot exceed 255 characters.';
        }

        $desc = trim((string) ($data['description'] ?? ''));
        if ($desc === '') {
            $errors['description'] = 'Please provide a short description or synopsis.';
        } elseif (mb_strlen($desc) > 5000) {
            $errors['description'] = 'Description cannot exceed 5000 characters.';
        }

        $submitterName = isset($data['submitter_name']) && trim((string) $data['submitter_name']) !== ''
            ? trim(strip_tags((string) $data['submitter_name']))
            : null;

        $submitterEmail = isset($data['submitter_email']) && trim((string) $data['submitter_email']) !== ''
            ? trim((string) $data['submitter_email'])
            : null;

        if ($submitterEmail !== null && !filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['submitter_email'] = 'Please provide a valid contact email address.';
        }

        $filePath = trim((string) ($data['file_path'] ?? ''));
        if ($filePath === '') {
            $errors['file_path'] = 'An eBook file must be uploaded.';
        }

        $format = strtolower(trim((string) ($data['file_format'] ?? $data['format'] ?? '')));
        if (!in_array($format, ['pdf', 'epub'], true)) {
            $errors['file_format'] = 'Supported formats are PDF and EPUB only.';
        }

        if ($errors !== []) {
            throw new EbookValidationException($errors);
        }

        $slug = $this->generateUniqueSlug($title);
        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $communityId = !empty($data['community_id']) ? (int) $data['community_id'] : null;
        $coverPath = !empty($data['cover_image_path']) ? (string) $data['cover_image_path'] : (!empty($data['cover_path']) ? (string) $data['cover_path'] : null);
        $fileSize = (int) ($data['file_size'] ?? 0);
        $language = !empty($data['language']) ? strtolower(substr(trim((string) $data['language']), 0, 10)) : 'en';
        $tags = !empty($data['tags']) ? trim((string) $data['tags']) : null;
        $allowDownload = isset($data['allow_download']) ? (int) (bool) $data['allow_download'] : 1;
        $status = !empty($data['status']) && in_array($data['status'], ['published', 'pending', 'rejected', 'hidden'], true)
            ? (string) $data['status']
            : 'pending';
        $featured = !empty($data['featured']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO ebooks (user_id, submitter_name, submitter_email, title, slug, author_name, description, category_id, community_id, ' .
            'file_path, file_format, file_size, cover_image_path, language, tags, allow_download, status, featured, created_at, updated_at) ' .
            'VALUES (:uid, :sub_name, :sub_email, :title, :slug, :author, :descr, :cat_id, :comm_id, :fpath, :format, :fsize, :cover, :lang, :tags, :allow_dl, :status, :feat, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            ':uid' => $userId,
            ':sub_name' => $submitterName,
            ':sub_email' => $submitterEmail,
            ':title' => $title,
            ':slug' => $slug,
            ':author' => $author,
            ':descr' => $desc,
            ':cat_id' => $categoryId,
            ':comm_id' => $communityId,
            ':fpath' => $filePath,
            ':format' => $format,
            ':fsize' => $fileSize,
            ':cover' => $coverPath,
            ':lang' => $language,
            ':tags' => $tags,
            ':allow_dl' => $allowDownload,
            ':status' => $status,
            ':feat' => $featured,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an eBook record.
     *
     * @param array<string, mixed> $data
     * @throws EbookValidationException
     */
    public function update(int $id, int $userId, array $data, bool $isAdmin = false): bool
    {
        $book = $this->findById($id);
        if ($book === null) {
            return false;
        }

        if (!$isAdmin && (int) $book['user_id'] !== $userId) {
            return false;
        }

        $updates = [];
        $params = [':id' => $id];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new EbookValidationException(['title' => 'Title cannot be empty.']);
            }
            $updates[] = 'title = :title';
            $params[':title'] = $title;
        }

        if (array_key_exists('author_name', $data)) {
            $author = trim((string) $data['author_name']);
            if ($author === '') {
                throw new EbookValidationException(['author_name' => 'Author name cannot be empty.']);
            }
            $updates[] = 'author_name = :author';
            $params[':author'] = $author;
        }

        if (array_key_exists('description', $data)) {
            $desc = trim((string) $data['description']);
            if ($desc === '') {
                throw new EbookValidationException(['description' => 'Description cannot be empty.']);
            }
            $updates[] = 'description = :descr';
            $params[':descr'] = $desc;
        }

        if (array_key_exists('category_id', $data)) {
            $updates[] = 'category_id = :cat_id';
            $params[':cat_id'] = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        }

        if (array_key_exists('community_id', $data)) {
            $updates[] = 'community_id = :comm_id';
            $params[':comm_id'] = !empty($data['community_id']) ? (int) $data['community_id'] : null;
        }

        if (array_key_exists('cover_image_path', $data)) {
            $updates[] = 'cover_image_path = :cover';
            $params[':cover'] = $data['cover_image_path'];
        }

        if (array_key_exists('tags', $data)) {
            $updates[] = 'tags = :tags';
            $params[':tags'] = $data['tags'];
        }

        if (array_key_exists('allow_download', $data)) {
            $updates[] = 'allow_download = :allow_dl';
            $params[':allow_dl'] = (int) (bool) $data['allow_download'];
        }

        if (array_key_exists('status', $data) && in_array($data['status'], ['published', 'draft', 'hidden'], true)) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if ($isAdmin && array_key_exists('featured', $data)) {
            $updates[] = 'featured = :feat';
            $params[':feat'] = (int) (bool) $data['featured'];
        }

        if ($updates === []) {
            return true;
        }

        $updates[] = 'updated_at = CURRENT_TIMESTAMP';

        $sql = 'UPDATE ebooks SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete an eBook record.
     */
    public function delete(int $id, int $userId, bool $isAdmin = false): bool
    {
        $book = $this->findById($id);
        if ($book === null) {
            return false;
        }

        if (!$isAdmin && (int) $book['user_id'] !== $userId) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM ebooks WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Increment real read count.
     */
    public function incrementReadCount(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE ebooks SET read_count = read_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Increment real download count.
     */
    public function incrementDownloadCount(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE ebooks SET download_count = download_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Update eBook status (e.g. published, hidden, rejected, pending).
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['published', 'pending', 'rejected', 'hidden', 'draft', 'archived'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE ebooks SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * List eBook submissions for moderation review.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, total_pages: int}
     */
    public function listSubmissions(string $status = 'pending', int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ebooks b WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT b.*, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, " .
            "c.name AS category_name, c.slug AS category_slug " .
            "FROM ebooks b " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "WHERE {$whereSql} " .
            "ORDER BY b.created_at DESC " .
            "LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(function (array $r): array {
            $formatted = $this->formatBookRow($r);
            // Include submitter contact for admin eyes only
            $formatted['submitter_name'] = $r['submitter_name'] ?? null;
            $formatted['submitter_email'] = $r['submitter_email'] ?? null;
            $formatted['rejection_reason'] = $r['rejection_reason'] ?? null;
            $formatted['reviewed_at'] = $r['reviewed_at'] ?? null;
            return $formatted;
        }, $rows);

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Approve a pending submission for public library catalog.
     */
    public function approveSubmission(int $id, int $reviewerId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ebooks SET status = 'published', reviewed_by = :rev, reviewed_at = CURRENT_TIMESTAMP, rejection_reason = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute([':rev' => $reviewerId, ':id' => $id]);
    }

    /**
     * Reject a submission with an optional reason.
     */
    public function rejectSubmission(int $id, int $reviewerId, ?string $reason = null): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ebooks SET status = 'rejected', reviewed_by = :rev, reviewed_at = CURRENT_TIMESTAMP, rejection_reason = :reason, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute([
            ':rev' => $reviewerId,
            ':reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : 'Submission did not meet community content or quality guidelines.',
            ':id' => $id,
        ]);
    }

    /**
     * Save reading progress for a user.
     *
     * @return array<string, mixed>
     */
    public function saveReadingProgress(
        int $userId,
        int $ebookId,
        ?int $currentPage = null,
        ?int $totalPages = null,
        int|float $progressPercent = 0,
        ?string $epubCfi = null
    ): array {
        $currentPage = $currentPage ?? 1;
        $totalPages = $totalPages ?? 1;
        $progressPercent = max(0, min(100, (int) round((float) $progressPercent)));
        $completed = ($progressPercent >= 95) ? 1 : 0;

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ebook_reading_progress ' .
                '(user_id, ebook_id, current_page, total_pages, progress_percent, epub_cfi, completed, last_read_at, created_at, updated_at) ' .
                'VALUES (:uid, :bid, :cp, :tp, :pct, :cfi, :comp, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ' .
                'ON CONFLICT(user_id, ebook_id) DO UPDATE SET ' .
                'current_page = :cp, total_pages = :tp, progress_percent = :pct, epub_cfi = :cfi, completed = :comp, ' .
                'last_read_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ebook_reading_progress ' .
                '(user_id, ebook_id, current_page, total_pages, progress_percent, epub_cfi, completed, last_read_at, created_at, updated_at) ' .
                'VALUES (:uid, :bid, :cp, :tp, :pct, :cfi, :comp, NOW(), NOW(), NOW()) ' .
                'ON DUPLICATE KEY UPDATE ' .
                'current_page = VALUES(current_page), ' .
                'total_pages = VALUES(total_pages), ' .
                'progress_percent = VALUES(progress_percent), ' .
                'epub_cfi = VALUES(epub_cfi), ' .
                'completed = VALUES(completed), ' .
                'last_read_at = NOW(), ' .
                'updated_at = NOW()'
            );
        }

        $stmt->execute([
            ':uid' => $userId,
            ':bid' => $ebookId,
            ':cp' => max(1, $currentPage),
            ':tp' => max(1, $totalPages),
            ':pct' => $progressPercent,
            ':cfi' => $epubCfi,
            ':comp' => $completed,
        ]);

        return [
            'current_page' => $currentPage,
            'last_page' => $currentPage,
            'total_pages' => $totalPages,
            'progress_percent' => $progressPercent,
            'percentage' => (float) $progressPercent,
            'completed' => (bool) $completed,
        ];
    }

    /**
     * Get reading progress for a user on a specific book.
     */
    public function getReadingProgress(int $userId, int $ebookId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT current_page, total_pages, progress_percent, epub_cfi, completed, last_read_at ' .
            'FROM ebook_reading_progress WHERE user_id = :uid AND ebook_id = :bid LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':bid' => $ebookId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['last_page'] = (int) $row['current_page'];
            $row['percentage'] = (float) $row['progress_percent'];
            return $row;
        }

        return null;
    }

    /**
     * Get continue reading books for signed-in user.
     *
     * @return list<array<string, mixed>>
     */
    public function getContinueReading(int $userId, int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, p.current_page, p.total_pages, p.progress_percent, p.last_read_at, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug " .
            "FROM ebook_reading_progress p " .
            "JOIN ebooks b ON p.ebook_id = b.id " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "WHERE p.user_id = :uid AND b.status = 'published' AND p.completed = 0 " .
            "ORDER BY p.last_read_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn($row) => $this->formatBookRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Save eBook to personal library.
     */
    public function saveBook(int $userId, int $ebookId): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_saved_ebooks (user_id, ebook_id, created_at) ' .
                'VALUES (:uid, :bid, CURRENT_TIMESTAMP) ' .
                'ON CONFLICT(user_id, ebook_id) DO NOTHING'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO user_saved_ebooks (user_id, ebook_id, created_at) ' .
                'VALUES (:uid, :bid, NOW())'
            );
        }
        $stmt->execute([':uid' => $userId, ':bid' => $ebookId]);
        return true;
    }

    /**
     * Remove eBook from personal library.
     */
    public function unsaveBook(int $userId, int $ebookId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_saved_ebooks WHERE user_id = :uid AND ebook_id = :bid');
        $stmt->execute([':uid' => $userId, ':bid' => $ebookId]);
        return false;
    }

    /**
     * Check if eBook is saved by user.
     */
    public function isBookSaved(int $userId, int $ebookId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_saved_ebooks WHERE user_id = :uid AND ebook_id = :bid LIMIT 1');
        $stmt->execute([':uid' => $userId, ':bid' => $ebookId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get all saved eBooks for a user.
     *
     * @return list<array<string, mixed>>
     */
    public function getSavedBooks(int $userId, ?string $search = null): array
    {
        $sql = "SELECT b.*, s.created_at AS saved_at, " .
            "u.display_name AS uploader_name, u.username AS uploader_username, u.avatar_path AS uploader_avatar, " .
            "c.name AS category_name, c.slug AS category_slug " .
            "FROM user_saved_ebooks s " .
            "JOIN ebooks b ON s.ebook_id = b.id " .
            "LEFT JOIN users u ON b.user_id = u.id " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "WHERE s.user_id = :uid AND b.status = 'published' ";

        $params = [':uid' => $userId];
        if ($search !== null && trim($search) !== '') {
            $sql .= "AND (b.title LIKE :s OR b.author_name LIKE :s OR b.description LIKE :s) ";
            $params[':s'] = '%' . trim($search) . '%';
        }

        $sql .= "ORDER BY s.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($row) => $this->formatBookRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * List eBooks uploaded by a specific user.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, " .
            "c.name AS category_name, c.slug AS category_slug, " .
            "cr.name AS community_name, cr.slug AS community_slug " .
            "FROM ebooks b " .
            "LEFT JOIN categories c ON b.category_id = c.id " .
            "LEFT JOIN chat_rooms cr ON b.community_id = cr.id " .
            "WHERE b.user_id = :uid " .
            "ORDER BY b.created_at DESC"
        );
        $stmt->execute([':uid' => $userId]);

        return array_map(fn($row) => $this->formatBookRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Create an eBook moderation report.
     *
     * @return array{status: string, report_id?: int, message: string}
     */
    public function createReport(int $ebookId, int $reporterId, string $reason, ?string $explanation = null): array
    {
        $book = $this->findById($ebookId);
        if ($book === null) {
            return ['status' => 'not_found', 'message' => 'eBook does not exist.'];
        }

        if ((int) $book['user_id'] === $reporterId) {
            return ['status' => 'forbidden', 'message' => 'You cannot report your own eBook.'];
        }

        $reasonsMap = [
            'copyright' => 'Copyright or intellectual-property concern',
            'inappropriate' => 'Inappropriate or adult content',
            'spam' => 'Spam or misleading content',
            'hate' => 'Hate or abusive content',
            'malicious' => 'Malicious or harmful file',
            'broken' => 'Corrupted file or false information',
            'other' => 'Other',
        ];

        $cleanReason = trim($reason);
        if (isset($reasonsMap[strtolower($cleanReason)])) {
            $cleanReason = $reasonsMap[strtolower($cleanReason)];
        } elseif (!in_array($cleanReason, array_values($reasonsMap), true)) {
            if ($cleanReason === '') {
                return ['status' => 'invalid_reason', 'message' => 'Please select a valid report reason.'];
            }
        }

        // Check for existing pending report by this reporter
        $check = $this->pdo->prepare(
            "SELECT id FROM ebook_reports WHERE ebook_id = :bid AND reporter_id = :rid AND status IN ('pending', 'under_review') LIMIT 1"
        );
        $check->execute([':bid' => $ebookId, ':rid' => $reporterId]);
        if ($check->fetchColumn() !== false) {
            return ['status' => 'already_reported', 'message' => 'You already have an active report under review for this eBook.'];
        }

        $cleanExplanation = ($explanation !== null && trim($explanation) !== '') ? mb_substr(trim($explanation), 0, 1000) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO ebook_reports (ebook_id, reporter_id, reason, explanation, status, created_at) ' .
            'VALUES (:bid, :rid, :reason, :expl, \'pending\', CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':bid' => $ebookId,
            ':rid' => $reporterId,
            ':reason' => $cleanReason,
            ':expl' => $cleanExplanation,
        ]);

        $reportId = (int) $this->pdo->lastInsertId();

        return [
            'status' => 'success',
            'id' => $reportId,
            'report_id' => $reportId,
            'reason' => $cleanReason,
            'message' => 'Thank you. Your report has been submitted to the moderation team.',
        ];
    }

    /**
     * List reports for moderation dashboard.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listReports(?string $status = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($status !== null && $status !== 'all') {
            $where[] = 'r.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ebook_reports r {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT r.*, " .
            "b.title AS ebook_title, b.author_name AS ebook_author, b.status AS ebook_status, " .
            "u.display_name AS reporter_name, u.username AS reporter_username " .
            "FROM ebook_reports r " .
            "JOIN ebooks b ON r.ebook_id = b.id " .
            "JOIN users u ON r.reporter_id = u.id " .
            "{$whereSql} " .
            "ORDER BY r.created_at DESC " .
            "LIMIT :lim OFFSET :off";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Update report status and optionally take moderation action on the eBook.
     */
    public function updateReportStatus(int $reportId, string $status, int $reviewerId, ?string $action = null): bool
    {
        $validStatuses = ['pending', 'under_review', 'reviewed', 'dismissed', 'action_taken'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        // Fetch report
        $stmt = $this->pdo->prepare('SELECT * FROM ebook_reports WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            return false;
        }

        $ebookId = (int) $report['ebook_id'];

        if ($action === 'hide') {
            $this->updateStatus($ebookId, 'hidden');
            $status = 'action_taken';
        } elseif ($action === 'restore') {
            $this->updateStatus($ebookId, 'published');
            $status = 'action_taken';
        }

        $up = $this->pdo->prepare(
            'UPDATE ebook_reports SET status = :st, reviewed_by = :rev, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        return $up->execute([
            ':st' => $status,
            ':rev' => $reviewerId,
            ':id' => $reportId,
        ]);
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
        if ($base === '') {
            $base = 'ebook';
        }

        $slug = $base;
        $counter = 1;

        while (true) {
            $stmt = $this->pdo->prepare('SELECT id FROM ebooks WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
            $counter++;
            $slug = $base . '-' . $counter;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatBookRow(array $row): array
    {
        $isAnonymous = empty($row['user_id']);
        if ($isAnonymous) {
            $uploaderName = !empty($row['submitter_name']) ? (string) $row['submitter_name'] : 'Community Contributor';
            $uploaderUsername = 'community';
            $uploaderAvatar = null;
        } else {
            $uploaderName = (string) ($row['uploader_name'] ?? 'Infinity Member');
            $uploaderUsername = (string) ($row['uploader_username'] ?? 'member');
            $uploaderAvatar = isset($row['uploader_avatar']) && is_string($row['uploader_avatar']) ? $row['uploader_avatar'] : null;
        }

        $coverUrl = !empty($row['cover_image_path']) ? (string) $row['cover_image_path'] : null;
        if (empty($coverUrl)) {
            // Generate deterministic, elegant fallback SVG cover
            $coverUrl = EbookFileService::generateSvgCover(
                (string) $row['title'],
                (string) $row['author_name'],
                (string) ($row['category_name'] ?? null)
            );
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => !empty($row['user_id']) ? (int) $row['user_id'] : null,
            'is_anonymous' => $isAnonymous,
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'author_name' => (string) $row['author_name'],
            'description' => (string) $row['description'],
            'category_id' => !empty($row['category_id']) ? (int) $row['category_id'] : null,
            'category_name' => (string) ($row['category_name'] ?? 'General'),
            'category_slug' => (string) ($row['category_slug'] ?? 'general'),
            'community_id' => !empty($row['community_id']) ? (int) $row['community_id'] : null,
            'community_name' => (string) ($row['community_name'] ?? ''),
            'community_slug' => (string) ($row['community_slug'] ?? ''),
            'file_path' => (string) $row['file_path'],
            'file_format' => strtoupper((string) $row['file_format']),
            'format' => strtolower((string) $row['file_format']),
            'mime_type' => strtolower((string) $row['file_format']) === 'pdf' ? 'application/pdf' : 'application/epub+zip',
            'file_size' => (int) ($row['file_size'] ?? 0),
            'file_size_formatted' => self::formatBytes((int) ($row['file_size'] ?? 0)),
            'cover_image_path' => $row['cover_image_path'] ?? null,
            'cover_path' => $coverUrl,
            'cover_url' => $coverUrl,
            'language' => (string) ($row['language'] ?? 'en'),
            'tags' => !empty($row['tags']) ? (is_array($row['tags']) ? $row['tags'] : array_filter(array_map('trim', explode(',', (string) $row['tags'])))) : [],
            'allow_download' => !empty($row['allow_download']),
            'status' => (string) $row['status'],
            'read_count' => (int) ($row['read_count'] ?? 0),
            'download_count' => (int) ($row['download_count'] ?? 0),
            'featured' => !empty($row['featured']),
            'rejection_reason' => $row['rejection_reason'] ?? null,
            'created_at' => (string) $row['created_at'],
            'uploader' => [
                'name' => $uploaderName,
                'username' => $uploaderUsername,
                'handle' => $isAnonymous ? '@community' : UserIdentityHelper::formatHandle($uploaderUsername, $uploaderName),
                'avatar_url' => UserIdentityHelper::resolveAvatarUrl($uploaderAvatar),
            ],
            'uploader_name' => $uploaderName,
            'uploader_username' => $uploaderUsername,
            'uploader_avatar' => $uploaderAvatar,
            // Reading progress fields if joined
            'current_page' => isset($row['current_page']) ? (int) $row['current_page'] : null,
            'last_page' => isset($row['current_page']) ? (int) $row['current_page'] : null,
            'total_pages' => isset($row['total_pages']) ? (int) $row['total_pages'] : null,
            'progress_percent' => isset($row['progress_percent']) ? (int) $row['progress_percent'] : null,
            'percentage' => isset($row['progress_percent']) ? (float) $row['progress_percent'] : null,
            'last_read_at' => $row['last_read_at'] ?? null,
        ];
    }

    public static function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }
}
