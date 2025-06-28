"""File upload and download routes for ImageProof."""

from __future__ import annotations

import logging
from pathlib import Path

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
from werkzeug.utils import secure_filename

logger = logging.getLogger(__name__)

files_bp = Blueprint(
    "files",
    __name__,
    template_folder="../templates",
)


def _allowed_file(filename: str) -> bool:
    allowed_exts = current_app.config.get("ALLOWED_EXTENSIONS", ())
    return Path(filename).suffix.lower() in allowed_exts


@files_bp.route("/upload", methods=["GET"])
def upload_form():
    """Render the file upload form."""
    return render_template("upload.html")  # Assumes you have a template named upload.html


@files_bp.route("/upload", methods=["POST"])
def upload():
    """Handle a file upload via POST form."""
    if "file" not in request.files:
        flash("No file part in request", "error")
        return redirect(request.url)
    
    file = request.files["file"]
    
    if file.filename == "":
        flash("No file selected", "error")
        return redirect(request.url)

    if not _allowed_file(file.filename):
        flash("File type not allowed", "error")
        return redirect(request.url)

    upload_folder = current_app.config.get("UPLOAD_FOLDER")
    Path(upload_folder).mkdir(parents=True, exist_ok=True)

    fname = secure_filename(file.filename)
    dest = Path(upload_folder) / fname
    file.save(dest)

    logger.info("Saved uploaded file to %s", dest)
    flash(f"File '{fname}' uploaded successfully", "success")
    return redirect(url_for("files.upload_form"))


@files_bp.route("/download/<path:filename>")
def download(filename: str):
    """Serve a file from the upload directory."""
    upload_folder = current_app.config.get("UPLOAD_FOLDER")
    dest = Path(upload_folder) / filename
    if not dest.exists():
        abort(404)
    return send_from_directory(upload_folder, filename, as_attachment=True)
