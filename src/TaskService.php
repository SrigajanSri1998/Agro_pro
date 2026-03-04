<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class TaskService
{
    public static function list(int $userId, array $query): array
    {
        $pdo = Database::connection();
        $status = $query['status'] ?? null;
        $search = trim((string)($query['search'] ?? ''));
        $includeDeleted = ($query['include_deleted'] ?? '0') === '1';
        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = min(50, max(1, (int)($query['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $where = ['user_id = :user_id'];
        $params = [':user_id' => $userId];

        if (!$includeDeleted) {
            $where[] = 'deleted_at IS NULL';
        }
        if ($status && in_array($status, ['todo', 'in_progress', 'done'], true)) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(title LIKE :search OR description LIKE :search)';
            $params[':search'] = "%{$search}%";
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $listStmt = $pdo->prepare("SELECT * FROM tasks WHERE {$whereSql} ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();

        return [
            'data' => $listStmt->fetchAll(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    public static function create(int $userId, array $payload): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO tasks (user_id, title, description, status, due_date) VALUES (:user_id, :title, :description, :status, :due_date)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => trim((string)$payload['title']),
            ':description' => trim((string)($payload['description'] ?? '')),
            ':status' => $payload['status'] ?? 'todo',
            ':due_date' => $payload['due_date'] ?? null,
        ]);

        return self::find($userId, (int)$pdo->lastInsertId(), true);
    }

    public static function find(int $userId, int $id, bool $includeDeleted = false): ?array
    {
        $pdo = Database::connection();
        $sql = 'SELECT * FROM tasks WHERE user_id = :user_id AND id = :id';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':id' => $id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public static function update(int $userId, int $id, array $payload): ?array
    {
        $task = self::find($userId, $id, true);
        if (!$task) {
            return null;
        }

        $allowed = ['title', 'description', 'status', 'due_date'];
        $setClauses = [];
        $params = [':id' => $id, ':user_id' => $userId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $setClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $payload[$field];
            }
        }

        if ($setClauses === []) {
            return $task;
        }

        $setClauses[] = 'updated_at = NOW()';
        $sql = 'UPDATE tasks SET ' . implode(', ', $setClauses) . ' WHERE id = :id AND user_id = :user_id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return self::find($userId, $id, true);
    }

    public static function softDelete(int $userId, int $id): bool
    {
        $stmt = Database::connection()->prepare('UPDATE tasks SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function restore(int $userId, int $id): bool
    {
        $stmt = Database::connection()->prepare('UPDATE tasks SET deleted_at = NULL, updated_at = NOW() WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }
}
