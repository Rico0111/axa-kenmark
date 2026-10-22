<?php

declare(strict_types=1);

use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Cli;
use AxaKenneth\Support\Database;
use AxaKenneth\Support\Validator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

fwrite(STDOUT, "========================================================\n");
fwrite(STDOUT, "AXA-Kenneth — Offline Production Administrator SQL Generator\n");
fwrite(STDOUT, "Use this tool to generate an importable SQL statement for Hostinger phpMyAdmin\n");
fwrite(STDOUT, "when SSH terminal access is unavailable.\n");
fwrite(STDOUT, "The password is never written to disk, git, or packages.\n");
fwrite(STDOUT, "========================================================\n\n");

$name = Cli::ask('Administrator display name', 'Kenneth');
$email = strtolower(Cli::ask('Administrator email'));
$roleChoice = Cli::ask('Role: 1 = super administrator, 2 = administrator', '1');
$role = $roleChoice === '2' ? 'admin' : 'super_admin';

$errors = Validator::validate(
    ['name' => $name, 'email' => $email],
    ['name' => 'required|max:120', 'email' => 'required|email|max:190'],
    ['name' => 'Name', 'email' => 'Email']
);
if ($errors !== []) {
    fwrite(STDERR, "\nValidation Error: " . Validator::first($errors) . "\n");
    exit(1);
}

$password = Cli::askPasswordAndConfirm(
    'Password (12+ characters, letters and numbers)',
    'Confirm password'
);

$hash = Auth::hashPassword($password);

// Escape strings safely for SQL insertion
$escapedName = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);
$escapedEmail = str_replace(["\\", "'"], ["\\\\", "\\'"], $email);
$escapedHash = str_replace(["\\", "'"], ["\\\\", "\\'"], $hash);
$escapedRole = str_replace(["\\", "'"], ["\\\\", "\\'"], $role);

$sql = "INSERT INTO `admins` (`name`, `email`, `password_hash`, `role`, `is_active`, `session_version`, `created_at`, `updated_at`)\n"
    . "VALUES ('{$escapedName}', '{$escapedEmail}', '{$escapedHash}', '{$escapedRole}', 1, 1, NOW(), NOW())\n"
    . "ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `password_hash` = VALUES(`password_hash`), `updated_at` = NOW();";

fwrite(STDOUT, "\n========================================================\n");
fwrite(STDOUT, "SUCCESS: Administrator SQL statement generated!\n");
fwrite(STDOUT, "========================================================\n\n");
fwrite(STDOUT, "Copy and execute the following SQL statement in Hostinger phpMyAdmin (SQL tab):\n\n");
fwrite(STDOUT, "--------------------------------------------------------\n");
fwrite(STDOUT, $sql . "\n");
fwrite(STDOUT, "--------------------------------------------------------\n\n");
fwrite(STDOUT, "After executing this SQL statement in phpMyAdmin, you can immediately sign in at:\n");
fwrite(STDOUT, "https://techtalkmnl.com/axa-kenmark/admin/login.php\n\n");

// Clear sensitive variables
unset($password, $hash);
