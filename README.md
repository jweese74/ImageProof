# 🖼️ PixlKey

_PixlKey_ is a secure PHP-based platform for digital artists and content creators to upload, watermark, license, and register their digital artwork. The system ensures verifiable ownership, metadata preservation, and flexible licensing—tying these not just to the artwork file, but to its unique cryptographic fingerprint (SHA-256 hash).

## 🎯 Project Goals

## 🎯 Project Goals — Implementation Snapshot

PixlKey’s purpose is to build a **searchable, decentralised registry of digital images, ownership rights, and licences**, anchored by each image’s **SHA-256 cryptographic fingerprint**.

That foundation unlocks four concrete capabilities:

| Goal                                                                   | How We Deliver It                                                                                                                                                                                                                                                                |                                            Status                                            |
| ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------------------: |
| **Verifiable proof of authorship & modification history**              | • Dual SHA-256 hashes captured *before* and *after* processing.<br>• Markdown **Certificate of Authenticity** records hash, UUID, timestamp, user ID.<br>• Submission log tracks IP, user-agent, and run time for every job.                                                     |                                          ✅ **Done**                                          |
| **Immutable links between artwork, metadata & licence**                | • UUID + licence text embedded directly into IPTC/XMP of final PNG **and** persisted in the database.<br>• Join-tables (creators, genres, keywords) bind all related entities atomically.<br>• Certificate mirrors the same UUID / licence for double-entry assurance.           |                                          ✅ **Done**                                          |
| **Decentralised-friendly, off-chain provenance registry**              | • Every asset is identified by its content-addressable SHA-256 digest.<br>• Database schema already contains `blockchain_tx` placeholder for future anchoring.<br>• Certificates export cleanly for IPFS pinning or on-chain notarisation.                                       |        🚧 **In Progress** — external anchoring & signature workflow slated for 0.6.0-Beta        |
| **Rights management resilient across formats, platforms & duplicates** | • All derivatives (thumb, preview, full) are normalised to PNG and carry the same watermark + embedded licence.<br>• Default-licence selector prevents conflicting terms across uploads.<br>• Duplicate-email and per-user watermark folders guard against ownership collisions. | 🚧 **In Progress** — perceptual-hash duplicate detection & bulk re-licensing toolkit planned |

> **Next Milestones**
> • Implement search/query API for public discovery.
> • Integrate IPFS + signed JSON manifest (or similar) for decentralised anchoring.
> • Add pHash duplicate detection and version-history table for airtight provenance.


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

## What It Does – Beta 0.5.0 snapshot

* **Accept high-resolution uploads** (PNG, JPG, WebP ≤ 200 MB; size ceiling read from `.env`/`config.php`).
* **Normalise to a canonical PNG**, strip legacy EXIF, then compute a pre-process **SHA-256 fingerprint**.
* **Apply default or on-the-fly watermarking**

  * bitmap overlay (bottom-right, auto-scaled) **plus** five oblique text marks for tamper deterrence.
* **Embed rights & provenance** with ExifTool – writes UUID, licence text, keywords, creators into IPTC/XMP.
* **Hash again post-embed**, write both digests to DB, and include them in a Markdown *Certificate of Authenticity*.
* **Generate derivative assets** – 400 px thumbnail and 800 px preview, both watermarked.
* **Package everything** (final PNG, thumbnail, preview, metadata dump, certificate) into a lean ZIP and stream it to the browser.
* **Persist complete registry row**: artwork UUID, both hashes, licence-ID, user-ID, timestamps, plus placeholders for future on-chain Tx.
* **User dashboards** to upload / delete watermarks and to create, edit, or set-default licences (Markdown rendered safely).
* **One-click metadata report extraction** – produces a signed\* Markdown digest from any processed file (*signature workflow in progress*).
* **Adaptive rate limiting** guards log-in, registration, uploads, watermark & licence actions, ZIP generation, and downloads – all tunable via ENV constants.

### Authentication & Security Enhancements **(0.4.9 → 0.5.0)**

