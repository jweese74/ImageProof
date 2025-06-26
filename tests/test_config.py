import importlib
from pathlib import Path

from app import config

def test_load_env_file(monkeypatch):
    env_path = config.BASE_DIR / ".env"
    original = env_path.read_text() if env_path.exists() else None
    env_path.write_text(
        "\n".join(
            [
                "SECRET_KEY=env-secret",
                "DATABASE_URI=sqlite:///env.db",
                "DEBUG=True",
                "MAX_CONTENT_LENGTH=123",
            ]
        )
    )

    monkeypatch.delenv("SECRET_KEY", raising=False)
    monkeypatch.delenv("DATABASE_URI", raising=False)
    monkeypatch.delenv("DEBUG", raising=False)
    monkeypatch.delenv("MAX_CONTENT_LENGTH", raising=False)

    importlib.reload(config)
    try:
        assert config.BaseConfig.SECRET_KEY == "env-secret"
        assert config.BaseConfig.DATABASE_URI == "sqlite:///env.db"
        assert config.BaseConfig.DEBUG is True
        assert config.BaseConfig.MAX_CONTENT_LENGTH == 123
    finally:
        if original is None:
            env_path.unlink()
        else:
            env_path.write_text(original)
        importlib.reload(config)