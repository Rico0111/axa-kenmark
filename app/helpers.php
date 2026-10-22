<?php

declare(strict_types=1);

use AxaKenneth\Support\Config;
use AxaKenneth\Support\Csrf;
use AxaKenneth\Support\Session;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Config::appUrl($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'assets/')) {
            $path = 'assets/' . $path;
        }

        $file = (string) Config::get('paths.public') . '/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return url($path) . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('admin_url')) {
    /** @param array<string, scalar> $params */
    function admin_url(string $route = 'dashboard', array $params = []): string
    {
        $query = array_merge(['r' => $route], $params);
        return url('admin/') . '?' . http_build_query($query);
    }
}

if (!function_exists('admin_page_url')) {
    /** @param array<string, scalar> $params */
    function admin_page_url(string $route, int $page, array $params = []): string
    {
        return admin_url($route, array_merge($params, ['page' => max(1, $page)]));
    }
}

if (!function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        static $nonce = null;
        if (!is_string($nonce)) {
            $nonce = base64_encode(random_bytes(18));
        }

        return $nonce;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old_value')) {
    /** @param array<string, mixed> $old */
    function old_value(array $old, string $key, string $default = ''): string
    {
        $value = $old[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }
}

if (!function_exists('checked')) {
    function checked(bool $condition): string
    {
        return $condition ? ' checked' : '';
    }
}

if (!function_exists('selected')) {
    function selected(bool $condition): string
    {
        return $condition ? ' selected' : '';
    }
}

if (!function_exists('field_invalid')) {
    /** @param array<string, string> $errors */
    function field_invalid(array $errors, string $field): string
    {
        return isset($errors[$field]) ? ' is-invalid' : '';
    }
}

if (!function_exists('field_error')) {
    /** @param array<string, string> $errors */
    function field_error(array $errors, string $field): string
    {
        if (!isset($errors[$field])) {
            return '';
        }

        return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
    }
}

if (!function_exists('safe_web_url')) {
    /**
     * Return an http(s) URL or a root-relative path. Anything else (including
     * javascript:, data:, and protocol-relative URLs) becomes an empty string.
     */
    function safe_web_url(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return $value;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '\\')) {
            return $value;
        }

        return '';
    }
}

if (!function_exists('media_url')) {
    function media_url(?int $mediaId): ?string
    {
        if ($mediaId === null || $mediaId <= 0) {
            return null;
        }

        return url('media/' . $mediaId);
    }
}

if (!function_exists('application_image_url')) {
    // Stored /assets/... paths belong to this application, including on a subpath.
    function application_image_url(?string $value): string
    {
        $value = safe_web_url($value);
        if (str_starts_with($value, '/')) {
            $base = \AxaKenneth\Support\Http::basePath();
            if ($base !== '' && ($value === $base || str_starts_with($value, $base . '/'))) {
                $value = substr($value, strlen($base));
            }
            return url(ltrim($value, '/'));
        }
        return $value;
    }
}

if (!function_exists('advisor_photo_url')) {
    /** @param array<string, mixed>|null $profile */
    function advisor_photo_url(?array $profile): string
    {
        if ($profile !== null) {
            $uploaded = media_url(isset($profile['photo_media_id']) ? (int) $profile['photo_media_id'] : null);
            if ($uploaded !== null) {
                return $uploaded;
            }
            $photoUrl = application_image_url(isset($profile['photo_url']) ? (string) $profile['photo_url'] : null);
            if ($photoUrl !== '') {
                return $photoUrl;
            }
        }

        return url('assets/images/kenneth.jpg');
    }
}

if (!function_exists('product_image_url')) {
    /** @param array<string, mixed> $product */
    function product_image_url(array $product): string
    {
        $uploaded = media_url(isset($product['image_media_id']) ? (int) $product['image_media_id'] : null);
        if ($uploaded !== null) {
            return $uploaded;
        }
        $imageUrl = application_image_url(isset($product['image_url']) ? (string) $product['image_url'] : null);
        if ($imageUrl !== '') {
            return $imageUrl;
        }

        return url('assets/images/kenneth.jpg');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $value, string $format = 'j M Y'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '—' : date($format, $timestamp);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '—' : date('j M Y, g:i a', $timestamp);
    }
}

if (!function_exists('is_verified')) {
    /** @param array<string, mixed> $row */
    function is_verified(array $row, string $field): bool
    {
        return !empty($row[$field . '_verified_at']);
    }
}

if (!function_exists('security_headers')) {
    function security_headers(bool $admin = false): void
    {
        if (headers_sent()) {
            return;
        }

        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data: https://images.unsplash.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "script-src 'self' 'nonce-" . csp_nonce() . "'; connect-src 'self' https://fonts.googleapis.com; "
            . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"
        );

        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if ($admin) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
    }
}
