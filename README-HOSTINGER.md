# AXA-Kenneth: original-layout Hostinger installation

This guide supersedes older `private/` and split-package handoff instructions.
Use only `dist/hostinger/axa-kenmark-localhost-copy.zip`. Nothing is deployed
automatically. The main website and its root configuration must stay unchanged.

Rebuild with `php scripts/build-hostinger-package.php` (PHP ZipArchive required).
On this Laragon CLI, zip is disabled in php.ini; enable it for the build only:
`D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -d extension=zip scripts/build-hostinger-package.php`.
The builder never reads .env or connects to a database. Manifest, exclusions and
SHA-256 are written beside the ZIP; these are review files, not website uploads.

## Layout and extraction

Create/open `public_html/axa-kenmark/` in File Manager, upload the ZIP INSIDE it,
and extract there. The archive has no wrapper directory:

```text
app/ config/ database/ docs/ public/ scripts/ storage/ tests/
.htaccess index.php .env.example .env.production.example
README.md README-HOSTINGER.md
```

Show hidden files and confirm `.htaccess` exists at the project root and inside
the protected directories. The public source remains `public/index.php` and
`public/admin/`. Do not extract over an old `private/` deployment: move the old
application to a non-web backup location first using File Manager. Remove the
uploaded ZIP after extraction. Never upload the repository's local `.env`.

The package includes migrations and seed SOURCE, public assets, documentation,
CLI scripts and tests. It excludes Git, handoffs, backups, local `.env`, dist,
database dumps, logs, sessions, caches, and uploaded media. Tests and scripts
are denied over HTTP. Existing media requires the separate private transfer below.

## Server prerequisite and mandatory acceptance gate

Use PHP 8.3+ with `pdo_mysql`, `mbstring`, `fileinfo`, sessions and Argon2id
support, and MySQL 8+. Allow this application's PHP process to write only its
`storage/logs`, `storage/sessions`, `storage/cache`, and `storage/uploads/media`.
Use 0755/0644 for ordinary directories/files, 0700 for private storage and 0600
for `.env` where hosting ownership permits; never use 0777. Set PHP
`upload_max_filesize` to at least 5M and `post_max_size` above 5M (e.g. 8M).
Keep `display_errors=Off` and `log_errors=On`.

Apache 2.4 or compatible LiteSpeed must honor this project's `.htaccess` files,
`Require all denied`, `Options -Indexes -MultiViews`, and rewrite `[END]`.
Apache needs `mod_rewrite` and `AllowOverride All`; headers support is useful.
Root rules deny internals before mapping approved assets and admin entry points
into `public/`. Other routes enter `public/index.php`; URLs retain `/axa-kenmark/`.
No changes to `public_html/.htaccess` are included or authorized.

**Do not place production credentials or customer data here until the actual
Hostinger rules pass.** First upload the credential-free source, then request:

```text
/axa-kenmark/.env.example
/axa-kenmark/.env.production.example
/axa-kenmark/app/bootstrap.php
/axa-kenmark/config/
/axa-kenmark/database/migrations/001_initial_schema.sql
/axa-kenmark/docs/legacy-static-index.html
/axa-kenmark/scripts/generate-key.php
/axa-kenmark/storage/
/axa-kenmark/tests/test-security-harness.php
/axa-kenmark/public/index.php
/axa-kenmark/.git/config
/axa-kenmark/README.md
```

Each must return **403**, without redirects or source content. Temporarily put a
random, nonsecret text marker inside `storage/` and verify its exact URL returns
403, then remove it. A 500, generic 404, or redirect is not proof of protection.
After configuring production, repeat for `.env`, storage marker, logs, and SQL.
Run `php scripts/check-hostinger-http.php` locally for read-only HTTP checks.
The script never downloads `.env`; it tests the credential-free templates.

If rules are ignored, denied by hosting policy, intercepted by the parent site's
router, or unsupported, STOP. Ask Hostinger to apply equivalent path-level deny
and routing rules to this application only. The closest safe alternative preserves
the entire tree but serves a dedicated subdomain with document root pointing at
`axa-kenmark/public/`. Keeping this exact subdirectory URL would require a hosting
alias/location mapping to `public/`. Neither alternative moves directories, but
requires provider configuration and possibly a changed APP_URL. Do not silently
fall back to a publicly readable repository root.

## Manual environment configuration

After the server acceptance gate, copy `.env.production.example` to `.env` in
`public_html/axa-kenmark/`. Fill every `REPLACE_WITH_...` placeholder:

- `APP_ENV=production`, `APP_DEBUG=false`.
- `APP_URL=https://techtalkmnl.com/axa-kenmark/` (include the subdirectory).
- Generate a new `APP_KEY` with `php scripts/generate-key.php` locally or in SSH.
  Copy its value into production only. Do not reuse a local key or placeholder.
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`: exact hPanel values,
  including database/user prefixes. Quote passwords with single quotes; the
  current parser removes surrounding single quotes and preserves their contents
  without expanding variables. Avoid double quotes for passwords containing backslashes.
- `DB_MIGRATION_USER` / `DB_MIGRATION_PASSWORD`: only for CLI migrations/seeds;
  hPanel may offer one database user. Do not run setup-local-database.php online.
- Leave `PUBLIC_PATH` unset; it resolves to the preserved `public/` folder.
- Keep `TRUST_PROXY_HEADERS=false` unless Hostinger confirms a trusted proxy and
  strips client-supplied forwarding headers. Verify HTTPS cookies are Secure.

Local remains `APP_ENV=local`, `APP_URL=http://axa-kenmark.test/`, using the
existing local MySQL settings. This work does not rewrite your local `.env`.

