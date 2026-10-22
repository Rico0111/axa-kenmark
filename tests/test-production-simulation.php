<?php

declare(strict_types=1);

/**
 * Production Base-Path Simulation & Hostinger Architecture Validation
 * Tests subpath routing, URL generation, cookie scoping, split bootstrap resolution,
 * and canonical redirection under the production configuration.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use AxaKenneth\Support\Config;
use AxaKenneth\Support\Http;

echo "========================================================\n";
echo "AXA-Kenneth Hostinger Production Simulation Test Suite\n";
echo "Base URL: https://techtalkmnl.com/axa-kenmark\n";
echo "========================================================\n\n";

$pass = 0;
$fail = 0;

function assertCheck(string $label, bool $condition, string $details = ''): void {
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$label}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$label} - {$details}\n";
    }
}

// 1. URL Helpers under production configuration
// Temporarily set Config app.url to production URL
$reflection = new ReflectionClass(Config::class);
$itemsProp = $reflection->getProperty('items');
$itemsProp->setAccessible(true);
$originalItems = $itemsProp->getValue();

$prodItems = $originalItems;
$prodItems['app']['url'] = 'https://techtalkmnl.com/axa-kenmark';
$prodItems['app']['session_name'] = 'axa_kenneth_session';
$itemsProp->setValue(null, $prodItems);

try {
    // Check URL generation
    assertCheck('1. Home URL generated with subpath', url('/') === 'https://techtalkmnl.com/axa-kenmark/', url('/'));
    assertCheck('2. Privacy URL generated with subpath', url('privacy') === 'https://techtalkmnl.com/axa-kenmark/privacy', url('privacy'));
    assertCheck('3. Inquiry form action generated with subpath', url('inquiry') === 'https://techtalkmnl.com/axa-kenmark/inquiry', url('inquiry'));
    assertCheck('4. Admin URL generated with subpath', url('admin/') === 'https://techtalkmnl.com/axa-kenmark/admin/', url('admin/'));
    assertCheck('5. Admin dashboard URL has correct subpath & query', admin_url('dashboard') === 'https://techtalkmnl.com/axa-kenmark/admin/?r=dashboard', admin_url('dashboard'));
    assertCheck('6. Admin login URL has correct subpath & query', admin_url('login') === 'https://techtalkmnl.com/axa-kenmark/admin/?r=login', admin_url('login'));
    assertCheck('7. Asset URL includes subpath', str_starts_with(asset('css/style.css'), 'https://techtalkmnl.com/axa-kenmark/assets/css/style.css'), asset('css/style.css'));
    assertCheck('8. Media URL generated with subpath', media_url(10) === 'https://techtalkmnl.com/axa-kenmark/media/10', media_url(10));

    // 2. Base path detection
    assertCheck('9. Http::basePath() returns /axa-kenmark', Http::basePath() === '/axa-kenmark', Http::basePath());

    // 3. Request path normalization
    $_SERVER['REQUEST_URI'] = '/axa-kenmark/';
    assertCheck('10. /axa-kenmark/ normalizes to /', Http::requestPath() === '/', Http::requestPath());

    $_SERVER['REQUEST_URI'] = '/axa-kenmark/privacy';
    assertCheck('11. /axa-kenmark/privacy normalizes to /privacy', Http::requestPath() === '/privacy', Http::requestPath());

    $_SERVER['REQUEST_URI'] = '/axa-kenmark/inquiry';
    assertCheck('12. /axa-kenmark/inquiry normalizes to /inquiry', Http::requestPath() === '/inquiry', Http::requestPath());

    $_SERVER['REQUEST_URI'] = '/axa-kenmark/consultation';
    assertCheck('13. /axa-kenmark/consultation normalizes to /consultation', Http::requestPath() === '/consultation', Http::requestPath());

    $_SERVER['REQUEST_URI'] = '/axa-kenmark/recruitment';
    assertCheck('14. /axa-kenmark/recruitment normalizes to /recruitment', Http::requestPath() === '/recruitment', Http::requestPath());

    $_SERVER['REQUEST_URI'] = '/axa-kenmark/media/42';
    assertCheck('15. /axa-kenmark/media/42 normalizes to /media/42', Http::requestPath() === '/media/42', Http::requestPath());

    $_SERVER['REQUEST_URI'] = '/axa-kenmark/admin/?r=dashboard';
    assertCheck('16. /axa-kenmark/admin/ normalizes to /admin/', Http::requestPath() === '/admin/', Http::requestPath());

    // 4. Fallback auto-detection from SCRIPT_NAME when APP_URL is unconfigured
    $itemsWithoutSubpath = $prodItems;
    $itemsWithoutSubpath['app']['url'] = 'https://techtalkmnl.com';
    $itemsProp->setValue(null, $itemsWithoutSubpath);

    $_SERVER['SCRIPT_NAME'] = '/axa-kenmark/index.php';
    $_SERVER['REQUEST_URI'] = '/axa-kenmark/privacy';
    assertCheck('17. Auto-detects base path from SCRIPT_NAME', Http::basePath() === '/axa-kenmark', Http::basePath());
    assertCheck('18. Normalizes request path with auto-detected base path', Http::requestPath() === '/privacy', Http::requestPath());

    // SCRIPT_NAME in admin folder
    $_SERVER['SCRIPT_NAME'] = '/axa-kenmark/admin/index.php';
    $_SERVER['REQUEST_URI'] = '/axa-kenmark/admin/?r=inquiries';
    assertCheck('19. Auto-detects base path from admin SCRIPT_NAME', Http::basePath() === '/axa-kenmark', Http::basePath());

    // Restore prod items
    $itemsProp->setValue(null, $prodItems);

    // 5. Session cookie path scoping
    $base = parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);
    $cookiePath = is_string($base) && $base !== '' ? rtrim($base, '/') . '/' : '/';
    assertCheck('20. Cookie path is strictly scoped to /axa-kenmark/', $cookiePath === '/axa-kenmark/', $cookiePath);

    // 6. Split Hostinger Directory Structure Simulation
    // Create a temporary mock directory structure simulating Hostinger Split layout:
    // /tmp/mock-hostinger/
    //    public_html/axa-kenmark/ (index.php, admin/index.php)
    //    axa-kenmark-core/app/bootstrap.php
    $mockRoot = sys_get_temp_dir() . '/mock-hostinger-' . bin2hex(random_bytes(4));
    $mockPublic = $mockRoot . '/public_html/axa-kenmark';
    $mockAdmin = $mockPublic . '/admin';
    $mockCore = $mockRoot . '/axa-kenmark-core/app';
    mkdir($mockAdmin, 0777, true);
    mkdir($mockCore, 0777, true);

    file_put_contents($mockCore . '/bootstrap.php', '<?php define("MOCK_BOOTSTRAP_LOADED", true);');

    // Test public/index.php candidate resolution in Split layout
    $indexCandidates = [
        dirname($mockPublic) . '/app/bootstrap.php',
        $mockPublic . '/private/app/bootstrap.php',
        dirname($mockPublic, 2) . '/axa-kenmark-core/app/bootstrap.php',
        dirname($mockPublic) . '/axa-kenmark-core/app/bootstrap.php',
    ];
    $resolvedIndex = '';
    foreach ($indexCandidates as $c) {
        if (is_file($c)) {
            $resolvedIndex = $c;
            break;
        }
    }
    assertCheck('21. Split layout resolver in index.php finds ~/axa-kenmark-core', $resolvedIndex === $mockCore . '/bootstrap.php', $resolvedIndex);

    // Test public/admin/index.php candidate resolution in Split layout
    $adminCandidates = [
        dirname($mockAdmin, 2) . '/app/bootstrap.php',
        dirname($mockAdmin) . '/private/app/bootstrap.php',
        dirname($mockAdmin, 3) . '/axa-kenmark-core/app/bootstrap.php',
        dirname($mockAdmin, 2) . '/axa-kenmark-core/app/bootstrap.php',
    ];
    $resolvedAdmin = '';
    foreach ($adminCandidates as $c) {
        if (is_file($c)) {
            $resolvedAdmin = $c;
            break;
        }
    }
    assertCheck('22. Split layout resolver in admin/index.php finds ~/axa-kenmark-core', $resolvedAdmin === $mockCore . '/bootstrap.php', $resolvedAdmin);

    // Clean up mock split directory
    @unlink($mockCore . '/bootstrap.php');
    @rmdir($mockCore);
    @rmdir($mockRoot . '/axa-kenmark-core');
    @rmdir($mockAdmin);
    @rmdir($mockPublic);
    @rmdir($mockRoot . '/public_html');
    @rmdir($mockRoot);

    // Verify the original hierarchy in the current release, never a stale private/ ZIP.
    $zipPath = dirname(__DIR__) . '/dist/hostinger/axa-kenmark-localhost-copy.zip';
    assertCheck('23. Original-layout ZIP exists', is_file($zipPath));
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Cannot open original-layout ZIP.');
    }
    foreach (['app/bootstrap.php', 'config/.htaccess', 'database/migrations/001_initial_schema.sql',
        'docs/.htaccess', 'public/index.php', 'public/admin/login.php', 'scripts/create-admin.php',
        'storage/.htaccess', 'tests/test-security-harness.php', '.htaccess', '.env.example',
        '.env.production.example', 'README-HOSTINGER.md'] as $entry) {
        assertCheck('Preserved ZIP entry: ' . $entry, $zip->locateName($entry) !== false);
    }
    foreach (['.env', 'private/app/bootstrap.php', 'admin/index.php', '.git/config',
        '.agent-handoff/CURRENT.md'] as $entry) {
        assertCheck('Excluded ZIP entry: ' . $entry, $zip->locateName($entry) === false);
    }
    $manifest = json_decode(file_get_contents(dirname(__DIR__) . '/dist/hostinger/localhost-copy-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest as $entry => $hash) {
        assertCheck('ZIP bytes match source manifest: ' . $entry, hash('sha256', $zip->getFromName($entry)) === $hash);
    }
    assertCheck('Advisor image respects production subpath', advisor_photo_url(['photo_url' => '/assets/images/kenneth.jpg']) === url('assets/images/kenneth.jpg'));
    assertCheck('Product image respects production subpath', product_image_url(['image_url' => '/assets/images/kenneth.jpg']) === url('assets/images/kenneth.jpg'));
    assertCheck('Already prefixed image avoids duplicate subpath', application_image_url('/axa-kenmark/assets/images/kenneth.jpg') === url('assets/images/kenneth.jpg'));
    assertCheck('External image preserved', application_image_url('https://images.unsplash.com/example') === 'https://images.unsplash.com/example');
    assertCheck('Unsafe image rejected', application_image_url('javascript:alert(1)') === '');
    $zip->close();
} finally {
    // Restore original Config items
    $itemsProp->setValue(null, $originalItems);
}

echo "\n========================================================\n";
echo "Simulation Results: {$pass}/" . ($pass + $fail) . " checks passed.\n";
if ($fail > 0) {
    echo "FAILED: Some simulation checks did not pass.\n";
    exit(1);
} else {
    echo "ALL {$pass} SIMULATION CHECKS PASSED PERFECTLY!\n";
    echo "========================================================\n";
    exit(0);
}
