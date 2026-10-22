<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class InquiryRepository
{
    public const TYPE_LABELS = [
        'general' => 'General inquiry',
        'consultation' => 'Consultation request',
        'recruitment' => 'Recruitment interest',
    ];

    public const STATUS_LABELS = [
        'new' => 'New',
        'in_review' => 'In review',
        'contacted' => 'Contacted',
        'scheduled' => 'Scheduled',
        'closed' => 'Closed',
        'spam' => 'Spam',
    ];

    public const PREFERRED_CONTACT_LABELS = [
        'email' => 'Email',
        'phone' => 'Phone call',
        'whatsapp' => 'WhatsApp',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @param array{inquiry_type:string,name:string,email:string,phone:string,preferred_contact:string,topic:string,preferred_time:string,location:string,experience:string,message:string,privacy_version:string,source:string,ip_hash:string,user_agent:string} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO inquiries
                (inquiry_type, name, email, phone, preferred_contact, topic, preferred_time,
                 location, experience, message, consent_at, privacy_version, source, status,
                 ip_hash, user_agent, created_at, updated_at)
             VALUES
                (:inquiry_type, :name, :email, :phone, :preferred_contact, :topic, :preferred_time,
                 :location, :experience, :message, NOW(), :privacy_version, :source, 'new',
                 :ip_hash, :user_agent, NOW(), NOW())"
        );
        $statement->execute([
            ':inquiry_type' => $data['inquiry_type'],
            ':name' => $data['name'],
            ':email' => strtolower($data['email']),
            ':phone' => $data['phone'],
            ':preferred_contact' => $data['preferred_contact'],
            ':topic' => $data['topic'],
            ':preferred_time' => $data['preferred_time'],
            ':location' => $data['location'],
            ':experience' => $data['experience'],
            ':message' => $data['message'],
            ':privacy_version' => $data['privacy_version'],
            ':source' => $data['source'],
            ':ip_hash' => $data['ip_hash'],
            ':user_agent' => $data['user_agent'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.*, a.name AS handled_by_name
             FROM inquiries i
             LEFT JOIN admins a ON a.id = i.handled_by
             WHERE i.id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $search, string $type, string $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->filters($search, $type, $status);

        $statement = $this->pdo->prepare(
            'SELECT i.*
             FROM inquiries i' . $where . '
             ORDER BY i.created_at DESC, i.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function count(string $search, string $type, string $status): int
    {
        [$where, $params] = $this->filters($search, $type, $status);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM inquiries i' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUS_LABELS), 0);
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS total FROM inquiries GROUP BY status')->fetchAll();
        foreach ($rows as $row) {
            $key = (string) $row['status'];
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function typeCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::TYPE_LABELS), 0);
        $rows = $this->pdo->query('SELECT inquiry_type, COUNT(*) AS total FROM inquiries GROUP BY inquiry_type')->fetchAll();
        foreach ($rows as $row) {
            $key = (string) $row['inquiry_type'];
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function newCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 6): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, inquiry_type, name, email, source, status, created_at
             FROM inquiries
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function updateWorkflow(int $id, string $status, string $internalNotes, int $adminId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE inquiries
             SET status = :status,
                 internal_notes = :internal_notes,
                 handled_by = :admin_id,
                 first_reviewed_at = CASE
                     WHEN first_reviewed_at IS NULL AND :review_status <> 'new' THEN NOW()
                     ELSE first_reviewed_at
                 END,
                 first_contacted_at = CASE
                     WHEN first_contacted_at IS NULL AND :contact_status IN ('contacted', 'scheduled') THEN NOW()
                     ELSE first_contacted_at
                 END,
                 closed_at = CASE
                     WHEN :closed_status IN ('closed', 'spam') THEN COALESCE(closed_at, NOW())
                     WHEN :reopen_status NOT IN ('closed', 'spam') THEN NULL
                     ELSE closed_at
                 END,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $statement->execute([
            ':status' => $status,
            ':internal_notes' => $internalNotes !== '' ? $internalNotes : null,
            ':admin_id' => $adminId,
            ':review_status' => $status,
            ':contact_status' => $status,
            ':closed_status' => $status,
            ':reopen_status' => $status,
            ':id' => $id,
        ]);
    }

    /**
     * @return array{0:string,1:array<string,string>}
     */
    private function filters(string $search, string $type, string $status): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "CONCAT_WS(' ', i.name, i.email, i.phone, i.topic, i.message, i.source) LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }
        if (array_key_exists($type, self::TYPE_LABELS)) {
            $where[] = 'i.inquiry_type = :type';
            $params[':type'] = $type;
        }
        if (array_key_exists($status, self::STATUS_LABELS)) {
            $where[] = 'i.status = :status';
            $params[':status'] = $status;
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }
}
