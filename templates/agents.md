### `/app/app.py`

**Purpose**:  
This is the main entry point for the Flask application. It handles configuration loading, blueprint registration, CSRF token handling, session teardown, database initialization, and conditional installer setup for the ImageProof system.

**Agent Role**:  
`app.py` acts as the **application orchestrator**, initializing and configuring the core Flask app. It wires together all routes, logging, CSRF handling, user context, background tasks, and database setup logic.

**Key Responsibilities**:
- Initializes Flask with template/static paths.
- Loads configuration from `config_object` (e.g., `DevelopmentConfig`).
- Configures logging and ensures log directories exist.
- Starts a background daemon thread to prune old log files daily.
- Registers CSRF token generation and validation via request hooks and context processors.
- Injects `current_user` context from Flask-Login (safe fallback using `SimpleNamespace` if unavailable).
- Registers blueprints for:
  - `public_bp`
  - `files_bp`
  - `member_bp` (with `/member` prefix)
  - `admin_bp` (with `/admin` prefix)
  - `stub_bp` (for incomplete routes)
  - `install_bp` (conditionally, if `INSTALL_SENTINEL_FILE` is missing)
- Initializes the database schema on first run.
- Optionally seeds development data when `seed=True`.

**Functions**:
- `create_app(config_object)`: Main factory that builds and returns a fully configured Flask app.
- `init_db(app, seed=False)`: Initializes DB tables and optionally seeds example data.
- `_start_log_prune_thread()`: Launches a daily log cleanup daemon using `Thread`.

**Security Features**:
- CSRF validation applied globally via `before_request`.
- Token injection provided via `csrf_field()` input helper and context-available token generator.

**Dependencies**:
- Internal: `config`, `logging_utils`, `models`, route blueprints, and CSRF utilities.
- External: `Flask`, `SQLAlchemy`, `flask_login`, `threading`, `logging`, `time`, `Path`.

**Installer Behavior**:
- If `INSTALL_SENTINEL_FILE` does **not** exist, the `install_bp` is registered and the setup screen will be available.

**Blueprint Routing Summary**:
| Blueprint     | URL Prefix   | Purpose                            |
|---------------|--------------|------------------------------------|
| `public_bp`   | `/`          | Public endpoints                   |
| `files_bp`    | `/`          | File upload and download routes    |
| `member_bp`   | `/member`    | Authenticated user actions         |
| `admin_bp`    | `/admin`     | Admin panel routes                 |
| `stub_bp`     | `/`          | Placeholder for incomplete routes  |
| `install_bp`  | `/install`   | Installer shown if sentinel absent |

