"""Certificate & Package Generation for ImageProof.

This module handles creation of proof-of-authenticity certificates (PDF/JSON)
and packaging of image files into a downloadable ZIP archive.
"""
from __future__ import annotations
import io
import json
import logging
import tempfile
import zipfile
from datetime import datetime
from pathlib import Path
from typing import Optional

from reportlab.lib.pagesizes import LETTER
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas
import qrcode

try:
    # Import the Image model class for type hints
    from app.models import Image  # type: ignore[attr-defined]
except ImportError:  # If models not available at runtime, ignore
    Image = None

logger = logging.getLogger(__name__)

def generate_qr_code(data: str) -> bytes:
    """Generate a PNG QR code for the given string data.
    Returns the image bytes of the QR code in PNG format.
    Raises ValueError if data is invalid (e.g., not a non-empty string).
    """
    if not isinstance(data, str) or not data:
        logger.error("Invalid data for QR code: %r", data)
        raise ValueError("Data for QR code must be a non-empty string.")
    try:
        logger.debug("Generating QR code for data: %s", data if len(data) < 100 else data[:100] + "...")
        qr_img = qrcode.make(data)  # PIL Image
        with io.BytesIO() as output:
            qr_img.save(output, format="PNG")
            qr_bytes = output.getvalue()
        logger.info("QR code generation successful (data length %d bytes).", len(data))
        return qr_bytes
    except Exception as e:
        logger.exception("Failed to generate QR code: %s", e)
        raise

def generate_certificate(image_record: Image, *, fmt: str = "PDF") -> Path:
    """Generate a proof-of-authenticity certificate for the given image_record.
    If fmt is "PDF", produces a PDF file with the image's details and QR code.
    If fmt is "JSON", produces a JSON file with the image's details.
    Returns the path to the generated certificate file (in a temporary directory).
    Raises ValueError if an unsupported format is requested.
    """
    fmt_up = fmt.upper()
    if fmt_up not in {"PDF", "JSON"}:
        logger.error("Unsupported certificate format requested: %s", fmt)
        raise ValueError(f"Unsupported certificate format: {fmt}")
    # Create temp directory for certificate file
    temp_dir = Path(tempfile.mkdtemp())
    timestamp = datetime.utcnow()
    try:
        if fmt_up == "PDF":
            cert_path = temp_dir / "certificate.pdf"
            # Prepare PDF canvas
            c = canvas.Canvas(str(cert_path), pagesize=LETTER)
            width, height = LETTER
            # Title
            c.setFont("Helvetica-Bold", 18)
            c.drawString(72, height - 72, "Certificate of Authenticity")
            c.setFont("Helvetica", 12)
            text_y = height - 108  # line below title
            # Include key metadata fields
            if hasattr(image_record, "title"):
                c.drawString(72, text_y, f"Title: {getattr(image_record, 'title')}")
                text_y -= 18
            if hasattr(image_record, "creator") or hasattr(image_record, "creator_name"):
                creator_name = getattr(image_record, "creator", None) or getattr(image_record, "creator_name", None)
                if creator_name:
                    c.drawString(72, text_y, f"Creator: {creator_name}")
                    text_y -= 18
            if hasattr(image_record, "registered_at"):
                reg_time = getattr(image_record, "registered_at")
                if reg_time:
                    # Format registration time as string
                    reg_str = reg_time.isoformat() if hasattr(reg_time, "isoformat") else str(reg_time)
                    c.drawString(72, text_y, f"Registered At: {reg_str}")
                    text_y -= 18
            # Unique hash/ID
            if hasattr(image_record, "sha256"):
                hash_val = getattr(image_record, "sha256")
                c.drawString(72, text_y, f"SHA-256: {hash_val}")
                text_y -= 18
            # Timestamp of certificate generation
            gen_str = timestamp.isoformat(sep=' ', timespec='seconds')
            c.drawString(72, text_y, f"Certificate Generated At: {gen_str} UTC")
            text_y -= 18
            # Generate and embed QR code linking to verification URL
            if hasattr(image_record, "sha256") and getattr(image_record, "sha256"):
                verify_url = f"https://imageproof.local/verify?sha256={getattr(image_record, 'sha256')}"
                try:
                    qr_img = qrcode.make(verify_url)
                    qr_reader = ImageReader(qr_img)
                    qr_size = 144  # 2 inches square
                    c.drawImage(qr_reader, width - qr_size - 72, 72, qr_size, qr_size)
                except Exception as e:
                    logger.error("Failed to embed QR code in certificate: %s", e)
            c.showPage()
            c.save()
        else:  # JSON
            cert_path = temp_dir / "certificate.json"
            data = {}
            # Include all fields from image_record (shallow copy of __dict__)
            if hasattr(image_record, "__dict__"):
                # Exclude any internal/private attributes if present
                data.update({k: v for k, v in image_record.__dict__.items() if not k.startswith("_")})
            else:
                # If no __dict__, attempt attribute-based extraction
                for attr in dir(image_record):
                    if attr.startswith("_") or callable(getattr(image_record, attr)):
                        continue
                    try:
                        data[attr] = getattr(image_record, attr)
                    except Exception:
                        pass
            # Add verification URL and timestamp
            if data.get("sha256"):
                data["verify_url"] = f"https://imageproof.local/verify?sha256={data['sha256']}"
            else:
                # If sha256 not present as key, try attribute
                sha_val = getattr(image_record, "sha256", None)
                if sha_val:
                    data["sha256"] = sha_val
                    data["verify_url"] = f"https://imageproof.local/verify?sha256={sha_val}"
            data["generated_at"] = timestamp.isoformat()
            # Ensure JSON serializable (convert any datetime to string)
            for key, value in list(data.items()):
                if isinstance(value, datetime):
                    data[key] = value.isoformat()
            cert_path.write_text(json.dumps(data, indent=2))
        logger.info("Certificate generated in %s format at %s", fmt_up, cert_path)
        return cert_path
    except Exception as e:
        logger.exception("Failed to generate certificate: %s", e)
        # Clean up partial file if exists
        raise

