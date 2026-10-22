<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class CategoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function published(): array
    {
        $statement = $this->pdo->query(
            'SELECT * FROM service_categories WHERE is_published = 1 ORDER BY sort_order ASC, name ASC'
        );

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function all(string $search = '', int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
                FROM service_categories c';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE c.name LIKE :search OR c.summary LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY c.sort_order ASC, c.name ASC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function count(string $search = ''): int
    {
        if ($search === '') {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM service_categories')->fetchColumn();
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM service_categories WHERE name LIKE :search OR summary LIKE :search'
        );
        $statement->execute([':search' => '%' . $search . '%']);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM service_categories WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function productCount(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
        $statement->execute([':id' => $id]);

        return (int) $statement->fetchColumn();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM service_categories WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO service_categories
                (name, slug, icon, summary, description, sort_order, is_published, created_at, updated_at)
             VALUES
                (:name, :slug, :icon, :summary, :description, :sort_order, :is_published, NOW(), NOW())'
        );
        $statement->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':icon' => $data['icon'],
            ':summary' => $data['summary'],
            ':description' => $data['description'],
            ':sort_order' => (int) $data['sort_order'],
            ':is_published' => (int) $data['is_published'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE service_categories SET
                name = :name, slug = :slug, icon = :icon, summary = :summary,
                description = :description, sort_order = :sort_order,
                is_published = :is_published, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':icon' => $data['icon'],
            ':summary' => $data['summary'],
            ':description' => $data['description'],
            ':sort_order' => (int) $data['sort_order'],
            ':is_published' => (int) $data['is_published'],
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM service_categories WHERE id = :id');
        $statement->execute([':id' => $id]);
    }
}
