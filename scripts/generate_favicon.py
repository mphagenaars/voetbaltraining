#!/usr/bin/env python3
"""Genereer een favicon (64x64 PNG) vanuit het Trainer Bobby logo.

Gebruik:
    pip3 install Pillow
    python3 scripts/generate_favicon.py

Crop het bal+swoosh gedeelte (linker 35%) en schaalt dit naar 64x64.
"""

from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
LOGO = ROOT / "public" / "images" / "logo.png"
FAVICON = ROOT / "public" / "images" / "favicon.png"


def main() -> None:
    if not LOGO.exists() or LOGO.stat().st_size == 0:
        print(f"❌ Logo niet gevonden of leeg: {LOGO}")
        return

    img = Image.open(LOGO).convert("RGBA")
    w, h = img.size
    print(f"Logo geladen: {w}×{h}")

    # Snij het bal-gedeelte (linker ~35%) uit
    ball = img.crop((0, 0, int(w * 0.35), h))

    # Maak vierkant met transparante achtergrond
    bw, bh = ball.size
    side = max(bw, bh)
    square = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    square.paste(ball, ((side - bw) // 2, (side - bh) // 2))

    # Schaal naar 64x64
    square.thumbnail((64, 64), Image.LANCZOS)
    square.save(FAVICON, "PNG")
    print(f"✅ Favicon opgeslagen: {FAVICON} ({square.size[0]}×{square.size[1]})")


if __name__ == "__main__":
    main()
