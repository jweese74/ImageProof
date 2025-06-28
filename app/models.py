"""Database models and engine setup for ImageProof."""
import logging
from datetime import datetime
from typing import List, Optional

from sqlalchemy import create_engine, text, String, DateTime, Text, ForeignKey, Index
from sqlalchemy.dialects import mysql
from sqlalchemy.engine import Engine
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship, Session, scoped_session, sessionmaker

# Initialize logger for this module
logger: logging.Logger = logging.getLogger(__name__)


class Base(DeclarativeBase):
    """Base declarative class for all database models."""
    pass


class User(Base):
    """User account model storing login credentials and user information."""
    __tablename__ = "users"
    __table_args__ = {"mysql_engine": "InnoDB", "mysql_charset": "utf8mb4"}

    id: Mapped[int] = mapped_column(mysql.INTEGER(unsigned=True), primary_key=True)
    email: Mapped[str] = mapped_column(String(255), nullable=False, unique=True)
    hashed_password: Mapped[str] = mapped_column(String(255), nullable=False)

    # Relationships
    images: Mapped[List["Image"]] = relationship(back_populates="user")
    action_logs: Mapped[List["ActionLog"]] = relationship(back_populates="user")


class Image(Base):
    """Image record model storing uploaded image metadata and hashes."""
    __tablename__ = "images"
    __table_args__ = (
        Index("idx_images_sha256", "sha256"),
        Index("idx_images_phash", "phash"),
        {"mysql_engine": "InnoDB", "mysql_charset": "utf8mb4"},
    )

    id: Mapped[int] = mapped_column(mysql.INTEGER(unsigned=True), primary_key=True)
    user_id: Mapped[Optional[int]] = mapped_column(
        mysql.INTEGER(unsigned=True),
        ForeignKey("users.id"),
        nullable=True,
    )
    title: Mapped[str] = mapped_column(String(255), nullable=False)
    creator_name: Mapped[str] = mapped_column(String(255), nullable=False)
    status: Mapped[str] = mapped_column(String(50), default="Certified")
    registered_at: Mapped[datetime] = mapped_column(
        DateTime,
        nullable=False,
        server_default=text("CURRENT_TIMESTAMP")
    )
    sha256: Mapped[str] = mapped_column(String(64), nullable=False)
    phash: Mapped[str] = mapped_column(String(16), nullable=False)

    # Relationships
    user: Mapped[Optional["User"]] = relationship(back_populates="images")
    action_logs: Mapped[List["ActionLog"]] = relationship(back_populates="image")


class ActionLog(Base):
    """Audit log model for recording significant user and image actions."""
    __tablename__ = "action_log"
    __table_args__ = {"mysql_engine": "InnoDB", "mysql_charset": "utf8mb4"}

    id: Mapped[int] = mapped_column(mysql.INTEGER(unsigned=True), primary_key=True)
    user_id: Mapped[Optional[int]] = mapped_column(
        mysql.INTEGER(unsigned=True),
        ForeignKey("users.id"),
        nullable=True,
    )
    image_id: Mapped[Optional[int]] = mapped_column(
        mysql.INTEGER(unsigned=True),
        ForeignKey("images.id"),
        nullable=True,
    )
    action: Mapped[str] = mapped_column(String(50), nullable=False)
    timestamp: Mapped[datetime] = mapped_column(
        DateTime, nullable=False, server_default=text("CURRENT_TIMESTAMP")
    )
    details: Mapped[Optional[str]] = mapped_column(Text)

    # Relationships
    user: Mapped[Optional["User"]] = relationship(back_populates="action_logs")
    image: Mapped[Optional["Image"]] = relationship(back_populates="action_logs")


# Database engine and session configuration
try:
    from app import config
    db_uri: str = getattr(config.BaseConfig, "DATABASE_URI", "") or ""
    if not db_uri:
        logger.error("Database URI is not set.")
        raise RuntimeError("No DATABASE_URI provided for engine.")
    engine: Engine = create_engine(db_uri, echo=False)
    logger.info("Database engine created successfully.")
except Exception as e:
    logger.error("Failed to create database engine: %s", e)
    raise

try:
    SessionLocal: scoped_session[Session] = scoped_session(sessionmaker(bind=engine))
    logger.info("Session maker configured successfully.")
except Exception as e:
    logger.error("Failed to configure SessionLocal: %s", e)
    raise


def create_all() -> None:
    """Create all database tables defined by the ORM models."""
    try:
        Base.metadata.create_all(bind=engine)
        logger.info("All tables created (if not existing).")
    except SQLAlchemyError as e:
        logger.error("Error creating tables: %s", e)
        raise
