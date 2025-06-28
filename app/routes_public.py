"""Public-facing routes for ImageProof."""

from flask import Blueprint, render_template, request, redirect, flash, url_for
from werkzeug.wrappers import Response
from werkzeug.security import generate_password_hash
from sqlalchemy.exc import IntegrityError

from app.models import User, SessionLocal
from app.security import generate_csrf_token

import os
from pathlib import Path
from werkzeug.utils import secure_filename
from PIL import Image as PILImage

from app.models import Image, SessionLocal
from app.image_processing import compute_sha256, compute_perceptual_hash
from app.watermark import apply_overlays
from app.certificate import create_registration_package


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
    hash_input = request.form.get("hash_input", "").strip()
    file = request.files.get("image_file")
    db = SessionLocal()

    try:
        results = []
        if hash_input:
            # SHA-256 hash lookup
            image = db.query(ImageRecord).filter_by(sha256=hash_input).first()
            if image:
                results = [image]
            else:
                flash("No exact match found for that hash.", "warning")

        elif file and file.filename:
            try:
                image_data = file.read()
                img = PILImage.open(BytesIO(image_data))
                sha256 = compute_sha256(image_data)
                phash = compute_perceptual_hash(img)

                # First check exact match by sha256
                image = db.query(ImageRecord).filter_by(sha256=sha256).first()
                if image:
                    results = [image]
                else:
                    # Fallback to perceptual similarity
                    all_images = db.query(ImageRecord).all()
                    results = [
                        i for i in all_images
                        if i.phash and compute_phash_similarity(i.phash, phash) > 0.85
                    ]
                    if not results:
                        flash("No similar images found.", "info")
            except Exception:
                flash("Invalid image file.", "danger")

        else:
            flash("Please provide a SHA-256 hash or upload an image.", "danger")

        return render_template("lookup_results.html", results=results)
    finally:
        db.close()

@public_bp.route("/register", methods=["GET", "POST"])
def register() -> str | Response:
    """Display and process the guest registration page."""
    if request.method == "POST":
        file = request.files.get("image")
        title = request.form.get("title", "").strip()
        creator = request.form.get("creator", "").strip()
        wm_position = request.form.get("wm_position", "bottom-right")
        wm_color = request.form.get("wm_color", "#FFFFFF")

        if not file or not title:
            flash("Image and title are required.", "danger")
            return redirect(url_for("public.register"))

        ext = Path(file.filename).suffix.lower()
        if ext not in ALLOWED_EXTENSIONS:
            flash("Invalid file type. Only PNG/JPG allowed.", "danger")
            return redirect(url_for("public.register"))

        # Save original
        safe_name = secure_filename(file.filename)
        original_path = UPLOAD_FOLDER / safe_name
        original_path.parent.mkdir(parents=True, exist_ok=True)
        file.save(original_path)

        # Open and fingerprint
        img = PILImage.open(original_path)
        sha256 = compute_sha256(original_path.read_bytes())
        phash = compute_perceptual_hash(img)

        # Apply watermark if needed
        overlays = [{
            "type": "text",
            "text": creator or "Anonymous",
            "position": wm_position,
            "color": wm_color,
            "opacity": 128,
        }]
        watermarked_img = apply_overlays(img.copy(), overlays)
        watermarked_path = original_path.with_stem(original_path.stem + "_wm")
        watermarked_img.save(watermarked_path)

        # Insert into DB
        db = SessionLocal()
        image_record = Image(
            title=title,
            creator_name=creator or "Anonymous",
            sha256=sha256,
            phash=phash,
            status="Certified",
        )
        db.add(image_record)
        db.commit()

        # Create ZIP package
        zip_path = create_registration_package(
            image_record=image_record,
            original_path=original_path,
            watermarked_path=watermarked_path,
        )

        flash("Image registered. Downloading proof package...", "success")
        return redirect(url_for("files.download_file", filename=zip_path.name))

    return render_template("register_guest.html")

@public_bp.route("/login", methods=["GET", "POST"])
def login() -> str | Response:
    """Render the login page or handle login submission."""
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "").strip()

        if not email or not password:
            flash("Please provide both email and password.", "danger")
            return redirect(url_for("public.login"))

        db = SessionLocal()
        try:
            user = db.query(User).filter_by(email=email).first()
            if user and check_password_hash(user.hashed_password, password):
                login_user(user)
                flash("Login successful.", "success")
                return redirect(url_for("member.member_home"))  # Or dashboard
            else:
                flash("Invalid email or password.", "danger")
        finally:
            db.close()

        return redirect(url_for("public.login"))

    return render_template("login.html")

@public_bp.route("/signup", methods=["GET", "POST"])
def signup() -> str | Response:
    """Render the sign-up page or handle account creation."""
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "").strip()
        agreed = request.form.get("agree_terms") == "on"

        # Basic validation
        if not email or not password or not agreed:
            flash("All fields are required and terms must be accepted.", "danger")
            return redirect(url_for("public.signup"))

        if len(password) < 8:
            flash("Password must be at least 8 characters long.", "danger")
            return redirect(url_for("public.signup"))

        # Attempt to create new user
        db = SessionLocal()
        try:
            hashed_pw = generate_password_hash(password)
            user = User(email=email, hashed_password=hashed_pw)
            db.add(user)
            db.commit()
            flash("Account created successfully. You can now log in.", "success")
            return redirect(url_for("public.login"))
        except IntegrityError:
            db.rollback()
            flash("That email is already registered.", "danger")
        finally:
            db.close()

        return redirect(url_for("public.signup"))

    return render_template("signup.html")