``` 

Let me know when you’re ready to proceed with the next file.

### `/app/certificate.py`

**Purpose**:  
Handles generation of authenticity certificates and bundled ZIP packages for registered images in the ImageProof platform.

**Agent Role**:  
This module is responsible for **evidence packaging** — transforming image metadata and associated assets into verifiable formats suitable for download, archiving, or public verification. It produces either a PDF or JSON certificate and assembles a ZIP archive containing original files, processed versions, and metadata.

**Key Components**:

- `generate_qr_code(data: str) -> bytes`  
  Creates a PNG-format QR code from a string input. Used for embedding verification URLs into certificates.

- `generate_certificate(image_record: Image, fmt: str = "PDF") -> Path`  
  Builds a certificate of authenticity in either PDF or JSON format based on the `image_record`.  
  - PDF version includes human-readable metadata and a QR code for verification.
  - JSON version includes all serializable attributes of the `image_record`.

- `create_registration_package(...) -> Path`  
  Bundles the following into a downloadable ZIP file:
  - Generated PDF certificate (`certificate.pdf`)
  - Original uploaded image
  - Watermarked image
  - Optional: social media–sized image, signature overlay
  - `metadata.json` — serialized attributes of the image record

**Output Format**:
- `certificate.pdf` or `certificate.json`  
- ZIP file named `<sha256>.zip` containing all relevant assets

**Dependencies**:
- `reportlab` for PDF generation
- `qrcode` for QR code creation
- `zipfile`, `tempfile`, `io`, `pathlib` for file handling
- `app.models.Image` type for input (optional import with fallback)
- Logger: uses `logging.getLogger(__name__)` for traceability

**Error Handling**:
- Validates inputs for non-empty strings and existing files.
- Gracefully logs and raises exceptions on failure.
- Cleans up partial output (e.g., broken ZIPs or temp files) if exceptions occur.

**Security Notes**:
- Output files are written to temporary directories with unique names.
- No direct user input is written to disk without validation.

**Usage Context**:
Invoked during the final stages of image registration to produce verifiable proof artifacts. These can then be served to the user via download or embedded in verification workflows.

### `/app/config.py`

**Purpose**:  
Defines core configuration settings, environment-dependent variables, and logging behavior for the ImageProof application. This module is central to secure and flexible deployment across development and production environments.

**Agent Role**:  
`config.py` serves as the **configuration controller and environment bootstrapper**. It loads environment variables, sets constants for directory paths, manages security settings, and configures logging. It is designed to be imported early to ensure other modules access correct app settings.

**Key Components**:

- **Base Paths**:
  - `BASE_DIR`, `STATIC_DIR`, `TEMPLATES_DIR`, `UPLOAD_DIR`, `LOG_DIR`: Define critical filesystem paths.
  - `INSTALL_SENTINEL_FILE`: Used to determine if the application has completed installation setup.

- **Configuration Classes**:
  - `BaseConfig`: Defines shared defaults for secrets, database URI, debug state, upload constraints, session behavior, and CSRF settings.
  - `DevelopmentConfig`: Enables debug mode for local development.
  - `ProductionConfig`: Disables debug mode for production deployment.

- **Logging Setup**:
  - `configure_logging()`: Applies structured logging based on `LOG_LEVEL` or `DEBUG` mode.
  - Uses console-based output with timestamps and module info.

- **Security-Related Settings**:
  - `SECRET_KEY`: Pulled from environment or securely generated if absent.
  - `SESSION_COOKIE_SECURE`, `SESSION_COOKIE_HTTPONLY`, `SESSION_COOKIE_SAMESITE`: Provide secure cookie handling.
  - `CSRF_FIELD_NAME`: Unified token name used across forms.

**Environment Variables Expected**:
- `SECRET_KEY`
- `DATABASE_URI`
- `DEBUG`
- `LOG_LEVEL`
- `SESSION_COOKIE_SECURE`
- `SESSION_LIFETIME`
- `MAX_CONTENT_LENGTH`

**Dependencies**:
- `python-dotenv` (optional): To load variables from `.env`
- `os`, `pathlib`, `secrets`, `logging`, `logging.config`

**Usage Pattern**:
- Imported in early application bootstrap (e.g. `app.py`)
- Used by Flask app factory for dynamic configuration selection.
- Invoked to configure runtime logging policies with `configure_logging()`.

**Notes**:
- Handles both default and user-specified `.env` values.
- Gracefully skips dotenv loading if the module isn't installed (`ImportError` is caught).

### `/app/image_processing.py`

**Purpose**:  
Handles core image analysis logic for ImageProof, including image fingerprinting (hashing), similarity detection, and feature extraction. This agent supports duplicate detection and visual comparison workflows.

**Agent Role**:  
This module functions as the **image analysis engine**, responsible for generating and comparing visual fingerprints from uploaded content. It interfaces with the database to retrieve and rank visually similar entries.

**Key Responsibilities**:
- **Hashing**:
  - `compute_sha256`: Returns the SHA-256 digest of an image's raw bytes (for exact duplicates).
  - `compute_perceptual_hash`: Computes a perceptual hash (pHash) using the `imagehash` library for visual similarity detection.

- **Feature Extraction**:
  - `extract_orb_features`: Extracts ORB (Oriented FAST and Rotated BRIEF) feature descriptors using OpenCV for more advanced image comparison (returns `np.ndarray`).

- **Similarity Comparison**:
  - `phash_similarity`: Compares two pHashes by Hamming distance (bitwise XOR and normalization).
  - `find_similar_images`: Queries the database for similar images using either:
    - Optimized SQL (`BIT_COUNT`) for MySQL/MariaDB, or
    - Python-based scoring fallback (for other engines like SQLite).

**Dependencies**:
- `PIL.Image` for image input.
- `imagehash` for perceptual hashing.
- `cv2` (OpenCV) for ORB feature extraction.
- `numpy` for image matrix manipulation.
- `sqlalchemy` for database interaction.
- `app.models.Image` and `SessionLocal` for ORM/database access.

**Security & Performance Notes**:
- OpenCV is optional but required for feature-level analysis.
- Uses parameterized SQL safely when interacting with raw queries.
- Logging is integrated throughout for observability.
- The fallback approach ensures compatibility with non-MySQL engines.

**Error Handling**:
- Graceful fallback if `cv2` is not available.
- Logs and continues on similarity calculation errors or query exceptions.

**Used By**:
- Likely invoked by upload or search endpoints in image ingestion pipelines, especially where duplicate or near-duplicate detection is necessary.

### `/app/logging_utils.py`

**Purpose**:  
Provides centralized logging configuration and maintenance utilities for the ImageProof application, including log level management, file rotation, and old log pruning.

**Agent Role**:  
Acts as the **logging configuration agent**, responsible for setting up, formatting, maintaining, and pruning log files for system observability. Ensures that logging is consistent across the app and respects configuration constraints defined in `config.py`.

**Key Functions**:
- `init_logging()`  
  Initializes the root logger with a `RotatingFileHandler`. Automatically creates the log directory if missing. Applies a standardized log format and log level.
  
- `set_log_level(level: str)`  
  Dynamically sets the root logger's level. Validates the input against `config.LOG_LEVEL_CHOICES`.

- `get_log_level()`  
  Retrieves the current effective logging level as a string.

- `prune_old_logs()`  
  Deletes log files older than `config.LOG_RETENTION_DAYS`, based on modification time. Supports rotating backups (e.g., `logfile.log.1`, `logfile.log.2`, etc.).

**Dependencies**:
- `app.config` for:
  - `LOG_DIR`: log storage path.
  - `LOG_FILE`: main log filename.
  - `LOG_FILE_MAX_BYTES`: size before rotation.
  - `LOG_BACKUP_COUNT`: number of rotated files to keep.
  - `LOG_LEVEL_DEFAULT`: initial log verbosity.
  - `LOG_LEVEL_CHOICES`: whitelist of acceptable levels.
  - `LOG_RETENTION_DAYS`: cutoff for deletion.

**Security Notes**:
- File pruning uses `missing_ok=True` to prevent unhandled exceptions.
- No user interaction is involved; all behavior is backend/internal.

**Suggested Invocation**:
- `init_logging()` should be called early during application startup (e.g., in `create_app()` or `app.py`).
- `prune_old_logs()` could be tied to a daily job or startup hook.

### `/app/models.py`

**Purpose**:  
Defines all SQLAlchemy ORM models and initializes the database engine and session for the ImageProof application.

**Agent Role**:  
`models.py` acts as the **data layer agent**, defining the schema, relationships, and engine bindings necessary for persistent storage and retrieval of user, image, and audit log data. It also configures the SQLAlchemy engine and scoped session used throughout the application.

**Core Models**:
- **`User`**:  
  Represents application users. Stores email and hashed password.  
  Relationships:  
  - One-to-many with `Image`  
  - One-to-many with `ActionLog`

- **`Image`**:  
  Represents an uploaded image, including cryptographic and perceptual hashes for comparison.  
  Relationships:  
  - Belongs to one `User`  
  - One-to-many with `ActionLog`  
  - Indexed by `sha256` and `phash` for efficient deduplication or lookup.

- **`ActionLog`**:  
  Audit log for recording actions performed by users on images.  
  Fields include timestamp, action description, and optional metadata.  
  Relationships:  
  - Belongs to `User`  
  - Belongs to `Image`

**Key Relationships**:
- `User.images` ↔ `Image.user`
- `User.action_logs` ↔ `ActionLog.user`
- `Image.action_logs` ↔ `ActionLog.image`

**Engine Configuration**:
- Pulls `DATABASE_URI` from `app.config.BaseConfig`
- Uses `create_engine()` with InnoDB backend and UTF-8MB4 charset
- Initializes a thread-safe `scoped_session` via `sessionmaker`

**Key Function**:
- `create_all()` – Generates tables from ORM metadata on first-time setup or migration. Logs success/failure and raises `SQLAlchemyError` on issues.

**Dependencies**:
- `sqlalchemy`, `sqlalchemy.dialects.mysql`
- `app.config` for database URI
- Python standard libraries: `logging`, `datetime`, `typing`

**Error Handling**:
- Logs and raises detailed errors if engine or session initialization fails
- Fails fast if `DATABASE_URI` is missing or invalid

**Security Notes**:
- Passwords are stored hashed (implies use of proper auth handling in other modules)
- Strong typing (`Mapped[...]`) used throughout for type safety and linting compatibility

### `/app/routes_admin.py`

**Purpose**:  
Defines admin-only route handlers for ImageProof, focusing primarily on system logging access and control. This module is isolated under an `admin` blueprint and intended strictly for users with elevated privileges.

**Agent Role**:  
This file functions as the **backend controller** for administrative utilities, specifically targeting application logging. It manages log visibility, log level changes, and secure file downloads for audit or debugging purposes.

**Blueprint**:  
- `admin_bp` registered under the name `"admin"`
- `template_folder` is explicitly set to `../templates`

**Routes**:

1. `"/"` – `admin_home()`  
   - Placeholder route that returns a static string.  
   - Potential future dashboard or redirect entry point for admin interface.

2. `"/logs"` – `logs_dashboard()`  
   - `GET`: Shows current log level and a list of available log files (name, size in MB, last modified time).  
   - `POST`: Accepts a new log level from the form and updates it via `logging_utils.set_log_level()`.  
   - Requires admin role via `@require_login(role="Admin")`.  
   - Renders `logs.html`.

3. `"/logs/download/<fname>"` – `download_log(fname)`  
   - Serves log files from the configured `LOG_DIR` if `fname` matches a strict pattern: `imageproof.log`, `imageproof.log.1`, `imageproof.log.2.gz`, etc.  
   - Protects against path traversal and invalid file access with a regex guard.  
   - Requires admin role.

**Security**:
- All routes use `@require_login(role="Admin")` for access control (except the placeholder root).
- File access is validated using `re.fullmatch()` to ensure only known-safe log files are served.
- Download endpoint uses `send_from_directory(..., as_attachment=True)` to force safe delivery.

**Dependencies**:
- `logging_utils` for log level handling
- Flask `Blueprint`, `render_template`, `send_from_directory`, etc.
- `app.security.require_login` for role-based protection
- `current_app.config["LOG_DIR"]` and `LOG_LEVEL_CHOICES`
- `logs.html` template (renders log dashboard)

**Future Considerations**:
- Expand `admin_home()` to provide a centralized dashboard for system monitoring.
- Include features like user management, config inspection, or job/task monitoring.

### `/app/routes_files.py`

**Purpose**:  
Defines routes for uploading and downloading files within the ImageProof application. This module ensures secure handling of file transfers, validating allowed extensions and preventing unsafe filenames.

**Agent Role**:  
This file acts as the **File Transfer Agent**, responsible for mediating between the frontend and file storage layer. It provides API endpoints to accept user uploads and serve stored files, using `Blueprint` routing for modular integration.

**Blueprint**:  
- `files_bp`  
  - URL Prefix: N/A (routes are mounted directly at `/upload` and `/download/<filename>`).
  - Template folder is set but unused in this module.

**Endpoints**:
- `POST /upload`  
  - Accepts a file upload via form-data.
  - Validates presence and type of file (`ALLOWED_EXTENSIONS`).
  - Saves to the configured `UPLOAD_FOLDER` using a secure filename.
  - Returns a simple `"uploaded"` confirmation string.
  - Logs uploaded file path using Python’s logging.

- `GET /download/<filename>`  
  - Serves a file from the `UPLOAD_FOLDER` directory as an attachment.
  - If the file does not exist, aborts with a 404 error.

**Key Functions**:
- `_allowed_file(filename)` – Validates file extension against the configured list.

**Security Notes**:
- Uses `secure_filename()` to sanitize user-uploaded filenames, mitigating path traversal risks.
- Verifies file existence before download.
- Only allows whitelisted extensions as configured in the Flask app.

**Dependencies**:
- Flask core (`Blueprint`, `request`, `abort`, `current_app`, `send_from_directory`)
- Werkzeug’s `secure_filename`
- Python built-ins: `logging`, `pathlib.Path`

**Configuration Keys Expected**:
- `UPLOAD_FOLDER` – Filesystem path where uploads are stored.
- `ALLOWED_EXTENSIONS` – Iterable of permitted file extensions (e.g., `.png`, `.pdf`, etc.).

### `/app/routes_install.py`

**Purpose**:  
Defines the route logic for the one-time installation screen of the ImageProof application. This interface allows initial configuration of the database and admin credentials on first access.

**Agent Role**:  
`routes_install.py` serves as the **installer controller** in the MVC pattern. It validates form input, flashes errors or success messages, and renders the installation interface. It is intended to be used only during first-run setup of the application.

**Blueprint**:  
- `install_bp` – URL prefix: `/install`
- Template directory: `../templates`

**Exposed Route**:
- `@install_bp.route("/install", methods=["GET", "POST"])`
  - GET: Presents the installation form.
  - POST: Validates and processes user-submitted installation data.

**Form Fields Validated**:
- `db_host`, `db_port`, `db_name`, `db_user`, `db_password`
- `admin_email` (must be a valid email format)
- `admin_password` (must be at least 8 characters)

**Key Behaviors**:
- On POST:
  - Collects and validates fields for completeness.
  - Applies basic regex validation for email.
  - Enforces minimum password length for admin user.
  - Displays errors using `flash()` with `"danger"` category.
  - If successful, redirects to `public.index` and flashes a `"success"` message.

**Template Used**:
- `install.html`

**Security Notes**:
- No CSRF protection is enforced here. Consider adding `{{ csrf_field() }}` in the template and validating it in the route.
- This route should be locked or disabled after installation to prevent configuration tampering.

**Future Enhancements (recommended)**:
- Write config to persistent storage (e.g., `.env` or database).
- Auto-disable or remove this route after successful install.
- Add server-side enforcement to block access after first setup.

### `/app/routes_member.py`

**Purpose**:  
Defines authenticated member-facing routes for the ImageProof application. This module serves as the entry point for rendering pages and handling requests made by logged-in users, primarily via the `member` blueprint.

**Agent Role**:  
`routes_member.py` registers the `member_bp` Flask blueprint, associates it with the appropriate templates directory, and defines placeholder routes for future member-facing features.

**Key Features**:
- Registers the `member` blueprint for route organization.
- Serves a basic root route (`/`) for members, currently returning a placeholder string.

**Defined Routes**:
- `@member_bp.route("/")`:  
  - **Endpoint**: `member.member_home`  
  - **Returns**: Static string `"ImageProof member"`  
  - **Planned**: Expected to be replaced with a dashboard-style view or redirect to user-facing interface.

**Dependencies**:
- `flask.Blueprint` for modular route registration.
- Template folder set to `../templates` to access shared HTML files.

**Template Use**:  
Currently, the route does not render a template — it returns a string. However, the template folder is defined, indicating future integration with views like `account_settings.html`.

**Status**:  
**Stub** — meant to be expanded in later phases for full member functionality (e.g., dashboard, image management, logs, etc.).

### `/app/routes_public.py`

**Purpose**:  
Defines all **public-facing routes** for the ImageProof application. These endpoints serve general-access pages such as home, login, registration, and basic lookup, with placeholder logic for future implementation.

**Agent Role**:  
The `routes_public.py` file acts as the **entry point for unauthenticated user interactions**. It is associated with the `public_bp` Blueprint and maps route URLs to appropriate HTML templates or stubbed responses.

**Key Routes**:
- `/` → Renders the public-facing homepage (`index.html`).
- `/lookup` → Accepts POST requests; currently returns a static placeholder response (`"lookup"`).
- `/register` → Displays the guest registration page (`register_guest.html`).
- `/login` → 
  - `GET`: Serves the login page (`login.html`).
  - `POST`: Stubbed login handler (returns `"login", 200`).
- `/signup` → 
  - `GET`: Serves the account creation page (`signup.html`).
  - `POST`: Stubbed signup handler (returns `"signup", 200`).

**Blueprint Configuration**:
- **Name**: `public`
- **Template folder**: `../templates` (relative to this file)

**Responsibilities**:
- Serves templates that comprise the public UI layer.
- Handles basic user actions like registration and authentication (placeholders for now).
- Provides structure for expansion into full authentication workflows.

**Status**:
- Authentication logic (login/signup) is not yet implemented. Routes are placeholders pending integration with backend validation and session management.

**Dependencies**:
- `flask.Blueprint`, `render_template`, `request`

**Security Notes**:
- No CSRF protection or form handling logic implemented yet.
- All sensitive operations (`/login`, `/signup`) need validation and session/token setup before deployment.

### `/app/routes_stub.py`

**Purpose**:  
Provides a fallback or placeholder route handler for incomplete or under-construction endpoints within the ImageProof application.

**Agent Role**:  
`routes_stub.py` defines the **Stub Agent**, a minimal Flask `Blueprint` that intercepts requests to undefined or partially implemented routes. It returns a clear HTTP `501 Not Implemented` response with a dynamic placeholder message.

**Key Features**:
- Registers a `Blueprint` named `"stub"`.
- Serves from the `/app/routes_stub.py` location with templates rooted at `../templates`.
- Defines a single route:  
  `/_stub/<path:name>` — responds with `"Placeholder for <name>"`, HTTP status `501`.

**Typical Use Case**:
- During early development or staged rollouts, stub endpoints can be used to:
  - Ensure route discovery works end-to-end.
  - Prevent "404 Not Found" confusion during incremental implementation.
  - Serve as a signal for missing functionality.

**Dependencies**:
- `flask.Blueprint`

**HTTP Status Code**:
- Returns `501 Not Implemented` for all stubbed paths.

**Security Notes**:
- No authentication or input sanitation is enforced — intended for development only.
- Should be disabled or protected in production deployments.

**Routing Pattern**:
- `/_stub/<path:name>` accepts and echoes any subpath (e.g., `/_stub/image/upload`, `/_stub/admin/settings`).

**Integration Note**:
- Codex and other automation tools may use this stub to safely register routes before logic is implemented.

### `/app/security.py`

**Purpose**:  
Provides **lightweight CSRF protection** and a **mock login requirement decorator** for use in the ImageProof application. It avoids reliance on external libraries for CSRF handling, enabling tighter control and customization in low-dependency environments.

**Agent Role**:  
This module acts as a **middleware utility** for form and request integrity. It ensures that state-changing operations (e.g., POST, PUT) are protected against Cross-Site Request Forgery by validating tokens stored in the user's session. It may also be used to wrap route functions in basic login checks (placeholder-only as coded).

**Key Features**:
- `generate_csrf_token()`:  
  Generates a secure session-based token if one does not already exist.  
  Used in templates to inject CSRF protection into forms.
  
- `validate_csrf_token()`:  
  Validates CSRF token from form data (`csrf_token`) or header (`X-CSRFToken`) against the session-stored token.  
  Aborts with HTTP 400 if invalid or missing (except for safe methods like GET/HEAD/OPTIONS).
  
- `require_login(role=None)`:  
  A **dummy decorator** designed for testing login-required routes. Does not enforce auth but maintains decorator structure for easy replacement or mocking.

**Dependencies**:
- `flask.request`, `flask.session`, and `flask.abort`
- `secrets` for token generation
- `functools.wraps` for preserving metadata in decorators

**Security Notes**:
- Does not use Flask-WTF; this is a custom CSRF implementation tailored for minimal setups.
- Session storage must be configured securely (e.g., HTTPOnly cookies, secure transport).
- The login decorator is **non-functional for production use**—it's a placeholder and should be replaced by a real authentication check (e.g., from `flask_login`).

**Usage Context**:
- Used by HTML templates like `account_settings.html` via `{{ csrf_field() }}`.
- Should be called in route handlers that process sensitive or modifying requests to prevent forgery.

### `/app/watermark.py`

**Purpose**:  
Provides core image watermarking logic for ImageProof, supporting both text-based and image-based overlays. Used to modify images by applying semi-transparent annotations or branding elements at defined positions.

**Agent Role**:  
This agent handles **image augmentation and watermarking**, forming a key component of the image processing pipeline. It provides a clean interface for applying multiple overlays—either text or images—on an input image, while enforcing limits and ensuring format compatibility.

**Key Functions**:

- `_calculate_position(base_size, overlay_size, position)`  
  Determines the (x, y) coordinate where an overlay should be placed. Supports keywords: `"top-left"`, `"top-right"`, `"bottom-left"`, `"bottom-right"`, `"center"`.

- `apply_text_watermark(image, text, position, color, opacity)`  
  Draws a semi-transparent text string onto the image at a specified location. Uses default font and alpha blending.

- `apply_image_watermark(image, overlay_img, position, opacity)`  
  Places a semi-transparent image (logo, stamp, etc.) as a watermark on the target image at the specified position.

- `apply_overlays(image, overlays)`  
  Main interface for applying multiple watermark layers. Accepts a list of overlay dictionaries specifying type (`"text"` or `"image"`), position, opacity, etc. Enforces `MAX_OVERLAYS` limit (default: 3).

**Design Notes**:
- All image modes are normalized to `"RGBA"` for alpha compositing.
- Logging is integrated for debugging watermark placement and errors.
- The default font is used for portability—this could be extended to support custom fonts.
- Uses `PIL` (`Pillow`) for all image manipulations.

**Dependencies**:
- `Pillow` (`Image`, `ImageDraw`, `ImageFont`, `ImageColor`)
- Python standard library: `logging`, `typing`

**Constants**:
- `MAX_OVERLAYS = 3` — prevents overload of composite complexity and clutter in output images.

**Failure Modes**:
- Raises `ValueError` on:
  - Invalid position keyword.
  - Unsupported overlay type.
  - Exceeding overlay count.
  - Malformed color strings.

**Security & Performance Notes**:
- Accepts pre-sanitized input from upstream modules.
- Assumes that overlay image objects are validated prior to this call.
- Could be optimized in future with batched GPU overlays or font caching for high-volume processing.

### `/static/main.js`

**Purpose**:  
Provides core client-side JavaScript functionality for basic UI interactivity within the ImageProof web interface.

**Agent Role**:  
Acts as the **UI behavior controller** for the site’s navigation menu. It enhances user interaction by toggling the visibility of navigation links, especially useful for responsive layouts or mobile-friendly interfaces.

**Key Features**:
- Defines a `toggleNav()` function that adds or removes the `open` class on the `.nav-links` element.
- Attaches a click event listener to the element with `id="nav-toggle"` after the DOM content is fully loaded.
- Enables responsive navigation toggling without requiring additional JS frameworks.

**Dependencies**:
- Expects an element with class `.nav-links` in the DOM.
- Expects a toggle trigger element with ID `#nav-toggle`.

