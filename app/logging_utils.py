import logging
from logging.handlers import RotatingFileHandler
from datetime import datetime, timedelta

from app import config


def init_logging() -> None:
    """Initialize root logger with a rotating file handler."""
    config.LOG_DIR.mkdir(parents=True, exist_ok=True)
    handler = RotatingFileHandler(
        config.LOG_FILE,
        maxBytes=config.LOG_FILE_MAX_BYTES,
        backupCount=config.LOG_BACKUP_COUNT,
    )
    formatter = logging.Formatter("%(asctime)s [%(levelname)s] %(name)s: %(message)s")
    handler.setFormatter(formatter)
    root = logging.getLogger()
    root.setLevel(getattr(logging, config.LOG_LEVEL_DEFAULT))
    root.addHandler(handler)


def set_log_level(level: str) -> None:
    """Set the logging level of the root logger."""
    level = level.upper()
    if level not in config.LOG_LEVEL_CHOICES:
        raise ValueError(f"Invalid log level: {level}")
    logging.getLogger().setLevel(getattr(logging, level))


def get_log_level() -> str:
    """Return the current root logger level name."""
    return logging.getLevelName(logging.getLogger().level)


def prune_old_logs() -> None:
    """Delete log files older than the configured retention period."""
    cutoff = datetime.now() - timedelta(days=config.LOG_RETENTION_DAYS)
    for file in config.LOG_DIR.glob(f"{config.LOG_FILE.name}*"):
        if file.is_file() and datetime.fromtimestamp(file.stat().st_mtime) < cutoff:
            file.unlink(missing_ok=True)