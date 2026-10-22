<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::put(self::SESSION_KEY, $token);
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token): bool
    {
        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function requireValid(?string $token): void
    {
        if (!self::validate($token)) {
            // 419 is not a registered HTTP status and some servers rewrite it
            // to 500, so use 403 for a rejected CSRF token.
            throw new HttpException(403, 'The security token has expired. Please refresh the page and try again.');
        }
    }
}
