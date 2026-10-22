# Testing and verification

Verified locally on 22 October 2026 against:

- Laragon on Windows.
- Apache 2.4.66.
- PHP 8.3.30 (`D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`).
- MySQL 8.4.3.
- Site URL `http://axa-kenmark.test/`.
- Chrome via Playwright 1.64.1 for responsive checks.

Temporary administrators, test inquiries, a test category, a test product, media,
rate-limit rows, session files, and test audit records were removed after
verification. The final database contains four categories, five unverified
products, zero administrators, zero inquiries, zero media records, no rate-limit
rows, and no test audit entries.

## Automated and scripted checks

### PHP syntax

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    & $php -l $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "Lint failed: $($_.FullName)" }
}
```

Result: all PHP files passed in the final Batch 2 check.

### Migrations and seed idempotency

```powershell
& $php scripts\migrate.php
& $php scripts\seed.php
```

Result: migrations `001_initial_schema.sql`, `002_admin_session_version.sql`, and
`003_business_inbox.sql` were applied. Re-running the migration command reported
them as already applied. Re-running the seed preserved existing rows, product
verification states, settings, and administrator edits.

### Batch 2 end-to-end harness

A temporary local harness exercised the public forms and administration workflows
through HTTP, using isolated test accounts, seeded records, and direct database
assertions. It was removed after the run and is not part of the repository.

Result: **129 checks passed, 0 failed.**

Coverage included:

| Area | Checks exercised |
| --- | --- |
| Public forms | all three form variants render; required fields; email, phone, and message validation; consent required; invalid submissions return 422 and are not stored; valid submissions return 303 |
| Consent and source | accepted privacy version stored; consent timestamp stored; `src` persists across pages; invalid source falls back to `direct`; IP stored only as an HMAC-SHA-256 hash; user agent stored |
| Abuse controls | honeypot receives the normal success response but creates no row; sixth submission from one IP returns 429 with `Retry-After`; invalid public CSRF token returns 403 |
| Inquiry inbox | dashboard metrics; sidebar new count; list, search, type filtering, and status filtering; detail view; consent and source display |
| Workflow | status transitions; internal notes; handling administrator; first-reviewed and first-contacted timestamps; close and reopen behavior; invalid status rejected without changing the record |
| Settings | super-admin access; validation and save; public metadata refresh; changed values restored; audit entry records changed keys only |
| Account management | create, duplicate-email rejection, role assignment, sign-in; regular-admin restrictions; deactivate and password reset invalidate sessions; last-super and self-protection rules; self password reset preserves the current session |
| Audit privacy | inquiry audit metadata contains status transitions but no names, messages, consent details, or notes; administrator audit metadata never includes passwords |

### Batch 3 security hardening and validation harness

A comprehensive automated harness was executed against `http://axa-kenmark.test/`
using PHP 8.3 and cURL cookie jars, covering protocol trust, security headers,
CSRF, injection probes, access control matrices, session invalidation, and rate limiting.

Result: **119 checks passed, 0 failed.**

