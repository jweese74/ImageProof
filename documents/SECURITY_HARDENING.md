## PixlKey Security Hardening Checklist

`/auth.php`
   * Fixed 0.4.4-beta **Session fixation**: call `session_regenerate_id(true)` immediately after successful login.
   * **Token rotation**: rotate CSRF token post-login/logout to prevent token reuse.
   * **Rate limiting / brute-force**: implement throttling on `login_user()` calls.
   * **Password verification**: authentication flow (currently elsewhere) must use `password_hash()` / `password_verify()`.
   * **Strict transport**: enforce HTTPS globally, not merely detect it.
   * **Same Origin Policy**: consider adding `header('X-Frame-Options: DENY')` in a central bootstrap.

`/config.php`
   * **Fallback credentials**: default `DB_PASS` is hard-coded; override via environment or secret manager in production.
   * **Debug leakage**: `DB_DEBUG=true` exposes exception details to clients; keep *false* outside dev.
   * **Credential scope**: ensure file permissions (`chmod 600`) prevent unauthorised reads.
   * **Transport security**: if DB is remote, use TLS-encrypted client connections.
   * **Secrets rotation**: consider runtime reload or container secret mounts to avoid redeploys purely for key rotation.

`/download_zip.php`
   * **Session fixation**: call `session_regenerate_id(true)` after login (handled in `auth.php`).
   * **Path traversal**: current whitelist regex mitigates most attacks; additionally consider using `realpath()` and confirming the resolved path begins with the expected base directory.
   * **Timing attacks**: fetch ownership with a constant-time comparison (`hash_equals()` on IDs) to avoid user-enumeration via response timing.
   * **Download abuse**: add rate-limiting or signed, expiring URLs to curb hot-linking and scraping.
   * **MIME sniffing**: send `X-Content-Type-Options: nosniff`.
   * **Audit logging**: log successful downloads (user, IP, timestamp) for traceability.

`/functions.php`
   * **Command injection**: all shell calls must use `escapeshellarg`; sanitise `$overlayText` and any user-provided font paths.
   * **Race conditions**: parallel requests could collide on temp filenames—introduce UUID-based naming or flocking.
   * **Path traversal**: rigorously validate filenames/paths before file I/O.
   * **Zip/Decompress bombs**: verify incoming image dimensions and MIME types to mitigate resource exhaustion.
   * **Directory permissions**: created as `0775`; confirm group ownership is restricted to service accounts.
   * **Information leakage**: wrap `echoStep()` behind a debug flag or logger for production to avoid unintended disclosure.

`/index.php`
   * **CSRF**: Already implemented; ensure token rotation on login/logout.
   * **Session Fixation**: Call `session_regenerate_id(true)` after login in `auth.php`.
   * **File Validation**: `process.php` must whitelist MIME types, file size, and image dimensions; consider additional EXIF scrubbing.
   * **Rate Limiting**: Introduce per-IP and per-user throttling to deter bulk uploads.
   * **XSS**: All echoed values are wrapped in `htmlspecialchars()`, but review `<textarea>` and error messages for edge cases.
   * **Content Security Policy (CSP)**: Recommend adding a strict CSP header to mitigate inline-script risks (currently uses inline JS).
   * **Server-side Storage**: Ensure `watermarks/` and `processed/` paths are not web-browseable without proper ACLs or `.htaccess` rules.

`/login.php`
   * **Open-redirect defence**: sanitise or whitelist `$next` to prevent arbitrary redirects.
   * Fixed 0.4.4-beta **Session fixation**: ensure `session_regenerate_id(true)` is called inside `login_user()`.
   * **Timing-side-channel**: always run `password_verify()` even when the e-mail is missing to equalise response time.
   * **Credential stuffing**: pair IP-based limits with (hashed) e-mail-based counters for more granular blocking.
   * **HTTPS & HSTS**: enforce TLS with `Strict-Transport-Security` headers at the web-server level.
   * **2FA readiness**: leave hooks to bolt on TOTP or WebAuthn flows.

`/logout.php`
   * **Cookie scope**: confirm `path`, `domain`, `secure`, and `httponly` flags mirror those set at login to avoid orphaned cookies.
   * **Cache-Control**: add `header('Cache-Control: no-store')` and `header('Pragma: no-cache')` to prevent cached authenticated pages.
   * **Redirect code**: consider `303 See Other` instead of default `302` to discourage replay of the previous POST.
   * Fixed 0.4.4-beta **Post-logout CSRF token rotation**: regenerate a fresh token if a new session is started immediately afterwards.

`/metadata_extractor.php`
   * **Shell safety**: wraps all shell calls with `escapeshellcmd()` / `escapeshellarg()` to block injection.
   * **Sensitive-field filtering**: excludes directory paths, inode data, rights, and similar private artefacts.
   * **Output sanitisation**: applies `htmlspecialchars()` on values to prevent Markdown injection.
   * **Path hygiene**: output location is taken verbatim from CLI—consider resolving to an allowed directory and checking write permissions.
   * **Error leakage**: STDERR currently prints raw ExifTool errors; filter or mask if exposed to untrusted users.
   * **Resource limits**: large or malformed files could exhaust memory; add size checks and time-outs in future.

