import io
import json
import zipfile
from types import SimpleNamespace
import pytest

from app import certificate

def test_generate_qr_code_valid():
    data = "https://example.com/test"
    qr_bytes = certificate.generate_qr_code(data)
    # Should return bytes and start with PNG header
    assert isinstance(qr_bytes, bytes)
    # PNG signature is 8 bytes: 0x89 50 4E 47 0D 0A 1A 0A
    assert qr_bytes[:8] == b'\x89PNG\r\n\x1a\n'

def test_generate_qr_code_invalid():
    with pytest.raises(ValueError):
        certificate.generate_qr_code("")  # empty string not allowed
    with pytest.raises(ValueError):
        certificate.generate_qr_code(123)  # non-string not allowed

def test_generate_certificate_pdf(tmp_path):
    # Create a dummy image record
    image_record = SimpleNamespace(
        id=1,
        title="Test Image",
        creator="Jane Doe",
        description="A sample test image.",
        sha256="2d711642b726b04401627ca9fbac32f5c8530fb1903cc4db02258717921a4881",
        registered_at="2025-01-01T00:00:00"
    )
    cert_path = certificate.generate_certificate(image_record, fmt="PDF")
    assert cert_path.exists()
    # Check that the PDF file starts with '%PDF'
    with open(cert_path, "rb") as f:
        header = f.read(4)
    assert header == b"%PDF"
    # Also test that specifying unsupported format raises
    with pytest.raises(ValueError):
        certificate.generate_certificate(image_record, fmt="TXT")

def test_generate_certificate_json(tmp_path):
    image_record = SimpleNamespace(
        title="Another Test",
        creator_name="John Doe",
        sha256="abcdef1234567890",
        registered_at=None
    )
    cert_path = certificate.generate_certificate(image_record, fmt="JSON")
    assert cert_path.exists()
    # Check that the file is JSON and contains expected fields
    data = json.loads(cert_path.read_text())
    # Should include sha256 and verify_url with the same hash
    assert data.get("sha256") == image_record.sha256
    assert "verify_url" in data and str(image_record.sha256) in data["verify_url"]
    # Should include generated_at timestamp
    assert "generated_at" in data
    # Creator_name field should be present in JSON if in image_record
    assert data.get("creator_name") == "John Doe"

def test_create_registration_package(tmp_path):
    # Dummy image files (1 byte each)
    orig_file = tmp_path / "original.png"
    orig_file.write_bytes(b"x")
    wm_file = tmp_path / "watermarked.png"
    wm_file.write_bytes(b"x")
    social_file = tmp_path / "social.png"
    social_file.write_bytes(b"x")
    sig_file = tmp_path / "signature.png"
    sig_file.write_bytes(b"x")
    # Dummy image record
    image_record = SimpleNamespace(
        id=42,
        title="Package Test",
        creator="Alice",
        sha256="d34db33f",
        extra_field="extra"  # some extra metadata to test metadata.json
    )
    package_path = certificate.create_registration_package(
        image_record, orig_file, wm_file, social_image=social_file, signature_image=sig_file
    )
    assert package_path.exists()
    # Package name should be <sha256>.zip
    assert package_path.name == f"{image_record.sha256}.zip"
    # Check ZIP contents
    with zipfile.ZipFile(package_path, "r") as zf:
        namelist = zf.namelist()
        # Certificate, original, watermarked, social, signature, metadata.json
        assert "certificate.pdf" in namelist
        assert orig_file.name in namelist
        assert wm_file.name in namelist
        assert social_file.name in namelist
        assert sig_file.name in namelist
        assert "metadata.json" in namelist
        # Verify metadata.json content matches image_record fields
        metadata_content = zf.read("metadata.json")
        meta = json.loads(metadata_content.decode("utf-8"))
        # The metadata should contain all keys from image_record.__dict__
        for key, value in image_record.__dict__.items():
            # We expect the same values in metadata (all serializable here)
            assert meta.get(key) == value

def test_create_registration_package_missing_optional(tmp_path):
    # Dummy files for required ones
    orig_file = tmp_path / "orig.jpg"
    orig_file.write_bytes(b"x")
    wm_file = tmp_path / "wm.jpg"
    wm_file.write_bytes(b"x")
    image_record = SimpleNamespace(id=99, sha256="ff00ff00")
    # No social_image or signature_image provided
    package_path = certificate.create_registration_package(image_record, orig_file, wm_file)
    with zipfile.ZipFile(package_path, "r") as zf:
        namelist = zf.namelist()
        assert "certificate.pdf" in namelist
        assert orig_file.name in namelist
        assert wm_file.name in namelist
        # These optional files should not be present
        assert all("social" not in name for name in namelist)
        assert all("signature" not in name for name in namelist)

def test_create_registration_package_missing_files(tmp_path):
    # Create only one of the files
    orig_file = tmp_path / "orig2.png"
    # Do not create orig_file (to trigger error)
    wm_file = tmp_path / "wm2.png"
    wm_file.write_bytes(b"x")
    image_record = SimpleNamespace(sha256="hashhash")
    # original_image does not exist, expect FileNotFoundError
    with pytest.raises(FileNotFoundError):
        certificate.create_registration_package(image_record, orig_file, wm_file)
