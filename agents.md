# Agents Reference

A consistently structured overview of the key scripts (“agents”) that make up the **Infinite Image Tools** legacy PHP stack and related utilities.  
Each entry follows the same heading order for clarity:

### File Location
### File Name
1. **Purpose** – What the file does and why it exists.  
2. **Agent Role** – How it behaves in the overall system.  
3. **Key Responsibilities** – Main behaviours / execution flow.  
4. **Security Considerations** – Relevant security notes.  
5. **Dependencies** – Direct runtime requirements.  
6. **Additional Notes** – Any extra usage or deployment guidance.

---
### File Location
`/`

### File Name
`config.php`

1. **Purpose**  
   This file configures and establishes a connection to a MariaDB/MySQL database using PHP's PDO (PHP Data Objects) extension. It defines constants for database connection parameters and handles connection logic, with optional debugging support.

2. **Agent Role**  
   Acts as a **core infrastructure agent** for backend database access in PHP-based components. It provides a reusable, centralized method to initialize a secure and consistent PDO connection.

3. **Key Responsibilities**  
   - Define database connection constants (host, port, db name, user, password).
   - Set character encoding for the connection (`utf8mb4`).
   - Optionally enable debug output during connection failures.
   - Attempt to connect using PDO.
   - Configure PDO with:
     - `ERRMODE_EXCEPTION` for proper error handling.
     - `FETCH_ASSOC` for clean associative array results.

4. **Security Considerations**  
   - **Hardcoded credentials**: Password (`DB_PASS`) is stored in plaintext—this should be moved to environment variables or a secure secret manager in production.
   - **Debug output**: `DB_DEBUG` is set to `true` which may reveal sensitive error details to the browser; must be disabled in production to avoid information disclosure.
   - **Error handling**: Ensures user-friendly fallback if debugging is disabled.
   - **PDO use**: A secure database access method when used correctly (prevents SQL injection if prepared statements are used elsewhere).

5. **Dependencies**  
   - PHP (≥ 7.0 recommended)
   - PHP PDO extension
   - A running MySQL/MariaDB server
   - Network access to `DB_HOST` on `DB_PORT`

6. **Additional Notes**  
   - If this file is included in multiple scripts, use `require_once` to avoid redeclaration.
   - Credentials should be abstracted via `.env` or system-level secrets where possible.
   - Ensure file permissions restrict access (`chmod 600 config.php`).
   - Consider adding connection retry logic for more robust deployments (e.g., on containerized or orchestrated systems).

---
### File Location
`/`

### File Name
`download_zip.php`

1. **Purpose**  
   This script serves a previously generated ZIP file (`final_assets.zip`) for a specific `runId` via HTTP download. It validates input, constructs the expected path, and returns the file as a downloadable asset.

2. **Agent Role**  
   Functions as a **delivery agent** responsible for serving ZIP bundles that were generated and stored during earlier image-processing or packaging stages. It finalizes the pipeline by enabling end-users to retrieve their packaged results.

3. **Key Responsibilities**  
   - Validate ownership of the `runId` via `processing_runs` table before serving file.
   - If found and authorized, serve the file with correct headers to prompt a ZIP download.
   - Include the database configuration (`config.php`)—for session, PDO, and ownership lookup.
   - Verify the presence and validity of the `runId` parameter from the query string.
   - Sanitize the `runId` to prevent directory traversal exploits.
   - Query the `processing_runs` table to confirm the `runId` belongs to the current session user.
   - Check for the existence of `final_assets.zip` inside the `processed/{user_id}/{runId}/` directory.
   - If ownership confirmed and file exists, serve the file with secure headers.
   - Return appropriate HTTP error codes if validation or file lookup fails.

4. **Security Considerations**  
   - **Input sanitization**: Uses a regex to strip dangerous characters from `runId`—good practice for avoiding directory traversal attacks.
   - **Direct file serving**: Only serves if file exists and path is resolved safely.
   - **Ownership enforcement**: As of 0.4.1-beta, the script verifies that the `runId` belongs to the logged-in user by querying the `processing_runs` table.
   - **Fixed filename in header**: Always serves as `final_assets.zip` regardless of actual file naming—consider including `runId` in filename for clarity/user feedback.

5. **Dependencies**  
   - `config.php` (though not functionally used in this snippet)
   - A previously generated ZIP file located at `processed/{runId}/final_assets.zip`
   - Web server (e.g., Apache/Nginx) with access to PHP and correct MIME type handling

