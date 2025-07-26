# Agents Reference

A consistently structured overview of the key scripts (“agents”) that make up the **PixlKey** legacy PHP stack and related utilities.  
Each entry follows the same heading order for clarity:

### File Location
### File Name
1. **Purpose** – What the file does and why it exists.  
2. **Agent Role** – How it behaves in the overall system.  
3. **Key Responsibilities** – Main behaviours / execution flow.  
4. **Security Considerations** – Relevant security notes.  
5. **Dependencies** – Direct runtime requirements.  
6. **Additional Notes** – Any extra usage or deployment guidance.

-----

`/core/auth/auth.php`

1. **Purpose**
   Provides robust authentication, session security, and CSRF protection for the PixlKey platform. Handles login state, user lookups, brute-force login rate-limiting, and session lifecycle control.

   As of `v0.5.1.1-alpha`, **session bootstrap logic has been modularised** into a dedicated helper (`SessionBootstrap.php`), centralising cookie-flag configuration and secure startup behaviour.

   Key integrated features:

   * **Centralised secure session bootstrap** (moved to `PixlKey\Session\SessionBootstrap::startSecureSession()`).
   * **Rate-limited login attempts** to prevent brute-force attacks.
   * **CSRF token generation and verification** for all non-GET requests.
   * **Modern password hashing with rehash fallback** for ongoing cryptographic strength.

2. **Agent Role**
   Core **Security & Session Agent**. Offers system-wide login enforcement, user session tracking, form security, and brute-force prevention using shared rate-limiter utilities.

3. **Key Responsibilities**

   * Initialise sessions securely via `PixlKey\Session\SessionBootstrap::startSecureSession()` (centralised).
   * Generate and validate CSRF tokens, skipping `GET` requests by default.
   * Authenticate users using `password_verify()` and upgrade hashes if `password_needs_rehash()` applies.
   * Enforce login attempt throttling using shared `rate_limiter.php`:

     * `too_many_attempts()` and `rate_limit_exceeded_response()` enforce per-IP ceilings.
   * Rotate session ID and CSRF token after successful login (`login_user()`).
   * Expose `require_login()` to protect authenticated routes.
   * Provide `current_user()` as a cached accessor for the session-bound user row.

4. **Security Considerations**

   * **CSRF** – Token required for all non-GET requests; rotated on login and logout.
   * **Session Fixation** – Mitigated by calling `session_regenerate_id(true)` on login.
   * **Rate Limiting** – Login attempts are throttled per IP; emits `429 Too Many Requests`.
   * **Password Security** – Uses `password_verify()`; rehashes old hashes with `PASSWORD_DEFAULT`.
   * **Secure Cookies** – All session cookies are `Secure`, `HttpOnly`, and `SameSite=Strict`, now enforced centrally by `SessionBootstrap`.
   * **Transport Security** – TLS enforced upstream via `config.php`; critical for session integrity.
   * **Header Hardening** – Recommend centralising `X-Frame-Options: DENY` and `Content-Security-Policy`.

5. **Dependencies**

   * `config.php` – Establishes `$pdo`, sets global rate-limit constants.
   * `../session/SessionBootstrap.php` – Provides centralised session startup.
   * `rate_limiter.php` – Provides login throttling logic and response helpers.
   * DB Table: `users` – Includes `user_id`, `email`, `password_hash`, `display_name`, `is_admin`, `last_login`.
   * PHP Extensions: `session`, `openssl` (for `random_bytes`)

6. **Additional Notes**

   * File is intentionally free of business logic; safe to mock/stub for unit testing.
   * For distributed deployments, consider externalising rate-limit counters (e.g. Redis).
   * `SameSite=Lax` may be required if third-party services are added in future.

7. **CHANGELOG**

   * **0.5.1.1-alpha** – Modularised session bootstrap: cookie-flag configuration and `session_start()` moved to `core/session/SessionBootstrap.php::startSecureSession()`. Ensures consistent session security across all entry scripts.
   * **0.5.0-beta** – Finalised session hardening, password rehash logic, and IP-based login rate limiting. `authenticate_user()` now cleanly separates credential checks.
   * Legacy changelog entries prior to `0.5.0` have been consolidated as security milestones.

-----

`/core/auth/rate_limiter.php`

1. **Purpose**
   Implements a lightweight, session-based rate-limiting layer to mitigate brute-force, abuse, and flooding across sensitive endpoints in PixlKey (e.g. login, registration, ZIP downloads, image uploads).

   * Pulls in global headers and config from `config.php`
   * Exposes helper functions for testing, recording, clearing, and responding to over-limit conditions
   * Supports overrideable thresholds via `.env` or `config.php` constants

2. **Agent Role**
   Acts as a **Security Utility Agent**, callable from any controller to monitor and enforce request limits based on session-tracked event history keyed by an arbitrary string (e.g. IP address, user ID, or composite token).

3. **Key Responsibilities**

   * **Threshold Evaluation**:
     `too_many_attempts($key, $max, $window)` returns true if the key has exceeded its quota in the given window.

   * **Rate Logging**:
     `record_failed_attempt($key)` stores current timestamp against a session key for limit tracking.

   * **Clearing**:
     `clear_failed_attempts($key)` resets a key's log—typically called after successful login or verification.

   * **Response Handling**:
     `rate_limit_exceeded_response($retryAfter)` issues a 429 HTTP status with a `Retry-After` header.

   * **Global Configuration**:
     Fallback defaults are set for login and download throttling, but callers must explicitly pass limits to helper functions.

4. **Security Considerations**

   * **Session Scope** – Rate tracking is ephemeral and user-session bound; long-term or multi-node persistence will require Redis or DB.
   * **Spoofable Keys** – Keys like IP addresses can be forged; for stronger enforcement, combine with user agent or logged-in user ID.
   * **Session Hygiene** – Ensure secure session parameters (`SameSite=Strict`, `cookie_httponly`, etc.) are enforced prior to use.
   * **Back-off Signalling** – Compliant clients should heed the `Retry-After` header; PixlKey's responses are minimal and fast.
   * **Layered Security** – Intended as application-level throttle only; pair with network-level controls (fail2ban, ModSecurity, CDN rate-limiting) for full-stack coverage.

5. **Dependencies**

   * Requires `session_start()` before use (typically handled by `auth.php`).
   * Loads `config.php` for headers and limit overrides.
   * Compatible with `.env` or static constant definition.
   * Does **not** require database access.
   * Used by: `login.php`, `register.php`, `download_zip.php`, `index.php`, or any script vulnerable to repeat abuse.

6. **Additional Notes**

   * Future improvements could include:

     * Persistent backends (e.g. Redis or SQLite)
     * Exponential back-off or CAPTCHA integration
     * Named "rate buckets" with global defaults per endpoint
     * Real-time lockout notifications or dashboard integration
   * Logging is stubbed but disabled by default (`error_log()` commented out).
   * Easy to integrate: simply pass a string key, max attempts, and a time window.

