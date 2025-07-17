# 🖼️ PixlKey

_PixlKey_ is a secure PHP-based platform for digital artists and content creators to upload, watermark, license, and register their digital artwork. The system ensures verifiable ownership, metadata preservation, and flexible licensing—tying these not just to the artwork file, but to its unique cryptographic fingerprint (SHA-256 hash).

## 🎯 Project Goals

The core goal of PixlKey is to create a **searchable, decentralized registry of digital images, ownership rights, and licensing**, anchored by each image's **cryptographic fingerprint** rather than just file content or metadata. This enables:

- Verifiable proof of authorship and modification history.
- Immutable links between artwork, metadata, and license.
- A decentralized-friendly, off-chain registry system for provenance.
- Rights management that is resilient across formats, platforms, and duplicates.

## 📜 Changelog

### [0.4.9-beta] – 2025-07-17
### Critical Security Task – Rate Limiting for Auth & Downloads
- **New module** `rate_limiter.php` introduces `too_many_attempts()`, `record_failed_attempt()`, and `rate_limit_exceeded_response()`—sending RFC-compliant **429 Too Many Requests** with `Retry-After`.
- **Centralised thresholds** moved to `config.php` (`LOGIN_ATTEMPT_LIMIT`, `DOWNLOAD_ATTEMPT_LIMIT`, `REGISTER_ATTEMPT_LIMIT`, etc.) and are override-able via environment variables. A master switch `RATE_LIMITING_ENABLED` allows runtime-wide disablement for load-testing.
- **Authentication flows** (`/auth.php`, `/login.php`, `/register.php`) now share the same IP-bucket (`login_<IP>`) with 5 attempts/15 min default; counters reset on successful login.
- **Download & processing endpoints** (`/download_zip.php`, `/process.php`) throttle ZIP access/build (10 requests/min per IP + user + runId) and respond with `429` on excess, protecting bandwidth and CPU.
- **Account actions** (`/my_watermarks.php`, `/my_licenses.php`, `/index.php`) adopt conservative 10 actions/min limits to curb spam and abusive form submissions.
- **Optional audit log**: commented `error_log()` helper can output to `/logs/rate_limiter.log` for forensics without polluting stdout.

> Provides defence-in-depth against brute-force attacks and bandwidth abuse, while keeping limits configurable for future Redis/IP-hash back-ends.

### [0.4.8-beta] – 2025-07-16
### Critical Security Check – Password Hash Verification
- `/auth.php`: Added `authenticate_user()` function using `password_verify()` for login and `password_needs_rehash()` to upgrade legacy hashes to `PASSWORD_DEFAULT` (bcrypt or Argon2id).
- `/login.php`: Password validation now includes hash upgrade on login using `password_needs_rehash()` with `password_hash()` if algorithm or cost changes are detected.

> Ensures all user authentication uses modern hashing algorithms with rehashing support, eliminating legacy or insecure password validation paths.