6. **Additional Notes**  
   - You may want to implement logging (e.g., which IP downloaded what and when).
   - Consider rate limiting or download expiry logic for production use.
   - Could benefit from a `Content-Security-Policy` or token-based access.
   - If `config.php` is not needed for this endpoint, removing it would slightly reduce attack surface and loading time.

---
### File Location
`/`

### File Name
`faq.html`

1. **Purpose**  
   Serves as a user-facing Frequently Asked Questions (FAQ) page for the *Infinite Muse Toolkit*. It explains what the toolkit does, how to use it, its security model, and offers guidance for users, contributors, and potential supporters.

2. **Agent Role**  
   Acts as a **passive informational agent** within the system—non-executable but critical for transparency, onboarding, and support. It builds user trust and educates visitors on toolkit capabilities and future direction.

3. **Key Responsibilities**  
   - Explain toolkit functionality (metadata embedding, certificate generation, packaging).
   - Reassure users on privacy and data handling.
   - Address common user questions (e.g. commercial use, form field requirements).
   - Offer channels for support and contribution.
   - Promote future roadmap and community involvement.
   - Provide responsive layout for various screen sizes using embedded CSS.

4. **Security Considerations**  
   - No direct data interaction or script execution (static HTML), so low risk surface.
   - References to images and external CSS should be verified and secured (e.g., `/unauthenticated/js/ckeditor/ckeditor-custom.min.css`).
   - Ensure images such as `./watermarks/muse_signature_black.png` are not vulnerable to path traversal or access control issues.

5. **Dependencies**  
   - Standard HTML5-compliant browser
   - External stylesheet: `/unauthenticated/js/ckeditor/ckeditor-custom.min.css`
   - Internal assets: watermark logo image(s) at `./watermarks/`

6. **Additional Notes**  
   - Could be generated dynamically by a templating engine (e.g., Flask/Jinja2) in the future for localization or user-specific content.
   - Text content is suitable for export to markdown or inclusion in documentation.
   - Ensure future edits remain accessible, especially for assistive technologies (ARIA roles, alt text, etc.).
   - Consider moving contact email to a protected form to prevent spam harvesting.

---
### File Location
`/`

### File Name
`functions.php`

1. **Purpose**  
   Provides configuration values and shared utility functions for the Infinite Muse Toolbox, including image watermarking, directory setup, file management, and real-time feedback rendering. It is intended to support front-end scripts like `index.php` and `process.php`.

2. **Agent Role**  
   Acts as a **supporting utility agent**, abstracting low-level operations (e.g. directory checks, watermark logic, dynamic UI updates) so the main application scripts remain clean and focused on logic flow.

3. **Key Responsibilities**  
   - **Configuration constants**: Sets global values like max file size, allowed extensions, and storage paths.
   - **Environment preparation**: Creates required directories (`/watermarks`, `/processed`) at runtime.
   - **User feedback**: `echoStep()` dynamically pushes messages to the browser during processing.
   - **File cleanup**: `clearProcessedFiles()` deletes old images from the processed directory, optionally for cron job use.
   - **Image watermarking**: `addWatermark()` resizes and composites a user-supplied watermark onto the image and overlays additional randomized semi-transparent text using ImageMagick.

4. **Security Considerations**  
   - **Shell execution risk**: Uses `shell_exec()` and `system()` heavily; input must be tightly controlled to prevent command injection.
   - **Temporary files**: Watermarking process creates temp files (`wm_` prefix); file names are derived from originals—validate paths to avoid overwriting unintended files.
   - **Directory permissions**: Creates folders with `0775`—ensure file ownership and group access are appropriate for your web server user.
   - **Debug visibility**: `echoStep()` outputs content directly to the browser, which may be verbose or leak stack details if misused in production.

5. **Dependencies**  
   - PHP (≥ 7.x)
   - ImageMagick CLI tools: `convert`, `identify`
   - Writable filesystem (for watermarking and file storage)
   - `config.php` (for DB and possibly shared runtime settings)

6. **Additional Notes**  
   - Designed to be included (`require_once`) by other scripts—not intended to be accessed directly via browser.
   - Output from `echoStep()` depends on a container element with ID `steps` in the HTML DOM; without this, no visible feedback is shown.
   - Cron-compatible cleanup (`clearProcessedFiles()`) can be triggered via a scheduled job or batch process.
   - Randomized watermark text feature (`$overlayText`) assumes this variable is declared elsewhere—ensure it's properly initialized before calling `addWatermark()`.

