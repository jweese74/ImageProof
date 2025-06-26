"""Public-facing routes for ImageProof."""

from flask import Blueprint

public_bp = Blueprint("public", __name__)


@public_bp.route("/")
def index() -> str:
    """Basic health check endpoint."""
    return "ImageProof public"