7. **CHANGELOG**

   * **0.5.0-beta** – Finalised helper interfaces and clarified usage; default thresholds now documented and overrideable. Included stubbed logging path and examples in comments.

   * **0.4.9-beta** – Added `rate_limit_exceeded_response()` and global toggle `RATE_LIMITING_ENABLED`; allowed .env overrides for common limit types.

   * **0.4.7-beta** – Required `config.php` for secure header enforcement.

-----

`/core/config/config.php`

1. **Purpose**
   Bootstraps the PixlKey runtime environment by securely loading environment variables, enforcing transport security, and establishing a hardened PDO connection to the MySQL/MariaDB database. It also defines constants for upload limits, branding metadata, and system-wide **rate-limiting thresholds** used across all modules.

2. **Agent Role**
   Acts as the **Configuration & Connection Agent**. It serves as the system’s trusted source for app metadata, environment secrets, database connectivity, and runtime policy enforcement.

3. **Key Responsibilities**

   * **Environment Bootstrap**

     * Loads optional `.env` via `vlucas/phpdotenv` if available.
     * Parses secrets into constants with fallback defaults.

   * **Security & Transport Enforcement**

     * Blocks all non-HTTPS requests unless from CLI or trusted proxy.
     * Emits `Strict-Transport-Security` and `X-Content-Type-Options` headers.

   * **Application Constants**

     * Defines `APP_NAME`, `APP_VERSION`, `APP_TITLE`, and `APP_HEADER` using a rotating tagline system.
     * Loads upload limits and debug flags (`DB_DEBUG`, `MAX_UPLOAD_MB`).

   * **Rate-Limiting Configuration**

     * Centralizes limits and decay durations for login, registration, and downloads.
     * Provides global toggle via `RATE_LIMITING_ENABLED`.

   * **Upload Size Controls**

     * Sets `upload_max_filesize` and `post_max_size` at runtime using `MAX_UPLOAD_MB`.

   * **PDO Setup**

     * Uses native prepared statements, exception mode, and associative fetches.
     * Logs errors server-side but suppresses them client-side unless `DB_DEBUG=true`.

4. **Security Considerations**

   * **HTTPS Enforcement** – Aborts insecure web requests with `403`; not bypassable via URL hacks.
   * **Header Hardening** – Enforces `HSTS` and `nosniff` on every request.
   * **Credential Scope** – Secrets must be owned and readable only by the server process (chmod `600`).
   * **Debug Mode** – `DB_DEBUG=true` reveals sensitive error messages—**never enable in production**.
   * **Secrets Management** – Suggest rotating secrets via volume mounts or secure stores.
   * **Rate-Limiting Safety** – Keep `RATE_LIMITING_ENABLED=true` in production to prevent brute-force or abuse.
   * **PDO Integrity** – All connection attributes are hardened by default, minimizing SQL injection surface.

5. **Dependencies**

   * **Runtime**

     * PHP 7.4+ with `pdo_mysql`
     * A reachable MySQL or MariaDB instance with the PixlKey schema

   * **Libraries**

     * *(Optional)* `vlucas/phpdotenv` via Composer

   * **Downstream Consumers**

     * `auth.php`, `process.php`, `index.php`, and other PixlKey modules that rely on the `$pdo` handle

6. **Additional Notes**

   * Replace the development database credentials (`pixlkey_user`, `pixlkey_password!`) before deploying.
   * Recommended: Use `.env` for all secrets and exclude it from version control.
   * Consider future failover support with PDO read-replica attributes or retry logic for distributed environments.

7. **CHANGELOG**

   * **0.5.0-beta** – Consolidated and documented all runtime constants, rate-limiting policies, and environment loading behavior. Hardened PDO logic and clarified debug exposure rules.

-----

`/core/helpers/functions.php`

1. **Purpose**
   Centralised utility module for PixlKey's backend image handling and system hygiene.
   Provides helper functions for watermark application, real-time UI feedback during processing, and safe cleanup of temporary artefacts. Also ensures secure runtime setup by importing `config.php`, which applies HTTPS enforcement and security headers across all use cases—including CLI or direct script calls.

2. **Agent Role**
   Acts as the **Utility & Media-Processing Agent**, abstracting complex or repetitive backend logic. Supports controller scripts such as `process.php`, `index.php`, and background cron tasks.

3. **Key Responsibilities**

* **Global Constants & Paths**

  * Defines `$maxFileSizeMb`, allowed image extensions, and paths to `watermarks/` and `processed/`.

* **Directory Bootstrapping**

  * Automatically creates critical folders (`watermarks`, `processed`) if missing.

* **Real-Time Feedback**

  * `echoStep($msg, $type)` injects dynamic HTML updates into the browser console, useful for multi-step image processing pipelines.

* **Image Cleanup**

  * `clearProcessedFiles()` purges all files in the `processed/` directory. Intended for nightly cron use or emergency resets.

* **ImageMagick-Powered Watermarking**

  * `addWatermark()` composites both a user-supplied watermark and multiple randomized, transparent text overlays onto submitted images.
  * Ensures the main watermark is resized proportionally to target image height (1/8), centered horizontally and bottom-aligned.
  * Random text is rendered in translucent colours using shell `convert` calls.

* **Security Header Inheritance**

  * By including `config.php`, inherits all global security headers and HTTPS blocking policies—even when this script is called in isolation.

4. **Security Considerations**

* **Shell Sanitisation**

  * All shell commands use `escapeshellarg()`. However, `addslashes()` is still used for injected draw-text—should be monitored for future improvements.

* **Filename Collision / Temp Races**

  * Currently uses deterministic filenames (`wm_*`). A future version should switch to UUID-based or timestamped names to avoid concurrency issues.

* **Path Validation**

  * Assumes upstream code verifies paths and filenames. Add stricter sanitation before direct user input is passed.

* **EXIF Attacks / Zip Bombs**

  * `addWatermark()` does not check image dimensions explicitly; recommend enforcing size/MIME guards earlier in pipeline (`process.php`).

* **Directory Permissions**

  * Directories are created with `0775`. Make sure web server group ownership is secure (e.g., `www-data` only).

* **Debug Info Leakage**

  * `echoStep()` outputs directly to the browser. Wrap in a dev-mode condition or route through a logger for production use.

5. **Dependencies**

* `config.php` – Defines `$pdo`, security headers, environment constants.
* PHP ≥ 7.4 with shell access.
* CLI ImageMagick tools: `convert`, `identify`
* Filesystem access to `watermarks/`, `processed/`

6. **Additional Notes**

* Migrate to PHP’s `Imagick` extension for improved sandboxing and error control.
* Consider formalising a log system for `echoStep()` events.
* Randomised watermark overlay could draw from a secure dictionary of cryptographic phrases or timestamp hashes in future builds.
* Cron job usage of `clearProcessedFiles()` should be paired with logging to avoid accidental wipes.