| Section | Checks exercised | Result |
| --- | --- | --- |
| 1. Headers & Cookies | Public home 200; `X-Content-Type-Options: nosniff`; `X-Frame-Options: DENY`; `X-Permitted-Cross-Domain-Policies: none`; `Referrer-Policy: strict-origin-when-cross-origin`; `Permissions-Policy`; `Cross-Origin-Opener-Policy: same-origin`; `X-Powered-By` absent; CSP with random script nonce; inline scripts use header nonce; session cookie is `HttpOnly`, `SameSite=Lax`, and not `Secure` over HTTP; HSTS absent over HTTP; spoofed `X-Forwarded-Proto` does not set `Secure` cookie when `trust_proxy_headers=false`; spoofed header does not send HSTS | 17/17 passed |
| 2. Auth & Roles | Unauthenticated redirects for `/admin/` and `/admin/?r=dashboard` (303); super admin login redirects to dashboard; dashboard 200 with `X-Robots-Tag: noindex`; super admin nav displays settings and admin links; super admin access to settings, admin accounts, admin create, and advisor verification checkboxes; regular admin login 200; regular admin nav hides settings and accounts; regular admin access allowed for inquiries, categories, products, media, and advisor profile; regular admin blocked (403) from settings, admin accounts, admin create, admin edit, and admin password reset | 27/27 passed |
| 3. CSRF & Methods | Public POST without `_csrf` returns 403; public POST with invalid `_csrf` returns 403; admin POST without `_csrf` returns 403; admin POST with bad `_csrf` returns 403; `GET /inquiry`, `GET /consultation`, `GET /recruitment` return 405 with `Allow: POST`; `POST /` returns 405; `GET /admin/?r=logout` returns 405 with `Allow: POST`; `POST /admin/?r=dashboard` returns 405 with `Allow: GET` | 11/11 passed |
| 4. Rate Limiting & Abuse | Honeypot submission returns 303 but creates 0 database records; missing consent returns 422 with 0 database records; valid inquiry returns 303, persists to DB, captures campaign source `batch3-harness-campaign`, records privacy notice version `2026-10-22` and consent timestamp, and stores 64-char HMAC-SHA-256 IP hash; sixth submission from same IP returns 429 Too Many Requests with `Retry-After: 1800` | 12/12 passed |
| 5. Injection Probes | XSS probe payload (`<script>alert("xss")</script><img src=x onerror=alert(1)>"'`) safely accepted; inquiry inbox list renders HTML entities encoded; inquiry detail view renders HTML entities encoded without executing tags; SQL injection in inquiry search (`' OR '1'='1 UNION...`) executes safely without SQL errors or data leaks | 5/5 passed |
| 6. Private Paths | Blocking and zero leakage on `/.env` (403), `/database/migrations/001_initial_schema.sql` (403), `/storage/logs/app-error.log` (403), `/.git/config` (403), `/app/bootstrap.php` (404), `/media/../.env` (403), `/assets/../../.env` (403), `/..%2f.env` (404) | 16/16 passed |
| 7. Media Lifecycle | Valid 1x1 PNG upload returns 303 and inserts DB record; served at `/media/{id}` with 200, `image/png`, `X-Content-Type-Options: nosniff`, and `ETag`; conditional request with `If-None-Match` returns 304 Not Modified; media delete returns 303 and removes record and file from storage; spoofed MIME text file renamed to `.jpg` rejected and not saved | 11/11 passed |
| 8. Account Lifecycle | Duplicate email creation rejected as 422 with validation error (catches duplicate key race); super admin password reset invalidates regular admin existing session (subsequent request redirects 303 to login); regular admin signs in with new password; self-demotion and self-deactivation of super admin rejected (422); logout returns 303, clears session cookie, and post-logout dashboard request redirects 303 | 8/8 passed |
| 9. vCard, SEO & Privacy | `GET /contact.vcf` returns 200 with `text/vcard` and omits unverified `TITLE:` and `ORG:`; `GET /privacy` returns 200 and displays current notice version `2026-10-22`; SEO JSON-LD `<script>` tag carries nonce matching CSP header | 8/8 passed |
| 10. Error Handling | 404 response returned for nonexistent route without leaking PHP stack traces, filesystem paths (`D:\laragon\...`), or database details (`PDOException`, `axa_kenneth`) | 4/4 passed |

The harness assertion for stale form values was tightened to check the rendered
field values rather than any occurrence of the name in page text. That corrected
check passed.

### HTTP smoke checks

| Check | Result |
| --- | --- |
| `GET /` | 200, public home rendered from MySQL |
| `GET /privacy` and `/privacy/` | 200 |
| `GET /sitemap.xml` | 200, XML |
| `GET /robots.txt` | 200, text |
| `GET /contact.vcf` | 200, `text/vcard` |
| `GET /admin/` unauthenticated | 303 to sign-in |
| `GET /admin` | 301 to `/admin/` |
| `GET /inquiry`, `/consultation`, `/recruitment` | 405, `Allow: POST` |
| `POST` without a valid CSRF token | 403 |
| `GET /does-not-exist` | 404, no stack trace |
| `GET /.env` | 403 by Apache rewrite rule |
| `GET /app/bootstrap.php` | 404 |
| `GET /database/migrations/001_initial_schema.sql` | 403 by extension rule |
| `GET /storage/logs/app-error.log` | 403 by extension rule |
| `GET /.git/config` | 403 by hidden-file rule |

