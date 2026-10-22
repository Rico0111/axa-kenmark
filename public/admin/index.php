<?php

declare(strict_types=1);

use AxaKenneth\Controllers\Admin\AdminAccountController;
use AxaKenneth\Controllers\Admin\AdvisorController;
use AxaKenneth\Controllers\Admin\AuthController;
use AxaKenneth\Controllers\Admin\CategoryController;
use AxaKenneth\Controllers\Admin\DashboardController;
use AxaKenneth\Controllers\Admin\InquiryController;
use AxaKenneth\Controllers\Admin\MediaController;
use AxaKenneth\Controllers\Admin\ProductController;
use AxaKenneth\Controllers\Admin\SettingsController;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Csrf;
use AxaKenneth\Support\Http;
use AxaKenneth\Support\HttpException;
use AxaKenneth\Support\Session;

// Locate application bootstrap across local development and production split deployment
$bootstrapCandidates = [
    dirname(__DIR__, 2) . '/app/bootstrap.php',                        // 1. Local Monolithic (app/ alongside public/)
    dirname(__DIR__) . '/private/app/bootstrap.php',                   // 2. Single-Folder layout (public_html/axa-kenmark/private/app/bootstrap.php)
    dirname(__DIR__, 3) . '/axa-kenmark-core/app/bootstrap.php',       // 3. Hostinger split (public_html/axa-kenmark/admin -> ~/axa-kenmark-core)
    dirname(__DIR__, 2) . '/axa-kenmark-core/app/bootstrap.php',       // 4. Alternate split
    is_file(__DIR__ . '/../core-path.php') ? (static function() {      // 5. Explicit override file
        $p = require __DIR__ . '/../core-path.php';
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

Session::start();
security_headers(true);

$method = Http::method();
$route = Http::queryString('r', 'dashboard');

$routes = [
    'login' => ['GET', 'POST'],
    'logout' => ['POST'],
    'dashboard' => ['GET'],
    'inquiries' => ['GET'],
    'inquiry-view' => ['GET'],
    'inquiry-update' => ['POST'],
    'advisor' => ['GET'],
    'advisor-update' => ['POST'],
    'categories' => ['GET'],
    'category-create' => ['GET'],
    'category-store' => ['POST'],
    'category-edit' => ['GET'],
    'category-update' => ['POST'],
    'category-delete' => ['POST'],
    'products' => ['GET'],
    'product-create' => ['GET'],
    'product-store' => ['POST'],
    'product-edit' => ['GET'],
    'product-update' => ['POST'],
    'product-delete' => ['POST'],
    'media' => ['GET'],
    'media-upload' => ['POST'],
    'media-delete' => ['POST'],
    'settings' => ['GET'],
    'settings-update' => ['POST'],
    'admins' => ['GET'],
    'admin-create' => ['GET'],
    'admin-store' => ['POST'],
    'admin-edit' => ['GET'],
    'admin-update' => ['POST'],
    'admin-password' => ['GET'],
    'admin-password-update' => ['POST'],
];

if (!isset($routes[$route])) {
    throw new HttpException(404, 'The administrator page you requested could not be found.');
}

if (!in_array($method, $routes[$route], true)) {
    header('Allow: ' . implode(', ', $routes[$route]));
    throw new HttpException(405, 'This request method is not supported for this page.');
}

if ($method === 'POST') {
    $token = $_POST['_csrf'] ?? null;
    Csrf::requireValid(is_string($token) ? $token : null);
}

try {
    if ($route === 'login') {
        $controller = new AuthController();
        if ($method === 'POST') {
            $controller->login();
        } else {
            $controller->showLogin();
        }
        exit;
    }

    Auth::requireLogin();

    switch ($route) {
        case 'logout':
            (new AuthController())->logout();
            break;

        case 'dashboard':
            (new DashboardController())->index();
            break;

        case 'inquiries':
            (new InquiryController())->index();
            break;
        case 'inquiry-view':
            (new InquiryController())->show();
            break;
        case 'inquiry-update':
            (new InquiryController())->update();
            break;

        case 'advisor':
            (new AdvisorController())->edit();
            break;
        case 'advisor-update':
            (new AdvisorController())->update();
            break;

        case 'categories':
            (new CategoryController())->index();
            break;
        case 'category-create':
            (new CategoryController())->create();
            break;
        case 'category-store':
            (new CategoryController())->store();
            break;
        case 'category-edit':
            (new CategoryController())->edit();
            break;
        case 'category-update':
            (new CategoryController())->update();
            break;
        case 'category-delete':
            (new CategoryController())->delete();
            break;

        case 'products':
            (new ProductController())->index();
            break;
        case 'product-create':
            (new ProductController())->create();
            break;
        case 'product-store':
            (new ProductController())->store();
            break;
        case 'product-edit':
            (new ProductController())->edit();
            break;
        case 'product-update':
            (new ProductController())->update();
            break;
        case 'product-delete':
            (new ProductController())->delete();
            break;

        case 'media':
            (new MediaController())->index();
            break;
        case 'media-upload':
            (new MediaController())->upload();
            break;
        case 'media-delete':
            (new MediaController())->delete();
            break;

        case 'settings':
            (new SettingsController())->edit();
            break;
        case 'settings-update':
            (new SettingsController())->update();
            break;

        case 'admins':
            (new AdminAccountController())->index();
            break;
        case 'admin-create':
            (new AdminAccountController())->create();
            break;
        case 'admin-store':
            (new AdminAccountController())->store();
            break;
        case 'admin-edit':
            (new AdminAccountController())->edit();
            break;
        case 'admin-update':
            (new AdminAccountController())->update();
            break;
        case 'admin-password':
            (new AdminAccountController())->password();
            break;
        case 'admin-password-update':
            (new AdminAccountController())->updatePassword();
            break;
    }
} catch (PDOException $exception) {
    error_log('Admin database failure: ' . $exception->getMessage());
    throw new HttpException(503, 'The administration area is temporarily unavailable. Please try again shortly.');
}
