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

`/auth.php`

1. **Purpose**
   Provides foundational session management, CSRF protection, and helper functions for authenticating users within **PixlKey**.

2. **Agent Role**
   Acts as the **Security & Session Agent**, enforcing safe request flows (login, form-submission) and exposing a unified API (`login_user()`, `require_login()`, `current_user()`) to the remainder of the application.

3. **Key Responsibilities**

   * Initialise a hardened PHP session (SameSite = Strict, HTTPS-aware, HttpOnly).
   * Generate and verify CSRF tokens for all non-GET requests.
   * Log users in, updating their `last_login` timestamp.
   * Gatekeep protected routes via `require_login()`, redirecting unauthenticated users.
   * Provide a cached `current_user()` lookup for downstream business logic.

4. **Security Considerations**

   * FIXED 0.4.4-beta **Session fixation**: call `session_regenerate_id(true)` immediately after successful login.
   * FIXED 0.4.6-beta **Token rotation**: rotate CSRF token post-login (`login_user()`), logout (`logout.php`), and after session regeneration (`store_data.php`) to prevent token reuse across privilege transitions.
   * **Rate limiting / brute-force**: implement throttling on `login_user()` calls.
   * **Password verification**: authentication flow (currently elsewhere) must use `password_hash()` / `password_verify()`.
   * **Strict transport**: enforce HTTPS globally, not merely detect it.
   * **Same Origin Policy**: consider adding `header('X-Frame-Options: DENY')` in a central bootstrap.

5. **Dependencies**

   * `config.php` – establishes `$pdo` database handle.
   * Database table `users` with columns: `user_id`, `email`, `display_name`, `is_admin`, `last_login`.
   * PHP `session` extension, `openssl` / `random_bytes` functions.

6. **Additional Notes**

   * Exports are deliberately minimal; keep business-logic-free to simplify unit tests.
   * Future releases might expose a JSON Web Token (JWT) endpoint for API access while preserving the same CSRF model for browser forms.
   * Evaluate migrating to `SameSite=Lax` with exception lists if third-party integrations are required.

7. **CHANGELOG**

   * **2025-07-14 · v0.4.6-beta** – CSRF Token Rotation Patch:
     - Added `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))` to `login_user()` immediately after session ID regeneration.
     - Ensures CSRF token changes after login to prevent cross-context reuse or privilege escalation.

   * **2025-07-12 · v0.4.4-beta** – Hardened session integrity:
     - Called `session_regenerate_id(true)` immediately after successful login inside `login_user()`.
     - Ensures proper mitigation of session fixation and session-swap attacks.
     - Validated full session lifecycle flow in tandem with `logout.php` patch.

   * **2025-07-11 · v0.4.2-beta** – Initial extraction into standalone `auth.php`; added SameSite Strict cookies, CSRF helpers, and user-cache to support the broader PixlKey refactor.

-----

`/config.php`

1. **Purpose**
   Establishes a PDO connection to the PixlKey MariaDB / MySQL database, pulling credentials from environment variables (with sensible fallbacks) and enforcing upload-size limits at runtime.

2. **Agent Role**
   Functions as the **Database & Configuration Agent**, supplying all other PHP modules with a ready-to-use `$pdo` handle and centralising environment-driven settings (e.g., maximum upload size).

3. **Key Responsibilities**

   * Load optional `.env` secrets via *php-dotenv* (Composer).
   * Convert environment variables into constants (`DB_HOST`, `DB_NAME`, … ).
   * Enforce PHP `upload_max_filesize` & `post_max_size` based on `MAX_UPLOAD_MB`.
   * Instantiate the PDO connection with hardened defaults:

     * `ERRMODE_EXCEPTION` for predictable error handling.
     * `FETCH_ASSOC` for associative-array results.
     * Native prepared statements (`EMULATE_PREPARES = false`).
   * Log and gracefully terminate on connection failure.

4. **Security Considerations**

   * **Fallback credentials**: default `DB_PASS` is hard-coded; override via environment or secret manager in production.
   * **Debug leakage**: `DB_DEBUG=true` exposes exception details to clients; keep *false* outside dev.
   * **Credential scope**: ensure file permissions (`chmod 600`) prevent unauthorised reads.
   * **Transport security**: if DB is remote, use TLS-encrypted client connections.
   * **Secrets rotation**: consider runtime reload or container secret mounts to avoid redeploys purely for key rotation.

5. **Dependencies**

   * PHP ≥ 7.4 with PDO & `pdo_mysql` extensions.
   * A reachable MySQL/MariaDB instance containing the PixlKey schema.
   * *(Optional)* `vlucas/phpdotenv` for `.env` parsing (`vendor/autoload.php`).
   * Other PixlKey modules (`auth.php`, `process.php`, etc.) that `require_once` this bootstrap.

6. **Additional Notes**

   * Replace `infinite_image_tools` default DB name/user once production credentials are finalised.
   * Future: add automatic retry / exponential back-off for high-latency containerised deployments.
   * Consider migrating to MySQL *read replica* pool support via PDO attributes for scaling.

7. **CHANGELOG**

   * **2025-07-12 · v0.4.5-beta** – Added dynamic `APP_VERSION`, `APP_NAME`, and randomized `APP_TITLE`/`APP_HEADER` string rotation for branding consistency across pages.

   * **2025-07-11 · v0.4.2-beta** – Migrated credential loading to environment variables, introduced optional *php-dotenv* support, added dynamic upload size limits, and consolidated PDO hardening flags for PixlKey.