7. **CHANGELOG**

* **0.5.0-beta** – Improved watermark dimension logic; centralised directory bootstrapping; echoStep styling now CSS-aware; random overlay logic hardened.
* **0.4.7-beta** – `config.php` is now required to enforce HTTPS headers on direct/CLI entry points.
* **0.4.2-beta** – Refactored from legacy helper bundle; renamed, namespace updated, and watermark logic made modular.

-----

`/core/metadata/metadata_extractor.php`

1. **Purpose**
   CLI utility that extracts embedded metadata from image files using ExifTool, filters out sensitive or low-value fields, and generates a human-readable Markdown report grouped by logical sections—enhancing provenance and audit transparency within **PixlKey**.

2. **Agent Role**
   Acts as the **Metadata Extraction Agent** in the PixlKey pipeline. Translates raw ExifTool JSON into semantic, reader-friendly Markdown for licensing review, content registration, or version control audit.

3. **Key Responsibilities**

   * Accept `--input` and `--output` CLI flags.
   * Validate image path and ensure ExifTool is accessible in the environment.
   * Execute `exiftool -j` safely and decode output JSON.
   * Prune metadata keys defined in the `$excludedFields` list.
   * Remap machine-field names (e.g. `ExifToolVersionNumber`) to human-friendly labels (e.g. `ExifTool Version`).
   * Organize fields into logical sections:

     * Basic Information
     * Technical Details
     * Metadata Identifiers
     * Additional Information
   * Render a clean Markdown report (`.md`) with formatted tables.
   * Strip newlines, escape HTML characters, and isolate single-record metadata cleanly.

4. **Security Considerations**

   * **Shell Safety** – All shell commands use `escapeshellcmd()` and `escapeshellarg()` to avoid injection.
   * **Sensitive Data Exclusion** – File paths, permissions, inode metadata, and rights statements are removed before export.
   * **HTML & Markdown Safety** – Values are wrapped with `htmlspecialchars()` to block markup injection or table breakage.
   * **Write Path Control** – Output file path is accepted as-is; in future, restrict to safe output directories or add write-safety checks.
   * **Error Surface** – ExifTool’s raw STDERR is returned to the user; may need filtering if exposed to the browser layer.
   * **Resource Management** – No hard memory or runtime limits yet; malformed or high-res images could cause overrun under load.

5. **Dependencies**

   * PHP (CLI mode) ≥ 7.x
   * ExifTool (installed and accessible in system `$PATH`)
   * `config.php` (optional; adds secure headers if invoked from web context)
   * Local write access to destination file path for Markdown output

6. **Additional Notes**

   * Intended for CLI-only use; if exposed via browser, wrap with full login, CSRF, and role-based access protections.
   * Markdown structure is stable and versionable—ideal for storing alongside digital certificates.
   * Excluded fields are hardcoded in PHP; could be moved to a YAML/INI config for better maintainability.
   * Future enhancements may include:

     * Automatic report upload into PixlKey asset database
     * Direct PDF output
     * Hashing and embedding of report back into image metadata (XMP block or private tag)

7. **CHANGELOG**

   * **0.5.0-beta** – Refactored output renderer to discard unmapped/leftover fields; removed “Other Information” section; now supports newline stripping and proper Markdown formatting.
   * **0.4.7-beta** – Added optional import of `config.php` to support secure invocation from web context (has no effect in CLI mode).
   * **0.4.2-beta** – Initial release. Basic CLI wiring, ExifTool integration, exclusion rules, and Markdown writer implemented.

-----

`/core/processing/process_helpers.php`

1. **Purpose**
   Provides reusable backend utilities for PixlKey’s image-processing workflow. Handles real-time progress streaming to the UI (`echoStep()`), and watermark embedding via ImageMagick (`addWatermark()`), ensuring consistent visual branding across all processed artwork.

2. **Agent Role**
   Functions as the **Image Processing Utility Agent**, operating behind the scenes during processing runs to apply secure, dynamically scaled watermarks and stream status messages in real-time to browser clients or CLI operators.

3. **Key Responsibilities**

   * **Progress Streaming** (`echoStep()`)

     * Sends one HTML-safe `<script>` block per step.
     * Auto-scrolls a log container with `#steps` ID.
     * Uses `flush()` to immediately push updates to browser.

   * **Watermark Application** (`addWatermark()`)

     * Calculates original image width.
     * Resizes the watermark to \~6% of image width.
     * Places it in the bottom-right corner with \~1% margin.
     * Automatically deletes temp resized watermark after use.

   * **Constants**

     * `$defaultWatermark` – fallback image for watermarking.
     * `$allowedExtensions` – enforced list of allowed input formats.

   * **Config Bootstrapping**

     * Imports `config.php` at load time, inheriting all global settings, security headers, and path configs—even during CLI invocation.

4. **Security Considerations**

   * **Shell Execution**

     * Uses `shell_exec()` for `identify` and `convert` commands.
     * All file paths safely escaped with `escapeshellarg()`.
     * Never accepts untrusted or user-submitted paths.

   * **Image Validation**

     * Assumes image integrity was checked prior to function call.
     * Requires upstream MIME-type and dimension checks in `process.php`.

   * **Path Traversal**

     * Inputs should be resolved to known safe locations; sandboxing recommended for long-term deployments.

   * **Resource Usage**

     * ImageMagick can be memory-intensive; production systems should apply `policy.xml` resource caps or isolate via worker queue.

   * **Output Timing**

     * Real-time streaming may expose timing patterns in multi-user contexts; consider output buffering for shared hosts.

   * **Transport Security**

     * `config.php` inclusion ensures HTTPS-only access and strict header policies even when helpers are invoked directly.

5. **Dependencies**

   * `config.php` – Global environment, transport enforcement, and PDO instance.
   * PHP 7.4+ – Uses typed parameters and strict mode.
   * ImageMagick CLI (`convert`, `identify`) – Required system binaries.
   * Directory structure – Must include `/watermarks/muse_signature_black.png` or other default image.

6. **Additional Notes**

   * All functions wrapped in `function_exists()` guards for compatibility and test re-use.
   * Future improvements may include:

     * Namespacing as `PixlKey\Image\Watermarker`
     * Adjustable watermark opacity and placement
     * Logging/dry-run support for debugging (`--dry-run`)
     * Optional fallback to GD/Intervention for environments without ImageMagick

7. **CHANGELOG**

   * **0.5.0-beta** – Hardened shell logic, confirmed `config.php` enforcement; ensured all core helpers are safe to reuse across web and CLI.
   * **0.4.7-beta** – Enforced TLS headers via `config.php`; applied image path security hardening.
   * **0.4.2-beta** – Renamed default watermark file; refined preview messaging and expanded allowed image types.

-----

`/core/processing/store_data.php`

