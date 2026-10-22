# Kenneth | AXA NFC Digital Business Card

A framework-free PHP 8.3 and MySQL 8 website for Kenneth's NFC business card and
protection-planning practice. It keeps the original AXA-inspired blue and gold
design while moving content, media, and administration into a database-backed
application.

The site includes the full public presentation, administrative foundation,
business inquiry workflows, and Batch 3 security hardening and validation.
Inquiry, consultation, and recruitment submissions flow into a private
administrative inbox with tamper-evident audit logging, role-based access control,
strict session invalidation, public abuse limits, CSP nonces, and proxy header trust
controls.

## Current features

- Public home page and privacy notice rendered from MySQL.
- Responsive AXA-inspired layout with the original carousel, motion, and imagery.
- Database-driven service categories and products.
- Explicit verification policy: the five seeded products are published but
  unverified, so the public site shows only their name, type, category, and image.
  Their summary and official URL remain hidden until a super administrator
  verifies them.
- Advisor title and organization are hidden until separately verified.
- Server-generated contact card at `/contact.vcf`, with unverified title and
  organization omitted.
- Public general-inquiry, consultation, and recruitment forms with explicit
  privacy consent, the accepted notice version, and a consent timestamp.
- Private inquiry inbox with type and status filters, search, pagination, internal
  notes, and a workflow covering new, in review, contacted, scheduled, closed, and
  spam states. First-review, first-contact, and closure timestamps are recorded.
- Campaign and NFC source tracking. A validated `src`, `source`, or `utm_source`
  value is retained in the visitor session and stored with the inquiry; otherwise
  the source is `direct`.
- Protected administration area at `/admin/` with:
  - administrator sign-in, sign-out, session expiry, and audit logging;
  - inquiry inbox and workflow management;
  - advisor profile editing;
  - category, product, and media management;
  - super-administrator-only product and advisor verification controls;
  - super-administrator-only site settings and administrator account management.
- Administrator account management with password reset, activation controls, and
  session invalidation. The last active super administrator cannot be removed,
  and administrators cannot deactivate or demote themselves.
- Server-side media library. Uploads are stored outside the web root and served
  through a controlled PHP route with ETag/304 support.
- SEO metadata, Open Graph/Twitter tags, JSON-LD, sitemap, and robots rules.
- Security controls including CSRF tokens, Argon2id password hashing, login and
  public-form rate limiting, a public-form honeypot, prepared statements, output
  escaping, CSP nonces, and security headers.

## Requirements

- PHP 8.3 with `pdo_mysql`, `fileinfo`, and `mbstring`.
- MySQL 8.0 or later.
- Apache 2.4 with `mod_rewrite`, `mod_headers`, and `AllowOverride All`.
- No Composer, Node, or build step is required to run the site.

On this Laragon installation, use the PHP 8.3 CLI explicitly. The `php` command
on `PATH` may point to a different version:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -v
```

## Quick start

The repository is already installed locally at
`D:\laragon\www\axa-kenmark` with an `.env` file and a seeded database. Do not
rerun the database setup script against this installation; it refuses to
overwrite an existing `.env`.

For a fresh installation:

```powershell
# 1. Create the database, least-privilege users, and .env.
php scripts/setup-local-database.php

# 2. Apply the schema and seed the four categories, five products, advisor,
#    and site settings.
php scripts/migrate.php
php scripts/seed.php

# 3. Provision an administrator interactively. No default account exists.
php scripts/create-admin.php
```

Then open:

- Public site: <http://axa-kenmark.test/>
- Privacy notice: <http://axa-kenmark.test/privacy>
- Administration: <http://axa-kenmark.test/admin/>

Laragon's `DocumentRoot` points to `public/`. For the original-layout Hostinger
subdirectory deployment, follow [README-HOSTINGER.md](README-HOSTINGER.md): the
project root has strict HTTP deny rules and routes requests into the preserved
`public/` folder. This arrangement requires verified server enforcement before
production credentials or data are uploaded. See [docs/install.md](docs/install.md)
for local vhost, database, and provisioning details.

### Administrator note

The temporary test administrators used during verification have been removed.
There is currently **no administrator account** in the database. Create one with
the interactive CLI before signing in:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\create-admin.php
```

Choose `1` for a super administrator when verification controls are required.
Passwords must contain at least 12 characters and include letters and numbers.

## Project structure

```text
axa-kenmark/
├── app/
│   ├── bootstrap.php              # Autoloading, environment, error handling
│   ├── Controllers/               # Public and admin controllers
│   ├── Repositories/              # PDO data access
│   ├── Support/                   # Auth, CSRF, sessions, validation, etc.
│   └── Views/                     # PHP templates
├── database/
│   ├── migrations/                # Forward-only SQL migrations
│   └── seeds/                     # Idempotent seed data
├── docs/                          # Installation, rollback, testing, legacy page
├── public/                        # Apache document root
│   ├── admin/                     # Admin front controller
│   ├── assets/                    # CSS, JavaScript, images
│   ├── .htaccess                  # Rewrites, headers, private-file blocking
│   └── index.php                  # Public front controller
├── scripts/                       # Setup, migration, seed, admin provisioning
├── storage/                       # Private logs, sessions, cache, uploads
├── backups/                       # Local vhost backup (ignored by Git)
├── .env.example
└── README.md
```

