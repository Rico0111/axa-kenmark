<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use PDO;

final class RateLimiter
{
    public static function tooManyAttempts(string $action, string $identifier, int $maxAttempts, int $decaySeconds): bool
    {
        $row = self::find($action, $identifier);
        if ($row === null) {
            return false;
        }

        $now = time();
        $blockedUntil = $row['blocked_until'] !== null ? strtotime((string) $row['blocked_until']) : false;
        if ($blockedUntil !== false && $blockedUntil > $now) {
            return true;
        }

        $windowStarted = strtotime((string) $row['window_started_at']);
        if ($windowStarted === false || $windowStarted < $now - $decaySeconds) {
            self::clear($action, $identifier);
            return false;
        }

        return (int) $row['attempts'] >= $maxAttempts;
    }

    public static function hit(string $action, string $identifier, int $maxAttempts, int $decaySeconds): void
    {
        $row = self::find($action, $identifier);
        $now = time();

        if ($row === null || strtotime((string) $row['window_started_at']) < $now - $decaySeconds) {
            $statement = Database::pdo()->prepare(
                'REPLACE INTO rate_limits (action, identifier_hash, attempts, window_started_at, blocked_until, updated_at)
                 VALUES (:action, :identifier_hash, 1, NOW(), NULL, NOW())'
            );
            $statement->execute([
                ':action' => $action,
                ':identifier_hash' => self::hash($identifier),
            ]);
            return;
        }

        $attempts = (int) $row['attempts'] + 1;
        $blockedUntil = $attempts >= $maxAttempts
            ? date('Y-m-d H:i:s', $now + $decaySeconds)
            : null;

        $statement = Database::pdo()->prepare(
            'UPDATE rate_limits
             SET attempts = :attempts, blocked_until = :blocked_until, updated_at = NOW()
             WHERE action = :action AND identifier_hash = :identifier_hash'
        );
        $statement->execute([
            ':attempts' => $attempts,
            ':blocked_until' => $blockedUntil,
            ':action' => $action,
            ':identifier_hash' => self::hash($identifier),
        ]);

        if (random_int(1, 100) === 1) {
            Database::pdo()->exec('DELETE FROM rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        }
    }

    public static function clear(string $action, string $identifier): void
    {
        $statement = Database::pdo()->prepare(
            'DELETE FROM rate_limits WHERE action = :action AND identifier_hash = :identifier_hash'
        );
        $statement->execute([
            ':action' => $action,
            ':identifier_hash' => self::hash($identifier),
        ]);
    }

    /**
     * @return array{attempts:int,window_started_at:string,blocked_until:?string}|null
     */
    private static function find(string $action, string $identifier): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT attempts, window_started_at, blocked_until
             FROM rate_limits WHERE action = :action AND identifier_hash = :identifier_hash LIMIT 1'
        );
        $statement->execute([
            ':action' => $action,
            ':identifier_hash' => self::hash($identifier),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private static function hash(string $identifier): string
    {
        return hash_hmac('sha256', $identifier, (string) Config::get('app.key'));
    }
}