-----

`/download_zip.php`

1. **Purpose**
   Delivers a previously-generated archive (`final_assets.zip`) associated with a specific `runId`, allowing the logged-in PixlKey user to download the finished asset bundle.

2. **Agent Role**
   Operates as the **Download & Delivery Agent** at the terminus of the processing pipeline, ensuring that only the rightful owner of a processing run can retrieve its packaged results.

3. **Key Responsibilities**

   * Enforce authentication via `require_login()`.
   * Validate the `runId` query parameter (presence, character whitelist).
   * Confirm ownership by querying `processing_runs` for `(runId, user_id)`.
   * Resolve the canonical path to `processing/<user_id>/<runId>/final_assets.zip`.
   * Emit appropriate HTTP error codes (400, 403, 404) on failure states.
   * Stream the ZIP file with correct `Content-Type`, `Content-Disposition`, and `Content-Length` headers.

4. **Security Considerations**

   * **Session fixation**: call `session_regenerate_id(true)` after login (handled in `auth.php`).
   * **Path traversal**: current whitelist regex mitigates most attacks; additionally consider using `realpath()` and confirming the resolved path begins with the expected base directory.
   * **Timing attacks**: fetch ownership with a constant-time comparison (`hash_equals()` on IDs) to avoid user-enumeration via response timing.
   * **Download abuse**: add rate-limiting or signed, expiring URLs to curb hot-linking and scraping.
   * **MIME sniffing**: send `X-Content-Type-Options: nosniff`.
   * **Audit logging**: log successful downloads (user, IP, timestamp) for traceability.

5. **Dependencies**

   * `auth.php` – session, `require_login()`, `current_user()`.
   * `config.php` – provides `$pdo` database handle.
   * Database table `processing_runs (run_id, user_id, …)`.
   * Directory structure rooted at `processing/<user_id>/<runId>/`.

6. **Additional Notes**

   * Consider delegating large-file transfer to web-server features (`X-Accel-Redirect` for Nginx, `X-Sendfile` for Apache) to reduce PHP memory footprint.
   * Allow optional filename customisation (`filename="pixlkey-<runId>.zip"`) for user clarity.
   * Future release: expose an API endpoint returning a short-lived pre-signed download URL suitable for headless clients.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** — Initial documentation entry; ownership verification (added in v0.4.1-beta) now mandatory, stricter input sanitisation, and explicit 400/403/404 responses formalised.

-----

`/functions.php`

1. **Purpose**
   Houses PixlKey’s shared configuration and helper routines—directory bootstrap, progress streaming, housekeeping, and ImageMagick-driven watermarking—so front-end controllers stay lean.

2. **Agent Role**
   Functions as the **Utility & Media-Processing Agent**, abstracting repetitive low-level chores for higher-level scripts such as `index.php`, `process.php`, and cron tasks.

3. **Key Responsibilities**

   * Define global configuration: maximum upload size, permitted file-types, storage paths.
   * Ensure runtime directories (`/watermarks`, `/processed`) exist with correct permissions.
   * Provide `echoStep()` for real-time browser feedback during long operations.
   * Offer `clearProcessedFiles()` for scheduled cleanup of obsolete artefacts.
   * Execute `addWatermark()` to apply user-supplied and randomised textual watermarks via ImageMagick.

4. **Security Considerations**

   * **Command injection**: all shell calls must use `escapeshellarg`; sanitise `$overlayText` and any user-provided font paths.
   * **Race conditions**: parallel requests could collide on temp filenames—introduce UUID-based naming or flocking.
   * **Path traversal**: rigorously validate filenames/paths before file I/O.
   * **Zip/Decompress bombs**: verify incoming image dimensions and MIME types to mitigate resource exhaustion.
   * **Directory permissions**: created as `0775`; confirm group ownership is restricted to service accounts.
   * **Information leakage**: wrap `echoStep()` behind a debug flag or logger for production to avoid unintended disclosure.

5. **Dependencies**

   * `config.php` (database handle and shared constants)
   * PHP ≥ 7.4, with `shell_exec`, `random_bytes` available
   * ImageMagick CLI tools: `convert`, `identify`
   * Writable filesystem under `$watermarkDir` and `$processedDir`

6. **Additional Notes**

   * Consider migrating from CLI ImageMagick to the Imagick PHP extension for finer error handling and sandboxing.
   * Replace ad-hoc `echoStep()` JavaScript with a central event-stream or WebSocket for cleaner separation.
   * Add PHPUnit tests with mocked shell calls to validate watermark logic without side-effects.
   * Future versions may support SVG watermarks, dynamic overlay-text libraries, and hashed run-directories for isolation.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** – File renamed and refactored for PixlKey: updated namespace comments, improved `echoStep()` JS injection, added multiple randomised text overlays, strengthened error handling.
   
-----

`/index.php`

1. **Purpose**
   Serves as the public and member‐facing landing page for **PixlKey**. It renders the artwork-upload form, recent‐thumbnail galleries, and navigation, acting as the visual gateway between artists and the platform’s backend processing pipeline.

2. **Agent Role**
   Functions as the **UI & Intake Agent**. It gathers user-supplied metadata and image files, injects CSRF tokens, and dispatches the payload to `process.php`. It also surfaces user-specific resources (watermarks, licences, thumbnails) when an authenticated session is present, or a read-only gallery when not.

