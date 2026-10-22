<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use AxaKenneth\Repositories\AdminRepository;

final class Auth
{
    private const LOGIN_ACCOUNT_MAX = 5;
    private const LOGIN_IP_MAX = 20;
    private const LOGIN_DECAY_SECONDS = 900;

    /** @var array<string, mixed>|null */
    private static ?array $currentAdmin = null;

    /**
     * @return array{ok:bool,message:string}
     */
    public static function attempt(string $email, string $password): array
    {
        Session::start();
        $email = strtolower(trim($email));
        $ip = Http::ip();
        $accountKey = $email . '|' . $ip;

        if (RateLimiter::tooManyAttempts('login.account', $accountKey, self::LOGIN_ACCOUNT_MAX, self::LOGIN_DECAY_SECONDS)
            || RateLimiter::tooManyAttempts('login.ip', $ip, self::LOGIN_IP_MAX, self::LOGIN_DECAY_SECONDS)
        ) {
            return [
                'ok' => false,
                'message' => 'Too many sign-in attempts. Please wait a few minutes and try again.',
            ];
        }

        if ($email === '' || $password === '') {
            self::recordFailure($email, $ip);
            return ['ok' => false, 'message' => 'Invalid email address or password.'];
        }

        $repository = new AdminRepository();
        $admin = $repository->findByEmail($email);

        if ($admin === null || (int) $admin['is_active'] !== 1) {
            self::recordFailure($email, $ip);
            usleep(200000);
            return ['ok' => false, 'message' => 'Invalid email address or password.'];
        }

        if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
            self::recordFailure($email, $ip);
            return ['ok' => false, 'message' => 'This account is temporarily locked. Please try again later.'];
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            self::recordFailure($email, $ip);
            usleep(200000);
            return ['ok' => false, 'message' => 'Invalid email address or password.'];
        }

        if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_ARGON2ID, self::hashOptions())) {
            $repository->updatePasswordHash((int) $admin['id'], self::hashPassword($password));
        }

        RateLimiter::clear('login.account', $accountKey);
        RateLimiter::clear('login.ip', $ip);

        Session::regenerate();
        Csrf::rotate();
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_session_version'] = (int) ($admin['session_version'] ?? 0);
        $_SESSION['auth_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ua_hash'] = self::userAgentHash();

        $repository->updateLastLogin((int) $admin['id']);
        AuditLogger::record((int) $admin['id'], 'auth.login', 'admin', (int) $admin['id']);

        self::$currentAdmin = null;

        return ['ok' => true, 'message' => 'Signed in successfully.'];
    }

    public static function check(): bool
    {
        Session::start();

        $adminId = (int) Session::get('admin_id', 0);
        if ($adminId <= 0) {
            return false;
        }

        if (!hash_equals((string) Session::get('ua_hash', ''), self::userAgentHash())) {
            self::logout();
            return false;
        }

        $now = time();
        $lastActivity = (int) Session::get('last_activity', 0);
        $authTime = (int) Session::get('auth_time', 0);
        $idleLimit = max(5, (int) Config::get('app.session_idle_minutes', 30)) * 60;
        $absoluteLimit = max(1, (int) Config::get('app.session_absolute_hours', 8)) * 3600;

        if ($lastActivity <= 0 || $now - $lastActivity > $idleLimit || $authTime <= 0 || $now - $authTime > $absoluteLimit) {
            self::logout();
            return false;
        }

        $admin = (new AdminRepository())->findById($adminId);
        if ($admin === null || (int) $admin['is_active'] !== 1) {
            self::logout();
            return false;
        }

        $expectedVersion = Session::get('admin_session_version');
        if (!is_int($expectedVersion) || (int) $admin['session_version'] !== $expectedVersion) {
            self::logout();
            return false;
        }

        Session::put('last_activity', $now);
        self::$currentAdmin = $admin;

        return true;
    }

    /** @return array<string, mixed>|null */
    public static function admin(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return self::$currentAdmin;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Flash::info('Please sign in to continue.');
            Http::redirect('/admin/?r=login');
        }
    }

    /** @param list<string> $roles */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $admin = self::admin();
        if ($admin === null || !in_array((string) $admin['role'], $roles, true)) {
            throw new HttpException(403, 'Your account does not have permission to perform this action.');
        }
    }

    /** Keep the current session valid after the signed-in administrator resets their own password. */
    public static function syncSessionVersion(int $version): void
    {
        Session::start();
        Session::put('admin_session_version', $version);
        Session::regenerate();
        Csrf::rotate();
        self::$currentAdmin = null;
    }

    public static function logout(): void
    {
        Session::start();
        $adminId = (int) Session::get('admin_id', 0);
        if ($adminId > 0) {
            AuditLogger::record($adminId, 'auth.logout', 'admin', $adminId);
        }

        self::$currentAdmin = null;
        Session::destroy();
    }

    public static function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, self::hashOptions());
        if (!is_string($hash)) {
            throw new \RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    /** @return array{memory_cost:int,time_cost:int,threads:int} */
    public static function hashOptions(): array
    {
        return [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ];
    }

    private static function recordFailure(string $email, string $ip): void
    {
        RateLimiter::hit('login.account', $email . '|' . $ip, self::LOGIN_ACCOUNT_MAX, self::LOGIN_DECAY_SECONDS);
        RateLimiter::hit('login.ip', $ip, self::LOGIN_IP_MAX, self::LOGIN_DECAY_SECONDS);
        AuditLogger::record(null, 'auth.login_failed', 'admin', null, [
            'email_sha256' => hash('sha256', $email),
        ]);
    }

    private static function userAgentHash(): string
    {
        return hash('sha256', Http::userAgent());
    }
}