1. **Purpose**
   Commits the full results of a completed image-processing run (identified by `runId`) into the PixlKey database. This includes storing artwork metadata, signed image files, certificates, submission logs, AI metadata, and related entity references using a transactionally safe, UUID-driven model.

   As of `v0.5.0-beta`, the script is hardened against session fixation, CSRF reuse, and non-HTTPS access.

2. **Agent Role**
   Acts as the **Data-Ingestion & Persistence Agent**, transforming a temporary processing directory into permanent, relationally linked records that underpin provenance, copyright, and certificate generation.

3. **Key Responsibilities**

   * **Session & CSRF Security**

     * Enforces login with `require_login()`
     * Rotates session ID (`session_regenerate_id(true)`)
     * Refreshes CSRF token
     * Validates CSRF on POST requests

   * **Ownership & Run Validation**

     * Requires a valid `runId` via GET/POST
     * Confirms ownership by querying `processing_runs`

   * **Filesystem Verification**

     * Validates presence of `processed/<runId>/`
     * Verifies required files like `data.json`, `*_metadata.txt`, and `*_certificate.md`

   * **Transactional Import**

     * All inserts occur within a single `BEGIN … COMMIT` block
     * Rolls back on failure for consistency

   * **Database Mapping**

     * Inserts a new `Artworks` record
     * Computes SHA-256 of signed images; stores in `Images`
     * Inserts human-readable certificates
     * Inserts AI metadata (if present)
     * Adds submission logs (IP, UA, timestamp)
     * De-duplicates and links:

       * Keywords → `ArtworkKeywords`
       * Genres   → `ArtworkGenres`
       * Creators → `ArtworkCreators`
       * Bylines  → `ArtworkBylines`

4. **Security Considerations**

   * **HTTPS Required** – Enforced via `config.php` at bootstrap
   * **Session Fixation** – Session ID regenerated at entry
   * **CSRF Reuse** – Token refreshed on entry
   * **Access Control** – `runId` ownership validated by `processing_runs.user_id`
   * **Path Safety** – `runId` treated as a literal UUID; must match known structure
   * **File Sanity** – Checks for presence and validity of required artefacts
   * **SQL Injection** – Fully protected via prepared statements
   * **Race Conditions** – Entire import wrapped in transaction; prevents double-ingestion
   * **Size/MIME Handling** – `mime_content_type` and `filesize` used for signed images, but further hardening (e.g. `finfo`) recommended

5. **Dependencies**

   * **Internal**

     * `auth.php` – Login + CSRF
     * `config.php` – PDO, HTTPS headers
     * `functions.php` – UUID and helper logic
     * `process_helpers.php` – shared ingestion tools

   * **Database Tables**

     * `Artworks`, `Images`, `Certificates`, `AIMetadata`, `Submissions`
     * `Keywords`, `Genres`, `Creators`, `Bylines` + respective many-to-many junctions

   * **Filesystem Layout**

     * `processed/<runId>/` directory containing:

       * `data.json`, `submission.json` (optional)
       * `*_signed.png`
       * `*_metadata.txt`, `*_certificate.md`, `*_ai_metadata.json`

   * **PHP Extensions**

     * `PDO`, `PDO_MYSQL`, `json`, `openssl`

6. **Additional Notes**

   * `getOrCreateId()` assumes UUIDs are returned by MySQL’s native `UUID()` call.
   * Consider abstracting keyword/genre/creator logic into modular service classes.
   * Future versions may include optional blockchain anchoring upon commit.
   * Server-side MIME inspection (`finfo_file`) is advised to prevent spoofed image types.
   * Certificate and metadata parsing assumes conventional naming—document this for third-party ingestion tools.

7. **CHANGELOG**

   * **0.5.0-beta** – Full refactor to enforce session/csrf/token rotation, simplify UUID handling, and modernise filesystem layout validation. Supports AI metadata, full provenance linkage, and safer error handling.
   * **0.4.7-beta** – Enforced HTTPS at bootstrap via `config.php`.
   * **0.4.6-beta** – Added CSRF token regeneration after session ID refresh.
   * **0.4.4-beta** – Introduced session hardening for post-login integrity.
   * **0.4.2-beta** – Initial PixlKey-branded ingestion logic, transaction-wrapped, with referential inserts.

-----

`/core/session/SessionBootstrap.php`

1. **Purpose**  
   Provides a **centralised, reusable helper** for starting PixlKey sessions securely.  
   Encapsulates cookie-flag configuration (`Secure`, `HttpOnly`, `SameSite=Strict`) and session-start logic into a single function to eliminate copy-paste boilerplate across entry points.

   As of `v0.5.1.1-alpha`, this module replaces inline `ini_set()` and `session_start()` calls previously scattered across authentication and controller scripts.

2. **Agent Role**  
   Acts as a **Session Bootstrap Agent**, ensuring all PixlKey components initialise sessions consistently and with strict security flags.

3. **Key Responsibilities**  

   * Configure PHP session cookie flags:  
     * `session.cookie_secure = 1`  
     * `session.cookie_httponly = 1`  
     * `session.cookie_samesite = Strict`  
   * Initialise the session via `session_start()` with strict cookie parameters for HTTPS deployments.
   * Silently return without action if the session is already active.
   * Provide a single entry-point function:  
     * `PixlKey\Session\startSecureSession()`

4. **Security Considerations**  

   * **Secure Cookies** – Enforces `Secure`, `HttpOnly`, and `SameSite=Strict` by default.
   * **Session Hygiene** – Prevents repeated or unsafe session restarts by returning early if `session_status() === PHP_SESSION_ACTIVE`.
   * **Transport Security** – Flags rely on HTTPS; paired with upstream TLS enforcement in `config.php`.
   * **Defence-in-depth** – Centralisation reduces the risk of inconsistent session initialisation across scripts.

5. **Dependencies**  

   * PHP session extension (native).  
   * Included by:  
     * `/core/auth/auth.php`  
     * Any future controller or script requiring secure session handling.

6. **Additional Notes**  

   * Namespaced under `PixlKey\Session` to prevent global function collisions.  
   * Provides an **idempotent session starter** (safe to call multiple times).  
   * Future versions could support configurable SameSite mode (`Lax` for third-party integrations).

7. **CHANGELOG**  

   * **0.5.1.1-alpha** – Initial release. Extracted session bootstrap logic from `auth.php` into a dedicated, namespaced helper for reuse across PixlKey entry scripts.

-----

`/core/tools/generate_hvf.py`

1. **Purpose**
   Generates a **High-Fidelity Visual (HFV) fingerprint** for any supported image file, producing a deterministic 64-character SHA-256 hash that represents the image’s content, structure, and timestamped signature.
   This fingerprint is used to assert image uniqueness and bind metadata to visual identity at the time of certification.