---
### File Location
`/`

### File Name
`index.php`

1. **Purpose**  
Acts as the primary entry point and front-end interface for the Infinite Muse Toolkit. This file presents users with a styled HTML form to submit artwork-related metadata and images for processing. It ties together backend processing and frontend interaction. As of `0.4.2-beta`, layout and visual usability enhancements improve the onboarding experience.

2. **Agent Role**  
   Serves as a **user-facing interaction agent**, rendering the HTML form UI and triggering the backend processing logic (`process.php`) on form submission. It acts as the central hub for collecting user input, displaying tooltips, previews, and instructions.

3. **Key Responsibilities**  
   - Load supporting PHP modules:
     - `config.php`: database connection.
     - `functions.php`: utility functions.
     - `process.php`: logic to handle POST submissions.
   - Display a multi-field HTML form with:
     - Title, description, keywords, genres, licensing, and other metadata.
     - File upload inputs (custom watermark, multiple image files).
     - Field validation and tooltips for user guidance.
   - Include JavaScript for image preview functionality.
   - Render form-styling with embedded CSS for a dark, elegant visual design.
   - Display preview placeholders (dashed outlines) to suppress broken image icons until files are selected.
   - Render thumbnail galleries in uniform 5-column grids for both public and member views.
   - Introduce header polish (logo shadow, custom font) for improved brand readability.
   - Provide important notices regarding file retention and privacy policy.

4. **Security Considerations**  
   - **Input Validation**: This file assumes `process.php` and `functions.php` handle sanitization and validation—must be verified.
   - **File Uploads**: Relies on HTML to accept files, but file-type enforcement must also be handled server-side (e.g., MIME checks).
   - **Session Handling**: No session or CSRF protection visible—important for production environments.
   - **Exposed Directories**: If `watermarks/` is publicly browsable, sensitive assets may be viewable—should be protected by server config or `.htaccess`.

5. **Dependencies**  
   - PHP (≥ 7.0)
   - `config.php`: for DB connection
   - `functions.php`: must define reusable utilities
   - `process.php`: form submission logic handler
   - JavaScript (vanilla) for file preview
   - External files:
     - Images (e.g., `/watermarks/muse_signature_black.png`)

6. **Additional Notes**  
  - Embedded tooltips and placeholder previews enhance UX—ideal for artist onboarding.
  - Layout changes in `0.4.2-beta` improved thumbnail consistency and form clarity.
  - Design remains responsive but may benefit from further mobile-specific tweaks.
  - Consider moving from inline styles to external CSS for long-term scalability.
  - Clear daily file deletion policy should be tied to a secure and verifiable cron job or backend cleanup task.

---
### File Location
`/`

### File Name
`metadata_extractor.php`

1. **Purpose**  
   This script extracts metadata from an image file using ExifTool and formats selected, non-sensitive information into a well-structured Markdown file. It is intended for post-processing signed or watermarked images for documentation or audit purposes.

2. **Agent Role**  
   Acts as a **metadata extraction agent** that transforms raw Exif data into human-readable Markdown, facilitating provenance tracking, author verification, and image audit trails in the PixlKey ecosystem.

3. **Key Responsibilities**  
   - Parse command-line arguments (`--input`, `--output`).
   - Validate the input image and environment (presence of ExifTool).
   - Extract all available metadata from the image using ExifTool (`-j` JSON output).
   - Remove sensitive or irrelevant fields from the metadata.
   - Map technical Exif fields to human-friendly labels.
   - Organize selected metadata into logical sections (e.g., Basic Information, Technical Details).
   - Generate a structured Markdown table report and save it to the output path.

4. **Security Considerations**  
   - **Sensitive field exclusion**: Filters out potentially private or system-level metadata (e.g., file paths, inode data, permissions).
   - **Output sanitization**: Applies `htmlspecialchars()` to avoid Markdown injection or rendering anomalies.
   - **Shell safety**: Uses `escapeshellcmd()` and `escapeshellarg()` to prevent command injection in shell commands.
   - **No user input via web**: Meant for CLI use only, reducing attack surface for web-based injection.
   - Ensure file write access is scoped securely—output location should be sanitized if web-integrated in future.