* Passwords verified with `password_verify()`; stored with `password_hash()` using **`PASSWORD_DEFAULT` (Argon2id/bcrypt)**.
* Old hashes silently upgraded on login via `password_needs_rehash()`.
* Session fixation defences: ID regeneration on login, registration, and logout, with simultaneous CSRF-token rotation.
* **Global Secure/HttpOnly/SameSite=Strict** cookies; mandatory HTTPS guard.
* Expanded **rate-limiting** (IP + user scope) now covers *all* sensitive endpoints, emits `429 Retry-After`.

### Visual / UX Enhancements **(0.4.2 → 0.5.0)**

* Dark-theme, responsive **login & registration** pages with inline error messaging.
* Centred thumbnail grid (5-across) in public/member galleries; live preview frames for uploads.
* Real-time, colour-coded **progress stream** during processing; glow-button download on completion.
* Subtle branding refresh – new header font, clearer logo, rotating poetic taglines for each request.


## Roadmap to 0.6.0-beta

| Priority | Area                                | Action Item                                               | Notes / Acceptance Test                                                                                                                                                                      |
| -------: | ----------------------------------- | --------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|    **1** | **Decentralised Provenance**        | **External anchor & signature workflow**                  | • Push artwork‐hash + certificate JSON to IPFS *and* store returned CID in `blockchain_tx` column.<br>• Sign certificate with project signing key (PGP or Ed25519) and publish detached sig. |
|    **2** | **Discovery & Searchability**       | **Public search API + UI**                                | • `/api/artworks` endpoint: query by SHA-256, UUID, keyword.<br>• Lightweight Vue/HTMX page for human search.<br>• Rate-limited, paginated JSON responses.                                   |
|    **3** | **Rights Management / Duplication** | **Perceptual-hash (pHash) duplicate detection**           | • Generate 64-bit pHash during ingestion.<br>• Flag visually identical but re-encoded uploads; prompt user to link or reject.                                                                |
|    **4** | **Provenance Audit Trail**          | **Append-only versions table**                            | • `artwork_versions` (`artwork_id`, `hash`, `created_at`, `modifier_id`).<br>• Automatically insert new row on every re-process.                                                             |
|    **5** | **Rights Management / Maintenance** | **Bulk re-licence updater**                               | • CLI script to re-embed updated licence text across historical uploads and regenerate certificates.                                                                                         |
|    **6** | **Security & Session Hardening**    | **Event logging for CSRF failures, logins, downloads**    | • Write to `security_events` table and `error_log`; include IP, UA, user\_id, outcome.                                                                                                       |
|    **7** | **Configuration & Validation**      | **Runtime ENV validation**                                | • Abort startup if `DB_PASS`, `DB_NAME`, `JWT_SECRET`, etc. are unset.                                                                                                                       |
|    **8** | **Configuration & Validation**      | **MIME-type validation on upload**                        | • Reject files where `mime_content_type()` ≠ declared extension.                                                                                                                             |
|    **9** | **Configuration & Validation**      | **Tighten directory permissions**                         | • Set watermark/upload dirs to `0750` (`0700` if private storage).                                                                                                                           |
|   **10** | **Maintainability & Modularity**    | **Refactor `store_data.php` into table-centric handlers** | • Separate classes: `ArtworksDAO`, `ImagesDAO`, `SubmissionsDAO`, wrapped in single transaction manager.                                                                                     |
|   **11** | **Maintainability & Modularity**    | **Replace silent failures with structured exceptions**    | • Swap `@unlink`, `die()` etc. for try/catch + PSR-3 logger.                                                                                                                                 |

---

## Tech Stack

- **Backend**: PHP 8+, ImageMagick, ExifTool, MySQL/MariaDB
- **Frontend**: Vanilla JS, dynamic HTML form generation, live processing feedback
- **Security**: Session hardening, CSRF protection, XSS filtering, SQL injection prevention
- **Persistence**: UUID-based relational schema with SHA-256 image fingerprinting
- **Optional**: `.env` configuration via `php-dotenv`; **Redis** (future) for persistent rate-limiting buckets

## 🔐 Security Enhancements — Beta 0.5 recap

### Session & Cookies

* Session ID regenerated on **login**, **registration**, **logout**, and again before long-running jobs (`store_data.php`) to foil fixation/replay.
* All session cookies now ship with `Secure`, `HttpOnly`, `SameSite=Strict`.