**Security Notes**:
- No direct DOM injection or unsafe input handling.
- Safe from XSS as written, due to limited scope and no user-generated input.

### `/static/styles.css`

**Purpose**:  
Defines the global styling rules for the ImageProof web application, including theming, layout, typography, responsiveness, and UI component appearance. It provides visual consistency across the entire frontend interface.

**Agent Role**:  
`styles.css` functions as the **UI stylist** for ImageProof, translating structural HTML into a cohesive dark-themed visual language. It enhances accessibility, interactivity, and brand cohesion across all user-facing pages.

**Key Responsibilities**:
- Declares root CSS variables for theme control (colors, background, accents).
- Sets default font stack using imported Google Fonts (`Inter`, `Roboto`).
- Applies dark mode styling for body, text, input fields, and containers.
- Styles key layout components: navbar, footer, sections, and cards.
- Defines reusable class utilities: `.btn`, `.alert`, `.banner`, `.form-group`, `.text-muted`.
- Supports user feedback with status indicators (`.certified`, `.disputed`, `.revoked`) and alert classes (`.success`, `.error`, `.info`).
- Implements responsive layout adjustments for small screens (`max-width: 600px`).
- Provides dedicated styling for image grid layout on dashboards and watermark previews.

**UI Elements Styled**:
- Typography (headings, paragraphs, links)
- Forms (inputs, textareas, buttons)
- Navigation bar
- Alerts and banners
- Cards and tables
- Image preview containers
- User status labels (certified/disputed/revoked)
- Responsive layout with media queries

**Visual Theme**:
- **Dark background**, muted borders, and high-contrast accents.
- **Accent color**: Old gold (`#d4af37`) to highlight active links, buttons, and certified statuses.
- Subtle **box shadows** and **hover effects** for interactivity.

**Dependencies**:
- Relies on the document structure defined by Jinja2 templates (e.g., `.navbar`, `.card`, `.image-grid`).
- Consumed in base template (`base.html`) for global accessibility.

**Security & Accessibility Notes**:
- Uses `:focus` and hover states for better keyboard and screen-reader accessibility.
- Avoids inline styles in favor of reusable, class-based design system.

### `/templates/account_settings.html`

