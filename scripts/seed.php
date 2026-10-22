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

try {
    $pdo = Database::connect($migrationConfig);
    $seedFiles = glob(Config::get('paths.database') . '/seeds/*.php') ?: [];
    sort($seedFiles, SORT_STRING);

    if ($seedFiles === []) {
        fwrite(STDOUT, "No seed files found.\n");
        exit(0);
    }

    foreach ($seedFiles as $seedFile) {
        require $seedFile;
        fwrite(STDOUT, 'Applied seed: ' . basename($seedFile) . "\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
