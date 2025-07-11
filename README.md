# 🖼️ PixlKey

_PixlKey_ is a secure PHP-based platform for digital artists and content creators to upload, watermark, license, and register their digital artwork. The system ensures verifiable ownership, metadata preservation, and flexible licensing—tying these not just to the artwork file, but to its unique cryptographic fingerprint (SHA-256 hash).

## 🎯 Project Goals

The core goal of PixlKey is to create a **searchable, decentralized registry of digital images, ownership rights, and licensing**, anchored by each image's **cryptographic fingerprint** rather than just file content or metadata. This enables:

- Verifiable proof of authorship and modification history.
- Immutable links between artwork, metadata, and license.
- A decentralized-friendly, off-chain registry system for provenance.
- Rights management that is resilient across formats, platforms, and duplicates.

## What It Does

- Upload high-resolution artwork (PNG, JPG, WebP up to 200MB).
- Apply dynamic or default watermarking via user controls.
- Embed metadata and author statements using ExifTool.
- Generate markdown certificates of authenticity.
- Package processed image, thumbnail, metadata, and cert into a ZIP.
- Persist image fingerprint, metadata, license ID, and ownership to a relational database.
- Allow users to manage watermark and license templates.
- Extract and publish signed metadata reports from processed files.

## Roadmap (Top Priorities)

### Security & Session Hardening
1. **Regenerate session ID on login** to mitigate fixation attacks. *(Implemented in `auth.php` and `logout.php`)*
2. **Strict `runId` sanitization and ownership checks** in download & store logic.
3. **Rate limiting and brute-force protection** on login/registration endpoints.
4. **CSRF failure, login, and download event logging** for audit and security.

### Configuration & Validation
5. **Validate required environment variables** (`DB_PASS`, `DB_NAME`, etc.) at runtime.
6. **MIME-type validation** for uploads (`mime_content_type`) in addition to file extension checks.
7. **Restrict watermark and upload directories** to correct permissions (`0700`, `0750`).
8. **Centralize config values** like `$allowedExtensions`, watermark paths, and max file size into `.env` or `config.php`.

### Maintainability & Modularity
9. **Refactor monolithic `store_data.php`** into modular handlers per table (e.g., `Artworks`, `Images`, `Submissions`).
10. **Replace silent errors (`@unlink`, `die()`)** with structured logging and exception handling.

---

## Tech Stack

- **Backend**: PHP 8+, ImageMagick, ExifTool, MySQL/MariaDB
- **Frontend**: Vanilla JS, dynamic HTML form generation, live processing feedback
- **Security**: Session hardening, CSRF protection, XSS filtering, SQL injection prevention
- **Persistence**: UUID-based relational schema with SHA-256 image fingerprinting
- **Optional**: `.env` configuration via `php-dotenv`

## 🔐 Security Enhancements

- Session ID regeneration on login and logout to prevent fixation.
- Secure cookie flags: `HttpOnly`, `Secure`, `SameSite=Strict`.
- CSRF token protection on all forms.
- Passwords hashed with `password_hash()` and verified with `password_verify()`.

## Status

- Core upload, watermark, and metadata functions complete.
- Testing phase: security, concurrency, and error handling enhancements in progress.
- Stable builds pending rollout after roadmap completion.

---

## Key Directories

| Path                | Description                                      |
|---------------------|--------------------------------------------------|
| `/app/auth.php`         | Session, login, and CSRF helpers                |
| `/app/jobs/store_data.php`   | Database ingestion from processed image package |
| `/app/tools/metadata_extractor.php` | CLI markdown metadata export               |
| `/app/functions.php`    | Watermarking, cleanup, and UI helpers           |
| `/public/my_licenses.php`  | License management interface                    |
| `/public/my_watermarks.php`| Watermark upload and default selection          |
| `/public/process.php`      | Core upload → watermark → ZIP pipeline          |

---

## License

MIT License — see `LICENSE.md` for details.

---

## Contact

For issues, contributions, or inquiries, contact [jweese74@gmail.com](https://infinitemusearts.com/toolkit) or open an issue in this repository.

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
