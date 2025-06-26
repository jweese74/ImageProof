"""Authenticated member routes for ImageProof."""

from flask import Blueprint

member_bp = Blueprint("member", __name__)


@member_bp.route("/")
def member_home() -> str:
    """Placeholder member route."""
    return "ImageProof member"