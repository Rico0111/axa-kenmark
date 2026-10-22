<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class ProductRepository
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
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             INNER JOIN service_categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND c.is_published = 1
             ORDER BY p.sort_order ASC, p.name ASC"
        );

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function all(string $search = '', string $status = '', int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                INNER JOIN service_categories c ON c.id = p.category_id';
        $conditions = [];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(p.name LIKE :search OR p.summary LIKE :search OR p.product_type LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.sort_order ASC, p.name ASC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function count(string $search = '', string $status = ''): int
    {
        $sql = 'SELECT COUNT(*) FROM products p';
        $conditions = [];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(p.name LIKE :search OR p.summary LIKE :search OR p.product_type LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             INNER JOIN service_categories c ON c.id = p.category_id
             WHERE p.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE slug = :slug';
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
            'INSERT INTO products
                (category_id, name, slug, product_type, summary, official_url, image_media_id, image_url,
                 image_alt, sort_order, status, verification_status, verified_at, verified_by,
                 verification_notes, created_at, updated_at)
             VALUES
                (:category_id, :name, :slug, :product_type, :summary, :official_url, :image_media_id, :image_url,
                 :image_alt, :sort_order, :status, :verification_status, :verified_at, :verified_by,
                 :verification_notes, NOW(), NOW())'
        );
        $statement->execute($this->params($data));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE products SET
                category_id = :category_id, name = :name, slug = :slug,
                product_type = :product_type, summary = :summary, official_url = :official_url,
                image_media_id = :image_media_id, image_url = :image_url, image_alt = :image_alt,
                sort_order = :sort_order, status = :status, verification_status = :verification_status,
                verified_at = :verified_at, verified_by = :verified_by,
                verification_notes = :verification_notes, updated_at = NOW()
             WHERE id = :id'
        );
        $params = $this->params($data);
        $params[':id'] = $id;
        $statement->execute($params);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute([':id' => $id]);
    }

    public function unverifiedPublishedCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM products WHERE status = 'published' AND verification_status <> 'verified'"
        )->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 6): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.status, p.verification_status, p.updated_at, c.name AS category_name
             FROM products p
             INNER JOIN service_categories c ON c.id = p.category_id
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS total FROM products GROUP BY status'
        )->fetchAll();

        $counts = ['draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function params(array $data): array
    {
        return [
            ':category_id' => (int) $data['category_id'],
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':product_type' => $data['product_type'],
            ':summary' => $data['summary'],
            ':official_url' => $data['official_url'] !== '' ? $data['official_url'] : null,
            ':image_media_id' => $data['image_media_id'] !== null && (int) $data['image_media_id'] > 0
                ? (int) $data['image_media_id']
                : null,
            ':image_url' => $data['image_url'] !== '' ? $data['image_url'] : null,
            ':image_alt' => $data['image_alt'] !== '' ? $data['image_alt'] : null,
            ':sort_order' => (int) $data['sort_order'],
            ':status' => $data['status'],
            ':verification_status' => $data['verification_status'],
            ':verified_at' => $data['verified_at'],
            ':verified_by' => $data['verified_by'],
            ':verification_notes' => $data['verification_notes'] !== '' ? $data['verification_notes'] : null,
        ];
    }
}
