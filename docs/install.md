# Installation and local setup

This guide targets the Laragon installation used for this project. The same
steps apply to another Apache/PHP/MySQL host with adjusted paths.

## 1. Prerequisites

- PHP 8.3 CLI and Apache module with `pdo_mysql`, `fileinfo`, and `mbstring`.
- MySQL 8.0 or later.
- Apache 2.4 with `mod_rewrite`, `mod_headers`, and `AllowOverride All`.
- Write access to `storage/` for logs, sessions, cache, and uploads.

Use the PHP 8.3 binary explicitly on this machine:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -v
```

The `php` command on `PATH` may point to PHP 8.4 or another installation. Running
the scripts with a different binary can produce misleading results.

## 2. Place the project

The project lives at:

```text
D:\laragon\www\axa-kenmark
```

The Apache document root must be:

```text
D:/laragon/www/axa-kenmark/public
```

Never use the repository root as `DocumentRoot`. The root contains `.env`,
application code, database migrations, and private uploads.

### Laragon vhost

The active vhost is:

```apache
<VirtualHost *:80>
    DocumentRoot "D:/laragon/www/axa-kenmark/public"
    ServerName axa-kenmark.test
    ServerAlias *.axa-kenmark.test
    <Directory "D:/laragon/www/axa-kenmark/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

File:

```text
D:\laragon\etc\apache2\sites-enabled\auto.axa-kenmark.test.conf
```

The original vhost is backed up at:

```text
backups/auto.axa-kenmark.test.conf.before-public-root-20261022-150134.bak
SHA-256 F9B5FFD7FD81174A9AC3B7EF8DA960A8A943EA1D572EFBC49AFB3091D0BBDA69
```

Test the configuration before restarting Apache:

```powershell
D:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin\httpd.exe -t
```

Restart Apache through Laragon, or restart the running `httpd` processes. Then
verify:

```powershell
curl.exe -I http://axa-kenmark.test/
curl.exe -I http://axa-kenmark.test/admin/
```

Do not change DNS, enable public access, or deploy to a public host as part of
local setup.

## 3. Create the database and environment

For a fresh install, run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\setup-local-database.php
```

The script:

- creates `axa_kenneth_db` if it does not already contain tables;
- creates `axa_kenneth_app` with `SELECT`, `INSERT`, `UPDATE`, and `DELETE` only;
- creates `axa_kenneth_migrator` with DDL rights scoped to `axa_kenneth_db`;
- generates an `APP_KEY` and random database passwords;
- writes `.env` with restrictive permissions;
- verifies both accounts can authenticate.

It refuses to overwrite an existing `.env` or an existing non-empty database.
The current local installation already has `.env` and seeded tables, so **do not
rerun setup** unless you intentionally created a separate environment.

Local Laragon MySQL commonly uses `root` with an empty password. The script
defaults to that. For another host, set `MYSQL_ADMIN_USER` and
`MYSQL_ADMIN_PASSWORD` in the environment before running it, or enter the
password when prompted.

Keep `.env` private. It is ignored by Git and must never be committed or included
in a support bundle.

### Environment variables and proxy trust

Key configuration parameters defined in `.env`:

| Key | Description | Default |
| --- | --- | --- |
| `APP_URL` | Canonical public URL | `http://axa-kenmark.test` |
| `APP_KEY` | 32-byte hexadecimal encryption/HMAC key | Generated at setup |
| `APP_TIMEZONE` | Application timezone | `Asia/Manila` |
| `SESSION_NAME` | Session cookie identifier | `axa_kenneth_session` |
| `SESSION_IDLE_MINUTES` | Inactivity session timeout | `30` |
| `SESSION_ABSOLUTE_HOURS` | Maximum session lifetime | `8` |
| `TRUST_PROXY_HEADERS` | Trust `X-Forwarded-*` headers | `false` |
| `PRIVACY_NOTICE_VERSION` | Current privacy notice version | `2026-10-22` |

**Security Note on `TRUST_PROXY_HEADERS`**: Keep `TRUST_PROXY_HEADERS=false` unless the
application is deployed behind a trusted reverse proxy (such as Cloudflare or AWS ALB)
that terminates TLS and overwrites client-supplied forwarded headers. Leaving this
`false` prevents clients from spoofing `X-Forwarded-Proto` over plain HTTP to manipulate
cookie security or protocol checks.

