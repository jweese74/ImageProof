# tests/test_image_pipeline.py

import os
os.environ["DATABASE_URI"] = "sqlite:///:memory:"

import pytest
from io import BytesIO
from PIL import Image
from PIL import ImageDraw
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
    base = Image.new("RGB", (256, 256), (255, 0, 0))  # red background
    alt = base.copy()
    draw = ImageDraw.Draw(alt)
    draw.rectangle((10, 10, 20, 20), fill=(0, 0, 255))  # minor change

    sha_base = compute_sha256(get_image_bytes(base))
    sha_alt = compute_sha256(get_image_bytes(alt))
    assert sha_base != sha_alt

    phash_base = compute_perceptual_hash(base)
    phash_alt = compute_perceptual_hash(alt)
    similarity = phash_similarity(phash_base, phash_alt)

    assert similarity >= 0.5  # should pass with this realistic tweak


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


def test_apply_image_watermark_transparent_and_opaque():
    base = Image.new("RGBA", (16, 16), (255, 0, 0, 255))

    transparent_overlay = Image.new("RGBA", (8, 8), (0, 255, 0, 128))
    result_transparent = apply_image_watermark(base, transparent_overlay, "top-left", opacity=0.5)

    expected_layer = Image.new("RGBA", base.size, (255, 255, 255, 0))
    tmp_overlay = transparent_overlay.copy()
    tmp_overlay.putalpha(int(255 * 0.5))
    expected_layer.paste(tmp_overlay, (0, 0), tmp_overlay)
    expected_transparent = Image.alpha_composite(base, expected_layer)
    assert list(result_transparent.getdata()) == list(expected_transparent.getdata())

    opaque_overlay = Image.new("RGB", (8, 8), (0, 0, 255))
    result_opaque = apply_image_watermark(base, opaque_overlay, "top-left", opacity=1.0)

    expected_layer2 = Image.new("RGBA", base.size, (255, 255, 255, 0))
    tmp_overlay2 = opaque_overlay.convert("RGBA")
    tmp_overlay2.putalpha(int(255 * 1.0))
    expected_layer2.paste(tmp_overlay2, (0, 0), tmp_overlay2)
    expected_opaque = Image.alpha_composite(base, expected_layer2)
    assert list(result_opaque.getdata()) == list(expected_opaque.getdata())


def test_apply_overlays_limit():
    red = Image.new("RGBA", (16, 16), (255, 0, 0, 255))
    overlays = []
    for i in range(4):
        overlays.append({"type": "text", "text": str(i), "position": "center"})
    with pytest.raises(ValueError):
        apply_overlays(red, overlays)


def test_apply_overlays_order():
    base = Image.new("RGBA", (8, 8), (0, 0, 0, 255))
    blue = Image.new("RGBA", (8, 8), (0, 0, 255, 255))
    green = Image.new("RGBA", (4, 4), (0, 255, 0, 255))
    overlays = [
        {"type": "image", "image": blue, "position": "top-left", "opacity": 1.0},
        {"type": "image", "image": green, "position": "top-left", "opacity": 1.0},
    ]
    result = apply_overlays(base, overlays)
    assert result.getpixel((0, 0)) == (0, 255, 0, 255)
    assert result.getpixel((7, 7)) == (0, 0, 255, 255)


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