**Purpose**:  
Provides a front-end interface for users to manage personal account settings in the ImageProof application. This includes changing their email, updating their password, managing Multi-Factor Authentication (MFA), and initiating data export or account deletion requests.

**Agent Role**:  
`account_settings.html` acts as the **presentation layer** for the authenticated user settings workflow. It consumes backend data via Flask's templating system (`{{ current_user }}` and `url_for(...)`), displays conditional content based on MFA state, and sends POST requests to various routes for account management operations.

**Key Features**:
- Shows current user email and MFA status.
- Allows changing email (with password verification).
- Allows changing password (with confirmation).
- Supports enabling, confirming, and disabling MFA (with password and OTP code).
- Provides buttons to request a data export or initiate account deletion (with a JavaScript confirmation prompt).
- Renders server-side flash messages for contextual user feedback per section.

**Template Blocks Used**:
- `title`: Sets browser tab title.
- `csrf_token`: Defined but overridden in the child template.
- `content`: Main HTML section rendered.
- `scripts`: Inherits from base for optional JS additions.

**Dependencies**:
- `base.html`
- `current_user` object from Flask-Login.
- Flask-WTF or custom CSRF token helpers (`{{ csrf_field() }}`).
- `member` blueprint routes:
  - `member_home`
  - `change_email`
  - `change_password`
  - `enable_mfa`
  - `disable_mfa`
  - `confirm_mfa`
  - `request_data_export`
  - `request_account_deletion`

**Security Notes**:
- All forms include CSRF protection.
- MFA changes require password and OTP code confirmation.
- Account deletion form includes an inline `confirm()` prompt for user verification.

### `/templates/admin_dashboard.html`

**Purpose**:  
Provides the interface for administrative oversight and control within the ImageProof platform. This dashboard offers real-time statistics, management tools, and a log of recent administrative activity, accessible only to users with the `Admin` role.

**Agent Role**:  
`admin_dashboard.html` serves as the **administrative control panel** for the application. It pulls live data from the backend (`stats`, `recent_actions`) and exposes internal tooling via clearly labeled interface components.

**Key Features**:
- Displays key metrics:
  - `Total Registered Images`
  - `Active Users`
  - `Flagged Images in Review`
  - `Disputed/Revoked Records`
- Provides direct access to critical admin functions:
  - View flagged images
  - Manage user accounts
  - Review system logs
  - Run duplicate detection
  - Access system settings
- Shows a scrollable list of `Recent Activity`, detailing admin-relevant action logs.
- Restricted access: only shown to authenticated users with `role == 'Admin'`.

**Template Blocks Used**:
- `title`: Custom tab title for admin interface.
- `content`: Core dashboard layout and content logic.
- `scripts`: Inherits optional JavaScript from `base.html`.

**Dependencies**:
- `base.html`
- `current_user` object from Flask-Login.
- `stats`: Dictionary-like object passed in context.
- `recent_actions`: List of admin logs (with `timestamp`, `description`, `action`).
- Admin routes:
  - `admin.view_flags`
  - `admin.manage_users`
  - `admin.system_logs`
  - `admin.duplicate_checker`
  - `admin.system_settings`

**Security Notes**:
- Access is conditional on both authentication and admin role:
  ```jinja
  {% if current_user.is_authenticated and current_user.role == 'Admin' %}
  ```
- Non-admin users attempting to access the page will be shown a minimal denial message.

**Visual Design**:
- Responsive grid layout for metrics and tools.
- Semantic sectioning (`admin-tools`, `recent-activity`) enhances usability and future accessibility.

### `/templates/base.html`

**Purpose**:  
Acts as the master layout template for all pages in the ImageProof web application. It defines the HTML structure, metadata, security headers, navigation bar, flash message display, and layout blocks that are extended by child templates.

**Agent Role**:  
`base.html` serves as the **layout engine** and structural backbone for all user-facing HTML templates. Other templates like `account_settings.html` extend this file to inherit styling, navigation, and layout consistency.

