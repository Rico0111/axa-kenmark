<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

final class Flash
{
    private const SESSION_KEY = '_flash';

    public static function set(string $type, string $message): void
    {
        $messages = Session::get(self::SESSION_KEY, []);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = ['type' => $type, 'message' => $message];
        Session::put(self::SESSION_KEY, $messages);
    }

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    /**
     * @return list<array{type:string,message:string}>
     */
    public static function consume(): array
    {
        $messages = Session::get(self::SESSION_KEY, []);
        Session::forget(self::SESSION_KEY);

        return is_array($messages) ? $messages : [];
    }
}
