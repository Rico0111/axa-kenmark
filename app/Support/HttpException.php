<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be processed.',
            403 => 'You do not have permission to access this page.',
            404 => 'The page you requested could not be found.',
            405 => 'The request method is not allowed.',
            419 => 'The security token has expired. Please refresh the page and try again.',
            429 => 'Too many requests. Please wait a moment and try again.',
            503 => 'The service is temporarily unavailable.',
            default => 'Something went wrong. Please try again.',
        };
    }
}