2. **Agent Role**
   Functions as a **low-level hashing utility** in PixlKey’s authenticity pipeline. Called during certificate generation, it transforms raw pixel data and visual statistics into a cryptographically strong digest—optionally salted with a server-side “pepper” to prevent third-party forgery.

3. **Key Responsibilities**

   * **Input Validation**

     * Requires an image path (`<image_path>`) and a timestamp (`--ts`) in ISO-8601 format.
     * Optionally accepts a `--pepper` value for salting the digest.

   * **Image Parsing**

     * Ensures the file is supported (`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`) and decodable.
     * Loads the image in RGB mode using PIL/Pillow.

   * **Feature Extraction**

     * Computes the following:

       * Dimensions (WxH)
       * File size (bytes)
       * Mean and standard deviation of each RGB channel
       * XOR checksum of pixel bytes
       * SHA-256 of the RGB histogram (768 bins)
       * Perceptual pHash (64-bit)

   * **Digest Assembly**

     * Combines extracted features into a canonical JSON object.
     * Appends optional pepper, hashes the result with SHA-256, and prints the fingerprint.

4. **Security Considerations**

   * **Digest Salting** – The `--pepper` option ensures only the PixlKey server can generate verifiable HFVs.
   * **Replay Protection** – Timestamp (`--ts`) is embedded to bind the fingerprint to a specific moment.
   * **Robustness** – Uses `Image.verify()` to catch corrupt files before processing.
   * **Determinism** – Canonical JSON structure and strict sort order ensure reproducible results.

5. **Dependencies**

   * **Python Standard Library**

     * `sys`, `os`, `json`, `hashlib`, `argparse`, `datetime`

   * **Third-Party**

     * `Pillow` (`PIL.Image`) – Image decoding and conversion
     * `NumPy` – Pixel-level math for statistics and XOR
     * `imagehash` – Perceptual hashing (pHash)

6. **Additional Notes**

   * Designed for integration into backend workflows or batch-certification tools.
   * Timestamp argument must be formatted as `YYYY-MM-DDTHH:MM:SSZ` (ISO-8601).
   * Pepper should be configured securely within the PixlKey backend; never exposed client-side.
   * Future versions may add optional digest formats (e.g. base64, QR) or metadata embedding.

7. **CHANGELOG**

   * **0.5.1-beta** – Initial agent implementation: complete HFV fingerprint pipeline with image statistics, perceptual hash, XOR checksum, and peppered SHA-256 output.

-----

`/public/download_zip.php`

1. **Purpose**
   Serves the user’s final packaged asset bundle (`final_assets.zip`) corresponding to a validated `runId`. Enforces **authentication**, **ownership**, **rate limits**, and **secure file handling** for all download requests.

Now features:

* HTTPS enforcement and secure headers via `config.php`
* Fine-grained throttling via `rate_limiter.php`
* Per-request validation and structured HTTP error handling

2. **Agent Role**
   Acts as the **Download & Delivery Agent**, sitting at the end of PixlKey’s processing pipeline. It ensures that only the rightful owner can retrieve their archive, prevents abuse through rate-limiting, and streams the archive directly to the user with appropriate headers.

3. **Key Responsibilities**

* Enforce user authentication via `require_login()`
* Validate the `runId` parameter and sanitize input
* Verify that the `runId` belongs to the current session user
* Locate the final `.zip` archive in `processed/<user_id>/<runId>/`
* Apply download throttling via a composite key: IP + user ID + `runId`
* Emit appropriate HTTP responses (`400`, `403`, `404`, or `429`) with clear messages
* Stream the file with secure headers: `Content-Type`, `Content-Disposition`, `Content-Length`

4. **Security Considerations**

* **Authentication**: Strict `require_login()` session enforcement
* **Input validation**: Regex whitelist to eliminate traversal and injection risks
* **Path traversal mitigation**: Controlled directory layout + ZIP detection via `glob()`; `realpath()` still recommended
* **Rate Limiting**: Default 10 downloads per 60 seconds (`DOWNLOAD_ATTEMPT_LIMIT` / `DOWNLOAD_DECAY_SECONDS`) via `.env`
* **Audit Logging**: Failed attempts recorded; successful downloads can be logged for traceability
* **Transport Security**: HTTPS enforced, HSTS and `nosniff` headers automatically applied via `config.php`
* **Timing Attacks**: Minimal footprint, but `hash_equals()` could be added if `runId` becomes sensitive or user-enumerable
* **Response Integrity**: Consistent structured error messages reduce ambiguity and exposure

5. **Dependencies**

* `auth.php` – Session auth, user validation
* `config.php` – PDO connection, HTTPS enforcement
* `rate_limiter.php` – Download throttling logic
* Database: `processing_runs` (`run_id`, `user_id`)
* File structure: `processed/<user_id>/<runId>/final_assets.zip`

6. **Additional Notes**

* Large downloads could be offloaded to Nginx (`X-Accel-Redirect`) or Apache (`X-Sendfile`) to save PHP memory
* Support for pre-signed short-lived URLs would enable future headless/API usage
* Filenames can be optionally formatted as `pixlkey-<runId>.zip` for clarity

7. **CHANGELOG**

* **0.5.0-beta** – Formalised all responses (`400`, `403`, `404`, `429`); modular rate key now includes IP + user + `runId`; sanitized ZIP lookup logic with glob fallback.
* **0.4.9-beta** – Integrated `rate_limiter.php`; endpoint now emits `429 Too Many Requests` with `Retry-After` support.
* **0.4.7-beta** – Added `config.php` at top-level to enforce HTTPS and inject standard security headers.

-----

`/public/login.php`

1. **Purpose**
   Provides PixlKey’s secure sign-in interface. Accepts user credentials, validates CSRF tokens, applies brute-force mitigation via IP-based rate limiting, and establishes a logged-in session on success.

   Enforces transport security by blocking HTTP access and sending standard security headers (via `config.php`).

2. **Agent Role**
   Acts as the **Authentication Gateway**. It authenticates users and initiates secure login sessions, serving as the entry point to PixlKey’s authenticated tools and features.

3. **Key Responsibilities**

   * **Login Form Rendering**

     * Presents a clean HTML login form with CSRF protection and minimal inline CSS.
     * Accepts `email`, `password`, and optional `next` redirect path.

   * **Request Handling**

     * On POST, enforces CSRF protection via `validate_csrf_token()`.
     * Applies IP-based brute-force prevention using the `login_` bucket and shared constants:
       `LOGIN_ATTEMPT_LIMIT`, `LOGIN_DECAY_SECONDS`.

   * **Authentication Logic**

     * Looks up user by email; always calls `password_verify()` to protect against timing attacks.
     * On success, rehashes the password if algorithm or cost parameters are outdated.
     * Clears rate-limiter counters and calls `login_user()` to establish session.
     * Redirects to the provided `$next` path.

   * **Error Handling**

     * Displays user-facing errors (invalid credentials, rate limits) on the same form page.
     * Uses `htmlspecialchars()` to safely echo dynamic error messages.