def create_registration_package(image_record: Image,
                                original_image: Path,
                                watermarked_image: Path,
                                social_image: Path | None = None,
                                signature_image: Path | None = None) -> Path:
    """Create a ZIP package containing the proof-of-authenticity certificate and related images.
    The ZIP (named <sha256>.zip) will include:
      - Certificate file (PDF or JSON) for the image_record (always PDF in current implementation).
      - Original image (exact file user uploaded).
      - Watermarked image (final image with overlays).
      - Social image (downsized watermarked image) if provided.
      - Signature image (signature-only overlay or version) if provided.
      - metadata.json file containing all fields of image_record.
    Returns the path to the created ZIP file in a temporary directory.
    Raises FileNotFoundError if any required file path does not exist.
    """
    # Validate required files exist
    if not original_image.exists():
        logger.error("Original image file not found: %s", original_image)
        raise FileNotFoundError(f"Original image file not found: {original_image}")
    if not watermarked_image.exists():
        logger.error("Watermarked image file not found: %s", watermarked_image)
        raise FileNotFoundError(f"Watermarked image file not found: {watermarked_image}")
    if social_image and not social_image.exists():
        logger.error("Social image file not found: %s", social_image)
        raise FileNotFoundError(f"Social image file not found: {social_image}")
    if signature_image and not signature_image.exists():
        logger.error("Signature image file not found: %s", signature_image)
        raise FileNotFoundError(f"Signature image file not found: {signature_image}")
    # Determine package name using image's SHA-256 (or unique ID if hash missing)
    sha_val = getattr(image_record, "sha256", None) or getattr(image_record, "sha256_hash", None)
    if not sha_val:
        # Fallback to image id if hash not present
        sha_val = str(getattr(image_record, "id", "image"))
    package_name = f"{sha_val}.zip"
    temp_dir = Path(tempfile.mkdtemp())
    package_path = temp_dir / package_name
    logger.info("Creating registration package: %s", package_path)
    try:
        # Generate certificate (PDF) for inclusion in the package
        cert_path = generate_certificate(image_record, fmt="PDF")
        with zipfile.ZipFile(package_path, mode="w") as zf:
            # Add certificate, renaming inside ZIP to a fixed name
            zf.write(cert_path, arcname=("certificate.pdf"))
            # Add original image (preserve original filename if possible)
            zf.write(original_image, arcname=original_image.name)
            # Add watermarked image
            zf.write(watermarked_image, arcname=watermarked_image.name)
            # Add optional images if present
            if social_image:
                zf.write(social_image, arcname=social_image.name)
            if signature_image:
                zf.write(signature_image, arcname=signature_image.name)
            # Add metadata.json with image_record fields
            metadata = {}
            if hasattr(image_record, "__dict__"):
                metadata.update({k: v for k, v in image_record.__dict__.items() if not k.startswith("_")})
            else:
                for attr in dir(image_record):
                    if attr.startswith("_") or callable(getattr(image_record, attr)):
                        continue
                    try:
                        metadata[attr] = getattr(image_record, attr)
                    except Exception:
                        pass
            # Convert any non-serializable values (e.g., datetime) to strings
            for k, v in list(metadata.items()):
                if isinstance(v, datetime):
                    metadata[k] = v.isoformat()
            zf.writestr("metadata.json", json.dumps(metadata, indent=2))
        logger.info("Registration package created at %s", package_path)
    except Exception as e:
        logger.exception("Failed to create registration package: %s", e)
        # If an exception occurred, attempt to remove incomplete zip
        try:
            package_path.unlink(missing_ok=True)
        except Exception:
            pass
        raise
    return package_path
