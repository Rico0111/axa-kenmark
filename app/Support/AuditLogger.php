<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use Throwable;

final class AuditLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public static function record(
        ?int $adminId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = []
    ): void {
        try {
            $statement = Database::pdo()->prepare(
                'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, ip_address, user_agent, metadata, created_at)
                 VALUES (:admin_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata, NOW())'
            );
            $statement->execute([
                ':admin_id' => $adminId,
                ':action' => substr($action, 0, 100),
                ':entity_type' => $entityType !== null ? substr($entityType, 0, 60) : null,
                ':entity_id' => $entityId,
                ':ip_address' => Http::ip(),
                ':user_agent' => Http::userAgent(),
                ':metadata' => $metadata === []
                    ? null
                    : json_encode(
                        $metadata,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                    ),
            ]);
        } catch (Throwable $exception) {
            error_log('Audit log write failed: ' . $exception->getMessage());
        }
    }
}
