from __future__ import annotations

import re

from flask import Blueprint, flash, redirect, render_template, request, url_for

install_bp = Blueprint(
    "install",
    __name__,
    template_folder="../templates",
)


@install_bp.route("/install", methods=["GET", "POST"])
def install_index() -> str:
    """Display and handle the initial install form."""
    if request.method == "POST":
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
        for field in required_fields:
            if not form.get(field):
                errors.append(f"{field.replace('_', ' ').title()} is required")

        password = form.get("admin_password", "")
        if password and len(password) < 8:
            errors.append("Admin password must be at least 8 characters long")

        email = form.get("admin_email", "")
        if email and not re.match(r"[^@]+@[^@]+\.[^@]+", email):
            errors.append("Invalid admin email address")

        if errors:
            for err in errors:
                flash(err, "danger")
            return render_template("install.html", form=form)

        flash("Installation complete", "success")
        return redirect(url_for("public.index"))

    return render_template("install.html")