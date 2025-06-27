"""Admin-only routes for ImageProof."""

from __future__ import annotations

from pathlib import Path
from datetime import datetime
import re

from flask import (
    Blueprint,
    abort,
    current_app,
    flash,
    redirect,
    render_template,
    request,
    send_from_directory,
    url_for,
)

from app import logging_utils
from app.security import require_login

admin_bp = Blueprint("admin", __name__)


@admin_bp.route("/")
def admin_home() -> str:
    """Placeholder admin route."""
    return "ImageProof admin"


@admin_bp.route("/logs", methods=["GET", "POST"])
@require_login(role="Admin")
def logs_dashboard():
    if request.method == "POST":
        new = request.form["log_level"]
        logging_utils.set_log_level(new)
        flash(f"Log level changed to {new}", "success")
        return redirect(url_for("admin.logs_dashboard"))

    files = sorted(
        Path(current_app.config["LOG_DIR"]).glob("imageproof.log*"),
        key=lambda p: p.stat().st_mtime,
        reverse=True,
    )
    files_info = [
        {
            "name": f.name,
            "size_mb": f.stat().st_size / 1024 / 1024,
            "modified": datetime.fromtimestamp(f.stat().st_mtime),
        }
        for f in files
    ]
    return render_template(
        "admin/logs.html",
        current_level=logging_utils.get_log_level(),
        choices=current_app.config["LOG_LEVEL_CHOICES"],
        files=files_info,
    )


@admin_bp.route("/logs/download/<fname>")
@require_login(role="Admin")
def download_log(fname):
    safe = re.fullmatch(r"imageproof\.log(\.\d+)?(\.gz)?", fname)
    if not safe:
        abort(404)
    return send_from_directory(
        current_app.config["LOG_DIR"], fname, as_attachment=True
    )