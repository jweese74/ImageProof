"""Flask application setup and database initialization for ImageProof."""
import logging
from flask import Flask
from app import config
from app.models import SessionLocal

logger: logging.Logger = logging.getLogger(__name__)


def create_app(config_object: type[config.BaseConfig] = config.DevelopmentConfig) -> Flask:
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
    app = Flask(__name__)
    app.config.from_object(config_object)
    config.configure_logging()
    logger.info("Flask app created with configuration: %s", config_object.__name__)

    # Register database session cleanup on app context teardown
    @app.teardown_appcontext
    def shutdown_session(exception: Exception | None = None) -> None:
        """Remove database session at the end of request or app context."""
        SessionLocal.remove()

    # Initialize database (create tables, optionally seed data)
    init_db(app, seed=False)
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
    from app import models  # import here to avoid circular imports
    from sqlalchemy.exc import SQLAlchemyError

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
                    models.text("INSERT INTO users (id, email, hashed_password) VALUES (:id, :email, :pw)"),
                    {"id": 1, "email": "testuser@example.com",
                     "pw": "$2b$12$d0V5m6WmIul1gUHXqYOfH.uNar5dBVK0L37tVgW0z2Jl2J2yJ4j8W"}
                )
                connection.execute(
                    models.text("INSERT INTO images (id, user_id, sha256, phash) VALUES (:id, :user_id, :sha256, :phash)"),
                    {"id": 1, "user_id": 1,
                     "sha256": "0e5751c026e543b2e8ab2eb06099eda2f4a2833f8b3e0b675d18497ad5e6eead",
                     "phash": "ffbbaaaaffbbaaaa"}
                )
            logger.info("Initial data seeded successfully.")
        except SQLAlchemyError as e:
            logger.error("Error inserting seed data: %s", e)
            raise
    else:
        logger.info("Seed data insertion skipped (seed=False).")
