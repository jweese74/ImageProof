# README.md

# ImageProof

ImageProof is a web application for registering and verifying the authenticity of digital images. It allows users to upload an image with descriptive metadata and optional overlays (such as a personal signature or watermark). The system then generates a secure proof-of-authenticity certificate and cryptographic hashes for the image, enabling anyone to later confirm that an image is original and untampered. By combining image fingerprinting, metadata, and user verification, ImageProof provides a trustworthy way to authenticate digital artwork and photography.

The platform emphasizes security and integrity. Uploaded images are scanned and constrained to allowed formats and sizes, and sensitive operations (like registrations and verifications) are logged. Configurable settings (in `app/config.py`) allow deployment in both development and production environments with appropriate security measures (debug mode, content size limits, etc.). This repository is limited to 20 core files to maintain simplicity and clarity.

## File Count Ledger

| File                      | Description                                       |
| ------------------------- | ------------------------------------------------- |
| `pyproject.toml`          | Project metadata, dependencies, and development tool configuration. |
| `app/config.py`           | Application configuration classes (Base, Development, Production) and logging setup. |
| `.pre-commit-config.yaml` | Pre-commit hooks for code formatting (Black, isort), linting (Ruff, Mypy), and security (Bandit). |
| `README.md`               | Project overview and phase-wise file count ledger. |
| **Total (Phase 0)**       | **4/20 files used**                               |
| **Total (Phase 1)**       | **8/20 files used**                               |
| **Total (Phase 3)**       | **11/20 files used**                              |
| **Total (Phase 4)**       | **13/20 files used**                              |

# Project Structure
ImageProof/
├── app/
│   ├── app.py
│   ├── config.py
│   ├── image_processing.py
│   ├── watermark.py
│   ├── models.py
│   └── certificate.py
├── tests/
│   ├── test_image_pipeline.py
│   └── test_certificate.py
├── templates/
├── static/
├── schema.sql
├── seed_data.sql
├── pyproject.toml
├── .pre-commit-config.yaml
└── README.md
