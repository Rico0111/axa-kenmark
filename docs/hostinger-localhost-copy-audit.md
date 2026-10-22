# Original-layout deployment audit — 2026-10-22

## Outcome and architecture

Prepared a manual-upload package preserving `app/`, `config/`, `database/`,
`docs/`, `public/`, `scripts/`, `storage/`, and `tests/`. No directories moved or
renamed. Root `.htaccess` and a small root PHP entry point were added; public
source and styling remain in place. Protected directories have independent
deny files. The package builder no longer produces a `private/` layout or split
archives. Earlier generated releases are obsolete and must not be uploaded.

ZIP: `dist/hostinger/axa-kenmark-localhost-copy.zip`. Extract INSIDE
`public_html/axa-kenmark/`, with no extra wrapper. The sibling
`localhost-copy-manifest.json` records each packaged source file's SHA-256;
`localhost-copy-SHA256.txt` identifies the finished ZIP. All packaged source
bytes match the current working tree. Included top-level contents:

```text
app/ config/ database/ docs/ public/ scripts/ storage/ tests/
.htaccess index.php .env.example .env.production.example
README.md README-HOSTINGER.md
```

Excluded: `.git/`, `.agent-handoff/`, local `.env`, `backups/`, `dist/`, local
database dumps, runtime logs/sessions/cache and uploaded media/test data. Storage
directories and their deny file remain. Migration SQL is versioned schema source,
not a customer database export. CLI tests contain synthetic test passwords;
no provisioned local administrator account/password is distributed. Existing
customer/admin data is never exported automatically.

The current bootstrap loads root `.env`; configuration lives in
`app/Support/Config.php` (`config/` was an empty reserved directory). PDO uses
prepared statements, utf8mb4, explicit exceptions and real prepares. Routing and
session cookie scope already support APP_URL's path. Fixed advisor/product
fallback image helpers so stored `/assets/...` paths include the production
subdirectory without doubling an existing prefix. Production rejects known
example APP_KEY placeholders. No branding, stylesheet, carousel logic or template
design was changed.

## Validation evidence

The normal Laragon website and MySQL listener were offline during inspection.
Tests used an extracted ZIP in a temporary directory, a separate Apache 2.4.66 /
PHP 8.3.30 server bound to 127.0.0.1:18085 and a newly initialized MySQL 8.4.3
instance on 127.0.0.1:13316, database `axa_deployment_audit`. No connection or
write was made to existing `axa_kenneth_db`; no existing local administrator
password or customer records were changed. Temporary services are stopped after
validation. Only this project's source was edited.

| Check | Result |
| --- | --- |
| Existing security/workflow harness | 119/119 pass against extracted subdirectory deployment |
| Existing CLI/password suite | 12/12 pass; now creates/removes its own temporary admin rather than resetting ID 7 |
| Production simulation/archive checks | 151/151 pass; URL helpers, image paths, cookie path, preserved hierarchy and archive hashes |
| PHP syntax | All 72 application/script/test PHP files pass PHP 8.3 lint |
| Read-only local HTTP acceptance | 17/17 pass; landing/admin/assets 200, protected targets 403 |
| Real nonsecret storage marker | 403 |
| mod_rewrite absent | Landing and direct public entry both deny with 403 |
| Browser (headless installed Chrome) | 18 checks pass; no JavaScript page errors |
| Public product carousel | Five cards render; next/previous buttons change the active slide |
| Advisor profile / images | Image loads at subdirectory URL; admin advisor/products/media pages render |
| Responsive layout | No horizontal overflow at 390, 768, 1024 and 1440 pixels |
| Three public forms | Browser submits inquiry, consultation, recruitment; each returns 303 and persists a row with consent |
| Admin authentication/session | Browser login reaches dashboard; harness verifies roles, logout, invalidation, CSRF, cookie flags and proxy spoof rejection |
| File uploads | Valid PNG upload/serve/ETag/304/delete pass; disguised PHP/image upload rejected, no media record created |
| Errors | Safe 404 output; invalid production key and unavailable database return controlled errors without secret disclosure |