The 403 responses are intentional blocks, not application failures. Private paths
are never served from the document root.

### CSRF and sessions

| Check | Result |
| --- | --- |
| POST to a POST-only admin route without `_csrf` | 403 |
| POST with an invalid `_csrf` | 403 |
| POST to a public form with an invalid `_csrf` | 403 |
| Successful login | 303 to dashboard |
| Session ID before vs after login | changed (fixation prevention) |
| CSRF token before vs after login | rotated |
| Logout | 303 to sign-in; session cookie removed |
| Dashboard after logout | 303 to sign-in |
| Administrator session after password reset by another super admin | 303 to sign-in |
| Administrator session after deactivation | 303 to sign-in |
| Self password reset | current session remains valid; other sessions invalidated |
| Self password reset with a stale session generation | rejected and signed out |

### Public form behavior

| Check | Result |
| --- | --- |
| General inquiry | 303; row stored with type `general` and preferred contact `email` |
| Consultation | 303; row stored with selected preferred contact and topic |
| Recruitment | 303; location and relevant background stored |
| Missing name, invalid email, invalid phone, short message | 422 with field-level errors; no row created |
| Missing consent | 422 with a consent error; no row created |
| Valid consent | version and timestamp stored |
| Source `?src=nfc-test` | retained in the session and stored with later submissions |
| Invalid source | ignored; source remains `direct` |
| Honeypot filled | normal 303 response; no row created |
| Sixth submission from one IP within 30 minutes | 429, `Retry-After: 1800`, no row created |
| Submitted values after success | not echoed into a fresh form |
| Invalid submission | form reopens and only that form's safe values are retained |

### Inquiry workflow

| Check | Result |
| --- | --- |
| New submission | starts as `new` |
| First review | `first_reviewed_at` recorded |
| Contacted or scheduled | `first_contacted_at` recorded once |
| Closed or spam | `closed_at` recorded |
| Reopened | status returns to `new`; `closed_at` cleared; first-contact history preserved |
| Handling administrator | recorded |
| Internal notes | stored; escaped everywhere they are displayed |
| Filtering and search | match expected records and exclude non-matching records |
| Invalid status | 422; record unchanged |
| Audit entry | previous and new status only; no personal data or notes |

### Settings and account management

| Check | Result |
| --- | --- |
| Regular administrator opens settings | 403 |
| Regular administrator opens administrator management | 403 |
| Regular administrator navigation | hides settings and account links |
| Regular administrator uses the inquiry inbox | 200 |
| Super administrator saves settings | 303, values updated |
| Public metadata after settings save | reflects the saved title |
| Audit entry | changed setting keys only, not values |
| Duplicate administrator email | 422 with a specific error |
| Last active super administrator deactivation or demotion | rejected |
| Self-deactivation or self-demotion | rejected |
| Deactivation | increments `session_version` and invalidates the existing session |
| Password reset | increments `session_version`; the target must sign in again |

### Media

| Check | Result |
| --- | --- |
| Valid image upload | 303, row and file created |
| File location | `storage/uploads/media/`, outside the document root |
| `GET /media/{id}` | 200 with correct MIME, `nosniff`, ETag |
| Conditional request with matching ETag | 304 |
| Delete through admin | 303, database row and file removed |
| Text file disguised as `.jpg` | rejected; no row created; type error shown |
| Upload size/dimension/MIME validation | valid-size and invalid-MIME paths exercised; size and dimension limits are implemented in code |

### Rate limiting

| Check | Result |
| --- | --- |
| Five failed sign-ins for one account/IP | subsequent attempt returned the generic lockout message |
| Failed sign-in message | does not reveal whether the email exists |
| Five public form submissions from one IP | sixth returned 429 with `Retry-After` |
| Rate-limit rows | created during tests and removed afterward |

The account limit is 5 failures per 15 minutes; the IP limit is 20 per 15 minutes.
Public forms allow 5 submissions per IP per 30 minutes, shared across the three
form types. Successful authentication clears the counters for that account and IP.

