**File Path**: `/app/auth.php`

**Purpose**:
Handles session initialization, user authentication, and provides secure CSRF protection for the PixlKey project.

**Agent Role**:
This file acts as an authentication and session management utility, securely managing user sessions, verifying user logins, and protecting against CSRF attacks.

**Behavior**:

* **Session Initialization**:
  Starts a secure session using PHP session management, enforcing strict cookie policies (`SameSite=Strict`, Secure, HttpOnly).

* **CSRF Token Handling**:

  * `generate_csrf_token()`: Generates and returns a secure token stored in the session for form submissions.
  * `validate_csrf_token()`: Validates submitted tokens against session-stored tokens, rejecting requests with a mismatched or missing token.

* **Authentication Helpers**:

+ * `login_user(string $user_id)`: Logs in a user by updating their `last_login` timestamp, regenerating the session ID, and storing their ID in the session. This mitigates session fixation attacks by invalidating any pre-existing session.
  * `require_login()`: Redirects unauthenticated users to the login page, preserving the requested URL for post-login redirection.
  * `current_user()`: Retrieves and caches user details from the database based on the session user ID.

**Security Notes**:

* Implements robust CSRF protection with cryptographically secure tokens and proper hashing comparisons (`hash_equals`).
* Secure session cookies configured with `Secure`, `HttpOnly`, and `SameSite=Strict` attributes to mitigate common web vulnerabilities (e.g., XSS, session hijacking).
* Database interactions use prepared statements, minimizing SQL injection risks.
* Implements session regeneration (`session_regenerate_id(true)`) on login to prevent session fixation attacks.


**Dependencies**:

* PHP's built-in session management and cryptography (`random_bytes`).
* `PDO` instance from `config.php` for database interactions.

**Recommended Improvements**:

* **Explicit Session Configuration**:
  Consider explicitly setting additional session security configurations like session timeout (`session.gc_maxlifetime`) to enhance control over session expiry.

* **Session Regeneration**:
  Implement session regeneration (`session_regenerate_id(true)`) during login to prevent session fixation attacks.

* **Logging & Monitoring**:
  Add logging for CSRF validation failures and unsuccessful login attempts to enhance security monitoring capabilities.

* **Error Handling Consistency**:
  Introduce consistent error handling (e.g., custom exceptions or dedicated error handler) for better maintainability and clarity during debugging.

* **Environment Checks**:
  Ensure `$_SERVER['HTTPS']` checks account for reverse proxies or load balancers to correctly determine cookie security contexts.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (recommend extensive security and integration tests before marking as Stable)

-----

**File Path**: `/app/config.php`

**Purpose**:
Acts as the central configuration manager and database connection bootstrap for the PixlKey project. Pulls sensitive values from environment variables to avoid credential exposure.

**Agent Role**:
This file operates as a database connector and configuration handler, responsible for establishing a secure PDO database connection and setting essential runtime limits.

**Behavior**:

* **Environment Configuration**:

  * Retrieves configuration details (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_DEBUG`, `MAX_UPLOAD_MB`) from environment variables.
  * Supports optional `.env` file integration using `php-dotenv`.

* **Runtime Upload Limits**:

  * Enforces PHP’s upload limits (`upload_max_filesize`, `post_max_size`) dynamically based on the defined maximum upload size (`MAX_UPLOAD_MB`).

* **PDO Connection Setup**:

  * Establishes a secure PDO connection to MySQL/MariaDB.
  * Sets PDO attributes for improved security and robust error handling (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`).

* **Error Handling**:

  * Provides different error handling paths depending on debug mode (`DB_DEBUG`).
  * Logs errors quietly in production, while offering verbose output during development.

**Security Notes**:

* Securely separates sensitive data from the codebase through environment variables.
* Explicitly disables prepared statement emulation (`PDO::ATTR_EMULATE_PREPARES`) to reduce SQL injection risks.
* Uses secure defaults for database credentials and explicitly recommends avoiding empty passwords in production.
* Proper error management to avoid leaking database details in production environments.

**Dependencies**:

* PHP PDO extension for MySQL/MariaDB connectivity.
* Optionally uses `vlucas/phpdotenv` package for loading environment variables from a `.env` file.

**Recommended Improvements**:

* **Mandatory Password Check**:

  * Consider adding a warning or enforced halt if `DB_PASS` is empty in a production environment to avoid accidental credential misconfiguration.

* **Environment Variable Validation**:

  * Explicit validation for required environment variables to ensure they're properly set, providing early runtime warnings or exceptions for missing critical values.

