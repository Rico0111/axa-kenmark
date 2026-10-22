<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

final class Str
{
    public static function slug(string $value): string
    {
        $value = trim($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? substr($value, 0, 170) : 'item';
    }

    public static function uniqueSlug(string $value, callable $exists): string
    {
        $base = self::slug($value);
        $candidate = $base;
        $suffix = 2;

        while ($exists($candidate)) {
            $candidate = substr($base, 0, 160) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    public static function truncate(string $value, int $length = 160): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $length - 1)) . '…';
    }

    public static function telHref(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        return 'tel:' . $digits;
    }

    public static function whatsappHref(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '63' . ltrim($digits, '0');
        }
        return 'https://wa.me/' . $digits;
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'A';
    }
}
