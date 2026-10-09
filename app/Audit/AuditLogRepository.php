<?php
declare(strict_types=1);

namespace InfinityBlog\Audit;

use PDO;

final class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array{actor_id?: int, action?: string, entity_type?: string, from?: string, to?: string} $filters
     * @return array{events: list<array<string, mixed>>, total: int}
     */
    public function listEvents(array $filters, int $page, int $perPage): array
    {
        $conditions = [];
        $parameters = [];
        if (isset($filters['actor_id'])) {
            $conditions[] = 'actor_user_id = :actor_id';
            $parameters['actor_id'] = $filters['actor_id'];
        }
        foreach (['action', 'entity_type'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = $field . ' = :' . $field;
                $parameters[$field] = $filters[$field];
            }
        }
        if (isset($filters['from'])) {
            $conditions[] = 'created_at >= :from_date';
            $parameters['from_date'] = $filters['from'] . ' 00:00:00';
        }
        if (isset($filters['to'])) {
            $conditions[] = 'created_at <= :to_date';
            $parameters['to_date'] = $filters['to'] . ' 23:59:59';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT id, actor_user_id, action, entity_type, entity_id, created_at FROM audit_logs' . $where .
            ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $events = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'actor_user_id' => $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            'action' => (string) $row['action'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => $row['entity_id'] === null ? null : (int) $row['entity_id'],
            'created_at' => (string) $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return ['events' => $events, 'total' => $total];
    }
}
