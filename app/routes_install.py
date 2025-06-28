from __future__ import annotations

import re

from flask import Blueprint, flash, redirect, render_template, request, url_for
from app.security import validate_csrf_token

install_bp = Blueprint(
    "install",
    __name__,
    template_folder="../templates",
)


@install_bp.route("/install", methods=["GET", "POST"])
def install_index() -> str:
    """
    Display and handle the initial install form.
    On GET: Render the form.
    On POST: Validate and process submitted install data.
    """
    if request.method == "POST":
        # Enforce CSRF validation
        validate_csrf_token()

        form = request.form
        errors: list[str] = []

        required_fields = [
            "db_host",
            "db_port",
            "db_name",
            "db_user",
            "db_password",
            "admin_email",
            "admin_password",
        ]

        # Check all required fields
        for field in required_fields:
            if not form.get(field):
                errors.append(f"{field.replace('_', ' ').title()} is required")

        # Enforce admin password strength
        password = form.get("admin_password", "")
        if password and len(password) < 8:
            errors.append("Admin password must be at least 8 characters long")

        # Validate email format
        email = form.get("admin_email", "")
        if email and not re.match(r"[^@]+@[^@]+\.[^@]+", email):
            errors.append("Invalid admin email address")

        # Show errors or proceed
        if errors:
            for err in errors:
                flash(err, "danger")
            return render_template("install.html", form=form)

        # [TO-DO] Persist config to environment or database
        # [TO-DO] Create admin user securely

        flash("Installation complete. You may now log in.", "success")
        return redirect(url_for("public.index"))

    # Default GET behavior
    return render_template("install.html", form={})