3. **Key Responsibilities**

   * **Bootstrap**

     * `require_once` of `auth.php`, `config.php`, and `functions.php`.
   * **Thumbnail Retrieval**

     * Query the `images` table for the 10 most recent thumbnails (user-specific when logged in; global otherwise).
   * **Conditional UI Rendering**

     * Member view: personalised nav, two-row thumbnail grid, upload form.
     * Public view: global thumbnail grid plus login / register calls-to-action.
   * **Upload Form**

     * Collect artwork metadata (title, creator, description, genre, keywords, etc.).
     * Gather optional watermark and licence selections (populated from the user’s saved items).
     * Accept up to 20 MB per image, with live previews via vanilla JavaScript.
     * Embed CSRF token (`generate_csrf_token()`) and enforce `require_login()` when gated.
   * **Styling & UX**

     * Inline dark-theme CSS, Orbitron headline font, 5-column adaptive thumbnail grid.
     * Placeholder dashed frames prevent broken‐image icons until a preview is loaded.
   * **Client-side Enhancements**

     * JavaScript `FileReader` previews for watermark and image uploads.
     * Dynamic display of stored watermark thumbnails based on `<select>` choice.

4. **Security Considerations**

   * **CSRF**: Already implemented; ensure token rotation on login/logout.
   * **Session Fixation**: Call `session_regenerate_id(true)` after login in `auth.php`.
   * **File Validation**: `process.php` must whitelist MIME types, file size, and image dimensions; consider additional EXIF scrubbing.
   * **Rate Limiting**: Introduce per-IP and per-user throttling to deter bulk uploads.
   * **XSS**: All echoed values are wrapped in `htmlspecialchars()`, but review `<textarea>` and error messages for edge cases.
   * **Content Security Policy (CSP)**: Recommend adding a strict CSP header to mitigate inline-script risks (currently uses inline JS).
   * **Server-side Storage**: Ensure `watermarks/` and `processed/` paths are not web-browseable without proper ACLs or `.htaccess` rules.

5. **Dependencies**

   * **Internal**

     * `auth.php` – session, CSRF, user helpers.
     * `config.php` – instantiates `$pdo`.
     * `functions.php` – shared utilities.
     * `process.php` – receives form data for watermarking, hashing, and DB insertion.
   * **Database Tables**

     * `images` (`thumbnail_path`, `user_id`, `created_at`).
     * `watermarks`, `licenses`.
   * **External Resources**

     * Google Fonts (Orbitron).
     * Logo image at `./watermarks/pixlkey2.png`.

6. **Additional Notes**

   * Consider migrating inline CSS/JS to versioned static assets for cacheability and CSP compliance.
   * Mobile usability is acceptable but could benefit from smaller thumbnail breakpoints and form field stacking.
   * A drag-and-drop upload zone with progress bars would enhance UX in later versions.
   * Future API consumers may require a JSON endpoint that mirrors this form’s capabilities.
   * Scheduled cleanup of original uploads (10 MB cap) should be handled by a cron‐driven artisan task to avoid disk bloat.

7. **CHANGELOG**

   * **0.4.5-beta (2025-07-12)** – Replaced hard-coded page title and header with randomized `APP_TITLE` / `APP_HEADER` values from `config.php`, supporting dynamic tagline branding per load.
   * **0.4.3-beta (2025-07-11)** – Added CSRF token injection, optional `require_login()` gating, and updated branding from Infinite Muse Arts to PixlKey.
   * **0.4.2-beta (2025-07-10)** – Overhauled dark-theme styling, 5-column responsive thumbnail grid, and UI polish (logo drop-shadow, Orbitron font, dashed preview frames).

-----

`/login.php`

1. **Purpose**
   Presents PixlKey’s sign-in form and performs credential verification, CSRF validation, and brute-force rate-limiting before establishing a user session.

2. **Agent Role**
   Functions as the **Authentication Gateway**, handing off secure, token-protected user sessions to the wider PixlKey application after successful login.

3. **Key Responsibilities**

   * Render an HTML login form with hardened defaults (hidden CSRF token, `novalidate`, minimal inline CSS).
   * Accept `email`, `password`, and optional `next` redirect target.
   * Enforce CSRF protection via `validate_csrf_token()`.
   * Throttle brute-force attempts (`too_many_attempts()`) and lock attackers for 15 minutes after 5 failures.
   * Verify supplied credentials against the `users` table (`password_verify` on `password_hash`).
   * Clear failed-attempt counters and invoke `login_user()` on success, redirecting to `$next`.
   * Accumulate errors for graceful user feedback on the same page.

4. **Security Considerations**

   * **Open-redirect defence**: sanitise or whitelist `$next` to prevent arbitrary redirects.
   * FIXED 0.4.4-beta **Session fixation**: ensure `session_regenerate_id(true)` is called inside `login_user()`.
   * **Timing-side-channel**: always run `password_verify()` even when the e-mail is missing to equalise response time.
   * **Credential stuffing**: pair IP-based limits with (hashed) e-mail-based counters for more granular blocking.
   * **HTTPS & HSTS**: enforce TLS with `Strict-Transport-Security` headers at the web-server level.
   * **2FA readiness**: leave hooks to bolt on TOTP or WebAuthn flows.

5. **Dependencies**

   * `auth.php` – CSRF helpers, `login_user()`, session bootstrap.
   * `rate_limiter.php` – `too_many_attempts()`, `record_failed_attempt()`, `clear_failed_attempts()`.
   * `$pdo` from `config.php` (pulled indirectly via `auth.php`).
   * Database table `users` (columns: `user_id`, `email`, `password_hash`).

