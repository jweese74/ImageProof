"""Flask application setup and database initialization for ImageProof."""

import logging
import time
from pathlib import Path
from threading import Thread
from types import SimpleNamespace

from flask import Flask

from app import config, logging_utils
from app.models import SessionLocal
from app.routes_admin import admin_bp
from app.routes_files import files_bp
from app.routes_member import member_bp
from app.routes_public import public_bp
from app.routes_stub import stub_bp
from app.routes_installer import installer_bp
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
    """Create and configure the Flask application.

    This function initializes the Flask app with the given configuration, sets up logging,
    and ensures the database is ready. It also registers a teardown function to close
    database sessions after each request.

    Args:
        config_object (type[config.BaseConfig]): The configuration class to use for the app.
            Defaults to config.DevelopmentConfig.

    Returns:
        Flask: The configured Flask application instance.
    """
    # Initialize Flask app and load configurations
    from pathlib import Path
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

    # CSRF protection
    @app.before_request
    def _csrf_protect() -> None:
        validate_csrf_token()

    @app.context_processor
    def _inject_csrf_token() -> dict[str, str]:
        return {config_object.CSRF_FIELD_NAME: generate_csrf_token()}

    @app.context_processor
    def _inject_current_user():
        try:
            from flask_login import current_user as login_user

            return {"current_user": login_user}
        except Exception:
            return {"current_user": SimpleNamespace(is_authenticated=False)}

    # Register database session cleanup on app context teardown
    @app.teardown_appcontext
    def shutdown_session(exception: Exception | None = None) -> None:
        """Remove database session at the end of request or app context."""
        SessionLocal.remove()

    # Initialize database (create tables, optionally seed data)
    init_db(app, seed=False)

    # If the installation sentinel is missing, expose the installer blueprint
    if not config.INSTALL_SENTINEL_FILE.exists():
        app.register_blueprint(installer_bp)

    # Register blueprints for application routes
    app.register_blueprint(public_bp)
    app.register_blueprint(files_bp)
    app.register_blueprint(member_bp, url_prefix="/member")
    app.register_blueprint(admin_bp, url_prefix="/admin")
    app.register_blueprint(stub_bp)

    return app


def init_db(app: Flask, seed: bool = False) -> None:
    """Initialize the database schema and seed initial data if requested.

    This function creates all database tables (if they do not exist) using the ORM models.
    If `seed` is True, it will load initial test data into the database for development/testing.

    Args:
        app (Flask): The Flask application instance (unused, provided for interface consistency).
        seed (bool, optional): Whether to insert initial seed data. Defaults to False.

    Returns:
        None

    Raises:
        SQLAlchemyError: If an error occurs during table creation or data insertion.
    """
    from sqlalchemy.exc import SQLAlchemyError

    from app import models  # import here to avoid circular imports

    logger.info("Initializing database for app: %s", app.name)
    logger.info("Creating database schema...")
    # Create tables from models
    models.create_all()
    logger.info("Database schema creation complete.")
    # Seed initial data if requested
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
