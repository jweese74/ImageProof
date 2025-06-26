import pytest
from io import BytesIO
from PIL import Image, ImageDraw

from app.image_processing import compute_sha256, compute_perceptual_hash


def get_image_bytes(img: Image.Image) -> bytes:
    with BytesIO() as output:
        img.save(output, format="PNG")
        return output.getvalue()


def test_compute_sha256_consistency():
    img1 = Image.new("RGB", (32, 32), (123, 50, 200))
    img2 = Image.new("RGB", (32, 32), (123, 50, 200))
    sha1 = compute_sha256(get_image_bytes(img1))
    sha2 = compute_sha256(get_image_bytes(img2))
    assert sha1 == sha2


def test_compute_sha256_uniqueness():
    img_red = Image.new("RGB", (32, 32), (255, 0, 0))
    img_blue = Image.new("RGB", (32, 32), (0, 0, 255))
    sha_red = compute_sha256(get_image_bytes(img_red))
    sha_blue = compute_sha256(get_image_bytes(img_blue))
    assert sha_red != sha_blue


def test_compute_perceptual_hash_consistency():
    img1 = Image.new("RGB", (32, 32), (10, 100, 20))
    img2 = Image.new("RGB", (32, 32), (10, 100, 20))
    phash1 = compute_perceptual_hash(img1)
    phash2 = compute_perceptual_hash(img2)
    assert phash1 == phash2


def test_compute_perceptual_hash_uniqueness():
    base = Image.new("RGB", (32, 32), (255, 0, 0))
    alt = base.copy()
    draw = ImageDraw.Draw(alt)
    draw.rectangle((8, 8, 24, 24), fill=(0, 0, 255))
    phash_base = compute_perceptual_hash(base)
    phash_alt = compute_perceptual_hash(alt)
    assert phash_base != phash_alt