6. **Additional Notes**

   * Consider integrating CAPTCHA after several failed attempts to cut down automated abuse.
   * Provide a password-reset path (`forgot_password.php`) and e-mail confirmation workflow.
   * Extract inline CSS to a shared stylesheet for maintainability and theming consistency.
   * Offer a JSON login endpoint for future SPA / mobile clients, re-using the same rate-limiting logic.

7. **CHANGELOG**

   * **2025-07-12 · v0.4.4-beta** – Fixed session fixation vulnerability by ensuring `session_regenerate_id(true)` is called immediately in `login_user()`.
   * **2025-07-11 · v0.4.2-beta** – Initial agent documentation: added CSRF validation, IP-based rate limiter, and password hashing checks to consolidate PixlKey’s login workflow.

-----

`/logout.php`

1. **Purpose**
   Terminates the current user session and returns the visitor to the login screen, ensuring all session data and cookies are purged.

2. **Agent Role**
   Functions as the **Session-Teardown Agent**, guaranteeing a clean logout that prevents residual authentication artefacts from persisting across requests.

3. **Key Responsibilities**

   * Clear all session variables with `session_unset()`.
   * Destroy the PHP session (`session_destroy()`).
   * Expire the session cookie immediately for both HTTP and HTTPS contexts.
   * Redirect the user to `login.php` once teardown completes.

4. **Security Considerations**

   * **Cookie scope**: confirm `path`, `domain`, `secure`, and `httponly` flags mirror those set at login to avoid orphaned cookies.
   * **Cache-Control**: add `header('Cache-Control: no-store')` and `header('Pragma: no-cache')` to prevent cached authenticated pages.
   * **Redirect code**: consider `303 See Other` instead of default `302` to discourage replay of the previous POST.
   * FIXED 0.4.6-beta **Post-logout CSRF token rotation**: regenerates a fresh CSRF token after new session is created, preventing token reuse across sessions.

5. **Dependencies**

   * `auth.php` – provides the active session context and helper configuration.
   * PHP session extension and standard cookie mechanisms.

6. **Additional Notes**

   * For SPA or API clients, expose a JSON response (`204 No Content`) variant rather than a hard redirect.
   * Future versions could log logout events for audit trails (`user_id`, `timestamp`, `ip_address`).

7. **CHANGELOG**

   * **2025-07-14 · v0.4.6-beta** – CSRF hardening:
     - After destroying the current session, a new CSRF token is generated alongside the new session (`session_start()` + `regenerate_id()`).
     - Prevents reuse of CSRF tokens post-logout and blocks privilege carryover.


   * **2025-07-12 · v0.4.4-beta** – Hardened against session fixation:
     - Ensures `session_start()` and `session_regenerate_id(true)` are executed **after** session destruction.
     - Fully reinitialises session post-teardown to invalidate fixation vectors.
     - Preserves secure session flags (`SameSite=Strict`, `Secure`, `HttpOnly`) during reinit.
     - Fixes ordering bug where `session_regenerate_id()` was called on an invalidated session.
   
   * **2025-07-11 · v0.4.2-beta** – Initial inclusion in PixlKey refactor, adds explicit cookie expiry and redirect to login screen.
-----

`/metadata_extractor.php`

1. **Purpose**
   A command-line script that harvests Exif metadata from an image via ExifTool, prunes sensitive fields, and emits a neatly-structured Markdown report—strengthening audit trails inside **PixlKey**.

2. **Agent Role**
   Serves as the **Metadata Extraction Agent**, translating raw, machine-oriented Exif JSON into human-readable documentation for provenance, licensing audits, and version control.

3. **Key Responsibilities**

   * Parse `--input` and `--output` CLI arguments.
   * Verify the target file exists and ExifTool is accessible in `$PATH`.
   * Run ExifTool with JSON output and capture results.
   * Remove predefined sensitive or noisy fields.
   * Map cryptic Exif keys to friendly labels.
   * Group data into logical sections (Basic Info, Technical Details, Identifiers, etc.).
   * Render the final Markdown table and write it to the specified path.

4. **Security Considerations**

   * **Shell safety**: wraps all shell calls with `escapeshellcmd()` / `escapeshellarg()` to block injection.
   * **Sensitive-field filtering**: excludes directory paths, inode data, rights, and similar private artefacts.
   * **Output sanitisation**: applies `htmlspecialchars()` on values to prevent Markdown injection.
   * **Path hygiene**: output location is taken verbatim from CLI—consider resolving to an allowed directory and checking write permissions.
   * **Error leakage**: STDERR currently prints raw ExifTool errors; filter or mask if exposed to untrusted users.
   * **Resource limits**: large or malformed files could exhaust memory; add size checks and time-outs in future.

5. **Dependencies**

   * PHP ≥ 7.x (CLI)
   * ExifTool (CLI) available in system `$PATH`
   * `config.php` **not** required—script is self-contained.
   * Local filesystem write access for the Markdown target.

6. **Additional Notes**

   * Designed strictly for CLI; if integrated into a web workflow, wrap access in the usual CSRF/session guards used elsewhere in PixlKey.
   * Consider externalising the field-exclusion list to a YAML/INI conf for easier updates.
   * Future enhancements: direct PDF export, automatic upload of the report into PixlKey’s registry, or embedding a cryptographic hash of the report back into the image’s XMP block.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** – Initial import into PixlKey: added `Rights` and `SourceFile` to exclusion list, reorganised section headings, and tightened shell-argument escaping.

