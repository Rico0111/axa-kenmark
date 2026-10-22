<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class AdminRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
        $statement->execute([':email' => strtolower($email)]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, name, email, role, is_active, last_login_at, created_at, session_version
             FROM admins
             ORDER BY is_active DESC, role DESC, name ASC'
        )->fetchAll();
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM admins WHERE email = :email AND (:except_id IS NULL OR id <> :id)'
        );
        $statement->execute([
            ':email' => strtolower($email),
            ':except_id' => $exceptId,
            ':id' => $exceptId ?? 0,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array<string, mixed>|null */
    public function findByIdForUpdate(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(
        string $name,
        string $email,
        string $passwordHash,
        string $role = 'admin',
        bool $isActive = true
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO admins (name, email, password_hash, role, is_active, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :role, :is_active, NOW(), NOW())'
        );
        $statement->execute([
            ':name' => $name,
            ':email' => strtolower($email),
            ':password_hash' => $passwordHash,
            ':role' => $role,
            ':is_active' => $isActive ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAccount(int $id, string $name, string $email, string $role, bool $isActive): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admins
             SET name = :name,
                 email = :email,
                 role = :role,
                 is_active = :is_active,
                 session_version = session_version + IF(:revoke = 1, 1, 0),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            ':name' => $name,
            ':email' => strtolower($email),
            ':role' => $role,
            ':is_active' => $isActive ? 1 : 0,
            ':revoke' => $isActive ? 0 : 1,
            ':id' => $id,
        ]);
    }

    /** Locks the active super-administrator rows so the last one cannot be removed concurrently. */
    public function lockActiveSuperAdministratorCount(): int
    {
        $statement = $this->pdo->query(
            "SELECT id FROM admins WHERE role = 'super_admin' AND is_active = 1 FOR UPDATE"
        );

        return count($statement->fetchAll());
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admins SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':password_hash' => $passwordHash, ':id' => $id]);
    }

    /** Resets a password, invalidates existing sessions, and returns the new session generation. */
    public function resetPassword(int $id, string $passwordHash): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE admins
             SET password_hash = :password_hash,
                 session_version = session_version + 1,
                 failed_login_attempts = 0,
                 locked_until = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([':password_hash' => $passwordHash, ':id' => $id]);

        $version = $this->pdo->prepare('SELECT session_version FROM admins WHERE id = :id LIMIT 1');
        $version->execute([':id' => $id]);

        return (int) $version->fetchColumn();
    }

    public function updateLastLogin(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admins SET last_login_at = NOW(), failed_login_attempts = 0, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':id' => $id]);
    }
}
