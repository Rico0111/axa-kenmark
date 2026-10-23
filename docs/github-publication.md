# GitHub publication

This repository's initial `main` is a reviewed source snapshot with independent
history. Original development history remains backed up locally and is not
included in this publication. Do not merge or push the older local development
branches into this repository: their history contains retired administrator
provisioning credentials and password hashes.

The application retains its original app/, config/, database/, docs/, public/,
scripts/, storage/ and tests/ hierarchy. See README.md for development and
README-HOSTINGER.md for manual deployment. The optional GitHub Actions SFTP
workflow is manual-only; see docs/hostinger-actions.md. Production requires
explicit approval and environment configuration before it can run.
The earlier Hostinger audit describes observed production failures; publishing
this source does not establish production readiness.

Environment examples contain placeholders only. Never commit .env, database
exports, logs, sessions, private uploads, complete-history bundles or release
archives. The original local Git backup includes sensitive historical material
and must remain private.

Validation before publication includes PHP syntax, isolated security/workflow
and CLI suites, production subdirectory simulation, HTTP restrictions, and
secret scanning of the full publication tree and its complete outgoing history.
