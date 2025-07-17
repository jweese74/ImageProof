# 📓 CHANGELOG

All notable changes to **PixlKey** will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/)  
and follows a simplified [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format.

---

## [0.4.9-beta] – 2025-07-17
### 🔒 Critical Security Enhancements
- 🚦 **Global rate limiting** introduced to harden authentication and asset-download endpoints:  
  - New `rate_limiter.php` middleware with sane defaults (⏱ 5 login attempts / 15 min, 10 downloads / min).  
  - Integrated into `/auth.php`, `/login.php`, `/register.php`, `/download_zip.php`, `/process.php`, `/my_watermarks.php`, `/my_licenses.php`, and the member landing page.  
  - Exceeds return `429 Too Many Requests` plus `Retry-After` header for graceful client back-off.  
  - Thresholds and master toggle (`RATE_LIMITING_ENABLED`) centralised in **config.php** and fully overridable via `.env`.  
  - Buckets reset on successful actions; optional file logger provides groundwork for future audit trails.

### ⚙️ Refactors & Misc
- Harmonised bucket-naming scheme so UI and API share counters (`login_`, `register:`, `wm:`, etc.).  
- Deferred `require_once 'rate_limiter.php'` until after session initialisation to avoid header-sent warnings.  

> This release mitigates brute-force credential stuffing and download scraping while remaining lightweight and easily extensible (e.g., Redis persistence in future).

---

## [0.4.8-beta] – 2025-07-16
### 🔒 Security Enhancements
- 🛡️ Enforced **modern password hashing** standards across all authentication flows:
  - Replaced legacy hash checks with `password_verify()` using `PASSWORD_DEFAULT` (currently `Argon2id`).
  - All new hashes are generated via `password_hash()`, ensuring strong, upgradable hashing.
  - Integrated `password_needs_rehash()` during login and auth to auto-upgrade old hashes silently.
  - Unified logic added to `/auth.php` and `/login.php` to verify, rehash, and persist secure passwords.

> This patch eliminates insecure or deprecated password handling, enabling seamless hash upgrades and future-proofing account authentication.

---

## [0.4.7-beta] – 2025-07-14
### 🔒 Security Enhancements
- 🧷 Enforced **Transport Layer Security** across all app entry points:
  - `/config.php` now blocks all non-TLS (plain HTTP) requests, except CLI scripts.
  - Sends strict security headers (`Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`) on all page loads.
  - All routes explicitly `require_once 'config.php'` early to guarantee enforcement.
- 🔐 Hardened **cookie scope** and session flags:
  - `session.cookie_secure`, `session.cookie_httponly`, and `session.cookie_samesite=Strict` are now set globally via `ini_set()` in `auth.php`.

> This patch ensures all traffic is end-to-end encrypted and protected against downgrade, clickjacking, and MIME-based injection attacks.

---

## [0.4.6-beta] – 2025-07-14
### 🔐 Security Enhancements
- 🔄 Added **CSRF token rotation** at key session privilege transitions to prevent token reuse:
  - `/auth.php`: `login_user()` now generates a fresh CSRF token immediately after login.
  - `/logout.php`: new session is created after logout and CSRF token rotated on session restart.
  - `/register.php`: rotates token post-registration, before calling `login_user()`.
  - `/store_data.php`: defensively regenerates token after `session_regenerate_id(true)` to guard sensitive POST processing.

> This patch prevents CSRF token replay across authentication transitions, enhancing session isolation and hardening against privilege escalation.

---

## [0.4.5-beta] – 2025-07-12
### 🔧 Internal Improvements
- 🎲 Introduced dynamic branding support in `config.php`:
  - Added `APP_TITLE` and `APP_HEADER` as randomized constants chosen per page load from a curated tagline pool.
  - Example variants include:
    - “PixlKey – Own Your Image”
    - “PixlKey – Signature by Light”
    - “PixlKey – Image. Identity. Immutable.”
  - Enables flexible UX tone and clearer identity without manually updating each file.
- 🧼 Updated `index.php` to consume `APP_TITLE` and `APP_HEADER` in HTML `<title>` and `<h1>`, replacing hardcoded version and text.

> This non-functional patch improves maintainability and user-facing polish without affecting any business logic or security flow.

---

## [0.4.4-beta] – 2025-07-12
### 🔒 Security Enhancements
- ✅ Hardened **session fixation protection** across login, logout, registration, and data ingestion:
  - `/auth.php`: `session_regenerate_id(true)` is now called inside `login_user()` immediately after successful login.
  - `/register.php`: Session ID is regenerated **before** calling `login_user()` to prevent reuse of pre-auth session.
  - `/logout.php`: Reordered logic to regenerate session **after** cookie expiry and session teardown, avoiding residual ID reuse.
  - `/store_data.php`: Defensive session regeneration added to reinforce session integrity before persisting sensitive records.
- Ensures full compliance with OWASP guidance on session lifecycle control and eliminates potential fixation or swap vectors.

> This patch strengthens authentication boundaries and session integrity, especially in shared browser environments.

---

## [0.4.3-beta] – 2025-07-11
### 🔒 Security Enhancements
- Implemented **rate limiting** on login and registration endpoints:
  - `/login.php`: Limits to 5 failed attempts per 15 minutes per IP.
  - `/register.php`: Limits to 5 attempts per 30 minutes per IP.
  - Helps mitigate brute-force login attacks and account creation abuse.
- Introduced `rate_limiter.php` utility:
  - Session-based tracking of attempt timestamps.
  - Includes `too_many_attempts()`, `record_failed_attempt()`, and `clear_failed_attempts()` functions.

---

## [0.4.2-beta] – 2025-07-11
### Added
- 👁️ Placeholder frames for **Watermark preview** and **Image preview** to avoid broken image icons before file selection.
- 🎨 Drop-shadow on logo and new Orbitron-styled `h1` title for improved branding visibility.

### Fixed
- 🖼️ Public gallery thumbnails now render in a 5×2 grid layout, matching member view.
- 📐 Thumbnail gallery width capped at 75% viewport for cleaner layout.
- 🧹 Removed stray `else:` token that disrupted thumbnail rendering logic.

> Patch release includes UI cleanup and visual bug fixes; no changes to backend or database logic.

---

## [0.4.1-beta] – 2025-07-11
### Changed
- 🔒 Enforced ownership verification in `download_zip.php`:
  - Added SQL-based check to confirm the `runId` belongs to the authenticated user.
  - Prevents unauthorized users from accessing ZIP archives they don’t own.
  - Returns `403 Forbidden` on failed access attempt.
  - Processing step now inserts each `runId` into `processing_runs` with `user_id`, enabling secure lookup.
- 🔒 Enforced ownership verification in `store_data.php`:
  - Validates that the provided `runId` belongs to the authenticated user before ingesting data.
  - Returns `403 Forbidden` if ownership check fails.
  - Protects against unauthorized database writes and metadata exposure.
- 🎯 Input validation hardened:
  - `runId` is sanitized and empty values are explicitly rejected.

 ---
 
 ## [0.4.0-beta] – 2025-07-10
 ### Added
 - Roadmap reset and preparation for 0.4.x beta stream.
 - Reinforced `auth.php` to regenerate session ID on login/logout.
 - Centralized CSRF helpers.
 - Core file audit and agent refactoring begun.

---

## [0.3.0-alpha] – 2024-06-29
### Added
- Initial working **Alpha release** of PixlKey.
- Core features implemented:
  - Image upload (PNG, JPG, WebP up to 200MB).
  - Dynamic or default watermarking with position control.
  - ExifTool metadata embedding (IPTC/XMP).
  - SHA-256 fingerprinting.
  - ZIP packaging of processed asset bundles.
  - Markdown certificate generation.
  - User management of license and watermark templates.
  - Basic image gallery and authentication interface.
  - Database schema for users, images, watermarks, licenses, and processing runs.

### Known Issues
- No rate limiting or brute-force protection yet.
- Some error handling is still silent (`die()`, `@unlink()`).
- No API or mobile interface.
- Lacks unit testing.

---

## [main reset] – 2025-07-10
### Changed
- ⚠️ **Repository reverted to `0.3.0-alpha`** to recover a known stable version.
- Removed all other branches (`0.5.0-beta`, `fix/`, `experimental`) to declutter and refocus.
- Set `main` as the new default GitHub branch pointing at `0.3.0-alpha`.

### Rationale
- The Alpha version is functional and testable.
- Other branches introduced instability and deviated from core goals.
- This reversion simplifies community contributions and roadmap clarity.

---

## [Unreleased]
### Planned
- Session and CSRF security enhancements (partial already implemented).
- Modularization of `store_data.php` and `process.php`.
- REST API support.
- Download authentication.
- Audit logs and IP logging.
- Docker and deployment scripts.