-----

`/my_licenses.php`

1. **Purpose**
   Presents a secure, in-browser dashboard where each PixlKey user can create, preview (Markdown → HTML), update, delete, and set a *default* licence text that will be attached to future uploads.

2. **Agent Role**
   Functions as the **Licence-Manager Agent**, bridging the database and the UI so artists always have a single source of truth for their reusable licensing terms.

3. **Key Responsibilities**

   * Enforce authenticated access via `require_login()`.
   * Validate CSRF tokens for every non-GET request.
   * Persist licence records (`INSERT`, `UPDATE`, `DELETE`) in the `licenses` table, scoped to the current `user_id`.
   * Guarantee only *one* default licence per user by clearing existing defaults before setting a new one.
   * Render licence text safely by converting Markdown with `Parsedown` in Safe Mode.
   * Provide a JavaScript helper to pre-fill the edit form for quick inline updates.

4. **Security Considerations**

   * **XSS**: `Parsedown::setSafeMode(true)` blocks raw HTML but may still allow crafted links; consider an additional HTML-purifier pass or migrate to `league/commonmark` with the security extension.
   * **Session fixation**: regenerate PHP session ID immediately after login (handled in `auth.php` roadmap).
   * **Token rotation**: rotate CSRF token on login/logout to reduce replay risk.
   * **Clickjacking**: add `X-Frame-Options: DENY` or a Content-Security-Policy header.
   * **Race condition**: wrapping the “clear defaults + set new default” logic in a DB transaction (or enforcing with an `ON UPDATE` trigger) prevents dual defaults under heavy concurrency.
   * **Rate limiting**: throttle repeated POSTs to discourage brute-force or automated spam.

5. **Dependencies**

   * `auth.php` – session, CSRF, and user helpers.
   * `config.php` – supplies the PDO handle `$pdo`.
   * `vendor/parsedown/Parsedown.php` (MIT) – Markdown parser.
   * Database table `licenses` (`license_id UUID`, `user_id`, `name`, `text_blob`, `is_default`, `created_at`).
   * Inline JavaScript function `editLicence()` for client-side form population.

6. **Additional Notes**

   * Store a *revision history* or `updated_at` field so users can roll back licence edits.
   * Offer licence *export/import* (JSON or SPDX) for interoperability.
   * Add pagination or search once licence counts grow large.
   * Consider a REST/GraphQL endpoint mirroring this CRUD logic for future SPA/mobile clients.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** – Initial integration into PixlKey: built CSRF-protected CRUD flow, Markdown preview with Parsedown Safe Mode, and single-default enforcement.
   
-----

`/my_watermarks.php`

1. **Purpose**
   Provides a self-service dashboard where logged-in artists can upload, list, designate a default, and delete personal watermark images (PNG, JPG/JPEG, or WEBP) for use throughout **PixlKey**.

2. **Agent Role**
   Functions as the **Watermark-CRUD Agent**, interfacing between the user, the filesystem, and the database to maintain each creator’s private watermark library.

3. **Key Responsibilities**

   * Verify the session and enforce authentication (`require_login()`).
   * Accept watermark uploads, validating extension and moving the file to a per-user directory.
   * Insert metadata into the `watermarks` table, auto-marking the first entry as default.
   * Allow users to:

     * **Set default** – toggles `is_default` for the chosen watermark.
     * **Delete** – removes both the DB record and the physical file.
   * Display the full watermark list with thumbnail previews and action buttons.
   * Surface success / error messages returned from each action.

4. **Security Considerations**

   * **MIME sniffing**: verify uploaded file type via `finfo_file()` or `getimagesize()` instead of trusting the extension.
   * **Path traversal**: sanitise `$uploadDir` construction and ensure `basename()` checks before deletion.
   * **File overwrites**: `uniqid()` is collision-safe but consider `bin2hex(random_bytes())` for stronger entropy.
   * **Quota / size limits**: enforce per-upload and per-user disk quotas to mitigate DoS-style abuse.
   * **Per-user isolation**: store watermarks outside the public web root or serve them via a controller that checks ownership.
   * **CSRF double submit**: tokens are present, but rotate them post-action to reduce token replay risk.

5. **Dependencies**

   * `auth.php` – session, CSRF, and user helpers.
   * `config.php` – provides `$pdo` connection and base configuration.
   * Database table `watermarks` with columns: `watermark_id` (UUID), `user_id`, `filename`, `path`, `is_default`, `uploaded_at`.
   * PHP extensions: `fileinfo`, `gd`/`imagick` (optional future format validation).

6. **Additional Notes**

   * Consider adding a **preview overlay** that shows the watermark atop a sample artwork before saving as default.
   * UI could benefit from drag-and-drop uploads and client-side validation (max dimensions, file weight).
   * Future release: allow SVG watermarks with strict sanitisation (e.g., `svg-sanitizer`).
   * Provide an audit trail so users can restore accidentally deleted watermarks.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** – Initial implementation of dedicated watermark dashboard; introduces per-user upload directories, default-selection logic, and CRUD actions via a single controller page.

-----

`/process_helpers.php`

1. **Purpose**
   Supplies reusable helper functions for server-side image preparation in **PixlKey**—most notably real-time progress messaging (`echoStep()`) and automated, proportionally-scaled watermark application (`addWatermark()`).

