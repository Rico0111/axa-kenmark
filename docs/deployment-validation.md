# Deployment preparation: commands and results

Date: 2026-10-23, Asia/Manila. Workspace: `D:\laragon\www\axa-kenmark`.
Commands below are the audit and verification record, not deployment authorization.
Read-only inspection also used Get-Content, Get-ChildItem, rg and git ls-tree to
read the handoff, instructions, tests, configuration and release builder. No
secret values were requested or printed. No remote write commands were run.

## Repository and GitHub audit

```powershell
Get-Location
rg --files --hidden -g '!.git' -g '!vendor' -g '!node_modules' -g '*.md'
Get-ChildItem -Path D:\AGENTS.md,D:\laragon\AGENTS.md,D:\laragon\www\AGENTS.md,D:\laragon\www\axa-kenmark\AGENTS.md -ErrorAction SilentlyContinue
Get-Content .agent-handoff/CURRENT.md
Get-Content README-HOSTINGER.md
Get-Content docs/testing.md
Get-Content docs/rollback.md
Get-Content scripts/build-hostinger-package.php
Get-Content scripts/check-hostinger-http.php
git status --short
git branch -avv
git remote -v
gh auth status
gh api repos/rolex-esto/axa-kenmark/branches/main --jq '.commit.sha'
gh api 'repos/rolex-esto/axa-kenmark/commits?sha=main&per_page=100' --jq '.[].sha'
git rev-list --count origin/main
git show -s --format='%H %P' origin/main
git ls-tree -r --name-only origin/main
git show origin/main:docs/github-publication.md
git diff --stat origin/main HEAD
git switch publication-clean
gh secret list --repo rolex-esto/axa-kenmark
gh api repos/rolex-esto/axa-kenmark/environments --jq '.environments[].name'
```

Results: no applicable AGENTS.md found; handoff read before branch switch.
Authenticated GitHub account: rolex-esto, HTTPS. Repository origin matches the
requested URL. Main's complete API commit list and local remote-tracking history
both contain only `d6542570f9bdc4d6300fee8326c8b1ca4a871fba`, a root commit.
All five expected secret names are present. No environments were configured.
The current clean branch is publication-clean; local unsafe main is untouched.

## Tools and syntax validation

Official action tag SHAs were resolved with `gh api repos/<owner>/<repo>/git/ref/tags/<tag>`
for actions/checkout v4, actions/upload-artifact v4 and actions/setup-python v5.
These SHAs are pinned in the workflow. Windows actionlint v1.7.12 and Gitleaks
v8.30.1 were downloaded from their official GitHub release assets into
`$env:TEMP\axa-deployment-tools` using gh api, Invoke-WebRequest and Expand-Archive:

```powershell
$toolDir = Join-Path $env:TEMP 'axa-deployment-tools'
New-Item -ItemType Directory -Force $toolDir | Out-Null
foreach ($repo in @('rhysd/actionlint','gitleaks/gitleaks')) {
    $release = gh api "repos/$repo/releases/latest" | ConvertFrom-Json
    $asset = $release.assets | Where-Object { $_.name -match 'windows_(amd64|x64)\.zip$' } | Select-Object -First 1
    $zip = Join-Path $toolDir $asset.name
    Invoke-WebRequest -UseBasicParsing $asset.browser_download_url -OutFile $zip
    Expand-Archive -LiteralPath $zip -DestinationPath $toolDir -Force
}
& "$toolDir\actionlint.exe" .github/workflows/deploy-hostinger.yml
git diff --check
py --version
py -m pip --version
py -m pip index versions paramiko
py -m pip install -r .github/deployment/requirements.txt
py -m unittest discover -s .github/deployment -p 'test_*.py'
```

Results: actionlint passes, no workflow syntax errors. Git whitespace check passes
(local Git may warn about LF/CRLF conversion). Python 3.14.5; final tested Paramiko
5.1.0, pinned in requirements.txt. Seven safeguard tests pass. Windows shell
syntax was exercised by local-verify.ps1. Ubuntu CI and Bash execution remain
pending an actual validate dispatch on GitHub; local checks do not prove a
GitHub runner execution. No workflow was dispatched during this task.

## Application and release verification

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php -d extension=zip scripts/build-hostinger-package.php
py .github/deployment/sftp_release.py audit
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .github/deployment/local-verify.ps1
```

The reusable verification script contains all underlying commands. It extracts
the credential-free release into a fresh private TEMP directory, initializes a
new MySQL 8.4.3 instance on port 3309, creates axa_ci and a disposable test user,
writes a new testing .env, and runs a separate Apache 2.4.66/PHP 8.3.30 instance
on port 8089. It does not reuse the local application .env or database. It checks
that both ports are free first and shuts down only its own server processes.

Within that disposable source copy it runs PHP lint, scripts/migrate.php,
scripts/seed.php, tests/test-cli-password.php, tests/test-security-harness.php,
tests/test-production-simulation.php and scripts/check-hostinger-http.php
against `http://127.0.0.1:8089`. All PHP invocations use the Laragon PHP binary;
the CLI enables ZipArchive per-process. Per-suite output is retained privately
under the printed TEMP directory, never uploaded as an artifact.

