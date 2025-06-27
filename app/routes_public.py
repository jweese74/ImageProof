"""Public-facing routes for ImageProof."""

from flask import Blueprint, render_template

public_bp = Blueprint("public", __name__)


@public_bp.route("/")
def index() -> str:
    """Render the public home page."""
    return render_template("index.html")


@public_bp.route("/lookup", methods=["POST"])
def lookup() -> str:
    """Placeholder lookup endpoint."""
    return "lookup", 200


@public_bp.route("/register")
def register() -> str:
    """Placeholder register page."""
    return "register", 200


@public_bp.route("/login")
def login() -> str:
    """Placeholder login page."""
    return "login", 200


@public_bp.route("/signup")
def signup() -> str:
    """Placeholder signup page."""
    return "signup", 200