## Export the local database separately using phpMyAdmin

1. Start Laragon MySQL and Apache. Open `http://localhost/phpmyadmin`.
2. Select **axa_kenneth_db** in the left sidebar. Do not choose Drop or Empty.
3. Choose **Export > Custom > SQL**, select all application tables, and include
   structure and data. Include `schema_migrations` to preserve migration history.
   Use utf8mb4 and transactional export if available. Exclude database creation /
   `USE axa_kenneth_db` directives, which conflict with Hostinger's assigned name.
   Avoid DROP TABLE output if importing anywhere other than a fresh database.
4. Save the export privately outside this repository/public_html. A full export
   contains administrator password hashes, customer details, consent history,
   and audit records. Treat it as a secret; never put it into the source ZIP.
5. In Hostinger **hPanel > Databases > Management/MySQL Databases**, create a NEW
   dedicated database and database user. Record their full prefixed names and
   the database host; do not reuse the main website's database.
6. Open phpMyAdmin for that new database, select **Import**, choose the private
   SQL export, and run it. Review errors and compare table counts. If upload
   limits require compressed SQL or SSH import, keep the file outside public_html.
7. Update the project's production `.env` database settings as above. Delete any
   temporary server export after import and retain an encrypted offline backup.
8. Do not run seed.php after importing your existing data. If future migrations
   are necessary, back up production first and check schema_migrations.

For an exact private migration, existing administrator hashes migrate too; this
does not expose plaintext passwords, but restrict and protect the SQL all the
same. Reset production administrator passwords before launch with
`php scripts/reset-admin-password.php <id>` in the project root. For a fresh
database with no admins, run `php scripts/create-admin.php`; without SSH, use
`php scripts/generate-admin-sql.php` locally and execute its output privately in
Hostinger phpMyAdmin. Do not publish generated SQL. No default admin is shipped.

To omit test/customer data, make a separate private working copy of the export,
review which rows must migrate and their foreign keys, and remove unwanted data
from that copy or a disposable database. Never delete local source records.
Preserve legitimate consent/audit relationships when migrating real inquiries.
Use an encrypted transfer for sensitive exports.

If media rows are imported, privately transfer the corresponding reviewed files
from `storage/uploads/media/` to the same production path after the deny checks.
Preserve storage keys and permissions; omit test uploads. No uploaded data is
automatically included in the source ZIP. Database-stored absolute localhost URLs
need a reviewed update in production (not a global SQL replacement); the existing
`/assets/...` paths and `/media/{id}` helpers work with APP_URL's subdirectory.

## HTTP 500 investigation

The landing URL and `/admin/login.php` returned HTTP 500 during this audit.
These responses alone cannot identify the cause. Production logs were not
available; no exact cause is established. Older handoff claims of READY and
`private/` package advice do not validate this layout on the real server.

After reproducing once, record UTC time/URL and inspect Hostinger `.logs/`
`error_log_<domain>` through File Manager and this project's
`storage/logs/php-error.log` / `app-error.log`. Do not publish raw logs.
If no application log appears, check server logs for rejected .htaccess
directives, rewrite loops, PHP handler/version issues and missing extensions.
Application failures can include missing bootstrap, missing/invalid .env or
APP_KEY, unwritable storage, database host/grants/import errors, or stale files.
These are investigation candidates, not diagnosed production causes.

Hostinger references:
- https://www.hostinger.com/support/1583298-where-to-find-your-website-s-error-logs-in-hostinger/
- https://www.hostinger.com/support/1583307-how-to-create-an-htaccess-file-at-hostinger/
- https://www.litespeedtech.com/docs/webserver/config/vhostgeneral

## Installation checklist

- [ ] Back up the existing application privately; leave the main site untouched.
- [ ] Extract the one new ZIP into an empty public_html/axa-kenmark/.
- [ ] Verify hidden files, PHP/extensions, deny rules and nonsecret marker on Hostinger.
- [ ] Export/import the database privately into a dedicated NEW Hostinger database.
- [ ] Create root .env from production template; replace every placeholder.
- [ ] Set storage/.env ownership and permissions; transfer reviewed media privately.
- [ ] Confirm `/axa-kenmark` redirects to `/axa-kenmark/` and assets load.
- [ ] Test landing page, carousel controls, advisor photo, privacy, vCard and sitemap.
- [ ] Login at `/axa-kenmark/admin/login.php`; verify dashboard and logout.
- [ ] Submit inquiry, consultation and recruitment with unique clearly marked test
      data; verify inbox/consent, then remove only those production test records.
- [ ] Verify valid/invalid CSRF handling and HTTPS session cookies scoped to
      `/axa-kenmark/` with Secure, HttpOnly and SameSite=Lax.
- [ ] Upload a small PNG/JPEG/WebP via admin; verify /media/id and rejection of PHP.
- [ ] Re-run HTTP restrictions, review private logs, remove uploaded ZIP/temp exports.
- [ ] Confirm the main techtalkmnl.com site still behaves as before.

Do not run the mutating automated security/CLI suites against production or your
existing local database; use a disposable database and isolated test server.
The validation report at `docs/hostinger-localhost-copy-audit.md` distinguishes
local results from pending production acceptance.
