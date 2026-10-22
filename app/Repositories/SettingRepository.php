<?php

declare(strict_types=1);

namespace AxaKenneth\Repositories;

use AxaKenneth\Support\Database;
use PDO;

final class SettingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $statement = $this->pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
        $statement->execute([':key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        $statement->execute([':key' => $key, ':value' => $value]);
    }

    /** @param array<string, string> $values */
    public function setMany(array $values): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $statement->execute([':key' => $key, ':value' => $value]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return $settings;
    }
}
