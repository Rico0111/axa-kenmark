<?php

declare(strict_types=1);

use AxaKenneth\Controllers\PublicController;
use AxaKenneth\Support\Config;
use AxaKenneth\Support\Csrf;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Session;
use AxaKenneth\Support\SourceTracker;

// Locate application bootstrap across local development and production split deployment
$bootstrapCandidates = [
    dirname(__DIR__) . '/app/bootstrap.php',                            // 1. Local Monolithic (app/ alongside public/)
    __DIR__ . '/private/app/bootstrap.php',                             // 2. Single-Folder layout (public_html/axa-kenmark/private/app/bootstrap.php)
    dirname(__DIR__, 2) . '/axa-kenmark-core/app/bootstrap.php',       // 3. Hostinger split (public_html/axa-kenmark -> ~/axa-kenmark-core)
    dirname(__DIR__) . '/axa-kenmark-core/app/bootstrap.php',          // 4. Alternate split
    is_file(__DIR__ . '/core-path.php') ? (static function() {         // 5. Explicit override file
        $p = require __DIR__ . '/core-path.php';
        return is_string($p) ? rtrim($p, '/\\') . '/app/bootstrap.php' : '';
    })() : '',
];

$bootstrapped = false;
foreach ($bootstrapCandidates as $candidate) {
    if ($candidate !== '' && is_file($candidate)) {
        require $candidate;
        $bootstrapped = true;
        break;
    }
}

if (!$bootstrapped) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Service unavailable</title></head><body style="font-family:sans-serif;padding:2rem;"><h1>Service unavailable</h1><p>We could not complete your request. Please try again shortly.</p></body></html>';
    exit;
}

$method = Http::method();
$rawUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$rawPath = parse_url($rawUri, PHP_URL_PATH);
$rawPath = is_string($rawPath) && $rawPath !== '' ? $rawPath : '/';

$basePath = Http::basePath();

// If accessed at the base folder without a trailing slash (e.g. /axa-kenmark),
// redirect to the canonical trailing-slash URL (/axa-kenmark/)
if ($basePath !== '' && $rawPath === $basePath) {
    header('Location: ' . Config::appUrl('/'), true, 301);
    exit;
}

$path = Http::requestPath();

if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

if ($path === '/index.php' || $path === '/public/index.php' || $path === '/public') {
    header('Location: ' . Config::appUrl('/'), true, 301);
    exit;
}

if ($path === '/private' || str_starts_with($path, '/private/')) {
    throw new HttpException(403, 'Access denied.');
}

Session::start();
security_headers();
SourceTracker::capture();

$postRoutes = [
    '/inquiry' => 'general',
    '/consultation' => 'consultation',
    '/recruitment' => 'recruitment',
];

if ($method === 'POST') {
    if (!isset($postRoutes[$path])) {
        throw new HttpException(405, 'This request method is not supported for this page.');
    }

    $token = $_POST['_csrf'] ?? null;
    Csrf::requireValid(is_string($token) ? $token : null);

    try {
        (new PublicController())->submitInquiry($postRoutes[$path]);
    } catch (PDOException $exception) {
        error_log('Public form database failure: ' . $exception->getMessage());
        throw new HttpException(503, 'The website is temporarily unavailable. Please try again shortly.');
    }
    exit;
}

if (isset($postRoutes[$path])) {
    header('Allow: POST');
    throw new HttpException(405, 'This request method is not supported for this page.');
}

if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    throw new HttpException(405, 'This request method is not supported for this page.');
}

$controller = new PublicController();

try {
    if ($path === '/') {
        $controller->home();
    } elseif ($path === '/privacy') {
        $controller->privacy();
    } elseif ($path === '/contact.vcf') {
        $controller->vcard();
    } elseif ($path === '/sitemap.xml') {
        $controller->sitemap();
    } elseif ($path === '/robots.txt') {
        $controller->robots();
    } elseif (preg_match('#^/media/([1-9][0-9]{0,18})$#', $path, $matches) === 1) {
        $_GET['id'] = $matches[1];
        $controller->media();
    } else {
        throw new HttpException(404, 'The page you requested could not be found.');
    }
} catch (PDOException $exception) {
    error_log('Public page database failure: ' . $exception->getMessage());
    throw new HttpException(503, 'The website is temporarily unavailable. Please try again shortly.');
}
