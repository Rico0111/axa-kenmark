<?php

declare(strict_types=1);

use AxaKenneth\Repositories\AdminRepository;
use AxaKenneth\Support\AuditLogger;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Cli;
use AxaKenneth\Support\Config;
use AxaKenneth\Support\Validator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

fwrite(STDOUT, "AXA-Kenneth administrator provisioning\n");
fwrite(STDOUT, "The password is never written to the repository or printed.\n\n");

$name = Cli::ask('Administrator name');
$email = strtolower(Cli::ask('Administrator email'));
$roleChoice = Cli::ask('Role: 1 = super administrator, 2 = administrator', '1');
$role = $roleChoice === '2' ? 'admin' : 'super_admin';

$errors = Validator::validate(
    ['name' => $name, 'email' => $email],
    ['name' => 'required|max:120', 'email' => 'required|email|max:190'],
    ['name' => 'Name', 'email' => 'Email']
);
if ($errors !== []) {
    fwrite(STDERR, Validator::first($errors) . "\n");
    exit(1);
}

$password = Cli::askPasswordAndConfirm(
    'Password (12+ characters, letters and numbers)',
    'Confirm password'
);

try {
    $repository = new AdminRepository();
    if ($repository->findByEmail($email) !== null) {
        fwrite(STDERR, "An administrator with that email already exists.\n");
        exit(1);
    }

    $id = $repository->create($name, $email, Auth::hashPassword($password), $role);
    AuditLogger::record(null, 'admin.created', 'admin', $id, ['role' => $role]);

    fwrite(STDOUT, "\nAdministrator created (ID {$id}).\n");
    fwrite(STDOUT, "Sign in at " . Config::appUrl('admin/') . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unable to create administrator: ' . $exception->getMessage() . "\n");
    exit(1);
}
