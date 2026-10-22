<?php

declare(strict_types=1);

use AxaKenneth\Support\Config;
use AxaKenneth\Support\Env;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'AxaKenneth\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
        return;
    }

    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Configure failure handling before loading configuration. If .env or APP_KEY
// is invalid, the visitor must receive a safe error page rather than a stack
// trace, while the details are written to the private log directory.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$bootstrapLogDirectory = BASE_PATH . '/storage/logs';
if (!is_dir($bootstrapLogDirectory)) {
    @mkdir($bootstrapLogDirectory, 0700, true);
}
ini_set('error_log', $bootstrapLogDirectory . '/php-error.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($bootstrapLogDirectory): void {
    $status = $exception instanceof HttpException ? $exception->statusCode() : 500;
    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    $requestPath = Http::requestPath();
    $admin = $requestPath === '/admin' || str_starts_with($requestPath, '/admin/');
    $logDirectory = Config::get('paths.logs', $bootstrapLogDirectory);
    $logDirectory = is_string($logDirectory) && $logDirectory !== '' ? $logDirectory : $bootstrapLogDirectory;
    $logFile = $logDirectory . '/app-error.log';

    $logLine = sprintf(
        "[%s] %s %s — %s: %s in %s:%d\n%s\n",
        date('c'),
        Http::method(),
        $requestPath,
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    if (@error_log($logLine, 3, $logFile) === false) {
        error_log(trim($logLine));
    }

    if (headers_sent()) {
        return;
    }

    http_response_code($status);
    security_headers($admin);

    $appName = Config::get('app.name', 'Kenneth | AXA Philippines');
    $appName = is_string($appName) && $appName !== '' ? $appName : 'Kenneth | AXA Philippines';
    $message = $status < 500
        ? $exception->getMessage()
        : 'We could not complete your request. Please try again shortly.';
    $publicMessage = $message;

    if ($status === 429) {
        header('Retry-After: 60');
    } elseif ($status === 500) {
        header('Retry-After: 60');
    }

    $viewsPath = Config::get('paths.views', BASE_PATH . '/app/Views');
    $errorView = (is_string($viewsPath) && $viewsPath !== '' ? $viewsPath : BASE_PATH . '/app/Views') . '/errors/http.php';

    try {
        if (is_file($errorView)) {
            require $errorView;
            exit;
        }
    } catch (Throwable $renderingFailure) {
        @error_log('Error view rendering failed: ' . $renderingFailure->getMessage(), 3, $logFile);
    }

    // Last-resort page for failures before the normal view layer is available.
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Service unavailable</title></head><body style="font-family:sans-serif;padding:2rem;"><h1>Service unavailable</h1><p>We could not complete your request. Please try again shortly.</p></body></html>';
    exit;
});

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH);

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Manila'));
mb_internal_encoding('UTF-8');

foreach (['logs', 'sessions', 'cache'] as $directory) {
    $path = (string) Config::get('paths.' . $directory);
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        error_log('Unable to create storage directory: ' . $path);
    }
}

ini_set('error_log', (string) Config::get('paths.logs') . '/php-error.log');