`/my_licenses.php`
   * **XSS**: `Parsedown::setSafeMode(true)` blocks raw HTML but may still allow crafted links; consider an additional HTML-purifier pass or migrate to `league/commonmark` with the security extension.
   * **Session fixation**: regenerate PHP session ID immediately after login (handled in `auth.php` roadmap).
   * **Token rotation**: rotate CSRF token on login/logout to reduce replay risk.
   * **Clickjacking**: add `X-Frame-Options: DENY` or a Content-Security-Policy header.
   * **Race condition**: wrapping the “clear defaults + set new default” logic in a DB transaction (or enforcing with an `ON UPDATE` trigger) prevents dual defaults under heavy concurrency.
   * **Rate limiting**: throttle repeated POSTs to discourage brute-force or automated spam.
   
`/my_watermarks.php`
   * **MIME sniffing**: verify uploaded file type via `finfo_file()` or `getimagesize()` instead of trusting the extension.
   * **Path traversal**: sanitise `$uploadDir` construction and ensure `basename()` checks before deletion.
   * **File overwrites**: `uniqid()` is collision-safe but consider `bin2hex(random_bytes())` for stronger entropy.
   * **Quota / size limits**: enforce per-upload and per-user disk quotas to mitigate DoS-style abuse.
   * **Per-user isolation**: store watermarks outside the public web root or serve them via a controller that checks ownership.
   * **CSRF double submit**: tokens are present, but rotate them post-action to reduce token replay risk.

`/process_helpers.php`
   * **Shell execution**: both `identify` and `convert` commands rely on `shell_exec()`—all paths are wrapped in `escapeshellarg()`, yet inputs must remain trusted (never accept user-supplied paths).
   * **Image validation**: confirm MIME type and size limits before processing to prevent ImageMagick exploits.
   * **Path traversal / symlinks**: guard `$runDir` and `$imagePath` against traversal; resolve realpath and ensure inside an expected directory.
   * **Resource usage**: ImageMagick can consume extensive RAM/CPU on crafted files—apply policy limits (`policy.xml`) or delegate to a sandbox.
   * **Output flushing**: `flush()` is safe but could leak timing information; consider buffering when multi-tenant scaling.

`/process.php`
   * **Shell injection**: although `escapeshellarg()` is used, full command strings (`$cmdZip`, `$cmdComposite`, etc.) are still assembled via interpolation; consider `proc_open()` with arg arrays.
   * **Path traversal**: filenames are sanitised, but the relative paths written to the DB should also be validated.
   * **Session fixation**: ensure `session_regenerate_id(true)` is executed on login (handled in `auth.php`).
   * **Resource exhaustion**: ImageMagick and `exiftool` can be CPU-/RAM-intensive; add timeout or memory limits (e.g., `ulimit`, `-limit`) to prevent DoS.
   * **Unbounded run directories**: periodic cleanup or quota enforcement is required to stop disk-space bloat.
   * **ZIP poisoning**: explicitly disallow “dot-dot” filenames when adding files to the archive (though only server-generated files are currently zipped).
   * **Output verbosity**: STDERR from shell commands is echoed directly; in production redirect to a secure log and mask internal paths.

`/rate_limiter.php`
   * **Ephemeral scope**: Session storage disappears on logout, expiry, or server restart; consider Redis or database persistence for clustered or long-lived protection.
   * **Identity spoofing**: If `$_SERVER['REMOTE_ADDR']` is used as the key, reverse proxies/VPNs can evade limits—combine with user agent, account ID, or proof-of-work.
   * **Session fixation**: Ensure the calling script has already started a secure session (`cookie_httponly`, `cookie_secure`, `SameSite=Strict`).
   * **Complementary controls**: This script throttles but does not block traffic; pair with server-level defences (ModSecurity, fail2ban, Cloudflare Rate Limiting).

`/register.php`
   * Fixed 0.4.4-beta **Session fixation**: call `session_regenerate_id(true)` after `login_user()` to prevent fixation attacks.
   * **Password policy**: consider enforcing complexity (upper/lower/number/symbol) and breached-password checks (e.g., Have I Been Pwned API).
   * **E-mail verification**: add double-opt-in workflow to stop disposable or mistyped addresses.
   * **Bot defence**: integrate CAPTCHA or address reputation scoring in addition to IP rate limiting.
   * **HTML escaping**: error output already uses `htmlspecialchars`, but ensure any future templating remains XSS-safe.
   * **Transport security**: mandate HTTPS for all requests, not simply detect it.
   
`/store_data.php`
   * Fixed 0.4.4-beta **Session fixation** – regenerate session ID after login (handled in `auth.php`, but essential here).
   * **Directory traversal** – cast/validate `runId` against a strict UUID regex before path building.
   * **Least-privilege storage** – keep `processed/` outside the web-root or protect via web-server ACLs.
   * **Privilege escalation** – verify `processing_runs.user_id` on every access, not just once.
   * **SQL injection** – currently mitigated with PDO prepared statements; maintain strict parameter binding.
   * **Oversized uploads** – add file-size caps and MIME-type whitelists before hashing or DB insert.
   * **Race conditions** – lock the row in `processing_runs` during import to prevent concurrent re-runs.