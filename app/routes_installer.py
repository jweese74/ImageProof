from flask import Blueprint

installer_bp = Blueprint("install", __name__)

@installer_bp.route("/install")
def install_index() -> str:
    """Placeholder installer page shown on first run."""
    return "Installer", 200