### Responsive browser and accessibility checks (Batch 3)

Chrome via Playwright 1.64.1 and axe-core (evaluated via Chrome DevTools Protocol to
respect Content Security Policy nonces) was executed across both desktop (1440×1000)
and mobile (390×844) viewports for 11 key routes:

- Public Home (with all three inquiry forms expanded)
- Public Privacy Notice
- Admin Sign-in
- Admin Dashboard
- Admin Inquiries Inbox
- Admin Inquiry Detail
- Admin Accounts List
- Admin Create Account
- Admin Account Edit
- Admin Password Reset
- Admin Settings

Results across both viewports:

- **Horizontal overflow**: 0px (none detected on any page).
- **Console errors**: 0 (all requests and scripts cleanly executed; fonts allowed via CSP `connect-src`).
- **Page errors / exceptions**: 0 uncaught errors.
- **Failed requests / HTTP errors**: 0 failed network requests.
- **Form control accessibility**: 100% of visible interactive controls have associated labels or accessible names.
- **Axe-core accessibility violations**: **0 violations** across all evaluated pages on both desktop and mobile viewports. Contrast ratios, landmark uniqueness, and ARIA roles were verified and tuned to WCAG AA standards.

## Not run or intentionally out of scope

These items were not tested or cannot be tested in this checkpoint:

1. **Public deployment, HTTPS, DNS, and HSTS.** Prohibited by the project brief.
   Repro later: deploy to a staging host with a valid certificate, set `APP_URL`
   to the HTTPS URL, confirm HSTS appears only over HTTPS, and verify the CSP and
   COOP headers in a browser.
2. **Cross-browser and physical-device testing.** Only Chrome was exercised.
   Repro: install Firefox and WebKit with `npx playwright install firefox webkit`,
   then repeat the public form, inbox, settings, and account flows at 390 px and
   1440 px.
3. **Screen-reader manual evaluation.** While axe-core automated audit passed with 0
   violations, manual screen-reader testing (NVDA / VoiceOver) was not performed.
4. **Load, stress, and penetration testing.** Not performed. Repro on an isolated
   staging environment with written authorization; do not point load tools at the
   local site while other work is in progress.
5. **Email and SMS notifications.** Not implemented. Nothing is sent when an
   inquiry arrives or an account changes. Repro after implementation: use a
   sandbox mail/SMS provider, verify delivery and failure handling, and confirm
   inquiry contents are not placed in logs.
6. **Bulk export, deletion, and retention automation.** Not implemented. Inquiry
   records are retained until an authorized administrator removes them through a
   controlled database process with a backup.
7. **External AXA link and content validation.** The five product URLs and
   summaries must be checked against current official AXA materials before any
   record is verified or published with details.
8. **Payment, policy issuance, binding quotes, claims, and personalized AI
   advice.** Prohibited by the project scope.
9. **Advisor credentials, AXA authorization, and brand permissions.** These remain
   unverified by design. No test should mark them verified without documentary
   evidence and a super administrator review.

## Reproducing the checks

The database intentionally has no administrator account. To test manually:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\create-admin.php
```

Use a temporary account and remove it afterward. To remove an account safely,
sign in as another super administrator, or use a controlled SQL statement against
`axa_kenneth_db` after taking a backup. Do not leave test accounts, inquiries,
audit entries, or rate-limit rows in the handoff database.

For a public form check:

1. Open `http://axa-kenmark.test/?src=local-test#contact-forms`.
2. Submit a consultation with a temporary email address and valid consent.
3. Confirm the success message appears and the submission is in the inbox with
   source `local-test`, the current privacy version, and a consent timestamp.
4. Move it through `in_review`, `contacted`, and `closed`; then reopen or mark it
   as spam to verify the timestamps.
5. Delete the test inquiry, its audit entries, and the `local-test` rate-limit
   rows before handoff.

To verify the public masking policy without an administrator, confirm that the
home page contains the five product names and the neutral verification note, but
not their summaries or official URLs, and that the vCard contains no `TITLE:` or
`ORG:` line while the advisor fields are unverified.