2. **Agent Role**
   Operates as the **Image Processing Utility Agent**, bridging user-facing workflows and low-level ImageMagick commands to ensure every uploaded artwork is uniformly watermarked and that users (or CLI operators) receive live feedback during lengthy batch runs.

3. **Key Responsibilities**

   * Import global configuration via `config.php`.
   * Stream incremental status updates to the browser (or console) through `echoStep()`, auto-scrolling the UI step log.
   * Watermark images with `addWatermark()`:

     * Detect source image width.
     * Resize the watermark to \~6 % of that width.
     * Position it bottom-right with a margin (\~1 %).
     * Clean up temporary artefacts after compositing.
   * Define shared constants:

     * `$defaultWatermark` – fallback watermark asset (`watermarks/pixlkey_signature_black.png`).
     * `$allowedExtensions` – permitted image file types array.

4. **Security Considerations**

   * **Shell execution**: both `identify` and `convert` commands rely on `shell_exec()`—all paths are wrapped in `escapeshellarg()`, yet inputs must remain trusted (never accept user-supplied paths).
   * **Image validation**: confirm MIME type and size limits before processing to prevent ImageMagick exploits.
   * **Path traversal / symlinks**: guard `$runDir` and `$imagePath` against traversal; resolve realpath and ensure inside an expected directory.
   * **Resource usage**: ImageMagick can consume extensive RAM/CPU on crafted files—apply policy limits (`policy.xml`) or delegate to a sandbox.
   * **Output flushing**: `flush()` is safe but could leak timing information; consider buffering when multi-tenant scaling.

5. **Dependencies**

   * `config.php` – central configuration / database handle.
   * ImageMagick CLI utilities (`identify`, `convert`).
   * PHP ≥ 7.4 (typed functions, `declare(strict_types=1)`).
   * Directory structure containing `/watermarks/pixlkey_signature_black.png` (or an alternative configured watermark).

6. **Additional Notes**

   * Functions are wrapped with `function_exists()` guards, enabling safe multiple inclusions.
   * For future releases:

     * Migrate to a Namespaced class (e.g., `PixlKey\Image\Watermarker`) to reduce global symbols.
     * Allow dynamic watermark opacity and placement via parameters or database flags.
     * Expose a CLI flag (`--dry-run`) that logs shell commands without executing, aiding debugging.
     * Introduce a pluggable image backend (e.g., GD, Intervention Image) for environments lacking ImageMagick.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** – Renamed default watermark to `pixlkey_signature_black.png`; updated inline documentation; added stricter `$allowedExtensions` defaults and fortified `echoStep()` against XSS via `json_encode()`.

-----

`/process.php`

1. **Purpose**
   Executes the end-to-end image-processing workflow for **PixlKey** whenever a user submits the main upload form. It ingests artwork files and associated metadata, transforms and watermarks each image, embeds IPTC/XMP information, generates thumbnails, extracts a metadata report, issues a Markdown certificate of authenticity, and finally packages all deliverables into a ZIP archive for download.

2. **Agent Role**
   Operates as the **Pipeline Orchestrator Agent**. It stitches together multiple helper libraries, shell utilities, and database calls, while streaming human-readable progress updates back to the browser so users can monitor each processing step in real time.

3. **Key Responsibilities**

   * Initialise runtime (disable buffering, set no-cache headers, emit progress UI).
   * Enforce authentication/CSRF checks (`require_login()`, `validate_csrf_token()`).
   * Parse, sanitise, and validate all form inputs (dates, text fields, keywords).
   * Resolve the active watermark (saved vs. one-off upload) and licence text for embedding.
   * Enforce per-file upload constraints (≤ 200 MB; allowed extensions).
   * For every uploaded artwork file:

     * Move it into a user-scoped *run* directory with a unique `runId`.
     * Strip pre-existing metadata (`exiftool -all=`) and convert to PNG.
     * Create a “signed” copy, resize and composite watermark, compute SHA-256 hashes.
     * Embed rich IPTC/XMP metadata and intellectual-genre tags.
     * Generate a 400 px thumbnail and 800 px preview, apply watermark if present.
     * Persist original/thumbnail details to MariaDB and log the run in `processing_runs`.
     * Extract full metadata into a Markdown report via `metadata_extractor.php`.
     * Produce a formal certificate of authenticity (`*.md`).
   * Bundle all final assets into `final_assets.zip` and present a download link via `download_zip.php`.

4. **Security Considerations**

   * **Shell injection**: although `escapeshellarg()` is used, full command strings (`$cmdZip`, `$cmdComposite`, etc.) are still assembled via interpolation; consider `proc_open()` with arg arrays.
   * **Path traversal**: filenames are sanitised, but the relative paths written to the DB should also be validated.
   * **Session fixation**: ensure `session_regenerate_id(true)` is executed on login (handled in `auth.php`).
   * **Resource exhaustion**: ImageMagick and `exiftool` can be CPU-/RAM-intensive; add timeout or memory limits (e.g., `ulimit`, `-limit`) to prevent DoS.
   * **Unbounded run directories**: periodic cleanup or quota enforcement is required to stop disk-space bloat.
   * **ZIP poisoning**: explicitly disallow “dot-dot” filenames when adding files to the archive (though only server-generated files are currently zipped).
   * **Output verbosity**: STDERR from shell commands is echoed directly; in production redirect to a secure log and mask internal paths.

