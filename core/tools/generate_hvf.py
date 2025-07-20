#!/usr/bin/env python3
"""
generate_hvf.py — Produce a high-fidelity visual (HFV) fingerprint for PixlKey.

The resulting 64-hex-character digest is a SHA-256 hash of a JSON payload
containing:
    • image size (WxH)
    • file size (bytes)
    • mean and standard-deviation per RGB channel
    • SHA-256 of the 768-bin RGB histogram
    • 8-bit XOR checksum over all pixel bytes
    • perceptual pHash (64-bit)
    • certificate timestamp (ISO-8601)
All of that is optionally salted with a server-side “pepper”, so only PixlKey
can generate a *matching* digest.

Usage:
    python3 generate_hvf.py <image_path> --ts "2025-07-20T19:30:00Z" \
        [--pepper "YOUR_SECRET"]

Exit codes:
    0  success
    1  argument or processing error
"""
import sys, os, json, hashlib, argparse, datetime as dt
from PIL import Image, UnidentifiedImageError
import numpy as np
import imagehash


# ──────────────────────────────── CLI parsing ──────────────────────────────── #
parser = argparse.ArgumentParser(description="Generate PixlKey HFV digest.")
parser.add_argument("image_path", help="Path to the image file")
parser.add_argument(
    "--ts",
    required=True,
    metavar="ISO_TIMESTAMP",
    help="Certificate timestamp (ISO-8601, e.g. 2025-07-20T19:30:00Z)",
)
parser.add_argument(
    "--pepper",
    default="",
    metavar="SECRET",
    help="Optional server-side pepper to salt the digest",
)
args = parser.parse_args()

file_path = args.image_path
cert_ts = args.ts
pepper = args.pepper

# Quick sanity-check on timestamp
try:
    dt.datetime.fromisoformat(cert_ts.replace("Z", "+00:00"))
except ValueError:
    sys.stderr.write("Invalid --ts value; must be ISO-8601.\n")
    sys.exit(1)

# Supported formats
if os.path.splitext(file_path)[1].lower() not in (
    ".png",
    ".jpg",
    ".jpeg",
    ".gif",
    ".webp",
):
    sys.stderr.write("Unsupported file type.\n")
    sys.exit(1)

# ──────────────────────────────── Image loading ────────────────────────────── #
try:
    im = Image.open(file_path)
    im.verify()  # quick corruption check
    im = Image.open(file_path).convert("RGB")  # reopen for processing
except (IOError, UnidentifiedImageError, Exception):
    sys.stderr.write("Cannot open or decode image.\n")
    sys.exit(1)

# ─────────────────────── Compute statistics & digests ─────────────────────── #
width, height = im.width, im.height
image_size = f"{width}x{height}"
file_size = os.path.getsize(file_path)

# NumPy array for pixel-level math
arr = np.asarray(im, dtype=np.uint8)

# Mean & standard deviation per channel
mean_color = [int(x) for x in arr.mean(axis=(0, 1))]
std_color = [round(float(x), 2) for x in arr.std(axis=(0, 1))]

# Fast XOR checksum over all bytes (acts as lightweight noise detector)
xor_checksum = f"{int(np.bitwise_xor.reduce(arr.flat)) & 0xFF:02x}"

# 768-bin histogram (256 per channel → deterministic order)
histogram_digest = hashlib.sha256(
    json.dumps(im.histogram(), separators=(",", ":")).encode("utf-8")
).hexdigest()

# 64-bit perceptual hash (pHash)
perceptual_hash = str(imagehash.phash(im))

# ───────────────────────────── Assemble payload ───────────────────────────── #
fingerprint = {
    "size": image_size,
    "filesize": file_size,
    "mean_color": mean_color,
    "std_color": std_color,
    "histogram_digest": histogram_digest,
    "perceptual_hash": perceptual_hash,
    "xor_checksum": xor_checksum,
    "timestamp": cert_ts,
}

# Canonical JSON string + pepper, then SHA-256
payload = json.dumps(fingerprint, sort_keys=True, separators=(",", ":")) + "|" + pepper
hvf_hash = hashlib.sha256(payload.encode()).hexdigest()

print(hvf_hash)
sys.exit(0)