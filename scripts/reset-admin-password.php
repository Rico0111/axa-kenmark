<?php

declare(strict_types=1);

use AxaKenneth\Repositories\AdminRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Cli;
use AxaKenneth\Support\Config;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

fwrite(STDOUT, "AXA-Kenneth administrator password reset\n");
fwrite(STDOUT, "The password is never written to the repository or printed.\n\n");

$identifier = '';
if ($argc > 1 && trim($argv[1]) !== '') {
    $identifier = trim($argv[1]);
} else {
    $identifier = Cli::ask('Administrator email or ID');
}

if ($identifier === '') {
    fwrite(STDERR, "Administrator email or ID is required.\n");
    exit(1);
}

$repository = new AdminRepository();
$admin = ctype_digit($identifier)
    ? $repository->findById((int) $identifier)
    : $repository->findByEmail($identifier);

if ($admin === null) {
    fwrite(STDERR, "Administrator not found for '{$identifier}'.\n");
    exit(1);
}

$statusLabel = (int) $admin['is_active'] === 1 ? 'active' : 'inactive';
fwrite(
    STDOUT,
    "Target: {$admin['name']} <{$admin['email']}> (ID: {$admin['id']}, Role: {$admin['role']}, Status: {$statusLabel})\n\n"
);

$password = Cli::askPasswordAndConfirm(
    'New password (12+ characters, letters and numbers)',
    'Confirm new password'
);

try {
    $newVersion = $repository->resetPassword((int) $admin['id'], Auth::hashPassword($password));
    AuditLogger::record((int) $admin['id'], 'admin.password_reset', 'admin', (int) $admin['id'], [
        'source' => 'cli',
        'session_version' => $newVersion,
    ]);

    fwrite(STDOUT, "\nPassword successfully reset for administrator {$admin['name']} <{$admin['email']}>.\n");
    fwrite(STDOUT, "Existing sessions have been invalidated (session version: {$newVersion}).\n");
    fwrite(STDOUT, "Sign in at " . Config::appUrl('admin/') . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unable to reset password: ' . $exception->getMessage() . "\n");
    exit(1);
}
