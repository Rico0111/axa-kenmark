<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

final class Http
{
    public static function redirect(string $path, int $status = 303): never
    {
        $url = preg_match('#^https?://#i', $path) === 1 ? $path : Config::appUrl($path);
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    public static function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function postString(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function queryString(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function queryInt(string $key, int $default = 0, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $value = filter_var($_GET[$key] ?? $default, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default, 'min_range' => $min, 'max_range' => $max],
        ]);

        return is_int($value) ? $value : $default;
    }

    public static function ip(): string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) ? $value : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        $value = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!is_string($value)) {
            return '';
        }

        // Normalize invalid byte sequences and control characters before storing
        // the value in a utf8mb4 column or writing it to an audit row.
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr($value, 0, 255);
    }

    public static function basePath(): string
    {
        $base = parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);
        $base = is_string($base) ? rtrim($base, '/') : '';

        if ($base === '' && isset($_SERVER['SCRIPT_NAME'])) {
            $scriptDir = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
            if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.') {
                if (str_ends_with($scriptDir, '/admin')) {
                    $scriptDir = rtrim(substr($scriptDir, 0, -6), '/');
                }
                $base = $scriptDir;
            }
        }

        return $base;
    }

    public static function requestPath(): string
    {
        $value = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url((string) $value, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $base = self::basePath();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base));
            $path = $path === '' ? '/' : $path;
        }

        return $path;
    }
}
