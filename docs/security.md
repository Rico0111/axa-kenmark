# Security architecture and controls

This document records the security architecture, threat model, trust boundaries,
operational procedures, and administrator handoff standards for Kenneth's AXA
NFC digital business card and practice management site.

---

## 1. System overview and trust boundaries

The system comprises two distinct operational tiers served by Apache 2.4 and PHP 8.3
with MySQL 8.4:

1. **Public visitor tier**
   - Public landing page (`/`), privacy notice (`/privacy`), contact vCard (`/contact.vcf`),
     robots rules (`/robots.txt`), XML sitemap (`/sitemap.xml`), and media route (`/media/{id}`).
   - Public form submission endpoints: `POST /inquiry`, `POST /consultation`,
     `POST /recruitment`.
   - Visitors are untrusted. All input is validated against strict white-lists,
     rate-limited, and sanitized before persistence or display.
   - Visitor sessions store CSRF tokens and source attribution only. No PII is
     stored in visitor session state.

2. **Administrative tier**
   - Private administrative interface located at `/admin/`.
   - Access restricted to authenticated administrators with active database accounts.
   - Two role levels:
     - **Administrator (`admin`)**: Inquiry inbox triage and notes, advisor profile draft
       edits, service category edits, product edits, and media upload/deletion.
     - **Super Administrator (`super_admin`)**: Full administrator privileges plus
       advisor title/authorization verification, product verification, site settings,
       and administrator account provisioning, deactivation, and password reset.
   - Administrative endpoints enforce role checks on every request, rotate session
     identifiers on privilege changes, and record tamper-evident audit logs.

3. **Database and filesystem tier**
   - Segregated MySQL database (`axa_kenneth_db`).
   - Two distinct database users:
     - `axa_kenneth_app`: Runtime user with `SELECT`, `INSERT`, `UPDATE`, `DELETE` only.
       No DDL rights (`CREATE`, `DROP`, `ALTER` prohibited).
     - `axa_kenneth_migrator`: Migration-only account with scoped schema modification rights.
   - Filesystem separation: Document root is strictly `public/`. Application logic (`app/`),
     migrations (`database/`), private storage (`storage/`), configuration (`.env`),
     and backups (`backups/`) reside outside the document root and cannot be served
     directly by the web server.

---

## 2. Core security controls

### 2.1 Defense against Injection (SQLi & XSS)
- **SQL Injection**: All database operations use PDO prepared statements with parameter
  binding. Emulated prepares are disabled (`PDO::ATTR_EMULATE_PREPARES => false`).
  No raw user input is concatenated into SQL queries.
- **Cross-Site Scripting (XSS)**: Output rendering uses context-aware HTML escaping
  via `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. Rich-text rendering
  is disallowed; internal notes and user messages render as plain text.
- **Content Security Policy (CSP)**:
  - Header: `Content-Security-Policy: default-src 'self'; img-src 'self' data: https://images.unsplash.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'nonce-...'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'`.
  - Cryptographically random nonce generated per request (`bin2hex(random_bytes(16))`)
    and injected into inline scripts (including SEO JSON-LD).

### 2.2 Cross-Site Request Forgery (CSRF)
- Every session generates a 256-bit cryptographic CSRF token.
- All state-changing endpoints (`POST`) require a matching `_csrf` token verified
  with `hash_equals()`.
- Failed CSRF checks return `403 Forbidden`.
- CSRF tokens are rotated upon authentication and privilege changes.

### 2.3 Authentication and Session Management
- **Password storage**: Passwords hashed with Argon2id using recommended parameters
  (`PASSWORD_ARGON2ID`).
- **Session cookies**:
  - `HttpOnly`: Prevents client-side script access to session cookies.
  - `SameSite=Lax`: Defends against cross-site request forgery and leakages.
  - `Secure`: Conditionally applied when HTTPS is verified.
- **Session fixation prevention**: Session ID regenerated upon login (`session_regenerate_id(true)`).
- **Session invalidation**:
  - Administrators have a database-backed `session_version`.
  - When an account's password is reset or the account is deactivated, `session_version`
    increments, immediately invalidating any existing active sessions for that account.
  - Self-resets maintain the current session while invalidating external sessions.
- **Account lockout & rate limiting**:
  - Failed login attempts tracked per email and IP address.
  - Exceeding thresholds enforces progressive lockouts (5 failures per 15 minutes per account;
    20 failures per 15 minutes per IP). Error messages are timing-neutral and generic.
  - Last active super administrator cannot be deactivated or demoted. Self-deactivation
    and self-demotion are blocked at the application level.

### 2.4 Rate Limiting & Abuse Prevention
- **Public form submissions**: Limited to 5 requests per 30 minutes per client IP
  across general inquiries, consultations, and career inquiries.
