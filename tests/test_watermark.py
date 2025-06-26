import numpy as np
from PIL import Image, ImageDraw, ImageFont

from app.watermark import apply_text_watermark, _calculate_position


def test_apply_text_watermark_positions():
    base = Image.new("RGBA", (32, 32), (255, 0, 0, 255))
    font = ImageFont.load_default()
    draw = ImageDraw.Draw(Image.new("RGBA", base.size))
    bbox = draw.textbbox((0, 0), "Hi", font=font)
    text_w = bbox[2] - bbox[0]
    text_h = bbox[3] - bbox[1]
    for pos in ["top-left", "top-right", "bottom-left", "bottom-right", "center"]:
        result = apply_text_watermark(base, "Hi", pos)
        diff = np.array(result) - np.array(base)
        mask = diff.any(axis=-1)
        ys, xs = np.nonzero(mask)
        assert ys.size > 0 and xs.size > 0, f"No pixels changed for {pos}"
        min_x, max_x = xs.min(), xs.max()
        min_y, max_y = ys.min(), ys.max()
        exp_x, exp_y = _calculate_position(base.size, (text_w, text_h), pos)
        assert exp_x - 2 <= min_x <= exp_x + 2
        assert exp_y - 2 <= min_y <= exp_y + 2
        assert exp_x + text_w - 2 <= max_x <= exp_x + text_w + 2
        assert exp_y + text_h - 2 <= max_y <= exp_y + text_h + 2
        assert result.getpixel((min_x, min_y)) != (255, 0, 0, 255)