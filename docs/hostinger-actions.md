# Hostinger 2 deployment and readiness

Prepared 2026-10-23 for `rolex-esto/axa-kenmark`. Local implementation only;
no push, workflow dispatch, SFTP connection, staging transfer or production
deployment was performed. Hostinger integration settings and Hostinger 1 were
not touched. Existing production acceptance failures in the handoff remain
unresolved until a separately authorized live check establishes otherwise.

## Publication boundary

GitHub main was verified through the API as the single root commit
`d6542570f9bdc4d6300fee8326c8b1ca4a871fba`, matching origin/main and
publication-clean. It has no parents. Local main and batch-* branches belong
to the unsafe original history. Never merge, push --all, push --mirror, push
tags indiscriminately, or force-push that history. Work here is uncommitted on
publication-clean. No AGENTS.md was found in the project or its ancestor
directories; `.agent-handoff/CURRENT.md` was read before switching branches.
Its statement that GitHub is empty/unauthenticated is superseded by this audit.

Before eventual publication, fetch origin, inspect the complete outgoing commit
list and diff, and rerun the scans in deployment-validation.md. Every new commit
must descend solely from sanitized main; recheck that the known contaminated
commits are not ancestors. Stage explicit files, review `git diff --cached`,
and publish only the reviewed clean branch/ref. Do not use the local branch
named main as the source. Publishing this workflow does not deploy anything.

## Workflow behavior

`.github/workflows/deploy-hostinger.yml` accepts only workflow_dispatch on
repository main. `validate` is the default and receives no hosting secrets.
All jobs have contents:read; checkout does not retain credentials. Actions are
pinned by SHA. A shared concurrency group serializes staging and production;
in-progress transfers are never automatically canceled.

The release job lints all application PHP, builds the established original-layout
ZIP, uses disposable MySQL 8.4 and Apache/PHP 8.3 for the existing CLI, security,
production simulation and HTTP suites, audits ZIP paths/manifest hashes, and
tests transfer safeguards. Linux explicitly skips the two Windows shell checks;
the remaining CLI checks still run. Windows verification covers all twelve.
No production database setup, migration, seed or account provisioning runs.

Transfer rebuilds source at the same immutable workflow SHA on a fresh runner.
The helper checks every uploaded byte against the manifest. It uses Paramiko's
SFTP subsystem with password authentication, only the supplied known_hosts,
RejectPolicy, no SSH agent and no key discovery. There is no SSH shell, remote
archive extraction, mirror operation or remote deletion command.

Production is hardcoded to:
`/home/u569245175/domains/techtalkmnl.com/public_html/axa-kenmark`.
The main website's directory is never a write destination. All ancestors must
be real directories and normalize to their exact absolute paths. Every existing
leaf must be a regular file. Chroot aliases and symlinks fail closed; do not
relax these checks to make an unexpected account work.

The release excludes dumps, private credentials, archives and runtime content.
Production transfers exclude the entire storage tree, including its .htaccess;
that existing protection must already match the release. `.env`, public/uploads,
logs, sessions, caches, persistent storage and unowned remote files are preserved.
New source directories can be created inside the exact destination only.
Uploaded source gets mode 0644 and new directories 0755. Existing persistent
permissions are not changed. Deny rules upload first; temporary files use
exclusive creation, hash read-back and OpenSSH posix-rename. All final files are
read back and hash-verified. If posix-rename is unsupported, stop and review;
there is no fallback that deletes the old file.

Each file replacement is atomic; a release spanning many files is not. A failed
transfer can leave a mixed release and temporary files. Do not auto-retry a
production failure without inspecting it. No automatic rollback is attempted.
Obsolete source files are retained by design and require separate review.

## Secrets and environment setup

All five repository secret names were present as of the audit. Values cannot be
read back from GitHub; presence does not prove connectivity or account identity.
Use Settings > Secrets and variables > Actions or:

```powershell
gh auth status
gh secret list --repo rolex-esto/axa-kenmark
gh api repos/rolex-esto/axa-kenmark/environments
```

Privately verify HOSTINGER_SFTP_HOST, HOSTINGER_SFTP_PORT and
HOSTINGER_SFTP_USER against the **Hostinger 2** hPanel SFTP account and assigned
account home. Check HOSTINGER_SFTP_PASSWORD without echoing it. Supply raw
OpenSSH known_hosts lines in HOSTINGER_SSH_KNOWN_HOSTS, not a fingerprint-only
string, JSON or base64. A nonstandard port needs `[hostname]:port` as its host
field. Obtain and compare the host-key fingerprint through an independent
trusted Hostinger channel before setting the secret; a fresh ssh-keyscan alone
does not authenticate the server. Host-key rotation requires independent review.
Never print secrets, enable shell tracing, paste them into issue comments, or
pass passwords on command lines. A connection error reports only its type.

No GitHub deployment environments existed during this audit. Before any transfer,
create `hostinger-2-staging` and `hostinger-2-production` in repository Settings
> Environments. Limit both to main. Configure production required reviewers and
disable administrator bypass where available. If the repository plan does not
support these controls, keep production disabled until an equivalent reviewed
gate is available. Environment creation and settings changes were not performed.

Production additionally requires the variable
`HOSTINGER_2_APPROVED_PRODUCTION_SHA` to equal the complete workflow commit SHA,
plus a backup_reference input of at least twelve characters. Leave the variable
unset until the user explicitly authorizes the first live deployment. The
variable is an operator approval record, not a substitute for the user's approval
or an actual backup verification. Set it for a single reviewed SHA only and
remove it after deployment. Environment reviewers should check the selected SHA,
backup receipt and staging result before approving.

