<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $savePath = (string) Config::get('paths.sessions');
        if (!is_dir($savePath) && !mkdir($savePath, 0700, true) && !is_dir($savePath)) {
            throw new \RuntimeException('Unable to create the session storage directory.');
        }

        $secure = self::isHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        if (PHP_VERSION_ID < 80400) {
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
        }
        ini_set('session.gc_maxlifetime', '28800');

        $base = parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);
        $cookiePath = is_string($base) && $base !== '' ? rtrim($base, '/') . '/' : '/';

        session_name((string) Config::get('app.session_name', 'axa_kenneth_session'));
        session_save_path($savePath);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new \RuntimeException('Unable to start a secure session.');
        }

        self::$started = true;

        if (!isset($_SESSION['_created_at'])) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
            $_SESSION['_regenerated_at'] = time();
        }

        // Periodic ID rotation limits the impact of a leaked session cookie.
        if (time() - (int) ($_SESSION['_regenerated_at'] ?? 0) > 900) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function isHttps(): bool
    {
        if ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ) {
            return true;
        }

        // Only honor forwarded protocol headers when the application is explicitly
        // deployed behind a trusted reverse proxy. Otherwise a client could spoof
        // the header over plain HTTP and disrupt secure-cookie behavior.
        if (!Config::get('app.trust_proxy_headers', false)) {
            return false;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (!is_string($forwarded)) {
            return false;
        }
        $first = strtolower(trim(explode(',', $forwarded)[0]));

        return $first === 'https';
    }
}
