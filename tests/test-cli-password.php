<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use AxaKenneth\Repositories\AdminRepository;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Database;

$phpBinary = PHP_BINARY;
$repoRoot = dirname(__DIR__);

$totalChecks = 0;
$passedChecks = 0;

function runTest(string $description, callable $callback): void
{
    global $totalChecks, $passedChecks;
    $totalChecks++;
    echo "Running: {$description}... ";
    try {
        $callback();
        $passedChecks++;
        echo "PASSED\n";
    } catch (Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

function runCliProcess(string $command, string $input = ''): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException("Failed to run command: {$command}");
    }

    if ($input !== '') {
        fwrite($pipes[0], $input);
    }
    fclose($pipes[0]);

    $stdout = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

echo "========================================================\n";
echo "AXA-Kenneth CLI Password & Terminal Regression Test Suite\n";
echo "========================================================\n\n";

if (\AxaKenneth\Support\Config::get('app.env') === 'production') {
    throw new RuntimeException('Run this mutating suite only against an isolated test database.');
}
$fixtureEmail = 'cli-fixture-' . bin2hex(random_bytes(6)) . '@example.test';
$fixtureId = (new AdminRepository())->create('CLI Test Fixture', $fixtureEmail, Auth::hashPassword(bin2hex(random_bytes(16))), 'super_admin');
try {
// 1. Cli::secret empty input rejection
runTest('Cli::secret rejects empty input', function () use ($phpBinary, $repoRoot) {
    $script = "require 'app/bootstrap.php'; \AxaKenneth\Support\Cli::secret('Password');";
    $res = runCliProcess("\"{$phpBinary}\" -r \"{$script}\"", "\n");
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Password cannot be empty.')) {
        throw new Exception("Expected 'Password cannot be empty.' in stderr. Got: {$res['stderr']}");
    }
});

// 2. Cli::secret rejects non-interactive terminal when allowPiped = false
runTest('Cli::secret rejects unavailable interactive terminal when required', function () use ($phpBinary, $repoRoot) {
    $script = "require 'app/bootstrap.php'; \AxaKenneth\Support\Cli::secret('Password', false);";
    $res = runCliProcess("\"{$phpBinary}\" -r \"{$script}\"", "testpassword123\n");
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Interactive terminal is required for secure password entry.')) {
        throw new Exception("Expected interactive requirement in stderr. Got: {$res['stderr']}");
    }
});

// 3. create-admin.php rejects empty password
runTest('create-admin.php rejects empty password', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $input = "Test Admin\ntest_empty@example.com\n1\n\n";
    $res = runCliProcess("\"{$phpBinary}\" scripts/create-admin.php", $input);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Password cannot be empty.')) {
        throw new Exception("Expected 'Password cannot be empty.' in stderr. Got: {$res['stderr']}");
    }
});

// 4. create-admin.php rejects short password (< 12 chars)
runTest('create-admin.php rejects short password (<12 chars)', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $input = "Test Admin\ntest_short@example.com\n1\nshort123\n";
    $res = runCliProcess("\"{$phpBinary}\" scripts/create-admin.php", $input);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Use at least 12 characters.')) {
        throw new Exception("Expected 'Use at least 12 characters.' in stderr. Got: {$res['stderr']}");
    }
});

// 5. create-admin.php rejects password without numbers
runTest('create-admin.php rejects password without numbers', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $input = "Test Admin\ntest_nonum@example.com\n1\nTwelveCharactersWithoutDigits\n";
    $res = runCliProcess("\"{$phpBinary}\" scripts/create-admin.php", $input);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Include at least one letter and one number.')) {
        throw new Exception("Expected 'Include at least one letter and one number.' in stderr. Got: {$res['stderr']}");
    }
});

// 6. create-admin.php rejects password confirmation mismatch
runTest('create-admin.php rejects confirmation mismatch', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $input = "Test Admin\ntest_mismatch@example.com\n1\nValidPassphrase1234!\nDifferentPassphrase1234!\n";
    $res = runCliProcess("\"{$phpBinary}\" scripts/create-admin.php", $input);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Passwords do not match.')) {
        throw new Exception("Expected 'Passwords do not match.' in stderr. Got: {$res['stderr']}");
    }
});

// 7. reset-admin-password.php rejects non-existent admin
runTest('reset-admin-password.php rejects non-existent administrator', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $res = runCliProcess("\"{$phpBinary}\" scripts/reset-admin-password.php 99999");
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], "Administrator not found for '99999'.")) {
        throw new Exception("Expected 'Administrator not found' in stderr. Got: {$res['stderr']}");
    }
});

// 8. reset-admin-password.php rejects confirmation mismatch
runTest('reset-admin-password.php rejects confirmation mismatch', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $input = "ValidPassphrase1234!\nDifferentPassphrase1234!\n";
    $res = runCliProcess("\"{$phpBinary}\" scripts/reset-admin-password.php {$fixtureId}", $input);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Passwords do not match.')) {
        throw new Exception("Expected 'Passwords do not match.' in stderr. Got: {$res['stderr']}");
    }
});