5. **Dependencies**

   * **PHP** ≥ 8.1 with extensions: `PDO`, `file_uploads`, `openssl` (for `random_bytes`).
   * **Helper scripts**: `auth.php`, `config.php`, `functions.php`, `process_helpers.php`, `metadata_extractor.php`.
   * **CLI tools**: `exiftool`, `convert`/`identify` (ImageMagick), `sha256sum`, `zip`.
   * **Database**: Tables `processing_runs`, `images`, `watermarks`, `licenses`, `users`.
   * Writable processing root (`/processing`) outside the public web root, protected via `.htaccess` or Nginx rules.

6. **Additional Notes**

   * Live HTML streaming is user-friendly but fragile behind certain reverse proxies; consider migrating heavy operations to an asynchronous queue (e.g., Gearman, Symfony Messenger, or a simple cron worker) and polling job status via AJAX/WebSockets.
   * Split monolithic logic into smaller service classes (UploadHandler, WatermarkService, MetadataService, ArchiveBuilder) for testability.
   * Future versions could auto-register SHA-256 fingerprints to an external notarisation service or blockchain anchor for stronger provenance.
   * Internationalisation: expose metadata fields in multiple locales; validate input against UTF-8 and normalise NFC.

7. **CHANGELOG**

   * **2025-07-11 – v0.4.2-beta**
     *Initial PixlKey refactor:* renamed UI strings, added CSRF validation call, user-scoped run directories, full IPTC/XMP embedding, metadata extraction step, certificate generation, and ZIP packaging with authenticated `runId` tracking.

-----

`/rate_limiter.php`

1. **Purpose**
   Implements a lightweight, session-scoped rate-limiting utility to curb brute-force or abuse attempts (e.g., login, registration) within **PixlKey**.

2. **Agent Role**
   Serves as a **Security Utility Agent**, supplying plug-and-play functions that any controller can call to detect and throttle rapid-fire requests tied to the same identifier (IP, username, or other token).

3. **Key Responsibilities**

   * Maintain an in-memory (session) log of recent request timestamps keyed by a caller-supplied string.
   * Evaluate whether the caller has exceeded a configurable threshold (`$maxAttempts`) within a decay window (`$decaySeconds`).
   * Expose helpers to record (`record_failed_attempt()`), test (`too_many_attempts()`), and clear (`clear_failed_attempts()`) violations.

4. **Security Considerations**

   * **Ephemeral scope**: Session storage disappears on logout, expiry, or server restart; consider Redis or database persistence for clustered or long-lived protection.
   * **Identity spoofing**: If `$_SERVER['REMOTE_ADDR']` is used as the key, reverse proxies/VPNs can evade limits—combine with user agent, account ID, or proof-of-work.
   * **Session fixation**: Ensure the calling script has already started a secure session (`cookie_httponly`, `cookie_secure`, `SameSite=Strict`).
   * **Complementary controls**: This script throttles but does not block traffic; pair with server-level defences (ModSecurity, fail2ban, Cloudflare Rate Limiting).

5. **Dependencies**

   * PHP sessions (`$_SESSION`) must be initialised (`session_start()`) **before** any function call.
   * No database requirement, but designed to coexist with the PDO instance loaded by `config.php`.
   * Typically included in `login.php`, `register.php`, or any endpoint vulnerable to spamming.

6. **Additional Notes**

   * Thresholds and decay durations are hard-coded by the caller; expose app-wide config constants for easier tuning.
   * Future revisions may:

     * Migrate storage to Redis for horizontal scaling.
     * Emit audit logs for SIEM correlation.
     * Support exponential back-off or CAPTCHA escalation after repeated lockouts.

7. **CHANGELOG**

   * **2025-07-11 · v0.4.2-beta** — Initial introduction of `rate_limiter.php` to PixlKey codebase with three core helpers (`too_many_attempts`, `record_failed_attempt`, `clear_failed_attempts`).

-----

`/register.php`

1. **Purpose**
   Presents the self-service sign-up form and performs all server-side logic required to create a new user account in **PixlKey**.

2. **Agent Role**
   Functions as the **Registration Agent**, bridging the public-facing interface and the authentication subsystem: it validates credentials, enforces rate limits, writes the new record to the `users` table, then seamlessly authenticates the newcomer.

3. **Key Responsibilities**

   * Render a minimal, dark-themed HTML registration form.
   * Enforce CSRF protection via `validate_csrf_token()`.
   * Throttle abusive sign-ups (max 5 attempts / IP / 30 min) with `rate_limiter.php`.
   * Validate inputs (RFC-5322 e-mail, password confirmation, ≥ 8-char length).
   * Ensure e-mail uniqueness before insertion.
   * Persist user with `password_hash()` (bcrypt/argon via `PASSWORD_DEFAULT`).
   * Clear rate-limit counters on success and call `login_user()` for instant access.
   * Redirect to `index.php` post-registration or surface human-readable errors.

4. **Security Considerations**

   * FIXED 0.4.6-beta **Session fixation & CSRF rotation**:
     - Call `session_regenerate_id(true)` before `login_user()` to prevent fixation attacks.
     - Rotate CSRF token (`$_SESSION['csrf_token'] = …`) immediately before login to ensure token isolation across privilege levels.
   * **Password policy**: consider enforcing complexity (upper/lower/number/symbol) and breached-password checks (e.g., Have I Been Pwned API).
   * **E-mail verification**: add double-opt-in workflow to stop disposable or mistyped addresses.
   * **Bot defence**: integrate CAPTCHA or address reputation scoring in addition to IP rate limiting.
   * **HTML escaping**: error output already uses `htmlspecialchars`, but ensure any future templating remains XSS-safe.
   * **Transport security**: mandate HTTPS for all requests, not simply detect it.

