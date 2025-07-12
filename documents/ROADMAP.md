## PixlKey Product Roadmap

`/auth.php`
   * Exports are deliberately minimal; keep business-logic-free to simplify unit tests.
   * Future releases might expose a JSON Web Token (JWT) endpoint for API access while preserving the same CSRF model for browser forms.
   * Evaluate migrating to `SameSite=Lax` with exception lists if third-party integrations are required.

`/config.php`
   * Replace `infinite_image_tools` default DB name/user once production credentials are finalised.
   * Future: add automatic retry / exponential back-off for high-latency containerised deployments.
   * Consider migrating to MySQL *read replica* pool support via PDO attributes for scaling.

`/admin_dashboard.php` (Not Yet Implemented)
   * Introduce RBAC-gated Admin Console (logs, disk usage, queue health). Requires is_admin=1.

`/download_zip.php`
   * Consider delegating large-file transfer to web-server features (`X-Accel-Redirect` for Nginx, `X-Sendfile` for Apache) to reduce PHP memory footprint.
   * Allow optional filename customisation (`filename="pixlkey-<runId>.zip"`) for user clarity.
   * Future release: expose an API endpoint returning a short-lived pre-signed download URL suitable for headless clients.

`/functions.php`
   * Consider migrating from CLI ImageMagick to the Imagick PHP extension for finer error handling and sandboxing.
   * Replace ad-hoc `echoStep()` JavaScript with a central event-stream or WebSocket for cleaner separation.
   * Add PHPUnit tests with mocked shell calls to validate watermark logic without side-effects.
   * Future versions may support SVG watermarks, dynamic overlay-text libraries, and hashed run-directories for isolation.
   
`/index.php`
   * Consider migrating inline CSS/JS to versioned static assets for cacheability and CSP compliance.
   * Mobile usability is acceptable but could benefit from smaller thumbnail breakpoints and form field stacking.
   * A drag-and-drop upload zone with progress bars would enhance UX in later versions.
   * Future API consumers may require a JSON endpoint that mirrors this form’s capabilities.
   * Scheduled cleanup of original uploads (10 MB cap) should be handled by a cron‐driven artisan task to avoid disk bloat.

`/install.php` (Not Yet Implemented)
   * Ship first-run installer to collect paths, choose image backend, and create initial admin.
      * Auto-create install.lock and deny access once setup completes.

`/login.php`
   * Consider integrating CAPTCHA after several failed attempts to cut down automated abuse.
   * Provide a password-reset path (`forgot_password.php`) and e-mail confirmation workflow.
   * Extract inline CSS to a shared stylesheet for maintainability and theming consistency.
   * Offer a JSON login endpoint for future SPA / mobile clients, re-using the same rate-limiting logic.
   
`/logout.php`
   * For SPA or API clients, expose a JSON response (`204 No Content`) variant rather than a hard redirect.
   * Future versions could log logout events for audit trails (`user_id`, `timestamp`, `ip_address`).
   
`/metadata_extractor.php`
   * Designed strictly for CLI; if integrated into a web workflow, wrap access in the usual CSRF/session guards used elsewhere in PixlKey.
   * Consider externalising the field-exclusion list to a YAML/INI conf for easier updates.
   * Future enhancements: direct PDF export, automatic upload of the report into PixlKey’s registry, or embedding a cryptographic hash of the report back into the image’s XMP block.
   
`/my_licenses.php`
   * Store a *revision history* or `updated_at` field so users can roll back licence edits.
   * Offer licence *export/import* (JSON or SPDX) for interoperability.
   * Add pagination or search once licence counts grow large.
   * Consider a REST/GraphQL endpoint mirroring this CRUD logic for future SPA/mobile clients.
   * Refactor views to consume layout.php + dashboard.css for consistent styling.

`/my_watermarks.php`
   * Consider adding a **preview overlay** that shows the watermark atop a sample artwork before saving as default.
   * UI could benefit from drag-and-drop uploads and client-side validation (max dimensions, file weight).
   * Future release: allow SVG watermarks with strict sanitisation (e.g., `svg-sanitizer`).
   * Provide an audit trail so users can restore accidentally deleted watermarks.
   * Refactor views to consume layout.php + dashboard.css for consistent styling.
   
`/process_helpers.php`
   * Migrate to a Namespaced class (e.g., `PixlKey\Image\Watermarker`) to reduce global symbols.
   * Allow dynamic watermark opacity and placement via parameters or database flags.
   * Expose a CLI flag (`--dry-run`) that logs shell commands without executing, aiding debugging.
   * Introduce a pluggable image backend (e.g., GD, Intervention Image) for environments lacking ImageMagick.
   
`/process.php`
   * Live HTML streaming is user-friendly but fragile behind certain reverse proxies; consider migrating heavy operations to an asynchronous queue (e.g., Gearman, Symfony Messenger, or a simple cron worker) and polling job status via AJAX/WebSockets.
   * Split monolithic logic into smaller service classes (UploadHandler, WatermarkService, MetadataService, ArchiveBuilder) for testability.
   * Future versions could auto-register SHA-256 fingerprints to an external notarisation service or blockchain anchor for stronger provenance.
   * Internationalisation: expose metadata fields in multiple locales; validate input against UTF-8 and normalise NFC.
   * Integrate Python-based HFV key (code already written) and persist result alongside SHA-256 in database.

`/rate_limiter.php`
   * Thresholds and decay durations are hard-coded by the caller; expose app-wide config constants for easier tuning.
   * Future revisions may:
     * Migrate storage to Redis for horizontal scaling.
     * Emit audit logs for SIEM correlation.
     * Support exponential back-off or CAPTCHA escalation after repeated lockouts.
     * Ensure verify-image endpoint is covered by per-IP/user rate limits to deter brute-force look-ups.

`/register.php`
   * UI/UX could benefit from progressive enhancement (client-side validation, strength meter).
   * Consider sending a welcome/verification e-mail with an expiring token before auto-login.
   * Future release might expose an API endpoint (`/api/register`) that re-uses this logic under JSON.
   * Style sheet is inline; migrate to a dedicated CSS file for maintainability.
   
`/store_data.php`
   * `getOrCreateId()` assumes `lastInsertId()` returns a UUID; ensure MySQL’s `UUID()` is the default for primary keys or return the generated value directly.
   * Consider extracting keyword/genre/creator insertion into reusable service classes for testability.
   * A future **v0.5** milestone could emit a blockchain-anchored provenance hash after successful commit.
   * Add server-side file-type inspection (e.g., `finfo_file`) before processing to harden against spoofed MIME types.

`/verify_image.php` (Not Yet Implemented)
   * Add POST /api/verify_image.php that returns 200/404/409 after constant-time SHA-256 + HFV comparison.