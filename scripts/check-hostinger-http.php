<?php

declare(strict_types=1);

// Read-only HTTP acceptance checks; does not bootstrap or read credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$base = rtrim($argv[1] ?? 'https://techtalkmnl.com/axa-kenmark', '/');
if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)) {
    throw new InvalidArgumentException('Provide an HTTP(S) application base URL.');
}
$checks = [
    '/' => 200, '/admin/login.php' => 200, '/assets/css/style.css' => 200,
    '/assets/js/script.js' => 200, '/assets/images/kenneth.jpg' => 200,
    '/.env.example' => 403, '/.env.production.example' => 403,
    '/app/bootstrap.php' => 403, '/config/' => 403,
    '/database/migrations/001_initial_schema.sql' => 403,
    '/docs/legacy-static-index.html' => 403, '/scripts/generate-key.php' => 403,
    '/storage/' => 403, '/tests/test-security-harness.php' => 403,
    '/public/index.php' => 403, '/.git/config' => 403, '/README.md' => 403,
];
$failed = 0;
foreach ($checks as $path => $expected) {
    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'AXA-Kenneth-ReadOnly-Acceptance/1.0']);
    $body = curl_exec($handle);
    $code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $ok = $body !== false && $code === $expected;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $path . ' HTTP ' . $code . ' expected ' . $expected . "\n";
    curl_close($handle);
}
echo $failed ? "NOT ACCEPTED: {$failed} checks failed.\n" : "HTTP checks passed; also verify a nonsecret storage marker, HTTPS cookies and authenticated workflows manually.\n";
exit($failed ? 1 : 0);