Security harness expectations now allow 403 for original-tree internals, and its
robots check understands subdirectories. Production simulation now validates the
new archive instead of an obsolete `private/` ZIP. Mutating suites reject
APP_ENV=production; they still must be run only on disposable test installations.
Local test storage and browser fixtures were outside the repository and ZIP.
Browser tests used the public seed catalog, not a copy of customer data.

## Actual Hostinger HTTP results and 500 findings

Read-only GET requests to `https://techtalkmnl.com/axa-kenmark/` and
`/admin/login.php` both returned HTTP **500** (IP observed: 145.79.24.61).
The current server returned 200 for CSS, JS and advisor image; 403 for the two
environment templates, migration SQL and `.git/config`; and 500 for
`app/bootstrap.php`, `config/`, legacy documentation HTML, the CLI key script,
`storage/`, the test harness, `public/index.php` and README. **10 of 17 acceptance
checks failed.** No response bodies or potentially private content are included
in this report.

These requests tested the CURRENT deployed release, not the newly built ZIP.
They demonstrate a failing live deployment; a 500 for a protected path does not
prove the deny rules work. No upload, deployment, production configuration
mutation, database mutation, or change to the main website occurred.

**Exact HTTP 500 cause is unconfirmed: production logs were unavailable.**
Missing root routing, the old builder's incompatible layout, missing bootstrap,
bad/missing .env or key, PHP extensions/handler, storage permissions, database
grants/import, parent routing and rejected .htaccess directives are candidates.
Local compatibility fixes are not evidence that one candidate caused the live
500. Correlate a reproduced request's timestamp with Hostinger server errors and
the application's private storage logs before diagnosing.

The old handoff's READY designation and historical test totals do not establish
production readiness. Hostinger's support describes error logs under `.logs/`
and hidden `.htaccess` handling; LiteSpeed's documentation describes configurable
override permissions. None establishes the exact account's active settings:

- https://www.hostinger.com/support/1583298-where-to-find-your-website-s-error-logs-in-hostinger/
- https://www.hostinger.com/support/1583307-how-to-create-an-htaccess-file-at-hostinger/
- https://www.litespeedtech.com/docs/webserver/config/vhostgeneral

## Required manual installation and remaining blockers

Follow `README-HOSTINGER.md` for the complete installation checklist, root .env
configuration, phpMyAdmin export/import, sensitive media transfer, permissions,
server restrictions and safe fallback. In particular:

1. Upload the credential-free ZIP into a clean dedicated application directory;
   verify actual Hostinger deny rules using nonsecret files BEFORE credentials.
2. Export `axa_kenneth_db` separately from `http://localhost/phpmyadmin` using
   Custom SQL structure/data and all application tables. Keep it privately.
3. Create a new dedicated Hostinger database/user, import privately via its
   phpMyAdmin, and compare counts/history. Never import into the main site's DB.
4. Copy root `.env.production.example` to root `.env`; set APP_ENV=production,
   APP_URL=https://techtalkmnl.com/axa-kenmark/, a new APP_KEY and hPanel DB_* values.
5. Transfer only reviewed existing media after deny checks. Set storage/.env
   permissions, reset production admin credentials, then test all live workflows.

Production remains **NOT ACCEPTED** until the new rules pass on Hostinger, the
database/environment are manually configured, HTTPS Secure cookies and real
workflows are checked, and any remaining 500 is explained by logs. If Hostinger
cannot enforce restrictions, provider-level path rules or a document root mapped
to the existing `public/` directory are required. A dedicated subdomain can keep
the tree intact but changes APP_URL; the exact desired subdirectory URL needs a
provider-controlled alias/location. No architecture change was made automatically.

No push, merge or deployment was performed.