4. **Security Considerations**

   * **CSRF** – Enforced on all POST requests.
   * **Rate Limiting** – Per-IP, 5 attempts max per `LOGIN_DECAY_SECONDS`; optionally sends HTTP 429.
   * **HTTPS Enforcement** – Enforced at runtime (`config.php`) and via HSTS headers.
   * **Session Fixation** – Addressed via `session_regenerate_id(true)` in `login_user()`.
   * **Timing Attack Mitigation** – Always performs `password_verify()` even on unknown accounts.
   * **Password Hashing** – Uses `password_needs_rehash()` to maintain strong cryptographic defaults.
   * **Redirect Safety** – `$next` parameter is sanitized but should be further hardened or whitelisted in future.

5. **Dependencies**

   * **Internal**

     * `auth.php` – CSRF tools, `login_user()`
     * `rate_limiter.php` – `too_many_attempts()`, `record_failed_attempt()`, `clear_failed_attempts()`
     * `config.php` – HTTPS enforcement, `$pdo`
     * `functions.php` – shared helpers

   * **Database Table**

     * `users` (`user_id`, `email`, `password_hash`)

6. **Additional Notes**

   * CAPTCHA integration is recommended after repeated failed logins.
   * Consider adding a password reset (`forgot_password.php`) and e-mail verification system.
   * CSS should eventually be extracted into a shared stylesheet.
   * For future API or SPA clients, a JSON version of this endpoint with token-based responses may be useful.

7. **CHANGELOG**

   * **0.5.0-beta** – Hardened credential handling, ensured password rehashing, unified rate limiter keys, and allowed optional 429 responses.
   * **0.4.7–0.4.9-beta** – HTTPS enforcement, session fixation fix, and adaptive rate-limiting finalised.

-----

`/public/logout.php`

1. **Purpose**
   Securely terminates the current user session and redirects to the login screen.
   Clears all session data, expires cookies, and regenerates a fresh session with a new CSRF token.
   Also emits transport-level security headers (`HSTS`, `nosniff`) via `config.php`.

2. **Agent Role**
   Functions as the **Session-Teardown Agent**, ensuring that logout fully invalidates the session and prevents authentication remnants or fixation vectors from persisting.

3. **Key Responsibilities**

   * Unset session variables using `session_unset()`.
   * Destroy the current session (`session_destroy()`).
   * Expire the session cookie immediately, mirroring its original flags.
   * Bootstrap a fresh secure session and regenerate its ID.
   * Rotate the CSRF token (`$_SESSION['csrf_token'] = bin2hex(...)`).
   * Redirect the user to `login.php`.

4. **Security Considerations**

   * **Cookie Flags** – Reapply `Secure`, `HttpOnly`, and `SameSite=Strict` after logout.
   * **Cache Control** – Should send `Cache-Control: no-store` and `Pragma: no-cache` to prevent caching of authenticated pages post-logout.
   * **CSRF Hygiene** – Ensures fresh token is issued for the new session, blocking CSRF reuse.
   * **Session Fixation** – Fully regenerates session ID after reinitialisation.
   * **Redirect Code** – Uses standard `302` redirect, but `303 See Other` could be considered for POST scenarios.
   * **Transport Security** – Secured by `config.php`, which enforces HTTPS and emits appropriate headers.

5. **Dependencies**

   * `auth.php` – Provides access to session and token helpers.
   * `config.php` – Applies HTTPS, HSTS, and security headers.
   * PHP session extension and cookie management.

6. **Additional Notes**

   * API or SPA variants may eventually require a `204 No Content` JSON-based logout option.
   * Logging logout events (`user_id`, timestamp, IP) would aid in future auditability.

7. **CHANGELOG**

   * **0.5.0-beta** – Full logout hardening: CSRF token rotation, session ID regeneration, and cookie expiry alignment. Global headers via `config.php`.

-----

`/public/my_licenses.php`

1. **Purpose**
   Provides a secure, session-restricted interface for managing reusable licence text blocks. Authenticated users can create, preview (Markdown → HTML), update, delete, and assign a *default* licence, which is automatically attached to future image uploads.

Built-in protections include CSRF enforcement, IP-based rate limiting, Markdown sanitisation, and HTTPS-only access.

2. **Agent Role**
   Acts as the **Licence-Manager Agent**—bridging user input with persistent licensing data. It serves as the canonical interface for managing user-defined legal terms and rights declarations in PixlKey.

3. **Key Responsibilities**

* **Authentication & Security**

  * Enforces `require_login()` access control.
  * Validates CSRF token on all POST actions.
  * Applies per-user rate-limiting: 10 POSTs per minute (via `rate_limiter.php`).
  * Markdown preview is rendered using `Parsedown` with Safe Mode enabled.

* **CRUD Functionality**

  * **Create / Update**: Insert or update licence records; clear existing defaults if a new default is set.
  * **Delete**: Securely remove a licence (POST with CSRF).
  * **Fetch**: Load all licences for the current user in reverse chronological order.

* **Frontend UX**

  * Inline preview of Markdown-rendered licence text.
  * JavaScript-driven form population for editing.
  * Single default licence enforced per user.

4. **Security Considerations**

* **XSS**: `Parsedown::setSafeMode(true)` neutralizes raw HTML; long-term, consider migration to `league/commonmark` with HTML-purifier extensions.
* **CSRF**: All state-altering actions require a valid token.
* **Rate Limiting**: POST actions are rate-limited (10/min/user) with HTTP 429 fallback.
* **Session Fixation**: Covered in `auth.php` roadmap; ensure session ID regeneration post-login.
* **Race Conditions**: Default enforcement should eventually move to a transactional or trigger-based DB layer to ensure atomicity under concurrent access.
* **Clickjacking**: `X-Frame-Options: DENY` is recommended at the server level (currently delegated to `config.php` headers).

5. **Dependencies**

* `auth.php` – session and CSRF enforcement.
* `config.php` – PDO access and HTTPS enforcement.
* `functions.php` – CSRF helpers.
* `rate_limiter.php` – POST rate limiting logic.
* `vendor/autoload.php` – Composer-based autoloader for `Parsedown`.
* `licenses` database table – stores all licence text, default flags, and metadata.

6. **Additional Notes**

* A version history or `updated_at` column would support rollback or audit features.
* Export/import tools (SPDX/JSON) could make licence reuse portable.
* REST/GraphQL endpoints would support future single-page apps or mobile clients.
* Pagination or search may be required for users managing many licences.

7. **CHANGELOG**

* **0.5.0-beta** – Restructured and Composer-loaded Parsedown integration; clarified UI logic; standardized POST tracking and message rendering.
* **0.4.9-beta** – Introduced rate limiting (10 POSTs/min/user) with HTTP 429 responses.
* **0.4.7-beta** – HTTPS enforcement and secure browser headers added via shared config.
* **0.4.2-beta** – Initial build: CRUD flow with CSRF, Markdown preview, and single-default logic.

