<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use RuntimeException;

final class Cli
{
    /**
     * Prompts the user for a standard line of text.
     */
    public static function ask(string $label, string $default = ''): string
    {
        $prompt = $label . ($default !== '' ? ' [' . $default . ']' : '') . ': ';
        fwrite(STDOUT, $prompt);
        fflush(STDOUT);

        $line = fgets(STDIN);
        if ($line === false) {
            return $default;
        }

        $value = trim($line);
        return $value !== '' ? $value : $default;
    }

    /**
     * Determines whether STDIN is connected to an interactive terminal.
     */
    public static function isInteractive(): bool
    {
        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    /**
     * Securely collects a secret/password from the terminal without plaintext echo.
     * On Windows, keypresses are read via PowerShell Console::ReadKey and masked with asterisks to STDERR.
     * On Unix, stty -echo is used.
     * When not attached to a TTY, reads a single line if $allowPiped is true.
     */
    public static function secret(string $label, bool $allowPiped = true): string
    {
        // 1. Always display the prompt clearly to the operator
        fwrite(STDOUT, $label . ': ');
        fflush(STDOUT);

        // 2. Handle non-interactive or redirected STDIN (pipes, test harnesses, CI)
        if (!self::isInteractive()) {
            if (!$allowPiped) {
                fwrite(STDERR, "\nInteractive terminal is required for secure password entry.\n");
                exit(1);
            }

            $line = fgets(STDIN);
            if ($line === false) {
                fwrite(STDERR, "\nFailed to read password from standard input.\n");
                exit(1);
            }

            $secret = rtrim($line, "\r\n");
            if ($secret === '') {
                fwrite(STDERR, "Password cannot be empty.\n");
                exit(1);
            }

            return $secret;
        }

        // 3. Windows interactive terminal (CMD, PowerShell, ConHost, Windows Terminal)
        if (DIRECTORY_SEPARATOR === '\\') {
            return self::readSecretWindows();
        }

        // 4. Unix/Linux/macOS interactive terminal
        return self::readSecretUnix();
    }

    /**
     * Prompts for a secret, confirms it, and validates length/complexity.
     */
    public static function askPasswordAndConfirm(
        string $passwordPrompt = 'Password (12+ characters, letters and numbers)',
        string $confirmPrompt = 'Confirm password',
        bool $allowPiped = true
    ): string {
        $password = self::secret($passwordPrompt, $allowPiped);

        $passwordErrors = Validator::validate(
            ['password' => $password],
            ['password' => 'required|password']
        );
        if ($passwordErrors !== []) {
            fwrite(STDERR, Validator::first($passwordErrors) . "\n");
            exit(1);
        }

        $confirmation = self::secret($confirmPrompt, $allowPiped);
        if (!hash_equals($password, $confirmation)) {
            fwrite(STDERR, "Passwords do not match.\n");
            exit(1);
        }

        return $password;
    }

    private static function readSecretWindows(): string
    {
        // PowerShell script executed with -EncodedCommand to avoid quoting/escaping issues.
        // Intercepts keypresses with [Console]::ReadKey($true) without echo to STDOUT.
        // Echos asterisks to [Console]::Error (STDERR) so the user gets visual masking feedback.
        // Emits the final clean password to [Console]::Out (STDOUT) which PHP captures via pipe.
        $psScript = <<<'POWERSHELL'
$pwd = ""
while ($true) {
    $key = [Console]::ReadKey($true)
    if ($key.Key -eq [ConsoleKey]::Enter) {
        [Console]::Error.WriteLine()
        break
    }
    if ($key.Key -eq [ConsoleKey]::Backspace) {
        if ($pwd.Length -gt 0) {
            $pwd = $pwd.Substring(0, $pwd.Length - 1)
            [Console]::Error.Write("`b `b")
        }
    } elseif ($key.Key -eq [ConsoleKey]::Escape) {
        while ($pwd.Length -gt 0) {
            $pwd = $pwd.Substring(0, $pwd.Length - 1)
            [Console]::Error.Write("`b `b")
        }
    } elseif ($key.KeyChar -eq 3) {
        # Ctrl+C
        [Console]::Error.WriteLine()
        exit 130
    } elseif ([int]$key.KeyChar -ge 32) {
        $pwd += $key.KeyChar
        [Console]::Error.Write("*")
    }
}
[Console]::Out.Write($pwd)
POWERSHELL;

        $encoded = base64_encode(iconv('UTF-8', 'UTF-16LE', $psScript));
        $cmd = 'powershell.exe -NoProfile -EncodedCommand ' . $encoded;

        $descriptors = [
            0 => STDIN,           // inherits console input handle for [Console]::ReadKey
            1 => ['pipe', 'w'],   // captures clean password output
            2 => STDERR,          // inherits console error handle for masked '*' display
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            fwrite(STDERR, "\nFailed to initialize secure terminal input process.\n");
            exit(1);
        }

        $secret = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        if ($exitCode === 130) {
            fwrite(STDERR, "Operation cancelled.\n");
            exit(130);
        }

        if ($exitCode !== 0) {
            fwrite(STDERR, "\nError reading secure input from terminal.\n");
            exit(1);
        }

        if ($secret === false || $secret === '') {
            fwrite(STDERR, "Password cannot be empty.\n");
            exit(1);
        }

        return (string) $secret;
    }

    private static function readSecretUnix(): string
    {
        $ttyState = @shell_exec('stty -g');
        @shell_exec('stty -echo');
        $secret = fgets(STDIN);

        if ($ttyState !== null && $ttyState !== '') {
            @shell_exec('stty ' . escapeshellarg(trim($ttyState)));
        } else {
            @shell_exec('stty echo');
        }
        fwrite(STDOUT, "\n");

        if ($secret === false) {
            fwrite(STDERR, "Failed to read password from terminal.\n");
            exit(1);
        }

        $secret = rtrim($secret, "\r\n");
        if ($secret === '') {
            fwrite(STDERR, "Password cannot be empty.\n");
            exit(1);
        }

        return $secret;
    }
}
