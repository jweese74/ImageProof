#!/usr/bin/env python3
import sys, os
import json, hashlib
from PIL import Image, UnidentifiedImageError
import numpy as np
import imagehash

def print_usage():
    print("Usage: generate_hvf.py [--help] <image_path>")
    sys.exit(0)

# Argument parsing
if len(sys.argv) != 2:
    if len(sys.argv) == 2 and sys.argv[1] in ("--help", "-h"):
        print_usage()
    else:
        sys.exit(1)

arg = sys.argv[1]
if arg in ("--help", "-h"):
    print_usage()

# File extension check (supported formats)
file_path = arg
ext = os.path.splitext(file_path)[1].lower()
if ext not in (".png", ".jpg", ".jpeg", ".gif", ".webp"):
    sys.exit(1)

try:
    # Open and verify image
    im = Image.open(file_path)
    im.verify()  # raises if image is broken
except (IOError, UnidentifiedImageError, Exception):
    sys.exit(1)

# Re-open for processing (verify() requires reopen)
try:
    im = Image.open(file_path)
    im = im.convert("RGB")  # ensure RGB format:contentReference[oaicite:17]{index=17}
except Exception:
    sys.exit(1)

# Compute image_size
width, height = im.width, im.height  # Pillow attributes:contentReference[oaicite:18]{index=18}
image_size = f"{width}x{height}"

# Compute mean_color using numpy
try:
    arr = np.array(im)
    # Calculate mean per channel across all pixels:contentReference[oaicite:19]{index=19}
    mean = np.mean(arr, axis=(0,1))
    mean_color = [int(mean[0]), int(mean[1]), int(mean[2])]
except Exception:
    sys.exit(1)

# Compute histogram digest
try:
    hist = im.histogram()  # returns list of counts:contentReference[oaicite:20]{index=20}
    # Serialize histogram list deterministically and hash
    hist_json = json.dumps(hist, separators=(',',':'))
    histogram_digest = hashlib.sha256(hist_json.encode('utf-8')).hexdigest()
except Exception:
    sys.exit(1)

# Compute perceptual hash
try:
    phash = imagehash.phash(im)         # perceptual hash object:contentReference[oaicite:21]{index=21}
    perceptual_hash = str(phash)        # hex string representation:contentReference[oaicite:22]{index=22}
except Exception:
    sys.exit(1)

# Assemble fingerprint dictionary
fingerprint = {
    "size": image_size,
    "mean_color": mean_color,
    "histogram_digest": histogram_digest,
    "perceptual_hash": perceptual_hash
}

# Serialize to JSON and hash
try:
    json_str = json.dumps(fingerprint, sort_keys=True, separators=(',',':'))
    hvf_hash = hashlib.sha256(json_str.encode('utf-8')).hexdigest()  # final HVF
    print(hvf_hash)
    sys.exit(0)
except Exception:
    sys.exit(1)