* **Logging Enhancements**:

  * Use a structured logging framework or solution for improved monitoring and troubleshooting in production, rather than relying solely on PHP’s default error log.

* **SSL Connection (Optional Enhancement)**:

  * Consider supporting SSL options for database connections, especially for secure communication with remote database servers.

* **Configuration Loading Checks**:

  * Implement checks to ensure the `.env` file is present and correctly loaded when using Composer, providing helpful error messages if misconfigured.

**Version & Status**:

* **Version**: v1.0
* **Status**: Stable (Recommended extensive load and integration testing before final deployment)

-----

**File Path**: `/public/download_zip.php`

**Purpose**:
Securely serves the processed ZIP archive containing the finalized assets for the currently logged-in user, based on the provided `runId`.

**Agent Role**:
This file operates as a secure content delivery handler, verifying user authorization and safely streaming ZIP files generated by previous processing steps.

**Behavior**:

* **Query Validation**:

  * Ensures `runId` is present and valid in the URL query string.
  * Sanitizes `runId` input using regular expression filtering.

* **File Path Resolution**:

  * Constructs a secure and user-specific file path, combining user UUID and sanitized `runId`.

* **File Delivery**:

  * Checks for file existence and readability.
  * Serves the file with appropriate HTTP headers (`Content-Type`, `Content-Disposition`, `Content-Length`) for secure download.

**Security Notes**:

* Input (`runId`) sanitization via `preg_replace` mitigates injection and directory traversal attacks.
* Uses session-based authentication (`require_login()` and `current_user()`) to ensure file access is restricted to the owning user.
* Explicit HTTP status responses (`400`, `404`) help with clear, secure error handling and debugging.

**Dependencies**:

* Authentication and session management (`auth.php`).
* Database and configuration via PDO instance (`config.php`).

**Recommended Improvements**:

* **Enhanced File Security**:

  * Consider additional verification steps (e.g., a database lookup) to ensure the requested `runId` explicitly belongs to the authenticated user, further preventing unauthorized access.

* **Logging and Monitoring**:

  * Implement access logging, especially for failed or suspicious download attempts, to support security auditing.

* **Rate Limiting**:

  * Introduce rate limiting or throttling mechanisms to prevent automated mass downloads or potential denial-of-service (DoS) attacks.

* **Graceful Error Handling**:

  * Consider serving consistent JSON responses for errors, improving integration with frontend applications or API clients.

* **Dynamic Filenames**:

  * Dynamically name the downloaded ZIP file (e.g., including the `runId` or date stamp) for better user experience and file management.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (Recommended thorough integration and security testing before promoting to Stable)

-----

**File Path**: `/functions.php`

**Purpose**:
Defines configuration variables and utility functions to support image uploading, processing, watermarking, and system maintenance in the Infinite Muse Toolbox.

**Agent Role**:
Utility and configuration agent providing file management, watermarking operations, and real-time feedback to the user interface during image processing tasks.

**Behavior**:

* **Configuration Setup**:

  * Sets global variables for maximum upload size, allowed file types, and directory paths for watermarks and processed files.

* **Directory Initialization**:

  * Ensures necessary directories (`/watermarks`, `/processed`) exist with correct permissions.

* **User Interface Feedback** (`echoStep()`):

  * Dynamically injects JavaScript into the client-side DOM for real-time progress updates during server-side processing.

* **File Maintenance** (`clearProcessedFiles()`):

  * Silently clears old files from the processed directory to manage disk usage.

* **Watermarking Utility** (`addWatermark()`):

  * Copies original image and applies user-provided watermark.
  * Dynamically resizes and composites watermark image.
  * Adds randomized textual watermark overlays for additional protection or branding.

**Security Notes**:

* Uses `escapeshellarg()` for shell command safety, mitigating risks of command injection.
* Checks file existence and validity at each step, providing user feedback on errors.
* Does not currently enforce strict upload size limits via environment configuration, relying instead on hard-coded settings.

**Dependencies**:

* Relies on external binaries (`convert` and `identify` from ImageMagick).
* Depends on system-level PHP functions (`shell_exec`, `system`) to interact with ImageMagick.

**Recommended Improvements**:

* **Environment Variables**:

  * Move `$maxFileSizeMb`, `$allowedExtensions`, and directory paths to `.env` file or central configuration (`config.php`) for better manageability.

* **Error Handling & Logging**:

  * Replace suppression (`@`) with explicit error checking and logging for easier debugging and system monitoring.
  * Introduce a logging mechanism for watermark and file operation failures.

* **Validation & Sanitization**:

  * Explicitly validate all inputs (image paths, watermark files, dimensions) before processing to enhance robustness.

