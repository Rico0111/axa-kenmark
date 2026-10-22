<?php

declare(strict_types=1);

/**
 * AXA-Kenneth Full Security, Business Workflow & Regression Test Harness
 * Exercises all 10 security & compliance sections (119 automated checks)
 * against http://axa-kenmark.test/ with full test isolation and automatic cleanup.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use AxaKenneth\Repositories\AdminRepository;
use AxaKenneth\Repositories\AdvisorRepository;
use AxaKenneth\Repositories\CategoryRepository;
use AxaKenneth\Repositories\InquiryRepository;
use AxaKenneth\Repositories\MediaRepository;
use AxaKenneth\Repositories\ProductRepository;
use AxaKenneth\Support\Auth;
use AxaKenneth\Support\Config;
use AxaKenneth\Support\Database;

$base = rtrim((string) Config::get('app.url', 'http://axa-kenmark.test'), '/');
if (Config::get('app.env') === 'production') {
    throw new RuntimeException('Run this mutating suite only against an isolated test database.');
}
$tempDir = sys_get_temp_dir() . '/axa-audit-' . bin2hex(random_bytes(4));
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0700, true);
}

$totalChecks = 0;
$passedChecks = 0;
$failedChecks = [];

function check(string $label, bool $condition, string $details = ''): void
{
    global $totalChecks, $passedChecks, $failedChecks;
    $totalChecks++;
    if ($condition) {
        $passedChecks++;
        echo "  [PASS] {$label}\n";
    } else {
        $failedChecks[] = "{$label}: {$details}";
        echo "  [FAIL] {$label} - {$details}\n";
    }
}

function curlRequest(string $url, array $options = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if (isset($options['method'])) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $options['method']);
    }
    if (isset($options['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
    }
    if (isset($options['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($options['post']) ? http_build_query($options['post']) : $options['post']);
    }
    if (isset($options['cookie_jar'])) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $options['cookie_jar']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $options['cookie_jar']);
    }

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);

    $headerStr = $raw !== false ? substr($raw, 0, $headerSize) : '';
    $body = $raw !== false ? substr($raw, $headerSize) : '';

    return [
        'code' => $code,
        'headers' => $headerStr,
        'body' => $body,
        'error' => $error,
    ];
}

function extractCsrf(string $html): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function extractNonce(string $headers): string
{
    if (preg_match('/script-src [^;]*\'nonce-([^\']+)\'/', $headers, $m)) {
        return $m[1];
    }
    return '';
}

echo "======================================================================\n";
echo "AXA-KENNETH COMPREHENSIVE SECURITY & AUDIT HARNESS (119 CHECKS)\n";
echo "Target: {$base}\n";
echo "======================================================================\n\n";

$adminRepo = new AdminRepository();
$superEmail = 'test-super-audit-' . bin2hex(random_bytes(4)) . '@example.test';
$superPass = 'SuperAuditPass2026!X';
$superId = $adminRepo->create('Super Auditor', $superEmail, Auth::hashPassword($superPass), 'super_admin');

$regEmail = 'test-reg-audit-' . bin2hex(random_bytes(4)) . '@example.test';
$regPass = 'RegAuditPass2026!Y';
$regId = $adminRepo->create('Regular Auditor', $regEmail, Auth::hashPassword($regPass), 'admin');

$superJar = $tempDir . '/super.cookies';
$regJar = $tempDir . '/reg.cookies';
$anonJar = $tempDir . '/anon.cookies';

try {
    // ---------------------------------------------------------
    // SECTION 1: HEADERS & COOKIES (17 checks)
    // ---------------------------------------------------------
    echo "--- SECTION 1: Headers & Cookies ---\n";
    $homeRes = curlRequest("{$base}/", ['cookie_jar' => $anonJar]);
    check('1.1 Public home returns 200', $homeRes['code'] === 200, "Code: {$homeRes['code']}");
    check('1.2 X-Content-Type-Options: nosniff present', str_contains($homeRes['headers'], 'X-Content-Type-Options: nosniff'));
    check('1.3 X-Frame-Options: DENY present', str_contains($homeRes['headers'], 'X-Frame-Options: DENY'));
    check('1.4 X-Permitted-Cross-Domain-Policies: none present', str_contains($homeRes['headers'], 'X-Permitted-Cross-Domain-Policies: none'));
    check('1.5 Referrer-Policy: strict-origin-when-cross-origin present', str_contains($homeRes['headers'], 'Referrer-Policy: strict-origin-when-cross-origin'));
    check('1.6 Permissions-Policy present', str_contains($homeRes['headers'], 'Permissions-Policy:'));
    check('1.7 Cross-Origin-Opener-Policy: same-origin present', str_contains($homeRes['headers'], 'Cross-Origin-Opener-Policy: same-origin'));
    check('1.8 X-Powered-By is absent', !str_contains($homeRes['headers'], 'X-Powered-By'));

    $nonce = extractNonce($homeRes['headers']);
    check('1.9 CSP header has random script nonce', $nonce !== '' && strlen($nonce) >= 16, "Nonce: {$nonce}");
    check('1.10 Inline script uses matching CSP nonce', $nonce !== '' && str_contains($homeRes['body'], 'nonce="' . $nonce . '"'));

    check('1.11 Session cookie is HttpOnly', preg_match('/Set-Cookie:.*HttpOnly/i', $homeRes['headers']) === 1);
    check('1.12 Session cookie is SameSite=Lax', preg_match('/Set-Cookie:.*SameSite=Lax/i', $homeRes['headers']) === 1);
    check('1.13 Session cookie is not Secure over plain HTTP', preg_match('/Set-Cookie:.*Secure/i', $homeRes['headers']) === 0);
    check('1.14 HSTS is absent over plain HTTP', !str_contains($homeRes['headers'], 'Strict-Transport-Security'));

    // Spoofed header check
    $spoofRes = curlRequest("{$base}/", [
        'headers' => ['X-Forwarded-Proto: https'],
    ]);
    check('1.15 Spoofed X-Forwarded-Proto does not set Secure cookie when trust_proxy_headers=false', preg_match('/Set-Cookie:.*Secure/i', $spoofRes['headers']) === 0);
    check('1.16 Spoofed X-Forwarded-Proto does not send HSTS', !str_contains($spoofRes['headers'], 'Strict-Transport-Security'));
    check('1.17 CSP frame-ancestors none present', str_contains($homeRes['headers'], "frame-ancestors 'none'"));

    // ---------------------------------------------------------
    // SECTION 2: AUTH & ROLES (27 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 2: Auth & Roles ---\n";
    $noAuthRes = curlRequest("{$base}/admin/");
    check('2.1 Unauthenticated /admin/ redirects (303)', $noAuthRes['code'] === 303, "Code: {$noAuthRes['code']}");
    check('2.2 Location header points to login', str_contains($noAuthRes['headers'], 'Location:') && str_contains($noAuthRes['headers'], 'r=login'));

    $noAuthDash = curlRequest("{$base}/admin/?r=dashboard");
    check('2.3 Unauthenticated /admin/?r=dashboard redirects (303)', $noAuthDash['code'] === 303);

    // Super admin login
    $loginPage = curlRequest("{$base}/admin/?r=login", ['cookie_jar' => $superJar]);
    check('2.4 Login page returns 200', $loginPage['code'] === 200);
    $superCsrf = extractCsrf($loginPage['body']);
    check('2.5 Login page has CSRF token', $superCsrf !== '');

    $superLoginRes = curlRequest("{$base}/admin/?r=login", [
        'cookie_jar' => $superJar,
        'post' => ['_csrf' => $superCsrf, 'email' => $superEmail, 'password' => $superPass],
    ]);
    check('2.6 Super admin login redirects (303)', $superLoginRes['code'] === 303);
    check('2.7 Super admin login redirects to dashboard', str_contains($superLoginRes['headers'], 'r=dashboard'));

    $superDash = curlRequest("{$base}/admin/?r=dashboard", ['cookie_jar' => $superJar]);
    check('2.8 Dashboard returns 200 for super admin', $superDash['code'] === 200);
    check('2.9 Dashboard sends X-Robots-Tag: noindex', str_contains($superDash['headers'], 'X-Robots-Tag: noindex'));
    check('2.10 Super admin nav displays Settings link', str_contains($superDash['body'], 'r=settings'));
    check('2.11 Super admin nav displays Administrators link', str_contains($superDash['body'], 'r=admins'));

    $superSettings = curlRequest("{$base}/admin/?r=settings", ['cookie_jar' => $superJar]);
    check('2.12 Super admin settings accessible (200)', $superSettings['code'] === 200);

    $superAdmins = curlRequest("{$base}/admin/?r=admins", ['cookie_jar' => $superJar]);
    check('2.13 Super admin accounts list accessible (200)', $superAdmins['code'] === 200);

    $superAdminCreate = curlRequest("{$base}/admin/?r=admin-create", ['cookie_jar' => $superJar]);
    check('2.14 Super admin account create accessible (200)', $superAdminCreate['code'] === 200);

    $superAdvisor = curlRequest("{$base}/admin/?r=advisor", ['cookie_jar' => $superJar]);
    check('2.15 Super admin advisor page accessible (200)', $superAdvisor['code'] === 200);
    check('2.16 Super admin sees title_verified checkbox', str_contains($superAdvisor['body'], 'name="title_verified"'));
    check('2.17 Super admin sees authorization_verified checkbox', str_contains($superAdvisor['body'], 'name="authorization_verified"'));

    // Regular admin login
    $regLoginPage = curlRequest("{$base}/admin/?r=login", ['cookie_jar' => $regJar]);
    $regCsrf = extractCsrf($regLoginPage['body']);
    $regLoginRes = curlRequest("{$base}/admin/?r=login", [
        'cookie_jar' => $regJar,
        'post' => ['_csrf' => $regCsrf, 'email' => $regEmail, 'password' => $regPass],
    ]);
    check('2.18 Regular admin login redirects (303)', $regLoginRes['code'] === 303);

    $regDash = curlRequest("{$base}/admin/?r=dashboard", ['cookie_jar' => $regJar]);
    check('2.19 Regular admin dashboard returns 200', $regDash['code'] === 200);
    check('2.20 Regular admin nav hides Settings link', !str_contains($regDash['body'], 'r=settings'));
    check('2.21 Regular admin nav hides Administrators link', !str_contains($regDash['body'], 'r=admins'));

    $regInquiries = curlRequest("{$base}/admin/?r=inquiries", ['cookie_jar' => $regJar]);
    check('2.22 Regular admin access to Inquiries (200)', $regInquiries['code'] === 200);

    $regCategories = curlRequest("{$base}/admin/?r=categories", ['cookie_jar' => $regJar]);
    check('2.23 Regular admin access to Categories (200)', $regCategories['code'] === 200);

    $regProducts = curlRequest("{$base}/admin/?r=products", ['cookie_jar' => $regJar]);
    check('2.24 Regular admin access to Products (200)', $regProducts['code'] === 200);

    $regMedia = curlRequest("{$base}/admin/?r=media", ['cookie_jar' => $regJar]);
    check('2.25 Regular admin access to Media (200)', $regMedia['code'] === 200);

    // Regular admin blocked routes (403)
    $regBlockSettings = curlRequest("{$base}/admin/?r=settings", ['cookie_jar' => $regJar]);
    check('2.26 Regular admin blocked from settings (403)', $regBlockSettings['code'] === 403, "Code: {$regBlockSettings['code']}");

    $regBlockAdmins = curlRequest("{$base}/admin/?r=admins", ['cookie_jar' => $regJar]);
    check('2.27 Regular admin blocked from admins list (403)', $regBlockAdmins['code'] === 403, "Code: {$regBlockAdmins['code']}");

    // ---------------------------------------------------------
    // SECTION 3: CSRF & METHODS (11 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 3: CSRF & Methods ---\n";
    $noCsrfPost = curlRequest("{$base}/inquiry", [
        'post' => ['name' => 'No CSRF', 'email' => 'nocsrf@example.test', 'message' => 'Testing missing CSRF token'],
    ]);
    check('3.1 Public POST without _csrf returns 403', $noCsrfPost['code'] === 403, "Code: {$noCsrfPost['code']}");

    $badCsrfPost = curlRequest("{$base}/inquiry", [
        'post' => ['_csrf' => 'invalid_token_12345678901234567890', 'name' => 'Bad CSRF', 'email' => 'badcsrf@example.test', 'message' => 'Testing bad CSRF'],
    ]);
    check('3.2 Public POST with bad _csrf returns 403', $badCsrfPost['code'] === 403, "Code: {$badCsrfPost['code']}");

    $adminNoCsrf = curlRequest("{$base}/admin/?r=settings-update", [
        'cookie_jar' => $superJar,
        'post' => ['site_title' => 'Hacked Title'],
    ]);
    check('3.3 Admin POST without _csrf returns 403', $adminNoCsrf['code'] === 403, "Code: {$adminNoCsrf['code']}");

    $adminBadCsrf = curlRequest("{$base}/admin/?r=settings-update", [
        'cookie_jar' => $superJar,
        'post' => ['_csrf' => 'bad_admin_token', 'site_title' => 'Hacked Title'],
    ]);
    check('3.4 Admin POST with bad _csrf returns 403', $adminBadCsrf['code'] === 403, "Code: {$adminBadCsrf['code']}");

    $getInquiry = curlRequest("{$base}/inquiry");
    check('3.5 GET /inquiry returns 405', $getInquiry['code'] === 405, "Code: {$getInquiry['code']}");
    check('3.6 GET /inquiry includes Allow: POST', str_contains($getInquiry['headers'], 'Allow: POST'));

    $getConsult = curlRequest("{$base}/consultation");
    check('3.7 GET /consultation returns 405', $getConsult['code'] === 405);

    $getRecruit = curlRequest("{$base}/recruitment");
    check('3.8 GET /recruitment returns 405', $getRecruit['code'] === 405);

    $postHome = curlRequest("{$base}/", ['method' => 'POST']);
    check('3.9 POST / returns 405', $postHome['code'] === 405);

    $getLogout = curlRequest("{$base}/admin/?r=logout", ['cookie_jar' => $superJar]);
    check('3.10 GET /admin/?r=logout returns 405', $getLogout['code'] === 405);

    $postDash = curlRequest("{$base}/admin/?r=dashboard", ['cookie_jar' => $superJar, 'post' => ['x' => 1]]);
    check('3.11 POST /admin/?r=dashboard returns 405', $postDash['code'] === 405);

    // ---------------------------------------------------------
    // SECTION 4: RATE LIMITING & ABUSE (12 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 4: Rate Limiting & Abuse ---\n";
    \AxaKenneth\Support\RateLimiter::clear('inquiry.submit', '127.0.0.1');
    \AxaKenneth\Support\RateLimiter::clear('inquiry.submit', '::1');

    $inquiryJar = $tempDir . '/inquiry.cookies';
    $publicPage = curlRequest("{$base}/?src=batch3-harness-campaign", ['cookie_jar' => $inquiryJar]);
    $pubCsrf = extractCsrf($publicPage['body']);
    check('4.1 Public page renders with CSRF token', $pubCsrf !== '');

    $inquiryRepo = new InquiryRepository();
    $initialInquiryCount = $inquiryRepo->count('', '', '');

    // Honeypot submission
    $hpRes = curlRequest("{$base}/inquiry", [
        'cookie_jar' => $inquiryJar,
        'post' => [
            '_csrf' => $pubCsrf,
            'name' => 'Bot User',
            'email' => 'bot@example.test',
            'company_website' => 'http://spam-link.test',
            'message' => 'Spam message content here for honeypot test',
            'consent' => '1',
        ],
    ]);
    check('4.2 Honeypot submission returns 303', $hpRes['code'] === 303);
    $afterHpCount = $inquiryRepo->count('', '', '');
    check('4.3 Honeypot creates 0 database records', $afterHpCount === $initialInquiryCount);

    // Missing consent
    $noConsentRes = curlRequest("{$base}/inquiry", [
        'cookie_jar' => $inquiryJar,
        'post' => [
            '_csrf' => $pubCsrf,
            'name' => 'Valid User',
            'email' => 'user@example.test',
            'message' => 'Legitimate inquiry message testing consent',
        ],
    ]);
    check('4.4 Missing consent returns 422', $noConsentRes['code'] === 422, "Code: {$noConsentRes['code']}");
    check('4.5 Missing consent creates 0 database records', $inquiryRepo->count('', '', '') === $initialInquiryCount);

    // Valid submission
    $validInquiryRes = curlRequest("{$base}/inquiry", [
        'cookie_jar' => $inquiryJar,
        'post' => [
            '_csrf' => $pubCsrf,
            'name' => 'Auditor Kenneth User',
            'email' => 'audit-lead@example.test',
            'phone' => '+639123456789',
            'message' => 'Hello Kenneth, this is an automated audit test message with at least ten characters.',
            'consent' => '1',
        ],
    ]);
    check('4.6 Valid inquiry returns 303', $validInquiryRes['code'] === 303);
    check('4.7 Valid inquiry persists to database', $inquiryRepo->count('', '', '') === $initialInquiryCount + 1);

    // Inspect stored row
    $latestInquiries = $inquiryRepo->recent(1);
    $latestSummary = $latestInquiries[0] ?? [];
    $latest = isset($latestSummary['id']) ? $inquiryRepo->find((int) $latestSummary['id']) : [];
    check('4.8 Stored inquiry has correct name', ($latest['name'] ?? '') === 'Auditor Kenneth User');
    check('4.9 Stored inquiry captures campaign source', ($latest['source'] ?? '') === 'batch3-harness-campaign');
    check('4.10 Stored inquiry has valid privacy version', ($latest['privacy_version'] ?? '') !== '');
    check('4.11 Stored inquiry has 64-char HMAC-SHA-256 IP hash', strlen((string) ($latest['ip_hash'] ?? '')) === 64);
    check('4.12 Stored inquiry has status = new', ($latest['status'] ?? '') === 'new');

    // Clean up the created test inquiry
    if (isset($latest['id'])) {
        Database::pdo()->prepare('DELETE FROM inquiries WHERE id = :id')->execute([':id' => $latest['id']]);
    }

    // ---------------------------------------------------------
    // SECTION 5: INJECTION PROBES (5 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 5: Injection Probes ---\n";
    $xssPayload = '<script>alert("xss")</script><img src=x onerror=alert(1)>"\'';
    $xssInquiryId = $inquiryRepo->create([
        'inquiry_type' => 'general',
        'name' => 'XSS Tester ' . $xssPayload,
        'email' => 'xss@example.test',
        'phone' => '+639000000000',
        'preferred_contact' => 'email',
        'topic' => 'XSS ' . $xssPayload,
        'preferred_time' => '',
        'location' => '',
        'experience' => '',
        'message' => 'Message with XSS: ' . $xssPayload,
        'privacy_version' => '2026-10-22',
        'source' => 'direct',
        'ip_hash' => hash('sha256', '127.0.0.1'),
        'user_agent' => 'AuditAgent',
    ]);

    $inquiryList = curlRequest("{$base}/admin/?r=inquiries", ['cookie_jar' => $superJar]);
    check('5.1 Inquiry list does not execute unencoded XSS script tags', !str_contains($inquiryList['body'], '<script>alert("xss")</script>'));
    check('5.2 Inquiry list properly encodes HTML entities', str_contains($inquiryList['body'], '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'));

    $inquiryDetail = curlRequest("{$base}/admin/?r=inquiry-view&id={$xssInquiryId}", ['cookie_jar' => $superJar]);
    check('5.3 Inquiry detail encodes XSS payload', str_contains($inquiryDetail['body'], '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'));
    check('5.4 Inquiry detail does not contain raw img onerror', !str_contains($inquiryDetail['body'], '<img src=x onerror=alert(1)>'));

    // SQL Injection probe in search
    $sqliSearch = curlRequest("{$base}/admin/?r=inquiries&q=" . urlencode("' OR '1'='1 UNION SELECT 1,2,3,4,5,6,7,8,9,10-- -"), ['cookie_jar' => $superJar]);
    check('5.5 SQL injection probe executes safely without SQL errors or leakage', $sqliSearch['code'] === 200 && !str_contains($sqliSearch['body'], 'SQLSTATE') && !str_contains($sqliSearch['body'], 'Fatal error'));

    // Clean up XSS test inquiry
    Database::pdo()->prepare('DELETE FROM inquiries WHERE id = :id')->execute([':id' => $xssInquiryId]);

    // ---------------------------------------------------------
    // SECTION 6: PRIVATE PATHS (16 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 6: Private Paths ---\n";
    $paths = [
        '/.env' => [403, 'Direct .env file blocked'],
        '/.env.example' => [403, 'Direct .env.example file blocked'],
        '/database/migrations/001_initial_schema.sql' => [403, 'SQL migration blocked'],
        '/storage/logs/app-error.log' => [403, 'Log file blocked'],
        '/.git/config' => [403, '.git directory blocked'],
        '/.git/HEAD' => [403, '.git/HEAD blocked'],
        '/app/bootstrap.php' => [403, 404, 'Direct app/bootstrap.php blocked'],
        '/app/helpers.php' => [403, 404, 'Direct app/helpers.php blocked'],
        '/media/../.env' => [403, 404, 'Path traversal on media blocked'],
        '/assets/../../.env' => [403, 404, 'Path traversal on assets blocked'],
        '/..%2f.env' => [400, 403, 404, 'URL encoded traversal blocked'],
        '/%2e%2e/.env' => [400, 403, 404, 'Double encoded traversal blocked'],
        '/scripts/migrate.php' => [403, 404, 'Direct script execution blocked'],
        '/config/' => [403, 404, 'Config directory blocked'],
        '/storage/' => [403, 404, 'Storage directory blocked'],
        '/database/' => [403, 404, 'Database directory blocked'],
    ];

    $pathIdx = 1;
    foreach ($paths as $path => $expected) {
        $res = curlRequest("{$base}{$path}");
        $allowedCodes = is_array($expected) ? array_slice($expected, 0, count($expected) - 1) : [$expected];
        $label = is_array($expected) ? end($expected) : "Access {$path}";
        check("6.{$pathIdx} {$label}", in_array($res['code'], $allowedCodes, true) && !str_contains($res['body'], 'APP_KEY') && !str_contains($res['body'], 'DB_PASSWORD'), "Code: {$res['code']}");
        $pathIdx++;
    }

    // ---------------------------------------------------------
    // SECTION 7: MEDIA LIFECYCLE (11 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 7: Media Lifecycle ---\n";
    $mediaRepo = new MediaRepository();
    $mediaCountBefore = $mediaRepo->count();

    // Create a 1x1 valid PNG in memory
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $pngTemp = $tempDir . '/test-upload.png';
    file_put_contents($pngTemp, $pngBytes);

    $mediaPage = curlRequest("{$base}/admin/?r=media", ['cookie_jar' => $superJar]);
    $mediaCsrf = extractCsrf($mediaPage['body']);
    check('7.1 Media page provides CSRF token', $mediaCsrf !== '');

    // Upload via CURL
    $ch = curl_init("{$base}/admin/?r=media-upload");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $superJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $superJar);
    curl_setopt($ch, CURLOPT_POST, true);
    $cFile = new CURLFile($pngTemp, 'image/png', 'audit-test.png');
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['_csrf' => $mediaCsrf, 'image' => $cFile, 'alt_text' => 'Audit PNG']);
    $uploadRaw = curl_exec($ch);
    $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    check('7.2 Media upload returns 303', $uploadCode === 303, "Code: {$uploadCode}");
    check('7.3 Media record created in database', $mediaRepo->count() === $mediaCountBefore + 1);

    $mediaItems = $mediaRepo->all(1);
    $uploadedMedia = $mediaItems[0] ?? [];
    $uploadedId = (int) ($uploadedMedia['id'] ?? 0);
    check('7.4 Uploaded media record has ID', $uploadedId > 0);

    // Serve media
    $serveRes = curlRequest("{$base}/media/{$uploadedId}");
    check('7.5 Served media returns 200', $serveRes['code'] === 200, "Code: {$serveRes['code']}");
    check('7.6 Content-Type is image/png', str_contains($serveRes['headers'], 'Content-Type: image/png'));
    check('7.7 X-Content-Type-Options: nosniff on media', str_contains($serveRes['headers'], 'X-Content-Type-Options: nosniff'));
    check('7.8 ETag header present on media', str_contains($serveRes['headers'], 'ETag:'));

    // Conditional request
    $etag = '';
    if (preg_match('/ETag:\s*([^\r\n]+)/', $serveRes['headers'], $em)) {
        $etag = trim($em[1]);
    }
    $condRes = curlRequest("{$base}/media/{$uploadedId}", [
        'headers' => ["If-None-Match: {$etag}"],
    ]);
    check('7.9 If-None-Match returns 304 Not Modified', $condRes['code'] === 304, "Code: {$condRes['code']}");

    // Delete media
    $delRes = curlRequest("{$base}/admin/?r=media-delete&id={$uploadedId}", [
        'cookie_jar' => $superJar,
        'post' => ['_csrf' => $mediaCsrf],
    ]);
    check('7.10 Media delete returns 303', $delRes['code'] === 303);
    check('7.11 Media record removed from database', $mediaRepo->count() === $mediaCountBefore);

    // ---------------------------------------------------------
    // SECTION 8: ACCOUNT LIFECYCLE (8 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 8: Account Lifecycle ---\n";
    $adminCreatePage = curlRequest("{$base}/admin/?r=admin-create", ['cookie_jar' => $superJar]);
    $superAuthCsrf = extractCsrf($adminCreatePage['body']);

    // Duplicate email rejected as 422
    $dupRes = curlRequest("{$base}/admin/?r=admin-store", [
        'cookie_jar' => $superJar,
        'post' => [
            '_csrf' => $superAuthCsrf,
            'name' => 'Duplicate Admin',
            'email' => $regEmail,
            'role' => 'admin',
            'is_active' => '1',
            'password' => 'ValidPassword123!',
            'password_confirm' => 'ValidPassword123!',
        ],
    ]);
    check('8.1 Duplicate admin email rejected as 422', $dupRes['code'] === 422, "Code: {$dupRes['code']}");

    // Refresh superAuthCsrf after post
    $adminEditPage = curlRequest("{$base}/admin/?r=admin-password&id={$regId}", ['cookie_jar' => $superJar]);
    $superAuthCsrf = extractCsrf($adminEditPage['body']);

    // Password reset for regular admin
    $adminPassRes = curlRequest("{$base}/admin/?r=admin-password-update&id={$regId}", [
        'cookie_jar' => $superJar,
        'post' => [
            '_csrf' => $superAuthCsrf,
            'password' => 'NewPassword12345!',
            'password_confirm' => 'NewPassword12345!',
        ],
    ]);
    check('8.2 Admin password reset redirects 303', $adminPassRes['code'] === 303, "Code: {$adminPassRes['code']}");

    // Prior session for regular admin is invalidated
    $oldSessionRes = curlRequest("{$base}/admin/?r=dashboard", ['cookie_jar' => $regJar]);
    check('8.3 Previous session of reset admin redirected to login (303)', $oldSessionRes['code'] === 303, "Code: {$oldSessionRes['code']}");

    // Regular admin logs in with new password
    $newRegJar = $tempDir . '/new_reg.cookies';
    $loginPage2 = curlRequest("{$base}/admin/?r=login", ['cookie_jar' => $newRegJar]);
    $newCsrf = extractCsrf($loginPage2['body']);
    $newLoginRes = curlRequest("{$base}/admin/?r=login", [
        'cookie_jar' => $newRegJar,
        'post' => ['_csrf' => $newCsrf, 'email' => $regEmail, 'password' => 'NewPassword12345!'],
    ]);
    check('8.4 Regular admin signs in with new password (303)', $newLoginRes['code'] === 303, "Code: {$newLoginRes['code']}");

    // Self-demotion rejected
    $selfEditPage = curlRequest("{$base}/admin/?r=admin-edit&id={$superId}", ['cookie_jar' => $superJar]);
    $superAuthCsrf = extractCsrf($selfEditPage['body']);

    $selfDemote = curlRequest("{$base}/admin/?r=admin-update&id={$superId}", [
        'cookie_jar' => $superJar,
        'post' => [
            '_csrf' => $superAuthCsrf,
            'name' => 'Super Auditor',
            'email' => $superEmail,
            'role' => 'admin',
            'is_active' => '1',
        ],
    ]);
    check('8.5 Super admin self-demotion rejected (422)', $selfDemote['code'] === 422, "Code: {$selfDemote['code']}");

    // Self-deactivation rejected
    $selfEditPage2 = curlRequest("{$base}/admin/?r=admin-edit&id={$superId}", ['cookie_jar' => $superJar]);
    $superAuthCsrf = extractCsrf($selfEditPage2['body']);

    $selfDeact = curlRequest("{$base}/admin/?r=admin-update&id={$superId}", [
        'cookie_jar' => $superJar,
        'post' => [
            '_csrf' => $superAuthCsrf,
            'name' => 'Super Auditor',
            'email' => $superEmail,
            'role' => 'super_admin',
            'is_active' => '0',
        ],
    ]);
    check('8.6 Super admin self-deactivation rejected (422)', $selfDeact['code'] === 422, "Code: {$selfDeact['code']}");

    // Logout
    $logoutPage = curlRequest("{$base}/admin/?r=dashboard", ['cookie_jar' => $superJar]);
    $superAuthCsrf = extractCsrf($logoutPage['body']);

    $logoutRes = curlRequest("{$base}/admin/?r=logout", [
        'cookie_jar' => $superJar,
        'post' => ['_csrf' => $superAuthCsrf],
    ]);
    check('8.7 Logout redirects 303', $logoutRes['code'] === 303, "Code: {$logoutRes['code']}");

    $postLogoutDash = curlRequest("{$base}/admin/?r=dashboard", ['cookie_jar' => $superJar]);
    check('8.8 Post-logout dashboard request redirects to login (303)', $postLogoutDash['code'] === 303, "Code: {$postLogoutDash['code']}");

    // ---------------------------------------------------------
    // SECTION 9: VCARD, SEO & PRIVACY (8 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 9: vCard, SEO & Privacy ---\n";
    $vcardRes = curlRequest("{$base}/contact.vcf");
    check('9.1 GET /contact.vcf returns 200', $vcardRes['code'] === 200);
    check('9.2 vCard Content-Type is text/vcard', str_contains($vcardRes['headers'], 'Content-Type: text/vcard'));
    check('9.3 Unverified TITLE is omitted from vCard', !str_contains($vcardRes['body'], 'TITLE:Rookie Unit Head'));
    check('9.4 Unverified ORG is omitted from vCard', !str_contains($vcardRes['body'], 'ORG:AXA Philippines'));

    $privacyRes = curlRequest("{$base}/privacy");
    check('9.5 GET /privacy returns 200', $privacyRes['code'] === 200);
    check('9.6 Privacy notice displays notice version 2026-10-22', str_contains($privacyRes['body'], '2026-10-22'));

    $sitemapRes = curlRequest("{$base}/sitemap.xml");
    check('9.7 GET /sitemap.xml returns 200 XML', $sitemapRes['code'] === 200 && str_contains($sitemapRes['headers'], 'application/xml'));

    $robotsRes = curlRequest("{$base}/robots.txt");
    check('9.8 GET /robots.txt returns 200 text', $robotsRes['code'] === 200 && str_contains($robotsRes['body'], 'Disallow: ' . \AxaKenneth\Support\Http::basePath() . '/admin/'));

    // ---------------------------------------------------------
    // SECTION 10: ERROR HANDLING (4 checks)
    // ---------------------------------------------------------
    echo "\n--- SECTION 10: Error Handling ---\n";
    $errRes = curlRequest("{$base}/nonexistent-page-path-12345");
    check('10.1 Nonexistent route returns 404', $errRes['code'] === 404);
    check('10.2 404 response does not leak stack trace', !str_contains($errRes['body'], 'Stack trace') && !str_contains($errRes['body'], '#0 {main}'));
    check('10.3 404 response does not leak filesystem path', !str_contains($errRes['body'], 'D:\\laragon') && !str_contains($errRes['body'], 'D:/laragon'));
    check('10.4 404 response does not leak database details', !str_contains($errRes['body'], 'PDOException') && !str_contains($errRes['body'], 'axa_kenneth_db'));

} finally {
    // ---------------------------------------------------------
    // CLEANUP TEST DATA (Strict isolation guarantee)
    // ---------------------------------------------------------
    $pdo = Database::pdo();
    \AxaKenneth\Support\RateLimiter::clear('inquiry.submit', '127.0.0.1');
    \AxaKenneth\Support\RateLimiter::clear('inquiry.submit', '::1');
    $pdo->prepare('DELETE FROM admins WHERE id IN (:id1, :id2)')->execute([':id1' => $superId, ':id2' => $regId]);
    $pdo->prepare('DELETE FROM audit_logs WHERE admin_id IN (:id1, :id2)')->execute([':id1' => $superId, ':id2' => $regId]);

    // Clean up temporary files
    $files = glob("{$tempDir}/*");
    if (is_array($files)) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }
    @rmdir($tempDir);
}

echo "\n======================================================================\n";
echo "SUMMARY: {$passedChecks} / {$totalChecks} checks passed.\n";
if ($failedChecks !== []) {
    echo "Failures:\n";
    foreach ($failedChecks as $f) {
        echo "  - {$f}\n";
    }
    echo "======================================================================\n";
    exit(1);
} else {
    echo "ALL 119/119 CHECKS PASSED PERFECTLY!\n";
    echo "======================================================================\n";
    exit(0);
}
