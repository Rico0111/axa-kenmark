$ErrorActionPreference = 'Stop'
# Disposable Windows verification; never reads the project's .env or local database.
$project = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$fixture = Join-Path $env:TEMP ('axa-deploy-check-' + [guid]::NewGuid().ToString('N'))
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$mysqlBin = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin'
$apache = 'D:/laragon/bin/apache/httpd-2.4.66-260223-Win64-VS18'
foreach ($port in @(3309, 8089)) {
    if (Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue) {
        throw "Disposable test port $port is already in use"
    }
}
New-Item -ItemType Directory $fixture | Out-Null
$source = Join-Path $fixture 'site'
Expand-Archive -LiteralPath (Join-Path $project 'dist/hostinger/axa-kenmark-localhost-copy.zip') -DestinationPath $source
New-Item -ItemType Directory (Join-Path $source 'dist/hostinger') -Force | Out-Null
Copy-Item -Path (Join-Path $project 'dist/hostinger/*') -Destination (Join-Path $source 'dist/hostinger') -Force
$data = Join-Path $fixture 'mysql'
$init = Start-Process "$mysqlBin\mysqld.exe" -ArgumentList @('--no-defaults', '--initialize-insecure', "--datadir=$data", '--console') -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput (Join-Path $fixture 'mysql-init-out.log') -RedirectStandardError (Join-Path $fixture 'mysql-init.log')
if ($init.ExitCode) { throw 'Disposable MySQL initialization failed' }
$mysqlProcess = $null
$httpProcess = $null
try {
    $mysqlProcess = Start-Process "$mysqlBin\mysqld.exe" -ArgumentList @('--no-defaults', "--datadir=$data", '--port=3309', '--bind-address=127.0.0.1', '--mysqlx=OFF', '--console') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $fixture 'mysql-out.log') -RedirectStandardError (Join-Path $fixture 'mysql-error.log')
    $ready = $false
    $ErrorActionPreference = 'Continue' # Native tools write status messages to stderr.
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        & "$mysqlBin\mysqladmin.exe" --no-defaults -h 127.0.0.1 -P 3309 -u root ping *> $null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (!$ready) { throw 'Disposable MySQL did not start' }
    $ErrorActionPreference = 'Stop'
    & "$mysqlBin\mysql.exe" --no-defaults -h 127.0.0.1 -P 3309 -u root -e "CREATE DATABASE axa_ci; CREATE USER 'axa_ci'@'127.0.0.1' IDENTIFIED BY 'disposable-ci-only'; GRANT ALL ON axa_ci.* TO 'axa_ci'@'127.0.0.1';"
    if ($LASTEXITCODE) { throw 'Disposable database setup failed' }
    $key = [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')
    @"
APP_ENV=testing
APP_DEBUG=false
APP_KEY=$key
APP_URL=http://127.0.0.1:8089
DB_HOST=127.0.0.1
DB_PORT=3309
DB_NAME=axa_ci
DB_USER=axa_ci
DB_PASSWORD=disposable-ci-only
DB_MIGRATION_USER=axa_ci
DB_MIGRATION_PASSWORD=disposable-ci-only
TRUST_PROXY_HEADERS=false
"@ | Set-Content -LiteralPath (Join-Path $source '.env') -Encoding Ascii
    $webPath = $source.Replace('\', '/')
    $confPath = (Join-Path $fixture 'httpd.conf').Replace('\', '/')
    $logPath = (Join-Path $fixture 'apache-error.log').Replace('\', '/')
    @"
ServerRoot "$apache"
Listen 127.0.0.1:8089
ServerName 127.0.0.1
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
Include "D:/laragon/etc/apache2/mod_php.conf"
TypesConfig conf/mime.types
ErrorLog "$logPath"
DocumentRoot "$webPath"
<Directory "$webPath">
AllowOverride All
Require all granted
</Directory>
"@ | Set-Content -LiteralPath $confPath -Encoding Ascii
    $configCheck = Start-Process "$apache/bin/httpd.exe" -ArgumentList @('-f', $confPath, '-t') -WindowStyle Hidden -Wait -PassThru -RedirectStandardError (Join-Path $fixture 'apache-config.log')
    if ($configCheck.ExitCode) { throw 'Disposable Apache config failed' }
    $httpProcess = Start-Process "$apache/bin/httpd.exe" -ArgumentList @('-f', $confPath) -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 2
    Push-Location $source
    try {
        $lintCount = 0
        Get-ChildItem -Recurse -Filter '*.php' | ForEach-Object {
            & $php -l $_.FullName | Out-Null
            if ($LASTEXITCODE) { throw "Lint failed: $($_.FullName)" }
            $lintCount++
        }
        Write-Output "PHP lint: $lintCount files passed"
        foreach ($script in @('scripts/migrate.php', 'scripts/seed.php', 'tests/test-cli-password.php', 'tests/test-security-harness.php', 'tests/test-production-simulation.php')) {
            & $php -d extension=zip $script *> (Join-Path $fixture ([IO.Path]::GetFileName($script) + '.log'))
            if ($LASTEXITCODE) { throw "Failed: $script; see private fixture logs" }
            Write-Output "PASS $script"
        }
        & $php scripts/check-hostinger-http.php http://127.0.0.1:8089 *> (Join-Path $fixture 'http-acceptance.log')
        if ($LASTEXITCODE) { throw 'HTTP acceptance failed; see private fixture logs' }
        Write-Output 'PASS HTTP acceptance'
    } finally { Pop-Location }
} finally {
    $ErrorActionPreference = 'Continue'
    if ($httpProcess) {
        # Terminate only children of this disposable server, then its parent.
        Get-CimInstance Win32_Process -Filter "ParentProcessId = $($httpProcess.Id)" | Where-Object { $_.Name -eq 'httpd.exe' } | ForEach-Object { Stop-Process -Id $_.ProcessId -ErrorAction SilentlyContinue }
        Stop-Process -Id $httpProcess.Id -ErrorAction SilentlyContinue
    }
    if ($mysqlProcess) { & "$mysqlBin\mysqladmin.exe" --no-defaults -h 127.0.0.1 -P 3309 -u root shutdown *> $null }
    Write-Output "Private verification records: $fixture"
}