5. **Dependencies**

   * `auth.php` – session, CSRF utilities, and `login_user()`.
   * `rate_limiter.php` – `too_many_attempts()`, `record_failed_attempt()`, `clear_failed_attempts()`.
   * Database table `users` (`email`, `password_hash`, `display_name`, etc.) via `$pdo`.
   * PHP extensions: `pdo_mysql` (or relevant driver), `openssl`/`random_bytes`.

6. **Additional Notes**

   * UI/UX could benefit from progressive enhancement (client-side validation, strength meter).
   * Consider sending a welcome/verification e-mail with an expiring token before auto-login.
   * Future release might expose an API endpoint (`/api/register`) that re-uses this logic under JSON.
   * Style sheet is inline; migrate to a dedicated CSS file for maintainability.

7. **CHANGELOG**

   * **2025-07-14 · v0.4.6-beta** – Hardened CSRF boundary:
     - Added CSRF token rotation after session ID regeneration, before calling `login_user()`.
     - Prevents token reuse or carryover from anonymous to authenticated context during registration.

   * **2025-07-11 · v0.4.2-beta** – Introduced `register.php`: rate-limited sign-up, CSRF guard, password hashing, and automatic post-registration login for PixlKey.

-----

`/store_data.php`

1. **Purpose**
   Ingests the results of a processed-image workflow (identified by `runId`) and persists all related artefacts—metadata, signed images, certificates, AI data—into the **PixlKey** relational database.

2. **Agent Role**
   Functions as the **Data-Ingestion & Persistence Agent**, translating a temporary, per-run directory on disk into normalised, referentially-sound records across the project’s core tables.

3. **Key Responsibilities**

   * **Authorisation** – confirm the current user owns the supplied `runId` via `processing_runs`.
   * **CSRF & session checks** – enforce token validation on POST and require an authenticated session.
   * **Filesystem validation** – ensure `processed/<runId>/` exists and contains expected artefacts.
   * **Atomic transaction** – wrap the entire import in `BEGIN … COMMIT | ROLLBACK` for consistency.
   * **Parse & map** – read `data.json`, `submission.json`, \*\_metadata.txt, \*\_certificate.md, \*\_ai\_metadata.json, then:

     * Create an `Artworks` record with generated UUIDs.
     * Compute SHA-256 for each `_signed.png`; store in `Images`.
     * Insert certificates, AI metadata, and submission details.
     * De-duplicate and link keywords, genres, creators, and bylines via many-to-many tables.
   * **Error handling** – surface readable errors in debug mode; generic messages otherwise.

4. **Security Considerations**

   * FIXED 0.4.6-beta **CSRF token rotation** – regenerates CSRF token after session ID is refreshed to prevent token reuse during data ingestion.
   * **Session fixation** – regenerate session ID after login (handled in `auth.php`, but essential here).
   * **Directory traversal** – cast/validate `runId` against a strict UUID regex before path building.
   * **Least-privilege storage** – keep `processed/` outside the web-root or protect via web-server ACLs.
   * **Privilege escalation** – verify `processing_runs.user_id` on every access, not just once.
   * **SQL injection** – currently mitigated with PDO prepared statements; maintain strict parameter binding.
   * **Oversized uploads** – add file-size caps and MIME-type whitelists before hashing or DB insert.
   * **Race conditions** – lock the row in `processing_runs` during import to prevent concurrent re-runs.

5. **Dependencies**

   * Internal: `auth.php`, `config.php`, `process_helpers.php`.
   * PHP extensions: `PDO`, `PDO_MYSQL`, `json`, `openssl` (for `random_bytes`).
   * Database schema: `Artworks`, `Images`, `Certificates`, `AIMetadata`, `Keywords`, `Genres`, `Creators`, `Bylines`, `Submissions`, plus junction tables (`ArtworkKeywords`, `ArtworkGenres`, etc.).
   * Directory layout: `processed/<runId>/` containing `data.json`, `submission.json`, signed images, certificates, and optional AI-metadata files.

6. **Additional Notes**

   * `getOrCreateId()` assumes `lastInsertId()` returns a UUID; ensure MySQL’s `UUID()` is the default for primary keys or return the generated value directly.
   * Consider extracting keyword/genre/creator insertion into reusable service classes for testability.
   * A future **v0.5** milestone could emit a blockchain-anchored provenance hash after successful commit.
   * Add server-side file-type inspection (e.g., `finfo_file`) before processing to harden against spoofed MIME types.

7. **CHANGELOG**

   * **2025-07-14 · v0.4.6-beta** – CSRF hardening:
     - Added `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))` after session regeneration.
     - Prevents post-login reuse of stale tokens in authenticated ingestion flows.

   * **2025-07-12 · v0.4.4-beta** – Session hardening and integrity fix.

     * Added `session_regenerate_id(true)` at entry to reinforce session integrity before sensitive writes.
     * Confirmed defensive mitigation of session fixation if user reaches this endpoint outside of normal login flow.
     * No structural changes to processing or database logic; patch focused on runtime session security.

   * **2025-07-11 · v0.4.2-beta** – First PixlKey-branded version.

     * Rebranded schema references from *Infinite Image Tools* to **PixlKey**.
     * Added ownership check against `processing_runs`.
     * Wrapped import in a single database transaction for atomicity.