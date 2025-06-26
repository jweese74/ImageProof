"""Admin-only routes for ImageProof."""

from flask import Blueprint

admin_bp = Blueprint("admin", __name__)


@admin_bp.route("/")
def admin_home() -> str:
    """Placeholder admin route."""
    return "ImageProof admin"