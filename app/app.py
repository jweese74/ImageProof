"""Flask application setup and database initialization for ImageProof."""

import logging
import time
from pathlib import Path
from threading import Thread
from types import SimpleNamespace

from flask import Flask
from flask_login import LoginManager
from markupsafe import Markup

from app import config, logging_utils
from app.models import SessionLocal, User
from app.routes_admin import admin_bp
from app.routes_files import files_bp
from app.routes_install import install_bp
from app.routes_member import member_bp
from app.routes_public import public_bp
from app.routes_stub import stub_bp
from app.security import generate_csrf_token, validate_csrf_token

logger: logging.Logger = logging.getLogger(__name__)

_prune_thread_started = False


def _start_log_prune_thread() -> None:
    """Start a background thread to periodically prune old logs."""

    def _prune_periodically() -> None:
        while True:
            logging_utils.prune_old_logs()
            time.sleep(60 * 60 * 24)

    thread = Thread(target=_prune_periodically, daemon=True)
    thread.start()


def create_app(
    config_object: type[config.BaseConfig] = config.DevelopmentConfig,
) -> Flask:
    """Create and configure the Flask application."""
    BASE_DIR = Path(__file__).resolve().parent.parent  # /opt/imageproof
    app = Flask(
        __name__,
        template_folder=str(BASE_DIR / "templates"),
        static_folder=str(BASE_DIR / "static"),
        static_url_path="/static",
    )
    app.config.from_object(config_object)

    # Ensure log folder exists before configuring logging
    config.LOG_DIR.mkdir(parents=True, exist_ok=True)
    config.configure_logging()
    logging_utils.init_logging()
    logger.info("Flask app created with configuration: %s", config_object.__name__)

    global _prune_thread_started
    if not _prune_thread_started:
        _start_log_prune_thread()
        _prune_thread_started = True

    # Ensure upload folder exists
    upload_folder = app.config.get("UPLOAD_FOLDER")
    if upload_folder:
        Path(upload_folder).mkdir(parents=True, exist_ok=True)

    # Flask-Login setup
    login_manager = LoginManager()
    login_manager.init_app(app)
    login_manager.login_view = "public.login"
    login_manager.login_message_category = "info"

    @login_manager.user_loader
    def load_user(user_id: str) -> User | None:
        try:
            return SessionLocal().query(User).get(int(user_id))
        except Exception:
            return None

    # CSRF protection
    @app.before_request
    def _csrf_protect() -> None:
        validate_csrf_token()

    @app.context_processor
    def _inject_csrf_token() -> dict[str, callable]:
        def csrf_field() -> str:
            token = generate_csrf_token()
            name = config_object.CSRF_FIELD_NAME
            return Markup(
                f'<input type="hidden" name="{name}" value="{token}">'
            )

        return {
            config_object.CSRF_FIELD_NAME: generate_csrf_token,
            "csrf_field": csrf_field,
        }

    @app.context_processor
    def _inject_current_user():
        try:
            from flask_login import current_user as login_user

            return {"current_user": login_user}
        except Exception:
            return {"current_user": SimpleNamespace(is_authenticated=False)}

    @app.teardown_appcontext
    def shutdown_session(exception: Exception | None = None) -> None:
        SessionLocal.remove()

    # Initialize database schema and seed data if needed
    init_db(app, seed=False)

    # Conditionally register installer if not already installed
    if not config.INSTALL_SENTINEL_FILE.exists():
        app.register_blueprint(install_bp)

    # Register all application blueprints
    app.register_blueprint(public_bp)
    app.register_blueprint(files_bp)
    app.register_blueprint(member_bp, url_prefix="/member")
    app.register_blueprint(admin_bp, url_prefix="/admin")
    app.register_blueprint(stub_bp)

    return app


def init_db(app: Flask, seed: bool = False) -> None:
    """Initialize the database schema and seed initial data if requested."""
    from sqlalchemy.exc import SQLAlchemyError
    from app import models  # Delayed import to avoid circular dependency

    logger.info("Initializing database for app: %s", app.name)
    logger.info("Creating database schema...")
    models.create_all()
    logger.info("Database schema creation complete.")

    if seed:
        logger.info("Seeding initial database data...")
        try:
            with models.engine.begin() as connection:
                connection.execute(
                    models.text(
                        "INSERT INTO users (id, email, hashed_password) VALUES (:id, :email, :pw)"
                    ),
                    {
                        "id": 1,
                        "email": "testuser@example.com",
                        "pw": "$2b$12$d0V5m6WmIul1gUHXqYOfH.uNar5dBVK0L37tVgW0z2Jl2J2yJ4j8W",
                    },
                )
                connection.execute(
                    models.text(
                        "INSERT INTO images (id, user_id, sha256, phash) VALUES (:id, :user_id, :sha256, :phash)"
                    ),
                    {
                        "id": 1,
                        "user_id": 1,
                        "sha256": "0e5751c026e543b2e8ab2eb06099eda2f4a2833f8b3e0b675d18497ad5e6eead",
                        "phash": "ffbbaaaaffbbaaaa",
                    },
                )
            logger.info("Initial data seeded successfully.")
        except SQLAlchemyError as e:
            logger.error("Error inserting seed data: %s", e)
            raise
    else:
        logger.info("Seed data insertion skipped (seed=False).")