### Cross-Site Request Forgery (CSRF)

* 32-byte cryptographically random token created once per session.
* Token embedded in every POST form *and* accepted via `X-CSRFTOKEN` header for AJAX/API calls.
* Token automatically rotated after each authentication event and on logout.

### Password Security

* Passwords hashed with `password_hash()` using `PASSWORD_DEFAULT` (Argon2id/bcrypt, depending on PHP build).
* Automatic re-hashing on login via `password_needs_rehash()` keeps algorithms current.
* `password_verify()` with constant-time comparison mitigates timing attacks.

### Adaptive Rate Limiting (`rate_limiter.php`)

| Protected action | Default limit | Decay window |
| ---------------- | ------------- | ------------ |
| Login            | `5` attempts  | `15 min`     |
| Registration     | `3` attempts  | `30 min`     |
| Downloads        | `10` files    | `10 min`     |
| Watermarks       | `10` actions  | `1 min`      |
| Licence edits    | `10` actions  | `1 min`      |
| ZIP packaging    | `3` jobs      | `10 min`     |

* All thresholds overrideable via environment variables (`*_ATTEMPT_LIMIT`, `*_DECAY_SECONDS`).
* Global toggle `RATE_LIMITING_ENABLED`.
* Graceful **429** responses include `Retry-After` for client back-off.
* Optional audit logging to `error_log` for forensic review.

### Transport & Header Hardening

* **HTTPS is mandatory**; plain-HTTP requests return **403 Forbidden**.
* Global headers:

  * `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload`
  * `X-Content-Type-Options: nosniff`

### Input Validation & Sanitisation

* `htmlspecialchars()`, `addslashes()`, and `escapeshellarg()` applied wherever user data touches HTML, SQL, or the shell.
* Upload guard: image files only, ≤ **200 MB** → otherwise **413 Payload Too Large**.
* IDs and paths validated/sanitised to block traversal and injection.

### Filesystem & Process Isolation

* Each processing run lives in a user-scoped, time-stamped directory; internal paths are masked in UI/logs.
* Temporary files auto-purged post-process; periodic cron cleans aged processed folders.

### Provenance & Integrity

* Pre- and post-process **SHA-256** digests stored with artwork to detect tampering.
* Database includes placeholder column for future on-chain (blockchain) anchoring.

## Status — Beta 0.5.0 snapshot

| Area                   | Current state                                                                                                                    | Next up                                                                     |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| **Core pipeline**      | Upload → watermark → hash → metadata embed/extract → certificate generation **fully operational** and passing integration tests. | Performance profiling on large (250 MB) batches.                            |
| **Security hardening** | Session-regeneration, strict cookies, CSRF rotation, adaptive rate-limiting and mandatory HTTPS *all merged to `main`*.          | Finish automated penetration test suite (OWASP ZAP & custom scripts).       |
| **Dashboards**         | User-facing Licence and Watermark CRUD UIs live; dark-mode auth screens polished.                                                | Add role-based admin view for system-wide metrics.                          |
| **CLI tooling**        | Metadata-extractor and batch-processing flags stable on Linux/macOS.                                                             | Windows PowerShell wrapper & man-page generation.                           |
| **Database layer**     | Strict PDO with transactions, UUID ids, and provenance tables frozen—no breaking migrations planned.                             | Lightweight read-replica strategy for search scaling.                       |
| **Docs & Ops**         | README security section updated; `.env.example` and sample Nginx config published.                                               | Dockerfile, CI pipeline, and automated cron-cleanup script.                 |
| **Provenance R\&D**    | SHA-256 digests stored; DB column reserved for future blockchain anchor.                                                         | Evaluate Arweave vs. Polygon for on-chain proof.                            |
| **Release track**      | **Feature-freeze** declared for Beta 0.5.x; only bug-fixes and test coverage improvements accepted.                              | Beta 0.6: accessibility audit, localisation scaffold, public API endpoints. |

**Bottom line:** core functionality is feature-complete and secure; we’re in a stabilisation sprint aimed at a public beta roll-out once automated tests, ops scripts, and performance benchmarks pass.

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
