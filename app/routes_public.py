"""Public-facing routes for ImageProof."""

from flask import Blueprint, render_template, request, redirect, flash, url_for
from werkzeug.wrappers import Response

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
def lookup() -> Response:
    """Process hash or image-based lookup request."""
    hash_value = request.form.get("hash_input") or ""
    # TODO: Implement actual lookup logic and redirect to results
    flash("Lookup feature is coming soon.", "info")
    return redirect(url_for("public.index"))


@public_bp.route("/register", methods=["GET", "POST"])
def register() -> str | Response:
    """Display and process the guest registration page."""
    if request.method == "POST":
        # TODO: Add guest registration handler
        flash("Guest registration logic not yet implemented.", "warning")
        return redirect(url_for("public.register"))
    return render_template("register_guest.html")


@public_bp.route("/login", methods=["GET", "POST"])
def login() -> str | Response:
    """Render the login page or handle login submission."""
    if request.method == "POST":
        # TODO: Add authentication logic
        flash("Login processing not yet implemented.", "warning")
        return redirect(url_for("public.login"))
    return render_template("login.html")


@public_bp.route("/signup", methods=["GET", "POST"])
def signup() -> str | Response:
    """Render the sign-up page or handle account creation."""
    if request.method == "POST":
        # TODO: Add user creation logic
        flash("Sign-up functionality coming soon.", "info")
        return redirect(url_for("public.signup"))
    return render_template("signup.html")
