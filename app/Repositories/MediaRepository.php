<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class MediaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 100, int $offset = 0): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM media ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO media
                (storage_key, original_name, mime_type, extension, size_bytes, width, height,
                 alt_text, title, checksum, uploaded_by, created_at)
             VALUES
                (:storage_key, :original_name, :mime_type, :extension, :size_bytes, :width, :height,
                 :alt_text, :title, :checksum, :uploaded_by, NOW())'
        );
        $statement->execute([
            ':storage_key' => $data['storage_key'],
            ':original_name' => $data['original_name'],
            ':mime_type' => $data['mime_type'],
            ':extension' => $data['extension'],
            ':size_bytes' => (int) $data['size_bytes'],
            ':width' => $data['width'] !== null ? (int) $data['width'] : null,
            ':height' => $data['height'] !== null ? (int) $data['height'] : null,
            ':alt_text' => $data['alt_text'] !== '' ? $data['alt_text'] : null,
            ':title' => $data['title'] !== '' ? $data['title'] : null,
            ':checksum' => $data['checksum'],
            ':uploaded_by' => $data['uploaded_by'] !== null ? (int) $data['uploaded_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function usageCount(int $id): int
    {
        $statement = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM products WHERE image_media_id = :product_id) +
                (SELECT COUNT(*) FROM advisor_profiles WHERE photo_media_id = :advisor_id)'
        );
        $statement->execute([':product_id' => $id, ':advisor_id' => $id]);

        return (int) $statement->fetchColumn();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM media WHERE id = :id');
        $statement->execute([':id' => $id]);
    }
}