* **Cron Management**:

  * Separate cron-based cleanup (`clearProcessedFiles()`) into a dedicated, isolated script for clear operational boundaries.

* **Code Refactoring**:

  * Break down complex functions like `addWatermark()` into smaller, reusable methods to enhance readability and maintainability.

* **Internationalization & Accessibility**:

  * Avoid hardcoded inline styles/colors; use CSS classes for consistent visual styles and potential theme support.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (recommended additional testing of watermarking process and cleanup functions before declaring Stable)

-----

**File Path**: `/public/index.php`

**Purpose**:
Serves as the unified landing page for both public visitors and logged-in members, displaying recent image thumbnails and providing user-specific upload forms and options.

**Agent Role**:
This file operates as the central presentation and interaction point for visitors and authenticated members. It manages user-specific content, image previews, watermark selections, license options, and facilitates secure image uploads.

**Behavior**:

* **Session and User Management**:

  * Checks if a user is logged in via `current_user()` (from `auth.php`).
  * Sets a boolean `$loggedIn` to manage view logic.

* **Thumbnail Retrieval**:

  * For authenticated users, retrieves the user's 10 most recent thumbnails.
  * For public visitors, retrieves the site-wide 10 most recent thumbnails.

* **User-Specific Options**:

  * Fetches available watermarks and licenses for logged-in users from the database.
  * Dynamically populates form dropdowns and provides file-upload capability.

* **Client-Side Preview**:

  * JavaScript enables live preview of selected watermark images and uploaded artwork prior to submission.

**Security Notes**:

* Employs `htmlspecialchars()` consistently to prevent Cross-Site Scripting (XSS) vulnerabilities when outputting user-controlled data.
* Integrates secure CSRF protection by embedding a token generated via `generate_csrf_token()` from `auth.php` into form submissions.
* Database interactions are safeguarded through the use of prepared statements.

**Dependencies**:

* Internal:

  * `auth.php`: for session handling, authentication checks, and CSRF token management.
  * `config.php`: database connectivity (`PDO`).
  * `functions.php`: general utility functions.
* External:

  * Standard PHP PDO extension for database interactions.

**Recommended Improvements**:

* **Pagination**:
  Consider implementing pagination or lazy-loading techniques for thumbnails to handle scalability as content grows.

* **Error Handling and User Feedback**:
  Add explicit error handling around database queries and inform users when a database retrieval fails.

* **Accessibility**:
  Improve accessibility by ensuring more descriptive `alt` attributes for thumbnails (currently generic "recent thumbnail").

* **JavaScript Robustness**:

  * Add error handling in JavaScript file preview functionality (e.g., unsupported file types).
  * Validate file size and type client-side before uploads to enhance UX.

* **Modularize CSS and JS**:
  Move inline CSS and JavaScript to separate external files to improve maintainability and browser caching.

* **Responsive Design Enhancements**:
  Add media queries and responsive adjustments for optimal viewing on mobile and tablet devices.

* **Logging and Analytics**:
  Integrate user interaction logging or basic analytics for performance monitoring and UX improvements.

**Version & Status**:

* **Version**: v1.1
* **Status**: Testing (recommended additional browser compatibility and integration tests before stable release)

-----

**File Path**: `/public/login.php`

**Purpose**:
Manages user authentication via email and password, providing a secure login form with CSRF protection for the PixlKey project.

**Agent Role**:
Acts as a login interface, verifying user credentials, handling authentication requests, initiating user sessions, and managing redirects upon successful login.

**Behavior**:

* **Form Submission Handling**:

  * Validates submitted email and password against stored credentials.
  * Verifies passwords securely using PHP’s built-in `password_verify` method.
  * Initiates user login via `login_user()` upon successful credential verification.
  * Redirects authenticated users to the originally requested resource.

* **CSRF Protection**:

  * Utilizes `validate_csrf_token()` to protect against cross-site request forgery.
  * Generates tokens through `generate_csrf_token()` and embeds them in form submissions.

* **Error Handling**:

  * Accumulates and clearly displays user-friendly errors for invalid login attempts.

**Security Notes**:

* Passwords securely stored and verified using PHP’s native hashing functions (`password_hash`, `password_verify`).
* Robust CSRF protection included for POST requests.
* Uses parameterized queries (prepared statements) for database interactions, effectively preventing SQL injection.
* Form inputs sanitized using `htmlspecialchars` to mitigate potential XSS vulnerabilities.

**Dependencies**:

* Requires `auth.php` for authentication/session helper functions.
* Depends on a properly configured PDO instance for database access.