- **Honeypot protection**: Invisible honeypot field (`company_website`) traps automated
  crawlers. Trapped submissions return HTTP 303 (mimicking success) but discard data
  without database insertion.
- **IP anonymization**: Public IP addresses are not stored in plaintext. They are hashed
  via HMAC-SHA-256 using `APP_KEY`, preserving abuse mitigation without persisting raw PII.

### 2.5 Security Headers
Every HTTP response includes standard hardening headers:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-Permitted-Cross-Domain-Policies: none`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()`
- `Cross-Origin-Opener-Policy: same-origin`
- `X-Powered-By`: Explicitly suppressed / removed.
- `Strict-Transport-Security`: Enforced exclusively when HTTPS is established.

### 2.6 Media and File Upload Security
- Uploads restricted to JPEG, PNG, and WebP.
- Multi-layer validation:
  1. Validated with `is_uploaded_file()`.
  2. Measured actual file size using `filesize()` (never trusting client `$_FILES['size']`).
  3. MIME detection via PHP `finfo` (`FILEINFO_MIME_TYPE`).
  4. Image structure verification using `getimagesize()` to detect polyglot payloads.
- Stored outside document root at `storage/uploads/media/` with random filenames.
- Served through controlled PHP handler (`/media/{id}`) with forced `nosniff`, correct
  MIME header, and conditional caching (`ETag` / `304 Not Modified`).

---

## 3. Reverse proxy & HTTPS trust model

### `TRUST_PROXY_HEADERS` Setting
- By default, `TRUST_PROXY_HEADERS=false` in `.env`.
- Over plain HTTP or untrusted reverse proxies, forwarded protocol headers such as
  `X-Forwarded-Proto` and `X-Forwarded-For` are **ignored**. This prevents spoofing
  attacks where a malicious client pretends to be on HTTPS to bypass cookie security
  or trigger anomalous caching behavior.
- In production behind an enterprise TLS terminating reverse proxy (e.g., Cloudflare,
  AWS ALB, NGINX), `TRUST_PROXY_HEADERS` may be set to `true` **only if** the proxy
  is configured to strip and overwrite all incoming client `X-Forwarded-*` headers.

---

## 4. Privacy, data retention, and regulatory compliance

- **Notice versioning**: Every form submission captures the exact version of the privacy
  notice agreed to (`privacy_version`) along with a timestamp (`consent_at`).
- **Data minimization**: Forms explicitly instruct visitors not to send bank accounts,
  government identifiers, or medical history.
- **Verification boundary**:
  - Advisor credentials, designations, and corporate affiliations remain hidden from the
    public site until explicitly verified by a Super Administrator.
  - Seeded products remain flagged as unverified; detailed summaries and links are
    suppressed publicly until official AXA materials are reviewed.
  - vCard generation (`/contact.vcf`) strictly omits `TITLE` and `ORG` attributes while
    unverified.

---

## 5. Operations, backup, and incident response

### 5.1 Backup & Restore
- Perform scheduled logical database backups using `mysqldump`:
  ```bash
  mysqldump -h 127.0.0.1 -u axa_kenneth_migrator -p --single-transaction --routines --triggers axa_kenneth_db > backup.sql
  ```
- Store database dumps and `storage/uploads/` backups in encrypted, access-controlled
  off-site locations. Never place backups within `public/` or the repository.

### 5.2 Deployment Security Checklist
1. Verify Apache `DocumentRoot` points to `<project_root>/public`, **never** project root.
2. Ensure `.env` has permissions `0600` (readable only by web server/PHP user).
3. Ensure `storage/` directory has write permissions only for the PHP runtime.
4. Verify HTTPS certificate is active and configure `APP_URL=https://...`.
5. Set `TRUST_PROXY_HEADERS=true` only if verified behind a trusted TLS proxy.
6. Verify database accounts are segregated (`axa_kenneth_app` vs `axa_kenneth_migrator`).
7. Confirm initial administrator creation is done via CLI (`php scripts/create-admin.php`).
8. Ensure no test accounts or default credentials exist.

### 5.3 Incident Response Guidelines
1. **Compromised Administrator Credential**:
   - Immediately sign in as another Super Administrator and reset the compromised account's
     password or deactivate it. This increments `session_version` and kills all active
     sessions instantly.
2. **Suspected Data Leak or Abuse**:
   - Check `storage/logs/app-error.log` and `audit_logs` table for anomalous actions.
   - Temporarily increase rate limiting thresholds in `Config.php` or block offending
     IP ranges at the firewall / web server level.
3. **Application Vulnerability**:
   - Follow rollback procedure documented in `docs/rollback.md` to restore the last known
     good release without losing customer inquiry records.
