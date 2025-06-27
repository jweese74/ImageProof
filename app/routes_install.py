from flask import Blueprint, render_template

install_bp = Blueprint("install", __name__)

@install_bp.route("/install")
def install_index() -> str:
    """Display the initial install form."""
    return render_template("install.html")