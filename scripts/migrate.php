<?php

declare(strict_types=1);

use AxaKenneth\Support\Config;
use AxaKenneth\Support\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$migrationConfig = Config::get('db');
$migrationConfig['user'] = (string) Config::get('db.migration_user');
$migrationConfig['password'] = (string) Config::get('db.migration_password');

if ($migrationConfig['user'] === '' || $migrationConfig['password'] === '') {
    fwrite(STDERR, "DB_MIGRATION_USER and DB_MIGRATION_PASSWORD must be configured in .env.\n");
    exit(1);
}

/**
 * Split a SQL file into statements while respecting quoted strings and comments.
 *
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= $char;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if (($char === '-' && $next === '-') || $char === '#') {
                $inLineComment = true;
                if ($char === '-') {
                    $i++;
                }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($char === "'" && !$inDouble && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
        } elseif ($char === '"' && !$inSingle && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
        } elseif ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $remaining = trim($buffer);
    if ($remaining !== '') {
        $statements[] = $remaining;
    }

    return $statements;
}

try {
    $pdo = Database::connect($migrationConfig);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $files = glob(Config::get('paths.database') . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);

    if ($files === []) {
        fwrite(STDOUT, "No migration files found.\n");
        exit(0);
    }

    $isApplied = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :migration');
    $record = $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, NOW())');
    $appliedCount = 0;

    foreach ($files as $file) {
        $name = basename($file);
        $isApplied->execute([':migration' => $name]);
        if ((int) $isApplied->fetchColumn() > 0) {
            fwrite(STDOUT, "Already applied: {$name}\n");
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Unable to read migration {$name}.");
        }

        $statements = splitSqlStatements($sql);
        if ($statements === []) {
            throw new RuntimeException("Migration {$name} has no executable statements.");
        }

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $record->execute([':migration' => $name]);
        $appliedCount++;
        fwrite(STDOUT, "Applied: {$name}\n");
    }

    fwrite(STDOUT, "Migrations complete. Newly applied: {$appliedCount}.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
