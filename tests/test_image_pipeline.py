# tests/test_image_pipeline.py

import os
os.environ["DATABASE_URI"] = "sqlite:///:memory:"

import pytest
from io import BytesIO
from PIL import Image
import numpy as np

from app.image_processing import (
    compute_sha256,
    compute_perceptual_hash,
    phash_similarity,
    extract_orb_features,
    find_similar_images,
)
from app.watermark import apply_text_watermark, apply_image_watermark, apply_overlays
from app.app import create_app, init_db
from app import models


def get_image_bytes(img: Image.Image) -> bytes:
    with BytesIO() as output:
        img.save(output, format="PNG")
        return output.getvalue()


def test_hash_and_similarity():
    red = Image.new("RGB", (16, 16), (255, 0, 0))
    blue = red.copy()
    blue.putpixel((0, 0), (0, 0, 255))
    red_bytes = get_image_bytes(red)
    blue_bytes = get_image_bytes(blue)

    sha_red = compute_sha256(red_bytes)
    sha_blue = compute_sha256(blue_bytes)
    assert isinstance(sha_red, str)
    assert isinstance(sha_blue, str)
    assert sha_red != sha_blue

    phash_red = compute_perceptual_hash(red)
    phash_blue = compute_perceptual_hash(blue)
    similarity = phash_similarity(phash_red, phash_blue)
    assert similarity >= 0.9


def test_orb_features():
    red = Image.new("RGB", (16, 16), (255, 0, 0))
    try:
        descriptors = extract_orb_features(red)
    except ImportError:
        pytest.skip("OpenCV not available")
    else:
        assert isinstance(descriptors, (list, np.ndarray))


def test_watermark_changes():
    red = Image.new("RGB", (16, 16), (255, 0, 0))
    red_rgba = red.convert("RGBA")
    text_wm = apply_text_watermark(red_rgba, "Hi", "center")
    assert text_wm != red_rgba

    overlay = Image.new("RGBA", (8, 8), (0, 0, 255, 255))
    img_wm = apply_image_watermark(red_rgba, overlay, "top-left", opacity=1.0)
    assert img_wm != red_rgba


def test_apply_overlays_limit():
    red = Image.new("RGBA", (16, 16), (255, 0, 0, 255))
    overlays = []
    for i in range(4):
        overlays.append({"type": "text", "text": str(i), "position": "center"})
    with pytest.raises(ValueError):
        apply_overlays(red, overlays)


def test_find_similar_images():
    app = create_app()
    init_db(app, seed=False)
    session = models.SessionLocal()
    user = models.User(id=1, email="test@example.com", hashed_password="pw")
    session.add(user)
    session.commit()

    red = Image.new("RGB", (16, 16), (255, 0, 0))
    sha = compute_sha256(get_image_bytes(red))
    phash = compute_perceptual_hash(red)
    image_record = models.Image(id=1, user_id=1, sha256=sha, phash=phash)
    session.add(image_record)
    session.commit()

    similar = find_similar_images(red)
    assert isinstance(similar, list)
    assert any(img.id == 1 for img in similar)