### [0.4.7-beta] – 2025-07-14
### Transport Security Enforcement
- `/config.php`: Enforces TLS-only access for all web traffic (403 if accessed via plain HTTP or misconfigured proxy).
- `/auth.php`: Forces `session.cookie_secure`, `HttpOnly`, and `SameSite=Strict` via `ini_set()` before `session_start()`.
- Global security headers emitted:
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`

### [0.4.6-beta] – 2025-07-14
### CSRF Token Rotation (Privilege Boundary Hardening)
- `/auth.php`: Now regenerates CSRF token immediately after successful login via `login_user()`.
- `/logout.php`: Starts a fresh session and issues a new CSRF token after teardown.
- `/register.php`: Rotates CSRF token after account creation before auto-login.
- `/store_data.php`: Defensively generates a new CSRF token after session ID regeneration.

> Prevents CSRF token reuse across authentication boundaries or session transitions.

### [0.4.5-beta] – 2025-07-12
### UI Branding & Version Centralization
- Introduced dynamic branding via random tagline selection:
  - `config.php` now defines `APP_TITLE` and `APP_HEADER` with rotating suffixes (e.g., "Proof of Vision", "Own Your Image").
  - All HTML templates referencing titles or headers now pull from `APP_TITLE` / `APP_HEADER`.
- Version string (`APP_VERSION`) is now defined once in `config.php` (`0.4.5-beta`) and reflected site-wide.
- Updated `index.php` to use the new constants in the `<title>` tag and `<h1>` header dynamically.

### [0.4.4-beta] – 2025-07-12
### Session Security Patch
- Regenerated session ID immediately after **successful login**, **registration**, and **logout**:
  - `auth.php`: added `session_regenerate_id(true)` inside `login_user()`.
  - `register.php`: explicitly regenerates session ID before `login_user()` to mitigate fixation from pre-auth context.
  - `logout.php`: new session is now properly started and ID regenerated **after** teardown.
  - `store_data.php`: added redundant session regeneration as a defense-in-depth checkpoint before ingesting data tied to user identity.
- These changes harden against **session fixation** and **session swap** attacks across all authentication boundaries.

### [0.4.3-beta] – 2025-07-11
### Security
- 🛡️ Rate limiting added to login and registration forms:
  - `/login.php`: blocks IP after 5 failed attempts within 15 minutes.
  - `/register.php`: throttles new user signups to 5 attempts per 30 minutes.
  - Prevents brute-force login and automated account creation attacks.
- Introduced new helper: `rate_limiter.php` using session-based attempt tracking.

### [0.4.2-beta] – 2025-07-11
### Fixed
- Thumbnail grid width now respects layout limits and renders properly in both public and member views.
- Broken image icon no longer shown in preview boxes when no file is selected.

### Added
- Placeholder preview frames for watermark/image preview.
- Header text restyled with Orbitron font and drop-shadowed PixlKey logo.

No changes to database, API, or core logic—this is a visual/UI refinement patch.

### [0.4.1-beta] – 2025-07-11
- Security patch: `download_zip.php` now enforces user ownership of ZIP downloads.
- `process.php` now inserts `runId` entries into `processing_runs` to support ownership checks.
- `store_data.php` now verifies that the given `runId` belongs to the logged-in user.
- Sanitized and validated `runId` input with clear failure responses.
- Returns `403 Forbidden` for unauthorized access attempts in both download and store logic.

### [main reset] – 2025-07-10
- Repository was reverted to `0.3.0-alpha` as the new `main` branch.
- All other branches (beta or experimental) were removed to simplify development and refocus on a stable base.
- This commit represents the official project baseline going forward.

### [0.3.0-alpha] – 2024-06-29
- Initial alpha release and proof-of-concept build completed.
- Functional upload → watermark → package flow working with database integration.

## What It Does

- Upload high-resolution artwork (PNG, JPG, WebP up to 10MB).
- Apply dynamic or default watermarking via user controls.
- Embed metadata and author statements using ExifTool.
- Generate markdown certificates of authenticity.
- Package processed image, thumbnail, metadata, and cert into a ZIP.
- Persist image fingerprint, metadata, license ID, and ownership to a relational database.
- Allow users to manage watermark and license templates.
- Extract and publish signed metadata reports from processed files.
- Adaptive rate limiting across authentication, downloads, uploads, watermark and licence actions (configurable via `config.php` / `.env`).


Authentication & Security Enhancements **(updated in 0.4.9-beta)**:
- Passwords are now securely verified using `password_verify()` and hashed with `password_hash()` using `PASSWORD_DEFAULT` (Argon2id or bcrypt).
- Legacy password hashes are automatically upgraded on login using `password_needs_rehash()`.
- Removed any legacy or plaintext password validation logic.
- Rate limiting now extends beyond login/registration to cover `/download_zip.php`, watermark uploads, licence management, ZIP processing and index-page uploads, with a global toggle (`RATE_LIMITING_ENABLED`) and per-endpoint thresholds.

Visual/UX enhancements (from 0.4.2-beta):
- Centered thumbnail grid (5-across) in public and member views.
- Preview frames for watermark and image uploads.
- Branding polish with new header font and clearer logo visibility.

## Roadmap (Top Priorities)

### Security & Session Hardening
1. **Regenerate session ID on login** to mitigate fixation attacks. *(Implemented in `auth.php` and `logout.php`)*
2. **Strict `runId` sanitization and ownership checks** in download & store logic. ✅ *(Enforced in 0.4.1-beta)*
3. **Rate limiting and brute-force protection** across authentication, downloads, uploads and critical POST actions. ✅ *(Expanded in 0.4.9-beta via deeper `rate_limiter.php` integrations)*
4. **CSRF failure, login, and download event logging** for audit and security.
5. **Enforce secure password hashing/verification** using `password_hash()` and `password_verify()` with `PASSWORD_DEFAULT`. ✅ *(Patched in 0.4.8-beta with rehash fallback support)*


### Configuration & Validation
5. **Validate required environment variables** (`DB_PASS`, `DB_NAME`, etc.) at runtime.
6. **MIME-type validation** for uploads (`mime_content_type`) in addition to file extension checks.
7. **Restrict watermark and upload directories** to correct permissions (`0700`, `0750`).
8. **Centralize config values** like `$allowedExtensions`, watermark paths, max file size, **and rate-limiting thresholds** into `.env` or `config.php`. ✅

### Maintainability & Modularity
9. **Refactor monolithic `store_data.php`** into modular handlers per table (e.g., `Artworks`, `Images`, `Submissions`).
10. **Replace silent errors (`@unlink`, `die()`)** with structured logging and exception handling.

---

## Tech Stack

- **Backend**: PHP 8+, ImageMagick, ExifTool, MySQL/MariaDB
- **Frontend**: Vanilla JS, dynamic HTML form generation, live processing feedback
- **Security**: Session hardening, CSRF protection, XSS filtering, SQL injection prevention
- **Persistence**: UUID-based relational schema with SHA-256 image fingerprinting
- **Optional**: `.env` configuration via `php-dotenv`; **Redis** (future) for persistent rate-limiting buckets

## 🔐 Security Enhancements

- Session ID regeneration on login and logout to prevent fixation.
- Registration flow now also regenerates session ID post-account creation.
- `store_data.php` enforces redundant session ID regeneration before processing.
- Secure cookie flags: `HttpOnly`, `Secure`, `SameSite=Strict`.
- CSRF token protection on all forms.
- Passwords securely hashed using `password_hash()` with `PASSWORD_DEFAULT` (Argon2id or bcrypt).
- Passwords verified using `password_verify()` across all authentication flows.
- Automatic hash upgrades on login using `password_needs_rehash()` to future-proof security.

- Adaptive rate limiting enforced across authentication, downloads, watermark uploads, licence actions and ZIP processing, driven by `rate_limiter.php`.
  - Protects against brute-force, scraping and bandwidth abuse.
  - Central thresholds (`LOGIN_ATTEMPT_LIMIT`, `DOWNLOAD_ATTEMPT_LIMIT`, etc.) overrideable via environment variables.
  - Optional global toggle (`RATE_LIMITING_ENABLED`) plus graceful **429** responses with `Retry-After`.

- HTTPS is now **strictly required** for all web access:
  - Non-TLS requests are rejected with `403 Forbidden`.
  - Application emits browser-hardening headers globally via `config.php`.

## Status

- Core upload, watermark, and metadata functions complete.
- Testing phase: security, concurrency, and error handling enhancements in progress.
- Stable builds pending rollout after roadmap completion.

---

## License

MIT License — see `LICENSE.md` for details.

---

## Contact

For issues, contributions, or inquiries, contact [jweese74@gmail.com](https://pixlkey.net) or open an issue in this repository.

## 🤝 Contributing

This project is the product of passion, experimentation, and late-night coffee—not professional software engineering. If you're a developer with experience in PHP, image processing, security, or UI/UX and want to contribute, **your help is welcome**.

### Ways You Can Help

- Audit or improve security (sessions, uploads, input validation).
- Refactor monolithic logic into modular components.
- Help build a REST API for interoperability.
- Enhance frontend UI/UX or make it mobile-friendly.
- Add unit or integration tests.
- Improve accessibility or error handling.

Whether you're seasoned or still learning, your input could help transform PixlKey into something lasting and resilient.

> 🛠 “I’ve fumbled it this far—now I’m calling in the pros.”  
> — _Jeff Weese, Project Maintainer_

### How to Get Started

1. Fork the repo
2. Check open issues or roadmap items
3. Submit a pull request with a short description
4. Or just open a discussion—we’re friendly!
