# app/watermark.py

import logging
from typing import List, Dict, Any

from PIL import Image, ImageDraw, ImageFont, ImageColor

logger: logging.Logger = logging.getLogger(__name__)


def _calculate_position(base_size: tuple[int, int],
                        overlay_size: tuple[int, int],
                        position: str) -> tuple[int, int]:
    """
    Calculate (x, y) coordinates for overlay given base and overlay sizes and a position keyword.
    """
    base_w, base_h = base_size
    overlay_w, overlay_h = overlay_size
    pos = position.lower()
    if pos == "top-left":
        return 0, 0
    if pos == "top-right":
        return base_w - overlay_w, 0
    if pos == "bottom-left":
        return 0, base_h - overlay_h
    if pos == "bottom-right":
        return base_w - overlay_w, base_h - overlay_h
    if pos == "center":
        return (base_w - overlay_w) // 2, (base_h - overlay_h) // 2
    raise ValueError(f"Invalid position: {position}")


def apply_text_watermark(image: Image.Image,
                         text: str,
                         position: str,
                         color: str = "#FFFFFF",
                         opacity: float = 0.3) -> Image.Image:
    """
    Overlay a semi-transparent text string onto the given image at the specified position.
    """
    if image.mode != "RGBA":
        base = image.convert("RGBA")
    else:
        base = image.copy()

    txt_layer = Image.new("RGBA", base.size, (255, 255, 255, 0))
    draw = ImageDraw.Draw(txt_layer)
    try:
        rgb = ImageColor.getrgb(color)
    except Exception:
        raise ValueError(f"Invalid color value: {color}")
    alpha = int(255 * opacity)
    font = ImageFont.load_default()
    bbox = draw.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]
    x, y = _calculate_position(base.size, (text_width, text_height), position)  # ← here
    draw.text((x, y), text, fill=(rgb[0], rgb[1], rgb[2], alpha), font=font)
    logger.debug("Applied text watermark '%s' at %s", text, position)
    result = Image.alpha_composite(base, txt_layer)
    return result


def apply_image_watermark(image: Image.Image,
                          overlay_img: Image.Image,
                          position: str,
                          opacity: float = 0.3) -> Image.Image:
    """
    Overlay another image onto the base image at the specified position.
    """
    if image.mode != "RGBA":
        base = image.convert("RGBA")
    else:
        base = image.copy()

    overlay = overlay_img.copy()
    if overlay.mode != "RGBA":
        overlay = overlay.convert("RGBA")
    alpha = int(255 * opacity)
    overlay.putalpha(alpha)

    layer = Image.new("RGBA", base.size, (255, 255, 255, 0))
    x, y = _calculate_position(base.size, overlay.size, position)
    layer.paste(overlay, (x, y), overlay)
    logger.debug("Applied image watermark at %s with opacity %f", position, opacity)
    result = Image.alpha_composite(base, layer)
    return result


def apply_overlays(image: Image.Image,
                   overlays: List[Dict[str, Any]]) -> Image.Image:
    """
    Apply multiple overlays (text or image) to the image in sequence.
    Raises ValueError if more than 3 overlays are provided.
    """
    if len(overlays) > 3:
        logger.error("Too many overlays provided (%d); max is 3", len(overlays))
        raise ValueError("A maximum of 3 overlays is allowed.")

    result = image
    for overlay in overlays:
        typ = overlay.get("type")
        if typ == "text":
            text = overlay.get("text", "")
            position = overlay.get("position", "center")
            color = overlay.get("color", "#FFFFFF")
            opacity = overlay.get("opacity", 0.3)
            result = apply_text_watermark(result, text, position, color, opacity)
        elif typ == "image":
            overlay_img = overlay.get("image")
            position = overlay.get("position", "center")
            opacity = overlay.get("opacity", 0.3)
            result = apply_image_watermark(result, overlay_img, position, opacity)
        else:
            logger.error("Unknown overlay type: %s", typ)
            raise ValueError(f"Unknown overlay type: {typ}")
    return result