## Private staging transfer test plan

1. Independently confirm this is the Hostinger 2 account. In its private home,
   provision `/home/u569245175/axa-kenmark-deploy-staging` with mode 0700, outside
   public_html. Create `.axa-deploy-target` inside it, containing exactly
   `axa-kenmark-hostinger-2` followed by one LF newline. Do not create a symlink.
   The workflow never creates this trusted base or marker itself.
2. Publish the reviewed workflow safely to sanitized main and run `validate`.
   Review the application results, package manifest and artifact. No hosting
   connection is made in this mode.
3. Manually run the same main SHA with mode `staging`, after configuring the
   staging environment. It creates a fresh private `<run-id>-<attempt>` directory,
   transfers credential-free source, and checks every remote SHA-256 by read-back.
   Existing run directories are rejected. Confirm the expected Hostinger account
   and private absolute directory independently in hPanel/SFTP.
4. Confirm no changes under public_html, no production .env access and no main
   website writes. Test failure paths with an isolated test SFTP server or the
   local safeguard tests: wrong host key, wrong marker, symlink ancestor/file,
   insufficient permissions, and unsupported posix-rename must fail closed.
   Do not damage real production files to test rejection behavior.
5. This proves transfer integrity only; private staging is not HTTP execution.
   An HTTP staging environment needs separately approved hosting configuration,
   a separate database, fresh APP_KEY and staging .env. Never copy production
   customer data into a public test site. No new subdomain is configured here.
   Private staging artifacts can be removed later by an operator after reviewing
   their exact paths; the workflow never recursively deletes remote files.

## Production backup and first-deployment gate

Before requesting approval, prepare a dated backup receipt identifying the source
SHA, previous release, exact AXA target, dedicated database, storage snapshot,
checksums, backup location and restore verification. A typed workflow input only
records the operator's assertion; the workflow cannot prove the backup exists.

1. Schedule a maintenance window and pause AXA customer/admin writes using a
   separately reviewed application-only mechanism. Do not edit the TechTalk root
   .htaccess. Record the root website's status and a visual baseline.
2. With hPanel/private SFTP tools, copy the complete AXA application directory
   including dotfiles, `.env`, storage, uploads, logs and sessions into a dated
   private backup outside public_html and outside this repository. Encrypt any
   downloaded backup. Restrict access to .env and customer data. Keep backups
   out of Actions artifacts and logs. Verify counts, hashes and readability.
3. Export only the dedicated AXA database using phpMyAdmin or a transactional
   mysqldump to private storage. Include migration history, triggers and required
   metadata. Verify the database name and restore into a disposable database;
   confirm row counts and application compatibility. Do not export/restore the
   main website's database. Keep a fresh backup before any later restore.
4. Reconcile the existing production layout with README-HOSTINGER.md. An old
   `private/` layout must be handled manually before enabling this workflow.
   Provision/review storage/.htaccess and writable runtime directories, .env
   and schema separately. This workflow does not upgrade the database.
5. Independently verify the production absolute directory, then create the same
   `.axa-deploy-target` marker there. Verify Apache/LiteSpeed private-path deny
   rules with credential-free templates before placing production credentials.
   Resolve the previously reported HTTP 500s. Never treat private staging as
   evidence that production PHP, MySQL, routing or access controls work.
6. Obtain explicit user authorization for the first live deployment of the
   reviewed SHA, configure the approval variable and protected environment, then
   dispatch production with the verified backup receipt. No production dispatch
   command is supplied for casual copy/paste.

## Post-deployment and rollback

The workflow checks remote hashes, the existing 17 read-only HTTP acceptance
paths (including private-path 403s), and the main TechTalk website's HTTPS
availability. HTTP redirects are not accepted by the PHP acceptance script.
The root availability check alone cannot prove the root website is unchanged;
compare the saved baseline separately. Confirm authenticated admin sign-in,
Secure/HttpOnly/SameSite cookies, error logs and persistent media manually.
Form submissions require separate approved test data and cleanup.

On any failed transfer/check, keep AXA in maintenance, preserve the run records
and inspect which source files changed. Restore the previous reviewed code from
the verified backup or a known-good sanitized release, using the same exact
destination and host-key checks. Keep current .env and storage unless there is a
documented reason to restore them. Restore files individually; never recursively
delete the AXA folder or anything in public_html. Leave unrelated files intact.
Review any leftover temporary/obsolete files individually; never run a broad
cleanup. Do not use the old handoff's remove-folder/drop-tables instructions.

There are no automated migrations, so a code rollback normally leaves the
database untouched. If a separate schema operation requires database recovery,
stop writes, take a fresh backup, restore only the dedicated AXA database from
the matching snapshot, and reconcile customer changes made after that snapshot.
Never drop unrelated databases or restore the TechTalk site's database.
Rerun hash and HTTP checks, verify sign-in and media, inspect new errors, then
resume writes. Record the recovered SHA and disable the production approval
variable. Keep a failed deployment visible as failed even after manual recovery.

## Readiness

Local workflow preparation and safeguards are implemented. GitHub runner execution,
host-key/account validation, private staging transfer, production backup/restore,
environment protection setup and production acceptance remain pending. No live
deployment is authorized by this document. See deployment-validation.md for
commands, actual local results and verification limits.

References: [GitHub workflow permissions](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax),
[GitHub deployment environments](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments),
[Paramiko host-key handling](https://docs.paramiko.org/en/stable/api/client.html).
