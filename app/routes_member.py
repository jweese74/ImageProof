"""Authenticated member routes for ImageProof."""

from flask import Blueprint, render_template
from app.security import require_login

member_bp = Blueprint(
    "member",
    __name__,
    template_folder="../templates",
)

@member_bp.route("/")
@require_login()
def member_dashboard() -> str:
    """Render the main member dashboard page."""
    # TODO: Inject user's registered images and flash messages if needed.
    return render_template("dashboard.html")
