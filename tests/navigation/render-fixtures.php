<?php
declare(strict_types=1);

// CLI-only presentation fixtures. Never boot the application, load its .env,
// authenticate, start sessions, instantiate a repository or connect to a DB.
if (PHP_SAPI !== 'cli' || $argc !== 2) {
    exit(1);
}
$root = dirname(__DIR__, 2);
$output = $argv[1];
if (!is_dir($output) && !mkdir($output, 0700, true)) {
    throw new RuntimeException('Cannot create fixture output');
}
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'AxaKenneth\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
require $root . '/app/helpers.php';
$fixtureEnv = $output . '/fixture.env';
file_put_contents($fixtureEnv, "APP_ENV=testing\nAPP_KEY=navigation-fixture-only-no-real-credentials\nAPP_URL=http://127.0.0.1:8837/axa-kenmark\n");
\AxaKenneth\Support\Env::load($fixtureEnv);
\AxaKenneth\Support\Config::load($root);
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/axa-kenmark/';

$publicData = [
    'profile' => ['display_name' => 'Kenneth', 'tagline' => 'Protection · Purpose · People',
        'bio' => 'A personal connection for protection planning, financial goals, and informed decisions.'],
    'categories' => [
        ['name' => 'Life protection', 'icon' => '01', 'summary' => 'Plan for the people who matter.'],
        ['name' => 'Health protection', 'icon' => '02', 'summary' => 'Prepare for life’s unexpected moments.'],
    ],
    'products' => [], 'settings' => [], 'structuredData' => [],
    'privacyVersion' => '2026-10-22', 'formType' => '', 'formOld' => [], 'formErrors' => [],
];
ob_start();
\AxaKenneth\Support\View::render('public/home', $publicData);
file_put_contents($output . '/public.html', ob_get_clean());
ob_start();
\AxaKenneth\Support\View::render('public/privacy', ['privacyVersion' => '2026-10-22', 'effectiveDate' => '22 October 2026']);
file_put_contents($output . '/privacy.html', ob_get_clean());

$dashboardData = [
    'pageTitle' => 'Dashboard', 'activeNav' => 'dashboard', 'newInquiryCount' => 7,
    'totalInquiryCount' => 12, 'inquiryStatusCounts' => ['closed' => 5],
    'recentInquiries' => [], 'productCounts' => ['published' => 5, 'draft' => 2, 'archived' => 0],
    'unverifiedCount' => 0, 'recentProducts' => [], 'categoryCount' => 4, 'mediaCount' => 6,
    'advisor' => null, 'titleVerified' => false, 'organizationVerified' => false,
];
foreach (['super_admin', 'admin'] as $role) {
    foreach (['dashboard', 'inquiries', 'advisor', 'categories', 'products', 'media', 'settings', 'admins'] as $active) {
        ob_start();
        \AxaKenneth\Support\View::render('admin/dashboard', array_merge($dashboardData, [
            'activeNav' => $active,
            'admin' => ['id' => 0, 'name' => 'Navigation review', 'role' => $role],
        ]), 'layouts/admin');
        file_put_contents($output . '/' . $role . '-' . $active . '.html', ob_get_clean());
    }
}
echo "Rendered database-free public, privacy and administrator-role layout fixtures.\n";