**Key Features**:
- Defines core HTML structure with accessibility-friendly `lang`, `meta`, and ARIA attributes.
- Sets important security headers:
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Content-Security-Policy` with restricted source domains
- Links external fonts (`Inter`) and static CSS/JS assets via Flask's `url_for()`.
- Provides a responsive and accessible navigation bar that adapts based on login state.
- Displays flash messages from Flask's message system.
- Includes a `<noscript>` warning for users with JavaScript disabled.

**Template Blocks Available**:
- `title`: Overrides the browser title tag.
- `csrf_token`: Includes CSRF token as a meta tag, overridable by children.
- `content`: Main injection point for child page content.
- `scripts`: Allows pages to inject additional scripts beneath the default JS.

**Dependencies**:
- Assumes availability of:
  - Flask-Login's `current_user` context variable
  - Routes in `public` and `member` blueprints:
    - `public.index`, `public.login`, `public.signup`
    - `member.member_home`
- Requires static files:
  - `/static/styles.css`
  - `/static/main.js`

**Security Notes**:
- Sets a restrictive `Content-Security-Policy` to mitigate XSS and injection attacks.
- CSRF token is injected via `<meta>` tag for JS usage or form integration.
- Navigation conditionally adapts based on user authentication state.

**Design Philosophy**:
- Dark/light mode ready (`color-scheme` meta).
- Clean, minimal layout with semantic HTML.
- Accessibility-conscious (ARIA labels, keyboard-friendly structure).

### `/templates/dashboard.html`

**Purpose**:  
Serves as the main user dashboard for authenticated members of the ImageProof application. Displays a summary of registered images, user-specific actions, and current notifications.

**Agent Role**:  
`dashboard.html` functions as the **authenticated user landing page**, presenting dynamic content such as image registrations and system notifications. It interfaces with the backend via `current_user`, `images`, and `get_flashed_messages`.

**Key Features**:
- Welcomes the user by `username`.
- Displays a grid of registered images with:
  - Title, registration date, and status tag.
  - Action buttons for viewing, downloading certificates, and transferring ownership.
- Lists notifications using Flask’s flash messaging system.
- Provides a call-to-action button to register a new image.

**Template Blocks Used**:
- `title`: Sets the page title in the browser tab.
- `content`: Contains all dashboard sections.
- `scripts`: Appends additional JS scripts from the base template.

**Dependencies**:
- `base.html`
- Flask-Login's `current_user`
- Image list passed to template as `images`
- Flash message system (`get_flashed_messages`)
- `member` blueprint routes:
  - `view_image`
  - `download_certificate`
  - `transfer_image`
  - `register_image`

**Accessibility Notes**:
- Action buttons include `aria-label` attributes for screen reader clarity.
- Flash messages are wrapped with `role="alert"` for accessibility compliance.

**User Experience Notes**:
- If no images are registered, the user is gently informed.
- Empty notification state is explicitly stated to avoid confusion.

### `/templates/flagged_detail.html`

**Purpose**:  
Provides the detailed administrative interface for reviewing, auditing, and resolving flagged images in the ImageProof platform. This page is exclusively accessible to users with the `Admin` role and serves as a case-management tool for content flagged by other users.

**Agent Role**:  
`flagged_detail.html` functions as an **administrative decision interface**. It displays metadata about the flagged image, the nature of the report, and any prior administrative actions taken. It allows the admin to assign a new status (`Certified`, `Disputed`, `Revoked`) and optionally log a note for auditing.

**Key Features**:
- Requires `current_user` to be authenticated and of role `Admin`.
- Displays image metadata (title, artist, registration date, hashes, preview, status).
- Includes flag details: who reported it, when, and why.
- Renders an optional **Action History** log of past administrative interventions.
- Provides a form for updating the image status and submitting an administrative note.

**Template Blocks Used**:
- `title`: Sets the browser tab title.
- `csrf_token`: Declared block for token injection.
- `content`: Main HTML page content for admins.
- `scripts`: Inherits from base template, available for custom JS.

**Dependencies**:
- `base.html`
- `current_user` from Flask-Login with `role == 'Admin'`
- `image`, `flag`, and `action_history` passed in from the controller.
- Flask route bindings:
  - `admin.flagged_list` (back navigation)
  - `admin.update_flag` (form submission)

**Security Notes**:
- Form uses `POST` method with CSRF protection (`{{ csrf_field() }}`).
- Template is gated by user role: non-admins will see an access-denied message.
- Admin notes are stored alongside the status update, preserving decision context.

**UX Considerations**:
- Status tags and metadata are styled for visual scanning.
- Preview image is rendered inline for contextual review.
- Action history enhances transparency and traceability.

### `/templates/flagged_list.html`

**Purpose**:  
Displays a searchable, filterable list of **flagged or disputed images** in the ImageProof system. Access is restricted to admin users. The interface allows administrators to review flagged records and navigate to their detailed inspection pages.

**Agent Role**:  
`flagged_list.html` is the **admin-facing review dashboard** for content flagged by users or automated checks. It serves as a control panel for moderation workflows.

**Key Features**:
- Only accessible to users with `current_user.role == 'Admin'`.
- Lists each flagged record with:
  - Record ID
  - Truncated image hash
  - Reporter identity
  - Status (e.g., "Disputed")
  - Link to detailed review view
- Status is styled with a dynamic CSS class (e.g., `status-disputed`).
- Filterable by status via a dropdown menu (`Disputed`, etc.).
- Displays flash messages for admin feedback (e.g., successful moderation action).
- Includes fallback messaging if no records are present.

**Template Blocks Used**:
- `title`: Sets document title.
- `csrf_token`: Present but unused in this GET-driven template.
- `content`: Primary rendering block for the page body.
- `scripts`: Allows additional JavaScript injection from the base template.

**Dependencies**:
- `base.html`
- `current_user` object from Flask-Login.
- Flask `flash()` messages with category metadata.
- `flagged_records`: A list of records passed by the route/controller.
- `admin.flagged_detail` route: Accepts `flag_id` parameter for drill-down.

**Security Notes**:
- Strictly checks for `Admin` role before rendering sensitive content.
- Graceful fallback for unauthorized users ("You do not have access...").
- No CSRF-sensitive actions in this template; only GET requests and links.

**UX Considerations**:
- Table layout is clear and responsive.
- Pagination or sorting not shown but may be desirable for long lists.
- Uses semantic roles (`role="alert"`, `role="search"`) for accessibility.

### `/templates/image_detail.html`

**Purpose**:  
Displays a detailed view of a registered image in the ImageProof platform, including metadata, status, and user-specific actions. This template serves as the main per-image interface for both authenticated and anonymous users.

**Agent Role**:  
Acts as a **context-sensitive viewer** for image data. It provides conditionally rendered UI elements based on the `authorized` flag, image `status`, and metadata availability. It links to download options, editing tools, and administrative controls if permitted.

**Key Features**:
- Displays:
  - Image preview (watermarked)
  - Metadata: creator, registration date, hashes (SHA-256, pHash), license
  - Image status tag with visual emphasis for `Flagged` or `Revoked` states
  - Optional QR code for quick verification
- Action buttons for:
  - Downloading a certificate
  - Downloading a ZIP package of related assets
- Admin-like actions (shown only if `authorized`):
  - Edit metadata
  - Transfer ownership
  - Delete image (with JavaScript confirmation)
  - Flag image for moderation or dispute

**Template Blocks Used**:
- `title`: Sets the browser window/tab title.
- `csrf_token`: Defined as an empty block; used within forms with `{{ csrf_field() }}`.
- `content`: Renders the full image detail UI.
- `scripts`: Extends parent scripts.

**Dependencies**:
- `base.html`
- `image` object (must include: `title`, `status`, `preview_url`, `creator_name`, `registered_at`, `sha256`, `phash`, `license_type`, `qr_url`, `id`)
- `authorized` boolean context variable
- CSRF token helper: `{{ csrf_field() }}`
- `member` blueprint routes:
  - `download_certificate`
  - `download_zip`
  - `edit_image`
  - `transfer_image`
  - `delete_image`
  - `flag_image`

**Security Notes**:
- Destructive actions (delete, flag) require POST requests with CSRF protection and visual confirmation prompts.
- Content visibility and actions are permission-gated by the `authorized` context flag.
- Displays only public information to unauthenticated users, preventing metadata leaks.

**Special Notes**:
- Designed to fallback gracefully if user is not logged in (`authorized == False`).
- Ready for potential PHP porting (note in comment suggests Jinja2-to-PHP translation is anticipated).

### `/templates/index.html`

**Purpose**:  
Serves as the public landing page for the ImageProof application, introducing users to its core functionality—verifying and protecting digital artwork. It includes an immediate image/hash verification form and calls to action for registration or login.

**Agent Role**:  
`index.html` functions as the **anonymous user interface and marketing entry point**. It provides a user-friendly way for visitors to test the verification system, learn about core features, and either log in or create an account. It is part of the `public` blueprint flow.

**Key Features**:
- Hero section with branding (`ImageProof`) and tagline.
- Public SHA-256 hash lookup or image upload form, using:
  - `POST /public/lookup`
  - `{{ csrf_field() }}` for CSRF protection.
- Feature list introducing the application's benefits (e.g., no original storage).
- Call-to-action buttons:
  - `Register Your Artwork`: → `public.register`
  - `Login`: → `public.login`
  - `Sign Up`: → `public.signup`

**Template Blocks Used**:
- `title`: Sets browser tab title to "Home - ImageProof".
- `csrf_token`: Overridable block for session protection.
- `content`: Contains full homepage layout.
- `scripts`: Placeholder for JS scripts, currently empty.

**Dependencies**:
- `base.html` template.
- Flask CSRF handling (`{{ csrf_field() }}` assumed to be injected by macro or global context).
- Routes from `public` blueprint:
  - `lookup`
  - `register`
  - `login`
  - `signup`

**Security Notes**:
- Includes CSRF token in the image/hash form.
- Form accepts both direct SHA-256 input and file upload.
- File input is restricted to `image/*` MIME types.

**Accessibility Notes**:
- Uses `aria-describedby` and `aria-live` for better screen reader support.
- Clean semantic structure for assistive technologies.

### `/templates/install.html`

**Purpose**:  
Acts as the **initial setup interface** for first-time configuration of the ImageProof application. This page is displayed when the app is launched in an uninitialized state and collects database credentials and admin user details to bootstrap the environment.

**Agent Role**:  
`install.html` is the **entry point for configuration**, capturing user-provided database connection parameters and creating the first administrator account. It works in tandem with backend logic to validate and persist the provided values.

**Key Features**:
- Gathers essential database configuration fields:
  - Host, Port, Name, User, Password.
- Collects initial administrator credentials:
  - Email and Password.
- Provides user-friendly placeholders to guide input.
- Submits form via POST to `install.install_index`.

**Template Blocks Used**:
- `title`: Sets the browser title to "Install - ImageProof".
- `csrf_token`: Present but overridden (handled explicitly via `{{ csrf_field() }}`).
- `content`: Renders the full setup form UI.

**Dependencies**:
- `base.html`
- `csrf_field()` macro for CSRF protection.
- Flask route `install.install_index` from the `install` blueprint.

**Security Notes**:
- CSRF token included to protect the form.
- Sensitive fields like `db_password` and `admin_password` are type="password".
- The setup form is intended to be shown **only once**, typically gated by application state.

**Post-Install Expectations**:
- Values submitted are saved to a configuration file or environment variable store.
- Admin user is created in the database.
- This page becomes **inaccessible** once setup completes to prevent reconfiguration.

**Notes for DevOps**:
- Useful for containerized or on-prem deployments requiring a UI-driven install step.
- Can be disabled or replaced in production if configuration is handled via `env` or automated scripts.

### `/templates/login.html`

**Purpose**:  
Renders the login page for the ImageProof application. This template allows users to enter their credentials and (optionally) an MFA code to authenticate and gain access to their dashboard.

**Agent Role**:  
Serves as the **authentication interface** for users attempting to log into ImageProof. It provides form inputs, displays validation errors and flash messages, and supports MFA interaction.

**Key Features**:
- Email and password input fields with validation error handling.
- Optional MFA code field (`mfa_code`) that can be dynamically shown by JavaScript or backend logic.
- Flash messages display authentication errors or system messages.
- Links to password recovery (placeholder) and account registration.

**Template Blocks Used**:
- `title`: Sets the browser tab title.
- `csrf_token`: Block placeholder (actual CSRF token injected via `{{ csrf_field() }}`).
- `content`: Main login form and supporting links.

**Dependencies**:
- `base.html`
- CSRF protection helper (`{{ csrf_field() }}`)
- Flask `url_for` resolution for:
  - `public.login`
  - `public.signup`
- `form` object (likely from Flask-WTF or manual instantiation) providing:
  - `form.email.errors`
  - `form.password.errors`
  - `form.mfa_code.errors`

**Security Notes**:
- CSRF token included in the POST form submission.
- Error messages displayed in context without echoing raw input, mitigating injection risks.
- MFA code input hidden by default but structured for secure, one-time-code entry.

### `/templates/logs.html`

**Purpose**:  
Provides a secured administrative interface for viewing and downloading system log files. It also allows real-time adjustment of the logging level for the ImageProof application.

**Agent Role**:  
`logs.html` serves as the **log monitoring and control dashboard** for admin users. It pulls a list of available log files and current logging settings from the backend, and lets administrators update log verbosity or download specific log files directly from the UI.

**Key Features**:
- Visible only to authenticated users with the `Admin` role.
- Form to change the current logging level (via POST).
- Dynamic dropdown for available logging levels (`choices`).
- Table listing:
  - Log file name
  - File size (in MB)
  - Last modified timestamp
  - Download action

**Template Blocks Used**:
- `title`: Sets browser tab title.
- `csrf_token`: Empty but defined for layout consistency.
- `content`: Full admin-only section for log visibility and controls.
- `scripts`: Inherits from `base.html`.

**Dependencies**:
- `base.html`
- Flask `current_user` context from Flask-Login.
- CSRF protection (`{{ csrf_field() }}`).
- Admin routes from the `admin` blueprint:
  - `admin.download_log`
- Backend must supply:
  - `files`: List of log file objects (with `name`, `size_mb`, `modified`)
  - `choices`: List of selectable logging levels (e.g., DEBUG, INFO)
  - `current_level`: Currently active log level (for selected option)

**Security Notes**:
- Entire page is gated by an inline check: `current_user.is_authenticated and current_user.role == 'Admin'`
- All forms are CSRF-protected.
- File downloads are routed through Flask to avoid direct path traversal vulnerabilities.

### `/templates/lookup_results.html`

**Purpose**:  
Displays the results of a reverse image lookup query. This page provides a user-facing view of all matched or similar images found in the ImageProof database, along with associated metadata and access control logic.

**Agent Role**:  
Acts as a **read-only results renderer** that serves dynamic image metadata and visibility rules based on user authentication and record privacy settings.

**Key Features**:
- Conditionally displays matching images or a fallback message when no matches are found.
- Shows image preview thumbnails (or placeholder when absent).
- Provides metadata per image:
  - Title
  - Artist name (if available)
  - Status (styled with class matching lowercase status value)
  - Registration date (formatted as `YYYY-MM-DD`)
- Restricts full view of private records to authenticated users.
- Includes action button linking to public image view route.
- Prompts unregistered users to create an account or register an image.

**Template Blocks Used**:
- `title`: Sets the page title.
- `csrf_token`: Stubbed but not used (non-modifying view).
- `content`: Main lookup results section.
- `scripts`: Inherits from `base.html`.

**Dependencies**:
- `base.html`
- Context variables:
  - `results`: List of result objects with properties:
    - `is_public`
    - `can_view`
    - `thumbnail_url`
    - `title`, `artist`, `status`, `registered_at`, `hash_id`
  - `login_url`: URL to login route.
  - `register_url`: URL to register a new image.
- `public.view_image` route for details link.

**Security Notes**:
- Visibility checks ensure only public or permissioned images are fully displayed.
- Private images are obfuscated unless the viewer is authorized.

### `/templates/manage_overlays.html`

**Purpose**:  
Enables authenticated users to manage custom image overlays—specifically signature and watermark layers—within the ImageProof system. This includes uploading new overlay images and deleting previously uploaded ones.

**Agent Role**:  
`manage_overlays.html` acts as a **user-facing interface for overlay asset management**. It integrates with the backend via the `member` blueprint, handles form submissions for uploads and deletions, and presents a dynamic overlay gallery.

**Key Features**:
- File upload form with validation for overlay images (PNG/JPG).
- Dropdown selector to classify the overlay as a `signature` or `watermark`.
- Dynamic gallery display of existing user-uploaded overlays.
- Each overlay includes:
  - Preview image
  - Type label
  - Upload date
  - Delete button (with JavaScript confirmation)

**Template Blocks Used**:
- `title`: Sets page title to "Manage Overlays".
- `csrf_token`: Empty by default; CSRF token is manually rendered inside forms.
- `content`: Renders upload form and existing overlay gallery.
- `scripts`: Inherits from `base.html`.

**Dependencies**:
- `base.html`
- `form` object (optional, from WTForms or Flask-WTF) for error rendering.
- `overlays` list (likely passed from the backend) containing:
  - `overlay.filename` (string)
  - `overlay.type` (string: `'signature'` or `'watermark'`)
  - `overlay.uploaded_at` (datetime)
  - `overlay.id` (int)
- Flask route handlers under the `member` blueprint:
  - `upload_overlay`
  - `delete_overlay`

**Security Notes**:
- CSRF protection is applied via `{{ csrf_field() }}` in all forms.
- File input accepts only specific MIME types/extensions.
- Deletion form includes a `confirm()` prompt for user verification.

**UX Enhancements**:
- Error feedback for invalid uploads is conditionally shown next to form fields.
- Responsive gallery layout (`image-grid`) for clean visual presentation of overlays.

### `/templates/register_guest.html`

**Purpose**:  
Provides a simplified public interface for non-authenticated users ("guests") to upload an image, enter basic metadata, and receive a verifiable proof of authorship via ImageProof's backend pipeline.

**Agent Role**:  
`register_guest.html` functions as the **guest-facing upload form**. It facilitates anonymous or semi-anonymous artwork submissions, forwarding image and metadata to backend processing routes for certificate generation and optional watermarking.

**Key Features**:
- Accepts image file uploads (`.png`, `.jpg`, `.jpeg`).
- Captures metadata fields: artwork title (required), creator name (optional).
- Allows optional watermark configuration:
  - Position (e.g. top-left, center).
  - Color via color input.
- Integrates flash message display for user feedback.
- Includes a CAPTCHA placeholder for bot mitigation (implementation assumed server-side).
- Outputs a button: “Register and Download Proof”.

**Template Blocks Used**:
- `title`: Sets page title to “Register as Guest”.
- `csrf_token`: Overridden placeholder (explicit CSRF injected with `{{ csrf_field() }}`).
- `content`: Main form and visual structure.
- `scripts`: Inherits from `base.html` for JS inclusion.

**Dependencies**:
- `base.html` for layout.
- Flash messaging (`get_flashed_messages`) for feedback.
- CSRF protection (`{{ csrf_field() }}` assumed from a helper macro or Flask-WTF).
- Guest submission handler (likely POST to `/register_guest`, or defined route in the guest blueprint).
- Backend image processing, watermarking, and certificate issuance logic.

**Security Notes**:
- CSRF protection is included via template injection.
- CAPTCHA placeholder indicates intent to prevent automated abuse.
- `multipart/form-data` is used properly for image upload handling.

**Accessibility Notes**:
- Form fields include `aria-label` attributes for screen readers.
- `aria-live="polite"` on image preview ensures dynamic content updates are announced without disruption.

**User Flow**:
1. Guest lands on the page and sees form.
2. Uploads image and fills out optional fields.
3. Configures watermark if desired.
4. Submits form → backend returns certificate or visual proof artifact.

### `/templates/register_image.html`

**Purpose**:  
Presents the form interface for authenticated users to register a new artwork image into the ImageProof system, including optional metadata, overlays, and security validation.

**Agent Role**:  
`register_image.html` functions as a **user-facing input agent** that collects image uploads, descriptive metadata, and optional overlay assets. It provides a preview capability and integrates CAPTCHA verification before submitting the data for processing and permanent storage.

**Key Features**:
- Uploads a primary artwork image (`original_image`), with file type restrictions (.png, .jpg, .jpeg).
- Accepts optional supporting assets:
  - `signature_image` (PNG only)
  - `watermark_image` (PNG only)
- Collects metadata:
  - `title` (required)
  - `description` (optional)
  - `creation_date` (optional)
- Contains a **Preview** button for overlay verification (hooked via JS).
- Incorporates CAPTCHA validation via `{{ render_captcha() }}`.
- Includes a browser confirmation warning: "You will not be able to retrieve this original image later."

**Template Blocks Used**:
- `title`: Sets browser tab title to "Register New Image".
- `csrf_token`: Stubbed (custom `csrf_field()` is used in form).
- `content`: Renders full image registration interface.
- `scripts`: Inherits parent scripts from `base.html`.

**Dependencies**:
- `base.html`
- Flask route: `register_image_member`
- Flask context: `csrf_field()`, `render_captcha()`
- Frontend JS for image preview (assumes hook to `#preview-button`)
- Form uses `multipart/form-data` for image/file inputs

**Security Notes**:
- POST submission protected by CSRF token.
- CAPTCHA included to prevent bot submissions.
- File inputs validated on client-side via MIME type, and should be revalidated server-side.

**Accessibility**:
- All form inputs include `aria-label`s for screen reader support.
- `image-preview` div uses `aria-live="polite"` for dynamic updates.

### `/templates/signup.html`

**Purpose**:  
Renders the user registration page for the ImageProof application. This form enables new users to create an account, supplying email, password, and agreement to terms. It optionally supports CAPTCHA integration and displays contextual error or success messages.

**Agent Role**:  
`signup.html` acts as the **front-end interface** for onboarding new users. It uses Jinja templating to dynamically present validation feedback and handles submission to the backend `public.signup` route for account creation.

**Key Features**:
- Email and password input fields with inline error display.
- Terms of Use and Privacy Policy agreement checkbox.
- Dynamic flash messages for feedback (e.g., success, validation failure).
- CAPTCHA widget container for bot protection (`#captcha-widget` div).
- Navigational link to login page if the user already has an account.

**Template Blocks Used**:
- `title`: Sets the browser tab title.
- `csrf_token`: Block defined but empty (assumed handled via `{{ csrf_field() }}`).
- `content`: Main page content including form.
- No custom script or styles injected (relies on base.html if needed).

**Dependencies**:
- `base.html` template
- Flask route: `public.signup`
- Flask route: `public.login`
- CSRF token helper: `{{ csrf_field() }}`
- Jinja `form` object (optional, for inline validation)
- Flash messages system (`get_flashed_messages(with_categories=True)`)

**Security Notes**:
- Includes CSRF protection field.
- CAPTCHA placeholder allows for spam/bot mitigation.
- Form uses POST and disables native validation (`novalidate`) in favor of custom messaging.

### `/tests/test_auth_pages.py`

**Purpose**:  
Unit test suite that verifies the correct loading and content of key authentication-related pages in the ImageProof application.

**Agent Role**:  
Serves as a **page availability and content validation agent** for the login, signup, and homepage routes. It ensures that essential user entry points are responsive and include expected HTML elements.

**Key Tests**:
- `test_login_page_loads`: Confirms that the `/login` route returns `HTTP 200` and contains the phrase `"Log In to ImageProof"`.
- `test_signup_page_loads`: Checks that the `/signup` route returns `HTTP 200` and includes the phrase `"Create an Account"`.
- `test_nav_links_exist`: Validates that the home page (`/`) contains links to the `/login` and `/signup` pages.

**Test Harness**:
- Uses `create_app()` to instantiate a test Flask application.
- Sends HTTP GET requests via Flask's `test_client()`.

**Dependencies**:
- `create_app()` from `app.app`
- Auth page templates and routes must be properly configured and render expected strings:
  - `/login`
  - `/signup`
  - `/`

**Test Goals**:
- Ensure critical user interface endpoints are reachable.
- Confirm semantic content matches UI/UX expectations.
- Catch template or route errors early in the deployment cycle.

**Notes**:
- These are **smoke tests**, ideal for CI pipelines to ensure core auth routes are functional.

### `/tests/test_certificate.py`

**Purpose**:  
Unit tests for the `certificate.py` module, verifying the correct functionality of QR code generation, certificate file outputs (PDF/JSON), and ZIP packaging logic for image registration.

**Agent Role**:  
This test module ensures the integrity of the certificate generation and packaging process. It validates file outputs, handles various input conditions (including edge cases), and checks the correctness of archive contents and metadata generation.

**Key Functions Tested**:

- `generate_qr_code(data)`  
  - Confirms PNG byte output for valid input.  
  - Ensures exceptions are raised for invalid or missing data.

- `generate_certificate(image_record, fmt="PDF" | "JSON")`  
  - Verifies proper file creation for PDF and JSON formats.  
  - Checks file headers (e.g., `%PDF`) and JSON schema expectations.  
  - Ensures unsupported formats raise `ValueError`.

- `create_registration_package(...)`  
  - Confirms all expected files (certificate, originals, metadata, etc.) are packaged correctly.  
  - Handles optional files (`social_image`, `signature_image`) gracefully.  
  - Validates `metadata.json` content matches fields in the `image_record`.  
  - Asserts proper ZIP file naming using SHA-256 hash.  
  - Verifies missing required files raise `FileNotFoundError`.

**Test Strategy**:
- Uses `tmp_path` fixture to isolate file operations during testing.
- Leverages `SimpleNamespace` to simulate image record objects.
- Applies strict assertions on file structure, headers, and ZIP content.
- Includes negative tests for robustness (invalid input, missing files).

**Dependencies**:
- `pytest`
- Python standard libraries: `io`, `json`, `zipfile`, `types`
- Internal module: `app.certificate`

**Coverage Summary**:
Covers all major branches of `certificate.py`, including:
- Input validation
- Format-specific certificate output
- Optional vs. required file packaging
- Metadata integrity

### `/tests/test_config.py`

**Purpose**:  
Verifies the behavior of the configuration module (`app/config.py`) by checking environment variable parsing and logging setup. Ensures consistent and predictable app configuration under test conditions.

**Agent Role**:  
`test_config.py` acts as the **validation layer** for runtime configuration logic, confirming that environment-dependent settings are read and applied as expected. It also ensures the `configure_logging()` function correctly adjusts the global logging level.

**Key Tests**:
- `test_base_config_env_reading`:  
  Validates that `BaseConfig` dynamically reads and applies environment variables including `SECRET_KEY`, `DATABASE_URI`, `DEBUG`, and `MAX_CONTENT_LENGTH`.

- `test_configure_logging`:  
  Ensures that the logging configuration:
  - Defaults to `DEBUG` level when `DEBUG=True` and `LOG_LEVEL` is not set.
  - Respects the `LOG_LEVEL` environment variable if present.

**Test Fixtures**:
- `restore_logging_and_config`:  
  Automatically applied to every test. Captures the current logging state and restores it post-test. Ensures isolated environment effects by reloading the config module after monkeypatching.

**Dependencies**:
- `pytest`
- `monkeypatch` fixture (for mocking environment variables)
- Python standard libraries: `importlib`, `os`, `logging`
- `app/config.py`

**Security/Integrity Notes**:
- Tests use `monkeypatch.setenv()` to simulate different runtime environments.
- Reloads the `config` module to enforce real-time evaluation of environment variables.

**Assurance Scope**:
- Confirms `BaseConfig` respects overrides via environment.
- Validates logging is dynamically adjustable based on `DEBUG` and `LOG_LEVEL`.

```python
# Sample config attributes tested:
BaseConfig.SECRET_KEY
BaseConfig.DATABASE_URI
BaseConfig.DEBUG
BaseConfig.MAX_CONTENT_LENGTH
```

### `/tests/test_first_run.py`

**Purpose**:  
Validates conditional exposure of the installation workflow in the ImageProof application based on presence or absence of an `.installed` sentinel file. These tests ensure the one-time installer behaves correctly on first and subsequent application runs.

**Agent Role**:  
This test suite acts as a **verification agent** for application bootstrapping logic. It confirms that the `install` blueprint is only available when the sentinel file does **not** exist, enforcing a secure and isolated setup step.

**Test Cases**:
- `test_installer_exposed_when_not_installed`:  
  - Simulates an environment where the `.installed` file is missing.  
  - Asserts that the `install` blueprint is registered on app creation.  
  - Uses `monkeypatch` to redirect `INSTALL_SENTINEL_FILE` to a temp path.

- `test_installer_skipped_when_installed`:  
  - Creates the `.installed` file in a temp directory.  
  - Asserts that the `install` blueprint is **not** registered.  
  - Again uses `monkeypatch` for isolated config manipulation.

**Dependencies**:
- `pytest`: test runner and fixture support.
- `monkeypatch`: pytest utility to temporarily modify `config.INSTALL_SENTINEL_FILE`.
- `create_app`: from `app.app`, responsible for conditional blueprint registration.
- `config.INSTALL_SENTINEL_FILE`: path to sentinel flag controlling installer visibility.

**Security/Deployment Implications**:
- Ensures the installer is only exposed during valid uninitialized states.
- Prevents reconfiguration or exposure of setup logic after installation is complete.

**Tags**: `bootstrap`, `installer`, `blueprint`, `pytest`, `config sentinel`

### `/tests/test_hash_functions.py`

**Purpose**:  
Unit tests for validating the behavior and reliability of the `compute_sha256` and `compute_perceptual_hash` functions from the `app.image_processing` module. These functions are critical for ensuring image integrity and similarity detection within the ImageProof application.

**Agent Role**:  
This test script acts as a **validator agent** for core image hashing logic. It programmatically generates in-memory images and asserts expected hash behavior (consistency and uniqueness).

**Test Cases**:

- `test_compute_sha256_consistency`  
  Ensures that the SHA256 hash function returns identical values for identical image content.

- `test_compute_sha256_uniqueness`  
  Verifies that visibly distinct images yield different SHA256 hashes.

- `test_compute_perceptual_hash_consistency`  
  Confirms that perceptual hashing is stable across identical image data.

- `test_compute_perceptual_hash_uniqueness`  
  Tests whether minor but meaningful visual changes (added shape and color) result in a different perceptual hash, indicating sensitivity to image content.

**Key Utilities**:
- `get_image_bytes(img)` – Converts a Pillow image to byte format for hashing.
- `pytest` – Used for running test cases.
- `ImageDraw` – Used to modify images slightly for uniqueness tests.

**Dependencies**:
- `PIL` (Pillow)
- `pytest`
- `app.image_processing`:
  - `compute_sha256`
  - `compute_perceptual_hash`

**Testing Strategy**:
- Operates entirely in memory—no filesystem interaction.
- Focuses on **determinism** and **collision resistance** for both hash types.
- Serves as a baseline test to catch regressions in the image hashing layer.

**File Type**:  
Automated test script — safe to run in CI environments.

### `/tests/test_image_pipeline.py`

**Purpose**:  
Unit test suite for the core image processing and watermarking logic in the ImageProof backend. Ensures correctness and integrity of image hashing, feature extraction, similarity detection, and watermark overlays. Also includes integration testing with the in-memory database and ORM models.

**Agent Role**:  
This test module acts as the **quality assurance agent** for image-related features. It validates hash consistency, perceptual similarity thresholds, watermark application correctness (text and image), overlay sequencing and limits, and the full image deduplication pipeline against a temporary database instance.

**Key Test Areas**:
- SHA-256 hashing and perceptual hashing comparison.
- ORB feature extraction with OpenCV.
- Text and image watermark functionality.
- Transparent vs opaque overlay blending validation.
- Enforcement of overlay limit logic (raises `ValueError`).
- Correct rendering order of multiple overlays.
- Integration with database-backed image similarity matching.

**Setup Details**:
- Forces SQLite in-memory database:  
  ```python
  os.environ["DATABASE_URI"] = "sqlite:///:memory:"
  ```
- Uses `create_app()` and `init_db()` from the main app to bootstrap a test context.
- Uses `models.SessionLocal()` to add and query `User` and `Image` ORM entries.

**Dependencies**:
- `pytest` for unit testing framework.
- `PIL.Image` and `PIL.ImageDraw` for in-memory test image creation.
- `numpy` for verifying descriptor formats.
- Application modules tested:
  - `app.image_processing`
  - `app.watermark`
  - `app.app`
  - `app.models`

**Edge Case Testing**:
- Overlays beyond the allowed limit (`>3`) raise a `ValueError`.
- Overlay stacking order affects final pixel values (verified by RGB tuples).
- Empty or unavailable ORB feature conditions are gracefully skipped.

**Security/Isolation Notes**:
- Does not interact with persistent data (uses `sqlite:///:memory:`).
- Tests are idempotent and cleanly isolated from production or dev environments.

### `/tests/test_smoke.py`

**Purpose**:  
A basic *smoke test* to verify the root URL (`/`) of the ImageProof application is reachable and returns HTTP 200 OK. This helps ensure that the app initializes properly and the routing is functional.

**Agent Role**:  
Acts as a **minimal verification agent** during testing or CI/CD pipelines. It confirms that the application starts without errors and the index route is active.

**Key Behaviors**:
- Imports `create_app()` from the main app factory.
- Instantiates a test client from Flask.
- Sends a GET request to `/`.
- Asserts that the HTTP response status is `200`.

**Dependencies**:
- Flask's test client mechanism.
- `create_app()` factory function from `app/app.py`.

**Test Scope**:  
- Confirms only the root path (`/`) responds.
- Does **not** test template rendering, session behavior, or auth state.

**Execution Context**:  
Typically run using `pytest` or other test runners during early-stage integration checks.

**Recommended Future Enhancements**:
- Add additional smoke tests for other core routes (`/login`, `/dashboard`, etc.).
- Include output validation (e.g. check HTML content) for more meaningful results.

### `/tests/test_watermark.py`

**Purpose**:  
Unit test module for verifying correct behavior of the watermarking functionality provided by `app.watermark`, specifically the positioning and rendering of text-based watermarks on images.

**Agent Role**:  
This agent ensures **visual integrity and spatial accuracy** of watermark placement logic across multiple predefined anchor points (e.g., corners and center). It validates that the watermark visibly alters pixels and appears in approximately the expected position.

**Tested Functions**:
- `apply_text_watermark(base_image, text, position)`  
- `_calculate_position(image_size, text_size, position)` (internal)

**Test Strategy**:
- Creates a 32x32 red RGBA base image.
- Uses default font to determine watermark text size.
- Applies watermark text `"Hi"` at each supported position:
  - `"top-left"`
  - `"top-right"`
  - `"bottom-left"`
  - `"bottom-right"`
  - `"center"`
- Converts images to NumPy arrays to detect changed pixels.
- Compares the actual watermark bounding box to expected coordinates with a ±2 pixel tolerance.
- Confirms that at least one pixel is modified and that the pixel at the expected start point has changed from the original base color.

**Dependencies**:
- `numpy`
- `Pillow (PIL)`
- Internal `app.watermark` module

**Coverage Importance**:
- Ensures that watermark positioning logic is **mathematically accurate**.
- Detects regressions where watermarks are misaligned or fail to render.
- Supports future enhancements or alternate font scaling by defining pixel bounds.

**Notes**:
- This test implicitly verifies rendering logic without relying on visual inspection.
- Designed for **headless and automated test environments**.

### `/.env`

**Purpose**:  
Holds environment-specific configuration variables for the ImageProof Flask application. These values are loaded at runtime (typically via `python-dotenv`) to separate sensitive or machine-specific settings from the codebase.

**Agent Role**:  
Acts as a **configuration provider** and security layer, ensuring credentials and development parameters are externalized from the main application logic.

**Key Variables**:

- `SECRET_KEY`:  
  Used by Flask to sign session cookies and protect against CSRF attacks. This value should remain secret and unique in production environments.

- `DATABASE_URI`:  
  SQLAlchemy-style connection URI for a local MariaDB instance.  
  Format: `mysql+mysqlconnector://<user>:<password>@<host>/<database>`

- `DEBUG`:  
  Enables Flask debug mode. Should be `False` in production to avoid revealing sensitive stack traces.

- `MAX_CONTENT_LENGTH`:  
  Caps the file upload size to 5 MB. Prevents denial-of-service via large payloads.

- `LOG_LEVEL`:  
  Controls the verbosity of log output. Common values: `DEBUG`, `INFO`, `WARNING`, `ERROR`, `CRITICAL`.

**Security Notes**:
- `.env` must be included in `.gitignore` to prevent leaking secrets to version control.
- The `SECRET_KEY` and database credentials should be rotated periodically.
- Always validate that `DEBUG=False` before deployment to staging or production.

**Dependencies**:
- Flask config loader (`app.config.from_envvar()` or `load_dotenv()` from `dotenv`).
- SQLAlchemy (`DATABASE_URI` consumed during `engine` initialization).

### `/.gitignore`

**Purpose**:  
Defines untracked files and directories that Git should ignore in the ImageProof project. This prevents sensitive data, environment-specific files, and transient build artifacts from being committed to version control.

**Agent Role**:  
Acts as a **repository hygiene gatekeeper**, ensuring that temporary files, system-specific settings, and local development artifacts do not pollute the Git history or get pushed to shared repositories.

**Ignored Content Categories**:

- **Python Build Artifacts**:
  - `__pycache__/`, `*.pyc`, `*.egg-info/`, `*.egg`
- **Sensitive Files**:
  - `.env`, `*.sqlite`, `*.db`, `*.log`, `*.zip`
- **IDE/Editor Configs**:
  - `.idea/` (PyCharm), `.vscode/`, `.DS_Store` (macOS)
- **Framework/Tool Artifacts**:
  - `instance/`, `coverage/`, `.cache/`

**Security Notes**:
- `.env` exclusion protects environment variables (API keys, DB passwords, etc.).
- Database and log file exclusions reduce risk of exposing user or internal data.

**Best Practices**:
- Keep this file updated as new tooling or frameworks are introduced.
- Use `.gitignore` at both project and global (user) levels to avoid accidental commits of local config.

### `/.pre-commit-config.yaml`

**Purpose**:  
Defines automated code quality checks and security linters to be executed via the [pre-commit](https://pre-commit.com/) framework before any commit is made to the repository. Ensures consistency, cleanliness, and adherence to best practices across the codebase.

**Agent Role**:  
Acts as the **code hygiene enforcer** in the development lifecycle. It integrates various formatting, linting, type-checking, and security tools into the version control workflow. This file ensures that no commit passes without being validated for style, type safety, import ordering, and security vulnerabilities.

**Configured Hooks**:

| Tool     | Description                                                                 |
|----------|-----------------------------------------------------------------------------|
| `ruff`   | Fast Python linter with autofix capabilities. Enforces style and correctness. |
| `black`  | Opinionated code formatter for consistent Python style.                    |
| `isort`  | Automatically sorts and groups imports.                                    |
| `mypy`   | Static type checker; strict mode enabled for maximum type safety.          |
| `bandit` | Security analyzer for Python code; runs in high verbosity (`-ll`).         |
| `end-of-file-fixer` | Ensures every file ends with a newline.                          |
| `trailing-whitespace` | Removes trailing whitespace from all lines.                   |

**Usage**:
1. Ensure you have `pre-commit` installed:  
   ```bash
   pip install pre-commit
   ```

2. Install hooks locally:  
   ```bash
   pre-commit install
   ```

3. Run hooks manually on all files (optional):  
   ```bash
   pre-commit run --all-files
   ```

**Security Notes**:
- `bandit` integration helps detect known insecure patterns during development.
- Combined with `mypy --strict`, this setup enforces both security and code correctness early in the pipeline.

**Integration Status**:  
Essential for all contributors. Should be installed and active in any local development environment.

### `/pyproject.toml`

**Purpose**:  
Defines the build configuration, project metadata, dependencies, and development tool settings for the ImageProof application using [PEP 621](https://peps.python.org/pep-0621/) standards.

**Agent Role**:  
`pyproject.toml` acts as the **project orchestration agent**, coordinating build tooling (`hatchling`), runtime dependencies, and development utilities. It ensures reproducibility, consistency, and enforceable standards across development and deployment environments.

**Key Functions**:
- Declares the project name, version, and author metadata.
- Specifies core Python runtime (`>=3.12`) and dependencies needed for:
  - Web framework (`flask`)
  - Image analysis (`opencv-python-headless`, `imagehash`, `pillow`)
  - File integrity and metadata (`python-magic`, `qrcode`, `reportlab`, `python-barcode`)
  - Database access (`mysql-connector-python`, `sqlalchemy`)
  - Environment management (`python-dotenv`)
- Declares optional `dev` extras with linting, type checking, testing, and formatting tools.
- Configures tools:
  - **Black**: Code formatter
  - **Isort**: Import sorting aligned with Black
  - **Ruff**: Fast linter
  - **Mypy**: Static type checker with strict mode
  - **Bandit**: Security linter, skipping specific tests (`B101`)
  - **Pytest**: Test runner configuration (`--strict-markers`)

**Build System**:
- Uses `hatchling` as the build backend.
- Builds wheels that include only the `app` package.

**Dependencies Summary**:
- **Runtime**: 11 core packages for image handling, security, watermarking, database, and environment configuration.
- **Development**: 11 tools to maintain quality, security, and consistency in the codebase.

**Security & Quality**:
- Enforces strict typing with Mypy.
- Lints for insecure code patterns with Bandit.
- Skips `assert_used` check (Bandit B101) due to controlled assertion usage in tests or debug flows.

**Compliance Targets**:
- Python 3.12+
- Consistent style, type, and test enforcement across contributors and CI/CD.

### `/schema.sql`

**Purpose**:  
Defines the core relational database schema for ImageProof. This schema lays the foundation for user accounts, uploaded image data, and user-driven action logging.

**Agent Role**:  
`schema.sql` serves as the **Phase 1 data layer initializer**, to be executed once during database setup. It establishes three key entities—`users`, `images`, and `action_log`—with the appropriate constraints, indexes, and foreign key relationships to support secure, query-efficient application behavior.

**Tables Defined**:

- **`users`**
  - `id`: Auto-incrementing primary key.
  - `email`: Unique identifier for login/authentication.
  - `hashed_password`: Securely stored password hash.
  - Constraint: Unique index on `email`.

- **`images`**
  - `id`: Auto-incrementing primary key.
  - `user_id`: Foreign key to `users.id`.
  - `sha256`: Unique SHA-256 hash of uploaded image.
  - `phash`: Perceptual hash for similarity detection.
  - Indexes: On `sha256` and `phash` for efficient lookups.
  - Constraint: Foreign key on `user_id`.

- **`action_log`**
  - `id`: Auto-incrementing primary key.
  - `user_id`: Optional foreign key to `users.id`.
  - `image_id`: Optional foreign key to `images.id`.
  - `action`: Type of action performed (e.g., upload, delete).
  - `timestamp`: Defaults to current server time.
  - `details`: Arbitrary metadata (JSON/text).
  - Constraints: Foreign keys on `user_id` and `image_id`.

**Engine and Encoding**:
- All tables use `InnoDB` for transactional integrity and foreign key support.
- `utf8mb4` character set ensures proper Unicode compatibility.

**Dependencies**:
- None at runtime, but intended to be executed by an ORM initializer or via `mysql < schema.sql`.

**Security Notes**:
- User passwords are never stored in plaintext.
- Action logs can track user behaviors and image modifications for auditing or rollback.

### `/seed_data.sql`

**Purpose**:  
Provides a minimal set of deterministic seed data for initializing the ImageProof database during development or testing. This data is designed to validate schema integrity, especially foreign key relationships between users and images.

**Agent Role**:  
`seed_data.sql` acts as the **initial data provisioner** for testing environments in Phase 1. It allows quick population of the database with known values to verify core logic, UI rendering, and API responses.

**Key Features**:
- Inserts one test user:
  - `email`: `testuser@example.com`
  - `hashed_password`: bcrypt hash for a known password (e.g., `"password123"`).
- Inserts one image tied to the test user:
  - `user_id` references `users.id = 1`
  - `sha256` and `phash` values are hardcoded to test image fingerprinting and lookup.

**Dependencies**:
- Assumes schema defined in `schema.sql` with:
  - `users` table: columns `id`, `email`, `hashed_password`
  - `images` table: columns `id`, `user_id`, `sha256`, `phash`
- Requires foreign key enforcement to be enabled in the SQL engine (e.g., MariaDB or MySQL).

**Use Case**:
- Run during development setup or test suite initialization to verify system behavior with a valid user-image relationship.
- Helps detect issues in early-stage queries, joins, or relationship mappings in the ORM.

**Security Notes**:
- Contains a test account only; never intended for production use.
- The password is pre-hashed and does not expose plaintext credentials.