**Recommended Improvements**:

* **Rate Limiting & Brute-Force Protection**:

  * Implement login attempt rate limiting to prevent brute-force attacks.

* **Session Regeneration**:

  * Explicitly regenerate session IDs upon successful login using `session_regenerate_id(true)` for additional session security.

* **Logging & Monitoring**:

  * Log failed login attempts for security audits and intrusion detection.
  * Provide optional admin notifications for repeated authentication failures.

* **Input Validation Enhancements**:

  * Include explicit email-format validation using PHP’s built-in validation (`filter_var`).

* **User Feedback**:

  * Consider distinguishing between different error scenarios for clearer user guidance (though avoid revealing specific reasons to maintain security).

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (Pending further security testing and validation)

-----

**File Path**: `/public/logout.php`

**Purpose**:
Logs out the currently authenticated user by securely terminating their session and session cookie, then redirects them to the login page.

**Agent Role**:
Serves as the session termination utility, ensuring complete logout and clean-up of user sessions.

**Behavior**:

* **Session Clean-Up**:

   * Regenerates session ID before logout (`session_regenerate_id(true)`).
   * Clears all active session variables (`session_unset()`).
   * Destroys the session (`session_destroy()`).

* **Cookie Invalidation**:

  * Explicitly expires the session cookie immediately by setting its expiration time to a past timestamp.

* **User Redirection**:

  * Redirects the user immediately to the login page (`login.php`) after session termination.

**Security Notes**:

* Securely invalidates sessions by regenerating the session ID first, destroying server-side session data, and expiring the client-side cookie.
* Maintains security best practices by ensuring session cookies respect the `Secure` and `HttpOnly` flags, preventing potential misuse.

**Dependencies**:

* `auth.php` for consistent session handling and configuration.

**Recommended Improvements**:

* **Error Handling & Logging**:

  * Introduce logging of logout events to support auditing and security monitoring, tracking successful and failed session terminations.

* **Explicit HTTPS Check**:

  * Confirm cookie security parameters (`secure` flag) by explicitly checking HTTPS context for robust handling in proxy/load-balanced environments.

* **Session Regeneration** (Optional Improvement):

  * Consider adding `session_regenerate_id(true)` prior to destroying the session for additional security against session fixation vulnerabilities.

* **Customizable Redirection**:

  * Allow optional URL parameters for flexible redirection after logout (e.g., via `$_GET['next']` parameter).

**Version & Status**:

* **Version**: v1.0
* **Status**: Stable (recommend continued monitoring for unusual logout patterns)

-----

**File Path**: `/app/tools/metadata_extractor.php`

**Purpose**:
Extracts, sanitizes, structures, and formats metadata from signed images into a polished Markdown document suitable for sharing, display, or archiving within the PixlKey project.

**Agent Role**:
This script serves as a standalone image metadata extraction and reporting utility, responsible for leveraging ExifTool to parse image metadata and transforming it into structured Markdown output.

**Behavior**:

* **Command-Line Argument Parsing**:
  Requires explicit input (`--input`) and output (`--output`) file paths, validating their existence and accessibility.

* **ExifTool Integration**:
  Utilizes ExifTool via the command line, executing a shell command (`exiftool -j`) to fetch image metadata in JSON format.

* **Security-Focused Filtering**:
  Excludes a predefined set of sensitive metadata fields (e.g., file paths, inodes, timestamps, permissions) to protect user privacy and system integrity.

* **Field Label Mapping**:
  Transforms raw metadata keys into clear, user-friendly labels for readability and clarity in the Markdown report.

* **Markdown Generation**:
  Organizes metadata into logical sections (Basic Information, Technical Details, Metadata Identifiers, Additional Information) for easy consumption.

**Security Notes**:

* Sensitive metadata fields explicitly filtered out (`SourceFile`, filesystem details, rights details).
* Sanitizes metadata values via `htmlspecialchars()` to prevent Markdown injection or formatting issues.
* Shell commands executed via `escapeshellcmd()` and `escapeshellarg()` to mitigate command injection risks.

**Dependencies**:

* External dependency on [ExifTool](https://exiftool.org/) installed and accessible in the system PATH.
* PHP built-in functions for JSON processing, command-line argument parsing, and file I/O.

**Recommended Improvements**:

* **Error Logging**:
  Implement structured logging to a file or monitoring system for easier debugging and incident tracking.

* **Input Validation**:
  Add stricter input file validation, such as ensuring MIME type checks or limiting acceptable file extensions to avoid processing unintended files.

* **Performance Optimization**:
  Consider adding caching or file checksum verification to avoid redundant metadata extraction on unchanged files.

* **JSON Parsing Robustness**:
  Implement checks for ExifTool output completeness, ensuring that critical fields exist before assuming successful extraction.

* **Exception Handling**:
  Replace basic error handling with structured exception handling for better readability and maintainability.

* **Unit Testing**:
  Introduce automated tests for critical logic, especially filtering logic and Markdown generation, to maintain code correctness through future changes.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (extensive edge case testing recommended before stable release)

-----

**File Path**: `/public/my_licenses.php`

**Purpose**:
Provides a user dashboard for managing license templates, enabling CRUD (Create, Read, Update, Delete) operations and setting a default license.

**Agent Role**:
Operates as a CRUD agent that manages user-specific license data, including secure database interactions, markdown rendering, and client-side form handling.

**Behavior**:

* **License Management Operations**:

  * **Create/Update**: Handles saving or updating licenses in the database. Users can designate a license as the default, which resets the previous default.
  * **Delete**: Allows users to delete existing licenses.
  * **Read**: Retrieves and displays licenses from the database in a readable markdown format.

* **Markdown Rendering**:

  * Utilizes the `Parsedown` library in safe mode for secure markdown-to-HTML conversion, preventing XSS attacks.

* **Form Handling & Client-side Interaction**:

  * Provides an intuitive interface for editing licenses through JavaScript form pre-filling.

**Security Notes**:

* **CSRF Protection**:

  * Employs the project's CSRF validation functions (`generate_csrf_token()` and `validate_csrf_token()`) to secure form submissions.

* **Input Validation & Sanitization**:

  * User inputs are trimmed and validated before database interactions.
  * Output is sanitized using `htmlspecialchars()` to protect against XSS attacks.
  * Markdown rendering is configured with `setSafeMode(true)` to strip dangerous HTML tags.

* **Database Security**:

  * Uses parameterized SQL queries (prepared statements) to prevent SQL injection.

**Dependencies**:

* Project Files:

  * `auth.php` for user authentication and CSRF protection.
  * `config.php` for database connection.
* External Library:

  * `Parsedown` (MIT-licensed Markdown parser)

**Recommended Improvements**:

* **Error Handling**:

  * Implement more granular exception handling and provide clearer feedback to users about potential issues (e.g., database connection errors).

* **Accessibility & UX**:

  * Enhance the user interface for accessibility by adding clearer labels, ARIA attributes, and improving keyboard navigation.
  * Provide visual feedback during loading states or after deletion.

* **Database Transactions**:

  * Consider wrapping updates to the default license in database transactions for atomicity and consistency.

* **Code Structure**:

  * Refactor HTML/CSS/JavaScript into separate files or templates for improved maintainability and readability.
  * Introduce consistent client-side validation to enhance user experience and reduce server-side errors.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (recommend thorough user interface and security audits before marking as Stable)

-----

**File Path**: `/public/my_watermarks.php`

**Purpose**:
Provides users with an interface for uploading, managing, and selecting their watermark images for use within the PixlKey project.

**Agent Role**:
Acts as a CRUD (Create, Read, Update, Delete) interface agent, enabling user-controlled management of watermark files, including uploading new files, setting defaults, and deletion of unwanted files.

**Behavior**:

* **File Upload**:
  Handles user-uploaded watermark images (`PNG`, `JPEG`, `JPG`, `WEBP`). Verifies the file type, stores files securely in user-specific directories, and adds database records.

* **Set Default Watermark**:
  Updates database records to designate a chosen watermark as the default, ensuring only one watermark is marked as default at a time.

* **Delete Watermark**:
  Removes watermark entries from both the filesystem and database, preventing orphaned files or database entries.

* **Display Watermarks**:
  Retrieves user watermark records from the database for display, showing thumbnails, filenames, default status, and actionable controls (set default, delete).

**Security Notes**:

* Implements CSRF token validation (`validate_csrf_token()`) for all POST operations.
* File uploads validate extensions explicitly and restrict allowed types to mitigate arbitrary file upload risks.
* Uses `htmlspecialchars()` consistently in output to prevent Cross-Site Scripting (XSS).
* Database queries utilize prepared statements to prevent SQL injection.
* User-specific directories prevent file visibility across different users.

**Dependencies**:

* `auth.php`: For authentication, session management, and CSRF handling.
* `config.php`: For PDO database connection.
* PHP built-in filesystem functions (`move_uploaded_file`, `mkdir`, `unlink`).

**Recommended Improvements**:

* **Thumbnail Generation**:

  * Consider automatically generating and storing standardized-sized thumbnails to improve performance and page loading speeds.

* **Error Handling**:

  * Replace the silent error suppression (`@unlink`) with explicit error checking and handling/logging to ensure issues do not silently fail.

* **File Size & Dimension Validation**:

  * Implement file size limits and dimension validation to further reduce potential abuse or performance impacts.

* **Input Sanitization & Validation**:

  * Consider adding checks for filenames to prevent filesystem traversal attacks or unintended overwriting of existing files.

* **Directory Permissions**:

  * Confirm correct permissions (`0700` or `0750`) on user-specific directories to strengthen isolation between user files.

* **Deletion Safety**:

  * Before deleting, verify file existence explicitly and provide meaningful error feedback if deletion fails.

* **UI & UX Enhancements**:

  * Improve usability by adding a confirmation modal/dialog with watermark previews before performing destructive operations like deletion.

* **Logging & Auditing**:

  * Log watermark actions (upload, delete, default selection) to enhance auditing and accountability.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing (recommend comprehensive functionality and security tests prior to marking as Stable)

-----

**File Path**: `/public/process.php`

**Purpose**:
Handles user-submitted image uploads, applies watermark and metadata, and generates signed outputs and certificates of authenticity. Outputs a downloadable ZIP archive containing processed assets.

**Agent Role**:
Serves as the **core image processing agent** of the PixlKey system. It validates uploads, enforces constraints, applies transformations (conversion, watermarking, metadata embedding), and persists image metadata to the database.

**Behavior**:

* **Authentication & Security**:

  * Requires user to be logged in via `require_login()`.
  * Verifies CSRF tokens immediately via `validate_csrf_token()`.

* **Input Handling**:

  * Accepts image uploads (`.png`, `.jpg`, `.jpeg`, `.webp`) up to 200MB each.
  * Accepts watermark either from a saved DB record or one-off upload.
  * Accepts a license record or falls back to a default phrase.
  * Accepts descriptive metadata via form fields (title, keywords, SEO headline, etc.).

* **Processing Pipeline**:

  1. Creates a dedicated per-user processing directory.
  2. Moves, validates, and sanitizes file names.
  3. Converts all uploaded files to `.png` format.
  4. Applies resized watermark (10% of image width, bottom-right placement).
  5. Strips existing metadata, then embeds structured metadata using `exiftool`.
  6. Generates thumbnails (400px) and previews (800px).
  7. Persists image metadata to MariaDB.
  8. Extracts metadata to `.md` using `metadata_extractor.php`.
  9. Creates a **Markdown certificate of authenticity** with embedded image info.

10. Zips all final assets and offers a download link.

* **Frontend Output**:

  * Outputs a live-processing HTML interface with color-coded status updates.
  * Finalizes with a download link for the ZIP archive.

**Security Notes**:

* CSRF protection enforced early with `validate_csrf_token()`.
* Uses `htmlspecialchars()` for sanitizing all user-submitted strings before embedding into HTML or metadata.
* Enforces upload size limits using both PHP `ini_set()` and runtime checks.
* Uses `escapeshellarg()` for all shell command parameters to prevent shell injection.
* Uses per-user and per-run directory isolation for temporary storage.

**Dependencies**:

* PHP: `GD` or `ImageMagick` (via `convert`), `exiftool`, `zip` (via shell).
* Internal:

  * `auth.php` (auth/session/CSRF)
  * `functions.php` (utility functions like `echoStep`)
  * `metadata_extractor.php` (CLI tool for .md metadata dump)
  * `process_helpers.php` (e.g. `addWatermark()`)

**Recommended Improvements**:

* **Security & Validation**:

  * Good: CSRF validation, HTML sanitization, and command escaping are all handled correctly.
  * *Suggestion*: Use stricter MIME-type verification (`mime_content_type()`) in addition to file extension checks for uploads.
  * *Suggestion*: Check `$userId` more explicitly for validity before using it in paths or queries.

* **Code Quality & Maintainability**:

  * *Suggestion*: Split into smaller logical modules (e.g., `image_processing.php`, `metadata_handler.php`, `output_ui.php`) for testability and future maintainability.
  * *Suggestion*: Replace `shell_exec()` with a more secure wrapper (`proc_open`) if server policy allows.
  * *Suggestion*: Extract HTML output to a template or use output buffering more declaratively.

* **Logging & Error Handling**:

  * *Suggestion*: Use proper logging (`error_log()` or Monolog) for internal errors instead of echoing errors to the browser.
  * *Suggestion*: Display user-friendly messages and optionally show a collapsible “debug info” panel for advanced users.

* **UX/UI**:

  * Good: Live step-by-step feedback gives great user clarity.
  * *Suggestion*: Add progress indicator or animated spinner to improve responsiveness for long processing tasks.

* **Performance**:

  * *Suggestion*: Consider queueing uploads for async/batch processing at scale.
  * *Suggestion*: Cache resized watermark to avoid repeated conversion per file.

**Version & Status**:

* **Version**: v1.2
* **Status**: Stable – requires further testing for concurrency and upload edge cases.

-----

**File Path**: `/app/tools/process_helpers.php`

**Purpose**:
Provides utility functions to support image processing workflows, including live feedback streaming for web UI and watermarking image files using ImageMagick CLI tools.

**Agent Role**:
Acts as a front-line processing utility agent. It enhances user experience via real-time progress updates and provides watermarking logic that integrates shell-based image manipulation into the web backend.

**Behavior**:

* **`echoStep(string $message, string $class = 'info')`**
  Streams a message to the front-end `<div id="steps">` using JavaScript injection. Auto-scrolls the container to ensure latest messages are visible.

* **`addWatermark(string $imagePath, string $watermarkPath, string $runDir)`**

  * Uses ImageMagick to:

    * Get image width.
    * Resize the watermark to \~6% of the image’s width.
    * Overlay the watermark in the bottom-right corner with margin.
  * Cleans up temporary resized watermark after use.

* **Global config values**:

  * `$defaultWatermark`: Fallback watermark image path.
  * `$allowedExtensions`: Permitted image file types.

**Security Notes**:

* **Shell Injection Protection**:

  * Good use of `escapeshellarg()` to prevent command injection in user-supplied file paths.
  * Still relies on shell execution (`shell_exec`, `convert`, etc.) which can be risky in shared hosting or improperly sandboxed environments.

* **JS Injection Protection**:

  * Uses `json_encode()` to escape messages in `echoStep()`—helps prevent DOM-based XSS injection.

* **Cleanup**:

  * Temporary files (`signature_small_...png`) are removed after use via `@unlink()`, preventing buildup.

**Dependencies**:

* **PHP Extensions**: None required beyond core.
* **External Tools**: [ImageMagick](https://imagemagick.org/) (`identify`, `convert`) must be installed and accessible from the server's shell path.

**Recommended Improvements**:

* **Error Handling & Logging**:

  * Check for command execution failures (e.g., validate `shell_exec` return value).
  * Log errors instead of failing silently (e.g., `if (!$width) { log('identify failed'); return; }`).

* **Security Hardenings**:

  * Ensure no user-controlled inputs can modify `$imagePath`, `$watermarkPath`, or `$runDir` without validation.
  * Consider restricting to a known safe directory to avoid arbitrary file access.

* **Code Structure**:

  * Move `$defaultWatermark` and `$allowedExtensions` to `config.php` or an environment file to avoid hardcoded paths in logic files.

* **Functionality**:

  * Add support for positioning the watermark (top-left, center, etc.) via optional parameters.
  * Allow scaling percentage to be configurable per user/session.

* **Testability**:

  * Currently difficult to unit test due to reliance on shell commands and global echoing.
  * Consider abstracting the shell layer for mocking during tests.

**Version & Status**:

* **Version**: v0.9
* **Status**: Testing – usable in development, needs refinement for production robustness and flexibility.

-----

**File Path**: `/public/register.php`

**Purpose**:
Handles new user registration for the PixlKey platform, validating input, enforcing security policies, and storing user credentials securely.

**Agent Role**:
Acts as a registration handler and form processor. It validates user input, ensures unique email addresses, hashes passwords securely, and stores the new user in the database. It then logs the user in and redirects them to the main page.

**Behavior**:

* **Form Submission Handling** (POST):

  * Validates the CSRF token using `validate_csrf_token()`.
  * Sanitizes and trims input (`email`, `display_name`, `password`, `password_confirm`).
  * Validates email format and password confirmation/length.
  * Checks whether the email already exists in the database.
  * If all checks pass:

    * Hashes the password with `password_hash(...)`.
    * Inserts the new user record into the database.
    * Retrieves the `user_id` via `lastInsertId()` or fallback query.
    * Calls `login_user()` and redirects to `index.php`.

* **Form Display** (GET or after error):

  * Displays any validation errors.
  * Includes a CSRF token for protection.
  * Provides minimal styling for visual clarity and accessibility.

**Security Notes**:

* **CSRF Protection**:
  Uses a hidden token input with secure token validation (`validate_csrf_token()`).

* **Password Handling**:
  Passwords are hashed using `PASSWORD_DEFAULT`, ensuring use of the latest recommended hashing algorithm (usually bcrypt or Argon2).

* **SQL Injection Protection**:
  All database interactions use parameterized queries (prepared statements), mitigating injection risks.

* **XSS Protection**:
  Error messages are passed through `htmlspecialchars()` before output to prevent reflected XSS.

* **Email Duplication Check**:
  Prevents registration of duplicate accounts by checking the `users` table before insert.

**Dependencies**:

* Relies on `auth.php` for session and CSRF functions (`login_user()`, `validate_csrf_token()`, etc.).
* Depends on `config.php` for the `$pdo` database connection.

**Recommended Improvements**:

* **ID Retrieval Consistency**:
  Prefer using `lastInsertId()` with a UUID scheme or explicitly return the UUID in the insert query instead of falling back to a second `SELECT`.

* **Stronger Password Requirements**:
  Consider adding checks for common weaknesses: e.g., uppercase/lowercase, numbers, symbols.

* **Username Sanitization & Length Limits**:
  Add validation rules for `display_name` length, disallowed characters, or profanity filtering.

* **Rate Limiting or CAPTCHA**:
  Add basic rate-limiting or CAPTCHA to protect against automated signup attempts.

* **Use of HTML5 Validation Attributes**:
  While `required` is present, consider using `minlength`, `maxlength`, and pattern matching for early client-side validation.

* **Accessibility Enhancements**:
  Associate form inputs and labels with `for`/`id` attributes to improve screen reader support.

**Version & Status**:

* **Version**: v1.0
* **Status**: Testing
  (Functionally complete and secure, but should be hardened with validation improvements and accessibility polish for production deployment.)

-----

**File Path**: `/app/jobs/store_data.php`

**Purpose**:
Processes image metadata and form input stored during an upload session, inserting structured data into the `infinite_image_tools` relational database. This includes artworks, associated metadata, images, certificates, AI annotations, and optional submissions.

**Agent Role**:
Acts as a data ingestion and persistence agent. It ingests structured files from a temporary "run" directory (e.g., `processed/{runId}/`), parses them, and populates multiple related database tables within a transaction to maintain data consistency.

**Behavior**:

* Enforces session authentication and CSRF protection for POST requests.
* Accepts a `runId` to locate a pre-processed directory containing:

  * `data.json`: Core metadata
  * `*_signed.png`, `*_metadata.txt`, `*_certificate.md`: Images and attached metadata
  * Optionally: `*_ai_metadata.json`, `submission.json`
* Maps and inserts values across multiple tables:

  * `Artworks`, `Images`, `Certificates`, `AIMetadata`, `Submissions`
  * `ArtworkKeywords`, `ArtworkGenres`, `ArtworkCreators`, `ArtworkBylines` (many-to-many)
* Wraps operations in a database transaction to ensure atomicity.

**Security Notes**:

* Requires authenticated session (`require_login()`).
* Validates CSRF tokens on `POST` requests.
* Uses prepared statements exclusively (mitigates SQL injection).
* Hashes images with SHA-256 (`hash_file()`).
* Skips filesystem path sanitization—relies on directory structure trust. May benefit from hardening.

**Dependencies**:

* `auth.php`: for session and CSRF helpers
* `config.php`: for `$pdo` connection
* `process_helpers.php`: assumed to contain processing utilities
* Filesystem: requires JSON, PNG, Markdown, TXT files structured per run

**Recommended Improvements**:

* **File path sanitation**: Validate or whitelist `runId` to prevent path traversal or injection (`basename($runId)` or regex enforcement).
* **Error logging**: Replace `die()` with centralized error handling and logging (e.g., `error_log()`, Sentry, or app log).
* **Validation layer**: Validate decoded JSON structure and expected keys (`isset`, data types) before DB insertion.
* **Fail-safe insertions**: Replace `INSERT IGNORE` with conflict-aware logic where needed to avoid masking integrity issues.
* **Refactor long method**: Break down the monolithic `try` block into reusable functions or per-table handlers for clarity and maintainability.
* **AI metadata**: Validate `json_decode()` results strictly and include fallback behavior or schema conformance checking.
* **Use file hashes as deduplication keys**: Useful for avoiding re-processing identical image uploads.
* **Missing uploads**: Consider a check to ensure that *at least one* image, metadata, or certificate was processed, to prevent “silent success”.

**Version & Status**:

* **Version**: v0.9
* **Status**: Testing
  (Core pipeline complete; requires structured validation, filesystem hardening, and better error handling before production)

-----
