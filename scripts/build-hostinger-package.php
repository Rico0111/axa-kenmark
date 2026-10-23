<?php

declare(strict_types=1);

// Source-only build: never bootstrap the app, read .env, or connect to MySQL.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('Enable PHP ZipArchive; dotfiles must be included.');
}
$root = dirname(__DIR__);
$output = $root . '/dist/hostinger';
if (!is_dir($output) && !mkdir($output, 0700, true)) {
    throw new RuntimeException('Cannot create output directory.');
}
$path = $output . '/axa-kenmark-localhost-copy.zip';
$zip = new ZipArchive();
if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create deployment ZIP.');
}
$manifest = [];
$excluded = 0;
$add = static function (string $relative) use ($root, $zip, &$manifest): void {
    $full = $root . '/' . $relative;
    if (is_link($full) || !$zip->addFile($full, $relative)) {
        throw new RuntimeException('Cannot package: ' . $relative);
    }
    $manifest[$relative] = hash_file('sha256', $full);
};
foreach (['app', 'config', 'database', 'docs', 'public', 'scripts', 'storage', 'tests'] as $directory) {
    $zip->addEmptyDir($directory);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $root . '/' . $directory, FilesystemIterator::SKIP_DOTS
    ));
    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $storageRuntime = $directory === 'storage' && $file->getFilename() !== '.htaccess';
        $unsafe = preg_match('~(^|/)(dumps?|backups?|uploads|private|node_modules|vendor|\.git)(/|$)|(^|/)\.env(\.|$)|\.(log|bak|backup|dump|zip|gz|7z|tar|rar|tmp|swp|pem|key|p12|pfx|cookie|cookies|session)$|\x7e$~i', $relative);
        $sqlDump = preg_match('/\.sql$/i', $relative) && !str_starts_with($relative, 'database/migrations/');
        if ($file->isLink() || $storageRuntime || $unsafe || $sqlDump) {
            $excluded++;
            continue;
        }
        if ($file->isFile()) {
            $add($relative);
        }
    }
}
foreach (['logs', 'sessions', 'cache', 'uploads/media'] as $directory) {
    $zip->addEmptyDir('storage/' . $directory);
}
foreach (['.htaccess', 'index.php', '.env.example', '.env.production.example', 'README.md', 'README-HOSTINGER.md'] as $file) {
    $add($file);
}
ksort($manifest);
if (!$zip->close()) {
    throw new RuntimeException('Cannot finalize deployment ZIP.');
}
file_put_contents($output . '/localhost-copy-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($output . '/localhost-copy-exclusions.txt', "Excluded entirely: .git/, .agent-handoff/, .env, backups/, dist/, local dumps, runtime storage and uploads.\nExcluded runtime/unsafe files: {$excluded} (names omitted to avoid leaking session IDs).\n");
file_put_contents($output . '/localhost-copy-SHA256.txt', hash_file('sha256', $path) . '  ' . basename($path) . "\n");
echo "Created {$path}\nSource files: " . count($manifest) . "\n";