## Configuration

Configuration is read from `.env`, which is ignored by Git. Start from
`.env.example` on a new machine.

| Setting | Purpose |
| --- | --- |
| `APP_ENV`, `APP_DEBUG` | Environment flag and safe error behavior. Keep `APP_DEBUG=false` outside local development. |
| `APP_URL` | Canonical base URL used by links, canonical tags, sitemap, and vCard. Use the final HTTPS URL in production. |
| `APP_KEY` | At least 32 random characters. Used for security identifiers and rate-limit hashing. |
| `DB_*` | Runtime account. It has only `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on `axa_kenneth_db`. |
| `DB_MIGRATION_*` | Separate migration account with DDL rights scoped to `axa_kenneth_db` only. |
| `SESSION_*` | Session name, idle timeout, and absolute lifetime. |
| `MEDIA_MAX_BYTES` | Maximum upload size, default 5 MiB. |
| `PRIVACY_NOTICE_VERSION` | Default notice version; the value in site settings is recorded with each accepted public form submission. |

Private runtime data lives under `storage/` and is excluded from Git. Uploaded
media is written to `storage/uploads/media/`, outside the document root, and is
served only through `/media/{id}`.

## Content and verification policy

- The five existing products are seeded as `published` with
  `verification_status = unverified`. This is deliberate.
- Public product cards show only the name, product type, category, image, and a
  neutral note. Summary and official URL are withheld while unverified.
- Only a super administrator can mark a product or advisor detail as verified.
- Any product content change clears verification. A super administrator must
  review and re-verify it in the same request.
- Any advisor title or organization change clears that specific verification for
  a regular administrator. A super administrator must review it again.
- Do not verification-mark credentials, benefits, premiums, eligibility, AXA
  authorization, or brand permissions without current official documentation.
- Testimonials remain deferred.

## Security notes

- All POST requests require a session-bound CSRF token. Invalid or missing tokens
  return HTTP 403 with a session-expired message.
- Passwords use Argon2id with a per-user salt and are rehashed when parameters
  change.
- Sessions use strict mode, HttpOnly, `SameSite=Lax`, periodic ID rotation, idle
  timeout, absolute timeout, and user-agent binding.
- Login attempts are rate-limited per account/IP and per IP; error messages do not
  reveal whether an email exists.
- Public forms are limited to five submissions per IP per 30 minutes. They also
  use a honeypot field, server-side validation, an explicit consent checkbox, and
  a CSRF token.
- Administrator password resets and deactivation increment a per-account session
  generation, invalidating that account's existing sessions. A self password
  reset keeps the current session signed in.
- Settings and administrator changes are audit-logged without passwords, inquiry
  notes, or submitted personal data.
- Uploads are validated by actual MIME type, image dimensions, and size, stored
  with random names, and served with `nosniff`.
- The Content Security Policy uses a per-request nonce for inline JSON-LD.
- Hidden files, `.env`, SQL, logs, and common script extensions are blocked by
  Apache rules. Application, database, and storage directories are outside the
  document root.
- Changes to content and accounts are recorded in `audit_logs`; failed and
  successful logins are recorded without passwords.

## Testing

Verification results, reproduction commands, and known gaps are in
[docs/testing.md](docs/testing.md). PHP lint, migrations, seeds, public/admin HTTP
flows, inquiry and account workflows, role restrictions, CSRF, media handling,
rate limiting, and responsive browser checks were run locally on 22 October 2026.
The Batch 2 end-to-end harness completed 129 checks with no failures.

## Rollback

The Apache vhost was changed to use `public/` as the document root. The original
vhost is backed up at:

```text
backups/auto.axa-kenmark.test.conf.before-public-root-20261022-150134.bak
SHA-256 F9B5FFD7FD81174A9AC3B7EF8DA960A8A943EA1D572EFBC49AFB3091D0BBDA69
```

Do **not** point the vhost back at the repository root while this application
exists; that would expose `.env`, `app/`, `database/`, and `storage/`. A safe
rollback requires a separate static checkout or a restore of the pre-PHP
deployment. Full instructions are in [docs/rollback.md](docs/rollback.md).

## Batch 2 boundaries

Not included yet:

- email or SMS notifications for new inquiries or account events;
- bulk inquiry export, deletion, or retention automation;
- a public unsubscribe or data-deletion request workflow (requests must be handled
  through the published contact channels);
- premium payments, policy issuance, binding quotes, or claims processing;
- personalized AI insurance advice;
- production deployment, DNS changes, or HTTPS provisioning.

The five products and the advisor title/organization remain unverified. Inquiries
are stored for authorized administrators only and are never used to issue a policy,
bind coverage, collect a premium, or process a claim.

## Related documentation

- [Installation and local setup](docs/install.md)
- [Rollback and recovery](docs/rollback.md)
- [Testing and verification](docs/testing.md)
- [Original static page](docs/legacy-static-index.html)
- [Original supplied notes](docs/ORIGINAL_README.txt)