## 4. Apply schema and seed data

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\migrate.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\seed.php
```

Migrations are forward-only and tracked in `schema_migrations`. Re-running the
migration command reports already-applied files. Batch 2 adds
`002_admin_session_version.sql` (session generation for account invalidation) and
`003_business_inbox.sql` (the `inquiries` table and its indexes). The seed is
idempotent and does not overwrite existing rows, so it is safe after administrators
have edited content.

The seed creates:

- four published service categories;
- five published products marked `unverified`;
- the advisor profile with an unverified title and organization;
- default site settings.

The unverified state is intentional. Do not mark a product or advisor detail as
verified until its wording and authorization have been checked against current
official materials.

## 5. Create an administrator

No default administrator is installed. Provision one interactively:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\create-admin.php
```

The prompts ask for:

- administrator name;
- email;
- role (`1` = super administrator, `2` = administrator);
- password;
- confirmation.

Password requirements: at least 12 characters with letters and numbers. The
password is hashed with Argon2id and is never printed or written to the
repository.

Roles:

- **Administrator** can manage the inquiry inbox, advisor profile, categories,
  products, and media. The profile title and organization are saved but remain
  masked publicly unless a super administrator has verified them.
- **Super administrator** can additionally verify products and advisor title or
  AXA authorization, edit site settings, and manage administrator accounts.

Account changes made through the administration area behave as follows:

- password resets and deactivation sign out that account's other sessions;
- the last active super administrator cannot be deactivated or demoted;
- administrators cannot deactivate or demote their own account;
- password resets never display or store the new password in audit metadata.

Remove or deactivate temporary test accounts before handing the site to Kenneth.
Do not create shared accounts.

## 6. Verify the installation

```powershell
curl.exe -s -o NUL -w "%{http_code}`n" http://axa-kenmark.test/
curl.exe -s -o NUL -w "%{http_code}`n" http://axa-kenmark.test/privacy
curl.exe -s -o NUL -w "%{http_code}`n" http://axa-kenmark.test/sitemap.xml
curl.exe -s -o NUL -w "%{http_code}`n" http://axa-kenmark.test/robots.txt
curl.exe -s -o NUL -w "%{http_code}`n" http://axa-kenmark.test/contact.vcf
curl.exe -s -o NUL -w "%{http_code}`n" http://axa-kenmark.test/admin/
```

Expected: `200` for the public pages and `303` for `/admin/` before signing in.
A missing page should return `404`, not a PHP stack trace.

The public forms are at `/#contact-forms`. Their endpoints are `POST /inquiry`,
`POST /consultation`, and `POST /recruitment`. A `GET` to those endpoints returns
`405`, and a `POST` without a valid session CSRF token returns `403`.

Sign in, edit a draft record, and confirm the public page does not expose
unverified product details or an unverified advisor title/organization. Submit a
test inquiry, confirm it appears in the inbox, and then handle or remove the test
record. Do not leave test inquiries in the handoff database.

## 7. Storage and permissions

Create these directories if they do not exist:

```text
storage/logs
storage/sessions
storage/cache
storage/uploads/media
```

The application creates missing directories with restrictive permissions. On
Windows, verify that the Apache account can write to `storage/` but that the
directory is not reachable through the web server.

Logs:

```text
storage/logs/app-error.log
storage/logs/php-error.log
```

These files are ignored by Git. Review them after installation instead of
enabling display of errors to visitors.

## 8. Common problems

### 500 error or blank page

Check `storage/logs/app-error.log` and `storage/logs/php-error.log`. Verify
`APP_KEY` is at least 32 characters and the database credentials in `.env` work.
Do not enable `APP_DEBUG` on a public host.

### 403 on an admin form

The CSRF token is missing or expired. Refresh the page and submit again. A
rejected token returns HTTP 403 by design.

### 404 on every page except the home page

Confirm `mod_rewrite` is enabled and the vhost points at `public/`, not the
repository root. Confirm `AllowOverride All` is set for that directory.

### Database connection error

Verify MySQL is running and `.env` uses the runtime account, not the migration
account. Then test with:

```powershell
D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -h 127.0.0.1 -u axa_kenneth_app -p axa_kenneth_db
```

The migration account is for `scripts/migrate.php` and `scripts/seed.php` only.

### Sign-in blocked

Five failed attempts for the same email/IP trigger a temporary lockout, and the
IP limit is 20 attempts per 15 minutes. Wait for the window to expire. Do not
delete rate-limit rows as a substitute for investigating repeated failures.

### Public form returns 429

A connection is limited to five public form submissions per 30 minutes. The
response includes `Retry-After: 1800`. Wait for the window to expire, then retry.
The limit is intentional abuse protection; do not disable it to test repeatedly.
For isolated local testing, wait for the window or clear only the relevant local
`rate_limits` rows afterward. Never clear production rate limits as a shortcut.
