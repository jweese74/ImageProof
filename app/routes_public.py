"""Public-facing routes for ImageProof."""

from flask import Blueprint, render_template, request

public_bp = Blueprint(
    "public",
    __name__,
    template_folder="../templates",
)


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
    """Display the guest registration page."""
    return render_template("register_guest.html")


@public_bp.route("/login", methods=["GET", "POST"])
def login() -> str:
    """Render the login page or handle login submission."""
    if request.method == "POST":
        # TODO: implement login logic
        return "login", 200
    return render_template("login.html")


@public_bp.route("/signup", methods=["GET", "POST"])
def signup() -> str:
    """Render the sign-up page or handle account creation."""
    if request.method == "POST":
        # TODO: implement sign-up logic
        return "signup", 200
    return render_template("signup.html")