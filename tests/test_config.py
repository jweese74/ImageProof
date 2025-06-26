import importlib
import logging
import os
import pytest

# Auto-used fixture to reset logging and reload config after each test
@pytest.fixture(autouse=True)
def restore_logging_and_config(monkeypatch):
    root = logging.getLogger()
    original_level = root.level
    original_handlers = list(root.handlers)
    yield
    # Restore logging state
    root.handlers[:] = original_handlers
    root.setLevel(original_level)
    # Reload config with environment restored by monkeypatch
    from app import config
    importlib.reload(config)


def test_base_config_env_reading(monkeypatch):
    monkeypatch.setenv("SECRET_KEY", "test-secret")
    monkeypatch.setenv("DATABASE_URI", "sqlite:///test.db")
    monkeypatch.setenv("DEBUG", "True")
    monkeypatch.setenv("MAX_CONTENT_LENGTH", "10")

    from app import config
    importlib.reload(config)

    assert config.BaseConfig.SECRET_KEY == "test-secret"
    assert config.BaseConfig.DATABASE_URI == "sqlite:///test.db"
    assert config.BaseConfig.DEBUG is True
    assert config.BaseConfig.MAX_CONTENT_LENGTH == 10


def test_configure_logging(monkeypatch):
    from app import config

    # When DEBUG is true and no LOG_LEVEL, level should be DEBUG
    monkeypatch.delenv("LOG_LEVEL", raising=False)
    monkeypatch.setenv("DEBUG", "True")
    importlib.reload(config)
    config.configure_logging()
    root = logging.getLogger()
    assert root.level == logging.DEBUG

    # When LOG_LEVEL is set, it takes precedence
    monkeypatch.setenv("LOG_LEVEL", "WARNING")
    importlib.reload(config)
    config.configure_logging()
    assert root.level == logging.WARNING