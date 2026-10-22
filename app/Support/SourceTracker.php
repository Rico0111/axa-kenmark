<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

/**
 * Records a short, non-personal campaign or NFC-tag identifier for the current
 * visitor session. The public form never trusts a hidden source field; only the
 * validated value retained in the server-side session is stored with an inquiry.
 */
final class SourceTracker
{
    private const SESSION_KEY = '_inquiry_source';
    private const QUERY_KEYS = ['src', 'source', 'utm_source'];

    public static function capture(): void
    {
        foreach (self::QUERY_KEYS as $key) {
            $value = $_GET[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $source = self::normalize($value);
            if ($source !== null) {
                Session::put(self::SESSION_KEY, $source);
                return;
            }
        }

        if (!is_string(Session::get(self::SESSION_KEY))) {
            Session::put(self::SESSION_KEY, 'direct');
        }
    }

    public static function current(): string
    {
        $source = Session::get(self::SESSION_KEY);
        return is_string($source) && $source !== '' ? $source : 'direct';
    }

    private static function normalize(string $value): ?string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