5. **Dependencies**  
   - PHP (≥ 7.x recommended)
   - [ExifTool](https://exiftool.org/) CLI utility (must be available in system PATH)
   - Input image file with embedded metadata
   - Write access to output location for Markdown file

6. **Additional Notes**  
   - Designed for CLI environments only (`php metadata_extractor.php --input=... --output=...`).
   - Can be extended to support HTML output, PDF conversion, or integration into a GUI/admin panel.
   - Can be scheduled or automated as part of a post-upload processing pipeline.
   - When integrated into a larger app, consider externalizing exclusion lists and mappings via a config file or `.env` values for better maintainability.

---
### File Location
`/`

### File Name
`process_helpers.php`

1. **Purpose**  
   Provides helper functions and configuration variables for processing images, specifically adding a watermark and printing progress messages. Intended to support server-side automation of image preparation tasks.

2. **Agent Role**  
   Serves as a **utility agent** responsible for enhancing user feedback and automating image manipulation (e.g. watermarking). Supports CLI or web-driven workflows by reporting processing steps and modifying images in place.

3. **Key Responsibilities**  
   - Load configuration from `config.php`.
   - Define `echoStep()` for user-facing progress updates.
   - Define `addWatermark()` to:
     - Measure image width.
     - Resize watermark proportionally (~6% of image width).
     - Apply it bottom-right with a small margin (~1%).
     - Use ImageMagick’s `convert` and `identify` tools.
     - Clean up temporary resized watermark files.
   - Define shared configuration values:
     - `$processedDir` for output location.
     - `$defaultWatermark` path.
     - `$allowedExtensions` for valid image types.

4. **Security Considerations**  
   - **Shell commands**: `shell_exec()` and `escapeshellarg()` are used to sanitize inputs, but extreme care must be taken—avoid user-controlled input here.
   - **File existence**: Checks guard against null paths but do not verify image integrity or content type.
   - **Temporary file cleanup**: Removes intermediate resized watermark image, reducing footprint.
   - **Output flushing (`flush()`)**: Safe but consider rate-limiting or output buffering in large-scale or multi-user contexts.
   - **Path management**: Hardcoded paths assume a specific directory structure; symlink or path traversal abuse could be an issue if external inputs are introduced later.

5. **Dependencies**  
   - PHP (≥ 7.0 recommended)
   - `config.php` (must be in the same directory or appropriately routed)
   - ImageMagick CLI tools (`identify`, `convert`)
   - Web server or CLI access to execute the script

6. **Additional Notes**  
   - Designed to be included in larger scripts via `require_once`.
   - For robustness, you may wish to:
     - Validate image MIME types explicitly.
     - Abstract CLI commands into a safer wrapper or log them.
     - Wrap helper functions in a class or namespace for reusability.
     - Allow the `$allowedExtensions` array to be configurable externally.
   - Consider configuring fallback watermark behaviour if `$defaultWatermark` is missing.

---
### File Location
`/`

### File Name
`process.php`

1. **Purpose**  
   A core execution script in the Infinite Muse Toolbox. It handles the full lifecycle of an image processing request initiated from a form submission. This includes uploading images, sanitising and validating metadata, applying watermarks, embedding IPTC/XMP metadata, generating thumbnails/previews, extracting metadata, producing certificates, and packaging output files into a ZIP archive.

2. **Agent Role**  
   Serves as the **primary orchestration agent** in the image-processing pipeline. It binds together multiple helper scripts and external CLI tools to perform complex transformations and validations, then provides immediate browser-based visual feedback to the user throughout each step.

3. **Key Responsibilities**  
   - Initialize runtime (buffering, headers, styles).
   - Accept and validate form data (including dates and metadata fields).
   - Handle file uploads (images and optional watermark).
   - For each uploaded image:
     - Move to a unique run directory.
     - Sanitize filename and validate extension.
     - Remove existing metadata via `exiftool`.
     - Convert image to PNG via `convert` (ImageMagick).
     - Create a signed copy and apply watermark.
     - Embed detailed metadata (IPTC/XMP).
     - Compute hashes before/after embedding.
     - Generate thumbnail and preview versions.
     - Optionally watermark preview/thumbnail.
     - Extract metadata using a separate PHP extractor.
     - Generate Markdown certificate.
   - Zip final outputs and offer download link via `download_zip.php`.
   - Register each `runId` to the `processing_runs` table so `download_zip.php` and `store_data.php` can verify ownership before proceeding.

4. **Security Considerations**  
   - **Input Sanitization**: Uses `htmlspecialchars()` on metadata fields to avoid XSS.
   - **File Validation**: Checks extension and upload errors; avoids unsafe file types.
   - **Command Execution**: Uses `escapeshellarg()` to mitigate shell injection risks in external commands.
   - **Error Exposure**: Some shell commands may still echo stderr output in verbose form—consider hiding or logging internally in production.
   - **Writable Directory**: Assumes `$processedDir` is a safe, writable location—should not be web-accessible without `.htaccess` or equivalent protections.
   - **No Authentication**: Lacks session or permission checks—should not be deployed on a public-facing endpoint without access control.
   - **Debug Output**: Verbose `echoStep()` messages are visible—must be limited or removed in production.

5. **Dependencies**  
   - PHP with:
     - `PDO` (via `config.php`)
     - `file_uploads` enabled
   - Shell tools:
     - `exiftool`
     - `convert` (ImageMagick)
     - `identify` (ImageMagick)
     - `sha256sum`
     - `zip`
   - Helper files:
     - `process_helpers.php` (e.g., defines `echoStep()`)
     - `functions.php` (e.g., `addWatermark()`)
     - `metadata_extractor.php` (parses embedded metadata)
     - `config.php` (DB setup)
   - A writable directory defined in `$processedDir`.

6. **Additional Notes**  
   - Ideal for use in kiosk, admin, or local processing scenarios where trusted users interact with a protected frontend.
   - Designed to provide live feedback using a styled HTML interface and `flush()` calls.
   - Could be modularised further to separate responsibilities (e.g., extraction, packaging).
   - Consider migrating long-running CLI calls to background workers for scalability.
   - Requires `download_zip.php` to handle secure access to archived result files.

---
### File Location
`/`

### File Name
`store_data.php`

1. **Purpose**  
   This script ingests structured data—typically the result of a user-submitted image certification workflow—and maps it into a relational database (`infinite_image_tools`). It handles parsed metadata, signed image files, certificates, AI metadata, and submission details for a given `runId`, which corresponds to a specific processing session.

2. **Agent Role**  
   Acts as a **data ingestion and persistence agent**. It connects processed image-related metadata and assets to their normalized database schema across multiple tables, ensuring proper referential integrity and uniqueness via UUIDs.

3. **Key Responsibilities**  
   - Validate presence and existence of a `runId` directory.
   - Load and parse `data.json`, `submission.json`, and related metadata/certificate files.
   - Create or reuse normalized entries for keywords, genres, creators, bylines, and relational tables.
   - Populate `Artworks`, `Images`, `Certificates`, `AIMetadata`, and `Submissions` tables.
   - Compute SHA-256 hashes of image files for integrity checks.
   - Wrap all inserts into a single transactional context for atomicity.
   - Roll back on any failure to prevent partial writes.
   - Validate `runId` ownership using `processing_runs` before loading or persisting any data.

4. **Security Considerations**  
   - **Ownership check**: As of `0.4.1-beta`, enforces that the `runId` belongs to the logged-in user before loading filesystem or writing to the database.
   - **Filesystem operations**: Reads files from local storage based on user-supplied ID—ensure `processed/` is outside of public web root or has proper ACLs.
   - **SQL injection**: Mitigated via PDO prepared statements throughout.
   - **Rollback on failure**: Ensures no partial database writes or inconsistent state.
   - **Debug mode**: Controlled by `DB_DEBUG` in `config.php`; must be disabled in production to avoid leaking error stack traces.

5. **Dependencies**  
   - Requires:
     - `/config.php` for PDO connection
     - `/process_helpers.php` for any externally defined helpers (assumed)
     - `PDO` and `PDO_MYSQL` extensions in PHP
     - A valid MariaDB schema supporting:
       - `Artworks`, `Images`, `Certificates`, `AIMetadata`, `Keywords`, `Genres`, `Creators`, `Bylines`, `Submissions`, and mapping tables such as `ArtworkKeywords`, `ArtworkGenres`, etc.
   - Filesystem access to:
     - `${runId}/data.json`
     - `${runId}/*.png`, `*_metadata.txt`, `*_certificate.md`, `*_ai_metadata.json`, `submission.json`

6. **Additional Notes**  
   - Assumes that `generateUUID()` uses the database's `UUID()` function for guaranteed format alignment.
   - Requires strict schema adherence—column names and types must match what’s expected in this script.
   - May be extended in the future to handle:
     - Blockchain record logging
     - Digital signatures
     - Logging/analytics for ingestion history
   - Can be modularized further for reuse and maintainability (e.g., separate out insert handlers).