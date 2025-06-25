# app/image_processing.py

import logging
import hashlib
from typing import List, Union

from PIL import Image as PILImage
import imagehash
import numpy as np
from sqlalchemy import text

from app.models import SessionLocal, Image as ImageModel

logger: logging.Logger = logging.getLogger(__name__)


def compute_sha256(image_bytes: bytes) -> str:
    """Return the SHA-256 hash of the given image bytes."""
    hasher = hashlib.sha256()
    hasher.update(image_bytes)
    digest = hasher.hexdigest()
    logger.debug("Computed SHA-256: %s", digest)
    return digest


def compute_perceptual_hash(image: PILImage) -> str:
    """Compute and return the perceptual hash (pHash) of the image as a hex string."""
    phash = imagehash.phash(image)
    phash_str = str(phash)
    logger.debug("Computed perceptual hash: %s", phash_str)
    return phash_str


def extract_orb_features(image: PILImage) -> np.ndarray:
    """
    Extract ORB feature descriptors from the given image.
    Returns a numpy array of descriptors.
    Raises ImportError if OpenCV is not installed.
    """
    try:
        import cv2  # type: ignore[import]
    except ImportError:
        logger.error("OpenCV (cv2) is not installed; cannot extract ORB features.")
        raise

    rgb = image.convert("RGB")
    image_np = np.array(rgb)
    gray = cv2.cvtColor(image_np, cv2.COLOR_RGB2GRAY)
    orb = cv2.ORB_create()
    keypoints, descriptors = orb.detectAndCompute(gray, None)
    if descriptors is None:
        logger.debug("No ORB descriptors found.")
        return np.array([], dtype=np.uint8)
    logger.debug("Extracted ORB descriptors of shape %s", descriptors.shape)
    return descriptors


def phash_similarity(phash1: str, phash2: str) -> float:
    """
    Compute similarity between two perceptual hash hex strings.
    Returns a value between 0 and 1 (1 means identical).
    """
    try:
        int1 = int(phash1, 16)
        int2 = int(phash2, 16)
    except ValueError:
        logger.error("Invalid perceptual hash values: %s, %s", phash1, phash2)
        raise
    xor = int1 ^ int2
    dist = xor.bit_count()
    bits = len(phash1) * 4
    similarity = 1.0 - dist / bits if bits > 0 else 0.0
    logger.debug("Perceptual hash distance: %d (of %d bits); similarity: %f", dist, bits, similarity)
    return similarity


def find_similar_images(image_or_phash: Union[PILImage, str]) -> List[ImageModel]:
    """
    Find images in the database with similar perceptual hashes.
    Uses BIT_COUNT via raw SQL if using MySQL; otherwise falls back to Python.
    Returns a list of ImageModel instances.
    """
    if isinstance(image_or_phash, str):
        phash_value = image_or_phash
    else:
        phash_value = compute_perceptual_hash(image_or_phash)

    session = SessionLocal()
    try:
        bind = session.get_bind()  # type: ignore[attr-defined]
        dialect = bind.dialect.name
    except Exception:
        dialect = ""
    results: List[ImageModel] = []

    if dialect in ("mysql", "mariadb"):
        sql = text(
            "SELECT id, BIT_COUNT(CONV(phash, 16, 10) ^ CONV(:phash, 16, 10)) AS distance "
            "FROM images ORDER BY distance ASC"
        )
        try:
            query = session.execute(sql, {"phash": phash_value})
            ids = [row[0] for row in query.fetchall()]
            logger.debug("Similar image IDs (SQL): %s", ids)
            if ids:
                results = session.query(ImageModel).filter(ImageModel.id.in_(ids)).all()
        except Exception as e:
            logger.error("Error executing similarity query: %s", e)
    else:
        # Fallback for other databases (e.g., SQLite)
        images = session.query(ImageModel).all()
        scored = []
        for img in images:
            try:
                sim = phash_similarity(phash_value, img.phash)
            except Exception as e:
                logger.error("Error computing similarity for ID %s: %s", img.id, e)
                continue
            scored.append((sim, img))
        scored.sort(key=lambda x: x[0], reverse=True)
        results = [img for _, img in scored]
        logger.debug("Similar images found (fallback): %s", [img.id for img in results])

    return results
