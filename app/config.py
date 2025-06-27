# app/config.py
import os
import logging
import logging.config
import secrets
from pathlib import Path
from typing import Any, Dict

# Base directory of the project (the parent directory of the app package)
BASE_DIR: Path = Path(__file__).resolve().parent.parent

# Load environment variables from a .env file if present. The file is expected
# at the repository root (BASE_DIR/.env). If it doesn't exist, ``load_dotenv``
# falls back to the default behaviour of searching the current working
# directory. This occurs at import time so that configuration values are set
# before other modules access them.
try:
    from dotenv import load_dotenv

    env_path = BASE_DIR / ".env"
    if env_path.exists():
        load_dotenv(env_path)
    else:
        load_dotenv()
except ImportError:  # pragma: no cover - optional dependency
    pass
# Directories for static files and templates
STATIC_DIR: Path = BASE_DIR / "static"
TEMPLATES_DIR: Path = BASE_DIR / "templates"
# Directory for uploaded files
UPLOAD_DIR: Path = BASE_DIR / "uploads"

# Logging configuration
LOG_DIR: Path = BASE_DIR / "logs"
LOG_FILE: Path = LOG_DIR / "imageproof.log"
LOG_LEVEL_CHOICES: list[str] = ["INFO", "WARNING", "ERROR"]
LOG_LEVEL_DEFAULT: str = "INFO"
LOG_FILE_MAX_BYTES: int = 5 * 1024 * 1024  # 5 MB
LOG_BACKUP_COUNT: int = 5  # number of rollovers to keep
LOG_RETENTION_DAYS: int = 7  # prune older files
# Sentinel file to determine if the app has completed initial installation
INSTALL_SENTINEL_FILE: Path = BASE_DIR / ".installed"


class BaseConfig:
    """Base configuration with default settings for the ImageProof application.

    This class defines the default configuration variables and security settings
    used by the application. It reads from environment variables where applicable
    to allow secure configuration of secrets and connection strings. Attributes
    like `SECRET_KEY` and `DATABASE_URI` are critical for security and are loaded
    from environment or generated securely if not provided.

    Attributes:
        SECRET_KEY: Secret key for session management and CSRF protection. If not
            found in environment, a random secure key is generated.
        DATABASE_URI: Database connection URI for the MySQL database. Should be
            provided via environment for production; defaults to empty string if not set.
        DEBUG: Flag indicating if debug mode is enabled. Loaded from the DEBUG
            environment variable (True/False), default is False for production.
        MAX_CONTENT_LENGTH: Maximum allowed size (in bytes) for uploaded files.
            Exceeding this will result in request rejection. Default is 5 MB.
        ALLOWED_EXTENSIONS: Allowed file extensions for image uploads.
        ALLOWED_MIME_TYPES: Allowed MIME types for image uploads.
    """
    SECRET_KEY: str = os.environ.get("SECRET_KEY", secrets.token_hex(16))
    DATABASE_URI: str = os.environ.get("DATABASE_URI", "")
    DEBUG: bool = os.environ.get("DEBUG", "False").lower() in ("true", "1", "yes")
    MAX_CONTENT_LENGTH: int = int(os.environ.get("MAX_CONTENT_LENGTH", "5242880"))
    ALLOWED_EXTENSIONS: tuple[str, ...] = (".png", ".jpg", ".jpeg", ".gif", ".bmp", ".tiff")
    ALLOWED_MIME_TYPES: tuple[str, ...] = (
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/bmp",
        "image/tiff",
    )
    UPLOAD_FOLDER: str = str(UPLOAD_DIR)

    # Session and CSRF security settings
    SESSION_COOKIE_HTTPONLY: bool = True
    SESSION_COOKIE_SAMESITE: str = "Lax"
    SESSION_COOKIE_SECURE: bool = os.environ.get("SESSION_COOKIE_SECURE", "False").lower() in ("true", "1", "yes")
    PERMANENT_SESSION_LIFETIME: int = int(os.environ.get("SESSION_LIFETIME", "3600"))

    CSRF_FIELD_NAME: str = "csrf_token"


class DevelopmentConfig(BaseConfig):
    """Configuration for development environment.

    Inherits all settings from BaseConfig and overrides certain values for development.
    Debug mode is enabled to provide verbose output and auto-reload. This config
    should not be used in production.
    """
    DEBUG: bool = True  # Ensure debug mode is on for development


class ProductionConfig(BaseConfig):
    """Configuration for production environment.

    Inherits all settings from BaseConfig and overrides values for a secure production setup.
    Debug mode is forced off to avoid verbose output and potential security issues. Use this
    configuration for deployed instances.
    """
    DEBUG: bool = False  # Ensure debug is always off in production


def configure_logging() -> None:
    """Configure structured logging for the application.

    Sets up the root logger with a console handler and a standard format.
    The log level is determined by the environment: if `LOG_LEVEL` is set, it will use that;
    otherwise, it defaults to 'DEBUG' if DEBUG mode is enabled, or 'INFO' in normal mode.

    Returns:
        None: Logging is configured in place.
    """
    log_level_env = os.environ.get("LOG_LEVEL")
    if log_level_env:
        level = log_level_env.upper()
    else:
        level = "DEBUG" if os.environ.get("DEBUG", "False").lower() in ("true", "1", "yes") else "INFO"
    logging_config: Dict[str, Any] = {
        "version": 1,
        "disable_existing_loggers": False,
        "formatters": {
            "default": {
                "format": "[%(asctime)s] %(levelname)s in %(module)s: %(message)s"
            }
        },
        "handlers": {
            "console": {
                "class": "logging.StreamHandler",
                "formatter": "default",
                "level": level
            }
        },
        "root": {
            "level": level,
            "handlers": ["console"]
        }
    }
    logging.config.dictConfig(logging_config)
