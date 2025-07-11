# 📓 CHANGELOG

All notable changes to **PixlKey** will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/)  
and follows a simplified [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format.

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