Verified results:

| Check | Result |
| --- | --- |
| PHP syntax | 73/73 |
| Migrations / seed in disposable MySQL | 3 migrations and seed passed |
| CLI regression on Windows | 12/12 |
| Security/workflow regression | 119/119 |
| Production base-path and package simulation | 154/154 in the final disposable run |
| Local original-layout HTTP acceptance | 17/17 |
| SFTP safeguard unit tests | 7/7 |
| ZIP allowlist / manifest SHA-256 audit | 108/108 source entries passed |

The first security run passed 118/119: the robots test used Http::basePath from
CLI, which falls back to the tests/ script directory when APP_URL has no path.
Its expectation now derives from the HTTP base URL under test. The corrected
suite passes 119/119. The CLI suite now explicitly skips two Windows-only shell
checks on Linux and retains them on Windows. No application runtime behavior
was changed to make tests pass.

During harness setup, PowerShell treated MySQL's stderr status output as an
exception; initialization was changed to Start-Process with explicit exit checks.
Apache test-server cleanup was also tightened to terminate only its own process
tree and to reject occupied test ports. Verification was rerun after these fixes.

The release builder's exclusions now also cover public uploads, private paths,
credential key files, cookie/session files and additional archive formats.
Runtime storage, .env, dumps, backups, dist, Git and handoffs remain excluded.
The transfer helper independently rejects unsafe archive names and hashes every
source entry. Production storage files are excluded again at transfer time.

## Secret scans

```powershell
& "$toolDir\gitleaks.exe" git --redact --no-banner --log-opts='publication-clean' .
& "$toolDir\gitleaks.exe" git --redact --no-banner --config .github/deployment/gitleaks.toml --log-opts='publication-clean' .
```

The default history scan found one false positive: .env.production.example:7,
the exact literal APP_KEY placeholder `REPLACE_WITH_UNIQUE_64_CHAR_HEX_KEY`.
The checked-in Gitleaks config allows only that exact matched assignment at that
exact template path. With this narrow exception the complete sanitized history
passes with no findings. Redacted JSON findings were inspected only for rule,
path, line, fingerprint and redacted match metadata.

The final working-source snapshot is scanned separately using Gitleaks dir.
Build it from `git ls-files --cached --others --exclude-standard`, preserving
relative paths in a fresh private TEMP directory; this excludes ignored .env,
runtime data, Git history and archives. Scan the extracted credential-free
release as well. Do not scan the entire live workspace indiscriminately or
publish scanner reports containing private values. Secret detection is heuristic;
history topology checks and the narrow source/package allowlist are also required.

```powershell
$scanDir = Join-Path $env:TEMP ('axa-source-scan-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory $scanDir | Out-Null
git ls-files --cached --others --exclude-standard | ForEach-Object {
    $target = Join-Path $scanDir $_
    New-Item -ItemType Directory -Force (Split-Path $target -Parent) | Out-Null
    Copy-Item -LiteralPath $_ -Destination $target
}
& "$toolDir\gitleaks.exe" dir --redact --no-banner --config .github/deployment/gitleaks.toml $scanDir
$releaseScan = Join-Path $env:TEMP ('axa-release-scan-' + [guid]::NewGuid().ToString('N'))
Expand-Archive -LiteralPath dist/hostinger/axa-kenmark-localhost-copy.zip -DestinationPath $releaseScan
& "$toolDir\gitleaks.exe" dir --redact --no-banner --config .github/deployment/gitleaks.toml $releaseScan
git merge-base --is-ancestor 041a4ed publication-clean
# Expected exit 1: excluded historical commit.
git merge-base --is-ancestor 12b8bb4 publication-clean
# Expected exit 1: excluded historical commit.
```

Final history, source snapshot and extracted release scans: no findings with the
exact nonsecret placeholder exception. Directory scans report absolute paths;
the allowlist accepts that same template filename at the end of an absolute path.
The first directory scan flagged the same placeholder until that path handling
was corrected. Both known contaminated commits returned ancestor exit 1.
Final private suite logs are under
`C:\Users\Kien\AppData\Local\Temp\axa-deploy-check-02c36b22bc914e5aad0550090eecaf01`.
The final fixture servers were stopped; neither test port remained listening.

Before any eventual push, repeat the above scans on every outgoing commit and
the staged source, after refreshing origin. Do not commit or publish the original
historical branches. No Git push, force-push, merge, Hostinger integration change,
live SFTP test or production deployment was performed in this task.

See hostinger-actions.md for remaining setup, staging test plan, backup receipt,
production approval gate, post-deployment checks and rollback instructions.