-----

`/public/my_watermarks.php`

1. **Purpose**
   Provides a secure dashboard where authenticated users can **upload**, **list**, **set default**, and **delete** their watermark images (PNG, JPG, JPEG, WEBP). All actions interact directly with the PixlKey database and per-user filesystem directories.

As of `v0.5.0-beta`, rate limiting, secure CSRF workflows, and HTTPS enforcement are fully integrated and active from the earliest bootstrap stage.

2. **Agent Role**
   Acts as the **Watermark-CRUD Agent**, maintaining a per-user watermark library for use in image processing workflows. Directly bridges user input with both filesystem storage and SQL-backed metadata.

3. **Key Responsibilities**

* Enforce login via `require_login()` before any output.
* Apply IP+user rate limiting (`WM_ATTEMPT_LIMIT`, `WM_DECAY_SECONDS`) to all write operations using `rate_limiter.php`.
* Handle POST actions:

  * **Upload** new watermark (validates extension and safely moves to per-user dir).
  * **Set default** watermark (resets all to `0`, sets chosen to `1`).
  * **Delete** watermark (removes both database record and physical file).
* Log only failed attempts for rate-limiting feedback fairness.
* Automatically mark the user’s **first** watermark as default.
* Display a table with:

  * Filename
  * Thumbnail preview
  * Default marker
  * Action buttons (set default / delete)
* Display success and error messages clearly above the UI.

4. **Security Considerations**

* **Transport**: HTTPS enforced at bootstrap via `config.php`; includes `HSTS` and content-sniff prevention headers.
* **CSRF**: Double-submit tokens injected on every form; tokens validated before all mutations.
* **Rate Limiting**: Scoped by IP and user; **only failed** attempts log toward block threshold.
* **Path Safety**:

  * Uses `uniqid()` for filenames to avoid overwrites.
  * Sanitizes deletion paths using `ltrim()` + `dirname(__DIR__)`.
* **MIME Verification**: Extension-validated only; recommend supplementing with `finfo_file()` in future versions.
* **Per-user Isolation**: Watermarks stored in `/watermarks/{user_id}/`; non-public unless called through controller.
* **Replay Resistance**: Suggest rotating CSRF tokens after each successful write action.
* **Quota & Abuse**: Future enhancements should include per-user watermark count/disk quotas and background cleanup tools.

5. **Dependencies**

* Internal:

  * `auth.php` – session/login + CSRF tools
  * `config.php` – database connection and security headers
  * `rate_limiter.php` – per-user IP throttle framework
  * `functions.php` – CSRF utility

* Database:

  * `watermarks` table: `watermark_id`, `user_id`, `filename`, `path`, `is_default`, `uploaded_at`

* PHP Extensions:

  * `fileinfo` – for robust MIME detection (recommended)
  * `gd` or `imagick` – optional for future image validation

6. **Additional Notes**

* Consider JavaScript-based file validation (max dimensions, size).
* Add watermark preview overlay to test visibility on black/white backgrounds.
* Countdown feedback UI could help users wait after hitting rate limits.
* Future roadmap may include support for vector (`.svg`) watermarks with sanitization.
* Logged deletions and undo/archive features would support better user recovery.

7. **CHANGELOG**

* **0.5.0-beta** – Finalised rate limiting and IP scope handling; split success/failure recording for better UX; enforced consistent CSRF token presence; cleaned error/reporting display.

-----

`/public/process.php`

1. **Purpose**
   Executes the full image-processing pipeline when a user submits artwork to PixlKey. Transforms, signs, and fingerprints uploaded images, embeds metadata and rights info, generates thumbnails and previews, writes database records, and packages the deliverables into a ZIP archive alongside a Markdown certificate of authenticity and extracted metadata.

2. **Agent Role**
   Acts as the **Pipeline Orchestrator Agent**, controlling every stage from ingestion to archival. It ties together PHP logic, CLI tools (ImageMagick, ExifTool), metadata handling, database persistence, and real-time HTML output to guide the user through each processing step.

3. **Key Responsibilities**

* **Form Processing**

  * Validates login and CSRF token
  * Parses metadata fields (title, date, genre, etc.)
  * Enforces 200 MB upload cap per image

* **Watermark Handling**

  * Chooses saved watermark or processes one-off upload
  * Applies scaled watermark during compositing phase

* **Rate Limiting**

  * Throttles ZIP packaging (default: 10 requests/min per IP)
  * Automatically clears counters on successful build

* **Per-Image Workflow**

  * Moves uploaded files into a user-specific `runId` folder
  * Strips EXIF metadata and converts to PNG
  * Generates SHA-256 hash and embeds XMP/IPTC metadata
  * Applies watermark, produces signed image
  * Creates 400px thumbnail and 800px preview
  * Stores file paths and hash into `images` DB table
  * Runs metadata extractor to produce `*_metadata.md`
  * Generates Markdown certificate of authenticity

* **ZIP Packaging**

  * Zips signed image, thumbnail, preview, metadata, and certificate
  * Presents download button and return-to-index link

* **User Feedback**

  * Streams visual HTML steps to user in real time

4. **Security Considerations**

* **Input Validation** – All form fields sanitised; image extensions and sizes validated
* **CSRF** – Token required and verified
* **Shell Injection** – Commands escaped with `escapeshellarg()`, but `proc_open()` is recommended for production hardening
* **Rate Limiting** – Configurable IP throttling on ZIP creation
* **Path Traversal** – Input filenames are sanitised; database paths verified
* **ZIP Poisoning** – Only server-generated files added to ZIP
* **Resource Exhaustion** – No memory or CPU limits on ImageMagick/ExifTool; consider `ulimit` or job queuing
* **Session Security** – Handled by `auth.php`; requires `session_regenerate_id()`
* **Disk Usage** – No auto-cleanup yet; persistent `/processed/` directories may accumulate

5. **Dependencies**

* **Internal**

  * `auth.php`, `config.php`, `rate_limiter.php`, `functions.php`
  * `process_helpers.php`, `metadata_extractor.php`

* **CLI Tools**

  * `exiftool` – metadata manipulation
  * `convert` / `identify` – ImageMagick operations
  * `sha256sum` – cryptographic hashing
  * `zip` – archival packaging

* **PHP Extensions**

  * `PDO`, `openssl`, `file_uploads`

* **Database**

  * Tables: `images`, `processing_runs`, `watermarks`, `licenses`, `users`

* **Filesystem**

  * Writable `/processed/` directory outside web root with `.htaccess` protection

6. **Additional Notes**

* **UX** – Streams live steps in HTML, compatible with most browsers
* **Refactor Potential** – Split into services (e.g., UploadHandler, ZipBuilder)
* **Queue-Ready** – Could offload intensive processing to background workers
* **Future Features**

  * Blockchain or notarisation hook for SHA-256 anchors
  * Internationalisation of metadata fields
  * Cleanup tasks for old run directories

