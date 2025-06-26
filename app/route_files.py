"""File upload and download routes for ImageProof."""

from __future__ import annotations

import logging
from pathlib import Path

from flask import Blueprint, abort, current_app, request, send_from_directory
from werkzeug.utils import secure_filename

logger = logging.getLogger(__name__)

files_bp = Blueprint("files", __name__)


def _allowed_file(filename: str) -> bool:
    allowed_exts = current_app.config.get("ALLOWED_EXTENSIONS", ())
    return Path(filename).suffix.lower() in allowed_exts


@files_bp.route("/upload", methods=["POST"])
def upload() -> str:
    """Handle a simple file upload."""
    if "file" not in request.files:
        abort(400, description="No file part")
    file = request.files["file"]
    if file.filename == "":
        abort(400, description="No selected file")
    if not _allowed_file(file.filename):
        abort(400, description="File type not allowed")
    upload_folder = current_app.config.get("UPLOAD_FOLDER")
    Path(upload_folder).mkdir(parents=True, exist_ok=True)
    fname = secure_filename(file.filename)
    dest = Path(upload_folder) / fname
    file.save(dest)
    logger.info("Saved uploaded file to %s", dest)
    return "uploaded"


@files_bp.route("/download/<path:filename>")
def download(filename: str):
    """Serve a file from the upload directory."""
    upload_folder = current_app.config.get("UPLOAD_FOLDER")
    dest = Path(upload_folder) / filename
    if not dest.exists():
        abort(404)
    return send_from_directory(upload_folder, filename, as_attachment=True)