<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $key = (string) Env::get('APP_KEY', '');
        if (strlen($key) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters. Run scripts/setup-local-database.php or update .env.');
        }
        if (Env::get('APP_ENV') === 'production' && preg_match('/^(replace[-_]|change[-_])/i', $key)) {
            throw new RuntimeException('Replace the example APP_KEY with a unique production key from scripts/generate-key.php.');
        }

        $publicDefault = is_dir($basePath . '/public')
            ? $basePath . '/public'
            : (is_dir(dirname($basePath) . '/assets') ? dirname($basePath) : $basePath . '/public');

        self::$items = [
            'app' => [
                'name' => (string) Env::get('APP_NAME', 'Kenneth | AXA Philippines'),
                'env' => (string) Env::get('APP_ENV', 'local'),
                'debug' => Env::bool('APP_DEBUG', false),
                'url' => rtrim((string) Env::get('APP_URL', 'http://axa-kenmark.test'), '/'),
                'key' => $key,
                'timezone' => (string) Env::get('APP_TIMEZONE', 'Asia/Manila'),
                'session_name' => (string) Env::get('SESSION_NAME', 'axa_kenneth_session'),
                'session_idle_minutes' => Env::int('SESSION_IDLE_MINUTES', 30),
                'session_absolute_hours' => Env::int('SESSION_ABSOLUTE_HOURS', 8),
                'trust_proxy_headers' => Env::bool('TRUST_PROXY_HEADERS', false),
                'privacy_version' => (string) Env::get('PRIVACY_NOTICE_VERSION', '2026-10-22'),
            ],
            'db' => [
                'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
                'port' => Env::int('DB_PORT', 3306),
                'name' => (string) Env::get('DB_NAME', 'axa_kenneth_db'),
                'user' => (string) Env::get('DB_USER', 'axa_kenneth_app'),
                'password' => (string) Env::get('DB_PASSWORD', ''),
                'charset' => (string) Env::get('DB_CHARSET', 'utf8mb4'),
                'migration_user' => (string) Env::get('DB_MIGRATION_USER', 'axa_kenneth_migrator'),
                'migration_password' => (string) Env::get('DB_MIGRATION_PASSWORD', ''),
            ],
            'media' => [
                'max_bytes' => Env::int('MEDIA_MAX_BYTES', 5 * 1024 * 1024),
                'path' => $basePath . '/storage/uploads/media',
            ],
            'paths' => [
                'base' => $basePath,
                'app' => $basePath . '/app',
                'config' => $basePath . '/config',
                'database' => $basePath . '/database',
                'public' => (string) Env::get('PUBLIC_PATH', $publicDefault),
                'storage' => $basePath . '/storage',
                'logs' => $basePath . '/storage/logs',
                'sessions' => $basePath . '/storage/sessions',
                'cache' => $basePath . '/storage/cache',
                'views' => $basePath . '/app/Views',
            ],
        ];

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function appUrl(string $path = ''): string
    {
        $base = (string) self::get('app.url', '');
        return $base . '/' . ltrim($path, '/');
    }
}