7. **CHANGELOG**

* **0.5.0-beta**
  Major overhaul for streaming UX, watermark override logic, detailed error handling, metadata previewing, and ZIP throttling cleanup logic.

* **0.4.9-beta**
  Introduced IP-based rate limiting for ZIP builds via `rate_limiter.php`.

* **0.4.2-beta**
  Initial rewrite for PixlKey: added CSRF validation, user `runId` scoping, certificate generation, and metadata embedding.

-----

`/public/register.php`

1. **Purpose**
   Renders the new user registration form and handles all logic to securely create a user account within PixlKey. Validates credentials, enforces rate limits, inserts the user record, and initiates login upon success.

2. **Agent Role**
   Acts as the **Registration Agent**, connecting the public interface to the authentication system. It validates and sanitises inputs, ensures unique registration, and defends against abuse via IP throttling and CSRF enforcement. Upon successful registration, it hardens session boundaries and logs the user in.

3. **Key Responsibilities**

* Render a compact, dark-themed registration form with CSRF token.
* Enforce HTTPS-only access and set strict transport security headers via `config.php`.
* Validate user inputs:

  * E-mail address must conform to RFC 5322.
  * Passwords must match and be ≥ 8 characters.
* Enforce IP-based rate limits:

  * Default: 5 attempts per 30 minutes (configurable).
  * Returns HTTP 429 with `Retry-After` header on limit breach.
* Ensure the submitted e-mail is not already registered.
* Hash password securely using `password_hash()` (`PASSWORD_DEFAULT`).
* Store new user in the `users` table, retrieving the `user_id` on success.
* Rotate session ID and CSRF token after registration to mitigate fixation.
* Automatically log the user in and redirect to the homepage.
* If any validation fails, errors are presented inline.

4. **Security Considerations**

* **CSRF**: Tokens validated on POST and rotated on successful registration.
* **Session Fixation**: Mitigated via `session_regenerate_id(true)` before login.
* **Rate Limiting**: Uses centralised config and persistent tracking; compliant clients can retry using `Retry-After`.
* **Password Security**:

  * Strong hashing via `password_hash()`.
  * Could benefit from complexity enforcement or breach detection (e.g., HaveIBeenPwned).
* **Transport Security**: Enforced at bootstrap; sends `HSTS`, `X-Frame-Options`, `nosniff` headers.
* **Bot Mitigation**: Only IP throttling present; CAPTCHA or behavioral scoring suggested for future.
* **XSS**: Output passed through `htmlspecialchars()`; future templating should maintain this standard.

5. **Dependencies**

* `auth.php` – CSRF utilities, `login_user()`.
* `rate_limiter.php` – abuse prevention functions.
* `config.php` – HTTPS headers, rate-limit constants.
* `functions.php` – `generate_csrf_token()`, etc.
* **Database**: `users` table (`email`, `password_hash`, `display_name`).
* **PHP Extensions**: `pdo_mysql`, `openssl` (for `random_bytes()`).

6. **Additional Notes**

* Client-side enhancements like field validation or password strength meters are not yet implemented.
* No e-mail verification or confirmation mechanism is in place.
* Inline styling and markup may be migrated to dedicated CSS templates.
* Logic could be reused by a future JSON API endpoint (`/api/register`).

7. **CHANGELOG**

* **0.5.0-beta** – Hardened session/CSRF boundaries; rate-limiting configuration centralised; registration pipeline stabilised for production readiness.

-----

`/index.php`

1. **Purpose**
   Acts as the unified landing page for PixlKey, offering both **public previews** and **member uploads**. Displays recent artwork thumbnails and, for authenticated users, renders a metadata-rich image upload form with watermarking and licensing options.

   As of `v0.5.0-beta`, it integrates:

   * IP-based rate limiting (via `rate_limiter.php`)
   * HTTPS enforcement and secure headers (via `config.php`)
   * Inline previews for selected watermarks and images using vanilla JavaScript

2. **Agent Role**
   Serves as the **UI & Intake Agent** of the PixlKey platform. It dynamically adjusts its interface based on login status and prepares all assets for submission to `process.php`, including CSRF tokens, image files, and licensing metadata.

3. **Key Responsibilities**

   * **Bootstrap**

     * Loads `auth.php` → `config.php` → `rate_limiter.php` → `functions.php` in order.

   * **Rate Limiting**

     * If the user is logged in and rate limiting is enabled, enforces a 10 uploads/min per-IP throttle.

   * **Thumbnail Display**

     * Shows latest 10 thumbnails, user-specific for members and site-wide for public visitors.

   * **Upload Form**

     * Collects artwork metadata (title, creator, description, date, tags, copyright).
     * Allows watermark selection or upload; includes preview with JavaScript.
     * Allows license selection; preselects user’s default if set.
     * Injects CSRF token and submits to `process.php` via POST.

   * **Conditional Rendering**

     * Members: navigation, dual thumbnail grid, upload form.
     * Public: global thumbnail grid and login/register prompts.

   * **Client-Side Preview**

     * Previews selected watermark or image file before upload using `FileReader`.

4. **Security Considerations**

   * **CSRF** – Tokens injected via `generate_csrf_token()`; ensure token rotation elsewhere.
   * **Rate Limiting** – Per-IP throttling (10 uploads/min) enforced for logged-in users.
   * **XSS** – All output escaped via `htmlspecialchars()`; monitor `<textarea>` edge cases.
   * **HTTPS Only** – Enforced at bootstrap; emits HSTS and anti-sniffing headers.
   * **Session Security** – Relies on secure login via `auth.php`; ensure session ID regeneration post-login.
   * **Access Control** – Watermarks and licences are only retrieved for authenticated users.
   * **Server Files** – Ensure `processed/` and `watermarks/` directories are access-protected.

5. **Dependencies**

   * **Internal**

     * `auth.php` – login session & CSRF helpers
     * `config.php` – HTTPS enforcement & PDO
     * `rate_limiter.php` – IP-based throttling
     * `functions.php` – shared utility functions
     * `process.php` – form receiver and image processor

   * **Database Tables**

     * `images` (for thumbnails)
     * `watermarks`, `licenses` (for member tools)

   * **External**

     * Google Fonts (Orbitron)
     * Logo: `./watermarks/pixlkey2.png`

6. **Additional Notes**

   * Consider externalizing CSS and JS for CSP compliance and cacheability.
   * Drag-and-drop and upload progress indicators could enhance UX.
   * API-ready architecture could mirror this form for future JSON-based clients.
   * Scheduled cleanup of uploaded originals (>10MB) is not yet implemented.

7. **CHANGELOG**

   * **0.5.0-beta** – Reorganized structure; formalised IP upload throttling; updated CSRF handling; preview thumbnails now support JavaScript-based live updates.