// 9. reset-admin-password.php rejects empty password
runTest('reset-admin-password.php rejects empty password', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $input = "\n";
    $res = runCliProcess("\"{$phpBinary}\" scripts/reset-admin-password.php {$fixtureId}", $input);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], 'Password cannot be empty.')) {
        throw new Exception("Expected 'Password cannot be empty.' in stderr. Got: {$res['stderr']}");
    }
});

// 10. Execution via Windows CMD
if (PHP_OS_FAMILY === 'Windows') {
runTest('Execution compatible with Windows CMD', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $cmd = "cmd.exe /c \"\"{$phpBinary}\" scripts/reset-admin-password.php 99999\"";
    $res = runCliProcess($cmd);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1 in CMD, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], "Administrator not found for '99999'.")) {
        throw new Exception("Expected administrator not found in CMD. Got: {$res['stderr']}");
    }
});

// 11. Execution via PowerShell
runTest('Execution compatible with PowerShell', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $psCmd = "powershell.exe -NoProfile -Command \"& '{$phpBinary}' scripts/reset-admin-password.php 99999\"";
    $res = runCliProcess($psCmd);
    if ($res['code'] !== 1) {
        throw new Exception("Expected exit code 1 in PowerShell, got {$res['code']}");
    }
    if (!str_contains($res['stderr'], "Administrator not found for '99999'.")) {
        throw new Exception("Expected administrator not found in PowerShell. Got: {$res['stderr']}");
    }
});

// 12. Password reset for temporary administrator with authentication verification
} else {
    echo "SKIP: Windows CMD and PowerShell compatibility checks require Windows.\n";
}
runTest('Reset temporary administrator password & verify login', function () use ($phpBinary, $fixtureId, $fixtureEmail) {
    $adminRepo = new AdminRepository();
    $adminBefore = $adminRepo->findById($fixtureId);
    if ($adminBefore === null) {
        throw new Exception("Test administrator does not exist.");
    }

    // Generate secure random password in memory
    $newSecret = 'AxaPass_' . bin2hex(random_bytes(8)) . '9!';
    $input = "{$newSecret}\n{$newSecret}\n";

    $res = runCliProcess("\"{$phpBinary}\" scripts/reset-admin-password.php {$fixtureId}", $input);
    if ($res['code'] !== 0) {
        throw new Exception("Password reset command failed with code {$res['code']}. Stderr: {$res['stderr']}");
    }

    if (!str_contains($res['stdout'], 'Password successfully reset for administrator')) {
        throw new Exception("Expected success message in stdout. Got: {$res['stdout']}");
    }

    // Verify DB update
    $adminAfter = $adminRepo->findById($fixtureId);
    if ($adminAfter === null) {
        throw new Exception("Test administrator disappeared.");
    }

    if ($adminAfter['password_hash'] === $adminBefore['password_hash']) {
        throw new Exception("Password hash was not updated.");
    }

    $hashInfo = password_get_info($adminAfter['password_hash']);
    if ($hashInfo['algoName'] !== 'argon2id') {
        throw new Exception("Expected Argon2id hash algorithm, got {$hashInfo['algoName']}");
    }

    if ((int) $adminAfter['session_version'] <= (int) $adminBefore['session_version']) {
        throw new Exception("Expected session_version to increment.");
    }

    // Verify authentication in a clean process using Auth::attempt via piped PHP script
    $verifyCode = "<?php\n" .
        "require 'app/bootstrap.php';\n" .
        "\$res = \AxaKenneth\Support\Auth::attempt('{$fixtureEmail}', '{$newSecret}');\n" .
        "if (!\$res['ok']) { echo 'AUTH_FAILED: ' . \$res['message']; exit(2); }\n" .
        "\$wrong = \AxaKenneth\Support\Auth::attempt('{$fixtureEmail}', 'WrongPassword123!');\n" .
        "if (\$wrong['ok']) { echo 'WRONG_AUTH_SUCCEEDED'; exit(3); }\n" .
        "echo 'AUTH_OK';\n";

    $authProcess = runCliProcess("\"{$phpBinary}\"", $verifyCode);
    if ($authProcess['code'] !== 0 || !str_contains($authProcess['stdout'], 'AUTH_OK')) {
        throw new Exception("Authentication verification failed: code {$authProcess['code']}, out: {$authProcess['stdout']}, err: {$authProcess['stderr']}");
    }

    // Verify audit log record exists
    $pdo = Database::pdo();
    $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = 'admin.password_reset' AND entity_id = {$fixtureId} ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $audit = $stmt->fetch();
    if (!$audit) {
        throw new Exception("Audit log record for password reset was not found.");
    }
});

} finally {
    $pdo = Database::pdo();
    $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$fixtureId]);
    $pdo->prepare('DELETE FROM audit_logs WHERE admin_id = ?')->execute([$fixtureId]);
}
echo "\n========================================================\n";
echo "Test Results: {$passedChecks}/{$totalChecks} checks passed.\n";
echo "========================================================\n";

if ($passedChecks !== $totalChecks) {
    exit(1);
}
