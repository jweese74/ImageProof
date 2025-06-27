from pathlib import Path

import pytest

from app.app import create_app
from app import config


def test_installer_exposed_when_not_installed(tmp_path, monkeypatch):
    sentinel = tmp_path / ".installed"
    monkeypatch.setattr(config, "INSTALL_SENTINEL_FILE", sentinel)
    app = create_app()
    assert "install" in app.blueprints


def test_installer_skipped_when_installed(tmp_path, monkeypatch):
    sentinel = tmp_path / ".installed"
    sentinel.touch()
    monkeypatch.setattr(config, "INSTALL_SENTINEL_FILE", sentinel)
    app = create_app()
    assert "install" not in app.blueprints