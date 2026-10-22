# Rollback and recovery

This document covers the Apache vhost change, application code, and database.
The guiding rule is: never point Apache at the repository root while the PHP
application is present. That directory contains `.env`, `app/`, `database/`, and
`storage/`, including credentials and uploaded files.

## 1. Apache vhost rollback

Original vhost backup:

```text
backups/auto.axa-kenmark.test.conf.before-public-root-20261022-150134.bak
SHA-256 F9B5FFD7FD81174A9AC3B7EF8DA960A8A943EA1D572EFBC49AFB3091D0BBDA69
```

Active vhost:

```text
D:\laragon\etc\apache2\sites-enabled\auto.axa-kenmark.test.conf
```

### Before changing anything

1. Make a timestamped copy of the current vhost in `backups/`.
2. Confirm the backup hash above.
3. Read the backup and confirm its `DocumentRoot` and `<Directory>` paths.

```powershell
$backup = 'backups\auto.axa-kenmark.test.conf.before-public-root-20261022-150134.bak'
(Get-FileHash $backup -Algorithm SHA256).Hash
Get-Content $backup
```

The backup points to `D:/laragon/www/axa-kenmark`, which was the flat static site
layout. That layout is no longer present in the current checkout. Restoring the
backup alone would expose private application files or produce a broken site.

### Safe options

**Option A — revert the application only, keep the secure document root**

Keep `DocumentRoot` at `D:/laragon/www/axa-kenmark/public` and check out an
earlier application commit on a separate branch. This is the normal code
rollback path.

**Option B — restore the static site safely**

Create a separate static checkout so its root contains `index.html` and
`assets/`, then point the vhost at that directory. Do not use the current
repository root.

```powershell
git worktree add --detach D:\laragon\www\axa-kenmark-static 789400b
```

Verify the static checkout contains `index.html` and `assets/`. Then update the
vhost to:

```apache
<VirtualHost *:80>
    DocumentRoot "D:/laragon/www/axa-kenmark-static"
    ServerName axa-kenmark.test
    ServerAlias *.axa-kenmark.test
    <Directory "D:/laragon/www/axa-kenmark-static">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Run the configuration test and restart Apache:

```powershell
D:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin\httpd.exe -t
```

Then verify `http://axa-kenmark.test/` and check that `.env`, `app/`, `storage/`,
and database files are not reachable.

### Restoring the PHP vhost

Point `DocumentRoot` and `<Directory>` back to
`D:/laragon/www/axa-kenmark/public`, run `httpd -t`, restart Apache, and verify
the public and admin URLs again.

## 2. Application code rollback

Current Git checkpoints:

| Ref | Commit | State |
| --- | --- | --- |
| `main` | `45325a3` | Baseline static website |
| `batch-0-fixes` | `789400b` | Static accessibility and interaction fixes |
| `batch-1-foundation` | `482bcc2` | PHP/MySQL public site and admin foundation |
| `batch-2-business` | `ca2fc01` | Public inquiry/consultation/recruitment forms, private inbox, settings, and administrator accounts |
| `batch-3-hardening` | `batch-3-hardening` | Security hardening, proxy header trust controls, upload verification, and end-to-end validation |

Do not force-push, rewrite history, add remotes, or merge branches automatically.
Create a new branch or checkpoint before a risky rollback.

To inspect an older state without disturbing the current worktree:

```powershell
git worktree add --detach D:\laragon\www\axa-kenmark-batch0 789400b
```

To roll the current application back to a previous commit, create a new branch
at the desired commit and switch to it after preserving any uncommitted work.

If a rollback requires restoring deleted database rows, use the database backup
and migration history; do not hand-edit production data without a backup.

## 3. Database rollback

The schema is managed by forward-only migrations in `database/migrations/`.
There are no destructive down migrations. Batch 2 added
`002_admin_session_version.sql` (an additive `admins.session_version` column) and
`003_business_inbox.sql` (the `inquiries` table). Both are additive: checking out
older application code leaves the extra column and table unused rather than
breaking the older code. Do not drop them during a code rollback unless a database
backup exists and the inquiry data is intentionally being discarded.

Before a schema change or restore:

1. Take a database dump.
2. Record the migration list from `schema_migrations`.
3. Keep the dump outside the web root and outside the repository if it contains
   customer data.
4. Do not modify unrelated databases or MySQL accounts.

Example local dump (run from a private directory, not `public/`):

```powershell
D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe `
  -h 127.0.0.1 -u root -p --single-transaction --routines --triggers `
  axa_kenneth_db > D:\laragon\backups\axa_kenneth_db-before-rollback.sql
```

Restore only after confirming the target database and taking a fresh backup:

```powershell
D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe `
  -h 127.0.0.1 -u root -p axa_kenneth_db < D:\laragon\backups\axa_kenneth_db-before-rollback.sql
```

The runtime account has no DDL rights, so it cannot drop or recreate tables.
The migration account is limited to `axa_kenneth_db`.

## 4. Verification after rollback

- `httpd -t` must pass before restarting Apache.
- The public home page must return `200`.
- `/admin/` must return `303` to sign-in when unauthenticated.
- `POST` to a public form without a valid CSRF token must return `403`, and `GET`
  to a form endpoint must return `405`.
- A missing page must return `404`.
- `.env`, `app/bootstrap.php`, `database/`, `storage/`, and `.git/` must not be
  downloadable.
- Test one administrator sign-in and sign-out if the application is restored.
- Check `storage/logs/app-error.log` for new errors.

If a rollback exposes private files, stop Apache immediately, restore the
`public/` document root, and rotate any credentials that may have been exposed.
